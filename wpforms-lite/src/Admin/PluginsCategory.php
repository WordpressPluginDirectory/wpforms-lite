<?php

namespace WPForms\Admin;

/**
 * Adds a "WPForms" category tab to the WordPress Plugins screen.
 *
 * Uses the `plugins_list` filter to register an extra group containing
 * every WPForms-owned plugin (the main plugin and all `wpforms-*` addons),
 * and the `plugins_list_status_text` filter (introduced in WordPress 7.0)
 * to provide a friendly label for the new tab.
 *
 * On WordPress versions older than 7.0, the feature is disabled so
 * customers keep relying on the existing search / filter mechanism.
 *
 * @since 1.10.0.5
 */
class PluginsCategory {

	/**
	 * Status slug used as a key in `$plugins` and as `?plugin_status=` value.
	 *
	 * @since 1.10.0.5
	 */
	private const STATUS_KEY = 'wpforms';

	/**
	 * Slug prefix shared by the main plugin and all WPForms addons.
	 *
	 * @since 1.10.0.5
	 */
	private const SLUG_PREFIX = 'wpforms';

	/**
	 * Init.
	 *
	 * @since 1.10.0.5
	 */
	public function init(): void {

		if ( ! $this->is_supported() ) {
			return;
		}

		$this->hooks();
	}

	/**
	 * Whether the current request supports the new plugins category tab.
	 *
	 * Registers on the `plugins.php` screen and on the AJAX
	 * `search-plugins` handler invoked by the live-search input,
	 * so the filter stays scoped to WPForms when the user clears
	 * the search box (the 'X' icon) and the table is re-rendered
	 * via admin-ajax.
	 *
	 * @since 1.10.0.5
	 *
	 * @return bool
	 */
	private function is_supported(): bool {

		global $pagenow;

		$is_plugins_screen = ! empty( $pagenow ) && $pagenow === 'plugins.php';

		// The 'X' clear icon on the live-search input fires the
		// `search-plugins` AJAX action, which re-runs `plugins_list`
		// from admin-ajax.php. The nonce is verified inside the core
		// handler before our filter runs.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$is_plugins_search_ajax = wp_doing_ajax()
			&& isset( $_POST['action'], $_POST['pagenow'] )
			&& sanitize_key( $_POST['action'] ) === 'search-plugins'
			&& sanitize_key( $_POST['pagenow'] ) === 'plugins';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $is_plugins_screen && ! $is_plugins_search_ajax ) {
			return false;
		}

		return is_wp_version_compatible( '7.0' );
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.10.0.5
	 */
	private function hooks(): void {

		add_filter( 'plugins_list', [ $this, 'add_category' ] );
		add_filter( 'plugins_list_status_text', [ $this, 'category_label' ], 10, 3 );
	}

	/**
	 * Append a WPForms group to the plugins list used by the list table.
	 *
	 * @since 1.10.0.5
	 *
	 * @param array|mixed $plugins Plugins grouped by status (`all`, `active`, ...).
	 *
	 * @return array
	 */
	public function add_category( $plugins ): array {

		$plugins = (array) $plugins;

		if ( empty( $plugins['all'] ) || ! is_array( $plugins['all'] ) ) {
			return $plugins;
		}

		$wpforms_plugins = [];

		foreach ( $plugins['all'] as $file => $plugin_data ) {
			if ( ! is_array( $plugin_data ) ) {
				continue;
			}

			if ( $this->is_wpforms_plugin( (string) $file, $plugin_data ) ) {
				$wpforms_plugins[ $file ] = $plugin_data;
			}
		}

		if ( ! empty( $wpforms_plugins ) ) {
			$plugins[ self::STATUS_KEY ] = $wpforms_plugins;
		}

		return $plugins;
	}

	/**
	 * Provide the human-readable label for the WPForms category tab.
	 *
	 * The list table escapes the returned string and appends the count
	 * span, so the value must be plain text without HTML.
	 *
	 * @since 1.10.0.5
	 *
	 * @param string|mixed $text  Status text. Default empty string.
	 * @param int|mixed    $count Number of plugins in the category.
	 * @param string|mixed $type  The status slug being filtered.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function category_label( $text, $count, $type ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		if ( $type !== self::STATUS_KEY ) {
			return is_string( $text ) ? $text : '';
		}

		// "WPForms" is a brand name, but keep the call translatable so locales
		// can adjust casing if needed. The %s count is appended by core.
		return __( 'WPForms', 'wpforms-lite' );
	}

	/**
	 * Determine whether a plugin should be listed under the WPForms category.
	 *
	 * The default rule matches by folder slug: the main plugin (`wpforms`)
	 * and any `wpforms-*` addon. Third parties can override via the
	 * exposed filter without touching this class.
	 *
	 * @since 1.10.0.5
	 *
	 * @param string $file        Plugin file path (e.g. `wpforms-stripe/wpforms-stripe.php`).
	 * @param array  $plugin_data Plugin metadata as returned by `get_plugins()`.
	 *
	 * @return bool
	 */
	private function is_wpforms_plugin( string $file, array $plugin_data ): bool {

		$slug    = explode( '/', $file )[0];
		$is_ours = $slug === self::SLUG_PREFIX || strpos( $slug, self::SLUG_PREFIX . '-' ) === 0;

		/**
		 * Filters whether a plugin should be listed under the WPForms category tab.
		 *
		 * @since 1.10.0.5
		 *
		 * @param bool   $is_ours     Whether the plugin is recognised as a WPForms plugin.
		 * @param string $file        Plugin file path (e.g. `wpforms-stripe/wpforms-stripe.php`).
		 * @param array  $plugin_data Plugin metadata as returned by `get_plugins()`.
		 *
		 * @return bool Whether the plugin should appear under the WPForms tab.
		 */
		return (bool) apply_filters( 'wpforms_admin_plugins_category_is_wpforms_plugin', $is_ours, $file, $plugin_data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
