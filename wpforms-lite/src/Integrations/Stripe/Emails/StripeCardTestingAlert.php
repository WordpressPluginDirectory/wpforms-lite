<?php

namespace WPForms\Integrations\Stripe\Emails;

use WPForms\Emails\Templates\Summary;
use WPForms\Helpers\Templates;

/**
 * Stripe card testing alert email template.
 *
 * Extends the Summary template to reuse its header (WPForms logo), background,
 * colors, and queries. Body and footer are overridden with alert-specific
 * content via templates/integrations/stripe/emails/stripe-card-testing-alert-{body,footer}.php.
 *
 * @since 2.0.0
 */
class StripeCardTestingAlert extends Summary {

	/**
	 * Template slug.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	const TEMPLATE_SLUG = 'stripe-card-testing-alert';

	/**
	 * Custom template path under the plugin's templates directory.
	 *
	 * @since 2.0.0
	 */
	const CUSTOM_TEMPLATE_PATH = 'integrations/stripe/emails/';

	/**
	 * Get the template parent slug.
	 *
	 * Returns 'summary' so missing template parts (header, style, queries) fall
	 * back to the Summary versions, preserving its header image and styling.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_parent_slug() {

		return Summary::TEMPLATE_SLUG;
	}

	/**
	 * Resolve the template file name, preferring the Stripe-namespaced location.
	 *
	 * Looks for `templates/integrations/stripe/emails/{slug}-{part}.php` first.
	 * Falls back to the parent's lookup, which finds the summary-* templates
	 * for header/style/queries.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name Base template part name (e.g. 'body', 'footer').
	 *
	 * @return string
	 */
	protected function get_full_template_name( $name ) {

		$sanitized = sanitize_file_name( $name );

		if ( $this->plain_text ) {
			$sanitized .= '-plain';
		}

		$custom = self::CUSTOM_TEMPLATE_PATH . $this->get_slug() . '-' . $sanitized;

		if ( ! Templates::locate( $custom . '.php' ) ) {
			return parent::get_full_template_name( $name );
		}

		/** This filter is documented in src/Emails/Templates/General.php. */
		return apply_filters( 'wpforms_emails_templates_general_get_full_template_name', $custom, $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
