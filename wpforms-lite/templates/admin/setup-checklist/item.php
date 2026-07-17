<?php
/**
 * Setup Checklist item row.
 *
 * @since 2.0.0
 *
 * @var array $item Item view-model: `id`, `title`, `description`, `is_complete`,
 *                  `status_label`, and `ctas` (array of resolved, render-ready CTAs).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$classes = [ 'wpforms-setup-checklist-item' ];

if ( ! empty( $item['is_complete'] ) ) {
	$classes[] = 'is-complete';
}

?>
<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-item="<?php echo esc_attr( $item['id'] ); ?>">
	<span class="wpforms-setup-checklist-item__check" aria-hidden="true"></span>
	<div class="wpforms-setup-checklist-item__body">
		<span class="screen-reader-text"><?php echo esc_html( $item['status_label'] ); ?></span>
		<h3 class="wpforms-setup-checklist-item__title"><?php echo esc_html( $item['title'] ); ?></h3>
		<p class="wpforms-setup-checklist-item__description"><?php echo esc_html( $item['description'] ); ?></p>
	</div>
	<div class="wpforms-setup-checklist-item__actions">
		<?php
		// A complete item shows no buttons — the green check is the only cue.
		foreach ( $item['ctas'] as $cta ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
			echo wpforms_render( 'admin/setup-checklist/item-button', [ 'cta' => $cta ], true );
		}
		?>
	</div>
</div>
