<?php

namespace WPForms\Integrations\AI\Admin\Chat\Filter\Translators;

use WPForms\Integrations\AI\Admin\Chat\Filter\Operators;

/**
 * Translate NormalizedFilter entries into raw `$wpdb` WHERE / HAVING fragments.
 *
 * Used by scopes that query custom WPForms tables directly via `$wpdb`
 * (analytics rollups today; future `entries_inventory` / `payments_inventory`
 * / `coupons` scopes). Each method appends to a `WpdbTranslationContext` —
 * either to the `where_parts` / `where_params` pair or the `having_parts` /
 * `having_params` pair. Scopes assemble the final bind list manually to
 * preserve placeholder order (see WpdbTranslationContext docblock).
 *
 * Instance methods rather than static so scopes can mock the helper in unit
 * tests — matches the `PostsTranslators` / `Operators` pattern.
 *
 * **Security contract:**
 * - **Column names** are interpolated into SQL directly (placeholders can't bind
 *   identifiers). Callers MUST pass scope-owned allow-listed names. Defense in
 *   depth: every method also validates `$column` against `/^\w+$/` (matches
 *   `[A-Za-z0-9_]` in non-Unicode mode) and silently drops the entry on mismatch.
 * - **HAVING expressions** are likewise interpolated raw — callers MUST pass
 *   scope-owned constants (e.g. `'SUM(views)'`, `'SUM(submissions) / NULLIF(SUM(views), 0) * 100'`).
 *   The expression regex guard is intentionally lighter (allows parens / spaces /
 *   arithmetic) since regex-validating arbitrary SQL math would be over-restrictive.
 * - **Values** ALWAYS go through `$wpdb->prepare()` via Operators / explicit
 *   `%d` / `%s` placeholders. Never interpolate filter values into SQL.
 *
 * @since 2.0.0
 */
class WpdbTranslators {

	/**
	 * Regex matching safe SQL identifiers (column names, with an optional `alias.` prefix).
	 *
	 * Permits the alias-qualified form callers use when the FROM clause aliases
	 * a table (e.g. `f.period_date` in `forms_ranking`). Still rejects whitespace,
	 * quotes, semicolons, parens, comment markers, and multi-segment paths.
	 *
	 * @since 2.0.0
	 */
	private const COLUMN_REGEX = '/^(?:[a-zA-Z_]\w*\.)?\w+$/';

	/**
	 * Regex matching safe HAVING expressions — letters, digits, underscores,
	 * whitespace, parens, arithmetic operators (`+ - * /`), and commas. No
	 * quotes, no semicolons, no comments.
	 *
	 * @since 2.0.0
	 */
	private const EXPRESSION_REGEX = '#^[\w\s()+\-*/,.]+$#';

	/**
	 * Op-helper.
	 *
	 * @since 2.0.0
	 *
	 * @var Operators
	 */
	private $operators;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Operators|null $operators Shared op-helper. Constructs a default instance when null.
	 */
	public function __construct( ?Operators $operators = null ) {

		$this->operators = $operators ?? new Operators();
	}

	/**
	 * Append a WHERE column comparison: `<column> <sql_op> %d` (or `%s`).
	 *
	 * Value placeholder follows the filter value's PHP type — int → `%d`,
	 * string → `%s`. Mixed-type values fall through to `%s` for safety.
	 *
	 * @since 2.0.0
	 *
	 * @param string                 $column Allow-listed column name.
	 * @param array                  $filter Normalized filter entry.
	 * @param WpdbTranslationContext $ctx    Accumulator context.
	 */
	public function where_compare( string $column, array $filter, WpdbTranslationContext $ctx ): void {

		if ( ! $this->is_safe_column( $column ) ) {
			return;
		}

		[ $op, $value ] = Operators::read_op_value( $filter );

		if ( $value === null ) {
			return;
		}

		$placeholder = is_int( $value ) ? '%d' : '%s';
		$fragment    = $this->operators->to_sql( $column, $op, $value, $placeholder );

		if ( $fragment === null ) {
			return;
		}

		// `Operators::to_sql()` pre-prepares the fragment with the value already
		// inlined and escaped, so the fragment carries no placeholder for the
		// outer `$wpdb->prepare()` to bind. Only `where_in` defers binding to
		// the outer prepare — see that method for the placeholder pattern.
		$ctx->where_parts[] = $fragment;
	}

	/**
	 * Append a WHERE `IN` / `NOT IN` clause: `<column> IN (%d, %d, …)`.
	 *
	 * Placeholder type follows the first value (int → `%d`, else `%s`).
	 * Empty values list is silently skipped.
	 *
	 * @since 2.0.0
	 *
	 * @param string                 $column Allow-listed column name.
	 * @param array                  $values Allow-listed values list (already FilterCompiler-normalized).
	 * @param WpdbTranslationContext $ctx    Accumulator context.
	 * @param bool                   $negate Whether to emit `NOT IN` instead of `IN`.
	 */
	public function where_in( string $column, array $values, WpdbTranslationContext $ctx, bool $negate = false ): void {

		if ( ! $this->is_safe_column( $column ) ) {
			return;
		}

		$values = array_values( $values );

		if ( $values === [] ) {
			return;
		}

		$placeholder  = is_int( reset( $values ) ) ? '%d' : '%s';
		$placeholders = implode( ', ', array_fill( 0, count( $values ), $placeholder ) );
		$keyword      = $negate ? 'NOT IN' : 'IN';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ctx->where_parts[] = "$column $keyword ( $placeholders )";
		$ctx->where_params  = array_merge( $ctx->where_params, $values );
	}

