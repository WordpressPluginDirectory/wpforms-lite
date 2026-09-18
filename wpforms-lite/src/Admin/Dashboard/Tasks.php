<?php

namespace WPForms\Admin\Dashboard;

use WPForms\Pro\Db\Dashboard\RollupRepository;
use WPForms\Pro\Tasks\Actions\DashboardBackfillTask;
use WPForms\Pro\Tasks\Actions\DashboardRollupTask;
use WPForms\Tasks\Actions\DashboardCacheRefreshTask;

/**
 * Dashboard background task registration.
 *
 * Feeds the Dashboard tasks into the Tasks manager through
 * `wpforms_tasks_get_tasks`, the same route every other feature uses, so the
 * manager instantiates them on its own schedule instead of the Loader having to
 * mirror its `init` timing.
 *
 * @since 2.0.2
 */
class Tasks {

	/**
	 * Bootstrap the registration.
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

		add_filter( 'wpforms_tasks_get_tasks', [ $this, 'register_tasks' ] );
	}

	/**
	 * Append the Dashboard tasks to the manager's class list.
	 *
	 * The rollup tasks read the Pro rollup tables directly, so they are only
	 * registered once those tables exist.
	 *
	 * @since 2.0.2
	 *
	 * @param array|mixed $tasks Task class list.
	 *
	 * @return array
	 */
	public function register_tasks( $tasks ): array {

		$tasks   = (array) $tasks;
		$tasks[] = DashboardCacheRefreshTask::class;

		if ( ! wpforms()->is_pro() || ! RollupRepository::tables_exist() ) {
			return $tasks;
		}

		$tasks[] = DashboardRollupTask::class;
		$tasks[] = DashboardBackfillTask::class;

		return $tasks;
	}
}
