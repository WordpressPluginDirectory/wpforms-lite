<?php

namespace WPForms\Integrations\SMTP;

use WPForms\Helpers\Plugin;
use WPMailSMTP\Options;

/**
 * WP Mail SMTP detection helpers.
 *
 * Single home for "is WP Mail SMTP active / configured" so the dashboard widget,
 * the SMTP settings page, and the SMTP notifications all share one check.
 *
 * @since 2.0.2
 */
class Helpers {

	/**
	 * WP Mail SMTP Lite plugin basename.
	 *
	 * @since 2.0.2
	 */
	private const LITE_PLUGIN = 'wp-mail-smtp/wp_mail_smtp.php';

	/**
	 * WP Mail SMTP Pro plugin basename.
	 *
	 * @since 2.0.2
	 */
	private const PRO_PLUGIN = 'wp-mail-smtp-pro/wp_mail_smtp.php';

	/**
	 * Whether WP Mail SMTP (Lite or Pro) is active and loaded.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public static function is_active(): bool {

		return function_exists( 'wp_mail_smtp' )
			&& ( Plugin::is_active( self::LITE_PLUGIN ) || Plugin::is_active( self::PRO_PLUGIN ) );
	}

	/**
	 * Whether WP Mail SMTP is active and configured with a real, complete mailer.
	 *
	 * The default 'mail' mailer (PHP mail) counts as "not configured". Uses the
	 * zero-arg Options::is_mailer_complete() check (guarded for older versions),
	 * so it needs no PHPMailer instance.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {

		if ( ! self::is_active() ) {
			return false;
		}

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
}
