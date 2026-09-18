<?php
/**
 * Dashboard widget gear-menu popover (framework).
 *
 * Renders a widget's settings controls from a declarative field schema, plus the
 * shared Save Changes button. Reused by every gear widget; a widget only declares
 * its fields via `AbstractWidget::get_settings_schema()`.
 *
 * Field types:
 * - `select`    — { name, label, options: [ value => label ], value, locked_by? } (`locked_by` names
 *   a checklist field that disables this one while it has any option checked).
 * - `checklist` — { name, label, panel?, options: [ value => label ], value: [ checked ],
 *   min_unchecked?, max_checked? } (one array setting; both caps disable the unchecked options
 *   once they are reached).
 * - `checkboxes`— { label?, panel?, options: [ key => label ], value: [ key => bool ] } (independent booleans).
 *
 * Every field type must submit a value even when empty, which is why the array types carry
 * a hidden input: `Ajax::save_widget_settings()` reads an absent key as "this popover did
 * not render the field" and keeps the stored value, so a type that submits nothing would
 * make clearing it impossible.
 *
 * @since 2.0.2
 *
 * @var array  $fields    Settings field schema.
 * @var string $widget_id Owning widget identifier.
 * @var bool   $can_reset Whether the widget is off its defaults, i.e. there is something to reset.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpforms-dashboard-widget-settings" hidden>
	<?php
	foreach ( $fields as $field ) :
		$field_type = $field['type'] ?? '';
		$field_name = $field['name'] ?? '';
		$field_id   = 'wpforms-dashboard-' . $widget_id . '-' . $field_name;
		$options    = (array) ( $field['options'] ?? [] );

		if ( $field_type === 'select' ) :
			?>
			<p class="wpforms-dashboard-widget-settings-label">
				<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ?? '' ); ?></label>
			</p>
			<select <?php wpforms_html_attributes( $field_id, [ 'wpforms-dashboard-widget-settings-field' ], array_filter( [ 'locked-by' => (string) ( $field['locked_by'] ?? '' ) ] ), [ 'name' => $field_name ], true ); ?>>
				<?php foreach ( $options as $option_value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( (string) ( $field['value'] ?? '' ), (string) $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
		elseif ( $field_type === 'checklist' ) :
			$selected = array_map( 'strval', (array) ( $field['value'] ?? [] ) );
			$panel    = empty( $field['panel'] ) ? '' : 'wpforms-dashboard-widget-settings-panel';
			// A list can require some options to stay unchecked, e.g. so a table keeps a row,
			// and can cap how many may be checked at once.
			$list_data = array_filter(
				[
					'min-unchecked' => absint( $field['min_unchecked'] ?? 0 ),
					'max-checked'   => absint( $field['max_checked'] ?? 0 ),
				]
			);
			?>
			<?php if ( ! empty( $field['label'] ) ) : ?>
				<p class="wpforms-dashboard-widget-settings-label"><?php echo esc_html( $field['label'] ); ?></p>
			<?php endif; ?>
			<?php // Sentinel so a fully-unchecked list still submits an (empty) value; without it the field is omitted and the server restores all options. ?>
			<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[]" value="">
			<ul <?php wpforms_html_attributes( '', [ 'wpforms-dashboard-widget-settings-list', 'wpforms-scrollbar-compact', $panel ], $list_data, [], true ); ?>>
				<?php foreach ( $options as $option_value => $option_label ) : ?>
					<li>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $field_name ); ?>[]" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( in_array( (string) $option_value, $selected, true ) ); ?>>
							<?php echo esc_html( $option_label ); ?>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
		elseif ( $field_type === 'checkboxes' ) :
			$values = (array) ( $field['value'] ?? [] );
			$panel  = empty( $field['panel'] ) ? '' : 'wpforms-dashboard-widget-settings-panel';
			?>
			<?php if ( ! empty( $field['label'] ) ) : ?>
				<p class="wpforms-dashboard-widget-settings-label"><?php echo esc_html( $field['label'] ); ?></p>
			<?php endif; ?>
			<div <?php wpforms_html_attributes( '', [ 'wpforms-dashboard-widget-settings-group','wpforms-scrollbar-compact', $panel ], [], [], true ); ?>>
				<?php foreach ( $options as $option_key => $option_label ) : ?>
					<label class="wpforms-dashboard-widget-settings-checkbox">
						<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>" value="0">
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>" value="1" <?php checked( ! empty( $values[ $option_key ] ) ); ?>>
						<?php echo esc_html( $option_label ); ?>
					</label>
				<?php endforeach; ?>
			</div>
			<?php
		endif;
	endforeach;
	?>

	<div class="wpforms-dashboard-widget-settings-footer">
		<button type="button" class="wpforms-btn wpforms-btn-sm wpforms-btn-blue-outline wpforms-dashboard-widget-settings-save">
			<?php esc_html_e( 'Save Changes', 'wpforms-lite' ); ?>
		</button>

		<?php
		// Always rendered, only hidden: a save can move the widget off its defaults, and the
		// widgets that re-render client-side never rebuild this markup to reveal it.
		$reset_label   = __( 'Reset to Default Settings', 'wpforms-lite' );
		$reset_classes = [ 'wpforms-dashboard-widget-settings-reset', empty( $can_reset ) ? 'wpforms-hidden' : '' ];
		?>
		<button type="button" <?php wpforms_html_attributes( '', $reset_classes, [], [ 'title' => $reset_label ], true ); ?>>
			<i class="fa fa-rotate-left" aria-hidden="true"></i>
			<span class="screen-reader-text"><?php echo esc_html( $reset_label ); ?></span>
		</button>
	</div>
</div>
