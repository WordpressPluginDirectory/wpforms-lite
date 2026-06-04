<?php
/**
 * Helper functions to clean and sanitize data, escape it and prepare the output.
 *
 * @since 1.8.0
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

use WPForms\Helpers\Templates;
use WPForms\Vendor\HTMLPurifier;
use WPForms\Vendor\HTMLPurifier_Config;
use WPForms\Helpers\File;
use WPForms\Vendor\enshrined\svgSanitize\Sanitizer;

/**
 * Decode special characters, both alpha- (<) and numeric-based (').
 * Sanitize recursively, preserve new lines.
 * Handle all the possible mixed variations of < and `&lt;` that can be processed into tags.
 *
 * @since 1.4.1
 * @since 1.6.0 Sanitize recursively, preserve new lines.
 *
 * @param string $string Raw string to decode.
 *
 * @return string
 */
function wpforms_decode_string( $string ) {

	if ( ! is_string( $string ) ) {
		return $string;
	}

	/*
	 * Sanitization should be done first, so tags are stripped and < is converted to &lt; etc.
	 * This iteration may do nothing when the string already comes with &lt; and &gt; only.
	 */
	$string = wpforms_sanitize_text_deeply( $string, true );

	// Now we need to convert the string without tags: &lt; back to < (same for quotes).
	$string = wp_kses_decode_entities( html_entity_decode( $string, ENT_QUOTES ) );

	// And now we need to sanitize AGAIN, to avoid unwanted tags that appeared after decoding.
	return wpforms_sanitize_text_deeply( $string, true );
}

/**
 * Sanitize key, primarily used for looking up options.
 *
 * @since 1.3.9
 *
 * @param string $key Key name.
 *
 * @return string
 */
function wpforms_sanitize_key( $key = '' ) {

	return preg_replace( '/[^a-zA-Z0-9_\-\.\:\/]/', '', $key );
}

/**
 * Sanitize hex color.
 *
 * @since 1.2.1
 *
 * @param string $color Color value.
 *
 * @return string
 */
function wpforms_sanitize_hex_color( $color ) {

	if ( empty( $color ) ) {
		return '';
	}

	// 3 or 6 hex digits, or the empty string.
	if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) {
		return $color;
	}

	return '';
}

/**
 * Sanitize error message, primarily used during form frontend output.
 *
 * @since 1.3.7
 * @since 1.7.6 Expand list of allowed HTML tags and attributes.
 *
 * @param string $error Error message.
 *
 * @return string
 */
function wpforms_sanitize_error( $error = '' ) {

	$allow = [
		'a'          => [
			'href'   => [],
			'title'  => [],
			'target' => [],
			'rel'    => [],
		],
		'br'         => [],
		'em'         => [],
		'strong'     => [],
		'del'        => [],
		'p'          => [
			'style' => [],
		],
		'blockquote' => [],
		'ul'         => [],
		'ol'         => [],
		'li'         => [],
		'span'       => [
			'style' => [],
		],
	];

	return wp_kses( $error, $allow );
}

/**
 * Sanitize a string, that can be a multiline.
 *
 * @uses wpforms_sanitize_text_deeply()
 *
 * @since 1.4.1
 *
 * @param string $string String to deeply sanitize.
 *
 * @return string Sanitized string, or empty string if not a string provided.
 */
function wpforms_sanitize_textarea_field( $string ) {

	return wpforms_sanitize_text_deeply( $string, true );
}

/**
 * Deeply sanitize the string, preserve newlines if needed.
 * Prevent maliciously prepared strings from containing HTML tags.
 *
 * @since 1.6.0
 *
 * @param string $string        String to deeply sanitize.
 * @param bool   $keep_newlines Whether to keep newlines. Default: false.
 *
 * @return string Sanitized string, or empty string if not a string provided.
 */
function wpforms_sanitize_text_deeply( $string, $keep_newlines = false ) {

	if ( is_object( $string ) || is_array( $string ) ) {
		return '';
	}

	$string        = (string) $string;
	$keep_newlines = (bool) $keep_newlines;

	$new_value = _sanitize_text_fields( $string, $keep_newlines );

	if ( strlen( $new_value ) !== strlen( $string ) ) {
		$new_value = wpforms_sanitize_text_deeply( $new_value, $keep_newlines );
	}

	return $new_value;
}

