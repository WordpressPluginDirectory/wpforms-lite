<?php

namespace WPForms\Admin\Dashboard;

use DateTimeImmutable;
use WPForms\Admin\Helpers\Chart as ChartHelper;
use WPForms\Admin\Payments\Views\Overview\Chart;
use WPForms\Db\Analytics\DB as AnalyticsDB;
use WPForms\Db\Payments\StatsAggregator;
use WPForms\Helpers\Transient;
use WPForms\Lite\Reports\EntriesCount;

/**
 * Dashboard aggregate cache.
 *
 * @since 2.0.2
 */
class Cache {

	/**
	 * Cache TTL in seconds.
	 *
	 * @since 2.0.2
	 */
	const TTL = HOUR_IN_SECONDS;

	/**
	 * Transient key prefix. `Transient` auto-prefixes `_wpforms_transient_`.
	 *
	 * @since 2.0.2
	 */
	const KEY_PREFIX = 'dashboard_cache_';

	/**
	 * Preset date-range keys (days).
	 *
	 * @since 2.0.2
	 */
	const PRESET_RANGES = [ '0', '1', '7', '30', '90', '365' ];

	/**
	 * Option holding the cache generation, bumped on every invalidation.
	 *
	 * Deliberately not autoloaded so the freshness re-read in `compute()` can drop just this
	 * one entry from the object cache instead of the whole `alloptions` blob.
	 *
	 * @since 2.0.2
	 */
	const GENERATION_OPTION = 'wpforms_dashboard_cache_generation';

	/**
	 * Entries count reports instance.
	 *
	 * @since 2.0.2
	 *
	 * @var EntriesCount|null
	 */
	private $entries_count;

	/**
	 * Bootstrap the cache.
	 *
	 * @since 2.0.2
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.2
	 */
	private function hooks(): void {

		add_action( 'wpforms_process_entry_saved', [ $this, 'invalidate_all' ] );
		add_action( 'wpforms_process_payment_saved', [ $this, 'invalidate_all' ] );
		add_action( 'wpforms_create_form', [ $this, 'invalidate_all' ] );
		add_action( 'wpforms_save_form', [ $this, 'invalidate_all' ] );
		add_action( 'wpforms_form_handler_duplicate_form', [ $this, 'invalidate_all' ] );
		add_action( 'wpforms_form_handler_update_status', [ $this, 'invalidate_all' ] );
		add_action( 'wpforms_delete_form', [ $this, 'invalidate_all' ] );

		add_action( 'wpforms_create_form', [ StatCards::class, 'clear_configured_cache' ] );
		add_action( 'wpforms_save_form', [ StatCards::class, 'clear_configured_cache' ] );
		add_action( 'wpforms_form_handler_update_status', [ StatCards::class, 'clear_configured_cache' ] );
		add_action( 'wpforms_delete_form', [ StatCards::class, 'clear_configured_cache' ] );
	}

	/**
	 * Get the cached aggregates for a range, computing and caching them on a miss.
	 *
	 * Recomputes unconditionally when the request forces a refresh.
	 *
	 * @since 2.0.2
	 *
	 * @param string            $range_key Cache range key. @see make_key().
	 * @param DateTimeImmutable $start     Range start date.
	 * @param DateTimeImmutable $end       Range end date.
	 *
	 * @return array
	 */
	public function get_or_compute( string $range_key, DateTimeImmutable $start, DateTimeImmutable $end ): array {

		// A forced refresh skips the cached read and re-derives the range inline. The
		// card-configuration cache has its own TTL, so drop it too — otherwise a stale
		// card layout would survive the refresh.
		if ( Helpers::is_force_refresh() ) {
			StatCards::clear_configured_cache();

			return $this->compute( $range_key, $start, $end );
		}

		$cached = Transient::get( self::KEY_PREFIX . $range_key );

		// Compare the generation the blob was computed under, not just its presence. The
		// invalidation helpers can only delete the preset keys — a custom-range hash is
		// not enumerable — so a bare presence check would keep serving a custom range for
		// the rest of its TTL after an entry, payment, or form change bumped the
		// generation. Gating the read makes every key shape follow the same invalidation.
		if ( is_array( $cached ) && (int) ( $cached['generation'] ?? -1 ) === (int) get_option( self::GENERATION_OPTION, 0 ) ) {
			return $cached;
		}

		return $this->compute( $range_key, $start, $end );
	}

