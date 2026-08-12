<?php

namespace WPForms\Admin\Education\Promo\SugarCalendar;

use WPForms\Education\SugarCalendar\Helper;

/**
 * Sugar Calendar promo banner at the top of the Fields panel preview,
 * shown for forms created from an individual event template.
 *
 * Not shown when the form was created from the Event Planning category
 * view — the user has already seen the templates grid banner there
 * (issue #18310: "banners should not be sequential").
 *
 * @since 2.0.0.3
 */
class FieldsPanel extends Banner {

	/**
	 * Unique banner id, product qualified so other products can add their own placements.
	 *
	 * @since 2.0.0.3
	 *
	 * @return string
	 */
	protected function get_banner_id(): string {

		return 'sugar-calendar-fields-panel';
	}

	/**
	 * Dismissal section slug.
	 *
	 * @since 2.0.0.3
	 *
	 * @return string
	 */
	protected function get_dismiss_section(): string {

		return 'promo-sugar-calendar-fields';
	}

	/**
	 * Placement hooks.
	 *
	 * @since 2.0.0.3
	 */
	protected function hooks(): void {

		parent::hooks();

		add_action( 'wpforms_builder_panel_fields_panel_content_title_after', [ $this, 'maybe_render_banner' ] );
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
			'heading' => __( 'Publishing an Event?', 'wpforms-lite' ),
			'body'    => sprintf( /* translators: %1$s - opening link tag, %2$s - closing link tag. */
				__( 'Your form collects the sign-ups, %1$sSugar Calendar Events%2$s handles the event around it: public calendar, RSVPs, tickets, and reminders.', 'wpforms-lite' ),
				'<a href="' . esc_url( Helper::WPORG_URL ) . '" class="wpforms-link-orange" target="_blank" rel="noopener noreferrer">',
				'</a>'
			),
		];
	}

	/**
	 * Render the banner when the form was created from a targeted template.
	 *
	 * @since 2.0.0.3
	 *
	 * @param array $form_data Form data and settings.
	 */
	public function maybe_render_banner( array $form_data ): void {

		if ( $this->is_coming_from_category( $form_data ) || ! $this->is_targeted_template( $form_data ) ) {
			return;
		}

		echo $this->render_banner(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
	}

	/**
	 * Whether the user just picked this form's template inside a targeted category.
	 *
	 * Only true for the view the builder redirects to right after the template was
	 * picked, so the rule stops applying as soon as the form is opened again: the
	 * stored category alone would suppress the banner forever (issue #18310).
	 *
	 * @since 2.0.0.3
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	private function is_coming_from_category( array $form_data ): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['newform'] ) ) {
			return false;
		}

		return in_array( $form_data['meta']['category'] ?? '', Helper::TEMPLATES_CATEGORIES, true );
	}

	/**
	 * Whether the form's source template belongs to one of the targeted categories.
	 *
	 * Reads the already built templates list instead of `get_template()`: the latter
	 * fetches the single-template cache, which costs file and database reads and
	 * schedules a usage-tracking request we would only throw away.
	 *
	 * @since 2.0.0.3
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	private function is_targeted_template( array $form_data ): bool {

		$slug = (string) ( $form_data['meta']['template'] ?? '' );

		if ( $slug === '' ) {
			return false;
		}

		foreach ( wpforms()->obj( 'builder_templates' )->get_templates() as $key => $template ) {
			if ( $slug !== ( $template['id'] ?? '' ) && $slug !== ( $template['slug'] ?? $key ) ) {
				continue;
			}

			$categories = (array) ( $template['categories'] ?? [] );

			// Remote templates hold a list of slugs, the built-in ones a slug => label map.
			$categories = $categories === array_values( $categories ) ? $categories : array_keys( $categories );

			return (bool) array_intersect( Helper::TEMPLATES_CATEGORIES, $categories );
		}

		return false;
	}
}
