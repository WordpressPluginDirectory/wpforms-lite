<?php

namespace WPForms\Integrations\AI\Admin\Chat\Filter;

/**
 * Result of FilterCompiler::normalize().
 *
 * Carries the kept (validated) entries that scopes feed into translators,
 * plus the rejected entries that scopes surface back to the LLM as
 * `rejected_filters` in the response payload.
 *
 * @since 2.0.0
 */
class NormalizationResult {

	/**
	 * Reason code: filter entry was not an array or missed required keys.
	 *
	 * @since 2.0.0
	 */
	public const REASON_MALFORMED = 'malformed';

	/**
	 * Reason code: field name not registered in the FieldSpec.
	 *
	 * @since 2.0.0
	 */
	public const REASON_UNKNOWN_FIELD = 'unknown_field';

	/**
	 * Reason code: op not allowed for this field's type.
	 *
	 * @since 2.0.0
	 */
	public const REASON_UNKNOWN_OP = 'unknown_op_for_type';

	/**
	 * Reason code: value coercion failed (e.g. invalid date format, non-numeric string for NUMERIC).
	 *
	 * @since 2.0.0
	 */
	public const REASON_INVALID_VALUE = 'invalid_value';

	/**
	 * Reason code: ENUM value not in the field's allowed_values.
	 *
	 * @since 2.0.0
	 */
	public const REASON_OUT_OF_ENUM_SET = 'out_of_enum_set';

	/**
	 * Reason code: dropped because the per-call cap was reached.
	 *
	 * @since 2.0.0
	 */
	public const REASON_MAX_EXCEEDED = 'max_filters_exceeded';

	/**
	 * Normalized + validated filters ready for translator dispatch.
	 *
	 * Each entry has the NormalizedFilter shape:
	 *   [ 'field' => string, 'op' => string, 'value' => mixed, 'since' => string, 'until' => string ]
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public $kept = [];

	/**
	 * Rejected filter entries surfaced back to the LLM.
	 *
	 * Each entry has the RejectedFilter shape:
	 *   [ 'field' => string, 'op' => string, 'value' => mixed, 'reason' => string ]
	 *
	 * The `reason` is one of the `REASON_*` constants on this class.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public $rejected = [];
}
