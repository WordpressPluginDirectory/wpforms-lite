<?php

namespace WPForms\Admin\Builder;

/**
 * Addons class.
 *
 * @since 1.9.2
 */
class Addons {

	/**
	 * List of addon options.
	 *
	 * @since 1.9.2
	 */
	const FIELD_OPTIONS = [
		'calculations'  => [
			'calculation_code',
			'calculation_code_js',
			'calculation_code_php',
			'calculation_is_enabled',
		],
		'form-locker'   => [
			'unique_answer',
		],
		'geolocation'   => [
			'display_map',
			'enable_address_autocomplete',
			'map_position',
		],
		'surveys-polls' => [
			'survey',
		],
	];

	/**
	 * Field options for disabled addons.
	 *
	 * @since 1.9.2
	 *
	 * @var array
	 */
	private $disabled_field_options = [];

	/**
	 * Initialize.
	 *
	 * @since 1.9.2
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Get a list of fields options added by disabled addons.
	 *
	 * @since 1.9.2
	 *
	 * @return array
	 */
	private function get_disabled_field_options(): array {

		$disabled_field_options = [];

		foreach ( self::FIELD_OPTIONS as $addon_slug => $addon_fields ) {
			if ( wpforms_is_addon_initialized( $addon_slug ) ) {
				continue;
			}

			$disabled_field_options[] = $addon_fields;
		}

		if ( empty( $disabled_field_options ) ) {
			return [];
		}

		return array_merge( ...$disabled_field_options );
	}

	/**
	 * Add hooks.
	 *
	 * @since 1.9.2
	 */
	private function hooks() {

		add_filter( 'wpforms_save_form_args', [ $this, 'save_disabled_addons_options' ], 10, 3 );
	}


	/**
	 * Field's options added by an addon can be deleted when the addon is deactivated or have incompatible status.
	 * The options are fully controlled by the addon when addon is active and compatible.
	 *
	 * @since 1.9.2
	 *
	 * @param array|mixed $post_data Post data.
	 *
	 * @return array
	 */
	public function save_disabled_addons_options( $post_data ): array {

		$post_data    = (array) $post_data;
		$post_content = wpforms_decode( wp_unslash( $post_data['post_content'] ?? '' ) );
		$form_obj     = wpforms()->obj( 'form' );

		if ( ! $form_obj || empty( $post_content['id'] ) ) {
			return $post_data;
		}

		$previous_form_data = $form_obj->get( $post_content['id'], [ 'content_only' => true ] );

		if ( empty( $previous_form_data ) ) {
			return $post_data;
		}

		$post_content = $this->preserve_fields( $post_content, $previous_form_data );
		$post_content = $this->preserve_providers( $post_content, $previous_form_data );

		$post_data['post_content'] = wpforms_encode( $post_content );

		return $post_data;
	}

	/**
	 * Preserve fields data from inactive addons.
	 *
	 * @since 1.9.3
	 *
	 * @param array $form_data          Form data.
	 * @param array $previous_form_data Previous form data.
	 *
	 * @return array
	 */
	private function preserve_fields( array $form_data, array $previous_form_data ): array {

		if ( empty( $form_data['fields'] ) ) {
			return $form_data;
		}

		$this->disabled_field_options = $this->get_disabled_field_options();
		$previous_fields              = $previous_form_data['fields'] ?? [];

		if ( empty( $this->disabled_field_options ) || empty( $previous_fields ) ) {
			return $form_data;
		}

		foreach ( $form_data['fields'] as $field_id => $new_field ) {
			if ( empty( $previous_fields[ $field_id ] ) ) {
				continue;
			}

			$form_data['fields'][ $field_id ] =
				$this->add_disabled_addons_options_field( (array) $new_field, (array) $previous_fields[ $field_id ] );
		}

		return $form_data;
	}

	/**
	 * Preserve providers that are not active.
	 *
	 * @since 1.9.3
	 *
	 * @param array $form_data          Form data.
	 * @param array $previous_form_data Previous form data.
	 *
	 * @return array
	 */
	private function preserve_providers( array $form_data, array $previous_form_data ): array {

		$previous_providers = $previous_form_data['providers'] ?? [];
		$active_providers   = wpforms_get_providers_available();

		foreach ( $previous_providers as $provider_id => $provider ) {
			if ( ! empty( $active_providers[ $provider_id ] ) ) {
				continue;
			}

			$form_data['providers'][ $provider_id ] = $provider;
		}

		return $form_data;
	}

	/**
	 * Add disabled addons options to the field.
	 *
	 * @since 1.9.2
	 *
	 * @param array $new_field Updated field data.
	 * @param array $old_field Old field data.
	 *
	 * @return array
	 */
	private function add_disabled_addons_options_field( array $new_field, array $old_field ): array {

		foreach ( $this->disabled_field_options as $option ) {
			if ( isset( $old_field[ $option ] ) ) {
				$new_field[ $option ] = $old_field[ $option ];
			}
		}

		return $new_field;
	}
}
