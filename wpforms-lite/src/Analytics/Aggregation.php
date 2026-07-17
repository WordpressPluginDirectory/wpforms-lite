<?php

namespace WPForms\Analytics;

use DateTimeImmutable;
use RuntimeException;
use Throwable;
use WPForms\Db\Analytics\DB;

/**
 * Form-level aggregation — shared base.
 *
 * Runs nightly via AnalyticsAggregationTask. Aggregates form-level daily
 * rows, maintains lifetime sentinel rows, marks processed snapshots, and
 * purges expired rows.
 *
 * @since 2.0.0
 */
class Aggregation {

	/**
	 * Grace window between local midnight and aggregation run (seconds).
	 *
	 * Replaces the previous SQL-level "abandonment threshold". By the time
	 * the task fires, every aggregated snapshot is at least this many
	 * seconds old, so still-active sessions cannot be falsely abandoned.
	 *
	 * @since 2.0.0
	 */
	public const ABANDONMENT_GRACE_SECONDS = 3600;

	/**
	 * Default retention window (days) for processed analytics snapshots.
	 *
	 * Used as the default for the `wpforms_analytics_aggregation_purge_old_retention_days`
	 * filter in both Lite and Pro. The 30-day window also acts as the race-safety
	 * margin for the Pro application-level cascade delete.
	 *
	 * @since 2.0.0
	 */
	public const DEFAULT_RETENTION_DAYS = 30;

	/**
	 * Injected DB instance. Null until first lazy resolve.
	 *
	 * @since 2.0.0
	 *
	 * @var DB|null
	 */
	private $db;

	/**
	 * Class constructor.
	 *
	 * Production code instantiated by the Loader passes nothing; the class
	 * lazy-resolves the analytics DB from the container on first use. Tests
	 * inject a concrete instance (real or spy) to bypass container wiring.
	 *
	 * @since 2.0.0
	 *
	 * @param DB|null $db Optional DB instance. Null = lazy container lookup.
	 */
	public function __construct( ?DB $db = null ) {

		$this->db = $db;
	}

	/**
	 * Resolve the DB instance — injected or lazy from the container.
	 *
	 * @since 2.0.0
	 *
	 * @return DB|null Null if neither injection nor container resolution worked.
	 */
	protected function db(): ?DB {

		if ( $this->db === null ) {
			$this->db = wpforms()->obj( 'analytics_db' );
		}

		return $this->db;
	}

	/**
	 * Execute the aggregation pipeline.
	 *
	 * @since 2.0.0
	 */
	public function run(): void {

		/**
		 * Fires before the nightly analytics aggregation pipeline runs.
		 *
		 * @since 2.0.0
		 */
		do_action( 'wpforms_analytics_aggregation_run_before' );

		$this->aggregate_in_transaction( $this->get_today_start() );

		$this->purge_old();

		/**
		 * Fires after the nightly analytics aggregation pipeline finishes.
		 *
		 * @since 2.0.0
		 */
		do_action( 'wpforms_analytics_aggregation_run_after' );
	}

	/**
	 * Run the additive aggregation steps inside a single DB transaction.
	 *
	 * Aggregate upserts are additive; pairing them with mark_processed in one
	 * transaction means a fatal mid-run cannot leave committed deltas behind
	 * unprocessed rows that would be re-aggregated on retry. Any Throwable
	 * triggers a ROLLBACK and is logged. purge_old() runs outside this wrap.
	 *
	 * @since 2.0.0
	 *
	 * @param string $today_start Today's start datetime in site timezone.
	 */
	private function aggregate_in_transaction( string $today_start ): void {

		global $wpdb;

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		try {
			$this->aggregate_forms( $today_start );
			$this->aggregate_extended( $today_start );
			$this->mark_processed( $today_start );

			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			$this->log_error(
				'Analytics aggregation: transaction rolled back',
				[
					'message' => $e->getMessage(),
				]
			);
		}
	}

	/**
	 * Log a forced analytics-aggregation error.
	 *
	 * Centralizes the shared wpforms_log() error arguments
	 * ( type = error, force = true ) used across the pipeline steps.
	 *
	 * @since 2.0.0
	 *
	 * @param string $title   Log entry title.
	 * @param array  $context Context data for the log entry.
	 */
	private function log_error( string $title, array $context ): void {

		wpforms_log(
			$title,
			$context,
			[
				'type'  => [ 'error' ],
				'force' => true,
			]
		);
	}

