<?php

namespace WPForms\Admin\Dashboard\Widgets\Traits;

/**
 * Gear-popover settings readers for the Dashboard "Forms" widget: the form
 * picker's choices and the clamped, sanitized stored selection.
 *
 * Not standalone. The using class must declare the `DEFAULT_ROWS`, `MIN_ROWS`
 * and `MAX_ROWS` constants and provide `get_settings()` (inherited from
 * `AbstractWidget`).
 *
 * @since 2.0.2
 */
trait EntriesSettingsTrait {

	/**
	 * Memoized "site has published forms" flag. Null until first resolved.
	 *
	 * @since 2.0.2
	 *
	 * @var bool|null
	 */
	private $has_published_forms;

	/**
	 * Memoized form-picker options.
	 *
	 * @since 2.0.2
	 *
	 * @var array|null
	 */
	private $form_choices;

	/**
	 * Memoized resolved gear settings.
	 *
	 * @since 2.0.2
	 *
	 * @var array|null
	 */
	private $resolved_settings;

	/**
	 * Build the form-picker options — every published form, sorted alphabetically.
	 * Fetched through the form handler so access-control and multilingual filters apply.
	 *
	 * @since 2.0.2
	 *
	 * @return array Form ID => title.
	 */
	private function get_form_choices(): array {

		if ( $this->form_choices !== null ) {
			return $this->form_choices;
		}

		$handler = wpforms()->obj( 'form' );
		$forms   = $handler ? $handler->get( '' ) : [];
		$choices = [];

		foreach ( is_array( $forms ) ? $forms : [] as $form ) {
			$choices[ (int) $form->ID ] = $form->post_title;
		}

		natcasesort( $choices );

		$this->form_choices = $choices;

		return $this->form_choices;
	}

	/**
	 * Build the form-picker options with the selected forms first, each group still
	 * alphabetical. A long list otherwise buries the handful of forms the user picked,
	 * and reordering here rather than in JS keeps the list still while boxes are being
	 * checked: it re-sorts on the next render, which is the save.
	 *
	 * @since 2.0.2
	 *
	 * @param array $selection Selected form IDs.
	 *
	 * @return array Form ID => title.
	 */
	private function get_ordered_form_choices( array $selection ): array {

		$choices = $this->get_form_choices();

		// Picking out of the choices keeps the selected group alphabetical too, and the
		// union appends the rest in order without renumbering a form ID.
		return wpforms_list_only( $choices, $selection ) + $choices;
	}

	/**
	 * Get the per-user gear settings, clamped/defaulted/sanitized.
	 *
	 * @since 2.0.2
	 *
	 * @return array {
	 *     @type bool  $graph Whether the trend graph is visible; on by default.
	 *     @type int   $count Table row cap, clamped to MIN_ROWS..MAX_ROWS.
	 *     @type array $forms Selected form IDs; empty keeps the default top-N mode.
	 * }
	 */
	private function get_resolved_settings(): array {

		if ( $this->resolved_settings !== null ) {
			return $this->resolved_settings;
		}

		$settings = $this->get_settings();
		$count    = isset( $settings['count'] ) ? (int) $settings['count'] : self::DEFAULT_ROWS;
		$forms    = isset( $settings['forms'] ) && is_array( $settings['forms'] ) ? $settings['forms'] : [];

		// The checklist submits a hidden empty sentinel (shared widget-settings template),
		// so non-numeric entries are filtered out. The selection sets the row count, so it
		// is capped at the same ceiling the gear enforces client-side, before the lookup
		// that resolves it. Intersecting with the picker options then drops forms trashed
		// since the selection was saved — the picker lists published forms only, so a stale
		// ID would render a row the gear offers no checkbox to remove.
		$selection = array_slice( array_filter( array_map( 'absint', $forms ) ), 0, self::MAX_ROWS );

		$this->resolved_settings = [
			'graph' => ! isset( $settings['graph'] ) || ! empty( $settings['graph'] ),
			'count' => max( self::MIN_ROWS, min( self::MAX_ROWS, $count ) ),
			// Nothing picked means the top-N mode, which needs no form lookup at all.
			'forms' => $selection ? array_values( array_intersect( $selection, array_keys( $this->get_form_choices() ) ) ) : [],
		];

		return $this->resolved_settings;
	}

	/**
	 * The widget's default gear settings, in the shape its popover submits.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	public function get_default_settings(): array {

		return [
			'graph' => '1',
			'count' => (string) self::DEFAULT_ROWS,
			'forms' => [],
		];
	}

	/**
	 * Whether the site has at least one published form.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function has_published_forms(): bool {

		if ( $this->has_published_forms !== null ) {
			return $this->has_published_forms;
		}

		$handler = wpforms()->obj( 'form' );
		$forms   = $handler ? $handler->get( '' ) : [];

		$this->has_published_forms = ! empty( $forms );

		return $this->has_published_forms;
	}
}
