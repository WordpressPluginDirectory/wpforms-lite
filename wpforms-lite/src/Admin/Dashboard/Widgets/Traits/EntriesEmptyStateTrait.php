<?php

namespace WPForms\Admin\Dashboard\Widgets\Traits;

/**
 * Empty-state rendering for the Dashboard "Forms" widget: the heading, copy,
 * action buttons, and the decorative sample chart drawn behind them.
 *
 * Self-contained -- requires nothing from the using class.
 *
 * @since 2.0.2
 */
trait EntriesEmptyStateTrait {

	/**
	 * Render the empty ("No Forms") state: a centered card over a muted graph
	 * backdrop. Each tier passes its own copy and buttons.
	 *
	 * @since 2.0.2
	 *
	 * @param string $heading     Card heading.
	 * @param string $description Card body copy.
	 * @param array  $buttons     Ordered action buttons (label, url, classes, target, attrs).
	 *
	 * @return string
	 */
	protected function render_empty_state( string $heading, string $description, array $buttons ): string {

		return (string) wpforms_render(
			'admin/dashboard/widgets/entries-empty',
			[
				'heading'       => $heading,
				'description'   => $description,
				'buttons'       => $buttons,
				'preview_graph' => $this->get_sample_graph(),
			],
			true
		);
	}

	/**
	 * Sample entries series for the empty-state backdrop. Dated relative to today
	 * so the axis always reads as a recent month.
	 *
	 * @since 2.0.2
	 *
	 * @return array Sparse `{ date, count }` points.
	 */
	private function get_sample_graph(): array {

		$counts = [ 0, 62, 53, 64, 71, 30, 0 ];
		$today  = date_create_immutable( 'now', wp_timezone() );
		$points = [];

		foreach ( $counts as $offset => $count ) {
			$points[] = [
				'date'  => $today->modify( sprintf( '-%d days', count( $counts ) - $offset - 1 ) )->format( 'Y-m-d' ),
				'count' => $count,
			];
		}

		return $points;
	}

	/**
	 * Build the empty-state action buttons. The labels arrive already localized
	 * so each tier passes its own text domain.
	 *
	 * @since 2.0.2
	 *
	 * @param string $create_label Primary "Create a Form" button label.
	 * @param string $import_label Secondary import button label.
	 *
	 * @return array
	 */
	protected function get_empty_state_ctas( string $create_label, string $import_label ): array {

		return [
			[
				'label'   => $create_label,
				'url'     => admin_url( 'admin.php?page=wpforms-builder' ),
				'classes' => 'wpforms-btn wpforms-btn-md wpforms-btn-orange',
				'target'  => '',
				'attrs'   => [],
			],
			[
				'label'   => $import_label,
				'url'     => admin_url( 'admin.php?page=wpforms-tools&view=import' ),
				'classes' => 'wpforms-btn wpforms-btn-md wpforms-btn-orange-outline',
				'target'  => '',
				'attrs'   => [],
			],
		];
	}
}
