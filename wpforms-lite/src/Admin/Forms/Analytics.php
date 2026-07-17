<?php

namespace WPForms\Admin\Forms;

use WP_Post;
use WPForms\Admin\Forms\Table\Facades\Columns;
use WPForms\Analytics\Analytics as AnalyticsFeature;
use WPForms\Pro\Analytics\Analytics as ProAnalytics;

/**
 * Analytics columns on the Forms Overview page.
 *
 * Registers three new columns (Views, Interactions, Conversion), pre-fetches
 * their data via a bulk query, and renders cells.
 *
 * @since 2.0.0
 */
class Analytics {

	/**
	 * Pre-fetched stats keyed by form ID.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $stats = [];

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
	private function hooks(): void {

		add_filter( 'wpforms_admin_forms_table_facades_columns_data', [ $this, 'register_columns' ] );
		add_filter( 'wpforms_overview_table_column_value', [ $this, 'render_column' ], 10, 3 );
		add_action( 'wpforms_admin_forms_list_table_prepare_items_after', [ $this, 'prefetch_stats' ] );
		add_filter( 'default_hidden_columns', [ $this, 'set_column_defaults' ], 11, 2 );
		add_action( 'wpforms_admin_header_after', [ $this, 'render_feature_tooltip' ] );

		// Priority 10 runs after the core row actions (priority 9), so "Entries" is
		// already present to insert "Analytics" after.
		add_filter( 'wpforms_overview_row_actions', [ $this, 'add_analytics_row_action' ], 10, 2 );
	}

	/**
	 * Add an "Analytics" row action after "Entries" in the Forms Overview table.
	 *
	 * Pro/Elite only — Lite users see the upgrade badge in the analytics columns
	 * instead. Gated by the same per-form capability as the analytics page.
	 *
	 * @since 2.0.0
	 *
	 * @param array|mixed $row_actions Row action links keyed by slug.
	 * @param WP_Post     $form        Form object.
	 *
	 * @return array
	 */
	public function add_analytics_row_action( $row_actions, $form ): array {

		$row_actions = (array) $row_actions;

		if ( ! wpforms()->is_pro() || ! ProAnalytics::is_allowed() || ! wpforms_current_user_can( 'view_entries_form_single', $form->ID ) ) {
			return $row_actions;
		}

		$analytics = [
			'analytics' => sprintf(
				'<a href="%s" title="%s">%s</a>',
				esc_url(
					add_query_arg(
						[
							'page'    => 'wpforms-analytics',
							'form_id' => $form->ID,
						],
						admin_url( 'admin.php' )
					)
				),
				esc_attr__( 'View analytics', 'wpforms-lite' ),
				esc_html__( 'Analytics', 'wpforms-lite' )
			),
		];

		// Insert after Entries (or append when absent), mirroring register_columns().
		$position = array_search( 'entries', array_keys( $row_actions ), true );
		$position = $position !== false ? $position + 1 : count( $row_actions );

		return array_slice( $row_actions, 0, $position, true )
			+ $analytics
			+ array_slice( $row_actions, $position, null, true );
	}

	/**
	 * Register analytics columns (Views, Interactions, Conversion).
	 *
	 * Inserts three analytics columns immediately after the Entries column when
	 * it exists, or appends them at the end of the columns list otherwise.
	 *
	 * Hooks: wpforms_admin_forms_table_facades_columns_data (filter).
	 *
	 * @since 2.0.0
	 *
	 * @param array|mixed $columns Existing columns data.
	 *
	 * @return array
	 */
	public function register_columns( $columns ): array {

		$columns = (array) $columns;

		$analytics_columns = [
			'analytics_views'        => [
				'label' => esc_html__( 'Views', 'wpforms-lite' ),
			],
			'analytics_interactions' => [
				'label' => esc_html__( 'Interactions', 'wpforms-lite' ),
			],
			'analytics_conversion'   => [
				'label' => esc_html__( 'Conversion', 'wpforms-lite' ),
			],
		];

		$position = array_search( 'entries', array_keys( $columns ), true );
		$position = $position !== false ? $position + 1 : count( $columns );

		return array_slice( $columns, 0, $position, true )
			+ $analytics_columns
			+ array_slice( $columns, $position, null, true );
	}

