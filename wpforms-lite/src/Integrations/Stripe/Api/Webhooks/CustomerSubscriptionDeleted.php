<?php

namespace WPForms\Integrations\Stripe\Api\Webhooks;

use RuntimeException;
use WPForms\Db\Payments\UpdateHelpers;

/**
 * Webhook customer.subscription.deleted class.
 *
 * @since 1.8.4
 */
class CustomerSubscriptionDeleted extends Base {

	/**
	 * Handle the Webhook's data.
	 *
	 * @since 1.8.4
	 *
	 * @throws RuntimeException If payment not found or not updated.
	 *
	 * @return bool
	 */
	public function handle() {

		$payment = wpforms()->obj( 'payment' )->get_by( 'subscription_id', $this->data->object->id );

		if ( ! $payment ) {
			return false;
		}

		if ( isset( $this->data->object->metadata->canceled_by ) && $this->data->object->metadata->canceled_by === 'wpforms_dashboard' ) {
			return false;
		}

		if ( $this->is_ended() ) {
			return ( new CustomerSubscriptionEnded() )
				->set_data( $this->data )
				->handle();
		}

		if ( ! UpdateHelpers::cancel_subscription( $payment->id, 'Stripe subscription cancelled from the Stripe dashboard.' ) ) {
			throw new RuntimeException( 'Payment not updated' );
		}

		return true;
	}

	/**
	 * Whether the deleted subscription reached its scheduled end date.
	 *
	 * When the schedule reaches
	 * its last phase, Stripe places a `cancel_at` timestamp on the
	 * subscription; when the clock reaches that date Stripe deletes the
	 * subscription and fires this event with `ended_at` equal to `cancel_at`.
	 *
	 * @since 1.10.2
	 *
	 * @return bool
	 */
	private function is_ended(): bool {

		$sub = $this->data->object;

		return ! empty( $sub->schedule )
			&& ! empty( $sub->cancel_at )
			&& ! empty( $sub->ended_at )
			&& (int) $sub->ended_at >= (int) $sub->cancel_at;
	}
}
