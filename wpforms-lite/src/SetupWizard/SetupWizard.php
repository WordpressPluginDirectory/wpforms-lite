<?php

namespace WPForms\SetupWizard;

use WPForms\Migrations\Base as MigrationsBase;
use WPForms\SetupChecklist\Page;
use WPForms\SetupWizard\Service\StateManager;

/**
 * Setup Wizard orchestrator.
 *
 * Registers the first-run entry point, REST init, and renders the POST bridge
 * that hands off to the wizard SPA. Also handles manual `?wpforms_setup_wizard=1`
 * re-entry and Lite-to-Pro relaunch with preserved state.
 *
 * @since 2.0.0
 */
class SetupWizard {

	/**
	 * Query argument that triggers a manual wizard launch from any admin URL.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const QUERY_ARG = 'wpforms_setup_wizard';

	/**
	 * Option name storing the plugin version present at first activation.
	 *
	 * Used as the single-write first-activation marker. `add_option()` returns
	 * true only on the very first activation; never expires.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const OPTION_INITIAL_VERSION = 'wpforms_setup_wizard_initial_version';

	/**
	 * Transient name for the single-shot first-run signal (1h TTL).
	 *
	 * Public so the Welcome page fallback and the StateManager finalizer can
	 * read/clear the same signal.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public const TRANSIENT_FIRST_RUN = 'wpforms_setup_wizard_first_run';

	/**
	 * Option name marking the wizard as finished.
	 *
	 * Once set, the wizard never auto-launches again and the Welcome page no
	 * longer redirects to the getting-started screen.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public const OPTION_COMPLETED = 'wpforms_setup_wizard_completed';

	/**
	 * Option that durably records the addon plugin files installed during the wizard.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public const OPTION_INSTALLED_ADDONS = 'wpforms_setup_wizard_installed_addons';

	/**
	 * Option name flagging the wizard as disabled entirely.
	 *
	 * The persisted form of the kill switch, for provisioning flows that
	 * install WPForms in a single request (AJAX, REST, WP-CLI) and cannot
	 * register the `wpforms_setup_wizard_setup_wizard_is_disabled` filter on the later
	 * pageviews where the wizard actually launches. Read by `is_disabled()`.
	 *
	 * @since 2.0.0.2
	 *
	 * @var string
	 */
	public const OPTION_DISABLED = 'wpforms_setup_wizard_disabled';

	/**
	 * Bridge service.
	 *
	 * @since 2.0.0
	 *
	 * @var Bridge
	 */
	private $bridge;

	/**
	 * REST API.
	 *
	 * @since 2.0.0
	 *
	 * @var RestApi
	 */
	private $rest_api;

	/**
	 * Stripe Connect OAuth handler.
	 *
	 * @since 2.0.0
	 *
	 * @var StripeConnect
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
	 * Initialize the orchestrator.
	 *
	 * Wires services and hooks. The capability gate lives in `maybe_launch()`
	 * and in `RestApi::validate_request()` so this method can also fire on REST
	 * requests where no user is logged in yet.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		$this->load_dependencies();
		$this->hooks();
	}

	/**
	 * Load the wizard dependencies.
	 *
	 * Every dependency is resolved to its edition-specific implementation when
	 * one exists (see {@see self::load_dependency()}).
	 *
	 * @since 2.0.0
	 */
	private function load_dependencies(): void {

		$auth    = $this->load_dependency( Auth::class );
		$service = $this->load_dependency( StateManager::class );

		$this->stripe_connect         = $this->load_dependency( StripeConnect::class );
		$this->bridge                 = $this->load_dependency( Bridge::class, [ $auth ] );
		$this->rest_api               = $this->load_dependency( RestApi::class, [ $auth, $service ] );
		$this->failed_installs_notice = $this->load_dependency( FailedInstallsNotice::class );
	}

