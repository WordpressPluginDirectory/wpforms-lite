<?php

namespace WPForms\Integrations\AI\Admin\Ajax\Chat;

use WPForms\Integrations\AI\Admin\Chat\ScopeBase;
use WPForms\Integrations\AI\Admin\Chat\ScopeRegistry;
use WPForms\Integrations\AI\Admin\Chat\SurfaceBase;
use WPForms\Integrations\AI\Admin\Chat\SurfaceRegistry;

/**
 * Request validation, surface/scope resolution, context assembly, and history sanitization.
 *
 * Extracted from the Chat AJAX handler to keep each class under the 20-method threshold.
 * Stateless — all dependencies arrive via method parameters.
 *
 * @since 2.0.0
 */
class RequestContext {

	/**
	 * Read a payload value as a string, with an optional fallback key.
	 *
	 * Collapses the repeated `(string) ( $payload[...] ?? '' )` extraction into a
	 * single helper. `isset()` mirrors the null-coalescing semantics of `??`
	 * (treating null and absent keys identically), so callers behave the same as
	 * the inline expressions they replace. The `userPrompt` fallback supports the
	 * dual `prompt` / `userPrompt` payload keys.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $payload      Sanitized request payload.
	 * @param string $key          Primary key to read.
	 * @param string $fallback_key Optional secondary key, used only when the
	 *                             primary key is absent or null.
	 *
	 * @return string Extracted value, or an empty string when neither key is set.
	 */
	public function payload_string( array $payload, string $key, string $fallback_key = '' ): string {

		if ( isset( $payload[ $key ] ) ) {
			return (string) $payload[ $key ];
		}

		if ( $fallback_key !== '' && isset( $payload[ $fallback_key ] ) ) {
			return (string) $payload[ $fallback_key ];
		}

		return '';
	}

	/**
	 * Validate the request payload. Returns null on success, an error envelope on failure.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $payload      Raw request payload.
	 * @param string $nonce_action Nonce action to verify against.
	 *
	 * @return array|null
	 */
	public function validate( array $payload, string $nonce_action ): ?array {

		$nonce = $this->payload_string( $payload, 'nonce' );

		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			return $this->error( 'nonce', 'Nonce verification failed.' );
		}

		// Accept both `prompt` (JS payload key) and `userPrompt` (internal name).
		$prompt = $this->payload_string( $payload, 'prompt', 'userPrompt' );

		if ( trim( $prompt ) === '' ) {
			return $this->error( 'empty_prompt', 'User prompt is empty.' );
		}

		if ( empty( $payload['surface'] ) ) {
			return $this->error( 'no_surface', 'Surface is required.' );
		}

