<?php

namespace WPForms\SetupWizard\Service;

/**
 * Suppresses the onboarding redirect a cross-plugin arms when WPForms activates it.
 *
 * Several plugins WPForms can install on the user's behalf arm a "just activated,
 * go to my onboarding" flag and consume it on `admin_init` at a priority ahead of
 * the Setup Wizard's own launch hook (`PHP_INT_MAX`), throwing the user out of our
 * onboarding and into theirs. This listener writes each plugin's opt-out flag the
 * moment WPForms activates it.
 *
 * Hooked on `wpforms_plugin_activated`, and registered by `SetupWizard::hooks()`
 * only when `RestApi::is_install_request()` says the current request is the
 * wizard's own `install-plugins` REST call. Outside that request the listener is
 * never registered, so installs from the Setup Checklist promos or the Education
 * one-click links leave each plugin's own onboarding intact — those users never
 * entered our wizard, and suppression is permanent. On this path the action fires
 * from `WPForms\Helpers\Plugin::activate()` immediately after `activate_plugin()`
 * succeeds, so a flag is never written for a plugin whose install failed.
 *
 * Suppression is permanent and never restored: WPForms' own onboarding is the one
 * the user chose, and each plugin's wizard stays reachable from its own admin menu.
 *
 * @since 2.0.0.4
 */
class CrossPluginOnboarding {

	/**
	 * Suppression registry, keyed by the activated plugin's directory slug.
	 *
	 * Keyed by slug rather than by full plugin file because the main-file
	 * basename reported to `wpforms_plugin_activated` can differ from the
	 * catalog's canonical file, and because each Pro variant lives in its own
	 * directory. `Education\WPConsent\InstallTracker` matches the same way.
	 *
	 * Each vendor reference below was verified by scanning every PHP file in the
	 * plugin's then-current wp.org release on 2026-08-11. A vendor renaming a flag
	 * degrades silently to an unsuppressed redirect, so re-verify these citations
	 * when touching this list.
	 *
	 * @since 2.0.0.4
	 *
	 * @var array<string, array{op: string, flag?: string}>
	 */
	private const SUPPRESSIONS = [
		// Documented opt-out option, checked at admin_init:9999 in
		// src/Admin/SetupWizard.php:132. Armed as a 30s transient in src/Core.php:678.
		'wp-mail-smtp'                           => [
			'op'   => 'enable_option',
			'flag' => 'wp_mail_smtp_activation_prevent_redirect',
		],
		// Pro ships the same Setup Wizard as Lite, so the option name is shared.
		'wp-mail-smtp-pro'                       => [
			'op'   => 'enable_option',
			'flag' => 'wp_mail_smtp_activation_prevent_redirect',
		],
		// Truthy value means "suppress" despite the name reading the other way:
		// app/Common/Standalone/SetupWizard.php:62 returns early when it is set.
		'all-in-one-seo-pack'                    => [
			'op'   => 'enable_option',
			'flag' => 'aioseo_activation_redirect',
		],
		// Dedicated disable option, checked at OMAPI/Welcome.php:112.
		'optinmonster'                           => [
			'op'   => 'enable_option',
			'flag' => 'optin_monster_api_activation_redirect_disabled',
		],
		// One-shot 30s transient armed in the activation hook at
		// googleanalytics.php:615, consumed at lite/includes/admin/welcome.php:37.
		'google-analytics-for-wordpress'         => [
			'op'   => 'delete_transient',
			'flag' => '_monsterinsights_activation_redirect',
		],
		// One-shot 60s transient armed in the activation hook at
		// app/Onboarding.php:116, consumed at app/Onboarding.php:45 on priority 10.
		'universally-language-translation-multilingual-tool' => [
			'op'   => 'delete_transient',
			'flag' => 'universally_onboarding_redirect',
		],
		// Durable option armed in the activation hook via
		// classes/class.plugin.upgrade.php:111, gated at
		// src/Controllers/WelcomeController.php:96. Its documented
		// `duplicator_disable_onboarding_redirect` filter is not usable here: it is
		// evaluated on a later request, so it would need an always-on registration
		// that also suppressed installs WPForms had nothing to do with.
		'duplicator'                             => [
			'op'   => 'delete_option',
			'flag' => 'duplicator_redirect_to_welcome',
		],
		// The only plugin here with no reachable install-time flag. It arms
		// `wpconsent_onboarding_redirect` from its own admin_init handler
		// (includes/class-wpconsent-install.php:54), which bails on non-admin
		// requests — and the Setup Wizard installs over REST, where admin_init never
		// fires. So the transient does not exist yet and deleting it would find
		// nothing; it gets armed on the next admin page the user opens and consumed
		// in that same request at priority 9999 (includes/admin/onboarding.php:12).
		// Seeding the option its arming branch is gated on is what prevents it.
		'wpconsent-cookies-banner-privacy-suite' => [
			'op' => 'seed_wpconsent',
		],
		// WPConsent Premium has no entry: its activation timestamp is stored under
		// `wpconsent_pro`, not `wpconsent`, so seeding the Lite key would neither
		// suppress it nor be truthful — and the wizard cannot install Premium anyway.
	];

