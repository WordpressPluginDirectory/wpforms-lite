<?php

namespace WPForms\SetupChecklist;

use WPForms\SetupWizard\Service\PluginCatalog;
use WPForms\SetupWizard\Service\PluginDetector;
use WPForms\SetupWizard\SetupWizard;

/**
 * Promo sections model for the Setup Checklist.
 *
 * Owns the static promo catalog (features, per-tier integrations, recommended growth
 * tools) and the logic that turns it into render-ready tiles and cards: license-tier
 * selection, wizard personalization, and the one-click install/activate CTA that the
 * checklist's item buttons share. {@see Page} renders what this returns.
 *
 * @since 2.0.0
 */
class Promos {

	/**
	 * Plugin detector (installed/active status for recommended plugins).
	 *
	 * @since 2.0.0
	 *
	 * @var PluginDetector
	 */
	private $plugin_detector;

	/**
	 * Plugin catalog (resolves slugs to addon plugin files and display names).
	 *
	 * @since 2.0.0
	 *
	 * @var PluginCatalog
	 */
	private $plugin_catalog;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param PluginDetector $plugin_detector Plugin detector.
	 * @param PluginCatalog  $plugin_catalog  Plugin catalog.
	 */
	public function __construct( PluginDetector $plugin_detector, PluginCatalog $plugin_catalog ) {

		$this->plugin_detector = $plugin_detector;
		$this->plugin_catalog  = $plugin_catalog;
	}

	/**
	 * Render-ready tiles for the "Take Your Forms to the Next Level" feature grid.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array>
	 */
	public function feature_tiles(): array {

		$features = [
			[
				'icon'        => 'fa-arrow-right-from-bracket',
				'title'       => __( 'Form Abandonment Recovery', 'wpforms-lite' ),
				'description' => __( 'Partial form submission & user journey to boost sales.', 'wpforms-lite' ),
				'slugs'       => [ 'form-abandonment', 'user-journey', 'geolocation' ],
			],
			[
				'icon'        => 'fa-chart-bar',
				'title'       => __( 'Surveys & Polls', 'wpforms-lite' ),
				'description' => __( 'Create interactive surveys & gain valuable insights.', 'wpforms-lite' ),
				'slugs'       => [ 'surveys-polls', 'save-resume' ],
			],
			[
				'icon'        => 'fa-calculator',
				'title'       => __( 'Quizzes & Calculators', 'wpforms-lite' ),
				'description' => __( 'Create lead generation quizzes & calculators.', 'wpforms-lite' ),
				'slugs'       => [ 'quiz', 'calculations' ],
			],
			[
				'icon'        => 'fa-comments',
				'title'       => __( 'Conversational Forms', 'wpforms-lite' ),
				'description' => __( 'Improve form completion rate & conversions.', 'wpforms-lite' ),
				'slug'        => 'conversational-forms',
			],
			[
				'icon'        => 'fa-signature',
				'title'       => __( 'Collect Signatures', 'wpforms-lite' ),
				'description' => __( 'Collect secure digital signatures on your forms.', 'wpforms-lite' ),
				'slugs'       => [ 'signatures', 'pdf' ],
			],
			[
				'icon'        => 'fa-sliders',
				'title'       => __( 'Advanced Form Tools', 'wpforms-lite' ),
				'description' => __( 'Advanced fields, form permission control, and more.', 'wpforms-lite' ),
				'slug'        => 'form-locker',
			],
		];

		$is_pro_plus = $this->is_pro_plus();
		$upgrade_url = wpforms_admin_upgrade_link( 'Setup Checklist', 'Take Your Forms to the Next Level' );
		$tiles       = [];

		foreach ( $features as $feature ) {
			$tiles[] = array_merge(
				[
					'icon'        => $feature['icon'],
					'title'       => $feature['title'],
					'description' => $feature['description'],
				],
				$this->feature_link( $feature, $is_pro_plus, $upgrade_url )
			);
		}

		return $tiles;
	}

