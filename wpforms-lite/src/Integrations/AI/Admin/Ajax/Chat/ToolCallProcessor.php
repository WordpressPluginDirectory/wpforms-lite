<?php

namespace WPForms\Integrations\AI\Admin\Ajax\Chat;

use WPForms\Integrations\AI\Admin\Chat\ScopeRegistry;

/**
 * Tool-call normalization, dispatch, streaming frames, and diagnostics.
 *
 * Extracted from the Chat AJAX handler to keep each class under the 20-method threshold.
 * Turn-scoped — created once per round-loop invocation with the dependencies
 * needed for the answer-round tool-call lifecycle.
 *
 * @since 2.0.0
 */
class ToolCallProcessor {

	/**
	 * Maximum tool calls accepted from a single round.
	 *
	 * Surplus calls beyond this cap are synthesized as `too_many_calls` tool errors
	 * so the model sees explicit feedback and can re-emit ignored calls in a
	 * subsequent round.
	 *
	 * @since 2.0.0
	 */
	private const MAX_TOOL_CALLS_PER_ROUND = 10;

	/**
	 * Sentinel key returned by `execute_tool_call()` when the model calls the
	 * synthetic `reclassify` tool. The round loop detects this and re-runs the
	 * classify round server-side instead of terminating.
	 *
	 * @since 2.0.0
	 */
	public const RECLASSIFY_SENTINEL = '__reclassify';

	/**
	 * User-facing message when the model is stuck re-emitting an identical query.
	 *
	 * @since 2.0.0
	 */
	private const STUCK_LOOP_MESSAGE = 'I couldn\'t find anything matching that. Try broadening your filters or asking about a different metric.';

	/**
	 * Scope registry for tool resolution and label lookups.
	 *
	 * @since 2.0.0
	 *
	 * @var ScopeRegistry
	 */
	private $registry;

	/**
	 * Original request payload passed through to scope data sources.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $payload;

	/**
	 * NDJSON frame sink for streaming tool-call brackets to the client.
	 *
	 * @since 2.0.0
	 *
	 * @var callable
	 */
	private $sink;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param ScopeRegistry $registry Scope registry for tool resolution.
	 * @param array         $payload  Original request payload.
	 * @param callable      $sink     NDJSON frame sink.
	 */
	public function __construct( ScopeRegistry $registry, array $payload, callable $sink ) {

		$this->registry = $registry;
		$this->payload  = $payload;
		$this->sink     = $sink;
	}

	/**
	 * Process tool calls from an answer-round API response.
	 *
	 * @since 2.0.0
	 *
	 * @param array $response       Raw answer-round API response.
	 * @param array $state          State bag built by `process_answer_round()`.
	 * @param array $allowed_scopes Scope slugs pinned for this turn; calls outside are rejected.
	 *
	 * @return array Updated state bag.
	 */
	public function process( array $response, array $state, array $allowed_scopes ): array {

		$tool_calls = $this->normalize_tool_call_arguments( (array) ( $response['tool_calls'] ?? [] ) );

		if ( $tool_calls === [] ) {
			$state['terminal'] = $this->error( 'protocol', 'Middleware returned neither message nor tool_calls.' );

			return $state;
		}

		$split    = $this->enforce_tool_call_cap( $tool_calls );
		$accepted = $split['accepted'];
		$rejected = $split['rejected'];
		$set_hash = $this->build_tool_call_set_hash( $accepted );

		// Window dedup: catch both the consecutive (A→A) and the non-consecutive
		// (A→B→…→A) repeat patterns. Anything the model already executed this
		// turn is sitting in `$history` as tool results — re-emitting the same
		// `(name, args)` set is definitionally stuck.
		if ( $this->is_stuck_loop( $set_hash, $state['seen_set_hashes'] ) ) {
			$state['terminal'] = $this->success(
				[
					'message'   => [ 'text' => self::STUCK_LOOP_MESSAGE ],
					'sessionId' => $state['session_id'],
				]
			);

			return $state;
		}

		if ( $set_hash !== '' ) {
			$state['seen_set_hashes'][] = $set_hash;
		}

		// Record the tool calls actually dispatched this round (post-stuck-loop) for diagnostics.
		$state['round']['tools'] = $this->collect_tool_calls( $accepted );

		// Declare every emitted call — accepted and overflow alike. OpenAI rejects any
		// role:tool message whose tool_call_id is absent from the preceding assistant
		// message, so the overflow rejections appended below must each be matched by a
		// declaration here. Only `$accepted` is dispatched; the cap limits execution,
		// not acknowledgement.
		$state['history'][] = [
			'role'       => 'assistant',
			'content'    => null,
			'tool_calls' => $tool_calls,
		];

		$state['terminal'] = $this->dispatch_tool_calls( $accepted, $state['history'], $allowed_scopes );

		// Acknowledge overflow rejections unconditionally. Even when dispatch ends the
		// round early (a reclassify call sets the terminal), every overflow call declared
		// above still needs a matching result, and the model should learn it hit the cap.
		$this->append_overflow_messages( $state['history'], $rejected );

		return $state;
	}

