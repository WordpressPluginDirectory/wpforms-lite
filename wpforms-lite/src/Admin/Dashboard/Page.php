<?php

namespace WPForms\Admin\Dashboard;

use WPForms\Admin\Addons\Install;
use WPForms\Admin\Dashboard\Widgets\Entries;
use WPForms\Admin\Helpers\Datepicker;
use WPForms\Admin\Payments\Views\Overview\Chart;

/**
 * Dashboard admin page controller.
 *
 * @since 2.0.2
 */
class Page {

	/**
	 * Access resolver.
	 *
	 * @since 2.0.2
	 *
	 * @var AccessResolver|null
	 */
	private $access_resolver;

	/**
	 * Widget pipeline.
	 *
	 * @since 2.0.2
	 *
	 * @var WidgetPipeline|null
	 */
	private $widget_pipeline;

	/**
	 * Memoized "is the Top Locations chart needed this request" result.
	 *
	 * @since 2.0.2
	 *
	 * @var bool|null
	 */
	private $is_locations_chart_needed = null;

	/**
	 * Memoized "is the Entries widget visible this request" result.
	 *
	 * @since 2.0.2
	 *
	 * @var bool|null
	 */
	private $is_entries_widget_visible = null;

	/**
	 * Memoized "is the Payments chart needed this request" result.
	 *
	 * @since 2.0.2
	 *
	 * @var bool|null
	 */
	private $is_payments_chart_needed = null;

	/**
	 * Initialize the class.
	 *
	 * @since 2.0.2
	 */
	public function init(): void {

		if ( ! $this->allow_load() ) {
			return;
		}

		$this->loader();

		$this->access_resolver = wpforms()->obj( 'dashboard_access_resolver' );
		$this->widget_pipeline = wpforms()->obj( 'dashboard_widget_pipeline' );

		if ( ! $this->access_resolver || ! $this->widget_pipeline ) {
			return;
		}

		$this->hooks();
	}

	/**
	 * Whether the class is allowed to load — only on the Dashboard page request.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function allow_load(): bool {

		return wpforms_is_admin_page( 'dashboard' );
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.2
	 */
	private function hooks(): void {

		add_filter( 'wpforms_admin_flyoutmenu', '__return_false' );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wpforms_admin_page', [ $this, 'output' ] );
	}

	/**
	 * Instantiate on-demand dependencies.
	 *
	 * @since 2.0.2
	 */
	private function loader(): void {

		// WidgetPipeline::init() resolves the AccessResolver and widget instances, so it must be registered last.
		$classes = [
			[
				'name' => 'Admin\Dashboard\StatCards',
				'id'   => StatCards::ID,
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\AccessResolver',
				'id'   => 'dashboard_access_resolver',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\Widgets\Entries',
				'id'   => 'dashboard_widget_entries',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\Widgets\Locations',
				'id'   => 'dashboard_widget_locations',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\Widgets\WhatsNew',
				'id'   => 'dashboard_widget_whats_new',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\Widgets\License',
				'id'   => 'dashboard_widget_license',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\Widgets\Payments',
				'id'   => 'dashboard_widget_payments',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\Widgets\WPMailSMTP',
				'id'   => 'dashboard_widget_wp_mail_smtp',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\Widgets\FeaturesAddons',
				'id'   => 'dashboard_widget_features_addons',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\Widgets\Integrations',
				'id'   => 'dashboard_widget_integrations',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\Widgets\GrowthTools',
				'id'   => 'dashboard_widget_growth_tools',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\Widgets\SpamSecurity',
				'id'   => 'dashboard_widget_spam_security',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\Widgets\GettingStarted',
				'id'   => 'dashboard_widget_getting_started',
				'hook' => false,
				'run'  => false,
			],
			[
				'name' => 'Admin\Dashboard\WidgetPipeline',
				'id'   => 'dashboard_widget_pipeline',
				'hook' => false,
				'run'  => 'init',
			],
		];

		/**
		 * Filters the Dashboard on-demand class registrations.
		 *
		 * @since 2.0.2
		 *
		 * @param array $classes Class registration configs.
		 */
		$classes = (array) apply_filters( 'wpforms_admin_dashboard_page_loader_classes', $classes );

		wpforms()->register_bulk( $classes );
	}

