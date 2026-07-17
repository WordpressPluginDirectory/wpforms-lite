<?php

namespace WPForms\Integrations\Stripe\Protections;

use WPForms\Integrations\Stripe\Helpers;

/**
 * Stripe error rate limiting.
 *
 * @since 1.8.2
 */
final class RateLimit {

	/**
	 * Rate limit scope ('ip' or 'global').
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private $scope;

	/**
	 * Whether the rate limit is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	private $enabled = true;

	/**
	 * Allowed number of attempts.
	 *
	 * @since 1.8.2
	 *
	 * @var int
	 */
	private $allowed_attempts;

	/**
	 * Rate Limit block expiration time.
	 *
	 * @since 1.8.2
	 *
	 * @var int
	 */
	private $expires_in;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $scope Rate limit scope ('ip' for per-IP, 'global' for site-wide).
	 */
	public function __construct( string $scope = 'ip' ) {

		$this->scope = $scope;
	}

	/**
	 * Perform certain things on class init.
	 *
	 * @since 1.8.2
	 */
	public function init() {

		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		if ( $this->scope === 'global' ) {

			/**
			 * This filter allows disabling the global Stripe rate limit.
			 *
			 * @since 2.0.0
			 *
			 * @param bool $enabled Whether the global rate limit is active.
			 */
			$this->enabled = (bool) apply_filters( 'wpforms_stripe_rate_limit_global_enabled', true );

			/**
			 * This filter allows modifying the global Stripe rate limit attempts count.
			 *
			 * @since 2.0.0
			 *
			 * @param int $count Attempts count site-wide before block kicks in.
			 */
			$this->allowed_attempts = (int) apply_filters( 'wpforms_stripe_rate_limit_global_allowed_attempts', 50 );

			/**
			 * This filter allows modifying the global Stripe rate limit expiration time.
			 *
			 * @since 2.0.0
			 *
			 * @param int $expires_in Expiration time in seconds.
			 */
			$this->expires_in = (int) apply_filters( 'wpforms_stripe_rate_limit_global_expires_in', HOUR_IN_SECONDS );
			// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

			return;
		}

		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		/**
		 * This filter allow to modify Stripe rate limit attempts count.
		 *
		 * @since 1.8.2
		 *
		 * @param int $count Attempts count.
		 */
		$this->allowed_attempts = (int) apply_filters( 'wpforms_stripe_rate_limit_allowed_attempts', 3 );

		/**
		 * This filter allow to modify Stripe rate limit expiration time.
		 *
		 * @since 1.8.2
		 *
		 * @param int $expires_in Expiration time.
		 */
		$this->expires_in = (int) apply_filters( 'wpforms_stripe_rate_limit_expires_in', HOUR_IN_SECONDS * 6 );
		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Check if rate limit is under threshold and passes.
	 *
	 * @since 1.8.2
	 *
	 * @return bool
	 */
	public function is_ok() {

		if ( ! $this->enabled ) {
			return true;
		}

		if ( ! Helpers::should_apply_protections() ) {
			return true;
		}

		$entry = $this->get_entry();

		if ( empty( $entry['attempts'] ) ) {
			return true;
		}

		if ( $entry['attempts'] < $this->allowed_attempts ) {
			return true;
		}

		$this->increment_attempts( $entry );

		return false;
	}

	/**
	 * Increment the number of attempts for a specific IP address.
	 *
	 * @since 1.8.2
	 *
	 * @param array $entry Rate limit entry data.
	 *
	 * @return bool
	 */
	public function increment_attempts( $entry = [] ) {

		if ( empty( $entry ) ) {
			$entry = $this->get_entry();
		}

		$entry['attempts'] = (int) $entry['attempts'] + 1;

		return $this->update_entry( $entry['attempts'] );
	}

	/**
	 * Get rate limit entry id.
	 *
	 * Per-IP scope hashes the requesting IP; global scope hashes the site URL,
	 * so all attempts site-wide share a single counter row.
	 *
	 * @since 1.8.2
	 * @since 2.0.0 Added global scope support.
	 *
	 * @return string
	 */
	private function get_entry_id() {

		if ( $this->scope === 'global' ) {
			return 'wpforms_stripe_attempt_global_' . wp_hash( site_url() );
		}

		return 'wpforms_stripe_attempt_' . wp_hash( wpforms_get_ip() );
	}

	/**
	 * Get rate limit entry attempts and expiration data.
	 *
	 * @since 1.8.2
	 *
	 * @return array
	 */
	private function get_entry() {

		$storage  = $this->get_storage_type();
		$entry_id = $this->get_entry_id();

		if ( $storage === 'file' ) {
			return $this->get_file_entry( $entry_id );
		}

		if ( $storage === 'transient' ) {
			return $this->get_transient_entry( $entry_id );
		}

		return [
			'attempts'   => false,
			'expiration' => false,
		];
	}

	/**
	 * Update rate limit entry attempts and expiration data.
	 *
	 * @since 1.8.2
	 *
	 * @param int $attempts Number of attempts to set.
	 *
	 * @return bool
	 */
	private function update_entry( $attempts ) {

		$storage  = $this->get_storage_type();
		$entry_id = $this->get_entry_id();

		if ( $storage === 'file' ) {
			return $this->update_file_entry( $entry_id, $attempts );
		}

		if ( $storage === 'transient' ) {
			return $this->update_transient_entry( $entry_id, $attempts );
		}

		return false;
	}

	/**
	 * Get a storage type where rate limit entries are saved.
	 *
	 * @since 1.8.2
	 *
	 * @return string
	 */
	private function get_storage_type() {

		$file = $this->get_file_path();

		if ( empty( $file ) ) {
			return 'transient';
		}

		if ( ! file_exists( $file ) ) {
			$this->create_file( $file );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		if ( ! is_writable( $file ) ) {
			return 'transient';
		}

		return 'file';
	}

	/**
	 * Get file path to store the rate limit entries in.
	 *
	 * @since 1.8.2
	 *
	 * @return string
	 */
	private function get_file_path() {

		if ( function_exists( 'wpforms_upload_dir' ) ) {
			$upload_dir = wpforms_upload_dir();
		}

		if ( isset( $upload_dir['path'] ) ) {
			$upload_dir['path'] = trailingslashit( $upload_dir['path'] ) . 'stripe';
		}

		$file_name = wp_hash( site_url() ) . '-rate-limiting.log';

		return isset( $upload_dir['path'] ) ? wp_normalize_path( trailingslashit( $upload_dir['path'] ) . $file_name ) : '';
	}

	/**
	 * Create index.html file in the specified directory if it doesn't exist.
	 *
	 * @since 1.8.2
	 *
	 * @return bool True if file exists or was successfully created, false on failure.
	 */
	private function create_index_html_file() {

		$file = $this->get_file_path();

		if ( empty( $file ) ) {
			return false;
		}

		$index_file = wp_normalize_path( trailingslashit( dirname( $file ) ) . 'index.html' );

		// Do nothing if index.html exists in the directory.
		if ( file_exists( $index_file ) ) {
			return true;
		}

		// Create empty index.html.
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		return file_put_contents( $index_file, '' ) !== false;
	}

	/**
	 * Create a file path to store the rate limit entries in.
	 *
	 * @since 1.8.2
	 *
	 * @param string $file File path.
	 *
	 * @return bool
	 */
	private function create_file( $file ) {

		if ( ! wp_mkdir_p( dirname( $file ) ) ) {
			return false;
		}

		if ( ! $this->create_index_html_file() ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $file, '' ) === false ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		chmod( $file, 0664 );

		return true;
	}

	/**
	 * Read full contents of a rate limit entries file.
	 *
	 * @since 1.8.2
	 *
	 * @return array
	 */
	private function read_whole_file() {

		$file = $this->get_file_path();

		if ( empty( $file ) ) {
			return [];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $file );
		$contents = json_decode( $contents, true );

		return is_array( $contents ) ? $contents : [];
	}

	/**
	 * Write full contents to a rate limit entries file.
	 *
	 * @since 1.8.2
	 *
	 * @param array $contents Array of all rate limit entries.
	 *
	 * @return bool
	 */
	private function write_whole_file( $contents ) {

		if ( ! is_array( $contents ) ) {
			return false;
		}

		$file = $this->get_file_path();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		if ( ! is_writable( $file ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions
		return (bool) file_put_contents( $file, wp_json_encode( $contents ) );
	}

	/**
	 * Filter out the expired rate limit entries from a file.
	 *
	 * @since 1.8.2
	 *
	 * @param array  $contents Array of all rate limit entries.
	 * @param string $entry_id Rate limit entry id.
	 *
	 * @return array
	 */
	private function filter_expired_file_entry( $contents, $entry_id ) {

		$expiration = isset( $contents[ $entry_id ]['expiration'] ) ? (int) $contents[ $entry_id ]['expiration'] : false;

		if ( empty( $expiration ) ) {
			return $contents;
		}

		if ( $expiration >= time() ) {
			return $contents;
		}

		unset( $contents[ $entry_id ] );

		$this->write_whole_file( $contents );

		return $contents;
	}

	/**
	 * Get rate limit entry attempts and expiration data from a file.
	 *
	 * @since 1.8.2
	 *
	 * @param string $entry_id Rate limit entry id.
	 *
	 * @return array
	 */
	private function get_file_entry( $entry_id ) {

		$contents = $this->read_whole_file();
		$contents = $this->filter_expired_file_entry( $contents, $entry_id );

		return [
			'attempts'   => isset( $contents[ $entry_id ]['attempts'] ) ? $contents[ $entry_id ]['attempts'] : false,
			'expiration' => isset( $contents[ $entry_id ]['expiration'] ) ? $contents[ $entry_id ]['expiration'] : false,
		];
	}

	/**
	 * Update rate limit entry attempts and expiration data in a file.
	 *
	 * @since 1.8.2
	 *
	 * @param string $entry_id Rate limit entry id.
	 * @param int    $attempts Number of attempts to set.
	 *
	 * @return bool
	 */
	private function update_file_entry( $entry_id, $attempts ) {

		if ( ! $this->create_index_html_file() ) {
			return false;
		}

		$contents = $this->read_whole_file();

		$contents[ $entry_id ] = [
			'attempts'   => $attempts,
			'expiration' => time() + $this->expires_in,
		];

		return $this->write_whole_file( $contents );
	}

	/**
	 * Get rate limit entry attempts and expiration data from a transient.
	 *
	 * @since 1.8.2
	 *
	 * @param string $entry_id Rate limit entry id.
	 *
	 * @return array
	 */
	private function get_transient_entry( $entry_id ) {

		return [
			'attempts'   => get_transient( $entry_id ),
			'expiration' => get_option( '_transient_timeout_' . $entry_id ),
		];
	}

	/**
	 * Update rate limit entry attempts and expiration data in a transient.
	 *
	 * @since 1.8.2
	 *
	 * @param string $entry_id Rate limit entry id.
	 * @param int    $attempts Number of attempts to set.
	 *
	 * @return bool
	 */
	private function update_transient_entry( $entry_id, $attempts ) {

		return set_transient( $entry_id, $attempts, $this->expires_in );
	}
}
