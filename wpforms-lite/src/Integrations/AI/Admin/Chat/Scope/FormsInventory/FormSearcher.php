<?php

namespace WPForms\Integrations\AI\Admin\Chat\Scope\FormsInventory;

use WPForms\Integrations\AI\Admin\Chat\Filter\FieldSpec;
use WPForms\Integrations\AI\Admin\Chat\Filter\FieldType;
use WPForms\Integrations\AI\Admin\Chat\Filter\FilterCompiler;
use WPForms\Integrations\AI\Admin\Chat\Filter\Translators\PostsTranslationContext;
use WPForms\Integrations\AI\Admin\Chat\Filter\Translators\PostsTranslators;

/**
 * Domain helper that owns filter compilation, WP_Query execution, and count queries for the forms-inventory scope.
 *
 * @since 2.0.0
 */
class FormSearcher {

	/**
	 * Maximum number of forms exposed in the state payload.
	 *
	 * @since 2.0.0
	 */
	private const FORMS_LIMIT = 50;

	/**
	 * Maximum number of rows returned by a `forms_list` search.
	 *
	 * @since 2.0.0
	 */
	private const SEARCH_LIMIT = 200;

	/**
	 * Maximum number of filters accepted per `forms_list` request_data call.
	 *
	 * @since 2.0.0
	 */
	private const MAX_FILTERS = 10;

	/**
	 * Allowed values for the `status` enum field.
	 *
	 * @since 2.0.0
	 */
	private const STATUS_VALUES = [ 'publish', 'draft', 'trash', 'pending' ];

	/**
	 * Lazy-built filter compiler instance.
	 *
	 * @since 2.0.0
	 *
	 * @var FilterCompiler|null
	 */
	private $compiler;

	/**
	 * Lazy-built posts translators instance.
	 *
	 * @since 2.0.0
	 *
	 * @var PostsTranslators|null
	 */
	private $translators;

	/**
	 * Callback that returns the owning scope's field spec array.
	 *
	 * @since 2.0.0
	 *
	 * @var callable
	 */
	private $field_spec_callback;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param callable $field_spec_callback Callback returning `[ field => type ]` from the owning scope's `get_field_spec()`.
	 */
	public function __construct( callable $field_spec_callback ) {

		$this->field_spec_callback = $field_spec_callback;
	}

	/**
	 * Return the maximum number of filters allowed per request.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	public function get_max_filters(): int {

		return self::MAX_FILTERS;
	}

	/**
	 * Return the maximum number of rows returned by a search query.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	public function get_search_limit(): int {

		return self::SEARCH_LIMIT;
	}

	/**
	 * Return the maximum number of forms exposed in the state payload.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	public function get_forms_limit(): int {

		return self::FORMS_LIMIT;
	}

	/**
	 * Whether this searcher can rank forms server-side by the given metric.
	 *
	 * Lite owns no aggregate metric tables, so it supports no ranking. Pro
	 * overrides to advertise `entries_count` (and, in future, other metrics).
	 *
	 * @since 2.0.0
	 *
	 * @param string $field Metric field slug (e.g. `entries_count`).
	 *
	 * @return bool
	 */
	public function supports_metric_ranking( string $field ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		return false;
	}

	/**
	 * Rank form IDs by their entry count, descending or ascending.
	 *
	 * Lite owns no aggregate metric tables, so it cannot rank by entries and
	 * returns an empty list. Pro overrides this against `wp_wpforms_entries`.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $limit        Maximum number of ranked IDs to return.
	 * @param string $order        Sort direction — `asc` for ascending, anything else descending.
	 * @param array  $restrict_ids When non-empty, rank only within these form IDs.
	 * @param string $since        ISO date floor (optional).
	 * @param string $until        ISO date ceiling (optional).
	 *
	 * @return array Form IDs in ranked order.
	 */
	public function rank_form_ids_by_entries_count( int $limit, string $order, array $restrict_ids, string $since, string $until ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		return [];
	}

	/**
	 * Count the forms that have at least one entry (within window / restrict set).
	 *
	 * Lite owns no aggregate metric tables, so it cannot count and returns zero.
	 * Pro overrides this against `wp_wpforms_entries`.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $restrict_ids When non-empty, count only within these form IDs.
	 * @param string $since        ISO date floor (optional).
	 * @param string $until        ISO date ceiling (optional).
	 *
	 * @return int Number of forms having at least one entry.
	 */
	public function count_forms_with_entries( array $restrict_ids, string $since, string $until ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		return 0;
	}

