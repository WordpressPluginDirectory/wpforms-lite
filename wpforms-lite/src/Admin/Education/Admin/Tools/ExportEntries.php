<?php

namespace WPForms\Admin\Education\Admin\Tools;

use WPForms\Admin\Tools\Export\Views\ExportViewsInterface;

/**
 * Export Entries Education class.
 *
 * Shows an education placeholder for Lite users to upgrade to Pro
 * for the Entries Export feature.
 *
 * @since 1.10.1
 */
class ExportEntries implements ExportViewsInterface {

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

		return __( 'Export Entries', 'wpforms-lite' );
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

		$utm_medium         = 'Tools - Export';
		$utm_content_link   = 'Entry Importer - Upgrade Link';
		$utm_content_button = 'Entry Importer - Upgrade Button';

		$upgrade_link = sprintf( /* translators: %1$s - WPForms.com Upgrade page URL. */
			' <strong><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></strong>',
			esc_url( wpforms_admin_upgrade_link( $utm_medium, $utm_content_link ) ),
			esc_html__( 'Upgrade to WPForms Pro', 'wpforms-lite' )
		);

		return [
			'heading_title'        => 'Export Entries',
			'badge'                => __( 'Pro', 'wpforms-lite' ),
			'features'             => [
				__( 'Export as CSV or XLSX', 'wpforms-lite' ),
				__( 'Choose Specific Fields to Include', 'wpforms-lite' ),
				__( 'Filter by Date Range', 'wpforms-lite' ),
				__( 'Search and Export Matching Entries', 'wpforms-lite' ),
				__( 'Schedule Automated Exports', 'wpforms-lite' ),
				__( 'Re-import Into Any WPForms Form', 'wpforms-lite' ),
			],
			'utm_medium'           => $utm_medium,
			'utm_content'          => $utm_content_button,
			'heading_description'  => '<p>' . esc_html__( 'Need to back up your form data, share it with your team, or move entries to another form? WPForms Pro lets you export entries from any form as a CSV or XLSX file. Choose which fields to include, filter by date range, and download a clean file ready for spreadsheets, CRMs, or re-importing into WPForms.', 'wpforms-lite' ) . wp_kses(
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
			'features_description' => __( 'Full Control Over Your Data', 'wpforms-lite' ),
		];
	}
}
