<?php

namespace WPForms\Admin\Addons;

use WPForms\SetupWizard\Service\PluginCatalog;
use WPForms\SetupWizard\Service\PluginDetector;
use WPForms\SetupWizard\SetupWizard;

/**
 * Integration promo cards shared by the Setup Checklist and the Dashboard.
 *
 * Owns the static per-tier integrations catalogs and the logic that turns them into
 * render-ready cards: license-tier selection, Setup Wizard personalization, and the
 * per-integration CTA resolution. Consumers render the cards through the shared
 * `admin/addons/integration-card` partial.
 *
 * @since 2.0.2
 */
class IntegrationCards {

	use InstallLinks;

	/**
	 * Checklist CTA mode: an active integration rests in the terminal "Installed" state.
	 *
	 * @since 2.0.2
	 */
	public const MODE_CHECKLIST = 'checklist';

	/**
	 * Dashboard CTA mode: an active integration links out to Settings → Integrations
	 * as Connect (no account configured yet) or Manage.
	 *
	 * @since 2.0.2
	 */
	public const MODE_DASHBOARD = 'dashboard';

	/**
	 * Plugin detector (installed/active status for the promoted plugins).
	 *
	 * @since 2.0.2
	 *
	 * @var PluginDetector
	 */
	private $plugin_detector;

	/**
	 * Plugin catalog (resolves slugs to addon plugin files and display names).
	 *
	 * @since 2.0.2
	 *
	 * @var PluginCatalog
	 */
	private $plugin_catalog;

	/**
	 * Memoized result of known_integration_files() — request-invariant.
	 *
	 * @since 2.0.2
	 *
	 * @var array<int, string>|null
	 */
	private $known_files;

	/**
	 * Constructor.
	 *
	 * @since 2.0.2
	 *
	 * @param PluginDetector $plugin_detector Plugin detector.
	 * @param PluginCatalog  $plugin_catalog  Plugin catalog.
	 */
	public function __construct( PluginDetector $plugin_detector, PluginCatalog $plugin_catalog ) {

		$this->plugin_detector = $plugin_detector;
		$this->plugin_catalog  = $plugin_catalog;
	}

	/**
	 * Render-ready cards for the integrations grid: tier-selected, personalized, and with
	 * each CTA link resolved.
	 *
	 * @since 2.0.2
	 *
	 * @param string $medium UTM medium identifying the consuming page (e.g. `Setup Checklist`).
	 * @param int    $max    Maximum number of cards to return.
	 * @param string $mode   CTA mode: self::MODE_CHECKLIST or self::MODE_DASHBOARD.
	 *
	 * @return array<int, array>
	 */
	public function get_cards( string $medium, int $max = 10, string $mode = self::MODE_CHECKLIST ): array {

		$upgrade_url = wpforms_admin_upgrade_link( $medium, 'Streamline Your Workflow with Seamless Integrations' );
		$cards       = [];

		foreach ( $this->get_integrations( $max ) as $integration ) {
			$integration['link'] = $this->integration_link( $integration, $upgrade_url, $mode );
			$cards[]             = $integration;
		}

		return $cards;
	}

	/**
	 * Resolve a single integration entry to its CTA link: upgrade, in-place plugin install,
	 * or in-place addon install.
	 *
	 * @since 2.0.2
	 *
	 * @param array  $integration Integration entry.
	 * @param string $upgrade_url Upgrade link target (paid tiers).
	 * @param string $mode        CTA mode: self::MODE_CHECKLIST or self::MODE_DASHBOARD.
	 *
	 * @return array
	 */
	private function integration_link( array $integration, string $upgrade_url, string $mode ): array {

		$action = $integration['action'] ?? 'install';

		if ( $action === 'upgrade' ) {
			return [
				'text'       => __( 'Upgrade', 'wpforms-lite' ),
				'url'        => $upgrade_url,
				'action'     => '',
				'plugin'     => '',
				'external'   => true,
				'is_upgrade' => true,
			];
		}

		// Free third-party plugin (e.g. Uncanny Automator) — one-click install in place; it
		// has no Settings → Integrations entry, so both modes share the "Installed" terminal state.
		if ( ! empty( $integration['plugin_slug'] ) ) {
			return $this->gated_install_link( [ $integration['basename'] ], 'plugin' );
		}

		// A WPForms addon included in this license tier — install it in place, reusing the
		// shared install endpoint (it resolves the licensed download server-side).
		$link = $this->addon_install_link( [ $integration['slug'] ] );

		if ( $mode !== self::MODE_DASHBOARD ) {
			return $link;
		}

		// Dashboard mode: a fully active addon links out to Settings → Integrations.
		if ( $link['action'] === 'active' ) {
			return [
				'text'     => $this->connect_manage_label( $integration ),
				'url'      => $this->integrations_page_url(),
				'action'   => '',
				'plugin'   => '',
				'external' => false,
			];
		}

		// The in-place install/activate CTA carries its post-install flip target for the widget JS.
		if ( in_array( $link['action'], [ 'install-plugin', 'activate-plugin' ], true ) ) {
			$link['data'] = [
				'installed-text' => $this->connect_manage_label( $integration ),
				'installed-url'  => $this->integrations_page_url(),
			];
		}

		return $link;
	}