	/**
	 * Register hooks.
	 *
	 * Called by `SetupWizard::hooks()` only for the wizard's own install request,
	 * so simply being loaded does not arm the listener.
	 *
	 * @since 2.0.0.4
	 */
	public function hooks(): void {

		add_action( 'wpforms_plugin_activated', [ $this, 'suppress' ] );
	}

	/**
	 * Suppress the activated plugin's onboarding redirect, when it has one.
	 *
	 * The parameter is deliberately untyped: the action also fires for every
	 * WPForms addon activation and from third-party code, so the value is
	 * validated rather than type-hinted.
	 *
	 * @since 2.0.0.4
	 *
	 * @param string $plugin_basename Path to the activated plugin file relative to the plugins directory.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function suppress( $plugin_basename ): void {

		if ( ! is_string( $plugin_basename ) || $plugin_basename === '' ) {
			return;
		}

		// Split rather than dirname()/basename(): those are locale-aware and have
		// produced results that differ between local runs and CI.
		$slug  = explode( '/', $plugin_basename )[0];
		$entry = self::SUPPRESSIONS[ $slug ] ?? null;

		if ( $entry === null ) {
			return;
		}

		$this->apply( $entry );
	}

	/**
	 * Apply a single registry entry.
	 *
	 * Every operation is best-effort: a failed write cannot break the install that
	 * triggered it, and every entry is idempotent under re-activation.
	 *
	 * @since 2.0.0.4
	 *
	 * @param array $entry Registry entry for the activated plugin.
	 */
	private function apply( array $entry ): void {

		switch ( $entry['op'] ) {
			case 'enable_option':
				update_option( $entry['flag'], true );
				break;

			case 'delete_option':
				delete_option( $entry['flag'] );
				break;

			case 'delete_transient':
				delete_transient( $entry['flag'] );
				break;

			case 'seed_wpconsent':
				$this->seed_wpconsent();
				break;

			// Unreachable while `SUPPRESSIONS` is the only source of `op`, and every
			// value it uses has a case above. A registry entry naming an operation
			// that does not exist here fails that entry's own unit test rather than
			// silently suppressing nothing.
			default:
				break;
		}
	}

	/**
	 * Seed WPConsent's first-activation option so its onboarding redirect is
	 * never armed.
	 *
	 * Writes exactly what `WPConsent_Install::maybe_run_install()` would have
	 * written, which keeps its fresh-install path intact: table creation still runs
	 * off the separate `wpconsent_install` option via its activation hook, the `0`
	 * version still triggers its `update_1_0_5()` cache clear, and
	 * includes/class-wpconsent-install.php:79 stamps the real version afterwards.
	 *
	 * @since 2.0.0.4
	 */
	private function seed_wpconsent(): void {

		// Clears a transient armed by an earlier admin request that ended before the
		// redirect at priority 9999 could fire. The seeding below cannot cover that
		// case, because it no-ops once `wpconsent_activated` is set — and that same
		// earlier request is what wrote it. Deleting an absent transient is a no-op,
		// so this is free on the ordinary fresh-install path.
		delete_transient( 'wpconsent_onboarding_redirect' );

		$activated = (array) get_option( 'wpconsent_activated', [] );

		// A pre-existing install has already spent its onboarding redirect, and its
		// first-activation timestamp must not move.
		if ( ! empty( $activated['wpconsent'] ) ) {
			return;
		}

		$activated['wpconsent'] = time();
		$activated['version']   = '0';

		update_option( 'wpconsent_activated', $activated );
	}
}
