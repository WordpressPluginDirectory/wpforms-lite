<?php

namespace WPForms\Integrations\AI\Admin\Chat\Scope\FormsInventory;

use WPForms\Integrations\AI\Admin\Chat\ScopeBase;

/**
 * Scope: forms inventory.
 *
 * @since 2.0.0
 */
class FormsInventory extends ScopeBase {

	/**
	 * Scope slug.
	 *
	 * @since 2.0.0
	 */
	public const SLUG = 'forms_inventory';

	/**
	 * Filter spec — field → type.
	 *
	 * The type determines which operators apply (see `get_ops_by_type()`).
	 * Subclasses extend the spec by overriding `get_field_spec()`.
	 *
	 * @since 2.0.0
	 */
	protected const FIELDS = [
		'id'       => 'int',
		'title'    => 'string',
		'status'   => 'enum',
		'created'  => 'date',
		'modified' => 'date',
		'author'   => 'string',
		'tags'     => 'array',
	];

	/**
	 * Lazy-built form searcher instance.
	 *
	 * @since 2.0.0
	 *
	 * @var FormSearcher|null
	 */
	private $searcher;

	/**
	 * Capability required to use this scope.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_capability(): string {

		return 'view_forms';
	}

	/**
	 * License tier required.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_requires(): string {

		return 'lite';
	}

	/**
	 * Include tokens this scope resolves on `request_data` rounds.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_data_sources(): array {

		return [
			'forms_list' => [ $this, 'fetch_forms_list' ],
		];
	}

	/**
	 * Build the forms inventory state contribution.
	 *
	 * @since 2.0.0
	 *
	 * @param array $surface     Active surface config.
	 * @param array $request     Sanitized request payload.
	 * @param array $accumulated Running state dict so far.
	 *
	 * @return array
	 */
	public function build_state( array $surface, array $request, array $accumulated ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		return $this->fetch_forms_refresh();
	}

	/**
	 * `request_data` resolver — returns either a filtered slice or the refresh snapshot.
	 *
	 * Reads `$payload['request_data']['args']`. Returns the round-1 snapshot only
	 * when neither filters nor an `order_by` ranking were requested; otherwise
	 * forwards the full args to `fetch_forms_search()` so a ranking with no
	 * filters (e.g. "top forms by entries") is honored.
	 *
	 * @since 2.0.0
	 *
	 * @param array $payload Request payload (with model's `request_data` frame injected by the AJAX handler).
	 *
	 * @return array
	 */
	public function fetch_forms_list( array $payload ): array {

		$args     = (array) ( $payload['request_data']['args'] ?? [] );
		$filters  = isset( $args['filters'] ) && is_array( $args['filters'] ) ? $args['filters'] : [];
		$order_by = (string) ( $args['order_by'] ?? '' );

		if ( $filters === [] && $order_by === '' ) {
			return $this->fetch_forms_refresh();
		}

		return $this->fetch_forms_search( $args );
	}

	/**
	 * Underlying impl — generic filtered query across the user's full forms inventory.
	 *
	 * Accepts a `filters` array where each entry is `{ field, op, value, since?, until? }`.
	 * Validates via `FilterCompiler`; surfaces rejections back to the LLM via the
	 * `rejected_filters` response key. When every filter is rejected, falls back to
	 * `fetch_forms_refresh()` so the model gets data plus a clear "all filters failed"
	 * signal.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Args from the model's `request_data.args` frame. Keys:
	 *                    `filters` (array of `{ field, op, value }`, optional),
	 *                    `limit` (int, optional, capped at `SEARCH_LIMIT`),
	 *                    `order_by` (string, optional — a rankable metric such as
	 *                    `entries_count`), `order` (`asc`/`desc`, default `desc`),
	 *                    `date_from` / `date_to` (ISO date window, optional).
	 *
	 * @return array
	 */
	public function fetch_forms_search( array $args ): array {

		$raw_filters = isset( $args['filters'] ) && is_array( $args['filters'] ) ? $args['filters'] : [];
		$form_search = $this->get_searcher();
		$result      = $form_search->get_compiler()->normalize( $raw_filters, $form_search->get_max_filters() );
		$limit       = (int) ( $args['limit'] ?? $form_search->get_search_limit() );
		$limit       = max( 1, min( $limit, $form_search->get_search_limit() ) );
		$order_by    = (string) ( $args['order_by'] ?? '' );

		// All-rejected fallback: keep the LLM unblocked with an unfiltered snapshot
		// plus the full rejection list so it can either retry with corrections or
		// answer with a caveat. Kept ahead of the ranking branch so a malformed
		// filter set still surfaces its rejections.
		if ( $result->kept === [] && $result->rejected !== [] ) {
			return $this->build_search_fallback_response( $result->rejected );
		}

		// Server-side ranking by a rankable metric (e.g. `entries_count`). A
		// ranking with no filters is valid, so this runs before the empty-kept
		// check below.
		if ( $form_search->supports_metric_ranking( $order_by ) ) {
			return $this->build_ranked_search_response( $result->kept, $result->rejected, $order_by, $limit, $args );
		}

		if ( $result->kept === [] ) {
			return $this->build_empty_search_response();
		}

		return $this->build_matched_search_response( $result->kept, $result->rejected, $limit );
	}

