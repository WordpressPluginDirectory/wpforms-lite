<?php

namespace WPForms\Analytics;

use WP_Post;

/**
 * Ingest snapshots from the frontend tracker.
 *
 * Handles two ingestion paths: the wpforms_analytics_snapshot AJAX action
 * (tab_switch beacons from navigator.sendBeacon) and the wpforms_process_complete
 * hook (submission snapshots piggybacked onto wpforms_submit).
 *
 * @since 2.0.0
 */
class Process {

	/**
	 * Upper bound for SMALLINT UNSIGNED counter columns (focus/click/input/errors).
	 *
	 * Clamps a tampered client so it cannot overflow the snapshot_fields write.
	 *
	 * @since 2.0.0
	 */
	private const MAX_SMALLINT = 65535;

	/**
	 * Upper bound for the INT(10) UNSIGNED duration_ms column.
	 *
	 * @since 2.0.0
	 */
	private const MAX_UNSIGNED_INT = 4294967295;

	/**
	 * Maximum stored length of a session ID (matches the VARCHAR(64) column).
	 *
	 * @since 2.0.0
	 */
	private const SESSION_ID_MAX_LENGTH = 64;

	/**
	 * Maximum stored length of a subfield key (matches the VARCHAR(64) column).
	 *
	 * @since 2.0.0
	 */
	private const SUBFIELD_KEY_MAX_LENGTH = 64;

	/**
	 * Maximum accepted byte length of the raw snapshot payload.
	 *
	 * The endpoint is `nopriv`, so an anonymous client could otherwise POST an
	 * arbitrarily large body into the LONGTEXT payload column. 64 KB is generous
	 * for the largest realistic form snapshot while bounding the write. Filterable
	 * via `wpforms_analytics_process_max_payload_bytes`.
	 *
	 * @since 2.0.0
	 */
	private const MAX_PAYLOAD_BYTES = 65535;

	/**
	 * Maximum number of snapshot writes allowed per form+IP within the throttle window.
	 *
	 * Filterable via `wpforms_analytics_process_register_snapshot_throttle_max`.
	 *
	 * @since 2.0.0
	 */
	private const SNAPSHOT_THROTTLE_MAX = 60;

	/**
	 * Throttle window, in seconds, over which SNAPSHOT_THROTTLE_MAX applies.
	 *
	 * @since 2.0.0
	 */
	private const SNAPSHOT_THROTTLE_WINDOW = 60;

	/**
	 * Start the engine.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 2.0.0
	 */
	private function hooks(): void {

		add_action( 'wp_ajax_wpforms_analytics_snapshot', [ $this, 'handle_snapshot_ajax' ] );
		add_action( 'wp_ajax_nopriv_wpforms_analytics_snapshot', [ $this, 'handle_snapshot_ajax' ] );

		add_action( 'wpforms_process_complete', [ $this, 'handle_submission' ], 10, 4 );
	}

