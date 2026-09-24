<?php

namespace WPForms\Tasks\Actions;

use WPForms\Db\ProductEvents\Buffer;
use WPForms\Integrations\ProductApi\ProductEvents;
use WPForms\Tasks\Task;

/**
 * Hand buffered product events over to the Product API.
 *
 * Events written by an anonymous form submission cannot be sent on the request
 * that produced them, so this task replays them from the buffer table. A row is
 * dropped only once the tracker has taken it, so a client that is unusable leaves
 * the work for the next run rather than losing it.
 *
 * @since 2.0.2.1
 */
class FlushProductEventsTask extends Task {

	/**
	 * Action Scheduler action name.
	 *
	 * @since 2.0.2.1
	 */
	public const ACTION = 'wpforms_product_events_flush';

	/**
	 * Interval between runs.
	 *
	 * Each batch is one outbound request, and the client rate-limits itself to 60 per
	 * hour, so the interval, the batch size and the batches per run are one decision.
	 *
	 * @since 2.0.2.1
	 */
	public const INTERVAL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Batches one run hands over before leaving the rest to the next.
	 *
	 * One batch a run capped a site at 400 events an hour, below what a busy form
	 * collects, so the buffer filled to its cap and trimmed. Five batches move 2000 an
	 * hour for 20 of the client's 60 requests, leaving room for the admin events sent
	 * in between, and keep a run inside Action Scheduler's time budget with each
	 * blocking send allowed its five seconds.
	 *
	 * @since 2.0.2.1
	 */
	public const MAX_BATCHES = 5;

	/**
	 * MySQL named lock serialising concurrent flushes.
	 *
	 * The batch is read and deleted in two steps, so two runners overlapping would
	 * send the same rows twice and then both delete them. Action Scheduler makes no
	 * promise that a recurring action runs alone.
	 *
	 * @since 2.0.2.1
	 */
	private const LOCK = 'product_events_flush';

	/**
	 * Log title.
	 *
	 * @since 2.0.2.1
	 *
	 * @var string
	 */
	protected $log_title = 'Product Events Flush';

	/**
	 * Class constructor.
	 *
	 * @since 2.0.2.1
	 */
	public function __construct() {

		parent::__construct( self::ACTION );

		$this->init();
	}

	/**
	 * Register the handler and schedule the action.
	 *
	 * @since 2.0.2.1
	 */
	public function init(): void {

		$this->hooks();

		$tasks = wpforms()->obj( 'tasks' );

		// Not registered yet on an early or partial boot, and scheduling needs it.
		if ( ! $tasks || $tasks->is_scheduled( self::ACTION ) !== false ) {
			return;
		}

		$this->recurring( time() + self::INTERVAL, self::INTERVAL )->register();
	}

	/**
	 * Register the action handler.
	 *
	 * @since 2.0.2.1
	 */
	private function hooks(): void {

		add_action( self::ACTION, [ $this, 'process' ] );
	}

	/**
	 * Hand over one batch of buffered events.
	 *
	 * @since 2.0.2.1
	 */
	public function process(): void {

		global $wpdb;

		$product_events = wpforms()->obj( 'ProductApi\ProductEvents' );

		if ( ! $product_events instanceof ProductEvents || ! $product_events->is_allowed_system() ) {
			return;
		}

		$buffer = $product_events->get_buffer();

		// Nothing to do is the common case until an event source is wired, and it costs one
		// indexed read here instead of a lock round trip plus a batch query.
		if ( ! $buffer->has_rows() ) {
			return;
		}

		// GET_LOCK names are scoped to the MySQL server, so the prefix keeps sites in a
		// network, and installs sharing a server, from contending on one name.
		$lock_name = $wpdb->prefix . self::LOCK;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$lock = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $lock_name, 0 ) );

		if ( $lock === '0' ) {
			// Another runner holds the batch. Its rows are still there for the next tick.
			return;
		}

		// A null result means the backend has no GET_LOCK at all, which some MySQL-compatible
		// drop-ins do not. Losing serialisation is better than never flushing again.
		$locked = $lock === '1';

		try {
			for ( $batch = 0; $batch < self::MAX_BATCHES; $batch++ ) {
				if ( ! $this->flush_batch( $product_events, $buffer ) ) {
					break;
				}
			}
		} finally {
			if ( $locked ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock_name ) );
			}
		}
	}

	/**
	 * Unschedule the recurring action.
	 *
	 * Not `( new self() )->cancel()`: the constructor runs init(), which schedules the
	 * action when it is not already scheduled, so cancelling that way schedules it on
	 * the line before it is removed.
	 *
	 * @since 2.0.2.1
	 */
	public static function unschedule(): void {

		self::unschedule_action( self::ACTION );
	}

	/**
	 * Replay one batch and drop the rows the tracker took.
	 *
	 * A row is deleted only once track_system() reports it queued. That covers the
	 * failures we can see from here — an unconfigured client, a tracker the client
	 * refuses to hand over — and leaves the rest for the next run. It does not cover
	 * delivery: the request goes out non-blocking on shutdown and nothing plugin-side
	 * can read its result. That half is awesomemotive/wpforms-product-api-client#1.
	 *
	 * @since 2.0.2.1
	 *
	 * @param ProductEvents $product_events Product events instance.
	 * @param Buffer        $buffer         Buffer instance.
	 *
	 * @return bool Whether a full batch left, so another one may be waiting. False on an
	 *              empty or short batch, and on a hand-over that was not confirmed: the
	 *              rows stay, and trying again in the same run would send them again.
	 */
	private function flush_batch( ProductEvents $product_events, Buffer $buffer ): bool {

		$rows = $buffer->get_batch();

		if ( ! $rows ) {
			return false;
		}

		$queued = [];

		foreach ( $rows as $row ) {
			$properties = json_decode( (string) $row['properties'], true );

			$accepted = $product_events->track_system(
				$row['event_name'],
				is_array( $properties ) ? $properties : [],
				$row['event_context'],
				(string) $row['occurred_at']
			);

			if ( $accepted ) {
				$queued[] = $row['id'];
			}
		}

		// A batch the tracker took none of would be read again, whole, on the next turn
		// of the loop, and refused again. The rows stay for the next run instead.
		if ( ! $queued ) {
			return false;
		}

		// Nothing is dropped on a hand-over we could not confirm. The rows stay for the
		// next run, which is the whole reason the send blocks here.
		if ( ! $product_events->send_now() ) {
			return false;
		}

		$buffer->delete( $queued );

		return count( $rows ) === Buffer::DEFAULT_BATCH;
	}
}
