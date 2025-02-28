<?php

namespace WPForms\Admin\Builder;

use WPForms\Requirements\Requirements;

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
		$post_content = json_decode( wp_unslash( $post_data['post_content'] ?? '' ), true );
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
		$post_content = $this->preserve_payments( $post_content, $previous_form_data );
		$post_content = $this->preserve_settings( $post_content, $previous_form_data );

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
	 * Preserve Providers that are not active.
	 *
	 * @since 1.9.3
	 *
	 * @param array $form_data          Form data.
	 * @param array $previous_form_data Previous form data.
	 *
	 * @return array
	 */
	private function preserve_providers( array $form_data, array $previous_form_data ): array {

		if ( empty( $previous_form_data['providers'] ) ) {
			return $form_data;
		}

		$active_providers = wpforms_get_providers_available();

		foreach ( $previous_form_data['providers'] as $provider_id => $provider ) {
			if ( ! empty( $active_providers[ $provider_id ] ) ) {
				continue;
			}

			$form_data['providers'][ $provider_id ] = $provider;
		}

		return $form_data;
	}

	/**
	 * Preserve Payments providers that are not active.
	 *
	 * @since 1.9.4
	 *
	 * @param array $form_data          Form data.
	 * @param array $previous_form_data Previous form data.
	 *
	 * @return array
	 */
	private function preserve_payments( array $form_data, array $previous_form_data ): array {

		if ( empty( $previous_form_data['payments'] ) ) {
			return $form_data;
		}

		foreach ( $previous_form_data['payments'] as $slug => $value ) {
			if ( ! empty( $form_data['payments'][ $slug ] ) ) {
				continue;
			}

			$form_data['payments'][ $slug ] = $value;
		}

		return $form_data;
	}

	/**
	 * Preserve addon notifications.
	 *
	 * @since 1.9.4
	 *
	 * @param string $slug                   Addon slug.
	 * @param array  $new_notifications      List of form notifications.
	 * @param array  $previous_notifications Previously saved list of form notifications.
	 *
	 * @return void
	 */
	private function preserve_addon_notifications( string $slug, array &$new_notifications, array $previous_notifications ): void {

		$prefix = $this->prepare_prefix( $slug );

		foreach ( $previous_notifications as $notification_id => $notification_settings ) {
			if ( empty( $new_notifications[ $notification_id ] ) ) {
				continue;
			}

			$changed_notifications = array_diff_key(
				$notification_settings,
				$new_notifications[ $notification_id ]
			);

			foreach ( $changed_notifications as $setting_name => $value ) {
				if ( strpos( $setting_name, $prefix ) === 0 ) {
					$new_notifications[ $notification_id ][ $setting_name ] = $value;
				}
			}
		}
	}

	/**
	 * Preserve settings of not active addons from the Settings tab.
	 *
	 * @since 1.9.4
	 *
	 * @param array $form_data          Form data.
	 * @param array $previous_form_data Previous form data.
	 *
	 * @return array
	 */
	public function preserve_settings( array $form_data, array $previous_form_data ): array {

		$requirements         = Requirements::get_instance();
		$not_validated_addons = $requirements->get_not_validated_addons();

		foreach ( $not_validated_addons as $path ) {
			$slug  = str_replace( 'wpforms-', '', basename( $path, '.php' ) );
			$panel = $this->prepare_prefix( $slug );

			// The addon settings stored its own panel, e.g., $form_data[lead_forms], $form_data[webhooks], etc.
			if ( ! empty( $previous_form_data[ $panel ] ) ) {
				$form_data[ $panel ] = $previous_form_data[ $panel ];

				continue;
			}

			if ( empty( $previous_form_data['settings'] ) ) {
				continue;
			}

			$this->preserve_addon_settings( $panel, $form_data, $previous_form_data );

			if ( ! empty( $form_data['settings']['notifications'] ) && ! empty( $previous_form_data['settings']['notifications'] ) ) {
				$this->preserve_addon_notifications( $slug, $form_data['settings']['notifications'], $previous_form_data['settings']['notifications'] );
			}
		}

		return $form_data;
	}

	/**
	 * Preserve addon settings stored inside the settings panel with a specific prefix.
	 * e.g. $form_data[settings][{$prefix}_enabled], $form_data[settings][{$prefix}_email], etc.
	 *
	 * @since 1.9.4
	 *
	 * @param string $prefix             Addon option prefix.
	 * @param array  $form_data          Form data.
	 * @param array  $previous_form_data Previous form data.
	 */
	private function preserve_addon_settings( string $prefix, array &$form_data, array $previous_form_data ): void {

		static $legacy_options = [
			'offline_forms'     => [ 'offline_form' ],
			'user_registration' => [ 'user_login_hide', 'user_reset_hide' ],
			'surveys_polls'     => [ 'survey_enable', 'poll_enable' ],
		];

		// BC: User Registration addon has `registration_` prefix instead of `user_registration`.
		if ( $prefix === 'user_registration' ) {
			$prefix = 'registration';
		}

		foreach ( $previous_form_data['settings'] as $setting_name => $value ) {
			if ( strpos( $setting_name, $prefix ) === 0 ) {
				$form_data['settings'][ $setting_name ] = $value;

				continue;
			}

			// BC: The options don't have a prefix and hard-coded in the `$legacy_options` variable.
			if ( isset( $legacy_options[ $prefix ] ) && in_array( $setting_name, $legacy_options[ $prefix ], true ) ) {
				$form_data['settings'][ $setting_name ] = $value;
			}
		}
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

	/**
	 * Convert slug to a addon prefix.
	 *
	 * @since 1.9.4
	 *
	 * @param string $slug Addon slug.
	 *
	 * @return string
	 */
	private function prepare_prefix( string $slug ): string {

		return str_replace( '-', '_', $slug );
	}
}
