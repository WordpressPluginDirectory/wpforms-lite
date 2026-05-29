<?php

namespace WPForms\Admin\Tools\Import\Views\Forms;

use WP_Error;
use WPForms\Helpers\File;
use WPForms\Admin\Tools\Importers;
use WPForms\Admin\Tools\Tools;
use WPForms_Form_Handler;
use WPForms\Admin\Tools\Import\Views\ImportViewsInterface;

/**
 * Forms Import Page class.
 *
 * Handles importing WPForms JSON exports and third-party form plugins.
 *
 * @since 1.10.1
 */
class Page implements ImportViewsInterface {

	/**
	 * View slug.
	 *
	 * @since 1.10.1
	 *
	 * @var string
	 */
	protected $slug = 'import';

	/**
	 * Registered importers.
	 *
	 * @since 1.10.1
	 *
	 * @var array
	 */
	public $importers = [];

	/**
	 * Initialize class.
	 *
	 * @since 1.10.1
	 */
	public function init(): void {

		$this->hooks();

		$this->importers = ( new Importers() )->get_importers();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.10.1
	 */
	private function hooks(): void {

		add_action( 'wpforms_tools_init', [ $this, 'import_process' ] );
	}

	/**
	 * Check if the current user has the capability to view the page.
	 *
	 * @since 1.10.1
	 *
	 * @return bool
	 */
	public function current_user_can(): bool {

		return wpforms_current_user_can( 'create_forms' );
	}

	/**
	 * Get the Tab label.
	 *
	 * @since 1.10.1
	 *
	 * @return string
	 */
	public function get_tab_label(): string {

		return __( 'Import Forms', 'wpforms-lite' );
	}

	/**
	 * Import process.
	 *
	 * @since 1.10.1
	 */
	public function import_process(): void {

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if (
			empty( $_POST['action'] ) ||
			$_POST['action'] !== 'import_form' ||
			empty( $_FILES['file']['tmp_name'] ) ||
			! isset( $_POST['submit-import'] ) ||
			! $this->verify_nonce()
		) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$this->process();
	}

	/**
	 * Page content.
	 *
	 * @since 1.10.1
	 */
	public function display(): void {

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$this->error_unfiltered_html_import_message();

			return;
		}

		$this->success_import_message();
		$this->wpforms_block();
		$this->other_forms_block();
	}

