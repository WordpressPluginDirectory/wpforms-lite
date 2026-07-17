<?php

namespace WPForms\SetupWizard\Service;

/**
 * Setup Wizard plugin catalog.
 *
 * Single source of truth for the plugins the wizard and Setup Checklist know about:
 * the cross-product plugins offered on the cross-plugins step, the third-party
 * form plugins it can import from, the recommended sister plugins promoted in the
 * Setup Checklist growth-tools promo, and every WPForms addon resolved from the
 * addons data handler rather than a hard-coded list. Every other collaborator
 * reads its plugin
 * metadata from here instead of repeating it — the detector needs the on-disk
 * files, the installer needs the main file, and the failed-installs notice needs
 * the display name and the wp.org link.
 *
 * Entries are keyed by the WordPress plugin file the SPA sends — the plugin
 * basename `{directory}/{main-file.php}`, exactly as WordPress identifies a
 * plugin. The key is the canonical WordPress.org (Lite) file: it is what the
 * installer installs and what detection reports under.
 *
 * Entry shape:
 * - `name` Display name shown to the user.
 * - `pro`  Optional Pro variant file shipped under a separate folder. Used only
 *          to fold Lite + Pro into a single detection result; never installed.
 *
 * @since 2.0.0
 */
class PluginCatalog {

	/**
	 * Regular WordPress.org plugins WPForms installs in place ( everything that is not a
	 * WPForms addon ): the wizard's cross-product plugins plus the Setup Checklist's
	 * recommended growth tools and the ActiveLayer anti-spam plugin. Keyed by the canonical
	 * Lite file, whose directory is the wp.org slug the download URL derives from. Detection
	 * treats them uniformly, so the checklist's recommendations also surface in the wizard's
	 * cross-plugins detection.
	 *
	 * @since 2.0.0
	 *
	 * @var array<string, array{name: string, pro?: string}>
	 */
	private const CROSS_PLUGINS = [
		'uncanny-automator/uncanny-automator.php'     => [
			'name' => 'Uncanny Automator',
			'pro'  => 'uncanny-automator-pro/uncanny-automator-pro.php',
		],
		'wp-mail-smtp/wp_mail_smtp.php'               => [
			'name' => 'WP Mail SMTP',
			'pro'  => 'wp-mail-smtp-pro/wp_mail_smtp.php',
		],
		'wpconsent-cookies-banner-privacy-suite/wpconsent.php' => [
			'name' => 'WPConsent',
		],
		'all-in-one-seo-pack/all_in_one_seo_pack.php' => [
			'name' => 'AIOSEO',
		],
		'universally-language-translation-multilingual-tool/universally.php' => [
			'name' => 'Universally',
		],
		'duplicator/duplicator.php'                   => [
			'name' => 'Duplicator',
		],
		'reviews-feed/sb-reviews.php'                 => [
			'name' => 'Reviews Feed',
		],
		'optinmonster/optin-monster-wp-api.php'       => [
			'name' => 'OptinMonster',
		],
		'google-analytics-for-wordpress/googleanalytics.php' => [
			'name' => 'MonsterInsights',
		],
		'activelayer-anti-spam-spam-protection-for-forms-comments/activelayer-anti-spam-spam-protection-for-forms-comments.php' => [
			'name' => 'ActiveLayer',
		],
	];

	/**
	 * Form plugins the wizard can import submissions/forms from.
	 *
	 * @since 2.0.0
	 *
	 * @var array<string, array{name: string, pro?: string}>
	 */
	private const FORMS_PLUGINS = [
		'contact-form-7/wp-contact-form-7.php' => [
			'name' => 'Contact Form 7',
		],
		'ninja-forms/ninja-forms.php'          => [
			'name' => 'Ninja Forms',
		],
		'pirate-forms/pirate-forms.php'        => [
			'name' => 'Pirate Forms',
		],
	];

	/**
	 * Detection files for the cross-product plugins, keyed by plugin file.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<int, string>>
	 */
	public function cross_plugin_files(): array {

		return $this->detection_files( self::CROSS_PLUGINS );
	}

	/**
	 * Detection files for the form plugins, keyed by plugin file.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<int, string>>
	 */
	public function forms_plugin_files(): array {

		return $this->detection_files( self::FORMS_PLUGINS );
	}

