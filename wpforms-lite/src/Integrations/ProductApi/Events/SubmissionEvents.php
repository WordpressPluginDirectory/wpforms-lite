<?php

namespace WPForms\Integrations\ProductApi\Events;

use Throwable;
use WPForms\Integrations\IntegrationInterface;
use WPForms\Integrations\ProductApi\ProductEvents;
use WPForms\Vendor\ProductApi\ProductApi;

/**
 * Report a received entry and a received payment as buffered visitor events.
 *
 * Neither event can send from where it happens: a submission is unauthenticated and a
 * gateway webhook has no user, and either way an outbound request does not belong on that
 * page load. Both write one row to the product events buffer, and FlushProductEventsTask
 * sends the rows in batches with each row's own occurrence time.
 *
 * Nothing is reconstructed for entries or payments received before consent. A Pro form's
 * entry counter is seeded once from the entries table so an old form does not report its
 * next entry as the first; a Lite form has no entry store and replays 1 to 5.
 *
 * @since 2.0.2.1
 */
class SubmissionEvents implements IntegrationInterface {

	/**
	 * Event sent once per accepted submission.
	 *
	 * @since 2.0.2.1
	 */
	public const EVENT_ENTRY = 'entry_received';

	/**
	 * Event sent once per payment row that reaches a paid status.
	 *
	 * @since 2.0.2.1
	 */
	public const EVENT_PAYMENT = 'payment_received';

	/**
	 * Form post meta holding how many entries have been reported, capped at ENTRY_NUMBER_CAP.
	 *
	 * @since 2.0.2.1
	 */
	public const ENTRIES_META = 'wpforms_product_events_entries';

	/**
	 * Payment meta marking a payment row that has been reported.
	 *
	 * @since 2.0.2.1
	 */
	public const PAYMENT_META = 'product_events_received';

	/**
	 * Form post meta Lite bumps on every submission, its only count of a form's entries.
	 *
	 * @since 2.0.2.1
	 */
	private const LITE_COUNTER_META = 'wpforms_entries_count';

	/**
	 * Entry statuses that are drafts, not submissions: Save and Resume's `partial` and
	 * Form Abandonment's `abandoned`.
	 *
	 * @since 2.0.2.1
	 */
	private const DRAFT_STATUSES = [ 'partial', 'abandoned' ];

	/**
	 * Highest entry number reported. The sheet asks for 1 to 5: the fifth entry is the
	 * activation threshold, and past it the stage does not change.
	 *
	 * @since 2.0.2.1
	 */
	public const ENTRY_NUMBER_CAP = 5;

	/**
	 * Statuses that mean money was received. A subscription's own row goes `active`, its
	 * money arrives as renewal rows that go `completed`, so subscription statuses are absent.
	 *
	 * @since 2.0.2.1
	 */
	private const RECEIVED_STATUSES = [ 'completed', 'processed' ];

	/**
	 * Gateway slug to the sheet's provider enum. Both PayPal gateways are one provider.
	 * A slug missing here passes through unchanged, so a new gateway is never dropped.
	 *
	 * @since 2.0.2.1
	 */
	private const PROVIDERS = [
		'stripe'          => 'stripe',
		'square'          => 'square',
		'paypal_commerce' => 'paypal',
		'paypal_standard' => 'paypal',
		'authorize_net'   => 'authorize_net',
	];

