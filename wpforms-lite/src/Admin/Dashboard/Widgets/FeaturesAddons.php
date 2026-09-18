<?php

namespace WPForms\Admin\Dashboard\Widgets;

use WPForms\Admin\Addons\FeatureTiles;
use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\WidgetState;
use WPForms\SetupWizard\Service\PluginCatalog;
use WPForms\SetupWizard\Service\PluginDetector;

/**
 * "Take Your Forms to the Next Level" Dashboard widget.
 *
 * Renders the shared feature-promo tiles (tier-aware Upgrade / Install CTA) in the main
 * column, followed by a "View All Addons" link. Always visible; no gear menu; not dismissible.
 *
 * @since 2.0.2
 */
class FeaturesAddons extends AbstractWidget {

	/**
	 * Column placement.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'main';

	/**
	 * Default sort position within the main column (below the data widgets).
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 40;

	/**
	 * Memoized feature-tiles service.
	 *
	 * @since 2.0.2
	 *
	 * @var FeatureTiles|null
	 */
	private $feature_tiles;

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'features-addons';
	}

	/**
	 * Get the (memoized) feature-tiles service.
	 *
	 * @since 2.0.2
	 *
	 * @return FeatureTiles
	 */
	private function get_feature_tiles(): FeatureTiles {

		if ( $this->feature_tiles === null ) {
			$this->feature_tiles = new FeatureTiles( new PluginDetector(), new PluginCatalog() );
		}

		return $this->feature_tiles;
	}

	/**
	 * Get the widget title.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_title(): string {

		return __( 'Take Your Forms to the Next Level', 'wpforms-lite' );
	}

	/**
	 * Get the widget state — always visible, no variant.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_state( AccessContext $access ): WidgetState { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; this widget is always visible.

		return new WidgetState( true );
	}

	/**
	 * Render the feature-tile grid.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; tiles are tier-resolved by FeatureTiles.

		$tiles = $this->get_feature_tiles()->get_tiles( 'Dashboard - Addons' );
		$html  = '<div class="wpforms-addon-tile-grid">';

		foreach ( $tiles as $tile ) {
			$html .= (string) wpforms_render( 'admin/addons/feature-tile', [ 'tile' => $tile ], true );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render the footer: descriptive copy + the "View All Addons" CTA button.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_footer( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; the CTA target is tier-derived.

		$is_pro_plus = $this->get_feature_tiles()->is_pro_plus();

		$url = $is_pro_plus
			? admin_url( 'admin.php?page=wpforms-addons' )
			: wpforms_utm_link( 'https://wpforms.com/features/', 'Dashboard - Addons', 'View All Addons' );

		return sprintf(
			'<p class="wpforms-dashboard-widget-features-addons-footer-text">%1$s</p><a class="wpforms-btn wpforms-btn-md wpforms-btn-orange wpforms-dashboard-widget-features-addons-view-all" href="%2$s"%3$s>%4$s</a>',
			esc_html__( 'Plus dozens of other powerful features and addons to meet all your form-building needs.', 'wpforms-lite' ),
			esc_url( $url ),
			$is_pro_plus ? '' : ' target="_blank" rel="noopener noreferrer"',
			esc_html__( 'View All Addons', 'wpforms-lite' )
		);
	}
}