	/**
	 * Resolve the plugin main file the installer should act on.
	 *
	 * The catalog key is the canonical WordPress.org (Lite) file, so a known
	 * plugin file resolves to itself; `wpforms_install_plugin()` derives the
	 * download URL and the already-installed check from it. Returning the empty
	 * string for unknown files keeps the installer pinned to the whitelist.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin file the SPA sent.
	 *
	 * @return string Plugin main file, or empty string when the file is unknown.
	 */
	public function main_file( string $plugin_file ): string {

		return $this->entry( $plugin_file ) !== null ? $plugin_file : '';
	}

	/**
	 * Resolve the display name for a plugin file.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin file the SPA sent.
	 *
	 * @return string Display name, or empty string when the file is unknown.
	 */
	public function name( string $plugin_file ): string {

		return $this->entry( $plugin_file )['name'] ?? '';
	}

	/**
	 * Whether a plugin file is a WPForms addon rather than a regular plugin.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin file the SPA sent.
	 *
	 * @return bool
	 */
	public function is_addon( string $plugin_file ): bool {

		if ( isset( self::CROSS_PLUGINS[ $plugin_file ] ) || isset( self::FORMS_PLUGINS[ $plugin_file ] ) ) {
			return false;
		}

		return $this->addon_entry( $plugin_file ) !== null;
	}

	/**
	 * Resolve the WordPress.org repository URL for a regular plugin.
	 *
	 * The catalog key is the canonical WordPress.org file, so its directory is
	 * the wp.org slug. Addons and unknown files resolve to the empty string:
	 * they have no wp.org repository page.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin file the SPA sent.
	 *
	 * @return string Repository URL, or empty string when there is none.
	 */
	public function wporg_url( string $plugin_file ): string {

		if ( $this->is_addon( $plugin_file ) || $this->entry( $plugin_file ) === null ) {
			return '';
		}

		return trailingslashit( 'https://wordpress.org/plugins/' . dirname( $plugin_file ) );
	}

	/**
	 * Build the plugin-file => detection-files map for a catalog group.
	 *
	 * Each entry detects against its own key plus the optional `pro` variant, so
	 * a single plugin file stands in for Lite + Pro builds shipped under
	 * different folders.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, array{name: string, pro?: string}> $plugins Catalog group.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function detection_files( array $plugins ): array {

		$map = [];

		foreach ( $plugins as $plugin_file => $entry ) {
			$map[ $plugin_file ] = array_values( array_filter( [ $plugin_file, $entry['pro'] ?? null ] ) );
		}

		return $map;
	}

	/**
	 * Look up a single entry across both plugin groups.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin file the SPA sent.
	 *
	 * @return array|null
	 */
	private function entry( string $plugin_file ): ?array {

		if ( isset( self::CROSS_PLUGINS[ $plugin_file ] ) ) {
			return self::CROSS_PLUGINS[ $plugin_file ];
		}

		if ( isset( self::FORMS_PLUGINS[ $plugin_file ] ) ) {
			return self::FORMS_PLUGINS[ $plugin_file ];
		}

		return $this->addon_entry( $plugin_file );
	}

	/**
	 * Resolve a WPForms addon entry from the addons data handler.
	 *
	 * Addons live in a `wpforms-{slug}` directory. The data handler is the source
	 * of truth for every addon, so the catalog asks it instead of duplicating the
	 * full list, which would drift. Only an addon's canonical main file
	 * (`{slug}/{slug}.php`) resolves, keeping the installer pinned to real addons.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin file the SPA sent.
	 *
	 * @return array|null Entry with a `name`, or null when the file is not a known addon.
	 */
	private function addon_entry( string $plugin_file ): ?array {

		if ( strpos( dirname( $plugin_file ), 'wpforms-' ) !== 0 ) {
			return null;
		}

		$addons = wpforms()->obj( 'addons' );

		if ( ! $addons ) {
			return null;
		}

		$addon = (array) $addons->get_addon( dirname( $plugin_file ) );

		if ( empty( $addon['path'] ) || $addon['path'] !== $plugin_file ) {
			return null;
		}

		return [ 'name' => $addon['name'] ?? '' ];
	}
}
