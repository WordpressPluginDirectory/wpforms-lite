<?php

namespace WPForms\Integrations\AI\Admin\Chat\Filter;

use DateTime;

/**
 * Validate + normalize raw filter entries against a FieldSpec.
 *
 * Scope-agnostic. Each AI Chat scope that accepts `args.filters` constructs a
 * compiler with its own FieldSpec (and optionally a custom coercion callback)
 * and feeds raw filter entries through `normalize()`. The result carries kept
 * entries ready for translator dispatch plus rejected entries to surface back
 * to the LLM.
 *
 * @since 2.0.0
 */
class FilterCompiler {

	/**
	 * Field spec the compiler validates against.
	 *
	 * @since 2.0.0
	 *
	 * @var FieldSpec
	 */
	private $spec;

	/**
	 * Optional scope-supplied scalar coercion callback.
	 *
	 * Signature: `fn( string $type, string $field, mixed $value ): mixed`.
	 *
	 * Returns the coerced value, or null/empty-string to signal rejection.
	 * When null, the compiler uses its default coercion table.
	 *
	 * @since 2.0.0
	 *
	 * @var callable|null
	 */
	private $coerce_callback;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param FieldSpec     $spec            Field spec the compiler validates against.
	 * @param callable|null $coerce_callback Optional `fn( string $type, string $field, $value ): mixed`.
	 *                                       Scopes typically pass `[ $this, 'coerce_scalar' ]` so Pro overrides apply.
	 */
	public function __construct( FieldSpec $spec, ?callable $coerce_callback = null ) {

		$this->spec            = $spec;
		$this->coerce_callback = $coerce_callback;
	}

	/**
	 * Validate + normalize a raw filter list.
	 *
	 * @since 2.0.0
	 *
	 * @param array $raw Raw filter array from `request_data.args.filters`.
	 * @param int   $max Per-call cap. Valid entries beyond this index are rejected with
	 *                   reason `max_filters_exceeded`.
	 *
	 * @return NormalizationResult
	 */
	public function normalize( array $raw, int $max = 10 ): NormalizationResult {

		$result = new NormalizationResult();
		$max    = max( 0, $max );

		foreach ( $raw as $entry ) {
			$this->classify_entry( $entry, $result, $max );
		}

		return $result;
	}

	/**
	 * Classify a single raw entry into kept or rejected on the running result.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed               $entry  Raw entry from the LLM payload.
	 * @param NormalizationResult $result Running result.
	 * @param int                 $max    Per-call cap on kept entries.
	 */
	private function classify_entry( $entry, NormalizationResult $result, int $max ): void {

		if ( ! is_array( $entry ) ) {
			$result->rejected[] = $this->build_rejection( [], NormalizationResult::REASON_MALFORMED );

			return;
		}

		$field = sanitize_key( (string) ( $entry['field'] ?? '' ) );
		$op    = sanitize_key( (string) ( $entry['op'] ?? '' ) );

		$value  = null;
		$reason = $this->validate_op_value( $field, $op, $entry, $value );

		if ( $reason !== '' ) {
			$result->rejected[] = $this->build_rejection( $entry, $reason );

			return;
		}

		if ( count( $result->kept ) >= $max ) {
			$result->rejected[] = $this->build_rejection( $entry, NormalizationResult::REASON_MAX_EXCEEDED );

			return;
		}

		$result->kept[] = [
			'field' => $field,
			'op'    => $op,
			'value' => $value,
			'since' => $this->parse_window_bound( $field, $entry, 'since' ),
			'until' => $this->parse_window_bound( $field, $entry, 'until' ),
		];
	}

