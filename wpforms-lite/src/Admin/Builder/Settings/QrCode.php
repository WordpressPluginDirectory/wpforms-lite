<?php

namespace WPForms\Admin\Builder\Settings;

use WPForms_Builder_Panel_Settings;

/**
 * QR Code setting in the General section of the Form Builder Settings panel.
 *
 * @since 2.0.1
 */
class QrCode {

	/**
	 * Bundled qr-code-styling library version.
	 *
	 * @since 2.0.1
	 */
	private const LIBRARY_VERSION = '1.9.2';

	/**
	 * Allowed destination types.
	 *
	 * @since 2.0.1
	 */
	private const DESTINATIONS = [ 'none', 'page', 'url' ];

	/**
	 * Allowed logo choices.
	 *
	 * Also read by usage tracking to whitelist per-form logo counters.
	 *
	 * @since 2.0.1
	 */
	public const LOGOS = [ 'none', 'wpforms', 'custom' ];

	/**
	 * License types unlocking the logo control.
	 *
	 * @since 2.0.1
	 */
	private const LOGO_LICENSES = [ 'pro', 'elite', 'agency', 'ultimate' ];

	/**
	 * Option holding the Pro logo upsell funnel counters.
	 *
	 * Written by the education class and read by usage tracking.
	 *
	 * @since 2.0.1
	 */
	public const LOGO_UPSELL_EVENTS_OPTION = 'wpforms_qr_code_logo_upsell_events';

	/**
	 * Pro logo upsell funnel event names.
	 *
	 * @since 2.0.1
	 */
	public const LOGO_UPSELL_EVENTS = [ 'shown', 'dismissed', 'upgraded' ];

	/**
	 * Initialize class.
	 *
	 * @since 2.0.1
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.1
	 */
	private function hooks(): void {

		add_action( 'wpforms_form_settings_general_main_after', [ $this, 'render' ] );
		add_filter( 'wpforms_save_form_args', [ $this, 'sanitize_settings' ] );
		add_filter( 'wpforms_builder_strings', [ $this, 'builder_strings' ] );
	}

	/**
	 * Add the QR Code strings to the builder localized data.
	 *
	 * @since 2.0.1
	 *
	 * @param array $strings Builder strings.
	 *
	 * @return array
	 */
	public function builder_strings( $strings ): array {

		$strings = (array) $strings;

		$strings['qr_code'] = [
			'file_name'      => 'wpforms-qr-code',
			'lib_url'        => esc_url( add_query_arg( 'ver', self::LIBRARY_VERSION, WPFORMS_PLUGIN_URL . 'assets/lib/qr-code-styling.min.js' ) ),
			'logo_wpforms'   => esc_url( WPFORMS_PLUGIN_URL . 'assets/images/builder/qr-code-logo.svg' ),
			'generate'       => esc_html__( 'Generate QR Code', 'wpforms-lite' ),
			'regenerate'     => esc_html__( 'Regenerate QR Code', 'wpforms-lite' ),
			'error_generate' => esc_html__( 'Could not be generated. Please try again.', 'wpforms-lite' ),
			'error_copy'     => esc_html__( 'Could not be copied. Please use Download instead.', 'wpforms-lite' ),
			'error_logo'     => esc_html__( 'The logo image could not be loaded. Please choose another one.', 'wpforms-lite' ),
			'copied'         => esc_html__( 'QR code copied to clipboard.', 'wpforms-lite' ),
			'error_page'     => esc_html__( 'You must select a page first.', 'wpforms-lite' ),
			'error_url'      => esc_html__( 'You must enter a valid URL first.', 'wpforms-lite' ),
			'preview_label'  => $this->get_preview_label_template(),
		];

		return $strings;
	}

	/**
	 * Determine whether the current license unlocks the logo control.
	 *
	 * @since 2.0.1
	 *
	 * @return bool
	 */
	public static function is_logo_allowed(): bool {

		return in_array( wpforms_get_license_type(), self::LOGO_LICENSES, true );
	}

	/**
	 * Get the Pro logo upsell funnel counters, zero-filled for missing events.
	 *
	 * @since 2.0.1
	 *
	 * @return array
	 */
	public static function get_logo_upsell_events(): array {

		return wp_parse_args(
			(array) get_option( self::LOGO_UPSELL_EVENTS_OPTION, [] ),
			array_fill_keys( self::LOGO_UPSELL_EVENTS, 0 )
		);
	}

