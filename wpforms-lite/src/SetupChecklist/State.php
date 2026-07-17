<?php

namespace WPForms\SetupChecklist;

/**
 * Setup Checklist per-site state.
 *
 * Owns the single per-site option that persists what the checklist itself
 * writes: whether it has been dismissed (and the progress percentage captured at
 * that moment, for telemetry), and the one-way "the user saved the email
 * settings" flag that the {@see CompletionDetector} reads for the email
 * notifications item. Detected plugin/setting state is never stored here — that
 * is recomputed live on each read.
 *
 * @since 2.0.0
 */
class State {

	/**
	 * Option name storing the checklist state blob.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const OPTION = 'wpforms_setup_checklist';

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function hooks(): void {

		add_action( 'wpforms_settings_updated', [ $this, 'maybe_flag_email_settings_saved' ], 10, 3 );
	}

	/**
	 * Whether the checklist has been dismissed on this site.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_dismissed(): bool {

		return ! empty( $this->get_all()['dismissed'] );
	}

	/**
	 * Dismiss the checklist, recording the progress percentage at dismissal.
	 *
	 * @since 2.0.0
	 *
	 * @param int $progress_percent Completion percentage at the moment of dismissal.
	 */
	public function dismiss( int $progress_percent ): void {

		$state                          = $this->get_all();
		$state['dismissed']             = true;
		$state['progress_at_dismissal'] = max( 0, min( 100, $progress_percent ) );

		$this->update( $state );
	}

	/**
	 * Progress percentage captured when the checklist was dismissed.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	public function get_progress_at_dismissal(): int {

		return (int) ( $this->get_all()['progress_at_dismissal'] ?? 0 );
	}

	/**
	 * Whether the user has saved the WPForms email settings at least once.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_email_settings_saved(): bool {

		return ! empty( $this->get_all()['email_settings_saved'] );
	}

	/**
	 * Flag email notifications as customized when the Email settings view is saved
	 * with an actual change.
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings     Saved settings (positional placeholder; `$updated` is the signal read).
	 * @param bool  $updated      Whether the settings option actually changed.
	 * @param array $old_settings An old array of plugin settings.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function maybe_flag_email_settings_saved( array $settings, bool $updated, array $old_settings ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		if ( ! $updated ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['view'] ) || sanitize_key( $_POST['view'] ) !== 'email' ) {
			return;
		}

		$this->set_email_settings_saved();
	}

	/**
	 * Persist the email-settings-saved flag once.
	 *
	 * @since 2.0.0
	 */
	private function set_email_settings_saved(): void {

		$state = $this->get_all();

		if ( ! empty( $state['email_settings_saved'] ) ) {
			return;
		}

		$state['email_settings_saved'] = true;

		$this->update( $state );
	}

	/**
	 * Read the full state blob.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	private function get_all(): array {

		$state = get_option( self::OPTION, [] );

		return is_array( $state ) ? $state : [];
	}

	/**
	 * Persist the full state blob.
	 *
	 * @since 2.0.0
	 *
	 * @param array $state State blob.
	 */
	private function update( array $state ): void {

		update_option( self::OPTION, $state, false );
	}
}
