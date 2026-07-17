<?php

namespace WPForms\Integrations\AI\Admin\Chat;

use WPForms\Integrations\AI\Admin\Ajax\Chat\Chat as ChatAjax;
use WPForms\Integrations\AI\Helpers;

/**
 * WPForms AI Chat — admin page handler / loader.
 *
 * Triggers the two registries' singleton init (they own their defaults and
 * dispatch registration filters for extension), then — when the current admin
 * screen matches an enabled surface — enqueues the chat element + chat.js,
 * renders the FAB / modal templates, and emits the two localized JS objects.
 *
 * Loaded by `\WPForms\Integrations\AI\AI::load()`.
 *
 * @since 2.0.0
 */
class Chat {

	/**
	 * Localized JS object name. Must match the chat.js consumer expectation.
	 *
	 * @since 2.0.0
	 */
	private const LOCALIZE_OBJECT = 'wpforms_ai_chat';

	/**
	 * Chat element handle — shared with the form editor.
	 *
	 * @since 2.0.0
	 */
	private const CHAT_ELEMENT_HANDLE = 'wpforms-ai-chat-element';

	/**
	 * Admin chat asset handle (JS + CSS share the handle).
	 *
	 * @since 2.0.0
	 */
	private const CHAT_HANDLE = 'wpforms-ai-chat';

	/**
	 * The active surface config (with `slug` injected by SurfaceRegistry), or null
	 * when the current admin screen has no matching enabled surface.
	 *
	 * @since 2.0.0
	 *
	 * @var array|null
	 */
	private $surface;

	/**
	 * Initialize.
	 *
	 * Boots the chat registries and scope/surface classes via `wpforms()->register_bulk()`,
	 * then captures the active surface (if any) and attaches page-render hooks.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		$this->loader();

		$surface_registry = wpforms()->obj( 'ai_chat_surface_registry' );

		$this->surface = $surface_registry instanceof SurfaceRegistry
			? $surface_registry->get_active_surface()
			: null;

		if ( $this->surface !== null ) {
			$this->hooks();
		}
	}

	/**
	 * Register the AI Chat registries, scopes, and surfaces through `wpforms()->register_bulk()`.
	 *
	 * The Loader's Pro/Lite namespace switch resolves each class to its Pro variant when present,
	 * falling back to the Lite class. Pure-Pro classes (Analytics, AnalyticsPage) silently no-op
	 * on Lite because no class is found at either resolved path.
	 *
	 * @since 2.0.0
	 */
	private function loader(): void {

		$classes = [
			// Registries first — `hook => false` runs the callback immediately
			// so the registries are available before scopes/surfaces self-register.
			[
				'name' => 'Integrations\AI\Admin\Chat\ScopeRegistry',
				'id'   => 'ai_chat_scope_registry',
				'hook' => false,
			],
			[
				'name' => 'Integrations\AI\Admin\Chat\SurfaceRegistry',
				'id'   => 'ai_chat_surface_registry',
				'hook' => false,
			],

			// Scopes — each init() self-registers into the scope registry.
			[
				'name' => 'Integrations\AI\Admin\Chat\Scope\WPFormsGeneral',
				'hook' => false,
			],
			[
				'name' => 'Integrations\AI\Admin\Chat\Scope\FormsInventory\FormsInventory',
				'hook' => false,
			],
			[
				'name' => 'Integrations\AI\Admin\Chat\Scope\Analytics\Analytics',
				'hook' => false,
			],

			// Surfaces.
			[
				'name' => 'Integrations\AI\Admin\Chat\Surface\AnalyticsPage',
				'hook' => false,
			],
		];

		wpforms()->register_bulk( $classes );
	}

	/**
	 * Register page-render hooks for the active surface.
	 *
	 * @since 2.0.0
	 */
	private function hooks(): void {

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_footer', [ $this, 'render_templates' ] );
		add_filter( 'admin_body_class', [ $this, 'filter_admin_body_class' ] );
		add_filter( 'wpforms_integrations_ai_admin_chat_chat_get_localize_chat_data', [ $this, 'get_chat_mode_strings' ] );
	}