	/**
	 * Compute the aggregates for a range and cache the result.
	 *
	 * @since 2.0.2
	 *
	 * @param string            $range_key Cache range key. @see make_key().
	 * @param DateTimeImmutable $start     Range start date.
	 * @param DateTimeImmutable $end       Range end date.
	 *
	 * @return array
	 */
	public function compute( string $range_key, DateTimeImmutable $start, DateTimeImmutable $end ): array {

		// Read the generation before aggregating, which on a large site is slow enough for a
		// submission to land in the meantime. Publishing unconditionally would then write a
		// result that predates the invalidation and resurrect stale figures for a full TTL.
		$generation = (int) get_option( self::GENERATION_OPTION, 0 );

		$data                 = $this->get_aggregates( $start, $end );
		$data['generated_at'] = time();
		$data['range']        = $range_key;
		$data['generation']   = $generation;
		$data['range_start']  = $start->format( 'Y-m-d' );
		$data['range_end']    = $end->format( 'Y-m-d' );

		// Drop the cached copy first: an invalidation from a concurrent request updated the
		// row without touching this request's object cache, so a plain re-read would return
		// the value captured above and the check would never fire.
		wp_cache_delete( self::GENERATION_OPTION, 'options' );

		if ( (int) get_option( self::GENERATION_OPTION, 0 ) === $generation ) {
			Transient::set( self::KEY_PREFIX . $range_key, $data, self::TTL );
		}

		return $data;
	}

	/**
	 * Invalidate every cached range.
	 *
	 * The generation bump is what actually invalidates: `get_or_compute()` rejects any
	 * blob stamped with an older generation, so custom-range keys — which cannot be
	 * enumerated here — stop being served too. Deleting the preset keys only reclaims
	 * their rows early instead of waiting for the TTL.
	 *
	 * There is no per-range variant on purpose. A data change moves the figures of every
	 * range that contains today, and the hooks that call this fire on a front-end
	 * submission, where no "range the user is viewing" exists to narrow it down.
	 *
	 * @since 2.0.2
	 */
	public function invalidate(): void {

		// Bump first: a compute already running elsewhere then sees a changed generation and
		// declines to publish its now-outdated result.
		update_option( self::GENERATION_OPTION, (int) get_option( self::GENERATION_OPTION, 0 ) + 1, false );

		$suffix = self::get_key_suffix();

		foreach ( self::PRESET_RANGES as $preset ) {
			Transient::delete( self::KEY_PREFIX . $preset . $suffix );
		}
	}

	/**
	 * Invalidate all preset ranges. Hook callback — ignores the hook's arguments.
	 *
	 * @since 2.0.2
	 */
	public function invalidate_all(): void {

		$this->invalidate();
	}

	/**
	 * Invalidate every cached range. Can be called statically from background tasks.
	 *
	 * @since 2.0.2
	 */
	public static function invalidate_all_presets(): void {

		// Hooks are bound in init(), not the constructor, so instantiating here is side-effect free.
		( new self() )->invalidate();
	}

	/**
	 * Derive the cache key for a date range: the preset days key, or the custom range's bounds.
	 *
	 * @since 2.0.2
	 *
	 * @param int|string        $days  Timespan days key, or 'custom'.
	 * @param DateTimeImmutable $start Range start date.
	 * @param DateTimeImmutable $end   Range end date.
	 *
	 * @return string
	 */
	public static function make_key( $days, DateTimeImmutable $start, DateTimeImmutable $end ): string {

		$today     = date_create_immutable( 'now', wp_timezone() )->format( 'Y-m-d' );
		$is_preset = in_array( (string) $days, self::PRESET_RANGES, true ) && $end->format( 'Y-m-d' ) === $today;

		$suffix = self::get_key_suffix();

		if ( $is_preset ) {
			return $days . $suffix;
		}

		// A custom range is identified by its own bounds. Both are fixed-width and option-name
		// safe, so they go in verbatim rather than hashed: the resulting name is well inside the
		// option-name limit and stays greppable in `wp_options`.
		return 'custom_' . $start->format( 'Ymd' ) . '_' . $end->format( 'Ymd' ) . $suffix;
	}

