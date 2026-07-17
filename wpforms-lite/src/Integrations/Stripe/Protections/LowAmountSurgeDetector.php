<?php

namespace WPForms\Integrations\Stripe\Protections;

use WPForms\Admin\Notice;
use WPForms\Emails\Mailer;
use WPForms\Integrations\Stripe\Emails\StripeCardTestingAlert;
use WPForms\Integrations\Stripe\Helpers;

/**
 * Low-amount surge detection.
 *
 * Tracks low-amount payment attempts site-wide within a sliding window.
 * When the volume exceeds a threshold, blocks all subsequent low-amount
 * attempts for a configurable duration. Designed to catch card-testing
 * attacks where attackers probe stolen cards with small charges.
 *
 * @since 2.0.0
 */
final class LowAmountSurgeDetector {

	/**
	 * Transient key for the sliding-window counter.
	 *
	 * @since 2.0.0
	 */
	private const COUNTER_TRANSIENT = 'wpforms_stripe_low_amount_counter';

	/**
	 * Transient key for the active block flag.
	 *
	 * @since 2.0.0
	 */
	private const BLOCK_TRANSIENT = 'wpforms_stripe_low_amount_block';

	/**
	 * Transient key for the one-email-per-block-period deduplication flag.
	 *
	 * @since 2.0.0
	 */
	private const NOTIFIED_TRANSIENT = 'wpforms_stripe_low_amount_notified';

	/**
	 * Maximum number of distinct affected forms to track per surge.
	 *
	 * Bounds the size of the counter transient against pathological cases where
	 * attempts hit many different forms. The alert lists up to this many forms;
	 * the "Review your forms" link covers any beyond the cap.
	 *
	 * @since 2.0.0
	 */
	private const MAX_TRACKED_FORMS = 20;

	/**
	 * Whether the detector is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Amount threshold (current currency unit). Payments at or below count.
	 *
	 * @since 2.0.0
	 *
	 * @var float
	 */
	private $amount_threshold;

