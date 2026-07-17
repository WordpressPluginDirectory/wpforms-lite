<?php

namespace WPForms\Education\WPConsent;

/**
 * WPConsent plugin state and CTA-data helper.
 *
 * Single source of truth for WPConsent education surfaces: detects
 * install/active state, exposes the WordPress.org install zip URL, the
 * post-install onboarding URL, and the data-* attribute payload the inline
 * install handler understands. Mirrors WPForms\Education\ActiveLayer\Helper.
 *
 * @since 2.0.0
 */
class Helper {

	/**
	 * Lite plugin folder slug.
	 *
	 * @since 2.0.0
	 */
	const SLUG = 'wpconsent-cookies-banner-privacy-suite';

	/**
	 * Lite plugin basename.
	 *
	 * @since 2.0.0
	 */
	const LITE_BASENAME = 'wpconsent-cookies-banner-privacy-suite/wpconsent.php';

	/**
	 * Pro plugin basename.
	 *
	 * @since 2.0.0
	 */
	const PRO_BASENAME = 'wpconsent-premium/wpconsent-premium.php';

	/**
	 * Canonical WordPress.org zip download URL.
	 *
	 * @since 2.0.0
	 */
	const INSTALL_ZIP_URL = 'https://downloads.wordpress.org/plugin/wpconsent-cookies-banner-privacy-suite.zip';

	/**
	 * Onboarding wizard admin page slug.
	 *
	 * @since 2.0.0
	 */
	const ONBOARDING_PAGE = 'wpconsent-onboarding';

	/**
	 * Cached resolved plugin basename.
	 *
	 * @since 2.0.0
	 *
	 * @var string|null
	 */
	private static $basename = null;

	/**
	 * Resolve the installed WPConsent basename (lite or pro), '' if absent.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function get_basename(): string {

		if ( self::$basename !== null ) {
			return self::$basename;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		self::$basename = '';

		foreach ( array_keys( get_plugins() ) as $basename ) {
			$folder = strtok( $basename, '/' );

			if ( $folder === self::SLUG || $folder === 'wpconsent-premium' ) {
				self::$basename = $basename;

				break;
			}
		}

		return self::$basename;
	}

	/**
	 * Whether the WPConsent plugin folder is present.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_installed(): bool {

		return self::get_basename() !== '';
	}

	/**
	 * Whether WPConsent (lite or pro) is currently active.
	 *
	 * @since 2.0.0
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
	 * Whether WPConsent code is loaded.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_available(): bool {

		return function_exists( 'wpconsent' );
	}

	/**
	 * Onboarding wizard URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function get_setup_url(): string {

		return admin_url( 'admin.php?page=' . self::ONBOARDING_PAGE );
	}

	/**
	 * Data-* payload for the inline install/activate button.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public static function get_install_button_data(): array {

		$nonce = wp_create_nonce( 'wpforms-admin' );

		if ( self::is_installed() ) {
			return [
				'action' => 'activate',
				'attrs'  => [
					'data-action' => 'activate',
					'data-path'   => self::get_basename(),
					'data-type'   => 'plugin',
					'data-nonce'  => $nonce,
				],
			];
		}

		return [
			'action' => 'install',
			'attrs'  => [
				'data-action' => 'install',
				'data-url'    => self::INSTALL_ZIP_URL,
				'data-type'   => 'plugin',
				'data-nonce'  => $nonce,
			],
		];
	}
}