/**
 * Sanitize an HTML string with a set of allowed HTML tags.
 *
 * @since 1.7.0
 *
 * @param string $value String to sanitize.
 *
 * @return string Sanitized string.
 */
function wpforms_sanitize_richtext_field( $value ) {

	$count = 1;
	$value = convert_invalid_entities( $value );

	// Remove 'script' and 'style' tags recursively.
	while ( $count ) {
		$value = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $value, - 1, $count );
	}

	// Make sure we have allowed tags only.
	$value = wp_kses( $value, wpforms_get_allowed_html_tags_for_richtext_field() );

	// Make sure that all tags are balanced.
	return force_balance_tags( $value );
}

/**
 * Escaping for Rich Text field values.
 *
 * @since 1.7.0
 * @since 1.9.1 Removed new lines after adding paragraphs and breaks tags.
 *
 * @param string $value Text to escape.
 *
 * @return string Escaped text.
 */
function wpforms_esc_richtext_field( $value ) {

	$value = wpautop( wpforms_sanitize_richtext_field( $value ) );

	return trim( str_replace( [ "\r\n", "\r", "\n" ], '', $value ) );
}

/**
 * Retrieve allowed HTML tags for Rich Text field.
 *
 * @since 1.7.0
 *
 * @return array Array of allowed tags.
 */
function wpforms_get_allowed_html_tags_for_richtext_field() {

	$allowed_tags = array_fill_keys(
		[
			'img',
			'h1',
			'h2',
			'h3',
			'h4',
			'h5',
			'h6',
			'p',
			'a',
			'ul',
			'ol',
			'li',
			'dl',
			'dt',
			'dd',
			'hr',
			'br',
			'code',
			'pre',
			'strong',
			'b',
			'em',
			'i',
			'blockquote',
			'cite',
			'q',
			'del',
			'span',
			'small',
			'table',
			'thead',
			'tbody',
			'tfoot',
			'th',
			'tr',
			'td',
			'abbr',
			'address',
			'sub',
			'sup',
			'ins',
			'figure',
			'figcaption',
			'caption',
			'div',
		],
		array_fill_keys(
			[ 'align', 'class', 'id', 'style', 'src', 'rel', 'alt', 'href', 'target', 'width', 'height', 'title', 'cite', 'start', 'reversed', 'datetime', 'scope', 'colspan', 'rowspan' ],
			[]
		)
	);

	/**
	 * Allowed HTML tags for Rich Text field.
	 *
	 * @since 1.7.0
	 *
	 * @param array $allowed_tags Allowed HTML tags.
	 */
	$tags = (array) apply_filters( 'wpforms_get_allowed_html_tags_for_richtext_field', $allowed_tags );

	// Force unset iframes, script and style no matter when we get back
	// from apply_filters, as they are a huge security risk.
	unset( $tags['iframe'], $tags['script'], $tags['style'] );

	return $tags;
}

/**
 * Sanitize an array, that consists of values as strings.
 * After that - merge all array values into multiline string.
 *
 * @since 1.4.1
 *
 * @param array $array Data to sanitize.
 *
 * @return mixed If not an array is passed (or empty var) - return unmodified var.
 *               Otherwise - a merged array into multiline string.
 */
function wpforms_sanitize_array_combine( $array ) {

	if ( empty( $array ) || ! is_array( $array ) ) {
		return $array;
	}

	return implode( "\n", array_map( 'sanitize_text_field', $array ) );
}

/**
 * Format, sanitize, and return/echo HTML element ID, classes, attributes,
 * and data attributes.
 *
 * @since 1.3.7
 *
 * @param string $id    HTML id attribute value.
 * @param array  $class A list of classnames for the class attribute.
 * @param array  $datas Data attributes.
 * @param array  $atts  Any additional HTML attributes and their values.
 * @param bool   $echo  Whether to echo the output or just return it. Defaults to return.
 *
 * @return string|void
 */
