<?php

namespace WPForms\SetupChecklist;

use WPForms\Admin\Addons\FeatureTiles;
use WPForms\Admin\Addons\GrowthToolTiles;
use WPForms\Admin\Addons\InstallLinks;
use WPForms\Admin\Addons\IntegrationCards;
use WPForms\SetupWizard\Service\PluginCatalog;
use WPForms\SetupWizard\Service\PluginDetector;

/**
 * Promo sections model for the Setup Checklist.
 *
 * Owns the static recommended growth tools catalog, delegating the feature tiles
 * catalog to {@see FeatureTiles} and the integrations catalog to {@see IntegrationCards},
 * plus the one-click install/activate CTA that the checklist's item buttons share.
 * {@see Page} renders what this returns.
 *
 * @since 2.0.0
 */
class Promos {

	use InstallLinks;

	/**
	 * Plugin detector (installed/active status for recommended plugins).
	 *
	 * @since 2.0.0
	 *
	 * @var PluginDetector
	 */
	private $plugin_detector;

	/**
	 * Plugin catalog (resolves slugs to addon plugin files and display names).
	 *
	 * @since 2.0.0
	 *
	 * @var PluginCatalog
	 */
	private $plugin_catalog;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param PluginDetector $plugin_detector Plugin detector.
	 * @param PluginCatalog  $plugin_catalog  Plugin catalog.
	 */
	public function __construct( PluginDetector $plugin_detector, PluginCatalog $plugin_catalog ) {

		$this->plugin_detector = $plugin_detector;
		$this->plugin_catalog  = $plugin_catalog;
	}

	/**
	 * Render-ready tiles for the "Take Your Forms to the Next Level" feature grid.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array>
	 */
	public function feature_tiles(): array {

		return ( new FeatureTiles( $this->plugin_detector, $this->plugin_catalog ) )->get_tiles( 'Setup Checklist' );
	}

	/**
	 * Render-ready cards for the integrations grid: tier-selected, personalized, and with
	 * each CTA link resolved.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array>
	 */
	public function integration_cards(): array {

		return ( new IntegrationCards( $this->plugin_detector, $this->plugin_catalog ) )->get_cards( 'Setup Checklist' );
	}

	/**
	 * Render-ready tiles for the "Set Up Recommended Growth Tools" grid.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array>
	 */
	public function growth_tool_tiles(): array {

		return ( new GrowthToolTiles( $this->plugin_detector, $this->plugin_catalog ) )->get_tiles();
	}
}
