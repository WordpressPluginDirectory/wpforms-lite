<?php

namespace WPForms\Admin\Dashboard\Widgets;

use WPForms\Admin\Addons\Install;
use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\Helpers;
use WPForms\Admin\Dashboard\PaymentStats;
use WPForms\Admin\Dashboard\StripeConnect;
use WPForms\Admin\Dashboard\WidgetState;
use WPForms\Db\Payments\ValueValidator;
use WPForms\Integrations\Stripe\Helpers as StripeHelpers;

/**
 * Dashboard "Payments" main-column widget.
 *
 * Shows a Stripe connect band when no payment gateway is configured, and
 * payment stats once one is connected.
 *
 * @since 2.0.2
 */
class Payments extends AbstractWidget {

	/**
	 * Column placement.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'main';

	/**
	 * Sort position within the main column.
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 20;

	/**
	 * Minimum "Number of Payments" gear-menu setting.
	 *
	 * @since 2.0.2
	 */
	public const MIN_PAYMENTS = 3;

	/**
	 * Maximum "Number of Payments" gear-menu setting.
	 *
	 * @since 2.0.2
	 */
	public const MAX_PAYMENTS = 10;

	/**
	 * Default "Number of Payments" gear-menu setting.
	 *
	 * @since 2.0.2
	 */
	public const DEFAULT_PAYMENTS = 5;

