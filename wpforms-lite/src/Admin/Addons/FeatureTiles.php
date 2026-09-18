<?php

namespace WPForms\Admin\Addons;

use WPForms\SetupWizard\Service\PluginCatalog;
use WPForms\SetupWizard\Service\PluginDetector;

/**
 * "Take Your Forms to the Next Level" feature-promo catalog.
 *
 * Owns the static feature catalog and turns it into render-ready tiles with a tier-aware
 * CTA: Upgrade on Lite/Basic/Plus, in-place Install/Activate/Installed on Pro/Elite.
 * Consumed by the Setup Checklist page and the Dashboard FeaturesAddons widget.
 *
 * @since 2.0.2
 */
class FeatureTiles {

	use InstallLinks;

	/**
	 * Plugin detector (installed/active status for addons).
	 *
	 * @since 2.0.2
	 *
	 * @var PluginDetector
	 */
	private $plugin_detector;

	/**
	 * Plugin catalog (resolves slugs to addon plugin files).
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
	 * Render-ready tiles for the "Take Your Forms to the Next Level" feature grid.
	 *
	 * @since 2.0.2
	 *
	 * @param string $upgrade_medium UTM medium for the Upgrade CTA (e.g. 'Setup Checklist', 'Dashboard - Addons').
	 *
	 * @return array
	 */
	public function get_tiles( string $upgrade_medium ): array {

		$features = [
			[
				'icon'        => 'fa-arrow-right-from-bracket',
				'title'       => __( 'Form Abandonment Recovery', 'wpforms-lite' ),
				'description' => __( 'Partial form submission & user journey to boost sales.', 'wpforms-lite' ),
				'slugs'       => [ 'form-abandonment', 'user-journey', 'geolocation' ],
			],
			[
				'icon'        => 'fa-chart-bar',
				'title'       => __( 'Surveys & Polls', 'wpforms-lite' ),
				'description' => __( 'Create interactive surveys & gain valuable insights.', 'wpforms-lite' ),
				'slugs'       => [ 'surveys-polls', 'save-resume' ],
			],
			[
				'icon'        => 'fa-calculator',
				'title'       => __( 'Quizzes & Calculators', 'wpforms-lite' ),
				'description' => __( 'Create lead generation quizzes & calculators.', 'wpforms-lite' ),
				'slugs'       => [ 'quiz', 'calculations' ],
			],
			[
				'icon'        => 'fa-comments',
				'title'       => __( 'Conversational Forms', 'wpforms-lite' ),
				'description' => __( 'Improve form completion rate & conversions.', 'wpforms-lite' ),
				'slug'        => 'conversational-forms',
			],
			[
				'icon'        => 'fa-signature',
				'title'       => __( 'Collect Signatures', 'wpforms-lite' ),
				'description' => __( 'Collect secure digital signatures on your forms.', 'wpforms-lite' ),
				'slugs'       => [ 'signatures', 'pdf' ],
			],
			[
				'icon'        => 'fa-sliders',
				'title'       => __( 'Advanced Form Tools', 'wpforms-lite' ),
				'description' => __( 'Advanced fields, form permission control, and more.', 'wpforms-lite' ),
				'slug'        => 'form-locker',
			],
		];

		$is_pro_plus = $this->is_pro_plus();
		$upgrade_url = wpforms_admin_upgrade_link( $upgrade_medium, 'Take Your Forms to the Next Level' );
		$tiles       = [];

		foreach ( $features as $feature ) {
			$tiles[] = array_merge(
				[
					'icon'        => $feature['icon'],
					'title'       => $feature['title'],
					'description' => $feature['description'],
				],
				$this->feature_link( $feature, $is_pro_plus, $upgrade_url )
			);
		}

		return $tiles;
	}

	/**
	 * Build the CTA link parts for a feature tile.
	 *
	 * @since 2.0.2
	 *
	 * @param array  $feature     Feature tile (icon, title, description, optional slug/slugs).
	 * @param bool   $is_pro_plus Whether the license is Pro or Elite.
	 * @param string $upgrade_url Upgrade link target used on Lite.
	 *
	 * @return array Tile link parts (`link_text`, `link_url`, `link_action`, `link_plugin`,
	 *               `link_external`, and `link_is_upgrade` on the Upgrade CTA).
	 */
	private function feature_link( array $feature, bool $is_pro_plus, string $upgrade_url ): array {

		if ( ! $is_pro_plus ) {
			return [
				'link_text'       => __( 'Upgrade', 'wpforms-lite' ),
				'link_url'        => $upgrade_url,
				'link_external'   => true,
				'link_is_upgrade' => true,
			];
		}

		if ( ! empty( $feature['slugs'] ) ) {
			$link = $this->addon_install_link( $feature['slugs'] );
		} elseif ( ! empty( $feature['slug'] ) ) {
			$link = $this->addon_install_link( [ $feature['slug'] ] );
		} else {
			// No addon slug mapped to this tile — fall back to browsing the Addons page.
			$link = $this->addons_page_link();
		}

		return [
			'link_text'     => $link['text'],
			'link_url'      => $link['url'] ?? '#',
			'link_action'   => $link['action'],
			'link_plugin'   => $link['plugin'],
			'link_external' => $link['external'],
		];
	}
}
