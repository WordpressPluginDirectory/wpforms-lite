<?php
/**
 * Dashboard "Top Locations" widget — empty-state card (education / install / no-data).
 *
 * A live sample table + donut (rendered statically, from `get_sample_view_data()`)
 * sits behind a centered card with a heading, body copy, and one or more action
 * buttons. Shared by the Lite education variant, the Pro install variant, and the
 * Pro data variant when the selected range has no location data; only the copy and
 * buttons differ.
 *
 * @since 2.0.2
 *
 * @var string $preview_html Sample table + donut markup (the `locations-chart` partial).
 * @var string $heading      Card heading.
 * @var string $description  Card body copy.
 * @var array  $ctas         Ordered action buttons, each: label, url, classes, target, attrs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpforms-dashboard-widget-locations-empty">
	<div class="wpforms-dashboard-widget-locations-preview" aria-hidden="true">
		<?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the locations-chart template, escaped there. ?>
	</div>

	<div class="wpforms-dashboard-widget-empty-card wpforms-dashboard-widget-locations-card">
		<h3 class="wpforms-dashboard-widget-empty-card-title"><?php echo esc_html( $heading ); ?></h3>
		<p class="wpforms-dashboard-widget-empty-card-description wpforms-dashboard-widget-locations-card-description"><?php echo esc_html( $description ); ?></p>

		<div class="wpforms-dashboard-widget-locations-card-actions">
			<?php foreach ( (array) $ctas as $cta ) : ?>
				<?php
				$cta_attrs = '';

				foreach ( (array) ( $cta['attrs'] ?? [] ) as $attr_name => $attr_value ) {
					$cta_attrs .= sprintf( ' %s="%s"', esc_attr( $attr_name ), esc_attr( $attr_value ) );
				}
				?>
				<a href="<?php echo esc_url( $cta['url'] ); ?>"
					class="<?php echo esc_attr( $cta['classes'] ); ?>"
					<?php echo ! empty( $cta['target'] ) ? 'target="' . esc_attr( $cta['target'] ) . '" rel="noopener noreferrer"' : ''; ?>
					<?php echo $cta_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute names and values escaped above. ?>>
					<?php echo esc_html( $cta['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</div>
