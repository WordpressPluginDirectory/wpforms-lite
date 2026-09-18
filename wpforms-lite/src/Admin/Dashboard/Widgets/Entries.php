<?php

namespace WPForms\Admin\Dashboard\Widgets;

use DateTimeImmutable; // phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement -- Referenced from the get_range_dates() docblock.
use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\WidgetState;
use WPForms\Admin\Dashboard\Widgets\Traits\EntriesEducationTrait;
use WPForms\Admin\Dashboard\Widgets\Traits\EntriesEmptyStateTrait;
use WPForms\Admin\Dashboard\Widgets\Traits\EntriesSettingsTrait;
use WPForms\Admin\Dashboard\Widgets\Traits\EntriesTableTrait;

/**
 * Dashboard "Forms" widget.
 *
 * @since 2.0.2
 */
class Entries extends AbstractWidget {

	use EntriesEducationTrait;
	use EntriesEmptyStateTrait;
	use EntriesSettingsTrait;
	use EntriesTableTrait;

	/**
	 * Column placement.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'main';

	/**
	 * Default sort position — first in the main column.
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 10;

	/**
	 * Default number of rows the table shows.
	 *
	 * @since 2.0.2
	 */
	private const DEFAULT_ROWS = 5; // NOSONAR Read via self:: from EntriesSettingsTrait, which Sonar analyses separately; traits cannot declare constants before PHP 8.2.

	/**
	 * Minimum number of rows the gear menu allows.
	 *
	 * @since 2.0.2
	 */
	private const MIN_ROWS = 3;

	/**
	 * Maximum number of rows the gear menu allows.
	 *
	 * @since 2.0.2
	 */
	private const MAX_ROWS = 10;

	/**
	 * Maximum number of entry-less forms considered when topping up unused table slots.
	 *
	 * Bounds the analytics lookup that ranks them, so its cost stays flat on sites with
	 * hundreds of forms instead of scaling with the form count.
	 *
	 * @since 2.0.2
	 */
	private const VIEWED_CANDIDATES = 30; // NOSONAR Read via self:: from EntriesTableTrait, which Sonar analyses separately; traits cannot declare constants before PHP 8.2.

	/**
	 * Widget-settings key persisting the form the graph is re-scoped to. Lives in
	 * the shared per-widget settings map, separately managed from the gear fields:
	 * the Pro subclass writes it (the Lite base has no per-row Graph column), and
	 * the gear-save AJAX handler carries it forward across saves.
	 *
	 * @since 2.0.2
	 */
	public const ACTIVE_FORM_SETTING = 'active_form_id';

