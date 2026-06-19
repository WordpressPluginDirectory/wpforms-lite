<?php

namespace WPForms\Integrations\Abilities;

use WP_Error;
use WP_Post;
use WPForms\Integrations\IntegrationInterface;
use WPForms\Integrations\AiMcp\AiMcp as AiMcpIntegration;

/**
 * WordPress Abilities API Integration for WPForms.
 *
 * Provides a standardized interface for AI assistants and automation tools
 * to discover and interact with WPForms functionality.
 *
 * @since 1.9.9
 */
abstract class Abilities implements IntegrationInterface {

	/**
	 * Ability namespace for WPForms abilities.
	 *
	 * @since 1.9.9
	 *
	 * @var string
	 */
	protected const ABILITY_NAMESPACE = 'wpforms';

	/**
	 * Category slug for WPForms abilities.
	 *
	 * @since 1.9.9
	 *
	 * @var string
	 */
	protected const CATEGORY_SLUG = 'wpforms-forms';

	/**
	 * Indicate if the current integration is allowed to load.
	 *
	 * @since 1.9.9
	 *
	 * @return bool
	 */
	public function allow_load(): bool {

		// Only load if the Abilities API is available (WordPress 6.9+).
		return function_exists( 'wp_register_ability' );
	}

	/**
	 * Load the integration.
	 *
	 * @since 1.9.9
	 */
	public function load(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.9.9
	 */
	protected function hooks(): void {

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register the WPForms ability category.
	 *
	 * @since 1.9.9
	 */
	public function register_category(): void {

		wp_register_ability_category(
			self::CATEGORY_SLUG,
			[
				'label'       => __( 'WPForms', 'wpforms-lite' ),
				'description' => __( 'Abilities for interacting with WPForms forms and entries.', 'wpforms-lite' ),
			]
		);
	}

	/**
	 * Register WPForms abilities.
	 *
	 * @since 1.9.9
	 */
	abstract public function register_abilities();

	/**
	 * Register common abilities shared between Lite and Pro.
	 *
	 * @since 1.9.9
	 */
	protected function register_common_abilities(): void {

		$this->register_list_forms_ability();
		$this->register_get_form_ability();
		$this->register_describe_editing_schema_ability();
		$this->register_create_form_ability();
		$this->register_update_form_settings_ability();
		$this->register_add_field_ability();
		$this->register_update_field_ability();
	}

	/**
	 * Register the list_forms ability.
	 *
	 * @since 1.9.9
	 */
	protected function register_list_forms_ability(): void {

		wp_register_ability(
			self::ABILITY_NAMESPACE . '/list-forms',
			[
				'label'               => __( 'List Forms', 'wpforms-lite' ),
				'description'         => __( 'List all available WPForms forms with their metadata.', 'wpforms-lite' ),
				'category'            => self::CATEGORY_SLUG,
				'execute_callback'    => [ $this, 'ability_list_forms' ],
				'permission_callback' => [ $this, 'check_view_forms_permission' ],
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'status' => [
							'description' => __( 'Filter forms by status.', 'wpforms-lite' ),
							'type'        => 'string',
							'enum'        => [ 'publish', 'draft', 'trash' ],
							'default'     => 'publish',
						],
						'limit'  => [
							'description' => __( 'Maximum number of forms to return.', 'wpforms-lite' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 20,
						],
						'offset' => [
							'description' => __( 'Number of forms to skip.', 'wpforms-lite' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'default'     => 0,
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'forms' => [
							'type'        => 'array',
							'description' => __( 'Array of form objects.', 'wpforms-lite' ),
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'id'       => [ 'type' => 'integer' ],
									'title'    => [ 'type' => 'string' ],
									'status'   => [ 'type' => 'string' ],
									'created'  => [ 'type' => 'string' ],
									'modified' => [ 'type' => 'string' ],
									'author'   => [ 'type' => 'integer' ],
								],
							],
						],
						'total' => [
							'type'        => 'integer',
							'description' => __( 'Total number of forms returned.', 'wpforms-lite' ),
						],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
					'mcp'          => [
						'public' => true,
					],
				],
			]
		);
	}

	/**
	 * Register the get_form ability.
	 *
	 * @since 1.9.9
	 */
	protected function register_get_form_ability(): void {

		wp_register_ability(
			self::ABILITY_NAMESPACE . '/get-form',
			[
				'label'               => __( 'Get Form', 'wpforms-lite' ),
				'description'         => __( 'Get detailed information about a specific WPForms form including its fields.', 'wpforms-lite' ),
				'category'            => self::CATEGORY_SLUG,
				'execute_callback'    => [ $this, 'ability_get_form' ],
				'permission_callback' => [ $this, 'check_view_single_form_permission' ],
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'form_id'        => [
							'description' => __( 'The ID of the form to retrieve.', 'wpforms-lite' ),
							'type'        => 'integer',
							'minimum'     => 1,
						],
						'include_fields' => [
							'description' => __( 'Whether to include field configuration.', 'wpforms-lite' ),
							'type'        => 'boolean',
							'default'     => true,
						],
					],
					'required'   => [ 'form_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'id'       => [ 'type' => 'integer' ],
						'title'    => [ 'type' => 'string' ],
						'status'   => [ 'type' => 'string' ],
						'created'  => [ 'type' => 'string' ],
						'modified' => [ 'type' => 'string' ],
						'author'   => [ 'type' => 'integer' ],
						'settings' => [ 'type' => 'object' ],
						'fields'   => [ 'type' => 'array' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
					'mcp'          => [
						'public' => true,
					],
				],
			]
		);
	}

	/**
	 * Permission callback: Check if the user can view forms.
	 *
	 * @since 1.9.9
	 *
	 * @return bool|WP_Error
	 */
	public function check_view_forms_permission() {

		if ( ! wpforms_current_user_can( 'view_forms' ) ) {
			return new WP_Error(
				'wpforms_forbidden',
				__( 'You do not have permission to view forms.', 'wpforms-lite' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Permission callback: Check if the user can view a specific form.
	 *
	 * @since 1.9.9
	 *
	 * @param mixed $input Input data containing form_id.
	 *
	 * @return bool|WP_Error
	 */
	public function check_view_single_form_permission( $input = null ) {

		$input   = $this->normalize_input( $input );
		$form_id = absint( $input['form_id'] ?? 0 );

		if ( ! $form_id || ! wpforms_current_user_can( 'view_form_single', $form_id ) ) {
			return new WP_Error(
				'wpforms_forbidden',
				__( 'You do not have permission to view this form.', 'wpforms-lite' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Ability callback: List forms.
	 *
	 * @since 1.9.9
	 *
	 * @param mixed $input Input data.
	 *
	 * @return array
	 */
	public function ability_list_forms( $input = null ): array {

		$args = $this->normalize_input( $input );

		$form_handler = $this->get_form_handler();

		if ( is_wp_error( $form_handler ) ) {
			return [
				'forms' => [],
				'total' => 0,
			];
		}

		$limit  = absint( $args['limit'] ?? 20 );
		$offset = absint( $args['offset'] ?? 0 );
		$status = sanitize_text_field( $args['status'] ?? 'publish' );

		// Get total count efficiently using the cached WordPress function.
		// wp_count_posts() returns string counts; cast to int to match the integer output schema.
		$counts = wp_count_posts( 'wpforms' );
		$total  = (int) ( $counts->{$status} ?? 0 );

		// Get paginated forms with proper WordPress pagination.
		$query_args = [
			'post_status'    => $status,
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'nopaging'       => false, // Override default to enable pagination.
			'order'          => 'DESC',
			'orderby'        => 'date',
		];

		$forms = $form_handler->get( '', $query_args );

		if ( empty( $forms ) ) {
			return [
				'forms' => [],
				'total' => $total,
			];
		}

		$result = [];

		foreach ( $forms as $form ) {
			$result[] = $this->format_form_summary( $form );
		}

		return [
			'forms' => $result,
			'total' => $total,
		];
	}

	/**
	 * Ability callback: Get single form.
	 *
	 * @since 1.9.9
	 *
	 * @param mixed $input Input data.
	 *
	 * @return array|WP_Error
	 */
	public function ability_get_form( $input = null ) {

		$args    = $this->normalize_input( $input );
		$form_id = absint( $args['form_id'] ?? 0 );

		if ( empty( $form_id ) ) {
			return new WP_Error(
				'wpforms_invalid_form_id',
				__( 'Invalid form ID.', 'wpforms-lite' ),
				[ 'status' => 400 ]
			);
		}

		$form_handler = $this->get_form_handler();

		if ( is_wp_error( $form_handler ) ) {
			return $form_handler;
		}

		$form = $form_handler->get( $form_id );

		if ( empty( $form ) ) {
			return new WP_Error(
				'wpforms_form_not_found',
				__( 'Form not found.', 'wpforms-lite' ),
				[ 'status' => 404 ]
			);
		}

		$include_fields = wp_validate_boolean( $args['include_fields'] ?? true );

		return $this->format_form_detail( $form, $include_fields );
	}

	/**
	 * Normalize input data to array format.
	 *
	 * @since 1.9.9
	 *
	 * @param mixed $input Input data (can be the array, object, or null).
	 *
	 * @return array
	 */
	protected function normalize_input( $input ): array {

		if ( is_array( $input ) ) {
			return $input;
		}

		if ( is_object( $input ) ) {
			return (array) $input;
		}

		return [];
	}

	/**
	 * Get the form handler and validate it.
	 *
	 * @since 1.9.9
	 *
	 * @return object|WP_Error Form handler object or WP_Error on failure.
	 */
	protected function get_form_handler() {

		$form_handler = wpforms()->obj( 'form' );

		if ( ! $form_handler ) {
			return new WP_Error(
				'wpforms_form_handler_error',
				__( 'Form handler not available.', 'wpforms-lite' ),
				[ 'status' => 500 ]
			);
		}

		return $form_handler;
	}

	/**
	 * Format form data for summary listing.
	 *
	 * @since 1.9.9
	 *
	 * @param WP_Post $form Form the `post` object.
	 *
	 * @return array
	 */
	protected function format_form_summary( WP_Post $form ): array {

		return [
			'id'       => $form->ID,
			'title'    => $form->post_title,
			'status'   => $form->post_status,
			'created'  => $form->post_date,
			'modified' => $form->post_modified,
			'author'   => absint( $form->post_author ),
		];
	}

	/**
	 * Format form data for the detailed view.
	 *
	 * @since 1.9.9
	 *
	 * @param WP_Post $form           Form `post` object.
	 * @param bool    $include_fields Whether to include fields.
	 *
	 * @return array
	 */
	protected function format_form_detail( WP_Post $form, bool $include_fields = true ): array {

		$form_handler = $this->get_form_handler();
		$form_data    = ! is_wp_error( $form_handler ) ? $form_handler->get( $form->ID, [ 'content_only' => true ] ) : [];

		// Ensure form_data is an array.
		if ( ! is_array( $form_data ) ) {
			$form_data = [];
		}

		$result = [
			'id'       => $form->ID,
			'title'    => $form->post_title,
			'status'   => $form->post_status,
			'created'  => $form->post_date,
			'modified' => $form->post_modified,
			'author'   => absint( $form->post_author ),
			'settings' => $this->get_safe_settings( $form_data ),
		];

		if ( $include_fields && ! empty( $form_data['fields'] ) ) {
			$result['fields'] = $this->format_fields( $form_data['fields'] );
		}

		return $result;
	}

	/**
	 * Get safe settings (without sensitive data).
	 *
	 * @since 1.9.9
	 *
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	protected function get_safe_settings( array $form_data ): array {

		$settings = $form_data['settings'] ?? [];

		// Return only safe, non-sensitive settings.
		return [
			'form_title'  => $settings['form_title'] ?? '',
			'form_desc'   => $settings['form_desc'] ?? '',
			'submit_text' => $settings['submit_text'] ?? __( 'Submit', 'wpforms-lite' ),
			'ajax_submit' => ! empty( $settings['ajax_submit'] ),
			'honeypot'    => ! empty( $settings['honeypot'] ),
			'antispam'    => ! empty( $settings['antispam'] ),
		];
	}

	/**
	 * Format fields for output.
	 *
	 * @since 1.9.9
	 *
	 * @param array $fields Form fields.
	 *
	 * @return array
	 */
	protected function format_fields( array $fields ): array {

		$result = [];

		foreach ( $fields as $field_id => $field ) {
			$result[] = [
				'id'          => absint( $field_id ),
				'type'        => sanitize_text_field( $field['type'] ?? '' ),
				'label'       => sanitize_text_field( $field['label'] ?? '' ),
				'description' => sanitize_text_field( $field['description'] ?? '' ),
				'required'    => ! empty( $field['required'] ),
				'size'        => sanitize_text_field( $field['size'] ?? 'medium' ),
			];
		}

		return $result;
	}

	/**
	 * Determine whether write abilities are enabled.
	 *
	 * Reads the AiMcp::SETTING_KEY key from the shared wpforms_settings option
	 * as the default, then runs it through the `wpforms_integrations_abilities_allow_write`
	 * filter so developers can still override programmatically in either direction.
	 *
	 * @since 1.10.2
	 *
	 * @return bool
	 */
	protected function write_enabled(): bool {

		$default = (bool) wpforms_setting( AiMcpIntegration::SETTING_KEY );

		/**
		 * Filters whether the WPForms write abilities are enabled.
		 *
		 * @since 1.10.2
		 *
		 * @param bool $enabled Whether writes are enabled. Default is the value of
		 *                      the AiMcp::SETTING_KEY key inside the shared
		 *                      `wpforms_settings` option (false if absent).
		 */
		return (bool) apply_filters( 'wpforms_integrations_abilities_allow_write', $default );
	}

	/**
	 * Get a configured FormMutator instance.
	 *
	 * @since 1.10.2
	 *
	 * @return FormMutator
	 */
	protected function get_mutator(): FormMutator {

		return new FormMutator( new FieldRegistry(), new SettingsRegistry() );
	}

	/**
	 * Check the write gate and return true or a WP_Error 403.
	 *
	 * @since 1.10.2
	 *
	 * @return bool|WP_Error
	 */
	private function check_write_gate() {

		if ( ! $this->write_enabled() ) {
			return new WP_Error(
				'wpforms_writes_disabled',
				__( 'Form write abilities are disabled.', 'wpforms-lite' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Permission gate shared by all write abilities.
	 *
	 * @since 1.10.2
	 *
	 * @param string   $cap     Capability to check.
	 * @param int|null $form_id Optional form ID for per-form capabilities.
	 *
	 * @return bool|WP_Error
	 */
	protected function check_write_permission( string $cap, $form_id = null ) {

		$gate = $this->check_write_gate();

		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$allowed = $form_id === null
			? wpforms_current_user_can( $cap )
			: wpforms_current_user_can( $cap, $form_id );

		if ( ! $allowed ) {
			return new WP_Error(
				'wpforms_forbidden',
				__( 'You do not have permission to perform this action.', 'wpforms-lite' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Permission callback: create form.
	 *
	 * @since 1.10.2
	 *
	 * @param mixed $input Input data (unused).
	 *
	 * @return bool|WP_Error
	 */
	public function check_create_form_permission( $input = null ) {

		return $this->check_write_permission( 'create_forms' );
	}

	/**
	 * Permission callback: edit a specific form.
	 *
	 * @since 1.10.2
	 *
	 * @param mixed $input Input data containing form_id.
	 *
	 * @return bool|WP_Error
	 */
	public function check_edit_form_permission( $input = null ) {

		$gate = $this->check_write_gate();

		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$input   = $this->normalize_input( $input );
		$form_id = absint( $input['form_id'] ?? 0 );

		if ( ! $form_id ) {
			return new WP_Error(
				'wpforms_invalid_form_id',
				__( 'Invalid form ID.', 'wpforms-lite' ),
				[ 'status' => 400 ]
			);
		}

		return $this->check_write_permission( 'edit_form_single', $form_id );
	}

	/**
	 * Ability callback: describe the editing schema (field types and form settings).
	 *
	 * @since 1.10.2
	 *
	 * @return array
	 */
	public function ability_describe_editing_schema(): array {

		return [
			'field_types'   => ( new FieldRegistry() )->describe(),
			'form_settings' => ( new SettingsRegistry() )->describe(),
		];
	}

	/**
	 * Build the meta block for a write ability (MCP-public only when writes are enabled).
	 *
	 * @since 1.10.2
	 *
	 * @param bool $destructive Whether the ability is destructive.
	 * @param bool $idempotent  Whether the ability is idempotent.
	 *
	 * @return array
	 */
	protected function write_meta( bool $destructive, bool $idempotent ): array {

		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			],
			'show_in_rest' => true,
			'mcp'          => [ 'public' => $this->write_enabled() ],
		];
	}

	/**
	 * Register the describe-editing-schema ability.
	 *
	 * @since 1.10.2
	 */
	protected function register_describe_editing_schema_ability(): void {

		wp_register_ability(
			self::ABILITY_NAMESPACE . '/describe-editing-schema',
			[
				'label'               => __( 'Describe Editing Schema', 'wpforms-lite' ),
				'description'         => __( 'Describe which field types, field properties and form settings can be created or edited.', 'wpforms-lite' ),
				'category'            => self::CATEGORY_SLUG,
				'execute_callback'    => [ $this, 'ability_describe_editing_schema' ],
				'permission_callback' => [ $this, 'check_view_forms_permission' ],
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'field_types'   => [ 'type' => 'array' ],
						'form_settings' => [ 'type' => 'array' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
					// Mirror the 4 write abilities: hide the editing schema describer from MCP
					// when writes are gated off so agents do not see an editing surface they
					// cannot act on. REST exposure is unaffected (gated by view_forms cap).
					'mcp'          => [ 'public' => $this->write_enabled() ],
				],
			]
		);
	}

	/**
	 * Register the create-form ability.
	 *
	 * @since 1.10.2
	 */
	protected function register_create_form_ability(): void {

		$registry          = new FieldRegistry();
		$settings_registry = new SettingsRegistry();

		wp_register_ability(
			self::ABILITY_NAMESPACE . '/create-form',
			[
				'label'               => __( 'Create Form', 'wpforms-lite' ),
				'description'         => __( 'Create a new WPForms form with an optional set of fields and settings.', 'wpforms-lite' ),
				'category'            => self::CATEGORY_SLUG,
				'execute_callback'    => [ $this, 'ability_create_form' ],
				'permission_callback' => [ $this, 'check_create_form_permission' ],
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'title'    => [
							'type'        => 'string',
							'description' => __( 'The form title.', 'wpforms-lite' ),
						],
						'fields'   => [
							'type'        => 'array',
							'description' => __( 'Optional initial fields.', 'wpforms-lite' ),
							'items'       => $registry->field_item_schema(),
						],
						'settings' => [
							'type'        => 'object',
							'description' => __( 'Optional initial settings.', 'wpforms-lite' ),
							'properties'  => $settings_registry->input_properties_schema(),
						],
					],
					'required'             => [ 'title' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'form_id' => [ 'type' => 'integer' ],
						'fields'  => [ 'type' => 'array' ],
					],
				],
				'meta'                => $this->write_meta( false, false ),
			]
		);
	}

	/**
	 * Register the update-form-settings ability.
	 *
	 * @since 1.10.2
	 */
	protected function register_update_form_settings_ability(): void {

		$settings_registry = new SettingsRegistry();

		wp_register_ability(
			self::ABILITY_NAMESPACE . '/update-form-settings',
			[
				'label'               => __( 'Update Form Settings', 'wpforms-lite' ),
				'description'         => __( 'Update safe form settings (title, description, submit button text).', 'wpforms-lite' ),
				'category'            => self::CATEGORY_SLUG,
				'execute_callback'    => [ $this, 'ability_update_form_settings' ],
				'permission_callback' => [ $this, 'check_edit_form_permission' ],
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'form_id'  => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'settings' => [
							// Lenient on purpose: unknown setting keys reach the callback and are
							// reported in `ignored` (settings have no per-type dimension, unlike fields).
							'type'       => 'object',
							'properties' => $settings_registry->input_properties_schema(),
						],
					],
					'required'             => [ 'form_id', 'settings' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'form_id' => [ 'type' => 'integer' ],
						'updated' => [ 'type' => 'array' ],
						'ignored' => [ 'type' => 'array' ],
					],
				],
				'meta'                => $this->write_meta( false, true ),
			]
		);
	}

	/**
	 * Register the add-field ability.
	 *
	 * @since 1.10.2
	 */
	protected function register_add_field_ability(): void {

		$registry = new FieldRegistry();

		wp_register_ability(
			self::ABILITY_NAMESPACE . '/add-field',
			[
				'label'               => __( 'Add Field', 'wpforms-lite' ),
				'description'         => __( 'Add a new field to a form. Call describe-editing-schema for supported types and properties.', 'wpforms-lite' ),
				'category'            => self::CATEGORY_SLUG,
				'execute_callback'    => [ $this, 'ability_add_field' ],
				'permission_callback' => [ $this, 'check_edit_form_permission' ],
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => array_merge(
						[
							'form_id' => [
								'type'    => 'integer',
								'minimum' => 1,
							],
							// The type is intentionally an unconstrained string so the
							// FormMutator callback owns every "this type is not supported here"
							// response with HTTP 422 `wpforms_field_type_unavailable`, covering
							// both Pro-on-Lite and entirely unknown types under one contract.
							// Discoverable types are advertised via describe-editing-schema.
							'type'    => [
								'type' => 'string',
							],
						],
						$registry->input_properties_schema()['properties']
					),
					'required'             => [ 'form_id', 'type' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'form_id'  => [ 'type' => 'integer' ],
						'field_id' => [ 'type' => 'integer' ],
						'type'     => [ 'type' => 'string' ],
					],
				],
				'meta'                => $this->write_meta( false, false ),
			]
		);
	}

	/**
	 * Register the update-field ability.
	 *
	 * @since 1.10.2
	 */
	protected function register_update_field_ability(): void {

		$registry = new FieldRegistry();

		wp_register_ability(
			self::ABILITY_NAMESPACE . '/update-field',
			[
				'label'               => __( 'Update Field', 'wpforms-lite' ),
				'description'         => __( 'Update properties of an existing field. Call describe-editing-schema for supported properties.', 'wpforms-lite' ),
				'category'            => self::CATEGORY_SLUG,
				'execute_callback'    => [ $this, 'ability_update_field' ],
				'permission_callback' => [ $this, 'check_edit_form_permission' ],
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => array_merge(
						[
							'form_id'  => [
								'type'    => 'integer',
								'minimum' => 1,
							],
							'field_id' => [
								'type'    => 'integer',
								'minimum' => 1,
							],
						],
						$registry->input_properties_schema()['properties']
					),
					'required'             => [ 'form_id', 'field_id' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'form_id'  => [ 'type' => 'integer' ],
						'field_id' => [ 'type' => 'integer' ],
						'updated'  => [ 'type' => 'array' ],
						'ignored'  => [ 'type' => 'array' ],
					],
				],
				'meta'                => $this->write_meta( false, true ),
			]
		);
	}

	/**
	 * Ability callback: create form.
	 *
	 * @since 1.10.2
	 *
	 * @param mixed $input Input data.
	 *
	 * @return array|WP_Error
	 */
	public function ability_create_form( $input = null ) {

		$args = $this->normalize_input( $input );

		return $this->get_mutator()->create_form(
			[
				'title'    => $args['title'] ?? '',
				'fields'   => $args['fields'] ?? [],
				'settings' => $args['settings'] ?? [],
			]
		);
	}

	/**
	 * Ability callback: update form settings.
	 *
	 * @since 1.10.2
	 *
	 * @param mixed $input Input data.
	 *
	 * @return array|WP_Error
	 */
	public function ability_update_form_settings( $input = null ) {

		$args = $this->normalize_input( $input );

		return $this->get_mutator()->update_settings( absint( $args['form_id'] ?? 0 ), (array) ( $args['settings'] ?? [] ) );
	}

	/**
	 * Ability callback: add field.
	 *
	 * @since 1.10.2
	 *
	 * @param mixed $input Input data.
	 *
	 * @return array|WP_Error
	 */
	public function ability_add_field( $input = null ) {

		$args  = $this->normalize_input( $input );
		$type  = sanitize_text_field( $args['type'] ?? '' );
		$props = $args;

		unset( $props['form_id'], $props['type'] );

		return $this->get_mutator()->add_field( absint( $args['form_id'] ?? 0 ), $type, $props );
	}

	/**
	 * Ability callback: update field.
	 *
	 * @since 1.10.2
	 *
	 * @param mixed $input Input data.
	 *
	 * @return array|WP_Error
	 */
	public function ability_update_field( $input = null ) {

		$args  = $this->normalize_input( $input );
		$props = $args;

		unset( $props['form_id'], $props['field_id'] );

		return $this->get_mutator()->update_field(
			absint( $args['form_id'] ?? 0 ),
			absint( $args['field_id'] ?? 0 ),
			$props
		);
	}
}