	/**
	 * Handle tab_switch beacons sent via navigator.sendBeacon().
	 *
	 * @since 2.0.0
	 */
	public function handle_snapshot_ajax(): void {

		if ( ! check_ajax_referer( 'wpforms', 'nonce', false ) ) {
			wp_send_json_error();
		}

		// Skip site staff (Author/Editor/Admin) so their own activity never
		// pollutes visitor analytics. Resolved fresh per request, so this holds
		// even when the page was served from a full-page cache.
		if ( ! Analytics::should_track_user() ) {
			wp_send_json_error();
		}

		$input = $this->read_snapshot_input();

		if ( ! $this->is_valid_snapshot_input( $input ) ) {
			wp_send_json_error();
		}

		// Reject oversized payloads before any form lookup or DB write — the
		// endpoint is anonymous (`nopriv`), so the body length is untrusted.
		if ( ! $this->is_payload_within_limit( $input['payload_raw'] ) ) {
			wp_send_json_error();
		}

		$form_data = $this->resolve_published_form_data( $input['form_id'] );

		if ( $form_data === null ) {
			wp_send_json_error();
		}

		$valid_ids = $this->get_tracked_field_ids( $form_data );

		$parsed = $this->parse_inner_payload( $input['payload_raw'], $valid_ids );

		if ( $parsed === null ) {
			wp_send_json_error();
		}

		// Per-form throttle: cap snapshot writes from a single form+IP in a short
		// window so an anonymous client cannot flood the table. Keyed on form_id (not
		// session_id, which the client rotates freely and would bypass the cap) plus
		// IP. Fails open on any cache error so legitimate tracking is never blocked.
		if ( ! $this->register_snapshot_throttle( $input['form_id'] ) ) {
			wp_send_json_error();
		}

		$snapshot_id = $this->save_snapshot(
			$input['form_id'],
			$input['session_id'],
			1,
			$input['form_visible'],
			$input['payload_raw'],
			$parsed['page'],
			$parsed['field_rows']
		);

		if ( $snapshot_id === 0 ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Resolve the decoded form config for a published form.
	 *
	 * Fetches the form as a WP_Post to assert 'publish' status, then decodes its
	 * post_content into the config array. Returns null when the form is missing,
	 * unpublished, or its config cannot be decoded — an unreadable config can't be
	 * field-validated, so the caller rejects the snapshot rather than recording a
	 * fieldless view.
	 *
	 * @since 2.0.0
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array|null Decoded form config, or null when the form is unusable.
	 */
	private function resolve_published_form_data( int $form_id ): ?array {

		$form = wpforms()->obj( 'form' )->get( $form_id, [ 'cap' => false ] );

		if ( ! $form instanceof WP_Post || $form->post_status !== 'publish' ) {
			return null;
		}

		$form_data = wpforms_decode( $form->post_content ?? '' );

		// An unreadable or empty config can't be field-validated; treat it like a
		// missing form so the caller rejects instead of recording a fieldless view.
		if ( ! is_array( $form_data ) || $form_data === [] ) {
			return null;
		}

		return $form_data;
	}

	/**
	 * Read and sanitize the snapshot beacon fields from $_POST.
	 *
	 * @since 2.0.0
	 *
	 * @return array Sanitized inputs: form_id, session_id, trigger, form_visible, payload_raw.
	 */
	private function read_snapshot_input(): array {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post = $_POST;

		$session_id = $this->clip_session_id( wp_unslash( $this->to_scalar_string( $post['session_id'] ?? '' ) ) );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$payload_raw = wp_unslash( $this->to_scalar_string( $post['payload'] ?? '' ) );

		// Auto-detect base64: JSON payloads start with '{', base64 does not.
		if ( $payload_raw !== '' && isset( $payload_raw[0] ) && $payload_raw[0] !== '{' ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$decoded = base64_decode( $payload_raw, true );

			if ( $decoded !== false ) {
				$payload_raw = $decoded;
			}
		}

		// For the three integer inputs we can't simply cast and keep the value: (int) and absint()
		// coerce non-empty arrays to 1, which would let shapes like form_id[]=x slip past validation.
		// scalar_int()/scalar_absint() guard with is_scalar first; non-scalars resolve to values
		// that fail downstream checks (form_id === 0, trigger !== 1, form_visible not in [0,1]).
		return [
			'form_id'      => $this->scalar_absint( $post['form_id'] ?? null ),
			'session_id'   => $session_id,
			'trigger'      => $this->scalar_int( $post['trigger'] ?? null, 0 ),
			'form_visible' => $this->scalar_int( $post['form_visible'] ?? null, -1 ),
			'payload_raw'  => $payload_raw,
		];
	}

	/**
	 * Validate the sanitized snapshot input envelope (pre-payload).
	 *
	 * Payload shape is checked separately by parse_inner_payload().
	 *
	 * @since 2.0.0
	 *
	 * @param array $input Sanitized input from read_snapshot_input().
	 *
	 * @return bool True when form_id, session_id, trigger and form_visible are shaped correctly.
	 */
	private function is_valid_snapshot_input( array $input ): bool {

		if ( $input['form_id'] === 0 || $input['session_id'] === '' ) {
			return false;
		}

		return $input['trigger'] === 1 && in_array( $input['form_visible'], [ 0, 1 ], true );
	}

	/**
	 * Whether the raw payload is within the accepted byte limit.
	 *
	 * @since 2.0.0
	 *
	 * @param string $payload_raw Raw JSON payload string.
	 *
	 * @return bool True when the payload is small enough to accept.
	 */
	private function is_payload_within_limit( string $payload_raw ): bool {

		/**
		 * Filters the maximum accepted byte length of a snapshot payload.
		 *
		 * @since 2.0.0
		 *
		 * @param int $max_bytes Maximum payload size in bytes.
		 */
		$max_bytes = (int) apply_filters( 'wpforms_analytics_process_max_payload_bytes', self::MAX_PAYLOAD_BYTES );

		return strlen( $payload_raw ) <= $max_bytes;
	}

	/**
	 * Register a snapshot write against the per-form+IP throttle.
	 *
	 * Counts writes in a short rolling window using a transient. Returns false
	 * once the cap is exceeded so the caller can bail. Fails OPEN: if the cache
	 * layer cannot store the counter, the write is allowed rather than blocked,
	 * so a broken object cache never silently drops legitimate tracking.
	 *
	 * Keyed on form_id + IP rather than session_id: the client generates a fresh
	 * session_id per page-load and rotates it on flush, so a session-keyed cap is
	 * trivially bypassed. form_id + IP caps a flooding source while keeping
	 * separate buckets per form to limit shared-NAT false positives.
	 *
	 * @since 2.0.0
	 *
	 * @param int $form_id Form ID the snapshot belongs to.
	 *
	 * @return bool True when the write is allowed, false when the cap is exceeded.
	 */
	private function register_snapshot_throttle( int $form_id ): bool {

		/**
		 * Filters the maximum number of snapshot writes per form+IP per window.
		 *
		 * @since 2.0.0
		 *
		 * @param int $max Maximum writes allowed in the throttle window.
		 */
		$max = (int) apply_filters( 'wpforms_analytics_process_register_snapshot_throttle_max', self::SNAPSHOT_THROTTLE_MAX );

		// A non-positive cap disables throttling entirely.
		if ( $max <= 0 ) {
			return true;
		}

		$key   = 'wpforms_an_thr_' . md5( $form_id . '|' . wpforms_get_ip() ); // NOSONAR.
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return false;
		}

		// Re-set the window on every write — a steadily active session keeps the
		// counter alive, which is the behaviour we want for flood protection.
		set_transient( $key, $count + 1, self::SNAPSHOT_THROTTLE_WINDOW );

		return true;
	}

	/**
	 * Parse and shape the inner JSON payload shared by both ingestion paths.
	 *
	 * The tab_switch beacon and the submission envelope both carry this same
	 * inner shape: { page: ?int, fields: [ ... ] }. Centralizing the decode,
	 * structure check, page coercion and field-row build prevents the two
	 * handlers from drifting.
	 *
	 * @since 2.0.0
	 *
	 * @param string $payload_raw Raw JSON string from either the AJAX payload or the submission envelope.
	 * @param array  $valid_ids   Field IDs eligible for tracking. Rows for other IDs are dropped.
	 *
	 * @return array|null [ 'page' => int|null, 'field_rows' => array ] on success, null when malformed.
	 */
	private function parse_inner_payload( string $payload_raw, array $valid_ids ): ?array {

		$payload = json_decode( $payload_raw, true );

		if ( ! is_array( $payload ) || ! isset( $payload['fields'] ) || ! is_array( $payload['fields'] ) ) {
			return null;
		}

		// (int) not absint() so negatives stay negative and fail the > 0 gate;
		// absint() would flip '-5' to 5 and record against the wrong 1-indexed page.
		// Clamp the upper bound to the page_number column's TINYINT UNSIGNED max
		// (255) so a garbage payload cannot exceed the range and make strict-mode
		// MySQL reject the whole snapshot row.
		$page_raw = $payload['page'] ?? null;
		$page_int = is_numeric( $page_raw ) ? (int) $page_raw : 0;
		$page     = $page_int > 0 ? min( $page_int, 255 ) : null;

		return [
			'page'       => $page,
			'field_rows' => $this->build_field_rows( $payload['fields'], $valid_ids ),
		];
	}

	/**
	 * Save a form-submission snapshot (trigger_type = 2).
	 *
	 * Reads the envelope from $_POST['wpforms']['analytics'], which the JS tracker
	 * appends to the existing wpforms_submit AJAX request. The envelope is JSON-encoded
	 * once (its payload is a nested object, not a pre-stringified string) so the form
	 * POST carries no backslash-escaped quotes for WAF anomaly rules to score. When the
	 * key is absent (CLI/REST/internal caller), we early-return without logging.
	 *
	 * @since 2.0.0
	 *
	 * @param array $fields    Sanitized submission fields (unused here).
	 * @param array $entry     Raw entry data (unused here).
	 * @param array $form_data Form config array.
	 * @param int   $entry_id  Entry ID (unused here).
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function handle_submission( array $fields, array $entry, array $form_data, int $entry_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		// Skip site staff (Author/Editor/Admin) so their own submissions never
		// pollute visitor analytics. This is the authoritative, cache-immune gate.
		if ( ! Analytics::should_track_user() ) {
			return;
		}

		$envelope = $this->extract_submission_envelope();

		if ( $envelope === null ) {
			return;
		}

		$identity = $this->resolve_submission_identity( $envelope, $form_data );

		if ( $identity === null ) {
			return;
		}

		// The submission envelope carries `payload` as a nested object so the form
		// POST contains no backslash-escaped quotes for WAF anomaly rules to score.
		// Re-encode it to the JSON string that parse_inner_payload() and the stored
		// `payload` column both expect. A pre-stringified payload from an older cached
		// analytics.js is still accepted as-is, so upgrades stay safe.
		$payload_val = $envelope['payload'] ?? '';
		$payload_raw = is_array( $payload_val )
			? (string) wp_json_encode( $payload_val )
			: $this->to_scalar_string( $payload_val );
		$valid_ids   = $this->get_tracked_field_ids( $form_data );
		$parsed      = $this->parse_inner_payload( $payload_raw, $valid_ids );

		if ( $parsed === null ) {
			return;
		}

		$form_visible = $this->normalize_submission_visible( $envelope['form_visible'] ?? 1 );

		$this->save_snapshot(
			$identity['form_id'],
			$identity['session_id'],
			2,
			$form_visible,
			$payload_raw,
			$parsed['page'],
			$parsed['field_rows']
		);
	}

	/**
	 * Resolve and validate the form_id + session_id for a submission snapshot.
	 *
	 * The envelope was already unslashed when decoded in
	 * extract_submission_envelope(), so session_id is clipped here without a
	 * second wp_unslash(). Returns null when either value is empty.
	 *
	 * @since 2.0.0
	 *
	 * @param array $envelope  Decoded submission envelope.
	 * @param array $form_data Form config array.
	 *
	 * @return array|null [ 'form_id' => int, 'session_id' => string ], or null when invalid.
	 */
	private function resolve_submission_identity( array $envelope, array $form_data ): ?array {

		$form_id    = absint( $form_data['id'] ?? 0 );
		$session_id = $this->clip_session_id( $this->to_scalar_string( $envelope['session_id'] ?? '' ) );

		if ( $form_id === 0 || $session_id === '' ) {
			return null;
		}

		return [
			'form_id'    => $form_id,
			'session_id' => $session_id,
		];
	}

	/**
	 * Normalize the submission envelope's form_visible flag to 0 or 1.
	 *
	 * The is_scalar guard matters because a non-empty array would otherwise
	 * coerce to 1 via (int), silently flipping a malformed payload to "visible".
	 * When the envelope's form_visible isn't a scalar — or isn't one of 0/1 —
	 * fall back to 1 (submissions are always on a visible form by construction).
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $raw Raw form_visible value from the decoded envelope.
	 *
	 * @return int Either 0 or 1.
	 */
	private function normalize_submission_visible( $raw ): int {

		$form_visible = $this->scalar_int( $raw, 1 );

		return in_array( $form_visible, [ 0, 1 ], true ) ? $form_visible : 1;
	}

	/**
	 * Extract and decode the analytics envelope piggy-backed on wpforms_submit.
	 *
	 * The upstream wpforms_submit handler has already validated the submission
	 * nonce before wpforms_process_complete fires, so we do not re-check it here.
	 *
	 * @since 2.0.0
	 *
	 * @return array|null Decoded envelope array, or null when missing/invalid.
	 */
	private function extract_submission_envelope(): ?array {

		// We only check array shape here; is_array() means no unslash/sanitize needed yet.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$wpforms_post = $_POST['wpforms'] ?? null;

		if ( ! is_array( $wpforms_post ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$raw = $this->to_scalar_string( $wpforms_post['analytics'] ?? '' );

		if ( $raw === '' ) {
			return null;
		}

		$unslashed = wp_unslash( $raw );

		// Auto-detect encoding: JSON starts with '{', base64 does not.
		if ( isset( $unslashed[0] ) && $unslashed[0] !== '{' ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$unslashed = base64_decode( $unslashed, true );

			if ( $unslashed === false ) {
				return null;
			}
		}

		$envelope = json_decode( $unslashed, true );

		return is_array( $envelope ) ? $envelope : null;
	}

	/**
	 * Persist a snapshot header and, on Pro, its field rows.
	 *
	 * @since 2.0.0
	 *
	 * @param int      $form_id      Form ID.
	 * @param string   $session_id   Sanitized session ID.
	 * @param int      $trigger_type Trigger type: 1 = tab_switch, 2 = form_submission.
	 * @param int      $form_visible Visibility flag: 0 or 1.
	 * @param string   $payload_raw  Raw JSON payload string.
	 * @param int|null $page         Page number or null.
	 * @param array    $fields       Normalized field rows.
	 *
	 * @return int Snapshot ID, or 0 on failure.
	 */
	private function save_snapshot(
		int $form_id,
		string $session_id,
		int $trigger_type,
		int $form_visible,
		string $payload_raw,
		?int $page,
		array $fields
	): int {

		$db = wpforms()->obj( 'analytics_db' );

		if ( ! $db ) {
			return 0;
		}

		$snapshot_id = (int) $db->save(
			[
				'form_id'      => $form_id,
				'session_id'   => $session_id,
				'trigger_type' => $trigger_type,
				'page_number'  => $page,
				'form_visible' => $form_visible,
				'payload'      => $payload_raw,
				'occurred_at'  => current_time( 'mysql' ),
				'processed'    => 0,
			]
		);

		if ( $snapshot_id === 0 ) {
			wpforms_log(
				'Analytics snapshot write failed',
				[
					'form_id' => $form_id,
					'trigger' => $trigger_type,
				],
				[
					'type'  => [ 'error' ],
					'force' => true,
				]
			);

			return 0;
		}

		if ( ! empty( $fields ) && method_exists( $db, 'save_fields' ) ) {
			$db->save_fields( $snapshot_id, $fields );
		}

		return $snapshot_id;
	}

	/**
	 * Build normalized rows for analytics_snapshot_fields from the raw payload.
	 *
	 * Invalid entries (missing or non-numeric id) are skipped. Counts are
	 * clamped to column limits so a broken client cannot overflow writes.
	 *
	 * @since 2.0.0
	 *
	 * @param array $fields    Raw fields array from the decoded payload.
	 * @param array $valid_ids Field IDs eligible for tracking. Rows for other IDs are dropped.
	 *
	 * @return array List of sanitized row arrays ready for DB::save_fields().
	 */
	private function build_field_rows( array $fields, array $valid_ids ): array {

		$out = [];

		foreach ( $fields as $field ) {

			$row = $this->build_field_row( $field, $valid_ids );

			if ( $row !== null ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Normalize a single raw field entry into a sanitized row.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $field     Raw field entry from the decoded payload.
	 * @param array $valid_ids Field IDs eligible for tracking. Rows for other IDs are dropped.
	 *
	 * @return array|null Sanitized row, or null when the entry is invalid.
	 */
	private function build_field_row( $field, array $valid_ids ): ?array {

		if ( ! is_array( $field ) ) {
			return null;
		}

		if ( ! isset( $field['id'] ) || ! is_numeric( $field['id'] ) ) {
			return null;
		}

		$id = absint( $field['id'] );

		// Field ID 0 is valid — it is the first field added to a blank form
		// (Form::next_field_id() returns 0 for it). $valid_ids, derived from
		// form_data['fields'] keys, is the authority on which IDs to keep.
		if ( ! in_array( $id, $valid_ids, true ) ) {
			return null;
		}

		return $this->build_field_metrics( $id, $field );
	}

	/**
	 * Assemble the sanitized metric row for an already-validated field entry.
	 *
	 * Counts are clamped to their column limits so a broken client cannot
	 * overflow the write. duration_ms stays null when absent — null (never
	 * interacted) is semantically distinct from 0 (interacted, took 0 ms).
	 *
	 * @since 2.0.0
	 *
	 * @param int   $id    Validated field ID.
	 * @param array $field Raw field entry from the decoded payload.
	 *
	 * @return array Sanitized row ready for DB::save_fields().
	 */
	private function build_field_metrics( int $id, array $field ): array {

		return [
			'field_id'      => $id,
			'subfield_key'  => $this->sanitize_subfield_key( $field['subKey'] ?? '' ),
			'was_displayed' => empty( $field['displayed'] ) ? 0 : 1,
			'focus_count'   => $this->clamp_count( $field, 'focusCount', self::MAX_SMALLINT ),
			'click_count'   => $this->clamp_count( $field, 'clickCount', self::MAX_SMALLINT ),
			'input_count'   => $this->clamp_count( $field, 'inputCount', self::MAX_SMALLINT ),
			'errors'        => $this->clamp_count( $field, 'errors', self::MAX_SMALLINT ),
			'duration_ms'   => $this->clamp_nullable_duration( $field ),
		];
	}

	/**
	 * Build the list of field IDs eligible for analytics tracking.
	 *
	 * By default, this is every field ID present in form_data['fields']. Field
	 * IDs outside this list are dropped before reaching analytics_snapshot_fields:
	 *  - The AntiSpam v3 honeypot field (assigned an ID that is deliberately
	 *    absent from form_data['fields'] — see AntiSpam::get_honeypot_field_id).
	 *  - Any other phantom field IDs a tampered client might inject.
	 *
	 * @since 2.0.0
	 *
	 * @param array $form_data Form data array.
	 *
	 * @return array Sanitized list of int field IDs.
	 */
	private function get_tracked_field_ids( array $form_data ): array {

		$valid_ids = isset( $form_data['fields'] ) && is_array( $form_data['fields'] )
			? array_keys( $form_data['fields'] )
			: [];

		/**
		 * Filters the list of field IDs eligible for analytics tracking.
		 *
		 * Default is every field ID present in form_data['fields']. The
		 * AntiSpam v3 honeypot is naturally excluded because it picks an
		 * ID that is not in form_data['fields'].
		 *
		 * @since 2.0.0
		 *
		 * @param array $valid_ids Field IDs eligible for tracking.
		 * @param array $form_data Form data array.
		 */
		$valid_ids = (array) apply_filters( 'wpforms_analytics_process_get_tracked_field_ids', $valid_ids, $form_data );

		return array_map( 'absint', $valid_ids );
	}

	/**
	 * Coerce a request value to a string, returning '' for non-scalar inputs.
	 *
	 * Raw request members ($_POST values and decoded envelope members) can
	 * arrive as arrays or objects. A direct (string) cast on those would
	 * emit an "Array to string conversion" warning that pollutes the JSON
	 * response body on the AJAX path. Returning '' for non-scalars feeds
	 * cleanly into the downstream validation (empty session_id / malformed
	 * JSON payload both trigger silent rejection).
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Raw value from $_POST or a decoded JSON envelope.
	 *
	 * @return string The value cast to string, or '' when the input isn't scalar.
	 */
	private function to_scalar_string( $value ): string {

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Coerce a request value to an int, returning a default for non-scalars.
	 *
	 * A direct (int) cast on a non-empty array would yield 1, silently letting
	 * shapes like form_visible[]=x slip past the downstream 0/1 validation.
	 * Guarding with is_scalar keeps malformed values mapped to a value that
	 * fails those checks (the supplied default).
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value    Raw value from $_POST or a decoded JSON envelope.
	 * @param int   $fallback Value returned when $value isn't scalar.
	 *
	 * @return int The value cast to int, or the fallback when not scalar.
	 */
	private function scalar_int( $value, int $fallback ): int {

		return is_scalar( $value ) ? (int) $value : $fallback;
	}

	/**
	 * Coerce a request value to a non-negative int, 0 for non-scalars.
	 *
	 * This is the absint() variant of scalar_int(): the is_scalar guard prevents
	 * a non-empty array from coercing to 1 and slipping a shape like form_id[]=x
	 * past the form_id === 0 gate.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Raw value from $_POST or a decoded JSON envelope.
	 *
	 * @return int The value cast via absint(), or 0 when not scalar.
	 */
	private function scalar_absint( $value ): int {

		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Sanitize and clip a session ID to its stored column length.
	 *
	 * Callers pass an already string-coerced value; unslashing (where needed)
	 * happens at the call site because the two ingestion paths differ — the
	 * AJAX path unslashes raw $_POST, the submission path receives an envelope
	 * already unslashed at decode time.
	 *
	 * @since 2.0.0
	 *
	 * @param string $value String-coerced raw session ID.
	 *
	 * @return string Sanitized session ID, at most SESSION_ID_MAX_LENGTH chars.
	 */
	private function clip_session_id( string $value ): string {

		return substr( sanitize_text_field( $value ), 0, self::SESSION_ID_MAX_LENGTH );
	}

	/**
	 * Sanitize a subfield key to the analytics column contract.
	 *
	 * Keys come from the input name bracket segment (e.g. 'first', 'postal',
	 * a Likert row slug). sanitize_key() bounds the charset; the substr bounds
	 * it to the VARCHAR(64) column so a tampered client cannot overflow.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Raw subKey from the decoded payload.
	 *
	 * @return string Sanitized subfield key, or '' when absent/invalid.
	 */
	private function sanitize_subfield_key( $value ): string {

		return substr( sanitize_key( $this->to_scalar_string( $value ) ), 0, self::SUBFIELD_KEY_MAX_LENGTH );
	}

	/**
	 * Read a field counter by key and clamp it to its column's upper bound.
	 *
	 * A missing key reads as 0. Counts are clamped so a broken client cannot
	 * overflow the unsigned column.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $field Raw field entry from the decoded payload.
	 * @param string $key   Counter key to read (e.g. 'focusCount').
	 * @param int    $max   Inclusive upper bound (column limit).
	 *
	 * @return int Non-negative count, never above $max.
	 */
	private function clamp_count( array $field, string $key, int $max ): int {

		return min( $max, absint( $field[ $key ] ?? 0 ) );
	}

	/**
	 * Read and clamp the nullable durationMs value from a field entry.
	 *
	 * Returns null when absent — null (never interacted) is semantically
	 * distinct from 0 (interacted, took 0 ms).
	 *
	 * @since 2.0.0
	 *
	 * @param array $field Raw field entry from the decoded payload.
	 *
	 * @return int|null Clamped duration in ms, or null when not present.
	 */
	private function clamp_nullable_duration( array $field ): ?int {

		if ( ! isset( $field['durationMs'] ) ) {
			return null;
		}

		return min( self::MAX_UNSIGNED_INT, absint( $field['durationMs'] ) );
	}
}
