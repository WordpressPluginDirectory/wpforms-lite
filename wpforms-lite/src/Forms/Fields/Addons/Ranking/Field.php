<?php

namespace WPForms\Forms\Fields\Addons\Ranking;

use WPForms\Forms\Fields\Traits\ProField as ProFieldTrait;
use WPForms\Forms\IconChoices;
use WPForms_Field;

/**
 * Ranking field.
 *
 * @since 2.0.0.5
 */
class Field extends WPForms_Field {

	use ProFieldTrait;

	/**
	 * Primary class constructor.
	 *
	 * @since 2.0.0.5
	 */
	public function init() {

		$this->name       = esc_html__( 'Ranking', 'wpforms-lite' );
		$this->keywords   = esc_html__( 'rank, order, priority, survey', 'wpforms-lite' );
		$this->type       = 'ranking';
		$this->icon       = 'fa-chart-simple';
		$this->order      = 307;
		$this->group      = 'fancy';
		$this->addon_slug = 'surveys-polls';

		// The default description is intentionally not listed here: these
		// defaults are merged into every stored field via field_data(), which
		// would inject the description into saved fields that lack the key.
		// New fields receive it in field_new_default() instead.
		$this->default_settings = [
			'survey'  => '1',
			'choices' => [
				1 => [
					'label' => esc_html__( 'Option 1', 'wpforms-lite' ),
					'value' => '',
				],
				2 => [
					'label' => esc_html__( 'Option 2', 'wpforms-lite' ),
					'value' => '',
				],
				3 => [
					'label' => esc_html__( 'Option 3', 'wpforms-lite' ),
					'value' => '',
				],
			],
		];

		$this->init_pro_field();
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 2.0.0.5
	 */
	protected function hooks() {}

	/**
	 * Get the default field descriptions keyed by layout.
	 *
	 * The builder swaps one default for another when the layout changes, so
	 * the same translated strings must back both the PHP prefill and the JS
	 * comparison.
	 *
	 * @since 2.0.0.5
	 *
	 * @return array
	 */
	public static function get_layout_default_descriptions(): array {

		return [
			'list' => esc_html__( 'Put these options in order, with your most preferred at the top.', 'wpforms-lite' ),
			'grid' => esc_html__( 'Put these options in order, with your most preferred first.', 'wpforms-lite' ),
		];
	}

	/**
	 * Define new field defaults.
	 *
	 * The new-field request pre-fills the description with an empty string,
	 * which wp_parse_args() in the base method treats as a set value, so the
	 * default description is applied here explicitly. Only new fields go
	 * through this path: a description cleared in the builder stays empty.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field Field settings.
	 *
	 * @return array
	 */
	public function field_new_default( $field ): array {

		$field = parent::field_new_default( $field );
		$type  = $field['type'] ?? '';

		if ( $type === $this->type && wpforms_is_empty_string( $field['description'] ?? '' ) ) {
			$field['description'] = self::get_layout_default_descriptions()['list'];
		}

		return $field;
	}

	/**
	 * Field options panel inside the builder.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field Field settings.
	 */
	public function field_options( $field ) {
		/*
		 * Basic field options.
		 */

		// Options open markup.
		$this->field_option(
			'basic-options',
			$field,
			[
				'markup'      => 'open',
				'after_title' => $this->get_field_options_notice(),
			]
		);

		// Label.
		$this->field_option( 'label', $field );

		// Description.
		$this->field_option( 'description', $field );

		// Choices repeater (includes Bulk Add).
		$this->field_option( 'choices', $field );

		// AI Generate Choices button.
		$this->field_option(
			'ai_modal_button',
			$field,
			[
				'value' => esc_html__( 'Generate Choices', 'wpforms-lite' ),
				'type'  => 'choices',
			]
		);

		// Use Image Choices toggle.
		$this->field_option( 'choices_images', $field );

		// Hide image labels toggle.
		$this->field_option( 'choices_images_hide', $field );

		// Image size (Small/Medium/Large) — ranking-specific option.
		$this->field_option_image_size( $field );

		// Use Icon Choices toggle.
		$this->field_option( 'choices_icons', $field );

		// Icon accent color.
		$this->field_option( 'choices_icons_color', $field );

		// Icon size (Small/Medium/Large).
		$this->field_option( 'choices_icons_size', $field );

		// Options close markup.
		$this->field_option(
			'basic-options',
			$field,
			[
				'markup' => 'close',
			]
		);

		/*
		 * Advanced field options.
		 */

		// Options open markup.
		$this->field_option(
			'advanced-options',
			$field,
			[
				'markup' => 'open',
			]
		);

		// Randomize order of choices.
		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'random',
				'content' => $this->field_element(
					'toggle',
					$field,
					[
						'slug'    => 'random',
						'value'   => isset( $field['random'] ) ? '1' : '0',
						'desc'    => esc_html__( 'Randomize Choices', 'wpforms-lite' ),
						'tooltip' => esc_html__( 'Check this option to randomize the order of the choices.', 'wpforms-lite' ),
					],
					false
				),
			]
		);

