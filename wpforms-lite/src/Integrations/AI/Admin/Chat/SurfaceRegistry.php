<?php

namespace WPForms\Integrations\AI\Admin\Chat;

/**
 * Surface Registry — instance-based, accessed via `wpforms()->obj( 'ai_chat_surface_registry' )`.
 *
 * Surface classes self-register through `register( SurfaceBase $surface )` from their
 * own `init()`. The legacy registration filter is dispatched in `init()` for
 * third-party config-array entries.
 *
 * @since 2.0.0
 */
class SurfaceRegistry {

	/**
	 * Registered surface instances, keyed by slug.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $surfaces = [];

	/**
	 * Patches applied on top of registered surfaces, keyed by slug.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $patches = [];

	/**
	 * Initialize.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		/**
		 * Filters the registered AI chat surfaces (legacy config-array shape).
		 *
		 * @since 2.0.0
		 *
		 * @param array $surfaces Map of slug => config.
		 */
		$external = (array) apply_filters( 'wpforms_integrations_ai_admin_chat_surface_registry', [] );

		foreach ( $external as $slug => $config ) {
			$this->register_legacy( (string) $slug, (array) $config );
		}
	}

	/**
	 * Register a surface class instance.
	 *
	 * @since 2.0.0
	 *
	 * @param SurfaceBase $surface Surface instance.
	 */
	public function register( SurfaceBase $surface ): void {

		$slug = $surface->get_slug();

		if ( $slug === '' ) {
			return;
		}

		$this->warn_on_unexpected_override( $slug, $surface );

		$this->surfaces[ $slug ] = $surface;
	}

	/**
	 * Log when a registration replaces a different class under the same slug.
	 *
	 * A subclass overriding its parent under the same slug is intentional and
	 * stays silent; an unrelated class reusing a slug is almost always a mistake,
	 * so surface it via the plugin log instead of letting the last writer win.
	 *
	 * @since 2.0.0
	 *
	 * @param string      $slug    Surface slug being registered.
	 * @param SurfaceBase $surface Incoming surface instance.
	 */
	private function warn_on_unexpected_override( string $slug, SurfaceBase $surface ): void {

		$existing = $this->surfaces[ $slug ] ?? null;

		if ( $existing === null || $surface instanceof $existing ) {
			return;
		}

		wpforms_log(
			'AI Chat: surface slug collision',
			[
				'slug'     => $slug,
				'existing' => get_class( $existing ),
				'incoming' => get_class( $surface ),
			],
			[ 'type' => 'error' ]
		);
	}

	/**
	 * Get a surface instance by slug.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Surface slug.
	 *
	 * @return SurfaceBase|null
	 */
	public function get( string $slug ): ?SurfaceBase {

		return $this->surfaces[ $slug ] ?? null;
	}

	/**
	 * Get all registered surfaces.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_all(): array {

		return $this->surfaces;
	}

	/**
	 * Shallow-merge a patch into an existing surface's config.
	 *
	 * Kept for backward compatibility with callers that need to amend a
	 * registered surface — `default_scopes` overrides done by Pro subclasses
	 * supersede this, but addons may still need it.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug  Surface slug.
	 * @param array  $patch Keys to overwrite.
	 */
	public function amend( string $slug, array $patch ): void {

		if ( ! isset( $this->surfaces[ $slug ] ) ) {
			return;
		}

		$this->patches[ $slug ] = array_merge( $this->patches[ $slug ] ?? [], $patch );
	}

	/**
	 * Get the surface matching the current admin screen, or null.
	 *
	 * Returns the surface CONFIG ARRAY (not the instance) so call sites can
	 * read `capability`, `body_class`, `default_scopes`, `color_scheme`, `slug`
	 * the way they do today.
	 *
	 * @since 2.0.0
	 *
	 * @return array|null
	 */
	public function get_active_surface(): ?array {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( $page === '' || ! isset( $this->surfaces[ $page ] ) ) {
			return null;
		}

		$surface = $this->surfaces[ $page ];

		if ( ! $surface->is_enabled() ) {
			return null;
		}

		return $this->config_for( $surface );
	}

	/**
	 * Build a config array for a surface instance (with any amend() patches applied).
	 *
	 * @since 2.0.0
	 *
	 * @param SurfaceBase $surface Surface instance.
	 *
	 * @return array
	 */
	public function config_for( SurfaceBase $surface ): array {

		$slug   = $surface->get_slug();
		$config = [
			'slug'           => $slug,
			'capability'     => $surface->get_capability(),
			'body_class'     => $surface->get_body_class(),
			'default_scopes' => $surface->get_default_scopes(),
			'color_scheme'   => $surface->get_color_scheme(),
			'enabled'        => $surface->is_enabled(),
		];

		if ( isset( $this->patches[ $slug ] ) ) {
			$config = array_merge( $config, $this->patches[ $slug ] );
		}

		return $config;
	}

	/**
	 * Register a third-party surface via legacy config-array shape.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug   Surface slug.
	 * @param array  $config Config dict.
	 */
	private function register_legacy( string $slug, array $config ): void {

		if ( $slug === '' ) {
			return;
		}

		$this->register( $this->make_legacy_adapter( $slug, $config ) );
	}

	/**
	 * Build a `SurfaceBase` adapter wrapping a legacy config-array entry.
	 *
	 * The adapter reads each surface attribute from the config dict with safe
	 * defaults, so third-party config-array surfaces behave like class-based ones.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug   Surface slug.
	 * @param array  $config Config dict.
	 *
	 * @return SurfaceBase
	 */
	private function make_legacy_adapter( string $slug, array $config ): SurfaceBase {

		return new class( $slug, $config ) extends SurfaceBase {

			/**
			 * Cached slug for the adapter.
			 *
			 * @since 2.0.0
			 *
			 * @var string
			 */
			private $slug_value;

			/**
			 * Cached config dict.
			 *
			 * @since 2.0.0
			 *
			 * @var array
			 */
			private $config;

			/**
			 * Adapter constructor.
			 *
			 * @since 2.0.0
			 *
			 * @param string $slug   Surface slug.
			 * @param array  $config Config dict.
			 */
			public function __construct( string $slug, array $config ) {

				$this->slug_value = $slug;
				$this->config     = $config;
			}

			/**
			 * Get the cached slug.
			 *
			 * @since 2.0.0
			 *
			 * @return string
			 */
			public function get_slug(): string {

				return $this->slug_value;
			}

			/**
			 * Get the configured capability.
			 *
			 * @since 2.0.0
			 *
			 * @return string
			 */
			public function get_capability(): string {

				return (string) ( $this->config['capability'] ?? 'manage_options' );
			}

			/**
			 * Get the configured default scopes.
			 *
			 * @since 2.0.0
			 *
			 * @return array
			 */
			public function get_default_scopes(): array {

				return (array) ( $this->config['default_scopes'] ?? [] );
			}

			/**
			 * Get the configured color scheme.
			 *
			 * @since 2.0.0
			 *
			 * @return string
			 */
			public function get_color_scheme(): string {

				return (string) ( $this->config['color_scheme'] ?? 'light' );
			}

			/**
			 * Get the configured body class.
			 *
			 * @since 2.0.0
			 *
			 * @return string
			 */
			public function get_body_class(): string {

				return (string) ( $this->config['body_class'] ?? 'wpforms-admin-chat-active' );
			}

			/**
			 * Whether the configured surface is enabled.
			 *
			 * @since 2.0.0
			 *
			 * @return bool
			 */
			public function is_enabled(): bool {

				return ! empty( $this->config['enabled'] );
			}
		};
	}
}
