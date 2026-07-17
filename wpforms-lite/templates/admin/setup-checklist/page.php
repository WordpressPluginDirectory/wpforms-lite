<?php
/**
 * Setup Checklist admin page.
 *
 * @since 2.0.0
 *
 * @var array $sections     Sections, each with `id`, `title`, `is_complete`, and `items`
 *                          (item view-models tagged `type` => `item` | `payment`).
 * @var array $features     Feature promo: `tiles` and `footer` (`text`, `cta_text`, `cta_url`).
 * @var array $integrations Integrations promo: `cards` and `footer` (+ `modifier`).
 * @var array $growth_tools Growth-tools promo: `tiles`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="wpforms-setup-checklist" class="wrap wpforms-admin-wrap wpforms-setup-checklist">
	<div class="wpforms-setup-checklist-title-bar">
		<h1 class="wpforms-setup-checklist-title-bar__title">
			<?php esc_html_e( 'Complete WPForms Setup Checklist', 'wpforms-lite' ); ?>
		</h1>
	</div>

	<?php
	foreach ( $sections as $section ) :
		$items_id        = 'wpforms-setup-checklist-section-' . sanitize_html_class( $section['id'] );
		$section_classes = [ 'wpforms-setup-checklist-section' ];

		if ( $section['is_complete'] ) {
			$section_classes[] = 'is-complete';
			$section_classes[] = 'is-collapsed';
		}

		$badge_modifier = $section['is_complete'] ? 'complete' : 'incomplete';
		$badge_label    = $section['is_complete'] ? __( 'Complete', 'wpforms-lite' ) : __( 'Incomplete', 'wpforms-lite' );
		?>
		<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>" data-section="<?php echo esc_attr( $section['id'] ); ?>">
			<div class="wpforms-setup-checklist-section__header">
				<h2 class="wpforms-setup-checklist-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
				<?php
				printf(
					'<span class="wpforms-setup-checklist-section__badge wpforms-setup-checklist-section__badge--%1$s">%2$s</span>',
					esc_attr( $badge_modifier ),
					esc_html( $badge_label )
				);
				?>
				<button type="button" class="wpforms-setup-checklist-section__toggle" aria-expanded="<?php echo $section['is_complete'] ? 'false' : 'true'; ?>" aria-controls="<?php echo esc_attr( $items_id ); ?>">
					<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
					<span class="screen-reader-text"><?php esc_html_e( 'Toggle section', 'wpforms-lite' ); ?></span>
				</button>
			</div>
			<div class="wpforms-setup-checklist-section__items" id="<?php echo esc_attr( $items_id ); ?>">
				<div class="wpforms-setup-checklist-section__items-inner">
					<?php
					foreach ( $section['items'] as $item ) {
						if ( ( $item['type'] ?? 'item' ) === 'payment' ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
							echo wpforms_render( 'admin/setup-checklist/payment', $item, true );

							continue;
						}

						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
						echo wpforms_render( 'admin/setup-checklist/item', [ 'item' => $item ], true );
					}
					?>
				</div>
			</div>
		</section>
	<?php endforeach; ?>

	<section class="wpforms-setup-checklist-promo" data-promo="features">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
		echo wpforms_render(
			'admin/setup-checklist/promo-header',
			[
				'body_id' => 'wpforms-setup-checklist-promo-features',
				'title'   => __( 'Take Your Forms to the Next Level', 'wpforms-lite' ),
			],
			true
		);
		?>
		<div class="wpforms-setup-checklist-promo__body" id="wpforms-setup-checklist-promo-features">
			<div class="wpforms-setup-checklist-promo__body-inner">
				<div class="wpforms-setup-checklist-promo__grid">
					<?php
					foreach ( $features['tiles'] as $tile ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
						echo wpforms_render( 'admin/setup-checklist/promo-tile', [ 'tile' => $tile ], true );
					}
					?>
				</div>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
				echo wpforms_render( 'admin/setup-checklist/promo-footer', $features['footer'], true );
				?>
			</div>
		</div>
	</section>

	<section class="wpforms-setup-checklist-promo" data-promo="integrations">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
		echo wpforms_render(
			'admin/setup-checklist/promo-header',
			[
				'body_id' => 'wpforms-setup-checklist-promo-integrations',
				'title'   => __( 'Streamline Your Workflow with Seamless Integrations', 'wpforms-lite' ),
			],
			true
		);
		?>
		<div class="wpforms-setup-checklist-promo__body" id="wpforms-setup-checklist-promo-integrations">
			<div class="wpforms-setup-checklist-promo__body-inner">
				<div class="wpforms-setup-checklist-promo__integrations">
					<?php
					foreach ( $integrations['cards'] as $card ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
						echo wpforms_render( 'admin/setup-checklist/integration-card', [ 'card' => $card ], true );
					}
					?>
				</div>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
				echo wpforms_render( 'admin/setup-checklist/promo-footer', $integrations['footer'], true );
				?>
			</div>
		</div>
	</section>

	<section class="wpforms-setup-checklist-promo" data-promo="growth-tools">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
		echo wpforms_render(
			'admin/setup-checklist/promo-header',
			[
				'body_id'  => 'wpforms-setup-checklist-promo-growth-tools',
				'title'    => __( 'Set Up Recommended Growth Tools', 'wpforms-lite' ),
				'subtitle' => __( 'Take your website to the next level with our sister plugins.', 'wpforms-lite' ),
			],
			true
		);
		?>
		<div class="wpforms-setup-checklist-promo__body" id="wpforms-setup-checklist-promo-growth-tools">
			<div class="wpforms-setup-checklist-promo__body-inner">
				<div class="wpforms-setup-checklist-promo__grid">
					<?php
					foreach ( $growth_tools['tiles'] as $tile ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
						echo wpforms_render( 'admin/setup-checklist/promo-tile', [ 'tile' => $tile ], true );
					}
					?>
				</div>
			</div>
		</div>
	</section>

	<div class="wpforms-setup-checklist-footer">
		<a href="#" class="wpforms-setup-checklist-dismiss" data-action="dismiss">
			<?php esc_html_e( 'Complete Setup Checklist', 'wpforms-lite' ); ?>
		</a>
	</div>
</div>
