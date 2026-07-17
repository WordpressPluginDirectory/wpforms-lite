<?php

namespace WPForms\Integrations\AI\Admin\Chat\Filter;

/**
 * Filter-operator helpers used by scope $wpdb queries.
 *
 * Translates the filter DSL's operator vocabulary (`eq`, `neq`, `lt`, `lte`,
 * `gt`, `gte`) into either a SQL comparison fragment (for WHERE / HAVING /
 * column comparisons) or a PHP boolean evaluation (for post-query filtering
 * of derived metrics like conversion rate).
 *
 * Instance methods rather than static so scopes can mock the helper in unit
 * tests — matches the `PostsTranslators` pattern.
 *
 * **Security:** callers MUST ensure the left-hand side of `to_sql()` is
 * safe — a scope-owned constant (e.g. `'COUNT(*)'`, `'SUM(views)'`) or an
 * allow-listed column name. Never pass user-supplied strings; this method
 * interpolates the LHS directly into the SQL template and only binds the
 * right-hand integer value via `$wpdb->prepare()`.
 *
 * @since 2.0.0
 */
class Operators {

	/**
	 * Filter-op slug → SQL comparison operator.
	 *
	 * @since 2.0.0
	 */
	public const SQL = [
		'eq'  => '=',
		'neq' => '<>',
		'lt'  => '<',
		'lte' => '<=',
		'gt'  => '>',
		'gte' => '>=',
	];

	/**
	 * Render a SQL comparison fragment: `<left> <sql_op> <placeholder>` bound via prepare.
	 *
	 * Works for both WHERE column comparisons (`views > 100`) and HAVING
	 * aggregate comparisons (`SUM(views) > 100`, `COUNT(*) >= 5`). The
	 * placeholder defaults to `%d` for the common integer-threshold case;
	 * date/string comparisons pass `%s`.
	 *
	 * @since 2.0.0
	 *
	 * @param string $left        Left-hand side (column name or aggregate expression).
	 *                            Caller-owned safe string — see class-level security note.
	 * @param string $op          Filter-op slug.
	 * @param mixed  $value       Threshold value (int for `%d`, string for `%s`).
	 * @param string $placeholder Prepared-statement placeholder — `%d` (default) or `%s`.
	 *                            Other placeholders are unsupported.
	 *
	 * @return string|null Prepared SQL fragment, or null if the op or placeholder is unsupported.
	 *
	 * @internal The returned fragment is value-inlined (already prepared). Some callers
	 *           embed it in a larger query that is prepared a second time, which only
	 *           stays safe while the inlined value contains no `%` (current callers pass
	 *           integer thresholds and validated ISO dates). Before introducing a
	 *           free-text / LIKE string filter, switch to a deferred placeholder + a
	 *           value bound by the outer prepare() so the value is never inlined here.
	 */
	public function to_sql( string $left, string $op, $value, string $placeholder = '%d' ): ?string {

		global $wpdb;

		if ( ! isset( self::SQL[ $op ] ) ) {
			return null;
		}

		if ( $placeholder !== '%d' && $placeholder !== '%s' ) {
			return null;
		}

		$sql_op = self::SQL[ $op ];

		// The placeholder is constrained to %d or %s above. Each branch keeps a
		// literal placeholder in the query so prepare() binds $value correctly;
		// only $left and $sql_op (caller-owned safe strings) are interpolated.
		if ( $placeholder === '%s' ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->prepare( "$left $sql_op %s", $value );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->prepare( "$left $sql_op %d", $value );
	}

	/**
	 * Evaluate the operator in PHP — used when a derived metric (e.g. conversion rate)
	 * is computed post-query and the threshold must be applied row-by-row.
	 *
	 * `eq` / `neq` cast `$actual` to int before comparison (matching the lossy
	 * integer-equality semantics of the SQL `=` / `<>` operators against `%d`
	 * bind parameters). Range operators compare as floats so a 50.5% rate vs
	 * 50% threshold behaves correctly.
	 *
	 * @since 2.0.0
	 *
	 * @param float  $actual    Left side (typically a computed metric value).
	 * @param string $op        Filter-op slug.
	 * @param int    $threshold Right side.
	 *
	 * @return bool False on unsupported op.
	 */
	public function evaluate( float $actual, string $op, int $threshold ): bool {

		// Strategy map mirroring the switch it replaced: `eq` / `neq` cast to int
		// (lossy SQL `%d` semantics), range ops compare as floats. Unknown ops fall
		// through to the `false` default below.
		// phpcs:disable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement
		$comparators = [
			'eq'  => static function () use ( $actual, $threshold ): bool {
				return (int) $actual === $threshold;
			},
			'neq' => static function () use ( $actual, $threshold ): bool {
				return (int) $actual !== $threshold;
			},
			'lt'  => static function () use ( $actual, $threshold ): bool {
				return $actual < $threshold;
			},
			'lte' => static function () use ( $actual, $threshold ): bool {
				return $actual <= $threshold;
			},
			'gt'  => static function () use ( $actual, $threshold ): bool {
				return $actual > $threshold;
			},
			'gte' => static function () use ( $actual, $threshold ): bool {
				return $actual >= $threshold;
			},
		];
		// phpcs:enable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement

		if ( ! isset( $comparators[ $op ] ) ) {
			return false;
		}

		return $comparators[ $op ]();
	}

	/**
	 * Read the operator slug and raw value out of a normalized filter entry.
	 *
	 * The op is normalized to a string; the value is returned as-is so each
	 * caller can apply its own coercion (int list, string, term list, etc.).
	 *
	 * @since 2.0.0
	 *
	 * @param array $filter Normalized filter entry.
	 *
	 * @return array{0: string, 1: mixed} Tuple of [ op, value ].
	 */
	public static function read_op_value( array $filter ): array {

		return [
			(string) ( $filter['op'] ?? '' ),
			$filter['value'] ?? null,
		];
	}
}
