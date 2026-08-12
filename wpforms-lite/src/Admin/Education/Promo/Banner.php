<?php

namespace WPForms\Admin\Education\Promo;

use WPForms\Admin\Education\EducationInterface;

/**
 * Base class for cross-promo banners.
 *
 * A banner promotes an external plugin with a dismissible notice and a
 * one-click install CTA. Concrete banners supply the copy, the placement
 * hooks, the pages they belong to, and the promoted-plugin state; this base
 * class owns the shared gates (installed check, capability, dismissal),
 * the template rendering, and the shared assets.
 *
 * @since 2.0.0.3
 */
abstract class Banner implements EducationInterface {

	/**
	 * Shared script handle.
	 *
	 * @since 2.0.0.3
	 */
	const SCRIPT_HANDLE = 'wpforms-education-promo-banner';

	/**
	 * Unique banner id (kebab-case, used in data attributes and template ids).
	 *
	 * @since 2.0.0.3
	 *
	 * @return string
	 */
	abstract protected function get_banner_id(): string;

	/**
	 * Education dismissal section slug (stored as `edu-<slug>` in user meta).
	 *
	 * @since 2.0.0.3
	 *
	 * @return string
	 */
	abstract protected function get_dismiss_section(): string;

	/**
	 * Arguments for the promo-banner template.
	 *
	 * @since 2.0.0.3
	 *
	 * @return array
	 */
	abstract protected function get_render_args(): array;

	/**
	 * Whether the promoted plugin is installed (Lite or Pro, active or not).
	 *
	 * @since 2.0.0.3
	 *
	 * @return bool
	 */
	abstract protected function is_plugin_installed(): bool;

	/**
	 * Register hooks shared by all banners.
	 *
	 * Descendants override this, call `parent::hooks()`, and add their
	 * placement-specific hooks.
	 *
	 * @since 2.0.0.3
	 */
	protected function hooks(): void {

		add_action( $this->get_enqueue_hook(), [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Whether the current admin page hosts this banner.
	 *
	 * @since 2.0.0.3
	 *
	 * @return bool
	 */
	protected function is_allowed_page(): bool {

		return wpforms_is_admin_page( 'builder' );
	}

	/**
	 * Hook the shared assets are enqueued on.
	 *
	 * `hooks()` runs only after `is_allowed_page()` passed, so any other page here is
	 * one the placement declared itself, and outside the builder there is no builder
	 * enqueue hook to use.
	 *
	 * @since 2.0.0.3
	 *
	 * @return string
	 */
	protected function get_enqueue_hook(): string {

		return wpforms_is_admin_page( 'builder' ) ? 'wpforms_builder_enqueues' : 'admin_enqueue_scripts';
	}

	/**
	 * Indicate whether the banner is allowed to load.
	 *
	 * @since 2.0.0.3
	 *
	 * @return bool
	 */
	public function allow_load(): bool {

		// Activation is required too: the install AJAX succeeds without it, and the user would be
		// left with an installed but inactive plugin and nothing to set up.
		// Ordered cheapest first: the installed check scans the plugins directory.
		return $this->is_allowed_page()
			&& ! $this->is_dismissed()
			&& wpforms_can_install( 'plugin' )
			&& wpforms_can_activate( 'plugin' )
			&& ! $this->is_plugin_installed();
	}

	/**
	 * Init.
	 *
	 * @since 2.0.0.3
	 */
	public function init(): void {

		if ( ! $this->allow_load() ) {
			return;
		}

		$this->hooks();
	}

	/**
	 * Whether the current user has dismissed this banner.
	 *
	 * @since 2.0.0.3
	 *
	 * @return bool
	 */
	protected function is_dismissed(): bool {

		$dismissed = (array) get_user_meta( get_current_user_id(), 'wpforms_dismissed', true );

		return ! empty( $dismissed[ 'edu-' . $this->get_dismiss_section() ] );
	}

	/**
	 * Render the banner markup.
	 *
	 * @since 2.0.0.3
	 *
	 * @return string
	 */
	protected function render_banner(): string {

		$args = $this->get_render_args();

		$args['banner_id']       = $this->get_banner_id();
		$args['dismiss_section'] = $this->get_dismiss_section();

		return wpforms_render( 'education/promo-banner', $args, true );
	}

	/**
	 * Enqueue the shared promo banner script.
	 *
	 * Every banner calls this, so both the enqueue and the localized payload are
	 * guarded: `ajax_url` comes from the `wpforms_education` object of the
	 * `wpforms-admin-education-core` dependency, and the only value left here is
	 * the install label, which is the same for all placements.
	 *
	 * @since 2.0.0.3
	 */
	public function enqueue_assets(): void {

		if ( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			WPFORMS_PLUGIN_URL . "assets/js/admin/education/promo-banner{$min}.js",
			[ 'jquery', 'wp-util', 'wpforms-admin-education-core' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'wpforms_education_promo',
			[
				'installing' => esc_html__( 'Installing...', 'wpforms-lite' ),
				// Fallback for a failed install: the `addon_error` string of the education core
				// is Pro only and speaks about addons, and the request may carry no message.
				'error'      => esc_html__( 'Could not install the plugin. Please try again or install it manually.', 'wpforms-lite' ),
			]
		);
	}
}
