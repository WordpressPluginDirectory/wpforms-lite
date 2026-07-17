<?php
/**
 * Setup Checklist promo tile (icon + title + description + CTA link).
 *
 * @since 2.0.0
 *
 * @var array $tile Tile data: `icon` or `image`, `title`, `description`,
 *                  `link_text`, `link_url`, `link_external`, `link_action`, `link_plugin`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_image    = ! empty( $tile['image'] );
$icon_classes = [ 'wpforms-setup-checklist-promo__feature-icon' ];

if ( $has_image ) {
	$icon_classes[] = 'wpforms-setup-checklist-promo__feature-icon--brand';
}

?>
<div class="wpforms-setup-checklist-promo__feature">
	<span class="<?php echo esc_attr( implode( ' ', $icon_classes ) ); ?>">
		<?php if ( $has_image ) : ?>
			<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/' . $tile['image'] ); ?>" alt="" width="40" height="40">
		<?php else : ?>
			<i class="fa-solid <?php echo esc_attr( $tile['icon'] ); ?>" aria-hidden="true"></i>
		<?php endif; ?>
	</span>
	<div class="wpforms-setup-checklist-promo__feature-body">
		<h3 class="wpforms-setup-checklist-promo__feature-title"><?php echo esc_html( $tile['title'] ); ?></h3>
		<div class="wpforms-setup-checklist-promo__feature-content">
			<p class="wpforms-setup-checklist-promo__feature-desc"><?php echo esc_html( $tile['description'] ); ?></p>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
			echo wpforms_render(
				'admin/setup-checklist/promo-link',
				[
					'base_class' => 'wpforms-setup-checklist-promo__feature-link',
					'link'       => [
						'text'     => $tile['link_text'],
						'url'      => $tile['link_url'] ?? '#',
						'action'   => $tile['link_action'] ?? '',
						'plugin'   => $tile['link_plugin'] ?? '',
						'external' => ! empty( $tile['link_external'] ),
					],
				],
				true
			);
			?>
		</div>
	</div>
</div>
