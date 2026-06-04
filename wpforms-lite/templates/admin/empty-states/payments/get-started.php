<?php
/**
 * Get Started HTML template.
 *
 * @since 1.8.2
 *
 * @var string $version  Either 'pro' or 'lite'.
 * @var array  $gateways Gateway tiles keyed by slug.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$allowed_html = [
	'a'      => [
		'href'   => [],
		'rel'    => [],
		'target' => [],
	],
	'strong' => [],
];

$guide_url = $version === 'pro'
	? 'https://wpforms.com/docs/how-to-install-and-use-the-stripe-addon-with-wpforms/'
	: 'https://wpforms.com/docs/using-stripe-with-wpforms-lite/';

// Inline SVG spinner so its color tracks the parent button's text color via `currentColor`.
$spinner_svg = '<svg class="wpforms-empty-payments-btn-spinner wpforms-hidden" width="16" height="16" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path fill="currentColor" opacity=".25" d="M40 0C17.9 0 0 17.9 0 40s17.9 40 40 40 40-17.9 40-40S62.1 0 40 0zm0 72C22.3 72 8 57.7 8 40S22.3 8 40 8s32 14.3 32 32-14.3 32-32 32z"/><path fill="currentColor" d="M75.8 47.4h-.4c-2.2-.2-3.8-2.2-3.6-4.4.1-1 .1-2 .1-3C72 22.4 57.6 8 40 8c-2.2 0-4-1.8-4-4s1.8-4 4-4c22.1 0 40 17.9 40 40 0 1.3-.1 2.5-.2 3.8-.2 2.1-1.9 3.6-4 3.6z"/></svg>';

// Stripe is the only hero tile; everything else falls into the grid.
$stripe_gateway = $gateways['stripe'] ?? null;
$grid_gateways  = $gateways;

unset( $grid_gateways['stripe'] );

?>
<div class="wpforms-admin-empty-state-container wpforms-admin-no-payments">

	<div class="wpforms-empty-payments-header">
		<h2 class="waving-hand-emoji"><?php esc_html_e( 'Ready to Accept Payments?', 'wpforms-lite' ); ?></h2>

		<p class="wpforms-empty-payments-intro">
			<?php esc_html_e( "The world's most powerful and easy-to-use payment gateways are just a few clicks away.", 'wpforms-lite' ); ?>
		</p>

		<?php if ( $version === 'lite' ) : ?>
			<p class="wpforms-empty-payments-fees">
				<?php esc_html_e( 'Note: 3% platform fees apply that can be removed by', 'wpforms-lite' ); ?>
				<a class="wpforms-empty-payments-fees-link wpforms-upgrade-modal" href="<?php echo esc_url( wpforms_admin_upgrade_link( 'Payments Dashboard', 'Splash - Lite Upgrade' ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Upgrading to Pro', 'wpforms-lite' ); ?>
				</a>
			</p>
		<?php else : ?>
			<p class="wpforms-empty-payments-fees">
				<?php
				echo wp_kses(
					__( 'Standard transaction fees apply, but you <strong>save the 3% platform fee</strong> with WPForms Pro!', 'wpforms-lite' ),
					[ 'strong' => [] ]
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( $stripe_gateway ) : ?>
		<div class="wpforms-empty-payments-stripe">
			<div class="wpforms-empty-payments-stripe-info">
				<img class="wpforms-empty-payments-stripe-icon" src="<?php echo esc_url( $stripe_gateway['icon'] ); ?>" alt="<?php echo esc_attr( $stripe_gateway['icon_alt'] ); ?>" width="90" height="90">
				<div class="wpforms-empty-payments-stripe-text">
					<h3>
						<img class="wpforms-empty-payments-stripe-brand" src="<?php echo esc_url( $stripe_gateway['brand'] ); ?>" alt="<?php echo esc_attr( $stripe_gateway['name'] ); ?>" width="97" height="40">
						<span class="wpforms-empty-payments-stripe-dash" aria-hidden="true">—</span>
						<span class="wpforms-empty-payments-stripe-tagline">
							<?php echo esc_html( $stripe_gateway['tagline'] ); ?>
						</span>
					</h3>
					<p>
						<?php echo esc_html( $stripe_gateway['description'] ); ?>
					</p>
				</div>
			</div>

			<a href="<?php echo esc_url( $stripe_gateway['url'] ); ?>" class="wpforms-btn wpforms-empty-payments-stripe-btn">
				<img class="wpforms-empty-payments-stripe-btn-icon" src="<?php echo esc_url( $stripe_gateway['btn_icon'] ); ?>" alt="" width="12" height="16">
				<span class="wpforms-empty-payments-stripe-btn-divider" aria-hidden="true"></span>
				<span class="wpforms-empty-payments-stripe-btn-text"><?php echo esc_html( $stripe_gateway['btn_text'] ); ?></span>
				<?php echo $spinner_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $grid_gateways ) ) : ?>
		<div class="wpforms-empty-payments-gateways">
			<?php foreach ( $grid_gateways as $gateway ) : ?>
				<div class="wpforms-empty-payments-gateway<?php echo $gateway['badge'] !== '' ? ' has-badge' : ''; ?>">
					<?php if ( $gateway['badge'] !== '' ) : ?>
						<span class="wpforms-empty-payments-gateway-badge"><?php echo esc_html( $gateway['badge'] ); ?></span>
					<?php endif; ?>

					<img src="<?php echo esc_url( $gateway['icon'] ); ?>" alt="<?php echo esc_attr( $gateway['name'] ); ?>">

					<h4><?php echo esc_html( $gateway['name'] ); ?></h4>
					<p><?php echo esc_html( $gateway['description'] ); ?></p>

					<a href="<?php echo esc_url( $gateway['url'] ); ?>" class="wpforms-btn wpforms-empty-payments-gateway-btn<?php echo $gateway['cta_class'] !== '' ? ' ' . esc_attr( $gateway['cta_class'] ) : ''; ?>" target="<?php echo esc_attr( $gateway['cta_target'] ); ?>"<?php echo $gateway['cta_target'] === '_blank' ? ' rel="noopener noreferrer"' : ''; ?>>
						<?php echo esc_html( $gateway['cta'] ); ?>
						<?php echo $spinner_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<p class="wpforms-admin-no-forms-footer">
		<?php
		printf(
			wp_kses( /* translators: %s - URL to the comprehensive guide. */
				__( 'Need some help? Check out our <a href="%s" rel="noopener noreferrer" target="_blank">comprehensive payments guide.</a>', 'wpforms-lite' ),
				$allowed_html
			),
			esc_url(
				wpforms_utm_link(
					$guide_url,
					'Payments Dashboard',
					'Splash - Manage Payments Documentation'
				)
			)
		);
		?>
	</p>
</div>

<script>
( () => {
	const spinnerSelector = '.wpforms-empty-payments-btn-spinner';
	const buttonSelectors = '.wpforms-empty-payments-stripe-btn, .wpforms-empty-payments-gateway-btn:not(.wpforms-upgrade-modal)';

	document.querySelectorAll( buttonSelectors ).forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			const spinner = btn.querySelector( spinnerSelector );

			if ( spinner ) {
				spinner.classList.remove( 'wpforms-hidden' );
			}
		} );
	} );

	// Reset spinners when the page is restored from BFCache (e.g. user hits browser back after a redirect).
	window.addEventListener( 'pageshow', () => {
		document.querySelectorAll( spinnerSelector ).forEach( ( spinner ) => {
			spinner.classList.add( 'wpforms-hidden' );
		} );
	} );
} )();
</script>
