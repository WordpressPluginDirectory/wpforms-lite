<?php
/**
 * Setup Checklist promo section header: title, optional subtitle, and collapse toggle.
 *
 * @since 2.0.0
 *
 * @var string $body_id  ID of the collapsible body the toggle controls.
 * @var string $title    Promo title.
 * @var string $subtitle Optional promo subtitle.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$subtitle = $subtitle ?? '';

?>
<div class="wpforms-setup-checklist-promo__header">
	<h2 class="wpforms-setup-checklist-promo__title"><?php echo esc_html( $title ); ?></h2>
	<?php if ( $subtitle !== '' ) : ?>
		<span class="wpforms-setup-checklist-promo__subtitle"><?php echo esc_html( $subtitle ); ?></span>
	<?php endif; ?>
	<button type="button" class="wpforms-setup-checklist-promo__toggle" aria-expanded="true" aria-controls="<?php echo esc_attr( $body_id ); ?>">
		<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
		<span class="screen-reader-text"><?php esc_html_e( 'Toggle section', 'wpforms-lite' ); ?></span>
	</button>
</div>
