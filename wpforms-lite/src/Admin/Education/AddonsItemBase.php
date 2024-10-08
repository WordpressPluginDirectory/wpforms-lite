<?php

namespace WPForms\Admin\Education;

/**
 * Base class for all "addon item" type Education features.
 *
 * @since 1.6.6
 */
abstract class AddonsItemBase implements EducationInterface {

	/**
	 * Instance of the Education\Core class.
	 *
	 * @since 1.6.6
	 *
	 * @var \WPForms\Admin\Education\Core
	 */
	protected $education;

	/**
	 * Instance of the Education\Addons class.
	 *
	 * @since 1.6.6
	 *
	 * @var \WPForms\Admin\Addons\Addons
	 */
	protected $addons;

	/**
	 * Template name for rendering single addon item.
	 *
	 * @since 1.6.6
	 *
	 * @var string
	 */
	protected $single_addon_template;

	/**
	 * Indicate if current Education feature is allowed to load.
	 * Should be called from the child feature class.
	 *
	 * @since 1.6.6
	 *
	 * @return bool
	 */
	abstract public function allow_load();

	/**
	 * Init.
	 *
	 * @since 1.6.6
	 */
	public function init() {

		if ( ! $this->allow_load() ) {
			return;
		}

		// Store the instance of the Education core class.
		$this->education = wpforms()->obj( 'education' );

		// Store the instance of the Education\Addons class.
		$this->addons = wpforms()->obj( 'addons' );

		// Define hooks.
		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.6.6
	 */
	abstract public function hooks();

	/**
	 * Display single addon item.
	 *
	 * @since 1.6.6
	 *
	 * @param array $addon Addon data.
	 */
	protected function display_single_addon( $addon ) {

		/**
		 * Filter to disallow addons to be displayed in the Education feature.
		 *
		 * @since 1.8.2
		 *
		 * @param bool   $display Whether to hide the addon.
		 * @param array  $slug    Addon data.
		 */
		$is_disallowed = (bool) apply_filters( 'wpforms_admin_education_addons_item_base_display_single_addon_hide', false, $addon );

		if ( empty( $addon ) || $is_disallowed ) {
			return;
		}

		echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$this->single_addon_template,
			$addon,
			true
		);
	}
}