	/**
	 * Append a WHERE `LIKE` / `NOT LIKE` clause with auto `%…%` wrapping.
	 *
	 * Runs the value through `$wpdb->esc_like()` first so user-supplied `%` or
	 * `_` wildcards don't broaden the search beyond what the model intended.
	 *
	 * @since 2.0.0
	 *
	 * @param string                 $column Allow-listed column name.
	 * @param array                  $filter Normalized filter entry (op must be `contains` or `not_contains`).
	 * @param WpdbTranslationContext $ctx    Accumulator context.
	 */
	public function where_like( string $column, array $filter, WpdbTranslationContext $ctx ): void {

		global $wpdb;

		if ( ! $this->is_safe_column( $column ) ) {
			return;
		}

		[ $op, $value ] = Operators::read_op_value( $filter );

		$value = (string) $value;

		if ( $value === '' ) {
			return;
		}

		$keywords = [
			'contains'     => 'LIKE',
			'not_contains' => 'NOT LIKE',
		];

		$keyword = $keywords[ $op ] ?? '';

		if ( $keyword === '' ) {
			return;
		}

		$like = '%' . $wpdb->esc_like( $value ) . '%';

		// Keep the `%s` placeholder in the fragment and defer the value bind to
		// `where_params` (the `where_in()` pattern). The column / keyword are still
		// interpolated identifiers, hence the ignore.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ctx->where_parts[]  = "$column $keyword %s";
		$ctx->where_params[] = $like;
	}

	/**
	 * Append a WHERE date-range clause for an ISO `YYYY-MM-DD` column.
	 *
	 * Translates the filter's op (`eq`, `lt`, `lte`, `gt`, `gte`) into the
	 * corresponding SQL comparison with the `%s` placeholder. `eq` emits a
	 * `BETWEEN` pair (inclusive same-day start + end).
	 *
	 * @since 2.0.0
	 *
	 * @param string                 $column Allow-listed date column name.
	 * @param array                  $filter Normalized filter entry.
	 * @param WpdbTranslationContext $ctx    Accumulator context.
	 */
	public function where_date_range( string $column, array $filter, WpdbTranslationContext $ctx ): void {

		global $wpdb;

		if ( ! $this->is_safe_column( $column ) ) {
			return;
		}

		[ $op, $value ] = Operators::read_op_value( $filter );

		$value = (string) $value;

		if ( $value === '' ) {
			return;
		}

		if ( $op === 'eq' ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ctx->where_parts[] = $wpdb->prepare( "$column = %s", $value );

			return;
		}

		$fragment = $this->operators->to_sql( $column, $op, $value, '%s' );

		if ( $fragment === null ) {
			return;
		}

		// Fragment is pre-prepared by `Operators::to_sql()` — see note in
		// `where_compare()`. Only deferred-placeholder paths push to `where_params`.
		$ctx->where_parts[] = $fragment;
	}

	/**
	 * Append a HAVING aggregate comparison: `<expression> <sql_op> %d`.
	 *
	 * Used for derived metrics like `SUM(submissions)`, `COUNT(*)`, or
	 * conversion-rate expressions (`SUM(submissions) / NULLIF(SUM(views), 0) * 100`).
	 * The expression is interpolated raw — see class-level security note.
	 *
	 * @since 2.0.0
	 *
	 * @param string                 $expression Aggregate SQL expression (scope-owned constant).
	 * @param array                  $filter     Normalized filter entry.
	 * @param WpdbTranslationContext $ctx        Accumulator context.
	 */
	public function having_compare( string $expression, array $filter, WpdbTranslationContext $ctx ): void {

		if ( ! $this->is_safe_expression( $expression ) ) {
			return;
		}

		$op    = (string) ( $filter['op'] ?? '' );
		$value = (int) ( $filter['value'] ?? 0 );

		$fragment = $this->operators->to_sql( $expression, $op, $value );

		if ( $fragment === null ) {
			return;
		}

		// Fragment is pre-prepared by `Operators::to_sql()` — see note in
		// `where_compare()`. `having_params` exists for symmetry with `where_params`
		// in case a future deferred-placeholder translator targets HAVING.
		$ctx->having_parts[] = $fragment;
	}

	/**
	 * Defense-in-depth check that a column name is a bare SQL identifier.
	 *
	 * @since 2.0.0
	 *
	 * @param string $column Column name.
	 *
	 * @return bool
	 */
	private function is_safe_column( string $column ): bool {

		return $column !== '' && preg_match( self::COLUMN_REGEX, $column ) === 1;
	}

	/**
	 * Defense-in-depth check that a HAVING expression contains only safe characters
	 * and no SQL comment markers (`--` line comments, `/*` block comments).
	 *
	 * The regex's character class permits `-` and `/` for arithmetic, so multi-char
	 * comment sequences slip past whitelist alone — handle them with an explicit
	 * substring scan.
	 *
	 * @since 2.0.0
	 *
	 * @param string $expression SQL aggregate expression.
	 *
	 * @return bool
	 */
	private function is_safe_expression( string $expression ): bool {

		if ( $expression === '' ) {
			return false;
		}

		if ( strpos( $expression, '--' ) !== false || strpos( $expression, '/*' ) !== false ) {
			return false;
		}

		return preg_match( self::EXPRESSION_REGEX, $expression ) === 1;
	}
}