	/**
	 * Error message for users with no `unfiltered_html` permission.
	 *
	 * @since 1.10.1
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	private function error_unfiltered_html_import_message(): void {

		printf(
			'<div class="notice notice-error inline"><p>%s</p></div>',
			sprintf(
				wp_kses( /* translators: %s - WPForms contact page URL. */
					__( 'You can\'t import forms because you don\'t have unfiltered HTML permissions. Please contact your site administrator or <a href="%s" target="_blank" rel="noopener noreferrer">reach out to our support team</a>.', 'wpforms-lite' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
						],
					]
				),
				esc_url( wpforms_utm_link( 'https://wpforms.com/contact/', 'Tools - Import', 'Support Link - Import Forms No Permissions' ) )
			)
		);
	}

	/**
	 * Success import message.
	 *
	 * @since 1.10.1
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	private function success_import_message(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wpforms_notice'] ) && $_GET['wpforms_notice'] === 'forms-imported' ) {
			?>
			<div class="updated notice is-dismissible">
				<p>
					<?php esc_html_e( 'Import was successfully finished.', 'wpforms-lite' ); ?>
					<?php
					if ( wpforms_current_user_can( 'view_forms' ) ) {
						printf(
							wp_kses( /* translators: %s - forms list page URL. */
								__( 'You can go and <a href="%s">check your forms</a>.', 'wpforms-lite' ),
								[ 'a' => [ 'href' => [] ] ]
							),
							esc_url( admin_url( 'admin.php?page=wpforms-overview' ) )
						);
					}
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * WPForms section.
	 *
	 * @since 1.10.1
	 */
	private function wpforms_block(): void {
		?>

		<div class="wpforms-setting-row tools wpforms-settings-row-divider">
			<h4><?php esc_html_e( 'WPForms Import', 'wpforms-lite' ); ?></h4>
			<p><?php esc_html_e( 'Select a WPForms export file.', 'wpforms-lite' ); ?></p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_attr( $this->get_link() ); ?>">
				<div class="wpforms-file-upload">
					<input type="file" name="file" id="wpforms-tools-form-import" class="inputfile"
						data-multiple-caption="{count} <?php esc_attr_e( 'files selected', 'wpforms-lite' ); ?>"
						accept=".json"
						aria-label="<?php esc_attr_e( 'Upload WPForms export file', 'wpforms-lite' ); ?>" />
					<label for="wpforms-tools-form-import">
						<span class="fld" aria-hidden="true"><span class="placeholder"><?php esc_html_e( 'No file chosen', 'wpforms-lite' ); ?></span></span>
						<strong class="wpforms-btn wpforms-btn-md wpforms-btn-light-grey" aria-hidden="true">
							<i class="fa fa-cloud-upload" aria-hidden="true"></i><?php esc_html_e( 'Choose a File', 'wpforms-lite' ); ?>
						</strong>
					</label>
				</div>
				<input type="hidden" name="action" value="import_form">
				<button name="submit-import" class="wpforms-btn wpforms-btn-md wpforms-btn-orange" id="wpforms-import" aria-disabled="true">
					<?php esc_html_e( 'Import Forms', 'wpforms-lite' ); ?>
				</button>
				<?php wp_nonce_field( 'wpforms_' . $this->slug . '_nonce', 'wpforms-tools-' . $this->slug . '-nonce' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Other forms section.
	 *
	 * @since 1.10.1
	 *
	 * @noinspection HtmlUnknownAttribute
	 */
	private function other_forms_block(): void {
		?>

		<div class="wpforms-setting-row tools" id="wpforms-importers">
			<h4><?php esc_html_e( 'Import Forms from Other Form Plugins', 'wpforms-lite' ); ?></h4>
			<p><?php esc_html_e( 'Not happy with other WordPress contact form plugins?', 'wpforms-lite' ); ?></p>
			<p><?php esc_html_e( 'WPForms makes it easy for you to switch by allowing you to import your third-party forms with a single click.', 'wpforms-lite' ); ?></p>

			<div class="wpforms-importers-wrap">
				<?php if ( empty( $this->importers ) ) { ?>
					<p><?php esc_html_e( 'No form importers are currently enabled.', 'wpforms-lite' ); ?> </p>
				<?php } else { ?>
					<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
						<span class="choicesjs-select-wrap">
							<select id="wpforms-tools-form-other-import" class="choicesjs-select" name="provider" data-search="<?php echo esc_attr( wpforms_choices_js_is_search_enabled( $this->importers ) ); ?>" required>
								<option value=""><?php esc_html_e( 'Select previous form plugin', 'wpforms-lite' ); ?></option>
								<?php
								foreach ( $this->importers as $importer ) {
									$status = '';

									if ( empty( $importer['installed'] ) ) {
										$status = esc_html__( 'Not Installed', 'wpforms-lite' );
									} elseif ( empty( $importer['active'] ) ) {
										$status = esc_html__( 'Not Active', 'wpforms-lite' );
									}
									printf(
										'<option value="%s" %s>%s %s</option>',
										esc_attr( $importer['slug'] ),
										! empty( $status ) ? 'disabled' : '',
										esc_html( $importer['name'] ),
										! empty( $status ) ? '(' . esc_html( $status ) . ')' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									);
								}
								?>
							</select>
						</span>
						<input type="hidden" name="page" value="<?php echo esc_attr( Tools::SLUG ); ?>">
						<input type="hidden" name="view" value="importer">
						<button class="wpforms-btn wpforms-btn-md wpforms-btn-orange" id="wpforms-import-other" aria-disabled="true">
							<?php esc_html_e( 'Import Forms', 'wpforms-lite' ); ?>
						</button>
					</form>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Import processing.
	 *
	 * @since 1.10.1
	 */
	private function process(): void { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		// Add a filter of the link rel attr to avoid JSON damage.
		add_filter( 'wp_targeted_link_rel', '__return_empty_string', 50, 1 );

		$ext = '';

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_FILES['file']['name'] ) ) {
			$ext = strtolower( pathinfo( sanitize_text_field( wp_unslash( $_FILES['file']['name'] ) ), PATHINFO_EXTENSION ) );
		}

		if ( $ext !== 'json' ) {
			wp_die(
				esc_html__( 'Please upload a valid .json form export file.', 'wpforms-lite' ),
				esc_html__( 'Error', 'wpforms-lite' ),
				[
					'response' => 400,
				]
			);
		}

		// The wp_unslash() function breaks upload on Windows.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Missing
		$filename = isset( $_FILES['file']['tmp_name'] ) ? sanitize_text_field( $_FILES['file']['tmp_name'] ) : '';

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = $this->import_forms( $filename );

		if ( $result !== null ) {
			wp_die(
				esc_html( $result->get_error_message() ),
				esc_html__( 'Error', 'wpforms-lite' ),
				[
					'response' => 400,
				]
			);
		}

		wp_safe_redirect( add_query_arg( [ 'wpforms_notice' => 'forms-imported' ] ) );
		exit;
	}

	/**
	 * Import forms from a file.
	 *
	 * @since 1.10.1
	 *
	 * @param string $filename File containing forms to be imported.
	 *
	 * @return null|WP_Error
	 */
	private function import_forms( string $filename ): ?WP_Error {

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return new WP_Error( 'no_permission', __( 'The unfiltered HTML permissions are required to import form.', 'wpforms-lite' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$forms = json_decode( File::remove_utf8_bom( file_get_contents( $filename ) ), true );

		if ( empty( $forms ) || ! is_array( $forms ) ) {
			return new WP_Error( 'bad_json', __( 'Please upload a valid .json form export file.', 'wpforms-lite' ) );
		}

		if ( ! $this->save_forms( $forms ) ) {
			return new WP_Error( 'no_permission', __( 'There was an error saving your form. Please check your file and try again.', 'wpforms-lite' ) );
		}

		return null;
	}

	/**
	 * Save forms.
	 *
	 * @since 1.10.1
	 *
	 * @param array $forms Forms.
	 *
	 * @return bool
	 */
	private function save_forms( array $forms ): bool {

		foreach ( $forms as $form ) {
			$title  = ! empty( $form['settings']['form_title'] ) ? $form['settings']['form_title'] : '';
			$desc   = ! empty( $form['settings']['form_desc'] ) ? $form['settings']['form_desc'] : '';
			$new_id = wp_insert_post(
				[
					'post_title'   => wp_slash( $title ),
					'post_status'  => 'publish',
					'post_type'    => 'wpforms',
					'post_excerpt' => wp_slash( $desc ),
				]
			);

			// When we cannot insert one form into the DB or update it,
			// we will have a similar issue with the following form in the JSON file.
			// So, it is better to bail out and inform the user that we cannot proceed.
			if ( ! $new_id ) {
				return false;
			}

			$form['id'] = $new_id;

			if ( ! $this->update_form( $form ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Update form.
	 *
	 * @since 1.10.1
	 *
	 * @param array $form Form.
	 *
	 * @return bool
	 */
	private function update_form( array $form ): bool {

		if ( wpforms_is_form_data_slashing_enabled() ) {
			$form = wp_slash( $form );
		}

		$result = wp_update_post(
			[
				'ID'           => $form['id'],
				'post_content' => wpforms_encode( $form ),
			]
		);

		if ( ! $result ) {
			return false;
		}

		if ( empty( $form['settings']['form_tags'] ) ) {
			return true;
		}

		$result = wp_set_post_terms(
			$form['id'],
			implode( ',', (array) $form['settings']['form_tags'] ),
			WPForms_Form_Handler::TAGS_TAXONOMY
		);

		if ( ! $result ) {
			return false;
		}

		return true;
	}

	/**
	 * Get a link to the import view.
	 *
	 * @since 1.10.1
	 *
	 * @return string
	 */
	private function get_link(): string {

		return add_query_arg(
			[
				'page' => Tools::SLUG,
				'view' => $this->slug,
				'tab'  => 'forms',
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Verify nonce field.
	 *
	 * @since 1.10.1
	 *
	 * @return bool
	 */
	private function verify_nonce(): bool {

		$nonce_name = 'wpforms-tools-' . $this->slug . '-nonce';
		$nonce      = isset( $_POST[ $nonce_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, 'wpforms_' . $this->slug . '_nonce' );
	}
}
