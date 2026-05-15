<?php

namespace WPForms\Admin\Settings\Captcha;

use WPForms\Education\ActiveLayer\Helper as ActiveLayer;

/**
 * Horizontal "Better Way to Stop Spam" notice on Settings → CAPTCHA.
 *
 * @since 1.10.0.5
 */
class ActiveLayerCallout {

	const VIEW = 'captcha';

	/**
	 * Register hooks.
	 *
	 * @since 1.10.0.5
	 */
	public function hooks() {

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wpforms_admin_settings_after', [ $this, 'render' ], 5, 1 );
	}

	/**
	 * Are we on the CAPTCHA settings view?
	 *
	 * @since 1.10.0.5
	 *
	 * @return bool
	 */
	private function is_captcha_settings_screen(): bool {

		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen || $screen->id !== 'wpforms_page_wpforms-settings' ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'general';

		return $view === self::VIEW;
	}

	/**
	 * Enqueue stylesheet only on the CAPTCHA settings view.
	 *
	 * @since 1.10.0.5
	 */
	public function enqueue_assets() {

		if ( ! $this->is_captcha_settings_screen() ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-activelayer-callout',
			WPFORMS_PLUGIN_URL . "assets/css/admin/activelayer-callout{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}

	/**
	 * Render a horizontal notice above the CAPTCHA form.
	 *
	 * @since 1.10.0.5
	 *
	 * @param string $view Current settings view slug.
	 */
	public function render( $view ) {

		if ( $view !== self::VIEW ) {
			return;
		}

		$modal       = ActiveLayer::get_modal_data();
		$attrs_html  = '';
		$cta_classes = 'wpforms-btn wpforms-btn-md wpforms-btn-orange wpforms-activelayer-callout__cta';
		$action      = $modal['attrs']['data-action'] ?? '';

		if ( $action === 'install' ) {
			$modal['link_text'] = __( 'Install ActiveLayer', 'wpforms-lite' );
		} elseif ( $action === 'activate' ) {
			$modal['link_text'] = __( 'Activate ActiveLayer', 'wpforms-lite' );
		}

		if ( ! empty( $modal['class'] ) ) {
			$cta_classes .= ' ' . $modal['class'];
		}

		if ( ! empty( $modal['attrs'] ) ) {
			foreach ( $modal['attrs'] as $key => $value ) {
				$attrs_html .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
			}
		}
		?>
		<div class="wpforms-activelayer-callout" role="region" aria-labelledby="wpforms-activelayer-callout-heading">

			<div class="wpforms-activelayer-callout__icon">
				<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/anti-spam/activelayer.svg' ); ?>" alt="">
			</div>

			<div class="wpforms-activelayer-callout__body">
				<p class="wpforms-activelayer-callout__eyebrow"><?php esc_html_e( 'Better Way to Stop Spam', 'wpforms-lite' ); ?></p>
				<h3 id="wpforms-activelayer-callout-heading" class="wpforms-activelayer-callout__heading">
					<?php esc_html_e( 'Prefer no CAPTCHAs?', 'wpforms-lite' ); ?>
				</h3>
				<p class="wpforms-activelayer-callout__text">
					<?php esc_html_e( 'CAPTCHAs can reduce form completions by up to 40%. ActiveLayer catches bots invisibly, without asking your visitors to prove they\'re human. Free tier available.', 'wpforms-lite' ); ?>
				</p>
				<a href="<?php echo esc_url( $modal['link'] ); ?>"
					class="<?php echo esc_attr( $cta_classes ); ?>"
					<?php echo $attrs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php echo esc_html( $modal['link_text'] ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}
