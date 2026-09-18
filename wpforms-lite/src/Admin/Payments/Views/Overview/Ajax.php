<?php

namespace WPForms\Admin\Payments\Views\Overview;

use WPForms\Db\Payments\StatsAggregator;
use WPForms\Admin\Helpers\Chart as ChartHelper;
use WPForms\Admin\Helpers\Datepicker;

/**
 * "Payments" overview page inside the admin, which lists all payments.
 * This page will be accessible via "WPForms" → "Payments".
 *
 * When requested data is sent via Ajax, this class is responsible for exchanging datasets.
 *
 * @since 1.8.2
 */
class Ajax {

	/**
	 * Temporary storage for the stat cards.
	 *
	 * @since 1.8.4
	 *
	 * @var array
	 */
	private $stat_cards;

	/**
	 * Hooks.
	 *
	 * @since 1.8.2
	 */
	public function hooks() {

		add_action( 'wp_ajax_wpforms_payments_overview_refresh_chart_dataset_data', [ $this, 'get_chart_dataset_data' ] );
		add_action( 'wp_ajax_wpforms_payments_overview_save_chart_preference_settings', [ $this, 'save_chart_preference_settings' ] );
		add_filter( 'wpforms_db_payments_payment_add_secondary_where_conditions_args', [ $this, 'modify_secondary_where_conditions_args' ] );
	}

	/**
	 * Generate and return the data for our dataset data.
	 *
	 * @since 1.8.2
	 */
	public function get_chart_dataset_data() {

		// Run a security check.
		check_ajax_referer( 'wpforms_payments_overview_nonce' );

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'wpforms-lite' ) );
		}

		$report   = ! empty( $_POST['report'] ) ? sanitize_text_field( wp_unslash( $_POST['report'] ) ) : null;
		$dates    = ! empty( $_POST['dates'] ) ? sanitize_text_field( wp_unslash( $_POST['dates'] ) ) : null;
		$fallback = [
			'data'    => [],
			'reports' => [],
		];

		// If the report type or dates for the timespan are missing, leave early.
		if ( ! $report || ! $dates ) {
			wp_send_json_error( $fallback );
		}

		// Validates and creates date objects of given timespan string.
		$timespans = Datepicker::process_string_timespan( $dates );

		// If the timespan is not validated, leave early.
		if ( ! $timespans ) {
			wp_send_json_error( $fallback );
		}

		// Extract start and end timespans in local (site) and UTC timezones.
		list( $start_date, $end_date, $utc_start_date, $utc_end_date ) = $timespans;

		// Get the stat cards.
		$this->stat_cards = Chart::stat_cards();

		// Payment stats service, seeded with the Payments page stat-card config.
		$aggregator = new StatsAggregator( $this->stat_cards );

		// Get the payments in the given timespan.
		$results = $aggregator->get_payments_in_timespan( $utc_start_date, $utc_end_date, $report );

		// In case the database's results were empty, leave early.
		if ( $report === Chart::ACTIVE_REPORT && empty( $results ) ) {
			wp_send_json_error( $fallback );
		}

		// Process the results and return the data.
		// The first element of the array is the total number of entries, the second is the data.
		list( , $data ) = ChartHelper::process_chart_dataset_data( $results, $start_date, $end_date );

		// Sends the JSON response back to the Ajax request, indicating success.
		wp_send_json_success(
			[
				'data'    => $data,
				'reports' => $this->maybe_format_amounts( $aggregator->get_summary_reports( $start_date, $end_date ) ),
			]
		);
	}

	/**
	 * Save the user's preferred graph style and color scheme.
	 *
	 * @since 1.8.2
	 */
	public function save_chart_preference_settings() {

		// Run a security check.
		check_ajax_referer( 'wpforms_payments_overview_nonce' );

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'wpforms-lite' ) );
		}

		$graph_style = isset( $_POST['graphStyle'] ) ? absint( $_POST['graphStyle'] ) : 2; // Line.

		update_user_meta( get_current_user_id(), 'wpforms_dash_widget_graph_style', $graph_style );

		exit();
	}

	/**
	 * Modify arguments of secondary where clauses.
	 *
	 * @since 1.8.2
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	public function modify_secondary_where_conditions_args( $args ) {

		// Set a current mode.
		if ( ! isset( $args['mode'] ) ) {
			$args['mode'] = Page::get_mode();
		}

		return $args;
	}

	/**
	 * Maybe format the amounts for the given stat cards.
	 *
	 * @since 1.8.4
	 *
	 * @param array $results Query results.
	 *
	 * @return array
	 */
	private function maybe_format_amounts( $results ) {

		// If the input is empty, leave early.
		if ( empty( $results ) ) {
			return [];
		}

		foreach ( $results as $key => $value ) {
			// If the given stat card doesn't have a button class, leave early.
			// If the given stat card doesn't have a button class of "is-amount," leave early.
			if ( ! isset( $this->stat_cards[ $key ]['button_classes'] ) || ! in_array( 'is-amount', $this->stat_cards[ $key ]['button_classes'], true ) ) {
				continue;
			}

			// Split the input by space to look for the count.
			$input_arr = (array) explode( ' ', $value );

			// If the given stat card doesn't have a count, leave early.
			if ( empty( $this->stat_cards[ $key ]['has_count'] ) || ! isset( $input_arr[1] ) ) {
				// Format the given amount and split the input by space.
				$results[ $key ] = wpforms_format_amount( $value, true );

				continue;
			}

			// The fields are stored as a `decimal` in the DB, and appears here as the string.
			// But all strings values, passed to wpforms_format_amount() are sanitized.
			// There is no need to sanitize it, as it is already a regular numeric string.
			$amount = wpforms_format_amount( (float) ( $input_arr[0] ?? $value ), true );

			// Format the amount with the concatenation of count in parentheses.
			// Example: 2185.52000000 (79).
			$results[ $key ] = sprintf(
				'%s <span>%s</span>',
				esc_html( $amount ),
				esc_html( $input_arr[1] ) // 1: Would be count of the records.
			);
		}

		return $results;
	}
}
