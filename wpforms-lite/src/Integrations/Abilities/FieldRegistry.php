<?php

namespace WPForms\Integrations\Abilities;

use Closure;

/**
 * Curated field-type and property registry for the Abilities API writers.
 *
 * Two levels: a property catalog (defined once) and a type -> property-keys map.
 * Both are filterable so addons can extend without core edits.
 *
 * @since 1.10.2
 */
class FieldRegistry {

	/**
	 * Availability requirement: always available.
	 *
	 * @since 1.10.2
	 */
	private const ALWAYS = 'always';

	/**
	 * Availability requirement: requires Pro license.
	 *
	 * @since 1.10.2
	 */
	private const PRO = 'pro';

	/**
	 * Property catalog: key => [ schema fragment, sanitizer callable ].
	 *
	 * @since 1.10.2
	 *
	 * @return array
	 */
	private function properties(): array {

		$properties = [
			'label'           => [
				'schema'   => [ 'type' => 'string' ],
				'sanitize' => 'sanitize_text_field',
			],
			'description'     => [
				'schema'   => [ 'type' => 'string' ],
				'sanitize' => 'sanitize_text_field',
			],
			'required'        => [
				'schema'   => [ 'type' => 'boolean' ],
				'sanitize' => [ $this, 'sanitize_bool' ],
			],
			'size'            => [
				'schema'   => [
					'type' => 'string',
					'enum' => [ 'small', 'medium', 'large' ],
				],
				'sanitize' => 'sanitize_text_field',
			],
			'label_hide'      => [
				'schema'   => [ 'type' => 'boolean' ],
				'sanitize' => [ $this, 'sanitize_bool' ],
			],
			'placeholder'     => [
				'schema'   => [ 'type' => 'string' ],
				'sanitize' => 'sanitize_text_field',
			],
			'default_value'   => [
				'schema'   => [ 'type' => 'string' ],
				'sanitize' => 'sanitize_text_field',
			],
			'input_columns'   => [
				'schema'   => [
					'type' => 'string',
					'enum' => [ '', 'inline', '2', '3' ],
				],
				'sanitize' => 'sanitize_text_field',
			],
			'style'           => [
				// Shared `$field['style']` storage key. For Dropdown (select): `classic` is
				// the plain browser-native `<select>`, `modern` swaps in Choices.js. For
				// File Upload: `classic` is a single-file native input, `modern` is the
				// drag-and-drop multi-file uploader. Constants live on
				// WPForms_Field_Select::STYLE_{CLASSIC|MODERN} and Forms\Fields\FileUpload\Field::STYLE_{CLASSIC|MODERN}.
				'schema'   => [
					'type' => 'string',
					'enum' => [ 'classic', 'modern' ],
				],
				'sanitize' => 'sanitize_text_field',
			],
			'extensions'      => [
				// Comma-separated list of allowed file extensions, e.g. `"pdf,doc,docx,png,jpg"`.
				// WPForms itself enforces this at submit time; we just store it.
				'schema'   => [ 'type' => 'string' ],
				'sanitize' => 'sanitize_text_field',
			],
			'max_size'        => [
				// Per-file maximum size in megabytes. WPForms also caps it at `wp_max_upload_size()`
				// at submit time, so values above the server limit silently fall back to the cap.
				'schema'   => [
					'type'    => 'integer',
					'minimum' => 1,
				],
				'sanitize' => 'absint',
			],
			'max_file_number' => [
				// Maximum number of files a user can upload through the field. Applies only to
				// the modern (multi-file) uploader; the classic style is single-file.
				'schema'   => [
					'type'    => 'integer',
					'minimum' => 1,
				],
				'sanitize' => 'absint',
			],
			'media_library'   => [
				// Store uploads in the WordPress Media Library in addition to the form's
				// uploads directory. Maps to `$field['media_library']` ("1" / "").
				'schema'   => [ 'type' => 'boolean' ],
				'sanitize' => [ $this, 'sanitize_bool' ],
			],
			'choices'         => [
				'schema'   => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'properties'           => [
							'label' => [ 'type' => 'string' ],
							'value' => [ 'type' => 'string' ],
						],
						'required'             => [ 'label' ],
						'additionalProperties' => false,
					],
				],
				'sanitize' => [ $this, 'sanitize_choices' ],
			],
		];

		/**
		 * Filters the Abilities API field-property catalog.
		 *
		 * @since 1.10.2
		 *
		 * @param array $properties Property catalog.
		 */
		return (array) apply_filters( 'wpforms_integrations_abilities_field_registry_properties', $properties );
	}

	/**
	 * Curated type registry.
	 *
	 * Per-type entry keys:
	 *   - `requires`        — availability gate: 'always' | 'pro' | callable | addon slug.
	 *   - `label`           — human-readable type label (i18n).
	 *   - `props`           — string[] of property keys allowed for this type (subset of properties()).
	 *   - `required_props`  — string[] of property keys that MUST be supplied; checked by preflight
	 *                         in FormMutator. Deliberately NOT named `required` so filter authors
	 *                         do not confuse it with the availability key `requires`.
	 *
	 * @since 1.10.2
	 *
	 * @return array
	 */
	private function types(): array {

		$text   = [ 'label', 'description', 'required', 'size', 'label_hide', 'placeholder', 'default_value' ];
		$choice = [ 'label', 'description', 'required', 'size', 'label_hide', 'choices' ];

		$types = [
			'text'        => [
				'requires' => self::ALWAYS,
				'label'    => __( 'Single Line Text', 'wpforms-lite' ),
				'props'    => $text,
			],
			'textarea'    => [
				'requires' => self::ALWAYS,
				'label'    => __( 'Paragraph Text', 'wpforms-lite' ),
				'props'    => $text,
			],
			'email'       => [
				'requires' => self::ALWAYS,
				'label'    => __( 'Email', 'wpforms-lite' ),
				'props'    => $text,
			],
			'number'      => [
				'requires' => self::ALWAYS,
				'label'    => __( 'Numbers', 'wpforms-lite' ),
				'props'    => $text,
			],
			'select'      => [
				'requires' => self::ALWAYS,
				'label'    => __( 'Dropdown', 'wpforms-lite' ),
				'props'    => array_merge( $choice, [ 'style' ] ),
			],
			'radio'       => [
				'requires' => self::ALWAYS,
				'label'    => __( 'Multiple Choice', 'wpforms-lite' ),
				'props'    => array_merge( $choice, [ 'input_columns' ] ),
			],
			'checkbox'    => [
				'requires' => self::ALWAYS,
				'label'    => __( 'Checkboxes', 'wpforms-lite' ),
				'props'    => array_merge( $choice, [ 'input_columns' ] ),
			],
			'name'        => [
				'requires' => self::ALWAYS,
				'label'    => __( 'Name', 'wpforms-lite' ),
				'props'    => [ 'label', 'description', 'required', 'size', 'label_hide' ],
			],
			'phone'       => [
				'requires' => self::PRO,
				'label'    => __( 'Phone', 'wpforms-lite' ),
				'props'    => $text,
			],
			'date-time'   => [
				'requires' => self::PRO,
				'label'    => __( 'Date / Time', 'wpforms-lite' ),
				'props'    => [ 'label', 'description', 'required', 'size', 'label_hide' ],
			],
			'file-upload' => [
				'requires' => self::PRO,
				'label'    => __( 'File Upload', 'wpforms-lite' ),
				'props'    => [
					'label',
					'description',
					'required',
					'size',
					'label_hide',
					'extensions',
					'max_size',
					'max_file_number',
					'media_library',
					'style',
				],
			],
		];

		/**
		 * Filters the Abilities API curated field-type registry.
		 *
		 * @since 1.10.2
		 *
		 * @param array $types Type registry.
		 */
		return (array) apply_filters( 'wpforms_integrations_abilities_field_registry_types', $types );
	}

	/**
	 * Human-readable label for a field type.
	 *
	 * @since 1.10.2
	 *
	 * @param string $type Field type.
	 *
	 * @return string The type's display label, or the raw type if unknown.
	 */
	public function get_type_label( string $type ): string {

		$types = $this->types();

		return $types[ $type ]['label'] ?? $type;
	}

	/**
	 * Whether a field type may be created or edited in this install.
	 *
	 * @since 1.10.2
	 *
	 * @param string $type Field type.
	 *
	 * @return bool
	 */
	public function is_field_type_available( string $type ): bool {

		$types = $this->types();

		if ( ! isset( $types[ $type ] ) ) {
			return false;
		}

		$requires = $types[ $type ]['requires'];

		if ( $requires === self::ALWAYS ) {
			return true;
		}

		if ( $requires === self::PRO ) {
			return wpforms()->is_pro();
		}

		// Accept callables shaped as Closures or [ $obj, 'method' ] arrays; a plain data
		// array (a likely filter-author mistake) is rejected here instead of warning
		// inside call_user_func() and silently falling through to false.
		if ( ( $requires instanceof Closure || is_array( $requires ) ) && is_callable( $requires ) ) {
			return (bool) call_user_func( $requires );
		}

		if ( is_string( $requires ) ) {
			return $this->is_addon_active( $requires );
		}

		// Fail closed on unknown shapes.
		return false;
	}

	/**
	 * Whether the given WPForms addon plugin is active.
	 *
	 * Lazily loads wp-admin/includes/plugin.php so the function is available
	 * outside the admin context.
	 *
	 * @since 1.10.2
	 *
	 * @param string $slug Addon plugin slug (e.g. 'wpforms-signatures').
	 *
	 * @return bool
	 */
	private function is_addon_active( string $slug ): bool {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( "{$slug}/{$slug}.php" );
	}

	/**
	 * Required property keys for a field type.
	 *
	 * Reads the `required_props` list declared in the type's registry entry, or
	 * returns an empty array when none are declared. The key is intentionally NOT
	 * `required` so filter authors cannot confuse it with the availability key
	 * `requires` (one letter apart, completely different meaning).
	 *
	 * @since 1.10.2
	 *
	 * @param string $type Field type.
	 *
	 * @return string[]
	 */
	public function required_properties( string $type ): array {

		$types = $this->types();

		return $types[ $type ]['required_props'] ?? [];
	}

	/**
	 * Available type keys for this install.
	 *
	 * Filters the curated set by per-type availability (Pro / addon active / callable).
	 * Use for discovery output (describer, listings) so unavailable types are not advertised.
	 *
	 * @since 1.10.2
	 *
	 * @return string[]
	 */
	public function available_types(): array {

		return array_values(
			array_filter(
				array_keys( $this->types() ),
				[ $this, 'is_field_type_available' ]
			)
		);
	}

	/**
	 * Allowed property keys for a type.
	 *
	 * @since 1.10.2
	 *
	 * @param string $type Field type.
	 *
	 * @return string[]
	 */
	public function allowed_properties( string $type ): array {

		$types = $this->types();

		return $types[ $type ]['props'] ?? [];
	}

	/**
	 * Sanitize and filter incoming properties for a type.
	 *
	 * @since 1.10.2
	 *
	 * @param string $type  Field type.
	 * @param array  $input Raw property input.
	 *
	 * @return array Sanitized values and list of ignored keys.
	 */
	public function sanitize_properties( string $type, array $input ): array {

		$allowed = $this->allowed_properties( $type );
		$catalog = $this->properties();
		$values  = [];
		$ignored = [];

		foreach ( $input as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) || ! isset( $catalog[ $key ] ) ) {
				$ignored[] = $key;

				continue;
			}

			$sanitizer = $catalog[ $key ]['sanitize'] ?? null;

			// Defensive: a third-party extension may register a property with a non-callable
			// sanitizer through the `wpforms_integrations_abilities_field_registry_properties`
			// filter. Drop the property to `ignored` instead of crashing on `call_user_func`.
			if ( ! is_callable( $sanitizer ) ) {
				$ignored[] = $key;

				continue;
			}

			$values[ $key ] = call_user_func( $sanitizer, $value );
		}

		return [
			'values'  => $values,
			'ignored' => $ignored,
		];
	}

	/**
	 * Flat input-properties JSON schema for add-field and update-field.
	 *
	 * @since 1.10.2
	 *
	 * @return array
	 */
	public function input_properties_schema(): array {

		$properties = [];

		foreach ( $this->properties() as $key => $definition ) {
			$properties[ $key ] = $definition['schema'];
		}

		return [
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		];
	}

	/**
	 * Full JSON schema for a single field item used by create-form's fields array.
	 *
	 * The type is intentionally an unconstrained string so the FormMutator preflight
	 * callback owns every "this type is not supported here" response with HTTP 422
	 * `wpforms_field_type_unavailable`, covering both Pro-on-Lite and entirely unknown
	 * types under one contract. Discoverable types are advertised via the
	 * describe-editing-schema ability.
	 *
	 * @since 1.10.2
	 *
	 * @return array
	 */
	public function field_item_schema(): array {

		return [
			'type'                 => 'object',
			'properties'           => array_merge(
				[
					'type' => [
						'type' => 'string',
					],
				],
				$this->input_properties_schema()['properties']
			),
			'required'             => [ 'type' ],
			'additionalProperties' => false,
		];
	}

	/**
	 * Describe available types and their properties for the discovery ability.
	 *
	 * @since 1.10.2
	 *
	 * @return array
	 */
	public function describe(): array {

		$catalog = $this->properties();
		$types   = $this->types();
		$out     = [];

		foreach ( $this->available_types() as $type ) {
			$props = [];

			foreach ( $this->allowed_properties( $type ) as $key ) {
				$props[] = [
					'key'    => $key,
					'schema' => $catalog[ $key ]['schema'] ?? [],
				];
			}

			$out[] = [
				'type'           => $type,
				'label'          => $types[ $type ]['label'] ?? $type,
				'properties'     => $props,
				'required_props' => $this->required_properties( $type ),
			];
		}

		return $out;
	}

	/**
	 * Sanitize a boolean-ish value to a WPForms-style string flag.
	 *
	 * Delegates to `rest_sanitize_boolean()` so that string transports (REST query
	 * string, form-encoded body) coerce correctly: `"false"`, `"0"`, `"off"`, `"no"`
	 * become false, otherwise PHP would treat any non-empty string — including the
	 * literal `"false"` — as truthy.
	 *
	 * @since 1.10.2
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string '1' or ''.
	 */
	protected function sanitize_bool( $value ): string {

		return rest_sanitize_boolean( $value ) ? '1' : '';
	}

	/**
	 * Sanitize an ordered list of label/value choice objects.
	 *
	 * @since 1.10.2
	 *
	 * @param mixed $choices Raw choices list.
	 *
	 * @return array Ordered list of sanitized choice arrays.
	 */
	protected function sanitize_choices( $choices ): array {

		if ( ! is_array( $choices ) ) {
			return [];
		}

		$clean = [];

		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) || ! isset( $choice['label'] ) ) {
				continue;
			}

			$clean[] = [
				'label' => sanitize_text_field( $choice['label'] ),
				'value' => isset( $choice['value'] ) ? sanitize_text_field( $choice['value'] ) : '',
			];
		}

		return $clean;
	}
}