	/**
	 * Emit a single tool-call NDJSON frame via the sink.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type         Frame type — `tool_call_start` or `tool_call_end`.
	 * @param string $tool_slug    Scope slug (or raw name for synthetic tools).
	 * @param string $tool_call_id Unique call ID for frontend correlation.
	 * @param string $label        Spinner-copy label (only for `tool_call_start`).
	 */
	private function sink_tool_frame( string $type, string $tool_slug, string $tool_call_id, string $label = '' ): void {

		$frame = [
			'v'       => 1,
			'type'    => $type,
			'tool'    => $tool_slug,
			'call_id' => $tool_call_id,
		];

		if ( $label !== '' ) {
			$frame['label'] = $label;
		}

		( $this->sink )( $frame );
	}

	/**
	 * Execute a single tool call, emit framing events, and append the result to history.
	 *
	 * Returns a reclassify sentinel (`[ RECLASSIFY_SENTINEL => hint ]`) when the
	 * model calls the synthetic `reclassify` tool, otherwise null so the caller
	 * continues iterating.
	 *
	 * @since 2.0.0
	 *
	 * @param array $call           Tool call frame (`id`, `name`, `arguments`).
	 * @param array $history        Conversation history array (passed by reference).
	 * @param array $allowed_scopes Scope slugs pinned for this turn; calls outside are rejected.
	 *
	 * @return array|null Reclassify sentinel or null to continue.
	 */
	private function execute_tool_call( array $call, array &$history, array $allowed_scopes ): ?array {

		$tool_call_id = (string) ( $call['id'] ?? '' );
		$tool_name    = (string) ( $call['name'] ?? '' );
		$label        = $this->derive_tool_call_label( $call );
		$tool_slug    = $this->extract_tool_slug( $tool_name );

		// The synthetic `reclassify` tool is not scope-prefixed and is exempt from
		// the allow-list — it is the model's escape hatch for requesting a different
		// scope set. Returns a sentinel array so the round loop can re-classify
		// server-side instead of punting to the browser.
		if ( $tool_name === 'reclassify' ) {
			$this->sink_tool_frame( 'tool_call_start', $tool_slug, $tool_call_id, $label );
			$this->sink_tool_frame( 'tool_call_end', $tool_slug, $tool_call_id );

			$arguments = (array) ( $call['arguments'] ?? [] );
			$hint      = sanitize_text_field( (string) ( $arguments['hint'] ?? $arguments['reason'] ?? '' ) );

			// OpenAI requires a tool result for every tool_call in the assistant
			// message. Without this, the re-classify call sends incomplete history
			// and OpenAI rejects it with "No tool output found for function call".
			$history[] = [
				'role'         => 'tool',
				'tool_call_id' => $tool_call_id,
				'content'      => 'Reclassifying with hint: ' . $hint,
			];

			return [ self::RECLASSIFY_SENTINEL => $hint ];
		}

		// Server-side scope authorization. The tool name comes from the LLM (relayed by
		// the middleware), so reject any call whose scope was not pinned for this turn by
		// the capability-filtered classify round. A synthetic tool error is appended to
		// history so the model is told and can `reclassify`; no framing is emitted because
		// the call never executes. The registry backstop re-checks the capability anyway.
		if ( ! in_array( $tool_slug, $allowed_scopes, true ) ) {
			$history[] = $this->build_scope_rejected_tool_message( $tool_call_id, $tool_slug );

			return null;
		}

		$this->sink_tool_frame( 'tool_call_start', $tool_slug, $tool_call_id, $label );

		$result = $this->registry->resolve_tool_call( $tool_name, (array) ( $call['arguments'] ?? [] ), $this->payload );

		$this->sink_tool_frame( 'tool_call_end', $tool_slug, $tool_call_id );

		$history[] = [
			'role'         => 'tool',
			'tool_call_id' => $tool_call_id,
			'content'      => (string) $result['content'],
		];

		return null;
	}