function wpforms_html_attributes( $id = '', $class = [], $datas = [], $atts = [], $echo = false ) {

	$id    = trim( $id );
	$parts = [];

	if ( ! empty( $id ) ) {
		$id = sanitize_html_class( $id );

		if ( ! empty( $id ) ) {
			$parts[] = 'id="' . $id . '"';
		}
	}

	if ( ! empty( $class ) ) {
		$class = wpforms_sanitize_classes( $class, true );

		if ( ! empty( $class ) ) {
			$parts[] = 'class="' . $class . '"';
		}
	}

	if ( ! empty( $datas ) ) {
		foreach ( $datas as $data => $val ) {
			$parts[] = 'data-' . sanitize_html_class( $data ) . '="' . esc_attr( $val ) . '"';
		}
	}

	if ( ! empty( $atts ) ) {
		foreach ( $atts as $att => $val ) {
			if ( '0' === (string) $val || ! empty( $val ) ) {
				if ( $att[0] === '[' ) {
					// Handle special case for bound attributes in AMP.
					$escaped_att = '[' . sanitize_html_class( trim( $att, '[]' ) ) . ']';
				} else {
					$escaped_att = sanitize_html_class( $att );
				}
				$parts[] = $escaped_att . '="' . esc_attr( $val ) . '"';
			}
		}
	}

	$output = implode( ' ', $parts );

	if ( $echo ) {
		echo trim( $output ); // phpcs:ignore
	} else {
		return trim( $output );
	}
}

/**
 * Sanitize string of CSS classes.
 *
 * @since 1.2.1
 *
 * @param array|string $classes CSS classes.
 * @param bool         $convert True will convert strings to array and vice versa.
 *
 * @return string|array
 */
function wpforms_sanitize_classes( $classes, $convert = false ) {

	$array = is_array( $classes );
	$css   = [];

	if ( ! empty( $classes ) ) {
		if ( ! $array ) {
			$classes = explode( ' ', trim( $classes ) );
		}
		foreach ( array_unique( $classes ) as $class ) {
			if ( ! empty( $class ) ) {
				$css[] = sanitize_html_class( $class );
			}
		}
	}

	if ( $array ) {
		return $convert ? implode( ' ', $css ) : $css;
	}

	return $convert ? $css : implode( ' ', $css );
}

/**
 * Include a template - alias to \WPForms\Helpers\Template::get_html.
 * Use 'require' if $args are passed or 'load_template' if not.
 *
 * @since 1.5.6
 *
 * @param string $template_name Template name.
 * @param array  $args          Arguments.
 * @param bool   $extract       Extract arguments.
 *
 * @throws RuntimeException If extract() tries to modify the scope.
 *
 * @return string Compiled HTML.
 */
function wpforms_render( $template_name, $args = [], $extract = false ) {

	return Templates::get_html( $template_name, $args, $extract );
}

/**
 * Alias for default readonly function.
 *
 * @since 1.6.9
 *
 * @param mixed $readonly One of the values to compare.
 * @param mixed $current  The other value to compare if not just true.
 * @param bool  $echo     Whether to echo or just return the string.
 *
 * @return string HTML attribute or empty string.
 */
function wpforms_readonly( $readonly, $current = true, $echo = true ) {

	if ( function_exists( 'wp_readonly' ) ) {
		return wp_readonly( $readonly, $current, $echo );
	}

	return __checked_selected_helper( $readonly, $current, $echo, 'readonly' );
}

/**
 * Get the required label text, with a filter.
 *
 * @since 1.4.4
 *
 * @return string
 */
function wpforms_get_required_label() {

	return apply_filters( 'wpforms_required_label', esc_html__( 'This field is required.', 'wpforms-lite' ) );
}

/**
 * Get the required field label HTML, with a filter.
 *
 * @since 1.4.8
 *
 * @return string
 */
