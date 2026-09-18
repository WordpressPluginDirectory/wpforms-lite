<?php
/**
 * Dashboard stat card template.
 *
 * @since 2.0.2
 *
 * @var string $id    Stat card identifier (e.g. `forms`, `total_entries`).
 * @var string $icon  Font Awesome family + icon classes (e.g. `fa-regular fa-copy`).
 * @var string $tint  Icon tint modifier (orange|red|blue|gray|green).
 * @var string $label       Stat card label.
 * @var string $label_short  Shorter label alternative, shown on narrow cards ('' = none).
 * @var string $value        Formatted stat card value.
 * @var string $value_short  Abbreviated value alternative, shown on narrow cards ('' = none).
 * @var array  $cta          Conditional CTA data (empty array = no CTA).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Render a text value with an optional shorter alternative. When a short
 * variant exists both are emitted; a container query in the stat-card SCSS
 * shows one and hides the other by card width. The label/CTA ellipsis remains
 * the fallback for long or translated strings that overflow either variant.
 *
 * The `$variant` class infix selects which swap the pair answers to: labels and
 * CTAs share the `text` threshold, while values swap on their own wider one —
 * a long amount stops fitting before a label does.
 *
 * `$tooltip_full` titles the short variant with the full text, for variants
 * where the short form is lossy and the exact one is worth recovering. It sits
 * on the short span alone, so a card wide enough to show the full text does not
 * get a tooltip repeating it.
 */
$render_text = static function ( string $full, string $short, string $variant = 'text', bool $tooltip_full = false ): void {

	if ( $short === '' ) {
		echo esc_html( $full );

		return;
	}

	printf(
		'<span class="wpforms-dashboard-stat-card-%1$s-full">%2$s</span><span %3$s>%4$s</span>',
		esc_attr( $variant ),
		esc_html( $full ),
		wpforms_html_attributes(
			'',
			[ "wpforms-dashboard-stat-card-{$variant}-short" ],
			[],
			// An empty title is dropped rather than rendered blank.
			[ 'title' => $tooltip_full ? $full : '' ]
		),
		esc_html( $short )
	);
};
?>
<div class="wpforms-dashboard-stat-card" data-stat-card="<?php echo esc_attr( $id ); ?>">
	<div class="wpforms-dashboard-stat-card-icon wpforms-dashboard-stat-card-icon-<?php echo esc_attr( $tint ); ?>">
		<i class="<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i>
	</div>
	<div class="wpforms-dashboard-stat-card-content">
		<p class="wpforms-dashboard-stat-card-label"><?php $render_text( $label, $label_short ); ?></p>
		<?php
		/*
		 * A CTA that replaces the value normally means no value element at all. For the
		 * in-place install/activate CTA it is rendered hidden instead, so activating the
		 * addon can reveal the real figure rather than have JavaScript assemble it.
		 *
		 * Restricted to that CTA type on purpose: an `upgrade`/`license` CTA is shown
		 * precisely to people not entitled to the number, so it must not reach their DOM.
		 */
		$is_pending_value = ! empty( $cta['replace_value'] ) && ( $cta['type'] ?? '' ) === 'education';
		$value_classes    = [ 'wpforms-dashboard-stat-card-value' ];

		if ( $is_pending_value ) {
			$value_classes[] = 'wpforms-hidden';
		}
		?>
		<?php if ( empty( $cta['replace_value'] ) || $is_pending_value ) : ?>
			<p class="<?php echo esc_attr( implode( ' ', $value_classes ) ); ?>"><?php $render_text( $value, $value_short, 'value', true ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $cta ) ) : ?>
			<?php if ( $cta['type'] === 'anti_spam' ) : ?>
				<button type="button" class="wpforms-dashboard-stat-card-cta wpforms-dashboard-stat-card-anti-spam-toggle" aria-expanded="false">
					<?php echo esc_html( $cta['label'] ); ?>
				</button>
				<div class="wpforms-education-feature-tooltip wpforms-dashboard-stat-card-tooltip wpforms-hidden">
					<button type="button" class="wpforms-education-feature-tooltip-close wpforms-dashboard-stat-card-tooltip-close" aria-label="<?php esc_attr_e( 'Close', 'wpforms-lite' ); ?>"></button>
					<p class="wpforms-education-feature-tooltip-body">
						<?php
						echo wp_kses(
							$cta['tooltip']['text'],
							[
								'a'      => [ 'href' => [] ],
								'strong' => [],
							]
						);
						?>
					</p>
					<div class="wpforms-education-feature-tooltip-footer">
						<?php
						$tooltip_button_classes = array_merge(
							[ 'wpforms-btn', 'wpforms-btn-sm', 'wpforms-btn-blue' ],
							explode( ' ', (string) $cta['tooltip']['button']['class'] )
						);
						?>
						<a href="<?php echo esc_url( $cta['tooltip']['button']['link'] ); ?>" <?php wpforms_html_attributes( '', $tooltip_button_classes, [], (array) $cta['tooltip']['button']['attrs'], true ); ?>>
							<?php echo esc_html( $cta['tooltip']['button']['link_text'] ); ?>
						</a>
						<a href="<?php echo esc_url( $cta['tooltip']['learn_more'] ); ?>" class="wpforms-education-feature-tooltip-learn-more" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Learn More', 'wpforms-lite' ); ?>
						</a>
					</div>
				</div>
			<?php else : ?>
				<?php
				// Merge any caller class into the base class list so a stray `class`
				// attribute doesn't render twice.
				$cta_attrs   = (array) ( $cta['attrs'] ?? [] );
				$cta_classes = array_merge(
					[ 'wpforms-dashboard-stat-card-cta' ],
					explode( ' ', (string) ( $cta_attrs['class'] ?? '' ) )
				);

				unset( $cta_attrs['class'] );
				?>
				<a href="<?php echo esc_url( $cta['url'] ); ?>"  <?php wpforms_html_attributes( '', $cta_classes, [], $cta_attrs, true ); ?>>
					<?php $render_text( $cta['label'], $cta['label_short'] ?? '' ); ?>
				</a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
