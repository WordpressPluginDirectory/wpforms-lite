<?php

namespace WPForms\Admin\Dashboard\Widgets;

use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\Helpers;
use WPForms\Admin\Dashboard\WidgetState;

/**
 * Dashboard widget base class.
 *
 * Defines the widget contract and renders the shared card shell.
 *
 * @since 2.0.2
 */
abstract class AbstractWidget {

	/**
	 * Column placement: 'main' or 'sidebar'.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'main';

	/**
	 * Default sort position within the column.
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 10;

	/**
	 * User meta key holding per-widget settings.
	 *
	 * @since 2.0.2
	 */
	public const SETTINGS_META_KEY = 'wpforms_dashboard_settings';

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	abstract public function get_id(): string;

	/**
	 * Get the widget title.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	abstract public function get_title(): string;

	/**
	 * Get the widget state for the given access context.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	abstract public function get_state( AccessContext $access ): WidgetState;

	/**
	 * Render the widget body.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	abstract protected function render_body( string $variant, array $data, AccessContext $access ): string;

	/**
	 * Render the widget footer. Empty string hides the footer.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_footer( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable default; the signature is the widget contract.

		return '';
	}

	/**
	 * Render the widget head (title row). Empty string renders get_title().
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_head( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable default; the signature is the widget contract.

		return '';
	}

	/**
	 * Extra state classes for the card root, beyond the id/variant ones the
	 * shell builds itself. Overridable default. Returning
	 * `wpforms-dashboard-widget-attention` opts the card into the responsive
	 * hoist above the stat cards (see dashboard-page.js).
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function get_extra_classes( string $variant, array $data, AccessContext $access ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable default; the signature is the widget contract.

		return [];
	}

	/**
	 * Render the widget into the shared card shell.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	public function render( string $variant, array $data, AccessContext $access ): string {

		$settings_schema = $this->get_settings_schema( $variant, $data, $access );

		return (string) wpforms_render(
			'admin/dashboard/widget',
			[
				'id'             => $this->get_id(),
				'variant'        => $variant,
				'extra_classes'  => $this->get_extra_classes( $variant, $data, $access ),
				'title'          => $this->get_title(),
				'head'           => $this->render_head( $variant, $data, $access ),
				'has_settings'   => ! empty( $settings_schema ),
				'settings'       => $this->render_settings( $settings_schema ),
				'body'           => $this->render_body( $variant, $data, $access ),
				'footer'         => $this->render_footer( $variant, $data, $access ),
				'is_dismissible' => $this->is_dismissible(),
			],
			true
		);
	}

	/**
	 * Get the widget column placement.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_column(): string {

		return static::COLUMN;
	}

	/**
	 * Declare the gear-menu settings for this widget as a field schema. Empty =
	 * no gear menu. The framework renders the popover + Save button and persists
	 * the fields; a widget only declares which controls it needs.
	 *
	 * Supported control types (see `templates/admin/dashboard/widget-settings.php`):
	 * `select`, `checklist` (one array setting), `checkboxes` (a group of boolean
	 * settings, optionally under a section label).
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function get_settings_schema( string $variant, array $data, AccessContext $access ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable default; the signature is the widget contract.

		return [];
	}

	/**
	 * Render the settings popover from a field schema via the shared template.
	 *
	 * @since 2.0.2
	 *
	 * @param array $fields Settings field schema.
	 *
	 * @return string
	 */
	private function render_settings( array $fields ): string {

		if ( empty( $fields ) ) {
			return '';
		}

		return (string) wpforms_render(
			'admin/dashboard/widget-settings',
			[
				'fields'    => $fields,
				'widget_id' => $this->get_id(),
				'can_reset' => $this->has_custom_settings(),
			],
			true
		);
	}

	/**
	 * Whether the user has moved the widget off its default settings, which is what the
	 * gear's reset control offers to undo. Answered from the declared defaults, so a
	 * widget states them once; one without a gear declares none and has nothing to reset.
	 *
	 * Only the declared keys are compared, which leaves out the state the gear does not
	 * own, and array values are compared as sets without the popover's empty sentinel.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public function has_custom_settings(): bool {

		$stored = $this->get_settings();

		foreach ( $this->get_default_settings() as $key => $default ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				continue;
			}

			if ( is_array( $default ) ? $this->normalize_list( $stored[ $key ] ) !== $this->normalize_list( $default ) : (string) $stored[ $key ] !== (string) $default ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Reduce a stored checklist value to a comparable set: the shared popover submits a
	 * hidden empty sentinel with every list, and the checkbox order follows the markup
	 * rather than the setting.
	 *
	 * @since 2.0.2
	 *
	 * @param mixed $value Stored or default list value.
	 *
	 * @return array
	 */
	private function normalize_list( $value ): array {

		$list = array_filter( array_map( 'strval', (array) $value ), 'strlen' );

		sort( $list );

		return $list;
	}

	/**
	 * The widget's default gear settings, shaped the way its popover submits them, so a
	 * reset can hand them straight to the client that renders the widget.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	public function get_default_settings(): array {

		return [];
	}

	/**
	 * Whether the widget can be dismissed by the user.
	 *
	 * When true, the shared shell adds the `wpforms-dismiss-container` class to the
	 * card root so the Education dismiss handler removes the whole card on click.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public function is_dismissible(): bool {

		return false;
	}

	/**
	 * Get the per-widget settings from user meta.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	public function get_settings(): array {

		$all = Helpers::get_user_meta_array( self::SETTINGS_META_KEY );

		return (array) ( $all[ $this->get_id() ] ?? [] );
	}

	/**
	 * Persist the per-widget settings into user meta.
	 *
	 * @since 2.0.2
	 *
	 * @param array $data Widget settings.
	 */
	public function save_settings( array $data ): void {

		$all = Helpers::get_user_meta_array( self::SETTINGS_META_KEY );

		$all[ $this->get_id() ] = $data;

		Helpers::update_user_meta_array( self::SETTINGS_META_KEY, $all );
	}
}
