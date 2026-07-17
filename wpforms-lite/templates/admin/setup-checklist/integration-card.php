<?php
/**
 * Setup Checklist integration brand card.
 *
 * @since 2.0.0
 *
 * @var array $card Integration card: `slug`, `name`, `tier`, optional `icon`, and the
 *                  resolved `link` parts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tier = $card['tier'] ?? '';

$badge_labels = [
	'free'  => __( 'Free', 'wpforms-lite' ),
	'plus'  => __( 'Plus', 'wpforms-lite' ),
	'pro'   => __( 'Pro', 'wpforms-lite' ),
	'elite' => __( 'Elite', 'wpforms-lite' ),
];
$badge_text   = $badge_labels[ $tier ] ?? '';
$badge_color  = $tier === 'free' ? 'green' : 'platinum';
$icon_file    = $card['icon'] ?? 'addon-icon-' . $card['slug'] . '.png';
$icon_url     = WPFORMS_PLUGIN_URL . 'assets/images/' . $icon_file;

?>
<div class="wpforms-setup-checklist-promo__integration" data-slug="<?php echo esc_attr( $card['slug'] ); ?>">
	<?php
	if ( $badge_text !== '' ) {
		printf(
			'<span class="wpforms-badge wpforms-badge-sm wpforms-badge-%1$s wpforms-badge-rounded wpforms-setup-checklist-promo__integration-badge">%2$s</span>',
			esc_attr( $badge_color ),
			esc_html( $badge_text )
		);
	}
	?>
	<img class="wpforms-setup-checklist-promo__integration-icon" src="<?php echo esc_url( $icon_url ); ?>" alt="" width="40" height="40">
	<h3 class="wpforms-setup-checklist-promo__integration-name"><?php echo esc_html( $card['name'] ); ?></h3>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
	echo wpforms_render(
		'admin/setup-checklist/promo-link',
		[
			'base_class' => 'wpforms-setup-checklist-promo__integration-link',
			'link'       => $card['link'],
		],
		true
	);
	?>
</div>
