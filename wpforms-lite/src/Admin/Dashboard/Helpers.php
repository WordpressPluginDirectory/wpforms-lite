<?php

namespace WPForms\Admin\Dashboard;

use WPForms\Integrations\Stripe\Helpers as StripeHelpers;
use WPForms\Integrations\Square\Helpers as SquareHelpers;
use WPForms\Integrations\PayPalCommerce\Connection as PayPalCommerceConnection;
use WPFormsAuthorizeNet\Helpers as AuthorizeNetHelpers;
use WPFormsMercadoPago\Helpers as MercadoPagoHelpers;

/**
 * Dashboard shared helpers.
 *
 * @since 2.0.2
 */
class Helpers {

	/**
	 * Get the current user's meta value guaranteed to be an array.
	 *
	 * @since 2.0.2
	 *
	 * @param string $key Meta key.
	 *
	 * @return array
	 */
	public static function get_user_meta_array( string $key ): array {

		$value = get_user_meta( get_current_user_id(), $key, true );

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Update the current user's meta with an array value.
	 *
	 * @since 2.0.2
	 *
	 * @param string $key   Meta key.
	 * @param array  $value Meta value.
	 */
	public static function update_user_meta_array( string $key, array $value ): void {

		update_user_meta( get_current_user_id(), $key, $value );
	}

	/**
	 * Whether any supported payment gateway is connected.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public static function is_payment_gateway_connected(): bool {

		$paypal_connection = PayPalCommerceConnection::get();

		return StripeHelpers::has_stripe_keys()
			|| SquareHelpers::is_square_configured()
			|| ( $paypal_connection && $paypal_connection->is_configured() )
			|| ( class_exists( AuthorizeNetHelpers::class ) && AuthorizeNetHelpers::has_authorize_net_keys() )
			|| ( class_exists( MercadoPagoHelpers::class ) && MercadoPagoHelpers::is_configured() );
	}

	/**
	 * Whether the request asks for a forced cache recompute via `force-check=1`.
	 *
	 * Read from GET on a page load and from POST on the date-range AJAX call, so a
	 * force-checked page view keeps recomputing while the flag stays in the URL.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public static function is_force_refresh(): bool {

		// No nonce check by design: the flag only re-derives data the user is already
		// looking at, mirroring WP core's `force-check` on the updates screen. The
		// capability check below is what gates the recompute.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$force_check = isset( $_GET['force-check'] )
			? absint( $_GET['force-check'] )
			: absint( $_POST['force-check'] ?? 0 );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

		return $force_check === 1 && current_user_can( wpforms_get_capability_manage_options() );
	}
}
