<?php

namespace WPForms\SetupWizard\Service;

/**
 * Setup Wizard plugin detector.
 *
 * Reports installed/active status for the two plugin groups the wizard cares
 * about: cross-product plugins surfaced on the cross-plugins step (WP Mail
 * SMTP, WPConsent) and form plugins the wizard can import from (Contact
 * Form 7, Ninja Forms, Pirate Forms). The plugin-file map for
 * both groups lives in `PluginCatalog`. Lite and Pro variants shipped under
 * separate folders are folded under a single reporting plugin file: it counts
 * as installed/active when any variant is.
 *
 * @since 2.0.0
 */
class PluginDetector {

	/**
	 * Plugin catalog.
	 *
	 * @since 2.0.0
	 *
	 * @var PluginCatalog
	 */
	private $catalog;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		$this->catalog = new PluginCatalog();
	}

	/**
	 * Report installed/active status for cross-product plugins.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array{installed: bool, active: bool}>
	 */
	public function cross_plugins(): array {

		return $this->inspect_plugins( $this->catalog->cross_plugin_files() );
	}

	/**
	 * Report installed/active status for forms plugins.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array{installed: bool, active: bool}>
	 */
	public function forms_plugins(): array {

		return $this->inspect_plugins( $this->catalog->forms_plugin_files() );
	}

	/**
	 * Report installed/active status for a single arbitrary plugin file.
	 *
	 * Catalog-free counterpart to {@see PluginDetector::cross_plugins()} and
	 * {@see PluginDetector::forms_plugins()}: resolves any plugin file
	 * (`{directory}/{main-file.php}`) so single-plugin checks and recommended-plugin
	 * promos can share one detector instead of re-rolling `is_plugin_active()` and
	 * `file_exists()`.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin file relative to `WP_PLUGIN_DIR`.
	 *
	 * @return array{installed: bool, active: bool}
	 */
	public function status( string $plugin_file ): array {

		return $this->inspect_plugin( [ $plugin_file ] );
	}

	/**
	 * Report installed/active status for a plugin-file => detection-files map.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, array<int, string>> $plugins Reporting plugin file => detection file paths.
	 *
	 * @return array<string, array{installed: bool, active: bool}>
	 */
	private function inspect_plugins( array $plugins ): array {

		return array_map(
			function ( $files ) {
				return $this->inspect_plugin( $files );
			},
			$plugins
		);
	}

	/**
	 * Report installed/active status across all variants of a single slug.
	 *
	 * Returns `installed: true` if any path is on disk and `active: true` if
	 * any of those installed variants is active. This lets a single plugin file
	 * stand in for Lite and Pro builds shipped under different folders.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, string> $plugin_files Plugin files relative to `WP_PLUGIN_DIR`.
	 *
	 * @return array{installed: bool, active: bool}
	 */
	private function inspect_plugin( array $plugin_files ): array {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins_dir = trailingslashit( WP_PLUGIN_DIR );
		$installed   = false;
		$active      = false;

		foreach ( $plugin_files as $plugin_file ) {
			if ( ! file_exists( $plugins_dir . $plugin_file ) ) {
				continue;
			}

			$installed = true;

			if ( is_plugin_active( $plugin_file ) ) {
				$active = true;

				break;
			}
		}

		return [
			'installed' => $installed,
			'active'    => $active,
		];
	}
}
