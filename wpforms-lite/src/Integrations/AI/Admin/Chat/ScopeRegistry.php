<?php

namespace WPForms\Integrations\AI\Admin\Chat;

use Throwable;

/**
 * Scope Registry — instance-based, accessed via `wpforms()->obj( 'ai_chat_scope_registry' )`.
 *
 * Scope classes self-register through `register( ScopeBase $scope )` from their
 * own `init()`.
 *
 * @since 2.0.0
 */
class ScopeRegistry {

	/**
	 * Registered scope instances, keyed by slug.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $scopes = [];

	/**
	 * Constructor.
	 *
	 * Accepts an optional pre-seeded scopes map for testing without a WPForms
	 * loader boot cycle.
	 *
	 * @since 2.0.0
	 *
	 * @param array $scopes Optional initial scope map.
	 */
	public function __construct( array $scopes = [] ) {

		foreach ( $scopes as $scope ) {
			$this->register( $scope );
		}
	}

	/**
	 * Register a scope class instance.
	 *
	 * @since 2.0.0
	 *
	 * @param ScopeBase $scope Scope instance.
	 */
	public function register( ScopeBase $scope ): void {

		$slug = $scope->get_slug();

		if ( $slug === '' ) {
			return;
		}

		$this->warn_on_unexpected_override( $slug, $scope );

		$this->scopes[ $slug ] = $scope;
	}

	/**
	 * Log when a registration replaces a different class under the same slug.
	 *
	 * A Pro scope overriding its Lite parent under the same slug is intentional
	 * and stays silent (the incoming instance is a subclass of the existing one).
	 * An unrelated class reusing a slug is almost always a mistake, so surface it
	 * via the plugin log instead of letting the last writer win invisibly.
	 *
	 * @since 2.0.0
	 *
	 * @param string    $slug  Scope slug being registered.
	 * @param ScopeBase $scope Incoming scope instance.
	 */
	private function warn_on_unexpected_override( string $slug, ScopeBase $scope ): void {

		$existing = $this->scopes[ $slug ] ?? null;

		if ( $existing === null || $scope instanceof $existing ) {
			return;
		}

		wpforms_log(
			'AI Chat: scope slug collision',
			[
				'slug'     => $slug,
				'existing' => get_class( $existing ),
				'incoming' => get_class( $scope ),
			],
			[ 'type' => 'error' ]
		);
	}

	/**
	 * Get a scope instance by slug.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Scope slug.
	 *
	 * @return ScopeBase|null
	 */
	public function get( string $slug ): ?ScopeBase {

		return $this->scopes[ $slug ] ?? null;
	}

	/**
	 * Get all registered scope instances.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_all(): array {

		return $this->scopes;
	}

	/**
	 * Get a scope instance by slug.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Scope slug.
	 *
	 * @return ScopeBase|null Scope instance or null when not registered.
	 */
	public function get_scope( string $slug ): ?ScopeBase {

		return $this->get( $slug );
	}

