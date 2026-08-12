<?php

namespace WPForms\Admin\Education\Promo\SugarCalendar;

use WPForms\Admin\Education\Promo\Banner as PromoBanner;
use WPForms\Education\SugarCalendar\Helper;

/**
 * Shared base for the Sugar Calendar promo banner placements.
 *
 * Everything the promoted product owns lives here, so a placement only supplies
 * its own copy and its own placement hooks.
 *
 * @since 2.0.0.3
 */
abstract class Banner extends PromoBanner {

	/**
	 * Copy of the placement: the `heading` and the `body` of the banner.
	 *
	 * @since 2.0.0.3
	 *
	 * @return array
	 */
	abstract protected function get_copy(): array;

	/**
	 * Whether Sugar Calendar (Lite or Pro) is installed.
	 *
	 * @since 2.0.0.3
	 *
	 * @return bool
	 */
	protected function is_plugin_installed(): bool {

		return Helper::is_installed();
	}

	/**
	 * Template arguments.
	 *
	 * @since 2.0.0.3
	 *
	 * @return array
	 */
	protected function get_render_args(): array {

		return array_merge(
			[
				'icon'         => 'fa-regular fa-calendar',
				'badge'        => __( 'Free', 'wpforms-lite' ),
				'cta_text'     => __( 'Install Sugar Calendar', 'wpforms-lite' ),
				'setup_text'   => __( 'Set Up Sugar Calendar', 'wpforms-lite' ),
				'setup_url'    => Helper::get_setup_url(),
				'button_attrs' => Helper::get_install_button_data(),
			],
			$this->get_copy()
		);
	}
}
