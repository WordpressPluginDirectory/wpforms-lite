<?php
/**
 * Shared addon-promo CTA link: Upgrade / Install / Activate / Installed.
 *
 * @since 2.0.0
 *
 * @var string $base_class Block-specific link class.
 * @var array  $link       Link parts: `text`, `url`, `action`, `plugin`, `external`, optional
 *                         `data` (extra data-* attributes as key => value), and optional
 *                         `is_upgrade` marking the link as a Pro-upgrade CTA.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$link_action = $link['action'] ?? '';

$classes = [ $base_class ];

if ( $link_action === 'active' ) {
	$classes[] = 'is-active';
}

// Only an explicit upgrade CTA opens the Pro upgrade modal. This must not be inferred from
// `external` alone: an out-of-band install fallback (e.g. a wordpress.org plugin page) is also
// an external, action-less link, and would otherwise open the modal instead of navigating.
if ( ! empty( $link['is_upgrade'] ) ) {
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

	foreach ( $link['data'] ?? [] as $data_key => $data_value ) {
		printf( ' data-%s="%s"', esc_attr( $data_key ), esc_attr( $data_value ) );
	}
	?>
><?php echo esc_html( $link['text'] ), ( $link_action === 'active' ? '<i class="fa-regular fa-circle-check wpforms-addon-tile__link-icon" aria-hidden="true"></i>' : '' ); ?></a>
