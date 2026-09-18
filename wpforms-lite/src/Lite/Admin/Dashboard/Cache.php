<?php

namespace WPForms\Lite\Admin\Dashboard;

use DateTimeImmutable;
use WPForms\Admin\Dashboard\Cache as CacheBase;
use WPForms\Admin\Helpers\Datepicker;
use WPForms\Integrations\LiteConnect\Integration as LCIntegration;
use WPForms\Integrations\LiteConnect\LiteConnect;
use WPForms\Lite\Reports\EntriesCount;

/**
 * Dashboard aggregate cache (Lite).
 *
 * Supplies the `entries` block the base leaves empty: LiteConnect entry stats
 * when the endpoint is available, local per-form counts otherwise.
 *
 * @since 2.0.2
 */
class Cache extends CacheBase {

	/**
	 * Get the aggregates for a range, overlaying LiteConnect entry stats
	 * when the endpoint is available.
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable $start Range start date.
	 * @param DateTimeImmutable $end   Range end date.
	 *
	 * @return array
	 */
	protected function get_aggregates( DateTimeImmutable $start, DateTimeImmutable $end ): array {

		$data     = parent::get_aggregates( $start, $end );
		$lc_stats = $this->get_lc_stats( $start, $end );

		// Lite's per-form counts are lifetime local counters, which never belong in
		// the "Entries Backed Up" column — the local rows contribute titles only.
		$local = $this->zero_form_counts( ( new EntriesCount() )->get_by_form() );

		$data['entries'] = [
			'graph' => $lc_stats ? $this->map_lc_graph( $lc_stats['days'] ) : [],
			'forms' => $this->enrich_forms_with_analytics(
				$lc_stats ? $this->map_lc_forms( $lc_stats['forms'], $local ) : $local,
				$start,
				$end
			),
		];

		return $data;
	}

	/**
	 * Get the total entries count for the stat card.
	 *
	 * Lite relabels the Total Entries card as "Entries Backed Up", so the value
	 * is the LiteConnect backed-up counter — the same source the Entries widget
	 * backup bar reads — never the lifetime local submission counters, which
	 * also count entries submitted before LiteConnect was enabled and therefore
	 * never backed up. Not connected means nothing is being backed up: 0.
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable $start Range start date. Unused on Lite.
	 * @param DateTimeImmutable $end   Range end date. Unused on Lite.
	 *
	 * @return int
	 */
	protected function get_entries_total( DateTimeImmutable $start, DateTimeImmutable $end ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		return $this->is_lc_connected() ? LCIntegration::get_new_entries_count() : 0;
	}

	/**
	 * Whether LiteConnect is connected (allowed and enabled).
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_lc_connected(): bool {

		return LiteConnect::is_allowed() && LiteConnect::is_enabled();
	}

	/**
	 * Fetch the LiteConnect entry stats for a range, empty when unavailable.
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable $start Range start date.
	 * @param DateTimeImmutable $end   Range end date.
	 *
	 * @return array
	 */
	private function get_lc_stats( DateTimeImmutable $start, DateTimeImmutable $end ): array {

		if ( ! $this->should_fetch_lc_stats( $start, $end ) ) {
			return [];
		}

		$period = min( 366, max( 1, (int) $end->diff( $start )->format( '%a' ) + 1 ) );

		return LCIntegration::get_stats( $period, wp_timezone_string() );
	}

	/**
	 * Zero the `count` on every `entries.forms` row.
	 *
	 * @since 2.0.2
	 *
	 * @param array $forms Entries.forms rows keyed by form_id.
	 *
	 * @return array
	 */
	private function zero_form_counts( array $forms ): array {

		foreach ( array_keys( $forms ) as $form_id ) {
			$forms[ $form_id ]['count'] = 0;
		}

		return $forms;
	}

	/**
	 * Whether LiteConnect stats should be fetched for this range.
	 *
	 * Returns false when LC is unavailable, the range ends before today
	 * (the endpoint only supports trailing windows from "now"), or the
	 * range is not the Lite default period. Lite has no datepicker, so
	 * only the default 30-day range is ever displayed; fetching LC data
	 * for other presets during background warm-up would waste external
	 * API calls that no user will see.
	 *
	 * @since 2.0.2
	 *
	 * @param DateTimeImmutable $start Range start date.
	 * @param DateTimeImmutable $end   Range end date.
	 *
	 * @return bool
	 */
	private function should_fetch_lc_stats( DateTimeImmutable $start, DateTimeImmutable $end ): bool {

		if ( ! $this->is_lc_connected() ) {
			return false;
		}

		$today = date_create_immutable( 'now', wp_timezone() )->format( 'Y-m-d' );

		if ( $end->format( 'Y-m-d' ) < $today ) {
			return false;
		}

		// Only fetch LC data for the Lite default period (30 days).
		$diff = (int) $end->diff( $start )->format( '%a' );

		return $diff === (int) Datepicker::TIMESPAN_DAYS;
	}

	/**
	 * Map the LC `days[]` response to the Dashboard `entries.graph` shape.
	 *
	 * Row-level validation is handled upstream by `Integration::validate_stats_response()`.
	 *
	 * @since 2.0.2
	 *
	 * @param array $days LC days array (sparse: zero-count days omitted).
	 *
	 * @return array
	 */
	private function map_lc_graph( array $days ): array {

		$graph = [];

		foreach ( $days as $day ) {
			$graph[] = [
				'date'  => (string) $day['date'],
				'count' => (int) $day['count'],
			];
		}

		return $graph;
	}

	/**
	 * Map the LC `forms[]` response to the Dashboard `entries.forms` shape.
	 *
	 * Resolves titles from the local forms map when available, falling back to
	 * `get_the_title()` for LC forms with a zero all-time local counter (which
	 * `EntriesCount::get_by_form()` excludes). Forms that no longer exist
	 * locally are dropped.
	 *
	 * Row-level validation is handled upstream by `StatsResponseValidator`.
	 *
	 * @since 2.0.2
	 *
	 * @param array $lc_forms    LC forms array.
	 * @param array $local_forms Local per-form counts, keyed by form_id.
	 *
	 * @return array Sorted by count desc.
	 */
	private function map_lc_forms( array $lc_forms, array $local_forms ): array {

		$result = [];

		foreach ( $lc_forms as $lc_form ) {
			$form_id = (int) $lc_form['form_id'];

			if ( isset( $local_forms[ $form_id ] ) ) {
				$title = $local_forms[ $form_id ]['title'] ?? '';
			} else {
				$title = get_the_title( $form_id );

				// Form no longer exists locally.
				if ( $title === '' ) {
					continue;
				}
			}

			$result[ $form_id ] = [
				'form_id' => $form_id,
				'count'   => (int) $lc_form['count'],
				'title'   => $title,
			];
		}

		// Maintain count-desc ordering contract from the parent cache.
		uasort(
			$result,
			static function ( $a, $b ) {

				return $b['count'] <=> $a['count'];
			}
		);

		return $result;
	}
}
