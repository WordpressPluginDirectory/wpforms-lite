<?php

namespace WPForms\Db\Analytics;

use WPForms_DB;

/**
 * Custom-tables handler for the analytics snapshots table.
 *
 * Owns the schema for wp_wpforms_analytics_snapshots and registers it with the
 * self-healing custom-tables registry (WPForms_Lite::CUSTOM_TABLES) so it is
 * created on install and recreated by the Settings self-heal / recreate-tables
 * tool. Read/write access lives in WPForms\Db\Analytics\DB.
 *
 * @since 2.0.0
 */
class Snapshots extends WPForms_DB {

	/**
	 * Primary class constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct();

		$this->table_name  = self::get_table_name();
		$this->primary_key = 'id';
		$this->type        = 'analytics_snapshots';
	}

	/**
	 * Get the table name.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function get_table_name(): string {

		return DB::snapshots_table();
	}

	/**
	 * Create the table.
	 *
	 * @since 2.0.0
	 */
	public function create_table(): void {

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$query = "CREATE TABLE $this->table_name (
			id           BIGINT(20)  UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id      BIGINT(20)  UNSIGNED NOT NULL,
			session_id   VARCHAR(64)          NOT NULL,
			trigger_type TINYINT(3)  UNSIGNED NOT NULL,
			page_number  TINYINT(3)  UNSIGNED NULL     DEFAULT NULL,
			form_visible TINYINT(1)  UNSIGNED NOT NULL DEFAULT 0,
			payload      LONGTEXT             NOT NULL,
			occurred_at  DATETIME             NOT NULL,
			processed    TINYINT(1)  UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY idx_unprocessed (processed, occurred_at),
			KEY idx_session (session_id, form_id, trigger_type),
			KEY idx_form_date (form_id, processed, form_visible, occurred_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $query );
	}
}
