<?php
/**
 * Dashboard "Payments" widget — data state.
 *
 * Graph canvas, the 3x2 tile grid, and the recent-payments table, seeded from the cached
 * `payments` block plus a live recent-payments query.
 *
 * @since 2.0.2
 *
 * @var array  $tiles        Ordered tile view data: `report`, `label`, `icon`, `tint`,
 *                            `button_classes`, `value`, `count`, `delta`, `is_selected`, `is_hidden`.
 * @var array  $tile_values  Raw tile values keyed by cache tile key (for the JS zero-value check).
 * @var array  $graph        Cached daily timeseries rows: `[ 'day' => …, 'count' => … ]`.
 * @var string $graph_report The report key the cached graph series represents (default `total_sales`).
 * @var array  $payments     Recent-payments table rows: `number`, `name`, `url`, `date_rel`,
 *                           `date_abs`, `type_label`, `total_formatted`, `status_key`, `status_label`.
 * @var int    $visible_rows Number of table rows to display; any extra rows are hidden (default `DEFAULT_PAYMENTS`).
 * @var array  $coupons_cta  Coupons tile CTA when the addon is inactive: `label`, `url`,
 *                           `classes`, `attrs`. Empty when the addon is active.
 * @var string $currency           Site currency code.
 * @var string $widget_id          Widget identifier (`payments`).
 * @var bool   $display_graph      Whether the graph is visible (gear setting).
 * @var bool   $display_stat_cards Whether the stat-cards grid is visible (gear setting).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$graph_config = [
	'graph'        => $graph,
	'graph_report' => $graph_report,
	'currency'     => $currency,
	'tiles'        => $tile_values,
];

$selected_label = '';

foreach ( $tiles as $tile ) {
	if ( $tile['is_selected'] ) {
		$selected_label = $tile['label'];

		break;
	}
}

/* translators: %s is the stat card label (e.g. "Total Sales"). */
$graph_aria_label = sprintf( __( 'Payments trend chart for %s.', 'wpforms-lite' ), $selected_label );

// Honor the gear "Display" toggles on initial render (the JS mirrors this on save).
$graph_classes = 'wpforms-dashboard-widget-payments-graph' . ( $display_graph ? '' : ' wpforms-hide' );
$tiles_classes = 'wpforms-dashboard-widget-payments-tiles' . ( $display_stat_cards ? '' : ' wpforms-hide' );
?>
<div class="wpforms-dashboard-widget-payments" data-widget="<?php echo esc_attr( $widget_id ); ?>">

	<div class="<?php echo esc_attr( $graph_classes ); ?>" data-config="<?php echo esc_attr( wp_json_encode( $graph_config ) ); ?>">
		<div class="spinner"></div>
		<div class="wpforms-overview-chart-notice wpforms-hide">
			<div class="wpforms-overview-chart-notice-content wpforms-dashboard-widget-empty-card">
				<h2 class="wpforms-dashboard-widget-empty-card-title"><?php esc_html_e( 'No Payments for Selected Period', 'wpforms-lite' ); ?></h2>
				<p class="wpforms-dashboard-widget-empty-card-description"><?php esc_html_e( 'Please select a different period or check back later.', 'wpforms-lite' ); ?></p>
			</div>
		</div>
		<canvas
			id="wpforms-dashboard-payments-overview-canvas"
			role="img"
			aria-label="<?php echo esc_attr( $graph_aria_label ); ?>"
		></canvas>
	</div>

	<div class="<?php echo esc_attr( $tiles_classes ); ?>">
		<?php foreach ( $tiles as $tile ) : ?>
			<?php
			$tile_classes = (array) $tile['button_classes'];

			if ( $tile['is_selected'] ) {
				$tile_classes[] = 'is-selected';
			}

			// Hidden by the Stat Cards gear setting — rendered but collapsed so the
			// gear can reveal it client-side without a reload.
			if ( ! empty( $tile['is_hidden'] ) ) {
				$tile_classes[] = 'wpforms-hide';
			}

			$show_coupons_cta = $tile['report'] === 'total_coupons' && $tile['value'] === '' && ! empty( $coupons_cta );
			?>
			<?php if ( $show_coupons_cta ) : ?>
				<div class="<?php echo wpforms_sanitize_classes( $tile_classes, true ); ?> wpforms-dashboard-widget-payments-tile-cta">
					<span class="wpforms-dashboard-stat-card-icon wpforms-dashboard-stat-card-icon-<?php echo esc_attr( $tile['tint'] ); ?>">
						<i class="<?php echo esc_attr( $tile['icon'] ); ?>" aria-hidden="true"></i>
					</span>
