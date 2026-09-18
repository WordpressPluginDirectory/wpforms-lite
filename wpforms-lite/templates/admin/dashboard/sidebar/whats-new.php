<?php
/**
 * Dashboard "What's New" sidebar widget body.
 *
 * @since 2.0.2
 *
 * @var string $title       Most-recent post title.
 * @var string $content     Most-recent post description (trusted HTML).
 * @var string $image_url   Thumbnail URL. Empty when the post has no image.
 * @var bool   $is_hero     Whether the image is a hero, i.e. authored full-width.
 * @var string $modal_class CSS class that triggers the What's New modal.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_classes = [ 'wpforms-dashboard-whats-new-post', empty( $is_hero ) ? '' : 'is-hero' ];
?>
<div class="wpforms-dashboard-whats-new">
	<div <?php wpforms_html_attributes( '', $post_classes, [], [], true ); ?>>
		<div class="wpforms-dashboard-whats-new-text">
			<?php if ( ! empty( $title ) ) : ?>
				<h3 class="wpforms-dashboard-whats-new-title"><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>

			<?php if ( ! empty( $content ) ) : ?>
				<div class="wpforms-dashboard-whats-new-description">
					<?php echo wp_kses_post( $content ); ?>
				</div>
			<?php endif; ?>

			<a href="#" class="wpforms-dashboard-arrow-link <?php echo esc_attr( $modal_class ); ?>">
				<span class="wpforms-dashboard-arrow-link-text"><?php esc_html_e( 'See What Else is New', 'wpforms-lite' ); ?></span>
				<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
			</a>
		</div>

		<?php if ( ! empty( $image_url ) ) : ?>
			<div class="wpforms-dashboard-whats-new-thumb">
				<img src="<?php echo esc_url( $image_url ); ?>" alt="">
			</div>
		<?php endif; ?>
	</div>
</div>
