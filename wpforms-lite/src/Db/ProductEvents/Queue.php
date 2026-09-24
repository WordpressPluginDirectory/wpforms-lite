<?php

namespace WPForms\Db\ProductEvents;

use WPForms_DB;

/**
 * Custom-tables handler for the product events buffer.
 *
 * Owns the schema for wp_wpforms_product_events_queue and registers it with the
 * self-healing custom-tables registry. Batch reads and retention live in
 * WPForms\Db\ProductEvents\Buffer; the inherited add() and
 * delete_where_in() are used as-is, which is why get_columns() has to be real: the base
 * class returns an empty array and an insert against that silently writes nothing.
 *
 * @since 2.0.2.1
 */
class Queue extends WPForms_DB {

	/**
	 * Primary class constructor.
	 *
	 * @since 2.0.2.1
	 */
	public function __construct() {

		parent::__construct();

		$this->table_name  = self::get_table_name();
		$this->primary_key = 'id';
		$this->type        = 'product_events_queue';
	}

	/**
	 * Get the table name.
	 *
	 * @since 2.0.2.1
	 *
	 * @return string
	 */
	public static function get_table_name(): string {

		global $wpdb;

		return $wpdb->prefix . 'wpforms_product_events_queue';
	}

	/**
	 * Get the table columns and their formats.
	 *
	 * @since 2.0.2.1
	 *
	 * @return array
	 */
	public function get_columns(): array {

		return [
			'id'            => '%d',
			'event_name'    => '%s',
			'event_context' => '%s',
			'properties'    => '%s',
			'occurred_at'   => '%s',
		];
	}

	/**
	 * Insert a row.
	 *
	 * Overridden only to supply the type identifier, so callers do not have to reach
	 * for the public property. Same shape as Db\Payments\Payment::add().
	 *
	 * @since 2.0.2.1
	 *
	 * @param array  $data Column data.
	 * @param string $type Optional. Data type context.
	 *
	 * @return int Id of the new row, zero on failure.
	 */
	public function add( $data, $type = '' ) {

		$type = empty( $type ) ? $this->type : $type;

		return parent::add( $data, $type );
	}

	/**
	 * Create the table.
	 *
	 * @since 2.0.2.1
	 */
	public function create_table(): void {

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$query = "CREATE TABLE $this->table_name (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_name    VARCHAR(64)         NOT NULL,
			event_context VARCHAR(20)         NOT NULL DEFAULT 'user',
			properties    LONGTEXT,
			occurred_at   DATETIME            NOT NULL,
			PRIMARY KEY  (id),
			KEY occurred_at (occurred_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $query );
	}
}
