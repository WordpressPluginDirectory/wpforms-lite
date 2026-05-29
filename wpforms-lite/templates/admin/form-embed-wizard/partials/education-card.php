<?php
/**
 * Form Embed Wizard.
 * Education card partial template.
 *
 * @since 1.10.1
 *
 * @var array $args {
 *     Template arguments.
 *
 *     @type array $item    Education item data (slug, label, description, icon, button_attrs, upgrade_url, demo_url).
 *     @type bool  $is_lite Whether the current install is Lite.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item    = $args['item'] ?? [];
$is_lite = ! empty( $args['is_lite'] );

if ( empty( $item ) ) {
	return;
}

$button_attrs = $item['button_attrs'] ?? [];
$class        = $button_attrs['class'] ?? '';
$data         = $button_attrs['data'] ?? [];
$icon         = $item['icon'] ?? '';
$label        = $item['label'] ?? '';
$description  = $item['description'] ?? '';
?>
<button
	type="button"
	class="wpforms-admin-form-embed-wizard-card wpforms-admin-form-embed-wizard-card-education <?php echo esc_attr( $class ); ?>"
	<?php foreach ( $data as $attr_name => $attr_value ) : ?>
		data-<?php echo esc_attr( $attr_name ); ?>="<?php echo esc_attr( $attr_value ); ?>"
	<?php endforeach; ?>>
	<span class="wpforms-admin-form-embed-wizard-card-icon">
		<i class="fa <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i>
	</span>
	<span class="wpforms-admin-form-embed-wizard-card-text">
		<span class="wpforms-admin-form-embed-wizard-card-title"><?php echo esc_html( $label ); ?></span>
		<span class="wpforms-admin-form-embed-wizard-card-desc"><?php echo esc_html( $description ); ?></span>
		<?php if ( $is_lite && ! empty( $item['upgrade_url'] ) ) : ?>
			<span class="wpforms-admin-form-embed-wizard-card-upgrade">
				<a href="<?php echo esc_url( $item['upgrade_url'] ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Upgrade to Pro', 'wpforms-lite' ); ?>
				</a>
			</span>
		<?php endif; ?>
	</span>
	<?php if ( $is_lite && ! empty( $item['demo_url'] ) ) : ?>
		<span class="wpforms-admin-form-embed-wizard-card-demo">
			<a href="<?php echo esc_url( $item['demo_url'] ); ?>" target="_blank" rel="noopener noreferrer">
				<span class="wpforms-admin-form-embed-wizard-card-demo-text"><?php esc_html_e( 'Demo', 'wpforms-lite' ); ?></span>
				<i class="fa fa-external-link" aria-hidden="true"></i>
			</a>
		</span>
	<?php else : ?>
		<span class="wpforms-admin-form-embed-wizard-card-chevron" aria-hidden="true">
			<i class="fa fa-chevron-right"></i>
		</span>
	<?php endif; ?>
</button>
