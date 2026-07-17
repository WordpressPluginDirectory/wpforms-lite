<?php

namespace WPForms\Tasks\Actions;

use DateTimeImmutable;
use WPForms\Analytics\Aggregation;
use WPForms\Analytics\Analytics;
use WPForms\Db\Analytics\DB as AnalyticsDB;
use WPForms\Tasks\Task;
use WPForms\Tasks\Tasks; // phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement

/**
 * Nightly aggregation task for Form Analytics.
 *
 * Schedules itself recurring at site-local midnight + abandonment grace
 * (default 1h after midnight). Delegates work to the Loader-resolved
 * Analytics\Aggregation (Lite) or Pro\Analytics\Aggregation (Pro).
 *
 * @since 2.0.0
 */
class AnalyticsAggregationTask extends Task {

	/**
	 * Action Scheduler action name.
	 *
	 * @since 2.0.0
	 */
	public const ACTION = 'wpforms_analytics_aggregate';

	/**
	 * Option key storing the last-applied interval (seconds).
	 *
	 * Used by reconcile_schedule() to detect cadence drift between the
	 * filter's current return value and the cadence the recurring action
	 * was created with. Autoloaded so init() stays on the cheap path.
	 *
	 * @since 2.0.0
	 */
	public const INTERVAL_OPTION = 'wpforms_analytics_aggregation_interval';

	/**
	 * Interval in seconds. 0 cancels schedule; non-zero floored at DAY_IN_SECONDS.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private $interval;

	/**
	 * Tasks instance.
	 *
	 * @since 2.0.0
	 *
	 * @var Tasks|null
	 */
	private $tasks;

	/**
	 * Log title.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $log_title = 'Analytics Aggregation';

	/**
	 * Class constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct( self::ACTION );

		$this->init();
		$this->hooks();
	}

	/**
	 * Register the recurring schedule.
	 *
	 * @since 2.0.0
	 */
	private function init(): void {

		$this->tasks = wpforms()->obj( 'tasks' );

		if ( ! $this->tasks ) {
			return;
		}

		// Schedule add/remove is an admin/cron concern. Skip it on anonymous
		// front-end requests, where the schedule never needs adjusting — this
		// avoids an option read and the interval filter on every page view.
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}

		if ( ! Analytics::is_enabled() ) {
			$this->remove_task();

			return;
		}

		// Don't schedule until the migration has created the analytics tables.
		if ( ! AnalyticsDB::tables_exist() ) {
			return;
		}

		/**
		 * Filter the aggregation task interval (seconds).
		 *
		 * Return 0 to cancel the schedule entirely. Non-zero values below
		 * DAY_IN_SECONDS are floored to DAY_IN_SECONDS to preserve the
		 * abandonment-grace correctness boundary.
		 *
		 * @since 2.0.0
		 *
		 * @param int $interval Interval in seconds. Default DAY_IN_SECONDS.
		 */
		$raw = (int) apply_filters( 'wpforms_tasks_actions_analytics_aggregation_task_init_interval', DAY_IN_SECONDS );

		$this->interval = $raw <= 0 ? 0 : max( DAY_IN_SECONDS, $raw );

		$this->reconcile_schedule();
	}

	/**
	 * Cancel any scheduled aggregation action and clear the recorded interval.
	 *
	 * Called from init() when the analytics kill switch is on so the documented
	 * "no scheduled task" contract holds even if analytics was disabled after a
	 * recurring action had already been registered.
	 *
	 * @since 2.0.0
	 */
	private function remove_task(): void {

		if ( $this->tasks->is_scheduled( self::ACTION ) !== false ) {
			$this->cancel();
		}

		delete_option( self::INTERVAL_OPTION );
	}

	/**
	 * Converge the scheduled action to the filtered desired interval.
	 *
	 * Self-healing reconciliation across plugin boots:
	 *  - Not scheduled + interval > 0 → schedule + record interval.
	 *  - Scheduled + interval == 0    → cancel + drop recorded interval.
	 *  - Scheduled + interval changed → cancel + reschedule + update record.
	 *  - Scheduled + interval same    → no-op (steady state).
	 *
	 * The recorded interval lives in an autoloaded option, so the
	 * steady-state path is one cached lookup with no DB write.
	 *
	 * @since 2.0.0
	 */
	private function reconcile_schedule(): void {

		$scheduled = $this->tasks->is_scheduled( self::ACTION ) !== false;

		// Cancellation requested.
		if ( $this->interval <= 0 ) {

			if ( $scheduled ) {
				$this->cancel();
				delete_option( self::INTERVAL_OPTION );
			}

			return;
		}

		// First-time scheduling — no existing recurring action.
		if ( ! $scheduled ) {

			$this->add_task();
			update_option( self::INTERVAL_OPTION, $this->interval );

			return;
		}

		// Already scheduled — re-arm only if cadence changed.
		if ( (int) get_option( self::INTERVAL_OPTION, 0 ) === $this->interval ) {
			return;
		}

		$this->cancel();
		$this->add_task();
		update_option( self::INTERVAL_OPTION, $this->interval );
	}

	/**
	 * Bind the recurring action to process().
	 *
	 * @since 2.0.0
	 */
	private function hooks(): void {

		add_action( self::ACTION, [ $this, 'process' ] );
	}

	/**
	 * Schedule the first run and recurring cadence.
	 *
	 * @since 2.0.0
	 */
	private function add_task(): void {

		if ( $this->interval <= 0 ) {
			return;
		}

		$this->tasks->create( self::ACTION )
			->recurring( $this->next_run_timestamp(), $this->interval )
			->params()
			->register();
	}

	/**
	 * Compute the first-run timestamp: next site-local midnight + grace.
	 *
	 * @since 2.0.0
	 *
	 * @return int Unix timestamp.
	 */
	private function next_run_timestamp(): int {

		$midnight = ( new DateTimeImmutable( 'tomorrow', wp_timezone() ) )->getTimestamp();

		return $midnight + Aggregation::ABANDONMENT_GRACE_SECONDS;
	}

	/**
	 * Recurring callback. Delegates to the resolved Aggregation instance.
	 *
	 * @since 2.0.0
	 */
	public function process(): void {

		$aggregator = wpforms()->obj( 'analytics_aggregation' );

		if ( ! $aggregator || ! method_exists( $aggregator, 'run' ) ) {
			return;
		}

		$aggregator->run();

		$this->log( 'Analytics aggregation completed.' );
	}
}
