<?php

namespace WPForms\Admin\Education;

/**
 * Reusable feature-discovery tooltip component.
 *
 * Renders a configurable tooltip card with title, optional badge, body text,
 * optional CTA button, optional learn-more link, and a dismiss button.
 * Consumers pass a config array; the component handles rendering, escaping,
 * and positioning script enqueue.
 *
 * @since 2.0.0
 */
class FeatureTooltip {

	/**
	 * Whether the positioning script has been enqueued this request.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	private $script_enqueued = false;

	/**
	 * Initialize.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	private function hooks(): void {

		add_action( 'admin_enqueue_scripts', [ $this, 'register_scripts' ] );
	}

	/**
	 * Register (not enqueue) the positioning script so it is available
	 * when get() enqueues it on demand.
	 *
	 * @since 2.0.0
	 */
	public function register_scripts(): void {

		$min = wpforms_get_min_suffix();

		wp_register_script(
			'wpforms-education-feature-tooltip',
			WPFORMS_PLUGIN_URL . "assets/js/admin/education/feature-tooltip{$min}.js",
			[ 'wpforms-admin-education-core' ],
			WPFORMS_VERSION,
			true
		);
	}

	/**
	 * Return the tooltip HTML.
	 *
	 * Required config keys: title, text, section.
	 * Optional: badge, learn_more_url, learn_more_text, button (array with text, url, classes, data).
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Tooltip configuration.
	 *
	 * @return string Tooltip HTML or empty string on invalid config.
	 */
	public function get( array $args ): string {

		$required = [ 'title', 'text', 'section' ];

		foreach ( $required as $key ) {
			if ( empty( $args[ $key ] ) ) {
				return '';
			}
		}

		$button_markup = '';

		if ( ! empty( $args['button'] ) && is_array( $args['button'] ) ) {
			$button_markup = $this->get_button_markup( $args['button'] );
		}

		$learn_more_text = $args['learn_more_text'] ?? esc_html__( 'Learn More', 'wpforms-lite' );

		if ( ! $this->script_enqueued ) {
			wp_enqueue_script( 'wpforms-education-feature-tooltip' );

			$this->script_enqueued = true;
		}

		return wpforms_render(
			'education/feature-tooltip',
			[
				'title'           => $args['title'],
				'badge'           => $args['badge'] ?? '',
				'text'            => $args['text'],
				'section'         => $args['section'],
				'button_markup'   => $button_markup,
				'learn_more_url'  => $args['learn_more_url'] ?? '',
				'learn_more_text' => $learn_more_text,
			],
			true
		);
	}

	/**
	 * Echo the tooltip HTML.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Tooltip configuration.
	 */
	public function render( array $args ): void {

		echo $this->get( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build the CTA button anchor tag from structured config.
	 *
	 * @since 2.0.0
	 *
	 * @param array $button Button config with text, url, classes, and optional data.
	 *
	 * @return string Anchor tag HTML or empty string if text/url missing.
	 */
	private function get_button_markup( array $button ): string {

		if ( empty( $button['text'] ) || empty( $button['url'] ) ) {
			return '';
		}

		$data_attrs = '';

		if ( ! empty( $button['data'] ) && is_array( $button['data'] ) ) {
			foreach ( $button['data'] as $key => $value ) {
				$data_attrs .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( $value ) );
			}
		}

		return sprintf(
			'<a href="%1$s" class="%2$s"%3$s>%4$s</a>',
			esc_url( $button['url'] ),
			esc_attr( $button['classes'] ?? 'wpforms-btn wpforms-btn-sm wpforms-btn-orange' ),
			$data_attrs,
			esc_html( $button['text'] )
		);
	}
}
