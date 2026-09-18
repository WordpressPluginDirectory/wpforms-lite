<?php

namespace WPForms\Integrations\AI\API\Http;

// phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement
use WP_Error;

/**
 * Response class.
 *
 * @since 1.9.1
 */
class Response {

	/**
	 * Response.
	 *
	 * @since 1.9.1
	 *
	 * @var array
	 */
	protected $response;

	/**
	 * Response constructor.
	 *
	 * @since 1.9.1
	 *
	 * @param array|WP_Error $response Response.
	 */
	public function __construct( $response ) {

		$this->response = $response;
	}

	/**
	 * Retrieve only the body from the raw response.
	 *
	 * @since 1.9.1
	 *
	 * @return array The body of the response.
	 */
	public function get_body(): array {

		$body = wp_remote_retrieve_body( $this->response );

		if ( empty( $body ) ) {
			return [];
		}

		return json_decode( $body, true ) ?? [];
	}

	/**
	 * Get error data.
	 *
	 * @since 1.9.1
	 *
	 * @return array
	 */
	public function get_error_data(): array {

		$code = $this->get_response_code();

		return [
			'error' => $this->get_response_message(),
			'code'  => empty( $code ) ? 'wp_error' : $code,
		];
	}

	/**
	 * Retrieve the user-facing response message.
	 *
	 * Every AI surface renders this string as the error title in the chat, so it
	 * only ever returns copy written for a user. `error_message` is authored by
	 * the middleware and passes through; a transport failure or a bare HTTP
	 * status phrase is technical and is replaced with the network notice. The
	 * raw text is kept for the log by get_raw_response_message().
	 *
	 * @since 1.9.1
	 *
	 * @return string The response error.
	 */
	public function get_response_message(): string {

		$body = is_wp_error( $this->response ) ? [] : $this->get_body();

		if ( ! empty( $body['error_message'] ) ) {
			return (string) $body['error_message'];
		}

		return __( 'There appears to be a network error.', 'wpforms-lite' );
	}

	/**
	 * Get the error log message.
	 *
	 * Logs the raw transport detail rather than `$error_data['error']`, which
	 * carries the user-facing copy from get_response_message().
	 *
	 * @since 1.9.2
	 *
	 * @param array $error_data Error data.
	 *
	 * @return string The error log message.
	 */
	public function get_log_message( array $error_data ): string {

		return sprintf( /* translators: %1$s - error code, %2$s - error message. */
			__( 'API response: %1$s %2$s', 'wpforms-lite' ),
			$error_data['code'],
			$this->get_raw_response_message()
		);
	}

	/**
	 * Retrieve the unfiltered response message for logging.
	 *
	 * `error` is preferred because the middleware reports every gate failure there
	 * and nowhere else — "Missing batch ID", "Invalid scope", "Rate limit exceeded".
	 * Only the chat endpoint pairs it with a user-facing `error_message`, and there
	 * `error` holds the code, which is the more useful half for a log. Without this
	 * the reason phrase ("Bad Request") is all that survives.
	 *
	 * @since 2.0.2
	 *
	 * @return string The raw transport or middleware error text.
	 */
	private function get_raw_response_message(): string {

		if ( is_wp_error( $this->response ) ) {
			return $this->response->get_error_message();
		}

		$body = $this->get_body();

		return (string) (
			$body['error'] ??
			$body['error_message'] ??
			wp_remote_retrieve_response_message( $this->response )
		);
	}

	/**
	 * Retrieve only the response code from the raw response.
	 *
	 * @since 1.9.1
	 *
	 * @return int The response code as an integer.
	 */
	private function get_response_code(): int {

		return absint( wp_remote_retrieve_response_code( $this->response ) );
	}

	/**
	 * Whether we received errors in the response.
	 *
	 * @since 1.9.1
	 *
	 * @return bool True if response has errors.
	 */
	public function has_errors(): bool {

		$code = $this->get_response_code();

		return $code < 200 || $code > 299;
	}
}
