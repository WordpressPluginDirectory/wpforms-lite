<?php
/**
 * Form Embed Wizard.
 * Embed popup HTML template.
 *
 * @since 1.6.2
 * @since 1.10.1 Redesigned to a six-card grid that combines existing embed
 *                  actions with product education items for Pro features.
 *
 * @var array $args Template arguments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_can_edit_pages = ! empty( $args['user_can_edit_pages'] );
$education_items     = ! empty( $args['education_items'] ) ? (array) $args['education_items'] : [];
$is_lite             = ! empty( $args['is_lite'] );

// Index education items by slug for easy access when interleaving with action cards.
$education_by_slug = [];

foreach ( $education_items as $item ) {
	$education_by_slug[ $item['slug'] ] = $item;
}

/**
 * Render an action card partial.
 *
 * @since 1.10.1
 *
 * @param array $card_args Partial arguments.
 */
$render_action_card = static function ( array $card_args ): void {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo wpforms_render( 'admin/form-embed-wizard/partials/action-card', $card_args );
};

/**
 * Render an education card partial.
 *
 * @since 1.10.1
 *
 * @param array $item    Education item data.
 * @param bool  $is_lite Whether the current install is Lite.
 */
$render_education_card = static function ( array $item, bool $is_lite ): void {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo wpforms_render(
		'admin/form-embed-wizard/partials/education-card',
		[
			'item'    => $item,
			'is_lite' => $is_lite,
		]
	);
};
?>

