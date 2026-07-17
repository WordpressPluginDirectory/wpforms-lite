<?php

namespace WPForms\Migrations;

use WPForms\Admin\Forms\Table\Facades\Columns;
use WPForms\Analytics\Analytics;
use WPForms\Helpers\DB;

/**
 * Analytics migration (Lite).
 *
 * Two-step migration shipped with 2.0.0:
 *
 * 1. Analytics table creation is handled by the self-healing custom-tables
 *    registry (WPForms\Helpers\DB::create_custom_tables()). The registry
 *    creates missing tables without dropping data, keeping this step idempotent
 *    across repeated migration runs and fresh installs alike. It does NOT align
 *    columns on tables that already exist — it skips them unchanged, so schema
 *    changes to an existing table need a dedicated migration.
 *
 * 2. Forms Overview saved-columns evolution: append the analytics columns to
 *    each user's saved column meta and remove the now-default-hidden
 *    Shortcode column. Without this, users who customized their column order
 *    before 2.0.0 would have to enable the new analytics columns manually
 *    via Screen Options.
 *
 * Idempotent: the custom-tables registry skips tables that already exist
 * (it does not alter them); the column-meta sweep skips users whose saved meta
 * already contains the analytics columns and has no retired columns to drop.
 *
 * @since 2.0.0
 */
class Upgrade2_0_0 extends UpgradeBase {

	/**
	 * Forms Overview columns introduced in 2.0.0.
	 *
	 * @since 2.0.0
	 */
	private const NEW_OVERVIEW_COLUMNS = [
		'analytics_views',
		'analytics_interactions',
		'analytics_conversion',
	];

	/**
	 * Forms Overview columns retired (default-hidden) in 2.0.0.
	 *
	 * Removed from saved user meta so the analytics columns become visible
	 * in the same row footprint by default. Users can re-enable Shortcode
	 * via Screen Options - the column itself is still registered.
	 *
	 * @since 2.0.0
	 */
	private const RETIRED_OVERVIEW_COLUMNS = [
		'shortcode',
	];

	/**
	 * Anchor key used to position the new analytics columns inside saved meta.
	 *
	 * Inserts the new columns immediately after the Entries column when the
	 * user has it saved; otherwise appends them at the end of the list.
	 *
	 * @since 2.0.0
	 */
	private const OVERVIEW_INSERT_AFTER = 'entries';

	/**
	 * Run the upgrade.
	 *
	 * @since 2.0.0
	 *
	 * @return bool|null true = completed, false = failed, null = background task in progress.
	 */
	public function run(): ?bool {

		// Tables are created by the self-healing custom-tables registry. This
		// also covers fresh installs where this migration is version-gated out.
		DB::create_custom_tables( true );

		// Skip the saved-meta sweep when the analytics feature is killed.
		// Tables remain dormant (not uninstalled), but the per-user column
		// reshuffle is an analytics-feature concern and must not run when the
		// feature is filtered off.
		if ( Analytics::is_enabled() ) {
			$this->migrate_overview_columns_user_meta();
		}

		return true;
	}

	/**
	 * Sweep every user with saved Forms Overview column meta and bring it in
	 * line with the 2.0.0 default visible set.
	 *
	 * Bounded operation - the meta key is set only for admin users who have
	 * customized columns via Screen Options or drag-and-drop. A direct
	 * usermeta query is cheaper and more PHPCS-friendly than `get_users()`
	 * with `meta_key`.
	 *
	 * @since 2.0.0
	 */
	private function migrate_overview_columns_user_meta(): void {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
				Columns::COLUMNS_USER_META_NAME
			)
		);

		if ( empty( $user_ids ) ) {
			return;
		}

		foreach ( $user_ids as $user_id ) {
			$this->migrate_overview_columns_for_user( (int) $user_id );
		}
	}

	/**
	 * Apply the column-meta evolution to one user's saved meta.
	 *
	 * Skips users whose saved meta is empty or non-array. The pure helper
	 * `evolve_overview_columns()` returns the input unchanged when there is
	 * nothing to add and nothing to remove, keeping repeated migration runs
	 * idempotent at zero write cost.
	 *
	 * @since 2.0.0
	 *
	 * @param int $user_id User ID.
	 */
	private function migrate_overview_columns_for_user( int $user_id ): void {

		$saved = get_user_meta( $user_id, Columns::COLUMNS_USER_META_NAME, true );

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return;
		}

		$migrated = self::evolve_overview_columns(
			$saved,
			self::NEW_OVERVIEW_COLUMNS,
			self::RETIRED_OVERVIEW_COLUMNS,
			self::OVERVIEW_INSERT_AFTER
		);

		if ( $migrated === $saved ) {
			return;
		}

		update_user_meta( $user_id, Columns::COLUMNS_USER_META_NAME, $migrated );
	}

	/**
	 * Pure helper: drop retired keys, then insert new keys after the anchor.
	 *
	 * Extracted as a static method so it is unit-testable without hitting WP
	 * options/usermeta or the DB. Idempotent: a saved list that already
	 * contains every new key and no retired keys passes through unchanged.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $saved        User's saved column keys (current order).
	 * @param array  $new_keys     Keys to introduce.
	 * @param array  $retired_keys Keys to remove (if present).
	 * @param string $insert_after Anchor key; new keys inserted right after it (appended if anchor absent).
	 *
	 * @return array Migrated column keys.
	 */
	public static function evolve_overview_columns( array $saved, array $new_keys, array $retired_keys, string $insert_after ): array {

		// Drop retired keys, preserving order for everything else.
		$result = array_values(
			array_filter(
				$saved,
				static function ( $key ) use ( $retired_keys ) {

					return ! in_array( $key, $retired_keys, true );
				}
			)
		);

		// Filter the new keys down to those genuinely missing (idempotency).
		$to_insert = array_values(
			array_filter(
				$new_keys,
				static function ( $key ) use ( $result ) {

					return ! in_array( $key, $result, true );
				}
			)
		);

		if ( empty( $to_insert ) ) {
			return $result;
		}

		$anchor_pos = array_search( $insert_after, $result, true );
		$splice_at  = $anchor_pos !== false ? (int) $anchor_pos + 1 : count( $result );

		array_splice( $result, $splice_at, 0, $to_insert );

		return $result;
	}
}
