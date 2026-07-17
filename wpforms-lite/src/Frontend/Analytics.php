<?php

namespace WPForms\Frontend;

use WPForms\Analytics\Analytics as AnalyticsGate;

/**
 * Form Analytics frontend script enqueue.
 *
 * Enqueues the base analytics.js tracker on pages that render a WPForms form.
 *
 * @since 2.0.0
 */
class Analytics {

	/**
	 * Start the engine.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 2.0.0
	 */
	protected function hooks(): void {

		add_action( 'wpforms_wp_footer', [ $this, 'enqueue_footer_assets' ] );
		add_filter( 'wpforms_frontend_strings', [ $this, 'add_composite_field_types' ] );
	}

	/**
	 * Inject the composite-field type allow-list into the frontend config.
	 *
	 * The tracker explodes only these field types into per-subfield rows.
	 * Addons (e.g. Surveys & Polls) extend the list via the filter below.
	 *
	 * @since 2.0.0
	 *
	 * @param array $strings Localized frontend strings/config.
	 *
	 * @return array
	 */
	public function add_composite_field_types( $strings ): array {

		$strings = (array) $strings;

		$field_types = [
			'name',
			'address',
			'date-time',
			'email',
			'password',
		];

		/**
		 * Filters the field types tracked per subfield by Form Analytics.
		 *
		 * @since 2.0.0
		 *
		 * @param array $types Field type slugs whose subfields are tracked individually.
		 */
		$strings['analytics_composite_field_types'] = (array) apply_filters(
			'wpforms_frontend_analytics_composite_field_types',
			$field_types
		);

		/**
		 * Filters whether the analytics submission envelope is base64-encoded.
		 *
		 * When true, the hidden analytics input is base64-encoded instead of raw
		 * JSON. This prevents WAF products (e.g. Cloudflare OWASP CRS) from
		 * flagging the nested JSON structure as a false-positive anomaly.
		 *
		 * @since 2.0.0.2
		 *
		 * @param bool $enabled Whether to base64-encode the analytics envelope.
		 */
		$strings['analytics_base64'] = (bool) apply_filters(
			'wpforms_frontend_analytics_base64_envelope',
			true
		);

		return $strings;
	}

	/**
	 * Enqueue frontend analytics assets.
	 *
	 * @since 2.0.0
	 *
	 * @param array $forms List of forms on the page.
	 */
	public function enqueue_footer_assets( array $forms ): void {

		if ( empty( $forms ) ) {
			return;
		}

		// Skip site staff (Author/Editor/Admin).
		if ( ! AnalyticsGate::should_track_user() ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-analytics',
			WPFORMS_PLUGIN_URL . "assets/js/frontend/analytics{$min}.js",
			[ 'jquery' ],
			WPFORMS_VERSION,
			true
		);
	}
}