	/**
	 * Build the key suffix encoding the state the aggregates are computed under.
	 *
	 * Payment tiles are aggregated in the site currency, and the entries domain is
	 * computed by the Lite or Pro cache class, so both belong in the key: otherwise a
	 * currency or Lite/Pro switch keeps serving the previous state's aggregates for the
	 * remainder of the TTL. The flavor has no change event to invalidate on — it is a
	 * filterable runtime state — so keying by it is what detects a switch. Blobs written
	 * under a superseded state become unreachable and lapse with their own expiry.
	 * `invalidate()` builds the same suffix, so it keeps matching the keys that are
	 * actually readable.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	private static function get_key_suffix(): string {

		return '_' . strtolower( wpforms_get_currency() ) . ( wpforms()->is_pro() ? '_pro' : '_lite' );
	}

	/**
	 * Get the aggregates for a range. Lite-safe values; the Pro override replaces the
	 * domains it can compute for real (entries, spam, locations).
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable $start Range start date.
	 * @param DateTimeImmutable $end   Range end date.
	 *
	 * @return array
	 */
	protected function get_aggregates( DateTimeImmutable $start, DateTimeImmutable $end ): array {

		$payments = $this->get_payments_aggregates( $start, $end );

		return [
			'stats'     => [
				'forms'            => $this->count_published_forms(),
				'total_entries'    => $this->get_entries_total( $start, $end ),
				'spam_entries'     => 0,
				'total_views'      => $this->get_total_views( $start, $end ),
				'total_payments'   => $this->get_payment_count( $payments['tiles']['total_payments'] ?? 0 ),
				'total_sales'      => (string) ( $payments['tiles']['total_sales'] ?? '0' ),
				'coupons_redeemed' => $this->get_payment_count( $payments['tiles']['coupons'] ?? 0 ),
				'total_refunded'   => $this->get_refunded_amount( $payments['tiles']['total_refunded'] ?? '0' ),
			],
			'entries'   => [
				'graph' => [],
				'forms' => [],
			],
			'payments'  => $payments,
			'locations' => [
				'countries'   => [],
				'donut_total' => 0,
			],
		];
	}

	/**
	 * Get the entries count reports instance, instantiating it once.
	 *
	 * @since 2.0.2
	 *
	 * @return EntriesCount
	 */
	private function get_entries_count(): EntriesCount {

		if ( $this->entries_count === null ) {
			$this->entries_count = new EntriesCount();
		}

		return $this->entries_count;
	}

	/**
	 * Get the total entries count across all forms. Lite: post-meta sum, no date scope.
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable $start Range start date. Unused on Lite.
	 * @param DateTimeImmutable $end   Range end date. Unused on Lite.
	 *
	 * @return int
	 */
	protected function get_entries_total( DateTimeImmutable $start, DateTimeImmutable $end ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		$total = 0;

		foreach ( $this->get_entries_count()->get_by_form() as $form ) {
			$total += (int) $form['count'];
		}

		return $total;
	}

	/**
	 * Count published forms.
	 *
	 * @since 2.0.2
	 *
	 * @return int
	 */
	private function count_published_forms(): int {

		return (int) wp_count_posts( 'wpforms' )->publish;
	}