	/**
	 * Aggregate snapshot rows into analytics_forms (daily + sentinel).
	 *
	 * @since 2.0.0
	 *
	 * @param string $today_start Today's start datetime in site timezone.
	 */
	public function aggregate_forms( string $today_start ): void {

		global $wpdb;

		$snaps = DB::snapshots_table();

		// `views` and `unique_sessions` are written with the same COUNT(DISTINCT session_id):
		// session_id is regenerated per page-load and never persisted, so one page-load is one
		// session and the two always match under the current model. `unique_sessions` is kept
		// populated for forward-compat (it would diverge if session_id ever became persistent,
		// cookie/storage-backed) but is intentionally not surfaced in any read/output path while
		// it stays identical to `views`.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					form_id,
					DATE(occurred_at)          AS period_date,
					COUNT(DISTINCT session_id) AS views,
					COUNT(DISTINCT session_id) AS unique_sessions,
					COUNT(DISTINCT CASE WHEN trigger_type = 2 THEN session_id END) AS submissions
				FROM $snaps
				WHERE processed      = 0
					AND form_visible = 1
					AND occurred_at  < %s
				GROUP BY form_id, DATE(occurred_at)",
				$today_start
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $rows ) ) {
			return;
		}

		$db = $this->db();

		if ( ! $db ) {
			return;
		}

		foreach ( $rows as $row ) {

			$deltas = [
				'views'           => (int) $row['views'],
				'unique_sessions' => (int) $row['unique_sessions'],
				'submissions'     => (int) $row['submissions'],
			];

			$db->upsert_form_aggregate( (int) $row['form_id'], (string) $row['period_date'], $deltas );
			$db->upsert_form_sentinel( (int) $row['form_id'], $deltas );
		}
	}

	/**
	 * Pro-only aggregation hook. No-op in Lite.
	 *
	 * @since 2.0.0
	 *
	 * @param string $today_start Today's start datetime in site timezone.
	 */
	public function aggregate_extended( string $today_start ): void {

		// Lite: no field-level rollup. Pro\Aggregation overrides this method.
	}

	/**
	 * Mark every snapshot in the aggregation window as processed.
	 *
	 * Part of the aggregation transaction: a failed UPDATE here throws, just as
	 * a Throwable in aggregate_forms/aggregate_extended does, so
	 * aggregate_in_transaction rolls the whole window back. This prevents the
	 * additive deltas from being committed while their snapshots stay
	 * unprocessed — which would re-aggregate and double-count on the next run.
	 * Idempotent: re-running on the same window is a no-op.
	 *
	 * @since 2.0.0
	 *
	 * @param string $today_start Today's start datetime in site timezone.
	 *
	 * @throws RuntimeException When the snapshot UPDATE fails, so the surrounding transaction rolls back.
	 */
	public function mark_processed( string $today_start ): void {

		global $wpdb;

		$snaps = DB::snapshots_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $snaps
				SET processed = 1
				WHERE processed     = 0
					AND occurred_at < %s",
				$today_start
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// A failed UPDATE must abort the transaction: committing the additive
		// deltas while leaving these snapshots unprocessed would re-aggregate
		// them on the next run and double-count. Throwing routes through the
		// aggregate_in_transaction() catch, which logs the error and rolls back.
		if ( $result === false ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message routed to the error log, not HTML output.
			throw new RuntimeException( 'Analytics aggregation: mark_processed failed. ' . $wpdb->last_error );
		}
	}

	/**
	 * Delete processed snapshots older than the retention cutoff.
	 *
	 * @since 2.0.0
	 */
	public function purge_old(): void {

		/**
		 * Filter the retention window (in days) for processed analytics snapshots.
		 *
		 * Return 0 (or negative) to disable purging entirely.
		 *
		 * @since 2.0.0
		 *
		 * @param int $days Retention window in days. Default DEFAULT_RETENTION_DAYS.
		 */
		$days = (int) apply_filters( 'wpforms_analytics_aggregation_purge_old_retention_days', self::DEFAULT_RETENTION_DAYS );

		if ( $days <= 0 ) {
			return;
		}

		$cutoff = ( new DateTimeImmutable( "-{$days} days", wp_timezone() ) )->format( 'Y-m-d H:i:s' );

		global $wpdb;

		$snaps = DB::snapshots_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$snaps}
				WHERE processed     = 1
					AND occurred_at < %s",
				$cutoff
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $result === false ) {
			$this->log_error(
				'Analytics aggregation: purge_old failed',
				[
					'cutoff'     => $cutoff,
					'last_error' => $wpdb->last_error,
				]
			);
		}
	}

	/**
	 * Today's start in site timezone (delegates to DB::today_boundaries()).
	 *
	 * @since 2.0.0
	 *
	 * @return string DATETIME string 'Y-m-d H:i:s'.
	 */
	protected function get_today_start(): string {

		$db = $this->db();

		if ( ! $db ) {
			return ( new DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d H:i:s' );
		}

		[ $today_start ] = $db->today_boundaries();

		return $today_start;
	}
}
