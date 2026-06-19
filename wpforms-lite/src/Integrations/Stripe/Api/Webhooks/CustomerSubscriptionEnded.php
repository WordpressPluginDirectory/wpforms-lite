<?php

namespace WPForms\Integrations\Stripe\Api\Webhooks;

use RuntimeException;

/**
 * Mark a Stripe subscription as completed when it reaches its scheduled end.
 *
 * @since 1.10.2
 */
class CustomerSubscriptionEnded extends Base {

	/**
	 * Inject the subscription event payload before calling handle().
	 *
	 * @since 1.10.2
	 *
	 * @param object $data Stripe event data (matches StripeEvent::$data shape).
	 *
	 * @return self
	 */
	public function set_data( object $data ): self {

		$this->data = $data;

		return $this;
	}

	/**
	 * Mark the subscription as completed.
	 *
	 * @since 1.10.2
	 *
	 * @throws RuntimeException If payment isn't updated.
	 *
	 * @return bool
	 */
	public function handle() {

		$payment = wpforms()->obj( 'payment' )->get_by( 'subscription_id', $this->data->object->id );

		if ( ! $payment ) {
			return false;
		}

		if ( ! wpforms()->obj( 'payment' )->update( $payment->id, [ 'subscription_status' => 'completed' ] ) ) {
			throw new RuntimeException( 'Payment not updated' );
		}

		wpforms()->obj( 'payment_meta' )->add_log(
			$payment->id,
			'Stripe subscription completed (reached its scheduled end date).'
		);

		return true;
	}
}
