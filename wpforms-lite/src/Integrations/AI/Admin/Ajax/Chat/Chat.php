<?php

namespace WPForms\Integrations\AI\Admin\Ajax\Chat;

use WPForms\Integrations\AI\Admin\Ajax\Base;
use WPForms\Integrations\AI\Admin\Chat\ScopeRegistry;
use WPForms\Integrations\AI\API\Chat as ChatAPI;

/**
 * AI Chat AJAX handler.
 *
 * Validates the request, assembles the initial context from each active scope's
 * `page_state_builder`, drives the middleware exchange loop against `API\Chat`,
 * and returns the final `message` envelope.
 *
 * Admin-only — no nopriv variant. Read-only by design.
 *
 * @since 2.0.0
 */
class Chat extends Base {

	/**
	 * AJAX action name. Generic across all scopes; also used as the nonce action.
	 *
	 * @since 2.0.0
	 */
	public const AJAX_ACTION = 'wpforms_ai_chat';

	/**
	 * Maximum chat <-> middleware rounds per request.
	 *
	 * Comparison queries (e.g. "April errors vs May errors") legitimately
	 * consume 2 rounds for sequential data fetches plus 1 answer round.
	 * Allow headroom for one wasted round (model occasionally re-emits
	 * request_data after sufficient data has been gathered) before
	 * surfacing `rounds_exceeded`.
	 *
	 * @since 2.0.0
	 */
	private const MAX_ROUNDS = 5;

	/**
	 * Max server-side reclassify attempts per turn.
	 *
	 * @since 2.0.0
	 */
	private const MAX_RECLASSIFY = 1;

	/**
	 * Generic error response message.
	 *
	 * @since 2.0.0
	 */
	private const ERROR_GENERIC = 'Unable to retrieve the requested data.';

	/**
	 * Chat API client. Replaced via `set_api_for_tests()` for unit tests.
	 *
	 * @since 2.0.0
	 *
	 * @var object Either API\Chat or a test stub exposing `chat()`.
	 */
	protected $api_chat;

	/**
	 * Request context helper for validation and resolution.
	 *
	 * @since 2.0.0
	 *
	 * @var RequestContext
	 */
	private $request_context;

	/**
	 * Turn-scoped tool-call processor. Created at the start of `run_round_loop()`.
	 *
	 * @since 2.0.0
	 *
	 * @var ToolCallProcessor
	 */
	private $tool_processor;

