<?php

namespace WPForms\Admin\Pages;

/**
 * SMTP Sub-page.
 *
 * @since 1.5.7
 */
class SMTP extends Page {

	/**
	 * Admin menu page slug.
	 *
	 * @since 1.5.7
	 *
	 * @var string
	 */
	public const SLUG = 'wpforms-smtp';

	/**
	 * Transient name used to store the install source between page load and plugin activation.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const SOURCE_TRANSIENT = 'wpforms_smtp_source';

	/**
	 * Configuration.
	 *
	 * @since 1.5.7
	 *
	 * @var array
	 */
	protected $config = [
		'lite_plugin'       => 'wp-mail-smtp/wp_mail_smtp.php',
		'lite_wporg_url'    => 'https://wordpress.org/plugins/wp-mail-smtp/',
		'lite_download_url' => 'https://downloads.wordpress.org/plugin/wp-mail-smtp.zip',
		'pro_plugin'        => 'wp-mail-smtp-pro/wp_mail_smtp.php',
		'smtp_settings_url' => 'admin.php?page=wp-mail-smtp',
		'smtp_wizard_url'   => 'admin.php?page=wp-mail-smtp-setup-wizard',
		// The parent uses the `{name}_onboarding` key for the step 'Setup' button URL.
		'smtp_onboarding'   => 'admin.php?page=wp-mail-smtp-setup-wizard',
	];

	/**
	 * Get the plugin name for use in IDs, CSS classes, config keys and AJAX action.
	 *
	 * @since 2.0.0
	 *
	 * @return string Plugin name.
	 */
	protected static function get_plugin_name(): string {

		return 'smtp';
	}

	/**
	 * Hooks.
	 *
	 * @since 1.5.7
	 */
	public function hooks(): void {

		// Check what page we are on.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( $page === self::SLUG ) {
			add_action( 'admin_init', [ $this, 'redirect_to_smtp_settings' ] );

			// Persist the install source so it survives the AJAX activation request.
			$this->maybe_store_source();
		}

		parent::hooks();

