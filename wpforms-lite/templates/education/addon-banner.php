<?php
/**
 * Product education addon banner template.
 *
 * Used by WPForms\Admin\Education\Builder\AddonBanners.
 *
 * @since 2.0.1
 *
 * @var string $banner_id       Unique banner identifier (kebab-case).
 * @var string $dismiss_section Education dismissal section slug.
 * @var string $heading         Banner heading.
 * @var string $body            Banner body, contains the addon name link.
 * @var array  $cta             CTA definition: `text`, `title`, `class`, and `attrs` (data attributes without the `data-` prefix).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<section class="wpforms-education-addon-banner wpforms-dismiss-container wpforms-dismiss-out" data-banner-id="<?php echo esc_attr( $banner_id ); ?>">
	<div class="wpforms-education-addon-banner-icon">
		<i class="fa fa-lightbulb-o" aria-hidden="true"></i>
	</div>
	<div class="wpforms-education-addon-banner-text">
		<h4><?php echo esc_html( $heading ); ?></h4>
		<p>
			<?php
			echo wp_kses(
				$body,
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
						'title'  => [],
					],
				]
			);
			?>
		</p>
	</div>
	<a href="#" class="wpforms-education-addon-banner-cta wpforms-link-orange <?php echo esc_attr( $cta['class'] ); ?>" title="<?php echo esc_attr( $cta['title'] ); ?>" <?php wpforms_html_attributes( '', [], $cta['attrs'], [], true ); ?>>
		<?php echo esc_html( $cta['text'] ); ?>
	</a>
	<button type="button" class="wpforms-dismiss-button" title="<?php esc_attr_e( 'Dismiss', 'wpforms-lite' ); ?>" data-section="<?php echo esc_attr( $dismiss_section ); ?>"></button>
</section>