<div id="wpforms-admin-form-embed-wizard-container" class="wpforms-admin-popup-container">
	<div id="wpforms-admin-form-embed-wizard" class="wpforms-admin-popup wpforms-admin-popup-wide">
		<div class="wpforms-admin-popup-content">
			<div id="wpforms-admin-form-embed-wizard-content-initial">
				<h3><?php esc_html_e( 'How Would You Like to Publish Your Form?', 'wpforms-lite' ); ?></h3>

				<div class="wpforms-admin-form-embed-wizard-grid">
					<?php // Row 1: Existing Page + Conversational Form. ?>
					<?php if ( $user_can_edit_pages ) : ?>
						<?php
						$render_action_card(
							[
								'action'      => 'select-page',
								'icon'        => 'fa-file-code-o',
								'title'       => __( 'Existing Page', 'wpforms-lite' ),
								'description' => __( 'Add to a page you\'ve already created.', 'wpforms-lite' ),
							]
						);
						?>
					<?php endif; ?>

					<?php
					if ( isset( $education_by_slug['conversational-forms'] ) ) {
						$render_education_card( $education_by_slug['conversational-forms'], $is_lite );
					}
					?>

					<?php // Row 2: New Page + Form Landing Page. ?>
					<?php if ( $user_can_edit_pages ) : ?>
						<?php
						$render_action_card(
							[
								'action'      => 'create-page',
								'icon'        => 'fa-plus-square-o',
								'title'       => __( 'New Page', 'wpforms-lite' ),
								'description' => __( 'Create a new page for your form.', 'wpforms-lite' ),
							]
						);
						?>
					<?php endif; ?>

					<?php
					if ( isset( $education_by_slug['form-pages'] ) ) {
						$render_education_card( $education_by_slug['form-pages'], $is_lite );
					}
					?>

					<?php // Row 3: Shortcode + Lead Form. ?>
					<?php
					$render_action_card(
						[
							'action'      => 'shortcode',
							'icon'        => 'fa-files-o',
							'title'       => __( 'Shortcode', 'wpforms-lite' ),
							'description' => __( 'Copy a shortcode to use anywhere.', 'wpforms-lite' ),
						]
					);
					?>

					<?php
					if ( isset( $education_by_slug['lead-forms'] ) ) {
						$render_education_card( $education_by_slug['lead-forms'], $is_lite );
					}
					?>
				</div>
			</div>

			<?php if ( $user_can_edit_pages ) : ?>
				<div id="wpforms-admin-form-embed-wizard-content-select-page" style="display: none;">
					<h3><?php esc_html_e( 'Embed in an Existing Page', 'wpforms-lite' ); ?></h3>
					<p><?php esc_html_e( 'Select the page you would like to embed your form in.', 'wpforms-lite' ); ?></p>
				</div>
				<div id="wpforms-admin-form-embed-wizard-content-create-page" style="display: none;">
					<h3><?php esc_html_e( 'Add to a New Page', 'wpforms-lite' ); ?></h3>
					<p><?php esc_html_e( 'What would you like to call the new page?', 'wpforms-lite' ); ?></p>
				</div>
				<div id="wpforms-admin-form-embed-wizard-section-go" class="wpforms-admin-popup-bottom wpforms-admin-popup-flex" style="display: none;">
					<?php echo $args['dropdown_pages']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="text" id="wpforms-admin-form-embed-wizard-new-page-title" value="" placeholder="<?php esc_attr_e( 'Name Your Page', 'wpforms-lite' ); ?>">
					<button type="button" data-action="go" class="wpforms-admin-popup-btn"><?php esc_html_e( 'Add to Page', 'wpforms-lite' ); ?></button>
				</div>
			<?php endif; ?>

			<div id="wpforms-admin-form-embed-wizard-content-shortcode" style="display: none;">
				<h3><?php esc_html_e( 'Copy the Form\'s Shortcode', 'wpforms-lite' ); ?></h3>
				<p><?php esc_html_e( 'Copy the shortcode and use it in any page builder or template.', 'wpforms-lite' ); ?></p>
				<div id="wpforms-admin-form-embed-wizard-shortcode-wrap">
					<input type="text" id="wpforms-admin-form-embed-wizard-shortcode" class="wpforms-admin-popup-shortcode" disabled />
					<button type="button" id="wpforms-admin-form-embed-wizard-shortcode-copy" class="wpforms-admin-popup-btn">
						<i class="fa fa-files-o" aria-hidden="true"></i>
					</button>
					<div id="wpforms-admin-form-embed-wizard-shortcode-tooltip">
						<div class="wpforms-shortcode-tooltip-content">
							<span class="wpforms-shortcode-tooltip-default"><?php esc_html_e( 'Copy shortcode to clipboard', 'wpforms-lite' ); ?></span>
							<span class="wpforms-shortcode-tooltip-copied"><?php esc_html_e( 'Shortcode copied to clipboard', 'wpforms-lite' ); ?></span>
						</div>
						<div class="wpforms-shortcode-tooltip-arrow"></div>
					</div>
				</div>
			</div>

			<div id="wpforms-admin-form-embed-wizard-section-toggles" class="wpforms-admin-popup-bottom">
				<p class="secondary">
					<?php
					$allowed_tags = [
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
						],
					];

					printf(
						wp_kses( /* translators: %s - link to the manual embed documentation. */
							__( 'You can also <a href="%s" target="_blank" rel="noopener noreferrer">embed your form manually</a> in any page or post.', 'wpforms-lite' ),
							$allowed_tags
						),
						esc_url( wpforms_utm_link( 'https://wpforms.com/docs/displaying-forms-on-your-site/', 'Builder Embed Modal', 'Embed Manually Documentation' ) )
					);
					?>
				</p>
			</div>

			<div id="wpforms-admin-form-embed-wizard-section-goback" class="wpforms-admin-popup-bottom" style="display: none;">
				<p class="secondary">
					<a href="#" class="wpforms-admin-popup-toggle initialstate-toggle">« <?php esc_html_e( 'Go back', 'wpforms-lite' ); ?></a>
				</p>
			</div>
		</div>
		<i class="fa fa-arrow-left wpforms-admin-popup-back initialstate-toggle" style="display: none;" aria-hidden="true"></i>
		<i class="fa fa-times wpforms-admin-popup-close"></i>
	</div>
</div>
