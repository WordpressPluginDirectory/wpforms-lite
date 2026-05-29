<?php
/**
 * Form Embed Wizard.
 * Action card partial template.
 *
 * @since 1.10.1
 *
 * @var array $args {
 *     Template arguments.
 *
 *     @type string $action      Data action attribute value.
 *     @type string $icon        Font Awesome icon class.
 *     @type string $title       Card title.
 *     @type string $description Card description.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$card_action      = $args['action'] ?? '';
$card_icon        = $args['icon'] ?? '';
$card_title       = $args['title'] ?? '';
$card_description = $args['description'] ?? '';
?>
<button
	type="button"
	class="wpforms-admin-form-embed-wizard-card wpforms-admin-form-embed-wizard-card-action"
	data-action="<?php echo esc_attr( $card_action ); ?>">
	<span class="wpforms-admin-form-embed-wizard-card-icon">
		<i class="fa <?php echo esc_attr( $card_icon ); ?>" aria-hidden="true"></i>
	</span>
	<span class="wpforms-admin-form-embed-wizard-card-text">
		<span class="wpforms-admin-form-embed-wizard-card-title"><?php echo esc_html( $card_title ); ?></span>
		<span class="wpforms-admin-form-embed-wizard-card-desc"><?php echo esc_html( $card_description ); ?></span>
	</span>
	<span class="wpforms-admin-form-embed-wizard-card-chevron" aria-hidden="true">
		<i class="fa fa-chevron-right"></i>
	</span>
</button>
