<?php

namespace WPForms\Admin\Tools\Export\Views;

/**
 * Export Views Interface.
 *
 * @since 1.10.1
 */
interface ExportViewsInterface {

	/**
	 * Initialize class.
	 *
	 * @since 1.10.1
	 */
	public function init(): void;

	/**
	 * Check if the current user has the capability to view the page.
	 *
	 * @since 1.10.1
	 *
	 * @return bool
	 */
	public function current_user_can(): bool;

	/**
	 * Page content.
	 *
	 * @since 1.10.1
	 */
	public function display(): void;

	/**
	 * Get the Tab label.
	 *
	 * @since 1.10.1
	 *
	 * @return string
	 */
	public function get_tab_label(): string;
}
