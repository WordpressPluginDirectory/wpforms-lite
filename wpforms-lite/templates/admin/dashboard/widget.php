<?php
/**
 * Dashboard widget shell template.
 *
 * @since 2.0.2
 *
 * @var string $id             Widget identifier (e.g. `entries`, `payments`).
 * @var string $variant        Widget variant; appended as the `wpforms-dashboard-widget-{id}-{variant}` state class.
 * @var array  $extra_classes  Extra state classes for the card root, declared by the widget.
 * @var bool   $is_dismissible Whether to add the `wpforms-dismiss-container` class to the card root.
 * @var string $title          Widget title.
 * @var string $head           Optional rich head HTML (title row). When empty, $title is rendered instead.
 * @var bool   $has_settings   Whether the widget has a settings (cog) menu.
 * @var string $settings       Settings popover rendered HTML (framework-built from the widget schema).
 * @var string $body           Widget body rendered HTML.
 * @var string $footer         Widget footer rendered HTML. Empty string hides the footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Card classes: the block, the widget itself, and (when set) its state. Styles hook these; data-widget stays the JS hook.
$widget_classes = [
	'wpforms-dashboard-widget',
	'wpforms-dashboard-widget-' . $id,
];

if ( $variant !== '' ) {
	$widget_classes[] = 'wpforms-dashboard-widget-' . $id . '-' . $variant;
}

if ( ! empty( $extra_classes ) ) {
	$widget_classes = array_merge( $widget_classes, wpforms_sanitize_classes( (array) $extra_classes ) );
}

if ( ! empty( $is_dismissible ) ) {
	$widget_classes[] = 'wpforms-dismiss-container';
}
?>
<div class="<?php echo esc_attr( implode( ' ', $widget_classes ) ); ?>" data-widget="<?php echo esc_attr( $id ); ?>">
	<div class="wpforms-dashboard-widget-head">
		<?php if ( $head !== '' ) : ?>
			<?php echo $head; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<h2 class="wpforms-dashboard-widget-title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>

		<?php if ( ! empty( $has_settings ) ) : ?>
			<button type="button" class="wpforms-dashboard-widget-cog" aria-label="<?php esc_attr_e( 'Widget settings', 'wpforms-lite' ); ?>">
				<i class="fa fa-gear" aria-hidden="true"></i>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $has_settings ) && ! empty( $settings ) ) : ?>
		<?php echo $settings; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the framework settings template, escaped there. ?>
	<?php endif; ?>

	<?php if ( $body !== '' ) : ?>
		<div class="wpforms-dashboard-widget-body">
			<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $footer ) ) : ?>
		<div class="wpforms-dashboard-widget-footer">
			<?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>
</div>
