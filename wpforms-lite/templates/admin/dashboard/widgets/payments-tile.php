<?php
/**
 * Dashboard "Payments" widget — one stat tile button.
 *
 * Rendered for every tile, and a second time (hidden) beside the Coupons CTA, so
 * activating the addon can reveal a tile built from this same markup instead of
 * having JavaScript assemble a lookalike.
 *
 * @since 2.0.2
 *
 * @var array $tile         Tile view data (report, label, icon, tint, value, count, delta).
 * @var array $tile_classes Button CSS classes, state modifiers already applied.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<button type="button" class="<?php echo wpforms_sanitize_classes( $tile_classes, true ); ?>" data-stats="<?php echo esc_attr( $tile['report'] ); ?>">
	<span class="wpforms-dashboard-stat-card-icon wpforms-dashboard-stat-card-icon-<?php echo esc_attr( $tile['tint'] ); ?>">
		<i class="<?php echo esc_attr( $tile['icon'] ); ?>" aria-hidden="true"></i>
	</span>
	<span class="wpforms-dashboard-widget-payments-tile-content">
		<span class="statcard-label"><?php echo esc_html( $tile['label'] ); ?></span>
		<span class="statcard-value">
			<?php
			echo esc_html( $tile['value'] );

			// Emit the count flush against the value (no whitespace text node
			// between them) so it sits directly after the amount.
			if ( $tile['count'] !== '' ) {
				printf(
					'<span class="wpforms-dashboard-widget-payments-tile-count">(%s)</span>',
					esc_html( $tile['count'] )
				);
			}
			?>
		</span>
	</span>
	<?php if ( $tile['delta'] !== null ) : ?>
		<span
			class="statcard-delta is-calculated <?php echo esc_attr( $tile['delta'] >= 0 ? 'is-upward' : 'is-downward' ); ?>"
			role="presentation"
			title="<?php esc_attr_e( 'Comparison to previous period', 'wpforms-lite' ); ?>"
		><?php echo esc_html( (string) abs( $tile['delta'] ) ); ?></span>
	<?php endif; ?>
</button>
