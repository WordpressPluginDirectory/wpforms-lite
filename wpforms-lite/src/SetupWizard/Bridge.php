<?php

namespace WPForms\SetupWizard;

/**
 * POST bridge that hands off to the wizard SPA.
 *
 * Renders a tiny auto-submitting HTML form that POSTs `token`, `rest_url`,
 * `exit_url`, and `restart_url` to the SPA's `startSession` endpoint. The POST
 * keeps the token out of browser history and out of `Referer`/`X-Forwarded-*`
 * headers, satisfies the CORS preflight rules expected by the SPA, and hides
 * the payload from password managers and browser extensions that scan URLs.
 *
 * @since 2.0.0
 */
class Bridge {

	/**
	 * Auth service used to issue the per-launch token.
	 *
	 * @since 2.0.0
	 *
	 * @var Auth
	 */
	private $auth;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Auth $auth Auth service.
	 */
	public function __construct( Auth $auth ) {

		$this->auth = $auth;
	}

	/**
	 * Render the auto-submit POST form.
	 *
	 * Called from the orchestrator after `maybe_launch()` decides the wizard
	 * should run for the current request. Sends the user's browser to the SPA
	 * with the wizard handshake payload. The caller is responsible for
	 * `exit`-ing after this method returns.
	 *
	 * @since 2.0.0
	 *
	 * @param array $payload Handshake payload sent to the SPA. Keys:
	 *                       `token`, `rest_url`, `exit_url`, `restart_url`.
	 */
	public function render( array $payload ): void {

		$inputs = '';

		foreach ( $payload as $name => $value ) {
			$inputs .= sprintf(
				'<input type="hidden" name="%s" value="%s" />',
				esc_attr( (string) $name ),
				esc_attr( (string) $value )
			);
		}

		$action   = esc_url( $this->get_handoff_url() );
		$charset  = esc_attr( get_bloginfo( 'charset' ) );
		$language = esc_attr( get_bloginfo( 'language' ) );
		$title    = esc_html__( 'WPForms Setup Wizard', 'wpforms-lite' );
		$cta      = esc_html__( 'Continue to Setup Wizard', 'wpforms-lite' );

		$style = 'html,body{margin:0}body{min-height:100vh;background:#FFFFFF}';

		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Frame-Options: DENY' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<!DOCTYPE html><html lang="' . $language . '"><head>'
			. '<meta charset="' . $charset . '" />'
			. '<meta name="robots" content="noindex,nofollow" />'
			. '<title>' . $title . '</title>'
			. '<style>' . $style . '</style>'
			. '</head><body>'
			. '<form id="wpforms-setup-wizard-bridge" method="POST" action="' . $action . '">'
			. $inputs
			. '<noscript><button type="submit">' . $cta . '</button></noscript>'
			. '</form>'
			. '<script>document.getElementById("wpforms-setup-wizard-bridge").submit();</script>'
			. '</body></html>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build the handshake payload for the current user.
	 *
	 * @since 2.0.0
	 *
	 * @param string $exit_url    Where to send the user on close.
	 * @param string $restart_url Where to send the user on restart.
	 *
	 * @return array
	 */
	public function build_payload( string $exit_url, string $restart_url ): array {

		$payload = [
			'token'       => $this->auth->generate_token(),
			'rest_url'    => rest_url( 'wpforms/v1/setup-wizard' ),
			'exit_url'    => $exit_url,
			'restart_url' => $restart_url,
		];

		/**
		 * Filter the bridge handshake payload.
		 *
		 * Used by the Stripe OAuth flow to inject `current_step=payments`
		 * so the SPA resumes at /steps/payments instead of /welcome after
		 * a wp-admin re-entry.
		 *
		 * @since 2.0.0
		 *
		 * @param array $payload Handshake payload.
		 */
		return (array) apply_filters( 'wpforms_setup_wizard_bridge_payload', $payload );
	}

	/**
	 * Whether the wizard SPA is reachable and healthy right now.
	 *
	 * Server-side preflight run before the handoff: a top-level form POST
	 * abandons the bridge page, so a client-side timeout cannot recover once the
	 * browser has navigated to a broken SPA. Probing the co-located health route
	 * here lets the orchestrator fall back to the Welcome page instead. The probe
	 * runs on every launch (which is infrequent) and is intentionally not cached,
	 * so a changed handoff URL or a down SPA is reflected immediately.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_spa_reachable(): bool {

		/**
		 * Short-circuit the SPA health probe.
		 *
		 * Return a boolean to force the result (e.g. true in local development
		 * against a sail-hosted Product API, or false to exercise the fallback).
		 *
		 * @since 2.0.0
		 *
		 * @param bool|null $reachable Forced result, or null to run the probe.
		 */
		$forced = apply_filters( 'wpforms_setup_wizard_bridge_spa_reachable', null );

		if ( is_bool( $forced ) ) {
			return $forced;
		}

		$health_url = $this->get_health_url();

		if ( $health_url === '' ) {
			return false;
		}

		$response = wp_remote_get(
			$health_url,
			[
				'timeout'   => 5,
				'sslverify' => true,
			]
		);

		return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
	}

	/**
	 * Get the SPA health probe URL, co-located with the handoff endpoint.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_health_url(): string {

		return trailingslashit( $this->get_handoff_url() ) . 'health';
	}

	/**
	 * Get the SPA endpoint that consumes the handshake POST.
	 *
	 * The `WPFORMS_SETUP_WIZARD_URL` constant overrides the default for local
	 * development against a sail-hosted Product API.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_handoff_url(): string {

		if ( defined( 'WPFORMS_SETUP_WIZARD_URL' ) ) {
			return (string) WPFORMS_SETUP_WIZARD_URL;
		}

		return 'https://wpformsapi.com/setupwizard/v1';
	}
}
