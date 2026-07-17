<?php

namespace WPForms\SetupWizard\Service;

use WPForms\Integrations\LiteConnect\LiteConnect;
use WPForms\Integrations\PayPalCommerce\Connection as PayPalCommerceConnection;
use WPForms\Integrations\Square\Helpers as SquareHelpers;
use WPForms\Integrations\Stripe\Admin\Connect as StripeAdminConnect;
use WPForms\Integrations\Stripe\Helpers as StripeHelpers;
use WPFormsAuthorizeNet\Helpers as AuthorizeNetHelpers;

/**
 * Setup Wizard settings detector.
 *
 * Reports boolean customer-site flags: whether Lite Connect is enabled and whether each
 * supported payment gateway (Stripe, PayPal Commerce, Square, and the Pro Authorize.Net
 * addon) is connected for the current payment mode. The wizard's `/hydrate` uses the Lite Connect + Stripe flags; the
 * setup checklist uses all gateways. Kept separate from `PluginDetector` so `StateManager`
 * can compose detection collaborators without owning any detection logic itself.
 *
 * @since 2.0.0
 */
class SettingsDetector {

	/**
	 * Whether Lite Connect is enabled on the WPForms Settings page.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_lite_connect_enabled(): bool {

		if ( class_exists( LiteConnect::class ) ) {
			return (bool) LiteConnect::is_enabled();
		}

		return (bool) wpforms_setting( 'lite-connect-enabled' );
	}

	/**
	 * Whether Stripe is configured for the current payment mode on this install.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_stripe_configured(): bool {

		if ( StripeHelpers::has_stripe_keys() ) {
			return true;
		}

		return ! empty( ( new StripeAdminConnect() )->get_connected_user_id() );
	}

	/**
	 * Whether PayPal Commerce is connected for the current payment mode.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_paypal_commerce_configured(): bool {

		$connection = PayPalCommerceConnection::get();

		return $connection !== null && $connection->is_configured();
	}

	/**
	 * Whether Square is connected for the current payment mode.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_square_configured(): bool {

		return SquareHelpers::is_square_configured();
	}

	/**
	 * Whether Authorize.Net is connected for the current payment mode.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_authorize_net_configured(): bool {

		return class_exists( AuthorizeNetHelpers::class ) && AuthorizeNetHelpers::has_authorize_net_keys();
	}
}
