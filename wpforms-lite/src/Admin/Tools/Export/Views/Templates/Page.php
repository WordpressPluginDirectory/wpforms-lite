<?php

namespace WPForms\Admin\Tools\Export\Views\Templates;

use WPForms\Admin\Tools\Export\Views\ExportViewsInterface;
use WPForms\Admin\Tools\Tools;
use WPForms\Helpers\Form;

/**
 * Export Templates Page class.
 *
 * Handles the Export Templates tab functionality.
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
	protected $slug = 'templates';

	/**
	 * Available forms.
	 *
	 * @since 1.10.1
	 *
	 * @var array
	 */
	private $forms = [];

	/**
	 * Template code if generated.
	 *
	 * @since 1.10.1
	 *
	 * @var string
	 */
	private $template = '';

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

		return __( 'Export Templates', 'wpforms-lite' );
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
	 * Export template process.
	 *
	 * @since 1.10.1
	 */
	public function process(): void {

		// phpcs:disable WordPress.Security.NonceVerification
		if (
			empty( $_POST['action'] ) ||
			$_POST['action'] !== 'export_template' ||
			! isset( $_POST['submit-export'] ) ||
			! $this->verify_nonce()
		) {
			return;
		}

		if ( empty( $_POST['form'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification

		$this->process_template();
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

			<h4 id="template-export"><?php esc_html_e( 'Export a Form Template', 'wpforms-lite' ); ?></h4>

			<?php
			if ( $this->template ) {
				$this->display_template_result();
			}
			?>

			<p><?php esc_html_e( 'Select a form to generate PHP code that can be used to register a custom form template.', 'wpforms-lite' ); ?></p>

			<form method="post" action="<?php echo esc_attr( $this->get_link() ); ?>">
				<?php $this->forms_select_html(); ?>
				<input type="hidden" name="action" value="export_template">
				<?php wp_nonce_field( 'wpforms_export_template_nonce', 'wpforms-tools-export-template-nonce' ); ?>
				<button name="submit-export" class="wpforms-btn wpforms-btn-md wpforms-btn-orange" id="wpforms-export-template" aria-disabled="true">
					<?php esc_html_e( 'Export Template', 'wpforms-lite' ); ?>
				</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Display template result.
	 *
	 * @since 1.10.1
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	private function display_template_result(): void {

		$doc_link = sprintf(
			wp_kses( /* translators: %s - WPForms.com docs URL. */
				__( 'For more information <a href="%s" target="_blank" rel="noopener noreferrer">see our documentation</a>.', 'wpforms-lite' ),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
				]
			),
			'https://wpforms.com/docs/how-to-create-a-custom-form-template/'
		);
		?>
		<p><?php esc_html_e( 'The following code can be used to register your custom form template. Copy and paste the following code to your theme\'s functions.php file or include it within an external file.', 'wpforms-lite' ); ?></p>
		<p><?php echo $doc_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<textarea class="info-area" readonly><?php echo esc_textarea( $this->template ); ?></textarea>
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
			<select id="wpforms-tools-form-template" class="choicesjs-select" name="form" data-search="<?php echo esc_attr( wpforms_choices_js_is_search_enabled( $this->forms ) ); ?>">
				<option value=""><?php esc_attr_e( 'Select a Template', 'wpforms-lite' ); ?></option>
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

		$nonce = isset( $_POST['wpforms-tools-export-template-nonce'] ) ? sanitize_key( $_POST['wpforms-tools-export-template-nonce'] ) : '';

		return (bool) wp_verify_nonce( $nonce, 'wpforms_export_template_nonce' );
	}

	/**
	 * Export template processing.
	 *
	 * @since 1.10.1
	 */
	private function process_template(): void {

		// Nonce is checked in the caller: process() method.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form_id  = isset( $_POST['form'] ) ? absint( $_POST['form'] ) : 0;
		$form_obj = wpforms()->obj( 'form' );

		if ( ! $form_obj || ! $form_id ) {
			return;
		}

		$form_data = $form_obj->get( $form_id, [ 'content_only' => true ] );

		// Define basic data with strict validation.
		$name = sanitize_text_field( $form_data['settings']['form_title'] ?? '' );
		$desc = sanitize_text_field( $form_data['settings']['form_desc'] ?? '' );
		$slug = sanitize_key( str_replace( [ ' ', '-' ], '_', trim( $name ) ) );

		if ( ! $slug ) {
			// Slug is always empty when the $form_data is not valid.
			return;
		}

		$class = 'WPForms_Template_' . $slug;
		$data  = $this->get_template_data( $slug, $form_data );

		// Build the final template string.
		$this->template = <<<EOT
if ( class_exists( 'WPForms_Template', false ) ) :
/**
 * {$name}
 * Template for WPForms.
 */
class $class extends WPForms_Template {

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Template name
		\$this->name = '$name';

		// Template slug
		\$this->slug = '$slug';

		// Template description
		\$this->description = '$desc';

		// Template field and settings
		\$this->data = $data;
	}
}
new $class();
endif;
EOT;
	}

	/**
	 * Get template data.
	 *
	 * @since 1.10.1
	 *
	 * @param string      $slug      Template slug.
	 * @param array|mixed $form_data Form data.
	 *
	 * @return string
	 */
	private function get_template_data( string $slug, $form_data ): string {

		// Format template field and settings data.
		$data                     = [];
		$data['meta']['template'] = $slug;
		$data['fields']           = isset( $form_data['fields'] ) && is_array( $form_data['fields'] )
			? wpforms_array_remove_empty_strings( $form_data['fields'] )
			: [];
		$data['settings']         = isset( $form_data['settings'] ) && is_array( $form_data['settings'] )
			? wpforms_array_remove_empty_strings( $form_data['settings'] )
			: [];

		$template_data = (string) var_export( $data, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		$template_data = str_replace( '  ', "\t", $template_data );

		return preg_replace( '/([\t\r\n]+?)array/', 'array', $template_data );
	}
}