	/**
	 * Render-ready cards for the integrations grid: tier-selected, personalized, and with
	 * each CTA link resolved.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array>
	 */
	public function integration_cards(): array {

		$upgrade_url = wpforms_admin_upgrade_link( 'Setup Checklist', 'Streamline Your Workflow with Seamless Integrations' );
		$cards       = [];

		foreach ( $this->get_integrations() as $integration ) {
			$integration['link'] = $this->integration_link( $integration, $upgrade_url );
			$cards[]             = $integration;
		}

		return $cards;
	}

	/**
	 * Render-ready tiles for the "Set Up Recommended Growth Tools" grid.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array>
	 */
	public function growth_tool_tiles(): array {

		$tools = [
			[
				'icon'        => 'setup-checklist/brand-aioseo.svg',
				'name'        => 'AIOSEO',
				'description' => __( 'Improve SEO rankings with AI tools and get valuable insights.', 'wpforms-lite' ),
				'basename'    => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
			],
			[
				'icon'        => 'setup-checklist/brand-universally.svg',
				'name'        => 'Universally',
				'description' => __( 'Easily translate your website into 110+ languages within minutes using AI.', 'wpforms-lite' ),
				'basename'    => 'universally-language-translation-multilingual-tool/universally.php',
			],
			[
				'icon'        => 'setup-checklist/brand-duplicator.svg',
				'name'        => 'Duplicator',
				'description' => __( 'Easy, fast, and secure WordPress backups and website migrations.', 'wpforms-lite' ),
				'basename'    => 'duplicator/duplicator.php',
			],
			[
				'icon'        => 'setup-checklist/brand-smashballoon.svg',
				'name'        => 'Reviews Feed',
				'description' => __( 'Show customer reviews from Google, Yelp, TripAdvisor, and more to boost sales.', 'wpforms-lite' ),
				'basename'    => 'reviews-feed/sb-reviews.php',
			],
			[
				'icon'        => 'setup-checklist/brand-optinmonster.svg',
				'name'        => 'OptinMonster',
				'description' => __( 'Get more email subscribers & sales with the #1 CRO toolkit for WordPress.', 'wpforms-lite' ),
				'basename'    => 'optinmonster/optin-monster-wp-api.php',
			],
			[
				'icon'        => 'setup-checklist/brand-monsterinsights.svg',
				'name'        => 'MonsterInsights',
				'description' => __( 'Website analytics made easy for WordPress. Form tracking, reports, and more.', 'wpforms-lite' ),
				'basename'    => 'google-analytics-for-wordpress/googleanalytics.php',
			],
		];

		$tiles = [];

		foreach ( $tools as $tool ) {
			$link = $this->install_link( [ $tool['basename'] ] );

			$tiles[] = [
				'image'         => $tool['icon'],
				'title'         => $tool['name'],
				'description'   => $tool['description'],
				'link_text'     => $link['text'],
				'link_external' => $link['external'],
				'link_action'   => $link['action'],
				'link_plugin'   => $link['plugin'],
			];
		}

		return $tiles;
	}

	/**
	 * Resolve the one-click install CTA for one or more plugins by their aggregate state.
	 *
	 * The install endpoint installs-or-activates and resolves addon-vs-plugin itself, so this
	 * builds a single CTA for any WordPress.org plugin or WPForms addon, addressed by basename
	 * ( comma-joined for a bundle ). An already-active set is a no-op "Installed" state. Shared
	 * with the checklist's item buttons (WP Mail SMTP, WPConsent).
	 *
	 * @since 2.0.0
	 *
	 * @param array $files Plugin main-file paths (folder/file.php).
	 *
	 * @return array Link parts: `text`, `action`, `plugin`, `external`, `installed`.
	 */
	public function install_link( array $files ): array {

		$all_active    = true;
		$all_installed = true;

		foreach ( $files as $file ) {
			$status        = $this->plugin_detector->status( $file );
			$all_active    = $all_active && $status['active'];
			$all_installed = $all_installed && $status['installed'];
		}

		if ( $all_active ) {
			$text = __( 'Installed', 'wpforms-lite' );
		} elseif ( $all_installed ) {
			$text = __( 'Activate', 'wpforms-lite' );
		} else {
			$text = __( 'Install', 'wpforms-lite' );
		}

		return [
			'text'      => $text,
			'action'    => $all_active ? 'active' : ( $all_installed ? 'activate-plugin' : 'install-plugin' ),
			'plugin'    => $all_active ? '' : implode( ',', $files ),
			'external'  => false,
			'installed' => $all_installed,
		];
	}

