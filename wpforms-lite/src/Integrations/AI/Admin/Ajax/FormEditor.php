<?php

namespace WPForms\Integrations\AI\Admin\Ajax;

use WPForms\Integrations\AI\Admin\Builder\FormEditor as BuilderFormEditor;
use WPForms\Integrations\AI\API\FormEditor as FormEditorAPI;

/**
 * AI Form Editor AJAX handler.
 *
 * @since 1.10.1
 */
class FormEditor extends Base {

	/**
	 * Form Editor API instance.
	 *
	 * @since 1.10.1
	 *
	 * @var FormEditorAPI
	 */
	private $form_editor_api;

	/**
	 * Initialize.
	 *
	 * @since 1.10.1
	 */
	public function init(): void {

		parent::init();

		$this->form_editor_api = new FormEditorAPI();

		$this->form_editor_api->init();
		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.10.1
	 */
	private function hooks(): void {

		add_action( 'wp_ajax_wpforms_ai_form_editor_process', [ $this, 'process' ] );
		add_action( 'wp_ajax_wpforms_ai_form_editor_reset', [ $this, 'reset' ] );
	}

	/**
	 * Process form editor AI request.
	 *
	 * Unified handler for all scopes.
	 *
	 * @since 1.10.1
	 */
	public function process(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		if ( ! $this->validate_nonce() ) {
			wp_send_json_error(
				[ 'error' => esc_html__( 'Your session expired. Please reload the builder.', 'wpforms-lite' ) ]
			);
		}

		$scope = sanitize_key( $this->get_post_data( 'scope' ) );

		// The analyze scope is always allowed — it is the initial prompt, not a pipeline scope.
		if ( $scope !== 'analyze' ) {
			$allowed = array_keys( ( new BuilderFormEditor() )->get_allowed_scopes() );

			if ( ! in_array( $scope, $allowed, true ) ) {
				wpforms_log(
					'AI Form Editor: unknown scope',
					[ 'scope' => $scope ],
					[ 'type' => 'error' ]
				);

				wp_send_json_error(
					[ 'error' => esc_html__( 'Invalid scope.', 'wpforms-lite' ) ]
				);
			}
		}

		$form_id = absint( $this->get_post_data( 'formId', 'int' ) );

		if ( empty( $form_id ) ) {
			wp_send_json_error(
				[ 'error' => esc_html__( 'No form ID found.', 'wpforms-lite' ) ]
			);
		}

		if ( ! wpforms_current_user_can( 'edit_form_single', $form_id ) ) {
			wp_send_json_error(
				[ 'error' => esc_html__( 'You are not allowed to edit this form.', 'wpforms-lite' ) ]
			);
		}

		$session_id = $this->get_post_data( 'sessionId' );

		// The analyze scope intentionally sends an empty session ID on the first edit of a chat session.
		// The middleware generates a stable session ID and returns it in the response.
		// All subsequent scopes (fields, settings, etc.) receive a session ID from the analyze step.
		if ( $scope !== 'analyze' && empty( $session_id ) ) {
			wp_send_json_error(
				[ 'error' => esc_html__( 'Missing session ID.', 'wpforms-lite' ) ]
			);
		}

		$batch_id  = $this->get_post_data( 'batchId' );
		$form_data = $this->get_post_data( 'formData', 'form_data' );

		if ( ! is_array( $form_data ) ) {
			$form_data = [];
		}

		// For non-analyze scopes the prompt is an AI-generated instruction from the `analyze` step's scopePrompts,
		// not raw user input. History is only relevant for the `analyze` scope.
		[ $prompt, $history ] = $this->get_request_arguments( $scope );

		if ( $this->is_empty_prompt( $prompt ) ) {
			wp_send_json_error(
				[ 'error' => esc_html__( 'Empty prompt.', 'wpforms-lite' ) ]
			);
		}

		// Create a form revision before applying changes.
		if ( $scope === 'analyze' ) {
			wp_save_post_revision( $form_id );
		}

		// Call the API.
		$response = $this->form_editor_api->process_scope( $scope, $session_id, $form_data, $prompt, $history, $batch_id );

		// Check for errors.
		if ( ! empty( $response['error'] ) ) {
			wp_send_json_error( $response );
		}

		if ( $scope === 'fields' ) {
			$response = $this->get_prepared_fields_response( $response, $form_id );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Reset form editor session.
	 *
	 * @since 1.10.1
	 */
	public function reset(): void {

		if ( ! $this->validate_nonce() ) {
			wp_send_json_error(
				[ 'error' => esc_html__( 'Your session expired. Please reload the builder.', 'wpforms-lite' ) ]
			);
		}

		wp_send_json_success();
	}

	/**
	 * Get scope-specific request arguments.
	 *
	 * For the `analyze` scope, retrieves prompt and validated conversation history.
	 * For other scopes, retrieves the prompt only (history is not applicable).
	 *
	 * @since 1.10.1
	 *
	 * @param string $scope Request scope.
	 *
	 * @return array{0: string, 1: array} Prompt and history.
	 */
	private function get_request_arguments( string $scope ): array {

		$prompt = $this->get_post_data( 'prompt' );

		if ( $scope !== 'analyze' ) {
			return [ $prompt, [] ];
		}

		$history = $this->get_post_data( 'history', 'json' );

		if ( ! is_array( $history ) || ! $this->validate_history( $history ) ) {
			$history = [];
		}

		return [ $prompt, $history ];
	}

	/**
	 * Validate a conversation history format.
	 *
	 * @since 1.10.1
	 *
	 * @param array $history History items.
	 *
	 * @return bool True if valid.
	 */
	private function validate_history( array $history ): bool {

		foreach ( $history as $item ) {
			if ( ! is_array( $item ) ) {
				return false;
			}

			if ( empty( $item['role'] ) || empty( $item['content'] ) ) {
				return false;
			}

			if ( ! in_array( $item['role'], [ 'user', 'assistant' ], true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get prepared fields response with enriched and decoded field data.
	 *
	 * @since 1.10.1
	 *
	 * @param array $response AI response data containing field changes.
	 * @param int   $form_id  Form ID.
	 *
	 * @return array
	 */
	private function get_prepared_fields_response( array $response, int $form_id ): array {

		if ( ! empty( $response['changes']['fieldsToAdd'] ) ) {
			// Enrich fields with rendered HTML.
			$response['changes']['fieldsToAdd'] = $this->get_enriched_fields( (array) $response['changes']['fieldsToAdd'], $form_id );

			// Replace AI-assigned IDs with real IDs in the message text.
			$response = $this->replace_ai_ids_in_message( $response );
		}

		if ( empty( $response['changes']['fieldsToUpdate'] ) ) {
			return $response;
		}

		// Sanitize fields data.
		foreach ( $response['changes']['fieldsToUpdate'] as $key => $field_data ) {
			$field_data = $this->get_sanitized_field_data( $field_data );

			// Re-pack choices into a sequential 0-indexed array so the JSON wire
			// stays a JS Array (not an Object) for `applyListUpdate()`. JS writes
			// its own 1-indexed `data-key` attributes on rebuild.
			if ( ! empty( $field_data['choices'] ) && is_array( $field_data['choices'] ) ) {
				$field_data['choices'] = $this->get_sanitized_choices( $field_data['choices'], 0 );
			}

			$response['changes']['fieldsToUpdate'][ $key ] = $field_data;
		}

		return $response;
	}

	/**
	 * Replace AI-assigned field IDs with real field IDs in the response message.
	 *
	 * The LLM generates message text referencing its own temporary IDs (e.g. "Email (ID #5)").
	 * After field enrichment assigns real IDs, this method replaces those references
	 * so the user sees the actual field IDs in the chat.
	 *
	 * @since 1.10.1
	 *
	 * @param array $response AI response with enriched fieldsToAdd and message.
	 *
	 * @return array Response with updated message text.
	 */
	private function replace_ai_ids_in_message( array $response ): array {

		if ( empty( $response['message'] ) || ! is_array( $response['message'] ) ) {
			return $response;
		}

		// Build ai_id → real id map from enriched fields.
		$id_map = [];

		foreach ( (array) $response['changes']['fieldsToAdd'] as $field ) {
			if ( isset( $field['ai_id'], $field['id'] ) && $field['ai_id'] !== $field['id'] ) {
				$id_map[ $field['ai_id'] ] = $field['id'];
			}
		}

		if ( empty( $id_map ) ) {
			return $response;
		}

		foreach ( [ 'title', 'text', 'notice', 'footer' ] as $key ) {
			if ( empty( $response['message'][ $key ] ) || ! is_string( $response['message'][ $key ] ) ) {
				continue;
			}

			$response['message'][ $key ] = preg_replace_callback(
				'/\(ID\s*#(\d+)\)/',
				static function ( array $matches ) use ( $id_map ) {
					$ai_id = (int) $matches[1];

					return isset( $id_map[ $ai_id ] )
						? '(ID #' . $id_map[ $ai_id ] . ')'
						: $matches[0];
				},
				$response['message'][ $key ]
			);
		}

		return $response;
	}

	/**
	 * Get fields enriched with rendered HTML.
	 *
	 * @since 1.10.1
	 *
	 * @param array $fields  Fields to add data from AI response.
	 * @param int   $form_id Form ID.
	 *
	 * @return array
	 */
	private function get_enriched_fields( array $fields, int $form_id ): array {

		foreach ( $fields as $key => $field_data ) {
			$field_data = $this->get_sanitized_field_data( (array) $field_data );

			// Re-index choices from 1 before HTML is rendered server-side.
			// New SFE fields are inserted via `preview_html` / `options_html`,
			// so 1-indexed keys are baked into the markup and survive the form save (#17447).
			if ( ! empty( $field_data['choices'] ) && is_array( $field_data['choices'] ) ) {
				$field_data['choices'] = $this->get_sanitized_choices( $field_data['choices'] );
			}

			$field_data = $this->get_new_field_data_with_html( $field_data, $form_id );

			if ( empty( $field_data ) ) {
				unset( $fields[ $key ] );

				continue;
			}

			$fields[ $key ] = $field_data;
		}

		return $fields;
	}

	/**
	 * Get sanitized field data with null values removed recursively.
	 *
	 * @since 1.10.1
	 *
	 * @param array $field_data Field data from AI response.
	 *
	 * @return array Field data without null values.
	 */
	private function get_sanitized_field_data( array $field_data ): array {

		foreach ( $field_data as $key => $value ) {
			if ( $value === null ) {
				unset( $field_data[ $key ] );

				continue;
			}

			if ( is_array( $value ) ) {
				$field_data[ $key ] = $this->get_sanitized_field_data( $value );
			}
		}

		return $field_data;
	}

	/**
	 * Get sanitized choices with sequential numeric keys.
	 *
	 * The AI middleware can return sparse or 0-indexed choices, but WPForms
	 * expects sequential keys — payment radio/select fields use the key as the
	 * submitted input value and bail out in format() when the value is "0"
	 * (PHP's empty( "0" ) === true).
	 *
	 * The `fieldsToAdd` path uses the default 1-indexed start because the
	 * sanitized array feeds server-rendered preview/options HTML, where the
	 * keys become persisted choice indices. The `fieldsToUpdate` path uses a
	 * 0-indexed start because the array is JSON-encoded on the wire and JS
	 * `applyListUpdate()` expects a proper Array (not an Object) — JS writes
	 * its own 1-indexed `data-key` attributes on rebuild.
	 *
	 * @since 1.10.1
	 *
	 * @param array $choices     Choices array from AI response.
	 * @param int   $start_index First numeric key in the returned array.
	 *
	 * @return array Choices re-indexed starting at $start_index.
	 */
	private function get_sanitized_choices( array $choices, int $start_index = 1 ): array {

		$sanitized = [];
		$index     = $start_index;

		foreach ( $choices as $choice ) {
			$sanitized[ $index ] = $choice;

			++$index;
		}

		return $sanitized;
	}

	/**
	 * Get the new field data with rendered HTML (preview and options).
	 *
	 * Delegates to WPForms_Field::get_new_field_preview_html() and
	 * WPForms_Field::get_new_field_options_html() for rendering,
	 * suitable for batch field creation without per-field AJAX overhead.
	 *
	 * @since 1.10.1
	 *
	 * @param array $field_data Field data from AI response.
	 * @param int   $form_id    Form ID.
	 *
	 * @return array Empty array on failure, or array with field, preview, and options keys.
	 */
	private function get_new_field_data_with_html( array $field_data, int $form_id ): array {

		$field_type = sanitize_key( $field_data['type'] ?? '' );

		if ( empty( $field_type ) ) {
			return [];
		}

		// Get the field type object (registered in WPForms_Field::common_hooks()).
		/** This filter is documented in src/Pro/Forms/Fields/Base/EntriesEdit.php. */
		$field_obj = apply_filters( "wpforms_fields_get_field_object_{$field_type}", null ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if ( ! $field_obj ) {
			return [];
		}

		$form_obj = wpforms()->obj( 'form' );

		if ( ! $form_obj ) {
			return [];
		}

		// Store id generated by AI to use it later for field sorting.
		$field_data['ai_id'] = $field_data['id'] ?? null;

		// Assign a new unique field ID.
		$field_data['id'] = $form_obj->next_field_id( $form_id );

		// Allow field ID 0 (first field); reject false/null from next_field_id().
		if ( empty( $field_data['id'] ) && $field_data['id'] !== 0 ) {
			return [];
		}

		// Apply field defaults and filters.
		$field_data                 = $this->get_prepared_field_data( $field_data );
		$field_data['preview_html'] = $field_obj->get_new_field_preview_html( $field_data );
		$field_data['options_html'] = $field_obj->get_new_field_options_html( $field_data );

		return $field_data;
	}

	/**
	 * Get prepared field data with defaults and filters applied.
	 *
	 * @since 1.10.1
	 *
	 * @param array $field_data Raw field data.
	 *
	 * @return array Prepared field data.
	 */
	private function get_prepared_field_data( array $field_data ): array {

		/** This filter is documented in includes/fields/class-base.php. */
		$field_data = (array) apply_filters( 'wpforms_field_new_default', $field_data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		/** This filter is documented in includes/fields/class-base.php. */
		$field_required = (string) apply_filters( 'wpforms_field_new_required', '', $field_data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if ( ! empty( $field_required ) ) {
			$field_data['required'] = '1';
		}

		return $field_data;
	}

	/**
	 * Check if AJAX is a Smart Form Editor field-creation call.
	 *
	 * @since 1.10.1
	 *
	 * @return bool
	 */
	public static function is_sfe_field_creation_ajax(): bool {

		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return false;
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'wpforms-ai-nonce' ) ) {
			return false;
		}

		if ( empty( $_POST['action'] ) ) {
			return false;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );

		return $action === 'wpforms_ai_form_editor_process';
	}
}