	/**
	 * Resolve the navigation label for an active integration: Connect until its provider
	 * has a configured account, Manage afterwards (and for integrations with no
	 * account concept, e.g. Zapier or Webhooks).
	 *
	 * @since 2.0.2
	 *
	 * @param array $integration Integration entry.
	 *
	 * @return string
	 */
	private function connect_manage_label( array $integration ): string {

		$provider = $this->integration_provider( $integration['slug'] ?? '' );

		if ( $provider !== '' && ! wpforms_get_providers_options( $provider ) ) {
			return __( 'Connect', 'wpforms-lite' );
		}

		return __( 'Manage', 'wpforms-lite' );
	}

	/**
	 * Map an integration slug to the `wpforms_providers` option bucket its addon writes
	 * accounts to. Integrations absent from the map have no account concept.
	 *
	 * @since 2.0.2
	 *
	 * @param string $slug Integration slug.
	 *
	 * @return string Provider option key, or empty string when there is none.
	 */
	private function integration_provider( string $slug ): string {

		$providers = [
			'brevo'         => 'sendinblue',
			'dropbox'       => 'dropbox',
			'google-drive'  => 'google-drive',
			'google-sheets' => 'google-sheets',
			'hubspot'       => 'hubspot',
			'mailchimp'     => 'mailchimpv3',
			'mailerlite'    => 'mailerlite',
			'notion'        => 'notion',
			'salesforce'    => 'salesforce',
			'slack'         => 'slack',
			'twilio'        => 'twilio',
		];

		return $providers[ $slug ] ?? '';
	}

	/**
	 * The Settings → Integrations page URL — the Connect/Manage and "View All" destination.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function integrations_page_url(): string {

		return admin_url( 'admin.php?page=wpforms-settings&view=integrations' );
	}

	/**
	 * Build an integration grid entry.
	 *
	 * @since 2.0.2
	 *
	 * @param string $slug   Integration slug (also the icon stem: addon-icon-{slug}.png).
	 * @param string $name   Display name.
	 * @param string $tier   Badge to show: '' (none), 'free', 'plus', 'pro', or 'elite'.
	 * @param string $action The 'install' (included in the tier) or 'upgrade' (needs a higher tier).
	 *
	 * @return array
	 */
	private function integration( string $slug, string $name, string $tier = '', string $action = 'install' ): array {

		return [
			'slug'   => $slug,
			'name'   => $name,
			'tier'   => $tier,
			'action' => $action,
		];
	}

	/**
	 * The Uncanny Automator entry — a free wordpress.org plugin installed in place.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function uncanny_automator_item(): array {

		return [
			'slug'        => 'uncanny-automator',
			'name'        => 'Uncanny Automator',
			'tier'        => 'free',
			'action'      => 'install',
			'icon'        => 'icon-provider-uncanny-automator.png',
			'plugin_slug' => 'uncanny-automator',
			'basename'    => 'uncanny-automator/uncanny-automator.php',
		];
	}

	/**
	 * The integrations shown in the promo grid for the current license tier.
	 *
	 * Lite and Basic share one list; Plus, Pro, and Elite each get their own,
	 * reflecting which integrations the tier already includes (Install) versus
	 * which require an upgrade.
	 *
	 * @since 2.0.2
	 *
	 * @param int $max Maximum number of entries to return.
	 *
	 * @return array<int, array>
	 */
	private function get_integrations( int $max ): array {

		$tier = $this->get_license_tier();

		if ( $tier === 'plus' ) {
			$list = $this->get_plus_integrations();
		} elseif ( $tier === 'pro' ) {
			$list = $this->get_pro_integrations();
		} elseif ( $tier === 'elite' ) {
			$list = $this->get_elite_integrations();
		} else {
			$list = $this->get_default_integrations();
		}

		return $this->personalize_integrations( $list, $max );
	}

