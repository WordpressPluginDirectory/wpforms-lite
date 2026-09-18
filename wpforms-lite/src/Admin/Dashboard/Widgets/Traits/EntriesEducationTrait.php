<?php

namespace WPForms\Admin\Dashboard\Widgets\Traits;

use WPForms\Admin\Addons\Install;
use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Lite\Integrations\LiteConnect\Integration as LiteConnectIntegration;
use WPForms\Lite\Integrations\LiteConnect\LiteConnect;

/**
 * Lite Connect bar and Form Abandonment promo for the Dashboard "Forms" widget.
 *
 * Not standalone. The using class must declare the `FIRST_VISIT_OPTION` constant
 * and supply an `AccessContext` to the promo methods.
 *
 * @since 2.0.2
 */
trait EntriesEducationTrait {

	/**
	 * Render the Lite Connect bar — the backup-status bar when connected, the
	 * opt-in toggle when not. The Lite Connect education class drives the bar's
	 * script, modal, and toggle AJAX; this only renders the markup.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	private function render_lite_connect_bar(): string {

		// Paid tiers show the local entries DB, so there is no Lite Connect bar.
		if ( wpforms()->is_pro() ) {
			return '';
		}

		// Skip when Lite Connect cannot run (e.g. filtered off) — the toggle would be a dead end.
		if ( ! LiteConnect::is_allowed() ) {
			return '';
		}

		$is_enabled = LiteConnect::is_enabled();

		$toggle = wpforms_panel_field_toggle_control(
			[
				'control-class' => 'wpforms-setting-lite-connect-auto-save-toggle',
			],
			'wpforms-setting-lite-connect-enabled',
			'',
			esc_html__( 'Enable Form Entry Backups', 'wpforms-lite' ),
			$is_enabled,
			'disabled'
		);

		return (string) wpforms_render(
			'admin/dashboard/widgets/entries-lite-connect',
			[
				'toggle'             => $toggle,
				'is_enabled'         => $is_enabled,
				'entries_since_info' => $this->get_lite_connect_entries_since_info(),
			],
			true
		);
	}

	/**
	 * Generate Lite Connect entries information: the backed-up count and the
	 * enabled-since date. Per-consumer duplicate of the same builder in the
	 * education classes (`Education\LiteConnect`, `Education\Admin\DidYouKnow`)
	 * — keep the copy in sync so the string matches what the toggle AJAX returns.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	private function get_lite_connect_entries_since_info(): string {

		$entries_count = LiteConnectIntegration::get_new_entries_count();
		$enabled_since = LiteConnectIntegration::get_enabled_since();

		$string = sprintf(
			esc_html( /* translators: %d - backed up entries count. */
				_n(
					'%d entry backed up',
					'%d entries backed up',
					$entries_count,
					'wpforms-lite'
				)
			),
			absint( $entries_count )
		);

		if ( ! empty( $enabled_since ) ) {
			$string .= ' ';
			$string .= esc_html(
				sprintf( /* translators: %1$s - time when Lite Connect was enabled. */
					__( 'since %1$s', 'wpforms-lite' ),
					wpforms_date_format( $enabled_since, '', true )
				)
			);
		}

		return $string;
	}

	/**
	 * Whether Lite Connect is available and connected — the state that moves the
	 * bar from the footer (opt-in toggle) to between graph and table (backup status).
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_lite_connect_connected(): bool {

		if ( wpforms()->is_pro() ) {
			return false;
		}

		return LiteConnect::is_allowed() && LiteConnect::is_enabled();
	}

	/**
	 * Render the Form Abandonment notice, or an empty string when it should not show.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return string
	 */
	private function render_abandonment_promo( AccessContext $access ): string {

		$promo = $this->get_abandonment_promo( $access );

		if ( empty( $promo ) ) {
			return '';
		}

		return (string) wpforms_render(
			'admin/dashboard/widgets/entries-promo',
			$promo,
			true
		);
	}

	/**
	 * Build the Form Abandonment notice data, or an empty array when it should not
	 * show. The notice is time-gated, not data-driven — no abandonment rate is computed.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return array Notice data (title, link), or [] when hidden.
	 */
	private function get_abandonment_promo( AccessContext $access ): array {

		// Nothing to promote once the Form Abandonment addon is active.
		if ( wpforms_is_addon_initialized( 'form-abandonment' ) ) {
			return [];
		}

		// Respect a prior per-user dismissal.
		if ( ! empty( $access->get_dismissals()['edu-dashboard-abandonment-promo'] ) ) {
			return [];
		}

		// Hold the notice back until 15 days after the first dashboard visit.
		$first_visit = (int) get_option( self::FIRST_VISIT_OPTION, 0 );

		if ( ! $first_visit || time() - $first_visit < 15 * DAY_IN_SECONDS ) {
			return [];
		}

		$is_pro_plus = in_array( $access->get_tier(), [ 'pro', 'elite', 'agency', 'ultimate' ], true );

		if ( $is_pro_plus ) {
			$title = __( 'Your Forms Are Seeing High Abandonment', 'wpforms-lite' );
			$link  = $this->get_abandonment_install_link();
		} else {
			$title = __( 'Get More Leads From Your Forms', 'wpforms-lite' );
			$link  = [
				'text'       => __( 'Upgrade to Pro', 'wpforms-lite' ),
				'url'        => wpforms_admin_upgrade_link( 'Dashboard - Entries', 'Form Abandonment' ),
				'action'     => '',
				'plugin'     => '',
				'external'   => true,
				'is_upgrade' => true,
			];
		}

		return [
			'title' => $title,
			'link'  => $link,
		];
	}

	/**
	 * Resolve the Form Abandonment install CTA from the addons feed: an in-place
	 * Install/Activate when entitled and installable, else the Addons-page link.
	 *
	 * @since 2.0.2
	 *
	 * @return array Shared install-link payload (text, url, action, plugin, external).
	 */
	private function get_abandonment_install_link(): array {

		$addons = wpforms()->obj( 'addons' );
		$addon  = $addons ? (array) $addons->get_addon( 'form-abandonment' ) : [];

		$is_activate = ( $addon['action'] ?? '' ) === 'activate';
		$cta_action  = $is_activate ? 'activate-plugin' : 'install-plugin';

		// Entitled, but unable to install in place (multisite, DISALLOW_FILE_MODS, or a user
		// without the capability) — send them to the Addons page, which renders its own gated
		// state, rather than a button the endpoint can only reject.
		if ( empty( $addon['path'] ) || empty( $addon['plugin_allow'] ) || ! Install::can_install_and_activate( $cta_action, 'addon' ) ) {
			return [
				'text'     => __( 'Install', 'wpforms-lite' ),
				'url'      => admin_url( 'admin.php?page=wpforms-addons' ),
				'action'   => '',
				'plugin'   => '',
				'external' => false,
			];
		}

		return [
			'text'     => $is_activate ? __( 'Activate', 'wpforms-lite' ) : __( 'Install & Activate', 'wpforms-lite' ),
			'url'      => '#',
			'action'   => $cta_action,
			'plugin'   => (string) $addon['path'],
			'external' => false,
		];
	}
}
