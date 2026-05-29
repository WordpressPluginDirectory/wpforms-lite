<?php

namespace WPForms\Admin\Builder;

use WP_Post;

/**
 * Provider for the preview dropdown education items.
 *
 * Centralizes the canonical item list shown in the Form Builder preview
 * dropdown and the Form Embed wizard card grid, together with the helpers
 * used to derive per-item state (addon status, demo URL).
 *
 * Results are memoized per form ID so the `wpforms_admin_builder_preview_dropdown_education_items_get_items`
 * filter runs once per request regardless of how many consumers ask for the list.
 *
 * @since 1.10.1
 */
class PreviewDropdownEducationItems {

	/**
	 * UTM medium shared by every preview dropdown education item.
	 *
	 * @since 1.10.1
	 */
	private const UTM_MEDIUM = 'Builder Preview Modal';

	/**
	 * Memoized items keyed by form ID (0 when no form is available).
	 *
	 * @since 1.10.1
	 *
	 * @var array<int, array>
	 */
	private static $cache = [];

	/**
	 * Form the items are built for.
	 *
	 * @since 1.10.1
	 *
	 * @var WP_Post|null
	 */
	private $form;

	/**
	 * Constructor.
	 *
	 * @since 1.10.1
	 *
	 * @param WP_Post|null $form Current form object, or null when unavailable.
	 */
	public function __construct( ?WP_Post $form = null ) {

		$this->form = $form;
	}

	/**
	 * Get the preview dropdown education items.
	 *
	 * Each item is an associative array with the following keys:
	 *   - slug:             Unique item identifier.
	 *   - icon:             Font Awesome class (e.g. `fa-commenting-o`).
	 *   - label:            Translated item label.
	 *   - modal_label:      Translated addon name used in the Addons Required modal.
	 *   - description:      Short item description.
	 *   - addon_slug:       WPForms addons slug (e.g. `wpforms-conversational-forms`).
	 *   - link:             Feature page URL on wpforms.com (used for demo links).
	 *   - utm_content:      UTM content string passed to the upgrade URL.
	 *   - demo_utm_content: UTM content string passed to the demo URL.
	 *   - section:          Settings panel section slug to navigate to.
	 *   - toggle_id:        ID of the settings toggle that activates the feature.
	 *   - preview_url:      Frontend preview URL once the addon is enabled.
	 *   - disabled_notice:  Notice shown in the settings section when the user
	 *                       arrives from the preview menu while the feature
	 *                       toggle is disabled.
	 *
	 * @since 1.10.1
	 *
	 * @return array
	 */
	public function get_items(): array {

		$form_id = $this->form instanceof WP_Post ? (int) $this->form->ID : 0;

		if ( isset( self::$cache[ $form_id ] ) ) {
			return self::$cache[ $form_id ];
		}

		$items = $this->build_default_items();

		/**
		 * Filter the list of Pro education items shown in the Form Builder preview dropdown.
		 *
		 * @since 1.10.1
		 *
		 * @param array        $items Education items.
		 * @param WP_Post|null $form  Current form object.
		 */
		$items = (array) apply_filters( 'wpforms_admin_builder_preview_dropdown_education_items_get_items', $items, $this->form );

		self::$cache[ $form_id ] = $items;

		return $items;
	}

	/**
	 * Build the default (unfiltered) item list.
	 *
	 * @since 1.10.1
	 *
	 * @return array
	 */
	private function build_default_items(): array {

		$lead_form_preview      = $this->get_lead_form_preview_url();
		$form_pages_preview     = $this->get_form_pages_preview_url();
		$conversational_preview = $this->get_conversational_forms_preview_url();

		return [
			[
				'slug'             => 'conversational-forms',
				'icon'             => 'fa-regular fa-message',
				'label'            => esc_html__( 'Conversational Form', 'wpforms-lite' ),
				'modal_label'      => esc_html__( 'Conversational Forms', 'wpforms-lite' ),
				'description'      => esc_html__( 'Make forms feel more natural.', 'wpforms-lite' ),
				'addon_slug'       => 'wpforms-conversational-forms',
				'link'             => 'https://wpforms.com/conversational-forms-demo/',
				'utm_medium'       => self::UTM_MEDIUM,
				'utm_content'      => 'Conversational Forms - Upgrade to Pro Button',
				'demo_utm_content' => 'Conversational Forms - View Demo Button',
				'section'          => 'conversational_forms',
				'toggle_id'        => 'wpforms-panel-field-settings-conversational_forms_enable',
				'preview_url'      => $conversational_preview,
				'disabled_notice'  => esc_html__( 'In order to preview your form as a Conversational Form, you must first enable the setting.', 'wpforms-lite' ),
			],
			[
				'slug'             => 'form-pages',
				'icon'             => 'fa-file-text-o',
				'label'            => esc_html__( 'Form Landing Page', 'wpforms-lite' ),
				'modal_label'      => esc_html__( 'Form Landing Pages', 'wpforms-lite' ),
				'description'      => esc_html__( 'Build distraction-free forms.', 'wpforms-lite' ),
				'addon_slug'       => 'wpforms-form-pages',
				'link'             => 'https://wpforms.com/form-pages-demo/',
				'utm_medium'       => self::UTM_MEDIUM,
				'utm_content'      => 'Form Pages - Upgrade to Pro Button',
				'demo_utm_content' => 'Form Pages - View Demo Button',
				'section'          => 'form_pages',
				'toggle_id'        => 'wpforms-panel-field-settings-form_pages_enable',
				'preview_url'      => $form_pages_preview,
				'disabled_notice'  => esc_html__( 'In order to preview your form as a Form Page, you must first enable the setting.', 'wpforms-lite' ),
			],
			[
				'slug'             => 'lead-forms',
				'icon'             => 'fa-bookmark-o',
				'label'            => esc_html__( 'Lead Form', 'wpforms-lite' ),
				'modal_label'      => esc_html__( 'Lead Forms', 'wpforms-lite' ),
				'description'      => esc_html__( 'Create engaging experiences.', 'wpforms-lite' ),
				'addon_slug'       => 'wpforms-lead-forms',
				'link'             => 'https://wpforms.com/templates/lead-generation-form-template/',
				'utm_medium'       => self::UTM_MEDIUM,
				'utm_content'      => 'Lead Forms - Upgrade to Pro Button',
				'demo_utm_content' => 'Lead Forms - View Demo Button',
				'section'          => 'lead_forms',
				'toggle_id'        => 'wpforms-panel-field-lead_forms-enable',
				'preview_url'      => $lead_form_preview,
				'disabled_notice'  => esc_html__( 'In order to preview your form as a Lead Form, you must first enable the setting.', 'wpforms-lite' ),
			],
		];
	}

