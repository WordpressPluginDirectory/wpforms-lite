<?php
/**
 * Email Summary CTA button partial.
 *
 * Renders a blue, centered call-to-action button used by the Pro re-engagement
 * alert block inside the weekly summary email.
 *
 * @since 1.10.1.1
 *
 * @var string $cta_url  Button destination URL.
 * @var string $cta_text Button label text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: separate; margin: 0 auto;">
	<tr>
		<td class="button button-blue" align="center" border="1" valign="middle" style="background:#056aab; border:1px solid #056aab;">
			<a href="<?php echo esc_url( $cta_url ); ?>" class="button-link" rel="noopener noreferrer" target="_blank" bgcolor="#056aab">
				<?php echo esc_html( $cta_text ); ?>
			</a>
		</td>
	</tr>
</table>