	/**
	 * Validate a raw entry's field/op/value triple and resolve the coerced value.
	 *
	 * Writes the coerced value into the `&$value` ref on success and returns an
	 * empty string. On failure returns a REASON_* constant; the caller pushes the
	 * entry into the rejected bucket using that reason.
	 *
	 * @since 2.0.0
	 *
	 * @param string $field Sanitized field slug.
	 * @param string $op    Sanitized operator slug.
	 * @param array  $entry Raw entry from the LLM payload.
	 * @param mixed  $value Out-param — set to the coerced value on success.
	 *
	 * @return string Empty string on success, otherwise a REASON_* constant.
	 */
	private function validate_op_value( string $field, string $op, array $entry, &$value ): string {

		if ( $field === '' || $op === '' ) {
			return NormalizationResult::REASON_MALFORMED;
		}

		if ( ! $this->spec->has_field( $field ) ) {
			return NormalizationResult::REASON_UNKNOWN_FIELD;
		}

		if ( ! in_array( $op, $this->spec->get_field_ops( $field ), true ) ) {
			return NormalizationResult::REASON_UNKNOWN_OP;
		}

		$type   = (string) $this->spec->get_field_type( $field );
		$reason = '';
		$value  = $this->normalize_value( $type, $field, $op, $entry['value'] ?? null, $reason );

		return $reason;
	}

	/**
	 * Resolve a window bound (`since`/`until`) for a kept entry.
	 *
	 * Returns the parsed ISO date when the field supports windowing, otherwise an
	 * empty string.
	 *
	 * @since 2.0.0
	 *
	 * @param string $field Sanitized field slug.
	 * @param array  $entry Raw entry from the LLM payload.
	 * @param string $bound Window bound key — `since` or `until`.
	 *
	 * @return string
	 */
	private function parse_window_bound( string $field, array $entry, string $bound ): string {

		if ( ! $this->spec->supports_window( $field ) ) {
			return '';
		}

		return $this->parse_iso_date( $entry[ $bound ] ?? '' );
	}

	/**
	 * Coerce + validate a filter value. Returns the value on success.
	 *
	 * Writes the rejection reason into the `&$reason` ref on failure; the caller
	 * branches on `$reason !== ''` to push the entry into the rejected bucket.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type      Field type (FieldType::*).
	 * @param string $field     Field slug.
	 * @param string $op        Operator slug.
	 * @param mixed  $raw_value Raw value as received.
	 * @param string $reason    Out-param — set to a REASON_* constant on rejection.
	 *
	 * @return mixed
	 */
	private function normalize_value( string $type, string $field, string $op, $raw_value, string &$reason ) {

		if ( in_array( $op, [ 'empty', 'not_empty' ], true ) ) {
			return null;
		}

		if ( $op === 'in' ) {
			return $this->normalize_in_value( $type, $field, $raw_value, $reason );
		}

		$coerced = $this->coerce( $type, $field, $raw_value );

		if ( $coerced === null || $coerced === '' ) {
			$reason = NormalizationResult::REASON_INVALID_VALUE;

			return null;
		}

		if ( $type === FieldType::ENUM && ! in_array( $coerced, $this->spec->get_allowed_values( $field ), true ) ) {
			$reason = NormalizationResult::REASON_OUT_OF_ENUM_SET;

			return null;
		}

		return $coerced;
	}

	/**
	 * Normalize the multi-value payload for the `in` operator.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type      Field type.
	 * @param string $field     Field slug.
	 * @param mixed  $raw_value Raw value (must be an array).
	 * @param string $reason    Out-param — set to a REASON_* constant on rejection.
	 *
	 * @return array|null
	 */
	private function normalize_in_value( string $type, string $field, $raw_value, string &$reason ): ?array {

		if ( ! is_array( $raw_value ) ) {
			$reason = NormalizationResult::REASON_INVALID_VALUE;

			return null;
		}

		$values = $this->coerce_in_elements( $type, $field, $raw_value );

		if ( $values === [] ) {
			$reason = NormalizationResult::REASON_INVALID_VALUE;

			return null;
		}

		if ( $type === FieldType::ENUM ) {
			$values = array_values( array_intersect( $values, $this->spec->get_allowed_values( $field ) ) );

			if ( $values === [] ) {
				$reason = NormalizationResult::REASON_OUT_OF_ENUM_SET;

				return null;
			}
		}

		return array_values( array_unique( $values, SORT_REGULAR ) );
	}

