<?php
/**
 * Shared addon-promo tile (icon/image + title + description + CTA link).
 *
 * Rendered by the Setup Checklist promo grids and the Dashboard FeaturesAddons widget.
 * Delegates the CTA to admin/addons/install-link.
 *
 * @since 2.0.2
 *
 * @var array $tile Tile data: `icon` or `image`, `title`, `description`, `link_text`,
 *                  `link_url`, `link_external`, `link_action`, `link_plugin`, and
 *                  `link_is_upgrade` when the CTA is a Pro-upgrade link.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_image    = ! empty( $tile['image'] );
$icon_classes = [ 'wpforms-addon-tile__icon' ];

if ( $has_image ) {
	$icon_classes[] = 'wpforms-addon-tile__icon--brand';
}

?>
<div class="wpforms-addon-tile">
	<span class="<?php echo esc_attr( implode( ' ', $icon_classes ) ); ?>">
		<?php if ( $has_image ) : ?>
			<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/' . $tile['image'] ); ?>" alt="" width="40" height="40">
		<?php else : ?>
			<i class="fa-solid <?php echo esc_attr( $tile['icon'] ); ?>" aria-hidden="true"></i>
		<?php endif; ?>
	</span>
	<div class="wpforms-addon-tile__body">
		<h3 class="wpforms-addon-tile__title"><?php echo esc_html( $tile['title'] ); ?></h3>
		<div class="wpforms-addon-tile__content">
			<p class="wpforms-addon-tile__desc"><?php echo esc_html( $tile['description'] ); ?></p>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
			echo wpforms_render(
				'admin/addons/install-link',
				[
					'base_class' => 'wpforms-addon-tile__link',
					'link'       => [
						'text'       => $tile['link_text'],
						'url'        => $tile['link_url'] ?? '#',
						'action'     => $tile['link_action'] ?? '',
						'plugin'     => $tile['link_plugin'] ?? '',
						'external'   => ! empty( $tile['link_external'] ),
						'is_upgrade' => ! empty( $tile['link_is_upgrade'] ),
					],
				],
				true
			);
			?>
		</div>
	</div>
</div>
