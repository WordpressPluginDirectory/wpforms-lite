<?php

namespace WPForms\Integrations\AI\Admin\Chat\Filter\Translators;

use WPForms\Integrations\AI\Admin\Chat\Filter\Operators;

/**
 * Translate NormalizedFilter entries into WP_Query args + posts_where callables.
 *
 * Used by scopes that target the `wpforms` post type via `WP_Query` — today
 * `Scope\FormsInventory\FormsInventory` (Lite + Pro). All methods are instance methods on
 * an injectable class so they're mockable in unit tests.
 *
 * @since 2.0.0
 */
class PostsTranslators {

	/**
	 * Time suffix marking the inclusive start of a day in `date_query` bounds.
	 *
	 * @since 2.0.0
	 */
	private const DAY_START = ' 00:00:00';

	/**
	 * Time suffix marking the inclusive end of a day in `date_query` bounds.
	 *
	 * @since 2.0.0
	 */
	private const DAY_END = ' 23:59:59';

	/**
	 * Translate an `id` filter into `post__in` / `post__not_in` WP_Query args.
	 *
	 * @since 2.0.0
	 *
	 * @param array                   $filter Normalized filter entry.
	 * @param PostsTranslationContext $ctx    Accumulator context.
	 */
	public function id( array $filter, PostsTranslationContext $ctx ): void {

		[ $op, $value ] = Operators::read_op_value( $filter );

		$values = is_array( $value ) ? array_map( 'intval', $value ) : [ (int) $value ];
		$values = array_values( array_filter( $values ) );

		if ( $values === [] ) {
			return;
		}

		if ( $op === 'neq' ) {
			// post__not_in is the only way to express `id neq` against the form CPT, a small bounded set.
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			$this->append_arg_ids( $ctx, 'post__not_in', $values );

			return;
		}

		$this->append_arg_ids( $ctx, 'post__in', $values );
	}

	/**
	 * Translate a string-column filter into a `posts_where` callable.
	 *
	 * Used for `title` (post_title). The callable narrows the WHERE clause
	 * directly — WP_Query has no first-class operator for "title equals X" or
	 * "title contains X" against post_title alone (`s` matches title + content
	 * + excerpt; not what we want).
	 *
	 * @since 2.0.0
	 *
	 * @param string                  $column Post column name (e.g. `post_title`).
	 * @param array                   $filter Normalized filter entry.
	 * @param PostsTranslationContext $ctx    Accumulator context.
	 */
	public function string_column( string $column, array $filter, PostsTranslationContext $ctx ): void {

		$column = sanitize_key( $column );

		if ( $column === '' ) {
			return;
		}

		[ $op, $value ] = Operators::read_op_value( $filter );

		$where_clause = $this->build_string_where( $op, $column, (string) $value );

		if ( $where_clause === null ) {
			return;
		}

		$ctx->wheres[] = $where_clause;
	}

	/**
	 * Build the `posts_where` callable for a string-column filter op.
	 *
	 * Comparison ops (`eq`, `neq`, `contains`, `not_contains`) bind a value via
	 * `$wpdb->prepare()`; the empty ops (`empty`, `not_empty`) emit a static
	 * NULL/blank test. Returns null for an unsupported op so the caller skips it.
	 *
	 * @since 2.0.0
	 *
	 * @param string $op     Operator slug.
	 * @param string $column Sanitized post column name.
	 * @param string $str    String value (already cast).
	 *
	 * @return callable|null `posts_where` filter callable, or null when the op is unsupported.
	 */
	private function build_string_where( string $op, string $column, string $str ): ?callable {

		global $wpdb;

		$posts_table = $wpdb->posts;

		// Comparison ops differ only by SQL operator and whether the bound value
		// is the raw string (`eq`/`neq`) or a `%…%` LIKE pattern (`contains`).
		$comparisons = [
			'eq'           => '=',
			'neq'          => '!=',
			'contains'     => 'LIKE',
			'not_contains' => 'NOT LIKE',
		];

		if ( isset( $comparisons[ $op ] ) ) {
			$sql_op = $comparisons[ $op ];
			$bind   = in_array( $op, [ 'contains', 'not_contains' ], true ) ? '%' . $wpdb->esc_like( $str ) . '%' : $str;

			return static function ( $where ) use ( $wpdb, $posts_table, $column, $sql_op, $bind ): string {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return $where . $wpdb->prepare( " AND {$posts_table}.{$column} {$sql_op} %s", $bind );
			};
		}

		if ( $op === 'empty' ) {
			return static function ( $where ) use ( $posts_table, $column ): string {
				return $where . " AND ( {$posts_table}.{$column} = '' OR {$posts_table}.{$column} IS NULL )";
			};
		}

		if ( $op === 'not_empty' ) {
			return static function ( $where ) use ( $posts_table, $column ): string {
				// Strict inverse of `empty` ( = '' OR IS NULL ): a column is "not empty"
				// only when it is both non-blank AND non-NULL. `column != ''` alone is
				// not enough — `NULL != ''` is NULL (not TRUE) in SQL, which would drop
				// NULL rows. Harmless on post_title (NOT NULL) but correct for any
				// nullable column this is later pointed at.
				return $where . " AND ( {$posts_table}.{$column} != '' AND {$posts_table}.{$column} IS NOT NULL )";
			};
		}

		return null;
	}

