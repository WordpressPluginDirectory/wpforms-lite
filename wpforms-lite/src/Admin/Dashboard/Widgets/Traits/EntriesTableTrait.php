<?php

namespace WPForms\Admin\Dashboard\Widgets\Traits;

/**
 * Forms-table row assembly for the Dashboard "Forms" widget: resolving the
 * cached superset against the gear selection, seating entry-less forms in the
 * slots the row cap leaves free, and restoring real entry counts.
 *
 * Not standalone. The using class must declare the `VIEWED_CANDIDATES` constant,
 * use `EntriesSettingsTrait` alongside this one, and provide three methods the
 * Pro subclass overrides or extends: `get_zero_filled_row()`,
 * `get_entry_counts()` and `get_range_dates()`. Those stay on the class so the
 * Pro overrides keep dispatching; this trait reaches them through `$this` while
 * they remain protected.
 *
 * @since 2.0.2
 */
trait EntriesTableTrait {

	/**
	 * Memoized display-ready table rows — needed several times per request, and
	 * every resolve may re-run the zero-fill enrichment queries.
	 *
	 * @since 2.0.2
	 *
	 * @var array|null
	 */
	private $table_rows;

	/**
	 * Resolve the display-ready table rows from the cached entries data.
	 *
	 * A form selection replaces the "Number of Forms" cap rather than competing with
	 * it: every selected form gets a row, sorted by its entries count, and a form
	 * missing from the cached superset (no entries in range) becomes a zero-filled
	 * row. The gear disables the cap while a selection exists, so the two settings
	 * never disagree about how many rows the table has. Without a selection the
	 * count-sorted superset is sliced to the cap (default 5).
	 *
	 * @since 2.0.2
	 *
	 * @param array $data Aggregated dashboard data (reads the `entries.forms` block).
	 *
	 * @return array List of resolved rows for the table template.
	 */
	protected function get_table_rows( array $data ): array {

		// Resolved once per request; every caller passes the same cached data.
		if ( $this->table_rows === null ) {
			$this->table_rows = $this->resolve_table_rows( $data );
		}

		return $this->table_rows;
	}

	/**
	 * Resolve the table rows (uncached). See `get_table_rows()` for the semantics.
	 *
	 * @since 2.0.2
	 *
	 * @param array $data Aggregated dashboard data (reads the `entries.forms` block).
	 *
	 * @return array List of resolved rows for the table template.
	 */
	private function resolve_table_rows( array $data ): array {

		$forms     = is_array( $data['entries']['forms'] ?? null ) ? $data['entries']['forms'] : [];
		$settings  = $this->get_resolved_settings();
		$selection = $settings['forms'];

		$chosen = $selection
			? $this->resolve_selected_rows( $selection, $forms, $data )
			: $this->resolve_top_rows( $forms, $data, $settings['count'] );

		return array_map( [ $this, 'resolve_row' ], $chosen );
	}

	/**
	 * Resolve the rows for the default top-N mode: the count-sorted superset, topped up to
	 * the row cap with forms that saw no entries in the range.
	 *
	 * @since 2.0.2
	 *
	 * @param array $forms     Cached superset of forms with entries in the range.
	 * @param array $data      Aggregated dashboard data.
	 * @param int   $row_count Row cap from the gear's "Number of Forms".
	 *
	 * @return array List of rows, still in the internal shape.
	 */
	private function resolve_top_rows( array $forms, array $data, int $row_count ): array {

		$chosen = array_values( $forms );

		// The superset is sourced from entries, so a form with no entries in range is
		// absent from it even when it has views. These rows need no entry count
		// restored: this branch only runs while the superset holds fewer rows than the
		// row cap, which is far below its ceiling, so the ceiling cannot be what
		// excluded them and their zero is real.
		$slots = $row_count - count( $chosen );

		if ( $slots > 0 ) {
			$chosen = array_merge( $chosen, $this->get_forms_without_entries( array_keys( $forms ), $data, $slots ) );
		}

		return array_slice( $chosen, 0, $row_count );
	}

	/**
	 * Resolve the rows for a hand-picked form selection, sorted by entries count.
	 *
	 * The selection is the row count: every checked form earns a row, so the ones the
	 * cached superset misses (no entries in the range) are built and counted here. The
	 * gear caps how many forms can be checked, which is what bounds this work.
	 *
	 * @since 2.0.2
	 *
	 * @param array $selection Selected form IDs.
	 * @param array $forms     Cached superset of forms with entries in the range.
	 * @param array $data      Aggregated dashboard data.
	 *
	 * @return array List of rows, still in the internal shape.
	 */
	private function resolve_selected_rows( array $selection, array $forms, array $data ): array {

		$chosen      = [];
		$missing_ids = [];

		foreach ( $selection as $form_id ) {
			if ( isset( $forms[ $form_id ] ) ) {
				$chosen[] = $forms[ $form_id ];
			} else {
				$missing_ids[] = $form_id;
			}
		}

		if ( $missing_ids ) {
			$rows   = $this->build_zero_filled_rows( $missing_ids, $data );
			$chosen = array_merge( $chosen, $this->apply_entry_counts( $rows, $data ) );
		}

		// After the merge, so the counted rows sort among themselves too.
		usort(
			$chosen,
			static function ( array $a, array $b ): int {

				return ( (int) ( $b['count'] ?? 0 ) ) <=> ( (int) ( $a['count'] ?? 0 ) );
			}
		);

		return $chosen;
	}

