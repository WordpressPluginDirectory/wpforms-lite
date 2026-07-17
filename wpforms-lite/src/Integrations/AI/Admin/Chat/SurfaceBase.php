<?php

namespace WPForms\Integrations\AI\Admin\Chat;

/**
 * Abstract base for AI Chat surfaces.
 *
 * A surface aggregates scope contributions into the full context dict shipped
 * to the middleware (`page_state` + `surface_state` namespaced by scope slug).
 *
 * @since 2.0.0
 */
abstract class SurfaceBase {

	/**
	 * Surface slug. Concrete classes define `public const SLUG = '...'`.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_slug(): string {

		return (string) static::SLUG;
	}

	/**
	 * Capability required to view this surface.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	abstract public function get_capability(): string;

	/**
	 * Default scopes active on this surface.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	abstract public function get_default_scopes(): array;

	/**
	 * Color scheme.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_color_scheme(): string {

		return 'light';
	}

	/**
	 * Admin body class appended when this surface is active.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_body_class(): string {

		return 'wpforms-admin-chat-active';
	}

	/**
	 * Whether this surface is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {

		return true;
	}

	/**
	 * Path to the surface's JS module relative to the chat-element directory.
	 *
	 * Returning `null` (the default) means this surface does not register
	 * a JS module — the chat's `surface-admin` fallback is used. Concrete
	 * surfaces override this to ship surface-specific page-state and
	 * sample-prompts logic.
	 *
	 * @since 2.0.0
	 *
	 * @return string|null Path string (relative to the chat-element JS dir) or null.
	 */
	public function get_js_module(): ?string {

		return null;
	}

	/**
	 * Helper-name slug used to register the surface module in `WPFormsAi.helpers`.
	 *
	 * Convention: `surface{StudlyCaseSlug}` — e.g. `wpforms-overview` →
	 * `surfaceWpformsOverview`. This is the key the chat element looks up
	 * when resolving `admin.surface`.
	 *
	 * @since 2.0.0
	 *
	 * @return string The helper name.
	 */
	public function get_js_helper_name(): string {

		$slug  = $this->get_slug();
		$parts = array_map( 'ucfirst', explode( '-', $slug ) );

		return 'surface' . implode( '', $parts );
	}

	/**
	 * Initialize: self-register into the surface registry, then run hooks().
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		$registry = wpforms()->obj( 'ai_chat_surface_registry' );

		if ( $registry instanceof SurfaceRegistry ) {
			$registry->register( $this );
		}

		$this->hooks();
	}

	/**
	 * Attach hooks. Override in subclasses.
	 *
	 * @since 2.0.0
	 */
	protected function hooks(): void {}

	/**
	 * Surface-owned page state. Override in subclasses.
	 *
	 * @since 2.0.0
	 *
	 * @param array $surface Active surface config.
	 * @param array $request Sanitized request payload.
	 *
	 * @return array
	 */
	protected function build_page_state( array $surface, array $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		return [];
	}

	/**
	 * Aggregate the full state dict for this surface.
	 *
	 * @since 2.0.0
	 *
	 * @param array $surface       Active surface config (with `slug` injected).
	 * @param array $request       Sanitized request payload.
	 * @param array $active_scopes Active scope slugs for this turn.
	 *
	 * @return array
	 */
	public function get_state( array $surface, array $request, array $active_scopes ): array {

		$page_state    = $this->build_page_state( $surface, $request );
		$surface_state = $this->build_surface_state( $surface, $request, $active_scopes, $page_state );

		$state = [
			'page_state'    => $page_state,
			'surface_state' => $surface_state,
		];

		/**
		 * Filters the aggregated surface state before it ships to the middleware.
		 *
		 * @since 2.0.0
		 *
		 * @param array $state         Aggregated state.
		 * @param array $surface       Active surface config.
		 * @param array $request       Sanitized request payload.
		 * @param array $active_scopes Active scope slugs.
		 */
		return (array) apply_filters(
			"wpforms_integrations_ai_admin_chat_surface_base_get_state_{$this->get_slug()}",
			$state,
			$surface,
			$request,
			$active_scopes
		);
	}

	/**
	 * Aggregate each active scope's contribution into the `surface_state` dict.
	 *
	 * Scopes run in `$active_scopes` order; the running `$accumulated` dict exposes
	 * earlier scopes' contributions so later scopes can read and patch prior state.
	 *
	 * @since 2.0.0
	 *
	 * @param array $surface       Active surface config.
	 * @param array $request       Sanitized request payload.
	 * @param array $active_scopes Active scope slugs for this turn.
	 * @param array $page_state    Surface-owned page state (seeds `$accumulated`).
	 *
	 * @return array Scope-slug => contribution map.
	 */
	private function build_surface_state( array $surface, array $request, array $active_scopes, array $page_state ): array {

		$scope_registry = wpforms()->obj( 'ai_chat_scope_registry' );

		if ( ! $scope_registry instanceof ScopeRegistry ) {
			return [];
		}

		$surface_state = [];
		$accumulated   = [
			'page_state'    => $page_state,
			'surface_state' => $surface_state,
		];

		foreach ( $active_scopes as $slug ) {
			$scope = $scope_registry->get( $slug );

			if ( ! $scope instanceof ScopeBase ) {
				continue;
			}

			$surface_state[ $slug ]       = $scope->get_state( $surface, $request, $accumulated );
			$accumulated['surface_state'] = $surface_state;
		}

		return $surface_state;
	}
}