	/**
	 * Indicate if the integration is allowed to load.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	public function allow_load(): bool {

		// The same test ProductEvents uses, so the two load or stay out together.
		return class_exists( ProductApi::class );
	}

	/**
	 * Load the integration.
	 *
	 * @since 2.0.2.1
	 */
	public function load(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.2.1
	 */
	private function hooks(): void {

		add_action( 'wpforms_process_complete', [ $this, 'maybe_track_entry' ], 10, 4 );

		// WPForms_DB broadcasts after every payment write, so no gateway and no hook in
		// Payment::update() is needed to see a status transition. The argument order differs.
		add_action( 'wpforms_post_update_payment', [ $this, 'maybe_track_updated_payment' ], 10, 2 );
		add_action( 'wpforms_post_insert_payment', [ $this, 'maybe_track_inserted_payment' ], 10, 2 );
	}

	/**
	 * Report an accepted submission.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array $fields    Fields data.
	 * @param array $entry     Form submission raw data.
	 * @param array $form_data Form data and settings.
	 * @param int   $entry_id  Entry ID, 0 in Lite.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function maybe_track_entry( $fields, $entry, $form_data, $entry_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed

		try {
			$product_events = $this->get_product_events();
			$form_id        = (int) ( $form_data['id'] ?? 0 );

			if ( ! $product_events || $form_id < 1 ) {
				return;
			}

			$product_events->buffer(
				self::EVENT_ENTRY,
				[
					'form_id'      => (string) $form_id,
					'entry_number' => $this->next_entry_number( $form_id, (int) $entry_id ),
				],
				ProductEvents::CONTEXT_VISITOR
			);
		} catch ( Throwable $e ) {
			// This runs inside the visitor's submission. Losing the event costs a row in a
			// funnel; letting it escape costs the visitor their submission.
			unset( $e );
		}
	}

	/**
	 * Report a payment row that was just updated.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array $data       Columns that were written.
	 * @param int   $payment_id Payment ID.
	 */
	public function maybe_track_updated_payment( $data, $payment_id ): void {

		$this->maybe_track_payment( (int) $payment_id, (array) $data );
	}

	/**
	 * Report a payment row that was just inserted, which some webhooks do already completed.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int   $payment_id Payment ID.
	 * @param array $data       Columns that were written.
	 */
	public function maybe_track_inserted_payment( $payment_id, $data ): void {

		global $wpdb;

		// WPForms_DB::add() returns $wpdb->insert_id read after this hook, so the guard
		// meta and buffer inserts below would hand the caller the wrong payment ID.
		$insert_id = $wpdb->insert_id;

		try {
			$this->maybe_track_payment( (int) $payment_id, (array) $data );
		} finally {
			$wpdb->insert_id = $insert_id;
		}
	}

	/**
	 * Report a payment row once it reaches a received status.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int   $payment_id Payment ID.
	 * @param array $data       Columns that were written.
	 */
	private function maybe_track_payment( int $payment_id, array $data ): void {

		try {
			$status = (string) ( $data['status'] ?? '' );

			// Most writes carry no status, or one that is not money received: a trashed
			// payment restored, a transaction id filled in, a refund. None reads the row.
			if ( $payment_id < 1 || ! in_array( $status, self::RECEIVED_STATUSES, true ) ) {
				return;
			}

			$product_events = $this->get_product_events();

			if ( ! $product_events ) {
				return;
			}

			// get_by(), not get(): get() refuses a caller without manage_options, and a
			// gateway webhook has no user at all.
			$payment = wpforms()->obj( 'payment' )->get_by( 'id', $payment_id );

			if ( ! $this->is_revenue( $payment ) ) {
				return;
			}

			$meta_handler = wpforms()->obj( 'payment_meta' );

			// One event per payment row, whatever the gateway sends afterwards.
			if ( $meta_handler->get_single( $payment_id, self::PAYMENT_META ) ) {
				return;
			}

			// bulk_add() is one INSERT; update_or_add() would repeat the SELECT just made.
			$meta_handler->bulk_add( $payment_id, [ self::PAYMENT_META => '1' ] );

			$product_events->buffer(
				self::EVENT_PAYMENT,
				[
					'form_id'  => (string) (int) $payment->form_id,
					'provider' => $this->get_provider( (string) $payment->gateway ),
				],
				ProductEvents::CONTEXT_VISITOR
			);
		} catch ( Throwable $e ) {
			// This runs inside a gateway webhook or an admin action. A missing analytics
			// row costs less than a failed payment update.
			unset( $e );
		}
	}

	/**
	 * Whether a payment row is revenue: the Dashboard's own rule, live and not trashed.
	 *
	 * @since 2.0.2.1
	 *
	 * @param mixed $payment Payment row, or null.
	 *
	 * @return bool
	 */
	private function is_revenue( $payment ): bool {

		return is_object( $payment )
			&& (int) $payment->form_id > 0
			&& ! empty( $payment->gateway )
			&& $payment->mode === 'live'
			&& (int) $payment->is_published === 1;
	}

	/**
	 * Map a gateway slug onto the sheet's provider enum.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $gateway Gateway slug, as the payments table stores it.
	 *
	 * @return string
	 */
	private function get_provider( string $gateway ): string {

		return self::PROVIDERS[ $gateway ] ?? $gateway;
	}

	/**
	 * Advance the form's entry counter and return the new number, or null past the cap.
	 *
	 * Not atomic: two submissions landing together can both read 2 and both report 3.
	 * One duplicate number in analytics costs less than a lock on every submission.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int $form_id  Form ID.
	 * @param int $entry_id Entry ID of this submission, 0 when it was not stored.
	 *
	 * @return int|null
	 */
	private function next_entry_number( int $form_id, int $entry_id ): ?int {

		$stored = get_post_meta( $form_id, self::ENTRIES_META, true );
		$count  = $stored === '' ? $this->seed_entry_count( $form_id, $entry_id ) : (int) $stored;

		if ( $count >= self::ENTRY_NUMBER_CAP ) {
			// A form seeded past the cap is written once, so its entries are never counted again.
			if ( $stored === '' ) {
				update_post_meta( $form_id, self::ENTRIES_META, self::ENTRY_NUMBER_CAP );
			}

			return null;
		}

		++$count;

		update_post_meta( $form_id, self::ENTRIES_META, $count );

		return $count;
	}

	/**
	 * How many entries the form already has when the counter first runs.
	 *
	 * Without this, every form live before the release would report its next entry as the
	 * first and the data team would read the release as a wave of activations. One count
	 * per form, once. Lite stores no entries but keeps a per-form counter, bumped on
	 * wpforms_process_entry_saved, which fires before wpforms_process_complete: this
	 * submission is already in it.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int $form_id  Form ID.
	 * @param int $entry_id Entry ID of this submission, 0 when it was not stored.
	 *
	 * @return int
	 */
	private function seed_entry_count( int $form_id, int $entry_id ): int {

		if ( ! wpforms()->is_pro() ) {
			return max( 0, (int) get_post_meta( $form_id, self::LITE_COUNTER_META, true ) - 1 );
		}

		$entry_handler = wpforms()->obj( 'entry' );
		$count         = (int) $entry_handler->get_entries( [ 'form_id' => $form_id ], true );

		// Form Abandonment and Save and Resume store what they capture as entries too, and
		// a plain count keeps everything but spam and trash, so a form that only ever
		// collected drafts would otherwise never report its real first entry.
		$drafts = (int) $entry_handler->get_entries(
			[
				'form_id' => $form_id,
				'status'  => self::DRAFT_STATUSES,
			],
			true
		);

		$count = max( 0, $count - $drafts );

		// The entry row is saved before wpforms_process_complete fires, so when this
		// submission was stored it is already in the count and must not be counted twice.
		return $entry_id > 0 ? max( 0, $count - 1 ) : $count;
	}

	/**
	 * Resolve the product events instance, or null when the site has not consented.
	 *
	 * @since 2.0.2.1
	 *
	 * @return ProductEvents|null
	 */
	private function get_product_events(): ?ProductEvents {

		$product_events = wpforms()->obj( 'ProductApi\ProductEvents' );

		return $product_events instanceof ProductEvents && $product_events->is_enabled() ? $product_events : null;
	}
}