	/**
	 * Append the surface's body class.
	 *
	 * @since 2.0.0
	 *
	 * @param string|mixed $classes Existing body classes.
	 *
	 * @return string
	 */
	public function filter_admin_body_class( $classes ): string {

		$classes    = (string) $classes;
		$body_class = (string) ( $this->surface['body_class'] ?? '' );

		if ( $body_class === '' ) {
			return $classes;
		}

		if ( ! $this->user_can_view() ) {
			return $classes;
		}

		return trim( $classes . ' ' . $body_class );
	}

	/**
	 * Enqueue the chat element + chat.js + per-surface CSS, and emit both localized objects.
	 *
	 *   - `wpforms_ai_chat_element` — chat-element strings + modules + nonce + actions.
	 *   - `wpforms_ai_chat`         — admin chat surface config consumed by chat.js.
	 *
	 * `chat-helpers-admin.js` is loaded as a dynamic ES module via the chat
	 * element's `modules` array, not as a classic script.
	 *
	 * @since 2.0.0
	 */
	public function enqueue_assets(): void {

		if ( ! $this->user_can_view() ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		// DOMPurify — required by chat-helpers-admin's sanitizeHtmlAnswer().
		wp_enqueue_script(
			'dom-purify',
			WPFORMS_PLUGIN_URL . 'assets/lib/purify.min.js',
			[],
			'3.4.1',
			false
		);

		// Chat element (CSS + JS). `wp-i18n` is a dep so dynamically-imported modules can call wp.i18n.__().
		wp_enqueue_style(
			self::CHAT_ELEMENT_HANDLE,
			WPFORMS_PLUGIN_URL . "assets/css/integrations/ai/chat-element$min.css",
			[],
			WPFORMS_VERSION
		);

		wp_enqueue_script(
			self::CHAT_ELEMENT_HANDLE,
			WPFORMS_PLUGIN_URL . "assets/js/integrations/ai/chat-element/wpforms-ai-chat-element$min.js",
			[ 'dom-purify', 'wp-i18n' ],
			WPFORMS_VERSION,
			false
		);

		// Admin chat CSS.
		wp_enqueue_style(
			self::CHAT_HANDLE,
			WPFORMS_PLUGIN_URL . "assets/css/integrations/ai/wpforms-ai-chat$min.css",
			[],
			WPFORMS_VERSION
		);

		// Shared modal drag/resize utility (WPForms.Admin.AIChatModal) — reused by chat.js and the form editor.
		wp_enqueue_script(
			'wpforms-admin-chat-modal',
			WPFORMS_PLUGIN_URL . "assets/js/admin/share/chat-modal$min.js",
			[],
			WPFORMS_VERSION,
			true
		);

		// Admin chat JS — chat.js IIFE that wires FAB, modal, drag, resize, chat-element.
		// Note: chat-helpers-admin is not listed as a dep — it is dynamically imported by the
		// chat element via the wpforms_ai_chat_element.modules array.
		wp_enqueue_script(
			self::CHAT_HANDLE,
			WPFORMS_PLUGIN_URL . "assets/js/integrations/ai/chat/chat$min.js",
			[
				'jquery',
				'jquery-ui-draggable',
				'jquery-ui-resizable',
				'wp-util',
				'wp-hooks',
				'wpforms-admin-chat-modal',
				self::CHAT_ELEMENT_HANDLE,
			],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			self::CHAT_ELEMENT_HANDLE,
			'wpforms_ai_chat_element',
			$this->get_localize_chat_data()
		);

		// Localize the admin chat surface config — chat.js and chat-helpers-admin read this.
		wp_localize_script(
			self::CHAT_HANDLE,
			self::LOCALIZE_OBJECT,
			$this->get_localize_data()
		);
	}

	/**
	 * Render the FAB + modal templates.
	 *
	 * @since 2.0.0
	 */
	public function render_templates(): void {

		if ( ! $this->user_can_view() ) {
			return;
		}

		// Templates are passive `<script type="text/html">` blocks; chat.js wp.template()s them.
		require WPFORMS_PLUGIN_DIR . 'templates/integrations/ai/chat.php';
	}

	/**
	 * Get the localize data for the chat element on admin chat surfaces.
	 *
	 * Independent of the form-editor's `\WPForms\Integrations\AI\Admin\Builder\Enqueues::get_localize_chat_data`
	 * to avoid coupling admin-area surfaces to the builder. Some strings are intentionally
	 * duplicated to keep the two surfaces decoupled.
	 *
	 * Modules array contains only:
	 *   - `api` — required infrastructure (chat element extracts it as `WPFormsAi.api`).
	 *   - `admin` — the admin chat mode helper.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	private function get_localize_chat_data(): array {

		$min = wpforms_get_min_suffix();

		// The chat element's api.js is used only for the rate endpoint in admin mode
		// (prompts route through dispatch.js with the wpforms_ai_chat nonce). The rate
		// handler ( Ajax\Base::rate_response() ) validates the shared 'wpforms-ai-nonce'
		// action, so the chat element must carry that nonce — matching the builder surfaces.
		$strings = Helpers::get_chat_element_strings(
			'wpforms-ai-nonce',
			[ 'admin' => ChatAjax::AJAX_ACTION ]
		);

		$strings['modules'] = [
			[
				'name' => 'api',
				'path' => "./modules/api$min.js",
			],
			[
				'name' => 'admin',
				'path' => "../chat/modules/chat-helpers-admin$min.js",
			],
			[
				'name' => 'adminBlocks',
				'path' => "../chat/modules/blocks$min.js",
			],
			[
				'name' => 'adminDispatch',
				'path' => "../chat/modules/dispatch$min.js",
			],
			[
				'name' => 'adminPageState',
				'path' => "../chat/modules/page-state$min.js",
			],
			[
				'name' => 'adminRenderer',
				'path' => "../chat/modules/renderer$min.js",
			],
			[
				'name' => 'adminUi',
				'path' => "../chat/modules/ui$min.js",
			],
		];

		// Always-present fallback surface module. The chat element uses it
		// when the active surface does not register its own JS module
		// (third-party admin page, Pro-only surface inactive, etc.).
		$strings['modules'][] = [
			'name' => 'surfaceAdmin',
			'path' => "../chat/modules/surface-admin$min.js",
		];

		// Active surface's JS module (if it registers one). Lazy loaded:
		// only the active surface's module ships per page, keeping the
		// admin page payload minimal.
		$active_surface = $this->resolve_active_surface();

		if ( $active_surface !== null ) {
			$js_module = $active_surface->get_js_module();

			if ( is_string( $js_module ) && $js_module !== '' ) {
				$strings['modules'][]     = [
					'name' => $active_surface->get_js_helper_name(),
					'path' => $js_module,
				];
				$strings['activeSurface'] = $active_surface->get_js_helper_name();
			}
		}

		// Cache-bust the dynamically imported ES modules. The chat element loads
		// them via `import( path )` with bare relative paths, so without a version
		// query the browser caches each module by URL indefinitely and never picks
		// up plugin updates. Append the plugin version so each release invalidates it.
		foreach ( $strings['modules'] as $index => $module ) {
			$separator = strpos( $module['path'], '?' ) === false ? '?' : '&';

			$strings['modules'][ $index ]['path'] = $module['path'] . $separator . 'ver=' . WPFORMS_VERSION;
		}

		/**
		 * Filters the admin AI chat element localize data.
		 *
		 * Mode-specific strings (e.g. the `admin` mode block) are added through this
		 * filter so the registration pattern mirrors the form-editor surface.
		 *
		 * @since 2.0.0
		 *
		 * @param array $strings Localize data.
		 */
		return (array) apply_filters( 'wpforms_integrations_ai_admin_chat_chat_get_localize_chat_data', $strings );
	}

