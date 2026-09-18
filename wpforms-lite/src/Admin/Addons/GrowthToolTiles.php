<?php

namespace WPForms\Admin\Addons;

use WPForms\SetupWizard\Service\PluginCatalog;
use WPForms\SetupWizard\Service\PluginDetector;

/**
 * "Recommended Growth Tools" promo catalog.
 *
 * Owns the curated Awesome Motive wordpress.org plugin catalog and turns it into render-ready
 * tiles with an in-place Install / Activate / Installed CTA. These are free wordpress.org
 * plugins, so — unlike FeatureTiles — the CTA is never tier-gated; it is still capability-gated,
 * falling back to an out-of-band link when the user cannot install in place. Consumed by the
 * Setup Checklist page and the Dashboard GrowthTools widget.
 *
 * @since 2.0.2
 */
class GrowthToolTiles {

	use InstallLinks;

	/**
	 * Plugin detector (installed/active status).
	 *
	 * @since 2.0.2
	 *
	 * @var PluginDetector
	 */
	private $plugin_detector;

	/**
	 * Plugin catalog (whitelist / basename resolution).
	 *
	 * @since 2.0.2
	 *
	 * @var PluginCatalog
	 */
	private $plugin_catalog;

	/**
	 * Constructor.
	 *
	 * @since 2.0.2
	 *
	 * @param PluginDetector $plugin_detector Plugin detector.
	 * @param PluginCatalog  $plugin_catalog  Plugin catalog.
	 */
	public function __construct( PluginDetector $plugin_detector, PluginCatalog $plugin_catalog ) {

		$this->plugin_detector = $plugin_detector;
		$this->plugin_catalog  = $plugin_catalog;
	}

	/**
	 * Render-ready tiles for the "Recommended Growth Tools" grid.
	 *
	 * @since 2.0.2
	 *
	 * @param array $basenames Optional allow-list of plugin basenames. Empty returns the full
	 *                         catalog (Setup Checklist); a subset returns only those, in catalog order.
	 *
	 * @return array
	 */
	public function get_tiles( array $basenames = [] ): array {

		$tiles = [];

		foreach ( $this->get_catalog() as $tool ) {
			if ( $basenames !== [] && ! in_array( $tool['basename'], $basenames, true ) ) {
				continue;
			}

			$link = $this->gated_install_link( [ $tool['basename'] ], 'plugin' );

			$tiles[] = [
				'image'         => $tool['image'],
				'title'         => $tool['title'],
				'description'   => $tool['description'],
				'link_text'     => $link['text'],
				'link_url'      => $link['url'] ?? '#',
				'link_external' => $link['external'],
				'link_action'   => $link['action'],
				'link_plugin'   => $link['plugin'],
			];
		}

		return $tiles;
	}

	/**
	 * The curated growth-tools catalog (name, description, brand logo, plugin basename).
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_catalog(): array {

		return [
			[
				'image'       => 'setup-checklist/brand-aioseo.svg',
				'title'       => 'AIOSEO',
				'description' => __( 'Improve SEO rankings with AI tools and get valuable insights.', 'wpforms-lite' ),
				'basename'    => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
			],
			[
				'image'       => 'setup-checklist/brand-universally.svg',
				'title'       => 'Universally',
				'description' => __( 'Easily translate your website into 110+ languages within minutes using AI.', 'wpforms-lite' ),
				'basename'    => 'universally-language-translation-multilingual-tool/universally.php',
			],
			[
				'image'       => 'setup-checklist/brand-duplicator.svg',
				'title'       => 'Duplicator',
				'description' => __( 'Easy, fast, and secure WordPress backups and website migrations.', 'wpforms-lite' ),
				'basename'    => 'duplicator/duplicator.php',
			],
			[
				'image'       => 'setup-checklist/brand-smashballoon.svg',
				'title'       => 'Reviews Feed',
				'description' => __( 'Show customer reviews from Google, Yelp, TripAdvisor, and more to boost sales.', 'wpforms-lite' ),
				'basename'    => 'reviews-feed/sb-reviews.php',
			],
			[
				'image'       => 'setup-checklist/brand-optinmonster.svg',
				'title'       => 'OptinMonster',
				'description' => __( 'Get more email subscribers & sales with the #1 CRO toolkit for WordPress.', 'wpforms-lite' ),
				'basename'    => 'optinmonster/optin-monster-wp-api.php',
			],
			[
				'image'       => 'setup-checklist/brand-monsterinsights.svg',
				'title'       => 'MonsterInsights',
				'description' => __( 'Website analytics made easy for WordPress. Form tracking, reports, and more.', 'wpforms-lite' ),
				'basename'    => 'google-analytics-for-wordpress/googleanalytics.php',
			],
		];
	}
}
