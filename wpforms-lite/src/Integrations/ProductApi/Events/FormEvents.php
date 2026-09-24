<?php

namespace WPForms\Integrations\ProductApi\Events;

use Throwable;
use WP_Post;
use WPForms\Forms\Locator;
use WPForms\Integrations\IntegrationInterface;
use WPForms\Integrations\ProductApi\ProductEvents;
use WPForms\Tasks\Actions\FormsLocatorScanTask;
use WPForms\Vendor\ProductApi\ProductApi;

/**
 * Form lifecycle product events.
 *
 * Reports a form the first time a visitor can reach it. A form sitting on five
 * pages is one activation, not five, so the event is guarded by a flag stored on
 * the form and never fires twice.
 *
 * There is no backfill. The Locator scan task and the permalink rescan rewrite the
 * locations meta of forms that have been live for years, so both are ignored on
 * purpose, and such a form is reported only when a human next saves a page
 * carrying it. That late report is accepted; a silent partial backfill is not.
 *
 * @since 2.0.2.1
 */
class FormEvents implements IntegrationInterface {

	/**
	 * Event name.
	 *
	 * @since 2.0.2.1
	 */
	public const EVENT_PUBLISHED = 'form_published';

	/**
	 * Form meta holding the embed method the form was first reached by.
	 *
	 * Its presence is the guard. The value is the answer to which method won, so
	 * there is no second key to keep in sync with this one.
	 *
	 * @since 2.0.2.1
	 */
	public const PUBLISHED_META = 'wpforms_product_events_published';

	/**
	 * Embed method: the WPForms Gutenberg block, the site editor included.
	 *
	 * @since 2.0.2.1
	 */
	public const METHOD_BLOCK = 'block';

	/**
	 * Embed method: the `[wpforms]` shortcode.
	 *
	 * @since 2.0.2.1
	 */
	public const METHOD_SHORTCODE = 'shortcode';

	/**
	 * Embed method: any of the three widget types Locator scans.
	 *
	 * A widget counts as soon as it exists, whatever its sidebar: the block widgets screen
	 * saves the widget before it assigns the sidebar, so the sidebar is unknown at the only
	 * moment this class hears about the widget.
	 *
	 * @since 2.0.2.1
	 */
	public const METHOD_WIDGET = 'widget';

	/**
	 * Embed method: the Divi module, version 4 or 5.
	 *
	 * @since 2.0.2.1
	 */
	public const METHOD_DIVI = 'divi';

	/**
	 * Embed method: the Elementor widget.
	 *
	 * @since 2.0.2.1
	 */
	public const METHOD_ELEMENTOR = 'elementor';

	/**
	 * Embed method: the Form Pages addon.
	 *
	 * @since 2.0.2.1
	 */
	public const METHOD_FORM_PAGE = 'form_page';

	/**
	 * Embed method: the Conversational Forms addon.
	 *
	 * @since 2.0.2.1
	 */
	public const METHOD_CONVERSATIONAL = 'conversational';

