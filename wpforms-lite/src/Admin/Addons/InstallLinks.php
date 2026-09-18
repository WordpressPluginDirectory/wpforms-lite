<?php

namespace WPForms\Admin\Addons;

/**
 * Install-link and license-tier primitives shared by addon-promo consumers.
 *
 * Resolves one-click install CTAs (install / activate / installed) for WordPress.org
 * plugins and WPForms addons, plus the license-tier helpers those CTAs branch on. The
 * using class must expose `$plugin_detector` (PluginDetector) and `$plugin_catalog`
 * (PluginCatalog) properties — {@see \WPForms\Admin\Addons\FeatureTiles} and
 * {@see \WPForms\SetupChecklist\Promos} both do.
 *
 * @since 2.0.2
 */
trait InstallLinks {

	/**
	 * Resolve the one-click install CTA for one or more plugins by their aggregate state.
	 *
	 * The install endpoint installs-or-activates and resolves addon-vs-plugin itself, so this
	 * builds a single CTA for any WordPress.org plugin or WPForms addon, addressed by basename
	 * ( comma-joined for a bundle ). An already-active set is a no-op "Installed" state.
	 *
	 * @since 2.0.2
	 *
	 * @param array $files Plugin main-file paths (folder/file.php).
	 *
	 * @return array Link parts: `text`, `action`, `plugin`, `external`, `installed`.
	 */
	public function install_link( array $files ): array {

		$all_active    = true;
		$all_installed = true;

		foreach ( $files as $file ) {
			$status        = $this->plugin_detector->status( $file );
			$all_active    = $all_active && $status['active'];
			$all_installed = $all_installed && $status['installed'];
		}

		if ( $all_active ) {
			$text = __( 'Installed', 'wpforms-lite' );
		} elseif ( $all_installed ) {
			$text = __( 'Activate', 'wpforms-lite' );
		} else {
			$text = __( 'Install', 'wpforms-lite' );
		}

		return [
			'text'      => $text,
			'action'    => $all_active ? 'active' : ( $all_installed ? 'activate-plugin' : 'install-plugin' ),
			'plugin'    => $all_active ? '' : implode( ',', $files ),
			'external'  => false,
			'installed' => $all_installed,
		];
	}

	/**
	 * Resolve the install CTA for one or more plugins, gated by what the user can actually do.
	 *
	 * Wraps install_link() with the capability gate: a CTA the shared install endpoint would
	 * reject is replaced by a fallback, so no site renders an Install/Activate button whose only
	 * possible outcome is a server-side failure. The fallback follows the type — the Addons page
	 * for a WPForms addon, the plugin's own destination for a wordpress.org one.
	 *
	 * @since 2.0.2
	 *
	 * @param array  $files Plugin main-file paths (folder/file.php). The wordpress.org fallback
	 *                      addresses the first of them, so bundles are addon-only in practice.
	 * @param string $type  Either 'plugin' for wordpress.org plugins or 'addon' for WPForms addons.
	 *
	 * @return array
	 */
	private function gated_install_link( array $files, string $type ): array {

		$link = $this->install_link( $files );

		if ( Install::can_install_and_activate( $link['action'], $type ) ) {
			return $link;
		}

		if ( $type === 'addon' ) {
			return $this->addons_page_link();
		}

		return $this->wporg_link( (string) reset( $files ), (bool) $link['installed'] );
	}

	/**
	 * The fallback CTA for a wordpress.org plugin the user cannot install or activate in place.
	 *
	 * An installed-but-inactive plugin can still be activated from the Plugins screen — multisite
	 * site admins keep `activate_plugins` while losing `install_plugins` — so a user who holds
	 * that capability is sent there. Everyone else gets the plugin's wordpress.org page, which
	 * remains useful when file modifications are disabled and the install must happen out of band.
	 *
	 * `is_upgrade` is deliberately absent: it is what marks a link as a Pro-upgrade CTA in the
	 * shared admin/addons/install-link template, and this is not one.
	 *
	 * @since 2.0.2
	 *
	 * @param string $file         Plugin main-file path (folder/file.php).
	 * @param bool   $is_installed Whether the plugin is already on disk.
	 *
	 * @return array
	 */
	private function wporg_link( string $file, bool $is_installed ): array {

		$fallback = Install::get_fallback_destination( $file, $is_installed );

		return [
			'text'     => $fallback['is_activate'] ? __( 'Activate', 'wpforms-lite' ) : __( 'Install', 'wpforms-lite' ),
			'url'      => $fallback['url'],
			'action'   => '',
			'plugin'   => '',
			'external' => ! $fallback['is_activate'],
		];
	}

	/**
	 * Whether the current license is Pro or Elite — the tiers that install addons in place.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public function is_pro_plus(): bool {

		return in_array( $this->get_license_tier(), [ 'pro', 'elite' ], true );
	}

	/**
	 * Resolve the install CTA for one or more WPForms addons by slug.
	 *
	 * Resolves each slug to its addon plugin file ( an unknown slug bails to the Addons page
	 * rather than rendering a partial install ), then defers to install_link(). Installing in
	 * place needs the install capability; a user who lacks it is sent to the Addons page, while
	 * an already-active addon still shows its "Installed" state.
	 *
	 * @since 2.0.2
	 *
	 * @param array $slugs Addon slugs ( one, or several for a bundled feature tile ).
	 *
	 * @return array
	 */
	private function addon_install_link( array $slugs ): array {

		$files = [];

		foreach ( $slugs as $slug ) {
			$file = $this->addon_plugin_file( $slug );

			if ( $file === '' ) {
				return $this->addons_page_link();
			}

			$files[] = $file;
		}

		if ( $files === [] ) {
			return $this->addons_page_link();
		}

		// Gate the in-place CTA by the actual operation. wpforms_can_install()/can_activate()
		// also honor license state and DISALLOW_FILE_MODS, so expired / disabled / file-mod-locked
		// sites fall back to the Addons page instead of a doomed in-place CTA.
		return $this->gated_install_link( $files, 'addon' );
	}

	/**
	 * The "Install" CTA that opens the Addons page — the fallback used when an in-place
	 * install is unavailable (an unknown slug, or a user who cannot install plugins).
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function addons_page_link(): array {

		return [
			'text'     => __( 'Install', 'wpforms-lite' ),
			'url'      => admin_url( 'admin.php?page=wpforms-addons' ),
			'action'   => '',
			'plugin'   => '',
			'external' => false,
		];
	}

	/**
	 * Resolve an addon slug to its WPForms addon plugin file.
	 *
	 * @since 2.0.2
	 *
	 * @param string $slug Addon slug.
	 *
	 * @return string Plugin file, or empty string when the slug is not a known addon.
	 */
	private function addon_plugin_file( string $slug ): string {

		$renamed = [
			'brevo' => 'sendinblue',
		];

		$folder      = 'wpforms-' . ( $renamed[ $slug ] ?? $slug );
		$plugin_file = $folder . '/' . $folder . '.php';

		return $this->plugin_catalog->is_addon( $plugin_file ) ? $plugin_file : '';
	}

	/**
	 * Resolve the license to a promo bucket: plus, pro, elite, or default (Lite + Basic).
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	private function get_license_tier(): string {

		$type = wpforms_get_license_type();

		if ( $type === 'plus' || $type === 'pro' ) {
			return $type;
		}

		// Elite and its legacy license names share the Elite list.
		if ( in_array( $type, [ 'elite', 'agency', 'ultimate' ], true ) ) {
			return 'elite';
		}

		// Lite (no license) and Basic share the default list.
		return 'default';
	}
}
