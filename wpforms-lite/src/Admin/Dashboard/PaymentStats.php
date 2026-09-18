<?php

namespace WPForms\Admin\Dashboard;

use DateTimeImmutable;
use WPForms\Admin\Payments\Views\Overview\Chart;
use WPForms\Db\Payments\StatsAggregator;

/**
 * Live payment stat tiles for the Dashboard.
 *
 * Unlike the Payments Overview, the Dashboard has no test/live switch — every figure it
 * shows is site-wide and live-mode. The shared payment queries resolve their mode through
 * `Overview\Page::get_mode()`, which falls back to the viewing user's stored Payments-page
 * mode on any admin-ajax request. Without the pin applied here, a range switch would return
 * that user's test-mode totals and store them in the site-wide Dashboard cache.
 *
 * @since 2.0.2
 */
class PaymentStats {

	/**
	 * Filter resolving the shared payment WHERE conditions (mode, currency, is_published).
	 *
	 * @since 2.0.2
	 *
	 * @var string
	 */
	public const WHERE_ARGS_FILTER = 'wpforms_db_payments_payment_add_secondary_where_conditions_args';

	/**
	 * Filter overriding the stat-card column aggregation expressions.
	 *
	 * @since 2.0.2
	 *
	 * @var string
	 */
	private const COLUMN_CLAUSES_FILTER = 'wpforms_admin_payments_views_overview_ajax_stats_column_clauses';

	/**
	 * Dashboard render order for the payment stat cards.
	 *
	 * The Overview catalog places refunds between sales and the subscription pair. The
	 * Dashboard leads with Total Sales (the default graph report), keeps the
	 * subscription cards together and closes its grid with the refund/coupon pair, so
	 * the order is declared here rather than reordering `Chart::stat_cards()` — that
	 * catalog also drives the Payments Overview screen.
	 *
	 * @since 2.0.2
	 */
	private const CARD_ORDER = [
		'total_sales',
		'total_payments',
		'total_subscription',
		'total_renewal_subscription',
		'total_refunded',
		'total_coupons',
	];

	/**
	 * Get the stat-card catalog in the Dashboard's own render order.
	 *
	 * Cards missing from the catalog are skipped, and catalog entries absent from
	 * `CARD_ORDER` keep their own order at the end — a card added to the Overview later
	 * still surfaces here instead of silently vanishing from the widget.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	public static function get_ordered_stat_cards(): array {

		$cards   = Chart::stat_cards();
		$ordered = [];

		foreach ( self::CARD_ORDER as $report ) {
			if ( isset( $cards[ $report ] ) ) {
				$ordered[ $report ] = $cards[ $report ];
			}
		}

		return $ordered + $cards;
	}

	/**
	 * Fill in any tile the source could not report, so the widget always renders the full
	 * set of stat cards.
	 *
	 * `Payments::get_tiles()` skips a card whose key is absent, and the sources disagree on
	 * which keys they produce: the live aggregator omits cards whose `Chart::stat_cards()`
	 * condition is false, while the rollup tables track only four of the six measures. Left
	 * alone, the same widget would gain and lose cards as the range crossed rollup coverage.
	 * Zeros here mean "nothing to report", which is what an absent card conveyed anyway.
	 *
	 * @since 2.0.2
	 *
	 * @param array $tiles Tiles resolved so far, keyed by cached `payments.tiles` key.
	 *
	 * @return array
	 */
	public static function fill_missing_tiles( array $tiles ): array {

		$empty_amount = [
			'amount' => '0',
			'count'  => 0,
		];

		// The canonical zero-tile shape — `Cache::get_empty_payments()` consumes it for the
		// no-gateway state, so the two states cannot drift apart.
		return $tiles + [
			'total_payments'    => 0,
			'total_sales'       => '0',
			'total_refunded'    => $empty_amount,
			'new_subscriptions' => $empty_amount,
			'renewals'          => $empty_amount,
			'coupons'           => 0,
		];
	}

