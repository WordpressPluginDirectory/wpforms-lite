<?php

namespace WPForms\Admin\Education\Builder;

use WPForms\Admin\Education\EducationInterface;
use WPForms\Admin\Education\Helpers;
use WPForms\Requirements\Requirements;

/**
 * Product education addon banners in the Form Builder.
 *
 * A reusable banner at the top of the Fields panel preview that recommends
 * an addon once the form reaches a field-count threshold and the addon is
 * not active yet. Only one banner shows at a time; catalog order defines
 * the priority. Dismissal is permanent per user.
 *
 * @since 2.0.1
 */
class AddonBanners implements EducationInterface {

	/**
	 * Option holding form settings enabled by default on newly created forms.
	 *
	 * Populated once an addon is installed and activated via a banner: from that
	 * point the addon should be enabled on all new forms going forward.
	 *
	 * @since 2.0.1
	 *
	 * @var string
	 */
	private const NEW_FORM_DEFAULTS_OPTION = 'wpforms_addon_banners_new_form_defaults';

	/**
	 * Indicate if the feature is allowed to load.
	 *
	 * @since 2.0.1
	 *
	 * @return bool
	 */
	public function allow_load(): bool {

		return wpforms_is_admin_page( 'builder' ) || wp_doing_ajax();
	}

	/**
	 * Init.
	 *
	 * @since 2.0.1
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 2.0.1
	 */
	private function hooks(): void {

		// Forms are created outside the builder too (duplication, WP-CLI, API),
		// where the builder gate below fails.
		add_filter( 'wpforms_create_form_args', [ $this, 'apply_new_form_defaults' ], 15 );

		if ( ! $this->allow_load() ) {
			return;
		}

		add_action( 'wpforms_builder_panel_fields_panel_content_title_after', [ $this, 'maybe_render_banner' ] );
		add_action( 'wpforms_builder_enqueues', [ $this, 'enqueues' ] );
		add_action( 'wp_ajax_wpforms_education_addon_banner_activated', [ $this, 'ajax_activated' ] );
	}

	/**
	 * Banners catalog. Array order defines the priority.
	 *
	 * @since 2.0.1
	 *
	 * @return array
	 */
	private function get_banners(): array {

		$banners = [
			'form-abandonment' => [
				'min_fields'       => 5,
				'settings'         => [ 'form_abandonment' ],
				// Form Abandonment does not affect the frontend form, so it is safe
				// to default it on for future forms; Save and Resume adds a visible
				// link, inappropriate on short forms, and stays per-form only.
				'new_form_default' => true,
				'section'          => 'form_abandonment',
				'page_url'         => 'https://wpforms.com/features/form-abandonment/',
				'utm_content'      => 'Form Abandonment Banner',
				'name'             => __( 'Form Abandonment', 'wpforms-lite' ),
				'heading'          => __( 'Recover the Visitors Who Almost Finished', 'wpforms-lite' ),
				'body'             => /* translators: %1$s - opening addon link tag, %2$s - closing addon link tag. */
					__( '%1$sForm Abandonment%2$s helps save leads you would have otherwise lost.', 'wpforms-lite' ),
			],
			'save-resume'      => [
				'min_fields'  => 10,
				// The sub-toggles replicate the addon defaults for a form where
				// it was never enabled manually, avoiding its builder warnings.
				'settings'    => [ 'save_resume_enable', 'save_resume_enable_resume_link', 'save_resume_enable_email_notification' ],
				'section'     => 'save_resume',
				'page_url'    => 'https://wpforms.com/features/save-and-resume-addon/',
				'utm_content' => 'Save and Resume Banner',
				'name'        => __( 'Save and Resume', 'wpforms-lite' ),
				'heading'     => __( 'Allow Visitors to Finish On Their Own Time', 'wpforms-lite' ),
				'body'        => /* translators: %1$s - opening addon link tag, %2$s - closing addon link tag. */
					__( '%1$sSave and Resume%2$s lets respondents finish it later instead of abandoning a long form.', 'wpforms-lite' ),
			],
		];

		/**
		 * Filters the addon banners catalog.
		 *
		 * @since 2.0.1
		 *
		 * @param array $banners Banners catalog keyed by addon clear slug.
		 */
		return (array) apply_filters( 'wpforms_admin_education_builder_addon_banners_get_banners', $banners );
	}

