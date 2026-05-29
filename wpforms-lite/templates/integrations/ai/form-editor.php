<?php
/**
 * WPForms Builder Smart Edit Modal Template.
 *
 * Outputs <script type="text/html"> blocks for the FAB and modal.
 * Rendered at runtime by form-editor.js via wp.template().
 *
 * @since 1.10.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<script type="text/html" id="tmpl-wpforms-smart-edit-fab">
	<button id="wpforms-smart-edit-fab"
	        class="wpforms-smart-edit-fab wpforms-smart-edit-fab-hidden"
	        type="button"
	        title="<?php esc_attr_e( 'Make changes with WPForms AI', 'wpforms-lite' ); ?>">
		<svg width="22" height="22" fill="#fff" viewBox="0 0 16 16" aria-hidden="true">
			<path d="M8.826 12.481c1.652.508 2.176 1.032 2.683 2.684.041.132.23.132.27 0 .508-1.653 1.031-2.176 2.683-2.684a.141.141 0 0 0 0-.27c-1.652-.507-2.175-1.03-2.683-2.683a.141.141 0 0 0-.27 0c-.507 1.653-1.03 2.176-2.683 2.684a.141.141 0 0 0 0 .27Zm-7.52-4.807c2.998-.922 3.948-1.871 4.869-4.87a.256.256 0 0 1 .49 0c.92 2.999 1.87 3.949 4.869 4.87.24.074.24.416 0 .49-2.999.921-3.948 1.87-4.87 4.869a.256.256 0 0 1-.49 0c-.92-2.998-1.87-3.948-4.868-4.87a.256.256 0 0 1 0-.489Zm7.52-4.411c1.652-.508 2.176-1.031 2.683-2.683a.141.141 0 0 1 .27 0c.508 1.652 1.031 2.175 2.683 2.683.133.04.133.23 0 .27-1.652.508-2.175 1.031-2.683 2.683a.141.141 0 0 1-.27 0c-.507-1.652-1.03-2.175-2.683-2.683a.141.141 0 0 1 0-.27Z"/>
		</svg>
		<span class="screen-reader-text"><?php esc_html_e( 'Smart Editing', 'wpforms-lite' ); ?></span>
	</button>
</script>

<script type="text/html" id="tmpl-wpforms-smart-edit-modal">
	<div id="wpforms-smart-edit-modal" class="wpforms-smart-edit-modal wpforms-smart-edit-modal-initial">

		<!-- Header — drag handle. -->
		<div class="wpforms-smart-edit-modal-header">
			<span class="wpforms-smart-edit-modal-title">
				<?php esc_html_e( 'WPForms AI', 'wpforms-lite' ); ?>
			</span>

			<div class="wpforms-smart-edit-modal-controls">
				<button class="js-wpforms-smart-edit-close wpforms-smart-edit-modal-btn"
				        title="<?php esc_attr_e( 'Hide', 'wpforms-lite' ); ?>">
					<i class="fa fa-chevron-down"></i>
				</button>
			</div>
		</div>

		<!-- Body — hosts the chat web component. -->
		<div class="wpforms-smart-edit-modal-body">
			<wpforms-ai-chat class="wpforms-ai-chat-light" mode="form-editor" field-id="form-editor"></wpforms-ai-chat>
		</div>

	</div>
</script>
