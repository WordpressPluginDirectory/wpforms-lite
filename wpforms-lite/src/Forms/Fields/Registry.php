<?php

namespace WPForms\Forms\Fields;

use WPForms_Field;

/**
 * Registry of instantiated field objects, keyed by field type slug.
 *
 * Every field announces itself via the `wpforms_field_registered` action once
 * initialized, giving a single authoritative collection of all available field
 * types (Lite, Pro, and addons) without hardcoded lists or per-type lookups.
 *
 * @since 2.0.0
 */
class Registry {

	/**
	 * Registered field objects, keyed by field type slug.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $fields = [];

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function hooks(): void {

		add_action( 'wpforms_field_registered', [ $this, 'add' ] );
	}

	/**
	 * Register a field object.
	 *
	 * @since 2.0.0
	 *
	 * @param WPForms_Field $field Field object to register.
	 */
	public function add( WPForms_Field $field ): void {

		if ( empty( $field->type ) ) {
			return;
		}

		$this->fields[ $field->type ] = $field;
	}

	/**
	 * Get a map of field type slug to human-readable field name.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_names(): array {

		$names = [];

		foreach ( $this->fields as $type => $field ) {
			$names[ $type ] = (string) $field->name;
		}

		return $names;
	}
}
