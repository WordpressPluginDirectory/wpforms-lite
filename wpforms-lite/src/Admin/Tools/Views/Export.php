<?php

namespace WPForms\Admin\Tools\Views;

use WPForms\Admin\Education\Admin\Tools\ExportEntries;
// phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement
use WPForms\Admin\Tools\Export\Views\ExportViewsInterface;
use WPForms\Admin\Tools\Export\Views\Forms\Page as FormsPage;
use WPForms\Admin\Tools\Export\Views\Templates\Page as TemplatesPage;
use WPForms\Admin\Tools\Tools;

/**
 * Class Export.
 * Handles the Export page with a tabbed interface for Entries, Forms, and Templates export.
 *
 * @since 1.6.6
 * @since 1.10.1 More export blocks to separate tabs and handle independently.
 */
class Export extends View {

	/**
	 * View slug.
	 *
	 * @since 1.6.6
	 *
	 * @var string
	 */
	protected $slug = 'export';

	/**
	 * Available sub-views (tabs).
	 *
	 * @since 1.10.1
	 *
	 * @var array
	 */
	private $sub_views = [];

	/**
	 * The current tab slug.
	 *
	 * @since 1.10.1
	 *
	 * @var string
	 */
	private $active_tab_slug;

	/**
	 * The current tab view.
	 *
	 * @since 1.10.1
	 *
	 * @var null|ExportViewsInterface
	 */
	private $active_view;

	/**
	 * Init view.
	 *
	 * @since 1.10.1
	 */
	public function init(): void {

		$this->init_active_view();
	}

	/**
	 * Get view label.
	 *
	 * @since 1.6.6
	 *
	 * @return string
	 */
	public function get_label(): string {

		return esc_html__( 'Export', 'wpforms-lite' );
	}

	/**
	 * Checking user capability to view.
	 *
	 * @since 1.6.6
	 *
	 * @return bool
	 */
	public function check_capability(): bool {

		return wpforms_current_user_can( [ 'edit_forms', 'view_entries' ] );
	}

	/**
	 * Display view content.
	 *
	 * @since 1.6.6
	 */
	public function display(): void {

		if ( empty( $this->active_view ) ) {
			return;
		}

		do_action( 'wpforms_admin_tools_export_top' );

		$this->display_tabs();

		$this->active_view->display();

		do_action( 'wpforms_admin_tools_export_bottom' );
	}

	/**
	 * Initialize the active sub-view (tab).
	 *
	 * @since 1.10.1
	 */
	private function init_active_view(): void {

		$view_ids = array_keys( $this->get_sub_views() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->active_tab_slug = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : (string) reset( $view_ids );

		// If the user tries to load an invalid view - fallback to the first available.
		if ( ! in_array( $this->active_tab_slug, $view_ids, true ) ) {
			$this->active_tab_slug = reset( $view_ids );
		}

		if ( ! isset( $this->sub_views[ $this->active_tab_slug ] ) ) {
			return;
		}

		$this->active_view = $this->sub_views[ $this->active_tab_slug ];

		$this->active_view->init();
	}

	/**
	 * Get available sub-views (tabs).
	 *
	 * @since 1.10.1
	 *
	 * @return array
	 */
	private function get_sub_views(): array {

		if ( ! empty( $this->sub_views ) ) {
			return $this->sub_views;
		}

		$views = [
			'entries' => new ExportEntries(),
		];

		/**
		 * Allow extending export views.
		 *
		 * @since 1.10.1
		 *
		 * @param array $views Array of views where the key is slug.
		 */
		$this->sub_views = (array) apply_filters( 'wpforms_admin_tools_views_export_get_sub_views', $views );

		// Forms and Templates are always available.
		$this->sub_views['forms']     = new FormsPage();
		$this->sub_views['templates'] = new TemplatesPage();

		if ( wpforms()->is_pro() ) {
			// Ensure entries is first in an array (default tab) for Pro users.
			$this->sub_views = array_merge( [ 'entries' => $this->sub_views['entries'] ], $this->sub_views );
		} else {
			// For Lite users, put Forms and Templates first since the Entries tab is an upgrade pitch.
			$this->sub_views = array_merge(
				[
					'forms'     => $this->sub_views['forms'],
					'templates' => $this->sub_views['templates'],
				],
				$this->sub_views
			);
		}

		$this->sub_views = array_filter(
			$this->sub_views,
			static function ( $view ) {

				return $view->current_user_can();
			}
		);

		return $this->sub_views;
	}

	/**
	 * Display tabs.
	 *
	 * @since 1.10.1
	 */
	private function display_tabs(): void {

		$views = $this->get_sub_views();

		// Remove views that should not be displayed as tabs.
		$views = array_filter(
			$views,
			static function ( $view ) {

				return ! empty( $view->get_tab_label() );
			}
		);

		// If there is only one view - no need to display tabs.
		if ( count( $views ) === 1 ) {
			return;
		}
		?>
		<div class="wpforms-tabs-wrapper">
			<nav class="nav-tab-wrapper">
				<?php foreach ( $views as $slug => $view ) : ?>
					<a href="<?php echo esc_url( $this->get_tab_url( $slug ) ); ?>" class="nav-tab <?php echo $slug === $this->active_tab_slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $view->get_tab_label() ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>
		<?php
	}

	/**
	 * Get tab URL.
	 *
	 * @since 1.10.1
	 *
	 * @param string $tab Tab slug.
	 *
	 * @return string
	 */
	private function get_tab_url( string $tab ): string {

		return add_query_arg(
			[
				'page' => Tools::SLUG,
				'view' => 'export',
				'tab'  => $tab,
			],
			admin_url( 'admin.php' )
		);
	}
}
