<?php

namespace WPForms\Integrations\AI\Admin\Chat\Filter;

/**
 * Field type tags used by FieldSpec + FilterCompiler.
 *
 * Class (not enum) — WPForms targets PHP 7.2+.
 *
 * @since 2.0.0
 */
class FieldType {

	/**
	 * Exact integer column (e.g. id, form_id).
	 *
	 * Operators: eq, neq, in. No date window.
	 *
	 * @since 2.0.0
	 */
	public const INT = 'int';

	/**
	 * Free-text column (e.g. title, post_title).
	 *
	 * Operators: eq, neq, contains, not_contains, empty, not_empty.
	 *
	 * @since 2.0.0
	 */
	public const STRING = 'string';

	/**
	 * Closed value set (e.g. status). Field must register allowed_values.
	 *
	 * Operators: eq, neq, in.
	 *
	 * @since 2.0.0
	 */
	public const ENUM = 'enum';

	/**
	 * ISO YYYY-MM-DD column (e.g. created, modified, period_date).
	 *
	 * Operators: eq, lt, lte, gt, gte.
	 *
	 * @since 2.0.0
	 */
	public const DATE = 'date';

	/**
	 * Multi-value field (e.g. tags). `ARRAY` is a PHP reserved word — use ARRAY_T.
	 *
	 * Operators: contains, not_contains, in, empty, not_empty.
	 *
	 * @since 2.0.0
	 */
	public const ARRAY_T = 'array';

	/**
	 * Aggregate metric, optionally windowed via since/until (e.g. entries_count, analytics_*).
	 *
	 * Operators: eq, neq, lt, lte, gt, gte.
	 *
	 * @since 2.0.0
	 */
	public const NUMERIC = 'numeric';

	/**
	 * Sanitize-key-style identifier (lowercase, underscores, hyphens). Open vocabulary
	 * — unlike ENUM, no allowed_values registration required.
	 *
	 * Operators: eq, neq, in.
	 *
	 * @since 2.0.0
	 */
	public const KEY = 'key';

	/**
	 * Default operator → type matrix.
	 *
	 * Used as the FilterCompiler fallback when a scope does not supply its own
	 * via `FieldSpec::set_ops_by_type()` or via the scope's `get_ops_by_type()`.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public static function default_ops_by_type(): array {

		return [
			self::INT     => [ 'eq', 'neq', 'in' ],
			self::STRING  => [ 'eq', 'neq', 'contains', 'not_contains', 'empty', 'not_empty' ],
			self::ENUM    => [ 'eq', 'neq', 'in' ],
			self::KEY     => [ 'eq', 'neq', 'in' ],
			self::DATE    => [ 'eq', 'lt', 'lte', 'gt', 'gte' ],
			self::ARRAY_T => [ 'contains', 'not_contains', 'in', 'empty', 'not_empty' ],
			self::NUMERIC => [ 'eq', 'neq', 'lt', 'lte', 'gt', 'gte' ],
		];
	}
}
