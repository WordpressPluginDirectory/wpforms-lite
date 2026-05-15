<?php

namespace WPForms\Education\ActiveLayer;

/**
 * Records the install/activate source on first-time activation of the
 * ActiveLayer plugin via WPForms's product education flows. Mirrors the
 * `<plugin_name>_source` + `<plugin_name>_date` convention used for the
 * other partner plugins (WPConsent, UncannyAutomator, Duplicator,
 * SugarCalendar) — see
 * \WPForms\Integrations\UsageTracking\UsageTracking::add_promotion_plugin_data()
 * for the corresponding reader.
 *
 * The two options are picked up by the WPForms Usage Tracking opt-in
 * payload (weekly POST to wpformsusage.com/v1/track) so installs
 * originating from the Form Builder card / CAPTCHA notice / first-spam
 * admin notice can be attributed.
 *
 * @since 1.10.0.5
 */
class InstallTracker {

	/**
	 * Option key — install source ('WPForms' or 'WPForms Lite').
	 *
	 * @since 1.10.0.5
	 */
	const SOURCE_OPTION = 'activelayer_source';

	/**
	 * Option key — unix timestamp of the first activation through WPForms.
	 *
	 * @since 1.10.0.5
	 */
	const DATE_OPTION = 'activelayer_date';

	/**
	 * Init.
	 *
	 * @since 1.10.0.5
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.10.0.5
	 */
	private function hooks() {

		add_action( 'wpforms_plugin_activated', [ $this, 'maybe_record_source' ] );
	}

	/**
	 * Write `activelayer_source` and `activelayer_date` when the activated
	 * plugin is ActiveLayer. Idempotent — once a source is recorded, later
	 * activations through WPForms do NOT overwrite it.
	 *
	 * @since 1.10.0.5
	 *
	 * @param string $plugin_basename Path to the activated plugin file relative to the plugins directory.
	 */
	public function maybe_record_source( $plugin_basename ) {

		if ( ! is_string( $plugin_basename ) || $plugin_basename === '' ) {
			return;
		}

		// Match anything under our slug folder; the main-file basename can vary.
		if ( strpos( $plugin_basename, Helper::SLUG . '/' ) !== 0 ) {
			return;
		}

		// Don't overwrite a previously recorded source.
		if ( get_option( self::SOURCE_OPTION ) ) {
			return;
		}

		$source = wpforms()->is_pro() ? 'WPForms' : 'WPForms Lite';

		update_option( self::SOURCE_OPTION, $source, false );
		update_option( self::DATE_OPTION, time(), false );
	}
}
