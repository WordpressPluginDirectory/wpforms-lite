<?php

namespace WPForms\Admin\Dashboard;

use DateTimeImmutable;
use WPForms\Admin\Dashboard\Widgets\AbstractWidget;
use WPForms\Admin\Dashboard\Widgets\Entries;
use WPForms\Admin\Helpers\Datepicker;

/**
 * Dashboard AJAX endpoints (base).
 *
 * @since 2.0.2
 */
class Ajax {

	/**
	 * Access resolver. Lazy-initialized — not registered during AJAX requests.
	 *
	 * @since 2.0.2
	 *
	 * @var AccessResolver|null
	 */
	private $access_resolver;

	/**
	 * Register hooks.
	 *
	 * @since 2.0.2
	 */
	public function hooks(): void {

		add_action( 'wp_ajax_wpforms_dashboard_save_widget_settings', [ $this, 'save_widget_settings' ] );
		add_action( 'wp_ajax_wpforms_dashboard_reset_widget_settings', [ $this, 'reset_widget_settings' ] );
		add_action( 'wp_ajax_wpforms_dashboard_get_entries_widget_html', [ $this, 'get_entries_widget_html' ] );
		add_action( 'wp_ajax_wpforms_dashboard_payments_save_selected_report', [ $this, 'payments_save_selected_report' ] );

		add_filter( PaymentStats::WHERE_ARGS_FILTER, [ $this, 'pin_dashboard_chart_requests_to_live_mode' ], PHP_INT_MAX );
	}

	/**
	 * Force live mode when the Payments Overview chart endpoint is serving the
	 * Dashboard's Payments widget (see the mode rationale on `PaymentStats`).
	 *
	 * @since 2.0.2
	 *
	 * @param array|mixed $args Query arguments.
	 *
	 * @return array
	 */
	public function pin_dashboard_chart_requests_to_live_mode( $args ): array {

		$args = (array) $args;

		if ( ! $this->is_dashboard_chart_request() ) {
			return $args;
		}

		$args['mode'] = 'live';

		return $args;
	}

	/**
	 * Whether the current request is the Dashboard's fetch from the Payments Overview
	 * chart endpoint.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_dashboard_chart_request(): bool {

		static $is_dashboard_request;

		if ( $is_dashboard_request !== null ) {
			return $is_dashboard_request;
		}

		// Nonce verification is owned by the endpoint itself; this only routes the mode.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$action  = sanitize_key( $_POST['action'] ?? '' );
		$context = sanitize_key( $_POST['context'] ?? '' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$is_dashboard_request = $action === 'wpforms_payments_overview_refresh_chart_dataset_data' && $context === 'dashboard';

		return $is_dashboard_request;
	}

	/**
	 * Verify the nonce and capability shared by every dashboard AJAX action.
	 *
	 * @since 2.0.2
	 *
	 * @return AccessContext
	 */
	protected function validate_request(): AccessContext {

		if ( ! check_ajax_referer( 'wpforms-dashboard', 'nonce', false ) ) {
			$this->send_error( 'nonce', esc_html__( 'Your session has expired. Please reload the page.', 'wpforms-lite' ) );
		}

		$access = $this->get_access();

		if ( ! $access->can_manage() ) {
			$this->send_error( 'cap', esc_html__( 'You do not have permission to do this.', 'wpforms-lite' ) );
		}

		return $access;
	}

	/**
	 * Persist a per-widget settings map to user meta.
	 *
	 * @since 2.0.2
	 */
	public function save_widget_settings(): void {

		$this->validate_request();

		// Nonce verified via validate_request() before this runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$widget_id = sanitize_key( $_POST['widget'] ?? '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via validate_request() before this runs.
		$settings = ! empty( $_POST['settings'] ) ? map_deep( (array) wp_unslash( $_POST['settings'] ), 'sanitize_text_field' ) : [];

		if ( $widget_id === '' ) {
			$this->send_error( 'invalid', esc_html__( 'Invalid widget.', 'wpforms-lite' ) );
		}

		$all      = Helpers::get_user_meta_array( AbstractWidget::SETTINGS_META_KEY );
		$existing = (array) ( $all[ $widget_id ] ?? [] );

		// The gear form posts only the fields it rendered, so a setting it did not carry
		// keeps its stored value instead of being wiped. That covers a state managed
		// outside the gear, the Entries widget's graph selection and the Payments widget's
		// selected report, and a field the current view had nothing to render, like the
		// Locations exclude list on a range with no location data. A rendered field always
		// submits, an array via its hidden sentinel, so an absent key is never the user
		// clearing it.
		$all[ $widget_id ] = $settings + $existing;

		Helpers::update_user_meta_array( AbstractWidget::SETTINGS_META_KEY, $all );

		$widget = $this->get_gear_widget( $widget_id );

		// The reset control follows the saved state, and the widgets that re-render
		// client-side never rebuild the popover markup that would carry it.
		wp_send_json_success(
			[
				'has_custom_settings' => $widget && $widget->has_custom_settings(),
			]
		);
	}

