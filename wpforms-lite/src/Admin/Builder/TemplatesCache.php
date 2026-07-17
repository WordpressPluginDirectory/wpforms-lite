<?php

namespace WPForms\Admin\Builder;

use WPForms\Helpers\CacheBase;
use WPForms\Helpers\File;
use WPForms\Helpers\Transient;

/**
 * Form templates cache handler.
 *
 * @since 1.6.8
 */
class TemplatesCache extends CacheBase {

	/**
	 * Templates list content cache files.
	 *
	 * @since 1.8.6
	 *
	 * @var array
	 */
	public const CONTENT_CACHE_FILES = [
		'admin-page' => 'templates-admin-page.html',
		'builder'    => 'templates-builder.html',
	];

	/**
	 * Cache key for prepared templates' data.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const PREPARED_DATA_KEY = 'wpforms_prepared_templates_data';

	/**
	 * Cache key prefix for rendered HTML batches.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const HTML_BATCH_PREFIX = 'wpforms_template_batch_';

	/**
	 * Registry key for tracking active HTML batch cache keys.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const HTML_BATCH_REGISTRY = 'wpforms_template_batch_registry';

	/**
	 * Registry key for tracking active prepared templates cache keys.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const PREPARED_DATA_REGISTRY = 'wpforms_prepared_templates_registry';

	/**
	 * List of plugins that can use the templates cache.
	 *
	 * @since 1.8.7
	 *
	 * @var array
	 */
	public const PLUGINS = [
		'wpforms',
		'wpforms-lite',
	];

