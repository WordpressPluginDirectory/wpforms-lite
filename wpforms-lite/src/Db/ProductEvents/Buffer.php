<?php

namespace WPForms\Db\ProductEvents;

/**
 * Local buffer for events that cannot be sent on the request that produced them.
 *
 * An anonymous form submission has no admin context and no logged-in user, and it
 * must not pay for an outbound request, so its events are written here and handed
 * over later by FlushProductEventsTask.
 *
 * @since 2.0.2.1
 */
class Buffer {

	/**
	 * Event context for anything attributable to the logged-in user.
	 *
	 * Declared here rather than on Queue: Queue extends WPForms_DB, a legacy include
	 * that is not autoloaded, so referencing a constant on it would drag that in
	 * wherever the vocabulary is needed. Mirrors the column default in the schema.
	 *
	 * @since 2.0.2.1
	 */
	public const CONTEXT_USER = 'user';

	/**
	 * Event context for anything an anonymous visitor did.
	 *
	 * @since 2.0.2.1
	 */
	public const CONTEXT_VISITOR = 'visitor';

	/**
	 * Event context for anything the site did with nobody logged in: WP-Cron, WP-CLI,
	 * Action Scheduler.
	 *
	 * @since 2.0.2.1
	 */
	public const CONTEXT_SYSTEM = 'system';

	/**
	 * The one event the cap never trims: a payment received. Mirrors
	 * SubmissionEvents::EVENT_PAYMENT, which cannot be referenced here without pulling
	 * the integrations layer into the storage one.
	 *
	 * @since 2.0.2.1
	 */
	public const TRIM_EXEMPT_EVENT = 'payment_received';

	/**
	 * Transient that throttles the log line for a buffer the trim cannot shrink.
	 *
	 * @since 2.0.2.1
	 */
	private const UNTRIMMABLE_LOG_TRANSIENT = 'wpforms_product_events_buffer_untrimmable';

	/**
	 * Default number of rows kept before the oldest are dropped.
	 *
	 * @since 2.0.2.1
	 */
	public const DEFAULT_CAP = 1000;

	/**
	 * Default number of rows handed over per flush.
	 *
	 * @since 2.0.2.1
	 */
	public const DEFAULT_BATCH = 100;

	/**
	 * Table handler.
	 *
	 * @since 2.0.2.1
	 *
	 * @var Queue
	 */
	private $queue;

	/**
	 * Class constructor.
	 *
	 * @since 2.0.2.1
	 */
	public function __construct() {

		$this->queue = new Queue();
	}

	/**
	 * Buffer an event.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $name       Event name, snake_case.
	 * @param array  $properties Event properties.
	 * @param string $context    Event context. Required rather than defaulted, so a
	 *                            caller cannot get a different answer here than from
	 *                            ProductEvents::buffer(), which defaults to visitor.
	 *
	 * @return bool Whether the event was buffered.
	 */
	public function add( string $name, array $properties, string $context ): bool {

		$name = sanitize_key( $name );

		if ( $name === '' ) {
			return false;
		}

		$id = (int) $this->queue->add(
			[
				'event_name'    => $name,
				'event_context' => self::normalize_context( $context ),
				'properties'    => (string) wp_json_encode( self::normalize_properties( $properties ) ),
				'occurred_at'   => gmdate( 'Y-m-d H:i:s' ),
			]
		);

		if ( ! $id ) {
			return false;
		}

		$this->enforce_cap( $id );

		return true;
	}