	/**
	 * Enqueue Dashboard page assets.
	 *
	 * @since 2.0.2
	 */
	public function enqueue_assets(): void {

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-dashboard',
			WPFORMS_PLUGIN_URL . "assets/css/admin/admin-dashboard$min.css",
			[ 'wpforms-admin' ],
			WPFORMS_VERSION
		);

		// The Entries and Payments charts plot a time-scale X axis, which needs Moment.
		// The Entries widget needs the library in every visible state: the data-state
		// graph, the empty-state sample chart, and a gear save re-enabling the graph
		// without a reload.
		$needs_time_scale = $this->is_entries_widget_visible() || $this->is_payments_chart_needed();

		// Chart.js: the Locations donut, the Entries trend graph, and/or the Payments line chart.
		if ( $this->is_locations_chart_needed() || $needs_time_scale ) {
			wp_enqueue_script(
				'wpforms-chart',
				WPFORMS_PLUGIN_URL . 'assets/lib/chart.min.js',
				$needs_time_scale ? [ 'moment' ] : [],
				'4.5.1',
				true
			);
		}

		// The time-scale X axis needs the Moment.js adapter.
		if ( $needs_time_scale ) {
			wp_enqueue_script(
				'wpforms-chart-adapter-moment',
				WPFORMS_PLUGIN_URL . 'assets/lib/chartjs-adapter-moment.min.js',
				[ 'moment', 'wpforms-chart' ],
				'1.0.1',
				true
			);
		}

