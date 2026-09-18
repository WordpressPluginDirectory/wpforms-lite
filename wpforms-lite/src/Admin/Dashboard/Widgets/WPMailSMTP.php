<?php

namespace WPForms\Admin\Dashboard\Widgets;

use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\WidgetState;
use WPForms\Integrations\SMTP\Helpers as SMTPHelpers;

/**
 * "WP Mail SMTP by WPForms" promo sidebar widget.
 *
 * A dismissible cross-promo for the free WP Mail SMTP plugin: an install CTA when
 * WP Mail SMTP is not active, a setup CTA when active but not configured, and hidden
 * once configured or dismissed. Identical on every tier.
 *
 * @since 2.0.2
 */
class WPMailSMTP extends AbstractWidget {

	/**
	 * Column placement: 'main' or 'sidebar'.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'sidebar';

	/**
	 * Default sort position within the column (below License 5, above What's New 20).
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 10;

	/**
	 * Education dismiss section key (stored as 'edu-{section}' in user meta).
	 *
	 * @since 2.0.2
	 */
	private const DISMISS_SECTION = 'dashboard-wp-mail-smtp';

	/**
	 * WP Mail SMTP download URL (WordPress.org).
	 *
	 * @since 2.0.2
	 */
	private const DOWNLOAD_URL = 'https://downloads.wordpress.org/plugin/wp-mail-smtp.zip';

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'wp-mail-smtp';
	}

	/**
	 * Get the widget title. Empty — the whole card is rendered as one flat block.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_title(): string {

		return '';
	}

	/**
	 * The card is user-dismissible.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public function is_dismissible(): bool {

		return true;
	}

	/**
	 * Get the widget state.
	 *
	 * Hidden when dismissed or when WP Mail SMTP is configured; otherwise the
	 * 'setup' variant (active, unconfigured) or 'install' variant (not active).
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState {

		$dismissals = $access->get_dismissals();

		if ( ! empty( $dismissals[ 'edu-' . self::DISMISS_SECTION ] ) ) {
			return new WidgetState( false );
		}

		// Resolve activation once; when inactive, skip the Options load in is_configured().
		if ( ! SMTPHelpers::is_active() ) {
			return new WidgetState( true, 'install' );
		}

		if ( SMTPHelpers::is_configured() ) {
			return new WidgetState( false );
		}

		return new WidgetState( true, 'setup' );
	}

	/**
	 * Render the whole card as one flat block (logo, title, badge, social proof,
	 * copy, state-dependent CTA, dismiss). The widget has no separate title bar,
	 * so it renders everything through the shell's head region and leaves the body
	 * empty — avoiding the head/body separator the shared shell draws otherwise.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant: 'install' or 'setup'.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_head( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $data and $access are part of the contract signature.

		$setup_url = admin_url( 'admin.php?page=wp-mail-smtp-setup-wizard' );

		$template_data = [
			'variant'         => $variant,
			'logo_url'        => WPFORMS_PLUGIN_URL . 'assets/images/smtp/pattie.svg',
			'setup_url'       => $setup_url,
			'dismiss_section' => self::DISMISS_SECTION,
			'install_url'     => '',
			'install_nonce'   => '',
			'redirect_url'    => '',
		];

		// The install CTA data (and its nonce) is only needed for the install variant.
		if ( $variant === 'install' ) {
			$template_data['install_url']   = self::DOWNLOAD_URL;
			$template_data['install_nonce'] = wp_create_nonce( 'wpforms-admin' );
			$template_data['redirect_url']  = $setup_url;
		}

		return (string) wpforms_render( 'admin/dashboard/sidebar/wp-mail-smtp', $template_data, true );
	}

	/**
	 * Render the body. Empty — the whole card renders via render_head() (see there).
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; this widget renders as a single block via render_head().

		return '';
	}
}
