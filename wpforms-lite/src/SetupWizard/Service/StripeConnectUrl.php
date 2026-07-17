<?php

namespace WPForms\SetupWizard\Service;

use WP_Error;
use WPForms\Integrations\Stripe\Admin\Connect;

/**
 * Setup Wizard Stripe Connect URL provider.
 *
 * Pure, request-lifecycle-free collaborator the REST layer depends on. Returns
 * the kickoff URL the SPA sends the browser to; the actual OAuth URL generation
 * and redirect handling live in the StripeConnect handler, which runs in the
 * wp-admin context where the nonce is bound to the correct session.
 *
 * @since 2.0.0
 */
class StripeConnectUrl {

	/**
	 * Query argument that triggers the Stripe OAuth kickoff.
	 *
	 * Shared contract between this service, which builds the kickoff URL, and
	 * the StripeConnect handler, which listens for it on `admin_init`.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public const KICKOFF_ARG = 'wpforms_setup_wizard_stripe_kickoff';

	/**
	 * Get the Stripe Connect URL for the wizard.
	 *
	 * Returns a kickoff URL rather than the OAuth URL directly. The kickoff URL
	 * defers OAuth URL generation to the wp-admin handler so the nonce is bound
	 * to the correct session context.
	 *
	 * @since 2.0.0
	 *
	 * @return array{connect_url: string}|WP_Error
	 */
	public function get_connect_url() {

		if ( ! class_exists( Connect::class ) ) {
			return new WP_Error(
				'wpforms_stripe_unavailable',
				esc_html__( 'Stripe integration is not loaded.', 'wpforms-lite' ),
				[ 'status' => 500 ]
			);
		}

		// Note: No nonce here. The REST endpoint runs in a token-auth context
		// (no cookie session), while the user visits this URL with cookie auth.
		// Nonces are bound to session tokens, so cross-context verification fails.
		// Security relies on: capability check, Stripe consent, user-bound transient.
		$kickoff_url = add_query_arg(
			[ self::KICKOFF_ARG => 1 ],
			admin_url( 'admin.php' )
		);

		return [ 'connect_url' => $kickoff_url ];
	}
}
