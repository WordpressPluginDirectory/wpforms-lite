<?php

namespace WPForms\Education\SugarCalendar;

/**
 * Sugar Calendar plugin state and CTA-data helper.
 *
 * Single source of truth for Sugar Calendar education surfaces: detects
 * install state, exposes the WordPress.org install zip URL, the
 * post-install onboarding URL, and the data-* attribute payload the promo
 * banner install handler understands. Mirrors WPForms\Education\WPConsent\Helper.
 *
 * @since 2.0.0.3
 */
class Helper {

	/**
	 * Lite plugin folder slug.
	 *
	 * @since 2.0.0.3
	 */
	const SLUG = 'sugar-calendar-lite';

	/**
	 * Pro plugin folder slug.
	 *
	 * @since 2.0.0.3
	 */
	const PRO_SLUG = 'sugar-calendar';

	/**
	 * Canonical WordPress.org zip download URL.
	 *
	 * @since 2.0.0.3
	 */
	const INSTALL_ZIP_URL = 'https://downloads.wordpress.org/plugin/sugar-calendar-lite.zip';

	/**
	 * WordPress.org plugin page URL.
	 *
	 * @since 2.0.0.3
	 */
	const WPORG_URL = 'https://wordpress.org/plugins/sugar-calendar-lite/';

	/**
	 * Onboarding (Getting Started) admin page slug.
	 *
	 * @since 2.0.0.3
	 */
	const ONBOARDING_PAGE = 'sugar-calendar-getting-started';

	/**
	 * Form templates categories targeted by the Sugar Calendar promo banners.
	 *
	 * @since 2.0.0.3
	 */
	const TEMPLATES_CATEGORIES = [ 'event-planning', 'registrations' ];

	/**
	 * Cached resolved plugin basename.
	 *
	 * @since 2.0.0.3
	 *
	 * @var string|null
	 */
	private static $basename = null;

	/**
	 * Resolve the installed Sugar Calendar basename (lite or pro), '' if absent.
	 *
	 * @since 2.0.0.3
	 *
	 * @return string
	 */
	private static function get_basename(): string {

		if ( self::$basename !== null ) {
			return self::$basename;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		self::$basename = '';

		foreach ( array_keys( get_plugins() ) as $basename ) {
			$folder = strtok( $basename, '/' );

			if ( $folder === self::SLUG || $folder === self::PRO_SLUG ) {
				self::$basename = $basename;

				break;
			}
		}

		return self::$basename;
	}

	/**
	 * Whether a Sugar Calendar plugin folder (Lite or Pro) is present.
	 *
	 * @since 2.0.0.3
	 *
	 * @param bool $refresh Whether to resolve the basename again, for callers that changed
	 *                      the installed plugins in the current request.
	 *
	 * @return bool
	 */
	public static function is_installed( bool $refresh = false ): bool {

		if ( $refresh ) {
			self::$basename = null;
		}

		return self::get_basename() !== '';
	}

	/**
	 * Onboarding wizard URL.
	 *
	 * @since 2.0.0.3
	 *
	 * @return string
	 */
	public static function get_setup_url(): string {

		return admin_url( 'admin.php?page=' . self::ONBOARDING_PAGE );
	}

	/**
	 * Data-* payload for the promo banner install button.
	 *
	 * Only the install state is covered: the banners never render once the
	 * plugin is installed, so there is nothing to activate from them.
	 *
	 * @since 2.0.0.3
	 *
	 * @return array
	 */
	public static function get_install_button_data(): array {

		return [
			'url'   => self::INSTALL_ZIP_URL,
			'type'  => 'plugin',
			'nonce' => wp_create_nonce( 'wpforms-admin' ),
		];
	}
}