		// Field size.
		$this->field_option( 'size', $field );

		// Layout: List / Grid.
		$this->field_option_layout( $field );

		// Columns (visible only when Layout = Grid).
		$this->field_option_columns( $field );

		// Arrows visibility.
		$this->field_option_arrows( $field );

		// Dynamic Choices.
		$this->field_option( 'dynamic_choices', $field );

		// Dynamic choice source (post type or taxonomy).
		$this->field_option( 'dynamic_choices_source', $field );

		// Custom CSS classes.
		$this->field_option( 'css', $field );

		// Hide label.
		$this->field_option( 'label_hide', $field );

		// Hide choice labels (visible only when Image Choices is on).
		$this->field_option_choices_labels_hide( $field );

		// Options close markup.
		// Note: the Read-Only toggle is injected automatically by WPForms_Field via
		// ReadOnlyFieldTrait on the 'wpforms_field_options_bottom_advanced-options' action.
		$this->field_option(
			'advanced-options',
			$field,
			[
				'markup' => 'close',
			]
		);
	}

	/**
	 * Image size (Small/Medium/Large) option row — ranking-specific.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field Field settings.
	 */
	private function field_option_image_size( array $field ): void {

		// A fixed list, not IconChoices::get_icon_sizes(): the frontend renderer
		// only recognizes these slugs, and the icon-sizes filter must not leak
		// icon-specific customizations into the image-size option.
		$size_options = [
			'large'  => esc_html__( 'Large', 'wpforms-lite' ),
			'medium' => esc_html__( 'Medium', 'wpforms-lite' ),
			'small'  => esc_html__( 'Small', 'wpforms-lite' ),
		];

		$image_size_lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'choices_images_size',
				'value'   => esc_html__( 'Image Size', 'wpforms-lite' ),
				'tooltip' => esc_html__( 'Select the size for image choices.', 'wpforms-lite' ),
			],
			false
		);

		$image_size_fld = $this->field_element(
			'select',
			$field,
			[
				'slug'    => 'choices_images_size',
				'value'   => ! empty( $field['choices_images_size'] ) ? esc_attr( $field['choices_images_size'] ) : 'large',
				'options' => $size_options,
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'choices_images_size',
				'content' => $image_size_lbl . $image_size_fld,
				'class'   => ! empty( $field['choices_images'] ) ? '' : 'wpforms-hidden',
			]
		);
	}

	/**
	 * Hide Choice Label option row (visible only when Image Choices is on).
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field Field settings.
	 */
	private function field_option_choices_labels_hide( array $field ): void {

		$fld = $this->field_element(
			'toggle',
			$field,
			[
				'slug'    => 'choices_labels_hide',
				'value'   => ! empty( $field['choices_labels_hide'] ) ? '1' : '0',
				'desc'    => esc_html__( 'Hide Choice Label', 'wpforms-lite' ),
				'tooltip' => esc_html__( 'Check this option to hide the label for each choice and rely on the image.', 'wpforms-lite' ),
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'choices_labels_hide',
				'content' => $fld,
				'class'   => ! empty( $field['choices_images'] ) ? '' : 'wpforms-hidden',
			]
		);
	}

	/**
	 * Layout option row (List / Grid).
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field Field settings.
	 */
	private function field_option_layout( array $field ): void {

		$layout_lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'input_layout',
				'value'   => esc_html__( 'Layout', 'wpforms-lite' ),
				'tooltip' => esc_html__( 'Select the display layout for the ranking field.', 'wpforms-lite' ),
			],
			false
		);

		$layout_fld = $this->field_element(
			'select',
			$field,
			[
				'slug'    => 'input_layout',
				'value'   => ! empty( $field['input_layout'] ) ? $field['input_layout'] : 'list',
				'options' => [
					'list' => esc_html__( 'List', 'wpforms-lite' ),
					'grid' => esc_html__( 'Grid', 'wpforms-lite' ),
				],
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'input_layout',
				'content' => $layout_lbl . $layout_fld,
			]
		);
	}

	/**
	 * Columns option row (visible only when Layout = Grid).
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field Field settings.
	 */
	private function field_option_columns( array $field ): void {

		$is_grid = isset( $field['input_layout'] ) && $field['input_layout'] === 'grid';
		$col_lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'ranking_columns',
				'value'   => esc_html__( 'Columns', 'wpforms-lite' ),
				'tooltip' => esc_html__( 'Number of columns to display in grid layout.', 'wpforms-lite' ),
			],
			false
		);

		$col_fld = $this->field_element(
			'select',
			$field,
			[
				'slug'    => 'ranking_columns',
				'value'   => ! empty( $field['ranking_columns'] ) ? $field['ranking_columns'] : '3',
				'options' => [
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				],
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'ranking_columns',
				'content' => $col_lbl . $col_fld,
				'class'   => $is_grid ? '' : 'wpforms-hidden',
			]
		);
	}

	/**
	 * Arrows visibility option row.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field Field settings.
	 */
	private function field_option_arrows( array $field ): void {

		$arrows_lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'input_arrows',
				'value'   => esc_html__( 'Arrows', 'wpforms-lite' ),
				'tooltip' => esc_html__( 'Control when the reorder arrow buttons are visible.', 'wpforms-lite' ),
			],
			false
		);

		$arrows_fld  = $this->field_element(
			'select',
			$field,
			[
				'slug'    => 'input_arrows',
				'value'   => ! empty( $field['input_arrows'] ) ? $field['input_arrows'] : 'hover',
				'options' => [
					'hover'   => esc_html__( 'On Hover', 'wpforms-lite' ),
					'visible' => esc_html__( 'Visible', 'wpforms-lite' ),
					'never'   => esc_html__( 'Never', 'wpforms-lite' ),
				],
			],
			false
		);
		$arrows_note = '<p class="sub-label">' . esc_html__( 'Arrows are always displayed on touch devices.', 'wpforms-lite' ) . '</p>';

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'input_arrows',
				'content' => $arrows_lbl . $arrows_fld . $arrows_note,
			]
		);
	}

	/**
	 * Get choices for the preview, resolving dynamic choices (post type or taxonomy) when configured.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field Field settings.
	 *
	 * @return array
	 */
	private function get_preview_choices( array $field ): array {

		$dynamic = $this->is_dynamic_choices( $field ) ? $field['dynamic_choices'] : '';

		if ( ( $dynamic === 'post_type' && ! empty( $field['dynamic_post_type'] ) ) ||
			( $dynamic === 'taxonomy' && ! empty( $field['dynamic_taxonomy'] ) ) ) {

			$choices = [];

			foreach ( $this->get_dynamic_preview_choices( $field ) as $index => $item ) {
				$choices[ $index + 1 ] = [
					'label' => $item['label'],
					'value' => '',
				];
			}

			return $choices;
		}

		return ! empty( $field['choices'] ) ? $field['choices'] : $this->default_settings['choices'];
	}

	/**
	 * Render the dynamic choices limit notice in the builder preview when total > 20.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field Field settings.
	 */
	private function field_preview_dynamic_choices_notice( array $field ): void {

		if ( ! $this->is_dynamic_choices( $field ) ) {
			return;
		}

		$limit = 20;
		$type  = $field['dynamic_choices'];
		$total = 0;

		if ( $type === 'post_type' && ! empty( $field['dynamic_post_type'] ) ) {
			$count = wp_count_posts( $field['dynamic_post_type'] );
			$total = isset( $count->publish ) ? (int) $count->publish : 0;
		} elseif ( $type === 'taxonomy' && ! empty( $field['dynamic_taxonomy'] ) ) {
			$total = (int) wp_count_terms( $field['dynamic_taxonomy'] );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->field_preview_choices_limit_notice( $total, $limit );
	}

	/**
	 * Field preview inside the builder.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field Field settings.
	 */
	public function field_preview( $field ) {

		$choices     = $this->get_preview_choices( $field );
		$is_grid     = ! empty( $field['input_layout'] ) && $field['input_layout'] === 'grid';
		$columns     = ! empty( $field['ranking_columns'] ) ? max( 2, min( 6, absint( $field['ranking_columns'] ) ) ) : 3;
		$show_arrows = isset( $field['input_arrows'] ) && $field['input_arrows'] === 'visible';

		// Label.
		$this->field_preview_option(
			'label',
			$field,
			[
				'label_badge' => $this->get_field_preview_badge(),
			]
		);

		// Description.
		$this->field_preview_option( 'description', $field );

		$style = $this->get_preview_style( $field, $is_grid, $columns );
		?>
		<ul class="<?php echo wpforms_sanitize_classes( $this->get_preview_classes( $field, $is_grid, $columns ), true ); ?>"
			<?php if ( $style ) : ?>
				style="<?php echo esc_attr( $style ); ?>"
			<?php endif; ?>>
			<?php
			foreach ( $choices as $key => $choice ) {
				$this->field_preview_item( $field, $choice, $key, $is_grid, $show_arrows );
			}
			?>
		</ul>

		<?php $this->field_preview_dynamic_choices_notice( $field ); ?>

		<?php

		// Hide remaining elements.
		$this->field_preview_option( 'hide-remaining', $field );
	}

	/**
	 * Render a single preview list item.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array      $field       Field settings.
	 * @param array      $choice      Choice data.
	 * @param int|string $key         Choice key, used as fallback label number.
	 * @param bool       $is_grid     Whether the layout is grid.
	 * @param bool       $show_arrows Whether arrows are visible.
	 */
	private function field_preview_item( array $field, array $choice, $key, bool $is_grid, bool $show_arrows ): void {

		$raw_label = isset( $choice['label'] ) ? trim( $choice['label'] ) : '';
		$label     = $raw_label !== '' ? $raw_label : $this->get_choice_placeholder_label( $key );
		$media     = $this->get_preview_item_media( $field, $choice );
		?>
		<li class="wpforms-ranking-preview-item">
			<?php if ( $is_grid ) : ?>
				<?php if ( $show_arrows ) : ?>
					<i class="fa fa-chevron-left wpforms-ranking-preview-arrow" aria-hidden="true"></i>
				<?php endif; ?>
				<span class="wpforms-ranking-preview-content">
					<?php echo $media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="wpforms-ranking-preview-label"><?php echo esc_html( $label ); ?></span>
				</span>
				<?php if ( $show_arrows ) : ?>
					<i class="fa fa-chevron-right wpforms-ranking-preview-arrow" aria-hidden="true"></i>
				<?php endif; ?>
			<?php else : ?>
				<?php echo $media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="wpforms-ranking-preview-label"><?php echo esc_html( $label ); ?></span>
				<?php if ( $show_arrows ) : ?>
					<span class="wpforms-ranking-preview-arrows">
						<i class="fa fa-chevron-up wpforms-ranking-preview-arrow" aria-hidden="true"></i>
						<i class="fa fa-chevron-down wpforms-ranking-preview-arrow" aria-hidden="true"></i>
					</span>
				<?php endif; ?>
				<i class="fa fa-grip-vertical wpforms-ranking-preview-grip" aria-hidden="true"></i>
			<?php endif; ?>
		</li>
		<?php
	}

	/**
	 * CSS classes for the preview list container.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field   Field settings.
	 * @param bool  $is_grid Whether the layout is grid.
	 * @param int   $columns Effective column count.
	 *
	 * @return array
	 */
	private function get_preview_classes( array $field, bool $is_grid, int $columns ): array {

		$classes = [
			'wpforms-ranking-preview',
			'wpforms-ranking-preview-' . ( $is_grid ? 'grid' : 'list' ),
		];

		if ( $is_grid && $columns >= 4 ) {
			$classes[] = 'wpforms-ranking-preview-dense';
		}

		// One size class scales both media types, so images reuse the icon-size class.
		if ( $this->is_icon_choices( $field ) || $this->is_image_choices( $field ) ) {
			$classes[] = 'wpforms-ranking-preview-icon-' . $this->get_preview_media_size( $field );
			$classes[] = 'wpforms-ranking-preview-has-media';
		}

		if ( $this->is_image_choices( $field ) && ! empty( $field['choices_labels_hide'] ) ) {
			$classes[] = 'wpforms-ranking-preview-labels-hidden';
		}

		return $classes;
	}

	/**
	 * Inline style for the preview list container.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field   Field settings.
	 * @param bool  $is_grid Whether the layout is grid.
	 * @param int   $columns Effective column count.
	 *
	 * @return string
	 */
	private function get_preview_style( array $field, bool $is_grid, int $columns ): string {

		$styles = [];

		if ( $is_grid ) {
			$styles[] = sprintf( 'grid-template-columns: repeat( %d, 1fr );', $columns );
		}

		if ( $this->is_icon_choices( $field ) ) {
			$icon_color = wpforms_sanitize_hex_color( $field['choices_icons_color'] ?? '' );
			$styles[]   = sprintf( '--wpforms-icon-choices-color: %s;', $icon_color ? $icon_color : IconChoices::get_default_color() );
		}

		return implode( ' ', $styles );
	}

	/**
	 * Media size slug shared by the icon and image preview scales.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field Field settings.
	 *
	 * @return string One of small, medium, large.
	 */
	private function get_preview_media_size( array $field ): string {

		$setting = $this->is_image_choices( $field ) ? 'choices_images_size' : 'choices_icons_size';
		$size    = $field[ $setting ] ?? '';

		return in_array( $size, [ 'small', 'medium', 'large' ], true ) ? $size : 'large';
	}

	/**
	 * Image or icon markup for a single preview item.
	 *
	 * Rendered here rather than in the builder JS so the preview is correct on
	 * load and while the Surveys and Polls addon is inactive. The markup mirrors
	 * what the addon rebuilds on every option change, so the two never diverge.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field  Field settings.
	 * @param array $choice Choice data.
	 *
	 * @return string
	 * @noinspection HtmlUnknownTarget
	 */
	private function get_preview_item_media( array $field, array $choice ): string {

		if ( $this->is_image_choices( $field ) ) {
			$image = ! empty( $choice['image'] ) ? $choice['image'] : WPFORMS_PLUGIN_URL . 'assets/images/builder/placeholder-200x125.svg';

			return sprintf(
				'<img src="%s" class="wpforms-ranking-preview-image" alt="" aria-hidden="true">',
				esc_url( $image )
			);
		}

		if ( ! $this->is_icon_choices( $field ) ) {
			return '';
		}

		$icon       = ! empty( $choice['icon'] ) ? $choice['icon'] : IconChoices::DEFAULT_ICON;
		$icon_style = ! empty( $choice['icon_style'] ) ? $choice['icon_style'] : IconChoices::DEFAULT_ICON_STYLE;

		return sprintf(
			'<i class="ic-fa-preview ic-fa-%1$s ic-fa-%2$s wpforms-ranking-preview-icon" aria-hidden="true"></i>',
			esc_attr( $icon_style ),
			esc_attr( $icon )
		);
	}

	/**
	 * Placeholder label for a choice with an empty label.
	 *
	 * The single source for every surface — builder previews, frontend display,
	 * and prefill matching — so a blank-labeled choice reads identically
	 * everywhere. Matches the builder's ranking_choice_empty_label_tpl string.
	 *
	 * @since 2.0.0.5
	 *
	 * @param int|string $key Choice key.
	 *
	 * @return string
	 */
	protected function get_choice_placeholder_label( $key ): string {

		/* translators: %d - option number. */
		return sprintf( esc_html__( 'Option %d', 'wpforms-lite' ), (int) $key );
	}

	/**
	 * Format one ranked choice label the way it is stored in the entry value.
	 *
	 * The stored value is a newline-joined list of these lines. Entry export and
	 * entry import both parse it back with strip_rank_prefix(), so the numbering
	 * format is defined here only.
	 *
	 * @since 2.0.0.5
	 *
	 * @param int    $position One-based rank position.
	 * @param string $label    Choice label.
	 *
	 * @return string
	 */
	public static function format_ranked_label( int $position, string $label ): string {

		return $position . '. ' . $label;
	}

	/**
	 * Strip the rank prefix from a stored ranked line.
	 *
	 * Inverse of format_ranked_label(). A line without a rank prefix is returned
	 * unchanged, which lets callers detect that case by comparing the result
	 * with the input.
	 *
	 * @since 2.0.0.5
	 *
	 * @param string $line Stored ranked line, e.g. "2. Option B".
	 *
	 * @return string
	 */
	public static function strip_rank_prefix( string $line ): string {

		return trim( (string) preg_replace( '/^\d+\.\s*/', '', $line ) );
	}

	/**
	 * Field display on the form front-end.
	 *
	 * @since 2.0.0.5
	 *
	 * @param array $field      Field settings.
	 * @param array $deprecated Deprecated array.
	 * @param array $form_data  Form data and settings.
	 */
	public function field_display( $field, $deprecated, $form_data ) {}
}
