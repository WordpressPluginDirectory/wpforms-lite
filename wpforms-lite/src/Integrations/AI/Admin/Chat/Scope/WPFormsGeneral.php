<?php

namespace WPForms\Integrations\AI\Admin\Chat\Scope;

use WPForms\Integrations\AI\Admin\Chat\ScopeBase;

/**
 * Scope: general WPForms knowledge.
 *
 * No state, no data sources — contributes only its prompt fragment via the
 * middleware module.
 *
 * @since 2.0.0
 */
class WPFormsGeneral extends ScopeBase {

	/**
	 * Scope slug — used in registration and frontend default-scope arrays.
	 *
	 * @since 2.0.0
	 */
	public const SLUG = 'wpforms_general';

	/**
	 * Capability required to use this scope.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_capability(): string {

		return 'view_forms';
	}

	/**
	 * License tier required.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_requires(): string {

		return 'lite';
	}

	/**
	 * Default in-flight label for the WPForms general scope.
	 *
	 * @since 2.0.0
	 *
	 * @param array $includes Include tokens being resolved.
	 * @param array $args     Optional args from the request_data frame.
	 *
	 * @return string
	 */
	public function get_tool_call_default_label( array $includes, array $args ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		return __( 'Working on it…', 'wpforms-lite' );
	}
}
