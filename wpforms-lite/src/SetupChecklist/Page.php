<?php

namespace WPForms\SetupChecklist;

use WPForms\Education\ActiveLayer\Helper as ActiveLayer;
use WPForms\SetupWizard\Service\SettingsDetector;
use WPForms\Integrations\Stripe\Stripe;

/**
 * Setup Checklist admin page controller.
 *
 * Builds the page view-model (sections with resolved CTAs, and the promo datasets from
 * {@see Promos}) and renders it through the `admin/setup-checklist/*` templates.
 *
 * @since 2.0.0
 */
class Page {

	/**
	 * Admin page slug — the stable deep link used by docs, in-product links,
	 * and the wizard hand-off.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public const SLUG = 'wpforms-setup-checklist';

	/**
	 * Checklist model.
	 *
	 * @since 2.0.0
	 *
	 * @var Checklist
	 */
	private $checklist;

	/**
	 * Promo sections model (features, integrations, growth tools, and install CTAs).
	 *
	 * @since 2.0.0
	 *
	 * @var Promos
	 */
	private $promos;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Checklist $checklist Checklist model.
	 * @param Promos    $promos    Promo sections model.
	 */
	public function __construct( Checklist $checklist, Promos $promos ) {

		$this->checklist = $checklist;
		$this->promos    = $promos;
	}

