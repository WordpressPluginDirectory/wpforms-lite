<?php

namespace WPForms\Integrations\LiteConnect;

/**
 * Validates the shape of a LiteConnect stats endpoint response.
 *
 * @since 2.0.2
 */
class StatsResponseValidator {

	/**
	 * Validate the full stats response structure.
	 *
	 * @since 2.0.2
	 *
	 * @param mixed $data Decoded JSON response.
	 *
	 * @return bool
	 */
	public static function validate( $data ): bool {

		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( ! isset( $data['total'] ) || ! is_numeric( $data['total'] ) ) {
			return false;
		}

		if ( ! isset( $data['days'] ) || ! is_array( $data['days'] ) ) {
			return false;
		}

		if ( ! isset( $data['forms'] ) || ! is_array( $data['forms'] ) ) {
			return false;
		}

		foreach ( $data['days'] as $day ) {
			if ( ! self::validate_day_row( $day ) ) {
				return false;
			}
		}

		foreach ( $data['forms'] as $form ) {
			if ( ! self::validate_form_row( $form ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate a single day row.
	 *
	 * @since 2.0.2
	 *
	 * @param mixed $day Row from the days array.
	 *
	 * @return bool
	 */
	private static function validate_day_row( $day ): bool {

		if ( ! is_array( $day ) || ! isset( $day['date'], $day['count'] ) ) {
			return false;
		}

		// is_string() guard: preg_match() throws TypeError on non-string in PHP 8+.
		// Pattern: YYYY-MM-DD (ISO 8601 date).
		if ( ! is_string( $day['date'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day['date'] ) ) {
			return false;
		}

		if ( ! is_numeric( $day['count'] ) || $day['count'] < 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate a single form row.
	 *
	 * @since 2.0.2
	 *
	 * @param mixed $form Row from the forms array.
	 *
	 * @return bool
	 */
	private static function validate_form_row( $form ): bool {

		if ( ! is_array( $form ) || ! isset( $form['form_id'], $form['count'] ) ) {
			return false;
		}

		if ( ! is_numeric( $form['form_id'] ) || $form['form_id'] <= 0 ) {
			return false;
		}

		if ( ! is_numeric( $form['count'] ) || $form['count'] < 0 ) {
			return false;
		}

		return true;
	}
}