	/**
	 * Get all published form IDs.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	protected function get_published_form_ids(): array {

		$forms = wpforms()->obj( 'form' )->get( '', [ 'fields' => 'ids' ] );

		return is_array( $forms ) ? array_map( 'absint', $forms ) : [];
	}

	/**
	 * Run a per-form analytics aggregate query over a date range.
	 *
	 * Shared by the total-views, per-form views and per-form interactions reads, which
	 * differ only in table and aggregate list. `$table` and `$select` are interpolated,
	 * so callers must pass trusted literals; the form IDs and dates are prepared.
	 *
	 * @since 2.0.2
	 *
	 * @param string            $table    Prefixed analytics table name.
	 * @param string            $select   Aggregate list following `form_id`, e.g. `SUM(views) AS views`.
	 * @param array             $form_ids Form IDs to aggregate over.
	 * @param DateTimeImmutable $start    Range start date.
	 * @param DateTimeImmutable $end      Range end date.
	 *
	 * @return array Rows carrying `form_id` plus the selected aggregates.
	 */
	protected function query_form_analytics( string $table, string $select, array $form_ids, DateTimeImmutable $start, DateTimeImmutable $end ): array {

		// The analytics tables are created by the Upgrade2_0_0 migration.
		if ( ! AnalyticsDB::tables_exist() ) {
			return [];
		}

		$form_ids = array_values( array_filter( array_map( 'absint', $form_ids ) ) );

		if ( ! $form_ids ) {
			return [];
		}

		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $form_ids ), '%d' ) );
		$values       = array_merge( $form_ids, [ $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) ] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT form_id, $select
				FROM $table
				WHERE form_id IN ( $placeholders ) AND period_date BETWEEN %s AND %s
				GROUP BY form_id",
				$values
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}

	/**
	 * Get the total cross-form page views for a range from the shared analytics table.
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable $start Range start date.
	 * @param DateTimeImmutable $end   Range end date.
	 *
	 * @return int
	 */
	private function get_total_views( DateTimeImmutable $start, DateTimeImmutable $end ): int {

		$form_ids = $this->get_published_form_ids();

		$rows = $this->query_form_analytics(
			AnalyticsDB::forms_table(),
			'SUM(views) AS views',
			$form_ids,
			$start,
			$end
		);

		$today_rows = $this->get_today_views_rows( $form_ids, $start, $end );

		return (int) array_sum( array_column( $rows, 'views' ) ) + (int) array_sum( array_column( $today_rows, 'views' ) );
	}

	/**
	 * Get today's live per-form views and submissions when the range covers today.
	 *
	 * The nightly aggregation never processes the current day, so a range that
	 * includes today must count its activity from the unprocessed snapshots —
	 * the same live layer the Analytics pages add. The two sources are disjoint
	 * by construction: aggregate rows only ever carry dates before today.
	 *
	 * @since 2.0.2
	 *
	 * @param array             $form_ids Form IDs to count.
	 * @param DateTimeImmutable $start    Range start date.
	 * @param DateTimeImmutable $end      Range end date.
	 *
	 * @return array Rows carrying form_id, views, submissions. Empty when the range excludes today.
	 */
	private function get_today_views_rows( array $form_ids, DateTimeImmutable $start, DateTimeImmutable $end ): array {

		if ( ! $this->range_includes_today( $start, $end ) || ! AnalyticsDB::tables_exist() ) {
			return [];
		}

		$db = wpforms()->obj( 'analytics_db' );

		return $db instanceof AnalyticsDB ? $db->get_today_stats( $form_ids ) : [];
	}

	/**
	 * Whether the inclusive [$start, $end] range covers today (site timezone).
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable $start Range start date.
	 * @param DateTimeImmutable $end   Range end date.
	 *
	 * @return bool
	 */
	protected function range_includes_today( DateTimeImmutable $start, DateTimeImmutable $end ): bool {

		$today = (string) current_time( 'Y-m-d' );

		return $start->format( 'Y-m-d' ) <= $today && $today <= $end->format( 'Y-m-d' );
	}

	/**
	 * Get per-form page views and submissions for a range from the shared analytics table.
	 *
	 * @since 2.0.2
	 *
	 * @param array             $form_ids Form IDs to fetch.
	 * @param DateTimeImmutable $start    Range start date.
	 * @param DateTimeImmutable $end      Range end date.
	 *
	 * @return array Map of form_id => [ 'views' => int, 'submissions' => int ].
	 */
	private function get_views_by_form( array $form_ids, DateTimeImmutable $start, DateTimeImmutable $end ): array {

		$rows = $this->query_form_analytics(
			AnalyticsDB::forms_table(),
			'SUM(views) AS views, SUM(submissions) AS submissions',
			$form_ids,
			$start,
			$end
		);

		$map = [];

		foreach ( $rows as $row ) {
			$map[ (int) $row['form_id'] ] = [
				'views'       => (int) $row['views'],
				'submissions' => (int) $row['submissions'],
			];
		}

		// Additive: a form seen only today materializes here with zero defaults.
		foreach ( $this->get_today_views_rows( $form_ids, $start, $end ) as $row ) {
			$form_id = (int) $row['form_id'];

			$map[ $form_id ]['views']       = ( $map[ $form_id ]['views'] ?? 0 ) + (int) $row['views'];
			$map[ $form_id ]['submissions'] = ( $map[ $form_id ]['submissions'] ?? 0 ) + (int) $row['submissions'];
		}

		return $map;
	}

	/**
	 * Rank the given forms by their page views over a range, returning the top IDs.
	 *
	 * Ranks on the analytics map alone, so the caller can hydrate posts for the returned
	 * IDs only — keeping its post-loading cost tied to the row cap rather than to the
	 * number of forms passed in. Forms with no views in the range are dropped: a row
	 * that would report nothing is not worth a slot.
	 *
	 * @since 2.0.2
	 *
	 * @param array             $form_ids Form IDs to rank.
	 * @param DateTimeImmutable $start    Range start date.
	 * @param DateTimeImmutable $end      Range end date.
	 * @param int               $limit    Maximum number of IDs to return.
	 *
	 * @return array List of form IDs, most-viewed first.
	 */
	public function get_top_viewed_form_ids( array $form_ids, DateTimeImmutable $start, DateTimeImmutable $end, int $limit ): array {

		if ( ! $form_ids || $limit <= 0 ) {
			return [];
		}

		$analytics = array_filter(
			$this->get_views_by_form( $form_ids, $start, $end ),
			static function ( array $row ): bool {

				return $row['views'] > 0;
			}
		);

		uasort(
			$analytics,
			static function ( array $a, array $b ): int {

				return $b['views'] <=> $a['views'];
			}
		);

		return array_slice( array_keys( $analytics ), 0, $limit );
	}

	/**
	 * Merge per-form analytics counters into the entries.forms rows.
	 *
	 * @since 2.0.2
	 *
	 * @param array             $forms Entries.forms rows keyed by form_id.
	 * @param DateTimeImmutable $start Range start date.
	 * @param DateTimeImmutable $end   Range end date.
	 *
	 * @return array
	 */
	public function enrich_forms_with_analytics( array $forms, DateTimeImmutable $start, DateTimeImmutable $end ): array {

		$analytics = $this->get_views_by_form( array_keys( $forms ), $start, $end );

		foreach ( array_keys( $forms ) as $form_id ) {
			$forms[ $form_id ]['views']       = $analytics[ $form_id ]['views'] ?? 0;
			$forms[ $form_id ]['submissions'] = $analytics[ $form_id ]['submissions'] ?? 0;
		}

		return $forms;
	}

	/**
	 * Get the payments aggregates. Gateway-gated.
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable $start Range start date.
	 * @param DateTimeImmutable $end   Range end date.
	 *
	 * @return array
	 */
	protected function get_payments_aggregates( DateTimeImmutable $start, DateTimeImmutable $end ): array {

		// No payment gateway connected.
		if ( ! $this->has_payment_gateway() ) {
			return $this->get_empty_payments();
		}

		// StatsAggregator may not be available on all branches.
		if ( ! class_exists( StatsAggregator::class ) ) {
			return $this->get_empty_payments();
		}

		$stats      = new PaymentStats();
		$aggregator = new StatsAggregator( Chart::stat_cards() );

		// Pinned to live mode like the tiles (see the mode rationale on `PaymentStats`).
		$graph_rows = $stats->with_live_mode(
			static function () use ( $aggregator, $start, $end ) {

				return $aggregator->get_payments_in_timespan( $start, $end, 'total_sales' );
			}
		);

		// Bucket the raw per-payment rows into a continuous daily series (converting
		// UTC to the site timezone), mirroring the Payments Overview chart endpoint.
		// Without this the graph plots one point per payment instead of one per day.
		list( , $graph ) = ChartHelper::process_chart_dataset_data( $graph_rows, $start, $end );

		return [
			'tiles'        => PaymentStats::fill_missing_tiles( $stats->get_tiles( $start, $end ) ),
			'graph'        => $graph,
			'graph_report' => 'total_sales',
		];
	}

	/**
	 * Whether a payment gateway has ever recorded a payment.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function has_payment_gateway(): bool {

		$payment = wpforms()->obj( 'payment' );

		if ( ! $payment ) {
			return false;
		}

		$payments = $payment->get_payments(
			[
				'mode'   => 'any',
				'number' => 1,
			]
		);

		return count( $payments ) > 0;
	}

	/**
	 * Get the empty payments block (no payment gateway connected).
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_empty_payments(): array {

		return [
			// `PaymentStats` owns the zero-tile shape; an empty set resolves to all-zero tiles.
			'tiles' => PaymentStats::fill_missing_tiles( [] ),
			'graph' => [],
		];
	}

	/**
	 * Parse a payment-count tile value into an int, stripping comma separators.
	 *
	 * @since 2.0.2
	 *
	 * @param int|string $tile Raw payment-count tile value.
	 *
	 * @return int
	 */
	private function get_payment_count( $tile ): int {

		return (int) str_replace( ',', '', (string) $tile );
	}

	/**
	 * Extract the refunded amount from a `total_refunded` payment tile value.
	 *
	 * Handles both the scalar string and array shapes returned by `StatsAggregator` and the empty fallback.
	 *
	 * @since 2.0.2
	 *
	 * @param array|string $tile Raw `total_refunded` tile value.
	 *
	 * @return string
	 */
	private function get_refunded_amount( $tile ): string {

		if ( is_array( $tile ) ) {
			return (string) ( $tile['amount'] ?? '0' );
		}

		$amount = explode( ' ', (string) $tile )[0];

		return $amount === '' ? '0' : $amount;
	}
}
