<?php

namespace WPForms\Admin\Education\Admin\Tools;

use WPForms\Admin\Tools\Import\Views\ImportViewsInterface;

/**
 * Import Entries Education class.
 *
 * Shows an education placeholder for Lite users to upgrade to Pro
 * for the Entries Import feature.
 *
 * @since 1.10.1
 */
class ImportEntries implements ImportViewsInterface {

	/**
	 * Initialize class.
	 *
	 * @since 1.10.1
	 */
	public function init(): void {
	}

	/**
	 * Get the Tab label.
	 *
	 * @since 1.10.1
	 *
	 * @return string
	 */
	public function get_tab_label(): string {

		return __( 'Import Entries', 'wpforms-lite' );
	}

	/**
	 * Check if the current user has the capability to view the page.
	 *
	 * @since 1.10.1
	 *
	 * @return bool
	 */
	public function current_user_can(): bool {

		return wpforms_current_user_can( [ 'edit_forms', 'view_entries' ] );
	}

	/**
	 * Page content.
	 *
	 * @since 1.10.1
	 */
	public function display(): void {

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render( 'education/admin/page', $this->get_template_data(), true );
	}

	/**
	 * Get the template data.
	 *
	 * @since 1.10.1
	 *
	 * @return array
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	private function get_template_data(): array {

		$images_url         = WPFORMS_PLUGIN_URL . 'assets/images/entry-importer/';
		$utm_medium         = 'Tools - Import';
		$utm_content_link   = 'Entry Importer - Upgrade Link';
		$utm_content_button = 'Entry Importer - Upgrade Button';

		$upgrade_link = sprintf( /* translators: %1$s - WPForms.com Upgrade page URL. */
			' <strong><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></strong>',
			esc_url( wpforms_admin_upgrade_link( $utm_medium, $utm_content_link ) ),
			esc_html__( 'Upgrade to WPForms Pro', 'wpforms-lite' )
		);

		return [
			'heading_title'        => __( 'Import Entries', 'wpforms-lite' ),
			'badge'                => __( 'Pro', 'wpforms-lite' ),
			'images'               => [
				[
					'url'   => $images_url . 'screenshot-other-plugins.png',
					'title' => __( 'Seamless Migration From Other Plugins', 'wpforms-lite' ),
				],
				[
					'url'   => $images_url . 'screenshot-field-mapping.png',
					'title' => __( 'Map Fields in Seconds', 'wpforms-lite' ),
				],
			],
			'features'             => [
				__( 'Migrate From Other Plugins', 'wpforms-lite' ),
				__( 'Import from Any CSV File', 'wpforms-lite' ),
				__( 'Map Columns to Form Fields', 'wpforms-lite' ),
				__( 'Works with CRM Exports', 'wpforms-lite' ),
				__( 'Preserve Your Complete History', 'wpforms-lite' ),
				__( 'Move Entries Between Forms', 'wpforms-lite' ),
			],
			'utm_medium'           => $utm_medium,
			'utm_content'          => $utm_content_button,
			/* translators: %1$s - WPForms.com Upgrade page URL. */
			'heading_description'  => '<p>' . esc_html__( 'Switching from another form plugin? Moving entries between forms? With WPForms Pro, you can import entries from any CSV file. Whether it\'s exported from another WordPress form plugin, pulled from your CRM, or built by hand. Map your data to the right fields, and every submission lands exactly where it belongs. No lost leads, no manual re-entry, no messy migrations. ', 'wpforms-lite' ) . wp_kses(
				$upgrade_link,
				[
					'a'      => [
						'href'   => [],
						'rel'    => [],
						'target' => [],
						'class'  => [],
					],
					'strong' => [],
				]
			) . '</p>',
			'features_description' => __( 'Powerful, Flexible Entry Imports', 'wpforms-lite' ),
		];
	}
}
