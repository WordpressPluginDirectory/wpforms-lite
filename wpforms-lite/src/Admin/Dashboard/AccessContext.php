<?php

namespace WPForms\Admin\Dashboard;

/**
 * Dashboard access context value object.
 *
 * @since 2.0.2
 */
class AccessContext {

	/**
	 * Context data, merged over the Lite defaults in the constructor.
	 *
	 * @since 2.0.2
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @since 2.0.2
	 *
	 * @param array $data Context data. Missing keys fall back to Lite defaults.
	 */
	public function __construct( array $data = [] ) {

		$this->data = array_merge(
			[
				'tier'             => 'lite',
				'is_pro'           => false,
				'is_expired'       => null,
				'is_disabled'      => null,
				'is_invalid'       => null,
				'is_limit_reached' => null,
				'has_license_key'  => false,
				'can_manage'       => false,
				'user_id'          => 0,
				'dismissals'       => [],
				'widget_settings'  => [],
			],
			$data
		);
	}

	/**
	 * Get the license tier: 'lite', 'basic', 'plus', 'pro', or 'elite'.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_tier(): string {

		return (string) $this->data['tier'];
	}

	/**
	 * Whether the Pro version is active.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public function is_pro(): bool {

		return (bool) $this->data['is_pro'];
	}

	/**
	 * Whether the license is expired. Null on Lite (unknown).
	 *
	 * @since 2.0.2
	 *
	 * @return bool|null
	 */
	public function is_expired(): ?bool {

		return $this->data['is_expired'];
	}

	/**
	 * Whether the license is disabled. Null on Lite (unknown).
	 *
	 * @since 2.0.2
	 *
	 * @return bool|null
	 */
	public function is_disabled(): ?bool {

		return $this->data['is_disabled'];
	}

	/**
	 * Whether the license is invalid. Null on Lite (unknown).
	 *
	 * @since 2.0.2
	 *
	 * @return bool|null
	 */
	public function is_invalid(): ?bool {

		return $this->data['is_invalid'];
	}

	/**
	 * Whether the license has no activations left. Null on Lite (unknown).
	 *
	 * @since 2.0.2
	 *
	 * @return bool|null
	 */
	public function is_limit_reached(): ?bool {

		return $this->data['is_limit_reached'];
	}

	/**
	 * Whether a license key is present. Always false on Lite.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public function has_license_key(): bool {

		return (bool) $this->data['has_license_key'];
	}

	/**
	 * Whether the current user can manage the Dashboard (AJAX capability gate).
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public function can_manage(): bool {

		return (bool) $this->data['can_manage'];
	}

	/**
	 * Get the current user ID.
	 *
	 * @since 2.0.2
	 *
	 * @return int
	 */
	public function get_user_id(): int {

		return (int) $this->data['user_id'];
	}

	/**
	 * Get the education dismissals user meta ('edu-{section}' => timestamp).
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	public function get_dismissals(): array {

		return (array) $this->data['dismissals'];
	}

	/**
	 * Get the per-widget settings user meta, keyed by widget ID.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	public function get_widget_settings(): array {

		return (array) $this->data['widget_settings'];
	}
}
