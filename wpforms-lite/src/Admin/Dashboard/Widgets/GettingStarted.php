<?php

namespace WPForms\Admin\Dashboard\Widgets;

use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\WidgetState;

/**
 * "Getting Started" sidebar widget.
 *
 * A static card with five documentation links and a "View All Documentation" link.
 * Identical on every tier; no gear menu; not dismissible; no JS.
 *
 * @since 2.0.2
 */
class GettingStarted extends AbstractWidget {

	/**
	 * Column placement.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'sidebar';

	/**
	 * Default sort position within the sidebar (last; 40 is reserved for Spam & Security Checkup).
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 50;

	/**
	 * UTM medium shared by every link in the widget.
	 *
	 * @since 2.0.2
	 */
	private const UTM_MEDIUM = 'Dashboard - Getting Started';

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'getting-started';
	}

	/**
	 * Get the widget title.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_title(): string {

		return esc_html__( 'Getting Started', 'wpforms-lite' );
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
	 * Render the widget body.
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
	protected function render_body( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; the widget is static.

		return (string) wpforms_render(
			'admin/dashboard/sidebar/getting-started',
			[
				'links'    => $this->get_links(),
				'view_all' => [
					'label' => __( 'View All Documentation', 'wpforms-lite' ),
					'url'   => wpforms_utm_link( 'https://wpforms.com/docs/', self::UTM_MEDIUM, 'View All Documentation' ),
				],
			],
			true
		);
	}

	/**
	 * Get the documentation links.
	 *
	 * @since 2.0.2
	 *
	 * @return array Links: each item has `label` and a UTM-wrapped `url`.
	 */
	private function get_links(): array {

		$links = [
			[
				'label' => __( 'Creating Your First Form', 'wpforms-lite' ),
				'url'   => wpforms_utm_link( 'https://wpforms.com/docs/creating-first-form/', self::UTM_MEDIUM, 'Creating Your First Form' ),
			],
			[
				'label' => __( 'Styling Your Forms', 'wpforms-lite' ),
				'url'   => wpforms_utm_link( 'https://wpforms.com/docs/styling-your-forms/', self::UTM_MEDIUM, 'Styling Your Forms' ),
			],
			[
				'label' => __( 'Testing Forms Before Launching', 'wpforms-lite' ),
				'url'   => wpforms_utm_link( 'https://wpforms.com/docs/how-to-properly-test-your-wordpress-forms-before-launching-checklist/', self::UTM_MEDIUM, 'Testing Forms Before Launching' ),
			],
			[
				'label' => __( 'Displaying Forms on Your Site', 'wpforms-lite' ),
				'url'   => wpforms_utm_link( 'https://wpforms.com/docs/displaying-forms-on-your-site/', self::UTM_MEDIUM, 'Displaying Forms on Your Site' ),
			],
			[
				'label' => __( 'Setting Up Form Notification Emails', 'wpforms-lite' ),
				'url'   => wpforms_utm_link( 'https://wpforms.com/docs/setup-form-notification-wpforms/', self::UTM_MEDIUM, 'Setting Up Form Notification Emails' ),
			],
		];

		/**
		 * Filter the Getting Started widget documentation links.
		 *
		 * @since 2.0.2
		 *
		 * @param array $links Documentation links: each item has `label` and `url`.
		 */
		return (array) apply_filters( 'wpforms_admin_dashboard_widgets_getting_started_get_links', $links );
	}
}
