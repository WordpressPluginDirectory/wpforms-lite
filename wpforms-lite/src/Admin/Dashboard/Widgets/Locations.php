<?php

namespace WPForms\Admin\Dashboard\Widgets;

use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\WidgetState;

/**
 * Dashboard "Top Locations" widget.
 *
 * Lite base: the educational "Upgrade to Pro" upsell over a blurred example
 * preview. The Pro subclass adds the entitled install prompt and the live
 * geolocation table + donut chart.
 *
 * @since 2.0.2
 */
class Locations extends AbstractWidget {

	/**
	 * Column placement.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'main';

	/**
	 * Default sort position — below the Addons widget (#40), where the design places the
	 * promo states. Moves to 25 in the Pro data state so the filled widget sits above
	 * Addons and Integrations.
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 45;

	/**
	 * Geolocation addon documentation page ("Learn More").
	 *
	 * @since 2.0.2
	 */
	protected const DOC_URL = 'https://wpforms.com/docs/how-to-install-and-use-the-geolocation-addon-with-wpforms/';

	/**
	 * Segment palette for the donut and the matching table dots. Values mirror
	 * the `$blue-30 / $green-30 / $yellow-50 / $orange-30 / $red-30` variables in
	 * `assets/scss/admin/_colors.scss`; a 6th+ country cycles the palette. Shared
	 * by the Pro data render and the Lite/install sample preview.
	 *
	 * @since 2.0.2
	 */
	protected const PALETTE = [ '#3788bd', '#00ba37', '#dba617', '#e79055', '#f86368' ];

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'locations';
	}

	/**
	 * Get the widget title.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_title(): string {

		return esc_html__( 'Top Locations', 'wpforms-lite' );
	}

	/**
	 * Resolve the widget state.
	 *
	 * Lite is never entitled to the Geolocation addon, so the base always shows
	 * the education upsell. The Pro subclass overrides this.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Part of the widget contract; the Lite base is always the education upsell.

		return new WidgetState( true, 'education' );
	}

	/**
	 * Render the widget head — title plus the example-data disclaimer (empty
	 * states) or the settings cog (Pro data state).
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data (unused).
	 * @param AccessContext $access  Access context (unused).
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_head( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $data and $access are part of the contract signature.

		// Data state: let the shell render the plain title + the framework gear (driven
		// by the settings schema). Empty states: title + the example-data disclaimer.
		if ( $variant === 'data' ) {
			return '';
		}

		return (string) wpforms_render(
			'admin/dashboard/widgets/locations-head',
			[
				'title'      => $this->get_title(),
				'disclaimer' => __( 'The data shown below is for example purposes only.', 'wpforms-lite' ),
			],
			true
		);
	}

	/**
	 * Render the widget body — the shared education/install empty state.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data (unused).
	 * @param AccessContext $access  Access context (unused).
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $variant/$data/$access are the contract; the Lite base only renders the education CTA.

		return $this->render_empty_state(
			__( 'Geolocation', 'wpforms-lite' ),
			__( 'Quickly see where in the world your visitors are located and gather stats and trends.', 'wpforms-lite' ),
			[ $this->get_education_cta(), $this->get_learn_more_cta() ]
		);
	}

	/**
	 * Render the shared empty-state body: a live sample table + donut (static
	 * preview) behind a centered card with a heading, body copy, and action buttons.
	 * Shared by the education and install states; the Pro no-data state renders the
	 * framework notice instead, so it never shows sample data.
	 *
	 * @since 2.0.2
	 *
	 * @param string $heading     Card heading.
	 * @param string $description Card body copy.
	 * @param array  $ctas        Ordered action buttons (label, url, classes, target, attrs).
	 *
	 * @return string
	 */
	protected function render_empty_state( string $heading, string $description, array $ctas ): string {

		$preview_html = (string) wpforms_render(
			'admin/dashboard/widgets/locations-chart',
			array_merge( $this->get_sample_view_data(), [ 'is_preview' => true ] ),
			true
		);

		return (string) wpforms_render(
			'admin/dashboard/widgets/locations',
			[
				'preview_html' => $preview_html,
				'heading'      => $heading,
				'description'  => $description,
				'ctas'         => $ctas,
			],
			true
		);
	}

	/**
	 * Sample "Top Locations" data for the education / install preview — a static,
	 * clearly illustrative dataset (the head carries the "example only" note). The
	 * shape matches the live view data so the shared template + JS render it the
	 * same way.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	protected function get_sample_view_data(): array {

		$rows = [
			[
				'code'     => 'US',
				'name'     => __( 'United States', 'wpforms-lite' ),
				'share'    => 40,
				'visitors' => 34,
			],
			[
				'code'     => 'CA',
				'name'     => __( 'Canada', 'wpforms-lite' ),
				'share'    => 31,
				'visitors' => 27,
			],
			[
				'code'     => 'AU',
				'name'     => __( 'Australia', 'wpforms-lite' ),
				'share'    => 14,
				'visitors' => 12,
			],
			[
				'code'     => 'GB',
				'name'     => __( 'United Kingdom', 'wpforms-lite' ),
				'share'    => 8,
				'visitors' => 7,
			],
			[
				'code'     => 'IE',
				'name'     => __( 'Ireland', 'wpforms-lite' ),
				'share'    => 7,
				'visitors' => 6,
			],
		];

		return [
			'countries'     => $this->apply_palette( $rows ),
			'all_countries' => $rows,
			'donut_total'   => 86,
			'settings'      => [
				'number_of_countries' => count( $rows ),
				'excluded'            => [],
			],
			'palette'       => self::PALETTE,
		];
	}

	/**
	 * Assign each row a palette color by its position, cycling the palette. Shared
	 * by the Lite/install sample preview and the Pro live data build.
	 *
	 * @since 2.0.2
	 *
	 * @param array $rows Country rows to color.
	 *
	 * @return array Rows with a `color` key added.
	 */
	protected function apply_palette( array $rows ): array {

		$colored = [];

		foreach ( array_values( $rows ) as $index => $row ) {
			$row['color'] = self::PALETTE[ $index % count( self::PALETTE ) ];
			$colored[]    = $row;
		}

		return $colored;
	}

	/**
	 * The education CTA — "Upgrade to Pro".
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_education_cta(): array {

		return [
			'label'   => __( 'Upgrade to Pro', 'wpforms-lite' ),
			'url'     => wpforms_admin_upgrade_link( 'Dashboard - Top Locations', 'Upgrade to Pro' ),
			'classes' => 'wpforms-btn wpforms-btn-md wpforms-btn-blue',
			'target'  => '_blank',
			'attrs'   => [],
		];
	}

	/**
	 * The secondary "Learn More" button shared by the education and install cards.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	protected function get_learn_more_cta(): array {

		return [
			'label'   => __( 'Learn More', 'wpforms-lite' ),
			'url'     => wpforms_utm_link( static::DOC_URL, 'Dashboard - Top Locations', 'Geolocation Documentation' ),
			'classes' => 'wpforms-btn wpforms-btn-md wpforms-btn-blue-outline',
			'target'  => '_blank',
			'attrs'   => [],
		];
	}
}
