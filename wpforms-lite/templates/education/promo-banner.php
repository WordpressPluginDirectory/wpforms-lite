<?php
/**
 * Generic cross-promo banner template.
 *
 * Used by WPForms\Admin\Education\Promo\Banner descendants.
 *
 * @since 2.0.0.3
 *
 * @var string $banner_id       Unique banner identifier (kebab-case).
 * @var string $icon            Font Awesome icon classes.
 * @var string $heading         Banner heading.
 * @var string $badge           Plan badge text (e.g. "Free").
 * @var string $body            Banner body, may contain a single link.
 * @var string $cta_text        Install button label.
 * @var string $setup_text      Post-install button label.
 * @var string $setup_url       Post-install button URL.
 * @var array  $button_attrs    Data attributes for the install button, without the `data-` prefix.
 * @var string $dismiss_section Education dismissal section slug.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$button_data = array_merge(
	$button_attrs,
	[
		'setup-url'  => $setup_url,
		'setup-text' => $setup_text,
	]
);

// The end of the heading and the badge travel to the next line as one piece, so the badge can
// never land on a line of its own. A short word before the last one joins them, otherwise it
// would be left dangling at the end of the previous line.
$heading_words = explode( ' ', $heading );
$words_count   = count( $heading_words );
$tail_size     = $words_count > 1 && mb_strlen( $heading_words[ $words_count - 2 ] ) <= 3 ? 2 : 1;
$heading_tail  = implode( ' ', array_splice( $heading_words, -$tail_size ) );
$heading_start = $heading_words ? implode( ' ', $heading_words ) . ' ' : '';
?>
<section class="wpforms-education-promo-banner wpforms-dismiss-container" data-banner-id="<?php echo esc_attr( $banner_id ); ?>">
	<div class="wpforms-education-promo-banner-icon">
		<i class="<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i>
	</div>
	<div class="wpforms-education-promo-banner-text">
		<h4>
			<?php // The heading tail and the badge are printed without whitespace in between to keep the gap exact. ?>
			<?php echo esc_html( $heading_start ); ?><span class="wpforms-education-promo-banner-heading-end"><?php echo esc_html( $heading_tail ); ?><span class="wpforms-badge wpforms-badge-sm wpforms-badge-inline wpforms-badge-rounded wpforms-education-promo-banner-badge"><?php echo esc_html( $badge ); ?></span></span>
		</h4>
		<p>
			<?php
			echo wp_kses(
				$body,
				[
					'a' => [
						'href'   => [],
						'class'  => [],
						'target' => [],
						'rel'    => [],
					],
				]
			);
			?>
		</p>
	</div>
	<button type="button" class="wpforms-btn wpforms-btn-sm wpforms-btn-orange wpforms-education-promo-banner-install" <?php wpforms_html_attributes( '', [], $button_data, [], true ); ?>>
		<?php echo esc_html( $cta_text ); ?>
	</button>
	<button type="button" class="wpforms-dismiss-button dashicons-no-alt" title="<?php esc_attr_e( 'Dismiss this message.', 'wpforms-lite' ); ?>" data-section="<?php echo esc_attr( $dismiss_section ); ?>"></button>
</section>
