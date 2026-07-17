<?php

namespace WPForms\SetupWizard;

use WPForms\Admin\Notice;
use WPForms\SetupWizard\Service\PluginCatalog;

/**
 * Failed plugin installs notice for the Setup Wizard.
 *
 * The wizard installs cross-product plugins and addons in the background. When
 * an installation fails, the plugin files and their error messages are parked in a
 * week-long transient here. After the wizard finishes, a dismissible admin notice
 * naming the failed regular plugins, each linked to its WordPress.org repository
 * page, is shown on WPForms admin pages (the form builder excluded) so the user
 * can install them manually. The transient TTL lets the notice fade on its own
 * after a week when it is never dismissed, and the next wizard launch clears it so
 * a fresh run never inherits stale failures.
 *
 * Failed addons are handled by the Pro subclass, which adds its own dismissible
 * notice (see {@see \WPForms\Pro\SetupWizard\FailedInstallsNotice}).
 *
 * @since 2.0.0
 */
class FailedInstallsNotice {

	/**
	 * Transient storing the failed installations as `plugin file => error message`.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const TRANSIENT = 'wpforms_setup_wizard_failed_installs';

	/**
	 * How long the failed installations survive before the notice disappears on its own.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private const TTL = WEEK_IN_SECONDS;

	/**
	 * Dismissible notice slug for failed regular plugins.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const NOTICE_SLUG_PLUGINS = 'setup_wizard_failed_installs_plugins';

	/**
	 * Plugin catalog.
	 *
	 * @since 2.0.0
	 *
	 * @var PluginCatalog
	 */
	protected $catalog;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		$this->catalog = new PluginCatalog();
	}

	/**
	 * Record the outcome of an installation run.
	 *
	 * The SPA retries failed installations with follow-up requests, so the
	 * record is merged across runs rather than overwritten: plugins installed
	 * by this run retract their earlier failures, new failures are folded in
	 * keyed by plugin file (a repeated failure updates its message instead of
	 * duplicating), and every write resets the week-long TTL. The record is
	 * cleared once no failures remain.
	 *
	 * @since 2.0.0
	 *
	 * @param array $failed    Failed installs as `plugin file => error message`.
	 * @param array $installed Plugin files successfully installed by this run.
	 */
	public function record( array $failed, array $installed ): void {

		$known  = array_diff_key( $this->get_failed(), array_flip( $installed ) );
		$failed = array_replace( $known, $failed );

		if ( empty( $failed ) ) {
			$this->clear();

			return;
		}

		set_transient( self::TRANSIENT, $failed, self::TTL );
	}

	/**
	 * Clear the recorded failed installations.
	 *
	 * Called on every wizard launch so a new run starts from a clean slate.
	 *
	 * @since 2.0.0
	 */
	public function clear(): void {

		delete_transient( self::TRANSIENT );
	}

	/**
	 * Register the recorded failures as dismissible admin notices.
	 *
	 * Hooked on `admin_notices` ahead of WPForms\Admin\Notice::display(): defers to
	 * {@see self::display_notices()} only on WPForms admin pages other than the form
	 * builder, and only while failures are still parked in the transient. The
	 * transient is written mid-run by the installation step and cleared on the next
	 * launch, so its presence means the user has returned from a wizard run that
	 * left plugins uninstalled.
	 *
	 * @since 2.0.0
	 */
	public function maybe_display(): void {

		// wpforms_is_admin_page() with no slug already excludes the form builder.
		if ( ! wpforms_is_admin_page() ) {
			return;
		}

		$this->display_notices( $this->get_failed() );
	}

	/**
	 * Register the failure notices for an installation run.
	 *
	 * Names the failed regular plugins inline, each linked to its WordPress.org
	 * repository page. The Pro subclass extends this with a separate notice for
	 * the failed addons.
	 *
	 * @since 2.0.0
	 *
	 * @param array $failed Failed installs as `plugin file => error message`.
	 */
	protected function display_notices( array $failed ): void {

		$plugins = [];

		foreach ( array_keys( $failed ) as $plugin ) {
			if ( $this->catalog->is_addon( $plugin ) ) {
				continue;
			}

			$plugins[] = $this->plugin_link( $plugin );
		}

		$this->add_notice( $this->plugins_message( $plugins ), self::NOTICE_SLUG_PLUGINS );
	}

	/**
	 * Register a single warning notice, skipping an empty message.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message Notice markup.
	 * @param string $slug    Dismissible notice slug.
	 */
	protected function add_notice( string $message, string $slug ): void {

		if ( $message === '' ) {
			return;
		}

		Notice::warning(
			$message,
			[
				'dismiss' => Notice::DISMISS_GLOBAL,
				'slug'    => $slug,
				'autop'   => false,
			]
		);
	}

	/**
	 * Read the recorded failed installations.
	 *
	 * @since 2.0.0
	 *
	 * @return array Failed installs as `plugin file => error message`.
	 */
	private function get_failed(): array {

		$failed = get_transient( self::TRANSIENT );

		return is_array( $failed ) ? $failed : [];
	}

	/**
	 * Wrap a plugin label with a link to its WordPress.org repository page.
	 *
	 * Falls back to the plain label when the catalog resolves no repository URL.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin Plugin file.
	 *
	 * @return string
	 */
	private function plugin_link( string $plugin ): string {

		$label = esc_html( $this->format_label( $plugin ) );
		$url   = $this->catalog->wporg_url( $plugin );

		if ( $url === '' ) {
			return $label;
		}

		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $url ),
			$label
		);
	}

	/**
	 * Message listing the failed regular plugins.
	 *
	 * @since 2.0.0
	 *
	 * @param array $plugins Plugin labels linked to WordPress.org.
	 *
	 * @return string Empty string when no regular plugins failed.
	 */
	private function plugins_message( array $plugins ): string {

		if ( empty( $plugins ) ) {
			return '';
		}

		return sprintf(
			'<p>%s</p>',
			sprintf(
				wp_kses( /* translators: %1$s - comma-separated list of plugin names linked to WordPress.org, %2$s - link to manual installation guide. */
					__( 'Could not install the following plugins during setup: %1$s. Please download and <a href="%2$s" target="_blank" rel="noopener noreferrer">install them manually</a>.', 'wpforms-lite' ),
					[
						'a' => [
							'href'   => true,
							'target' => true,
							'rel'    => true,
						],
					]
				),
				implode( ', ', $plugins ),
				esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-manually-install-addons-in-wpforms/', 'Setup Wizard Plugins Error', 'install them manually' ) )
			)
		);
	}

	/**
	 * Resolve a human-readable label for a failed install.
	 *
	 * Prefers the catalog name (keyed by plugin file), then the addon catalog
	 * name, and finally a humanized form of the identifier itself.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin Plugin file or addon slug.
	 *
	 * @return string
	 */
	protected function format_label( string $plugin ): string {

		$name = $this->catalog->name( $plugin );

		if ( $name !== '' ) {
			return $name;
		}

		$addons = wpforms()->obj( 'addons' );

		if ( $addons !== null && method_exists( $addons, 'get_addon' ) ) {
			$addon = $addons->get_addon( $plugin );

			if ( is_array( $addon ) && ! empty( $addon['name'] ) ) {
				return (string) $addon['name'];
			}
		}

		return ucwords( str_replace( [ '-', '_' ], ' ', $plugin ) );
	}
}
