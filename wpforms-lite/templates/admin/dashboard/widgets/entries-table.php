<?php
/**
 * Dashboard "Entries"/"Forms" widget — per-form table.
 *
 * Shared by the Lite base and the Pro subclass — tier differences arrive as data,
 * not as branching here. Unlocked metric cells link to the per-form Form Analytics
 * page; locked ones render the upgrade teaser built below. The Graph column (Basic+)
 * mirrors the Entries Overview toggle: chart-bar and red dismiss buttons that swap
 * as the graph scopes to that form.
 *
 * @since 2.0.2
 *
 * @var array $rows              Display-ready rows: form_id, title, edit_form_url, entries, entries_url, views, interactions, conversion.
 * @var array $columns           Ordered column definitions: key, label, linked.
 * @var bool  $is_pro_analytics  Whether the Interactions/Conversion metrics are unlocked.
 * @var bool  $show_graph_column Whether the per-row Graph column is present.
 * @var int   $active_form_id    Form the graph is scoped to (Basic+); 0 when site-wide.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The locked-cell teaser: a blurred value wrapped in the Form Analytics upgrade
// modal, mirroring the Forms Overview badge. The individual cell is the click target.
$render_locked_cell = static function ( string $tooltip ): string {
	return sprintf(
		'<a href="#" class="education-modal" title="%5$s" data-action="upgrade" data-name="%1$s" data-license="pro" data-banner-src="%2$s" data-utm-medium="Dashboard - Entries" data-utm-content="analytics-upgrade"><img src="%3$s" alt="%4$s" width="48" height="27"></a>',
		esc_attr__( 'Form Analytics', 'wpforms-lite' ),
		esc_url( WPFORMS_PLUGIN_URL . 'assets/images/education/analytics-preview.png' ),
		esc_url( WPFORMS_PLUGIN_URL . 'assets/images/education/blurred-value.svg' ),
		esc_attr__( 'Upgrade to Pro', 'wpforms-lite' ),
		esc_attr( $tooltip )
	);
};
?>
<div class="wpforms-dashboard-widget-entries-table wpforms-dashboard-widget-table-wrap wpforms-dashboard-widget-table-scroll wpforms-scrollbar-compact">
	<table class="wpforms-dashboard-widget-table">
		<thead>
			<tr>
				<?php foreach ( $columns as $column ) : ?>
					<th class="wpforms-dashboard-widget-entries-col-<?php echo esc_attr( $column['key'] ); ?>">
						<?php echo esc_html( $column['label'] ); ?>
					</th>
				<?php endforeach; ?>
				<?php if ( $show_graph_column ) : ?>
					<th class="wpforms-dashboard-widget-entries-col-graph"><?php esc_html_e( 'Chart', 'wpforms-lite' ); ?></th>
				<?php endif; ?>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr class="wpforms-dashboard-widget-entries-table-empty">
					<td colspan="<?php echo esc_attr( count( $columns ) + ( $show_graph_column ? 1 : 0 ) ); ?>"><?php esc_html_e( 'No entries yet.', 'wpforms-lite' ); ?></td>
				</tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<?php
				// The per-form Form Analytics page, reused by the views, interactions,
				// and conversion cells whenever they are unlocked (Pro/Elite).
				$analytics_url = add_query_arg(
					[
						'page'    => 'wpforms-analytics',
						'form_id' => (int) $row['form_id'],
					],
					admin_url( 'admin.php' )
				);
				?>
				<tr>
					<?php foreach ( $columns as $column ) : ?>
						<td class="wpforms-dashboard-widget-entries-col-<?php echo esc_attr( $column['key'] ); ?>">
							<?php

							switch ( $column['key'] ) {
								case 'name':
									printf(
										'<a href="%1$s">%2$s</a>',
										esc_url( $row['edit_form_url'] ),
										esc_html( $row['title'] )
									);
									break;

								case 'entries':
									$entries = number_format_i18n( $row['entries'] );

									if ( ! empty( $column['linked'] ) && ! empty( $row['entries_url'] ) ) {
										printf(
											'<a href="%1$s">%2$s</a>',
											esc_url( $row['entries_url'] ),
											esc_html( $entries )
										);
									} else {
										echo esc_html( $entries );
									}
									break;

								case 'views':
									$views = number_format_i18n( $row['views'] );

									if ( ! empty( $column['linked'] ) ) {
										printf(
											'<a href="%1$s">%2$s</a>',
											esc_url( $analytics_url ),
											esc_html( $views )
										);
									} else {
										echo esc_html( $views );
									}
									break;

								case 'interactions':
									if ( $is_pro_analytics ) {
										printf(
											'<a href="%1$s">%2$s</a>',
											esc_url( $analytics_url ),
											esc_html( number_format_i18n( $row['interactions'] ) )
										);
									} else {
										echo $render_locked_cell( __( 'Upgrade to Pro to unlock Interactions', 'wpforms-lite' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the closure.
									}
									break;

								case 'conversion':
									if ( $is_pro_analytics ) {
										printf(
											'<a href="%1$s">%2$s</a>',
											esc_url( $analytics_url ),
											esc_html( number_format_i18n( $row['conversion'], 1 ) . '%' )
										);
									} else {
										echo $render_locked_cell( __( 'Upgrade to Pro to unlock Conversion Rate', 'wpforms-lite' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the closure.
									}
									break;
							}

							?>
						</td>
					<?php endforeach; ?>
					<?php if ( $show_graph_column ) : ?>
						<?php $is_active = $active_form_id > 0 && $active_form_id === (int) $row['form_id']; ?>
						<td class="wpforms-dashboard-widget-entries-col-graph">
							<?php // A form with no entries in range has nothing to plot, so it gets no toggle. ?>
							<?php if ( (int) $row['entries'] > 0 ) : ?>
								<button type="button" class="wpforms-dashboard-widget-entries-graph-reset dashicons dashicons-dismiss<?php echo $is_active ? '' : ' wpforms-hide'; ?>"
									title="<?php esc_attr_e( 'Reset chart to display all forms', 'wpforms-lite' ); ?>"
									aria-label="<?php echo esc_attr( sprintf( /* translators: %s - form title. */ __( 'Reset the chart to all forms (currently showing %s)', 'wpforms-lite' ), $row['title'] ) ); ?>"></button>
								<button type="button" class="wpforms-dashboard-widget-entries-graph-btn<?php echo $is_active ? ' is-active wpforms-hide' : ''; ?>" data-form-id="<?php echo esc_attr( $row['form_id'] ); ?>"
									aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
									title="<?php esc_attr_e( 'Display only this form data in the chart', 'wpforms-lite' ); ?>"
									aria-label="<?php echo esc_attr( sprintf( /* translators: %s - form title. */ __( 'Show the chart for %s', 'wpforms-lite' ), $row['title'] ) ); ?>">
									<i class="dashicons dashicons-chart-bar" aria-hidden="true"></i>
								</button>
							<?php endif; ?>
						</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