	/**
	 * Surge count threshold.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private $surge_count;

	/**
	 * Detection window in seconds.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private $window;

	/**
	 * Block duration in seconds.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private $block_duration;

	/**
	 * Init.
	 *
	 * @since 2.0.0
	 *
	 * @return LowAmountSurgeDetector
	 */
	public function init() {

		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		/**
		 * Filter whether low-amount surge detection is enabled.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $enabled Whether the surge detector is active.
		 */
		$this->enabled = (bool) apply_filters( 'wpforms_stripe_low_amount_surge_enabled', true );

		/**
		 * Filter the low-amount threshold (in current currency unit).
		 *
		 * @since 2.0.0
		 *
		 * @param float $threshold Amount threshold. Payments at or below count toward the surge.
		 */
		$this->amount_threshold = (float) apply_filters( 'wpforms_stripe_low_amount_threshold', 3.00 );

		/**
		 * Filter the surge count threshold.
		 *
		 * @since 2.0.0
		 *
		 * @param int $count Number of low-amount attempts that trigger a block.
		 */
		$this->surge_count = (int) apply_filters( 'wpforms_stripe_low_amount_surge_count', 10 );

		/**
		 * Filter the detection window in seconds.
		 *
		 * @since 2.0.0
		 *
		 * @param int $window Sliding-window length in seconds.
		 */
		$this->window = (int) apply_filters( 'wpforms_stripe_low_amount_surge_window', 120 );

		/**
		 * Filter the block duration in seconds.
		 *
		 * @since 2.0.0
		 *
		 * @param int $duration Block duration in seconds.
		 */
		$block_duration = (int) apply_filters( 'wpforms_stripe_low_amount_block_duration', HOUR_IN_SECONDS );
		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

		// Defensive: set_transient with 0 makes the option permanent (never expires).
		// Force at least 1 second so the auto-reset via TTL is always guaranteed.
		$this->block_duration = max( 1, $block_duration );

		return $this;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function hooks(): void {

		add_action( 'admin_notices', [ $this, 'maybe_render_admin_notice' ] );
	}

	/**
	 * Track a low-amount payment attempt.
	 *
	 * Increments the sliding-window counter and records the affected form so
	 * the alert email can list which forms the surge targeted. Activates the
	 * block when the count exceeds the surge threshold. No-op if the amount is
	 * above the threshold, the detector is disabled, or test-mode protections
	 * are off.
	 *
	 * @since 2.0.0
	 *
	 * @param float  $amount     Payment amount.
	 * @param int    $form_id    Form ID the attempt came from.
	 * @param string $form_title Form title, captured here so the email needs no later lookup.
	 */
	public function track_attempt( float $amount, int $form_id = 0, string $form_title = '' ): void {

		if ( ! $this->enabled ) {
			return;
		}

		if ( ! Helpers::should_apply_protections() ) {
			return;
		}

		if ( $amount <= 0 ) {
			return;
		}

		if ( $amount > $this->amount_threshold ) {
			return;
		}

		// If a block is already active, no need to keep counting.
		if ( $this->is_block_flag_set() ) {
			return;
		}

		$entry = get_transient( self::COUNTER_TRANSIENT );

		if (
			! is_array( $entry )
			|| ! isset( $entry['count'], $entry['window_start'] )
			|| (int) $entry['window_start'] + $this->window < time()
		) {
			// Start a new window.
			$entry = [
				'count'        => 1,
				'window_start' => time(),
				'forms'        => [],
			];
		} else {
			$entry['count'] = (int) $entry['count'] + 1;
		}

		// Record the affected form.
		if ( ! isset( $entry['forms'] ) || ! is_array( $entry['forms'] ) ) {
			$entry['forms'] = [];
		}

		if (
			$form_id > 0
			&& ( isset( $entry['forms'][ $form_id ] ) || count( $entry['forms'] ) < self::MAX_TRACKED_FORMS )
		) {
			$entry['forms'][ $form_id ] = $form_title;
		}

		set_transient( self::COUNTER_TRANSIENT, $entry, $this->window + 5 );

		if ( $entry['count'] < $this->surge_count ) {
			return;
		}

		$this->activate_block( $entry['forms'] );
	}

	/**
	 * Check whether the given amount is currently blocked.
	 *
	 * @since 2.0.0
	 *
	 * @param float $amount Payment amount.
	 *
	 * @return bool
	 */
	public function is_blocked( float $amount ): bool {

		if ( ! $this->enabled ) {
			return false;
		}

		if ( ! Helpers::should_apply_protections() ) {
			return false;
		}

		if ( $amount <= 0 ) {
			return false;
		}

		if ( $amount > $this->amount_threshold ) {
			return false;
		}

		return $this->is_block_flag_set();
	}

	/**
	 * Activate the block: set the block flag and notify admin once per period.
	 *
	 * The block auto-resets when its transient expires after $block_duration
	 * seconds — no explicit delete_transient is needed.
	 *
	 * @since 2.0.0
	 *
	 * @param array $forms Affected forms as [ form_id => title ].
	 */
	private function activate_block( array $forms = [] ): void {

		set_transient( self::BLOCK_TRANSIENT, '1', $this->block_duration );

		// One email per block period. The flag's TTL matches block_duration,
		// so a new surge right after expiration does not re-notify.
		if ( ! empty( get_transient( self::NOTIFIED_TRANSIENT ) ) ) {
			return;
		}

		set_transient( self::NOTIFIED_TRANSIENT, '1', $this->block_duration );

		$this->send_admin_email( $forms );
	}

	/**
	 * Send the one-time admin email about an activated block.
	 *
	 * @since 2.0.0
	 *
	 * @param array $forms Affected forms as [ form_id => title ].
	 */
	private function send_admin_email( array $forms = [] ): void {

		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		/**
		 * Filter the admin email recipient for surge detection notifications.
		 *
		 * @since 2.0.0
		 *
		 * @param string $email Recipient email address.
		 */
		$to = (string) apply_filters( 'wpforms_stripe_low_amount_surge_email_to', get_option( 'admin_email' ) );
		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

		$subject = sprintf(
			/* translators: %s - site name. */
			esc_html__( '[%s] Possible card testing activity detected', 'wpforms-lite' ),
			$site_name
		);

		$template = ( new StripeCardTestingAlert() )->set_args(
			[
				'body' => [
					'threshold_formatted' => $this->get_threshold_formatted(),
					'duration_formatted'  => $this->get_duration_formatted(),
					'forms_url'           => admin_url( 'admin.php?page=wpforms-overview' ),
					'affected_forms'      => $this->get_affected_forms_list( $forms ),
				],
			]
		);

		( new Mailer() )
			->template( $template )
			->subject( $subject )
			->to_email( $to )
			->send();
	}

	/**
	 * Register a warning notice when a block is active.
	 *
	 * @since 2.0.0
	 */
	public function maybe_render_admin_notice(): void {

		if ( ! $this->is_block_flag_set() ) {
			return;
		}

		if ( ! current_user_can( wpforms_get_capability_manage_options() ) ) {
			return;
		}

		$message  = '<p><strong>' . esc_html__( 'Possible Card Testing Detected', 'wpforms-lite' ) . '</strong></p>';
		$message .= '<p>' . sprintf(
			/* translators: %s - human-readable block duration (e.g. "5 mins", "1 hour"). */
			esc_html__( 'A surge of low-amount Stripe payments was recently detected on your site. As a precaution, low-amount payments are temporarily blocked. The block will lift automatically within %s.', 'wpforms-lite' ),
			esc_html( $this->get_duration_formatted() )
		) . '</p>';
		$message .= '<p>' . sprintf(
			wp_kses(
				/* translators: %1$s - link opening tag to forms overview, %2$s - closing tag. */
				__( '<strong>Recommended Action:</strong> Enable the "Minimum Price" option on Payment Single Item fields in your %1$sforms%2$s.', 'wpforms-lite' ),
				[
					'a'      => [
						'href' => [],
					],
					'strong' => [],
				]
			),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wpforms-overview' ) ) . '">',
			'</a>'
		) . '</p>';

		Notice::warning(
			$message,
			[
				'dismiss' => true,
				'slug'    => 'stripe_low_amount_surge',
				'autop'   => false,
				'class'   => 'wpforms-stripe-low-amount-surge-notice',
			]
		);
	}

	/**
	 * Get the current block flag value, normalized to bool.
	 *
	 * Uses a truthy check instead of strict `=== '1'` because some object cache
	 * backends may coerce stored types (e.g. string `'1'` → int `1`). Any
	 * non-empty value coming back from get_transient means the block is active.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_block_flag_set(): bool {

		$value = get_transient( self::BLOCK_TRANSIENT );

		return $value !== false && ! empty( $value );
	}

	/**
	 * Build the affected-forms list for the alert email.
	 *
	 * @since 2.0.0
	 *
	 * @param array $forms Tracked forms as [ form_id => title ].
	 *
	 * @return array List of [ 'title' => string, 'url' => string ] entries.
	 */
	private function get_affected_forms_list( array $forms ): array {

		$list = [];

		foreach ( $forms as $form_id => $title ) {
			$form_id = absint( $form_id );

			if ( $form_id <= 0 ) {
				continue;
			}

			$title = is_string( $title ) ? trim( $title ) : '';

			if ( $title === '' ) {
				$title = sprintf(
					/* translators: %d - form ID. */
					__( 'Form #%d', 'wpforms-lite' ),
					$form_id
				);
			}

			$list[] = [
				'title' => $title,
				'url'   => admin_url( sprintf( 'admin.php?page=wpforms-builder&view=fields&form_id=%d', $form_id ) ),
			];
		}

		return $list;
	}

	/**
	 * Format the amount threshold using the configured currency.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_threshold_formatted(): string {

		if ( function_exists( 'wpforms_format_amount' ) ) {
			return (string) wpforms_format_amount( $this->amount_threshold, true );
		}

		return '$' . number_format( $this->amount_threshold, 2 );
	}

	/**
	 * Format the block duration as a human-readable interval.
	 *
	 * Uses WordPress's human_time_diff() so the output respects locale and
	 * matches the unit users see elsewhere in admin (e.g. "5 mins", "1 hour").
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_duration_formatted(): string {

		$now = time();

		return human_time_diff( $now, $now + $this->block_duration );
	}
}
