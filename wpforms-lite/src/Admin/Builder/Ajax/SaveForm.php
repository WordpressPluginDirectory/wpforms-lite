<?php

namespace WPForms\Admin\Builder\Ajax;

/**
 * Save the form data.
 *
 * @since 1.9.4
 */
class SaveForm {

	/**
	 * Field types that persist a `choices` array and require empty-choice pruning on save.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private const CHOICE_FIELD_TYPES = [
		'radio',
		'checkbox',
		'select',
		'payment-checkbox',
		'payment-multiple',
		'payment-select',
	];

	/**
	 * The form fields processing while saving the form.
	 *
	 * @since 1.9.4
	 *
	 * @param array $fields    Form fields data.
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	public function process_fields( array $fields, array $form_data ): array {

		$form_obj = wpforms()->obj( 'form' );

		if ( ! $form_obj || empty( $fields ) || empty( $form_data['id'] ) ) {
			return $fields;
		}

		$saved_form_data = $form_obj->get( $form_data['id'], [ 'content_only' => true ] );

		foreach ( $fields as $field_id => $field_data ) {
			if ( empty( $field_data['type'] ) ) {
				continue;
			}

			// Prune fully-empty leftover choices so a deleted choice is removed before persistence.
			$field_data = $this->prune_empty_choices( $field_data );

			/**
			 * Filter field settings before saving the form.
			 *
			 * @since 1.9.4
			 *
			 * @param array $field_data      Field data.
			 * @param array $form_data       Forms data.
			 * @param array $saved_form_data Saved form data.
			 */
			$fields[ $field_id ] = apply_filters( "wpforms_admin_builder_ajax_save_form_field_{$field_data['type']}", $field_data, $form_data, $saved_form_data );
		}

		return $fields;
	}

	/**
	 * Prune fully-empty leftover choices from a choice-type field before it is saved.
	 *
	 * Dynamic-choice fields (post type / taxonomy) are skipped because their
	 * `choices` are not the persisted source of truth.
	 *
	 * @since 2.0.0
	 *
	 * @param array $field_data Field data being saved.
	 *
	 * @return array
	 */
	private function prune_empty_choices( array $field_data ): array {

		// Only choice-type fields persist a `choices` array.
		if ( ! in_array( $field_data['type'], self::CHOICE_FIELD_TYPES, true ) ) {
			return $field_data;
		}

		// Reach the field type object registered in WPForms_Field::common_hooks().
		/** This filter is documented in src/Pro/Forms/Fields/Base/EntriesEdit.php. */
		$field_obj = apply_filters( "wpforms_fields_get_field_object_{$field_data['type']}", null ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		// The field object guards dynamic-choice fields and empty choices arrays internally.
		if ( ! $field_obj instanceof \WPForms_Field ) {
			return $field_data;
		}

		return $field_obj->remove_empty_choices_on_save( $field_data );
	}
}