	/**
	 * Decode each tool call's `arguments` from a JSON string to an array.
	 *
	 * OpenAI's Responses API delivers `function_call.arguments` as a JSON-encoded
	 * string. The middleware forwards that string verbatim, so the value arrives
	 * here as a string. Every downstream consumer — set-hash, label derivation,
	 * dispatch into the registry, history persistence — expects an array. Casting
	 * a string to array yields `[ "{json}" ]`, silently dropping every argument.
	 *
	 * Normalize once at the boundary; preserve already-decoded arrays so tests
	 * that pass tool calls as arrays keep working.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tool_calls Tool calls from the middleware response.
	 *
	 * @return array Tool calls with `arguments` decoded to arrays.
	 */
	private function normalize_tool_call_arguments( array $tool_calls ): array {

		foreach ( $tool_calls as $idx => $call ) {
			$args = $call['arguments'] ?? [];

			if ( is_string( $args ) ) {
				$decoded = json_decode( $args, true );
				$args    = is_array( $decoded ) ? $decoded : [];
			}

			$tool_calls[ $idx ]['arguments'] = (array) $args;
		}

		return $tool_calls;
	}

	/**
	 * Split incoming tool calls into accepted and rejected (overflow) lists.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tool_calls Tool calls returned by the middleware.
	 *
	 * @return array { accepted: array, rejected: array }
	 */
	private function enforce_tool_call_cap( array $tool_calls ): array {

		if ( count( $tool_calls ) <= self::MAX_TOOL_CALLS_PER_ROUND ) {
			return [
				'accepted' => $tool_calls,
				'rejected' => [],
			];
		}

		return [
			'accepted' => array_slice( $tool_calls, 0, self::MAX_TOOL_CALLS_PER_ROUND ),
			'rejected' => array_slice( $tool_calls, self::MAX_TOOL_CALLS_PER_ROUND ),
		];
	}

	/**
	 * Hash the set of tool calls in a round for stuck-loop detection.
	 *
	 * Hashes each call's `(name, args_json)` after stripping `_label`. Set-equality
	 * via sorting ensures `[A,B]` and `[B,A]` collapse to the same hash, so the
	 * dedup catches truly identical re-emissions without false positives when the
	 * model reorders calls.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tool_calls Tool calls accepted this round (post-cap-enforcement).
	 *
	 * @return string Hash.
	 */
	private function build_tool_call_set_hash( array $tool_calls ): string {

		if ( $tool_calls === [] ) {
			return '';
		}

		$signatures = [];

		foreach ( $tool_calls as $call ) {
			$args = (array) ( $call['arguments'] ?? [] );

			// Strip the _label arg — it's UX, not data identity.
			unset( $args['_label'] );

			ksort( $args );

			$signatures[] = (string) $call['name'] . '|' . md5( (string) wp_json_encode( $args ) ); // NOSONAR.
		}

		sort( $signatures );

		return md5( implode( '||', $signatures ) ); // NOSONAR.
	}

	/**
	 * Check whether the model is stuck re-emitting an identical tool-call set.
	 *
	 * @since 2.0.0
	 *
	 * @param string $set_hash        Hash of the current round's tool-call set.
	 * @param array  $seen_set_hashes Hashes of all previously dispatched sets this turn.
	 *
	 * @return bool Whether the model is stuck re-emitting an identical set.
	 */
	private function is_stuck_loop( string $set_hash, array $seen_set_hashes ): bool {

		return $set_hash !== '' && in_array( $set_hash, $seen_set_hashes, true );
	}

