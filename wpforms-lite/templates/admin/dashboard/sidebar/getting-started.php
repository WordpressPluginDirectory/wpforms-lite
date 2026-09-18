<?php
/**
 * Dashboard "Getting Started" sidebar widget body.
 *
 * @since 2.0.2
 *
 * @var array $links    Documentation links: each item has `label` and `url`.
 * @var array $view_all "View All Documentation" link: `label` and `url`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
echo wpforms_render( 'admin/dashboard/doc-list', [ 'links' => $links ], true );
?>
<div class="wpforms-dashboard-getting-started-footer">
	<a class="wpforms-dashboard-arrow-link" href="<?php echo esc_url( $view_all['url'] ); ?>" target="_blank" rel="noopener noreferrer">
		<span class="wpforms-dashboard-arrow-link-text"><?php echo esc_html( $view_all['label'] ); ?></span>
		<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
	</a>
</div>
