<?php

namespace WPForms\Integrations\Abilities;

/**
 * Safe form-settings whitelist for the Abilities API writers.
 *
 * @since 1.10.2
 */
class SettingsRegistry {

	/**
	 * Whitelisted settings: key => { sanitize, description }.
	 *
	 * @since 1.10.2
	 *
	 * @return array
	 */
	private function settings(): array {

		return [
			'form_title'  => [
				'sanitize'    => 'sanitize_text_field',
				'description' => __( 'The form title.', 'wpforms-lite' ),
			],
			'form_desc'   => [
				'sanitize'    => 'wp_kses_post',
				'description' => __( 'The form description.', 'wpforms-lite' ),
			],
			'submit_text' => [
				'sanitize'    => 'sanitize_text_field',
				'description' => __( 'The submit button label.', 'wpforms-lite' ),
			],
		];
	}

	/**
	 * Sanitize an incoming partial settings array.
	 *
	 * @since 1.10.2
	 *
	 * @param array $input Raw settings input.
	 *
	 * @return array Sanitized whitelisted values plus the list of ignored keys.
	 */
	public function sanitize( array $input ): array {

		$allowed   = $this->settings();
		$sanitized = [];
		$ignored   = [];

		foreach ( $input as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				$ignored[] = $key;

				continue;
			}

			$sanitized[ $key ] = call_user_func( $allowed[ $key ]['sanitize'], $value );
		}

		return [
			'sanitized' => $sanitized,
			'ignored'   => $ignored,
		];
	}

	/**
	 * JSON schema `properties` map for the whitelisted settings.
	 *
	 * @since 1.10.2
	 *
	 * @return array
	 */
	public function input_properties_schema(): array {

		$properties = [];

		foreach ( array_keys( $this->settings() ) as $key ) {
			$properties[ $key ] = [ 'type' => 'string' ];
		}

		return $properties;
	}

	/**
	 * Describe the editable settings for the discovery ability.
	 *
	 * @since 1.10.2
	 *
	 * @return array
	 */
	public function describe(): array {

		$result = [];

		foreach ( $this->settings() as $key => $def ) {
			$result[] = [
				'key'         => $key,
				'schema'      => [ 'type' => 'string' ],
				'description' => $def['description'],
			];
		}

		return $result;
	}
}