	/**
	 * Lead Form preview URL for the current form.
	 *
	 * @since 1.10.1
	 *
	 * @return string
	 */
	private function get_lead_form_preview_url(): string {

		if ( ! $this->form instanceof WP_Post ) {
			return '';
		}

		return (string) wpforms_get_form_preview_url( $this->form->ID, true );
	}

	/**
	 * Form Pages preview URL for the current form.
	 *
	 * Matches the URL used by the "Preview Form Page" button in the Form Pages
	 * settings section (`#wpforms-form-pages-preview-form-page`). Returns an
	 * empty string when pretty permalinks are disabled, in which case the
	 * dropdown falls back to navigating to the Form Pages settings section.
	 *
	 * @since 1.10.1
	 *
	 * @return string
	 */
	private function get_form_pages_preview_url(): string {

		return $this->get_post_name_preview_url();
	}

	/**
	 * Conversational Form preview URL for the current form.
	 *
	 * Matches the URL used by the "Preview Conversational Form" button in the
	 * Conversational Forms settings section
	 * (`#wpforms-conversational-forms-preview-conversational-form`). Returns an
	 * empty string when pretty permalinks are disabled, in which case the
	 * dropdown falls back to navigating to the Conversational Forms settings
	 * section.
	 *
	 * @since 1.10.1
	 *
	 * @return string
	 */
	private function get_conversational_forms_preview_url(): string {

		return $this->get_post_name_preview_url();
	}

	/**
	 * Build a `home_url( $form->post_name )` preview URL.
	 *
	 * Shared by addons that serve the form at a dedicated frontend slug
	 * (Form Pages, Conversational Forms) and require pretty permalinks.
	 *
	 * @since 1.10.1
	 *
	 * @return string
	 */
	private function get_post_name_preview_url(): string {

		if ( ! $this->form instanceof WP_Post ) {
			return '';
		}

		if ( empty( get_option( 'permalink_structure' ) ) ) {
			return '';
		}

		return (string) home_url( $this->form->post_name );
	}

	/**
	 * Check whether a WPForms addon is installed and active.
	 *
	 * @since 1.10.1
	 *
	 * @param string $addon_slug Addon slug (e.g. `wpforms-conversational-forms`).
	 *
	 * @return bool
	 */
	public static function is_addon_active( string $addon_slug ): bool {

		if ( $addon_slug === '' ) {
			return false;
		}

		$addons = wpforms()->obj( 'addons' );

		if ( ! $addons ) {
			return false;
		}

		return $addons->is_active( $addon_slug );
	}

	/**
	 * Build the "View Demo" link for an item.
	 *
	 * @since 1.10.1
	 *
	 * @param array $item Education item.
	 *
	 * @return string
	 */
	public static function get_demo_url( array $item ): string {

		$link = $item['link'] ?? '';

		if ( $link === '' ) {
			return '';
		}

		return wpforms_utm_link(
			$link,
			$item['utm_medium'] ?? '',
			$item['demo_utm_content'] ?? ( $item['utm_content'] ?? '' )
		);
	}

	/**
	 * Reset the memoized cache.
	 *
	 * Primarily useful in tests. Callers should not rely on cache invalidation
	 * during a single request.
	 *
	 * @since 1.10.1
	 */
	public static function flush_cache(): void {

		self::$cache = [];
	}
}
