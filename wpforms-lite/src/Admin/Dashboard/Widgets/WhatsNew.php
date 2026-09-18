<?php

namespace WPForms\Admin\Dashboard\Widgets;

use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\WidgetState;

/**
 * "What's New" sidebar widget.
 *
 * @since 2.0.2
 */
class WhatsNew extends AbstractWidget {

	/**
	 * Column placement: 'main' or 'sidebar'.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'sidebar';

	/**
	 * Default sort position within the column (below License, above Growth Tools).
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 20;

	/**
	 * Memoized most-recent feed block. Null until first resolved.
	 *
	 * @since 2.0.2
	 *
	 * @var array|null
	 */
	private $latest_block;

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'whats-new';
	}

	/**
	 * Get the widget title.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_title(): string {

		return esc_html__( 'What\'s New', 'wpforms-lite' );
	}

	/**
	 * Get the widget state. Hidden when the feed has no posts.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Part of the widget contract; the widget is tier-agnostic.

		return new WidgetState( ! empty( $this->get_latest_block() ), 'data' );
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
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Part of the widget contract; this widget uses neither the variant nor the aggregate data.

		$latest = $this->get_latest_block();

		if ( empty( $latest ) ) {
			return '';
		}

		return (string) wpforms_render(
			'admin/dashboard/sidebar/whats-new',
			[
				'title'       => $latest['title'] ?? '',
				'content'     => $latest['content'] ?? '',
				'image_url'   => $latest['img']['url'] ?? '',
				// The feed types its images, and the splash modal lays a hero out full-width
				// (see SplashTrait::get_block_layout()); the widget stacks it for the same reason.
				'is_hero'     => ( $latest['img']['type'] ?? '' ) === 'hero',
				'modal_class' => 'wpforms-splash-modal-open',
			],
			true
		);
	}

	/**
	 * Get the most-recent What's New post from the Splash feed cache. Memoized.
	 *
	 * @since 2.0.2
	 *
	 * @return array Most-recent block, or an empty array when the feed is empty.
	 */
	private function get_latest_block(): array {

		if ( isset( $this->latest_block ) ) {
			return $this->latest_block;
		}

		$cache  = wpforms()->obj( 'splash_cache' );
		$data   = $cache ? (array) $cache->get() : [];
		$blocks = $data['blocks'] ?? [];

		$latest = is_array( $blocks ) && ! empty( $blocks ) ? (array) $blocks[0] : [];

		/**
		 * Filter the featured What's New post shown in the sidebar widget.
		 *
		 * @since 2.0.2
		 *
		 * @param array $latest Featured feed block.
		 * @param array $blocks All available feed blocks, newest first.
		 */
		$this->latest_block = (array) apply_filters( 'wpforms_admin_dashboard_widgets_whats_new_get_latest_block', $latest, $blocks );

		return $this->latest_block;
	}
}
