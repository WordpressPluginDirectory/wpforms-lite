<?php

namespace WPForms\SetupChecklist;

/**
 * Setup Checklist configuration and data model.
 *
 * Single declarative source of the checklist's sections and items. The model is
 * intentionally ordering- and insertion-friendly: every section and item carries
 * an `order` weight, and both lists pass through filters so a later iteration can
 * reorder or insert recommended items without touching this class. Nothing here
 * performs detection — completion is resolved by {@see CompletionDetector}, keyed
 * by each item's `id` — and nothing here renders markup.
 *
 * @since 2.0.0
 */
class Config {

	/**
	 * "Create Your First Form" section ID.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const SECTION_CREATE_FORM = 'create_first_form';

	/**
	 * "Set Up Form Compliance & Protection" section ID.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const SECTION_COMPLIANCE = 'compliance_protection';

	/**
	 * "Connect to Payment Gateway" section ID.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const SECTION_PAYMENT = 'payment_gateway';

	/**
	 * Ordered checklist sections, each with its ordered items.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array>
	 */
	public function get_sections(): array {

		$sections = [
			self::SECTION_CREATE_FORM => [
				'id'              => self::SECTION_CREATE_FORM,
				'title'           => __( 'Create Your First Form', 'wpforms-lite' ),
				'order'           => 10,
				// A form exists by EITHER path — built or imported — so any single
				// item completes this section, not all of them.
				'complete_on_any' => true,
				'items'           => $this->get_create_form_items(),
			],
			self::SECTION_COMPLIANCE  => [
				'id'    => self::SECTION_COMPLIANCE,
				'title' => __( 'Set Up Form Compliance & Protection', 'wpforms-lite' ),
				'order' => 20,
				'items' => $this->get_compliance_items(),
			],
			self::SECTION_PAYMENT     => [
				'id'    => self::SECTION_PAYMENT,
				'title' => __( 'Connect to Payment Gateway', 'wpforms-lite' ),
				'order' => 30,
				'items' => $this->get_payment_items(),
			],
		];

		/**
		 * Filter the checklist sections before they are ordered.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, array> $sections Sections keyed by section ID.
		 */
		$sections = (array) apply_filters( 'wpforms_setup_checklist_config_get_sections', $sections );

		$sections = $this->sort_by_order( $sections );

		foreach ( $sections as $id => $section ) {
			if ( ! empty( $section['items'] ) && is_array( $section['items'] ) ) {
				$sections[ $id ]['items'] = $this->sort_by_order( $section['items'] );
			}
		}

		return $sections;
	}

	/**
	 * Items for the "Create Your First Form" section.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array>
	 */
	private function get_create_form_items(): array {

		return [
			'build_form'   => [
				'id'          => 'build_form',
				'section'     => self::SECTION_CREATE_FORM,
				'title'       => __( 'Build a Form', 'wpforms-lite' ),
				'description' => __( 'Describe what you need and let WPForms AI build it for you, pick from 2,100+ templates, or start from scratch with the drag and drop builder.', 'wpforms-lite' ),
				'order'       => 10,
				'cta'         => [
					'label' => __( 'Create a Form', 'wpforms-lite' ),
					'icon'  => 'fa-plus',
					'url'   => admin_url( 'admin.php?page=wpforms-builder' ),
				],
			],
			'import_forms' => [
				'id'          => 'import_forms',
				'section'     => self::SECTION_CREATE_FORM,
				'title'       => __( 'Import Forms', 'wpforms-lite' ),
				'description' => __( 'Migrate quickly and easily. Bring your existing forms over in just a few clicks, so you can pick up right where you left off.', 'wpforms-lite' ),
				'order'       => 20,
				'conditional' => true,
				'cta'         => [
					'label'    => __( 'Import Forms', 'wpforms-lite' ),
					'icon'     => 'fa-file-import',
					'url'      => admin_url( 'admin.php?page=wpforms-tools&view=import&tab=forms' ),
					'modifier' => 'secondary',
				],
			],
		];
	}

