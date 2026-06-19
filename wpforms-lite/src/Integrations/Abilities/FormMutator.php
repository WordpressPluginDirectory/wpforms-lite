<?php

namespace WPForms\Integrations\Abilities;

use WP_Error;

/**
 * Performs the read -> merge -> update() write operations for the Abilities API.
 *
 * @since 1.10.2
 */
class FormMutator {

	/**
	 * Field registry.
	 *
	 * @since 1.10.2
	 *
	 * @var FieldRegistry
	 */
	private $fields;

	/**
	 * Settings registry.
	 *
	 * @since 1.10.2
	 *
	 * @var SettingsRegistry
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.10.2
	 *
	 * @param FieldRegistry    $fields   Field registry.
	 * @param SettingsRegistry $settings Settings registry.
	 */
	public function __construct( FieldRegistry $fields, SettingsRegistry $settings ) {

		$this->fields   = $fields;
		$this->settings = $settings;
	}

	/**
	 * Create a new form with an optional initial set of fields and settings.
	 *
	 * Two-layer orphan guard: a preflight check rejects bad input before add(),
	 * and a persist-failure rollback deletes the stub form so a downstream write
	 * error cannot leave one behind either.
	 *
	 * @since 1.10.2
	 *
	 * @param array $args Expects: title (string), fields (array, optional), settings (array, optional).
	 *
	 * @return array|WP_Error Associative array with form_id and fields on success, or WP_Error.
	 */
	public function create_form( array $args ) {

		$title = sanitize_text_field( $args['title'] ?? '' );

		if ( $title === '' ) {
			return new WP_Error(
				'wpforms_invalid_title',
				__( 'A form title is required.', 'wpforms-lite' ),
				[ 'status' => 400 ]
			);
		}

		$handler = wpforms()->obj( 'form' );

		if ( ! $handler ) {
			return new WP_Error(
				'wpforms_form_handler_error',
				__( 'Form handler not available.', 'wpforms-lite' ),
				[ 'status' => 500 ]
			);
		}

		$incoming     = $args['fields'] ?? [];
		$incoming_set = ! empty( $args['settings'] );

		// Preflight: validate every field type BEFORE creating the form so that a bad field
		// in the batch is rejected without the stub-form write. The downstream persist-failure
		// rollback below covers the remaining (rare) DB/filter veto failure window.
		$preflight = $this->preflight_field_types( $incoming );

		if ( is_wp_error( $preflight ) ) {
			return $preflight;
		}

		// `builder => false` makes add() populate sensible defaults (submit, notification, confirmation).
		$form_id = $handler->add( $title, [], [ 'builder' => false ] );

		if ( empty( $form_id ) ) {
			return new WP_Error(
				'wpforms_create_failed',
				__( 'Could not create the form.', 'wpforms-lite' ),
				[ 'status' => 500 ]
			);
		}

		if ( empty( $incoming ) && ! $incoming_set ) {
			return [
				'form_id' => (int) $form_id,
				'fields'  => [],
			];
		}

		$result = $this->populate_and_persist( $handler, (int) $form_id, $incoming, $incoming_set ? (array) $args['settings'] : null );

		// Roll back the stub form on any downstream failure so a persist error cannot orphan it.
		if ( is_wp_error( $result ) ) {
			wp_delete_post( (int) $form_id, true );
		}

		return $result;
	}

	/**
	 * Load form data, apply initial fields and settings, then persist the form.
	 *
	 * Called only when there is at least one field or one setting to apply so
	 * that create_form() stays focused on validation and orchestration.
	 *
	 * @since 1.10.2
	 *
	 * @param object     $handler  Form handler object.
	 * @param int        $form_id  ID of the newly created form.
	 * @param array      $fields   List of raw field input arrays to build.
	 * @param array|null $settings Raw settings input, or null when no settings were supplied.
	 *
	 * @return array|WP_Error Associative array with form_id and fields on success, or WP_Error.
	 */
	private function populate_and_persist( $handler, int $form_id, array $fields, $settings ) {

		$form_data = $handler->get( $form_id, [ 'content_only' => true ] );
		$form_data = is_array( $form_data ) ? $form_data : [];

		$created_fields = $this->apply_initial_fields( $form_data, $fields );

		if ( is_wp_error( $created_fields ) ) {
			return $created_fields;
		}

		if ( $settings !== null ) {
			$this->apply_initial_settings( $form_data, $settings );
		}

		$saved = $this->persist( $form_id, $form_data );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return [
			'form_id' => $form_id,
			'fields'  => $created_fields,
		];
	}

