<?php

namespace WPForms\SetupChecklist;

use WPForms\Education\ActiveLayer\Helper as ActiveLayer;
use WPForms\Integrations\LiteConnect\Integration;
use WPForms\SetupWizard\Service\PluginDetector;
use WPForms\SetupWizard\Service\SettingsDetector;
use WPMailSMTP\Options;

/**
 * Setup Checklist completion detector.
 *
 * Resolves whether a checklist item is complete, keyed by the item `id` declared
 * in {@see Config}. Every rule is intentionally cheap — option reads, a cached
 * plugin scan, or a single light query — and performs no remote calls, so the
 * page and the menu progress bar can detect state on every load. Rules reuse the
 * canonical core sources (the form object, settings, the Stripe integration, the
 * ActiveLayer helper) rather than re-implementing detection.
 *
 * @since 2.0.0
 */
class CompletionDetector {

	/**
	 * Per-site state store (source of the email-settings-saved flag).
	 *
	 * @since 2.0.0
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Plugin detector (installed/active status for cross-product plugins).
	 *
	 * @since 2.0.0
	 *
	 * @var PluginDetector
	 */
	private $plugin_detector;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param State          $state           Per-site state store.
	 * @param PluginDetector $plugin_detector Plugin detector.
	 */
	public function __construct( State $state, PluginDetector $plugin_detector ) {

		$this->state           = $state;
		$this->plugin_detector = $plugin_detector;
	}

	/**
	 * Whether the given checklist item is complete.
	 *
	 * @since 2.0.0
	 *
	 * @param string $item_id Checklist item ID from {@see Config}.
	 *
	 * @return bool
	 */
	public function is_complete( string $item_id ): bool {

		$checks = [
			'build_form'           => [ $this, 'is_form_created' ],
			'import_forms'         => [ $this, 'is_form_imported' ],
			'lite_connect'         => [ $this, 'is_lite_connect_complete' ],
			'email_deliverability' => [ $this, 'is_email_deliverability_configured' ],
			'spam_protection'      => [ $this, 'is_spam_protection_enabled' ],
			'email_notifications'  => [ $this->state, 'is_email_settings_saved' ],
			'privacy_compliance'   => [ $this, 'is_privacy_compliance_configured' ],
			'payment_gateway'      => [ $this, 'is_payment_gateway_connected' ],
		];

		if ( ! isset( $checks[ $item_id ] ) ) {
			return false;
		}

		$callback = $checks[ $item_id ];

		return (bool) $callback();
	}

	/**
	 * Whether the user has built a form — at least one form that was not imported.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_form_created(): bool {

		$forms = wpforms()->obj( 'form' );

		// Count only a form the user actually built.
		$built = $forms->get(
			'',
			[
				'post__not_in'           => $this->get_imported_form_ids(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		return ! empty( $built );
	}

	/**
	 * Whether at least one form has been imported from another form plugin.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_form_imported(): bool {

		return ! empty( get_option( 'wpforms_imported', [] ) );
	}

	/**
	 * WPForms form IDs created by importing from another form plugin.
	 *
	 * @since 2.0.0
	 *
	 * @return int[]
	 */
	private function get_imported_form_ids(): array {

		$imported = get_option( 'wpforms_imported', [] );

		if ( ! is_array( $imported ) ) {
			return [];
		}

		$ids = [];

		foreach ( $imported as $forms ) {
			if ( is_array( $forms ) ) {
				$ids = array_merge( $ids, array_keys( $forms ) );
			}
		}

		return array_map( 'absint', $ids );
	}

	/**
	 * Whether the Lite Connect checklist item is complete.
	 *
	 * On Lite the item tracks enabling entry backups, so it is complete once Lite
	 * Connect is enabled. On Pro the user has already upgraded, so the item becomes
	 * the entry-restore step and is complete once the backed-up entries have been
	 * restored.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_lite_connect_complete(): bool {

		if ( wpforms()->is_pro() ) {
			return $this->is_lite_connect_restored();
		}

		return ( new SettingsDetector() )->is_lite_connect_enabled();
	}

	/**
	 * Whether the user had Lite Connect enabled.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function had_lite_connect_enabled(): bool {

		return class_exists( Integration::class ) && (bool) Integration::get_enabled_since();
	}

	/**
	 * Whether the Lite Connect entry backups have already been restored.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_lite_connect_restored(): bool {

		if ( ! class_exists( Integration::class ) ) {
			return false;
		}

		$import = wpforms_setting( 'import', false, Integration::get_option_name() );
		$status = is_array( $import ) ? ( $import['status'] ?? '' ) : '';

		return $status === 'done';
	}

	/**
	 * Whether the Lite Connect entry restore is currently in progress (scheduled or running,
	 * but not yet done).
	 *
	 * Exposed so the checklist CTA can show a disabled "Import in Progress" state instead
	 * of the actionable button while a restore is under way.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_lite_connect_restore_in_progress(): bool {

		if ( ! class_exists( Integration::class ) ) {
			return false;
		}

		$import = wpforms_setting( 'import', false, Integration::get_option_name() );
		$status = is_array( $import ) ? ( $import['status'] ?? '' ) : '';

		return in_array( $status, [ 'scheduled', 'running' ], true );
	}

	/**
	 * Whether WP Mail SMTP is installed and configured with a real mailer.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_email_deliverability_configured(): bool {

		if ( ! class_exists( Options::class ) ) {
			return false;
		}

		$options = Options::init();
		$mailer  = (string) $options->get( 'mail', 'mailer' );

		if ( $mailer === '' || $mailer === 'mail' ) {
			return false;
		}

		return ! method_exists( $options, 'is_mailer_complete' ) || $options->is_mailer_complete();
	}

	/**
	 * Whether spam protection is in place: ActiveLayer active or CAPTCHA configured.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_spam_protection_enabled(): bool {

		if ( class_exists( ActiveLayer::class ) && ActiveLayer::is_set_up() ) {
			return true;
		}

		$captcha = wpforms_get_captcha_settings();

		return $captcha['provider'] !== 'none'
			&& ! empty( $captcha['site_key'] )
			&& ! empty( $captcha['secret_key'] );
	}

	/**
	 * Whether WPConsent is active and its setup wizard (onboarding) is complete.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_privacy_compliance_configured(): bool {

		if ( ! $this->plugin_detector->status( 'wpconsent-cookies-banner-privacy-suite/wpconsent.php' )['active'] ) {
			return false;
		}

		return function_exists( 'wpconsent' )
			&& (bool) wpconsent()->settings->get_option( 'onboarding_completed' );
	}

	/**
	 * Whether a payment gateway is connected.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_payment_gateway_connected(): bool {

		$detector = new SettingsDetector();

		// Any supported gateway — Stripe, PayPal Commerce, Square, or Authorize.Net — connected in the CURRENT payment mode completes the item.
		return $detector->is_stripe_configured()
			|| $detector->is_paypal_commerce_configured()
			|| $detector->is_square_configured()
			|| $detector->is_authorize_net_configured();
	}
}
