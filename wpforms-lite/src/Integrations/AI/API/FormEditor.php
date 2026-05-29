<?php

namespace WPForms\Integrations\AI\API;

use WPForms\Integrations\AI\Admin\Ajax\Forms as FormsAjax;
use WPForms\Integrations\AI\Helpers;

/**
 * Form Editor API class.
 *
 * @since 1.10.1
 */
class FormEditor extends API {

	/**
	 * API endpoint.
	 *
	 * @since 1.10.1
	 */
	private const ENDPOINT = '/ai-form-editor';

	/**
	 * Process a scope request via the middleware API.
	 *
	 * @since 1.10.1
	 *
	 * @param string $scope      Scope to process (analyze, fields, settings, etc.).
	 * @param string $session_id Session ID.
	 * @param array  $form_data  Serialized form data.
	 * @param string $prompt     User prompt.
	 * @param array  $history    Conversation history (analyze scope only).
	 * @param string $batch_id   Batch ID.
	 *
	 * @return array
	 */
	public function process_scope(
		string $scope,
		string $session_id,
		array $form_data,
		string $prompt = '',
		array $history = [],
		string $batch_id = ''
	): array {

		$body = $this->get_request_body( $scope, $session_id, $form_data, $prompt, $history );

		/**
		 * Filters the request body before sending it to the API.
		 *
		 * @since 1.10.1
		 *
		 * @param array  $body       Request body.
		 * @param string $scope      Current scope.
		 * @param string $session_id Session ID.
		 * @param array  $form_data  Original (raw) form data. Note: $body['form'] contains the prepared version.
		 */
		$body = (array) apply_filters(
			'wpforms_integrations_aiapi_form_editor_process_scope_before_send',
			$body,
			$scope,
			$session_id,
			$form_data
		);

		$headers = [];

		if ( ! empty( $batch_id ) ) {
			$headers = [
				'x-wpforms-batch-id' => $batch_id,
			];
		}

		$response = $this->request->post( self::ENDPOINT, $body, $headers );

		if ( $response->has_errors() ) {
			$error_data = $response->get_error_data();

			Helpers::log_error( $response->get_log_message( $error_data ), self::ENDPOINT, $body );

			return $error_data;
		}

		return $response->get_body();
	}

	/**
	 * Get the request body for a given scope.
	 *
	 * @since 1.10.1
	 *
	 * @param string $scope      Scope to process.
	 * @param string $session_id Session ID.
	 * @param array  $form_data  Serialized form data.
	 * @param string $prompt     User prompt.
	 * @param array  $history    Conversation history.
	 *
	 * @return array
	 */
	private function get_request_body(
		string $scope,
		string $session_id,
		array $form_data,
		string $prompt,
		array $history
	): array {

		$body = [
			'scope'           => $scope,
			'sessionId'       => $session_id,
			'form'            => $this->get_prepared_form_data( $form_data ),
			'prompt'          => $this->prepare_prompt( $prompt ),
			'lite'            => ! wpforms()->is_pro(),
			'addons'          => $this->get_addons(),
			'gdpr'            => wpforms_setting( 'gdpr' ),
			'pagebreak'       => true,
			'debug'           => defined( 'WPFORMS_AI_DEBUG' ) && WPFORMS_AI_DEBUG,
			'global_settings' => $this->get_global_settings(),
		];

		// Analyze scope includes the conversation history.
		if ( $scope === 'analyze' ) {
			$body['history'] = $history;
		}

		return $body;
	}

	/**
	 * Get active addons.
	 *
	 * @since 1.10.1
	 *
	 * @return array List of active addons.
	 */
	private function get_addons(): array {

		$addons_obj = wpforms()->obj( 'addons' );

		if ( ! $addons_obj ) {
			return [];
		}

		$addons = [];

		// Get the current version of the Quiz addon.
		$quiz_version = defined( 'WPFORMS_QUIZ_VERSION' ) ? WPFORMS_QUIZ_VERSION : '';

		// Get available addons.
		foreach ( FormsAjax::FORM_GENERATOR_REQUIRED_ADDONS as $slug ) {
			$addon = $addons_obj->get_addon( $slug );

			if (
				empty( $addon ) || // Exceptional case when `addons.json` is not loaded.
				empty( $addon['clear_slug'] ) ||
				( isset( $addon['status'] ) && $addon['status'] !== 'active' )
			) {
				continue;
			}

			// Skip the Quiz addon if it's not compatible.
			if ( $addon['clear_slug'] === 'quiz' && version_compare( $quiz_version, '1.3.0', '<=' ) ) {
				continue;
			}

			$addons[] = $addon['clear_slug'];
		}

		return $addons;
	}

	/**
	 * Get global settings to pass to the AI middleware.
	 *
	 * @since 1.10.1
	 *
	 * @return array
	 */
	private function get_global_settings(): array {

		$captcha  = wpforms_get_captcha_settings();
		$provider = $captcha['provider'] ?? 'none';

		return [
			'captcha' => [
				'provider'       => $provider,
				'configured'     => $provider !== 'none'
									&& ! empty( $captcha['site_key'] )
									&& ! empty( $captcha['secret_key'] ),
				'recaptcha_type' => $provider === 'recaptcha' ? ( $captcha['recaptcha_type'] ?? null ) : null,
			],
		];
	}

	/**
	 * Get prepared form data for the middleware.
	 *
	 * Strips unnecessary properties, ensures a clean field/settings format.
	 *
	 * @since 1.10.1
	 *
	 * @param array $form_data Raw form data from the browser.
	 *
	 * @return array Lean form data with fields and settings.
	 */
	private function get_prepared_form_data( array $form_data ): array {

		if ( ! empty( $form_data['fields'] ) ) {
			$form_data = wpforms_sanitize_form_data( $form_data );
		}

		return [
			'fields'       => $form_data['fields'] ?? [],
			'fields_order' => array_keys( $form_data['fields'] ?? [] ),
			'settings'     => $form_data['settings'] ?? [],
		];
	}
}
