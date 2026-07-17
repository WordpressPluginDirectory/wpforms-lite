<?php

namespace WPForms\Integrations\AI\API;

use WPForms\Integrations\AI\API\Http\Response;

/**
 * AI Chat middleware client.
 *
 * POSTs the assembled context to the universal `/api/v1/ai-chat` endpoint.
 * Receives either a final `message` envelope or a `tool_calls` directive —
 * the caller (AJAX handler) drives the round loop.
 *
 * @since 2.0.0
 */
class Chat extends API {

	/**
	 * Universal chat endpoint path. Leading slash is required — `Request::get_request_url()`
	 * concatenates `self::URL . $endpoint`.
	 *
	 * @since 2.0.0
	 */
	private const ENDPOINT = '/ai-chat';

	/**
	 * POST a chat request and return the decoded response.
	 *
	 * The middleware dispatches on a required `mode` field: `'classify'` picks
	 * the minimum scope set needed for the user's question, `'answer'` (with
	 * possible `tool_calls` sub-rounds) produces the final reply. Callers run
	 * classify once per user turn, then answer rounds until a terminal `message`.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $context         Assembled context payload.
	 * @param string $user_prompt     The user's prompt for this exchange.
	 * @param string $session_id      Per-page-load session identifier.
	 * @param array  $history         Conversation history (role/content pairs).
	 * @param string $batch_id        Empty on the first call; echoed back on follow-ups to claim rate-limit exemption.
	 * @param string $mode            Round discriminator — `'classify'` or `'answer'`.
	 * @param string $reclassify_hint Scope hint from a prior reclassify tool call. Only meaningful for classify mode.
	 *
	 * @return array
	 */
	public function chat(
		array $context,
		string $user_prompt,
		string $session_id,
		array $history,
		string $batch_id = '',
		string $mode = 'answer',
		string $reclassify_hint = ''
	): array {

		$body = [
			'mode'            => $mode,
			'userPrompt'      => $user_prompt,
			'scopes'          => $context['scopes'] ?? [],
			'availableScopes' => $context['availableScopes'] ?? [],
			'context'         => $context,
			'history'         => $history,
			'sessionId'       => $session_id,
		];

		if ( $reclassify_hint !== '' ) {
			$body['reclassifyHint'] = $reclassify_hint;
		}

		$response = $this->request->post( self::ENDPOINT, $body, $this->build_request_headers( $batch_id ) );

		if ( $response->has_errors() ) {
			return $this->build_error_response( $response );
		}

		return wp_parse_args( $response->get_body(), $this->response_defaults() );
	}

	/**
	 * Build the request headers for a chat request.
	 *
	 * Batch ID propagates the classify round's `responseId` to the answer round
	 * via the `x-wpforms-batch-id` request header — middleware uses it to claim
	 * the rate-limit exemption granted by the prior classify.
	 *
	 * @since 2.0.0
	 *
	 * @param string $batch_id Batch ID from the prior classify round; empty to omit the header.
	 *
	 * @return array Request headers.
	 */
	private function build_request_headers( string $batch_id ): array {

		$headers = [];

		if ( $batch_id !== '' ) {
			$headers['x-wpforms-batch-id'] = $batch_id;
		}

		return $headers;
	}

	/**
	 * Build the error response envelope from a failed request.
	 *
	 * @since 2.0.0
	 *
	 * @param Response $response Failed API response.
	 *
	 * @return array
	 */
	private function build_error_response( Response $response ): array {

		$error_data = $response->get_error_data();

		return array_merge(
			$this->response_defaults(),
			[
				'error'      => (string) ( $error_data['error'] ?? '' ),
				'error_code' => (int) ( $error_data['code'] ?? 0 ),
			]
		);
	}

	/**
	 * The canonical chat response shape, with every field defaulting to null.
	 *
	 * Shared by the success path (as `wp_parse_args` defaults) and the error path
	 * so both return the same key set.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	private function response_defaults(): array {

		return [
			'message'        => null,
			'tool_calls'     => null,
			'error'          => null,
			'sessionId'      => null,
			'responseId'     => null,
			'selectedScopes' => null,
		];
	}
}
