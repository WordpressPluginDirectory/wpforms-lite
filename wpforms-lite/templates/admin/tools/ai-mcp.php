<?php
/**
 * AI MCP page template — WPForms → Tools → AI MCP.
 *
 * Two-block layout from the Figma design (file 0zyEC40sf4SsSgL4KUOI3B, node 3:343):
 *   1. Dark hero block — eyebrow / title / lede / CTA row (install button + toggle)
 *      with an AI-clients column on the right.
 *   2. White cards-panel — section header + three capability cards (Forms, Fields,
 *      Entries) with bullet lists.
 *
 * @since 1.10.2
 *
 * @var string $state               WPVibe state — 'not_installed' | 'installed_inactive' | 'active'.
 * @var bool   $is_write_enabled    Current value of the ai-mcp-write-enabled setting.
 * @var bool   $is_pro              True when WPForms Pro is active; drives Pro-badge hiding.
 * @var string $wpvibe_download_url WPVibe wp.org ZIP URL.
 * @var string $wpvibe_basename     WPVibe plugin basename (e.g. vibe-ai/vibe-ai.php).
 * @var string $wpvibe_setup_url    WPVibe admin page URL — anchor for the active-state CTA.
 * @var bool   $has_visited_wpvibe  True if the user has been on the WPVibe admin page before.
 * @var string $docs_url            URL for the "View Abilities API Documentation" link.
 */

defined( 'ABSPATH' ) || exit;

$can_install  = wpforms_can_install( 'plugin' );
$can_activate = wpforms_can_activate( 'plugin' );

$cards = [
	[
		'slug'    => 'forms',
		'icon'    => 'file-lines',
		'title'   => __( 'Forms', 'wpforms-lite' ),
		'bullets' => [
			__( 'Create a brand new form using plain English', 'wpforms-lite' ),
			__( 'Update form settings and submit button text', 'wpforms-lite' ),
			__( "Get a form's full configuration, including every field", 'wpforms-lite' ),
		],
		'pro'     => false,
	],
	[
		'slug'    => 'fields',
		'icon'    => 'list-alt',
		'title'   => __( 'Fields', 'wpforms-lite' ),
		'bullets' => [
			__( 'Add a field to an existing form', 'wpforms-lite' ),
			__( 'Update an existing field (label, required, options…)', 'wpforms-lite' ),
			__( 'Discover which field types are available', 'wpforms-lite' ),
		],
		'pro'     => false,
	],
	[
		'slug'    => 'entries',
		'icon'    => 'envelope-open-text',
		'title'   => __( 'Entries', 'wpforms-lite' ),
		'bullets' => [
			__( 'Browse entries for any form, with pagination', 'wpforms-lite' ),
			__( 'Search entries by date range, field value & status', 'wpforms-lite' ),
			__( "Get a single entry's full details", 'wpforms-lite' ),
		],
		'pro'     => true,
	],
];

$ai_clients = [
	[
		'asset' => 'ai-claude.svg',
		'label' => __( 'Claude', 'wpforms-lite' ),
	],
	[
		'asset' => 'ai-chatgpt.svg',
		'label' => __( 'ChatGPT', 'wpforms-lite' ),
	],
	[
		'asset' => 'ai-cursor.svg',
		'label' => __( 'Cursor', 'wpforms-lite' ),
	],
];
?>