	/**
	 * Personalize the integrations grid from what the user set up in the Setup Wizard.
	 *
	 * The Setup Wizard and the promo grids render in separate requests and cannot share
	 * runtime state, so the wizard durably records every addon it installs in the
	 * SetupWizard::OPTION_INSTALLED_ADDONS option (written by
	 * StateManager::record_installed_addons()). This method is the consumer of that record:
	 * it reads the installed addons and reorders the grid so the integrations the user
	 * already set up lead, followed by the curated tier list, capped at $max total.
	 * Wizard-installed integrations missing from the curated list are rebuilt from the addon
	 * catalog; feature-only addons and unknown plugins are skipped so they never leak into
	 * the promo grid.
	 *
	 * @since 2.0.2
	 *
	 * @param array $curated Curated integrations for the current license tier.
	 * @param int   $max     Maximum number of entries to return.
	 *
	 * @return array<int, array>
	 */
	private function personalize_integrations( array $curated, int $max ): array {

		$installed = $this->wizard_installed_addons();

		if ( $installed === [] ) {
			return array_slice( $curated, 0, $max );
		}

		$lead       = [];
		$rest       = [];
		$seen_files = [];

		// Curated integrations the user set up in the wizard lead; the rest follow.
		foreach ( $curated as $integration ) {
			$file         = $this->integration_plugin_file( $integration );
			$seen_files[] = $file;

			if ( $file !== '' && in_array( $file, $installed, true ) ) {
				$lead[] = $integration;
			} else {
				$rest[] = $integration;
			}
		}

		// Wizard-installed integrations missing from the curated list lead too; feature addons
		// and unknown plugins are skipped. Each is badged from its addon data (see wizard_integration()).
		$known = $this->known_integration_files();

		foreach ( $installed as $file ) {
			if ( in_array( $file, $seen_files, true ) || ! in_array( $file, $known, true ) ) {
				continue;
			}

			$seen_files[] = $file;
			$lead[]       = $this->wizard_integration( $file );
		}

		return array_slice( array_merge( $lead, $rest ), 0, $max );
	}

	/**
	 * Build a grid entry for a wizard-installed integration the current tier does not curate.
	 *
	 * @since 2.0.2
	 *
	 * @param string $file Plugin file.
	 *
	 * @return array
	 */
	private function wizard_integration( string $file ): array {

		$entry = [
			'slug'   => str_replace( 'wpforms-', '', dirname( $file ) ),
			'name'   => $this->plugin_catalog->name( $file ),
			'tier'   => '',
			'action' => 'install',
		];

		$addons = wpforms()->obj( 'addons' );
		$addon  = $addons ? (array) $addons->get_addon( dirname( $file ) ) : [];

		if ( empty( $addon['path'] ) || $addon['path'] !== $file ) {
			return $entry;
		}

		if ( empty( $addon['plugin_allow'] ) ) {
			$entry['tier']   = $addon['license_level'] ?? '';
			$entry['action'] = 'upgrade';
		}

		return $entry;
	}

	/**
	 * Plugin files for every integration any tier lists — the set the grid recognises,
	 * so wizard-installed feature addons do not leak into the integrations promo.
	 *
	 * @since 2.0.2
	 *
	 * @return array<int, string>
	 */
	private function known_integration_files(): array {

		if ( $this->known_files !== null ) {
			return $this->known_files;
		}

		$all = array_merge(
			$this->get_default_integrations(),
			$this->get_plus_integrations(),
			$this->get_pro_integrations(),
			$this->get_elite_integrations()
		);

		$this->known_files = array_values( array_filter( array_map( [ $this, 'integration_plugin_file' ], $all ) ) );

		return $this->known_files;
	}

