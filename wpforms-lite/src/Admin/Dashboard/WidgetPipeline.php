<?php

namespace WPForms\Admin\Dashboard;

/**
 * Dashboard widget pipeline.
 *
 * Evaluates widget states once at init and renders per-column widget HTML.
 *
 * @since 2.0.2
 */
class WidgetPipeline {

	/**
	 * Prepared widgets per column.
	 *
	 * Shape: [ 'main' => [ [ 'widget' => AbstractWidget, 'variant' => string ], ... ], 'sidebar' => [ ... ] ].
	 *
	 * @since 2.0.2
	 *
	 * @var array
	 */
	private $widgets = [];

	/**
	 * Initialize the pipeline.
	 *
	 * @since 2.0.2
	 */
	public function init(): void {

		$this->init_widgets();
	}

	/**
	 * Evaluate widget states and group visible widgets per column, sorted.
	 *
	 * @since 2.0.2
	 */
	private function init_widgets(): void {

		$access_resolver = wpforms()->obj( 'dashboard_access_resolver' );

		if ( ! $access_resolver ) {
			return;
		}

		$access = $access_resolver->get_context();
		$items  = [];

		foreach ( $this->get_widgets() as $widget ) {
			$state = $widget->get_state( $access );

			if ( ! $state->is_visible() ) {
				continue;
			}

			$items[] = [
				'widget'  => $widget,
				'variant' => $state->get_variant(),
				'order'   => $state->get_order_override() ?? $widget::ORDER,
				'column'  => $widget->get_column(),
			];
		}

		usort(
			$items,
			static function ( $a, $b ) {

				return $a['order'] <=> $b['order'];
			}
		);

		foreach ( $items as $item ) {
			$this->widgets[ $item['column'] ][] = [
				'widget'  => $item['widget'],
				'variant' => $item['variant'],
			];
		}
	}

	/**
	 * Get the widget instances from the registry.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_widgets(): array {

		$ids = [
			'dashboard_widget_entries',
			'dashboard_widget_locations',
			'dashboard_widget_whats_new',
			'dashboard_widget_license',
			'dashboard_widget_payments',
			'dashboard_widget_wp_mail_smtp',
			'dashboard_widget_features_addons',
			'dashboard_widget_integrations',
			'dashboard_widget_growth_tools',
			'dashboard_widget_spam_security',
			'dashboard_widget_getting_started',
		];

		/**
		 * Filters the Dashboard widget IDs to render.
		 *
		 * @since 2.0.2
		 *
		 * @param array $ids Widget registry IDs.
		 */
		$ids = (array) apply_filters( 'wpforms_admin_dashboard_widget_pipeline_get_widgets', $ids );

		return array_filter( array_map( [ wpforms(), 'obj' ], $ids ) );
	}

	/**
	 * Render all visible widgets of a column into HTML.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $column Column: 'main' or 'sidebar'.
	 * @param array         $data   Aggregated data.
	 * @param AccessContext $access Access context.
	 *
	 * @return string
	 */
	public function render_column( string $column, array $data, AccessContext $access ): string {

		$html = '';

		foreach ( $this->widgets[ $column ] ?? [] as $item ) {
			$html .= $item['widget']->render( $item['variant'], $data, $access );
		}

		return $html;
	}
}