	/**
	 * Register hooks, only while the current request is the checklist page.
	 *
	 * @since 2.0.0
	 */
	public function hooks(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

		if ( $page !== self::SLUG ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wpforms_admin_page', [ $this, 'output' ] );
		add_action( 'in_admin_header', [ $this, 'hide_admin_notices' ], PHP_INT_MAX );
	}

	/**
	 * Suppress all admin notices on the checklist page.
	 *
	 * This is a focused onboarding screen — no admin notices belong here, including
	 * WPForms' own (e.g. the Lite Connect "can't reach backup server" warning), which
	 * WordPress otherwise relocates to just after the page's first <h1>, landing inside
	 * the title bar. Runs on `in_admin_header`, before the `admin_notices` hooks fire.
	 *
	 * @since 2.0.0
	 */
	public function hide_admin_notices(): void {

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
	}

	/**
	 * Get the page's stable admin URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function get_url(): string {

		return add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );
	}

	/**
	 * Enqueue the page assets.
	 *
	 * @since 2.0.0
	 */
	public function enqueue_assets(): void {

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-setup-checklist',
			WPFORMS_PLUGIN_URL . "assets/css/admin/setup-checklist{$min}.css",
			[ 'wpforms-admin' ],
			WPFORMS_VERSION
		);

		wp_enqueue_style( 'jquery-confirm' );

		wp_enqueue_script(
			'wpforms-setup-checklist',
			WPFORMS_PLUGIN_URL . "assets/js/admin/setup-checklist{$min}.js",
			[ 'jquery', 'jquery-confirm' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-setup-checklist',
			'wpforms_setup_checklist',
			[
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'dismiss_nonce' => wp_create_nonce( Ajax::DISMISS_ACTION ),
				'install_nonce' => wp_create_nonce( Ajax::INSTALL_PLUGIN_ACTION ),
				'dismiss'       => [
					'title'   => esc_html__( 'Are you sure?', 'wpforms-lite' ),
					'content' => esc_html__( 'This will permanently dismiss the Setup Checklist, and you will not be able to bring it back. This cannot be undone.', 'wpforms-lite' ),
					'confirm' => esc_html__( 'Yes, Dismiss', 'wpforms-lite' ),
					'cancel'  => esc_html__( 'Cancel', 'wpforms-lite' ),
					'error'   => esc_html__( 'Something went wrong dismissing the checklist. Please try again.', 'wpforms-lite' ),
				],
				'plugins'       => [
					'installing' => esc_html__( 'Installing…', 'wpforms-lite' ),
					'activating' => esc_html__( 'Activating…', 'wpforms-lite' ),
					'installed'  => esc_html__( 'Installed', 'wpforms-lite' ),
					'error'      => esc_html__( 'Something went wrong. Please install the plugin from the Plugins page.', 'wpforms-lite' ),
				],
			]
		);
	}

	/**
	 * Render the page.
	 *
	 * @since 2.0.0
	 */
	public function output(): void {

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpforms_render() returns escaped HTML.
		echo wpforms_render(
			'admin/setup-checklist/page',
			[
				'sections'     => $this->view_sections(),
				'features'     => [
					'tiles'  => $this->promos->feature_tiles(),
					'footer' => [
						'text'     => __( 'Plus dozens of other powerful features and addons to meet all your form-building needs.', 'wpforms-lite' ),
						'cta_text' => __( 'View All Features & Addons', 'wpforms-lite' ),
						'cta_url'  => $this->view_all_url( 'admin.php?page=wpforms-addons', 'https://wpforms.com/features/', 'View All Features' ),
					],
				],
				'integrations' => [
					'cards'  => $this->promos->integration_cards(),
					'footer' => [
						'text'     => sprintf(
							/* translators: %1$s - Zapier, %2$s - Uncanny Automator, %3$s - Make (each emphasised). */
							__( '30+ native integrations, plus thousands more via %1$s, %2$s, and %3$s.', 'wpforms-lite' ),
							'<strong>Zapier</strong>',
							'<strong>Uncanny Automator</strong>',
							'<strong>Make</strong>'
						),
						'cta_text' => __( 'View All Integrations', 'wpforms-lite' ),
						'cta_url'  => $this->view_all_url( 'admin.php?page=wpforms-settings&view=integrations', 'https://wpforms.com/integrations/', 'View All Integrations' ),
						'modifier' => 'secondary',
					],
				],
				'growth_tools' => [
					'tiles' => $this->promos->growth_tool_tiles(),
				],
			],
			true
		);
	}

	/**
	 * Build the per-section view-model: completion state plus each item resolved for render.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array>
	 */
	private function view_sections(): array {

		$sections = [];

		foreach ( $this->checklist->get_sections() as $section ) {
			$items = [];

			foreach ( $section['items'] as $item ) {
				$items[] = $this->view_item( $item );
			}

			$sections[] = [
				'id'          => $section['id'],
				'title'       => $section['title'],
				'is_complete' => ! empty( $section['complete'] ),
				'items'       => $items,
			];
		}

		return $sections;
	}

	/**
	 * Build a single item view-model. The payment gateway item becomes the Stripe hero when
	 * the integration is available, otherwise it renders as a normal item.
	 *
	 * @since 2.0.0
	 *
	 * @param array $item Item enriched with completion state.
	 *
	 * @return array
	 */
	private function view_item( array $item ): array {

		if ( $item['id'] === 'payment_gateway' ) {
			$gateway = $this->get_stripe_gateway();

			if ( $gateway !== null ) {
				return [
					'type'                => 'payment',
					'gateway'             => $gateway,
					'is_stripe_connected' => ( new SettingsDetector() )->is_stripe_configured(),
					'is_pro_plus'         => $this->promos->is_pro_plus(),
					'settings_url'        => admin_url( 'admin.php?page=wpforms-settings&view=payments' ),
				];
			}
		}

		$is_complete = ! empty( $item['complete'] );

		return [
			'type'         => 'item',
			'id'           => $item['id'],
			'title'        => $item['title'],
			'description'  => $item['description'],
			'is_complete'  => $is_complete,
			// The check glyph is decorative (aria-hidden), so completion is voiced to
			// screen readers with a status label read just before the item title.
			'status_label' => $is_complete ? __( 'Completed', 'wpforms-lite' ) : __( 'Not completed', 'wpforms-lite' ),
			// A complete item shows no buttons — the green check is the only cue.
			'ctas'         => $is_complete ? [] : $this->resolve_ctas( $item ),
		];
	}

	/**
	 * Resolve an item's CTAs to their renderable shapes, dropping the ones that resolve empty.
	 *
	 * @since 2.0.0
	 *
	 * @param array $item Item enriched with completion state.
	 *
	 * @return array<int, array>
	 */
	private function resolve_ctas( array $item ): array {

		$ctas     = $item['ctas'] ?? ( ! empty( $item['cta'] ) ? [ $item['cta'] ] : [] );
		$resolved = [];

		foreach ( $ctas as $cta ) {
			$cta = $this->resolve_cta( $cta );

			if ( ! empty( $cta ) ) {
				$resolved[] = $cta;
			}
		}

		return $resolved;
	}

	/**
	 * Resolve a CTA to its renderable shape, expanding dynamic ones.
	 *
	 * @since 2.0.0
	 *
	 * @param array $cta CTA data, possibly a dynamic marker (`wpmailsmtp`, `wpconsent`,
	 *                   `captcha`, `activelayer`).
	 *
	 * @return array Renderable CTA data for the item-button template.
	 */
	private function resolve_cta( array $cta ): array {

		if ( ! empty( $cta['wpmailsmtp'] ) ) {
			return $this->plugin_item_cta(
				'wp-mail-smtp/wp_mail_smtp.php',
				__( 'Install WP Mail SMTP', 'wpforms-lite' ),
				__( 'Activate WP Mail SMTP', 'wpforms-lite' ),
				__( 'Set Up WP Mail SMTP', 'wpforms-lite' ),
				admin_url( 'admin.php?page=wp-mail-smtp' )
			);
		}

		if ( ! empty( $cta['wpconsent'] ) ) {
			return $this->plugin_item_cta(
				'wpconsent-cookies-banner-privacy-suite/wpconsent.php',
				__( 'Install WPConsent', 'wpforms-lite' ),
				__( 'Activate WPConsent', 'wpforms-lite' ),
				__( 'Set Up WPConsent', 'wpforms-lite' ),
				admin_url( 'admin.php?page=wpconsent-onboarding' )
			);
		}

		if ( ! empty( $cta['captcha'] ) ) {
			// Drop the CAPTCHA fallback once ActiveLayer is installed — the item then
			// funnels the user to set up ActiveLayer instead of offering two paths.
			if ( class_exists( ActiveLayer::class ) && ActiveLayer::is_installed() ) {
				return [];
			}

			return [
				'label'    => __( 'Set Up Captcha', 'wpforms-lite' ),
				'url'      => admin_url( 'admin.php?page=wpforms-settings&view=captcha' ),
				'modifier' => 'grey',
			];
		}

		if ( empty( $cta['activelayer'] ) ) {
			return $cta;
		}

		if ( ! class_exists( ActiveLayer::class ) ) {
			return [];
		}

		if ( ActiveLayer::is_activated() ) {
			return [
				'label'    => __( 'Set Up ActiveLayer', 'wpforms-lite' ),
				'url'      => admin_url( 'admin.php?page=activelayer-settings' ),
				'modifier' => 'grey',
			];
		}

		return [
			'label'    => ActiveLayer::is_installed()
				? __( 'Activate ActiveLayer', 'wpforms-lite' )
				: __( 'Install ActiveLayer', 'wpforms-lite' ),
			'modifier' => 'grey',
			'action'   => ActiveLayer::is_installed() ? 'activate-plugin' : 'install-plugin',
			'plugin'   => ActiveLayer::FILE,
			'reload'   => true,
		];
	}

	/**
	 * Build an item-button CTA for an in-place plugin install that reloads on success.
	 *
	 * Shared by the WP Mail SMTP and WPConsent checklist items: when the plugin is already
	 * active it links to the plugin's own setup screen; otherwise it installs or activates
	 * in place through the shared endpoint, then reloads so the row re-evaluates.
	 *
	 * @since 2.0.0
	 *
	 * @param string $file      Plugin main-file path (folder/file.php).
	 * @param string $install   Label when the plugin is absent.
	 * @param string $activate  Label when the plugin is installed but inactive.
	 * @param string $setup     Label when the plugin is already active.
	 * @param string $setup_url Setup screen URL used in the active state.
	 *
	 * @return array
	 */
	private function plugin_item_cta( string $file, string $install, string $activate, string $setup, string $setup_url ): array {

		$link = $this->promos->install_link( [ $file ] );

		if ( $link['action'] === 'active' ) {
			return [
				'label'    => $setup,
				'url'      => $setup_url,
				'modifier' => 'grey',
			];
		}

		return [
			'label'    => $link['installed'] ? $activate : $install,
			'modifier' => 'grey',
			'action'   => $link['action'],
			'plugin'   => $link['plugin'],
			'reload'   => true,
		];
	}

	/**
	 * Get the Stripe gateway tile data from the Payments empty state provider.
	 *
	 * @since 2.0.0
	 *
	 * @return array|null Gateway data, or null when the Stripe integration is unavailable.
	 */
	private function get_stripe_gateway(): ?array {

		$stripe = Stripe::class;

		if ( ! class_exists( $stripe ) || ! method_exists( $stripe, 'get_started_gateway' ) ) {
			return null;
		}

		$gateway = $stripe::get_started_gateway();

		if ( ! is_array( $gateway ) ) {
			return null;
		}

		// Route the Connect button through the checklist's own OAuth kickoff so the
		// post-connect redirect lands back on the checklist (see StripeConnect),
		// rather than the payments settings page.
		$gateway['url'] = add_query_arg(
			StripeConnect::KICKOFF_ARG,
			1,
			admin_url( 'admin.php' )
		);

		return $gateway;
	}

	/**
	 * Resolve a "View All" footer URL.
	 *
	 * @since 2.0.0
	 *
	 * @param string $admin_path    The wp-admin path used on Pro and Elite.
	 * @param string $marketing_url Marketing URL used on the other tiers.
	 * @param string $utm_content   UTM content tag for the marketing URL.
	 *
	 * @return string
	 */
	private function view_all_url( string $admin_path, string $marketing_url, string $utm_content ): string {

		if ( $this->promos->is_pro_plus() ) {
			return admin_url( $admin_path );
		}

		return wpforms_utm_link( $marketing_url, 'Setup Checklist', $utm_content );
	}
}
