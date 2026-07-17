<?php

namespace WPForms\SetupWizard;

use WPForms\Integrations\Stripe\Admin\Connect;

/**
 * Shared Stripe Connect OAuth-return handler.
 *
 * Owns the request-lifecycle pattern for connecting Stripe from a wp-admin screen
 * and routing the user back to that screen instead of the payments settings page:
 *
 * 1. Listens for a feature-specific kickoff query argument.
 * 2. Generates the real OAuth URL in wp-admin context and redirects to wpforms.com,
 *    storing a per-user "pending" transient.
 * 3. After OAuth completes, intercepts the Stripe addon's `wp_safe_redirect()` to the
 *    settings page (via the `wp_redirect` filter) and reroutes to the feature's own
 *    destination — keyed on the `stripe_connect=complete` return marker plus the
 *    pending transient, so it never hijacks an unrelated connect (e.g. one started
 *    from the settings page or by another feature).
 *
 * Subclasses supply only the kickoff argument, the pending-transient key, the Stripe
 * mode, and the destination URL. The Setup Wizard and the Setup Checklist each extend
 * this with their own values; the lifecycle itself lives here once.
 *
 * @since 2.0.0
 */
abstract class AbstractStripeConnect {

	/**
	 * Query argument that triggers the OAuth kickoff for this feature.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	abstract protected function kickoff_arg(): string;

	/**
	 * Transient key storing the pending OAuth state for this feature.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	abstract protected function pending_transient(): string;

	/**
	 * Stripe mode ('live' or 'test') to connect in.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	abstract protected function get_stripe_mode(): string;

	/**
	 * Destination URL to route to after a successful OAuth handshake.
	 *
	 * Called once the pending transient is consumed; subclasses may also set their
	 * own resume state here before returning the URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	abstract protected function get_destination_url(): string;

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function hooks(): void {

		// Only register admin hooks in admin context.
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		// Kickoff handler runs before the plugin's OAuth handler (priority 10).
		add_action( 'admin_init', [ $this, 'handle_kickoff' ], 9 );

		// Intercept the plugin's redirect after successful OAuth.
		add_filter( 'wp_redirect', [ $this, 'intercept_oauth_redirect' ], 9 );

		// Fallback for cases where the plugin's handler bails early.
		add_action( 'admin_init', [ $this, 'handle_post_oauth' ], 11 );

		$this->register_extra_hooks();
	}

	/**
	 * Register feature-specific hooks beyond the shared OAuth lifecycle.
	 *
	 * No-op by default; subclasses override to add their own (e.g. the wizard
	 * injects a forced step into its bridge payload).
	 *
	 * @since 2.0.0
	 */
	protected function register_extra_hooks(): void {}

	/**
	 * Handle the Stripe OAuth kickoff request.
	 *
	 * Generates the real OAuth URL in the proper session context and redirects to
	 * wpforms.com. Sets a pending transient so the post-OAuth redirect can route
	 * back to the originating feature.
	 *
	 * @since 2.0.0
	 */
	public function handle_kickoff(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ $this->kickoff_arg() ] ) ) {
			return;
		}

		if ( ! current_user_can( wpforms_get_capability_manage_options() ) ) {
			return;
		}

		if ( ! class_exists( Connect::class ) ) {
			wp_die( esc_html__( 'Stripe integration is not loaded.', 'wpforms-lite' ) );
		}

		$connect   = ( new Connect() )->init();
		$oauth_url = $connect->get_connect_with_stripe_url( $this->get_stripe_mode() );

		set_transient(
			$this->pending_transient(),
			[ 'user_id' => get_current_user_id() ],
			15 * MINUTE_IN_SECONDS
		);

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( $oauth_url );
		exit;
	}

	/**
	 * Intercept the plugin's redirect after successful Stripe OAuth.
	 *
	 * Catches the `wp_safe_redirect()` call in Connect::handle_oauth_handshake()
	 * and reroutes to the feature's destination instead of the Settings page.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $location Redirect URL.
	 *
	 * @return string
	 */
	public function intercept_oauth_redirect( $location ): string {

		$location = (string) $location;

		if ( ! $this->should_reroute() ) {
			return $location;
		}

		return $this->consume_pending_and_get_destination();
	}

	/**
	 * Handle post-OAuth fallback for cases where the plugin bails early.
	 *
	 * If the plugin's OAuth handler fails (e.g. wp_remote_post error) and never
	 * reaches its redirect, this fallback ensures the user still returns to the
	 * originating feature.
	 *
	 * @since 2.0.0
	 */
	public function handle_post_oauth(): void {

		if ( ! $this->should_reroute() ) {
			return;
		}

		wp_safe_redirect( $this->consume_pending_and_get_destination() );
		exit;
	}

	/**
	 * Determine whether the current request should reroute to this feature.
	 *
	 * Reroutes only on a genuine OAuth return (`stripe_connect=complete`) that this
	 * same user kicked off (matching pending transient) — never an unrelated connect.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function should_reroute(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$stripe_connect = isset( $_GET['stripe_connect'] ) ? sanitize_key( $_GET['stripe_connect'] ) : '';

		if ( $stripe_connect !== 'complete' ) {
			return false;
		}

		$pending = get_transient( $this->pending_transient() );

		if ( ! is_array( $pending ) ) {
			return false;
		}

		return (int) ( $pending['user_id'] ?? 0 ) === get_current_user_id();
	}

	/**
	 * Consume the pending transient and return the destination URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function consume_pending_and_get_destination(): string {

		delete_transient( $this->pending_transient() );

		return $this->get_destination_url();
	}
}
