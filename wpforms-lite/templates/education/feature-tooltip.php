<?php
/**
 * Education feature-discovery tooltip.
 *
 * @since 2.0.0
 *
 * @var string $title           Tooltip heading (pre-escaped by consumer).
 * @var string $badge           Optional badge label (empty string when absent).
 * @var string $text            Body paragraph (pre-escaped by consumer).
 * @var string $section         Dismiss data-section identifier.
 * @var string $button_markup   Pre-built CTA anchor tag (may be empty).
 * @var string $learn_more_url  Learn more link URL (empty string when absent).
 * @var string $learn_more_text Learn more link text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpforms-education-feature-tooltip wpforms-dismiss-container wpforms-dismiss-out">
	<button
		type="button"
		class="wpforms-dismiss-button wpforms-education-feature-tooltip-close"
		title="<?php esc_attr_e( 'Dismiss this message.', 'wpforms-lite' ); ?>"
		data-section="<?php echo esc_attr( $section ); ?>"
	></button>

	<h4 class="wpforms-education-feature-tooltip-title">
		<?php echo esc_html( $title ); ?>
		<?php if ( $badge !== '' ) : ?>
			<span class="wpforms-education-feature-tooltip-badge"><?php echo esc_html( $badge ); ?></span>
		<?php endif; ?>
	</h4>

	<p class="wpforms-education-feature-tooltip-body">
		<?php echo esc_html( $text ); ?>
	</p>

	<?php if ( $button_markup !== '' || $learn_more_url !== '' ) : ?>
		<div class="wpforms-education-feature-tooltip-footer">
			<?php echo $button_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $learn_more_url !== '' ) : ?>
				<a href="<?php echo esc_url( $learn_more_url ); ?>" class="wpforms-education-feature-tooltip-learn-more" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $learn_more_text ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