<div class="wpforms-ai-mcp-page">

	<section class="wpforms-ai-mcp-hero">

		<div class="wpforms-ai-mcp-hero-copy">

			<p class="wpforms-ai-mcp-eyebrow"><?php esc_html_e( 'WordPress Abilities API + WPForms', 'wpforms-lite' ); ?></p>
			<h1 class="wpforms-ai-mcp-title"><?php esc_html_e( 'Use WPForms With Your Favorite AI', 'wpforms-lite' ); ?></h1>
			<p class="wpforms-ai-mcp-lede">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: WPVibe.ai inline link. */
						__( 'Connect your WordPress site and WPForms to AI assistants like Claude, ChatGPT, Cursor, and more. Ask them to find submissions, build forms, or edit fields in plain English. No copy-pasting, no exports. Connect them with the free %s plugin.', 'wpforms-lite' ),
						sprintf(
							'<a href="%s" target="_blank" rel="noopener noreferrer"><strong>WPVibe.ai</strong></a>',
							esc_url( 'https://wpvibe.ai/?utm_source=wpformsplugin&utm_medium=link&utm_campaign=ai-mcp-page' )
						)
					),
					[
						'a'      => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
						],
						'strong' => [],
					]
				);
				?>
			</p>

			<div class="wpforms-ai-mcp-cta-row">

				<?php if ( $state === 'not_installed' ) : ?>
					<?php if ( $can_install ) : ?>
						<button
							type="button"
							class="wpforms-btn wpforms-btn-orange wpforms-ai-mcp-wpvibe-button"
							data-action="install"
							data-plugin="<?php echo esc_attr( $wpvibe_download_url ); ?>"
						><?php esc_html_e( 'Install & Activate WPVibe', 'wpforms-lite' ); ?></button>
					<?php else : ?>
						<a
							href="https://wordpress.org/plugins/vibe-ai/"
							class="wpforms-btn wpforms-btn-orange wpforms-ai-mcp-wpvibe-button"
							target="_blank"
							rel="noopener noreferrer"
						><?php esc_html_e( 'Install from WordPress.org →', 'wpforms-lite' ); ?></a>
					<?php endif; ?>
				<?php elseif ( $state === 'installed_inactive' && $can_activate ) : ?>
					<button
						type="button"
						class="wpforms-btn wpforms-btn-orange wpforms-ai-mcp-wpvibe-button"
						data-action="activate"
						data-plugin="<?php echo esc_attr( $wpvibe_basename ); ?>"
					><?php esc_html_e( 'Activate WPVibe', 'wpforms-lite' ); ?></button>
				<?php elseif ( $state === 'active' ) : ?>
					<a
						class="wpforms-btn wpforms-btn-blue wpforms-ai-mcp-wpvibe-button"
						href="<?php echo esc_url( $wpvibe_setup_url ); ?>"
					>
						<?php
						echo esc_html(
							$has_visited_wpvibe
								? __( 'Go To WPVibe', 'wpforms-lite' )
								: __( 'Set Up WPVibe', 'wpforms-lite' )
						);
						?>
					</a>
				<?php endif; ?>
				<?php /* installed_inactive without activate cap → no button, only the toggle is rendered below. */ ?>

				<span class="wpforms-toggle-control wpforms-ai-mcp-toggle">
					<input
						type="checkbox"
						id="wpforms-ai-mcp-write-toggle"
						value="1"
						<?php checked( $is_write_enabled, true ); ?>
					>
					<label class="wpforms-toggle-control-icon" for="wpforms-ai-mcp-write-toggle" aria-hidden="true"></label>
					<label class="wpforms-ai-mcp-toggle-label" for="wpforms-ai-mcp-write-toggle">
						<?php esc_html_e( 'Enable MCP Write Access', 'wpforms-lite' ); ?>
					</label>
				</span>

			</div>

			<?php if ( $state === 'not_installed' && ! $can_install ) : ?>
				<p class="wpforms-ai-mcp-install-note">
					<?php esc_html_e( 'Your site is configured to disallow plugin installation from the dashboard.', 'wpforms-lite' ); ?>
				</p>
			<?php endif; ?>

		</div>

		<aside class="wpforms-ai-mcp-clients" aria-label="<?php esc_attr_e( 'Supported AI clients', 'wpforms-lite' ); ?>">
			<?php foreach ( $ai_clients as $client ) : ?>
				<div class="wpforms-ai-mcp-client-pill">
					<span class="wpforms-ai-mcp-client-icon">
						<img
							src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/admin/tools/ai-mcp/' . $client['asset'] ); ?>"
							alt=""
							role="presentation"
						>
					</span>
					<span class="wpforms-ai-mcp-client-label"><?php echo esc_html( $client['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
			<p class="wpforms-ai-mcp-clients-note"><?php esc_html_e( '+ Any MCP Client', 'wpforms-lite' ); ?></p>
		</aside>

	</section>

	<section class="wpforms-ai-mcp-capabilities">

		<header class="wpforms-ai-mcp-capabilities-head">
			<h2 class="wpforms-ai-mcp-capabilities-title"><?php esc_html_e( 'Everything WPForms Can Do With AI', 'wpforms-lite' ); ?></h2>
			<a
				class="wpforms-ai-mcp-docs-link"
				href="<?php echo esc_url( wpforms_utm_link( $docs_url, 'Tools - AI MCP', 'Learn More - Abilities API Documentation' ) ); ?>"
				target="_blank"
				rel="noopener noreferrer"
			>
				<span class="wpforms-ai-mcp-docs-text"><?php esc_html_e( 'View Abilities API Documentation', 'wpforms-lite' ); ?></span>
				<span class="wpforms-ai-mcp-docs-arrow fa-solid fa-arrow-right" aria-hidden="true"></span>
			</a>
		</header>

		<div class="wpforms-ai-mcp-cards">
			<?php foreach ( $cards as $card ) : ?>
				<article class="wpforms-ai-mcp-card wpforms-ai-mcp-card-<?php echo esc_attr( $card['slug'] ); ?>">

					<header class="wpforms-ai-mcp-card-head">
						<span class="wpforms-ai-mcp-card-icon fa-solid fa-<?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
						<h3 class="wpforms-ai-mcp-card-title"><?php echo esc_html( $card['title'] ); ?></h3>
					</header>

					<ul class="wpforms-ai-mcp-card-bullets">
						<?php foreach ( $card['bullets'] as $bullet ) : ?>
							<li>
								<span class="wpforms-ai-mcp-card-dot" aria-hidden="true"></span>
								<span class="wpforms-ai-mcp-card-bullet-text"><?php echo esc_html( $bullet ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>

					<?php if ( $card['pro'] && ! $is_pro ) : ?>
						<span
							class="wpforms-ai-mcp-pro-badge education-modal"
							data-name="<?php echo esc_attr( $card['title'] ); ?>"
							data-plural="1"
							data-action="upgrade"
							role="button"
							tabindex="0"
						><?php esc_html_e( 'Pro', 'wpforms-lite' ); ?></span>
					<?php endif; ?>

				</article>
			<?php endforeach; ?>
		</div>

	</section>

</div>
