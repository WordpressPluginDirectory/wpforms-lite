<?php
/**
 * Dashboard "Top Locations" widget head — empty states.
 *
 * Title on the inline-start, the "example purposes only" disclaimer on the
 * inline-end. The data state uses the shell's default title + framework gear
 * instead, so this template is only rendered for the education/install states.
 *
 * @since 2.0.2
 *
 * @var string $title      Widget title (pre-escaped by the widget).
 * @var string $disclaimer Example-data disclaimer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2 class="wpforms-dashboard-widget-title"><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by Locations::get_title(). ?></h2>
<p class="wpforms-dashboard-widget-locations-disclaimer"><?php echo esc_html( $disclaimer ); ?></p>
