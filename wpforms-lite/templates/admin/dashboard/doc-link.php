<?php
/**
 * Dashboard shared doc-link row.
 *
 * A single documentation link (page icon + label) that opens in a new tab.
 * Reused by any dashboard widget that lists documentation articles.
 *
 * @since 2.0.2
 *
 * @var string $label Link text.
 * @var string $url   Link URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<a class="wpforms-dashboard-doc-link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
	<i class="fa-regular fa-file-lines" aria-hidden="true"></i>
	<span><?php echo esc_html( $label ); ?></span>
</a>
