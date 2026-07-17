<?php

namespace WPForms\Education\WPConsent;

/**
 * Records the install/activate source on first-time activation of WPConsent
 * via WPForms's product education flows. Mirrors the
 * `<plugin_name>_source` + `<plugin_name>_date` convention used for the
 * other partner plugins — see
 * \WPForms\Integrations\UsageTracking\UsageTracking::add_promotion_plugin_data()
 * for the corresponding reader.
 *
 * The two options are picked up by the WPForms Usage Tracking opt-in
 * payload (weekly POST to wpformsusage.com/v1/track) so installs
 * originating from WPForms product education flows can be attributed.
 *
 * @since 2.0.0
 */
class InstallTracker {

	/**
	 * Option key — install source ('WPForms' or 'WPForms Lite').
	 *
	 * @since 2.0.0
	 */
	const SOURCE_OPTION = 'wpconsent_source';

	/**
	 * Option key — unix timestamp of the first activation through WPForms.
	 *
	 * @since 2.0.0
	 */
	const DATE_OPTION = 'wpconsent_date';

	/**
	 * Init.
	 *
	 * @since 2.0.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	private function hooks() {

		add_action( 'wpforms_plugin_activated', [ $this, 'maybe_record_source' ] );
	}

	/**
	 * Write `wpconsent_source` and `wpconsent_date` when the activated
	 * plugin is WPConsent. Idempotent — once a source is recorded, later
	 * activations through WPForms do NOT overwrite it.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_basename Path to the activated plugin file relative to the plugins directory.
	 */
	public function maybe_record_source( $plugin_basename ) {

		if ( ! is_string( $plugin_basename ) || $plugin_basename === '' ) {
			return;
		}

		// Match anything under the lite or pro WPConsent folders; the main-file basename can vary.
		if ( strpos( $plugin_basename, Helper::SLUG . '/' ) !== 0 && strpos( $plugin_basename, 'wpconsent-premium/' ) !== 0 ) {
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
