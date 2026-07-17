<?php

namespace WPForms\SetupWizard;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPForms\SetupChecklist\Page;
use WPForms\SetupWizard\Service\StateManager;

/**
 * Setup Wizard REST API.
 *
 * Exposes the endpoints the SPA calls back into:
 * - `GET  /hydrate`              Initial fetch of settings + wizard_settings.
 * - `POST /update`               Persist wizard step state.
 * - `POST /install-plugins`      License-gated install of addons/cross-product plugins.
 * - `POST /complete`             Finalize the wizard.
 * - `GET  /license/key`          Fetch the configured license key.
 * - `POST /license/verify`       Validate and activate a license key.
 *
 * Every route uses `validate_request()` as its `permission_callback`. The
 * permission callback also hydrates the bound user into the current request
 * via `wp_set_current_user()` so downstream calls behave as if the admin were
 * logged in on the WP host.
 *
 * @since 2.0.0
 */
class RestApi {

	/**
	 * REST namespace.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const ROUTE_NAMESPACE = 'wpforms/v1';

	/**
	 * Route prefix shared by all wizard routes.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const ROUTE_BASE = '/setup-wizard';

	/**
	 * License API base URL, used when the Pro `WPFORMS_UPDATER_API` constant is
	 * not defined (i.e. in Lite).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const LICENSE_API_URL_FALLBACK = 'https://wpformsapi.com/license/v1';

	/**
	 * Minimum accepted license-key length (anti-abuse guard).
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private const LICENSE_MIN_KEY_LENGTH = 16;

	/**
	 * Maximum accepted license-key length (anti-abuse guard).
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private const LICENSE_MAX_KEY_LENGTH = 255;

	/**
	 * Auth service.
	 *
	 * @since 2.0.0
	 *
	 * @var Auth
	 */
	private $auth;

	/**
	 * Setup Wizard state service.
	 *
	 * @since 2.0.0
	 *
	 * @var StateManager
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Auth         $auth    Auth service.
	 * @param StateManager $service State manager.
	 */
	public function __construct( Auth $auth, StateManager $service ) {

		$this->auth    = $auth;
		$this->service = $service;
	}

