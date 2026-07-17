<?php

namespace WPForms\SetupChecklist;

use WPForms\Integrations\Stripe\Helpers;
use WPForms\SetupWizard\AbstractStripeConnect;

/**
 * Setup Checklist Stripe Connect OAuth handler.
 *
 * Routes the user back to the checklist after they connect Stripe from the
 * payment card, instead of the payments settings page. The whole OAuth
 * request-lifecycle is reused from {@see AbstractStripeConnect}; this class only
 * supplies the checklist-specific kickoff argument, pending transient, Stripe
 * mode, and destination. A checklist-specific transient keeps it isolated from
 * the wizard's parallel handler, so the two never reroute each other's connects.
 *
 * @since 2.0.0
 */
class StripeConnect extends AbstractStripeConnect {

	/**
	 * Query argument that triggers the Stripe OAuth kickoff from the checklist.
	 *
	 * Public so {@see Page} can build the Connect button's kickoff URL against the
	 * same contract this handler listens for.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public const KICKOFF_ARG = 'wpforms_checklist_stripe_kickoff';

	/**
	 * Transient storing the pending Stripe OAuth state for checklist flows.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const TRANSIENT_PENDING = 'wpforms_setup_checklist_stripe_pending';

	/**
	 * Query argument that triggers the Stripe OAuth kickoff.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function kickoff_arg(): string {

		return self::KICKOFF_ARG;
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
	 * Get the Stripe mode for checklist connections.
	 *
	 * Mirrors the payments settings: connect in whichever mode the site's Stripe
	 * test/live toggle is currently set to, so a user testing in test mode does not
	 * get pushed into a live connection.
	 *
	 * @since 2.0.0
	 *
	 * @return string 'live' or 'test'.
	 */
	protected function get_stripe_mode(): string {

		return Helpers::get_stripe_mode();
	}

	/**
	 * Destination after a successful OAuth handshake: back to the checklist page.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_destination_url(): string {

		return add_query_arg(
			'page',
			Page::SLUG,
			admin_url( 'admin.php' )
		);
	}
}
