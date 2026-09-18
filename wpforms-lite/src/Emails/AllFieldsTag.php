<?php

namespace WPForms\Emails;

/**
 * The {all_fields} smart tag parameters.
 *
 * The tag is not a registered smart tag: mailers replace it literally after
 * regular smart tags are processed. This class centralizes recognizing the
 * tag with optional parameters and deciding which fields its parameters
 * exclude from the rendered output.
 *
 * @since 2.0.2
 */
class AllFieldsTag {

	/**
	 * Pattern matching the {all_fields} tag with optional parameters.
	 *
	 * Mirrors the smart tag recognition regex in wpforms_get_all_smart_tags(),
	 * so any {all_fields ...} variant the tag parser captures is replaced here
	 * instead of leaking verbatim into the sent message.
	 *
	 * @since 2.0.2
	 */
	const PATTERN = '~\{all_fields([ =][^\n}]*)?\}~';

	/**
	 * Parse tag parameters into exclusion options.
	 *
	 * The `exclude` parameter accepts a comma-separated list of field IDs
	 * and/or the keyword `hidden`, which excludes all Hidden-type fields.
	 * The value may be quoted or bare. Unknown tokens are ignored.
	 *
	 * @since 2.0.2
	 *
	 * @param string $params Parameters part of the tag.
	 *
	 * @return array Empty array when nothing is excluded, otherwise [ 'ids' => int[], 'hidden' => bool ].
	 */
	public static function parse( string $params ): array {

		if ( ! preg_match( '/(?:^|\s)exclude=(?:(["\'])(.+?)\1|([^\s"\'}]+))/', $params, $matches ) ) {
			return [];
		}

		$options = [
			'ids'    => [],
			'hidden' => false,
		];

		foreach ( explode( ',', $matches[3] ?? $matches[2] ) as $token ) {
			$token = strtolower( trim( $token ) );

			if ( $token === 'hidden' ) {
				$options['hidden'] = true;

				continue;
			}

			// Plain field IDs and repeater instance IDs like `7_2`, which absint() maps to the base field ID.
			if ( preg_match( '/^\d+(?:_\d+)?$/', $token ) ) {
				$options['ids'][] = absint( $token );
			}
		}

		$options['ids'] = array_values( array_unique( $options['ids'] ) );

		return $options['ids'] || $options['hidden'] ? $options : [];
	}

	/**
	 * Expand excluded container IDs with the IDs of the fields inside them.
	 *
	 * Excluding a Layout or Repeater field excludes its child fields as well,
	 * regardless of whether a mailer renders them via the container or as
	 * flat top-level rows.
	 *
	 * @since 2.0.2
	 *
	 * @param array $options   Options returned by self::parse().
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	public static function expand( array $options, array $form_data ): array {

		if ( empty( $options['ids'] ) || empty( $form_data['fields'] ) ) {
			return $options;
		}

		foreach ( (array) $form_data['fields'] as $field ) {
			if (
				! is_array( $field ) ||
				! in_array( $field['type'] ?? '', [ 'layout', 'repeater' ], true ) ||
				! in_array( absint( $field['id'] ?? 0 ), $options['ids'], true )
			) {
				continue;
			}

			foreach ( (array) ( $field['columns'] ?? [] ) as $column ) {
				foreach ( (array) ( $column['fields'] ?? [] ) as $child_id ) {
					$options['ids'][] = absint( $child_id );
				}
			}
		}

		$options['ids'] = array_values( array_unique( $options['ids'] ) );

		return $options;
	}

	/**
	 * Replace every {all_fields} tag in a text, honoring its parameters.
	 *
	 * Identical option sets are rendered once per text.
	 *
	 * @since 2.0.2
	 *
	 * @param string   $text   Text to process.
	 * @param callable $render Renderer receiving options returned by self::parse(), returning the replacement.
	 *
	 * @return string
	 */
	public static function replace( string $text, callable $render ): string {

		$cache = [];

		return (string) preg_replace_callback(
			self::PATTERN,
			static function ( $matches ) use ( &$cache, $render ) {

				$options   = self::parse( $matches[1] ?? '' );
				$cache_key = (string) wp_json_encode( $options );

				if ( ! isset( $cache[ $cache_key ] ) ) {
					$cache[ $cache_key ] = (string) $render( $options );
				}

				return $cache[ $cache_key ];
			},
			$text
		);
	}

	/**
	 * Whether a field is excluded by the given options.
	 *
	 * @since 2.0.2
	 *
	 * @param array $field   Field data.
	 * @param array $options Options returned by self::parse().
	 *
	 * @return bool
	 */
	public static function is_excluded( array $field, array $options ): bool {

		if ( ! $options ) {
			return false;
		}

		if ( ! empty( $options['hidden'] ) && ( $field['type'] ?? '' ) === 'hidden' ) {
			return true;
		}

		// Repeater instances carry derived IDs like `7_2`; absint() maps them to the base field ID.
		return in_array( absint( $field['id'] ?? 0 ), $options['ids'] ?? [], true );
	}
}
