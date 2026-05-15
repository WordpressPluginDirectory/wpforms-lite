<?php
/**
 * Also Available block.
 *
 * @since 1.7.8
 *
 * @var array $blocks All educational content blocks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpforms-panel-content-also-available">
	<?php
	foreach ( $blocks as $key => $block ) :

		if ( empty( $block['show'] ) ) {
			continue;
		}

		$slug  = strtolower( $key );
		$class = ! empty( $block['class'] ) ? $block['class'] : '';
		?>

		<div class="wpforms-panel-content-also-available-item <?php echo sanitize_html_class( "wpforms-panel-content-also-available-item-{$slug}" ); ?><?php echo ! empty( $block['badge'] ) ? ' wpforms-panel-content-also-available-item-has-badge' : ''; ?>">
			<?php if ( ! empty( $block['badge'] ) ) : ?>
				<span class="wpforms-badge wpforms-badge-sm wpforms-badge-rounded wpforms-badge-green wpforms-panel-content-also-available-item-badge">
					<svg width="11" height="11" viewBox="0 0 12 12" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M6 0.75l1.545 3.13 3.455 0.503-2.5 2.437 0.59 3.43L6 8.63l-3.09 1.624 0.59-3.43L1 4.383l3.455-0.503L6 0.75z"/></svg>
					<?php echo esc_html( $block['badge'] ); ?>
				</span>
			<?php endif; ?>
			<div class='wpforms-panel-content-also-available-item-logo'>
				<img src="<?php echo esc_url( $block['logo'] ); ?>" alt="<?php echo esc_attr( $block['title'] ); ?>">
			</div>

			<div class='wpforms-panel-content-also-available-item-info'>
				<h3><?php echo esc_html( $block['title'] ); ?></h3>
				<p><?php echo esc_html( $block['description'] ); ?></p>
				<?php
				$attrs_html = '';

				if ( ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
					foreach ( $block['attrs'] as $attr_key => $attr_value ) {
						$attrs_html .= sprintf(
							' %s="%s"',
							esc_attr( $attr_key ),
							esc_attr( $attr_value )
						);
					}
				}

				$is_external = empty( $block['attrs']['data-action'] )
					&& $block['link'] !== '#'
					&& strpos( $block['link'], admin_url() ) !== 0;
				$rel_attr    = $is_external ? ' target="_blank" rel="noopener noreferrer"' : '';
				?>
				<a class="<?php echo esc_attr( trim( $class ) ); ?>"
					href="<?php echo esc_url( $block['link'] ); ?>"<?php echo $rel_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $attrs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php echo esc_html( $block['link_text'] ); ?>
				</a>
			</div>
		</div>

	<?php endforeach; ?>
</div>