	/**
	 * Render an analytics column cell.
	 *
	 * Dispatches to the appropriate private renderer based on $column_name.
	 * Returns the original $value unchanged when $form is not a WP_Post instance
	 * or when $column_name does not match an analytics column.
	 *
	 * Hooks: wpforms_overview_table_column_value (filter).
	 * Matches the signature used by Forms\Locator::column_value — no strict type
	 * hints on filter callback params.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $value       Current cell value (pass-through for unknown columns).
	 * @param mixed  $form        Form post object; must be WP_Post to render.
	 * @param string $column_name Column identifier.
	 *
	 * @return mixed
	 */
	public function render_column( $value, $form, string $column_name ) {

		if ( ! $form instanceof WP_Post ) {
			return $value;
		}

		$form_id = (int) $form->ID;

		switch ( $column_name ) {
			case 'analytics_views':
				$value = $this->render_views( $form_id );
				break;

			case 'analytics_interactions':
				$value = $this->render_interactions( $form_id );
				break;

			case 'analytics_conversion':
				$value = $this->render_conversion( $form_id );
				break;
		}

		return $value;
	}

	/**
	 * Bulk-fetch stats for all forms on the current overview page.
	 *
	 * Retrieves views and submissions for every form in a single DB call and
	 * stores the result in $stats keyed by form ID. No-ops when $form_ids is
	 * empty or non-array, or when the analytics DB object is unavailable.
	 *
	 * Hooks: wpforms_admin_forms_list_table_prepare_items_after (action). The
	 * parameter is untyped because the action's payload depends on the dispatcher
	 * (`ListTable::prepare_items()`), which may pass a non-array if the items
	 * lookup degraded - keeping this method tolerant prevents a fatal in that path.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $form_ids Form IDs (expected: int[]).
	 */
	public function prefetch_stats( $form_ids ): void {

		if ( ! is_array( $form_ids ) || empty( $form_ids ) ) {
			$this->stats = [];

			return;
		}

		$db = wpforms()->obj( 'analytics_db' );

		if ( ! $db ) {
			$this->stats = [];

			return;
		}

		$this->stats = $db->get_overview_stats( $form_ids );
	}

	/**
	 * Hide the Shortcode column by default on the Forms Overview screen.
	 *
	 * Hooks: default_hidden_columns (filter, priority 11). Runs after
	 * ListTable::default_hidden_columns() (priority 10), receives its output,
	 * gates on the same screen ID + Columns::has_selected_columns() conditions,
	 * and appends 'shortcode' to keep the analytics columns visually grouped
	 * after Entries on first visit.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $hidden Hidden columns array (filter input may be non-array).
	 * @param mixed $screen WP_Screen-like object for the current admin screen.
	 *
	 * @return array
	 */
	public function set_column_defaults( $hidden, $screen ): array {

		$hidden = (array) $hidden;

		if ( ! is_object( $screen ) || ! isset( $screen->id ) || $screen->id !== 'toplevel_page_wpforms-overview' ) {
			return $hidden;
		}

		if ( Columns::has_selected_columns() ) {
			return $hidden;
		}

		$hidden[] = 'shortcode';

		return $hidden;
	}

	/**
	 * Render the Views column cell.
	 *
	 * @since 2.0.0
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return string
	 */
	private function render_views( int $form_id ): string {

		$views = $this->stats[ $form_id ]['views'] ?? null;

		if ( $views === null ) {
			return '—';
		}

		$value = number_format_i18n( (int) $views );

		if ( ! wpforms()->is_pro() ) {
			return $value;
		}

		if ( ! ProAnalytics::is_allowed() ) {
			return $value;
		}

		return $this->build_analytics_link( $form_id, 'views', $value );
	}