	/**
	 * Fetch form IDs in modified-DESC order.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function fetch_form_ids(): array {

		$ids = wpforms()->obj( 'form' )->get(
			'',
			[
				'order'          => 'DESC',
				'orderby'        => 'modified',
				'posts_per_page' => self::FORMS_LIMIT + 1,
				'fields'         => 'ids',
			]
		);

		return is_array( $ids ) ? $ids : [];
	}

	/**
	 * Fetch form IDs matching the compiled filter set.
	 *
	 * Combines WP_Query args, `tax_query`, `date_query`, and ad-hoc
	 * `posts_where` clauses produced by the per-field translators. All filters
	 * combine with AND.
	 *
	 * @since 2.0.0
	 *
	 * @param array $filters Normalized filter list (`{ field, op, value }` entries).
	 * @param int   $limit   Maximum IDs to fetch (caller already added +1 for truncated detection).
	 *
	 * @return array
	 */
	public function fetch_form_ids_by_filters( array $filters, int $limit ): array { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$ctx = $this->compile_filters( $filters );

		// Title contains/not_contains and similar translators emit a closure on
		// `posts_where` instead of WP_Query args. Bind them around the get()
		// call — same scoped add/remove pattern as `WPForms\Admin\Forms\Search`.
		$installed = [];

		foreach ( $ctx->wheres as $cb ) {
			add_filter( 'posts_where', $cb, 10, 1 );

			$installed[] = $cb;
		}

		$args = array_merge(
			[
				'order'          => 'DESC',
				'orderby'        => 'modified',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				// Override `get_multiple()`'s `nopaging => true` default so the
				// LIMIT clause is actually emitted.
				'nopaging'       => false,
			],
			$ctx->to_query_args()
		);

		$ids = wpforms()->obj( 'form' )->get( '', $args );

		foreach ( $installed as $cb ) {
			remove_filter( 'posts_where', $cb );
		}

		return is_array( $ids ) ? $ids : [];
	}

	/**
	 * Count form IDs matching the compiled filter set (no result limit applied).
	 *
	 * Used by `fetch_forms_search()` to recover the true total when the limited
	 * slice is truncated; `count( $form_ids )` against a `LIMIT n+1` query only
	 * yields the cap, not the real match count. Mirrors `fetch_form_ids_by_filters()`
	 * but pulls the full id set (ids-only) through the same `Form_Handler::get()`
	 * wrapper so all WPForms-side query filters apply identically.
	 *
	 * @since 2.0.0
	 *
	 * @param array $filters Normalized filter list (`{ field, op, value }` entries).
	 *
	 * @return int
	 */
	public function count_form_ids_by_filters( array $filters ): int { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$ctx = $this->compile_filters( $filters );

		$installed = [];

		foreach ( $ctx->wheres as $cb ) {
			add_filter( 'posts_where', $cb, 10, 1 );

			$installed[] = $cb;
		}

		$args = array_merge(
			[
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'nopaging'       => true,
				'no_found_rows'  => true,
			],
			$ctx->to_query_args()
		);

		$ids = wpforms()->obj( 'form' )->get( '', $args );

		foreach ( $installed as $cb ) {
			remove_filter( 'posts_where', $cb );
		}

		return is_array( $ids ) ? count( $ids ) : 0;
	}

	/**
	 * Lazy-build the FilterCompiler for this scope.
	 *
	 * Composes the FieldSpec from the scope's `get_field_spec()` + `get_ops_by_type()`
	 * (Pro overrides apply) and wires `coerce_scalar()` as the compiler's coercion
	 * callback so Pro can override coercion via the existing protected method.
	 *
	 * @since 2.0.0
	 *
	 * @return FilterCompiler
	 */
	public function get_compiler(): FilterCompiler {

		if ( $this->compiler !== null ) {
			return $this->compiler;
		}

		// No coerce callback — FilterCompiler's default coercion handles every
		// FieldType this scope registers. If a future Pro subclass needs custom
		// coercion (e.g. a new field type beyond NUMERIC), the FilterCompiler
		// constructor still accepts an optional callable as the second arg.
		$this->compiler = new FilterCompiler( $this->build_field_spec() );

		return $this->compiler;
	}

	/**
	 * Translate a list of normalized filters into a PostsTranslationContext.
	 *
	 * Dispatches each known field to the appropriate translator method on the
	 * shared `PostsTranslators` instance; unknown fields delegate to
	 * `translate_extra_field()` (the Pro extension hook).
	 *
	 * @since 2.0.0
	 *
	 * @param array $filters Normalized filters.
	 *
	 * @return PostsTranslationContext
	 */
	private function compile_filters( array $filters ): PostsTranslationContext {

		$ctx = new PostsTranslationContext();
		$map = $this->get_field_translator_map( $this->get_translators() );

		foreach ( $filters as $filter ) {
			$field = (string) ( $filter['field'] ?? '' );

			if ( ! isset( $map[ $field ] ) ) {
				// Unknown core field — delegate to the Pro extension hook.
				// Preserve byte-identical by-ref signature.
				$this->translate_extra_field( $filter, $ctx->args, $ctx->wheres, $ctx->date_clauses, $ctx->tax_clauses );

				continue;
			}

			$map[ $field ]( $filter, $ctx );
		}

		return $ctx;
	}