	/**
	 * Resolve a tool call by its scope-prefixed name.
	 *
	 * Parses `<scope>__<tool>`, strips the UX-only `_label` argument, dispatches
	 * to the owning scope's resolver callable. Resolver exceptions are caught
	 * and converted to the standard error envelope.
	 *
	 * Does NOT handle the synthetic `reclassify` tool — that is detected and
	 * short-circuited by `Ajax\Chat::run_round_loop()` before this method is called.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name    Scope-prefixed tool name (e.g. `analytics__form_stats`).
	 * @param array  $args    Tool arguments. May include `_label` (stripped).
	 * @param array  $payload Request payload (for resolver context).
	 *
	 * @return array { content: string, is_error: bool, tool_slug: string }
	 */
	public function resolve_tool_call( string $name, array $args, array $payload ): array {

		$separator_pos = strpos( $name, '__' );

		if ( $separator_pos === false ) {
			return $this->format_tool_error(
				'unknown_tool',
				sprintf( 'Tool name "%s" is missing the scope prefix.', $name ),
				'Tool names must be in the form scope_slug__tool_name.',
				''
			);
		}

		$scope_slug = substr( $name, 0, $separator_pos );
		$token      = substr( $name, $separator_pos + 2 );
		$scope      = $this->scopes[ $scope_slug ] ?? null;

		if ( $scope === null ) {
			return $this->format_tool_error(
				'unknown_tool',
				sprintf( 'Unknown scope "%s".', $scope_slug ),
				'',
				$scope_slug
			);
		}

		// Defense-in-depth backstop. The tool name originates from the LLM (relayed by
		// the middleware), outside the trust boundary, and is resolved against the full
		// registry — so the registry must re-authorize every dispatch regardless of
		// caller discipline. The Ajax handler's per-turn allow-list is the primary gate;
		// this guarantees no scope executes for a user lacking its capability.
		if ( ! wpforms_current_user_can( $scope->get_capability() ) ) {
			return $this->format_tool_error(
				'forbidden_scope',
				sprintf( 'You do not have permission to use the "%s" scope.', $scope_slug ),
				'',
				$scope_slug
			);
		}

		// Strip the UX-only label argument before resolver dispatch.
		unset( $args['_label'] );

		$callables = $scope->get_data_sources();

		if ( ! isset( $callables[ $token ] ) ) {
			return $this->format_tool_error(
				'unknown_tool',
				sprintf( 'Scope "%1$s" does not expose tool "%2$s".', $scope_slug, $token ),
				'',
				$scope_slug
			);
		}

		return $this->dispatch_resolver( $callables[ $token ], $args, $payload, $scope_slug );
	}

	/**
	 * Invoke a resolver callable and wrap its result in the standard envelope.
	 *
	 * Mirrors the β `args` into the legacy `request_data.args` slot for back-compat,
	 * calls the resolver, and converts any thrown exception into a `resolver_failure`
	 * error envelope.
	 *
	 * @since 2.0.0
	 *
	 * @param callable $resolver   Resolver callable for the requested token.
	 * @param array    $args       Tool arguments (already stripped of `_label`).
	 * @param array    $payload    Request payload (for resolver context).
	 * @param string   $scope_slug Owning scope slug for the result frame.
	 *
	 * @return array [ content: string, is_error: bool, tool_slug: string ]
	 */
	private function dispatch_resolver( callable $resolver, array $args, array $payload, string $scope_slug ): array {

		try {
			$resolver_payload         = $payload;
			$resolver_payload['args'] = $args;

			// Back-compat shim: legacy fetch methods (Analytics, FormsInventory) still
			// read from $payload['request_data']['args'] — the dead α envelope. Mirror
			// the β args into that slot so date_range, filters, granularity, etc. are
			// not silently dropped. Remove once every fetch method reads from
			// $payload['args'] directly.
			$resolver_payload['request_data']         = (array) ( $resolver_payload['request_data'] ?? [] );
			$resolver_payload['request_data']['args'] = $args;

			// Invoke the resolver callable.
			$result = $resolver( $resolver_payload );
		} catch ( Throwable $e ) {
			return $this->format_tool_error(
				'resolver_failure',
				sanitize_text_field( $e->getMessage() ),
				'',
				$scope_slug
			);
		}

		return [
			'content'   => (string) wp_json_encode( [ 'data' => $result ] ),
			'is_error'  => false,
			'tool_slug' => $scope_slug,
		];
	}

	/**
	 * Format a tool-call error message in the standard envelope.
	 *
	 * @since 2.0.0
	 *
	 * @param string $code  Machine-readable kind (e.g. `unknown_tool`).
	 * @param string $error Human-readable summary.
	 * @param string $hint  Optional remedy.
	 * @param string $slug  Scope slug for the frame (may be empty for unrouted errors).
	 *
	 * @return array Standard error envelope.
	 */
	private function format_tool_error( string $code, string $error, string $hint, string $slug ): array {

		$payload = [
			'error' => $error,
			'code'  => $code,
		];

		if ( $hint !== '' ) {
			$payload['hint'] = $hint;
		}

		return [
			'content'   => (string) wp_json_encode( $payload ),
			'is_error'  => true,
			'tool_slug' => $slug,
		];
	}
}