	/**
	 * Indicate if the integration is allowed to load.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	public function allow_load(): bool {

		// The same test ProductEvents uses, so the two load or stay out together.
		return class_exists( ProductApi::class );
	}

	/**
	 * Load the integration.
	 *
	 * @since 2.0.2.1
	 */
	public function load(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.2.1
	 */
	private function hooks(): void {

		// Locator funnels the shortcode, the block, every widget type, the site editor and
		// both standalone addons into one meta, so one pair of hooks covers all of them.
		add_action( 'added_post_meta', [ $this, 'maybe_track_location' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'maybe_track_location' ], 10, 4 );

		// Locator skips that write when publishing changes nothing but the status, and Divi 5
		// and Elementor never reach it at all, so a saved page is read for all three.
		add_action( 'save_post', [ $this, 'maybe_track_post' ], 10, 2 );
	}

	/**
	 * Report a form that Locator has just recorded a reachable location for.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int    $meta_id    Meta row ID.
	 * @param int    $object_id  Form ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function maybe_track_location( $meta_id, $object_id, $meta_key, $meta_value ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		if ( $meta_key !== Locator::LOCATIONS_META || ! is_array( $meta_value ) ) {
			return;
		}

		// A background rewrite of the meta is not a publication, see the class docblock.
		if ( doing_action( FormsLocatorScanTask::SAVE_ACTION ) || doing_action( FormsLocatorScanTask::RESCAN_ACTION ) ) {
			return;
		}

		$product_events = $this->get_product_events();
		$form_id        = (int) $object_id;

		if ( ! $product_events || ! $this->is_trackable( $form_id ) ) {
			return;
		}

		foreach ( $meta_value as $location ) {
			$method = is_array( $location ) ? $this->get_location_method( $location, $form_id ) : '';

			if ( $method !== '' ) {
				$this->track_published( $product_events, $form_id, $method );

				return;
			}
		}
	}

	/**
	 * Report the forms a saved post carries, as read off the post itself.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 */
	public function maybe_track_post( $post_id, $post ): void {

		$post_id = (int) $post_id;

		if (
			! $post instanceof WP_Post ||
			$post->post_status !== 'publish' ||
			wp_is_post_revision( $post_id ) ||
			wp_is_post_autosave( $post_id )
		) {
			return;
		}

		$product_events = $this->get_product_events();

		// Consent settles before anything parses a page.
		if ( ! $product_events ) {
			return;
		}

		$locator = wpforms()->obj( 'locator' );

		// Locator's own post types are public or publicly queryable plus the site editor
		// templates, so a form's own `wpforms` post, a Divi library layout and any private CPT
		// are out before anything reads their content or meta.
		if ( ! $locator instanceof Locator || ! in_array( $post->post_type, $locator->get_post_types(), true ) ) {
			return;
		}

		foreach ( $this->get_post_form_ids( $post, $locator ) as $form_id => $method ) {
			if ( $this->is_trackable( $form_id ) ) {
				$this->track_published( $product_events, $form_id, $method );
			}
		}
	}

	/**
	 * Get the form IDs embedded on a post, keyed by embed method.
	 *
	 * @since 2.0.2.1
	 *
	 * @param WP_Post $post    Post object, of a type Locator scans.
	 * @param Locator $locator Locator, for the syntaxes it already matches.
	 *
	 * @return array Form ID => embed method.
	 */
	private function get_post_form_ids( WP_Post $post, Locator $locator ): array {

		$form_ids = [];

		foreach ( $this->get_divi_form_ids( $post->post_content ) as $form_id ) {
			$form_ids[ $form_id ] = self::METHOD_DIVI;
		}

		// The meta path cannot see the syntax for a template, so both paths report the same thing.
		$is_template = $post->post_type === Locator::WP_TEMPLATE || $post->post_type === Locator::WP_TEMPLATE_PART;

		foreach ( $locator->get_form_ids( $post->post_content ) as $form_id ) {
			$method = $is_template ? self::METHOD_BLOCK : $this->get_content_method( $post->post_content, $form_id );

			if ( $method !== '' ) {
				$form_ids[ $form_id ] = $form_ids[ $form_id ] ?? $method;
			}
		}

		foreach ( $this->get_elementor_form_ids( $post->ID ) as $form_id ) {
			// The first syntax read wins a tie; a page carrying the same form twice is a
			// broken page either way.
			$form_ids[ $form_id ] = $form_ids[ $form_id ] ?? self::METHOD_ELEMENTOR;
		}

		return $form_ids;
	}

	/**
	 * Whether a form is worth doing any work for.
	 *
	 * Consent has settled before this runs. What is left is whether the ID names a form at
	 * all, since the builder paths read IDs off page content, whether the form is published,
	 * since the frontend refuses to render a trashed one so nobody can reach it, and whether
	 * it was reported. The status comes off the same cached post as the type.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return bool
	 */
	private function is_trackable( int $form_id ): bool {

		return $form_id > 0 &&
			get_post_type( $form_id ) === 'wpforms' &&
			get_post_status( $form_id ) === 'publish' &&
			get_post_meta( $form_id, self::PUBLISHED_META, true ) === '';
	}

	/**
	 * Report the form, once.
	 *
	 * @since 2.0.2.1
	 *
	 * @param ProductEvents $product_events Product events integration, consent already checked.
	 * @param int           $form_id        Form ID.
	 * @param string        $method         Embed method.
	 */
	private function track_published( ProductEvents $product_events, int $form_id, string $method ): void {

		// Returns false when the key already exists. Core checks and then inserts, so two saves
		// landing between the two can both report the form: one extra analytics row, accepted.
		if ( ! add_post_meta( $form_id, self::PUBLISHED_META, $method, true ) ) {
			return;
		}

		try {
			// Not track(): that one wants manage_options, and the person publishing a page is
			// as often an editor. This path checks consent and sends on shutdown of the same
			// request, so the event is still attributed to whoever actually published.
			$sent = $product_events->track_system(
				self::EVENT_PUBLISHED,
				[
					'form_id'            => (string) $form_id,
					'embed_method'       => $method,
					'days_since_created' => $this->get_days_since_created( $form_id ),
				]
			);
		} catch ( Throwable $e ) {
			// This runs while a post is being saved. Losing the event costs a row in a
			// funnel; letting it escape costs the customer their page.
			unset( $e );

			$sent = false;
		}

		// A flag with no event behind it would silence the form for good, so the next save retries.
		if ( ! $sent ) {
			delete_post_meta( $form_id, self::PUBLISHED_META );
		}
	}

	/**
	 * Resolve the embed method of a location, or an empty string when it is out of reach.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array $location Location, as Locator stores it.
	 * @param int   $form_id  Form ID.
	 *
	 * @return string
	 */
	private function get_location_method( array $location, int $form_id ): string {

		$type = $location['type'] ?? '';

		// A standalone location exists only while its addon setting is on, so there is
		// nothing further to test.
		$standalone = [
			'form_pages'           => self::METHOD_FORM_PAGE,
			'conversational_forms' => self::METHOD_CONVERSATIONAL,
		];

		if ( isset( $standalone[ $type ] ) ) {
			return $standalone[ $type ];
		}

		// Reachable as soon as it exists, see METHOD_WIDGET.
		if ( $type === Locator::WIDGET ) {
			return self::METHOD_WIDGET;
		}

		return $this->get_post_location_method( $location, (string) $type, $form_id );
	}

	/**
	 * Resolve the embed method of a location that lives on a post.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array  $location Location, as Locator stores it.
	 * @param string $type     Location type, which here is the post type.
	 * @param int    $form_id  Form ID.
	 *
	 * @return string
	 */
	private function get_post_location_method( array $location, string $type, int $form_id ): string {

		// A post nobody can open yet is not a publication.
		if ( ( $location['status'] ?? '' ) !== 'publish' ) {
			return '';
		}

		// A site editor template is reached with the same block as a page.
		if ( $type === Locator::WP_TEMPLATE || $type === Locator::WP_TEMPLATE_PART ) {
			return self::METHOD_BLOCK;
		}

		$post = get_post( (int) ( $location['id'] ?? 0 ) );

		return $post instanceof WP_Post ? $this->get_content_method( $post->post_content, $form_id ) : '';
	}

	/**
	 * Resolve which syntax embedded a form in a post.
	 *
	 * Locator records that a form is on a post but not how it got there, and the two it
	 * matches are different buckets in the report. Divi is tested first because its own
	 * shortcode starts with `wpforms` and would otherwise read as the core one.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $content Post content.
	 * @param int    $form_id Form ID.
	 *
	 * @return string
	 */
	private function get_content_method( string $content, int $form_id ): string {

		$id = (string) $form_id;

		if (
			preg_match( '#\[\s*wpforms_selector[^]]*form_id\s*=\s*"' . $id . '"#', $content ) ||
			in_array( $form_id, $this->get_divi_form_ids( $content ), true )
		) {
			return self::METHOD_DIVI;
		}

		if ( preg_match( '#wp:wpforms/form-selector\s*\{[^}]*"formId"\s*:\s*"' . $id . '"#', $content ) ) {
			return self::METHOD_BLOCK;
		}

		if ( preg_match( '#\[\s*wpforms\s[^]]*id\s*=\s*"' . $id . '"#', $content ) ) {
			return self::METHOD_SHORTCODE;
		}

		return '';
	}

	/**
	 * Get the form IDs embedded with the Divi 5 module.
	 *
	 * Divi 4 needs nothing here: its shortcode starts with `wpforms`, so Locator already
	 * matches it and the location carries it. Divi 5 stores a block of its own whose ID is
	 * nested a level deeper than the core one, which Locator matches neither half of.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $content Post content.
	 *
	 * @return int[]
	 */
	private function get_divi_form_ids( string $content ): array {

		if ( strpos( $content, 'wp:wpforms/divi-form-selector' ) === false ) {
			return [];
		}

		// The core parser, because the ID is nested JSON and a module with no form chosen
		// yet would send a regular expression running on into the next block's numbers.
		return array_values( array_unique( $this->find_divi_form_ids( parse_blocks( $content ) ) ) );
	}

	/**
	 * Walk the block tree for Divi 5 modules.
	 *
	 * Divi wraps the module in a placeholder, a section, a row and a column, so the top
	 * level of the tree never holds it.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array $blocks Parsed blocks.
	 *
	 * @return int[]
	 */
	private function find_divi_form_ids( array $blocks ): array {

		$form_ids = [];

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( ( $block['blockName'] ?? '' ) === 'wpforms/divi-form-selector' ) {
				$form_ids[] = (int) ( $block['attrs']['formId']['desktop']['value'] ?? 0 );
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$form_ids = array_merge( $form_ids, $this->find_divi_form_ids( $block['innerBlocks'] ) );
			}
		}

		return array_filter( $form_ids );
	}

