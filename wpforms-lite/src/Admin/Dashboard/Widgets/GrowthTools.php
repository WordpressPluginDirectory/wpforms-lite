<?php

namespace WPForms\Admin\Dashboard\Widgets;

use WPForms\Admin\Addons\GrowthToolTiles;
use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\WidgetState;
use WPForms\SetupWizard\Service\PluginCatalog;
use WPForms\SetupWizard\Service\PluginDetector;

/**
 * "Recommended Growth Tools" sidebar widget.
 *
 * Renders a curated grid of Awesome Motive wordpress.org plugins with an in-place
 * Install / Activate / Installed CTA. Identical on every tier; no gear menu; not dismissible.
 *
 * @since 2.0.2
 */
class GrowthTools extends AbstractWidget {

	/**
	 * Column placement.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'sidebar';

	/**
	 * Default sort position within the sidebar (below What's New).
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 30;

	/**
	 * The four plugins shown on the Dashboard (a subset of the shared catalog).
	 *
	 * @since 2.0.2
	 */
	private const PLUGINS = [
		'all-in-one-seo-pack/all_in_one_seo_pack.php',
		'universally-language-translation-multilingual-tool/universally.php',
		'duplicator/duplicator.php',
		'reviews-feed/sb-reviews.php',
	];

	/**
	 * Memoized growth-tools service.
	 *
	 * @since 2.0.2
	 *
	 * @var GrowthToolTiles|null
	 */
	private $growth_tool_tiles;

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'growth-tools';
	}

	/**
	 * Get the widget title.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_title(): string {

		return esc_html__( 'Recommended Growth Tools', 'wpforms-lite' );
	}

	/**
	 * Get the widget state — always visible, no variant, no tier variation.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_state( AccessContext $access ): WidgetState { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; the widget is tier-agnostic and always visible.

		return new WidgetState( true );
	}

	/**
	 * Render the growth-tools tile grid.
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
	protected function render_body( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; tiles are self-resolved by GrowthToolTiles.

		$tiles = $this->get_growth_tool_tiles()->get_tiles( self::PLUGINS );

		return (string) wpforms_render(
			'admin/dashboard/sidebar/growth-tools',
			[ 'tiles' => $tiles ],
			true
		);
	}

	/**
	 * Get the (memoized) growth-tools service.
	 *
	 * @since 2.0.2
	 *
	 * @return GrowthToolTiles
	 */
	private function get_growth_tool_tiles(): GrowthToolTiles {

		if ( $this->growth_tool_tiles === null ) {
			$this->growth_tool_tiles = new GrowthToolTiles( new PluginDetector(), new PluginCatalog() );
		}

		return $this->growth_tool_tiles;
	}
}
