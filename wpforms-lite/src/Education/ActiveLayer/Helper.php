<?php

namespace WPForms\Education\ActiveLayer;

/**
 * ActiveLayer plugin state and CTA-data helper.
 *
 * Single source of truth for ActiveLayer education surfaces: detects
 * install/active state, exposes the WordPress.org install zip URL, the
 * post-install dashboard URL, and the data-* attribute payload that the
 * existing Education modal JS understands.
 *
 * @since 1.10.0.5
 */
class Helper {

	/**
	 * WordPress.org plugin slug. Matches the folder name created on install.
	 *
	 * @since 1.10.0.5
	 */
	const SLUG = 'activelayer-anti-spam-spam-protection-for-forms-comments';

	/**
	 * Canonical WordPress.org zip download URL.
	 *
	 * @since 1.10.0.5
	 */
	const INSTALL_ZIP_URL = 'https://downloads.wordpress.org/plugin/activelayer-anti-spam-spam-protection-for-forms-comments.zip';

	/**
	 * Admin page slug ActiveLayer registers as its top-level dashboard menu.
	 *
	 * @since 1.10.0.5
	 */
	const DASHBOARD_PAGE_SLUG = 'activelayer-dashboard';

	/**
	 * Cached plugin basename ('folder/main-file.php') once resolved.
	 *
	 * @since 1.10.0.5
	 *
	 * @var string|null
	 */
	private static $basename = null;

	/**
	 * Whether ActiveLayer is present in the plugins directory.
	 *
	 * @since 1.10.0.5
	 *
	 * @return bool
	 */
	public static function is_installed(): bool {

		return self::get_basename() !== '';
	}

	/**
	 * Whether ActiveLayer is currently active.
	 *
	 * @since 1.10.0.5
	 *
	 * @return bool
	 */
	public static function is_activated(): bool {

		$basename = self::get_basename();

		if ( $basename === '' ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $basename );
	}

	/**
	 * Resolve ActiveLayer's plugin basename ('slug/main-file.php') by
	 * scanning get_plugins() for an entry whose folder matches our slug.
	 * The main file inside the folder may not match the slug — never
	 * hard-code it.
	 *
	 * @since 1.10.0.5
	 *
	 * @return string Plugin basename, or empty string if not installed.
	 */
	public static function get_basename(): string {

		if ( self::$basename !== null ) {
			return self::$basename;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$prefix = self::SLUG . '/';

		foreach ( array_keys( get_plugins() ) as $candidate ) {
			if ( strpos( $candidate, $prefix ) === 0 ) {
				self::$basename = $candidate;

				return self::$basename;
			}
		}

		self::$basename = '';

		return self::$basename;
	}

	/**
	 * WordPress.org zip URL — used as the `plugin` argument the
	 * `wpforms_install_addon` AJAX handler passes to PluginSilentUpgrader.
	 *
	 * @since 1.10.0.5
	 *
	 * @return string
	 */
	public static function get_install_zip_url(): string {

		return self::INSTALL_ZIP_URL;
	}

	/**
	 * Admin URL of the ActiveLayer dashboard menu page. Callers should only
	 * render this link when `is_activated()` is true; we don't validate that
	 * the page actually exists.
	 *
	 * @since 1.10.0.5
	 *
	 * @return string
	 */
	public static function get_dashboard_url(): string {

		return admin_url( 'admin.php?page=' . self::DASHBOARD_PAGE_SLUG );
	}

	/**
	 * Build the ($link, $link_text, $class, $attrs) payload consumed by
	 * any surface that wants to show the ActiveLayer CTA.
	 *
	 * Three discrete states:
	 *   - Not installed → 'install' modal action, points at the WP.org zip.
	 *   - Installed but inactive → 'activate' modal action, points at the basename.
	 *   - Active → plain link to the ActiveLayer dashboard, no modal.
	 *
	 * The 'attrs' array is rendered as data-* attributes by callers; the
	 * keys already include the `data-` prefix so the template can iterate
	 * them blindly.
	 *
	 * @since 1.10.0.5
	 *
	 * @return array{link:string,link_text:string,class:string,attrs:array<string,string>}
	 */
	public static function get_modal_data(): array {

		// Installed AND active → plain dashboard link, no modal.
		if ( self::is_activated() ) {
			return [
				'link'      => self::get_dashboard_url(),
				'link_text' => __( 'Dashboard', 'wpforms-lite' ),
				'class'     => '',
				'attrs'     => [],
			];
		}

		$nonce = wp_create_nonce( 'wpforms-admin' );

		// Installed but inactive → activate modal.
		// JS reads $button.data('path') → attribute MUST be `data-path`.
		// Verified against `activateAddon()` in
		// `assets/js/admin/education/core.js` — reads $button.data('path').
		if ( self::is_installed() ) {
			return [
				'link'      => '#',
				'link_text' => __( 'Get Started &rarr;', 'wpforms-lite' ),
				'class'     => 'education-modal',
				'attrs'     => [
					'data-action' => 'activate',
					'data-name'   => 'ActiveLayer plugin',
					'data-path'   => self::get_basename(),
					'data-type'   => 'plugin',
					'data-nonce'  => $nonce,
				],
			];
		}

		// Not installed → install modal pointing at the WP.org zip.
		// JS reads $button.data('url') → attribute MUST be `data-url`.
		return [
			'link'      => '#',
			'link_text' => __( 'Get Started &rarr;', 'wpforms-lite' ),
			'class'     => 'education-modal',
			'attrs'     => [
				'data-action' => 'install',
				'data-name'   => 'ActiveLayer plugin',
				'data-url'    => self::get_install_zip_url(),
				'data-type'   => 'plugin',
				'data-nonce'  => $nonce,
			],
		];
	}
}