	/**
	 * Get the live payment tiles for a range, keyed by cached `payments.tiles` key.
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable $start Range start date.
	 * @param DateTimeImmutable $end   Range end date.
	 *
	 * @return array
	 */
	public function get_tiles( DateTimeImmutable $start, DateTimeImmutable $end ): array {

		// StatsAggregator may not be available on all branches.
		if ( ! class_exists( StatsAggregator::class ) ) {
			return [];
		}

		$row = $this->with_live_mode(
			static function () use ( $start, $end ) {

				return ( new StatsAggregator( Chart::stat_cards() ) )->get_summary_reports( $start, $end );
			}
		);

		return $this->normalize_tiles( (array) $row );
	}

	/**
	 * Run a callback with the shared payment queries pinned to live mode.
	 *
	 * Pinned for the duration of the callback only. Pinning globally would break the
	 * Payments Overview, whose test/live switch legitimately drives the same filter —
	 * so the pin is released in `finally`, or a throwing callback would leak live mode
	 * into every later query in the request.
	 *
	 * @since 2.0.2
	 *
	 * @param callable $callback Callback running the payment queries.
	 *
	 * @return mixed The callback's return value.
	 */
	public function with_live_mode( callable $callback ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- The mode pin is deliberately request-scoped; registering it in hooks() would force live mode on the Payments Overview too.

		add_filter( self::WHERE_ARGS_FILTER, [ $this, 'force_live_mode' ], PHP_INT_MAX );

		try {
			return $callback();
		} finally {
			remove_filter( self::WHERE_ARGS_FILTER, [ $this, 'force_live_mode' ], PHP_INT_MAX );
		}
	}

	/**
	 * Force live mode on the shared payment WHERE conditions.
	 *
	 * Runs last so it wins over the Payments page's own mode resolution.
	 *
	 * @since 2.0.2
	 *
	 * @param array|mixed $args Query arguments.
	 *
	 * @return array
	 */
	public function force_live_mode( $args ): array {

		$args         = (array) $args;
		$args['mode'] = 'live';

		return $args;
	}

	/**
	 * Whether anything overrides the stat-card column aggregation expressions.
	 *
	 * The rollup tables store pre-aggregated sums, so an override of the aggregation SQL
	 * cannot be reproduced from them. Callers use this to fall back to the live path rather
	 * than serve totals that would silently disagree with the Payments Overview. No core
	 * code hooks this filter, so any callback at all belongs to an addon or a custom snippet.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public static function has_column_clause_overrides(): bool {

		return has_filter( self::COLUMN_CLAUSES_FILTER );
	}

	/**
	 * Map `StatsAggregator::get_summary_reports()`'s Chart-report keys to the cached
	 * `payments.tiles` keys.
	 *
	 * Also carries each tile's `{report}_delta` through as `{tile}_delta`, when present.
	 * `get_summary_reports()` already computes the deltas, so this is a pass-through.
	 * Reports absent from the row are omitted, so their tile is skipped entirely.
	 *
	 * @since 2.0.2
	 *
	 * @param array $row Raw summary report row, keyed by `Chart::stat_cards()` report names.
	 *
	 * @return array
	 */
	private function normalize_tiles( array $row ): array {

		$map = [
			'total_payments'             => 'total_payments',
			'total_sales'                => 'total_sales',
			'total_refunded'             => 'total_refunded',
			'total_subscription'         => 'new_subscriptions',
			'total_renewal_subscription' => 'renewals',
			'total_coupons'              => 'coupons',
		];

		$tiles = [];

		foreach ( $map as $source_key => $target_key ) {
			if ( array_key_exists( $source_key, $row ) ) {
				$tiles[ $target_key ] = $row[ $source_key ];
			}

			$delta_key = "{$source_key}_delta";

			if ( array_key_exists( $delta_key, $row ) ) {
				$tiles[ "{$target_key}_delta" ] = (int) $row[ $delta_key ];
			}
		}

		return $tiles;
	}
}