	/**
	 * Whether the current license is Pro or Elite — the tiers that install addons in place.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_pro_plus(): bool {

		return in_array( $this->get_license_tier(), [ 'pro', 'elite' ], true );
	}

	/**
	 * Build the CTA link parts for a "Take Your Forms to the Next Level" feature tile.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $feature     Feature tile (icon, title, description, optional slug).
	 * @param bool   $is_pro_plus Whether the license is Pro or Elite.
	 * @param string $upgrade_url Upgrade link target used on Lite.
	 *
	 * @return array Tile link parts (`link_text`, `link_url`, `link_action`, `link_plugin`, `link_external`).
	 */
	private function feature_link( array $feature, bool $is_pro_plus, string $upgrade_url ): array {

		if ( ! $is_pro_plus ) {
			return [
				'link_text'     => __( 'Upgrade', 'wpforms-lite' ),
				'link_url'      => $upgrade_url,
				'link_external' => true,
			];
		}

		if ( ! empty( $feature['slugs'] ) ) {
			$link = $this->addon_install_link( $feature['slugs'] );
		} elseif ( ! empty( $feature['slug'] ) ) {
			$link = $this->addon_install_link( [ $feature['slug'] ] );
		} else {
			// No addon slug mapped to this tile — fall back to browsing the Addons page.
			$link = $this->addons_page_link();
		}

		return [
			'link_text'     => $link['text'],
			'link_url'      => $link['url'] ?? '#',
			'link_action'   => $link['action'],
			'link_plugin'   => $link['plugin'],
			'link_external' => $link['external'],
		];
	}

	/**
	 * Resolve a single integration entry to its CTA link: upgrade, in-place plugin install,
	 * or in-place addon install.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $integration Integration entry.
	 * @param string $upgrade_url Upgrade link target (paid tiers).
	 *
	 * @return array
	 */
	private function integration_link( array $integration, string $upgrade_url ): array {

		$action = $integration['action'] ?? 'install';

		if ( $action === 'upgrade' ) {
			return [
				'text'     => __( 'Upgrade', 'wpforms-lite' ),
				'url'      => $upgrade_url,
				'action'   => '',
				'plugin'   => '',
				'external' => true,
			];
		}

		// Free third-party plugin (e.g. Uncanny Automator) — one-click install in place.
		if ( ! empty( $integration['plugin_slug'] ) ) {
			return $this->install_link( [ $integration['basename'] ] );
		}

		// A WPForms addon included in this license tier — install it in place, reusing the
		// Setup Wizard's gateway (it resolves the licensed download server-side).
		return $this->addon_install_link( [ $integration['slug'] ] );
	}

	/**
	 * Resolve the install CTA for one or more WPForms addons by slug.
	 *
	 * Resolves each slug to its addon plugin file ( an unknown slug bails to the Addons page
	 * rather than rendering a partial install ), then defers to {@see Promos::install_link()}.
	 * Installing in place needs the install capability; a user who lacks it is sent to the
	 * Addons page, while an already-active addon still shows its "Installed" state.
	 *
	 * @since 2.0.0
	 *
	 * @param array $slugs Addon slugs ( one, or several for a bundled feature tile ).
	 *
	 * @return array
	 */
	private function addon_install_link( array $slugs ): array {

		$files = [];

		foreach ( $slugs as $slug ) {
			$file = $this->addon_plugin_file( $slug );

			if ( $file === '' ) {
				return $this->addons_page_link();
			}

			$files[] = $file;
		}

		if ( $files === [] ) {
			return $this->addons_page_link();
		}

		$link = $this->install_link( $files );

		if ( $link['action'] !== 'active' && ! current_user_can( 'install_plugins' ) ) {
			return $this->addons_page_link();
		}

		return $link;
	}

