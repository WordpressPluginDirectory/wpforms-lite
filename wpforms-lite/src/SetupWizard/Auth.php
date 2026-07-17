<?php

namespace WPForms\SetupWizard;

use WP_REST_Request;

/**
 * Session token authentication for the Setup Wizard.
 *
 * Issues an HMAC-SHA512 token bound to the issuing admin's user ID. The token
 * is stored in a 1-hour transient as `[ 'token' => string, 'user_id' => int ]`,
 * refreshed on every authenticated request, and verified with `hash_equals()`.
 * Validating a token returns the bound user ID so the REST layer can hydrate
 * the cross-origin request via `wp_set_current_user()`.
 *
 * @since 2.0.0
 */
class Auth {

	/**
	 * Transient name storing the active token payload.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const TRANSIENT_TOKEN = 'wpforms_setup_wizard_token';

	/**
	 * Token lifetime in seconds.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private const TTL = HOUR_IN_SECONDS;

	/**
	 * Header name the SPA uses to send the token back.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const HEADER = 'X-WPForms-Setup-Wizard-Token';

	/**
	 * Issue a token bound to the given user ID.
	 *
	 * Reuses the existing token when the stored transient still belongs to the
	 * same user, so a second launch in the same session yields a stable token.
	 *
	 * @since 2.0.0
	 *
	 * @return string The hashed token, raw value to send to the SPA.
	 */
	public function generate_token(): string {

		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return '';
		}

		$stored = get_transient( self::TRANSIENT_TOKEN );

		if (
			is_array( $stored )
			&& ! empty( $stored['token'] )
			&& $user_id === (int) ( $stored['user_id'] ?? 0 )
		) {
			$token = (string) $stored['token'];
		} else {
			$token = wp_generate_password( 64, false );
		}

		set_transient(
			self::TRANSIENT_TOKEN,
			[
				'token'   => $token,
				'user_id' => $user_id,
			],
			self::TTL
		);

		return $this->sign( $user_id, $token );
	}

	/**
	 * Refresh the stored token's TTL without changing its value.
	 *
	 * @since 2.0.0
	 *
	 * @param array $stored_token Token currently in use.
	 */
	private function refresh( array $stored_token ): void {

		set_transient( self::TRANSIENT_TOKEN, $stored_token, self::TTL );
	}

	/**
	 * Validate a REST request and return the bound user ID.
	 *
	 * Reads the token from the request header, compares with `hash_equals()`,
	 * and returns the user ID if valid. Returns 0 on failure.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return int Bound user ID, or 0 if the token is missing/invalid.
	 */
	public function validate_request( WP_REST_Request $request ): int {

		$token = (string) $request->get_header( self::HEADER );

		if ( $token === '' ) {
			return 0;
		}

		$stored = get_transient( self::TRANSIENT_TOKEN );

		if ( ! is_array( $stored ) || empty( $stored['token'] ) ) {
			return 0;
		}

		$user_id = (int) ( $stored['user_id'] ?? 0 );

		if ( $user_id <= 0 ) {
			return 0;
		}

		$expected = $this->sign( $user_id, (string) $stored['token'] );

		if ( ! hash_equals( $expected, $token ) ) {
			return 0;
		}

		$this->refresh( $stored );

		return $user_id;
	}

	/**
	 * Revoke the active token (e.g., on wizard completion).
	 *
	 * @since 2.0.0
	 */
	public function revoke(): void {

		delete_transient( self::TRANSIENT_TOKEN );
	}

	/**
	 * Build the HMAC-SHA512 signature for a token payload.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $user_id User the token is bound to.
	 * @param string $token   Per-request entropy.
	 *
	 * @return string
	 */
	private function sign( int $user_id, string $token ): string {

		return hash_hmac( 'sha512', $user_id . '|' . $token, wp_salt() );
	}
}
