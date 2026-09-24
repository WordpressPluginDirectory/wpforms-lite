<?php

namespace WPForms\Migrations;

use WPForms\Helpers\DB;

/**
 * Class upgrade for the 2.0.2.1 release.
 *
 * The release adds the product events queue table to the custom-tables
 * registry. The registry creates missing tables only while a migration for the
 * running version is due, so without a class for this version an upgraded site
 * would keep buffering into a table that does not exist until someone opened
 * Settings > General.
 *
 * @since 2.0.2.1
 *
 * @noinspection PhpUnused
 */
class Upgrade2_0_2_1 extends UpgradeBase {

	/**
	 * Run upgrade.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	public function run(): bool {

		// Tables are created by the self-healing custom-tables registry. This
		// also covers fresh installs where this migration is version-gated out.
		DB::create_custom_tables( true );

		return true;
	}
}