	/**
	 * Dispatch the accepted tool calls in order, appending results to history.
	 *
	 * Returns a reclassify sentinel or terminal envelope as soon as a call ends
	 * the loop, otherwise null so the answer round can continue.
	 *
	 * @since 2.0.0
	 *
	 * @param array $accepted       Accepted tool calls (post-cap-enforcement).
	 * @param array $history        Conversation history array (passed by reference).
	 * @param array $allowed_scopes Scope slugs pinned for this turn; calls outside are rejected.
	 *
	 * @return array|null Terminal envelope on early exit, null otherwise.
	 */
	private function dispatch_tool_calls( array $accepted, array &$history, array $allowed_scopes ): ?array {

		foreach ( $accepted as $idx => $call ) {
			$terminal = $this->execute_tool_call( $call, $history, $allowed_scopes );

			if ( $terminal !== null ) {
				// Append placeholder tool results for any remaining calls so the
				// assistant message's tool_call IDs all have matching results.
				$this->backfill_remaining_tool_results( $history, $accepted, $idx + 1 );

				return $terminal;
			}
		}

		return null;
	}

	/**
	 * Backfill placeholder tool results for calls skipped after an early exit.
	 *
	 * OpenAI requires a tool result for every tool_call ID in the assistant
	 * message. When the loop exits early (e.g. reclassify sentinel), any
	 * remaining calls have no result yet — this fills the gap.
	 *
	 * @since 2.0.0
	 *
	 * @param array $history  Conversation history array (passed by reference).
	 * @param array $accepted Full list of accepted tool calls.
	 * @param int   $from     Index of the first unanswered call.
	 */
	private function backfill_remaining_tool_results( array &$history, array $accepted, int $from ): void {

		$remaining = array_slice( $accepted, $from );

		foreach ( $remaining as $call ) {
			$history[] = [
				'role'         => 'tool',
				'tool_call_id' => (string) ( $call['id'] ?? '' ),
				'content'      => 'Skipped — reclassifying.',
			];
		}
	}

	/**
	 * Append synthetic `too_many_calls` tool errors for over-cap rejected calls.
	 *
	 * @since 2.0.0
	 *
	 * @param array $history  Conversation history array (passed by reference).
	 * @param array $rejected Tool calls rejected by the per-round cap.
	 */
	private function append_overflow_messages( array &$history, array $rejected ): void {

		foreach ( $rejected as $call ) {
			$history[] = $this->build_overflow_tool_message( (string) ( $call['id'] ?? '' ) );
		}
	}

	/**
	 * Build the synthetic `too_many_calls` tool error message for a rejected call.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tool_call_id The rejected call's ID.
	 *
	 * @return array History entry shaped `{role: 'tool', tool_call_id, content}`.
	 */
	private function build_overflow_tool_message( string $tool_call_id ): array {

		return [
			'role'         => 'tool',
			'tool_call_id' => $tool_call_id,
			'content'      => (string) wp_json_encode(
				[
					'error' => 'Too many tool calls in one round',
					'code'  => 'too_many_calls',
					'hint'  => sprintf(
						'Maximum %d tool calls per round. Re-emit ignored calls in a subsequent round if still needed.',
						self::MAX_TOOL_CALLS_PER_ROUND
					),
				]
			),
		];
	}

	/**
	 * Build the synthetic `scope_not_allowed` tool error for an out-of-scope call.
	 *
	 * Mirrors the overflow-rejection pattern: a `{role: tool}` history entry whose
	 * content is a tool-error envelope. Tells the model the requested scope was not
	 * pinned for this turn so it can fall back to the `reclassify` tool. The string
	 * is consumed by the model, not the user, so it is intentionally not translated —
	 * matching `build_overflow_tool_message()`.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tool_call_id The rejected call's ID.
	 * @param string $tool_slug    The disallowed scope slug parsed from the tool name.
	 *
	 * @return array History entry shaped `{role: 'tool', tool_call_id, content}`.
	 */
	private function build_scope_rejected_tool_message( string $tool_call_id, string $tool_slug ): array {

		return [
			'role'         => 'tool',
			'tool_call_id' => $tool_call_id,
			'content'      => (string) wp_json_encode(
				[
					'error' => sprintf(
						'The "%s" scope is not available for this query. Use the reclassify tool to request a different scope.',
						$tool_slug
					),
					'code'  => 'scope_not_allowed',
				]
			),
		];
	}

