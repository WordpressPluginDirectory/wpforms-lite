<?php

namespace WPForms\Tasks\Actions;

use WPForms\Admin\Dashboard\Cache;
use WPForms\Admin\Helpers\Datepicker;
use WPForms\Pro\Db\Dashboard\RollupReadiness; // phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement -- Pro symbol guarded by is_pro() at runtime; Lite→Pro inversion accepted for this dashboard feature.
use WPForms\Tasks\Task;
use WPForms\Tasks\Tasks; // phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement

/**
 * Recurring Dashboard aggregate cache warm-up.
 *
 * @since 2.0.2
 */
class DashboardCacheRefreshTask extends Task {

	/**
	 * Action Scheduler action name.
	 *
	 * @since 2.0.2
	 */
	public const ACTION = 'wpforms_dashboard_refresh_cache';

	/**
	 * Option key storing the last-applied interval (seconds).
	 *
	 * @since 2.0.2
	 */
	public const INTERVAL_OPTION = 'wpforms_dashboard_cache_refresh_interval';

	/**
	 * Interval in seconds. 0 cancels schedule; non-zero floored at 30 minutes.
	 *
	 * @since 2.0.2
	 *
	 * @var int
	 */
	private $interval;

	/**
	 * Tasks instance.
	 *
	 * @since 2.0.2
	 *
	 * @var Tasks|null
	 */
	private $tasks;

	/**
	 * Log title.
	 *
	 * @since 2.0.2
	 *
	 * @var string
	 */
	protected $log_title = 'Dashboard Cache Refresh';

	/**
	 * Class constructor.
	 *
	 * @since 2.0.2
	 */
	public function __construct() {

		parent::__construct( self::ACTION );

		$this->init();
		$this->hooks();
	}

	/**
	 * Register the recurring schedule.
	 *
	 * @since 2.0.2
	 */
	private function init(): void {

		$this->tasks = wpforms()->obj( 'tasks' );

		if ( ! $this->tasks ) {
			return;
		}

		// Schedule add/remove is an admin/cron concern.
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}

		/**
		 * Filter the Dashboard cache refresh task interval (seconds).
		 *
		 * Return 0 to cancel the schedule entirely. Non-zero values below
		 * 30 minutes are floored to 30 minutes.
		 *
		 * @since 2.0.2
		 *
		 * @param int $interval Interval in seconds. Default 30 * MINUTE_IN_SECONDS.
		 */
		$raw = (int) apply_filters( 'wpforms_tasks_actions_dashboard_cache_refresh_task_init_interval', 30 * MINUTE_IN_SECONDS );

		$this->interval = $raw <= 0 ? 0 : max( 30 * MINUTE_IN_SECONDS, $raw );

		$this->reconcile_schedule();
	}

	/**
	 * Converge the scheduled action to the filtered desired interval.
	 *
	 * @since 2.0.2
	 */
	private function reconcile_schedule(): void {

		$is_scheduled = $this->tasks->is_scheduled( self::ACTION ) !== false;

		// Cancellation requested.
		if ( $this->interval <= 0 ) {

			if ( $is_scheduled ) {
				$this->cancel();
				delete_option( self::INTERVAL_OPTION );
			}

			return;
		}

		// First-time scheduling — no existing recurring action.
		if ( ! $is_scheduled ) {

			$this->add_task();
			update_option( self::INTERVAL_OPTION, $this->interval, false );

			return;
		}

		// Already is_scheduled — re-arm only if cadence changed.
		if ( (int) get_option( self::INTERVAL_OPTION, 0 ) === $this->interval ) {
			return;
		}

		$this->cancel();
		$this->add_task();
		update_option( self::INTERVAL_OPTION, $this->interval, false );
	}

	/**
	 * Bind the recurring action to process().
	 *
	 * @since 2.0.2
	 */
	private function hooks(): void {

		add_action( self::ACTION, [ $this, 'process' ] );
	}

	/**
	 * Schedule the first run and recurring cadence.
	 *
	 * @since 2.0.2
	 */
	private function add_task(): void {

		if ( $this->interval <= 0 ) {
			return;
		}

		// First run is immediate, not one interval out, so the very first Dashboard
		// view is served from a warm cache on a fresh install as well as an upgrade.
		$this->tasks->create( self::ACTION )
			->recurring( time(), $this->interval )
			->params()
			->register();
	}

	/**
	 * Recurring callback. Warms every preset range except '365'.
	 *
	 * Elevates to an administrator to bypass per-user capability filters.
	 *
	 * @since 2.0.2
	 */
	public function process(): void {

		$cache = wpforms()->obj( 'dashboard_cache' );

		if ( ! $cache ) {
			return;
		}

		$original_user_id = get_current_user_id();
		$admin_id         = $this->get_admin_user_id();

		if ( $admin_id ) {
			wp_set_current_user( $admin_id );
		}

		try {
			$ready = class_exists( RollupReadiness::class )
				? ( new RollupReadiness() )->ready_presets()
				: Cache::PRESET_RANGES;

			$warmed = array_diff( $ready, [ '365' ] );

			foreach ( $warmed as $days ) {
				[ $start, $end ] = Datepicker::get_timespan_dates( $days );

				// Build the key the same way the page does, so the warmed entry is the one
				// the next request reads. Passing the bare day count would write a key the
				// currency-qualified lookup never matches.
				$cache->compute( Cache::make_key( $days, $start, $end ), $start, $end );
			}
		} finally {
			wp_set_current_user( $original_user_id );
		}

		$this->log( 'Dashboard cache refresh completed.' );
	}

	/**
	 * Get the lowest-ID site administrator, or 0 if none exists.
	 *
	 * @since 2.0.2
	 *
	 * @return int
	 */
	private function get_admin_user_id(): int {

		$admins = get_users(
			[
				'role'    => 'administrator',
				'number'  => 1,
				'fields'  => 'ID',
				'orderby' => 'ID',
				'order'   => 'ASC',
			]
		);

		return ! empty( $admins ) ? (int) $admins[0] : 0;
	}
}