	/**
	 * Coerce each element of an `in` value list, dropping ones that fail coercion.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type      Field type.
	 * @param string $field     Field slug.
	 * @param array  $raw_value Raw value list.
	 *
	 * @return array Coerced, non-empty values (order preserved, not yet de-duplicated).
	 */
	private function coerce_in_elements( string $type, string $field, array $raw_value ): array {

		$values = [];

		foreach ( $raw_value as $element ) {
			$coerced = $this->coerce( $type, $field, $element );

			if ( $coerced === null || $coerced === '' || $coerced === false ) {
				continue;
			}

			$values[] = $coerced;
		}

		return $values;
	}

	/**
	 * Coerce a single value through the scope callback or the default coercion table.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type  Field type.
	 * @param string $field Field slug.
	 * @param mixed  $value Raw value.
	 *
	 * @return mixed
	 */
	private function coerce( string $type, string $field, $value ) {

		if ( $this->coerce_callback !== null ) {
			return call_user_func( $this->coerce_callback, $type, $field, $value );
		}

		return $this->default_coerce( $type, $value );
	}

	/**
	 * Default coercion table — used when no scope callback is supplied.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type  Field type.
	 * @param mixed  $value Raw value.
	 *
	 * @return mixed
	 */
	private function default_coerce( string $type, $value ) {

		if ( $type === FieldType::INT || $type === FieldType::NUMERIC ) {
			return $this->coerce_integer( $value );
		}

		if ( $type === FieldType::DATE ) {
			return $this->parse_iso_date( $value );
		}

		// String-family types each sanitize a string value through a dedicated
		// WordPress sanitizer and reject non-strings (null). Mapping the type to
		// its sanitizer keeps the per-type ternaries out of this method's branching.
		$string_sanitizers = [
			FieldType::STRING  => 'sanitize_text_field',
			FieldType::ARRAY_T => 'sanitize_text_field',
			FieldType::ENUM    => 'sanitize_key',
			FieldType::KEY     => 'sanitize_key',
		];

		if ( ! isset( $string_sanitizers[ $type ] ) || ! is_string( $value ) ) {
			return null;
		}

		return $string_sanitizers[ $type ]( $value );
	}

	/**
	 * Coerce a raw value to an integer for INT / NUMERIC fields.
	 *
	 * Accepts native integers as-is and digit-only strings (optionally signed).
	 * Returns null for anything that is not a clean integer.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int|null
	 */
	private function coerce_integer( $value ): ?int {

		if ( is_int( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && ctype_digit( ltrim( $value, '-' ) ) ) {
			return (int) $value;
		}

		return null;
	}

	/**
	 * Validate + normalize an ISO `YYYY-MM-DD` date string.
	 *
	 * Returns the valid ISO date, or empty string if invalid (which the caller
	 * treats as a rejection signal).
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Raw input.
	 *
	 * @return string
	 */
	private function parse_iso_date( $value ): string {

		$value = sanitize_text_field( (string) $value );

		if ( $value === '' ) {
			return '';
		}

		$date = DateTime::createFromFormat( 'Y-m-d', $value );

		return $date !== false && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	/**
	 * Build a RejectedFilter entry from a raw entry + reason code.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $entry  Raw entry (may be partial/malformed).
	 * @param string $reason One of NormalizationResult::REASON_* constants.
	 *
	 * @return array
	 */
	private function build_rejection( array $entry, string $reason ): array {

		return [
			'field'  => isset( $entry['field'] ) ? (string) $entry['field'] : '',
			'op'     => isset( $entry['op'] ) ? (string) $entry['op'] : '',
			'value'  => $entry['value'] ?? null,
			'reason' => $reason,
		];
	}
}