		// The addon hook is scoped to the SMTP page, matching the pre-refactor behavior.
		if ( $page === self::SLUG ) {
			/**
			 * Hook for addons.
			 *
			 * @since 1.5.7
			 */
			do_action( 'wpforms_admin_pages_smtp_hooks' );
		}
	}

	/**
	 * Store the install source in a short-lived, user-keyed transient.
	 *
	 * The shared AJAX handler does not forward a `source` parameter, so we capture
	 * the `?source=` query argument on page load and read it back on activation.
	 *
	 * @since 2.0.0
	 */
	protected function maybe_store_source(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source = sanitize_text_field( wp_unslash( $_GET['source'] ?? '' ) );

		if ( $source === '' ) {
			return;
		}

		// Only a known source is mapped; everything else falls back to the default on activation.
		$value = $source === 'woocommerce' ? 'wpforms-woocommerce' : 'wpforms';

		// TTL is generous enough to outlive a realistic install-and-activate flow.
		set_transient( self::SOURCE_TRANSIENT . '_' . get_current_user_id(), $value, HOUR_IN_SECONDS );
	}

	/**
	 * Set the wp_mail_smtp_source option on WP Mail SMTP plugin activation.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_basename Plugin basename.
	 */
	public function plugin_activated( string $plugin_basename ): void {

		if ( $plugin_basename !== $this->config['lite_plugin'] ) {
			return;
		}

		$transient_key = self::SOURCE_TRANSIENT . '_' . get_current_user_id();
		$source        = get_transient( $transient_key );

		delete_transient( $transient_key );

		// Absent or unknown source falls back to the default attribution.
		if ( ! in_array( $source, [ 'wpforms', 'wpforms-woocommerce' ], true ) ) {
			$source = 'wpforms';
		}

		update_option( 'wp_mail_smtp_source', $source );
	}

	/**
	 * Set wp_mail_smtp_source option to 'wpforms' on WP Mail SMTP plugin activation.
	 *
	 * @since 1.8.7
	 * @deprecated 2.0.0
	 *
	 * @param string $plugin_basename Plugin basename.
	 */
	public function smtp_activated( $plugin_basename ): void {

		_deprecated_function( __METHOD__, '2.0.0', __CLASS__ . '::plugin_activated()' );

		$this->plugin_activated( (string) $plugin_basename );
	}

	/**
	 * Get heading image URL.
	 *
	 * The parent default assumes an SVG, but WP Mail SMTP ships a PNG logo.
	 *
	 * @since 2.0.0
	 *
	 * @return string Heading image URL.
	 */
	protected function get_heading_image_url(): string {

		return WPFORMS_PLUGIN_URL . 'assets/images/smtp/wpforms-wpmailsmtp.png';
	}

	/**
	 * Get heading title text.
	 *
	 * @since 2.0.0
	 *
	 * @return string Heading title.
	 */
	protected function get_heading_title(): string {

		return esc_html__( 'Making Email Deliverability Easy for WordPress', 'wpforms-lite' );
	}

	/**
	 * Get heading alt text for logo.
	 *
	 * @since 2.0.0
	 *
	 * @return string Heading alt text.
	 */
	protected function get_heading_alt_text(): string {

		return esc_attr__( 'WPForms ♥ WP Mail SMTP', 'wpforms-lite' );
	}

	/**
	 * Get heading description strings.
	 *
	 * @since 2.0.0
	 *
	 * @return array Array of description strings.
	 */
	protected function get_heading_strings(): array {

		return [
			esc_html__( 'WP Mail SMTP fixes deliverability problems with your WordPress emails and form notifications. It\'s built by the same folks behind WPForms.', 'wpforms-lite' ),
		];
	}

	/**
	 * Get screenshot features list.
	 *
	 * @since 2.0.0
	 *
	 * @return array Array of feature strings.
	 */
	protected function get_screenshot_features(): array {

		return [
			esc_html__( 'Improves email deliverability in WordPress.', 'wpforms-lite' ),
			esc_html__( 'Used by 4+ million websites.', 'wpforms-lite' ),
			esc_html__( 'Free mailers: SendLayer, SMTP.com, Brevo, Google Workspace / Gmail, Mailgun, Postmark, SendGrid.', 'wpforms-lite' ),
			esc_html__( 'Pro mailers: Amazon SES, Microsoft 365 / Outlook.com, Zoho Mail.', 'wpforms-lite' ),
		];
	}

	/**
	 * Get screenshot alt text.
	 *
	 * @since 2.0.0
	 *
	 * @return string Alt text for screenshot image.
	 */
	protected function get_screenshot_alt_text(): string {

		return esc_attr__( 'WP Mail SMTP screenshot', 'wpforms-lite' );
	}

	/**
	 * Generate and output step 'Result' section HTML.
	 *
	 * The SMTP landing page completes at step 'Setup' (go to settings),
	 * so no separate result section is rendered.
	 *
	 * @since 2.0.0
	 */
	protected function output_section_step_result(): void {
		// SMTP has no third step; the flow ends after the setup step.
	}

	/**
	 * Ajax endpoint. Check plugin setup status.
	 * Used to properly init step 'Setup' section after completing step 'Install'.
	 *
	 * @since 1.5.7
	 */
	public function ajax_check_plugin_status(): void {

		// Security checks.
		if (
			! check_ajax_referer( 'wpforms-admin', 'nonce', false ) ||
			! wpforms_current_user_can()
		) {
			wp_send_json_error(
				[ 'error' => esc_html__( 'You do not have permission.', 'wpforms-lite' ) ]
			);

			return;
		}

		if ( ! $this->is_smtp_activated() ) {
			wp_send_json_error(
				[ 'error' => esc_html__( 'Plugin unavailable.', 'wpforms-lite' ) ]
			);

			return;
		}

		$result                  = [];
		$result['setup_status']  = (int) $this->is_smtp_configured();
		$result['license_level'] = wp_mail_smtp()->get_license_type();

		// Prevent redirect to the WP Mail SMTP Setup Wizard on the fresh installs.
		// We need this workaround since WP Mail SMTP doesn't check whether the mailer is already configured when redirecting to the Setup Wizard on the first run.
		if ( $result['setup_status'] > 0 ) {
			// SMTP has no third step, so the 'Setup' button itself becomes the final CTA linking to the settings page.
			$result['setup_completed_url'] = admin_url( $this->config['smtp_settings_url'] );

			update_option( 'wp_mail_smtp_activation_prevent_redirect', true );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Get $phpmailer instance.
	 *
	 * @since 1.5.7
	 * @since 1.6.1.2 Conditionally returns $phpmailer v5 or v6.
	 * @since 1.8.7 Use always $phpmailer v6.
	 *
	 * @return \PHPMailer|\PHPMailer\PHPMailer\PHPMailer Instance of PHPMailer.
	 */
	protected function get_phpmailer() {

		global $phpmailer;

		if ( ! ( $phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
			require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
			$phpmailer = new \PHPMailer\PHPMailer\PHPMailer( true ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return $phpmailer;
	}

	/**
	 * Whether WP Mail SMTP plugin configured or not.
	 *
	 * @since 1.5.7
	 *
	 * @return bool True if some mailer is selected and configured properly.
	 */
	protected function is_smtp_configured(): bool {

		if ( ! $this->is_smtp_activated() ) {
			return false;
		}

		$phpmailer = $this->get_phpmailer();
		$mailer    = \WPMailSMTP\Options::init()->get( 'mail', 'mailer' );

		return ! empty( $mailer ) &&
			$mailer !== 'mail' &&
			wp_mail_smtp()->get_providers()->get_mailer( $mailer, $phpmailer )->is_mailer_complete();
	}

	/**
	 * Whether WP Mail SMTP plugin active or not.
	 *
	 * @since 1.5.7
	 *
	 * @return bool True if SMTP plugin is active.
	 */
	protected function is_smtp_activated(): bool {

		return function_exists( 'wp_mail_smtp' ) && ( is_plugin_active( $this->config['lite_plugin'] ) || is_plugin_active( $this->config['pro_plugin'] ) );
	}

	/**
	 * Redirect to SMTP settings page.
	 *
	 * @since 1.5.7
	 */
	public function redirect_to_smtp_settings(): void {

		// Redirect to SMTP plugin if it is activated.
		if ( $this->is_smtp_configured() ) {
			wp_safe_redirect( admin_url( $this->config['smtp_settings_url'] ) );
			exit;
		}
	}

	/**
	 * Whether a plugin is configured or not.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if a plugin is configured properly.
	 */
	protected function is_plugin_configured(): bool {

		return $this->is_smtp_configured();
	}

	/**
	 * Whether a plugin is active or not.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if the plugin is active.
	 */
	protected function is_plugin_activated(): bool {

		return $this->is_smtp_activated();
	}

	/**
	 * Whether a plugin is finished setup or not.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if the plugin is finished setup.
	 */
	protected function is_plugin_finished_setup(): bool {

		return $this->is_smtp_configured();
	}

	/**
	 * Whether a plugin is available (class/function exists).
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if a plugin is available.
	 */
	protected function is_plugin_available(): bool {

		return function_exists( 'wp_mail_smtp' );
	}

	/**
	 * Whether a pro-version is active.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if a pro-version is active.
	 */
	protected function is_pro_active(): bool {

		return $this->is_smtp_activated() && is_plugin_active( $this->config['pro_plugin'] );
	}

	/**
	 * Get the heading for the installation step.
	 *
	 * @since 2.0.0
	 *
	 * @return string Install step heading.
	 */
	protected function get_install_heading(): string {

		return esc_html__( 'Install and Activate WP Mail SMTP', 'wpforms-lite' );
	}

	/**
	 * Get the description for the installation step.
	 *
	 * @since 2.0.0
	 *
	 * @return string Install step description.
	 */
	protected function get_install_description(): string {

		return esc_html__( 'Install WP Mail SMTP from the WordPress.org plugin repository.', 'wpforms-lite' );
	}

	/**
	 * Get the plugin title.
	 *
	 * @since 2.0.0
	 *
	 * @return string Plugin title.
	 */
	protected function get_plugin_title(): string {

		return esc_html__( 'WP Mail SMTP', 'wpforms-lite' );
	}

	/**
	 * Get the installation button text.
	 *
	 * @since 2.0.0
	 *
	 * @return string Install button text.
	 */
	protected function get_install_button_text(): string {

		return esc_html__( 'Install WP Mail SMTP', 'wpforms-lite' );
	}

	/**
	 * Get the text when a plugin is installed and activated.
	 *
	 * @since 2.0.0
	 *
	 * @return string Installed & activated text.
	 */
	protected function get_installed_activated_text(): string {

		return esc_html__( 'WP Mail SMTP Installed & Activated', 'wpforms-lite' );
	}

	/**
	 * Get the activate button text.
	 *
	 * @since 2.0.0
	 *
	 * @return string Activate button text.
	 */
	protected function get_activate_text(): string {

		return esc_html__( 'Activate WP Mail SMTP', 'wpforms-lite' );
	}

	/**
	 * Get the heading for the setup step.
	 *
	 * @since 2.0.0
	 *
	 * @return string Setup step heading.
	 */
	protected function get_setup_heading(): string {

		return esc_html__( 'Set Up WP Mail SMTP', 'wpforms-lite' );
	}

	/**
	 * Get the description for the setup step.
	 *
	 * @since 2.0.0
	 *
	 * @return string Setup step description.
	 */
	protected function get_setup_description(): string {

		return esc_html__( 'Select and configure your mailer.', 'wpforms-lite' );
	}

	/**
	 * Get the setup button text.
	 *
	 * @since 2.0.0
	 *
	 * @return string Setup button text.
	 */
	protected function get_setup_button_text(): string {

		return esc_html__( 'Open Setup Wizard', 'wpforms-lite' );
	}

	/**
	 * Get the text when setup is completed.
	 *
	 * @since 2.0.0
	 *
	 * @return string Setup completed text.
	 */
	protected function get_setup_completed_text(): string {

		return esc_html__( 'Go to SMTP settings', 'wpforms-lite' );
	}

	/**
	 * Get the text when a pro-version is installed and activated.
	 *
	 * @since 2.0.0
	 *
	 * @return string Pro installed and activated text.
	 */
	protected function get_pro_installed_activated_text(): string {

		return esc_html__( 'WP Mail SMTP Installed & Activated', 'wpforms-lite' );
	}
}
