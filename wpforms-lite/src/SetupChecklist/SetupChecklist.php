<?php

namespace WPForms\SetupChecklist;

use WPForms\SetupWizard\Service\PluginCatalog;
use WPForms\SetupWizard\Service\PluginDetector;

/**
 * Setup Checklist orchestrator.
 *
 * This foundation wires the per-site {@see State} store and assembles the
 * {@see Checklist} model (sections, items, completion, progress). The menu item,
 * page rendering, promo grids, and AJAX endpoints are layered on in later parts.
 *
 * @since 2.0.0
 */
class SetupChecklist {

	/**
	 * Checklist model: sections, items, completion, and progress.
	 *
	 * @since 2.0.0
	 *
	 * @var Checklist
	 */
	private $checklist;

	/**
	 * Per-site state store: dismissal, progress-at-dismissal, email-saved flag.
	 *
	 * @since 2.0.0
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Admin menu item (sidebar entry + progress bar).
	 *
	 * @since 2.0.0
	 *
	 * @var Menu
	 */
	private $menu;

	/**
	 * Admin page controller (the checklist page route).
	 *
	 * @since 2.0.0
	 *
	 * @var Page
	 */
	private $page;

	/**
	 * AJAX endpoints (the footer dismiss action).
	 *
	 * @since 2.0.0
	 *
	 * @var Ajax
	 */
	private $ajax;

	/**
	 * Stripe Connect OAuth-return handler: routes back to the checklist after connect.
	 *
	 * @since 2.0.0
	 *
	 * @var StripeConnect
	 */
	private $stripe_connect;

	/**
	 * Initialize the orchestrator.
	 *
	 * Runs on the `init` hook (registered on both Lite and Pro in the Loader). Detection is
	 * intentionally lazy — constructing the model here is cheap, and the actual
	 * completion checks only run when a consumer reads from {@see Checklist}.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		$plugin_detector = new PluginDetector();
		$plugin_catalog  = new PluginCatalog();

		$this->state     = new State();
		$this->checklist = new Checklist( new Config(), new CompletionDetector( $this->state, $plugin_detector ), $plugin_detector, $plugin_catalog );
		$this->menu      = new Menu( $this->checklist, $this->state );
		$this->page      = new Page( $this->checklist, new Promos( $plugin_detector, $plugin_catalog ) );
		$this->ajax      = new Ajax( $this->checklist, $this->state );

		$this->stripe_connect = new StripeConnect();

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	private function hooks(): void {

		$this->state->hooks();
		$this->menu->hooks();
		$this->page->hooks();
		$this->ajax->hooks();
		$this->stripe_connect->hooks();
	}
}