function wpforms_get_field_required_label() {

	$label_html = apply_filters_deprecated(
		'wpforms_field_required_label',
		[ ' <span class="wpforms-required-label">*</span>' ],
		'1.4.8 of the WPForms plugin',
		'wpforms_get_field_required_label'
	);

	return apply_filters( 'wpforms_get_field_required_label', $label_html );
}

/**
 * Escape unselected choices for radio/checkbox fields.
 *
 * @since 1.8.3
 *
 * @param string $formatted_field HTML field.
 *
 * @return string
 */
function wpforms_esc_unselected_choices( $formatted_field ) {

	$allowed_html = wp_kses_allowed_html( 'post' );

	$allowed_html['input'] = [
		'type'     => [],
		'disabled' => [],
		'checked'  => [],
		'class'    => [],
		'value'    => [],
	];
	$allowed_html['label'] = [];

	return wp_kses( $formatted_field, $allowed_html );
}

/**
 * Decode HTML entities in a string.
 * Do it cycle to decode all possible entities, including cases like `&amp;lt;`.
 *
 * @since 1.9.2.3
 *
 * @param string      $html     HTML.
 * @param int         $flags    Flags.
 * @param string|null $encoding Encoding.
 *
 * @return string
 * @noinspection PhpMissingParamTypeInspection
 */
function wpforms_html_entity_decode_deep( string $html, int $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, $encoding = null ): string {

	do {
		$previous_html = $html;
		$html          = html_entity_decode( $html, $flags, $encoding );
	} while ( $html !== $previous_html );

	return $html;
}

/**
 * Sanitize form data.
 *
 * @since 1.9.3
 *
 * @param array $data Form data.
 *
 * @return array
 */
function wpforms_sanitize_form_data( array $data ): array {

	foreach ( $data['fields'] as & $field ) {
		$field = wpforms_sanitize_field( $field );
	}

	unset( $field );

	return $data;
}

/**
 * Sanitize form field.
 *
 * @since 1.9.3
 *
 * @param array $field Field.
 *
 * @return array
 */
function wpforms_sanitize_field( array $field ): array {

	$raw_field_options = [
		'html' => [ 'code' ],
	];

	$field_type  = $field['type'] ?? '';
	$raw_options = $raw_field_options[ $field_type ] ?? [];

	/**
	 * Filter raw options for a field type.
	 * Allows modifying options that should not be sanitized.
	 *
	 * @since 1.9.3
	 *
	 * @param array  $raw_options Raw options.
	 * @param string $field_type  Field type.
	 */
	$raw_options = (array) apply_filters( 'wpforms_raw_options', $raw_options, $field_type );

	$purifier          = wpforms_get_html_purifier();
	$decode_and_purify = static function ( $item ) use ( $purifier ) {
		return $purifier->purify( wpforms_html_entity_decode_deep( $item ) );
	};

	foreach ( $field as $option => & $value ) {
		if ( in_array( $option, $raw_options, true ) ) {
			continue;
		}

		$value = wp_unslash( $value );

		if ( is_array( $value ) ) {
			array_walk_recursive( $value, $decode_and_purify );
		} else {
			$value = $decode_and_purify( $value );
		}

		$value = wp_slash( $value );
	}

	unset( $value );

	return $field;
}

/**
 * Get allowed HTML purifier object.
 *
 * @since 1.9.3
 *
 * @return HTMLPurifier
 */
function wpforms_get_html_purifier(): HTMLPurifier {

	static $purifier;

	if ( $purifier ) {
		return $purifier;
	}

	require_once WPFORMS_PLUGIN_DIR . '/vendor_prefixed/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';

	$config    = HTMLPurifier_Config::createDefault();
	$cache_dir = trailingslashit( File::get_cache_dir() ) . 'htmlpurifier';

	// Make sure the cache directory exists.
	File::mkdir( $cache_dir );

	$config->set( 'Cache.SerializerPath', $cache_dir );
	$config->set( 'Attr.AllowedRel', 'noopener,noreferrer,external,follow,nofollow,ugc,sponsored,tag' );
	$config->set( 'Attr.AllowedFrameTargets', [ '_blank', '_self', '_parent', '_top' ] );
	$config->set( 'HTML.TargetNoopener', false );
	$config->set( 'HTML.TargetNoreferrer', false );

	$purifier = new HTMLPurifier( $config );

	return $purifier;
}

