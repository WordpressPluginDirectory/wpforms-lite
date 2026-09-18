<?php

namespace WPForms\Admin\Dashboard;

use WPForms\Admin\Dashboard\Widgets\AbstractWidget;

/**
 * Builds the immutable access context for the Dashboard page.
 *
 * @since 2.0.2
 */
class AccessResolver {

	/**
	 * Cached context.
	 *
	 * @since 2.0.2
	 *
	 * @var AccessContext|null
	 */
	private $context;

	/**
	 * Get the access context, building it on first call.
	 *
	 * @since 2.0.2
	 *
	 * @return AccessContext
	 */
	public function get_context(): AccessContext {

		if ( $this->context === null ) {
			$this->context = new AccessContext( $this->get_data() );
		}

		return $this->context;
	}

	/**
	 * Get the raw context data. The Pro override adds license-derived values.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	protected function get_data(): array {

		return [
			// Mirrors the capability the Dashboard submenu is registered with, so a site
			// filtering `wpforms_manage_cap` does not reach a page whose AJAX actions all
			// reject it.
			'can_manage'      => current_user_can( wpforms_get_capability_manage_options() ),
			'user_id'         => get_current_user_id(),
			'dismissals'      => Helpers::get_user_meta_array( 'wpforms_dismissed' ),
			'widget_settings' => Helpers::get_user_meta_array( AbstractWidget::SETTINGS_META_KEY ),
		];
	}
}