	/**
	 * Build the core field → translator-callable map.
	 *
	 * Each callable receives the normalized filter and the shared translation
	 * context, then routes to the matching method on the `PostsTranslators`
	 * instance with the column / taxonomy / value-set baked in.
	 *
	 * @since 2.0.0
	 *
	 * @param PostsTranslators $translators Shared translators instance.
	 *
	 * @return array Field slug → callable( array $filter, PostsTranslationContext $ctx ): void.
	 */
	private function get_field_translator_map( PostsTranslators $translators ): array {

		return [
			'id'       => static function ( array $filter, PostsTranslationContext $ctx ) use ( $translators ): void {
				$translators->id( $filter, $ctx );
			},
			'title'    => static function ( array $filter, PostsTranslationContext $ctx ) use ( $translators ): void {
				$translators->string_column( 'post_title', $filter, $ctx );
			},
			'status'   => static function ( array $filter, PostsTranslationContext $ctx ) use ( $translators ): void {
				$translators->status( $filter, $ctx, self::STATUS_VALUES );
			},
			'created'  => static function ( array $filter, PostsTranslationContext $ctx ) use ( $translators ): void {
				$translators->date_column( 'post_date', $filter, $ctx );
			},
			'modified' => static function ( array $filter, PostsTranslationContext $ctx ) use ( $translators ): void {
				$translators->date_column( 'post_modified', $filter, $ctx );
			},
			'author'   => static function ( array $filter, PostsTranslationContext $ctx ) use ( $translators ): void {
				$translators->author( $filter, $ctx );
			},
			'tags'     => static function ( array $filter, PostsTranslationContext $ctx ) use ( $translators ): void {
				$translators->tags( $filter, $ctx, 'wpforms_form_tag' );
			},
		];
	}

	/**
	 * Extension point — translate a non-Lite filter into query contributions.
	 *
	 * The Lite class returns no extra fields, so this is a no-op. Pro overrides
	 * to translate Pro-only fields (`entries_count`, `analytics.*`). The full
	 * filter (including `since`/`until` date scope) is passed so the override
	 * can branch on the window.
	 *
	 * **Signature is intentionally stable.** Pro subclasses ship independently
	 * on wordpress.org; changing the parameter shape would break sites where
	 * Lite is updated before Pro on the same release cycle.
	 *
	 * @since 2.0.0
	 *
	 * @param array $filter       Normalized filter (`{ field, op, value, since, until }`).
	 * @param array $args         WP_Query args accumulator.
	 * @param array $wheres       Posts_where callable accumulator.
	 * @param array $date_clauses Date_query accumulator.
	 * @param array $tax_clauses  Tax_query accumulator.
	 */
	protected function translate_extra_field( array $filter, array &$args, array &$wheres, array &$date_clauses, array &$tax_clauses ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		// no-op — overridden by Pro.
	}

	/**
	 * Lazy-build the PostsTranslators helper.
	 *
	 * @since 2.0.0
	 *
	 * @return PostsTranslators
	 */
	private function get_translators(): PostsTranslators {

		if ( $this->translators !== null ) {
			return $this->translators;
		}

		$this->translators = new PostsTranslators();

		return $this->translators;
	}

	/**
	 * Compose a FieldSpec from the scope's per-field type map.
	 *
	 * Honors Pro extensions to `get_field_spec()` and `get_ops_by_type()` — those
	 * still return plain arrays (byte-identical signatures, see §3.8 of the
	 * filter compiler design); the composer translates them into the new shape.
	 *
	 * @since 2.0.0
	 *
	 * @return FieldSpec
	 */
	private function build_field_spec(): FieldSpec {

		$spec        = new FieldSpec();
		$ops_by_type = FieldType::default_ops_by_type();

		foreach ( ( $this->field_spec_callback )() as $field => $type ) {
			$ops             = $ops_by_type[ $type ] ?? [];
			$allowed_values  = $field === 'status' ? self::STATUS_VALUES : [];
			$supports_window = $type === FieldType::NUMERIC;

			$spec->add( (string) $field, (string) $type, $ops, $allowed_values, $supports_window );
		}

		return $spec;
	}
}