<span class="wpforms-dashboard-widget-payments-tile-content">
						<span class="statcard-label"><?php echo esc_html( $tile['label'] ); ?></span>

						<?php
						$coupons_cta_attrs = '';

						foreach ( (array) ( $coupons_cta['attrs'] ?? [] ) as $attr_name => $attr_value ) {
							$coupons_cta_attrs .= sprintf( ' %s="%s"', esc_attr( $attr_name ), esc_attr( $attr_value ) );
						}
						?>
						<a
							href="<?php echo esc_url( $coupons_cta['url'] ); ?>"
							class="<?php echo esc_attr( $coupons_cta['classes'] ); ?>"
							<?php echo $coupons_cta_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute names/values escaped above. ?>
						>
							<?php echo esc_html( $coupons_cta['label'] ); ?> <span aria-hidden="true">&rarr;</span>
						</a>
					</span>
				</div>
				<?php
				// The real tile, rendered now and hidden, so activating the addon reveals
				// markup identical to a reload's instead of a JavaScript-built lookalike.
				// It carries no value yet — the activation handler fetches that.
				//
				// `is-selected` is dropped: while the addon is inactive the CTA above is what
				// represents this report on screen and carries the marker, so leaving it here
				// too would hand the revealed tile a green selected accent inherited from page
				// load rather than from the current selection.
				$pending_classes = array_diff( $tile_classes, [ 'is-selected' ] );

				$pending_classes[] = 'wpforms-dashboard-widget-payments-tile-pending';

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
				echo wpforms_render(
					'admin/dashboard/widgets/payments-tile',
					[
						'tile'         => $tile,
						'tile_classes' => $pending_classes,
					],
					true
				);
				?>
			<?php else : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
				echo wpforms_render(
					'admin/dashboard/widgets/payments-tile',
					[
						'tile'         => $tile,
						'tile_classes' => $tile_classes,
					],
					true
				);
				?>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<?php // With no rows the table area is omitted entirely (the graph already carries the empty state). The list is not range-scoped, so rows cannot appear without a reload. ?>
	<?php if ( ! empty( $payments ) ) : ?>
		<div class="wpforms-dashboard-widget-payments-table wpforms-dashboard-widget-table-wrap wpforms-dashboard-widget-table-scroll wpforms-scrollbar-compact">
			<table class="wpforms-dashboard-widget-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Payment', 'wpforms-lite' ); ?></th>
						<th><?php esc_html_e( 'Date', 'wpforms-lite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'wpforms-lite' ); ?></th>
						<th><?php esc_html_e( 'Total', 'wpforms-lite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wpforms-lite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $payments as $index => $payment ) : ?>
						<tr<?php echo $index >= $visible_rows ? ' class="wpforms-dash-widget-forms-list-hidden-el"' : ''; ?>>
							<td>
								<a href="<?php echo esc_url( $payment['url'] ); ?>">
									<?php echo esc_html( $payment['number'] ); ?>
									<?php if ( $payment['name'] !== '' ) : ?>
										- <?php echo esc_html( $payment['name'] ); ?>
									<?php endif; ?>
								</a>
							</td>
							<td><span title="<?php echo esc_attr( $payment['date_abs'] ); ?>"><?php echo esc_html( $payment['date_rel'] ); ?></span></td>
							<td><?php echo esc_html( $payment['type_label'] ); ?></td>
							<td><?php echo esc_html( $payment['total_formatted'] ); ?></td>
							<td>
								<span class="wpforms-payment-status status-<?php echo esc_attr( $payment['status_key'] ); ?>">
									<?php echo esc_html( $payment['status_label'] ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
