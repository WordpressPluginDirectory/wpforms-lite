<?php
/**
 * Dashboard "Forms" widget Lite Connect bar (Lite only).
 *
 * A full-width band with two views the shared Lite Connect education script
 * swaps live: the opt-in toggle with a help tip and the muted "not stored" note
 * when Lite Connect is off, or the backup-status info when it is on. The
 * `wpforms-education-lite-connect-*` classes and the toggle input ID are that
 * script's contract — keep them in sync with the classic-dashboard markup
 * (`education/admin/lite-connect/dashboard-widget-before`).
 *
 * @since 2.0.2
 *
 * @var string $toggle             Enable Entry backups toggle markup.
 * @var bool   $is_enabled         Is backup entry enabled?
 * @var string $entries_since_info Entries information string, already escaped by its builder.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wpforms-education-lite-connect-wrapper">
	<div class="wpforms-education-lite-connect-setting <?php echo $is_enabled ? 'wpforms-hidden' : ''; ?>">
		<?php echo $toggle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<i class="fa fa-question-circle-o wpforms-help-tooltip wpforms-education-lite-connect-help"
			title="<?php esc_attr_e( 'Entries are available through email notifications. If you enable Entry Backups, you can restore them once you upgrade to WPForms Pro.', 'wpforms-lite' ); ?>"
			aria-hidden="true"></i>
		<span class="wpforms-education-lite-connect-note"><?php esc_html_e( 'Entries are not stored in WPForms Lite', 'wpforms-lite' ); ?></span>
	</div>
	<div class="wpforms-education-lite-connect-enabled-info <?php echo ! $is_enabled ? 'wpforms-hidden' : ''; ?>">
		<i class="fa fa-info-circle" aria-hidden="true"></i>
		<span><?php echo $entries_since_info; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the builder; re-escaping renders entities literally in translated strings. ?></span>
		<a href="<?php echo esc_url( wpforms_admin_upgrade_link( 'Dashboard - Entries', 'restore-entries' ) ); ?>" target="_blank" rel="noopener noreferrer" class="wpforms-upgrade-modal">
			<?php esc_html_e( 'Restore Entries', 'wpforms-lite' ); ?>
		</a>
	</div>
</div>
