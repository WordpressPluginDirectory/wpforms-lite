<?php
/**
 * Setup Checklist promo CTA link: Upgrade / Install / Activate / Installed.
 *
 * @since 2.0.0
 *
 * @var string $base_class Block-specific link class.
 * @var array  $link       Link parts: `text`, `url`, `action`, `plugin`, `external`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$link_action = $link['action'] ?? '';

$classes = [ $base_class ];

if ( $link_action === 'active' ) {
	$classes[] = 'is-active';
}

if ( $link_action === '' && ! empty( $link['external'] ) ) {
	$classes[] = 'wpforms-upgrade-modal';
}

?>
<a
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	href="<?php echo esc_url( $link['url'] ?? '#' ); ?>"
	<?php
	if ( ! empty( $link['external'] ) ) {
		echo ' target="_blank" rel="noopener noreferrer"';
	}

	if ( $link_action !== '' ) {
		printf( ' data-action="%s"', esc_attr( $link_action ) );
	}

	if ( ! empty( $link['plugin'] ) ) {
		printf( ' data-plugin="%s"', esc_attr( $link['plugin'] ) );
	}
	?>
><?php echo esc_html( $link['text'] ), ( $link_action === 'active' ? '<i class="fa-regular fa-circle-check wpforms-setup-checklist-promo__feature-link-icon" aria-hidden="true"></i>' : '' ); ?></a>