	/**
	 * Items for the "Set Up Form Compliance & Protection" section.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array>
	 */
	private function get_compliance_items(): array {

		return [
			'lite_connect'         => [
				'id'          => 'lite_connect',
				'section'     => self::SECTION_COMPLIANCE,
				'title'       => __( 'Enable Lite Connect for Entry Backups', 'wpforms-lite' ),
				'description' => __( "WPForms Lite doesn't store entries in your database. Enable secure entry backups.", 'wpforms-lite' ),
				'order'       => 10,
				'cta'         => [
					'label'    => __( 'Enable Entry Backups', 'wpforms-lite' ),
					'url'      => admin_url( 'admin.php?page=wpforms-settings' ),
					'modifier' => 'grey',
					'action'   => 'lite-connect',
				],
			],
			'email_deliverability' => [
				'id'          => 'email_deliverability',
				'section'     => self::SECTION_COMPLIANCE,
				'title'       => __( 'Configure Email Deliverability', 'wpforms-lite' ),
				'description' => __( 'Make sure your form emails always get delivered in your inbox with WP Mail SMTP.', 'wpforms-lite' ),
				'order'       => 20,
				'cta'         => [
					// Install / Set Up label resolved live in Page::resolve_cta();
					// both link to the WPForms SMTP page, which handles the install.
					'wpmailsmtp' => true,
				],
			],
			'spam_protection'      => [
				'id'          => 'spam_protection',
				'section'     => self::SECTION_COMPLIANCE,
				'title'       => __( 'Enable Spam Protection', 'wpforms-lite' ),
				'description' => __( 'Protect your forms with our CAPTCHA integrations or ActiveLayer, an AI-powered spam blocker that just works.', 'wpforms-lite' ),
				'order'       => 30,
				'ctas'        => [
					[ 'activelayer' => true ],
					[ 'captcha' => true ],
				],
			],
			'email_notifications'  => [
				'id'          => 'email_notifications',
				'section'     => self::SECTION_COMPLIANCE,
				'title'       => __( 'Customize Email Notifications', 'wpforms-lite' ),
				'description' => __( 'Add your logo, colors, and styles to ensure emails match your brand.', 'wpforms-lite' ),
				'order'       => 40,
				'cta'         => [
					'label'    => __( 'Customize Emails', 'wpforms-lite' ),
					'url'      => admin_url( 'admin.php?page=wpforms-settings&view=email' ),
					'modifier' => 'grey',
				],
			],
			'privacy_compliance'   => [
				'id'          => 'privacy_compliance',
				'section'     => self::SECTION_COMPLIANCE,
				'title'       => __( 'Set Up Privacy Compliance', 'wpforms-lite' ),
				'description' => __( 'Improve GDPR, CCPA, and privacy compliance with cookie banner and consent records.', 'wpforms-lite' ),
				'order'       => 50,
				'cta'         => [
					'wpconsent' => true,
				],
			],
		];
	}

	/**
	 * Items for the "Connect to Payment Gateway" section.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array>
	 */
	private function get_payment_items(): array {

		return [
			'payment_gateway' => [
				'id'          => 'payment_gateway',
				'section'     => self::SECTION_PAYMENT,
				'title'       => __( 'Connect to Payment Gateway', 'wpforms-lite' ),
				'description' => __( 'Accept credit card payments, Apple Pay, Google Pay, ACH, and more with WPForms Stripe integration.', 'wpforms-lite' ),
				'order'       => 10,
			],
		];
	}

	/**
	 * Sort a keyed list of sections or items by their `order` weight, ascending.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, array> $entries Keyed list of entries that each carry an `order`.
	 *
	 * @return array<string, array>
	 */
	private function sort_by_order( array $entries ): array {

		uasort(
			$entries,
			static function ( $a, $b ) {

				return ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 );
			}
		);

		return $entries;
	}
}
