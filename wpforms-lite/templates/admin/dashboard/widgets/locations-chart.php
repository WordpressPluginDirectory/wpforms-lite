<?php
/**
 * Dashboard "Top Locations" widget — table + donut chart.
 *
 * A top-countries table (colored dot + name + share + visitors) beside a donut
 * chart. The donut is drawn client-side by the widget-locations module from the
 * `data-config` payload; the table is the chart's text alternative. Shared by the
 * Pro filled data state and the Lite/install preview (fed with sample data and
 * flagged `data-preview` so the module renders it statically, without live events).
 *
 * @since 2.0.2
 *
 * @var array $countries     Visible rows: code, name, share, visitors, color.
 * @var array $all_countries Full sorted list (code, name, share, visitors) for JS re-render.
 * @var int   $donut_total   Total location-tagged submissions (donut center number).
 * @var array $settings      Resolved settings: number_of_countries, excluded[].
 * @var array $palette       Segment/dot color palette.
 * @var array $universally   Universally cross-promo data (share, languages, days, cta). Optional; [] hides it.
 * @var bool  $is_preview    Whether this is a static sample preview (no live re-render). Optional.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$universally = $universally ?? [];
$is_preview  = $is_preview ?? false;

$config = wp_json_encode(
	[
		'all'        => $all_countries,
		'donutTotal' => $donut_total,
		'excluded'   => $settings['excluded'],
		'number'     => $settings['number_of_countries'],
		'palette'    => $palette,
	]
);
?>
<div class="wpforms-dashboard-locations" data-config="<?php echo esc_attr( $config ); ?>"<?php echo $is_preview ? ' data-preview="1"' : ''; ?>>
	<div class="wpforms-dashboard-widget-locations-body">
		<table class="wpforms-dashboard-widget-locations-table wpforms-dashboard-widget-table">
			<thead>
				<tr>
					<th class="wpforms-dashboard-widget-locations-col-country"><?php esc_html_e( 'Country', 'wpforms-lite' ); ?></th>
					<th class="wpforms-dashboard-widget-locations-col-share"><?php esc_html_e( 'Share', 'wpforms-lite' ); ?></th>
					<th class="wpforms-dashboard-widget-locations-col-visitors"><?php esc_html_e( 'Visitors', 'wpforms-lite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $countries as $country ) :
					$share = (float) $country['share'];
					?>
					<tr>
						<td class="wpforms-dashboard-widget-locations-col-country">
							<span class="wpforms-dashboard-widget-locations-dot" style="background-color: <?php echo esc_attr( $country['color'] ); ?>;"></span>
							<?php echo esc_html( $country['name'] ); ?>
						</td>
						<td class="wpforms-dashboard-widget-locations-col-share"><?php echo esc_html( number_format_i18n( $share, 0 ) . '%' ); ?></td>
						<td class="wpforms-dashboard-widget-locations-col-visitors"><?php echo esc_html( number_format_i18n( $country['visitors'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="wpforms-dashboard-widget-locations-chart">
			<div class="wpforms-dashboard-widget-locations-chart-canvas">
				<canvas class="wpforms-dashboard-widget-locations-donut" role="img"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %s - total number of form entries. */ __( 'Top locations donut chart, %s form entries.', 'wpforms-lite' ), number_format_i18n( $donut_total ) ) ); ?>"></canvas>
				<div class="wpforms-dashboard-widget-locations-chart-center">
					<span class="wpforms-dashboard-widget-locations-chart-total"><?php echo esc_html( number_format_i18n( $donut_total ) ); ?></span>
					<span class="wpforms-dashboard-widget-locations-chart-caption"><?php esc_html_e( 'Form Entries', 'wpforms-lite' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $universally ) ) : ?>
		<?php
		// Interim copy pending review. It covers the shapes the trigger produces: one or
		// two dominant translations, or a share split too thin to name any of them.
		$promo_brand    = '<strong class="wpforms-dashboard-widget-locations-promo-brand">Universally</strong>';
		$promo_variants = array_map( 'esc_html', (array) $universally['languages'] );

		$promo_days = number_format_i18n( $universally['days'] );

		if ( count( $promo_variants ) === 1 ) {
			/* translators: 1: number of days the trigger looks back, 2: language name. */
			$promo_title = sprintf( esc_html__( 'A Lot of Your Visitors in the Last %1$s Days Read %2$s', 'wpforms-lite' ), $promo_days, $promo_variants[0] );
			/* translators: 1: language name, 2: Universally brand name. */
			$promo_body = sprintf( esc_html__( 'Consider translating your website into %1$s with %2$s.', 'wpforms-lite' ), $promo_variants[0], $promo_brand );
		} elseif ( count( $promo_variants ) === 2 ) {
			/* translators: 1: number of days the trigger looks back, 2: language name, 3: another language name. */
			$promo_title = sprintf( esc_html__( 'A Lot of Your Visitors in the Last %1$s Days Read %2$s and %3$s', 'wpforms-lite' ), $promo_days, $promo_variants[0], $promo_variants[1] );
			/* translators: 1: language name, 2: another language name, 3: Universally brand name. */
			$promo_body = sprintf( esc_html__( 'Consider translating your website into %1$s and %2$s with %3$s.', 'wpforms-lite' ), $promo_variants[0], $promo_variants[1], $promo_brand );
		} else {
			/* translators: 1: share of visitors, formatted as a percentage, 2: number of days the trigger looks back. */
			$promo_title = sprintf( esc_html__( '%1$s of Your Visitors in the Last %2$s Days Read Another Language', 'wpforms-lite' ), esc_html( number_format_i18n( $universally['share'] ) . '%' ), $promo_days );
			/* translators: %s - Universally brand name. */
			$promo_body = sprintf( esc_html__( 'Consider translating your website with %s.', 'wpforms-lite' ), $promo_brand );
		}

		$promo_attrs = '';

		foreach ( (array) $universally['cta']['attrs'] as $attr_name => $attr_value ) {
			$promo_attrs .= sprintf( ' %s="%s"', esc_attr( $attr_name ), esc_attr( $attr_value ) );
		}
		?>
		<div class="wpforms-dashboard-widget-banner wpforms-dashboard-widget-locations-promo wpforms-dismiss-container">
			<span class="wpforms-dashboard-widget-locations-promo-icon">
				<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/setup-checklist/brand-universally.svg' ); ?>" alt="">
			</span>

			<div class="wpforms-dashboard-widget-locations-promo-text">
				<p class="wpforms-dashboard-widget-locations-promo-title">
					<?php echo $promo_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every interpolated value is escaped above. ?>
				</p>
				<p class="wpforms-dashboard-widget-banner-text wpforms-dashboard-widget-locations-promo-body">
					<?php echo $promo_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Language names escaped above; brand markup is a static literal. ?>
					<a href="#" class="wpforms-education-toggle-plugin-btn wpforms-dashboard-widget-locations-promo-link"<?php echo $promo_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes escaped above. ?>>
						<?php echo esc_html( $universally['cta']['label'] ); ?><i class="fa fa-arrow-right" aria-hidden="true"></i>
					</a>
				</p>
			</div>

			<button type="button" class="wpforms-dismiss-button wpforms-dashboard-widget-locations-promo-dismiss"
				data-section="dashboard-universally-promo" aria-label="<?php esc_attr_e( 'Dismiss', 'wpforms-lite' ); ?>">
				<i class="fa fa-times" aria-hidden="true"></i>
			</button>
		</div>
	<?php endif; ?>
</div>
