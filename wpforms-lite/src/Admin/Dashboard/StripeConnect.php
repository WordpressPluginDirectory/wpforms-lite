<?php

namespace WPForms\Admin\Dashboard;

use WPForms\Integrations\Stripe\Helpers;
use WPForms\SetupWizard\AbstractStripeConnect;

/**
 * Dashboard Stripe Connect OAuth handler.
 *
 * Routes the user back to the Dashboard after they connect Stripe from the
 * Payments widget's connect band, instead of the payments settings page. The
 * whole OAuth request-lifecycle is reused from {@see AbstractStripeConnect};
 * this class only supplies the Dashboard-specific kickoff argument, pending
 * transient, Stripe mode, and destination. A Dashboard-specific transient
 * keeps it isolated from the wizard's and checklist's parallel handlers, so
 * none of them reroute each other's connects.
 *
 * @since 2.0.2
 */
class StripeConnect extends AbstractStripeConnect {

	/**
	 * Query argument that triggers the Stripe OAuth kickoff from the Dashboard.
	 *
	 * Public so {@see \WPForms\Admin\Dashboard\Widgets\Payments} can build the
	 * Connect button's kickoff URL against the same contract this handler
	 * listens for.
	 *
	 * @since 2.0.2
	 *
	 * @var string
	 */
	public const KICKOFF_ARG = 'wpforms_dashboard_stripe_kickoff';

	/**
	 * Transient prefix storing the pending Stripe OAuth state for Dashboard flows.
	 *
	 * @since 2.0.2
	 *
	 * @var string
	 */
	private const TRANSIENT_PENDING = 'wpforms_dashboard_stripe_pending_';

	/**
	 * Query argument that triggers the Stripe OAuth kickoff.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	protected function kickoff_arg(): string {

		return self::KICKOFF_ARG;
	}

	/**
	 * Transient key storing the pending OAuth state.
	 *
	 * Scoped to the initiating user: the key is written on kickoff and read back on the
	 * OAuth return, both under the same logged-in user. A single site-wide key let two
	 * administrators connecting at once overwrite each other's pending state, after which
	 * the first one's return failed the user check and rerouted to the wrong user.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	protected function pending_transient(): string {

		return self::TRANSIENT_PENDING . get_current_user_id();
	}

	/**
	 * Get the Stripe mode for Dashboard connections.
	 *
	 * Mirrors the payments settings: connect in whichever mode the site's Stripe
	 * test/live toggle is currently set to, so a user testing in test mode does not
	 * get pushed into a live connection.
	 *
	 * @since 2.0.2
	 *
	 * @return string 'live' or 'test'.
	 */
	protected function get_stripe_mode(): string {

		return Helpers::get_stripe_mode();
	}

	/**
	 * Destination after a successful OAuth handshake: back to the Dashboard.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	protected function get_destination_url(): string {

		return add_query_arg( 'page', 'wpforms-dashboard', admin_url( 'admin.php' ) );
	}
}