/**
 * Sanitize an SVG file in place, stripping scripts, event handlers and other
 * potentially malicious markup.
 *
 * Non-SVG files are left untouched. Sites that explicitly enable SVG support
 * would otherwise store SVG uploads unsanitized, allowing stored XSS. To stay
 * safe by default, this function fails closed: when the SVG sanitizer is
 * unavailable or sanitization fails, it returns false so the caller can reject
 * the file rather than store it unsanitized.
 *
 * Gzipped SVG variants (e.g. .svgz) cannot be parsed as plain XML, so they fail
 * sanitization and are rejected rather than stored unsanitized.
 *
 * @since 1.10.1.1
 *
 * @param string $file_path Absolute path to the file to sanitize.
 * @param string $file_name Optional. Original file name used to determine the extension when
 *                          the path itself has none (e.g. a PHP upload temp file). Default ''.
 *
 * @return bool True if the file is not an SVG or was sanitized successfully, false on failure.
 */
function wpforms_sanitize_svg_file( string $file_path, string $file_name = '' ): bool {

	if ( ! is_file( $file_path ) ) {
		return false;
	}

	// Derive the extension from the original name when provided, otherwise from the path.
	$name_for_ext = $file_name !== '' ? $file_name : $file_path;
	$extension    = strtolower( pathinfo( $name_for_ext, PATHINFO_EXTENSION ) );

	// Bail early for anything that is not an SVG. Nothing to sanitize.
	if ( ! in_array( $extension, [ 'svg', 'svgz' ], true ) ) {
		return true;
	}

	$contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	if ( empty( $contents ) ) {
		return false;
	}

	$sanitized = wpforms_sanitize_svg_markup( $contents );

	if ( $sanitized === false ) {
		return false;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions
	return file_put_contents( $file_path, $sanitized ) !== false;
}

/**
 * Sanitize an SVG markup string, stripping scripts, event handlers and other
 * potentially malicious markup.
 *
 * Fails closed: returns false when the sanitizer library is unavailable or when
 * the markup cannot be sanitized (e.g. it is not valid SVG XML).
 *
 * @since 1.10.1.1
 *
 * @param string $markup SVG markup to sanitize.
 *
 * @return string|false Sanitized markup, or false on failure.
 */
function wpforms_sanitize_svg_markup( string $markup ) {

	// Fail closed if the sanitizer library is not available.
	if ( ! class_exists( Sanitizer::class ) ) {
		return false;
	}

	$sanitizer = new Sanitizer();

	// Block references to remote resources to prevent SSRF and data exfiltration.
	$sanitizer->removeRemoteReferences( true );

	$sanitized = $sanitizer->sanitize( $markup );

	if ( $sanitized === false ) {
		return false;
	}

	// The sanitizer library only strips remote references wrapped in quotes, e.g. url( 'http://...' ).
	// Bare references such as fill="url(http://...)" slip through, so strip remote url() targets here.
	// Local references like url(#gradient) and data: URIs have no // and are intentionally left intact.
	$sanitized = (string) preg_replace(
		'~url\(\s*[\'"]?\s*(?:(?:https?|ftp|file):)?//[^)]*\)~i',
		'url()',
		$sanitized
	);

	// The library's removeRemoteReferences() only matches url()-wrapped values, and its href allow-list
	// explicitly permits bare http(s) URLs. So elements like <image>, <pattern> and <marker> keep a remote
	// href/xlink:href and silently load the resource (tracking pixel risk) when the file is opened. Strip
	// any href/xlink:href whose value is a remote reference (scheme://... or protocol-relative //...).
	// Local fragment references (#id), relative paths (/path) and data: URIs have no // and are kept intact.
	return (string) preg_replace(
		'~\s(?:xlink:)?href\s*=\s*([\'"])\s*(?:(?:https?|ftp|file):)?//.*?\1~is',
		'',
		$sanitized
	);
}
