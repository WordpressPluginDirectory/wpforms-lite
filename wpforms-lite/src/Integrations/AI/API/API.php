<?php

namespace WPForms\Integrations\AI\API;

use WPForms\Integrations\AI\API\Http\Request;
use WPForms\Integrations\AI\Helpers;

/**
 * API class.
 *
 * @since 1.9.1
 */
class API {

	/**
	 * API limit.
	 *
	 * @since 1.9.1
	 */
	const LIMIT = 100;

	/**
	 * API limit max.
	 *
	 * @since 1.9.1
	 */
	const LIMIT_MAX = 1000;

	/**
	 * Request instance.
	 *
	 * @since 1.9.1
	 *
	 * @var Request
	 */
	protected $request;

	/**
	 * Initialize the API.
	 *
	 * @since 1.9.1
	 */
	public function init() {

		$this->request = new Request();
	}

	/**
	 * Rate the response.
	 *
	 * @since 1.9.1
	 *
	 * @param bool   $helpful     Whether the response was helpful.
	 * @param string $response_id Response ID to rate.
	 *
	 * @return array
	 */
	public function rate( bool $helpful, string $response_id ): array {

		$args = [
			'helpful'    => $helpful,
			'responseId' => $response_id,
		];

		$endpoint = '/rate-response';

		$response = $this->request->post( $endpoint, $args );

		if ( $response->has_errors() ) {
			$error_data = $response->get_error_data();

			Helpers::log_error( $response->get_log_message( $error_data ), $endpoint, $args );

			return $error_data;
		}

		return $response->get_body();
	}

	/**
	 * Get the limit for the API request.
	 * Returns limit set by the filter or the default limit.
	 * The limit is capped at LIMIT_MAX.
	 *
	 * @since 1.9.1
	 *
	 * @return int
	 */
	protected function get_limit(): int {

		return min(
			/**
			 * Filter the limit for the API request.
			 *
			 * @since 1.9.1
			 *
			 * @param int $limit Limit for the API request.
			 */
			(int) apply_filters( 'wpforms_integrations_ai_api_get_limit', self::LIMIT ), // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			self::LIMIT_MAX
		);
	}

	/**
	 * Prepare the prompt.
	 *
	 * @since 1.9.1
	 *
	 * @param string $prompt Prompt text.
	 *
	 * @return string
	 */
	protected function prepare_prompt( string $prompt ): string {

		// Remove any HTML tags.
		$prompt = wp_strip_all_tags( $prompt );

		// Remove any extra spaces.
		$prompt = preg_replace( '/\s+/', ' ', $prompt );

		// Remove any extra characters.
		return trim( $prompt, ' .,!?:' );
	}

	/**
	 * Get global settings to pass to the AI middleware.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	protected function get_global_settings(): array {

		$captcha  = wpforms_get_captcha_settings();
		$provider = $captcha['provider'] ?? 'none';

		return [
			'captcha'     => [
				'provider'       => $provider,
				'configured'     => $provider !== 'none'
									&& ! empty( $captcha['site_key'] )
									&& ! empty( $captcha['secret_key'] ),
				'recaptcha_type' => $provider === 'recaptcha' ? ( $captcha['recaptcha_type'] ?? null ) : null,
			],
			'geolocation' => $this->get_geolocation_settings(),
			'privacy'     => [
				// Not the master GDPR toggle, which travels as the top-level `gdpr` body value.
				// This is `gdpr && gdpr-disable-uuid` resolved, the fact the Quiz addon needs:
				// quizzes identify takers by cookie and cannot run without one.
				'disable_user_cookies' => ! wpforms_is_collecting_cookies_allowed(),
			],
		];
	}

	/**
	 * Get Geolocation addon settings to pass to the AI middleware.
	 *
	 * Reads the saved provider/API-key settings directly (the same settings the
	 * Geolocation addon's own provider classes check internally) rather than
	 * depending on the addon's classes, so this works whether or not the addon
	 * is installed.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_geolocation_settings(): array {

		$provider = (string) wpforms_setting( 'geolocation-field-provider', '' );

		$configured = false;

		if ( $provider === 'google-places' ) {
			$configured = ! empty( wpforms_setting( 'geolocation-google-places-api-key' ) );
		} elseif ( $provider === 'mapbox-search' ) {
			$configured = ! empty( wpforms_setting( 'geolocation-mapbox-search-access-token' ) );
		}

		return [
			'provider'   => $provider !== '' ? $provider : 'none',
			'configured' => $configured,
		];
	}
}
