<?php

namespace WPForms\Integrations\AI\Admin\Chat\Filter;

/**
 * Declarative field × operator registry consumed by FilterCompiler.
 *
 * Scopes build their spec at instantiation time; the compiler validates
 * each incoming filter against the spec without knowing scope internals.
 *
 * @since 2.0.0
 */
class FieldSpec {

	/**
	 * Registered fields keyed by field slug.
	 *
	 * Each entry has shape:
	 *   [
	 *       'type'            => FieldType::*,
	 *       'ops'             => string[],
	 *       'allowed_values'  => string[],   // ENUM only.
	 *       'supports_window' => bool,       // NUMERIC only.
	 *   ]
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $fields = [];

	/**
	 * Per-type operator allow-list. Falls back to FieldType::default_ops_by_type() per lookup.
	 *
	 * Scopes that want to extend the matrix (e.g. Pro adding the `numeric` family)
	 * call `set_ops_by_type()` with their merged map before any `add()` calls.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $ops_by_type = [];

	/**
	 * Override the operator-by-type matrix.
	 *
	 * Call this before `add()` if a scope needs Pro/custom types or wants to
	 * narrow the default ops for a known type. Subsequent `add()` calls validate
	 * the per-field op list against this matrix.
	 *
	 * @since 2.0.0
	 *
	 * @param array $matrix Operator slugs allowed per type.
	 */
	public function set_ops_by_type( array $matrix ): void {

		$this->ops_by_type = $matrix;
	}

	/**
	 * Register a field.
	 *
	 * Silently no-ops if the requested type or ops are invalid — scopes are
	 * expected to register correct entries. Positional args only (PHP 7.2+).
	 *
	 * @since 2.0.0
	 *
	 * @param string $field           Field slug exposed to the LLM.
	 * @param string $type            One of FieldType::* constants.
	 * @param array  $ops             Operator slugs allowed for this field.
	 * @param array  $allowed_values  Enum value set (only for FieldType::ENUM).
	 * @param bool   $supports_window Honor since/until on this filter (NUMERIC only).
	 */
	public function add( string $field, string $type, array $ops, array $allowed_values = [], bool $supports_window = false ): void {

		$field = sanitize_key( $field );

		if ( $field === '' ) {
			return;
		}

		$allowed_for_type = $this->get_ops_by_type()[ $type ] ?? [];

		if ( $allowed_for_type === [] ) {
			return;
		}

		// Restrict the per-field ops to the type's allowed set.
		$ops = array_values( array_intersect( $ops, $allowed_for_type ) );

		if ( $ops === [] ) {
			return;
		}

		$this->fields[ $field ] = [
			'type'            => $type,
			'ops'             => $ops,
			'allowed_values'  => array_values( $allowed_values ),
			'supports_window' => $type === FieldType::NUMERIC && $supports_window,
		];
	}

	/**
	 * Whether the field is registered.
	 *
	 * @since 2.0.0
	 *
	 * @param string $field Field slug.
	 *
	 * @return bool
	 */
	public function has_field( string $field ): bool {

		return isset( $this->fields[ $field ] );
	}

	/**
	 * Get the field's declared type, or null if not registered.
	 *
	 * @since 2.0.0
	 *
	 * @param string $field Field slug.
	 *
	 * @return string|null
	 */
	public function get_field_type( string $field ): ?string {

		return $this->fields[ $field ]['type'] ?? null;
	}

	/**
	 * Get the operators allowed for the field.
	 *
	 * @since 2.0.0
	 *
	 * @param string $field Field slug.
	 *
	 * @return array
	 */
	public function get_field_ops( string $field ): array {

		return $this->fields[ $field ]['ops'] ?? [];
	}

	/**
	 * Get the ENUM value set for the field (empty array for non-enum fields).
	 *
	 * @since 2.0.0
	 *
	 * @param string $field Field slug.
	 *
	 * @return array
	 */
	public function get_allowed_values( string $field ): array {

		return $this->fields[ $field ]['allowed_values'] ?? [];
	}

	/**
	 * Whether the field honors a since/until date window.
	 *
	 * @since 2.0.0
	 *
	 * @param string $field Field slug.
	 *
	 * @return bool
	 */
	public function supports_window( string $field ): bool {

		return ! empty( $this->fields[ $field ]['supports_window'] );
	}

	/**
	 * Get a `field => type` map of all registered fields.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_fields(): array {

		$out = [];

		foreach ( $this->fields as $field => $config ) {
			$out[ $field ] = $config['type'];
		}

		return $out;
	}

	/**
	 * Get the active operator-by-type matrix, falling back to FieldType defaults.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_ops_by_type(): array {

		return $this->ops_by_type !== [] ? $this->ops_by_type : FieldType::default_ops_by_type();
	}
}
