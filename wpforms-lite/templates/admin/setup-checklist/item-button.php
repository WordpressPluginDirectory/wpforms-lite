<?php
/**
 * Setup Checklist item CTA button.
 *
 * @since 2.0.0
 *
 * @var array $cta CTA data: `label`, `url`, and optional `icon` (Font Awesome solid glyph),
 *                 `modifier` (button colour variant), `external` (open in a new tab),
 *                 `action` plus `plugin` (drive the install JS handler), `reload`
 *                 (reload the page on AJAX success instead of an in-place button swap),
 *                 `spinner` (reveal an inline loading spinner on click, for navigating CTAs),
 *                 and `disabled` (render as inert — no click handlers, greyed out via CSS).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$variants = [
	'secondary' => 'wpforms-btn-blue',
	'grey'      => 'wpforms-btn-light-grey',
];

$is_disabled = ! empty( $cta['disabled'] );

$classes = [
	'wpforms-btn',
	'wpforms-btn-sm',
	$variants[ $cta['modifier'] ?? '' ] ?? 'wpforms-btn-orange',
	'wpforms-setup-checklist-item__button',
];

?>
<a
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	href="<?php echo esc_url( $cta['url'] ?? '#' ); ?>"
	<?php
	if ( $is_disabled ) {
		echo ' aria-disabled="true" tabindex="-1"';
	}

	if ( ! empty( $cta['external'] ) ) {
		echo ' target="_blank" rel="noopener noreferrer"';
	}

	if ( ! $is_disabled && ! empty( $cta['action'] ) ) {
		printf( ' data-action="%s"', esc_attr( $cta['action'] ) );
	}

	if ( ! $is_disabled && ! empty( $cta['plugin'] ) ) {
		printf( ' data-plugin="%s"', esc_attr( $cta['plugin'] ) );
	}

	if ( ! $is_disabled && ! empty( $cta['reload'] ) ) {
		echo ' data-reload="1"';
	}
	?>
>
	<?php if ( ! empty( $cta['icon'] ) ) : ?>
		<i class="fa-solid <?php echo esc_attr( $cta['icon'] ); ?>" aria-hidden="true"></i>
	<?php endif; ?>
	<?php echo esc_html( $cta['label'] ); ?>
	<?php if ( ! empty( $cta['spinner'] ) ) : ?>
		<svg class="wpforms-setup-checklist-item__button-spinner" width="16" height="16" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path fill="currentColor" opacity=".25" d="M40 0C17.9 0 0 17.9 0 40s17.9 40 40 40 40-17.9 40-40S62.1 0 40 0zm0 72C22.3 72 8 57.7 8 40S22.3 8 40 8s32 14.3 32 32-14.3 32-32 32z"/><path fill="currentColor" d="M75.8 47.4h-.4c-2.2-.2-3.8-2.2-3.6-4.4.1-1 .1-2 .1-3C72 22.4 57.6 8 40 8c-2.2 0-4-1.8-4-4s1.8-4 4-4c22.1 0 40 17.9 40 40 0 1.3-.1 2.5-.2 3.8-.2 2.1-1.9 3.6-4 3.6z"/></svg>
	<?php endif; ?>
</a>
