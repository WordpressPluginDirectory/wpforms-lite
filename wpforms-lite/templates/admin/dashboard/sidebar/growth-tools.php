<?php
/**
 * Dashboard "Recommended Growth Tools" sidebar widget body.
 *
 * Renders a 2-column grid of bordered plugin tiles (24px brand icon + name, description,
 * and an in-place Install/Activate/Installed CTA). The CTA reuses the shared
 * admin/addons/install-link partial so the addon-tiles.js module binds to it.
 *
 * @since 2.0.2
 *
 * @var array $tiles Growth-tool tiles: each with `image`, `title`, `description`,
 *                   `link_text`, `link_url`, `link_action`, `link_plugin`, `link_external`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wpforms-growth-tools-grid">
	<?php foreach ( $tiles as $tile ) : ?>
		<div class="wpforms-growth-tool">
			<div class="wpforms-growth-tool-head">
				<span class="wpforms-growth-tool-icon">
					<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/' . $tile['image'] ); ?>" alt="" width="24" height="24">
				</span>
				<span class="wpforms-growth-tool-name"><?php echo esc_html( $tile['title'] ); ?></span>
			</div>

			<p class="wpforms-growth-tool-desc"><?php echo esc_html( $tile['description'] ); ?></p>

			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
			echo wpforms_render(
				'admin/addons/install-link',
				[
					'base_class' => 'wpforms-addon-tile__link',
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
	<?php endforeach; ?>
</div>