	/**
	 * Build the unfiltered-snapshot fallback returned when every filter was rejected.
	 *
	 * @since 2.0.0
	 *
	 * @param array $rejected Rejected-filter list surfaced back to the LLM.
	 *
	 * @return array
	 */
	private function build_search_fallback_response( array $rejected ): array {

		$refresh                     = $this->fetch_forms_refresh();
		$refresh['filters']          = [];
		$refresh['rejected_filters'] = $rejected;

		return $refresh;
	}

	/**
	 * Build the empty result returned when no filters were kept and none were rejected.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	private function build_empty_search_response(): array {

		return [
			'forms_summary' => [],
			'truncated'     => false,
			'filters'       => [],
			'total'         => 0,
		];
	}

	/**
	 * Build the matched-rows response for a kept filter set.
	 *
	 * @since 2.0.0
	 *
	 * @param array $kept     Normalized filters that passed validation.
	 * @param array $rejected Rejected filters (appended as `rejected_filters` when non-empty).
	 * @param int   $limit    Result cap (already clamped to `SEARCH_LIMIT`).
	 *
	 * @return array
	 */
	private function build_matched_search_response( array $kept, array $rejected, int $limit ): array {

		$form_ids = $this->get_searcher()->fetch_form_ids_by_filters( $kept, $limit + 1 );
		$total    = count( $form_ids );

		$truncated = $total > $limit;
		$form_ids  = array_slice( $form_ids, 0, $limit );

		// When the slice is truncated, `count( $form_ids )` reflects the cap
		// (`limit + 1`), not the true match count. Re-issue a count-only query
		// so the LLM sees the actual total matching the filter set.
		if ( $truncated ) {
			$total = $this->get_searcher()->count_form_ids_by_filters( $kept );
		}

		$rows = $this->build_rows( $form_ids );
		$rows = $this->enrich_rows( $rows );

		$response = [
			'forms_summary' => $rows,
			'truncated'     => $truncated,
			'filters'       => $kept,
			'total'         => $total,
		];

		if ( $rejected !== [] ) {
			$response['rejected_filters'] = $rejected;
		}

		return $response;
	}

	/**
	 * Build a metric-ranked response (e.g. "top N forms by entries").
	 *
	 * Ranks forms server-side by the requested metric (currently only
	 * `entries_count`) so an old, rarely-modified form with the most entries
	 * still ranks first — unlike the recency-capped snapshot. When filters are
	 * present, the ranking is restricted to the matching form IDs.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $kept     Normalized filters that passed validation (may be empty — ranking without filters is valid).
	 * @param array  $rejected Rejected filters (appended as `rejected_filters` when non-empty).
	 * @param string $order_by Rankable metric slug.
	 * @param int    $limit    Result cap (already clamped to `SEARCH_LIMIT`).
	 * @param array  $args     Raw request args — read for `order` and the optional `date_from` / `date_to` window.
	 *
	 * @return array
	 */
	private function build_ranked_search_response( array $kept, array $rejected, string $order_by, int $limit, array $args ): array {

		$searcher = $this->get_searcher();

		// Reached only when the searcher reports supports_metric_ranking() === true.
		// That is the Pro FormSearcher, which defines rank_form_ids_by_entries_count()
		// and count_forms_with_entries(); Lite returns false here so this branch never
		// runs in Lite (where those methods are absent).
		$order = ( $args['order'] ?? 'desc' ) === 'asc' ? 'asc' : 'desc';
		$since = (string) ( $args['date_from'] ?? '' );
		$until = (string) ( $args['date_to'] ?? '' );

		// When filters are present, rank only within the matching form IDs.
		$restrict = $kept === []
			? []
			: $searcher->fetch_form_ids_by_filters( $kept, $searcher->get_search_limit() + 1 );

		// An empty restrict set means two different things: no filters at all
		// (rank the whole portfolio) versus filters that matched nothing. The
		// downstream EntriesQuery treats an empty restrict as "no restriction",
		// so without this guard a zero-match filter would silently rank the
		// entire portfolio. Short-circuit to an empty ranking in that case.
		$filters_matched_nothing = $kept !== [] && $restrict === [];

		// Dispatch by metric. Only `entries_count` is rankable today; the guard
		// keeps future metrics a one-line addition without changing the shape.
		$ranked_ids = ( ! $filters_matched_nothing && $order_by === 'entries_count' )
			? $searcher->rank_form_ids_by_entries_count( $limit, $order, $restrict, $since, $until )
			: [];

		$total = $filters_matched_nothing
			? 0
			: $searcher->count_forms_with_entries( $restrict, $since, $until );

		$rows = $this->enrich_rows( $this->build_rows( $ranked_ids ) );

		$response = [
			'forms_summary' => $rows,
			'truncated'     => $total > count( $ranked_ids ),
			'order_by'      => $order_by,
			'order'         => $order,
			'total'         => $total,
		];

		if ( $rejected !== [] ) {
			$response['rejected_filters'] = $rejected;
		}

		return $response;
	}