	/**
	 * Initialize.
	 *
	 * @since 2.0.0
	 */
	public function init() {

		parent::init();

		$this->api_chat        = new ChatAPI();
		$this->request_context = new RequestContext();

		$this->api_chat->init();
		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	private function hooks(): void {

		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'process' ] );
	}

	/**
	 * Process an AJAX request from the live admin context.
	 *
	 * Emits the response as an NDJSON stream over chunked transfer:
	 * intermediate `tool_call_start` / `tool_call_end` frames during the
	 * round loop, followed by a terminal `message` or `error` frame.
	 *
	 * @since 2.0.0
	 */
	public function process(): void {

		// Tool-calling can issue multiple sequential middleware round-trips
		// (classify + up to MAX_ROUNDS answer rounds). Each leg is bound to
		// Http\Request::TIMEOUT (60s), so the cumulative AJAX time can exceed
		// hosts' default PHP max_execution_time (often 30s). Raise the floor
		// so long multi-tool turns don't die mid-stream — the symptom is the
		// browser's "Stream closed unexpectedly" protocol error when PHP
		// terminates the script before `flush_frame()` writes the terminal
		// message frame. 120s headroom covers the 4-round case (classify +
		// forms_ranking + parallel form_stats_trend + final answer) we've
		// observed in the wild; bump higher if hosts in the field need it.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payload = (array) wp_unslash( $_POST );

		$this->send_streaming_headers();
		$this->disable_output_buffering();

		$response = $this->process_for_tests( $payload, [ $this, 'flush_frame' ] );

		if ( $response['status'] === 'success' ) {
			$this->flush_frame(
				[
					'v'    => 1,
					'type' => 'message',
				] + (array) $response['data']
			);
		} else {
			$this->flush_frame(
				[
					'v'       => 1,
					'type'    => 'error',
					'code'    => (string) ( $response['code'] ?? 'unknown' ),
					'message' => (string) ( $response['error'] ?? '' ),
				]
			);
		}

		exit;
	}

	/**
	 * Process a request payload. Pure function — does not call wp_send_json_*.
	 *
	 * The live `process()` wraps this for the production AJAX path; tests call
	 * it directly to assert the response shape without going through wp-die.
	 *
	 * @since 2.0.0
	 *
	 * @param array         $payload Request payload (sanitized).
	 * @param callable|null $sink    Optional frame sink invoked on tool-call
	 *                               brackets during the answer-round loop.
	 *                               Receives a single associative array per call.
	 *                               Defaults to a no-op so non-streaming callers
	 *                               (tests, classify-only paths) are unaffected.
	 *
	 * @return array Uniform response envelope `{ status, code, error, data }`.
	 */
	public function process_for_tests( array $payload, ?callable $sink = null ): array {

		$sink = $sink ?? $this->default_frame_sink();

		if ( $this->request_context === null ) {
			$this->request_context = new RequestContext();
		}

		$validation = $this->request_context->validate( $payload, self::AJAX_ACTION );

		if ( $validation !== null ) {
			return $validation;
		}

		$payload = $this->request_context->decode_page_state( $payload );
		$surface = $this->request_context->resolve_surface( $payload );

		// `resolve_surface()` returns an error envelope (has a non-null `status`)
		// when the surface is unknown or the user lacks its capability.
		if ( isset( $surface['status'] ) ) {
			return $surface;
		}

		$requested_scopes = $this->request_context->sanitize_scope_list( $payload['scopes'] ?? [] );
		$active_scopes    = $this->request_context->filter_scopes_by_capability( $requested_scopes );

		if ( $active_scopes === [] ) {
			return $this->error( 'scopes_empty', 'No accessible scopes for this request.' );
		}

		$context = $this->request_context->build_initial_context( $surface, $active_scopes, $payload );

		return $this->run_round_loop( $context, $payload, $active_scopes, $sink );
	}

	/**
	 * Build the no-op frame sink used when no streaming sink is supplied.
	 *
	 * Buffered call sites (tests, classify-only paths) pass no sink, so frames
	 * emitted during the answer-round loop are silently discarded.
	 *
	 * @since 2.0.0
	 *
	 * @return callable Sink that accepts a single frame array and does nothing.
	 */
	private function default_frame_sink(): callable {

		return static function (): void {};
	}

	/**
	 * Emit response headers for chunked NDJSON streaming.
	 *
	 * @since 2.0.0
	 */
	private function send_streaming_headers(): void {

		nocache_headers();
		header( 'Content-Type: application/x-ndjson; charset=utf-8' );

		// Disable nginx response buffering — chunks must reach the client immediately.
		header( 'X-Accel-Buffering: no' );
	}

	/**
	 * Disable output buffering so flushed frames reach the client immediately.
	 *
	 * @since 2.0.0
	 */
	private function disable_output_buffering(): void {

		// Use ob_end_clean (not ob_end_flush) so any HTML/output buffered by other
		// plugins above us is *discarded*, not leaked into our NDJSON stream.
		// Admin AJAX shouldn't accumulate page-render output, but defensive plugins
		// (caching, minification) sometimes open buffers anyway.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// phpcs:ignore WordPress.PHP.IniSet.Risky, WordPress.PHP.NoSilencedErrors.Discouraged
		@ini_set( 'zlib.output_compression', '0' );
	}

	/**
	 * Write one NDJSON frame to the output stream and flush.
	 *
	 * Acts as the frame sink passed into `process_for_tests()` during the live
	 * AJAX path. Aborts the request when the client disconnects.
	 *
	 * Visibility is `public` because it is passed as a callable via
	 * `[ $this, 'flush_frame' ]` — narrowing would break the sink injection.
	 *
	 * @since 2.0.0
	 *
	 * @param array $frame Frame payload.
	 */
	public function flush_frame( array $frame ): void {

		$encoded = wp_json_encode( $frame );

		// Guard against encode failures (UTF-8 issues, etc.). Dropping a terminal frame
		// silently would hang the client; substitute a generic error so the stream
		// always closes with a parseable terminal.
		if ( $encoded === false ) {
			$encoded = wp_json_encode(
				[
					'v'       => 1,
					'type'    => 'error',
					'code'    => 'encode_failed',
					'message' => 'Frame could not be encoded.',
				]
			);
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $encoded is the return value of wp_json_encode(); JSON is the wire format for this streaming endpoint, not HTML.
		echo $encoded . "\n";
		flush();

		if ( connection_aborted() ) {
			exit;
		}
	}

	/**
	 * Inject a stub API for tests.
	 *
	 * Type-hint is intentionally loose so tests can pass anonymous classes that
	 * expose the same `chat()` signature without inheriting from `API\Chat`.
	 *
	 * @since 2.0.0
	 *
	 * @param object $api Anything with a `chat()` method matching `API\Chat::chat()`.
	 */
	public function set_api_for_tests( $api ): void {

		$this->api_chat = $api;
	}

	/**
	 * Drive the chat <-> middleware exchange loop.
	 *
	 * Round 1: classify (unchanged — picks the minimum scope subset).
	 * Rounds 2..N: answer rounds. Middleware returns `{tool_calls | message | error}`.
	 *   - `message` → terminal, emit and return.
	 *   - `tool_calls` → execute each sequentially, append `{role: tool}` messages
	 *     to history, continue.
	 *   - `error` → terminal, emit and return.
	 *
	 * @since 2.0.0
	 *
	 * @param array    $context       Initial assembled context.
	 * @param array    $payload       Original request payload.
	 * @param array    $active_scopes Active scope slugs.
	 * @param callable $sink          Frame sink invoked on tool-call brackets.
	 *
	 * @return array
	 */
	private function run_round_loop( array $context, array $payload, array $active_scopes, callable $sink ): array { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$user_prompt = sanitize_textarea_field( $this->request_context->payload_string( $payload, 'prompt', 'userPrompt' ) );
		$session_id  = sanitize_text_field( $this->request_context->payload_string( $payload, 'sessionId' ) );
		$history     = $this->request_context->sanitize_history( $payload['history'] ?? [] );

		// Per-turn diagnostics attached to the terminal success envelope as `processingData`.
		// The chat element logs it to the console via `wpf.debug()` (debug mode only),
		// mirroring the form editor. `rounds` holds one entry per OpenAI request (the
		// breakdown — wall-clock + middleware time + token usage + tool calls); `time` is
		// the whole-turn wall-clock, which (unlike a sum of per-request server times)
		// includes plugin<->middleware latency and the plugin-side tool execution between
		// rounds.
		$turn_start = microtime( true );
		$telemetry  = [
			'time'   => 0.0,
			'rounds' => [],
			'scopes' => [],
			'tools'  => [],
		];

		// Classify round — picks minimum scope subset.
		$classify = $this->run_classify_round( $context, $active_scopes, $user_prompt, $session_id, $history );

		$telemetry['rounds'][] = $classify['round'];

		if ( $classify['terminal'] !== null ) {
			return $classify['terminal'];
		}

		$context    = $classify['context'];
		$session_id = $classify['session_id'];
		$batch_id   = $classify['batch_id'];

		// Scopes the classify round narrowed the turn to.
		$telemetry['scopes'] = array_values( array_map( 'strval', (array) ( $context['scopes'] ?? [] ) ) );

		$registry = wpforms()->obj( 'ai_chat_scope_registry' );

		$this->tool_processor = $registry instanceof ScopeRegistry
			? new ToolCallProcessor( $registry, $payload, $sink )
			: null;

		// A non-empty $active_scopes guarantees the registry resolved upstream, so this
		// is effectively unreachable today. Guard locally anyway so process_answer_round()
		// can never dereference a null processor if that invariant changes.
		if ( $this->tool_processor === null ) {
			return $this->error( 'registry_unavailable', self::ERROR_GENERIC );
		}

		$reclassify_used = 0;

		// Track every tool-call set hash we've already executed this turn so
		// `process_answer_round()` can fire the stuck-loop short-circuit on any
		// repeat (not just consecutive A→A but also A→B→…→A). The model has the
		// prior tool results in its history, so re-emitting an identical set is
		// always indicative of being stuck.
		$seen_set_hashes = [];

		for ( $round = 1; $round <= self::MAX_ROUNDS; $round++ ) {
			$result = $this->process_answer_round(
				$context,
				$user_prompt,
				$session_id,
				$history,
				$batch_id,
				$seen_set_hashes
			);

			// Sync out-vars updated inside the helper.
			$session_id      = $result['session_id'];
			$seen_set_hashes = $result['seen_set_hashes'];
			$history         = $result['history'];

			$telemetry['rounds'][] = $result['round'];
			$telemetry['tools']    = array_merge( $telemetry['tools'], (array) ( $result['round']['tools'] ?? [] ) );

			if ( $result['terminal'] === null ) {
				continue;
			}

			// Server-side reclassify: the model called the synthetic `reclassify`
			// tool. Re-run classify with the hint and continue the answer loop
			// with the updated scope set instead of punting to the browser.
			if ( isset( $result['terminal'][ ToolCallProcessor::RECLASSIFY_SENTINEL ] ) ) {
				$reclassify = $this->handle_reclassify(
					(string) $result['terminal'][ ToolCallProcessor::RECLASSIFY_SENTINEL ],
					$reclassify_used,
					$context,
					$active_scopes,
					$user_prompt,
					$session_id,
					$history
				);

				// Budget exhausted or classify failed.
				if ( $reclassify === null || ! isset( $reclassify['context'] ) ) {
					$telemetry['time'] = microtime( true ) - $turn_start;

					return $this->attach_processing_data(
						is_array( $reclassify ) ? $reclassify : $this->error( 'reclassify_exhausted', self::ERROR_GENERIC ),
						$telemetry
					);
				}

				$telemetry['rounds'][] = $reclassify['round'];

				++$reclassify_used;
				$context    = $reclassify['context'];
				$session_id = $reclassify['session_id'];
				$batch_id   = $reclassify['batch_id'];

				$telemetry['scopes'] = array_values( array_map( 'strval', (array) ( $context['scopes'] ?? [] ) ) );

				continue;
			}

			$telemetry['time'] = microtime( true ) - $turn_start;

			return $this->attach_processing_data( $result['terminal'], $telemetry );
		}

		return $this->error( 'rounds_exceeded', self::ERROR_GENERIC );
	}

	/**
	 * Handle a server-side reclassify attempt.
	 *
	 * Returns the new classify state bag on success, or null when the
	 * reclassify budget is exhausted. On classify failure the error terminal
	 * is returned directly (the caller should propagate it).
	 *
	 * @since 2.0.0
	 *
	 * @param string $hint            Scope hint from the reclassify tool call.
	 * @param int    $reclassify_used Number of reclassifies already consumed this turn.
	 * @param array  $context         Current assembled context.
	 * @param array  $active_scopes   Active scope slugs.
	 * @param string $user_prompt     Sanitized user prompt.
	 * @param string $session_id      Current session ID.
	 * @param array  $history         Conversation history so far.
	 *
	 * @return array|null Classify state bag on success, null when budget exhausted.
	 */
	private function handle_reclassify( string $hint, int $reclassify_used, array $context, array $active_scopes, string $user_prompt, string $session_id, array $history ): ?array {

		if ( $reclassify_used >= self::MAX_RECLASSIFY ) {
			return null;
		}

		return $this->run_classify_round( $context, $active_scopes, $user_prompt, $session_id, $history, $hint );
	}

	/**
	 * Run the classify round and resolve the narrowed scope context.
	 *
	 * Returns a state bag:
	 *   - `terminal`   (array|null) — non-null error envelope when classify fails.
	 *   - `context`    (array)      — context with `scopes` narrowed to the selection.
	 *   - `session_id` (string)     — updated session ID.
	 *   - `batch_id`   (string)     — rate-limit batch ID for subsequent rounds.
	 *   - `round`      (array)      — per-request diagnostics entry (see `build_round_entry()`).
	 *
	 * @since 2.0.0
	 *
	 * @param array  $context         Initial assembled context.
	 * @param array  $active_scopes   Active scope slugs.
	 * @param string $user_prompt     Sanitized user prompt.
	 * @param string $session_id      Current session ID.
	 * @param array  $history         Conversation history so far.
	 * @param string $reclassify_hint Scope hint from a prior reclassify tool call.
	 *
	 * @return array State bag.
	 */
	private function run_classify_round( array $context, array $active_scopes, string $user_prompt, string $session_id, array $history, string $reclassify_hint = '' ): array {

		$started  = microtime( true );
		$classify = $this->api_chat->chat( $context, $user_prompt, $session_id, $history, '', 'classify', $reclassify_hint );
		$round    = $this->build_round_entry( 'classify', microtime( true ) - $started, $classify );

		if ( ! empty( $classify['error'] ) ) {
			$error_code = ( (int) ( $classify['error_code'] ?? 0 ) ) === 429 ? 'rate_limit' : 'api_error';

			return [
				'terminal'   => $this->error( $error_code, (string) $classify['error'] ),
				'context'    => $context,
				'session_id' => $session_id,
				'batch_id'   => '',
				'round'      => $round,
			];
		}

		if ( ! empty( $classify['sessionId'] ) ) {
			$session_id = (string) $classify['sessionId'];
		}

		$selected = (array) ( $classify['selectedScopes'] ?? [] );
		$selected = array_values( array_intersect( $active_scopes, array_map( 'strval', $selected ) ) );

		if ( $selected !== [] ) {
			$context['scopes'] = $selected;
		}

		return [
			'terminal'   => null,
			'context'    => $context,
			'session_id' => $session_id,
			'batch_id'   => (string) ( $classify['responseId'] ?? '' ),
			'round'      => $round,
		];
	}

	/**
	 * Run one answer round and return a state bag for the caller.
	 *
	 * The returned array always has:
	 *   - `terminal`        (array|null) — non-null when this round ends the loop.
	 *   - `session_id`      (string)     — updated session ID.
	 *   - `seen_set_hashes` (array)      — accumulator of every accepted set hash this turn.
	 *   - `history`         (array)      — updated conversation history.
	 *   - `round`           (array)      — per-request diagnostics entry (see `build_round_entry()`); its `tools` are filled in `process_tool_calls()`.
	 *
	 * @since 2.0.0
	 *
	 * @param array    $context         Assembled context (passed by value — scopes already narrowed).
	 * @param string   $user_prompt     Sanitized user prompt.
	 * @param string   $session_id      Current session ID.
	 * @param array    $history         Conversation history so far.
	 * @param string   $batch_id        Rate-limit batch ID from the classify round.
	 * @param string[] $seen_set_hashes Tool-call set hashes already executed this turn (stuck-loop window).
	 *
	 * @return array State bag.
	 */
	private function process_answer_round( array $context, string $user_prompt, string $session_id, array $history, string $batch_id, array $seen_set_hashes ): array {

		$state = [
			'session_id'      => $session_id,
			'seen_set_hashes' => $seen_set_hashes,
			'history'         => $history,
		];

		$started  = microtime( true );
		$response = $this->api_chat->chat( $context, $user_prompt, $session_id, $history, $batch_id, 'answer' );

		$state['round'] = $this->build_round_entry( 'answer', microtime( true ) - $started, $response );

		if ( ! empty( $response['sessionId'] ) ) {
			$state['session_id'] = (string) $response['sessionId'];
		}

		$state['terminal'] = $this->resolve_answer_terminal( $response, $state['session_id'] );

		if ( $state['terminal'] !== null ) {
			return $state;
		}

		// Scopes the classify round pinned for this turn (capability-filtered upstream
		// in `filter_scopes_by_capability()`). Threaded into dispatch as the tool-call
		// allow-list so an LLM-supplied tool name cannot reach a scope outside the set.
		$allowed_scopes = array_map( 'strval', (array) ( $context['scopes'] ?? [] ) );

		return $this->tool_processor->process( $response, $state, $allowed_scopes );
	}

	/**
	 * Resolve an early terminal envelope from an answer-round response.
	 *
	 * Returns an error envelope when the middleware reports an error, a success
	 * envelope when it returns a final message, or null when the loop should
	 * continue into tool-call dispatch.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $response   Raw answer-round API response.
	 * @param string $session_id Current session ID for the success envelope.
	 *
	 * @return array|null Terminal envelope, or null to continue.
	 */
	private function resolve_answer_terminal( array $response, string $session_id ): ?array {

		if ( ! empty( $response['error'] ) ) {
			$code = ( (int) ( $response['error_code'] ?? 0 ) ) === 429 ? 'rate_limit' : 'api_error';

			return $this->error( $code, (string) $response['error'] );
		}

		if ( ! empty( $response['message'] ) ) {
			return $this->success(
				[
					'message'    => $response['message'],
					'sessionId'  => $session_id,
					// Propagate the middleware's tracked response ID so the chat
					// element can set `data-response-id` on the answer — the rate
					// (like/dislike) endpoint requires it.
					'responseId' => (string) ( $response['responseId'] ?? '' ),
				]
			);
		}

		return null;
	}

	/**
	 * Build a uniform success response envelope.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Response payload.
	 *
	 * @return array
	 */
	private function success( array $data ): array {

		return [
			'status' => 'success',
			'code'   => null,
			'error'  => null,
			'data'   => $data,
		];
	}

	/**
	 * Build a uniform error response envelope.
	 *
	 * The `error` field name (not `message`) matches what api.js / chat-helpers-admin consume.
	 *
	 * @since 2.0.0
	 *
	 * @param string $code  Error code slug.
	 * @param string $error Human-readable error message.
	 *
	 * @return array
	 */
	private function error( string $code, string $error ): array {

		return [
			'status' => 'error',
			'code'   => $code,
			'error'  => $error,
			'data'   => null,
		];
	}

	/**
	 * Build a per-request diagnostics entry for one middleware round-trip.
	 *
	 * Each classify/answer round routes through the middleware's `aiHandler`, which stamps
	 * `processingData.time` (its own processing time, seconds) and `processingData.usage`
	 * (OpenAI token usage) on the response. `time` here is the plugin-measured wall-clock for
	 * the request (network + middleware), `serverTime` the middleware's self-reported figure.
	 *
	 * @since 2.0.0
	 *
	 * @param string $mode     Round mode — `classify` or `answer`.
	 * @param float  $wall     Plugin-measured wall-clock for the request, in seconds.
	 * @param array  $response Raw middleware chat response.
	 *
	 * @return array Round entry `{ mode, time, serverTime, usage, tools }`. `tools` starts empty and is filled by `process_tool_calls()` for answer rounds that dispatch calls.
	 */
	private function build_round_entry( string $mode, float $wall, array $response ): array {

		$processing = (array) ( $response['processingData'] ?? [] );

		return [
			'mode'       => $mode,
			'time'       => number_format( $wall, 3, '.', '' ),
			'serverTime' => isset( $processing['time'] ) ? (string) $processing['time'] : null,
			'usage'      => $processing['usage'] ?? null,
			'tools'      => [],
		];
	}

	/**
	 * Attach the aggregated per-turn diagnostics to a terminal success envelope.
	 *
	 * The chat element logs `response.processingData` to the console via `wpf.debug()`
	 * (debug mode only), matching the form editor. Error envelopes are returned untouched —
	 * only successful answers carry diagnostics. Shape:
	 *   - `time`   — whole-turn wall-clock (seconds): network + middleware + plugin-side tool execution.
	 *   - `rounds` — one entry per OpenAI request: `{ mode, time, serverTime, usage, tools }`.
	 *   - `scopes` — scopes the classify round pinned for the turn.
	 *   - `tools`  — flat list of every dispatched tool-call object across all rounds.
	 *
	 * @since 2.0.0
	 *
	 * @param array $terminal  Terminal response envelope.
	 * @param array $telemetry Aggregated `{ time, rounds, scopes, tools }` accumulator.
	 *
	 * @return array The envelope, with `processingData` added when it is a success.
	 */
	private function attach_processing_data( array $terminal, array $telemetry ): array {

		if ( ( $terminal['status'] ?? '' ) !== 'success' ) {
			return $terminal;
		}

		$terminal['data']['processingData'] = [
			'time'   => number_format( (float) ( $telemetry['time'] ?? 0 ), 3, '.', '' ),
			'rounds' => array_values( (array) $telemetry['rounds'] ),
			'scopes' => array_values( (array) $telemetry['scopes'] ),
			'tools'  => array_values( (array) $telemetry['tools'] ),
		];

		return $terminal;
	}
}
