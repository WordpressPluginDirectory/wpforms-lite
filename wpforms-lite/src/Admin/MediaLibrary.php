<?php

namespace WPForms\Admin;

/**
 * Sanitize SVG files uploaded through the WordPress media library within WPForms admin.
 *
 * Image Choices, the Content field and other WPForms admin areas upload images via the
 * standard WordPress media library, which bypasses the File Upload field's SVG sanitization.
 * This class closes that gap by sanitizing SVGs at upload time in WPForms-originated requests.
 *
 * @since 1.10.2
 */
class MediaLibrary {

	/**
	 * Initialize.
	 *
	 * @since 1.10.2
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.10.2
	 */
	private function hooks(): void {

		add_filter( 'wp_handle_upload_prefilter', [ $this, 'sanitize_svg_upload' ] );
	}

	/**
	 * Sanitize an SVG being uploaded to the media library from a WPForms admin context.
	 *
	 * Reuses wpforms_sanitize_svg_file(): non-SVG files are untouched, and the upload is
	 * rejected when an SVG cannot be sanitized (e.g. gzipped .svgz or invalid XML), mirroring
	 * the File Upload field behavior.
	 *
	 * @since 1.10.2
	 *
	 * @param array|mixed $file Array of a single uploaded file ( name, type, tmp_name, error, size ).
	 *
	 * @return array Modified file array.
	 */
	public function sanitize_svg_upload( $file ): array {

		$file = (array) $file;

		// Preserve any pre-existing upload error ( e.g. file too large, failed PHP upload ).
		if ( ! empty( $file['error'] ) ) {
			return $file;
		}

		// Only act on SVG uploads; everything else passes through untouched.
		if ( empty( $file['name'] ) || ! $this->is_svg( (string) $file['name'] ) ) {
			return $file;
		}

		// Limit to uploads originating from WPForms admin to avoid touching unrelated SVGs.
		if ( ! $this->is_wpforms_upload() ) {
			return $file;
		}

		if ( empty( $file['tmp_name'] ) || ! wpforms_sanitize_svg_file( $file['tmp_name'], (string) $file['name'] ) ) {
			$file['error'] = esc_html__( 'Sorry, this SVG file could not be sanitized, so it was not uploaded.', 'wpforms-lite' );
		}

		return $file;
	}

	/**
	 * Whether the file name points to an SVG ( or gzipped SVG ).
	 *
	 * @since 1.10.2
	 *
	 * @param string $file_name Uploaded file name.
	 *
	 * @return bool
	 */
	private function is_svg( string $file_name ): bool {

		$extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		return in_array( $extension, [ 'svg', 'svgz' ], true );
	}

	/**
	 * Whether the current upload request originates from a WPForms admin context.
	 *
	 * Detected either by the `wpforms-` post_id marker ( Rich Text field convention ) or by a
	 * referer pointing to a WPForms admin page. Nonce and capability are already verified upstream
	 * by WordPress core ( wp_ajax_upload_attachment ), so this only reads request context for routing.
	 *
	 * @since 1.10.2
	 *
	 * @return bool
	 */
	private function is_wpforms_upload(): bool {

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$post_id = isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( strpos( $post_id, 'wpforms-' ) === 0 ) {
			return true;
		}

		$referer = wp_get_referer();

		if ( ! $referer ) {
			return false;
		}

		$query = (string) wp_parse_url( $referer, PHP_URL_QUERY );

		wp_parse_str( $query, $args );

		$page = isset( $args['page'] ) ? (string) $args['page'] : '';

		return strpos( $page, 'wpforms' ) === 0;
	}
}