	/**
	 * Build display rows for the forms that have no entries in the range, filling
	 * every slot the row cap still allows.
	 *
	 * Candidates are the newest forms absent from the superset, capped at
	 * `VIEWED_CANDIDATES` so the ranking lookup never scales with the number of forms on
	 * the site; views then decide which of them earn the free slots first. Newest-first
	 * matters because the default form titles repeat ("Blank Form", "Simple Contact
	 * Form"), so an alphabetical window would keep seating stale duplicates ahead of the
	 * form the user just embedded.
	 *
	 * Slots the views ranking leaves free go to the newest forms with no activity at
	 * all: "Number of Forms" is a promise about how many forms the table lists, so a
	 * quiet range must still fill it rather than collapsing the table to the one form
	 * that happened to see traffic.
	 *
	 * @since 2.0.2
	 *
	 * @param array $counted_ids Form IDs already present in the cached superset.
	 * @param array $data        Aggregated dashboard data (reads the range bounds).
	 * @param int   $slots       Number of rows the cap still allows.
	 *
	 * @return array List of zero-filled, analytics-enriched rows.
	 */
	private function get_forms_without_entries( array $counted_ids, array $data, int $slots ): array {

		// Already resolved for the gear menu's form picker, so this adds no query here.
		$candidates = array_diff( array_keys( $this->get_form_choices() ), $counted_ids );
		$cache      = wpforms()->obj( 'dashboard_cache' );

		[ $start, $end ] = $this->get_range_dates( $data );

		if ( ! $candidates || ! $cache || ! $start || ! $end ) {
			return [];
		}

		// Newest first, then bound the window the ranking query has to cover.
		rsort( $candidates, SORT_NUMERIC );

		$form_ids = $cache->get_top_viewed_form_ids(
			array_slice( $candidates, 0, self::VIEWED_CANDIDATES ),
			$start,
			$end,
			$slots
		);

		// Ranking drops the forms with no views, so top up from the same newest-first
		// candidate list — no extra query, the titles are already primed per row.
		$form_ids = array_merge(
			$form_ids,
			array_slice( array_diff( $candidates, $form_ids ), 0, $slots - count( $form_ids ) )
		);

		return $form_ids ? $this->build_zero_filled_rows( $form_ids, $data ) : [];
	}

	/**
	 * Build display rows for gear-selected forms absent from the cached superset,
	 * priming the post cache in one query (each row resolves the form title).
	 *
	 * @since 2.0.2
	 *
	 * @param array $form_ids Form IDs to build rows for.
	 * @param array $data     Aggregated dashboard data (reads the range bounds).
	 *
	 * @return array List of zero-filled, analytics-enriched rows.
	 */
	private function build_zero_filled_rows( array $form_ids, array $data ): array {

		// One posts query for every title instead of one per form.
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $form_ids, false, false );
		}

		$rows = [];

		foreach ( $form_ids as $form_id ) {
			$rows[ $form_id ] = $this->get_zero_filled_row( $form_id );
		}

		return array_values( $this->enrich_zero_filled_rows( $rows, $data ) );
	}

	/**
	 * Restore real entry counts on rows the cached superset's ceiling left out.
	 *
	 * @since 2.0.2
	 *
	 * @param array $rows Zero-filled, analytics-enriched rows.
	 * @param array $data Aggregated dashboard data (reads the range bounds).
	 *
	 * @return array
	 */
	private function apply_entry_counts( array $rows, array $data ): array {

		if ( ! $rows ) {
			return [];
		}

		$counts = $this->get_entry_counts( wp_list_pluck( $rows, 'form_id' ), $data );

		if ( ! $counts ) {
			return $rows;
		}

		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['count'] = $counts[ (int) $row['form_id'] ] ?? 0;
		}

		return $rows;
	}

	/**
	 * Merge range-scoped analytics counters into zero-filled rows. The cache's
	 * compute-time enrichment never saw these forms — without this, a form with
	 * real views/interactions but no entries in range would render all-zero counters.
	 *
	 * @since 2.0.2
	 *
	 * @param array $rows Zero-filled rows keyed by form ID.
	 * @param array $data Aggregated dashboard data (reads the range bounds).
	 *
	 * @return array
	 */
	private function enrich_zero_filled_rows( array $rows, array $data ): array {

		if ( empty( $rows ) ) {
			return [];
		}

		$cache = wpforms()->obj( 'dashboard_cache' );

		[ $start, $end ] = $this->get_range_dates( $data );

		if ( ! $cache || ! $start || ! $end ) {
			return $rows;
		}

		return $cache->enrich_forms_with_analytics( $rows, $start, $end );
	}

	/**
	 * Shape a single cached form row into the display-ready row the table needs.
	 * Conversion is not stored — it is derived here, matching the Analytics
	 * rounding convention.
	 *
	 * @since 2.0.2
	 *
	 * @param array $row Cached `entries.forms` row.
	 *
	 * @return array
	 */
	private function resolve_row( array $row ): array {

		$form_id     = (int) ( $row['form_id'] ?? 0 );
		$views       = (int) ( $row['views'] ?? 0 );
		$submissions = (int) ( $row['submissions'] ?? 0 );

		return [
			'form_id'       => $form_id,
			'title'         => (string) ( $row['title'] ?? '' ),
			'edit_form_url' => add_query_arg(
				[
					'page'    => 'wpforms-builder',
					'view'    => 'fields',
					'form_id' => $form_id,
				],
				admin_url( 'admin.php' )
			),
			'entries'       => (int) ( $row['count'] ?? 0 ),
			// The cached `edit_url` is the form's entries page (Pro only); empty on Lite.
			'entries_url'   => (string) ( $row['edit_url'] ?? '' ),
			'views'         => $views,
			'interactions'  => (int) ( $row['interactions'] ?? 0 ),
			// Duplicates Pro\Db\Analytics\DB::safe_pct(), which is Pro-only and unreachable from this Lite base.
			'conversion'    => $views > 0 ? round( $submissions / $views * 100, 1 ) : 0.0,
		];
	}
}