	/**
	 * Translate a `status` filter into the WP_Query `post_status` arg.
	 *
	 * @since 2.0.0
	 *
	 * @param array                   $filter         Normalized filter entry.
	 * @param PostsTranslationContext $ctx            Accumulator context.
	 * @param array                   $allowed_values The full enum value set (used for `neq` complement).
	 */
	public function status( array $filter, PostsTranslationContext $ctx, array $allowed_values ): void {

		[ $op, $value ] = Operators::read_op_value( $filter );

		if ( $op === 'in' ) {
			$this->append_arg_ids( $ctx, 'post_status', (array) $value, true );

			return;
		}

		if ( $op === 'eq' ) {
			$this->append_arg_ids( $ctx, 'post_status', [ (string) $value ], true );

			return;
		}

		if ( $op === 'neq' ) {
			$ctx->args['post_status'] = array_values( array_diff( $allowed_values, [ (string) $value ] ) );
		}
	}

	/**
	 * Append a clause to the running `date_query` accumulator.
	 *
	 * @since 2.0.0
	 *
	 * @param string                  $column WP_Query date_query column (e.g. `post_date`, `post_modified`).
	 * @param array                   $filter Normalized filter entry.
	 * @param PostsTranslationContext $ctx    Accumulator context.
	 */
	public function date_column( string $column, array $filter, PostsTranslationContext $ctx ): void {

		$column = sanitize_key( $column );

		if ( $column === '' ) {
			return;
		}

		$op  = (string) ( $filter['op'] ?? '' );
		$iso = (string) ( $filter['value'] ?? '' );

		if ( $iso === '' ) {
			return;
		}

		$bounds = $this->build_date_clause( $op, $iso );

		// Unknown ops are filtered upstream by FilterCompiler — defensive guard.
		if ( $bounds === [] ) {
			return;
		}

		$ctx->date_clauses[] = array_merge(
			[
				'column'    => $column,
				'inclusive' => in_array( $op, [ 'eq', 'lte', 'gte' ], true ),
			],
			$bounds
		);
	}

	/**
	 * Build the `after`/`before` bounds for a date clause from an operator + ISO date.
	 *
	 * Returns an empty array for unsupported operators so the caller can skip the
	 * clause (defensive — unknown ops are filtered upstream by FilterCompiler).
	 *
	 * @since 2.0.0
	 *
	 * @param string $op  Operator slug.
	 * @param string $iso ISO `YYYY-MM-DD` date string.
	 *
	 * @return array
	 */
	private function build_date_clause( string $op, string $iso ): array {

		$start = $iso . self::DAY_START;
		$end   = $iso . self::DAY_END;

		// Per-op `after`/`before` bounds. `eq` spans the whole day; the range ops
		// pick the day edge that makes the comparison inclusive or exclusive.
		$clauses = [
			'eq'  => [
				'after'  => $start,
				'before' => $end,
			],
			'lt'  => [ 'before' => $start ],
			'lte' => [ 'before' => $end ],
			'gt'  => [ 'after' => $end ],
			'gte' => [ 'after' => $start ],
		];

		return $clauses[ $op ] ?? [];
	}

	/**
	 * Translate an `author` filter into WP_Query author args + posts_where callables.
	 *
	 * For `eq`/`neq` we resolve the author by login or display_name and use
	 * `author__in` / `author__not_in`. For `contains`/`not_contains` we add a
	 * `posts_where` that subselects users whose login or display_name matches.
	 *
	 * @since 2.0.0
	 *
	 * @param array                   $filter Normalized filter entry.
	 * @param PostsTranslationContext $ctx    Accumulator context.
	 */
	public function author( array $filter, PostsTranslationContext $ctx ): void {

		[ $op, $value ] = Operators::read_op_value( $filter );

		$str = trim( (string) $value );

		if ( $str === '' ) {
			return;
		}

		if ( in_array( $op, [ 'eq', 'neq' ], true ) ) {
			$this->apply_author_exact( $op, $str, $ctx );

			return;
		}

		$this->apply_author_contains( $op, $str, $ctx );
	}

