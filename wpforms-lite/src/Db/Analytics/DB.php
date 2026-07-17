<?php

namespace WPForms\Db\Analytics;

use DateTimeImmutable;
use RuntimeException;
use WPForms\Helpers\DB as HelpersDB;

/**
 * Analytics DB operations.
 *
 * Writes raw snapshot headers and serves as the Lite query base for the
 * Forms Overview columns. Extended by WPForms\Pro\Db\Analytics\DB which adds
 * field-level writes and all Pro read queries.
 *
 * @since 2.0.0
 */
class DB {

	/**
	 * Per-request cache for the tables_exist() check.
	 *
	 * @since 2.0.0
	 *
	 * @var bool|null
	 */
	private static $tables_exist;

	/**
	 * Magic period_date value marking the lifetime sentinel row.
	 *
	 * Used in the analytics_forms and analytics_fields aggregate tables.
	 * Real date far in the past (year 1000) so it's invisible to all realistic
	 * date-range queries while remaining compatible with MySQL 5.7+ default
	 * sql_mode (which rejects '0000-00-00').
	 *
	 * @since 2.0.0
	 */
	public const LIFETIME_SENTINEL_DATE = '1000-01-01';

	/**
	 * Get the full wp_wpforms_analytics_snapshots table name.
	 *
	 * @since 2.0.0
	 *
	 * @return string Prefixed table name.
	 */
	public static function snapshots_table(): string {

		global $wpdb;

		return $wpdb->prefix . 'wpforms_analytics_snapshots';
	}

	/**
	 * Get the full wp_wpforms_analytics_forms table name.
	 *
	 * @since 2.0.0
	 *
	 * @return string Prefixed table name.
	 */
	public static function forms_table(): string {

		global $wpdb;

		return $wpdb->prefix . 'wpforms_analytics_forms';
	}

