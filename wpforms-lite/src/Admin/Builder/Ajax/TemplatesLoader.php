<?php

namespace WPForms\Admin\Builder\Ajax;

use WPForms\Admin\Traits\FormTemplates;

/**
 * AJAX handler for infinite scroll template loading.
 *
 * @since 2.0.0
 */
class TemplatesLoader {

	use FormTemplates;

	/**
	 * Default batch size.
	 *
	 * @since 2.0.0
	 */
	private const DEFAULT_LIMIT = 12;

	/**
	 * Maximum allowed batch size.
	 *
	 * @since 2.0.0
	 */
	private const MAX_LIMIT = 24;

	/**
	 * Determine if the class is allowed to load.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function allow_load(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

		// Load only in the case of AJAX calls from the Form Builder.
		return wpforms_is_admin_ajax() && strpos( $action, 'wpforms_get_templates_' ) === 0;
	}

	/**
	 * Initialize.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		if ( ! $this->allow_load() ) {
			return;
		}

		// Initialize addons object for FormTemplates trait.
		$this->addons_obj = wpforms()->obj( 'addons' );

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	private function hooks(): void {

		add_action( 'wp_ajax_wpforms_get_templates_batch', [ $this, 'ajax_get_templates_batch' ] );
	}

	/**
	 * AJAX handler for getting a template batch.
	 *
	 * @since 2.0.0
	 */
	public function ajax_get_templates_batch(): void {

		// Verify nonce.
		if ( ! check_ajax_referer( 'wpforms-form-templates', 'nonce', false ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'Security check failed.', 'wpforms-lite' ),
				]
			);
		}

		// Check permissions.
		if ( ! wpforms_current_user_can( 'create_forms' ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'You do not have permission to access templates.', 'wpforms-lite' ),
				]
			);
		}

		// Get and sanitize parameters.
		$params = $this->get_sanitized_params();

		// Validate parameters.
		$validation_error = $this->validate_params( $params );

		if ( $validation_error ) {
			wp_send_json_error(
				[
					'message' => $validation_error,
				]
			);
		}

		// Start performance tracking.
		$start_time   = microtime( true );
		$start_memory = memory_get_usage();

		// Get filtered templates.
		$result = $this->get_templates_batch( $params );

		if ( ! $result ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'Failed to get templates batch.', 'wpforms-lite' ),
				]
			);
		}

		// Add performance metadata.
		$result['metadata'] = [
			'execution_time' => round( ( microtime( true ) - $start_time ) * 1000, 2 ), // ms.
			'memory_usage'   => round( ( memory_get_usage() - $start_memory ) / 1024, 2 ), // KB.
		];

		wp_send_json_success( $result );
	}

	/**
	 * Get and sanitize request parameters.
	 *
	 * @since 2.0.0
	 *
	 * @return array Sanitized parameters.
	 */
	private function get_sanitized_params(): array {

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		return [
			'offset'      => isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0,
			'limit'       => isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : self::DEFAULT_LIMIT,
			'category'    => isset( $_POST['category'] ) ? sanitize_key( $_POST['category'] ) : 'all',
			'subcategory' => isset( $_POST['subcategory'] ) ? sanitize_key( $_POST['subcategory'] ) : '',
			'search'      => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'favorite'    => isset( $_POST['favorite'] ) && filter_var( wp_unslash( $_POST['favorite'] ), FILTER_VALIDATE_BOOLEAN ),
		];
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Validate request parameters.
	 *
	 * @since 2.0.0
	 *
	 * @param array $params Parameters to validate.
	 *
	 * @return string|null Error message or null if valid.
	 */
	private function validate_params( array $params ): ?string {

		if ( $params['offset'] < 0 ) {
			return esc_html__( 'Invalid offset parameter.', 'wpforms-lite' );
		}

		if ( $params['limit'] < 1 || $params['limit'] > self::MAX_LIMIT ) {
			return sprintf(
				/* translators: %d - maximum limit value. */
				esc_html__( 'Limit must be between 1 and %d.', 'wpforms-lite' ),
				self::MAX_LIMIT
			);
		}

		if ( strlen( $params['search'] ) > 100 ) {
			return esc_html__( 'Search query too long.', 'wpforms-lite' );
		}

		return null;
	}

	/**
	 * Get a batch of templates.
	 *
	 * @since 2.0.0
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array|null Batch response data.
	 */
	public function get_templates_batch( array $params ): ?array {

		$cache_obj = $this->get_cache_obj();
		$cache_key = '';

		// Layer 1: Check the HTML batch cache for identical requests.
		if ( $cache_obj ) {
			$cache_key    = $this->build_batch_cache_key( $params );
			$cached_batch = $cache_obj->get_html_batch_cache( $cache_key );

			if ( is_array( $cached_batch ) && ! empty( $cached_batch['templates'] ) ) {
				return $cached_batch;
			}
		}

		// Layer 2: Try to load prepared templates from the cache.
		$this->load_prepared_templates_from_cache( $cache_obj );

		// Get filtered templates.
		$filtered = $this->get_filtered_templates(
			$params['offset'],
			$params['limit'],
			$params['category'],
			$params['subcategory'],
			$params['search'],
			$params['favorite']
		);

		// Render HTML for each template.
		$templates_html = [];

		foreach ( $filtered['templates'] as $template_data ) {
			$templates_html[] = wpforms_render(
				'builder/templates-item',
				$template_data,
				true
			);
		}

		$result = [
			'templates' => $templates_html,
			'total'     => $filtered['total'],
			'offset'    => $params['offset'] + count( $templates_html ),
			'has_more'  => $filtered['has_more'],
		];

		// Save to HTML batch cache.
		if ( $cache_obj && ! empty( $cache_key ) ) {
			$cache_obj->save_html_batch_cache( $cache_key, $result );
		}

		return $result;
	}

	/**
	 * Load prepared templates from the cache or generate fresh.
	 *
	 * @since 2.0.0
	 *
	 * @param object|null $cache_obj Templates cache object.
	 */
	private function load_prepared_templates_from_cache( $cache_obj ): void {

		// Try to load from the prepared templates cache.
		$cached_templates = $cache_obj ? $cache_obj->get_prepared_templates_cache() : null;

		if ( ! empty( $cached_templates ) ) {
			$this->prepared_templates = $cached_templates;

			return;
		}

		// Cache miss: full preparation.
		$builder_templates = wpforms()->obj( 'builder_templates' );

		if ( ! $builder_templates ) {
			return;
		}

		$builder_templates->get_templates();
		$this->prepare_templates_data();

		// Save to prepared templates cache.
		if ( $cache_obj && ! empty( $this->prepared_templates ) ) {
			$cache_obj->save_prepared_templates_cache( $this->prepared_templates );
		}
	}

	/**
	 * Build a unique cache key for the batch request.
	 *
	 * @since 2.0.0
	 *
	 * @param array $params Request parameters.
	 *
	 * @return string Cache key.
	 */
	private function build_batch_cache_key( array $params ): string {

		return wp_json_encode(
			[
				'offset'      => $params['offset'],
				'limit'       => $params['limit'],
				'category'    => $params['category'],
				'subcategory' => $params['subcategory'],
				'search'      => $params['search'],
				'favorite'    => $params['favorite'],
				'version'     => WPFORMS_VERSION,
				'user'        => get_current_user_id(),
			]
		);
	}

	/**
	 * Get the templates cache object.
	 *
	 * @since 2.0.0
	 *
	 * @return object|null Cache object or null.
	 */
	private function get_cache_obj() {

		return wpforms()->obj( 'builder_templates_cache' );
	}
}
