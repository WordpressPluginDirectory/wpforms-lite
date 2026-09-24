<?php

namespace WPForms\Integrations\ProductApi;

use Throwable;
use WPForms\Db\ProductEvents\Buffer;
use WPForms\Integrations\IntegrationInterface;
use WPForms\Integrations\UsageTracking\UsageTracking;
use WPForms\Tasks\Actions\FlushProductEventsTask;
use WPForms\Vendor\ProductApi\Events\Event;
use WPForms\Vendor\ProductApi\Events\EventsManager;
use WPForms\Vendor\ProductApi\Events\EventTracker;
use WPForms\Vendor\ProductApi\ProductApi;

/**
 * Product events tracking.
 *
 * Boots the Product API client once per request, on Lite and on Pro alike, and
 * exposes the two entry points the rest of the plugin uses to report an event.
 * Which entry point applies depends on where the event came from, not on what
 * it describes: track() for anything an admin did, track_system() for anything
 * replayed later by a background task.
 *
 * @since 2.0.2.1
 */
class ProductEvents implements IntegrationInterface {

	/**
	 * Production Product API base URL.
	 *
	 * @since 2.0.2.1
	 */
	public const API_URL = 'https://wpformsapi.com';

	/**
	 * Plugin slug passed to the client.
	 *
	 * Must be the literal `wpforms`. The Product API builds the site-ownership
	 * verification parameter from the slugified product name, which resolves to
	 * this value, and the client derives the option name, the AJAX action, the
	 * script handle and the JS global from it as well.
	 *
	 * @since 2.0.2.1
	 */
	public const PLUGIN_SLUG = 'wpforms';

	/**
	 * Event context for anything attributable to the logged-in user.
	 *
	 * Declared on Buffer, which owns the stored shape.
	 *
	 * @since 2.0.2.1
	 */
	public const CONTEXT_USER = Buffer::CONTEXT_USER;

	/**
	 * Event context for anything an anonymous visitor did.
	 *
	 * Keeps the client from attaching identity: EventTracker only sends `user_info`
	 * when the context is exactly `user`, and its fallback there resolves the site
	 * administrator, which would put an admin's name and email on a form submission
	 * made by somebody else.
	 *
	 * @since 2.0.2.1
	 */
	public const CONTEXT_VISITOR = Buffer::CONTEXT_VISITOR;

	/**
	 * Event context for anything the site did with nobody logged in.
	 *
	 * What a `user` event becomes when no user is logged in: WP-Cron publishing a
	 * scheduled page, WP-CLI, Action Scheduler. Like `visitor`, it keeps the client from
	 * attaching identity, so the site administrator is not credited with the action.
	 *
	 * @since 2.0.2.1
	 */
	public const CONTEXT_SYSTEM = Buffer::CONTEXT_SYSTEM;

	/**
	 * AJAX action the client registers for events reported from JavaScript.
	 *
	 * Derived by the client from the plugin slug; repeated here because the guard
	 * below has to hook the same name.
	 *
	 * @since 2.0.2.1
	 */
	public const AJAX_ACTION = 'wpforms_product_events_log';

	/**
	 * Script handle the client registers for events reported from JavaScript.
	 *
	 * Derived by the client from the plugin slug, same as the action above, and
	 * repeated for the same reason: every screen that wants events has to enqueue
	 * this exact name.
	 *
	 * @since 2.0.2.1
	 */
	public const SCRIPT_HANDLE = self::PLUGIN_SLUG . '-product-events';

	/**
	 * Buffer instance.
	 *
	 * Held rather than built per call: WPForms_DB registers a `query` filter in its
	 * constructor, so every extra instance adds a callback that runs against every
	 * remaining query in the request.
	 *
	 * @since 2.0.2.1
	 *
	 * @var Buffer
	 */
	private $buffer;

	/**
	 * Event tracker: null until resolved, false when the client is unusable.
	 *
	 * @since 2.0.2.1
	 *
	 * @var EventTracker|false|null
	 */
	private $tracker;