	/**
	 * Validate every incoming field type before the form is created.
	 *
	 * Runs a preflight check so that a bad field type or a missing required prop
	 * in the batch cannot leave an orphaned form behind. v1 curated types declare
	 * no required props, but custom types registered through the
	 * `wpforms_integrations_abilities_field_registry_types` filter may, and this
	 * path makes that promise hold for them too.
	 *
	 * @since 1.10.2
	 *
	 * @param array $fields List of raw field input arrays, each containing a 'type' key.
	 *
	 * @return true|WP_Error True when all types are available, WP_Error on the first failure.
	 */
	private function preflight_field_types( array $fields ) {

		foreach ( $fields as $field_input ) {
			$type  = (string) ( $field_input['type'] ?? '' );
			$props = $field_input;

			unset( $props['type'] );

			$result = $this->validate_field_input( $type, $props );

			if ( $result['error'] !== null ) {
				return $result['error'];
			}
		}

		return true;
	}

	/**
	 * Validate a field type and its props against the registry.
	 *
	 * Single source of truth for the two checks both the batch preflight and the
	 * single-field builder need: type availability, then a missing-required-prop
	 * diff against the sanitized input. Returning the sanitized values lets the
	 * builder consume them without re-sanitizing inside its own scope.
	 *
	 * @since 1.10.2
	 *
	 * @param string $type  Field type.
	 * @param array  $props Raw property input (already stripped of the type key).
	 *
	 * @return array {
	 *     @type array         $values Sanitized property values (empty on type-availability failure).
	 *     @type WP_Error|null $error  Validation error or null on success.
	 * }
	 */
	private function validate_field_input( string $type, array $props ): array {

		if ( ! $this->fields->is_field_type_available( $type ) ) {
			return [
				'values' => [],
				'error'  => new WP_Error(
					'wpforms_field_type_unavailable',
					/* translators: %s - field type. */
					sprintf( __( 'Field type "%s" is not available on this site.', 'wpforms-lite' ), $type ),
					[ 'status' => 422 ]
				),
			];
		}

		$sanitized = $this->fields->sanitize_properties( $type, $props );
		$missing   = array_values(
			array_diff(
				$this->fields->required_properties( $type ),
				array_keys( $sanitized['values'] )
			)
		);

		if ( ! empty( $missing ) ) {
			return [
				'values' => $sanitized['values'],
				'error'  => new WP_Error(
					'wpforms_field_props_missing',
					sprintf(
						/* translators: %1$s field type, %2$s comma-separated missing prop keys. */
						__( 'Required properties for field type "%1$s" are missing: %2$s.', 'wpforms-lite' ),
						$type,
						implode( ', ', $missing )
					),
					[
						'status'  => 400,
						'missing' => $missing,
					]
				),
			];
		}

		return [
			'values' => $sanitized['values'],
			'error'  => null,
		];
	}

	/**
	 * Build each incoming field and append it to form data.
	 *
	 * Iterates over the supplied field inputs, builds each one via build_new_field(),
	 * appends the result to $form_data['fields'], and returns the created-field summary list.
	 *
	 * @since 1.10.2
	 *
	 * @param array $form_data Form data passed by reference; fields are appended in place.
	 * @param array $fields    List of raw field input arrays to build.
	 *
	 * @return array|WP_Error Ordered list of arrays with 'id' and 'type' keys on success, or WP_Error.
	 */
	private function apply_initial_fields( array &$form_data, array $fields ) {

		$created_fields = [];

		foreach ( $fields as $field_input ) {
			$built = $this->build_new_field( $form_data, (string) ( $field_input['type'] ?? '' ), $field_input );

			if ( is_wp_error( $built ) ) {
				return $built;
			}

			$form_data['fields'][ $built['id'] ] = $built;
			$created_fields[]                    = [
				'id'   => $built['id'],
				'type' => $built['type'],
			];
		}

		return $created_fields;
	}

	/**
	 * Merge whitelisted settings into form data.
	 *
	 * Sanitizes the supplied settings via the registry and merges the accepted
	 * values into $form_data['settings'], preserving any pre-existing keys.
	 *
	 * @since 1.10.2
	 *
	 * @param array $form_data Form data passed by reference; settings are merged in place.
	 * @param array $settings  Raw settings input keyed by setting name.
	 */
	private function apply_initial_settings( array &$form_data, array $settings ): void {

		$sanitized             = $this->settings->sanitize( $settings );
		$existing              = isset( $form_data['settings'] ) && is_array( $form_data['settings'] ) ? $form_data['settings'] : [];
		$form_data['settings'] = array_merge( $existing, $sanitized['sanitized'] );
	}

