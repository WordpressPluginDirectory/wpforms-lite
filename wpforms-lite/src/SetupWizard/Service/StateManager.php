<?php

namespace WPForms\SetupWizard\Service;

// phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement
use WP_Error;
use WPForms\SetupWizard\SetupWizard;
use WPForms\SetupWizard\FailedInstallsNotice;

/**
 * Setup Wizard state manager.
 *
 * Single entry point the REST layer talks to. Composes the wizard's
 * collaborators (`PluginInstaller` for installations, `PluginDetector`
 * and `SettingsDetector` for live detection) and owns the canonical
 * state stored in a WordPress transient:
 * - `settings` is the detected customer-site context the SPA reads on `/hydrate`,
 *   assembled from `SettingsDetector` (Lite Connect flag, Stripe configured).
 * - `wizard_settings` is an opaque blob the SPA owns; the manager replaces it
 *   wholesale on each `/update` and hands it back unchanged on the next hydrate.
 *   On `/complete` the manager picks the keys listed in {@see self::WPFORMS_SETTINGS_KEYS}
 *   out of `wizard_settings` — the SPA sends them under their exact
 *   `wpforms_settings` key (e.g. `lite-connect-enabled`, `gdpr`) — and
 *   `array_replace`s them into `wpforms_settings` without a per-key if-ladder.
 *
 * The hydrate payload is enriched with live `cross_plugins` and
 * `forms_plugins` arrays from `PluginDetector` on every read.
 *
 * @since 2.0.0
 */
class StateManager {

	/**
	 * Transient name storing the wizard state blob.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const TRANSIENT_STATE = 'wpforms_setup_wizard_state';

	/**
	 * State lifetime in seconds.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private const TTL = HOUR_IN_SECONDS;

	/**
	 * `wpforms_settings` keys the SPA is allowed to mirror through `/update`.
	 *
	 * Keys are sent by the SPA verbatim and `array_replace`d into
	 * `wpforms_settings` — no per-key if-ladder. Any other payload keys land in
	 * the opaque `wizard_settings` blob.
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	private const WPFORMS_SETTINGS_KEYS = [
		'lite-connect-enabled',
		'gdpr',
	];

	/**
	 * Plugin installer service.
	 *
	 * @since 2.0.0
	 *
	 * @var PluginInstaller
	 */
	private $installer;

	/**
	 * Plugin detector service.
	 *
	 * @since 2.0.0
	 *
	 * @var PluginDetector
	 */
	private $plugin_detector;

	/**
	 * Settings detector service.
	 *
	 * @since 2.0.0
	 *
	 * @var SettingsDetector
	 */
	private $settings_detector;

	/**
	 * Stripe Connect URL provider.
	 *
	 * @since 2.0.0
	 *
	 * @var StripeConnectUrl
	 */
	private $stripe_connect;