	/**
	 * Read the oldest buffered events.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int $limit Maximum number of rows to read.
	 *
	 * @return array List of rows, oldest first.
	 */
	public function get_batch( int $limit = self::DEFAULT_BATCH ): array {

		global $wpdb;

		$table = $this->queue->table_name;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare( "SELECT id, event_name, event_context, properties, occurred_at FROM $table ORDER BY occurred_at ASC, id ASC LIMIT %d", max( 1, $limit ) );
		$rows  = $this->queue->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Delete buffered events by id.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array $ids Row ids.
	 *
	 * @return bool Whether anything was deleted.
	 */
	public function delete( array $ids ): bool {

		return (bool) $this->queue->delete_where_in( 'id', array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Reduce a context to one the Product API will accept.
	 *
	 * Anything unrecognised falls back to `visitor` rather than `user`, because `user`
	 * is the context that makes the client attach identity, and its fallback there
	 * resolves the site administrator.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $context Event context.
	 *
	 * @return string
	 */
	public static function normalize_context( string $context ): string {

		$context = sanitize_key( $context );

		return in_array( $context, [ self::CONTEXT_USER, self::CONTEXT_SYSTEM ], true ) ? $context : self::CONTEXT_VISITOR;
	}

	/**
	 * Normalize event properties into the one shape both send paths use.
	 *
	 * Keys only. Values are left alone on purpose: the buffered path encodes the whole
	 * array once on the way in and decodes it once on the way out, so a nested value
	 * survives, whereas encoding it separately would bury it as a JSON string that the
	 * decode cannot restore.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array $properties Event properties.
	 *
	 * @return array
	 */
	public static function normalize_properties( array $properties ): array {

		$normalized = [];

		foreach ( $properties as $key => $value ) {
			$normalized[ sanitize_key( $key ) ] = $value;
		}

		return $normalized;
	}

	/**
	 * Whether anything is waiting to be flushed.
	 *
	 * One indexed read. The flush asks this before taking the lock, so an empty buffer
	 * costs a single row lookup rather than a lock round trip.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	public function has_rows(): bool {

		return (bool) $this->queue->get_var( "SELECT id FROM {$this->queue->table_name} LIMIT 1" );
	}

	/**
	 * Count buffered events.
	 *
	 * @since 2.0.2.1
	 *
	 * @return int
	 */
	public function count(): int {

		return (int) $this->queue->get_var( "SELECT COUNT(*) FROM {$this->queue->table_name}" );
	}

	/**
	 * Get the maximum number of rows kept.
	 *
	 * @since 2.0.2.1
	 *
	 * @return int
	 */
	private function get_cap(): int {

		/**
		 * Filter the number of buffered product events kept before the oldest are dropped.
		 *
		 * @since 2.0.2.1
		 *
		 * @param int $cap Maximum number of rows.
		 */
		return max( 1, (int) apply_filters( 'wpforms_db_product_events_buffer_get_cap', self::DEFAULT_CAP ) );
	}

	/**
	 * Drop everything older than the last `cap` rows.
	 *
	 * Retention is enforced on write rather than by a cleanup pass, because a cleanup
	 * pass would itself depend on cron, and a site whose cron never runs is exactly the
	 * case that needs bounding.
	 *
	 * Bounded by looking up the oldest row worth keeping rather than by arithmetic on
	 * the last id. Ids are not contiguous — the flush deletes only the rows the tracker
	 * accepted, so a row that keeps failing holds a low id while the auto-increment
	 * marches on — and `$last_id - $cap` would eventually delete that row with the table
	 * nowhere near the cap. The lookup walks at most `$cap` primary-key entries and
	 * returns nothing at all while the buffer is under the cap.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int $last_id Id of the row just inserted.
	 */
	private function enforce_cap( int $last_id ): void {

		global $wpdb;

		$cap = $this->get_cap();

		// Cheap precondition: with fewer ids issued than the cap, nothing can be over it.
		if ( $last_id <= $cap ) {
			return;
		}

		$table = $this->queue->table_name;

		// The newest row that is already past the cap, counting back from the newest.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$oldest_kept = (int) $this->queue->get_var( $wpdb->prepare( "SELECT id FROM $table ORDER BY id DESC LIMIT 1 OFFSET %d", $cap ) );

		if ( $oldest_kept < 1 ) {
			return;
		}

		// A payment row is one payment, nothing later stands in for it, so the trim leaves
		// those to be delivered whatever their age. An entry row past the fifth is a `null`
		// number the next entry repeats. Payments are few, so this bounds them by delivery
		// rather than by the cap, and only an API unreachable for months would let them grow.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dropped = (int) $this->queue->query( $wpdb->prepare( "DELETE FROM $table WHERE id <= %d AND event_name <> %s", $oldest_kept, self::TRIM_EXEMPT_EVENT ) );

		if ( $dropped < 1 ) {
			$this->log_untrimmable();

			return;
		}

		// The only signal anyone gets that events were lost. Delivery itself is not
		// observable, so a silent trim here would be invisible from both ends.
		wpforms_log(
			'Product events buffer cap reached',
			[ 'dropped' => $dropped ],
			[ 'type' => [ 'error' ] ]
		);
	}

	/**
	 * Log a buffer over the cap that the trim could not shrink.
	 *
	 * Everything past the cap is then payment rows the flush has not delivered, which
	 * the trim spares on purpose, so the table grows on every submission and the usual
	 * "cap reached" line never appears. Once an hour, not on every insert: the insert
	 * that finds this is every submission on the site until delivery resumes.
	 *
	 * @since 2.0.2.1
	 */
	private function log_untrimmable(): void {

		if ( get_transient( self::UNTRIMMABLE_LOG_TRANSIENT ) ) {
			return;
		}

		set_transient( self::UNTRIMMABLE_LOG_TRANSIENT, 1, HOUR_IN_SECONDS );

		wpforms_log(
			'Product events buffer over the cap with only payment rows left to trim',
			[ 'rows' => $this->count() ],
			[ 'type' => [ 'error' ] ]
		);
	}
}