	/**
	 * Option holding the timestamp of the first Dashboard visit — the anchor the
	 * time-gated Form Abandonment notice counts from. Stamped by `Page::output()`,
	 * read here. An option (not user meta) so a single site-wide clock decides when
	 * the notice becomes eligible, mirroring how `Pro\Admin\Analytics\Page` anchors
	 * its AI notice on a cached option.
	 *
	 * @since 2.0.2
	 */
	public const FIRST_VISIT_OPTION = 'wpforms_dashboard_first_visit';

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'entries';
	}

	/**
	 * Get the widget title.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_title(): string {

		return __( 'Forms', 'wpforms-lite' );
	}

	/**
	 * Resolve the widget state.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Part of the widget contract; visibility is unconditional.

		return new WidgetState( true, $this->has_published_forms() ? 'data' : 'empty' );
	}

	/**
	 * Render the widget body per variant.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated dashboard data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string {

		// The empty ("No Forms") state — Lite copy; the Pro subclass supplies the "No Entry Data" copy.
		if ( $variant !== 'data' ) {
			return $this->render_empty_state(
				__( 'No Forms, Yet', 'wpforms-lite' ),
				__( 'Describe what you need and let WPForms AI build it for you, pick from dozens of templates, or start from scratch with the drag and drop builder.', 'wpforms-lite' ),
				$this->get_empty_state_ctas( __( 'Create a Form', 'wpforms-lite' ), __( 'Import Forms', 'wpforms-lite' ) )
			);
		}

		return (string) wpforms_render(
			'admin/dashboard/widgets/entries',
			$this->get_view_data( $data, $access ),
			true
		);
	}

	/**
	 * Build the data-state view: the resolved table rows and columns, the trend
	 * graph payload with its range bounds and per-form re-scope, and the Lite
	 * Connect bar.
	 *
	 * @since 2.0.2
	 *
	 * @param array         $data   Aggregated dashboard data.
	 * @param AccessContext $access Access context.
	 *
	 * @return array
	 */
	private function get_view_data( array $data, AccessContext $access ): array {

		$config     = $this->get_table_config( $access );
		$settings   = $this->get_resolved_settings();
		$show_graph = $settings['graph'];

		return [
			'rows'              => $this->get_table_rows( $data ),
			'columns'           => $config['columns'],
			'is_pro_analytics'  => $config['is_pro_analytics'],
			// The per-row buttons re-scope the trend graph, so they follow its visibility.
			'show_graph_column' => $config['show_graph_column'] && $show_graph,
			'graph'             => $this->get_graph_data( $data ),
			'show_graph'        => $show_graph,
			'range_start'       => (string) ( $data['range_start'] ?? '' ),
			'range_end'         => (string) ( $data['range_end'] ?? '' ),
			'active_form_id'    => $this->get_active_form_id( $data ),
			// Nothing can re-scope a hidden graph, so skip the per-form series query.
			'active_graph'      => $show_graph ? $this->get_active_graph( $data ) : [],
			// The backup bar sits between graph and table; Pro fills it with the restore CTA.
			'lite_connect_bar'  => $this->render_entries_backup_bar(),
			'graph_notice'      => $this->get_graph_notice(),
		];
	}

	/**
	 * Render the entry-backup bar that sits between the graph and the table.
	 *
	 * The Lite base renders the Lite Connect backup-status bar, and only once
	 * connected — the opt-in toggle belongs in the footer instead. Pro overrides
	 * this to offer restoring entries that Lite Connect backed up before the
	 * upgrade.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	protected function render_entries_backup_bar(): string {

		return $this->is_lite_connect_connected() ? $this->render_lite_connect_bar() : '';
	}

	/**
	 * Get the graph no-data notice copy (Lite base). Lite has no date picker, so
	 * the copy points at the data source instead of the range.
	 *
	 * @since 2.0.2
	 *
	 * @return array Notice copy: heading, description.
	 */
	protected function get_graph_notice(): array {

		if ( ! $this->is_lite_connect_connected() ) {
			return [
				'heading'     => __( 'No Entries Backed Up', 'wpforms-lite' ),
				'description' => __( 'Enable Form Entry Backups to see entry statistics.', 'wpforms-lite' ),
			];
		}

		return [
			'heading'     => __( 'No Entries Yet', 'wpforms-lite' ),
			'description' => __( 'Once you start getting entries, a chart with entry statistics will be shown here.', 'wpforms-lite' ),
		];
	}

	/**
	 * Render the widget footer: the Lite Connect bar — unless it already renders
	 * between graph and table (`render_body()`) — then the Form Abandonment notice.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated dashboard data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_footer( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $data is part of the contract signature; the footer content depends only on $variant/$access.

		$footer = '';

		if ( ! $this->is_lite_connect_connected() || $variant !== 'data' ) {
			$footer .= $this->render_lite_connect_bar();
		}

		// The Form Abandonment notice sits below the table, so it is data-state only.
		if ( $variant === 'data' ) {
			$footer .= $this->render_abandonment_promo( $access );
		}

		return $footer;
	}

	/**
	 * Declare the gear-menu "Display Options": the graph toggle, the row count,
	 * and the form picker.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated dashboard data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function get_settings_schema( string $variant, array $data, AccessContext $access ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $variant drives the gate; $data/$access are part of the contract signature.

		// No gear on the empty ("No Forms") state — there is nothing to configure.
		if ( $variant !== 'data' ) {
			return [];
		}

		$settings      = $this->get_resolved_settings();
		$count_options = [];

		for ( $number = self::MIN_ROWS; $number <= self::MAX_ROWS; $number++ ) {
			$count_options[ $number ] = number_format_i18n( $number );
		}

		return [
			[
				'type'    => 'checkboxes',
				'label'   => __( 'Display Options', 'wpforms-lite' ),
				'panel'   => true,
				'options' => [
					'graph' => __( 'Display Chart', 'wpforms-lite' ),
				],
				'value'   => [
					'graph' => $settings['graph'],
				],
			],
			[
				'type'      => 'select',
				'name'      => 'count',
				'label'     => __( 'Number of Forms', 'wpforms-lite' ),
				'options'   => $count_options,
				'value'     => $settings['count'],
				// A hand-picked selection sets the row count itself, so the cap is locked
				// while the form checklist below has anything checked.
				'locked_by' => 'forms',
			],
			[
				'type'        => 'checklist',
				'name'        => 'forms',
				'panel'       => true,
				'options'     => $this->get_ordered_form_choices( $settings['forms'] ),
				'value'       => $settings['forms'],
				// The selection sets the row count, so it shares the table's row ceiling.
				'max_checked' => self::MAX_ROWS,
			],
		];
	}

	/**
	 * Extract the sparse trend-graph series from the cached entries data. The
	 * series omits zero-entry days; the client zero-fills the gaps before charting.
	 *
	 * @since 2.0.2
	 *
	 * @param array $data Aggregated dashboard data (reads the `entries.graph` block).
	 *
	 * @return array List of { date: 'Y-m-d', count: int } points.
	 */
	private function get_graph_data( array $data ): array {

		$graph = $data['entries']['graph'] ?? [];

		return is_array( $graph ) ? $graph : [];
	}

	/**
	 * Resolve the form ID the graph is currently scoped to. Always site-wide (0)
	 * on the Lite base; the Pro subclass reads the persisted selection.
	 *
	 * @since 2.0.2
	 *
	 * @param array $data Aggregated dashboard data.
	 *
	 * @return int Active form ID, or 0 when the graph is scoped site-wide.
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function get_active_form_id( array $data ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Base seam; the Pro subclass reads $data.

		return 0;
	}

	/**
	 * Resolve the active form's trend series when the graph is scoped to one form.
	 * Empty on the Lite base (no re-scope); the site-wide series always ships
	 * separately, so a reset can restore it client-side.
	 *
	 * @since 2.0.2
	 *
	 * @param array $data Aggregated dashboard data.
	 *
	 * @return array List of { date: 'Y-m-d', count: int } points, empty when scoped site-wide.
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function get_active_graph( array $data ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Base seam; the Pro subclass reads $data.

		return [];
	}

	/**
	 * Get the table column configuration (Lite base: everything locked, no Graph
	 * column). The Pro subclass overrides per tier.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return array {
	 *     @type array $columns           Ordered column definitions (key, label, linked).
	 *     @type bool  $is_pro_analytics  Whether Interactions/Conversion are unlocked.
	 *     @type bool  $show_graph_column Whether the per-row Graph column is present.
	 * }
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function get_table_config( AccessContext $access ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $access is part of the contract; the Lite base is always the locked tier.

		return [
			'columns'           => [
				[
					'key'    => 'name',
					'label'  => __( 'Form Names', 'wpforms-lite' ),
					'linked' => true,
				],
				[
					'key'   => 'entries',
					'label' => __( 'Entries Backed Up', 'wpforms-lite' ),
				],
				[
					'key'   => 'views',
					'label' => __( 'Views', 'wpforms-lite' ),
				],
				[
					'key'   => 'interactions',
					'label' => __( 'Interactions', 'wpforms-lite' ),
				],
				[
					'key'   => 'conversion',
					'label' => __( 'Conversion', 'wpforms-lite' ),
				],
			],
			'is_pro_analytics'  => false,
			'show_graph_column' => false,
		];
	}

	/**
	 * Get entry counts for the given forms over the selected range.
	 *
	 * Empty on Lite, where the column reports Lite Connect backed-up entries: a form
	 * absent from the cached set genuinely has none, and the Lite cache applies no
	 * ceiling for a row to fall below. The Pro subclass counts them for real.
	 *
	 * @since 2.0.2
	 *
	 * @param array $form_ids Form IDs to count.
	 * @param array $data     Aggregated dashboard data (reads the range bounds).
	 *
	 * @return array Map of form_id => entry count.
	 */
	protected function get_entry_counts( array $form_ids, array $data ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Seam for the Pro override; Lite has no ceiling. NOSONAR same reason.

		return [];
	}

	/**
	 * Rebuild the selected range's WP-timezone date boundaries from the cached data.
	 *
	 * The render path only carries the `Y-m-d` range strings, so this restores the
	 * start-of-day / end-of-day instants `Datepicker` produces. The stamps must be
	 * checked for emptiness before parsing: `date_create_immutable( '' )` returns
	 * "now", not false, so a transient written without them (pre-stamp cache) would
	 * otherwise resolve to a today-only range.
	 *
	 * @since 2.0.2
	 *
	 * @param array $data Aggregated dashboard data.
	 *
	 * @return array {
	 *     @type DateTimeImmutable|null $0 Range start, or null when the range is absent/invalid.
	 *     @type DateTimeImmutable|null $1 Range end, or null when the range is absent/invalid.
	 * }
	 */
	protected function get_range_dates( array $data ): array {

		$range_start = (string) ( $data['range_start'] ?? '' );
		$range_end   = (string) ( $data['range_end'] ?? '' );

		if ( $range_start === '' || $range_end === '' ) {
			return [ null, null ];
		}

		$timezone = wp_timezone();
		$start    = date_create_immutable( $range_start, $timezone );
		$end      = date_create_immutable( $range_end, $timezone );

		if ( ! $start || ! $end ) {
			return [ null, null ];
		}

		return [ $start->setTime( 0, 0, 0 ), $end->setTime( 23, 59, 59 ) ];
	}

	/**
	 * Build a zero-filled row for a selected form absent from the cached superset.
	 * The Pro subclass adds the form's entries-page URL to the shape.
	 *
	 * @since 2.0.2
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array
	 */
	protected function get_zero_filled_row( int $form_id ): array {

		return [
			'form_id'     => $form_id,
			'title'       => get_the_title( $form_id ),
			'count'       => 0,
			'views'       => 0,
			'submissions' => 0,
		];
	}

	/**
	 * Render the widget for a freshly selected date range (the AJAX stats response),
	 * so the client can swap the whole widget in one step. Same signature as
	 * `Locations::render_for_range()`.
	 *
	 * @since 2.0.2
	 *
	 * @param array         $data   Aggregated dashboard data for the selected range.
	 * @param AccessContext $access Access context.
	 *
	 * @return string
	 */
	public function render_for_range( array $data, AccessContext $access ): string {

		$state = $this->get_state( $access );

		if ( ! $state->is_visible() ) {
			return '';
		}

		return $this->render( $state->get_variant(), $data, $access );
	}
}
