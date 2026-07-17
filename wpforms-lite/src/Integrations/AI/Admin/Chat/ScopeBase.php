<?php

namespace WPForms\Integrations\AI\Admin\Chat;

/**
 * Abstract base for AI Chat scopes.
 *
 * Concrete scopes extend this class, override the abstract identity helpers
 * and `build_state()`, and define `public const SLUG = '...'`. The surface
 * aggregator calls each active scope's `get_state()` which wraps `build_state()`
 * in the per-scope filter.
 *
 * Instantiated through `wpforms()->register([ 'name' => '…' ])` from
 * `Chat\Chat::loader()`. The `init()` method self-registers the instance
 * into the scope registry; subclasses can extend `hooks()` to attach
 * additional filters/actions.
 *
 * @since 2.0.0
 */
abstract class ScopeBase {

	/**
	 * Scope slug. Concrete classes define `public const SLUG = '...'`.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_slug(): string {

		return (string) static::SLUG;
	}

	/**
	 * Capability shortcut passed to `wpforms_current_user_can()`.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	abstract public function get_capability(): string;

	/**
	 * License tier — `'lite'` or `'pro'`.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	abstract public function get_requires(): string;

	/**
	 * Resolver callables keyed by include token.
	 *
	 * Legacy resolver callables. The chat protocol now uses OpenAI native tool calling
	 * with tool definitions owned by the middleware. This method stays for one release
	 * so addons overriding it don't fatal.
	 *
	 * @since 2.0.0
	 * @deprecated 2.0.0
	 *
	 * @return array { token => callable } map.
	 */
	public function get_data_sources(): array {

		return [];
	}

	/**
	 * Initialize: self-register into the scope registry, then run hooks().
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		$registry = wpforms()->obj( 'ai_chat_scope_registry' );

		if ( $registry instanceof ScopeRegistry ) {
			$registry->register( $this );
		}

		$this->hooks();
	}

	/**
	 * Attach hooks. Override in subclasses to add filters/actions.
	 *
	 * @since 2.0.0
	 */
	protected function hooks(): void {} // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

	/**
	 * Build the scope's raw state contribution. Override in concrete scopes.
	 *
	 * @since 2.0.0
	 *
	 * @param array $surface     Active surface config.
	 * @param array $request     Sanitized request payload.
	 * @param array $accumulated Running state dict so far (`{ page_state, surface_state }`).
	 *
	 * @return array
	 */
	public function build_state( array $surface, array $request, array $accumulated ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		return [];
	}

	/**
	 * Default in-flight label shown to the user when this scope is resolving data.
	 *
	 * Used as a fallback when the middleware response does not carry an LLM-composed
	 * `request_data.label`. Subclasses override to provide scope-flavored copy.
	 *
	 * @since 2.0.0
	 *
	 * @param array $includes Include tokens being resolved (e.g. `[ 'forms_list' ]`).
	 * @param array $args     Optional args from the request_data frame.
	 *
	 * @return string
	 */
	public function get_tool_call_default_label( array $includes, array $args ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		return __( 'Working on it…', 'wpforms-lite' );
	}

	/**
	 * Return the scope's state contribution, after the per-scope filter chain.
	 *
	 * Called by the surface aggregator; the returned array is namespaced under
	 * the scope's slug inside `surface_state`.
	 *
	 * @since 2.0.0
	 *
	 * @param array $surface     Active surface config.
	 * @param array $request     Sanitized request payload.
	 * @param array $accumulated Running state dict so far.
	 *
	 * @return array
	 */
	final public function get_state( array $surface, array $request, array $accumulated ): array {

		$state = $this->build_state( $surface, $request, $accumulated );

		/**
		 * Filters the scope's state contribution.
		 *
		 * Subclasses should override `build_state()` instead of hooking here.
		 * This filter is intended for third-party addons that cannot subclass.
		 *
		 * @since 2.0.0
		 *
		 * @param array $state       Scope's state contribution.
		 * @param array $surface     Active surface config.
		 * @param array $request     Sanitized request payload.
		 * @param array $accumulated Running state dict so far.
		 */
		return (array) apply_filters(
			"wpforms_integrations_ai_admin_chat_scope_base_get_state_{$this->get_slug()}",
			$state,
			$surface,
			$request,
			$accumulated
		);
	}
}
