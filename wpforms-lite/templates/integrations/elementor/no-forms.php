<?php
/**
 * No forms template.
 *
 * @since 1.6.2
 *
 * @var bool $can_view_forms   Whether the current user is entitled to view forms.
 * @var bool $can_create_forms Whether the current user is entitled to create forms.
 */

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

// Both flags default to true to keep a render without arguments working as before.
$can_view_forms   = ! isset( $can_view_forms ) || $can_view_forms;
$can_create_forms = ! isset( $can_create_forms ) || $can_create_forms;

?>
<div class="wpforms-admin-empty-state-container wpforms-elementor-no-forms">

	<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/empty-states/no-form-elementor.svg' ); ?>" alt=""/>

	<?php if ( ! $can_view_forms ) : ?>

		<p>
			<b><?php esc_html_e( 'You don’t have permission to use WPForms.', 'wpforms-lite' ); ?></b>
		</p>

		<p><?php esc_html_e( 'Contact your site administrator for access.', 'wpforms-lite' ); ?></p>

	<?php else : ?>

		<p>
			<?php
			echo wp_kses(
				__( 'You can use <b>WPForms</b> to build contact forms, surveys, payment forms, and more with just a few clicks.', 'wpforms-lite' ),
				[ 'b' => [] ]
			);
			?>
		</p>

		<?php if ( $can_create_forms ) : ?>
			<button type="button" class="wpforms-btn"><?php esc_html_e( 'Get Started', 'wpforms-lite' ); ?></button>
		<?php endif; ?>

		<p class="wpforms-admin-no-forms-footer">
			<?php
			printf(
				wp_kses( /* translators: %s - URL to the documentation article. */
					__( 'Need some help? Check out our <a href="%s" class="wpforms-comprehensive-link" target="_blank" rel="noopener noreferrer">comprehensive guide</a>.', 'wpforms-lite' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
							'class'  => [],
						],
					]
				),
				'https://wpforms.com/docs/creating-first-form/'
			);
			?>
		</p>

	<?php endif; ?>

</div>
