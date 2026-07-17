<?php
/**
 * Setup Checklist promo footer bar: descriptive copy + a "View All" CTA.
 *
 * @since 2.0.0
 *
 * @var string $text     Footer copy (may contain `<strong>` emphasis).
 * @var string $cta_text CTA button label.
 * @var string $cta_url  CTA target URL.
 * @var string $modifier Optional CTA colour modifier (e.g. `secondary`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$modifier = $modifier ?? '';

// Appearance + hover come from the standard .wpforms-btn classes; the page class
// only sets the gap before the trailing arrow. Default orange; `secondary` is blue.
$cta_classes = [
	'wpforms-btn',
	'wpforms-btn-sm',
	$modifier === 'secondary' ? 'wpforms-btn-blue' : 'wpforms-btn-orange',
	'wpforms-setup-checklist-promo__footer-cta',
];

$is_external = wp_parse_url( $cta_url, PHP_URL_HOST ) !== wp_parse_url( admin_url(), PHP_URL_HOST );

?>
<div class="wpforms-setup-checklist-promo__footer">
	<p class="wpforms-setup-checklist-promo__footer-text"><?php echo wp_kses( $text, [ 'strong' => [] ] ); ?></p>
	<a class="<?php echo esc_attr( implode( ' ', $cta_classes ) ); ?>" href="<?php echo esc_url( $cta_url ); ?>"<?php echo $is_external ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
		<?php echo esc_html( $cta_text ); ?>
		<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
	</a>
</div>