	/**
	 * Instantiate a wizard class, preferring the edition-specific implementation.
	 *
	 * Pro tries `WPForms\Pro\{Class}`, Lite tries `WPForms\Lite\{Class}`, and
	 * both fall back to the shared `WPForms\{Class}` when no override exists.
	 *
	 * @since 2.0.0
	 *
	 * @param string $dependency Shared class name.
	 * @param array  $args       Constructor arguments.
	 *
	 * @return object
	 */
	private function load_dependency( string $dependency, array $args = [] ): object {

		$edition       = wpforms()->is_pro() ? 'Pro' : 'Lite';
		$edition_class = 'WPForms\\' . $edition . '\\' . substr( $dependency, strlen( 'WPForms\\' ) );

		$dependency = class_exists( $edition_class ) ? $edition_class : $dependency;

		return new $dependency( ...$args );
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	private function hooks(): void {

		add_action( 'rest_api_init', [ $this->rest_api, 'register_routes' ] );
		add_action( 'admin_init', [ $this, 'maybe_record_first_activation' ] );
		add_action( 'admin_init', [ $this, 'maybe_launch' ], PHP_INT_MAX );
		add_action( 'admin_notices', [ $this->failed_installs_notice, 'maybe_display' ] );

		$this->stripe_connect->hooks();
	}

	/**
	 * Maybe launch the wizard.
	 *
	 * Decides whether to render the POST bridge for the current admin request:
	 * - First-time activation within the 1h launch window.
	 * - Lite-to-Pro relaunch with preserved state.
	 * - Manual launch via the `wpforms_setup_wizard` query argument.
	 *
	 * Auto-launch is suppressed when the user lacks `manage_options`, in WP-CLI,
	 * in network admin, or in local environments (unless explicitly overridden).
	 * Both launch paths are suppressed when the wizard is disabled via the
	 * `wpforms_setup_wizard_disabled` option, the
	 * `WPFORMS_SETUP_WIZARD_DISABLED` constant, or the
	 * `wpforms_setup_wizard_setup_wizard_is_disabled` filter.
	 *
	 * @since 2.0.0
	 */
	public function maybe_launch(): void {

		if ( wp_doing_ajax() || wpforms_is_rest() ) {
			return;
		}

		if ( self::is_disabled() ) {
			$this->strip_manual_launch_arg();

			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->strip_manual_launch_arg();

			return;
		}

		$manual = $this->is_manual_launch_request();

		if ( ! $manual && ! self::will_auto_launch() ) {
			return;
		}

		// Consume the one-shot first-run signal so auto-launch fires exactly once.
		// If the preflight below sends the user to the Welcome fallback, they get
		// the fallback experience rather than a re-launch on the next pageview.
		if ( ! $manual ) {
			delete_transient( self::TRANSIENT_FIRST_RUN );
		}

		// Preflight: a top-level POST abandons the bridge page, so the only way to
		// recover from an unreachable or erroring SPA is to check it here, before
		// the handoff, and send the user to the Welcome fallback instead.
		if ( ! $this->bridge->is_spa_reachable() ) {
			wp_safe_redirect( $this->get_fallback_url() );

			exit;
		}

		$this->failed_installs_notice->clear();

		$payload = $this->bridge->build_payload(
			$this->get_exit_url(),
			$this->get_restart_url()
		);

		$this->bridge->render( $payload );

		exit;
	}

	/**
	 * Strip the manual launch query argument when the current user cannot launch the wizard.
	 *
	 * Acts as the CSRF mitigation for the URL-driven launch: an attacker who
	 * tricks an underprivileged user into following the link gets redirected
	 * to a clean URL without the parameter, so refreshing or sharing the page
	 * no longer attempts to launch the wizard.
	 *
	 * @since 2.0.0
	 */
	private function strip_manual_launch_arg(): void {

		if ( ! $this->is_manual_launch_request() ) {
			return;
		}

		if ( headers_sent() ) {
			return;
		}

		wp_safe_redirect( remove_query_arg( self::QUERY_ARG ) );

		exit;
	}

	/**
	 * Record the first activation on the first admin pageview of a fresh install.
	 *
	 * The plugin's `init`-hooked services are not loaded during the activation
	 * request itself (the plugin is included inside `activate_plugin()` after
	 * `init` has fired), so first-run is detected lazily here instead of from the
	 * activation hook. A fresh install is one where the migrations base has not
	 * recorded a previous core version — the same signal the Welcome page uses to
	 * tell an initial install from an upgrade. `record_first_activation()` is a
	 * one-shot, so this is a cheap no-op on every subsequent request.
	 *
	 * @since 2.0.0
	 */
	public function maybe_record_first_activation(): void {

		// Already recorded on a previous request.
		if ( get_option( self::OPTION_INITIAL_VERSION ) !== false ) {
			return;
		}

		// Existing install upgrading, not a fresh activation.
		if ( get_option( MigrationsBase::PREVIOUS_CORE_VERSION_OPTION_NAME ) ) {
			return;
		}

		$this->record_first_activation();
	}

	/**
	 * Mark the plugin's first activation.
	 *
	 * Sets `wpforms_setup_wizard_initial_version` and the `first_run` transient
	 * so the next admin pageview triggers the wizard.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if this call recorded the first activation.
	 */
	private function record_first_activation(): bool {

		$version = defined( 'WPFORMS_VERSION' ) ? WPFORMS_VERSION : '';

		if ( ! add_option( self::OPTION_INITIAL_VERSION, $version, '', false ) ) {
			return false;
		}

		set_transient( self::TRANSIENT_FIRST_RUN, 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Whether the wizard should auto-launch on the current request.
	 *
	 * Side-effect-free predicate shared with the Welcome page so exactly one of
	 * {wizard, Welcome fallback} acts on a fresh install. Static because every
	 * input is global state (options, the first-run transient, the request
	 * context) — it carries no instance state and must be callable from the
	 * legacy Welcome class before the wizard renders.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function will_auto_launch(): bool {

		if ( self::is_disabled() ) {
			return false;
		}

		if ( get_option( self::OPTION_COMPLETED ) ) {
			return false;
		}

		if ( ! get_transient( self::TRANSIENT_FIRST_RUN ) ) {
			return false;
		}

		if ( self::is_excluded_context() || self::is_excluded_page() ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the Setup Wizard is disabled entirely.
	 *
	 * A kill switch for hosts and agencies that provision WPForms
	 * programmatically: when disabled, the whole first-run experience stands
	 * down — the wizard never launches (neither the first-run auto-launch nor
	 * the manual `wpforms_setup_wizard` query argument), and the Welcome page
	 * suppresses its fallback activation redirect as well (see
	 * `WPForms_Welcome::redirect()`). Public and static for the same reason as
	 * `will_auto_launch()`: it carries no instance state and is consumed by
	 * the Welcome page before the wizard renders.
	 *
	 * Three inputs serve different deployment shapes: the option persists a
	 * decision made in a single provisioning request, the constant serves
	 * wp-config-level control, and the filter is the runtime override that
	 * can force either direction.
	 *
	 * @since 2.0.0.2
	 *
	 * @return bool
	 */
	public static function is_disabled(): bool {

		$disabled = get_option( self::OPTION_DISABLED )
			|| ( defined( 'WPFORMS_SETUP_WIZARD_DISABLED' ) && WPFORMS_SETUP_WIZARD_DISABLED );

		/**
		 * Filter whether the Setup Wizard is disabled entirely.
		 *
		 * Returning true suppresses the first-run auto-launch, the manual
		 * launch via the `wpforms_setup_wizard` query argument, and the
		 * Welcome page fallback activation redirect.
		 *
		 * @since 2.0.0.2
		 *
		 * @param bool $disabled Whether the wizard is disabled. Defaults to true
		 *                       when the `wpforms_setup_wizard_disabled` option
		 *                       or the `WPFORMS_SETUP_WIZARD_DISABLED` constant
		 *                       is set, otherwise false.
		 */
		return (bool) apply_filters( 'wpforms_setup_wizard_setup_wizard_is_disabled', (bool) $disabled );
	}

	/**
	 * Whether the current request is a context the wizard must never auto-launch in.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private static function is_excluded_context(): bool {

		if ( is_network_admin() ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['activate-multi'] );
	}

	/**
	 * Whether the current admin page must not be interrupted by the wizard.
	 *
	 * Mirrors the Welcome page exclusions: launching from the block editor breaks
	 * the WPForms Gutenberg block, and the form builder is its own flow.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private static function is_excluded_page(): bool {

		global $pagenow;

		if ( in_array( $pagenow, [ 'edit.php', 'post.php', 'post-new.php', 'site-editor.php' ], true ) ) {
			return true;
		}

		return wpforms_is_admin_page( 'builder' );
	}

	/**
	 * Determine whether the manual launch query argument is present and valid.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_manual_launch_request(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ self::QUERY_ARG ] );
	}

	/**
	 * Get the URL the wizard should return the user to on exit.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_exit_url(): string {

		return Page::get_url();
	}

	/**
	 * Get the URL the wizard should restart from.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_restart_url(): string {

		return add_query_arg(
			self::QUERY_ARG,
			1,
			$this->get_exit_url()
		);
	}

	/**
	 * Get the URL that launches the wizard manually from any admin page.
	 *
	 * Shared with the Welcome page so its "Launch Setup Wizard" notice and the
	 * wizard agree on the query argument without duplicating its name.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function get_manual_launch_url(): string {

		return add_query_arg(
			self::QUERY_ARG,
			1,
			admin_url( 'admin.php?page=wpforms-overview' )
		);
	}

	/**
	 * Get the Welcome getting-started URL used as the client-side fallback.
	 *
	 * If the external SPA never takes over after the handoff, the bridge sends
	 * the user here instead of leaving them on the spinner.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_fallback_url(): string {

		return admin_url( 'index.php?page=wpforms-getting-started' );
	}
}