	/**
	 * Drop a widget's stored settings, which puts it back on its defaults.
	 *
	 * @since 2.0.2
	 */
	public function reset_widget_settings(): void {

		$this->validate_request();

		// Nonce verified via validate_request() before this runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$widget_id = sanitize_key( $_POST['widget'] ?? '' );

		if ( $widget_id === '' ) {
			$this->send_error( 'invalid', esc_html__( 'Invalid widget.', 'wpforms-lite' ) );
		}

		$widget   = $this->get_gear_widget( $widget_id );
		$defaults = $widget ? $widget->get_default_settings() : [];
		$all      = Helpers::get_user_meta_array( AbstractWidget::SETTINGS_META_KEY );

		// Only the keys the gear owns are dropped. The same bucket carries state the
		// popover never shows — the Payments widget's last-viewed report, the Entries
		// graph's active form — and a settings reset has no business forgetting it.
		$all[ $widget_id ] = array_diff_key( (array) ( $all[ $widget_id ] ?? [] ), $defaults );

		Helpers::update_user_meta_array( AbstractWidget::SETTINGS_META_KEY, $all );

		// The defaults travel back so the client can put the popover and the widget on
		// them through the same path a save takes.
		wp_send_json_success(
			[
				'settings' => $defaults,
			]
		);
	}

	/**
	 * Resolve a widget that owns a gear popover by its identifier, registering it on
	 * demand: the Dashboard page loader does not run in an AJAX request, so the widget
	 * is not in the container yet. Each name resolves to its Pro subclass when Pro is
	 * active.
	 *
	 * @since 2.0.2
	 *
	 * @param string $widget_id Widget identifier.
	 *
	 * @return AbstractWidget|null
	 */
	protected function get_gear_widget( string $widget_id ): ?AbstractWidget {

		$names = [
			'entries'   => 'Admin\Dashboard\Widgets\Entries',
			'payments'  => 'Admin\Dashboard\Widgets\Payments',
			'locations' => 'Admin\Dashboard\Widgets\Locations',
		];

		if ( ! isset( $names[ $widget_id ] ) ) {
			return null;
		}

		$object_id = "dashboard_widget_{$widget_id}";

		if ( ! wpforms()->obj( $object_id ) ) {
			wpforms()->register(
				[
					'name' => $names[ $widget_id ],
					'id'   => $object_id,
					'hook' => false,
					'run'  => false,
				]
			);
		}

		$widget = wpforms()->obj( $object_id );

		return $widget instanceof AbstractWidget ? $widget : null;
	}

	/**
	 * Re-render the Entries widget for the current range and return its markup.
	 *
	 * The Entries table and graph are server-rendered, so a gear settings save
	 * cannot re-render them client-side (unlike the config-driven Locations
	 * widget). The client swaps this markup in after a save. Works on both builds
	 * — Lite has no date-range picker, so an absent range falls back to the
	 * default 30-day range.
	 *
	 * @since 2.0.2
	 */
	public function get_entries_widget_html(): void {

		$access = $this->validate_request();

		// Nonce verified via validate_request() before this runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );

		[ $start, $end, $days ] = $this->resolve_timespan( $date );

		$data   = wpforms()->obj( 'dashboard_cache' )->get_or_compute( Cache::make_key( $days, $start, $end ), $start, $end );
		$widget = $this->get_entries_widget();

		if ( ! $widget ) {
			$this->send_error( 'invalid', esc_html__( 'Unable to load the Entries widget.', 'wpforms-lite' ) );
		}

		wp_send_json_success(
			[
				'entries_html' => $widget->render_for_range( $data, $access ),
			]
		);
	}

