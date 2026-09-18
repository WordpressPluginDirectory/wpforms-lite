<?php

namespace WPForms\Db\Payments;

use DateTimeImmutable;
use WPForms\Admin\Helpers\Datepicker;
use wpdb; // phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement

/**
 * Payment statistics aggregator.
 *
 * @since 2.0.2
 */
class StatsAggregator {

	/**
	 * Primary report used as the GROUP BY anchor for the summary derived table.
	 *
	 * @since 2.0.2
	 *
	 * @var string
	 */
	const ACTIVE_REPORT = 'total_payments';

	/**
	 * Payments database table name.
	 *
	 * @since 2.0.2
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Stat cards configuration (label, funnel, meta_key, has_count per report).
	 *
	 * @since 2.0.2
	 *
	 * @var array
	 */
	private $stat_cards;

	/**
	 * Constructor.
	 *
	 * @since 2.0.2
	 *
	 * @param array $stat_cards Stat cards configuration, e.g. Chart::stat_cards().
	 */
	public function __construct( array $stat_cards ) {

		$this->stat_cards = $stat_cards;
		$this->table_name = wpforms()->obj( 'payment' )->table_name;
	}

	/**
	 * Retrieve payment records grouped by day within the specified timespan.
	 *
	 * @global wpdb $wpdb Instantiation of the wpdb class.
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable|mixed $start_date Start date for the timespan preferably in UTC.
	 * @param DateTimeImmutable|mixed $end_date   End date for the timespan preferably in UTC.
	 * @param string                  $report     Payment summary stat card name. i.e. "total_payments".
	 *
	 * @return array
	 */
	public function get_payments_in_timespan( $start_date, $end_date, string $report ): array {

		// Ensure given timespan dates are in UTC timezone.
		list( $utc_start_date, $utc_end_date ) = Datepicker::process_timespan_mysql( [ $start_date, $end_date ] );

		// If the time period is not a date object, leave early.
		if ( ! ( $start_date instanceof DateTimeImmutable ) || ! ( $end_date instanceof DateTimeImmutable ) ) {
			return [];
		}

		// Get the database instance.
		global $wpdb;

		// SELECT clause to construct the SQL statement.
		$column_clause = $this->get_stats_column_clause( $report );

		// JOIN clause to construct the SQL statement for metadata.
		$join_by_meta = $this->add_join_by_meta( $report );

		// WHERE clauses for items query statement.
		$where_clause = $this->get_stats_where_clause( $report );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT date_created_gmt AS day, $column_clause AS count FROM $this->table_name AS p {$join_by_meta}
					WHERE 1=1 $where_clause AND date_created_gmt BETWEEN %s AND %s GROUP BY day ORDER BY day ASC",
				[
					$utc_start_date->format( Datepicker::DATETIME_FORMAT ),
					$utc_end_date->format( Datepicker::DATETIME_FORMAT ),
				]
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Fetch and generate payment summary reports (raw aggregates + deltas).
	 *
	 * @global wpdb $wpdb Instantiation of the wpdb class.
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable|mixed $start_date Start date for the timespan preferably in UTC.
	 * @param DateTimeImmutable|mixed $end_date   End date for the timespan preferably in UTC.
	 *
	 * @return array|null
	 */
	public function get_summary_reports( $start_date, $end_date ) {

		// Ensure given timespan dates are in UTC timezone.
		list( $utc_start_date, $utc_end_date ) = Datepicker::process_timespan_mysql( [ $start_date, $end_date ] );

		// If the time period is not a date object, leave early.
		if ( ! ( $start_date instanceof DateTimeImmutable ) || ! ( $end_date instanceof DateTimeImmutable ) ) {
			return [];
		}

		// Get the database instance.
		global $wpdb;

		list( $clause, $query ) = $this->prepare_sql_summary_reports( $utc_start_date, $utc_end_date );

		$group_by = self::ACTIVE_REPORT;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			"SELECT $clause FROM (SELECT $query) AS results GROUP BY $group_by",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Generate SQL statements to create a derived (virtual) table for the report stat cards.
	 *
	 * @global wpdb $wpdb Instantiation of the wpdb class.
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable|mixed $start_date Start date for the timespan.
	 * @param DateTimeImmutable|mixed $end_date   End date for the timespan.
	 *
	 * @return array
	 */
	private function prepare_sql_summary_reports( $start_date, $end_date ) {

		// In case there are no report stat cards defined, leave early.
		if ( empty( $this->stat_cards ) ) {
			return [ '', '' ];
		}

		global $wpdb;

		$clause = []; // SELECT clause.
		$query  = []; // Query statement for the derived table.

		// Validates and creates date objects for the previous time spans.
		$prev_timespans = Datepicker::get_prev_timespan_dates( $start_date, $end_date );

		// If the timespan is not validated, leave early.
		if ( ! $prev_timespans ) {
			return [ '', '' ];
		}

		[ $prev_start_date, $prev_end_date ] = $prev_timespans;

		// Get the default number of decimals for the payment currency.
		$current_currency  = wpforms_get_currency();
		$currency_decimals = wpforms_get_currency_decimals( $current_currency );

		// Loop through the reports and create the SQL statements.
		foreach ( $this->stat_cards as $report => $attributes ) {

			// Skip stat card, if it's not supposed to be displayed or disabled (upsell).
			if (
				( isset( $attributes['condition'] ) && ! $attributes['condition'] )
				|| in_array( 'disabled', $attributes['button_classes'], true )
			) {
				continue;
			}

			// Determine whether the number of rows has to be counted.
			$has_count = isset( $attributes['has_count'] ) && $attributes['has_count'];

			// SELECT clause to construct the SQL statement.
			$column_clause = $this->get_stats_column_clause( $report, $has_count );

			// JOIN clause to construct the SQL statement for metadata.
			$join_by_meta = $this->add_join_by_meta( $report );

			// WHERE clauses for items query statement.
			$where_clause = $this->get_stats_where_clause( $report );

			// Get the current and previous values for the report.
			$current_value = "TRUNCATE($report,$currency_decimals)";
			$prev_value    = "TRUNCATE({$report}_prev,$currency_decimals)";

			// Add the current and previous reports to the SELECT clause.
			$clause[] = $report;
			$clause[] = "ROUND( ( ( $current_value - $prev_value ) / $current_value ) * 100 ) AS {$report}_delta";

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.MissingReplacements
			$query[] = $wpdb->prepare(
				"(
					SELECT $column_clause
					FROM $this->table_name AS p
					{$join_by_meta}
					WHERE 1=1 $where_clause AND date_created_gmt BETWEEN %s AND %s
				) AS $report,
				(
					SELECT $column_clause
					FROM $this->table_name AS p
					{$join_by_meta}
					WHERE 1=1 $where_clause AND date_created_gmt BETWEEN %s AND %s
				) AS {$report}_prev",
				[
					$start_date->format( Datepicker::DATETIME_FORMAT ),
					$end_date->format( Datepicker::DATETIME_FORMAT ),
					$prev_start_date->format( Datepicker::DATETIME_FORMAT ),
					$prev_end_date->format( Datepicker::DATETIME_FORMAT ),
				]
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.MissingReplacements
		}

		return [
			implode( ',', $clause ),
			implode( ',', $query ),
		];
	}

	/**
	 * Helper method to build where clause used to construct the SQL statement.
	 *
	 * @since 2.0.2
	 *
	 * @param string $report Payment summary stat card name. i.e. "total_payments".
	 *
	 * @return string
	 */
	private function get_stats_where_clause( string $report ): string {

		// Get the default WHERE clause from the Payments database class.
		$clause = wpforms()->obj( 'payment' )->add_secondary_where_conditions();

		// If the report doesn't have any additional funnel arguments, leave early.
		if ( ! isset( $this->stat_cards[ $report ]['funnel'] ) ) {
			return $clause;
		}

		// Get the where arguments for the report.
		$where_args = (array) $this->stat_cards[ $report ]['funnel'];

		// If the where arguments are empty, leave early.
		if ( empty( $where_args ) ) {
			return $clause;
		}

		return $this->prepare_sql_where_clause( $where_args, $clause );
	}

	/**
	 * Prepare SQL where clause for the given funnel arguments.
	 *
	 * @since 2.0.2
	 *
	 * @param array  $where_args Array of where arguments.
	 * @param string $clause     SQL where clause.
	 *
	 * @return string
	 */
	private function prepare_sql_where_clause( array $where_args, string $clause ): string {

		$allowed_funnels = [ 'in', 'not_in' ];

		$filtered_where_args = array_filter(
			$where_args,
			static function ( $key ) use ( $allowed_funnels ) {

				return in_array( $key, $allowed_funnels, true );
			},
			ARRAY_FILTER_USE_KEY
		);

		// Leave early if the filtered where arguments are empty.
		if ( empty( $filtered_where_args ) ) {
			return $clause;
		}

		// Loop through the where arguments and add them to the clause.
		foreach ( $filtered_where_args as $operator => $columns ) {
			foreach ( $columns as $column => $values ) {
				if ( ! is_array( $values ) ) {
					continue;
				}

				// Skip if the value is not valid.
				$valid_values = array_filter(
					$values,
					static function ( $item ) use ( $column ) {

						return ValueValidator::is_valid( $item, $column );
					}
				);

				$placeholders = wpforms_wpdb_prepare_in( $valid_values );
				$clause      .= $operator === 'in' ? " AND {$column} IN ({$placeholders})" : " AND {$column} NOT IN ({$placeholders})";
			}
		}

		return $clause;
	}

	/**
	 * Helper method to build column clause used to construct the SQL statement.
	 *
	 * @since 2.0.2
	 *
	 * @param string $report     Stats card chart type (name). i.e. "total_payments".
	 * @param bool   $with_count Whether to concatenate the count to the clause.
	 *
	 * @return string
	 */
	private function get_stats_column_clause( string $report, bool $with_count = false ): string {

		// Default column clause.
		// Count the number of rows as fast as possible.
		$default = 'COUNT(*)';

		// If the report has a meta key, then count the number of unique rows for the meta table.
		if ( isset( $this->stat_cards[ $report ]['meta_key'] ) ) {
			$default = 'COUNT(pm.id)';
		}

		/**
		 * Filters the column clauses for the stat cards.
		 *
		 * @since 1.8.2
		 *
		 * @param array $clauses Array of column clauses.
		 */
		$clauses = (array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Hook tag preserved verbatim after the move from Ajax for backward compatibility.
			'wpforms_admin_payments_views_overview_ajax_stats_column_clauses',
			[
				'total_payments'             => "FORMAT({$default},0)",
				'total_sales'                => 'IFNULL(SUM(total_amount),0)',
				'total_refunded'             => 'IFNULL(SUM(pm.meta_value),0)',
				'total_subscription'         => 'IFNULL(SUM(total_amount),0)',
				'total_renewal_subscription' => 'IFNULL(SUM(total_amount),0)',
				'total_coupons'              => "FORMAT({$default},0)",
			]
		);

		$clause = isset( $clauses[ $report ] ) ? $clauses[ $report ] : $default;

		// Several stat cards might include the count of payment records.
		if ( $with_count ) {
			$clause = "CONCAT({$clause}, ' (', {$default}, ')')";
		}

		return $clause;
	}

	/**
	 * Add join by meta table.
	 *
	 * @global wpdb $wpdb Instantiation of the wpdb class.
	 *
	 * @since 2.0.2
	 *
	 * @param string $report Stats card chart type (name). i.e. "total_payments".
	 *
	 * @return string
	 */
	private function add_join_by_meta( string $report ): string {

		// Leave early if the meta key is empty.
		if ( ! isset( $this->stat_cards[ $report ]['meta_key'] ) ) {
			return '';
		}

		// Retrieve the global database instance.
		global $wpdb;

		// Retrieve the meta table name.
		$meta_table_name = wpforms()->obj( 'payment_meta' )->table_name;

		return $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"LEFT JOIN {$meta_table_name} AS pm ON p.id = pm.payment_id AND pm.meta_key = %s",
			$this->stat_cards[ $report ]['meta_key']
		);
	}
}
