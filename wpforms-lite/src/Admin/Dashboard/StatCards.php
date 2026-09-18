<?php

namespace WPForms\Admin\Dashboard;

use WPForms\Education\ActiveLayer\Helper as ActiveLayerHelper;
use WPForms\Helpers\Transient;

/**
 * Dashboard stat cards: catalog, gating, formatting, and row rendering.
 *
 * Stat cards are not widgets — they bypass AbstractWidget/WidgetPipeline and
 * render on their own path (Page → StatCards → stat-card template). One
 * catalog-driven class; no Pro subclass — values arrive tier-correct from the
 * cache, and the only tier deltas are presentation.
 *
 * @since 2.0.2
 */
class StatCards {

	/**
	 * Container id the class is registered under.
	 *
	 * @since 2.0.2
	 */
	public const ID = 'dashboard_stat_cards';

	/**
	 * Transient key caching whether anti-spam tracking is configured.
	 *
	 * @since 2.0.2
	 */
	public const CONFIGURED_TRANSIENT = 'dashboard_stat_cards_anti_spam_configured';

	/**
	 * Longest value that still fits a card at its narrowest.
	 *
	 * A card at the four-up minimum leaves about 96px for the value, and the
	 * 24px metric type averages ~12px per glyph. Longer values get a compact
	 * alternative; shorter ones keep their exact figure.
	 *
	 * @since 2.0.2
	 */
	private const VALUE_FIT_CHARS = 8;

	/**
	 * Number of cards rendered by the last render() call.
	 *
	 * @since 2.0.2
	 *
	 * @var int
	 */
	private $rendered_count = 0;

	/**
	 * Aggregated dashboard data for the render in progress.
	 *
	 * The cards themselves need only the `stats` block, but the Anti-Spam CTA links to the
	 * form at the top of the Entries widget, which lives in `entries.forms`.
	 *
	 * @since 2.0.2
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 * Render the stat-card row for the given cached data.
	 *
	 * @since 2.0.2
	 *
	 * @param array         $data   Aggregated dashboard data.
	 * @param AccessContext $access Access context.
	 *
	 * @return string
	 */
	public function render( array $data, AccessContext $access ): string {

		$html                 = '';
		$this->rendered_count = 0;
		$this->data           = $data;
		$stats                = is_array( $data['stats'] ?? null ) ? $data['stats'] : [];

		foreach ( $this->get_catalog() as $id => $card ) {
			if ( ! $this->is_visible( $card, $access ) ) {
				continue;
			}

			++$this->rendered_count;

			$raw   = $stats[ $id ] ?? 0;
			$value = $this->format_value( $raw, $card['format'] );

			$html .= wpforms_render(
				'admin/dashboard/stat-card',
				[
					'id'          => $id,
					'icon'        => $card['icon'],
					'tint'        => $card['tint'],
					'label'       => $this->get_label( $card, $access ),
					'label_short' => $card['label_short'] ?? '',
					'value'       => $value,
					'value_short' => $this->get_short_value( $raw, $card['format'], $value ),
					'cta'         => $this->get_cta( $card ),
				],
				true
			);
		}

		return $html;
	}

	/**
	 * Get the count-driven row modifier class for the last rendered row.
	 *
	 * The SCSS maps each count to the row distribution (max 4 per row;
	 * 5 → 3+2, 7 → 4+3).
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_row_class(): string {

		return $this->rendered_count > 0 ? 'wpforms-dashboard-stat-cards-count-' . $this->rendered_count : '';
	}

	/**
	 * Format the raw stats into display-ready strings for the AJAX payload.
	 *
	 * Shared with the Pro Ajax::get_stats() response so the initial render and
	 * the refresh can never disagree. Never mutates the cached array upstream —
	 * the cache stays raw.
	 *
	 * @since 2.0.2
	 *
	 * @param array $stats Raw cached `stats` aggregates.
	 *
	 * @return array `[ 'value' => string, 'value_short' => string ]` per card id.
	 */
	public function format( array $stats ): array {

		$formatted = [];

		foreach ( $this->get_catalog() as $id => $card ) {
			if ( ! array_key_exists( $id, $stats ) ) {
				continue;
			}

			$value = $this->format_value( $stats[ $id ], $card['format'] );

			$formatted[ $id ] = [
				'value'       => $value,
				'value_short' => $this->get_short_value( $stats[ $id ], $card['format'], $value ),
			];
		}

		return $formatted;
	}