		wp_enqueue_script(
			'wpforms-dashboard',
			WPFORMS_PLUGIN_URL . "assets/js/admin/dashboard/dashboard-page$min.js",
			$this->get_script_dependencies(),
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-dashboard',
			'wpforms_dashboard',
			$this->get_localized_data()
		);
	}

	/**
	 * Get the data localized for the Dashboard page script.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	protected function get_localized_data(): array {

		$tier = wpforms_get_license_type();

		if ( ! $tier ) {
			$tier = 'lite';
		}

		$min = wpforms_get_min_suffix();

		// Shared gear-menu module — available on every tier (e.g. the Entries widget
		// has settings on Lite); bound by class, it no-ops until a widget renders a cog.
		$modules = [
			[
				'name' => 'widgetSettings',
				'path' => WPFORMS_PLUGIN_URL . "assets/js/admin/dashboard/modules/widget-settings$min.js",
			],
			[
				'name' => 'addonTiles',
				'path' => WPFORMS_PLUGIN_URL . "assets/js/admin/dashboard/modules/addon-tiles$min.js",
			],
		];

		// Shared chart primitives — required by both graph widgets, whose visibility
		// conditions are independent, so it must load for either one alone.
		if ( $this->is_entries_widget_visible() || $this->is_payments_chart_needed() ) {
			$modules[] = [
				'name' => 'chartHelpers',
				'path' => WPFORMS_PLUGIN_URL . "assets/js/admin/dashboard/modules/chart-helpers$min.js",
			];
		}

		// Top Locations donut module — for the live data state and the sample preview.
		if ( $this->is_locations_chart_needed() ) {
			$modules[] = [
				'name' => 'widgetLocations',
				'path' => WPFORMS_PLUGIN_URL . "assets/js/admin/dashboard/modules/widget-locations$min.js",
			];
		}

		// Entries widget module — owns the trend graph with range/gear re-render.
		if ( $this->is_entries_widget_visible() ) {
			$modules[] = [
				'name' => 'widgetEntries',
				'path' => WPFORMS_PLUGIN_URL . "assets/js/admin/dashboard/modules/widget-entries$min.js",
			];
		}

		// Payments chart module — line/area graph with tile re-scope.
		if ( $this->is_payments_chart_needed() ) {
			$modules[] = [
				'name' => 'paymentsHelpers',
				'path' => WPFORMS_PLUGIN_URL . "assets/js/admin/dashboard/modules/payments-helpers$min.js",
			];

			$modules[] = [
				'name' => 'widgetPayments',
				'path' => WPFORMS_PLUGIN_URL . "assets/js/admin/dashboard/modules/widget-payments$min.js",
			];
		}

		$localized = [
			'nonce'       => wp_create_nonce( 'wpforms-dashboard' ),
			'tier'        => $tier,
			'date_format' => Datepicker::get_wp_date_format_for_momentjs(),
			'is_debug'    => wpforms_debug(),
			'modules'     => $modules,
			'i18n'        => [
				// Chart.js draws dataset labels as canvas text, so it never decodes entities.
				'entries' => __( 'Entries', 'wpforms-lite' ),
			],
			'addons'      => [
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'action'     => Install::ACTION,
				'nonce'      => wp_create_nonce( Install::ACTION ),
				'installing' => esc_html__( 'Installing…', 'wpforms-lite' ),
				'activating' => esc_html__( 'Activating…', 'wpforms-lite' ),
				'installed'  => esc_html__( 'Installed', 'wpforms-lite' ),
				'error'      => esc_html__( 'Something went wrong. Please install the addon from the Addons page.', 'wpforms-lite' ),
			],
		];

		// Payments chart data — nonce, dates, colors, and currency decimals.
		if ( $this->is_payments_chart_needed() ) {
			[ $start, $end ] = $this->get_timespan();

			$localized['paymentsDates']         = $start->format( 'Y-m-d' ) . Datepicker::TIMESPAN_DELIMITER . $end->format( 'Y-m-d' );
			$localized['paymentsOverviewNonce'] = wp_create_nonce( 'wpforms_payments_overview_nonce' );
			$localized['paymentsDecimals']      = absint( wpforms_get_currency_decimals( wpforms_get_currency() ) );
			$localized['paymentsColors']        = [
				// Refunds keep their own grey; every other report uses the green default.
				'total_refunded' => [
					'borderColor'     => '#50575e',
					'backgroundColor' => '#ebebec',
				],
				'default'        => [
					'borderColor'     => '#008a20',
					'backgroundColor' => '#e3f3e4',
				],
			];

			// Per-report empty-state headings, shared with the Payments Overview chart.
			$localized['paymentsNoData'] = Chart::get_no_data_headings();
		}

		return $localized;
	}

	/**
	 * Get the date-range datepicker HTML. Empty on Lite; the Pro subclass overrides this.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	protected function get_datepicker_html(): string {

		return '';
	}

	/**
	 * Get the Dashboard script dependencies. The Pro subclass adds flatpickr.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	protected function get_script_dependencies(): array {

		// `underscore` powers `_.escape()` (and other helpers) in the widget modules.
		$dependencies = [ 'jquery', 'underscore' ];

		// Chart.js: needed by the Locations donut, the Entries trend graph, and/or
		// the Payments timeseries chart.
		if ( $this->is_locations_chart_needed() || $this->is_entries_widget_visible() || $this->is_payments_chart_needed() ) {
			$dependencies[] = 'wpforms-chart';
		}

		// The Entries and Payments charts additionally need the Moment.js time-scale adapter.
		if ( $this->is_entries_widget_visible() || $this->is_payments_chart_needed() ) {
			$dependencies[] = 'wpforms-chart-adapter-moment';
		}

		return $dependencies;
	}

	/**
	 * Whether the Top Locations widget renders its donut in this request. Every
	 * visible state now shows a table + donut (sample preview or live data), so
	 * its Chart.js library and JS module need enqueuing; the hidden no-data state
	 * does not.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	protected function is_locations_chart_needed(): bool {

		// Resolved once per request; queried by the style, script-dep, and module hooks.
		if ( $this->is_locations_chart_needed !== null ) {
			return $this->is_locations_chart_needed;
		}

		$locations = wpforms()->obj( 'dashboard_widget_locations' );
		$access    = wpforms()->obj( 'dashboard_access_resolver' );

		$this->is_locations_chart_needed = $locations && $access
			? $locations->get_state( $access->get_context() )->is_visible()
			: false;

		return $this->is_locations_chart_needed;
	}

	/**
	 * Whether the Entries widget is visible in this request. Every visible state
	 * needs the widget's JS module (range/gear re-render swap, per-row graph
	 * buttons, help tips) and the Chart.js library with its Moment adapter (data
	 * graph, empty-state sample chart, gear re-enable without a reload).
	 *
	 * Always true on core's own paths — the widget reports itself visible in every
	 * state. The null arm still covers a third party dropping the widget through the
	 * loader or pipeline filters, since `init()` only verifies the resolver and the
	 * pipeline. Kept as the seam a hideable widget would use — `WidgetState::$visible`
	 * is live elsewhere (`WPMailSMTP` returns false). Variant is deliberately not
	 * consulted: the empty state draws a sample chart, so it needs the same assets.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_entries_widget_visible(): bool {

		// Resolved once per request; queried by the style, script-dep, and module hooks.
		if ( $this->is_entries_widget_visible !== null ) {
			return $this->is_entries_widget_visible;
		}

		$entries = wpforms()->obj( 'dashboard_widget_entries' );
		$access  = wpforms()->obj( 'dashboard_access_resolver' );

		$this->is_entries_widget_visible = $entries && $access
			? $entries->get_state( $access->get_context() )->is_visible()
			: false;

		return $this->is_entries_widget_visible;
	}

	/**
	 * Whether the Payments widget renders its chart in this request. Only the
	 * visible data state (with a connected gateway) shows the line/area chart;
	 * the connect state does not.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_payments_chart_needed(): bool {

		if ( $this->is_payments_chart_needed !== null ) {
			return $this->is_payments_chart_needed;
		}

		$payments = wpforms()->obj( 'dashboard_widget_payments' );
		$access   = wpforms()->obj( 'dashboard_access_resolver' );

		if ( ! $payments || ! $access ) {
			$this->is_payments_chart_needed = false;

			return false;
		}

		$state = $payments->get_state( $access->get_context() );

		$this->is_payments_chart_needed = $state->is_visible() && $state->get_variant() === 'data';

		return $this->is_payments_chart_needed;
	}

	/**
	 * Get the date-range readiness notice HTML. Empty on Lite; the Pro subclass overrides this.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	protected function get_date_range_notice_html(): string {

		return '';
	}

	/**
	 * Resolve the selected timespan tuple (start, end, preset days, label).
	 *
	 * The Pro subclass adapts the default range to the largest rollup-covered preset.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	protected function get_timespan(): array {

		return Datepicker::process_timespan();
	}

	/**
	 * Render the Dashboard page.
	 *
	 * @since 2.0.2
	 */
	public function output(): void {

		// Stamp the first dashboard visit once — the anchor the time-gated Form Abandonment
		// notice counts 15 days from. Read first, so every later visit costs a cached option
		// read instead of an uncached write probe; mirrors how `Pro\Admin\Analytics\Page`
		// caches its collecting-since anchor. Stamped before the widgets render so the notice
		// sees the timestamp on the very first visit.
		if ( ! get_option( Entries::FIRST_VISIT_OPTION ) ) {
			update_option( Entries::FIRST_VISIT_OPTION, time(), false );
		}

		[ $start, $end, $days ] = $this->get_timespan();

		$data = wpforms()->obj( 'dashboard_cache' )->get_or_compute( Cache::make_key( $days, $start, $end ), $start, $end );

		$access = $this->access_resolver->get_context();

		$stat_cards = wpforms()->obj( StatCards::ID );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render(
			'admin/dashboard/page',
			[
				'datepicker'        => $this->get_datepicker_html(),
				'datepicker_notice' => $this->get_date_range_notice_html(),
				'stat_cards'        => $stat_cards ? $stat_cards->render( $data, $access ) : '',
				'stat_cards_class'  => $stat_cards ? $stat_cards->get_row_class() : '',
				'main_widgets'      => $this->widget_pipeline->render_column( 'main', $data, $access ),
				'sidebar_widgets'   => $this->widget_pipeline->render_column( 'sidebar', $data, $access ),
			],
			true
		);
	}
}