	/**
	 * Render the Interactions column cell.
	 *
	 * On Lite, returns the Pro upgrade badge. On Pro with stats present,
	 * returns a link to the Analytics page for the given form.
	 * Returns a dash on Pro when the form has no stats.
	 *
	 * @since 2.0.0
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return string
	 */
	private function render_interactions( int $form_id ): string {

		if ( ! wpforms()->is_pro() || ! ProAnalytics::is_allowed() ) {
			return $this->render_pro_badge( __( 'Upgrade to Pro to unlock Interactions', 'wpforms-lite' ) );
		}

		$interactions = $this->stats[ $form_id ]['interactions'] ?? null;

		if ( $interactions === null ) {
			return '—';
		}

		return $this->build_analytics_link( $form_id, 'interactions', number_format_i18n( (int) $interactions ) );
	}

	/**
	 * Render the Pro upgrade teaser for locked columns (Lite only).
	 *
	 * Outputs a blurred-value image wrapped in the education-modal link, so the
	 * locked metric reads as "there is data here, upgrade to see it". Both gated
	 * columns (Interactions, Conversion) share a single `analytics-upgrade`
	 * utm_content value. The `data-utm-medium` attribute is read by the Lite
	 * education modal JS to override the upgrade URL's utm_medium with
	 * `forms-overview`.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tooltip Hover title naming the locked metric (e.g. "Upgrade to Pro to unlock Interactions").
	 *
	 * @return string
	 */
	private function render_pro_badge( string $tooltip ): string {

		return sprintf(
			'<a href="#" class="education-modal" title="%5$s" data-action="upgrade" data-name="%1$s" data-license="pro" data-banner-src="%2$s" data-utm-medium="forms-overview" data-utm-content="analytics-upgrade"><img src="%3$s" alt="%4$s" width="48" height="27"></a>',
			esc_attr__( 'Form Analytics', 'wpforms-lite' ),
			esc_url( WPFORMS_PLUGIN_URL . 'assets/images/education/analytics-preview.png' ),
			esc_url( WPFORMS_PLUGIN_URL . 'assets/images/education/blurred-value.svg' ),
			esc_attr__( 'Upgrade to Pro', 'wpforms-lite' ),
			esc_attr( $tooltip )
		);
	}

	/**
	 * Render the Conversion column cell.
	 *
	 * @since 2.0.0
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return string
	 */
	private function render_conversion( int $form_id ): string {

		if ( ! wpforms()->is_pro() || ! ProAnalytics::is_allowed() ) {
			return $this->render_pro_badge( __( 'Upgrade to Pro to unlock Conversion Rate', 'wpforms-lite' ) );
		}

		$value = $this->calculate_conversion_value( $this->stats[ $form_id ] ?? null );

		if ( $value === null ) {
			return '—';
		}

		return $this->build_analytics_link( $form_id, 'conversion', $value );
	}

	/**
	 * Compute the formatted conversion-rate string for a form's stats.
	 *
	 * @since 2.0.0
	 *
	 * @param array|null $stats Pre-fetched stats for the form, or null when absent.
	 *
	 * @return string|null Formatted "NN.N%" value, or null when stats are missing or views are zero.
	 */
	private function calculate_conversion_value( ?array $stats ): ?string {

		if ( ! $stats ) {
			return null;
		}

		$views       = (int) ( $stats['views'] ?? 0 );
		$submissions = (int) ( $stats['submissions'] ?? 0 );

		if ( $views <= 0 ) {
			return null;
		}

		$rate = round( ( $submissions / $views ) * 100, 1 );

		return number_format_i18n( $rate, 1 ) . '%';
	}