	/**
	 * Whether all analytics tables required by the current tier exist.
	 *
	 * Checks the two Lite tables unconditionally, plus the two Pro-only
	 * tables when Pro is active. Cached per request.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function tables_exist(): bool {

		if ( self::$tables_exist !== null ) {
			return self::$tables_exist;
		}

		global $wpdb;

		if ( ! $wpdb ) {
			return false;
		}

		$exists = HelpersDB::table_exists( self::snapshots_table() )
			&& HelpersDB::table_exists( self::forms_table() );

		if ( $exists && wpforms()->is_pro() ) {
			$exists = HelpersDB::table_exists( $wpdb->prefix . 'wpforms_analytics_snapshot_fields' )
				&& HelpersDB::table_exists( $wpdb->prefix . 'wpforms_analytics_fields' );
		}

		self::$tables_exist = $exists;

		return self::$tables_exist;
	}

	/**
	 * Insert a snapshot header into wp_wpforms_analytics_snapshots.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Snapshot header data. Required keys: form_id, session_id,
	 *                    trigger_type, form_visible, payload, occurred_at.
	 *                    Optional: page_number, processed (defaults 0).
	 *
	 * @return int|false Inserted row ID on success, false on failure.
	 */
	public function save( array $data ) {

		global $wpdb;

		$defaults = [
			'page_number' => null,
			'processed'   => 0,
		];

		$row = array_merge( $defaults, $data );

		$formats = [
			'%d', // form_id.
			'%s', // session_id.
			'%d', // trigger_type.
			'%d', // page_number (nullable — wpdb treats as int).
			'%d', // form_visible.
			'%s', // payload.
			'%s', // occurred_at.
			'%d', // processed.
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			self::snapshots_table(),
			[
				'form_id'      => (int) $row['form_id'],
				'session_id'   => (string) $row['session_id'],
				'trigger_type' => (int) $row['trigger_type'],
				'page_number'  => isset( $row['page_number'] ) ? (int) $row['page_number'] : null,
				'form_visible' => (int) $row['form_visible'],
				'payload'      => (string) $row['payload'],
				'occurred_at'  => (string) $row['occurred_at'],
				'processed'    => (int) $row['processed'],
			],
			$formats
		);

		if ( $result === false ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Upsert a daily form-level aggregate row.
	 *
	 * Uses INSERT ... ON DUPLICATE KEY UPDATE so repeat calls accumulate
	 * delta values. Called by the nightly aggregation task.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $form_id     Form ID.
	 * @param string $period_date Aggregation date (Y-m-d).
	 * @param array  $deltas      Keys: views, unique_sessions, submissions. Missing keys default to 0.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the upsert fails, so the surrounding aggregation transaction rolls back.
	 */
	public function upsert_form_aggregate( int $form_id, string $period_date, array $deltas ): void {

		global $wpdb;

		$table = self::forms_table();

		$views           = (int) ( $deltas['views'] ?? 0 );
		$unique_sessions = (int) ( $deltas['unique_sessions'] ?? 0 );
		$submissions     = (int) ( $deltas['submissions'] ?? 0 );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (form_id, period_date, views, unique_sessions, submissions)
				 VALUES (%d, %s, %d, %d, %d)
				 ON DUPLICATE KEY UPDATE
				     views           = views           + VALUES(views),
				     unique_sessions = unique_sessions + VALUES(unique_sessions),
				     submissions     = submissions     + VALUES(submissions)",
				$form_id,
				$period_date,
				$views,
				$unique_sessions,
				$submissions
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $result === false ) {
			wpforms_log(
				'Analytics DB: upsert_form_aggregate failed',
				[
					'table'       => $table,
					'form_id'     => $form_id,
					'period_date' => $period_date,
					'last_error'  => $wpdb->last_error,
				],
				[
					'type'  => [ 'error' ],
					'force' => true,
				]
			);

			// Abort the aggregation transaction: committing the marked-processed
			// snapshots while this additive delta was dropped would lose the delta
			// permanently. Throwing routes through aggregate_in_transaction()'s
			// catch, which rolls back so the snapshots are retried next run.
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message routed to the error log, not HTML output.
			throw new RuntimeException( 'Analytics aggregation: upsert_form_aggregate failed. ' . $wpdb->last_error );
		}
	}

	/**
	 * Upsert the lifetime sentinel row for a form.
	 *
	 * Convenience wrapper — period_date is always LIFETIME_SENTINEL_DATE.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $form_id Form ID.
	 * @param array $deltas  Delta values (see upsert_form_aggregate).
	 *
	 * @return void
	 */
	public function upsert_form_sentinel( int $form_id, array $deltas ): void {

		$this->upsert_form_aggregate( $form_id, self::LIFETIME_SENTINEL_DATE, $deltas );
	}

	/**
	 * Get lifetime overview stats for the given forms.
	 *
	 * Hybrid query: merges the lifetime sentinel row (fast PK lookup) with
	 * today's unprocessed snapshots (live count since the last nightly
	 * aggregation). Returns correct numbers on brand-new installs too.
	 *
	 * @since 2.0.0
	 *
	 * @param array $form_ids Form IDs to fetch stats for.
	 *
	 * @return array Map of form_id => [ 'views' => int, 'submissions' => int ].
	 */
	public function get_overview_stats( array $form_ids ): array {

		if ( empty( $form_ids ) ) {
			return [];
		}

		global $wpdb;

		$form_ids = array_values( array_filter( array_map( 'absint', $form_ids ) ) );

		if ( empty( $form_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
		$forms_table  = self::forms_table();
		$snaps_table  = self::snapshots_table();

		[ $today_start, $tomorrow_start ] = $this->today_boundaries();

		// Layer 1: lifetime sentinel rows (PK lookup).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sentinel_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT form_id, views, submissions
				 FROM {$forms_table}
				 WHERE form_id IN ({$placeholders})
				   AND period_date = %s",
				array_merge( $form_ids, [ self::LIFETIME_SENTINEL_DATE ] )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Layer 2: today's unprocessed snapshots (index-friendly range filter).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$today_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT form_id,
				        COUNT(DISTINCT session_id)  AS views,
				        COUNT(DISTINCT CASE WHEN trigger_type = 2 THEN session_id END) AS submissions
				 FROM {$snaps_table}
				 WHERE form_id IN ({$placeholders})
				   AND processed    = 0
				   AND form_visible = 1
				   AND occurred_at >= %s
				   AND occurred_at <  %s
				 GROUP BY form_id",
				array_merge( $form_ids, [ $today_start, $tomorrow_start ] )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return $this->merge_overview_layers( $sentinel_rows, $today_rows, $form_ids );
	}

	/**
	 * Compute today's window boundaries in site timezone as DATETIME strings.
	 *
	 * @since 2.0.0
	 *
	 * @return array Tuple [ today_start, tomorrow_start ] in 'Y-m-d H:i:s' form.
	 */
	public function today_boundaries(): array {

		$tz             = wp_timezone();
		$today          = new DateTimeImmutable( 'today', $tz );
		$tomorrow       = $today->modify( '+1 day' );
		$today_start    = $today->format( 'Y-m-d H:i:s' );
		$tomorrow_start = $tomorrow->format( 'Y-m-d H:i:s' );

		return [ $today_start, $tomorrow_start ];
	}

	/**
	 * Merge sentinel + today DB rows into a form_id-keyed map.
	 *
	 * @since 2.0.0
	 *
	 * @param array $sentinel_rows Rows from layer 1.
	 * @param array $today_rows    Rows from layer 2.
	 * @param array $form_ids      Form IDs requested.
	 *
	 * @return array Map of form_id => stats.
	 */
	protected function merge_overview_layers( array $sentinel_rows, array $today_rows, array $form_ids ): array {

		$by_form = [];

		foreach ( $form_ids as $form_id ) {
			$by_form[ $form_id ] = [
				'views'       => 0,
				'submissions' => 0,
			];
		}

		$by_form = $this->accumulate_overview_rows( $by_form, $sentinel_rows );

		return $this->accumulate_overview_rows( $by_form, $today_rows );
	}

	/**
	 * Add one query layer's rows into the form-keyed accumulator.
	 *
	 * Both overview layers (lifetime sentinel + today) share the same shape and
	 * column set, so they accumulate through this single routine.
	 *
	 * @since 2.0.0
	 *
	 * @param array $by_form Accumulator keyed by form_id.
	 * @param array $rows    Rows from one query layer.
	 *
	 * @return array The accumulator with $rows added.
	 */
	private function accumulate_overview_rows( array $by_form, array $rows ): array {

		foreach ( $rows as $row ) {
			$id = (int) $row['form_id'];

			$by_form[ $id ]['views']       += (int) $row['views'];
			$by_form[ $id ]['submissions'] += (int) $row['submissions'];
		}

		return $by_form;
	}
}
