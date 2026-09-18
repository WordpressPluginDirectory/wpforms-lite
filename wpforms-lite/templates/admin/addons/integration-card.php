<?php
/**
 * Shared integration brand card, rendered by the Setup Checklist and the Dashboard.
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
<div class="wpforms-integration-card" data-slug="<?php echo esc_attr( $card['slug'] ); ?>">
	<?php
	if ( $badge_text !== '' ) {
		printf(
			'<span class="wpforms-badge wpforms-badge-sm wpforms-badge-%1$s wpforms-badge-rounded wpforms-integration-card__badge">%2$s</span>',
			esc_attr( $badge_color ),
			esc_html( $badge_text )
		);
	}
	?>
	<img class="wpforms-integration-card__icon" src="<?php echo esc_url( $icon_url ); ?>" alt="" width="40" height="40">
	<h3 class="wpforms-integration-card__name"><?php echo esc_html( $card['name'] ); ?></h3>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
	echo wpforms_render(
		'admin/addons/install-link',
		[
			'base_class' => 'wpforms-integration-card__link',
			'link'       => $card['link'],
		],
		true
	);
	?>
</div>