	/**
	 * Build the Analytics-page link markup for a populated column cell (Pro).
	 *
	 * @since 2.0.0
	 *
	 * @param int    $form_id      Form ID the link targets.
	 * @param string $css_modifier Column suffix for the link CSS class.
	 * @param string $value        Display value (escaped on output).
	 *
	 * @return string Anchor markup pointing at the form's Analytics page.
	 */
	private function build_analytics_link( int $form_id, string $css_modifier, string $value ): string {

		return sprintf(
			'<a href="%1$s" class="wpforms-analytics-%2$s-link">%3$s</a>',
			esc_url( admin_url( 'admin.php?page=wpforms-analytics&form_id=' . $form_id ) ),
			esc_attr( $css_modifier ),
			esc_html( $value )
		);
	}

	/**
	 * Render the Form Analytics feature-discovery tooltip on Forms Overview.
	 *
	 * @since 2.0.0
	 */
	public function render_feature_tooltip(): void {

		if ( ! $this->should_render_feature_tooltip() ) {
			return;
		}

		$tooltip = wpforms()->obj( 'education_feature_tooltip' );

		if ( ! $tooltip ) {
			return;
		}

		/**
		 * Filter the Learn More URL for the Form Analytics feature-discovery tooltip.
		 *
		 * @since 2.0.0
		 *
		 * @param string $url Default UTM-wrapped wpforms.com URL.
		 */
		$learn_more_url = apply_filters(
			'wpforms_admin_forms_analytics_render_feature_tooltip_learn_more_url',
			wpforms_utm_link(
				AnalyticsFeature::DOC_URL,
				'forms-overview',
				'Analytics Feature Tooltip'
			)
		);

		$button = wpforms()->is_pro() && ProAnalytics::is_allowed()
			? [
				'text'    => esc_html__( 'View Analytics', 'wpforms-lite' ),
				'url'     => add_query_arg( 'page', 'wpforms-analytics', admin_url( 'admin.php' ) ),
				'classes' => 'wpforms-btn wpforms-btn-sm wpforms-btn-orange wpforms-education-feature-tooltip-view',
			]
			: [
				'text'    => esc_html__( 'Upgrade to Pro', 'wpforms-lite' ),
				'url'     => '#',
				'classes' => 'wpforms-btn wpforms-btn-sm wpforms-btn-orange education-modal',
				'data'    => [
					'name'        => esc_attr__( 'Form Analytics', 'wpforms-lite' ),
					'license'     => 'pro',
					'action'      => 'upgrade',
					'banner-src'  => WPFORMS_PLUGIN_URL . 'assets/images/education/analytics-preview.png',
					'utm-medium'  => 'forms-overview',
					'utm-content' => 'analytics-feature-tooltip',
				],
			];

		$tooltip->render(
			[
				'title'          => esc_html__( 'Form Analytics', 'wpforms-lite' ),
				'badge'          => esc_html__( 'NEW', 'wpforms-lite' ),
				'text'           => esc_html__( 'Monitor views, conversions, and field-level activity to optimize performance.', 'wpforms-lite' ),
				'section'        => 'analytics-feature-discovery',
				'learn_more_url' => $learn_more_url,
				'button'         => $button,
			]
		);
	}

	/**
	 * Whether the Form Analytics feature-discovery tooltip should render.
	 *
	 * Gates on the current admin screen and the per-user dismissal flag stored
	 * in the `wpforms_dismissed` user meta map.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True when the tooltip should render on this request.
	 */
	private function should_render_feature_tooltip(): bool {

		$screen    = get_current_screen();
		$screen_id = $screen->id ?? '';

		if ( ! $screen || $screen_id !== 'toplevel_page_wpforms-overview' ) {
			return false;
		}

		$dismissed = (array) get_user_meta( get_current_user_id(), 'wpforms_dismissed', true );

		return empty( $dismissed['edu-analytics-feature-discovery'] );
	}
}