	/**
	 * Failed installations notice.
	 *
	 * @since 2.0.0
	 *
	 * @var FailedInstallsNotice
	 */
	private $failed_installs_notice;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		$this->load_dependencies();
	}

	/**
	 * Instantiate the service collaborators.
	 *
	 * @since 2.0.0
	 */
	private function load_dependencies(): void {

		$this->installer              = new PluginInstaller();
		$this->plugin_detector        = new PluginDetector();
		$this->settings_detector      = new SettingsDetector();
		$this->stripe_connect         = new StripeConnectUrl();
		$this->failed_installs_notice = new FailedInstallsNotice();
	}

	/**
	 * Get the Stripe Connect kickoff URL for the wizard.
	 *
	 * @since 2.0.0
	 *
	 * @return array{connect_url: string}|WP_Error
	 */
	public function get_stripe_connect_url() {

		return $this->stripe_connect->get_connect_url();
	}

	/**
	 * Read the full wizard state, initializing the transient on the first call.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_state(): array {

		$state = get_transient( self::TRANSIENT_STATE );

		if ( ! is_array( $state ) || ! isset( $state['settings'] ) || ! is_array( $state['wizard_settings'] ?? null ) ) {
			$state = $this->default_state();

			$this->save_state( $state );

			return $state;
		}

		// External WP state can change outside the wizard's awareness mid-run —
		// Lite Connect toggled in Settings, Stripe connected via OAuth in a
		// wp-admin redirect, a cross-plugin activated. Re-detect `settings` on
		// every read so the SPA never sees a stale snapshot. `wizard_settings`
		// is the SPA's own writeable state and stays cached.
		$state['settings'] = $this->detect_settings();

		return $state;
	}

	/**
	 * Persist the full wizard state to the transient.
	 *
	 * @since 2.0.0
	 *
	 * @param array $state Full state blob (`settings` + `wizard_settings`).
	 */
	public function save_state( array $state ): void {

		set_transient( self::TRANSIENT_STATE, $state, self::TTL );
	}

	/**
	 * Hydrate payload for the SPA's `/hydrate` call.
	 *
	 * Returns the persisted state augmented with live plugin-detection results,
	 * so the SPA always sees the current installed/active status of the
	 * cross-product plugins and the form plugins it can import from.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_hydrate_payload(): array {

		$payload = $this->get_state();

		$payload['cross_plugins'] = $this->plugin_detector->cross_plugins();
		$payload['form_plugins']  = $this->plugin_detector->forms_plugins();

		return $payload;
	}

	/**
	 * Replace the wizard_settings blob with the SPA's latest snapshot.
	 *
	 * The contents are intentionally opaque to the server: whatever the SPA
	 * sends becomes the new `wizard_settings`. Previous values are discarded.
	 * Any keys matching {@see self::WPFORMS_SETTINGS_KEYS} are mirrored into
	 * `wpforms_settings` on the same call so a user's step-level choice
	 * (e.g. Lite Connect on Welcome) persists even if they exit before
	 * reaching `/complete`. `apply_settings()` runs again on `/complete` as
	 * an idempotent safety net.
	 *
	 * @since 2.0.0
	 *
	 * @param array $wizard_settings Wizard-owned state to persist verbatim.
	 *
	 * @return array Refreshed full state.
	 */
	public function save_wizard_settings( array $wizard_settings ): array {

		$state = $this->get_state();

		$state['wizard_settings'] = $wizard_settings;

		$this->save_state( $state );
		$this->apply_settings();

		return $state;
	}

	/**
	 * Install queued plugins and fold the result into the wizard state.
	 *
	 * Runs the installer and stores the raw outcome under
	 * `wizard_settings.install_results` so the SPA can render success/failure UI
	 * on its next hydrate. Fresh installed/active status is reported by
	 * `PluginDetector` on the next `/hydrate` call.
	 *
	 * @since 2.0.0
	 *
	 * @param array $plugins Plugin files the SPA asked to install.
	 *
	 * @return array{installed: array, failed: array}
	 */
	public function install_plugins( array $plugins ): array {

		$result = $this->installer->install( $plugins );

		$this->apply_install_results( $result );
		$this->failed_installs_notice->record( $result['failed'], $result['installed'] );
		$this->record_installed_addons( $result['installed'] );

		return [
			'installed' => $result['installed'],
			'failed'    => $result['failed'],
		];
	}

	/**
	 * Durably record the addon plugin files installed during the wizard.
	 *
	 * @since 2.0.0
	 *
	 * @param array $installed Plugin files installed (or already present) and activated.
	 */
	private function record_installed_addons( array $installed ): void {

		if ( $installed === [] ) {
			return;
		}

		$recorded = (array) get_option( SetupWizard::OPTION_INSTALLED_ADDONS, [] );
		$merged   = array_values( array_unique( array_merge( $recorded, $installed ) ) );

		update_option( SetupWizard::OPTION_INSTALLED_ADDONS, $merged, false );
	}

	/**
	 * Record the outcome of an installation run into the wizard state.
	 *
	 * @since 2.0.0
	 *
	 * @param array{installed: array, failed: array} $result Installer result payload.
	 */
	private function apply_install_results( array $result ): void {

		$state = $this->get_state();

		$state['wizard_settings']['install_results'] = [
			'installed' => $result['installed'],
			'failed'    => $result['failed'],
		];

		$this->save_state( $state );
	}

	/**
	 * Finalize the wizard for a chosen outcome.
	 *
	 * Stub for now. The intended responsibility is to refresh the `settings`
	 * snapshot so any post-wizard read reflects the customer site after the
	 * installation queue ran (Stripe keys saved, Lite Connect toggled, etc.).
	 *
	 * @since 2.0.0
	 *
	 * @param string $outcome Final outcome: `build`, `import`, or `exit`.
	 */
	public function complete( string $outcome ): void {

		// Apply any settings the SPA chose during the run (e.g., Lite Connect, GDPR).
		$this->apply_settings();

		// Mark finished: suppresses both auto-launch and the Welcome fallback.
		// The edition is recorded so a later Lite-to-Pro upgrade can relaunch
		// the wizard once for its Pro-only steps (see
		// WPForms\Pro\SetupWizard\SetupWizard::maybe_relaunch_after_pro_upgrade()).
		update_option(
			SetupWizard::OPTION_COMPLETED,
			[
				'version' => defined( 'WPFORMS_VERSION' ) ? WPFORMS_VERSION : '',
				'edition' => wpforms()->is_pro() ? 'pro' : 'lite',
			]
		);

		// Clean up the one-shot first-run signal. The state transient is left to
		// expire on its own TTL, so the REST `complete` response can still read
		// `wizard_settings`; the session token is revoked by the REST layer.
		delete_transient( SetupWizard::TRANSIENT_FIRST_RUN );

		/**
		 * Fires after the Setup Wizard has been finalized.
		 *
		 * @since 2.0.0
		 *
		 * @param string $outcome Final outcome: `build`, `import`, or `exit`.
		 */
		do_action( 'wpforms_setup_wizard_service_state_manager_complete', $outcome );
	}

	/**
	 * Mirror the wizard's chosen settings into `wpforms_settings`.
	 *
	 * The SPA sends option mirrors under their exact `wpforms_settings` keys
	 * (see {@see self::WPFORMS_SETTINGS_KEYS}). We pick those out of
	 * `wizard_settings`, `array_replace` them into the live settings, and let
	 * `wpforms_update_settings()` fire its filter chain — the Lite Connect
	 * integration uses that hook to stamp `lite-connect-enabled-since` on the
	 * first truthy transition. Idempotent: runs on every `/update` so a
	 * mid-wizard exit can't lose a confirmed choice, and again on `/complete`.
	 *
	 * @since 2.0.0
	 */
	private function apply_settings(): void {

		$state   = $this->get_state();
		$updates = array_intersect_key(
			$state['wizard_settings'],
			array_flip( self::WPFORMS_SETTINGS_KEYS )
		);

		if ( $updates === [] ) {
			return;
		}

		$settings = (array) get_option( 'wpforms_settings', [] );

		wpforms_update_settings( array_replace( $settings, $updates ) );
	}

	/**
	 * Default state shape returned when the transient is empty.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	private function default_state(): array {

		return [
			'settings'        => $this->detect_settings(),
			'wizard_settings' => [],
		];
	}

	/**
	 * Whether the site has at least one WPForms form, of any kind (built or imported).
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function has_forms(): bool {

		$forms = wpforms()->obj( 'form' )->get(
			'',
			[
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		return ! empty( $forms );
	}

	/**
	 * Detect the customer-site context exposed to the SPA.
	 *
	 * Protected so the Pro manager can enrich the snapshot with Pro-only keys.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function detect_settings(): array {

		return [
			'is_pro'               => wpforms()->is_pro(),
			'has_forms'            => $this->has_forms(),
			'lite_connect_enabled' => $this->settings_detector->is_lite_connect_enabled(),
			'stripe_configured'    => $this->settings_detector->is_stripe_configured(),
		];
	}
}