	/**
	 * Get the form IDs embedded with the Elementor widget.
	 *
	 * Gated three times over, because this is the only part of the feature that reads a
	 * meta nothing else on the request has asked for, and `_elementor_data` runs to
	 * hundreds of kilobytes on a heavy page.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int[]
	 */
	private function get_elementor_form_ids( int $post_id ): array {

		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return [];
		}

		$data = get_post_meta( $post_id, '_elementor_data', true );

		// A substring scan over that string is cheap; decoding it is not.
		if ( ! is_string( $data ) || strpos( $data, '"widgetType":"wpforms"' ) === false ) {
			return [];
		}

		$decoded = json_decode( $data, true );

		return is_array( $decoded ) ? array_values( array_unique( $this->find_elementor_form_ids( $decoded ) ) ) : [];
	}

	/**
	 * Walk the Elementor element tree for WPForms widgets.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array $elements Elementor elements.
	 *
	 * @return int[]
	 */
	private function find_elementor_form_ids( array $elements ): array {

		$form_ids = [];

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( ( $element['widgetType'] ?? '' ) === 'wpforms' ) {
				$form_ids[] = (int) ( $element['settings']['form_id'] ?? 0 );
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$form_ids = array_merge( $form_ids, $this->find_elementor_form_ids( $element['elements'] ) );
			}
		}

		return array_filter( $form_ids );
	}

	/**
	 * Get the form's age in whole days.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return int
	 */
	private function get_days_since_created( int $form_id ): int {

		$form = get_post( $form_id );

		if ( ! $form instanceof WP_Post ) {
			return 0;
		}

		// Read off the GMT column, so a later timezone change does not move the answer, and
		// false for the zero date, which strtotime() would turn into two million days.
		$created_date = get_post_datetime( $form, 'date', 'gmt' );

		if ( ! $created_date ) {
			return 0;
		}

		$created = $created_date->getTimestamp();

		return (int) max( 0, floor( ( time() - $created ) / DAY_IN_SECONDS ) );
	}

	/**
	 * Get the product events integration, or null when the site has not consented.
	 *
	 * Consent is an option read with no query behind it, so each entry point resolves this
	 * once and first, and a site with product events off pays nothing further.
	 *
	 * @since 2.0.2.1
	 *
	 * @return ProductEvents|null
	 */
	private function get_product_events(): ?ProductEvents {

		$product_events = wpforms()->obj( 'ProductApi\ProductEvents' );

		return $product_events instanceof ProductEvents && $product_events->is_enabled() ? $product_events : null;
	}
}