	/**
	 * Render the winning banner at the top of the Fields panel preview.
	 *
	 * @since 2.0.1
	 *
	 * @param array|mixed $form_data Form data and settings.
	 */
	public function maybe_render_banner( $form_data ): void {

		$form_data = (array) $form_data;
		$eligible  = $this->get_eligible_banner( $form_data );

		if ( ! $eligible ) {
			return;
		}

		[ $slug, $banner, $addon ] = $eligible;

		echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template.
			'education/addon-banner',
			$this->get_render_args( $slug, $banner, $addon, (int) ( $form_data['id'] ?? 0 ) ),
			true
		);
	}

	/**
	 * Education dismissal section slug for a banner.
	 *
	 * @since 2.0.1
	 *
	 * @param string $slug Addon clear slug.
	 *
	 * @return string
	 */
	private function get_section_slug( string $slug ): string {

		return 'addon-banner-' . $slug;
	}

	/**
	 * Get the first eligible banner: catalog order is the priority order,
	 * so a lower-priority banner becomes eligible only once every banner
	 * above it is dismissed or its addon is active.
	 *
	 * @since 2.0.1
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return array Empty array or a [ slug, banner, addon ] tuple.
	 */
	public function get_eligible_banner( array $form_data ): array {

		$addons_obj = wpforms()->obj( 'addons' );

		if ( ! $addons_obj ) {
			return [];
		}

		$fields      = (array) ( $form_data['fields'] ?? [] );
		$first_field = (array) reset( $fields );

		// A template leading with an Internal Information field keeps the top
		// of the preview to itself.
		if ( ( $first_field['type'] ?? '' ) === 'internal-information' ) {
			return [];
		}

		$count = Helpers::count_fillable_fields( $form_data );

		foreach ( $this->get_banners() as $slug => $banner ) {
			if ( $count < $banner['min_fields'] || Helpers::is_dismissed( $this->get_section_slug( $slug ) ) ) {
				continue;
			}

			$addon = $addons_obj->get_addon( $slug );

			if ( empty( $addon ) || ( $addon['status'] ?? '' ) === 'active' ) {
				continue;
			}

			return [ $slug, $banner, $addon ];
		}

		return [];
	}

	/**
	 * Arguments for the addon-banner template.
	 *
	 * @since 2.0.1
	 *
	 * @param string $slug    Addon clear slug.
	 * @param array  $banner  Banner catalog entry.
	 * @param array  $addon   Addon data.
	 * @param int    $form_id Current form ID.
	 *
	 * @return array
	 */
	private function get_render_args( string $slug, array $banner, array $addon, int $form_id ): array {

		// Marketing pinned utm_campaign/utm_medium the opposite way to
		// wpforms_utm_link(), so the parameters are built directly here.
		$addon_link = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer" title="%2$s">',
			esc_url(
				add_query_arg(
					[
						'utm_source'   => 'WordPress',
						'utm_medium'   => 'plugin',
						'utm_campaign' => rawurlencode( 'Builder Education' ),
						'utm_content'  => rawurlencode( $banner['utm_content'] ),
						'utm_locale'   => wpforms_sanitize_key( get_locale() ),
					],
					$banner['page_url']
				)
			),
			/* translators: %s - addon name. */
			esc_attr( sprintf( __( 'Learn more about the %s addon', 'wpforms-lite' ), $banner['name'] ) )
		);

		return [
			'banner_id'       => $this->get_section_slug( $slug ),
			'dismiss_section' => $this->get_section_slug( $slug ),
			'heading'         => $banner['heading'],
			'body'            => sprintf( $banner['body'], $addon_link, '</a>' ),
			'cta'             => $this->get_cta( $banner, $addon, $form_id ),
		];
	}

	/**
	 * Build the CTA definition: a one-click inline install when the addon is
	 * accessible, or the same education modal the Settings tab items use.
	 *
	 * @since 2.0.1
	 *
	 * @param array $banner  Banner catalog entry.
	 * @param array $addon   Addon data.
	 * @param int   $form_id Current form ID.
	 *
	 * @return array
	 */
	private function get_cta( array $banner, array $addon, int $form_id ): array {

		$action    = $addon['action'] ?? 'upgrade';
		$is_inline = ! empty( $addon['plugin_allow'] ) && (
			( $action === 'install' && ! empty( $addon['url'] ) && wpforms_can_install( 'addon' ) && wpforms_can_activate( 'addon' ) ) ||
			( $action === 'activate' && wpforms_can_activate( 'addon' ) )
		);

		if ( $is_inline ) {
			$setup_url = add_query_arg(
				[
					'page'    => 'wpforms-builder',
					'view'    => 'settings',
					'section' => $banner['section'],
					'form_id' => $form_id,
				],
				admin_url( 'admin.php' )
			);

			return [
				'text'  => $this->get_cta_text( $action ),
				'title' => $this->get_cta_title( $action, $banner['name'] ),
				'class' => '',
				'attrs' => [
					'action'      => $action,
					'url'         => $addon['url'],
					'path'        => $addon['path'],
					'nonce'       => $addon['nonce'],
					'form-id'     => $form_id,
					'slug'        => $addon['clear_slug'],
					'settings'    => implode( ',', $banner['settings'] ),
					'setup-url'   => $setup_url,
					'setup-text'  => __( 'Explore Settings', 'wpforms-lite' ),
					/* translators: %s - addon name. */
					'setup-title' => sprintf( __( 'Explore the %s addon settings', 'wpforms-lite' ), $banner['name'] ),
				],
			];
		}

		$attrs = [
			'name'        => $addon['modal_name'],
			'slug'        => $addon['clear_slug'],
			'video'       => $addon['video'],
			'license'     => $addon['license_level'],
			// Marketing asked for identical UTMs across every banner state.
			'utm-content' => $banner['utm_content'],
		];

		if ( wpforms()->is_pro() ) {
			$attrs['action'] = $action;
			$attrs['path']   = $addon['path'];
			$attrs['url']    = $addon['url'];
			$attrs['nonce']  = $addon['nonce'];

			if ( $action === 'incompatible' ) {
				$attrs['message'] = Requirements::get_instance()->get_notice( $addon['path'] );
			}
		}

		return [
			'text'  => $action === 'upgrade' ? __( 'Upgrade to Pro', 'wpforms-lite' ) : $this->get_cta_text( $action ),
			'title' => $this->get_cta_title( $action, $banner['name'] ),
			'class' => 'education-modal',
			'attrs' => $attrs,
		];
	}

	/**
	 * CTA text for the install/activate actions.
	 *
	 * @since 2.0.1
	 *
	 * @param string $action Addon action.
	 *
	 * @return string
	 */
	private function get_cta_text( string $action ): string {

		return $action === 'activate' ?
			__( 'Activate', 'wpforms-lite' ) :
			__( 'Install & Activate', 'wpforms-lite' );
	}

	/**
	 * CTA tooltip naming the addon the action applies to.
	 *
	 * @since 2.0.1
	 *
	 * @param string $action Addon action.
	 * @param string $name   Addon name.
	 *
	 * @return string
	 */
	private function get_cta_title( string $action, string $name ): string {

		if ( $action === 'activate' ) {
			/* translators: %s - addon name. */
			return sprintf( __( 'Activate the %s addon', 'wpforms-lite' ), $name );
		}

		if ( $action === 'install' ) {
			/* translators: %s - addon name. */
			return sprintf( __( 'Install and activate the %s addon', 'wpforms-lite' ), $name );
		}

		/* translators: %s - addon name. */
		return sprintf( __( 'Upgrade to Pro and unlock the %s addon', 'wpforms-lite' ), $name );
	}

	/**
	 * Enqueue the banner script.
	 *
	 * @since 2.0.1
	 */
	public function enqueues(): void {

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-education-addon-banner',
			WPFORMS_PLUGIN_URL . "assets/js/admin/education/addon-banner{$min}.js",
			[ 'jquery', 'wpforms-admin-education-core' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-education-addon-banner',
			'wpforms_education_addon_banner',
			[
				'installing' => esc_html__( 'Installing...', 'wpforms-lite' ),
				'activating' => esc_html__( 'Activating...', 'wpforms-lite' ),
				'error'      => esc_html__( 'Could not install the addon. Please try again or install it manually.', 'wpforms-lite' ),
			]
		);
	}

	/**
	 * Once an addon is activated via a banner: enable it on the form that
	 * triggered the banner and default it on for all new forms going forward.
	 *
	 * @since 2.0.1
	 */
	public function ajax_activated(): void {

		check_ajax_referer( 'wpforms-admin', 'nonce' );

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$slug    = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		$banner  = $this->get_banners()[ $slug ] ?? [];

		if ( ! $form_id || ! $banner ) {
			wp_send_json_error();
		}

		if ( ! wpforms_current_user_can( 'edit_form_single', $form_id ) ) {
			wp_send_json_error();
		}

		if ( ! empty( $banner['new_form_default'] ) ) {
			$defaults = (array) get_option( self::NEW_FORM_DEFAULTS_OPTION, [] );

			$defaults[ $slug ] = $banner['settings'];

			update_option( self::NEW_FORM_DEFAULTS_OPTION, $defaults, false );
		}

		$form_obj  = wpforms()->obj( 'form' );
		$form_data = $form_obj ? $form_obj->get( $form_id, [ 'content_only' => true ] ) : [];

		if ( $form_data ) {
			foreach ( $banner['settings'] as $setting ) {
				$form_data['settings'][ $setting ] = '1';
			}

			$form_obj->update( $form_id, $form_data );
		}

		wp_send_json_success();
	}

	/**
	 * Enable the banner-activated addons on newly created forms.
	 *
	 * Applied once, at form creation, so turning the setting off later
	 * is respected and never re-enabled.
	 *
	 * @since 2.0.1
	 *
	 * @param array|mixed $args Form creation arguments.
	 *
	 * @return array
	 */
	public function apply_new_form_defaults( $args ): array {

		$args     = (array) $args;
		$defaults = (array) get_option( self::NEW_FORM_DEFAULTS_OPTION, [] );

		if ( ! $defaults || empty( $args['post_content'] ) ) {
			return $args;
		}

		$post_content = json_decode( wp_unslash( $args['post_content'] ), true );

		if ( ! is_array( $post_content ) ) {
			return $args;
		}

		$banners = $this->get_banners();

		foreach ( $defaults as $slug => $settings ) {
			// The catalog flag also neutralizes stale option entries recorded
			// before an addon was excluded from the new-form defaults.
			if ( empty( $banners[ $slug ]['new_form_default'] ) ) {
				continue;
			}

			// Requirements-based check works in AJAX, WP-CLI, and API contexts,
			// where the addons object does not report the status reliably.
			if ( ! wpforms_is_addon_initialized( $slug ) ) {
				continue;
			}

			foreach ( (array) $settings as $setting ) {
				// A template may explicitly set the value: respect it.
				$post_content['settings'][ $setting ] = $post_content['settings'][ $setting ] ?? '1';
			}
		}

		$args['post_content'] = wpforms_encode( $post_content );

		return $args;
	}
}