	/**
	 * Add a single field to an existing form.
	 *
	 * @since 1.10.2
	 *
	 * @param int    $form_id Form ID.
	 * @param string $type    Field type.
	 * @param array  $props   Raw property input.
	 *
	 * @return array|WP_Error Associative array with form_id, field_id, and type on success, or WP_Error.
	 */
	public function add_field( int $form_id, string $type, array $props ) {

		$form_data = $this->load_form_data( $form_id );

		if ( is_wp_error( $form_data ) ) {
			return $form_data;
		}

		$field = $this->build_new_field( $form_data, $type, array_merge( $props, [ 'type' => $type ] ) );

		if ( is_wp_error( $field ) ) {
			return $field;
		}

		$form_data['fields'][ $field['id'] ] = $field;

		$saved = $this->persist( $form_id, $form_data );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return [
			'form_id'  => $form_id,
			'field_id' => $field['id'],
			'type'     => $type,
		];
	}

	/**
	 * Update properties of an existing field.
	 *
	 * @since 1.10.2
	 *
	 * @param int   $form_id  Form ID.
	 * @param int   $field_id Field ID (1-based key used by WPForms).
	 * @param array $props    Raw property input to apply.
	 *
	 * @return array|WP_Error Associative array with form_id, field_id, updated, and ignored on success, or WP_Error.
	 */
	public function update_field( int $form_id, int $field_id, array $props ) {

		$form_data = $this->load_form_data( $form_id );

		if ( is_wp_error( $form_data ) ) {
			return $form_data;
		}

		if ( empty( $form_data['fields'][ $field_id ] ) ) {
			return new WP_Error(
				'wpforms_field_not_found',
				__( 'Field not found.', 'wpforms-lite' ),
				[ 'status' => 404 ]
			);
		}

		$field = $form_data['fields'][ $field_id ];
		$type  = (string) ( $field['type'] ?? '' );

		// Reject edits to a field whose type is not available on this install (e.g. a phone field
		// left behind after a downgrade or import). Mirrors the add_field() availability guard.
		if ( ! $this->fields->is_field_type_available( $type ) ) {
			return new WP_Error(
				'wpforms_field_type_unavailable',
				/* translators: %s - field type. */
				sprintf( __( 'Field type "%s" is not available on this site.', 'wpforms-lite' ), $type ),
				[ 'status' => 422 ]
			);
		}

		$sanitized = $this->fields->sanitize_properties( $type, $props );

		$this->overlay_properties( $field, $sanitized['values'] );

		$form_data['fields'][ $field_id ] = $field;

		$saved = $this->persist( $form_id, $form_data );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return [
			'form_id'  => $form_id,
			'field_id' => $field_id,
			'updated'  => array_keys( $sanitized['values'] ),
			'ignored'  => $sanitized['ignored'],
		];
	}

	/**
	 * Update whitelisted form settings, merging with any pre-existing settings.
	 *
	 * @since 1.10.2
	 *
	 * @param int   $form_id  Form ID.
	 * @param array $settings Raw settings input keyed by setting name.
	 *
	 * @return array|WP_Error Associative array with form_id, updated, and ignored on success, or WP_Error.
	 */
	public function update_settings( int $form_id, array $settings ) {

		$form_data = $this->load_form_data( $form_id );

		if ( is_wp_error( $form_data ) ) {
			return $form_data;
		}

		$result = $this->settings->sanitize( $settings );

		$existing              = isset( $form_data['settings'] ) && is_array( $form_data['settings'] ) ? $form_data['settings'] : [];
		$form_data['settings'] = array_merge( $existing, $result['sanitized'] );

		$saved = $this->persist( $form_id, $form_data );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return [
			'form_id' => $form_id,
			'updated' => array_keys( $result['sanitized'] ),
			'ignored' => $result['ignored'],
		];
	}

