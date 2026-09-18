<?php
/**
 * Helper functions related to privacy, geolocation and user data.
 *
 * @since 1.8.0
 */

/**
 * Get the user IP address.
 *
 * @since 1.2.5
 * @since 1.7.3 Improve the IP detection quality by taking care of proxies (e.g. when the site is behind Cloudflare).
 *
 * Code based on the:
 *   - WordPress method \WP_Community_Events::get_unsafe_client_ip
 *   - Cloudflare documentation https://support.cloudflare.com/hc/en-us/articles/206776727
 *
 * @return string
 */
function wpforms_get_ip(): string {

	$ip = '127.0.0.1';

	$address_headers = [
		'HTTP_TRUE_CLIENT_IP',
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_REAL_IP',
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'REMOTE_ADDR',
	];

	foreach ( $address_headers as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) {
			continue;
		}

		/*
		 * HTTP_X_FORWARDED_FOR can contain a chain of comma-separated addresses, with or without spaces.
		 * The first address is the original client. It can't be trusted for authenticity,
		 * but we don't need to for this purpose.
		 */

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$address_chain = explode( ',', wp_unslash( $_SERVER[ $header ] ) );
		$ip            = filter_var( trim( $address_chain[0] ), FILTER_VALIDATE_IP );

		break;
	}

	/**
	 * Filter detected IP address.
	 *
	 * @since 1.2.5
	 *
	 * @param string $ip IP address.
	 */
	return (string) filter_var( apply_filters( 'wpforms_get_ip', $ip ), FILTER_VALIDATE_IP );
}

/**
 * Get the visitor's preferred language tag from the request, e.g. `pt-br` from
 * `pt-BR,pt;q=0.9,en-US;q=0.8`.
 *
 * Read from the request header rather than a form field, so it cannot be spoofed from the
 * client. Only the highest-quality tag is kept: a translation is chosen by one language,
 * and the rest of the list would be user data with no use for it.
 *
 * Only the parts the translation lookup reads are kept, so `ca-valencia` is stored as `ca`
 * and resolves to the same translation. That also keeps what a client can put in the table
 * bounded: the free-form subtag a tag may legally carry is where an unlimited number of
 * distinct values would otherwise come from, and nothing ever reads it.
 *
 * Sites behind a proxy that normalizes the header, and multilingual plugins that hold a
 * better signal in a cookie or a URL prefix, can supply the tag through the filter below.
 * A filtered value goes through the same shape and the same trimming, so a hook can neither
 * put arbitrary text in the entry nor store more of a tag than the header path would.
 *
 * @since 2.0.2
 *
 * @return string Lowercase language tag, or an empty string when the request carries none.
 */
function wpforms_get_visitor_language(): string {

	// A tag is a two or three letter language, an optional four-letter script and an
	// optional region: letters like `BR`, or the UN M49 digits browsers send for `es-419`.
	// Any further subtag still matches, so the tag is recognised, but is not captured.
	$tag_pattern = '([a-z]{2,3})(?:-([a-z]{4}))?(?:-([a-z]{2}|\d{3}))?(?:-[a-z\d]{1,8})*';

	// Join the captured parts back into the tag that gets stored.
	$to_tag = static function ( array $parts ): string {
		return strtolower( implode( '-', array_filter( [ $parts[1] ?? '', $parts[2] ?? '', $parts[3] ?? '' ] ) ) );
	};

	$language  = '';
	$preferred = 0;

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each tag is matched against the pattern above.
	$header = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? (string) wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) : '';

	// The header is attacker-controlled and the winning tag is stored with the entry, so
	// it is capped like the user agent (see WPForms\Forms\Submission::get_user_info()).
	foreach ( explode( ',', substr( $header, 0, 256 ) ) as $entry ) {
		// Anything else, including the `*` wildcard, is skipped. The spacing is part of the
		// pattern because the header may arrive as `en-US; q=0.8`.
		if ( ! preg_match( '/^\s*' . $tag_pattern . '\s*(?:;\s*q=([\d.]+))?\s*$/i', $entry, $parts ) ) {
			continue;
		}

		// An explicit `q=0` means "not acceptable", so it never wins.
		$quality = isset( $parts[4] ) && $parts[4] !== '' ? (float) $parts[4] : 1;

		if ( $quality > $preferred ) {
			$language  = $to_tag( $parts );
			$preferred = $quality;
		}
	}

	/**
	 * Filter the visitor language tag read from the request.
	 *
	 * Lets a site read the language from somewhere better than the header: a proxy that
	 * normalizes it away, or a multilingual plugin holding it in a cookie or URL prefix.
	 *
	 * @since 2.0.2
	 *
	 * @param string $language Lowercase language tag, or an empty string when the request carries none.
	 */
	$language = (string) apply_filters( 'wpforms_get_visitor_language', $language );

	return preg_match( '/^' . $tag_pattern . '$/i', $language, $parts ) ? $to_tag( $parts ) : '';
}

/**
 * Determine if collecting user's IP is allowed by GDPR setting (globally or per form).
 * Majority of our users have GDPR disabled.
 * So we remove this data from the request only when it's not needed:
 * 1) when GDPR is enabled AND globally disabled user details storage;
 * 2) when GDPR is enabled AND IP address processing is disabled on per form basis.
 *
 * @since 1.6.6
 *
 * @param array $form_data Form settings.
 *
 * @return bool
 */
function wpforms_is_collecting_ip_allowed( $form_data = [] ) {

	if (
		wpforms_setting( 'gdpr', false ) &&
		(
			wpforms_setting( 'gdpr-disable-details', false ) ||
			( ! empty( $form_data ) && ! empty( $form_data['settings']['disable_ip'] ) )
		)
	) {
		return false;
	}

	return true;
}

/**
 * Determine if collecting cookies is allowed by GDPR setting.
 *
 * @since 1.7.5
 *
 * @return bool
 */
function wpforms_is_collecting_cookies_allowed() {

	return ! ( wpforms_setting( 'gdpr', false ) && wpforms_setting( 'gdpr-disable-uuid', false ) );
}