	/**
	 * Apply an exact-match author filter (`eq` / `neq`) via author__in / author__not_in.
	 *
	 * Resolves the search string to user IDs. When no user matches, forces an empty
	 * result set by injecting a sentinel `post__in` of `0` rather than dropping the filter.
	 *
	 * @since 2.0.0
	 *
	 * @param string                  $op  Operator slug (`eq` or `neq`).
	 * @param string                  $str Trimmed author search string.
	 * @param PostsTranslationContext $ctx Accumulator context.
	 */
	private function apply_author_exact( string $op, string $str, PostsTranslationContext $ctx ): void {

		$user_ids = $this->resolve_author_ids_exact( $str );

		if ( $user_ids === [] ) {
			// No matches: force an empty result set rather than ignoring the filter.
			$this->append_arg_ids( $ctx, 'post__in', [ 0 ] );

			return;
		}

		$key = $op === 'eq' ? 'author__in' : 'author__not_in';

		$this->append_arg_ids( $ctx, $key, $user_ids, true );
	}

	/**
	 * Apply a substring author filter (`contains` / `not_contains`) via a posts_where subselect.
	 *
	 * @since 2.0.0
	 *
	 * @param string                  $op  Operator slug (`contains` or `not_contains`).
	 * @param string                  $str Trimmed author search string.
	 * @param PostsTranslationContext $ctx Accumulator context.
	 */
	private function apply_author_contains( string $op, string $str, PostsTranslationContext $ctx ): void {

		global $wpdb;

		$like   = '%' . $wpdb->esc_like( $str ) . '%';
		$negate = $op === 'not_contains' ? 'NOT' : '';

		$ctx->wheres[] = static function ( $where ) use ( $wpdb, $like, $negate ): string {

			$sub = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->users} WHERE user_login LIKE %s OR display_name LIKE %s",
				$like,
				$like
			);

			return $where . " AND {$wpdb->posts}.post_author {$negate} IN ( {$sub} )";
		};
	}

	/**
	 * Translate a `tags` filter into a `tax_query` clause.
	 *
	 * @since 2.0.0
	 *
	 * @param array                   $filter   Normalized filter entry.
	 * @param PostsTranslationContext $ctx      Accumulator context.
	 * @param string                  $taxonomy Taxonomy slug (e.g. `wpforms_form_tag`).
	 */
	public function tags( array $filter, PostsTranslationContext $ctx, string $taxonomy ): void {

		$taxonomy = sanitize_key( $taxonomy );

		if ( $taxonomy === '' ) {
			return;
		}

		[ $op, $value ] = Operators::read_op_value( $filter );

		$existence_clause = $this->build_tax_existence_clause( $op, $taxonomy );

		if ( $existence_clause !== null ) {
			$ctx->tax_clauses[] = $existence_clause;

			return;
		}

		$terms = is_array( $value ) ? array_filter( array_map( 'strval', $value ) ) : [ (string) $value ];
		$terms = array_filter(
			$terms,
			static function ( $t ): bool {

				return $t !== '';
			}
		);
		$terms = array_values( $terms );

		if ( $terms === [] ) {
			return;
		}

		$operator = $op === 'not_contains' ? 'NOT IN' : 'IN';

		$ctx->tax_clauses[] = [
			'taxonomy' => $taxonomy,
			'field'    => 'name',
			'terms'    => $terms,
			'operator' => $operator,
		];
	}

	/**
	 * Build the existence-based `tax_query` clause for the `empty` / `not_empty` ops.
	 *
	 * @since 2.0.0
	 *
	 * @param string $op       Operator slug.
	 * @param string $taxonomy Sanitized taxonomy slug.
	 *
	 * @return array|null Tax clause, or null when the op is not an existence check.
	 */
	private function build_tax_existence_clause( string $op, string $taxonomy ): ?array {

		$operators = [
			'empty'     => 'NOT EXISTS',
			'not_empty' => 'EXISTS',
		];

		if ( ! isset( $operators[ $op ] ) ) {
			return null;
		}

		return [
			'taxonomy' => $taxonomy,
			'operator' => $operators[ $op ],
		];
	}

	/**
	 * Resolve an `author eq/neq X` value to user IDs.
	 *
	 * Matches against `user_login` (exact) first, falls back to `display_name`
	 * (exact). Returns an array — multiple users can share a display name.
	 *
	 * @since 2.0.0
	 *
	 * @param string $value Author search string.
	 *
	 * @return array
	 */
	private function resolve_author_ids_exact( string $value ): array {

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->users WHERE user_login = %s OR display_name = %s",
				$value,
				$value
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}

	/**
	 * Merge a list of IDs into an existing WP_Query args accumulator key.
	 *
	 * @since 2.0.0
	 *
	 * @param PostsTranslationContext $ctx    Accumulator context.
	 * @param string                  $key    Args key (e.g. `post__in`, `author__not_in`).
	 * @param array                   $ids    IDs to merge into the current value.
	 * @param bool                    $unique Whether to de-duplicate the merged list.
	 */
	private function append_arg_ids( PostsTranslationContext $ctx, string $key, array $ids, bool $unique = false ): void {

		$merged = array_merge( (array) ( $ctx->args[ $key ] ?? [] ), $ids );

		$ctx->args[ $key ] = $unique ? array_values( array_unique( $merged ) ) : $merged;
	}
}
