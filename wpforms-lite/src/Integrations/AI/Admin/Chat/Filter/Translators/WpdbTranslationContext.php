<?php

namespace WPForms\Integrations\AI\Admin\Chat\Filter\Translators;

/**
 * Accumulator object passed through WpdbTranslators while compiling filters.
 *
 * Holds WHERE and HAVING fragments + their bind params in **separate**
 * buffers. The final SQL emits WHERE before HAVING; a single flat bind list
 * would scramble placeholder order if a HAVING-bound filter were processed
 * before a WHERE-bound one. Callers assemble the final bind list as:
 *
 *     [ ...prefix_params, ...$ctx->where_params, ...$ctx->having_params, ...suffix_params ]
 *
 * to match the placeholder order in the SQL template.
 *
 * Public properties intentionally — keep parity with PostsTranslationContext
 * and let `WpdbTranslators` methods append directly without an accessor layer.
 *
 * @since 2.0.0
 */
class WpdbTranslationContext {

	/**
	 * WHERE clause fragments (joined with AND by the caller).
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	public $where_parts = [];

	/**
	 * Bind params for $where_parts, in placeholder order.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public $where_params = [];

	/**
	 * HAVING clause fragments (joined with AND by the caller).
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	public $having_parts = [];

	/**
	 * Bind params for $having_parts, in placeholder order.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public $having_params = [];
}
