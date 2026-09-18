<?php
/**
 * Dashboard "Entries"/"Forms" widget — empty ("No Forms") state.
 *
 * A centered card (heading, body copy, action buttons) over a muted graph
 * backdrop. Shown on every tier when the site has no published forms; the table
 * and Form Abandonment notice are absent. Only the copy and the secondary import
 * button differ by tier. The backdrop is not inert: it reuses the live chart
 * wrapper class, so the widget-entries module draws the `data-config` sample
 * series into it as a real chart, which the widget SCSS then blurs.
 *
 * @since 2.0.2
 *
 * @var string $heading       Card heading.
 * @var string $description   Card body copy.
 * @var array  $buttons       Ordered action buttons, each: label, url, classes, target, attrs.
 * @var array  $preview_graph Sample `{ date, count }` points plotted dimmed behind the card.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wpforms-dashboard-widget-entries-empty">
	<div class="wpforms-dashboard-widget-entries-empty-preview" aria-hidden="true">
		<div class="wpforms-dashboard-widget-entries-chart"
			data-config="<?php echo esc_attr( wp_json_encode( [ 'graph' => $preview_graph ] ) ); ?>">
			<canvas></canvas>
		</div>
	</div>

	<div class="wpforms-dashboard-widget-entries-empty-card">
		<h3 class="wpforms-dashboard-widget-entries-empty-title"><?php echo esc_html( $heading ); ?></h3>
		<p class="wpforms-dashboard-widget-entries-empty-description"><?php echo esc_html( $description ); ?></p>

		<div class="wpforms-dashboard-widget-entries-empty-actions">
			<?php foreach ( (array) $buttons as $button ) : ?>
				<?php
				$button_attrs = '';

				foreach ( (array) ( $button['attrs'] ?? [] ) as $attr_name => $attr_value ) {
					$button_attrs .= sprintf( ' %s="%s"', esc_attr( $attr_name ), esc_attr( $attr_value ) );
				}
				?>
				<a href="<?php echo esc_url( $button['url'] ); ?>"
					class="<?php echo wpforms_sanitize_classes( $button['classes'] ); ?>"
					<?php echo ! empty( $button['target'] ) ? 'target="' . esc_attr( $button['target'] ) . '" rel="noopener noreferrer"' : ''; ?>
					<?php echo $button_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute names and values escaped above. ?>>
					<?php echo esc_html( $button['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</div>
