<?php
/**
 * Helper logging and debug functions.
 *
 * @since 1.8.0
 */

use WPForms\Logger\Log;

/**
 * Check whether the plugin works in a debug mode.
 *
 * @since 1.2.3
 *
 * @return bool
 */
function wpforms_debug(): bool {

	$debug = false;

	if ( ( defined( 'WPFORMS_DEBUG' ) && true === WPFORMS_DEBUG ) && is_super_admin() ) {
		$debug = true;
	}

	/**
	 * Filters wpforms_debug status.
	 *
	 * @since 1.2.3
	 *
	 * @param bool $debug WPForms debug status.
	 */
	return (bool) apply_filters( 'wpforms_debug', $debug );
}

/**
 * Replace sensitive values with a placeholder, to keep credentials out of debug output and logs.
 *
 * Matching is exact-key and case-insensitive: only the keys listed below (and any added via the filter)
 * are redacted, so non-secret lookalikes like `password-strength` keep their real values. A sensitive
 * key holding an array or an object is redacted as a whole, so a credential stored under a generic
 * child key (e.g. `'token' => [ 'value' => 'secret' ]`) does not slip through.
 * Empty values are left as they are — redacting them would wrongly suggest that a credential
 * is configured.
 *
 * Arrays and objects are copied, never redacted in place, so the caller keeps its own data intact.
 * Objects are traversed through their accessible properties only: a credential kept in a private
 * or protected property is out of reach and is still printed by `print_r()`.
 *
 * @since 2.0.1
 *
 * @param mixed $data Data to redact. Arrays and objects are traversed, any other type is returned as it is.
 *
 * @return mixed Data with sensitive values replaced by `[redacted]`.
 */
function wpforms_debug_redact_sensitive_data( $data ) {

	$default_keys = [
		'password',
		'form_locker_password',
		'protection_password',
		'protection_password_confirm',
		'secret',
		'client_secret',
		'api_key',
		'apikey',
		'access_token',
		'refresh_token',
		'private_key',
		'tgm-updater-key',
	];

	/**
	 * Filters the list of array keys whose values are redacted in debug output and logs.
	 *
	 * Keys are matched exactly and case-insensitively, so the casing they are given in does not matter.
	 *
	 * @since 2.0.1
	 *
	 * @param string[] $keys Sensitive array keys.
	 */
	$keys = apply_filters( 'wpforms_debug_redact_sensitive_data_keys', $default_keys );

	// A callback that returns nothing must not silently turn the redaction off.
	$keys = is_array( $keys ) ? $keys : $default_keys;

	// Lowercase the whole list, so that a key given with natural casing still matches.
	$keys = array_map( 'strtolower', array_filter( $keys, 'is_string' ) );

	return wpforms_debug_redact_sensitive_data_value( $data, $keys );
}

/**
 * Redact the sensitive values of a single array or object, recursively.
 *
 * Internal helper of `wpforms_debug_redact_sensitive_data()`, which is the function to call.
 *
 * @since 2.0.1
 *
 * @param mixed    $value Value to redact.
 * @param string[] $keys  Sensitive array keys, lowercase.
 * @param int      $depth Current nesting level.
 *
 * @return mixed Value with sensitive data replaced by `[redacted]`.
 */
function wpforms_debug_redact_sensitive_data_value( $value, array $keys, int $depth = 0 ) {

	// Stop on absurdly deep data: a self-referencing array or object would otherwise never end.
	// An array or an object is replaced rather than returned, because the keys inside it have
	// not been looked at, so printing it would leak every credential it holds.
	if ( $depth > 32 ) {
		return is_array( $value ) || is_object( $value ) ? '[max depth reached]' : $value;
	}

	if ( is_array( $value ) ) {
		$redacted = [];

		foreach ( $value as $key => $item ) {
			// An empty value is kept as it is: redacting it would suggest that a credential is configured.
			$redacted[ $key ] = in_array( strtolower( (string) $key ), $keys, true ) && ! empty( $item )
				? '[redacted]'
				: wpforms_debug_redact_sensitive_data_value( $item, $keys, $depth + 1 );
		}

		return $redacted;
	}

	if ( ! is_object( $value ) ) {
		return $value;
	}

	$properties = get_object_vars( $value );
	$redacted   = wpforms_debug_redact_sensitive_data_value( $properties, $keys, $depth );

	// Nothing to hide: the object is returned as it was given, keeping its `print_r()` shape.
	if ( $redacted === $properties ) {
		return $value;
	}

	$clone = clone $value;

	foreach ( $redacted as $key => $item ) {
		$clone->{$key} = $item;
	}

	return $clone;
}

/**
 * Helper function to display debug data.
 *
 * @since 1.0.0
 *
 * @param mixed $data    What to dump - can be any type.
 * @param bool  $do_echo Whether to print or return. The default is to print.
 *
 * @return string|void
 */
