<?php

namespace WPForms\SetupChecklist;

use WPForms\Pro\Admin\PluginList;
use WPForms\SetupWizard\Service\PluginCatalog;
use WPForms\SetupWizard\Service\PluginDetector;

/**
 * Setup Checklist model.
 *
 * Composes the declarative {@see Config} with live {@see CompletionDetector}
 * results into the shape the menu and the page consume: sections enriched with
 * per-item completion, per-section completion, and an overall progress summary.
 * Detection results are memoized for the request so a single page load that asks
 * for both the sections and the progress runs each check only once.
 *
 * @since 2.0.0
 */
class Checklist {

	/**
	 * Data-model source.
	 *
	 * @since 2.0.0
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Completion detector.
	 *
	 * @since 2.0.0
	 *
	 * @var CompletionDetector
	 */
	private $detector;

	/**
	 * Plugin detector (installed/active status for form plugins).
	 *
	 * @since 2.0.0
	 *
	 * @var PluginDetector
	 */
	private $plugin_detector;

	/**
	 * Plugin catalog (display names for detected form plugins).
	 *
	 * @since 2.0.0
	 *
	 * @var PluginCatalog
	 */
	private $plugin_catalog;

	/**
	 * Memoized enriched sections for the current request.
	 *
	 * @since 2.0.0
	 *
	 * @var array|null
	 */
	private $sections;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Config             $config          Data-model source.
	 * @param CompletionDetector $detector        Completion detector.
	 * @param PluginDetector     $plugin_detector Plugin detector.
	 * @param PluginCatalog      $plugin_catalog  Plugin catalog.
	 */
	public function __construct( Config $config, CompletionDetector $detector, PluginDetector $plugin_detector, PluginCatalog $plugin_catalog ) {

		$this->config          = $config;
		$this->detector        = $detector;
		$this->plugin_detector = $plugin_detector;
		$this->plugin_catalog  = $plugin_catalog;
	}

	/**
	 * Sections enriched with completion state.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array>
	 */
	public function get_sections(): array {

		if ( $this->sections !== null ) {
			return $this->sections;
		}

		$sections = $this->config->get_sections();

		foreach ( $sections as $section_id => $section ) {
			$completed = 0;
			$items     = $section['items'] ?? [];

			foreach ( $items as $item_id => $item ) {
				$item = $this->resolve_item( $item );

				if ( $item === null ) {
					unset( $items[ $item_id ] );

					continue;
				}

				$items[ $item_id ] = $item;

				$is_complete = $this->detector->is_complete( $item['id'] );

				$items[ $item_id ]['complete'] = $is_complete;

				if ( $is_complete ) {
					++$completed;
				}
			}

			$total = count( $items );

			$complete_on_any = ! empty( $section['complete_on_any'] );

			$sections[ $section_id ]['items']           = $items;
			$sections[ $section_id ]['completed_items'] = $completed;
			$sections[ $section_id ]['total_items']     = $total;
			$sections[ $section_id ]['complete']        = $this->is_section_complete( $completed, $total, $complete_on_any );
		}

		$this->sections = $sections;

		return $this->sections;
	}

	/**
	 * Determine whether a section is complete.
	 *
	 * A section needs at least one item. It is complete when any item is done
	 * for `complete_on_any` sections, or when every item is done otherwise.
	 *
	 * @since 2.0.0
	 *
	 * @param int  $completed       Number of completed items in the section.
	 * @param int  $total           Total number of items in the section.
	 * @param bool $complete_on_any Whether a single completed item marks the section complete.
	 *
	 * @return bool
	 */
	private function is_section_complete( int $completed, int $total, bool $complete_on_any ): bool {

		if ( $total === 0 ) {
			return false;
		}

		return $complete_on_any ? $completed > 0 : $completed === $total;
	}

	/**
	 * Resolve an item before counting: a non-applicable conditional item returns null.
	 *
	 * @since 2.0.0
	 *
	 * @param array $item Conditional item from the config.
	 *
	 * @return array|null The finalised item, or null when it does not apply.
	 */
	private function resolve_item( array $item ): ?array {

		if ( $item['id'] === 'lite_connect' ) {
			return $this->resolve_lite_connect_item( $item );
		}

		if ( $item['id'] !== 'import_forms' ) {
			return $item;
		}

		$detected = array_filter(
			$this->plugin_detector->forms_plugins(),
			static function ( $status ) {

				return ! empty( $status['installed'] );
			}
		);

		if ( empty( $detected ) ) {
			return $this->detector->is_complete( 'import_forms' ) ? $item : null;
		}

		if ( count( $detected ) === 1 ) {
			$item['title'] = sprintf(
				/* translators: %s - the detected form plugin name. */
				__( 'Import From %s', 'wpforms-lite' ),
				$this->plugin_catalog->name( (string) array_keys( $detected )[0] )
			);
		} else {
			$item['title'] = __( 'Import From Other Form Plugins', 'wpforms-lite' );
		}

		return $item;
	}

	/**
	 * Resolve the Lite Connect sub-step.
	 *
	 * @since 2.0.0
	 *
	 * @param array $item Conditional item from the config.
	 *
	 * @return array|null The finalised item, or null when it does not apply.
	 */
	private function resolve_lite_connect_item( array $item ): ?array {

		if ( ! wpforms()->is_pro() ) {
			return $item;
		}

		if ( ! $this->detector->had_lite_connect_enabled() || ! $this->has_valid_license() ) {
			return null;
		}

		$item['title']        = __( 'Restore Entry Backups', 'wpforms-lite' );
		$item['description']  = __( 'Since you enabled Lite Connect in the free version of WPForms, you have entries backed up and ready to be restored.', 'wpforms-lite' );
		$item['cta']['label'] = __( 'Restore Entry Backups', 'wpforms-lite' );
		$item['cta']['url']   = add_query_arg(
			[
				'wpforms_lite_connect_action' => 'import',
				'_wpnonce'                    => wp_create_nonce( 'wpforms_lite_connect_action' ),
			],
			Page::get_url()
		);

		$item['cta']['spinner'] = true;

		if ( $this->detector->is_lite_connect_restore_in_progress() ) {
			$item['cta']['label']    = __( 'Import in Progress', 'wpforms-lite' );
			$item['cta']['disabled'] = true;

			unset( $item['cta']['action'] );
		}

		return $item;
	}

	/**
	 * Whether the site has a valid Pro license.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function has_valid_license(): bool {

		return ( new PluginList() )->is_valid_license();
	}

	/**
	 * Overall progress summary.
	 *
	 * @since 2.0.0
	 *
	 * @return array{percent:int, completed_sections:int, total_sections:int, completed_items:int, total_items:int}
	 */
	public function get_progress(): array {

		$sections = $this->get_sections();

		$total_sections     = count( $sections );
		$completed_sections = 0;
		$total_items        = 0;
		$completed_items    = 0;

		foreach ( $sections as $section ) {
			$completed_sections += ! empty( $section['complete'] ) ? 1 : 0;
			$total_items        += $section['total_items'] ?? 0;
			$completed_items    += $section['completed_items'] ?? 0;
		}

		$percent = $total_sections > 0 ? (int) round( $completed_sections / $total_sections * 100 ) : 0;

		return [
			'percent'            => $percent,
			'completed_sections' => $completed_sections,
			'total_sections'     => $total_sections,
			'completed_items'    => $completed_items,
			'total_items'        => $total_items,
		];
	}
}