	/**
	 * The card catalog.
	 *
	 * Keys are the cached `stats` keys and the `data-stat-card` DOM ids. Array
	 * order is the render order.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_catalog(): array {

		/**
		 * Filter the Dashboard stat cards catalog.
		 *
		 * Keys are the cached `stats` keys and the `data-stat-card` DOM ids;
		 * array order is the render order.
		 *
		 * @since 2.0.2
		 *
		 * @param array $catalog Stat cards catalog.
		 */
		return (array) apply_filters(
			'wpforms_admin_dashboard_stat_cards_get_catalog',
			[
				'forms'            => [
					'icon'   => 'fa-brands fa-wpforms',
					'tint'   => 'orange',
					'label'  => __( 'Forms', 'wpforms-lite' ),
					'format' => 'count',
				],
				'total_entries'    => [
					'icon'       => 'fa-regular fa-copy',
					'tint'       => 'orange',
					'label'      => __( 'Total Entries', 'wpforms-lite' ),
					'label_lite' => __( 'Entries Backed Up', 'wpforms-lite' ),
					'format'     => 'count',
				],
				'total_views'      => [
					'icon'   => 'fa-regular fa-eye',
					'tint'   => 'blue',
					'label'  => __( 'Total Views', 'wpforms-lite' ),
					'format' => 'count',
				],
				'spam_entries'     => [
					'icon'   => 'fa-solid fa-ban',
					'tint'   => 'red',
					'label'  => __( 'Spam Entries', 'wpforms-lite' ),
					'format' => 'count',
					'show'   => 'pro_plugin',
					'cta'    => 'anti_spam',
				],
				'total_payments'   => [
					'icon'   => 'fa-regular fa-credit-card',
					'tint'   => 'green',
					'label'  => __( 'Total Payments', 'wpforms-lite' ),
					'format' => 'count',
					'show'   => 'gateway',
				],
				'total_sales'      => [
					'icon'   => 'fa-regular fa-money-bill-1',
					'tint'   => 'green',
					'label'  => __( 'Total Sales', 'wpforms-lite' ),
					'format' => 'amount',
					'show'   => 'gateway',
				],
				'coupons_redeemed' => [
					'icon'        => 'fa-solid fa-tags',
					'tint'        => 'green',
					'label'       => __( 'Coupons Redeemed', 'wpforms-lite' ),
					'label_short' => __( 'Coupons', 'wpforms-lite' ),
					'format'      => 'count',
					'show'        => 'gateway',
					'cta'         => 'coupons',
				],
				'total_refunded'   => [
					'icon'   => 'fa-solid fa-rotate-left',
					'tint'   => 'gray',
					'label'  => __( 'Total Refunded', 'wpforms-lite' ),
					'format' => 'amount',
					'show'   => 'gateway',
				],
			]
		);
	}

	/**
	 * Whether a card renders. Evaluated at page render only — the AJAX refresh
	 * never adds or removes cards.
	 *
	 * @since 2.0.2
	 *
	 * @param array         $card   Catalog entry.
	 * @param AccessContext $access Access context.
	 *
	 * @return bool
	 */
	private function is_visible( array $card, AccessContext $access ): bool {

		switch ( $card['show'] ?? '' ) {
			case 'pro_plugin':
				// Spam storage is paid-plugin-only — the card is Basic+.
				return $access->is_pro();

			case 'gateway':
				// Payment cards are hidden for now: a 2x4 top row is too busy, and the
				// Payments widget below already covers these stats. Restore by returning
				// Helpers::is_payment_gateway_connected() — the UI gate; Cache::has_payment_gateway()
				// is the orthogonal data gate.
				return false;

			default:
				// No plan or gateway gate — always visible.
				return true;
		}
	}

	/**
	 * Resolve the card label for the current tier.
	 *
	 * @since 2.0.2
	 *
	 * @param array         $card   Catalog entry.
	 * @param AccessContext $access Access context.
	 *
	 * @return string
	 */
	private function get_label( array $card, AccessContext $access ): string {

		return ! $access->is_pro() && isset( $card['label_lite'] ) ? $card['label_lite'] : $card['label'];
	}

	/**
	 * Format a raw stat value for display.
	 *
	 * @since 2.0.2
	 *
	 * @param mixed  $value  Raw value (int count or string amount).
	 * @param string $format Format type: `count` or `amount`.
	 *
	 * @return string
	 */
	private function format_value( $value, string $format ): string {

		$formatted = $format === 'amount'
			? wpforms_format_amount( wpforms_sanitize_amount( (string) $value ), true )
			: number_format_i18n( (int) $value );

		return html_entity_decode( $formatted, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Compact alternative for a value too long to fit a narrow card.
	 *
	 * Returns an empty string when the full value already fits, so short
	 * figures never lose precision they did not need to. A container query in
	 * the stat-card SCSS decides which of the two is shown.
	 *
	 * @since 2.0.2
	 *
	 * @param mixed  $value  Raw value (int count or string amount).
	 * @param string $format Format type: `count` or `amount`.
	 * @param string $full   Formatted full value.
	 *
	 * @return string
	 */
	private function get_short_value( $value, string $format, string $full ): string {

		if ( mb_strlen( $full ) <= self::VALUE_FIT_CHARS ) {
			return '';
		}

		$number = $format === 'amount' ? (float) wpforms_sanitize_amount( (string) $value ) : (float) $value;

		$units = [
			/* translators: Abbreviated billions suffix on a dashboard metric, e.g. $1.2B. */
			1000000000 => _x( 'B', 'abbreviated billions', 'wpforms-lite' ),
			/* translators: Abbreviated millions suffix on a dashboard metric, e.g. $1.2M. */
			1000000    => _x( 'M', 'abbreviated millions', 'wpforms-lite' ),
			/* translators: Abbreviated thousands suffix on a dashboard metric, e.g. $1.2K. */
			1000       => _x( 'K', 'abbreviated thousands', 'wpforms-lite' ),
		];

		foreach ( $units as $divisor => $suffix ) {
			if ( abs( $number ) < $divisor ) {
				continue;
			}

			$mantissa = $number / $divisor;

			// Hold three significant figures at every scale — `$1.2M`, `$12.3M`, `$576K`.
			// Dropping the decimal at 100 also sidesteps trimming a locale-aware `.0`.
			$compact = number_format_i18n( $mantissa, abs( $mantissa ) < 100 ? 1 : 0 ) . $suffix;

			return $format === 'amount' ? $this->add_currency_symbol( $compact ) : $compact;
		}

		// A long value below the smallest unit is a count with wide separators; leave it be.
		return '';
	}

	/**
	 * Attach the configured currency symbol to an already-abbreviated number.
	 *
	 * Mirrors the symbol placement in wpforms_format_amount(), which cannot be
	 * reused here: it always renders the currency's fixed decimal count, and an
	 * abbreviated figure needs at most one.
	 *
	 * @since 2.0.2
	 *
	 * @param string $number Abbreviated number, unit suffix included.
	 *
	 * @return string
	 */
	private function add_currency_symbol( string $number ): string {

		$currency   = strtoupper( wpforms_get_currency() );
		$currencies = wpforms_get_currencies();
		$symbol     = html_entity_decode( $currencies[ $currency ]['symbol'] ?? '', ENT_QUOTES, 'UTF-8' );

		if ( $symbol === '' ) {
			return $number;
		}

		if ( ( $currencies[ $currency ]['symbol_pos'] ?? 'left' ) !== 'right' ) {
			return $symbol . $number;
		}

		/** This filter is documented in wpforms/includes/functions/payments.php. */
		$padding = apply_filters( 'wpforms_currency_symbol_padding', ' ' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Owned by wpforms_format_amount(); re-applied so the abbreviated amount pads identically.

		return $number . $padding . $symbol;
	}

	/**
	 * Resolve a card's conditional CTA. Empty array = no CTA.
	 *
	 * @since 2.0.2
	 *
	 * @param array $card Catalog entry.
	 *
	 * @return array
	 */
	private function get_cta( array $card ): array {

		$type = $card['cta'] ?? '';

		if ( $type === 'anti_spam' ) {
			return $this->get_anti_spam_cta();
		}

		if ( $type === 'coupons' ) {
			return $this->get_coupons_cta();
		}

		return [];
	}

	/**
	 * The Spam Entries card's Anti-Spam Setup CTA.
	 *
	 * Shown until some form logs spam entries. Value and CTA are independent axes —
	 * a non-zero spam count with a visible CTA is a legitimate state.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_anti_spam_cta(): array {

		if ( ! $this->is_education_available() || $this->is_anti_spam_configured() ) {
			return [];
		}

		$modal  = ActiveLayerHelper::get_modal_data();
		$action = $modal['attrs']['data-action'] ?? '';

		if ( $action === 'install' ) {
			$modal['link_text'] = __( 'Install ActiveLayer', 'wpforms-lite' );
		} elseif ( $action === 'activate' ) {
			$modal['link_text'] = __( 'Activate ActiveLayer', 'wpforms-lite' );
		} else {
			$modal['link_text'] = __( 'ActiveLayer Dashboard', 'wpforms-lite' );
		}

		return [
			'type'        => 'anti_spam',
			'label'       => __( 'Anti-Spam Setup', 'wpforms-lite' ),
			'label_short' => __( 'Setup', 'wpforms-lite' ),
			'tooltip'     => [
				'text'       => sprintf(
					wp_kses( /* translators: %1$s - Store Spam Entries setting name, %2$s - ActiveLayer plugin name. */
						__( 'Enable %1$s in your form settings, or install %2$s for the best protection.', 'wpforms-lite' ),
						[ 'strong' => [] ]
					),
					$this->get_store_spam_entries_link(),
					'<strong>ActiveLayer</strong>'
				),
				'button'     => $modal,
				'learn_more' => wpforms_utm_link(
					'https://wpforms.com/docs/how-to-prevent-spam-in-wpforms/',
					'Dashboard - Stat Cards',
					'Anti-Spam Setup'
				),
			],
		];
	}

	/**
	 * Get the "Store Spam Entries in the Database" label, linked to a form's spam settings.
	 *
	 * The setting lives on the form, not in global settings, so the promo has to name a
	 * concrete form. It links to the form at the top of the Entries widget — the busiest one
	 * in the selected range, and the one the reader is already looking at.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	private function get_store_spam_entries_link(): string {

		$label   = '<strong>' . esc_html__( 'Store Spam Entries in the Database', 'wpforms-lite' ) . '</strong>';
		$form_id = $this->get_link_form_id();

		if ( $form_id === 0 ) {
			return $label;
		}

		$url = add_query_arg(
			[
				'page'    => 'wpforms-builder',
				'view'    => 'settings',
				'section' => 'anti_spam',
				'form_id' => $form_id,
			],
			admin_url( 'admin.php' )
		);

		return '<a href="' . esc_url( $url ) . '">' . $label . '</a>';
	}

	/**
	 * Resolve the form the Anti-Spam promo links to: the first row of the Entries widget.
	 *
	 * `entries.forms` is the count-sorted superset the widget builds its rows from, so its
	 * first key is the range's busiest form. It holds only forms with entries in the range,
	 * so a range with none falls back to the newest published form — any form will do at that
	 * point, since the promo is about switching the setting on at all.
	 *
	 * @since 2.0.2
	 *
	 * @return int Form ID, or `0` when the site has no form to link to.
	 */
	private function get_link_form_id(): int {

		$forms = is_array( $this->data['entries']['forms'] ?? null ) ? $this->data['entries']['forms'] : [];

		// `reset()` + `key()` rather than `array_key_first()`, which needs PHP 7.3.
		if ( $forms ) {
			reset( $forms );

			return absint( key( $forms ) );
		}

		$newest = wpforms()->obj( 'form' )->get(
			'',
			[
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'posts_per_page' => 1,
				// `get_multiple()` defaults to `nopaging`, which would ignore the limit above.
				'nopaging'       => false,
			]
		);

		return empty( $newest ) ? 0 : absint( $newest[0] );
	}

	/**
	 * Whether spam entries are already being logged.
	 *
	 * A cached scan of published forms for the `store_spam_entries` builder toggle, and
	 * nothing else: this card promotes spam-entry logging, which neither ActiveLayer nor a
	 * CAPTCHA provides, so a connected ActiveLayer does not satisfy it. The Spam & Security
	 * Checkup widget below is what promotes those.
	 *
	 * Deliberately ignores `anti_spam.filtering_store_spam` — the builder auto-enables it for
	 * new forms, so counting it would satisfy the condition on virtually every site.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_anti_spam_configured(): bool {

		$cached = Transient::get( self::CONFIGURED_TRANSIENT );

		// Transient::get() returns false on a miss; the yes/no sentinel keeps a "not configured" result distinct from that.
		if ( $cached !== false ) {
			return $cached === 'yes';
		}

		$is_configured = $this->query_anti_spam_configured();

		Transient::set( self::CONFIGURED_TRANSIENT, $is_configured ? 'yes' : 'no', DAY_IN_SECONDS );

		return $is_configured;
	}

	/**
	 * Scan published forms for the `store_spam_entries` builder toggle.
	 *
	 * A prepared LIKE pre-filter fetches only the forms that mention the setting,
	 * instead of loading every published form.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function query_anti_spam_configured(): bool {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$candidates = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_content FROM {$wpdb->posts} WHERE post_type = 'wpforms' AND post_status = 'publish' AND post_content LIKE %s",
				'%' . $wpdb->esc_like( 'store_spam_entries' ) . '%'
			)
		);

		foreach ( $candidates as $content ) {
			$form_data = wpforms_decode( $content );

			if ( ! empty( $form_data['settings']['store_spam_entries'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Clear the cached anti-spam-configured flag.
	 *
	 * @since 2.0.2
	 */
	public static function clear_configured_cache(): void {

		Transient::delete( self::CONFIGURED_TRANSIENT );
	}

	/**
	 * Whether the admin education system is available.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_education_available(): bool {

		// Unregistered when the `wpforms_admin_education` kill switch is off.
		return (bool) wpforms()->obj( 'education_feature_tooltip' );
	}

	/**
	 * The Coupons Redeemed card's per-plan CTA.
	 *
	 * Consumes the Addons API `action` field — no custom entitlement logic.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_coupons_cta(): array {

		$addons = wpforms()->obj( 'addons' );
		$addon  = $addons ? $addons->get_addon( 'coupons' ) : [];
		$action = $addon['action'] ?? 'upgrade';

		if ( $action === '' || $action === 'incompatible' ) {
			// Active (or version-blocked edge): render the count, no CTA.
			return [];
		}

		if ( $action === 'install' || $action === 'activate' ) {
			// The one-click flow rides the education modal JS.
			if ( ! $this->is_education_available() ) {
				return [];
			}

			// An already-installed-but-inactive addon only needs activating, so the CTA
			// says so — matching the Payments widget's Coupons tile. Keyed off $action
			// rather than branched, to keep this method under the complexity ceiling.
			$labels = [
				'install'  => [
					'label' => __( 'Install & Activate', 'wpforms-lite' ),
					'short' => __( 'Install', 'wpforms-lite' ),
				],
				'activate' => [
					'label' => __( 'Activate', 'wpforms-lite' ),
					'short' => __( 'Activate', 'wpforms-lite' ),
				],
			];

			return [
				'type'          => 'education',
				'replace_value' => true,
				'label'         => $labels[ $action ]['label'],
				'label_short'   => $labels[ $action ]['short'],
				'url'           => '#',
				'attrs'         => [
					'class'       => 'education-modal',
					'data-action' => $action,
					'data-name'   => $addon['modal_name'] ?? 'Coupons addon',
					'data-path'   => $addon['path'] ?? '',
					'data-url'    => $addon['url'] ?? '',
					'data-type'   => 'addon',
					'data-nonce'  => $addon['nonce'] ?? '',
				],
			];
		}

		// `upgrade` / `license`.
		return [
			'type'          => 'link',
			'replace_value' => true,
			'label'         => __( 'Upgrade', 'wpforms-lite' ),
			'url'           => wpforms_admin_upgrade_link( 'Dashboard - Stat Cards', 'Coupons Redeemed' ),
			'attrs'         => [
				'target' => '_blank',
				'rel'    => 'noopener noreferrer',
			],
		];
	}
}
