<?php

namespace WPForms\Db\Analytics;

use WPForms_DB;

/**
 * Custom-tables handler for the analytics forms aggregate table.
 *
 * Owns the schema for wp_wpforms_analytics_forms and registers it with the
 * self-healing custom-tables registry. Read/write access lives in
 * WPForms\Db\Analytics\DB. The table uses a composite primary key
 * (form_id, period_date); the inherited scalar-PK CRUD is never used here.
 *
 * @since 2.0.0
 */
class Forms extends WPForms_DB {

	/**
	 * Primary class constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct();

		$this->table_name  = self::get_table_name();
		$this->primary_key = 'form_id';
		$this->type        = 'analytics_forms';
	}

	/**
	 * Get the table name.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function get_table_name(): string {

		return DB::forms_table();
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
			form_id         BIGINT(20) UNSIGNED NOT NULL,
			period_date     DATE                NOT NULL,
			views           INT(10)    UNSIGNED NOT NULL DEFAULT 0,
			unique_sessions INT(10)    UNSIGNED NOT NULL DEFAULT 0,
			submissions     INT(10)    UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (form_id, period_date)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $query );
	}
}
