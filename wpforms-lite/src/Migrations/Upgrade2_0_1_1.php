<?php

namespace WPForms\Migrations;

use WPForms\Integrations\LiteConnect\LiteConnect;
use WPForms\Integrations\UsageTracking\UsageTracking;

/**
 * Class upgrade for 2.0.1.1 release.
 *
 * @since 2.0.1.1
 *
 * @noinspection PhpUnused
 */
class Upgrade2_0_1_1 extends UpgradeBase {

	/**
	 * Run upgrade.
	 *
	 * @since 2.0.1.1
	 *
	 * @return bool
	 */
	public function run(): bool {

		$this->enable_usage_tracking();

		return true;
	}

	/**
	 * Enable usage tracking on installs that already opted into Lite Connect.
	 *
	 * Writes with a direct update_option() call on purpose: migrations run on
	 * wpforms_loaded at priority -9999, long before integrations are loaded, so
	 * wpforms_update_settings() would fire the settings hook chain against
	 * services that do not exist yet.
	 *
	 * @since 2.0.1.1
	 */
	private function enable_usage_tracking(): void {

		// Pro users are not affected by this.
		if ( wpforms()->is_pro() ) {
			return;
		}

		$settings = (array) get_option( 'wpforms_settings', [] );

		// A stored false is deliberately overridden: the Settings > Misc toggle defaults to
		// off and saving that tab writes every toggle, so a stored false usually records a
		// saved-defaults artifact rather than a decision, and the two are indistinguishable.
		// The upgrade therefore applies the Lite Connect consent the same way a fresh opt-in
		// would; opting out afterwards via Settings > Misc sticks permanently.
		if ( empty( $settings[ LiteConnect::SETTINGS_SLUG ] ) || ! empty( $settings[ UsageTracking::SETTINGS_SLUG ] ) ) {
			return;
		}

		$settings[ UsageTracking::SETTINGS_SLUG ] = true;

		update_option( 'wpforms_settings', $settings );
	}
}
