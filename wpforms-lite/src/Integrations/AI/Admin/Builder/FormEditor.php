<?php

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection AutoloadingIssuesInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace WPForms\Integrations\AI\Admin\Builder;

use WPForms\Integrations\AI\Helpers;
use WPForms\Integrations\LiteConnect\LiteConnect;

/**
 * AI Form Editor builder enqueues and module registration.
 *
 * @since 1.10.1
 */
class FormEditor {

	/**
	 * Initialize.
	 *
	 * @since 1.10.1
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.10.1
	 */
	private function hooks(): void {

		add_action( 'wpforms_builder_enqueues', [ $this, 'enqueues' ] );
		add_filter( 'wpforms_builder_js_modules', [ $this, 'add_js_modules' ] );
		add_filter( 'wpforms_integrations_ai_admin_builder_enqueues_localize_chat_strings', [ $this, 'add_chat_mode_strings' ] );
		add_action( 'admin_footer', [ $this, 'output' ] );
	}

	/**
	 * Enqueue styles and scripts.
	 *
	 * @since 1.10.1
	 *
	 * @param string|null $view Current view (panel).
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function enqueues( $view ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		if ( $this->is_revision_view() ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-resizable' );

		wp_localize_script(
			'wpforms-builder',
			'wpforms_ai_form_editor',
			$this->get_localize_data()
		);
	}

	/**
	 * Output the Smart Edit template blocks.
	 *
	 * Outputs <script type="text/html"> templates for the FAB and modal.
	 * JS renders and attaches them to #wpforms-builder via wp.template().
	 *
	 * Bails out in revision view: AI editing of a historical revision would
	 * silently promote it to the current version (the JS save flow bypasses
	 * `confirmSaveRevision()` for the pre-AI checkpoint). The FAB is hidden
	 * server-side so revision view never even shows the entry point.
	 *
	 * @since 1.10.1
	 */
	public function output(): void {

		 // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;

		if ( ! $form_id || ! wpforms_current_user_can( 'edit_form_single', $form_id ) ) {
			return;
		}

		// The AI Form Editor is not available in revision view.
		if ( $this->is_revision_view() ) {
			return;
		}

		echo wpforms_render( 'integrations/ai/form-editor' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get allowed scopes for the form editor.
	 *
	 * @since 1.10.1
	 *
	 * @return array<string, array{priority: int}>
	 */
	public function get_allowed_scopes(): array {

		/**
		 * Filter the allowed scopes for the AI form editor.
		 *
		 * Each scope declares a priority that controls UI display order.
		 * The middleware determines the actual pipeline execution order.
		 *
		 * @since 1.10.1
		 *
		 * @param array $scopes Scopes keyed by name, each with a 'priority' value.
		 */
		return (array) apply_filters(
			'wpforms_integrations_ai_admin_builder_form_editor_get_allowed_scopes',
			[
				'fields'            => [ 'priority' => 10 ],
				'calculation'       => [ 'priority' => 20 ],
				'conditional_logic' => [ 'priority' => 30 ],
				'settings'          => [ 'priority' => 100 ],
			]
		);
	}

	/**
	 * Whether the builder is opened in revision view.
	 *
	 * Centralizes the `revision_id` query-var check used by `enqueues()`,
	 * `output()`, and `add_js_modules()`. The AI Form Editor is fully gated
	 * in revision view: assets are not localized, modules are not registered,
	 * and the FAB template is not rendered. Without this, the JS modules
	 * would still load and the module loader would log a "Template not found"
	 * error when trying to render the absent FAB.
	 *
	 * @since 1.10.1
	 *
	 * @return bool
	 */
	private function is_revision_view(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$revision_id = isset( $_GET['revision_id'] ) ? absint( wp_unslash( $_GET['revision_id'] ) ) : 0;

		return $revision_id > 0;
	}

	/**
	 * Get localize data for the AI Form Editor.
	 *
	 * @since 1.10.1
	 *
	 * @return array
	 */
	private function get_localize_data(): array {

		$scopes = $this->get_allowed_scopes();

		// Sort by priority and build an ordered key list for JS.
		uasort(
			$scopes,
			static function ( $a, $b ) {

				return ( $a['priority'] ?? 100 ) <=> ( $b['priority'] ?? 100 );
			}
		);

		$sorted_scopes = [];

		foreach ( array_keys( $scopes ) as $key ) {
			$sorted_scopes[] = [ 'key' => $key ];
		}

		return [
			'nonce'                 => wp_create_nonce( 'wpforms-ai-nonce' ),
			'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
			'isPro'                 => wpforms()->is_pro(),
			'isLicenseActive'       => Helpers::is_license_active(),
			'liteConnectAllowed'    => LiteConnect::is_allowed(),
			'liteConnectEnabled'    => LiteConnect::is_enabled(),
			'scopes'                => $sorted_scopes,
			'revisionToastTitle'    => esc_html__( 'Changes Saved', 'wpforms-lite' ),
			'revisionToast'         => wp_kses(
				__( 'A <a href="#" class="wpforms-panel-content-revisions-link">revision</a> has been automatically created.', 'wpforms-lite' ),
				[
					'a' => [
						'href'  => [],
						'class' => [],
					],
				]
			),
			'checkpointError'       => esc_html__( 'Restore point not saved', 'wpforms-lite' ),
			'checkpointErrorReason' => esc_html__( "Your AI edit didn't run because the form couldn't be saved. Please try again.", 'wpforms-lite' ),
		];
	}

	/**
	 * Add JS modules to the builder.
	 *
	 * @since 1.10.1
	 *
	 * @param array $modules List of JS modules.
	 *
	 * @return array
	 */
	public function add_js_modules( $modules ): array {

		$modules = (array) $modules;

		if ( $this->is_revision_view() ) {
			return $modules;
		}

		$min      = wpforms_get_min_suffix();
		$base_url = WPFORMS_PLUGIN_URL . 'assets/js/integrations/ai/form-editor/';

		$modules['AIFormEditor']                            = $base_url . "form-editor{$min}.js";
		$modules['AIFormEditorApi']                         = $base_url . "modules/api{$min}.js";
		$modules['AIFormEditorCommands']                    = $base_url . "modules/commands{$min}.js";
		$modules['AIFormEditorHelpers']                     = $base_url . "modules/helpers{$min}.js";
		$modules['AIFormEditorApplicatorsConditionalLogic'] = $base_url . "modules/applicators-conditional-logic{$min}.js";
		$modules['AIFormEditorApplicators']                 = $base_url . "modules/applicators{$min}.js";
		$modules['AIFormEditorApplicatorsBlocks']           = $base_url . "modules/applicators-blocks{$min}.js";

		return $modules;
	}

	/**
	 * Add chat mode strings for the form-editor mode.
	 *
	 * @since 1.10.1
	 *
	 * @param array $strings Localize strings.
	 *
	 * @return array
	 * @noinspection HtmlUnknownTarget
	 */
	public function add_chat_mode_strings( $strings ): array {

		$strings = (array) $strings;

		$strings['form-editor'] = [
			'title'              => esc_html__( 'WPForms AI', 'wpforms-lite' ),
			'description'        => esc_html__( 'What would you like to change? Describe it in plain language and we\'ll update your form fields, settings, and more.', 'wpforms-lite' ),
			'learnMore'          => esc_html__( 'Learn More About WPForms AI', 'wpforms-lite' ),
			'placeholder'        => esc_html__( 'Describe the changes you would like to make...', 'wpforms-lite' ),
			'waiting'            => esc_html__( 'Just a minute...', 'wpforms-lite' ),
			'stopButtonTooltip'  => esc_html__( 'Stop response', 'wpforms-lite' ),
			'errors'             => [
				'default'    => esc_html__( 'An error occurred while editing form.', 'wpforms-lite' ),
				'rate_limit' => esc_html__( 'You\'ve hit your daily AI request limit.', 'wpforms-lite' ),
			],
			'footer'             => [
				esc_html__( 'Changes applied! Need anything else?', 'wpforms-lite' ),
				esc_html__( 'Done! Want to make more changes?', 'wpforms-lite' ),
				esc_html__( 'All set! Anything else to adjust?', 'wpforms-lite' ),
				esc_html__( 'Changes made. What\'s next?', 'wpforms-lite' ),
			],
			'reasons'            => [
				'default'    => esc_html__( 'Please try again.', 'wpforms-lite' ),
				'rate_limit' => sprintf(
					wp_kses( /* translators: %s - WPForms contact support link. */
						__( 'You can make up to 50 AI requests per day. If you believe this is an error, <a href="%s" target="_blank" rel="noopener noreferrer">please contact WPForms support</a>.', 'wpforms-lite' ),
						[
							'a' => [
								'href'   => [],
								'target' => [],
								'rel'    => [],
							],
						]
					),
					wpforms_utm_link( 'https://wpforms.com/account/support/', 'AI Feature' )
				),
			],
			'samplePromptsTitle' => esc_html__( 'Example questions', 'wpforms-lite' ),
			'samplePrompts'      => [
				[
					'title' => esc_html__( 'How can I improve this form?', 'wpforms-lite' ),
				],
				[
					'title' => esc_html__( 'Send an email notification to the customer', 'wpforms-lite' ),
				],
				[
					'title' => esc_html__( 'Make all fields required', 'wpforms-lite' ),
				],
				[
					'title' => esc_html__( 'Change the submit button text to "Get Started"', 'wpforms-lite' ),
				],
				[
					'title' => esc_html__( 'Group the fields into sections', 'wpforms-lite' ),
				],
				[
					'title' => esc_html__( 'Add a GDPR consent checkbox', 'wpforms-lite' ),
				],
			],
			'responseButtons'    => [
				'like'    => esc_html__( 'Give positive feedback', 'wpforms-lite' ),
				'dislike' => esc_html__( 'Give negative feedback', 'wpforms-lite' ),
				'retry'   => esc_html__( 'Retry', 'wpforms-lite' ),
				'clear'   => esc_html__( 'Clear chat history', 'wpforms-lite' ),
			],
			'noChanges'          => esc_html__( 'No changes needed.', 'wpforms-lite' ),
			'scopeProgress'      => [
				'fields'            => esc_html__( 'Generating field changes...', 'wpforms-lite' ),
				'settings'          => esc_html__( 'Updating form settings...', 'wpforms-lite' ),
				'calculation'       => esc_html__( 'Generating calculations...', 'wpforms-lite' ),
				'conditional_logic' => esc_html__( 'Configuring conditional logic...', 'wpforms-lite' ),
			],
		];

		$strings['actions']['form-editor'] = 'wpforms_ai_form_editor_process';

		return $strings;
	}
}