	/**
	 * Indicate if the integration is allowed to load.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	public function allow_load(): bool {

		return class_exists( ProductApi::class );
	}

	/**
	 * Load the integration.
	 *
	 * @since 2.0.2.1
	 */
	public function load(): void {

		try {
			ProductApi::configure( $this->get_config() )
				->with_events( [ 'log_events_cap' => 'manage_options' ] )
				->boot();
		} catch ( Throwable $e ) {
			// ProductApi::configure() throws when something configured the client first. Tracking
			// is not worth taking the site down for, so the request continues and get_tracker()
			// reports the failure once, when an event is actually sent.
			unset( $e );
		}

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.2.1
	 */
	private function hooks(): void {

		// The client registers its AJAX endpoint unconditionally and its handler goes
		// straight to the tracker, so consent has to be enforced in front of it. Runs
		// before the client's own callback, which sits on the default priority.
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_check_consent' ], 1 );

		add_action(
			'wpforms_settings_updated',
			function () {

				if ( ! $this->is_enabled() ) {
					FlushProductEventsTask::unschedule();
				}
			}
		);

		if ( ! $this->is_enabled() ) {
			return;
		}

		add_filter(
			'wpforms_tasks_get_tasks',
			static function ( $tasks ) {

				// Cast first: an earlier filter returning a non-array would otherwise
				// deprecate on PHP 8.1 and fatal beyond it.
				$tasks   = (array) $tasks;
				$tasks[] = FlushProductEventsTask::class;

				return $tasks;
			}
		);
	}

	/**
	 * Refuse client-side events while tracking is switched off.
	 *
	 * @since 2.0.2.1
	 */
	public function ajax_check_consent(): void {

		if ( $this->is_enabled() ) {
			return;
		}

		wp_send_json_error();
	}

	/**
	 * Track an event originating from an admin request.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $name       Event name, snake_case.
	 * @param array  $properties Event properties.
	 * @param string $context    Event context.
	 *
	 * @return bool Whether the event reached the tracker.
	 */
	public function track( string $name, array $properties = [], string $context = self::CONTEXT_USER ): bool {

		if ( ! $this->is_allowed() ) {
			return false;
		}

		return $this->send( $name, $properties, $context );
	}

	/**
	 * Track an event that no admin screen is behind.
	 *
	 * Consent is the only gate, because the two callers have no capability to test:
	 * the buffered flush runs with no user at all, and a form becoming reachable is
	 * the work of whoever publishes the page, as often an editor as an administrator.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $name        Event name, snake_case.
	 * @param array  $properties  Event properties.
	 * @param string $context     Event context.
	 * @param string $occurred_at When the event happened, UTC `Y-m-d H:i:s`. Empty for
	 *                             anything happening now.
	 *
	 * @return bool Whether the event reached the tracker. False means the caller still
	 *              owns it — the flush uses this to decide which buffer rows to drop.
	 */
	public function track_system( string $name, array $properties = [], string $context = self::CONTEXT_USER, string $occurred_at = '' ): bool {

		if ( ! $this->is_allowed_system() ) {
			return false;
		}

		return $this->send( $name, $properties, $context, $occurred_at );
	}

	/**
	 * Buffer an event that cannot be sent on the request that produced it.
	 *
	 * Anonymous form submissions land here: there is no admin context, no logged-in
	 * user, and the submission must not pay for an outbound request. The row is
	 * handed over later by FlushProductEventsTask.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $name       Event name, snake_case.
	 * @param array  $properties Event properties.
	 * @param string $context    Event context.
	 */
	public function buffer( string $name, array $properties = [], string $context = self::CONTEXT_VISITOR ): void {

		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->get_buffer()->add( $name, $properties, $context );
	}

	/**
	 * Enqueue the script that exposes the JS reporting global on an admin screen.
	 *
	 * The client registers the handle with an empty `src` and attaches the code with
	 * `wp_add_inline_script()`, so nothing is printed until it is enqueued. Consent is
	 * checked here rather than by the caller, for the same reason track() and buffer()
	 * check it: a screen asks for events, it does not decide whether it may have them.
	 *
	 * @since 2.0.2.1
	 */
	public function enqueue_script(): void {

		if ( ! $this->is_enabled() ) {
			return;
		}

		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * Whether an admin-originated event may be tracked.
	 *
	 * A capability gate, not a location one. `is_admin()` is true for every
	 * admin-ajax.php request, an anonymous form submission included, so what actually
	 * separates the two paths is the logged-in check and `manage_options` below. On top
	 * of that it refuses cron and WP-CLI, where there is no user to attribute to.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	public function is_allowed(): bool {

		if ( wp_doing_cron() || wpforms_doing_wp_cli() ) {
			return false;
		}

		if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return $this->is_enabled();
	}

	/**
	 * Whether a buffered event may be handed over.
	 *
	 * Consent is the only check here. The flush runs from Action Scheduler, so
	 * there is no current user to test a capability on, and cron is the very
	 * context is_allowed() refuses.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	public function is_allowed_system(): bool {

		return $this->is_enabled();
	}

	/**
	 * Whether product events tracking is enabled.
	 *
	 * Read on every call rather than cached at load time. Consent is granted
	 * partway through the Setup Wizard request, and StateManager::complete()
	 * applies those settings before it fires its completion action, so a value
	 * captured on wpforms_loaded is already stale by the time the first event
	 * of a fresh Lite site fires.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {

		$usage_tracking = wpforms()->obj( 'UsageTracking\\UsageTracking' );

		if ( ! $usage_tracking instanceof UsageTracking ) {
			return false;
		}

		$is_enabled = $usage_tracking->allow_load() && $usage_tracking->is_enabled() && $this->can_send();

		/**
		 * Whether product events tracking is enabled.
		 *
		 * Separate from the usage tracking gate it reads, so product events can be
		 * switched off without switching off usage tracking itself.
		 *
		 * @since 2.0.2.1
		 *
		 * @param bool $is_enabled Whether tracking is enabled.
		 */
		return (bool) apply_filters( 'wpforms_integrations_product_api_product_events_is_enabled', $is_enabled );
	}

	/**
	 * Whether the client would send at all.
	 *
	 * The client's own rule, applied up front: on Pro it refuses to send without a key
	 * the license server accepted, and it refuses at shutdown, long after track() has
	 * returned and the once-guards are written. Pro forces usage tracking on, so without
	 * this a Pro site with a missing or expired license would keep writing guards for
	 * events that never left and never will, and fill the buffer to its cap.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	private function can_send(): bool {

		return ! wpforms()->is_pro() || wpforms_is_license_valid();
	}

	/**
	 * Hand an event to the client.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $name        Event name.
	 * @param array  $properties  Event properties.
	 * @param string $context     Event context.
	 * @param string $occurred_at When the event happened, UTC `Y-m-d H:i:s`. Empty for
	 *                             anything happening now.
	 *
	 * @return bool Whether the event was queued on the tracker.
	 */
	private function send( string $name, array $properties, string $context, string $occurred_at = '' ): bool {

		$name          = sanitize_key( $name );
		$event_tracker = $this->get_tracker();

		if ( $name === '' || ! $event_tracker ) {
			return false;
		}

		$event = new Event( $name, Buffer::normalize_properties( $properties ) );

		$event->context( $this->resolve_context( $context ) );

		if ( $occurred_at === '' ) {
			$event_tracker->track( $event );

			return true;
		}

		// A replayed event carries its own time, and two identical rows are two real
		// occurrences, so it must not collapse into the one already queued.
		$event_tracker->track_each( $event->time( $occurred_at ) );

		return true;
	}

	/**
	 * The context an event is sent in.
	 *
	 * A `user` event with nobody logged in becomes a `system` one. The client attaches
	 * identity to `user` events and, finding no current user, falls back to whoever owns
	 * the site's admin email, so a page WP-Cron publishes or a WP-CLI run would credit the
	 * administrator with work they never did, and warn when no account carries that email.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $context Context the caller asked for.
	 *
	 * @return string
	 */
	public function resolve_context( string $context ): string {

		$context = Buffer::normalize_context( $context );

		if ( $context === self::CONTEXT_USER && get_current_user_id() === 0 ) {
			return self::CONTEXT_SYSTEM;
		}

		return $context;
	}

	/**
	 * Hand the queued events over now and report whether they left.
	 *
	 * The shutdown flush is non-blocking and its result is unreadable, which is no use
	 * to a caller that has to decide whether its own rows may be dropped.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	public function send_now(): bool {

		$event_tracker = $this->get_tracker();

		if ( ! $event_tracker ) {
			return false;
		}

		$response = $event_tracker->send_now();

		// Nothing was queued, so the caller has nothing to drop either.
		if ( $response === null ) {
			return true;
		}

		// A WP_Error means the request never completed: no route, a timeout, or a rate
		// limit that short-circuited before it was made.
		if ( is_wp_error( $response ) ) {
			$this->log_failed_handover( $response->get_error_code(), $response->get_error_message() );

			return false;
		}

		if ( ! $response->is_successful() ) {
			$body = $response->get_body();

			$this->log_failed_handover(
				(string) $response->get_status_code(),
				is_string( $body ) ? $body : (string) wp_json_encode( $body )
			);

			return false;
		}

		return true;
	}

	/**
	 * Log a batch the API did not take.
	 *
	 * The status code is what separates a proxy refusing the request from the API
	 * itself rejecting it, so it is logged apart from the body rather than folded
	 * into one message.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $code   Error code, or the HTTP status.
	 * @param string $detail Error message, or the response body.
	 */
	private function log_failed_handover( string $code, string $detail ): void {

		wpforms_log(
			'Product events were not handed over',
			[
				'code' => $code,
				// A blocking proxy answers with a full HTML page, which has no business
				// filling the logs table.
				'body' => mb_substr( trim( $detail ), 0, 500 ),
			],
			[ 'type' => [ 'error' ] ]
		);
	}

	/**
	 * Get the event tracker.
	 *
	 * Resolved once per request. The client throws when it was never configured or
	 * when events were not enabled on it, which is the only failure mode here.
	 *
	 * @since 2.0.2.1
	 *
	 * @return EventTracker|null
	 */
	private function get_tracker(): ?EventTracker {

		if ( $this->tracker === null ) {
			// Marked unusable up front, so a failure is logged once per request rather than per event.
			$this->tracker = false;

			try {
				$this->tracker = ProductApi::get( EventsManager::class )->get_tracker();
			} catch ( Throwable $e ) {
				wpforms_log(
					'Product events are not available',
					[ 'message' => $e->getMessage() ],
					[ 'type' => [ 'error' ] ]
				);
			}
		}

		return $this->tracker instanceof EventTracker ? $this->tracker : null;
	}

	/**
	 * Get the buffer.
	 *
	 * Held rather than rebuilt, and shared with the flush task, because every Queue
	 * instance adds a `query` filter callback for the rest of the request.
	 *
	 * @since 2.0.2.1
	 *
	 * @return Buffer
	 */
	public function get_buffer(): Buffer {

		if ( ! $this->buffer ) {
			$this->buffer = new Buffer();
		}

		return $this->buffer;
	}

	/**
	 * Get the client configuration.
	 *
	 * @since 2.0.2.1
	 *
	 * @return array
	 */
	private function get_config(): array {

		return [
			'api_url'        => $this->get_api_url(),
			'site_url'       => home_url(),
			// Closures, not values: the client reads them when it sends, on shutdown. A
			// value taken here is the state at boot, and on the request where an admin
			// enters the key that state is "no key", so the client refused to send the
			// very event that reports the activation.
			'license_key'    => static function () {

				return wpforms_get_license_key();
			},
			'license_valid'  => static function () {

				return wpforms_is_license_valid();
			},
			'is_pro'         => wpforms_is_pro(),
			'user_agent'     => wpforms_get_default_user_agent(),
			'environment'    => wp_get_environment_type(),
			'plugin_slug'    => self::PLUGIN_SLUG,
			'plugin_version' => WPFORMS_VERSION,
		];
	}

	/**
	 * Get the Product API base URL.
	 *
	 * Overridable with a constant so the same build can be pointed at the relay,
	 * at the Product API directly, or at a local stack without a release.
	 *
	 * @since 2.0.2.1
	 *
	 * @return string
	 */
	private function get_api_url(): string {

		if ( defined( 'WPFORMS_PRODUCT_API_BASE_URL' ) && WPFORMS_PRODUCT_API_BASE_URL ) {
			return untrailingslashit( WPFORMS_PRODUCT_API_BASE_URL );
		}

		return self::API_URL;
	}
}
