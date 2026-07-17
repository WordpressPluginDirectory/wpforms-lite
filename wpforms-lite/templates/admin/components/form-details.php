<?php
/**
 * Reusable form-details title fragment: small caps sub-label + h3 with the
 * current form title and an optional popdown form selector. Caller wraps it
 * in a `.form-details` container and adds its own actions slot.
 *
 * Click-to-toggle behavior is wired globally in assets/js/admin/admin.js
 * against the `.toggle` element inside `.form-selector`.
 *
 * @since 2.0.0
 *
 * @var string $sub_label            Small caps label above the title (e.g. "Select Form").
 * @var int    $current_form_id      The currently-selected form ID.
 * @var string $current_form_title   The currently-selected form title (pre-resolved by the caller).
 * @var string $title_suffix         Optional HTML appended after the form title.
 * @var string $form_list_items_html Pre-rendered <li> items for the popdown (empty disables it).
 */

defined( 'ABSPATH' ) || exit;

$sub_label            = $sub_label ?? '';
$current_form_id      = isset( $current_form_id ) ? (int) $current_form_id : 0;
$current_form_title   = $current_form_title ?? '';
$title_suffix         = $title_suffix ?? '';
$form_list_items_html = $form_list_items_html ?? '';

if ( $current_form_id === 0 ) {
	return;
}

$title_suffix_allowed = [
	'span' => [
		'class' => [],
	],
];

?>
<?php if ( $sub_label !== '' ) : ?>
	<span class="form-details-sub"><?php echo esc_html( $sub_label ); ?></span>
<?php endif; ?>

<h3 class="form-details-title">
	<?php
	echo esc_html( wp_strip_all_tags( $current_form_title ) );
	echo wp_kses( $title_suffix, $title_suffix_allowed );
	?>

	<?php if ( $form_list_items_html !== '' ) : ?>
		<div class="form-selector">
			<a href="#" title="<?php esc_attr_e( 'Open form selector', 'wpforms-lite' ); ?>" class="toggle dashicons dashicons-arrow-down-alt2"></a>
			<div class="form-list">
				<ul>
					<?php
					echo $form_list_items_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</ul>
			</div>
		</div>
	<?php endif; ?>
</h3>