	/**
	 * Filter spec accessor — `[ field => type ]`.
	 *
	 * Subclasses override to extend the spec with additional fields. Lite returns
	 * the static `FIELDS` constant; Pro adds `entries_count` and the four
	 * analytics metrics on top.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_field_spec(): array {

		return static::FIELDS;
	}

	/**
	 * Modified-DESC snapshot, capped at `FORMS_LIMIT`.
	 *
	 * @since 2.0.0
	 *
	 * @return array Result map with `forms_summary` and `truncated` keys.
	 */
	public function fetch_forms_refresh(): array {

		$form_search = $this->get_searcher();
		$form_ids    = $form_search->fetch_form_ids();

		$truncated = count( $form_ids ) > $form_search->get_forms_limit();
		$form_ids  = array_slice( $form_ids, 0, $form_search->get_forms_limit() );

		$rows = $this->build_rows( $form_ids );
		$rows = $this->enrich_rows( $rows );

		return [
			'forms_summary'   => $rows,
			'truncated'       => $truncated,
			'total_published' => (int) wp_count_posts( 'wpforms' )->publish,
		];
	}

	/**
	 * Extension point — subclasses enrich the form rows here.
	 *
	 * Lite returns the rows unchanged. Pro overrides to add `entries_count` and
	 * (when Analytics is enabled) an `analytics` sub-object.
	 *
	 * @since 2.0.0
	 *
	 * @param array $rows Form rows produced by `build_rows()`.
	 *
	 * @return array
	 */
	protected function enrich_rows( array $rows ): array {

		return $rows;
	}

	/**
	 * Build form rows from a list of form IDs.
	 *
	 * @since 2.0.0
	 *
	 * @param array $form_ids Form IDs (already capped + sliced by the caller).
	 *
	 * @return array
	 */
	private function build_rows( array $form_ids ): array {

		// Prime the term cache for the slice so `get_the_terms()` calls below are cache hits.
		if ( $form_ids ) {
			update_object_term_cache( $form_ids, 'wpforms' );
		}

		$rows = [];

		foreach ( $form_ids as $form_id ) {
			$post = get_post( (int) $form_id );

			if ( ! $post ) {
				continue;
			}

			$rows[] = $this->build_form_row( $post );
		}

		return $rows;
	}

	/**
	 * Default in-flight label for the forms inventory scope.
	 *
	 * @since 2.0.0
	 *
	 * @param array $includes Include tokens being resolved.
	 * @param array $args     Optional args from the request_data frame.
	 *
	 * @return string
	 */
	public function get_tool_call_default_label( array $includes, array $args ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		return __( 'Looking up your forms…', 'wpforms-lite' );
	}

	/**
	 * Build a single form row.
	 *
	 * @since 2.0.0
	 *
	 * @param object $post Form post.
	 *
	 * @return array
	 */
	private function build_form_row( $post ): array {

		$author = get_userdata( (int) $post->post_author );

		return [
			'id'        => (int) $post->ID,
			'title'     => (string) $post->post_title,
			'status'    => (string) $post->post_status,
			'created'   => (string) $post->post_date,
			'modified'  => (string) $post->post_modified,
			'author'    => $author ? (string) $author->display_name : '',
			'tags'      => $this->get_form_tag_names( (int) $post->ID ),
			'shortcode' => sprintf( '[wpforms id="%d"]', (int) $post->ID ),
		];
	}

	/**
	 * Get the form's tag names.
	 *
	 * @since 2.0.0
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array
	 */
	private function get_form_tag_names( int $form_id ): array {

		$terms = get_the_terms( $form_id, 'wpforms_form_tag' );

		if ( ! is_array( $terms ) ) {
			return [];
		}

		return array_values(
			array_map(
				static function ( $term ): string {

					return (string) $term->name;
				},
				$terms
			)
		);
	}

	/**
	 * Create the form searcher instance.
	 *
	 * Pro overrides to return Pro FormSearcher.
	 *
	 * @since 2.0.0
	 *
	 * @return FormSearcher
	 */
	protected function create_searcher(): FormSearcher {

		return new FormSearcher( [ $this, 'get_field_spec' ] );
	}

	/**
	 * Get the form searcher.
	 *
	 * @since 2.0.0
	 *
	 * @return FormSearcher
	 */
	protected function get_searcher(): FormSearcher {

		if ( $this->searcher !== null ) {
			return $this->searcher;
		}

		$this->searcher = $this->create_searcher();

		return $this->searcher;
	}
}
