<?php

namespace WPForms\Integrations\AI\Admin\Chat\Filter\Translators;

/**
 * Accumulator object passed through PostsTranslators while compiling filters.
 *
 * Each translator method appends to one of the four public accumulators. The
 * scope's `compile_filters()` then folds the date and tax accumulators into
 * the WP_Query args via `to_query_args()` before passing to `WP_Query`.
 *
 * Public properties intentionally — they're passed by reference into the
 * existing `translate_extra_field()` Pro extension hook to preserve LSP
 * compatibility across independent Lite/Pro release cycles.
 *
 * @since 2.0.0
 */
class PostsTranslationContext {

	/**
	 * WP_Query args accumulator (post__in, post__not_in, post_status, author__in, …).
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public $args = [];

	/**
	 * `posts_where` filter callables to install around the WP_Query call.
	 *
	 * @since 2.0.0
	 *
	 * @var callable[]
	 */
	public $wheres = [];

	/**
	 * `date_query` clauses accumulator.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public $date_clauses = [];

	/**
	 * `tax_query` clauses accumulator.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public $tax_clauses = [];

	/**
	 * Fold the date/tax accumulators into the final WP_Query args.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function to_query_args(): array {

		$query_args = $this->args;

		if ( $this->date_clauses !== [] ) {
			$query_args['date_query'] = $this->date_clauses;
		}

		if ( $this->tax_clauses !== [] ) {
			$query_args['tax_query'] = count( $this->tax_clauses ) === 1 // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				? $this->tax_clauses
				: array_merge( [ 'relation' => 'AND' ], $this->tax_clauses );
		}

		return $query_args;
	}
}