	/**
	 * Determine if the class is allowed to load.
	 *
	 * @since 1.6.8
	 *
	 * @return bool
	 */
	protected function allow_load(): bool {

		$has_permissions  = wpforms_current_user_can( [ 'create_forms', 'edit_forms' ] );
		$allowed_requests = wpforms_is_admin_ajax() ||
		                    wpforms_is_admin_page( 'builder' ) ||
		                    wpforms_is_admin_page( 'templates' ) ||
		                    wpforms_is_admin_page( 'tools', 'action-scheduler' );
		$allow            = wp_doing_cron() || wpforms_doing_wp_cli() || ( $has_permissions && $allowed_requests );

		/**
		 * Whether to load this class.
		 *
		 * @since 1.7.2
		 *
		 * @param bool $allow True or false.
		 */
		return (bool) apply_filters( 'wpforms_admin_builder_templatescache_allow_load', $allow ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Initialize the class.
	 *
	 * @since 1.8.7
	 */
	public function init(): void { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		parent::init();

		// Upgrade cached templates data after the plugin update.
		add_action( 'upgrader_process_complete', [ $this, 'upgrade_templates' ] );
	}

	/**
	 * Upgrade cached templates data after the plugin update.
	 *
	 * @since 1.8.7
	 *
	 * @param object $upgrader WP_Upgrader instance.
	 */
	public function upgrade_templates( $upgrader ): void {

		if ( $this->allow_update_cache( $upgrader ) ) {
			$this->update( true );
		}
	}

	/**
	 * Determine if allowed to update the cache.
	 *
	 * @since 1.8.7
	 *
	 * @param object $upgrader WP_Upgrader instance.
	 *
	 * @return bool
	 */
	private function allow_update_cache( $upgrader ): bool {

		$result = $upgrader->result ?? null;

		// Check if the plugin was updated.
		if ( ! $result ) {
			return false;
		}

		// Check if updated plugin is WPForms.
		if ( ! in_array( $result['destination_name'], self::PLUGINS, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Provide settings.
	 *
	 * @since 1.6.8
	 *
	 * @return array Settings array.
	 */
	protected function setup(): array {

		return [

			// Remote source URL.
			'remote_source'  => 'https://wpforms.com/templates/api/get/',

			// Cache file.
			'cache_file'     => 'templates.json',

			/**
			 * Time-to-live of the templates cache files in seconds.
			 *
			 * This applies to `uploads/wpforms/cache/templates.json`
			 * and all *.json files in `uploads/wpforms/cache/templates/` directory.
			 *
			 * @since 1.6.8
			 *
			 * @param integer $cache_ttl Cache time-to-live, in seconds.
			 *                           Default value: WEEK_IN_SECONDS.
			 */
			'cache_ttl'      => (int) apply_filters( 'wpforms_admin_builder_templates_cache_ttl', WEEK_IN_SECONDS ),

			/**
			 * Time-to-live of AJAX-related cache (prepared templates and HTML batches) in seconds.
			 *
			 * This applies to prepared templates cache, HTML batch cache, and their registries.
			 *
			 * @since 2.0.0
			 *
			 * @param int $ajax_cache_ttl AJAX cache time-to-live, in seconds.
			 *                            Default value: DAY_IN_SECONDS.
			 */
			'ajax_cache_ttl' => (int) apply_filters( 'wpforms_admin_builder_templates_cache_ajax_cache_ttl', DAY_IN_SECONDS ),

			// Scheduled update action.
			'update_action'  => 'wpforms_admin_builder_templates_cache_update',
		];
	}

	/**
	 * Prepare data to store in a local cache.
	 *
	 * @since 1.6.8
	 *
	 * @param array $data Raw data received by the remote request.
	 *
	 * @return array Prepared data for caching.
	 */
	protected function prepare_cache_data( $data ): array {

		if (
			empty( $data ) ||
			! is_array( $data ) ||
			empty( $data['status'] ) ||
			$data['status'] !== 'success' ||
			empty( $data['data'] )
		) {
			return [];
		}

		$cache_data = $data['data'];

		// Strip the word "Template" from the end of each template name.
		foreach ( $cache_data['templates'] as $slug => $template ) {
			$cache_data['templates'][ $slug ]['name'] = preg_replace( '/\sTemplate$/', '', $template['name'] );
		}

		return $cache_data;
	}

	/**
	 * Update the cache.
	 *
	 * @since 1.8.6
	 *
	 * @param bool $force Whether to force cache update.
	 *
	 * @return bool
	 */
	public function update( bool $force = false ): bool {

		$result = parent::update( $force );

		if ( ! $result ) {
			return false;
		}

		$this->wipe_content_cache();
		$this->clear_prepared_templates_cache();
		$this->clear_html_batch_cache();

		return true;
	}

	/**
	 * Get cached templates content.
	 *
	 * @since 1.8.6
	 *
	 * @return string
	 */
	public function get_content_cache(): string {

		// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		return File::get_contents( $this->get_content_cache_file() ) ?: '';
	}

	/**
	 * Save templates content cache.
	 *
	 * @since 1.8.6
	 *
	 * @param string|mixed $content Templates content.
	 *
	 * @return bool
	 */
	public function save_content_cache( $content ): bool {

		return File::put_contents( $this->get_content_cache_file(), (string) $content );
	}

	/**
	 * Wipe cached templates content.
	 *
	 * @since 1.8.6
	 */
	public function wipe_content_cache(): void {

		$cache_dir = $this->get_cache_dir();

		// Delete the template content cache files. They will be regenerated on the first visit.
		foreach ( self::CONTENT_CACHE_FILES as $file ) {

			$cache_file = $cache_dir . $file;

			if ( is_file( $cache_file ) && is_readable( $cache_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $cache_file );
			}
		}
	}

	/**
	 * Get templates content cache file path.
	 *
	 * @since 1.8.6
	 *
	 * @return string
	 */
	private function get_content_cache_file(): string {

		$context = wpforms_is_admin_page( 'templates' ) ? 'admin-page' : 'builder';

		return File::get_cache_dir() . self::CONTENT_CACHE_FILES[ $context ];
	}

	/**
	 * Get prepared templates data from the cache.
	 *
	 * @since 2.0.0
	 *
	 * @return array Cached templates data or empty array.
	 */
	public function get_prepared_templates_cache(): array {

		$cache = Transient::get( $this->get_prepared_cache_key() );

		if ( ! is_array( $cache ) || empty( $cache ) ) {
			return [];
		}

		if ( ! isset( $cache['templates'], $cache['version'], $cache['timestamp'] ) ) {
			return [];
		}

		if ( $cache['version'] !== WPFORMS_VERSION ) {
			$this->clear_prepared_templates_cache();

			return [];
		}

		return $cache['templates'];
	}

	/**
	 * Save prepared templates data to cache.
	 *
	 * @since 2.0.0
	 *
	 * @param array|mixed $templates Prepared templates data.
	 *
	 * @return bool True on success.
	 */
	public function save_prepared_templates_cache( $templates ): bool {

		if ( ! is_array( $templates ) ) {
			return false;
		}

		$cache_key  = $this->get_prepared_cache_key();
		$cache_data = [
			'templates' => $templates,
			'version'   => WPFORMS_VERSION,
			'timestamp' => time(),
		];

		$result = Transient::set( $cache_key, $cache_data, $this->settings['ajax_cache_ttl'] );

		// Register the key for bulk clearing.
		$this->register_cache_key( self::PREPARED_DATA_REGISTRY, $cache_key );

		return $result;
	}

	/**
	 * Clear prepared templates cache.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True on success.
	 */
	public function clear_prepared_templates_cache(): bool {

		$keys = Transient::get( self::PREPARED_DATA_REGISTRY );

		if ( is_array( $keys ) ) {
			foreach ( $keys as $key ) {
				Transient::delete( $key );
			}
		}

		Transient::delete( self::PREPARED_DATA_REGISTRY );

		return true;
	}

	/**
	 * Get user-specific prepared templates cache key.
	 *
	 * @since 2.0.0
	 *
	 * @return string Transient key.
	 */
	private function get_prepared_cache_key(): string {

		return self::PREPARED_DATA_KEY . '_' . get_current_user_id();
	}

	/**
	 * Get rendered HTML batch from cache.
	 *
	 * @since 2.0.0
	 *
	 * @param string $cache_key Unique cache key for this batch.
	 *
	 * @return array|false Cached batch data or false.
	 */
	public function get_html_batch_cache( string $cache_key ) {

		$transient_key = self::HTML_BATCH_PREFIX . md5( $cache_key );

		return Transient::get( $transient_key );
	}

	/**
	 * Save a rendered HTML batch to cache.
	 *
	 * @since 2.0.0
	 *
	 * @param string $cache_key  Unique cache key for this batch.
	 * @param array  $batch_data Batch response data.
	 *
	 * @return bool True on success.
	 */
	public function save_html_batch_cache( string $cache_key, array $batch_data ): bool {

		$transient_key = self::HTML_BATCH_PREFIX . md5( $cache_key );

		$result = Transient::set( $transient_key, $batch_data, $this->settings['ajax_cache_ttl'] );

		// Register the key for bulk clearing.
		$this->register_cache_key( self::HTML_BATCH_REGISTRY, $transient_key );

		return $result;
	}

	/**
	 * Clear all HTML batch caches.
	 *
	 * @since 2.0.0
	 *
	 * @return int Number of deleted transients.
	 */
	private function clear_html_batch_cache(): int {

		$keys    = Transient::get( self::HTML_BATCH_REGISTRY );
		$deleted = 0;

		if ( is_array( $keys ) ) {
			foreach ( $keys as $key ) {
				if ( Transient::delete( $key ) ) {
					++$deleted;
				}
			}
		}

		Transient::delete( self::HTML_BATCH_REGISTRY );

		return $deleted;
	}

	/**
	 * Register a cache key in a registry transient for bulk clearing.
	 *
	 * @since 2.0.0
	 *
	 * @param string $registry_key Registry transient key.
	 * @param string $cache_key    Cache key to register.
	 */
	private function register_cache_key( string $registry_key, string $cache_key ): void {

		$keys = Transient::get( $registry_key );

		if ( ! is_array( $keys ) ) {
			$keys = [];
		}

		if ( in_array( $cache_key, $keys, true ) ) {
			return;
		}

		$keys[] = $cache_key;

		// Set expiration matching the AJAX cache TTL to prevent indefinite growth.
		Transient::set( $registry_key, $keys, $this->settings['ajax_cache_ttl'] );
	}

	/**
	 * Clear all caches.
	 *
	 * @since 2.0.0
	 */
	public function clear_all(): void {

		$this->clear_prepared_templates_cache();
		$this->clear_html_batch_cache();
		$this->wipe_content_cache();
	}
}