	/**
	 * Map of `Chart::stat_cards()` report keys to the cached `payments.tiles` keys.
	 *
	 * Must stay in sync with `PaymentStats::normalize_tiles()`.
	 *
	 * @since 2.0.2
	 */
	private const TILE_MAP = [
		'total_payments'             => 'total_payments',
		'total_sales'                => 'total_sales',
		'total_refunded'             => 'total_refunded',
		'total_subscription'         => 'new_subscriptions',
		'total_renewal_subscription' => 'renewals',
		'total_coupons'              => 'coupons',
	];

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'payments';
	}

	/**
	 * Get the widget title.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_title(): string {

		return esc_html__( 'Payments', 'wpforms-lite' );
	}

	/**
	 * Resolve the widget state.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_state( AccessContext $access ): WidgetState { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $access is part of the contract signature.

		if ( ! Helpers::is_payment_gateway_connected() ) {
			return new WidgetState( true, 'connect' );
		}

		return new WidgetState( true, 'data' );
	}

	/**
	 * Render the widget body.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant: 'connect' or 'data'.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string {

		if ( $variant === 'connect' ) {
			return $this->render_connect();
		}

		return (string) wpforms_render(
			'admin/dashboard/widgets/payments',
			$this->get_view_data( (array) ( $data['payments'] ?? [] ), $access ),
			true
		);
	}

	/**
	 * Render the connect band shown when no payment gateway is configured.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	private function render_connect(): string {

		$connect_url = add_query_arg(
			StripeConnect::KICKOFF_ARG,
			'1',
			admin_url( 'admin.php' ) . '?page=wpforms-dashboard'
		);

		return (string) wpforms_render(
			'admin/dashboard/widgets/payments-connect',
			[
				'connect_url'       => $connect_url,
				'settings_url'      => StripeHelpers::get_settings_page_url(),
				'show_mercado_pago' => $this->is_mercado_pago_supported(),
			],
			true
		);
	}

	/**
	 * Whether the site currency is one Mercado Pago can process — the connect
	 * footer only advertises the gateway when it could actually be used.
	 *
	 * Mirrors the addon's site-to-currency map (`WPFormsMercadoPago\Helpers`),
	 * which is not readable here: the map is private and the addon may not be
	 * installed.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_mercado_pago_supported(): bool {

		$supported = [ 'ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'UYU' ];

		return in_array( strtoupper( wpforms_get_currency() ), $supported, true );
	}

	/**
	 * Declare the gear-menu settings — only in the data state. The framework renders
	 * the popover + Save button and persists the values.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function get_settings_schema( string $variant, array $data, AccessContext $access ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $data and $access are part of the contract signature; the schema itself is data-independent.

		if ( $variant !== 'data' ) {
			return [];
		}

		$settings = $this->get_resolved_settings();
		$options  = [];

		for ( $number = self::MIN_PAYMENTS; $number <= self::MAX_PAYMENTS; $number++ ) {
			$options[ $number ] = number_format_i18n( $number );
		}

		return [
			[
				'type'    => 'checkboxes',
				'name'    => 'display',
				'label'   => __( 'Display Options', 'wpforms-lite' ),
				'panel'   => true,
				'options' => [
					'display_graph'      => __( 'Display Chart', 'wpforms-lite' ),
					'display_stat_cards' => __( 'Display Stat Cards', 'wpforms-lite' ),
				],
				'value'   => [
					'display_graph'      => $settings['display_graph'],
					'display_stat_cards' => $settings['display_stat_cards'],
				],
			],
			[
				'type'    => 'checklist',
				'name'    => 'cards',
				'label'   => __( 'Stat Cards', 'wpforms-lite' ),
				'panel'   => true,
				'options' => $this->get_card_options(),
				'value'   => $settings['cards'],
			],
			[
				'type'    => 'select',
				'name'    => 'number_of_payments',
				'label'   => __( 'Number of Payments', 'wpforms-lite' ),
				'options' => $options,
				'value'   => $settings['number_of_payments'],
			],
		];
	}

	/**
	 * Get the Stat Cards checklist options: report key => label, listed in the same order
	 * the tiles render so the gear menu reads top-to-bottom like the grid.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_card_options(): array {

		$options = [];

		foreach ( PaymentStats::get_ordered_stat_cards() as $report => $card ) {
			$options[ $report ] = (string) ( $card['label'] ?? $report );
		}

		return $options;
	}

	/**
	 * Get the per-user gear settings, clamped/defaulted/sanitized.
	 *
	 * @since 2.0.2
	 *
	 * @return array {
	 *     @type bool   $display_graph      Whether the graph is visible.
	 *     @type bool   $display_stat_cards Whether the stat tiles grid is visible.
	 *     @type array  $cards              Visible tile report keys (see `TILE_MAP`).
	 *     @type int    $number_of_payments Table row count, clamped to MIN_PAYMENTS..MAX_PAYMENTS.
	 *     @type string $selected_report    Last-viewed graph report, or '' when never set/invalid.
	 * }
	 */
	private function get_resolved_settings(): array {

		$settings = $this->get_settings();
		$number   = (int) ( $settings['number_of_payments'] ?? self::DEFAULT_PAYMENTS );
		$number   = max( self::MIN_PAYMENTS, min( self::MAX_PAYMENTS, $number ) );
		$selected = (string) ( $settings['selected_report'] ?? '' );

		return [
			'display_graph'      => ( (string) ( $settings['display_graph'] ?? '1' ) ) !== '0',
			'display_stat_cards' => ( (string) ( $settings['display_stat_cards'] ?? '1' ) ) !== '0',
			'cards'              => $this->sanitize_cards( $settings['cards'] ?? null ),
			'number_of_payments' => $number,
			'selected_report'    => array_key_exists( $selected, self::TILE_MAP ) ? $selected : '',
		];
	}

	/**
	 * The widget's default gear settings, in the shape its popover submits. The
	 * last-viewed report is not among them — it records where the user was, not how the
	 * widget is configured, so a reset leaves it alone.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	public function get_default_settings(): array {

		return [
			'display_graph'      => '1',
			'display_stat_cards' => '1',
			'cards'              => array_keys( self::TILE_MAP ),
			'number_of_payments' => (string) self::DEFAULT_PAYMENTS,
		];
	}

	/**
	 * Sanitize the Stat Cards checklist value to the known tile report keys, defaulting
	 * to all cards when unset.
	 *
	 * @since 2.0.2
	 *
	 * @param mixed $cards Raw saved checklist value.
	 *
	 * @return array
	 */
	private function sanitize_cards( $cards ): array {

		$known = array_keys( self::TILE_MAP );

		if ( ! is_array( $cards ) ) {
			return $known;
		}

		return array_values( array_intersect( array_map( 'strval', $cards ), $known ) );
	}

	/**
	 * Build the data-state view: ordered tiles, the graph series, the recent-payments table,
	 * and the Coupons CTA.
	 *
	 * @since 2.0.2
	 *
	 * @param array         $cached_payments Cached `payments` block (`tiles`, `graph`, `graph_report`).
	 * @param AccessContext $access          Access context.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	private function get_view_data( array $cached_payments, AccessContext $access ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $access is part of the contract signature; the Coupons CTA tier gate is resolved from the addon feed itself.

		$graph_report = (string) ( $cached_payments['graph_report'] ?? 'total_sales' );
		$settings     = $this->get_resolved_settings();

		// Restore the last-viewed report when it is still a visible tile; otherwise
		// fall back to the report the cached graph series represents.
		$selected_report = $settings['selected_report'];

		if ( $selected_report === '' || ! in_array( $selected_report, $settings['cards'], true ) ) {
			$selected_report = $graph_report;
		}

		return [
			'tiles'              => $this->get_tiles( (array) ( $cached_payments['tiles'] ?? [] ), $selected_report, $settings['cards'] ),
			'tile_values'        => (array) ( $cached_payments['tiles'] ?? [] ),
			'graph'              => (array) ( $cached_payments['graph'] ?? [] ),
			'graph_report'       => $graph_report,
			'payments'           => $this->get_recent_payments(),
			'visible_rows'       => $settings['number_of_payments'],
			'coupons_cta'        => $this->get_coupons_cta(),
			'currency'           => wpforms_get_currency(),
			'widget_id'          => $this->get_id(),
			'display_graph'      => $settings['display_graph'],
			'display_stat_cards' => $settings['display_stat_cards'],
		];
	}

	/**
	 * Get the live recent-payments table rows (not cached, not date-range-scoped — the most
	 * recent activity regardless of the selected date range, up to `MAX_PAYMENTS`).
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_recent_payments(): array {

		$payment = wpforms()->obj( 'payment' );

		if ( ! $payment ) {
			return [];
		}

		$payments = (array) $payment->get_payments(
			[
				'number'  => self::MAX_PAYMENTS,
				'orderby' => 'id',
				'order'   => 'DESC',
			]
		);

		return array_map( [ $this, 'build_payment_row' ], $payments );
	}

	/**
	 * Build one recent-payment row's view data.
	 *
	 * @since 2.0.2
	 *
	 * @param array $payment Raw payment row (from `Payment::get_payments()`).
	 *
	 * @return array
	 */
	private function build_payment_row( array $payment ): array {

		$payment_id = absint( $payment['id'] ?? 0 );
		$status_key = strtolower( (string) ( $payment['status'] ?? '' ) );
		$type       = (string) ( $payment['type'] ?? '' );
		$dates      = $this->get_payment_dates( (string) ( $payment['date_updated_gmt'] ?? '' ) );

		return [
			'number'          => '#' . $payment_id,
			'name'            => (string) ( $payment['title'] ?? '' ),
			'url'             => $this->get_payment_url( $payment_id ),
			'date_rel'        => $dates['rel'],
			'date_abs'        => $dates['abs'],
			'type_label'      => (string) ( ValueValidator::get_allowed_types()[ $type ] ?? $type ),
			'total_formatted' => wpforms_format_amount( wpforms_sanitize_amount( $payment['total_amount'] ?? 0 ), true ),
			'status_key'      => $status_key,
			'status_label'    => $this->get_payment_status_label( $status_key ),
		];
	}

	/**
	 * Get the single-payment page URL for a payment ID.
	 *
	 * @since 2.0.2
	 *
	 * @param int $payment_id Payment ID.
	 *
	 * @return string
	 */
	private function get_payment_url( int $payment_id ): string {

		return (string) add_query_arg(
			[
				'page'       => 'wpforms-payments',
				'view'       => 'payment',
				'payment_id' => $payment_id,
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Get a payment's relative and absolute display dates from its updated-GMT timestamp.
	 * Mirrors `Payments\Views\Overview\Table::get_column_date()`.
	 *
	 * @since 2.0.2
	 *
	 * @param string $date_updated_gmt GMT-based `date_updated_gmt` column value.
	 *
	 * @return array
	 */
	private function get_payment_dates( string $date_updated_gmt ): array {

		if ( empty( $date_updated_gmt ) ) {
			return [
				'rel' => '',
				'abs' => '',
			];
		}

		$local_timestamp = strtotime( get_date_from_gmt( $date_updated_gmt, 'Y-m-d H:i' ) );
		$gmt_timestamp   = strtotime( $date_updated_gmt );

		// Relative "X ago" for past/now, an absolute date for future timestamps —
		// matching `Payments\Views\Overview\Table::get_column_date()`, which this list
		// mirrors. Without the future guard, `human_time_diff()` would render a future
		// payment as "… ago".
		if ( $gmt_timestamp <= time() ) {
			/* translators: %s - relative time difference, e.g. "5 minutes", "12 days". */
			$relative = sprintf( esc_html__( '%s ago', 'wpforms-lite' ), human_time_diff( $gmt_timestamp ) );
		} else {
			$relative = wpforms_datetime_format( $local_timestamp, 'M j, Y', false );
		}

		return [
			'rel' => $relative,
			'abs' => wpforms_datetime_format( $local_timestamp, 'Y-m-d H:i', false ),
		];
	}

	/**
	 * Get a payment status's display label. `partrefund` is worded as "% Refunded" (mirrors
	 * `Payments\Views\Overview\Table::get_column_status()`).
	 *
	 * @since 2.0.2
	 *
	 * @param string $status_key Lowercased payment status.
	 *
	 * @return string
	 */
	private function get_payment_status_label( string $status_key ): string {

		if ( $status_key === 'partrefund' ) {
			return __( '% Refunded', 'wpforms-lite' );
		}

		return (string) ( ValueValidator::get_allowed_statuses()[ $status_key ] ?? $status_key );
	}

	/**
	 * Build the ordered, labelled, formatted tiles from the Dashboard card order and the
	 * cached raw tile values. Tiles whose report is absent from the aggregate (the
	 * subscription cards, when `Chart`'s `condition` is false) are skipped entirely;
	 * tiles hidden by the saved Stat Cards gear setting are still rendered but flagged
	 * `is_hidden`, so the gear can reveal them client-side without a reload.
	 *
	 * @since 2.0.2
	 *
	 * @param array  $tiles           Cached `payments.tiles` raw values, keyed by cache tile key.
	 * @param string $selected_report The report whose tile is marked selected.
	 * @param array  $visible_cards   Report keys of the tiles the gear menu keeps visible.
	 *
	 * @return array
	 */
	private function get_tiles( array $tiles, string $selected_report, array $visible_cards ): array {

		$result = [];

		foreach ( PaymentStats::get_ordered_stat_cards() as $report => $card ) {
			$tile_key = self::TILE_MAP[ $report ] ?? $report;

			if ( ! array_key_exists( $tile_key, $tiles ) ) {
				continue;
			}

			$tile              = $this->build_tile( $report, $card, $tiles, $tile_key, $selected_report );
			$tile['is_hidden'] = ! in_array( $report, $visible_cards, true );
			$result[]          = $tile;
		}

		return $result;
	}

	/**
	 * Build one tile's view data.
	 *
	 * @since 2.0.2
	 *
	 * @param string $report          The `Chart::stat_cards()` report key.
	 * @param array  $card            Stat card definition (`label`, `button_classes`, …).
	 * @param array  $tiles           Cached `payments.tiles` raw values, keyed by cache tile key.
	 * @param string $tile_key        Cached tile key for this report (see `TILE_MAP`).
	 * @param string $selected_report The report whose tile is marked selected.
	 *
	 * @return array
	 */
	private function build_tile( string $report, array $card, array $tiles, string $tile_key, string $selected_report ): array {

		$button_classes = (array) ( $card['button_classes'] ?? [] );
		$has_count      = ! empty( $card['has_count'] );
		$value          = $tiles[ $tile_key ];
		$delta_key      = "{$tile_key}_delta";

		return [
			'report'         => $report,
			'label'          => (string) ( $card['label'] ?? '' ),
			'icon'           => (string) ( $card['icon'] ?? '' ),
			'tint'           => (string) ( $card['tint'] ?? '' ),
			'button_classes' => $button_classes,
			'value'          => $this->format_tile_value( $report, $button_classes, $value ),
			'count'          => $this->format_tile_count( $has_count, $value ),
			'delta'          => array_key_exists( $delta_key, $tiles ) ? (int) $tiles[ $delta_key ] : null,
			'is_selected'    => $report === $selected_report,
		];
	}

	/**
	 * Format a tile's main displayed value. The Coupons tile shows no value (an empty
	 * string) while the addon is inactive — the template renders the CTA branch instead.
	 *
	 * @since 2.0.2
	 *
	 * @param string           $report         The `Chart::stat_cards()` report key.
	 * @param array            $button_classes Stat card `button_classes` (`is-amount` drives formatting).
	 * @param int|string|array $value          Raw cached tile value.
	 *
	 * @return string
	 */
	private function format_tile_value( string $report, array $button_classes, $value ): string {

		if ( $report === 'total_coupons' && ! wpforms_is_addon_initialized( 'coupons' ) ) {
			return '';
		}

		$amount = $this->split_tile_value( $value )['amount'];

		if ( in_array( 'is-amount', $button_classes, true ) ) {
			return wpforms_format_amount( $amount, true );
		}

		return number_format_i18n( (int) str_replace( ',', '', $amount ) );
	}

	/**
	 * Format a tile's parenthesized row count (amount+count tiles only).
	 *
	 * @since 2.0.2
	 *
	 * @param bool             $has_count Whether the stat card definition includes a row count.
	 * @param int|string|array $value     Raw cached tile value.
	 *
	 * @return string
	 */
	private function format_tile_count( bool $has_count, $value ): string {

		if ( ! $has_count ) {
			return '';
		}

		$count = $this->split_tile_value( $value )['count'];

		return $count !== null ? number_format_i18n( $count ) : '';
	}

	/**
	 * Split a tile's raw cached value into its amount and row-count parts. The live
	 * aggregator concatenates has-count tiles into a single "amount (count)" string
	 * (`PaymentStats::normalize_tiles()`); the empty-state placeholder and the Pro
	 * rollup path use an `[ 'amount' => …, 'count' => … ]` array instead. Handles both.
	 *
	 * @since 2.0.2
	 *
	 * @param int|string|array $value Raw cached tile value.
	 *
	 * @return array
	 */
	private function split_tile_value( $value ): array {

		if ( is_array( $value ) ) {
			return [
				'amount' => (string) ( $value['amount'] ?? '0' ),
				'count'  => isset( $value['count'] ) ? (int) $value['count'] : null,
			];
		}

		if ( preg_match( '/^(.*?)\s*\((\d+)\)$/', (string) $value, $matches ) ) {
			return [
				'amount' => $matches[1],
				'count'  => (int) $matches[2],
			];
		}

		return [
			'amount' => (string) $value,
			'count'  => null,
		];
	}

	/**
	 * Resolve the Coupons tile CTA shown while the Coupons addon is inactive: the
	 * in-place Install & Activate flow when entitled (Pro/Elite), else Upgrade to Pro.
	 * Empty when the addon is already active — the tile shows the redeemed count instead.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_coupons_cta(): array {

		if ( wpforms_is_addon_initialized( 'coupons' ) ) {
			return [];
		}

		$addon = $this->get_addon_data( 'coupons' );

		if ( empty( $addon['plugin_allow'] ) ) {
			return [
				'label'   => __( 'Upgrade to Pro', 'wpforms-lite' ),
				'url'     => wpforms_admin_upgrade_link( 'Dashboard - Payments', 'Coupons' ),
				'classes' => 'wpforms-dashboard-widget-payments-coupons-cta',
				'attrs'   => [
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				],
			];
		}

		$is_activate = ( $addon['action'] ?? '' ) === 'activate';
		$cta_action  = $is_activate ? 'activate-plugin' : 'install-plugin';

		// Entitled, but unable to install in place (multisite, DISALLOW_FILE_MODS, or a user
		// without the capability) — send them to the Addons page, which renders its own gated
		// state, rather than a button the endpoint can only reject.
		if ( ! Install::can_install_and_activate( $cta_action, 'addon' ) ) {
			return [
				'label'   => __( 'Install & Activate', 'wpforms-lite' ),
				'url'     => admin_url( 'admin.php?page=wpforms-addons' ),
				'classes' => 'wpforms-dashboard-widget-payments-coupons-cta',
				'attrs'   => [],
			];
		}

		// Install or activate in place through the shared addon-tiles endpoint
		// (wpforms_addons_install), which resolves the addon by its plugin file and
		// installs-or-activates. On success the widget refreshes the tile without a
		// reload — see widget-payments.js.
		return [
			'label'   => $is_activate ? __( 'Activate', 'wpforms-lite' ) : __( 'Install & Activate', 'wpforms-lite' ),
			'url'     => '#',
			'classes' => 'wpforms-dashboard-widget-payments-coupons-cta wpforms-addon-tile__link',
			'attrs'   => [
				'data-action' => $cta_action,
				'data-plugin' => $addon['path'] ?? '',
			],
		];
	}

	/**
	 * Get an addon's feed data (entitlement, action, install payload).
	 *
	 * @since 2.0.2
	 *
	 * @param string $slug Addon slug.
	 *
	 * @return array Addon data, or an empty array when the addons service is unavailable.
	 */
	private function get_addon_data( string $slug ): array {

		$addons = wpforms()->obj( 'addons' );

		return $addons ? (array) $addons->get_addon( $slug ) : [];
	}
}
