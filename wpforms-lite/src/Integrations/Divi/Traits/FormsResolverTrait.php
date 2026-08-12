<?php

namespace WPForms\Integrations\Divi\Traits;

/**
 * Trait FormsResolverTrait.
 *
 * Provides implementation for resolving WPForms forms in Divi integration.
 *
 * @since 1.9.9
 */
trait FormsResolverTrait {

	/**
	 * Get the forms the current user is entitled to view.
	 *
	 * Retrieves those forms from the database ordered by descending ID.
	 *
	 * @since 1.9.9
	 *
	 * @return array Array of WP_Post objects representing forms. Empty when the user may view no
	 *               forms, or when the form object is unavailable.
	 */
	public function get_forms(): array {

		// The list is built for the Divi Visual Builder, which runs on the frontend ( ?et_fb=1 ),
		// where the Pro owner-scoping filter ( wpforms_get_multiple_forms_args ) is not registered.
		// Authorize and scope the query here so the list never depends on the request context.
		if ( ! wpforms_current_user_can_view_forms() ) {
			return [];
		}

		$forms_obj = wpforms()->obj( 'form' );

		// Get the forms the current user is entitled to see, for the editor.
		$forms = $forms_obj ? $forms_obj->get( '', wpforms_get_viewable_forms_args( [ 'order' => 'DESC' ] ) ) : [];

		// If $forms is false, return an empty array.
		return $forms ? (array) $forms : [];
	}

	/**
	 * Get form options for the forms the current user is entitled to view.
	 *
	 * Retrieves those forms and formats them as an option array by iterating
	 * through each form and adding it to the options using add_form_in_options().
	 *
	 * @since 1.9.9
	 *
	 * @return array Array of form options.
	 */
	public function get_form_options(): array {

		$forms   = $this->get_forms();
		$options = [];

		foreach ( $forms as $form ) {
			$options = $this->add_form_in_options( $options, $form );
		}

		return $options;
	}
}