	/**
	 * Choose the spinner-copy label for a `tool_call_start` frame.
	 *
	 * LLM-composed `arguments._label` wins when present — it can reference args
	 * (e.g. "Looking up your forms from the last 3 months…"). Falls back to the
	 * scope's `get_tool_call_default_label()`; finally to a generic localized
	 * string when the scope cannot be resolved.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tool_call The tool call frame from the middleware. Has
	 *                         `name` (scope-prefixed), `arguments`, `id`.
	 *
	 * @return string Label text.
	 */
	private function derive_tool_call_label( array $tool_call ): string {

		$args  = (array) ( $tool_call['arguments'] ?? [] );
		$label = isset( $args['_label'] ) ? trim( (string) $args['_label'] ) : '';

		if ( $label !== '' ) {
			return $label;
		}

		$name = (string) ( $tool_call['name'] ?? '' );

		return $this->resolve_scope_default_label( $name, $args );
	}

	/**
	 * Resolve a scope's default tool-call label from a scope-prefixed tool name.
	 *
	 * Falls back to a generic localized string when the name is not scope-prefixed
	 * or the scope cannot be resolved.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name Scope-prefixed tool name (e.g. `analytics__form_stats`).
	 * @param array  $args Tool-call arguments (the UX-only `_label` is stripped before dispatch).
	 *
	 * @return string Label text.
	 */
	private function resolve_scope_default_label( string $name, array $args ): string {

		$separator_pos = strpos( $name, '__' );
		$working_on_it = __( 'Working on it…', 'wpforms-lite' );

		if ( $separator_pos === false ) {
			return $working_on_it;
		}

		$scope_slug = substr( $name, 0, $separator_pos );
		$scope      = $this->registry->get( $scope_slug );

		if ( $scope === null ) {
			return $working_on_it;
		}

		unset( $args['_label'] );

		$includes = [ substr( $name, $separator_pos + 2 ) ];

		return $scope->get_tool_call_default_label( $includes, $args );
	}

	/**
	 * Extract the scope slug from a tool call's prefixed name.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tool_name Scope-prefixed name (e.g. `analytics__form_stats`).
	 *
	 * @return string Scope slug, or the raw name for synthetic tools (e.g. `reclassify`).
	 */
	private function extract_tool_slug( string $tool_name ): string {

		$separator_pos = strpos( $tool_name, '__' );

		if ( $separator_pos === false ) {
			return $tool_name;
		}

		return substr( $tool_name, 0, $separator_pos );
	}

	/**
	 * Map a tool-call list to diagnostic objects `{ name, args, label }`.
	 *
	 * Keeps the full scope-prefixed tool name (e.g. `analytics__field_stats`) plus the call
	 * arguments. The internal `_label` arg (UX spinner copy, not data) is lifted out to a
	 * top-level `label` and stripped from `args`.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tool_calls Accepted tool calls for a round.
	 *
	 * @return array List of `{ name, args, label? }` objects in execution order.
	 */
	private function collect_tool_calls( array $tool_calls ): array {

		$calls = [];

		foreach ( $tool_calls as $call ) {
			$name = (string) ( $call['name'] ?? '' );

			if ( $name === '' ) {
				continue;
			}

			$args  = (array) ( $call['arguments'] ?? [] );
			$label = isset( $args['_label'] ) ? (string) $args['_label'] : '';

			unset( $args['_label'] );

			$entry = [
				'name' => $name,
				'args' => (object) $args,
			];

			if ( $label !== '' ) {
				$entry['label'] = $label;
			}

			$calls[] = $entry;
		}

		return $calls;
	}

	/**
	 * Build a uniform success response envelope.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Response payload.
	 *
	 * @return array
	 */
	private function success( array $data ): array {

		return [
			'status' => 'success',
			'code'   => null,
			'error'  => null,
			'data'   => $data,
		];
	}

	/**
	 * Build a uniform error response envelope.
	 *
	 * The `error` field name (not `message`) matches what api.js / chat-helpers-admin consume.
	 *
	 * @since 2.0.0
	 *
	 * @param string $code  Error code slug.
	 * @param string $error Human-readable error message.
	 *
	 * @return array
	 */
	private function error( string $code, string $error ): array {

		return [
			'status' => 'error',
			'code'   => $code,
			'error'  => $error,
			'data'   => null,
		];
	}
}
