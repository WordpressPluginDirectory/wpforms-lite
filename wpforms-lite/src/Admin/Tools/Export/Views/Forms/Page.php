<?php

namespace WPForms\Admin\Tools\Export\Views\Forms;

use WPForms\Admin\Tools\Export\Views\ExportViewsInterface;
use WPForms\Admin\Tools\Tools;
use WPForms\Helpers\Form;

/**
 * Export Forms Page class.
 *
 * Handles the Export Forms tab functionality.
 *
 * @since 1.10.1
 */
class Page implements ExportViewsInterface {

	/**
	 * View slug.
	 *
	 * @since 1.10.1
	 *
	 * @var string
	 */
	protected $slug = 'forms';

	/**
	 * Available forms.
	 *
	 * @since 1.10.1
	 *
	 * @var array
	 */
	private $forms = [];

	/**
	 * Initialize class.
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

		add_action( 'wpforms_tools_init', [ $this, 'process' ] );
	}

	/**
	 * Get the Tab label.
	 *
	 * @since 1.10.1
	 *
	 * @return string
	 */
	public function get_tab_label(): string {

		return __( 'Export Forms', 'wpforms-lite' );
	}

	/**
	 * Check if the current user has the capability to view the page.
	 *
	 * @since 1.10.1
	 *
	 * @return bool
	 */
	public function current_user_can(): bool {

		return wpforms_current_user_can( 'edit_forms' );
	}

	/**
	 * Export process.
	 *
	 * @since 1.10.1
	 */
	public function process(): void {

		// phpcs:disable WordPress.Security.NonceVerification
		if (
			empty( $_POST['action'] ) ||
			$_POST['action'] !== 'export_form' ||
			! isset( $_POST['submit-export'] ) ||
			! $this->verify_nonce()
		) {
			return;
		}

		if ( empty( $_POST['forms'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification

		$this->process_export();
	}

	/**
	 * Page content.
	 *
	 * @since 1.10.1
	 */
	public function display(): void {

		$this->forms = Form::get_all();

		if ( empty( $this->forms ) ) {
			echo wpforms_render( 'admin/empty-states/no-forms' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			return;
		}
		?>

		<div class="wpforms-setting-row tools">

			<h4 id="form-export"><?php esc_html_e( 'Export Forms', 'wpforms-lite' ); ?></h4>

			<p><?php esc_html_e( 'Use form export files to create a backup of your forms or to import forms to another site.', 'wpforms-lite' ); ?></p>

			<form method="post" action="<?php echo esc_attr( $this->get_link() ); ?>">
				<?php $this->forms_select_html(); ?>
				<input type="hidden" name="action" value="export_form">
				<?php wp_nonce_field( 'wpforms_export_forms_nonce', 'wpforms-tools-export-forms-nonce' ); ?>
				<button name="submit-export" class="wpforms-btn wpforms-btn-md wpforms-btn-orange" id="wpforms-export-form" aria-disabled="true">
					<?php esc_html_e( 'Export Forms', 'wpforms-lite' ); ?>
				</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Get a link to the view page.
	 *
	 * @since 1.10.1
	 *
	 * @return string
	 */
	private function get_link(): string {

		return add_query_arg(
			[
				'page' => Tools::SLUG,
				'view' => 'export',
				'tab'  => $this->slug,
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Forms selector HTML.
	 *
	 * @since 1.10.1
	 */
	private function forms_select_html(): void {
		?>

		<span class="choicesjs-select-wrap">
			<select id="wpforms-tools-form-export" class="choicesjs-select" name="forms[]" multiple size="1" data-search="<?php echo esc_attr( wpforms_choices_js_is_search_enabled( $this->forms ) ); ?>">
				<option value=""><?php esc_attr_e( 'Select Form(s)', 'wpforms-lite' ); ?></option>
				<?php foreach ( $this->forms as $form ) : ?>
					<option value="<?php echo absint( $form->ID ); ?>"><?php echo esc_html( $form->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</span>
		<?php
	}

	/**
	 * Verify nonce field.
	 *
	 * @since 1.10.1
	 *
	 * @return bool
	 */
	private function verify_nonce(): bool {

		$nonce = isset( $_POST['wpforms-tools-export-forms-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpforms-tools-export-forms-nonce'] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, 'wpforms_export_forms_nonce' );
	}

	/**
	 * Export processing.
	 *
	 * @since 1.10.1
	 */
	private function process_export(): void {

		// Nonce is verified in process().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form_ids = isset( $_POST['forms'] ) ? array_map( 'absint', (array) $_POST['forms'] ) : [];
		$export   = $this->get_forms_data( $form_ids );

		ignore_user_abort( true );

		wpforms_set_time_limit();

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wpforms-form-export-' . current_time( 'm-d-Y' ) . '.json' );
		header( 'Expires: 0' );

		echo wp_json_encode( $export );
		exit;
	}

	/**
	 * Get the exportable data for the given form IDs.
	 *
	 * Only published forms of the `wpforms` post type are exportable, matching
	 * the query this method replaced. Forms the current user is not allowed to
	 * read are skipped: the form handler applies the object-level capability
	 * check per form when access controls are active.
	 *
	 * @since 2.0.0.3
	 *
	 * @param array $form_ids Requested form IDs.
	 *
	 * @return array
	 */
	private function get_forms_data( array $form_ids ): array {

		$form_obj = wpforms()->obj( 'form' );

		if ( ! $form_obj ) {
			return [];
		}

		$export = [];

		foreach ( array_unique( array_filter( $form_ids ) ) as $form_id ) {
			// Only published forms may be exported: the form handler alone would also return templates and trashed forms.
			if ( get_post_type( $form_id ) !== 'wpforms' || get_post_status( $form_id ) !== 'publish' ) {
				continue;
			}

			// The form handler applies the `view_form_single` capability check when access controls are active.
			$form_data = $form_obj->get( $form_id, [ 'content_only' => true ] );

			if ( empty( $form_data ) ) {
				continue;
			}

			$export[] = $form_data;
		}

		return $export;
	}
}
