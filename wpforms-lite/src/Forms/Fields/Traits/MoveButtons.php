<?php

namespace WPForms\Forms\Fields\Traits;

/**
 * Trait MoveButtons.
 *
 * Markup for the field preview move buttons.
 *
 * @since 2.0.2
 */
trait MoveButtons {

	/**
	 * Generate the field move buttons HTML.
	 *
	 * @since 2.0.2
	 *
	 * @return string Move buttons HTML.
	 */
	protected function get_move_buttons_html(): string {

		// The toolbar uses a roving tabindex: the FieldMover module assigns tabindex="0" to a single button.
		return sprintf(
			'<a href="#" role="button" tabindex="-1" class="wpforms-field-move-up" title="%1$s" aria-label="%1$s"><i class="fa fa-angle-up" aria-hidden="true"></i></a>',
			esc_attr__( 'Move Field Up', 'wpforms-lite' )
		) . sprintf(
			'<a href="#" role="button" tabindex="-1" class="wpforms-field-move-down" title="%1$s" aria-label="%1$s"><i class="fa fa-angle-down" aria-hidden="true"></i></a>',
			esc_attr__( 'Move Field Down', 'wpforms-lite' )
		);
	}
}
