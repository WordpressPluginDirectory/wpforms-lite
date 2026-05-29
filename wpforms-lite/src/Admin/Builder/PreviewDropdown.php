<?php

namespace WPForms\Admin\Builder;

use WPForms_Builder;
use WPForms\Admin\Addons\Addons;
use WP_Post;

/**
 * Form Builder preview dropdown class.
 *
 * Registers assets powering the split-button Preview dropdown in the
 * Form Builder toolbar, which surfaces the Standard Form Preview link
 * along with Pro product education items.
 *
 * @since 1.10.1
 */
class PreviewDropdown {

	/**
	 * Init class.
	 *
	 * @since 1.10.1
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.10.1
	 */
	private function hooks() {

		add_action( 'wpforms_builder_enqueues', [ $this, 'enqueues' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 1.10.1
	 */
	public function enqueues() {

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-builder-preview-dropdown',
			WPFORMS_PLUGIN_URL . "assets/js/admin/builder/preview-dropdown{$min}.js",
			[ 'jquery', 'wpforms-builder' ],
			WPFORMS_VERSION,
			true
		);

		// The Addons Required modal is only shown to Pro users when a
		// feature's addon is not installed or not activated.
		if ( ! wpforms()->is_pro() ) {
			return;
		}

		wp_enqueue_script(
			'wpforms-builder-preview-dropdown-education',
			WPFORMS_PLUGIN_URL . "assets/js/admin/builder/preview-dropdown-education{$min}.js",
			[ 'jquery', 'underscore', 'jquery-confirm', 'wpforms-builder', 'wpforms-admin-education-core' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-builder-preview-dropdown-education',
			'wpforms_builder_preview_dropdown_education',
			$this->get_localized_data()
		);
	}

	/**
	 * Get data passed to the preview dropdown education JS module.
	 *
	 * @since 1.10.1
	 *
	 * @return array
	 */
	private function get_localized_data(): array {

		return [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wpforms-admin' ),
			'addons'   => $this->get_addons_data(),
			'strings'  => [
				'title'            => __( 'Addons Required', 'wpforms-lite' ),
				'description'      => __( 'In order to preview your form in other formats, you need to install addons. Would you like to install them now?', 'wpforms-lite' ),
				'install_activate' => __( 'Install & Activate', 'wpforms-lite' ),
				'cancel'           => __( 'Cancel', 'wpforms-lite' ),
				'view_demo'        => __( 'View Demo', 'wpforms-lite' ),
				'installing'       => __( 'Installing', 'wpforms-lite' ),
				'activating'       => __( 'Activating', 'wpforms-lite' ),
				'no_selection'     => __( 'Please select at least one addon to install.', 'wpforms-lite' ),
				'generic_error'    => __( 'Something went wrong while installing the addons. Please try again.', 'wpforms-lite' ),
				'addon_activated'  => __( 'Addon Activated', 'wpforms-lite' ),
				'addons_activated' => __( 'Addons Activated', 'wpforms-lite' ),
			],
		];
	}

	/**
	 * Get addons data for the Addons Required modal.
	 *
	 * Combines the preview dropdown education items with live addon status
	 * (installed/active) so the JS can render checkboxes and drive installation.
	 *
	 * @since 1.10.1
	 *
	 * @return array
	 */
	private function get_addons_data(): array {

		$form       = $this->get_current_form();
		$items      = ( new PreviewDropdownEducationItems( $form ) )->get_items();
		$addons_obj = wpforms()->obj( 'addons' );
		$data       = [];

		foreach ( $items as $item ) {
			$prepared = $this->prepare_addon_item_data( $item, $addons_obj );

			if ( $prepared === [] ) {
				continue;
			}

			$data[] = $prepared;
		}

		return $data;
	}

	/**
	 * Resolve the current builder form from the Builder singleton.
	 *
	 * @since 1.10.1
	 *
	 * @return WP_Post|null
	 */
	private function get_current_form(): ?WP_Post {

		if ( ! class_exists( WPForms_Builder::class ) ) {
			return null;
		}

		$form = WPForms_Builder::instance()->form ?? null;

		return $form instanceof WP_Post ? $form : null;
	}

	/**
	 * Merge a single preview dropdown item with its live addon status.
	 *
	 * @since 1.10.1
	 *
	 * @param array       $item       Education item (see PreviewDropdownEducationItems::get_items()).
	 * @param Addons|null $addons_obj WPForms addons handler, or null if unavailable.
	 *
	 * @return array Data consumable by the education JS, or empty array when the item has no addon slug.
	 */
	private function prepare_addon_item_data( array $item, $addons_obj ): array {

		$addon_slug = $item['addon_slug'] ?? '';

		if ( $addon_slug === '' ) {
			return [];
		}

		$addon  = $addons_obj ? $addons_obj->get_addon( $addon_slug ) : [];
		$status = $addon['status'] ?? 'missing';

		return [
			'slug'         => $addon_slug,
			'name'         => $this->get_addon_item_name( $item ),
			'icon'         => $item['icon'] ?? '',
			'demo_url'     => PreviewDropdownEducationItems::get_demo_url( $item ),
			'install_url'  => $addon['url'] ?? '',
			'path'         => $addon['path'] ?? '',
			'section'      => $item['section'] ?? '',
			'status'       => $status,
			'is_active'    => $status === 'active',
			'is_installed' => in_array( $status, [ 'active', 'installed' ], true ),
		];
	}

	/**
	 * Resolve the user-facing name for an addon education item.
	 *
	 * Falls back to the regular label when a modal-specific label is not provided.
	 *
	 * @since 1.10.1
	 *
	 * @param array $item Education item (see PreviewDropdownEducationItems::get_items()).
	 *
	 * @return string
	 */
	private function get_addon_item_name( array $item ): string {

		return $item['modal_label'] ?? ( $item['label'] ?? '' );
	}
}