	/**
	 * Add chat mode strings for the `admin` mode.
	 *
	 * Hooked into the admin chat-data filter so the admin chat surface follows
	 * the same registration pattern as the form-editor mode (see
	 * `\WPForms\Integrations\AI\Admin\Builder\FormEditor::add_chat_mode_strings`).
	 *
	 * The chat element reads `wpforms_ai_chat_element.admin.*` via `this.modeStrings.*`;
	 * without these the welcome screen renders "undefined" labels and the input
	 * crashes on the placeholder string. Sample-prompt defaults are JS-side and
	 * extended via `wp.hooks` from `chat-helpers-admin.js`.
	 *
	 * @since 2.0.0
	 *
	 * @param array|mixed $strings Localize strings.
	 *
	 * @return array
	 */
	public function get_chat_mode_strings( $strings ): array {

		$strings = (array) $strings;

		$strings['admin'] = [
			'placeholder'        => esc_html__( 'Ask WPForms AI…', 'wpforms-lite' ),
			'title'              => esc_html__( 'WPForms AI', 'wpforms-lite' ),
			'description'        => esc_html__( 'Ask a question about your form\'s performance. Get insights on trends, drop-off points, and ways to improve conversions.', 'wpforms-lite' ),
			'samplePromptsTitle' => esc_html__( 'Example Questions:', 'wpforms-lite' ),
			'responseButtons'    => [
				'like'    => esc_html__( 'Helpful', 'wpforms-lite' ),
				'dislike' => esc_html__( 'Not helpful', 'wpforms-lite' ),
				'retry'   => esc_html__( 'Regenerate', 'wpforms-lite' ),
				'clear'   => esc_html__( 'Clear chat', 'wpforms-lite' ),
			],
		];

		return $strings;
	}