	/**
	 * Render the QR Code setting in the General section.
	 *
	 * @since 2.0.1
	 *
	 * @param WPForms_Builder_Panel_Settings $settings Settings panel instance.
	 */
	public function render( WPForms_Builder_Panel_Settings $settings ): void {

		$form_data   = (array) $settings->form_data;
		$qr_settings = $form_data['settings'] ?? [];
		$pages       = $this->get_pages( absint( $qr_settings['qr_code_page_id'] ?? 0 ) );
		$options     = [ 'none' => esc_html__( 'None', 'wpforms-lite' ) ];

		// With no page to link to, the Page destination has nothing to offer.
		if ( $pages ) {
			$options['page'] = esc_html__( 'Page', 'wpforms-lite' );
		}

		$options['url'] = esc_html__( 'Custom URL', 'wpforms-lite' );

		wpforms_panel_field(
			'select',
			'settings',
			'qr_code',
			$settings->form_data,
			esc_html__( 'QR Code', 'wpforms-lite' ),
			[
				'default' => 'none',
				'options' => $options,
				'tooltip' => esc_html__( 'Generate a QR code that opens the page or URL you choose, ready to copy or download for print.', 'wpforms-lite' ),
			]
		);

		$this->render_content( $settings, $pages );
	}

	/**
	 * Render the QR Code content block: destination fields, preview, and actions.
	 *
	 * @since 2.0.1
	 *
	 * @param WPForms_Builder_Panel_Settings $settings Settings panel instance.
	 * @param array                          $pages    Pages available for the Destination Page select.
	 */
	private function render_content( WPForms_Builder_Panel_Settings $settings, array $pages ): void {

		$form_data   = (array) $settings->form_data;
		$qr_settings = $form_data['settings'] ?? [];
		$destination = $this->get_effective_destination( $qr_settings['qr_code'] ?? 'none', $pages );
		$logo_url    = $this->get_custom_logo_url( absint( $qr_settings['qr_code_logo_id'] ?? 0 ) );
		$generated   = esc_url_raw( $qr_settings['qr_code_generated'] ?? '' );
		$permalinks  = [];
		$is_hidden   = ! in_array( $destination, [ 'page', 'url' ], true );

		foreach ( array_keys( $pages ) as $id ) {
			/** This filter is documented in includes/functions/access.php. */
			$permalinks[ $id ] = esc_url_raw( (string) apply_filters( 'wpforms_search_pages_for_dropdown_permalink', get_permalink( $id ), get_post( $id ) ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		}

		?>
		<div id="wpforms-panel-field-settings-qr_code-content"
			class="wpforms-panel-field wpforms-panel-field-qr-code-content<?php echo $is_hidden ? ' wpforms-hidden' : ''; ?>"
			data-logo-url="<?php echo esc_url( $logo_url ); ?>"
			data-pages="<?php echo esc_attr( (string) wp_json_encode( $permalinks ) ); ?>">

			<div class="wpforms-qr-code-preview-column">
				<div class="wpforms-qr-code-preview" role="img" aria-label="<?php echo esc_attr( $this->get_preview_label( $generated ) ); ?>">
					<div class="wpforms-qr-code-preview-canvas"></div>
					<div class="wpforms-qr-code-preview-status" aria-hidden="true"></div>
				</div>

				<div class="wpforms-qr-code-actions">
					<button type="button" class="wpforms-btn wpforms-btn-md wpforms-btn-blue wpforms-qr-code-generate">
						<?php esc_html_e( 'Generate QR Code', 'wpforms-lite' ); ?>
					</button>

					<div class="wpforms-qr-code-actions-generated wpforms-hidden">
						<button type="button" class="wpforms-btn wpforms-btn-md wpforms-btn-light-grey wpforms-qr-code-action" data-qr-action="copy" aria-expanded="false">
							<i class="fa fa-copy" aria-hidden="true"></i><?php esc_html_e( 'Copy', 'wpforms-lite' ); ?>
						</button>
						<button type="button" class="wpforms-btn wpforms-btn-md wpforms-btn-light-grey wpforms-qr-code-action" data-qr-action="download" aria-expanded="false">
							<i class="fa fa-download" aria-hidden="true"></i><?php esc_html_e( 'Download', 'wpforms-lite' ); ?>
						</button>
					</div>

					<div class="wpforms-qr-code-format-menu wpforms-context-menu-list wpforms-hidden">
						<button type="button" class="wpforms-qr-code-format wpforms-context-menu-list-item" data-qr-format="png">
							<span class="wpforms-context-menu-list-item-icon"><i class="fa fa-file-image-o" aria-hidden="true"></i></span>
							<span class="wpforms-context-menu-list-item-text"><?php esc_html_e( 'PNG Image', 'wpforms-lite' ); ?></span>
						</button>
						<button type="button" class="wpforms-qr-code-format wpforms-context-menu-list-item" data-qr-format="svg">
							<span class="wpforms-context-menu-list-item-icon"><i class="fa fa-code" aria-hidden="true"></i></span>
							<span class="wpforms-context-menu-list-item-text"><?php esc_html_e( 'SVG Vector', 'wpforms-lite' ); ?></span>
						</button>
					</div>
				</div>
			</div>

			<div class="wpforms-qr-code-settings-column">
				<?php
				wpforms_panel_field(
					'select',
					'settings',
					'qr_code_page_id',
					$settings->form_data,
					esc_html__( 'Destination Page', 'wpforms-lite' ),
					[
						'class'       => 'wpforms-panel-field-qr-code-page' . ( $destination === 'page' ? '' : ' wpforms-hidden' ),
						'options'     => $pages,
						'placeholder' => esc_html__( '--- Select a Page ---', 'wpforms-lite' ),
						'choicesjs'   => [
							'use_ajax'    => true,
							'callback_fn' => 'select_pages',
						],
						'after'       => '<p class="note">' . esc_html__( 'Choose the page for the QR code link.', 'wpforms-lite' ) . '</p>',
					]
				);

				wpforms_panel_field(
					'text',
					'settings',
					'qr_code_url',
					$settings->form_data,
					esc_html__( 'Destination URL', 'wpforms-lite' ),
					[
						'class'       => 'wpforms-panel-field-qr-code-url' . ( $destination === 'url' ? '' : ' wpforms-hidden' ),
						'placeholder' => 'https://',
						'after'       => '<p class="note">' . esc_html__( 'Enter the URL for the QR code link.', 'wpforms-lite' ) . '</p>',
					]
				);

				/**
				 * Fires where the QR Code Logo control is rendered: Pro and Elite hook the full control here, every
				 * lower plan a locked one.
				 *
				 * @since 2.0.1
				 *
				 * @param WPForms_Builder_Panel_Settings $settings Settings panel instance.
				 */
				do_action( 'wpforms_admin_builder_settings_qr_code_logo', $settings );
				?>
			</div>

			<input type="hidden" name="settings[qr_code_generated]" value="<?php echo esc_url( $generated ); ?>">
		</div>
		<?php
	}

	/**
	 * Resolve the destination to render: a saved Page has nothing to point at once the pages are gone.
	 *
	 * @since 2.0.1
	 *
	 * @param string $destination Saved destination.
	 * @param array  $pages       Pages available for the Destination Page select.
	 *
	 * @return string
	 */
	private function get_effective_destination( string $destination, array $pages ): string {

		return $destination === 'page' && ! $pages ? 'none' : $destination;
	}

	/**
	 * Get the preview text alternative: names the encoded destination once generated.
	 *
	 * @since 2.0.1
	 *
	 * @param string $generated Generated destination URL, empty when not generated.
	 *
	 * @return string
	 */
	private function get_preview_label( string $generated ): string {

		if ( ! $generated ) {
			return esc_html__( 'QR code preview', 'wpforms-lite' );
		}

		return sprintf( $this->get_preview_label_template(), $generated );
	}

	/**
	 * Get the preview label template shared by the PHP render and the JS aria-label update.
	 *
	 * @since 2.0.1
	 *
	 * @return string
	 */
	private function get_preview_label_template(): string {

		/* translators: %s - destination URL encoded in the QR code. */
		return esc_html__( 'QR code linking to %s', 'wpforms-lite' );
	}

	/**
	 * Get the custom logo attachment URL.
	 *
	 * @since 2.0.1
	 *
	 * @param int    $logo_id Logo attachment ID.
	 * @param string $size    Image size to return.
	 *
	 * @return string
	 */
	protected function get_custom_logo_url( int $logo_id, string $size = 'full' ): string {

		if ( ! $logo_id || ! wp_attachment_is_image( $logo_id ) ) {
			return '';
		}

		// Don't expose attachments the current user has no access to.
		if ( ! current_user_can( 'edit_post', $logo_id ) ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $logo_id, $size );

		if ( ! $url ) {
			return '';
		}

		// Drop the scheme so the logo always follows the page. A stored http URL on an https builder is mixed
		// content: the browser blocks the library's fetch and the code never renders.
		return (string) preg_replace( '#^https?:#i', '', $url );
	}

	/**
	 * Get the list of pages for the Destination Page select.
	 *
	 * @since 2.0.1
	 *
	 * @param int $page_id Currently saved page ID.
	 *
	 * @return array
	 */
	private function get_pages( int $page_id ): array {

		$pages = [];

		foreach ( wpforms_search_posts() as $post ) {
			$pages[ $post->ID ] = esc_html( wpforms_get_post_title( $post ) );
		}

		// Keep the saved page selectable even when it is outside the default search results.
		$saved_page = $page_id && ! isset( $pages[ $page_id ] ) ? get_post( $page_id ) : null;

		if (
			$saved_page &&
			$saved_page->post_status === 'publish' &&
			$saved_page->post_type === 'page' &&
			current_user_can( 'read_post', $page_id )
		) {
			$pages[ $page_id ] = esc_html( wpforms_get_post_title( $saved_page ) );
		}

		return $pages;
	}

	/**
	 * Sanitize the QR Code settings on form save.
	 *
	 * @since 2.0.1
	 *
	 * @param array $args Form save arguments.
	 *
	 * @return array
	 */
	public function sanitize_settings( $args ): array {

		$args = (array) $args;

		if ( empty( $args['post_content'] ) ) {
			return $args;
		}

		$form_data = json_decode( stripslashes( $args['post_content'] ), true );

		if ( empty( $form_data['settings'] ) ) {
			return $args;
		}

		$settings = $form_data['settings'];

		$settings['qr_code']           = $this->whitelist( $settings['qr_code'] ?? 'none', self::DESTINATIONS, 'none' );
		$settings['qr_code_logo']      = $this->whitelist( $settings['qr_code_logo'] ?? 'wpforms', self::LOGOS, 'wpforms' );
		$settings['qr_code_page_id']   = absint( $settings['qr_code_page_id'] ?? 0 );
		$settings['qr_code_logo_id']   = absint( $settings['qr_code_logo_id'] ?? 0 );
		$settings['qr_code_url']       = $this->sanitize_url( $settings['qr_code_url'] ?? '' );
		$settings['qr_code_generated'] = $this->sanitize_url( $settings['qr_code_generated'] ?? '' );

		$form_data['settings'] = $this->clear_disabled_settings( $settings );
		$args['post_content']  = wpforms_encode( $form_data );

		return $args;
	}

	/**
	 * Reset the QR Code settings on save when the feature is off. None means nothing is configured, so keeping a
	 * destination and a logo around would resurface them the moment the setting is switched back on. This runs on
	 * save only: the builder leaves the fields alone so switching back and forth in one session is not a data loss.
	 *
	 * @since 2.0.1
	 *
	 * @param array $settings Sanitized settings.
	 *
	 * @return array
	 */
	private function clear_disabled_settings( array $settings ): array {

		if ( $settings['qr_code'] !== 'none' ) {
			return $settings;
		}

		$settings['qr_code_page_id']   = 0;
		$settings['qr_code_url']       = '';
		$settings['qr_code_logo']      = 'wpforms';
		$settings['qr_code_logo_id']   = 0;
		$settings['qr_code_generated'] = '';

		return $settings;
	}

	/**
	 * Whitelist a value against the allowed list.
	 *
	 * @since 2.0.1
	 *
	 * @param mixed  $value    Raw value.
	 * @param array  $allowed  Allowed values.
	 * @param string $fallback Fallback value.
	 *
	 * @return string
	 */
	private function whitelist( $value, array $allowed, string $fallback ): string {

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Sanitize a destination URL: http/https schemes only.
	 *
	 * @since 2.0.1
	 *
	 * @param mixed $url Raw URL.
	 *
	 * @return string
	 */
	private function sanitize_url( $url ): string {

		return esc_url_raw( (string) $url, [ 'http', 'https' ] );
	}
}