		return null;
	}

	/**
	 * Decode the `pageState` key in the payload from a JSON string to an array.
	 *
	 * `pageState` ships from JS as a JSON string because the AJAX helper
	 * (`api.js`) only knows how to URL-encode flat key/value pairs and arrays —
	 * nested objects would arrive as "[object Object]". Decode once here so
	 * surfaces and scopes can treat `$payload['pageState']` as a regular nested
	 * array. Non-string or undecodable values are left untouched / reset to an
	 * empty array, matching the prior inline behavior.
	 *
	 * @since 2.0.0
	 *
	 * @param array $payload Sanitized request payload.
	 *
	 * @return array Payload with `pageState` normalized to an array when present as a string.
	 */
	public function decode_page_state( array $payload ): array {

		if ( ! isset( $payload['pageState'] ) || ! is_string( $payload['pageState'] ) ) {
			return $payload;
		}

		$decoded              = json_decode( $payload['pageState'], true );
		$payload['pageState'] = is_array( $decoded ) ? $decoded : [];

		return $payload;
	}

	/**
	 * Resolve the active surface from the payload.
	 *
	 * Looks the surface up in the registry, validates the instance, and checks
	 * the caller's capability. Returns the surface config array on success, or an
	 * error envelope (identifiable by its `status` key) when resolution fails.
	 *
	 * @since 2.0.0
	 *
	 * @param array $payload Sanitized request payload.
	 *
	 * @return array Surface config on success, error envelope on failure.
	 */
	public function resolve_surface( array $payload ): array {

		$surface_slug     = sanitize_key( $this->payload_string( $payload, 'surface' ) );
		$surface_registry = wpforms()->obj( 'ai_chat_surface_registry' );
		$surface_instance = $surface_registry instanceof SurfaceRegistry ? $surface_registry->get( $surface_slug ) : null;

		if ( ! $surface_instance instanceof SurfaceBase ) {
			return $this->error( 'surface_unknown', 'Unknown surface.' );
		}

		$surface = $surface_registry->config_for( $surface_instance );

		if ( ! wpforms_current_user_can( (string) $surface['capability'] ) ) {
			return $this->error( 'capability', 'Insufficient capability for this surface.' );
		}

		return $surface;
	}

	/**
	 * Sanitize and validate the scopes list against the registry.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $scopes Raw scopes value (expected array of strings).
	 *
	 * @return array
	 */
	public function sanitize_scope_list( $scopes ): array {

		if ( ! is_array( $scopes ) ) {
			return [];
		}

		$registry = wpforms()->obj( 'ai_chat_scope_registry' );

		if ( ! $registry instanceof ScopeRegistry ) {
			return [];
		}

		$cleaned = [];

		foreach ( $scopes as $scope ) {
			$slug = sanitize_key( (string) $scope );

			if ( $slug === '' ) {
				continue;
			}

			if ( $registry->get( $slug ) === null ) {
				continue;
			}

			$cleaned[] = $slug;
		}

		return array_values( array_unique( $cleaned ) );
	}

	/**
	 * Reduce a scope list to those the current user can access.
	 *
	 * @since 2.0.0
	 *
	 * @param array $scopes Scope slugs.
	 *
	 * @return array
	 */
	public function filter_scopes_by_capability( array $scopes ): array {

		$registry = wpforms()->obj( 'ai_chat_scope_registry' );

		if ( ! $registry instanceof ScopeRegistry ) {
			return [];
		}

		$allowed = [];

		foreach ( $scopes as $slug ) {
			$scope = $registry->get( $slug );

			if ( ! $scope instanceof ScopeBase ) {
				continue;
			}

			if ( ! wpforms_current_user_can( $scope->get_capability() ) ) {
				continue;
			}

			$allowed[] = $slug;
		}

		return $allowed;
	}

	/**
	 * Build the initial context by asking the active surface to aggregate scope
	 * contributions and its own `page_state`.
	 *
	 * The resulting shape matches the middleware contract:
	 *
	 *     {
	 *         page_context:    'wpforms-overview',
	 *         scopes:          [ 'wpforms_general', 'forms_inventory', … ],
	 *         availableScopes: [ { slug, requiresLicense }, … ],
	 *         page_state:      { … surface-owned filters / view state … },
	 *         surface_state:   { scope_slug: { … } , … },
	 *     }
	 *
	 * @since 2.0.0
	 *
	 * @param array $surface       Active surface (with `slug` injected).
	 * @param array $active_scopes Active scope slugs.
	 * @param array $payload       Sanitized request payload.
	 *
	 * @return array
	 */
	public function build_initial_context( array $surface, array $active_scopes, array $payload ): array {

		$surface_registry = wpforms()->obj( 'ai_chat_surface_registry' );
		$available_scopes = $this->build_available_scopes( $active_scopes );

		$surface_instance = $surface_registry instanceof SurfaceRegistry
			? $surface_registry->get( (string) ( $surface['slug'] ?? '' ) )
			: null;

		// Surface owns the aggregation: it calls each active scope's state-builder,
		// namespaces results under `surface_state[$slug]`, and adds its own `page_state`.
		$aggregated = $surface_instance instanceof SurfaceBase
			? $surface_instance->get_state( $surface, $payload, $active_scopes )
			: [];

		return array_merge(
			[
				'page_context'    => (string) ( $surface['slug'] ?? '' ),
				'scopes'          => $active_scopes,
				'availableScopes' => $available_scopes,
				'page_state'      => [],
				'surface_state'   => [],
			],
			$aggregated
		);
	}

	/**
	 * Build the `availableScopes` list for the classify round.
	 *
	 * Each entry carries the scope's license tier so the classify round can filter
	 * against the caller's license. `requiresLicense` mirrors the middleware
	 * module's `requiresLicense` export. Scopes that cannot be resolved are skipped.
	 *
	 * @since 2.0.0
	 *
	 * @param array $active_scopes Active scope slugs.
	 *
	 * @return array List of `{ slug, requiresLicense }` entries.
	 */
	public function build_available_scopes( array $active_scopes ): array {

		$registry = wpforms()->obj( 'ai_chat_scope_registry' );

		if ( ! $registry instanceof ScopeRegistry ) {
			return [];
		}

		$available_scopes = [];

		foreach ( $active_scopes as $slug ) {
			$scope = $registry->get( $slug );

			if ( ! $scope instanceof ScopeBase ) {
				continue;
			}

			$available_scopes[] = [
				'slug'            => $slug,
				'requiresLicense' => $scope->get_requires(),
			];
		}

		return $available_scopes;
	}

	/**
	 * Sanitize the conversation history array.
	 *
	 * The JS chat helper sends `history` as a JSON-stringified value. Two shapes
	 * are accepted: a bare array of per-turn `{question, answer}` entries (new
	 * dispatch.js), and the legacy `{data: [...]}` wrapper shape produced by the
	 * History helper's default `JSON.stringify`. Translate either to the
	 * OpenAI-style `{role, content}` per-message shape the middleware expects.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $history Raw history value.
	 *
	 * @return array
	 */
	public function sanitize_history( $history ): array {

		$entries = $this->normalise_history_payload( $history );
		$cleaned = [];

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			foreach ( $this->normalise_history_message( $entry ) as $message ) {
				$cleaned[] = $message;
			}
		}

		// Truncation safety net — middleware also enforces this.
		return array_slice( $cleaned, -20 );
	}

	/**
	 * Normalize the raw history value into a bare array of turn entries.
	 *
	 * JS sends `history` as a JSON-stringified value. Two shapes are accepted: a
	 * bare array of per-turn `{question, answer}` entries (new dispatch.js), and
	 * the legacy `{data: [...]}` wrapper produced by the History helper's default
	 * `JSON.stringify`. Both collapse to the bare array of turn entries here.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $history Raw history value.
	 *
	 * @return array Bare array of turn entries.
	 */
	private function normalise_history_payload( $history ): array {

		// JS sends history as a JSON string — decode first, then normalize.
		if ( is_string( $history ) ) {
			$decoded = json_decode( $history, true );
			$history = is_array( $decoded ) ? $decoded : [];
		}

		if ( ! is_array( $history ) ) {
			return [];
		}

		// The chat element's History helper serializes a wrapper object — unwrap
		// its `data` key into the bare array of {question, answer} turn entries.
		if ( isset( $history['data'] ) && is_array( $history['data'] ) ) {
			$history = $history['data'];
		}

		return $history;
	}

	/**
	 * Normalize a single history turn into OpenAI-style `{role, content}` messages.
	 *
	 * A turn yields a `user` message for its question and an `assistant` message
	 * for its answer; empty parts are skipped.
	 *
	 * @since 2.0.0
	 *
	 * @param array $entry A single `{question, answer}` history turn.
	 *
	 * @return array List of zero, one, or two `{role, content}` messages.
	 */
	private function normalise_history_message( array $entry ): array {

		$question = sanitize_textarea_field( (string) ( $entry['question'] ?? '' ) );
		$answer   = sanitize_textarea_field( (string) ( $entry['answer'] ?? '' ) );

		$messages = [];

		if ( $question !== '' ) {
			$messages[] = [
				'role'    => 'user',
				'content' => $question,
			];
		}

		if ( $answer !== '' ) {
			$messages[] = [
				'role'    => 'assistant',
				'content' => $answer,
			];
		}

		return $messages;
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