	/**
	 * Assemble the wpforms_ai_chat localize payload.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	private function get_localize_data(): array {

		$scopes = (array) ( $this->surface['default_scopes'] ?? [] );

		return [
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'action'      => ChatAjax::AJAX_ACTION,
			'nonce'       => wp_create_nonce( ChatAjax::AJAX_ACTION ),
			'surface'     => (string) ( $this->surface['slug'] ?? '' ),
			'bodyClass'   => (string) ( $this->surface['body_class'] ?? '' ),
			'colorScheme' => (string) ( $this->surface['color_scheme'] ?? 'light' ),
			'mode'        => 'admin',
			'scopes'      => $this->filter_scopes_for_current_user( $scopes ),
			'strings'     => [
				'errorGeneric' => esc_html__( 'Something went wrong. Please try again.', 'wpforms-lite' ),
				'errorNonce'   => esc_html__( 'Session expired. Refresh the page and try again.', 'wpforms-lite' ),
			],
		];
	}

	/**
	 * Reduce a scope-slug list to those the current user has capability for.
	 *
	 * @since 2.0.0
	 *
	 * @param array $scopes Scope slugs.
	 *
	 * @return array Re-indexed list of accessible scope slugs.
	 */
	private function filter_scopes_for_current_user( array $scopes ): array {

		$registry = wpforms()->obj( 'ai_chat_scope_registry' );

		if ( ! $registry instanceof ScopeRegistry ) {
			return [];
		}

		$allowed = [];

		foreach ( $scopes as $scope_slug ) {
			$scope = $registry->get( (string) $scope_slug );

			if ( ! $scope instanceof ScopeBase ) {
				continue;
			}

			if ( ! wpforms_current_user_can( $scope->get_capability() ) ) {
				continue;
			}

			$allowed[] = $scope_slug;
		}

		return $allowed;
	}

	/**
	 * Resolve the currently active surface instance.
	 *
	 * Reuses the slug already cached in `$this->surface` (set by `init()` from
	 * `SurfaceRegistry::get_active_surface()`) and fetches the corresponding
	 * instance from the registry. Returns null when the surface is inactive
	 * or the registry is unavailable — in which case the chat element falls
	 * back to the `surface-admin` no-op module.
	 *
	 * @since 2.0.0
	 *
	 * @return SurfaceBase|null
	 */
	private function resolve_active_surface(): ?SurfaceBase {

		$slug = (string) ( $this->surface['slug'] ?? '' );

		if ( $slug === '' ) {
			return null;
		}

		$registry = wpforms()->obj( 'ai_chat_surface_registry' );

		if ( ! $registry instanceof SurfaceRegistry ) {
			return null;
		}

		return $registry->get( $slug );
	}

	/**
	 * Whether the current user can view the chat on this surface.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function user_can_view(): bool {

		$capability = (string) ( $this->surface['capability'] ?? 'manage_options' );

		return wpforms_current_user_can( $capability );
	}
}
