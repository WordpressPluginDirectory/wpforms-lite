<?php

namespace WPForms\Admin\Dashboard\Widgets;

use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\WidgetState;

/**
 * Dashboard "License / Update" sidebar widget.
 *
 * Lite base: the edition + version head, the "Upgrade to Pro" link, and the
 * pending-update CTA. License status is a Pro-only concept — the Pro subclass
 * adds the no-license, expired, disabled, invalid, and limit-reached states
 * along with their description body.
 *
 * @since 2.0.2
 */
class License extends AbstractWidget {

	/**
	 * Column placement.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'sidebar';

	/**
	 * Sort position — first sidebar card.
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 5;

	/**
	 * Cached update-pending flag for the current render.
	 *
	 * @since 2.0.2
	 *
	 * @var bool|null
	 */
	private $update_available = null;

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'license';
	}

	/**
	 * Get the widget title. Unused — the license card renders a rich head via render_head().
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_title(): string {

		return '';
	}

	/**
	 * Resolve the widget state.
	 *
	 * Lite has no license to inspect, so the base is always the healthy card.
	 * The Pro subclass overrides this with the license problem states.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_state( AccessContext $access ): WidgetState { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Part of the widget contract; the Lite base has no license state to resolve.

		return new WidgetState( true, 'data' );
	}

	/**
	 * Mark the card for the responsive "attention" hoist when a plugin update is pending.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function get_extra_classes( string $variant, array $data, AccessContext $access ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $variant, $data and $access are part of the contract signature.

		return $this->is_update_available() ? [ 'wpforms-dashboard-widget-attention' ] : [];
	}

	/**
	 * Render the widget head — the title row (edition + version + CTA).
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant (unused).
	 * @param array         $data    Aggregated data (unused).
	 * @param AccessContext $access  Access context (unused).
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_head( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $variant, $data and $access are part of the contract signature.

		return (string) wpforms_render(
			'admin/dashboard/sidebar/license-head',
			$this->get_view_data(),
			true
		);
	}

	/**
	 * Render the widget body. Lite has no body — the description belongs to the
	 * Pro license problem states.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant (unused).
	 * @param array         $data    Aggregated data (unused).
	 * @param AccessContext $access  Access context (unused).
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $variant, $data and $access are part of the contract signature.

		return '';
	}

	/**
	 * Build the view data for the head template.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_view_data(): array {

		return [
			'edition_label'    => __( 'WPForms Lite', 'wpforms-lite' ),
			'version'          => WPFORMS_VERSION,
			'update_available' => $this->is_update_available(),
			'upgrade_url'      => wpforms_admin_upgrade_link( 'Dashboard - License', 'Upgrade to Pro' ),
			'plugins_url'      => self_admin_url( 'plugins.php' ),
		];
	}

	/**
	 * Whether a WPForms plugin update is pending.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	protected function is_update_available(): bool {

		if ( $this->update_available !== null ) {
			return $this->update_available;
		}

		$transient = get_site_transient( 'update_plugins' );

		$this->update_available = isset( $transient->response[ plugin_basename( WPFORMS_PLUGIN_FILE ) ] );

		return $this->update_available;
	}
}
