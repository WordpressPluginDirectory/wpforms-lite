<?php
/**
 * Dashboard widget notice — a light in-body banner stating why a widget has nothing
 * to show. Framework template: any widget can render it instead of its data body.
 *
 * @since 2.0.2
 *
 * @var string $message Notice text.
 * @var string $label   Optional leading label, rendered in bold before the text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$message = $message ?? '';
$label   = $label ?? '';
?>
<div class="wpforms-dashboard-widget-banner">
	<p class="wpforms-dashboard-widget-banner-text">
		<?php if ( $label !== '' ) : ?>
			<span class="wpforms-dashboard-widget-banner-label"><?php echo esc_html( $label ); ?></span> &mdash;
		<?php endif; ?>
		<?php echo esc_html( $message ); ?>
	</p>
</div>
