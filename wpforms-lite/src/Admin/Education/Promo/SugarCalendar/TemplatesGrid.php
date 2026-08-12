<?php

namespace WPForms\Admin\Education\Promo\SugarCalendar;

use WPForms\Education\SugarCalendar\Helper;

/**
 * Sugar Calendar promo banner above the templates grid, shown while one of
 * the targeted template categories is selected.
 *
 * The same grid is rendered in the builder Setup panel and on the admin
 * Templates page, so the banner covers both, with a shared dismissal.
 *
 * The markup is printed as an Underscore template and injected by JS on
 * category switch: the grid HTML itself is cached site-wide
 * (TemplatesCache content file + batch transients), so per-user banner
 * state must never be rendered into it.
 *
 * @since 2.0.0.3
 */
class TemplatesGrid extends Banner {

	/**
	 * Unique banner id, product qualified so other products can add their own placements.
	 *
	 * @since 2.0.0.3
	 *
	 * @return string
	 */
	protected function get_banner_id(): string {

		return 'sugar-calendar-templates-grid';
	}

	/**
	 * Dismissal section slug.
	 *
	 * @since 2.0.0.3
	 *
	 * @return string
	 */
	protected function get_dismiss_section(): string {

		return 'promo-sugar-calendar-templates';
	}

	/**
	 * Whether the current admin page renders the templates grid.
	 *
	 * @since 2.0.0.3
	 *
	 * @return bool
	 */
	protected function is_allowed_page(): bool {

		return parent::is_allowed_page() || wpforms_is_admin_page( 'templates' );
	}

	/**
	 * Placement hooks.
	 *
	 * @since 2.0.0.3
	 */
	protected function hooks(): void {

		parent::hooks();

		// Same hook the templates upgrade banner uses to print its Underscore template.
		add_action( 'admin_print_scripts', [ $this, 'print_banner_template' ] );
	}

	/**
	 * Banner copy.
	 *
	 * @since 2.0.0.3
	 *
	 * @return array
	 */
	protected function get_copy(): array {

		return [
			'heading' => __( 'Building an Event Form? Add a Calendar to Match', 'wpforms-lite' ),
			'body'    => sprintf( /* translators: %1$s - opening link tag, %2$s - closing link tag. */
				__( 'A registration form is a great start. %1$sSugar Calendar Events%2$s runs the event itself. Publish it on a calendar people can find, take RSVPs or sell tickets, and remind everyone who signed up.', 'wpforms-lite' ),
				'<a href="' . esc_url( Helper::WPORG_URL ) . '" class="wpforms-link-orange" target="_blank" rel="noopener noreferrer">',
				'</a>'
			),
		];
	}

	/**
	 * Print the banner as an Underscore template for JS injection.
	 *
	 * The target categories travel on the tag instead of a localized global, so any
	 * number of products can print their own template without coordinating payloads.
	 * The install URL identifies the product, so the banner is not injected anymore
	 * once it was installed from another placement in the same session.
	 *
	 * @since 2.0.0.3
	 */
	public function print_banner_template(): void {

		printf(
			'<script type="text/html" id="tmpl-wpforms-education-promo-banner-%1$s" data-promo-banner-id="%1$s" data-promo-categories="%2$s" data-promo-install-url="%3$s">%4$s</script>',
			esc_attr( $this->get_banner_id() ),
			esc_attr( implode( ',', Helper::TEMPLATES_CATEGORIES ) ),
			esc_attr( Helper::INSTALL_ZIP_URL ),
			$this->render_banner() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
		);
	}
}
