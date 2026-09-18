<?php

namespace WPForms\Admin\Settings\Captcha;

use WPForms\Admin\Notice;

/**
 * CAPTCHA configuration error flag and the related admin notice.
 *
 * A provider rejecting the site's secret key, or being unreachable, is a site
 * owner problem rather than a spam signal, so it is stored here and surfaced
 * as a persistent dismissible admin notice.
 *
 * @since 2.0.2
 */
class ConfigurationError {

	/**
	 * Option holding the current configuration error details.
	 *
	 * @since 2.0.2
	 */
	const OPTION = 'wpforms_captcha_configuration_error';

	/**
	 * Notice slug.
	 *
	 * @since 2.0.2
	 */
	const SLUG = 'captcha-configuration-error';

	/**
	 * CAPTCHA settings that decide which credentials are sent for verification,
	 * each mapped to the value the plugin falls back to when it is not stored.
	 *
	 * A change to any of them means the owner has just reconfigured the CAPTCHA.
	 * The provider and the reCAPTCHA type belong here as much as the keys do:
	 * disabling the CAPTCHA or moving to another provider retires the error
	 * just as surely as correcting a secret key.
	 *
	 * The fallbacks are what make the comparison trustworthy. A CAPTCHA tab save
	 * writes the defaults of the providers the site does not use, so `recaptcha-type`
	 * arrives as `v2` and `captcha-provider` as `recaptcha` on a site that never
	 * stored either. Comparing raw values would read that as a reconfiguration.
	 *
	 * @since 2.0.2
	 */
	const CREDENTIAL_SETTINGS = [
		'captcha-provider'     => 'recaptcha',
		'recaptcha-type'       => 'v2',
		'recaptcha-site-key'   => '',
		'recaptcha-secret-key' => '',
		'hcaptcha-site-key'    => '',
		'hcaptcha-secret-key'  => '',
		'turnstile-site-key'   => '',
		'turnstile-secret-key' => '',
	];

	/**
	 * Store the configuration error.
	 *
	 * @since 2.0.2
	 *
	 * @param string $reason   Error reason: 'invalid-secret' or 'unreachable'.
	 * @param string $provider Human-readable provider name.
	 */
	public static function flag( string $reason, string $provider ): void {

		update_option(
			self::OPTION,
			[
				'reason'   => $reason,
				'provider' => $provider,
			],
			true
		);
	}

	/**
	 * Retire the configuration error.
	 *
	 * Also drops the notice dismissal record, so dismissing acknowledges only
	 * the current occurrence and a later one is shown again.
	 *
	 * @since 2.0.2
	 */
	public static function clear(): void {

		// The option is autoloaded, so this read is served from the cache.
		if ( get_option( self::OPTION ) === false ) {
			return;
		}

		delete_option( self::OPTION );

		$dismissed = (array) get_option( 'wpforms_admin_notices', [] );
		$slug      = sanitize_key( self::SLUG );

		if ( ! isset( $dismissed[ $slug ] ) ) {
			return;
		}

		unset( $dismissed[ $slug ] );

		update_option( 'wpforms_admin_notices', $dismissed );
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.2
	 */
	public function hooks(): void {

		add_action( 'admin_notices', [ $this, 'notice' ] );
		add_action( 'wpforms_settings_updated', [ $this, 'clear_on_settings_update' ], 10, 3 );
	}

	/**
	 * Register the configuration error notice.
	 *
	 * @since 2.0.2
	 */
	public function notice(): void {

		$error = get_option( self::OPTION );

		if ( empty( $error['reason'] ) || empty( $error['provider'] ) ) {
			return;
		}

		if ( ! current_user_can( wpforms_get_capability_manage_options() ) ) {
			return;
		}

		$provider      = esc_html( $error['provider'] );
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=wpforms-settings&view=captcha' ) ),
			esc_html__( 'CAPTCHA settings', 'wpforms-lite' )
		);
		$args          = [
			'dismiss' => Notice::DISMISS_GLOBAL,
			'slug'    => self::SLUG,
		];

		if ( $error['reason'] === 'invalid-secret' ) {
			Notice::error(
				sprintf(
					/* translators: %1$s - CAPTCHA provider name, %2$s - link to the CAPTCHA settings screen. */
					esc_html__( '%1$s is rejecting your secret key, so form submissions are being blocked. Please check your %2$s.', 'wpforms-lite' ),
					$provider,
					$settings_link
				),
				$args
			);

			return;
		}

		Notice::warning(
			sprintf(
				/* translators: %1$s - CAPTCHA provider name, %2$s - link to the CAPTCHA settings screen. */
				esc_html__( 'We couldn\'t reach %1$s to verify your secret key. Test a form submission to confirm your CAPTCHA is working, or review your %2$s.', 'wpforms-lite' ),
				$provider,
				$settings_link
			),
			$args
		);
	}

	/**
	 * Retire the configuration error once the CAPTCHA configuration is changed.
	 *
	 * The `wpforms_settings_updated` action fires on every settings tab save,
	 * hence the comparison against the previous values.
	 *
	 * @since 2.0.2
	 *
	 * @param array $settings     An array of plugin settings.
	 * @param bool  $updated      Whether an option was updated or not.
	 * @param array $old_settings An old array of plugin settings.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function clear_on_settings_update( $settings, $updated, $old_settings ): void {

		$settings     = (array) $settings;
		$old_settings = (array) $old_settings;

		foreach ( self::CREDENTIAL_SETTINGS as $setting => $fallback ) {
			if ( $this->effective_value( $settings, $setting, $fallback ) !== $this->effective_value( $old_settings, $setting, $fallback ) ) {
				self::clear();

				return;
			}
		}
	}

	/**
	 * Resolve the value the plugin would act on for the given setting.
	 *
	 * A setting absent from the array, and one stored empty, both mean the owner never
	 * chose anything, so both resolve to the fallback. That is what keeps a save which
	 * merely materializes a never-configured setting from retiring the error on a site
	 * that is still broken.
	 *
	 * The `wpforms_update_settings` filter runs immediately before the action this feeds,
	 * so a third party can leave any value under any key. A non-scalar is not a CAPTCHA
	 * setting the plugin could act on, and casting one would warn or throw, so it counts
	 * as nothing chosen.
	 *
	 * @since 2.0.2
	 *
	 * @param array  $settings An array of plugin settings.
	 * @param string $setting  Setting to read.
	 * @param string $fallback Value the plugin falls back to when the setting is not stored.
	 *
	 * @return string
	 */
	private function effective_value( array $settings, string $setting, string $fallback ): string {

		$value = $settings[ $setting ] ?? '';
		$value = is_scalar( $value ) ? (string) $value : '';

		return $value === '' ? $fallback : $value;
	}
}
