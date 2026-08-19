<?php

namespace WPForms\Admin\Education\Builder;

use WPForms\Admin\Education\AddonsItemBase;

/**
 * Builder/Ranking education notice for choice-based fields.
 *
 * @since 2.0.0.5
 */
class Ranking extends AddonsItemBase {

	/**
	 * Indicate if the current Education feature is allowed to load.
	 *
	 * @since        2.0.0.5
	 *
	 * @return bool
	 *
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function allow_load() {

		return wpforms_is_admin_page( 'builder' ) || wp_doing_ajax();
	}

	/**
	 * Hooks.
	 *
	 * @since        2.0.0.5
	 *
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function hooks() {

		add_action( 'wpforms_field_options_after_label', [ $this, 'ranking_fields' ], 10, 2 );
	}

	/**
	 * Display the "Let People Rank Their Choices" education notice.
	 *
	 * @since        2.0.0.5
	 *
	 * @param array  $field    Field data.
	 * @param object $instance Builder instance.
	 *
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection HtmlUnknownTarget
	 */
	public function ranking_fields( $field, $instance ) {

		if ( ! in_array( $field['type'], [ 'radio', 'checkbox', 'select' ], true ) ) {
			return;
		}

		if ( ! $this->is_ranking_edu_notice_needed() ) {
			return;
		}

		$form_id         = ! empty( $instance->form_id ) ? (int) $instance->form_id : 0;
		$dismissed       = (array) get_user_meta( get_current_user_id(), 'wpforms_dismissed', true );
		$ranking_section = "builder-form-{$form_id}-field-options-ranking-notice";

		if ( ! empty( $dismissed[ 'edu-' . $ranking_section ] ) ) {
			return;
		}

		if ( $this->is_quiz_edu_notice_visible( $form_id, $dismissed ) ) {
			return;
		}

		printf(
			'<div class="wpforms-alert wpforms-alert-info wpforms-educational-alert wpforms-field-educational-alert wpforms-dismiss-container">
				<button type="button" class="wpforms-dismiss-button" title="%1$s" data-section="%2$s"></button>
				<div class="wpforms-field-edu-alert-heading">
					<h3>%3$s</h3>
					<span class="wpforms-badge wpforms-badge-sm wpforms-badge-green wpforms-badge-rounded">%4$s</span>
				</div>
				<p>%5$s</p>
				<a href="%6$s" target="_blank" rel="noopener noreferrer">%7$s</a>
			</div>',
			esc_html__( 'Dismiss this notice.', 'wpforms-lite' ),
			esc_attr( $ranking_section ),
			esc_html__( 'Let People Rank Their Choices', 'wpforms-lite' ),
			esc_html__( 'NEW', 'wpforms-lite' ),
			esc_html__( 'Want to know what your visitors really prioritize? Add a Ranking Field and let them sort choices. Perfect for surveys, product feedback, and voting.', 'wpforms-lite' ),
			esc_url( wpforms_utm_link( 'https://wpforms.com/docs/ranking-field/', 'Ranking Education', 'Ranking Documentation' ) ),
			esc_html__( 'Learn More', 'wpforms-lite' )
		);
	}

	/**
	 * Whether the ranking education notice should be shown based on the surveys-polls addon state.
	 *
	 * @since 2.0.0.5
	 *
	 * @return bool True when the notice is needed.
	 */
	private function is_ranking_edu_notice_needed(): bool {

		$addon = (array) $this->addons->get_addon( 'surveys-polls' );

		if ( empty( $addon ) || empty( $addon['action'] ) || empty( $addon['status'] ) ) {
			return false;
		}

		return ! ( $addon['status'] === 'active' && $addon['action'] !== 'upgrade' );
	}

	/**
	 * Whether the quiz education notice is currently visible (present but not yet dismissed).
	 *
	 * @since 2.0.0.5
	 *
	 * @param int   $form_id   Current form ID.
	 * @param array $dismissed User's dismissed notices keyed by section slug.
	 *
	 * @return bool
	 */
	private function is_quiz_edu_notice_visible( int $form_id, array $dismissed ): bool {

		$quiz_addon = (array) $this->addons->get_addon( 'quiz' );

		// When the quiz addon is active and requires no upgrade its education notice never renders.
		if (
			empty( $quiz_addon ) ||
			empty( $quiz_addon['action'] ) ||
			empty( $quiz_addon['status'] ) ||
			( $quiz_addon['status'] === 'active' && $quiz_addon['action'] !== 'upgrade' )
		) {
			return false;
		}

		$quiz_section = "builder-form-{$form_id}-field-options-quiz-notice";

		return empty( $dismissed[ 'edu-' . $quiz_section ] );
	}
}
