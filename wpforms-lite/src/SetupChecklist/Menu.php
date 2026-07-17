<?php

namespace WPForms\SetupChecklist;

/**
 * Setup Checklist admin menu item.
 *
 * Adds the "Setup Checklist" entry to the top of the WPForms submenu (above "All
 * Forms"), rendering the section-progress bar inside the menu label. The parent
 * menu still lands on All Forms — this only adds a sibling link, visible to
 * admins. Once the checklist is dismissed the link is removed and a direct hit on
 * its URL redirects to the Forms overview — dismissal is permanent per site.
 *
 * The bar's CSS is printed inline on `admin_head`: the menu renders on every
 * admin screen, but the main WPForms admin stylesheet only loads on WPForms
 * pages, so a reused stylesheet would leave the bar unstyled everywhere else.
 *
 * @since 2.0.0
 */
class Menu {

	/**
	 * CSS class added to the menu link so the inline styles target only this item.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const ITEM_CLASS = 'wpforms-setup-checklist-menu-item';

	/**
	 * WPForms parent menu slug the item is attached to.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const PARENT_SLUG = 'wpforms-overview';

	/**
	 * Checklist model (source of the progress percentage).
	 *
	 * @since 2.0.0
	 *
	 * @var Checklist
	 */
	private $checklist;

	/**
	 * Per-site state store (dismissal).
	 *
	 * @since 2.0.0
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Checklist $checklist Checklist model.
	 * @param State     $state     Per-site state store.
	 */
	public function __construct( Checklist $checklist, State $state ) {

		$this->checklist = $checklist;
		$this->state     = $state;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function hooks(): void {

		add_action( 'wpforms_admin_menu', [ $this, 'register' ] );
		add_action( 'admin_menu', [ $this, 'reorder' ], PHP_INT_MAX );
		add_action( 'admin_page_access_denied', [ $this, 'maybe_redirect_dismissed' ] );
		add_action( 'admin_head', [ $this, 'print_styles' ] );
	}

	/**
	 * Send a dismissed checklist's page back to the Forms overview.
	 *
	 * @since 2.0.0
	 */
	public function maybe_redirect_dismissed(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( $page !== Page::SLUG || ! $this->state->is_dismissed() ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PARENT_SLUG ) );

		exit;
	}

	/**
	 * Register the submenu item and the page route under the WPForms menu.
	 *
	 * @since 2.0.0
	 *
	 * @param object $menu WPForms admin menu instance passed by the action.
	 */
	public function register( $menu ): void {

		$capability = wpforms_get_capability_manage_options();

		if ( ! current_user_can( $capability ) ) {
			return;
		}

		$title = $this->state->is_dismissed()
			? esc_html__( 'Setup Checklist', 'wpforms-lite' )
			: $this->get_menu_title();

		add_submenu_page(
			self::PARENT_SLUG,
			esc_html__( 'WPForms Setup Checklist', 'wpforms-lite' ),
			$title,
			$capability,
			Page::SLUG,
			[ $menu, 'admin_page' ]
		);
	}

	/**
	 * Position the item, or hide its link when the checklist is dismissed.
	 *
	 * @since 2.0.0
	 */
	public function reorder(): void {

		if ( ! current_user_can( wpforms_get_capability_manage_options() ) ) {
			return;
		}

		if ( $this->state->is_dismissed() ) {
			remove_submenu_page( self::PARENT_SLUG, Page::SLUG );

			return;
		}

		$this->move_after_overview();
	}

	/**
	 * Output the inline menu styles on every admin screen the menu shows on.
	 *
	 * @since 2.0.0
	 */
	public function print_styles(): void {

		if ( $this->state->is_dismissed() || ! current_user_can( wpforms_get_capability_manage_options() ) ) {
			return;
		}

		$item = '#adminmenu .wp-submenu a.' . self::ITEM_CLASS;

		$styles =
			$item . '{box-sizing:border-box;display:flex;flex-direction:column;justify-content:center;align-items:flex-start;gap:8px;padding:12px 12px 13px;border-bottom:1px solid #3C434A;}'
			. $item . ' .wpforms-setup-checklist-menu-label{width:100%;font-weight:500;font-size:13px;line-height:16px;}'
			. $item . ' .wpforms-setup-checklist-menu-progress{position:relative;width:100%;height:4px;background:#1D2327;border-radius:2px;overflow:hidden;}'
			. $item . ' .wpforms-setup-checklist-menu-progress-bar{display:block;height:4px;min-width:4px;background:#00BA37;border-radius:2px;}';

		// "All Forms" stays first in the markup so the top-level link targets it;
		// flex `order` lifts the checklist above it visually. The submenu head (shown
		// only when the menu is collapsed) is kept on top.
		$submenu_sel = '#adminmenu #toplevel_page_' . self::PARENT_SLUG . ' .wp-submenu';

		$styles .=
			$submenu_sel . '{display:flex;flex-direction:column;}'
			. $submenu_sel . ' .wp-submenu-head{order:-2;}'
			. $submenu_sel . ' li:has(> a.' . self::ITEM_CLASS . '){order:-1;}';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		printf( '<style id="wpforms-setup-checklist-menu-styles">%s</style>', $styles );
	}

	/**
	 * Build the menu label markup: the title plus the progress bar.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_menu_title(): string {

		$percent = (int) $this->checklist->get_progress()['percent'];
		$percent = max( 0, min( 100, $percent ) );

		return sprintf(
			'<span class="wpforms-setup-checklist-menu-label">%1$s</span>'
			. '<span class="wpforms-setup-checklist-menu-progress">'
			. '<span class="wpforms-setup-checklist-menu-progress-bar" style="width:%2$d%%;"></span>'
			. '<span class="screen-reader-text">%3$s</span>'
			. '</span>',
			esc_html__( 'Setup Checklist', 'wpforms-lite' ),
			$percent,
			esc_html(
				sprintf(
					/* translators: %d: setup checklist completion percentage. */
					__( '%d%% complete', 'wpforms-lite' ),
					$percent
				)
			)
		);
	}

	/**
	 * Place the checklist item right after "All Forms" in the WPForms submenu.
	 *
	 * @since 2.0.0
	 */
	private function move_after_overview(): void {

		global $submenu;

		if ( empty( $submenu[ self::PARENT_SLUG ] ) ) {
			return;
		}

		$items = $submenu[ self::PARENT_SLUG ];
		$ours  = null;

		foreach ( $items as $key => $item ) {
			if ( isset( $item[2] ) && $item[2] === Page::SLUG ) {
				$ours = $item;

				unset( $items[ $key ] );

				break;
			}
		}

		if ( $ours === null ) {
			return;
		}

		// The 5th element becomes a class on the menu link (see adjust_pro_menu_item).
		$ours[4] = empty( $ours[4] ) ? self::ITEM_CLASS : $ours[4] . ' ' . self::ITEM_CLASS;

		$items    = array_values( $items );
		$position = 1;

		// Insert just after "All Forms" (the parent's own page) so it stays first.
		foreach ( $items as $key => $item ) {
			if ( isset( $item[2] ) && $item[2] === self::PARENT_SLUG ) {
				$position = $key + 1;

				break;
			}
		}

		array_splice( $items, $position, 0, [ $ours ] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu[ self::PARENT_SLUG ] = $items;
	}
}
