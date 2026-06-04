<?php
/**
 * Email Summary body template (plain text).
 *
 * This template can be overridden by copying it to yourtheme/wpforms/emails/summary-body-plain.php.
 *
 * @since 1.5.4
 * @since 1.8.8 Added `$overview`, and `$notification_block` parameters.
 *
 * @var array $overview           Form entries overview data.
 * @var array $entries            Form entries data to loop through.
 * @var array $notification_block Notification block shown before the Info block.
 * @var array $info_block         Info block shown at the end of the email.
 * @var array $reengagement_alert Re-engagement alert args (Pro-only).
 */

use WPForms\Integrations\LiteConnect\LiteConnect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Used to separate strings in the email.
$separator  = '   |   ';
$divider    = "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
$utm_source = 'Weekly Summary Email';

echo esc_html__( 'Your Weekly WPForms Summary', 'wpforms-lite' ) . "\n\n";

echo esc_html__( 'Here’s how your forms performed this past week. Below is a breakdown of submissions by form.', 'wpforms-lite' ) . "\n\n";

if ( isset( $overview['total'] ) ) {
	echo $divider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	printf( /* translators: %1$d - number of entries. */
		esc_html__( '%1$d Total', 'wpforms-lite' ),
		absint( $overview['total'] )
	);

	if ( isset( $overview['trends'] ) ) {
		echo esc_html( $separator ) . ( (int) $overview['trends'] >= 0 ? '↑' : '↓' ) . esc_html( $overview['trends'] ) . "\n\n";
		echo wp_kses( _n( 'Entry This Week', 'Entries This Week', absint( $overview['total'] ), 'wpforms-lite' ), [] );
	}

	echo "\n\n" . $divider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

echo $divider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

echo esc_html__( 'Form', 'wpforms-lite' ) . esc_html( $separator ) . esc_html__( 'Entries', 'wpforms-lite' );

echo "\n\n" . $divider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

foreach ( $entries as $row ) {
	echo ( isset( $row['title'] ) ? esc_html( $row['title'] ) : '' ) . esc_html( $separator ) . ( isset( $row['count'] ) ? absint( $row['count'] ) : '' );

	if ( isset( $row['trends'] ) ) {
		echo esc_html( $separator ) . ( (int) $row['trends'] >= 0 ? '↑' : '↓' ) . esc_html( $row['trends'] );
	}

	echo "\n\n";
}

if ( empty( $entries ) ) {
	echo esc_html__( 'It appears you do not have any form entries yet.', 'wpforms-lite' ) . "\n\n";
}

if ( ! wpforms()->is_pro() ) {
	echo $divider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	if ( LiteConnect::is_enabled() ) {
		echo esc_html__( 'Your entries are being backed up, but only for one year.', 'wpforms-lite' ) . "\n\n";
		echo esc_html__( 'Lite Connect is saving your entries securely in the cloud. Upgrade to Pro to keep them permanently and manage all your entries right inside WordPress.', 'wpforms-lite' ) . "\n\n";
		echo esc_html__( 'Upgrade to Pro', 'wpforms-lite' ) . ': ';
		echo esc_url( wpforms_utm_link( 'https://wpforms.com/pricing-lite/', $utm_source, 'Upgrade - With LC' ) );
	} else {
		echo esc_html__( 'Your entries are not being backed up.', 'wpforms-lite' ) . "\n\n";
		echo esc_html__( 'Enable Lite Connect today to start storing entries. When you’re ready to manage your entries inside WordPress, just upgrade to Pro and we’ll import them in seconds!', 'wpforms-lite' ) . "\n\n";
		echo esc_html__( 'Enable Lite Connect', 'wpforms-lite' ) . ': ';
		echo esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-use-lite-connect-for-wpforms/', $utm_source, 'Documentation#backup-with-lite-connect' ) ) . "\n\n";
		echo esc_html__( 'Upgrade to Pro', 'wpforms-lite' ) . ': ';
		echo esc_url( wpforms_utm_link( 'https://wpforms.com/pricing-lite/', $utm_source, 'Upgrade - Without LC' ) );
	}

	echo "\n\n";
}

if ( ! empty( $reengagement_alert ) ) {
	echo $divider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo esc_html( $reengagement_alert['title'] ) . "\n\n";
	echo esc_html( $reengagement_alert['content'] ) . "\n\n";
	echo esc_html( $reengagement_alert['button_text'] ) . ': ' . esc_url( $reengagement_alert['button_url'] ) . "\n\n";
}

if ( ! empty( $notification_block ) ) {
	echo $divider . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	if ( ! empty( $notification_block['title'] ) ) {
		echo esc_html( $notification_block['title'] ) . "\n\n";
	}

	if ( ! empty( $notification_block['content'] ) ) {
		echo wp_kses_post( $notification_block['content'] ) . "\n\n";
	}

	if ( ! empty( $notification_block['btns']['main']['url'] ) && ! empty( $notification_block['btns']['main']['text'] ) ) {
		echo esc_html( $notification_block['btns']['main']['text'] ) . ': ' . esc_url( $notification_block['btns']['main']['url'] ) . "\n\n";
	}

	if ( ! empty( $notification_block['btns']['alt']['url'] ) && ! empty( $notification_block['btns']['alt']['text'] ) ) {
		echo esc_html( $notification_block['btns']['alt']['text'] ) . ': ' . esc_url( $notification_block['btns']['alt']['url'] ) . "\n\n";
	}
}

echo $divider . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! empty( $info_block['title'] ) ) {
	echo esc_html( $info_block['title'] ) . "\n\n";
}

if ( ! empty( $info_block['content'] ) ) {
	echo wp_kses_post( $info_block['content'] ) . "\n\n";
}

if ( ! empty( $info_block['button'] ) && ! empty( $info_block['url'] ) ) {
	echo esc_html( $info_block['button'] ) . ': ' . esc_url( $info_block['url'] ) . "\n\n";
}
