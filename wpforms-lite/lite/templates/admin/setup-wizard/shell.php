<?php
/**
 * Setup Wizard first screen: standalone document shell.
 *
 * The close icon is Font Awesome Free 7.2.0 `xmark` (solid) by @fontawesome -
 * https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0,
 * Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2026 Fonticons, Inc.
 *
 * The `dir` attribute below is as far as RTL support goes here: physical
 * padding and margins in setup-wizard.scss still will not mirror, because
 * WPForms has no RTL CSS pipeline. Revisit if one is adopted.
 *
 * @since 2.0.0.3
 *
 * @var string $view     Screen view HTML.
 * @var array  $config   JS configuration.
 * @var string $exit_url Close destination.
 * @var array  $assets   Compiled asset URLs: css[], js[].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php esc_html_e( 'WPForms Setup Wizard', 'wpforms-lite' ); ?></title>
	<?php foreach ( $assets['css'] as $css_url ) : ?>
		<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone document rendered outside the admin enqueue pipeline. ?>
		<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>" />
	<?php endforeach; ?>
</head>
<body class="wpforms-setup-wizard">
	<div class="wpforms-wizard">
		<header class="wpforms-wizard-header">
			<img
				src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/wpforms-logo.svg' ); ?>"
				alt="<?php esc_attr_e( 'WPForms', 'wpforms-lite' ); ?>"
				class="wpforms-wizard-header__logo"
			/>
			<a
				href="<?php echo esc_url( $exit_url ); ?>"
				class="wpforms-wizard-header__close"
				title="<?php esc_attr_e( 'Close Setup Wizard', 'wpforms-lite' ); ?>"
				aria-label="<?php esc_attr_e( 'Close Setup Wizard', 'wpforms-lite' ); ?>"
			><svg class="wpforms-icon wpforms-icon--xmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" fill="currentColor" aria-hidden="true"><path d="M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z" /></svg></a>
		</header>
		<main class="wpforms-wizard__content">
			<?php echo $view; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template-rendered, escaped at the source. ?>
		</main>
	</div>
	<script>window.wpforms_setup_wizard_screen = <?php echo wp_json_encode( $config ); ?>;</script>
	<?php foreach ( $assets['js'] as $js_url ) : ?>
		<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Standalone document rendered outside the admin enqueue pipeline. ?>
		<script src="<?php echo esc_url( $js_url ); ?>"></script>
	<?php endforeach; ?>
</body>
</html>
