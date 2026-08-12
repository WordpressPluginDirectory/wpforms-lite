<?php
/**
 * Setup Wizard first screen: Welcome view
 *
 * Icons are Font Awesome Free 7.2.0 by @fontawesome - https://fontawesome.com
 * License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1,
 * Code: MIT License) Copyright 2026 Fonticons, Inc., except `wpforms-ai`, a custom WPForms mark.
 *
 * @since 2.0.0.3
 *
 * @var bool   $is_consent_checked Whether the Lite Connect consent box starts checked.
 * @var string $exit_url           Skip Guided Setup destination.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$icons = [
	'wpforms-ai'    => '<svg class="wpforms-icon wpforms-icon--wpforms-ai" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path transform="translate(1.402 3.282)" d="M0.225786 6.31224C3.97335 5.16035 5.16072 3.97335 6.31224 0.225786C6.40478 -0.0752622 6.83166 -0.0752622 6.92421 0.225786C8.0761 3.97335 9.2631 5.16072 13.0107 6.31224C13.3117 6.40478 13.3117 6.83166 13.0107 6.92421C9.2631 8.0761 8.07573 9.2631 6.92421 13.0107C6.83166 13.3117 6.40478 13.3117 6.31224 13.0107C5.16035 9.2631 3.97335 8.07573 0.225786 6.92421C-0.0752622 6.83166 -0.0752622 6.40478 0.225786 6.31224Z" /><path transform="translate(10.913 0.603)" d="M0.124553 3.47852C2.18983 2.84387 2.84387 2.18946 3.47852 0.124553C3.52937 -0.0415176 3.76494 -0.0415176 3.81578 0.124553C4.45044 2.18983 5.10484 2.84387 7.16975 3.47852C7.33582 3.52937 7.33582 3.76494 7.16975 3.81578C5.10448 4.45044 4.45044 5.10484 3.81578 7.16975C3.76494 7.33582 3.52937 7.33582 3.47852 7.16975C2.84387 5.10448 2.18946 4.45044 0.124553 3.81578C-0.0415176 3.76494 -0.0415176 3.52937 0.124553 3.47852Z" /><path transform="translate(10.913 11.783)" d="M0.124553 3.47852C2.18983 2.84387 2.84387 2.18946 3.47852 0.124553C3.52937 -0.0415176 3.76494 -0.0415176 3.81578 0.124553C4.45044 2.18983 5.10484 2.84387 7.16975 3.47852C7.33582 3.52937 7.33582 3.76494 7.16975 3.81578C5.10448 4.45044 4.45044 5.10484 3.81578 7.16975C3.76494 7.33582 3.52937 7.33582 3.47852 7.16975C2.84387 5.10448 2.18946 4.45044 0.124553 3.81578C-0.0415176 3.76494 -0.0415176 3.52937 0.124553 3.47852Z" /></svg>',
	'copy'          => '<svg class="wpforms-icon wpforms-icon--copy" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor" aria-hidden="true"><path d="M192 0c-35.3 0-64 28.7-64 64l0 256c0 35.3 28.7 64 64 64l192 0c35.3 0 64-28.7 64-64l0-200.6c0-17.4-7.1-34.1-19.7-46.2L370.6 17.8C358.7 6.4 342.8 0 326.3 0L192 0zM64 128c-35.3 0-64 28.7-64 64L0 448c0 35.3 28.7 64 64 64l192 0c35.3 0 64-28.7 64-64l0-16-64 0 0 16-192 0 0-256 16 0 0-64-16 0z" /></svg>',
	'credit-card'   => '<svg class="wpforms-icon wpforms-icon--credit-card" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M0 128l0 32 512 0 0-32c0-35.3-28.7-64-64-64L64 64C28.7 64 0 92.7 0 128zm0 80L0 384c0 35.3 28.7 64 64 64l384 0c35.3 0 64-28.7 64-64l0-176-512 0zM64 360c0-13.3 10.7-24 24-24l48 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-48 0c-13.3 0-24-10.7-24-24zm144 0c0-13.3 10.7-24 24-24l64 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-64 0c-13.3 0-24-10.7-24-24z" /></svg>',
	'pen-to-square' => '<svg class="wpforms-icon wpforms-icon--pen-to-square" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M471.6 21.7c-21.9-21.9-57.3-21.9-79.2 0L368 46.1 465.9 144 490.3 119.6c21.9-21.9 21.9-57.3 0-79.2L471.6 21.7zm-299.2 220c-6.1 6.1-10.8 13.6-13.5 21.9l-29.6 88.8c-2.9 8.6-.6 18.1 5.8 24.6s15.9 8.7 24.6 5.8l88.8-29.6c8.2-2.7 15.7-7.4 21.9-13.5L432 177.9 334.1 80 172.4 241.7zM96 64C43 64 0 107 0 160L0 416c0 53 43 96 96 96l256 0c53 0 96-43 96-96l0-96c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 96c0 17.7-14.3 32-32 32L96 448c-17.7 0-32-14.3-32-32l0-256c0-17.7 14.3-32 32-32l96 0c17.7 0 32-14.3 32-32s-14.3-32-32-32L96 64z" /></svg>',
	'ban'           => '<svg class="wpforms-icon wpforms-icon--ban" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M367.2 412.5L99.5 144.8c-22.4 31.4-35.5 69.8-35.5 111.2 0 106 86 192 192 192 41.5 0 79.9-13.1 111.2-35.5zm45.3-45.3c22.4-31.4 35.5-69.8 35.5-111.2 0-106-86-192-192-192-41.5 0-79.9 13.1-111.2 35.5L412.5 367.2zM0 256a256 256 0 1 1 512 0 256 256 0 1 1 -512 0z" /></svg>',
	'envelope'      => '<svg class="wpforms-icon wpforms-icon--envelope" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M48 64c-26.5 0-48 21.5-48 48 0 15.1 7.1 29.3 19.2 38.4l208 156c17.1 12.8 40.5 12.8 57.6 0l208-156c12.1-9.1 19.2-23.3 19.2-38.4 0-26.5-21.5-48-48-48L48 64zM0 196L0 384c0 35.3 28.7 64 64 64l384 0c35.3 0 64-28.7 64-64l0-188-198.4 148.8c-34.1 25.6-81.1 25.6-115.2 0L0 196z" /></svg>',
	'arrow-right'   => '<svg class="wpforms-icon wpforms-icon--arrow-right" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M502.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L402.7 224 32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l370.7 0-105.4 105.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l160-160z" /></svg>',
];

$features = [
	[
		'icon'        => 'wpforms-ai',
		'title'       => __( 'WPForms AI', 'wpforms-lite' ),
		'description' => __( 'Let AI build your forms for you in a matter of seconds.', 'wpforms-lite' ),
	],
	[
		'icon'        => 'copy',
		'title'       => __( 'Dozens of Form Templates', 'wpforms-lite' ),
		'description' => __( 'Customize with drag & drop form builder.', 'wpforms-lite' ),
	],
	[
		'icon'        => 'credit-card',
		'title'       => __( 'Payment Forms', 'wpforms-lite' ),
		'description' => __( 'Accept payments with Stripe, Square & PayPal.', 'wpforms-lite' ),
	],
	[
		'icon'        => 'pen-to-square',
		'title'       => __( 'Form Styles & Themes', 'wpforms-lite' ),
		'description' => __( 'Easily customize the look & feel of your forms.', 'wpforms-lite' ),
	],
	[
		'icon'        => 'ban',
		'title'       => __( 'Spam Protection', 'wpforms-lite' ),
		'description' => __( 'Smart spam protection & captcha built-in.', 'wpforms-lite' ),
	],
	[
		'icon'        => 'envelope',
		'title'       => __( 'Form Notifications', 'wpforms-lite' ),
		'description' => __( 'Notify customers & employees via email, text & more.', 'wpforms-lite' ),
	],
];
?>
<section class="wpforms-welcome">
	<div class="wpforms-welcome__body">
		<div class="wpforms-welcome__heading">
			<h1 class="wpforms-welcome__title"><?php esc_html_e( 'Welcome to WPForms', 'wpforms-lite' ); ?></h1>
			<p class="wpforms-welcome__subhead">
				<?php esc_html_e( 'The most powerful and user-friendly form builder for WordPress. This quick setup tailors WPForms to your site. It only takes a minute.', 'wpforms-lite' ); ?>
			</p>
		</div>

		<div class="wpforms-welcome__features">
			<?php foreach ( $features as $feature ) : ?>
				<div class="wpforms-feature-card">
					<div class="wpforms-feature-card__icon"><?php echo $icons[ $feature['icon'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div class="wpforms-feature-card__text">
						<h2 class="wpforms-feature-card__title"><?php echo esc_html( $feature['title'] ); ?></h2>
						<p class="wpforms-feature-card__description"><?php echo esc_html( $feature['description'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="wpforms-welcome__ctas">
			<div><button type="button" class="wpforms-button wpforms-button--large wpforms-button--shadow" id="wpforms-welcome-submit">
					<span class="wpforms-button__label"><?php esc_html_e( 'Set Up My Forms', 'wpforms-lite' ); ?></span>
					<span class="wpforms-button__icon-slot" aria-hidden="true"><?php echo $icons['arrow-right']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</button></div>
			<noscript>
				<div class="wpforms-welcome__noscript">
					<?php esc_html_e( 'The Setup Wizard requires JavaScript. Enable it and reload this page, or continue without the wizard.', 'wpforms-lite' ); ?>
				</div>
			</noscript>
			<div>
				<a href="<?php echo esc_url( $exit_url ); ?>" class="wpforms-welcome__skip"><?php esc_html_e( 'Skip Guided Setup', 'wpforms-lite' ); ?></a>
			</div>
			<div class="wpforms-welcome__redirect-notice"><?php esc_html_e( 'Note: You will be transferred to a WPForms.com site to complete the setup wizard.', 'wpforms-lite' ); ?></div>
		</div>
	</div>

	<footer class="wpforms-welcome__consent">
		<label class="wpforms-checkbox">
			<input type="checkbox" class="wpforms-checkbox__input" id="wpforms-consent" <?php checked( $is_consent_checked ); ?> />
			<span class="wpforms-checkbox__box" aria-hidden="true">
				<svg class="wpforms-checkbox__check" xmlns="http://www.w3.org/2000/svg" width="7" height="7" viewBox="0 0 7 7" fill="none" aria-hidden="true"><path d="M6.28813 0.600088L2.68775 5.44221L0.600033 3.88988" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
			<span class="wpforms-checkbox__label">
				<?php
				printf(
					/* translators: %1$s - bold Lite Connect label, %2$s - Terms of Service link. */
					esc_html__( 'I agree to enable %1$s to securely back up my entries and unlock the WPForms AI experience, per the %2$s. You can disable this at any time in WPForms settings.', 'wpforms-lite' ),
					'<strong>' . esc_html__( 'Lite Connect', 'wpforms-lite' ) . '</strong>',
					'<a href="' . esc_url( 'https://wpforms.com/terms/?utm_source=WordPress&utm_medium=Onboarding%20Wizard&utm_campaign=liteplugin&utm_content=Welcome%20Terms%20of%20Service' ) . '" target="_blank" rel="noopener noreferrer" class="wpforms-link">' . esc_html__( 'Terms of Service', 'wpforms-lite' ) . '</a>'
				);
				?>
			</span>
		</label>
	</footer>
</section>
