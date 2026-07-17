<?php

namespace WPForms\SetupWizard;

use WPForms\SetupWizard\Service\StripeConnectUrl;

/**
 * Setup Wizard Stripe Connect OAuth handler.
 *
 * Routes the user back into the wizard (resumed at the payments step) after
 * connecting Stripe, instead of the payments settings page. The OAuth
 * request-lifecycle — kickoff, redirect interception, pending-transient guard —
 * lives in {@see AbstractStripeConnect}; this class supplies the wizard-specific
 * kickoff argument, transient, Stripe mode, and destination, and additionally
 * forces the wizard to resume on the payments step via the bridge payload.
 *
 * @since 2.0.0
 */
class StripeConnect extends AbstractStripeConnect {

	/**
	 * Transient storing the pending Stripe OAuth state for wizard flows.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const TRANSIENT_PENDING = 'wpforms_setup_wizard_stripe_pending';

	/**
	 * Transient storing the forced step to resume after OAuth.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const TRANSIENT_FORCE_STEP = 'wpforms_setup_wizard_force_step';

	/**
	 * Query argument used by the main wizard launcher.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const WIZARD_ARG = 'wpforms_setup_wizard';

	/**
	 * Query argument that triggers the Stripe OAuth kickoff.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function kickoff_arg(): string {

		return StripeConnectUrl::KICKOFF_ARG;
	}

	/**
	 * Transient key storing the pending OAuth state.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function pending_transient(): string {

		return self::TRANSIENT_PENDING;
	}

	/**
	 * Get the Stripe mode for wizard connections.
	 *
	 * By default, the wizard connects in live mode. Define the constant
	 * `WPFORMS_SETUP_WIZARD_STRIPE_TEST_MODE` as true to use test mode
	 * for development/testing.
	 *
	 * @since 2.0.0
	 *
	 * @return string 'live' or 'test'.
	 */
	protected function get_stripe_mode(): string {

		$mode = 'live';

		if ( defined( 'WPFORMS_SETUP_WIZARD_STRIPE_TEST_MODE' ) && WPFORMS_SETUP_WIZARD_STRIPE_TEST_MODE ) {
			$mode = 'test';
		}

		/**
		 * Filter the Stripe mode used by the wizard.
		 *
		 * @since 2.0.0
		 *
		 * @param string $mode 'live' or 'test'.
		 */
		return apply_filters( 'wpforms_setup_wizard_stripe_connect_mode', $mode );
	}

	/**
	 * Register wizard-specific hooks beyond the shared OAuth lifecycle.
	 *
	 * @since 2.0.0
	 */
	protected function register_extra_hooks(): void { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		// Inject forced step into bridge payload.
		add_filter( 'wpforms_setup_wizard_bridge_payload', [ $this, 'inject_forced_step' ] );
	}

	/**
	 * Destination after a successful OAuth handshake: relaunch the wizard, resumed
	 * at the payments step.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_destination_url(): string {

		// Set forced step so the wizard resumes at payments.
		set_transient( self::TRANSIENT_FORCE_STEP, 'payments', HOUR_IN_SECONDS );

		return add_query_arg(
			self::WIZARD_ARG,
			1,
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Inject the forced step into the bridge payload.
	 *
	 * After Stripe OAuth completes, the wizard should resume at the payments
	 * step instead of the welcome screen.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $payload Bridge payload.
	 *
	 * @return array
	 */
	public function inject_forced_step( $payload ): array {

		$payload = (array) $payload;

		$forced_step = get_transient( self::TRANSIENT_FORCE_STEP );

		if ( ! empty( $forced_step ) ) {
			$payload['current_step'] = $forced_step;

			delete_transient( self::TRANSIENT_FORCE_STEP );
		}

		return $payload;
	}
}
