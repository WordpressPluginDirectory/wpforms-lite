<?php

namespace WPForms\SetupWizard\Service;

/**
 * Setup Wizard install gateway.
 *
 * Resolves each SPA plugin file against `PluginCatalog` and hands it to
 * `wpforms_install_plugin()`, which downloads it from WordPress.org (or just
 * activates it when already present) and activates it. The outcome is
 * partitioned into installed/failed for the SPA.
 *
 * @since 2.0.0
 */
class PluginInstaller {

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
	 * Install a list of plugin files and partition the outcome for the SPA.
	 *
	 * @since 2.0.0
	 *
	 * @param array $plugin_files Plugin files requested by the SPA.
	 *
	 * @return array {
	 *     Per-plugin install result keyed by the original SPA plugin file.
	 *
	 *     @type array $installed Plugin files installed (or already present) and activated.
	 *     @type array $failed    Plugin files that failed, plugin file => error message.
	 * }
	 */
	public function install( array $plugin_files ): array {

		$plugin_files = array_values( array_filter( array_map( 'wpforms_sanitize_key', $plugin_files ) ) );
		$installed    = [];
		$failed       = [];

		foreach ( $plugin_files as $plugin_file ) {
			$result = wpforms_install_plugin( $this->catalog->main_file( $plugin_file ) );

			if ( is_wp_error( $result ) ) {
				$failed[ $plugin_file ] = $result->get_error_message();

				continue;
			}

			$installed[] = $plugin_file;
		}

		return [
			'installed' => $installed,
			'failed'    => $failed,
		];
	}
}