	/**
	 * Load form content from the form handler.
	 *
	 * @since 1.10.2
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array|WP_Error Form data array on success, or WP_Error on failure.
	 */
	private function load_form_data( int $form_id ) {

		$handler = wpforms()->obj( 'form' );

		if ( ! $handler ) {
			return new WP_Error(
				'wpforms_form_handler_error',
				__( 'Form handler not available.', 'wpforms-lite' ),
				[ 'status' => 500 ]
			);
		}

		$form_data = $handler->get( $form_id, [ 'content_only' => true ] );

		if ( empty( $form_data ) || ! is_array( $form_data ) ) {
			return new WP_Error(
				'wpforms_form_not_found',
				__( 'Form not found.', 'wpforms-lite' ),
				[ 'status' => 404 ]
			);
		}

		return $form_data;
	}

	/**
	 * Allocate the next field ID in memory and advance the form counter.
	 *
	 * @since 1.10.2
	 *
	 * @param array $form_data Form data passed by reference; field_id counter is advanced.
	 *
	 * @return int Allocated field ID.
	 */
	private function allocate_field_id( array &$form_data ): int {

		$counter = absint( $form_data['field_id'] ?? 0 );
		$max     = ! empty( $form_data['fields'] ) && is_array( $form_data['fields'] )
			? max( array_map( 'absint', array_keys( $form_data['fields'] ) ) )
			: 0;

		$id = max( $counter, $max + 1 );

		$form_data['field_id'] = $id + 1;

		return $id;
	}

	/**
	 * Build a structurally valid new field via the canonical default path.
	 *
	 * @since 1.10.2
	 *
	 * @param array  $form_data Form data passed by reference, for ID allocation.
	 * @param string $type      Field type.
	 * @param array  $input     Raw property input including the type key.
	 *
	 * @return array|WP_Error Built field array or WP_Error on failure.
	 */
	private function build_new_field( array &$form_data, string $type, array $input ) {

		$props = $input;

		unset( $props['type'] );

		// Validate before allocating an ID so a rejected field does not advance
		// the in-memory field_id counter.
		$result = $this->validate_field_input( $type, $props );

		if ( $result['error'] !== null ) {
			return $result['error'];
		}

		$id = $this->allocate_field_id( $form_data );

		$field = [
			'id'          => $id,
			'type'        => $type,
			'label'       => $this->fields->get_type_label( $type ),
			'description' => '',
		];

		/** This filter is documented in wpforms/includes/fields/class-base.php. */
		$field = (array) apply_filters( 'wpforms_field_new_default', $field ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$this->overlay_properties( $field, $result['values'] );

		return $field;
	}

	/**
	 * Overlay sanitized properties onto a field array.
	 *
	 * @since 1.10.2
	 *
	 * @param array $field  Field array passed by reference.
	 * @param array $values Sanitized property values keyed by property name.
	 */
	private function overlay_properties( array &$field, array $values ): void {

		foreach ( $values as $key => $value ) {
			if ( $key === 'choices' ) {
				$this->merge_choices( $field, $value );

				continue;
			}

			$field[ $key ] = $value;
		}
	}

	/**
	 * Merge an ordered choices list by index, preserving existing per-choice keys.
	 *
	 * @since 1.10.2
	 *
	 * @param array $field   Field array passed by reference.
	 * @param array $choices Ordered list of choice arrays with at least a label key.
	 */
	private function merge_choices( array &$field, array $choices ): void {

		// Defensive: a stored field may carry a non-array `choices` value (corrupt data,
		// import, legacy format). Fall back to an empty list to avoid string-offset access.
		$existing = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : [];
		$result   = [];
		$index    = 1;

		foreach ( $choices as $choice ) {
			$base             = $existing[ $index ] ?? [];
			$base['label']    = $choice['label'];
			$base['value']    = $choice['value'];
			$result[ $index ] = $base;

			++$index;
		}

		$field['choices'] = $result;
	}

	/**
	 * Persist form data through the form handler.
	 *
	 * @since 1.10.2
	 *
	 * @param int   $form_id   Form ID.
	 * @param array $form_data Full form data to save.
	 *
	 * @return int|WP_Error Saved form ID on success, WP_Error on failure.
	 */
	private function persist( int $form_id, array $form_data ) {

		$handler = wpforms()->obj( 'form' );

		if ( ! $handler ) {
			return new WP_Error(
				'wpforms_form_handler_error',
				__( 'Form handler not available.', 'wpforms-lite' ),
				[ 'status' => 500 ]
			);
		}

		$form_data['id'] = $form_id;

		$result = $handler->update( $form_id, $form_data );

		if ( empty( $result ) ) {
			return new WP_Error(
				'wpforms_update_failed',
				__( 'Could not save the form.', 'wpforms-lite' ),
				[ 'status' => 500 ]
			);
		}

		return (int) $result;
	}
}