	/**
	 * Resolve the posted date-range string into a timespan tuple clamped to the window the
	 * Dashboard supports.
	 *
	 * The posted range is user input with no bounds of its own: `Datepicker` rejects only an
	 * unparseable or reversed pair, and the picker's `minDate` is a client-side affordance the
	 * request does not have to honour (it is also applied only once the readiness response
	 * lands, so even the UI can emit a wider range). Left alone it costs real work rather than
	 * returning early: the Top Locations live fallback walks the span the rollup does not cover
	 * in fixed-size chunks, one query per chunk, so a range reaching decades back issues
	 * thousands of joins, and every distinct range hashes into a transient key of its own.
	 *
	 * Both ends are pulled into `[ floor, today ]`, so a range overshooting either side lands
	 * on the nearest supported one and a range lying wholly outside collapses onto that
	 * boundary. The clamp is not silent: the tuple is re-derived from the clamped days, so the
	 * preset count and label the client renders describe the range it actually received.
	 *
	 * @since 2.0.2
	 *
	 * @param string $date Posted date-range string.
	 *
	 * @return array Timespan tuple: start, end, preset days, label.
	 */
	protected function resolve_timespan( string $date ): array {

		[ $start, $end ] = Datepicker::process_timespan_from_string( $date );

		$floor = $this->get_range_floor();
		$today = date_create_immutable( 'now', wp_timezone() )->setTime( 23, 59, 59 );

		// Lift a range reaching below the window, then pull back one running past today. Only
		// the day survives: the tuple is rebuilt from `Y-m-d`, which restores the boundary
		// times, so re-deriving an unclamped range reproduces it exactly.
		$start = min( max( $start, $floor ), $today );
		$end   = min( max( $end, $floor ), $today );

		return Datepicker::process_timespan_from_string( Datepicker::concat_dates( $start, $end ) );
	}

	/**
	 * Get the oldest day a requested range may start at.
	 *
	 * The widest range the picker can ask for, so no legitimate request is affected and an
	 * arbitrary older one cannot mint a cache key of its own. Deliberately not narrowed to the
	 * day the stored data begins: a preset would then be served as the shorter range the data
	 * covers, and `resolve_timespan()` would rename it after that shorter range, so a young site
	 * saw "Last 7 days" answer as "Yesterday". The days a narrower floor would skip hold
	 * nothing, so serving them changes no figure the Dashboard shows.
	 *
	 * @since 2.0.2
	 *
	 * @return DateTimeImmutable
	 */
	protected function get_range_floor(): DateTimeImmutable {

		$widest = max( array_map( 'intval', Cache::PRESET_RANGES ) );

		return date_create_immutable( 'now', wp_timezone() )->setTime( 0, 0, 0 )->modify( "-$widest days" );
	}

	/**
	 * Resolve the Entries widget, typed for the callers that render it.
	 *
	 * @since 2.0.2
	 *
	 * @return Entries|null
	 */
	protected function get_entries_widget(): ?Entries {

		$widget = $this->get_gear_widget( 'entries' );

		return $widget instanceof Entries ? $widget : null;
	}

	/**
	 * Persist the widget's currently selected graph/tile report to user meta, so the
	 * next page load restores the last-viewed graph.
	 *
	 * @since 2.0.2
	 */
	public function payments_save_selected_report(): void {

		$this->validate_request();

		// Nonce verified via validate_request() before this runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$widget_id = sanitize_key( $_POST['widget'] ?? '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via validate_request() before this runs.
		$report = sanitize_key( $_POST['report'] ?? '' );

		if ( $widget_id === '' || $report === '' ) {
			$this->send_error( 'invalid', esc_html__( 'Invalid request.', 'wpforms-lite' ) );
		}

		$all                                = Helpers::get_user_meta_array( AbstractWidget::SETTINGS_META_KEY );
		$widget_settings                    = (array) ( $all[ $widget_id ] ?? [] );
		$widget_settings['selected_report'] = $report;
		$all[ $widget_id ]                  = $widget_settings;

		Helpers::update_user_meta_array( AbstractWidget::SETTINGS_META_KEY, $all );

		wp_send_json_success();
	}

	/**
	 * Get the access context, registering the resolver on first use.
	 *
	 * @since 2.0.2
	 *
	 * @return AccessContext
	 */
	protected function get_access(): AccessContext {

		if ( ! $this->access_resolver ) {
			wpforms()->register(
				[
					'name' => 'Admin\Dashboard\AccessResolver',
					'id'   => 'dashboard_access_resolver',
					'hook' => false,
					'run'  => false,
				]
			);

			$this->access_resolver = wpforms()->obj( 'dashboard_access_resolver' );
		}

		return $this->access_resolver->get_context();
	}

	/**
	 * Send a JSON error envelope and stop execution.
	 *
	 * @since 2.0.2
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Human-readable error message.
	 */
	protected function send_error( string $code, string $message ): void {

		wp_send_json_error(
			[
				'code'    => $code,
				'message' => $message,
			]
		);
	}
}
