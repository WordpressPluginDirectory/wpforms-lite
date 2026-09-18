<?php
/**
 * Dashboard "Spam & Security Checkup" sidebar widget body.
 *
 * Two checklist groups of two mutually-substitutable rows, then a documentation list that
 * reuses the shared dashboard doc-list partial.
 *
 * @since 2.0.2
 *
 * @var array  $groups     Checklist groups: each with `title` and `rows`.
 * @var string $docs_title Heading for the documentation group.
 * @var array  $doc_links  Documentation links: each with `label` and `url`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<?php foreach ( $groups as $group ) : ?>
	<div class="wpforms-dashboard-spam-security-group">
		<h3 class="wpforms-dashboard-spam-security-group-title"><?php echo esc_html( $group['title'] ); ?></h3>
		<ul class="wpforms-dashboard-spam-security-rows">
			<?php
			foreach ( $group['rows'] as $row ) :
				$row_classes = [ 'wpforms-dashboard-spam-security-row' ];

				if ( $row['is_met'] ) {
					$row_classes[] = 'wpforms-dashboard-spam-security-row-met';
				}

				if ( $row['is_superseded'] ) {
					$row_classes[] = 'wpforms-dashboard-spam-security-row-superseded';
				}

				$icon_class = $row['is_met'] ? 'fa-regular fa-circle-check' : 'fa-regular fa-circle';

				$name_class = 'wpforms-dashboard-spam-security-row-name';

				if ( $row['link'] === '' ) {
					$name_html = sprintf(
						'<span class="%1$s">%2$s</span>',
						esc_attr( $name_class ),
						esc_html( $row['name'] )
					);
				} else {
					$name_html = sprintf(
						'<a href="%1$s" %2$s>%3$s</a>',
						esc_url( $row['link'] ),
						wpforms_html_attributes( '', array_filter( [ $name_class, 'wpforms-dashboard-spam-security-row-link', $row['link_class'] ] ), [], (array) $row['link_attrs'] ),
						esc_html( $row['name'] )
					);
				}

				$or_html = $row['or_text'] !== ''
					? '<i class="wpforms-dashboard-spam-security-row-or">' . esc_html( $row['or_text'] ) . '</i>'
					: '';

				// The format string is escaped; the two arguments are pre-escaped HTML.
				$label_html = sprintf( esc_html( $row['label_format'] ), $name_html, $or_html );

				if ( $row['is_superseded'] ) {
					$label_html = '<s>' . $label_html . '</s>';
				}
				?>
				<li class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
					<i class="wpforms-dashboard-spam-security-row-icon <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></i>
					<span class="wpforms-dashboard-spam-security-row-label">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Composed above from an escaped format string and pre-escaped arguments.
						echo $label_html;
						?>
						<?php if ( $row['is_superseded'] ) : ?>
							<span class="screen-reader-text">
								<?php
								printf(
									/* translators: %s - the alternative that already satisfies this group, e.g. ActiveLayer. */
									esc_html__( 'Not needed — %s covers this.', 'wpforms-lite' ),
									esc_html( $row['sibling_name'] )
								);
								?>
							</span>
						<?php elseif ( $row['is_met'] ) : ?>
							<span class="screen-reader-text"><?php esc_html_e( 'Done.', 'wpforms-lite' ); ?></span>
						<?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endforeach; ?>
<div class="wpforms-dashboard-spam-security-group">
	<h3 class="wpforms-dashboard-spam-security-group-title"><?php echo esc_html( $docs_title ); ?></h3>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
	echo wpforms_render( 'admin/dashboard/doc-list', [ 'links' => $doc_links ], true );
	?>
</div>
