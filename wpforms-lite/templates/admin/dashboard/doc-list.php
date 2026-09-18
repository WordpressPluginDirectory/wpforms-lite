<?php
/**
 * Dashboard shared documentation list.
 *
 * A vertical list of documentation links, each row rendered by the shared `doc-link` partial.
 * Reused by any dashboard widget that lists documentation articles.
 *
 * @since 2.0.2
 *
 * @var array $links Documentation links: each item has `label` and `url`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<ul class="wpforms-dashboard-doc-list">
	<?php foreach ( $links as $doc_link ) : ?>
		<?php
		// Filtered data: skip items without a URL or label.
		if ( empty( $doc_link['url'] ) || empty( $doc_link['label'] ) ) {
			continue;
		}
		?>
		<li>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
			echo wpforms_render( 'admin/dashboard/doc-link', $doc_link, true );
			?>
		</li>
	<?php endforeach; ?>
</ul>