	/**
	 * Resolve an integration entry to its plugin file: an explicit basename (cross-product
	 * plugins like Uncanny Automator) or the WPForms addon file derived from the slug.
	 *
	 * @since 2.0.2
	 *
	 * @param array $integration Integration entry.
	 *
	 * @return string Plugin file, or empty string when it cannot be resolved.
	 */
	private function integration_plugin_file( array $integration ): string {

		if ( ! empty( $integration['basename'] ) ) {
			return $integration['basename'];
		}

		return $this->addon_plugin_file( $integration['slug'] ?? '' );
	}

	/**
	 * Addon plugin files the user installed during the Setup Wizard (durable record).
	 *
	 * @since 2.0.2
	 *
	 * @return array<int, string>
	 */
	private function wizard_installed_addons(): array {

		return array_values( array_filter( (array) get_option( SetupWizard::OPTION_INSTALLED_ADDONS, [] ) ) );
	}

	/**
	 * Integrations for Lite and Basic (every paid integration is an upgrade).
	 *
	 * @since 2.0.2
	 *
	 * @return array<int, array>
	 */
	private function get_default_integrations(): array {

		return [
			$this->integration( 'google-sheets', 'Google Sheets', 'pro', 'upgrade' ),
			$this->integration( 'mailchimp', 'Mailchimp', 'plus', 'upgrade' ),
			$this->uncanny_automator_item(),
			$this->integration( 'brevo', 'Brevo', 'plus', 'upgrade' ),
			$this->integration( 'zapier', 'Zapier', 'pro', 'upgrade' ),
			$this->integration( 'google-drive', 'Google Drive', 'pro', 'upgrade' ),
			$this->integration( 'slack', 'Slack', 'pro', 'upgrade' ),
			$this->integration( 'webhooks', 'Webhooks', 'elite', 'upgrade' ),
			$this->integration( 'hubspot', 'HubSpot', 'elite', 'upgrade' ),
			$this->integration( 'dropbox', 'Dropbox', 'pro', 'upgrade' ),
		];
	}

	/**
	 * Integrations for the Plus tier.
	 *
	 * @since 2.0.2
	 *
	 * @return array<int, array>
	 */
	private function get_plus_integrations(): array {

		return [
			$this->integration( 'google-sheets', 'Google Sheets', 'pro', 'upgrade' ),
			$this->integration( 'slack', 'Slack' ),
			$this->integration( 'mailchimp', 'Mailchimp' ),
			$this->integration( 'notion', 'Notion' ),
			$this->uncanny_automator_item(),
			$this->integration( 'mailerlite', 'MailerLite' ),
			$this->integration( 'twilio', 'Twilio' ),
			$this->integration( 'google-drive', 'Google Drive', 'pro', 'upgrade' ),
			$this->integration( 'brevo', 'Brevo' ),
			$this->integration( 'dropbox', 'Dropbox', 'pro', 'upgrade' ),
		];
	}

	/**
	 * Integrations for the Pro tier.
	 *
	 * @since 2.0.2
	 *
	 * @return array<int, array>
	 */
	private function get_pro_integrations(): array {

		return [
			$this->integration( 'google-sheets', 'Google Sheets' ),
			$this->integration( 'slack', 'Slack' ),
			$this->integration( 'mailchimp', 'Mailchimp' ),
			$this->integration( 'zapier', 'Zapier' ),
			$this->uncanny_automator_item(),
			$this->integration( 'dropbox', 'Dropbox' ),
			$this->integration( 'mailerlite', 'MailerLite' ),
			$this->integration( 'google-drive', 'Google Drive' ),
			$this->integration( 'hubspot', 'HubSpot', 'elite', 'upgrade' ),
			$this->integration( 'webhooks', 'Webhooks', 'elite', 'upgrade' ),
		];
	}

	/**
	 * Integrations for the Elite tier (everything is included — all Install).
	 *
	 * @since 2.0.2
	 *
	 * @return array<int, array>
	 */
	private function get_elite_integrations(): array {

		return [
			$this->integration( 'google-sheets', 'Google Sheets' ),
			$this->integration( 'salesforce', 'Salesforce' ),
			$this->integration( 'mailchimp', 'Mailchimp' ),
			$this->integration( 'hubspot', 'HubSpot' ),
			$this->integration( 'zapier', 'Zapier' ),
			$this->uncanny_automator_item(),
			$this->integration( 'google-drive', 'Google Drive' ),
			$this->integration( 'mailerlite', 'MailerLite' ),
			$this->integration( 'dropbox', 'Dropbox' ),
			$this->integration( 'webhooks', 'Webhooks' ),
		];
	}
}