function wpforms_debug_data( $data, bool $do_echo = true ) {

	if ( ! wpforms_debug() ) {
		return;
	}

	if ( is_array( $data ) || is_object( $data ) ) {
		$data = wpforms_debug_redact_sensitive_data( $data );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$data = print_r( $data, true );
	}

	$output = sprintf(
		'<style>
			.wpforms-debug {
				line-height: 0;
			}
			.wpforms-debug textarea {
				background: #f6f7f7 !important;
				margin: 20px 0 0 0;
				width: 100%%;
				height: 500px;
				font-size: 12px;
				font-family: Consolas, Menlo, Monaco, monospace;
				direction: ltr;
				unicode-bidi: embed;
				line-height: 1.4;
				padding: 10px;
				border-radius: 0;
				border-color: #c3c4c7;
				box-sizing: border-box;
			}
			.postbox .wpforms-debug {
				padding: 6px;
			}
			.postbox .wpforms-debug:not(:first-of-type) {
				padding-top: 0;
			}
			.postbox .wpforms-debug textarea {
				margin-top: 0 !important;
			}
		</style>
		<div class="wpforms-debug">
			<textarea readonly>=================== WPFORMS DEBUG ===================%s</textarea>
		</div>',
		"\n\n" . esc_html( $data )
	);

	/**
	 * Allow developers to determine whether the debug data should be displayed.
	 * Works only in debug mode (`WPFORMS_DEBUG` constant is `true`).
	 *
	 * @since 1.6.8
	 *
	 * @param bool $allow_display True by default.
	 */
	$allow_display = apply_filters( 'wpforms_debug_data_allow_display', true );

	if ( $do_echo && $allow_display ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	} else {
		return $output;
	}
}

/**
 * Log helper.
 *
 * @since 1.0.0
 *
 * @param string $title   Title of a log message.
 * @param mixed  $message Content of a log message.
 * @param array  $args    Expected keys: type, form_id, meta, parent, force.
 */
function wpforms_log( $title = '', $message = '', $args = [] ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

	// Skip if logs disabled in Tools -> Logs.
	if ( empty( $args['force'] ) && ! wpforms_setting( 'logs-enable' ) ) {
		return;
	}

	// Require log title.
	if ( empty( $title ) ) {
		return;
	}

	/**
	 * Compare error levels to determine if we should log.
	 * Current supported levels:
	 * - Conditional Logic (conditional_logic)
	 * - Entries (entry)
	 * - Errors (error)
	 * - Payments (payment)
	 * - Providers (provider)
	 * - Security (security)
	 * - Spam (spam)
	 * - Log (log)
	 */
	$types = ! empty( $args['type'] ) ? (array) $args['type'] : [ 'error' ];

	// Skip invalid logs types.
	$log_types = Log::get_log_types();

	foreach ( $types as $key => $type ) {
		if ( ! isset( $log_types[ $type ] ) ) {
			unset( $types[ $key ] );
		}
	}

	if ( empty( $types ) ) {
		return;
	}

	/**
	 * Filter log message.
	 *
	 * @since 1.8.2
	 *
	 * @param mixed  $message Log message.
	 * @param string $title   Log title.
	 * @param array  $args    Log arguments.
	 */
	$message = apply_filters( 'wpforms_log_message', $message, $title, $args );

	// Make arrays and objects look nice, keeping credentials out of the stored log message.
	if ( is_array( $message ) || is_object( $message ) ) {
		$message = wpforms_debug_redact_sensitive_data( $message );
		$message = '<pre>' . esc_html( print_r( $message, true ) ) . '</pre>'; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}

	// Filter logs types from Tools -> Logs page.
	$logs_types = wpforms_setting( 'logs-types' );

	if ( $logs_types && empty( array_intersect( $logs_types, $types ) ) ) {
		return;
	}

	// Filter user roles from Tools -> Logs page.
	$current_user       = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
	$current_user_id    = $current_user->ID ?? 0;
	$current_user_roles = $current_user->roles ?? [];
	$logs_user_roles    = wpforms_setting( 'logs-user-roles' );

	if ( $logs_user_roles && empty( array_intersect( $logs_user_roles, $current_user_roles ) ) ) {
		return;
	}

	// Filter logs users from Tools -> Logs page.
	$logs_users = wpforms_setting( 'logs-users' );

	if ( $logs_users && ! in_array( $current_user_id, $logs_users, true ) ) {
		return;
	}

	$log = wpforms()->obj( 'log' );

	if ( ! $log || ! method_exists( $log, 'add' ) ) {
		return;
	}

	// Create log entry.
	$log->add(
		$title,
		$message,
		$types,
		isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0,
		isset( $args['parent'] ) ? absint( $args['parent'] ) : 0,
		$current_user_id
	);
}

/**
 * Wrapper for set_time_limit to see if it is enabled.
 *
 * @since 1.6.4
 *
 * @param int $limit Time limit.
 */
function wpforms_set_time_limit( $limit = 0 ) {

	if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
		@set_time_limit( $limit ); // @codingStandardsIgnoreLine
	}
}
