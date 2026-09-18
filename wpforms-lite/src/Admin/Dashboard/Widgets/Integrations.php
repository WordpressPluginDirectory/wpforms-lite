<?php

namespace WPForms\Admin\Dashboard\Widgets;

use WPForms\Admin\Addons\IntegrationCards;
use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\WidgetState;
use WPForms\SetupWizard\Service\PluginCatalog;
use WPForms\SetupWizard\Service\PluginDetector;

/**
 * "Streamline Your Workflow with Seamless Integrations" Dashboard widget.
 *
 * Renders the shared integration cards (Upgrade / Install / Activate / Connect / Manage
 * CTA per card) in the main column, followed by a footer with the "View All Integrations"
 * CTA. Always visible; no gear menu; not dismissible.
 *
 * @since 2.0.2
 */
class Integrations extends AbstractWidget {

	/**
	 * Column placement.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'main';

	/**
	 * Default sort position within the main column (the last widget, below FeaturesAddons).
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 50;

	/**
	 * Number of cards the widget grid shows.
	 *
	 * @since 2.0.2
	 */
	private const CARDS_COUNT = 8;

	/**
	 * Memoized integration-cards service.
	 *
	 * @since 2.0.2
	 *
	 * @var IntegrationCards|null
	 */
	private $integration_cards;

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'integrations';
	}

	/**
	 * Get the (memoized) integration-cards service.
	 *
	 * @since 2.0.2
	 *
	 * @return IntegrationCards
	 */
	private function get_integration_cards(): IntegrationCards {

		if ( $this->integration_cards === null ) {
			$this->integration_cards = new IntegrationCards( new PluginDetector(), new PluginCatalog() );
		}

		return $this->integration_cards;
	}

	/**
	 * Get the widget title.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_title(): string {

		return __( 'Streamline Your Workflow with Seamless Integrations', 'wpforms-lite' );
	}

	/**
	 * Get the widget state — always visible, no variant.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_state( AccessContext $access ): WidgetState { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; this widget is always visible.

		return new WidgetState( true );
	}

	/**
	 * Render the integration-card grid.
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
	protected function render_body( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; cards are tier-resolved by IntegrationCards.

		$cards = $this->get_integration_cards()->get_cards( 'Dashboard - Integrations', self::CARDS_COUNT, IntegrationCards::MODE_DASHBOARD );
		$html  = '<div class="wpforms-dashboard-widget-integrations-grid">';

		foreach ( $cards as $card ) {
			$html .= (string) wpforms_render( 'admin/addons/integration-card', [ 'card' => $card ], true );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render the footer: descriptive copy + the "View All Integrations" CTA.
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
	protected function render_footer( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; the CTA target is tier-derived.

		$text = sprintf(
			/* translators: %1$s - Zapier, %2$s - Uncanny Automator, %3$s - Make (each emphasised). */
			__( '30+ native integrations, plus thousands more via %1$s, %2$s, and %3$s.', 'wpforms-lite' ),
			'<strong>Zapier</strong>',
			'<strong>Uncanny Automator</strong>',
			'<strong>Make</strong>'
		);

		$is_pro_plus = $this->get_integration_cards()->is_pro_plus();

		$url = $is_pro_plus
			? $this->get_integration_cards()->integrations_page_url()
			: wpforms_utm_link( 'https://wpforms.com/integrations/', 'Dashboard - Integrations', 'View All Integrations' );

		return sprintf(
			'<p class="wpforms-dashboard-widget-integrations-footer-text">%1$s</p><a class="wpforms-btn wpforms-btn-md wpforms-btn-blue wpforms-dashboard-widget-integrations-view-all" href="%2$s"%3$s>%4$s</a>',
			wp_kses( $text, [ 'strong' => [] ] ),
			esc_url( $url ),
			$is_pro_plus ? '' : ' target="_blank" rel="noopener noreferrer"',
			esc_html__( 'View All Integrations', 'wpforms-lite' )
		);
	}
}