	/**
	 * The "Install" CTA that opens the Addons page — the fallback used when an in-place
	 * install is unavailable (an unknown slug, or a user who cannot install plugins).
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	private function addons_page_link(): array {

		return [
			'text'     => __( 'Install', 'wpforms-lite' ),
			'url'      => admin_url( 'admin.php?page=wpforms-addons' ),
			'action'   => '',
			'plugin'   => '',
			'external' => false,
		];
	}

	/**
	 * Resolve an integration slug to its WPForms addon plugin file.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Integration slug.
	 *
	 * @return string Plugin file, or empty string when the slug is not a known addon.
	 */
	private function addon_plugin_file( string $slug ): string {

		$renamed = [
			'brevo' => 'sendinblue',
		];

		$folder      = 'wpforms-' . ( $renamed[ $slug ] ?? $slug );
		$plugin_file = $folder . '/' . $folder . '.php';

		return $this->plugin_catalog->is_addon( $plugin_file ) ? $plugin_file : '';
	}

	/**
	 * Build an integration grid entry.
	 *
	 * @since 2.0.0
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
	 * @since 2.0.0
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
	 * @since 2.0.0
	 *
	 * @return array<int, array>
	 */
	private function get_integrations(): array {

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

		return $this->personalize_integrations( $list );
	}

	/**
	 * Personalize the integrations grid from what the user set up in the Setup Wizard.
	 *
	 * The Setup Wizard and this checklist render in separate requests and cannot share
	 * runtime state, so the wizard durably records every addon it installs in the
	 * SetupWizard::OPTION_INSTALLED_ADDONS option (written by
	 * StateManager::record_installed_addons()). This method is the consumer of that record:
	 * it reads the installed addons and reorders the grid so the integrations the user
	 * already set up lead, followed by the curated tier list, capped at 10 total.
	 * Wizard-installed integrations missing from the curated list are rebuilt from the addon
	 * catalog; feature-only addons and unknown plugins are skipped so they never leak into
	 * the promo grid.
	 *
	 * @since 2.0.0
	 *
	 * @param array $curated Curated integrations for the current license tier.
	 *
	 * @return array<int, array>
	 */
	private function personalize_integrations( array $curated ): array {

		$max       = 10;
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
	 * @since 2.0.0
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
	 * @since 2.0.0
	 *
	 * @return array<int, string>
	 */
	private function known_integration_files(): array {

		$all = array_merge(
			$this->get_default_integrations(),
			$this->get_plus_integrations(),
			$this->get_pro_integrations(),
			$this->get_elite_integrations()
		);

		return array_values( array_filter( array_map( [ $this, 'integration_plugin_file' ], $all ) ) );
	}

	/**
	 * Resolve an integration entry to its plugin file: an explicit basename (cross-product
	 * plugins like Uncanny Automator) or the WPForms addon file derived from the slug.
	 *
	 * @since 2.0.0
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
	 * @since 2.0.0
	 *
	 * @return array<int, string>
	 */
	private function wizard_installed_addons(): array {

		return array_values( array_filter( (array) get_option( SetupWizard::OPTION_INSTALLED_ADDONS, [] ) ) );
	}

	/**
	 * Resolve the license to a promo bucket: plus, pro, elite, or default (Lite + Basic).
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_license_tier(): string {

		$type = wpforms_get_license_type();

		if ( $type === 'plus' || $type === 'pro' ) {
			return $type;
		}

		// Elite and its legacy license names share the Elite list.
		if ( in_array( $type, [ 'elite', 'agency', 'ultimate' ], true ) ) {
			return 'elite';
		}

		// Lite (no license) and Basic share the default list.
		return 'default';
	}

	/**
	 * Integrations for Lite and Basic (every paid integration is an upgrade).
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array>
	 */
	private function get_default_integrations(): array {

		return [
			$this->integration( 'google-sheets', 'Google Sheets', 'pro', 'upgrade' ),
			$this->integration( 'mailchimp', 'Mailchimp', 'pro', 'upgrade' ),
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
	 * @since 2.0.0
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
	 * @since 2.0.0
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
	 * @since 2.0.0
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