	/**
	 * Register REST routes.
	 *
	 * @since 2.0.0
	 */
	public function register_routes(): void {

		$permission = [ $this, 'validate_request' ];

		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_BASE . '/hydrate',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'hydrate' ],
				'permission_callback' => $permission,
			]
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_BASE . '/update',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update' ],
				'permission_callback' => $permission,
			]
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_BASE . '/install-plugins',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'install_plugins' ],
				'permission_callback' => $permission,
				'args'                => [
					'plugins' => [
						'type'              => 'array',
						'required'          => true,
						'default'           => [],
						'items'             => [
							'type' => 'string',
						],
						'sanitize_callback' => [ $this, 'sanitize_plugin_files' ],
					],
				],
			]
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_BASE . '/complete',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'complete' ],
				'permission_callback' => $permission,
				'args'                => [
					'outcome' => [
						'type'              => 'string',
						'default'           => 'build',
						'enum'              => [ 'build', 'import', 'exit', 'forms' ],
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_BASE . '/stripe/connect-url',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'stripe_connect_url' ],
				'permission_callback' => $permission,
			]
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_BASE . '/license/key',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'license_key' ],
				'permission_callback' => $permission,
			]
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_BASE . '/license/verify',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'verify_license' ],
				'permission_callback' => $permission,
				'args'                => [
					'key' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Whether the current request is the Setup Wizard's install-plugins REST call.
	 *
	 * Evaluated as early as `wpforms_loaded`, before the REST route is dispatched
	 * and before the session is authenticated, so it matches the namespaced route
	 * against the request URI rather than relying on the REST dispatcher. Pretty
	 * permalinks carry the route in the URL path; plain permalinks carry it in the
	 * `rest_route` query var, so both forms are checked. The Addons handler reads
	 * this to load its addon and license data in this otherwise non-admin context;
	 * the install action itself stays gated by `validate_request()` and
	 * `wpforms_can_install()`.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_install_request(): bool {

		if ( ! wpforms_is_rest() ) {
			return false;
		}

		$route = self::ROUTE_NAMESPACE . self::ROUTE_BASE . '/install-plugins';

		// Plain permalinks route REST through the `rest_route` query var
		// (e.g. `/index.php?rest_route=/wpforms/v1/...`), leaving the route out of
		// the URL path, so match it there first. An exact match keeps an unrelated
		// longer route from triggering the early load.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rest_route = isset( $_GET['rest_route'] ) ? sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) : '';

		if ( $rest_route !== '' ) {
			return untrailingslashit( $rest_route ) === '/' . $route;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = untrailingslashit( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );

		// Match the route as the trailing path segment (REST adds a `/wp-json` prefix),
		// not merely a substring, so an unrelated longer route cannot trigger the early load.
		return $path === $route || substr( $path, - strlen( '/' . $route ) ) === '/' . $route;
	}

	/**
	 * Sanitize the `plugins` argument for the install-plugins route.
	 *
	 * Coerces to an array of plugin-file strings (`{directory}/{main-file.php}`);
	 * non-string entries and empty values are dropped. `wpforms_sanitize_key()`
	 * preserves the `/` and `.` a plugin file needs. The catalog whitelist in
	 * `PluginCatalog::main_file()` remains the guard against installing an
	 * arbitrary path.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Raw argument value supplied by the SPA.
	 *
	 * @return array
	 */
	public function sanitize_plugin_files( $value ): array {

		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values( array_filter( array_map( 'wpforms_sanitize_key', $value ) ) );
	}

	/**
	 * Permission callback for every wizard route.
	 *
	 * Verifies the session token via Auth, hydrates the bound user with
	 * `wp_set_current_user()`, and refreshes the token TTL on success.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|WP_Error True when the request is allowed.
	 */
	public function validate_request( WP_REST_Request $request ) {

		$user_id = $this->auth->validate_request( $request );

		if ( $user_id <= 0 ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'Invalid Setup Wizard session.', 'wpforms-lite' ),
				[ 'status' => 401 ]
			);
		}

		wp_set_current_user( $user_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'Insufficient capability.', 'wpforms-lite' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * `GET /hydrate` Initial fetch of the wizard state.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function hydrate( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		return new WP_REST_Response( $this->service->get_hydrate_payload(), 200 );
	}

	/**
	 * `POST /update` Replace the wizard_settings blob.
	 *
	 * The request body is the new `wizard_settings` snapshot. The server treats
	 * the contents as opaque and overwrites the previous value wholesale. The
	 * single exception, on Pro, is the `lite_connect.consent` flag: when truthy,
	 * the state manager schedules the Lite Connect entries restore in the
	 * background (see {@see \WPForms\Pro\SetupWizard\Service\StateManager}).
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {

		$payload = (array) $request->get_json_params();
		$state   = $this->service->save_wizard_settings( $payload );

		return new WP_REST_Response( $state, 200 );
	}

	/**
	 * `POST /install-plugins` Install queued plugins/addons.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function install_plugins( WP_REST_Request $request ): WP_REST_Response {

		$plugins = (array) $request->get_param( 'plugins' );

		return new WP_REST_Response( $this->service->install_plugins( $plugins ), 200 );
	}

	/**
	 * `POST /complete` Finalize the wizard and resolve every redirect target.
	 *
	 * Now fired once on the SPA's Complete-page mount rather than per-CTA, so
	 * the response carries the redirect URLs for all outcomes up front. The
	 * CTAs navigate using the stored URLs without re-calling the endpoint.
	 *
	 * Delegates to `StateManager::complete()` so the manager can refresh
	 * its `settings` snapshot once post-wizard side effects exist. The SPA owns
	 * the `wizard_settings` blob and is expected to send any completion
	 * metadata (`completed_at`, cleared queues) through `/update` before
	 * invoking this route.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function complete( WP_REST_Request $request ): WP_REST_Response {

		$outcome = (string) $request->get_param( 'outcome' );

		$this->service->complete( $outcome );
		$this->auth->revoke();

		$state = $this->service->get_state();

		return new WP_REST_Response(
			[
				'redirect_urls'   => [
					'build'  => $this->get_redirect_url( 'build' ),
					'import' => $this->get_redirect_url( 'import' ),
					'exit'   => $this->get_redirect_url( 'exit' ),
					'forms'  => $this->get_redirect_url( 'forms' ),
				],
				'wizard_settings' => $state['wizard_settings'],
			],
			200
		);
	}

	/**
	 * `POST /stripe/connect-url` Get the Stripe Connect URL for the wizard.
	 *
	 * Returns a kickoff URL that defers OAuth URL generation to wp-admin context
	 * so the nonce is bound to the correct session.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function stripe_connect_url( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		$result = $this->service->get_stripe_connect_url();

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * `GET /license/key` Fetch the configured license key only.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function license_key( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		return new WP_REST_Response( [ 'key' => wpforms_get_license_key() ], 200 );
	}

	/**
	 * `POST /license/verify` Validate and activate a license key.
	 *
	 * Verifies the key against the license API and runs the edition-specific
	 * completion step: Pro only stores the verified key, while Lite upgrades
	 * the site to WPForms Pro (install, activate, deactivate Lite).
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function verify_license( WP_REST_Request $request ): WP_REST_Response {

		$key    = (string) $request->get_param( 'key' );
		$result = $this->activate_license( $key );

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Validate a key against the license API and run the edition-specific
	 * completion step.
	 *
	 * The validated key is persisted before the completion step runs, so a valid
	 * key is never lost if a subsequent edition-specific action (e.g. the Lite
	 * Pro install) fails. The Lite and Pro controllers override this seam; the
	 * shared base only verifies and stores the key.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key License key.
	 *
	 * @return array|WP_Error
	 */
	protected function activate_license( string $key ) {

		$verify = $this->verify_key( $key );

		if ( is_wp_error( $verify ) ) {
			return $verify;
		}

		update_option(
			'wpforms_license',
			[
				'key'              => $key,
				'type'             => (string) $verify['type'],
				'is_expired'       => false,
				'is_disabled'      => false,
				'is_invalid'       => false,
				'is_limit_reached' => false,
				'is_flagged'       => false,
			]
		);

		return [
			'success'   => true,
			'installed' => true,
			'activated' => true,
			'message'   => esc_html__( 'License activated.', 'wpforms-lite' ),
		];
	}

	/**
	 * Verify a key against the license API without persisting anything.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key License key.
	 *
	 * @return array|WP_Error Decoded verification response, or `WP_Error` when
	 *                        the key is rejected, the API is unreachable, or the
	 *                        response is malformed.
	 */
	private function verify_key( string $key ) {

		$size_error = $this->validate_license_key_size( $key );

		if ( $size_error !== null ) {
			return $size_error;
		}

		$verify = $this->license_api_request( 'verify-key', $key );

		if ( $verify === null ) {
			return new WP_Error(
				'wpforms_setup_wizard_license_connection',
				esc_html__( 'There was an error connecting to the license server. Please try again later.', 'wpforms-lite' ),
				[ 'status' => 503 ]
			);
		}

		if ( ! empty( $verify['error'] ) ) {
			return new WP_Error(
				'wpforms_setup_wizard_license_rejected',
				(string) $verify['error'],
				[ 'status' => 400 ]
			);
		}

		// A success response without a license type is malformed and must not be treated as a valid activation.
		if ( empty( $verify['type'] ) ) {
			return new WP_Error(
				'wpforms_setup_wizard_license_invalid_response',
				esc_html__( 'There was an error connecting to the license server. Please try again later.', 'wpforms-lite' ),
				[ 'status' => 502 ]
			);
		}

		return $verify;
	}

	/**
	 * Perform a live GET request against the license API.
	 *
	 * Replicates the essentials of the Pro `perform_remote_request()` without
	 * the Pro `License` class or any caching layer.
	 *
	 * @since 2.0.0
	 *
	 * @param string $action API action slug appended to the base URL.
	 * @param string $key    License key.
	 * @param array  $extra  Extra query args merged over the defaults.
	 *
	 * @return array|null Decoded JSON as an associative array, or null on transport/HTTP error.
	 */
	protected function license_api_request( string $action, string $key, array $extra = [] ): ?array {

		$args = array_merge(
			[
				'tgm-updater-action'      => $action,
				'tgm-updater-key'         => $key,
				'tgm-updater-wp-version'  => get_bloginfo( 'version' ),
				'tgm-updater-php-version' => PHP_VERSION,
				'tgm-updater-referer'     => site_url(),
				'wpforms_refresh_key'     => 1,
			],
			$extra
		);

		$response = wp_remote_get(
			add_query_arg( $args, $this->license_api_url() . '/' . $action ),
			[
				'user-agent' => wpforms_get_default_user_agent(),
				'timeout'    => 30,
			]
		);

		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) ? $body : null;
	}

	/**
	 * Resolve the license API base URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function license_api_url(): string {

		return defined( 'WPFORMS_UPDATER_API' ) ? WPFORMS_UPDATER_API : self::LICENSE_API_URL_FALLBACK;
	}

	/**
	 * Guard the key length to block empty, garbage, or oversized payloads.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key License key.
	 *
	 * @return WP_Error|null `WP_Error` when out of bounds, null when acceptable.
	 */
	private function validate_license_key_size( string $key ): ?WP_Error {

		$length = strlen( $key );

		if ( $length < self::LICENSE_MIN_KEY_LENGTH || $length > self::LICENSE_MAX_KEY_LENGTH ) {
			return new WP_Error(
				'wpforms_setup_wizard_license_invalid_size',
				esc_html__( 'Please enter a valid license key.', 'wpforms-lite' ),
				[ 'status' => 400 ]
			);
		}

		return null;
	}

	/**
	 * Build an error response from a WP_Error, using its `status` data as the HTTP code.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Error $error Error to surface to the SPA.
	 *
	 * @return WP_REST_Response
	 */
	private function error_response( WP_Error $error ): WP_REST_Response {

		$status = (int) ( ( (array) $error->get_error_data() )['status'] ?? 500 );

		return new WP_REST_Response( [ 'error' => $error->get_error_message() ], $status );
	}

	/**
	 * Resolve the post-wizard redirect target for a given outcome.
	 *
	 * @since 2.0.0
	 *
	 * @param string $outcome Outcome page: `build`, `import`, `exit`, or `forms`.
	 *
	 * @return string
	 */
	private function get_redirect_url( string $outcome ): string {

		if ( $outcome === 'import' ) {
			return admin_url( 'admin.php?page=wpforms-tools&view=import&tab=forms' );
		}

		if ( $outcome === 'exit' ) {
			return Page::get_url();
		}

		if ( $outcome === 'forms' ) {
			return admin_url( 'admin.php?page=wpforms-overview' );
		}

		return admin_url( 'admin.php?page=wpforms-builder' );
	}
}
