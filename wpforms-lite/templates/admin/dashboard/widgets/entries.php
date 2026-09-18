<?php
/**
 * Dashboard "Entries"/"Forms" widget body.
 *
 * Wraps the trend graph and the per-form entries table (the filled data state).
 * The empty ("No Forms") state is a separate template (`entries-empty`). The graph
 * region mirrors the Payments widget graph: a spinner overlay, the shared no-data
 * notice, and a bare canvas, carrying its sparse series as a `data-config` payload
 * the widget-entries module reads to draw the chart; it is skipped when the
 * "Display Graph" setting is off. The body div carries the range separately — it
 * must survive the graph-off state, where no chart region (and no config) renders.
 *
 * @since 2.0.2
 *
 * @var array  $rows              Display-ready table rows.
 * @var array  $columns           Ordered column definitions (key, label, linked).
 * @var bool   $is_pro_analytics  Whether the Interactions/Conversion metrics are unlocked.
 * @var bool   $show_graph_column Whether the per-row Graph column is present.
 * @var array  $graph             Sparse trend series: list of { date: 'Y-m-d', count: int } points.
 * @var bool   $show_graph        Whether the trend graph region is shown.
 * @var string $range_start       Selected range start date (Y-m-d); spans the graph X axis.
 * @var string $range_end         Selected range end date (Y-m-d); spans the graph X axis.
 * @var int    $active_form_id    Form the graph is scoped to (Basic+); 0 when site-wide.
 * @var array  $active_graph      Active form's sparse series when scoped; empty when site-wide.
 * @var string $lite_connect_bar  Backup-status bar markup between graph and table (Lite, connected); empty otherwise.
 * @var array  $graph_notice      Tier-aware no-data notice copy: heading, description.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$graph_config = [
	'graph'       => $graph,
	'activeGraph' => $active_graph,
];
?>
<div class="wpforms-dashboard-widget-entries-body"
	data-range-start="<?php echo esc_attr( $range_start ); ?>"
	data-range-end="<?php echo esc_attr( $range_end ); ?>">
	<?php if ( $show_graph ) : ?>
		<div class="wpforms-dashboard-widget-entries-chart"
			data-config="<?php echo esc_attr( wp_json_encode( $graph_config ) ); ?>">
			<div class="spinner"></div>
			<div class="wpforms-overview-chart-notice wpforms-hide">
				<div class="wpforms-overview-chart-notice-content wpforms-dashboard-widget-empty-card">
					<h2 class="wpforms-dashboard-widget-empty-card-title"><?php echo esc_html( $graph_notice['heading'] ?? '' ); ?></h2>
					<p class="wpforms-dashboard-widget-empty-card-description"><?php echo esc_html( $graph_notice['description'] ?? '' ); ?></p>
				</div>
			</div>
			<canvas
				role="img"
				aria-label="<?php esc_attr_e( 'Entries over time chart', 'wpforms-lite' ); ?>"
			></canvas>
		</div>
	<?php endif; ?>
	<?php echo $lite_connect_bar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered template output, escaped within the partial. ?>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered template output, escaped within the partial.
	echo wpforms_render(
		'admin/dashboard/widgets/entries-table',
		[
			'rows'              => $rows,
			'columns'           => $columns,
			'is_pro_analytics'  => $is_pro_analytics,
			'show_graph_column' => $show_graph_column,
			'active_form_id'    => $active_form_id,
		],
		true
	);
	?>
</div>
