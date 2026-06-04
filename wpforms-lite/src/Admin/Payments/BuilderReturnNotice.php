<?php

namespace WPForms\Admin\Payments;

use WPForms\Admin\Notice;

/**
 * Queue a "Return to your form" success notice on the Payments settings page
 * after the user completes a connect flow that they started from the form
 * builder.
 *
 * The notice is rendered through the standard {@see Notice::success()} pipeline
 * so it inherits the same look-and-feel as "Settings were successfully saved."
 *
 * @since 1.10.1.1
 */
class BuilderReturnNotice {

	/**
	 * Query arg used to round-trip the source form ID through the connect flow.
	 *
	 * @since 1.10.1.1
	 */
	const FORM_ID_QUERY_ARG = 'wpforms_return_form_id';

	/**
	 * Query arg used by OAuth callbacks to flag the gateway that just connected.
	 *
	 * @since 1.10.1.1
	 */
	const GATEWAY_QUERY_ARG = 'wpforms_connected';

	/**
	 * Queue the notice if the OAuth callback round-tripped this gateway's slug
	 * back to the Payments settings page.
	 *
	 * Thin wrapper around {@see self::maybe_queue()} that handles the
	 * `?wpforms_connected=<slug>` query arg check shared by all OAuth-based
	 * gateways (Stripe, Square).
	 *
	 * @since 1.10.1.1
	 *
	 * @param string $gateway_slug  Gateway slug as emitted by the callback
	 *                              (e.g. "stripe", "square").
	 * @param string $gateway_label Localized gateway name (e.g. "Stripe").
	 */
	public static function maybe_queue_oauth( string $gateway_slug, string $gateway_label ): void {

		if ( ! wpforms_is_admin_page( 'settings', 'payments' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ self::GATEWAY_QUERY_ARG ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( sanitize_key( $_GET[ self::GATEWAY_QUERY_ARG ] ) !== $gateway_slug ) {
			return;
		}

		self::maybe_queue( $gateway_label, $gateway_slug );
	}

	/**
	 * Queue the success notice if the request carries a valid return form ID
	 * and the current user can edit that form.
	 *
	 * @since 1.10.1.1
	 *
	 * @param string $gateway_label Localized gateway name (e.g. "Stripe").
	 * @param string $gateway_slug  Builder payments-panel section slug
	 *                              (e.g. "stripe", "square", "authorize_net").
	 *                              Appended as `&section=…` so the user lands
	 *                              back on the same gateway tab.
	 */
	public static function maybe_queue( string $gateway_label, string $gateway_slug = '' ): void {

		$form_id = self::get_return_form_id();

		if ( $form_id <= 0 ) {
			return;
		}

		if ( ! wpforms_current_user_can( 'edit_form_single', $form_id ) ) {
			return;
		}

		$form = wpforms()->obj( 'form' )->get( $form_id );

		if ( empty( $form ) ) {
			return;
		}

		$builder_url_args = [
			'page'    => 'wpforms-builder',
			'view'    => 'payments',
			'form_id' => $form_id,
		];

		if ( $gateway_slug !== '' ) {
			$builder_url_args['section'] = sanitize_key( $gateway_slug );
		}

		$builder_url = add_query_arg( $builder_url_args, admin_url( 'admin.php' ) );

		$message = sprintf(
			wp_kses(
				/* translators: %1$s - Gateway name, %2$s - Builder URL. */
				__( 'You have successfully connected to <strong>%1$s</strong>. <a href="%2$s">Return to your form</a> to continue editing.', 'wpforms-lite' ),
				[
					'strong' => [],
					'a'      => [ 'href' => [] ],
				]
			),
			esc_html( $gateway_label ),
			esc_url( $builder_url )
		);

		Notice::success(
			$message,
			[
				'slug' => 'wpforms-payments-builder-return-' . $form_id,
			]
		);
	}

	/**
	 * Read and validate the return form ID from the current request.
	 *
	 * @since 1.10.1.1
	 *
	 * @return int Positive form ID, or 0 if missing/invalid.
	 */
	public static function get_return_form_id(): int {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ! empty( $_GET[ self::FORM_ID_QUERY_ARG ] ) ? absint( $_GET[ self::FORM_ID_QUERY_ARG ] ) : 0;
	}
}
