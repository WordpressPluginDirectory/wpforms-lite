<?php

namespace WPForms\Vendor\ProductApi\Auth;

use WPForms\Vendor\ProductApi\Context;
use WPForms\Vendor\ProductApi\Options;
/**
 * Site ownership verification provider.
 *
 * Handles the callback from the server during site registration.
 * Receives a nonce from the server and returns an HMAC signature
 * computed using the challenge_secret stored in transient.
 *
 * @since 1.0.0
 */
class SiteOwnershipVerificationProvider
{
    /**
     * Context instance.
     *
     * @since 1.0.0
     *
     * @var Context
     */
    private $context;
    /**
     * Options instance.
     *
     * @since 1.0.0
     *
     * @var Options
     */
    private $options;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Context $context Context instance.
     * @param Options $options Options instance.
     */
    public function __construct(Context $context, Options $options)
    {
        $this->context = $context;
        $this->options = $options;
    }
    /**
     * Register hooks.
     *
     * @since 1.0.0
     */
    public function hooks()
    {
        add_action('init', [$this, 'handle_verification_callback']);
    }
    /**
     * Handle the site ownership verification callback from the server.
     *
     * @since 1.0.0
     */
    public function handle_verification_callback()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_REQUEST[$this->context->get_plugin_slug() . '-product-api-site-ownership-verification'])) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
        if (empty($nonce)) {
            wp_send_json(['error' => 'Nonce is required.'], 400);
        }
        if (empty($action)) {
            wp_send_json(['error' => 'Action is required.'], 400);
        }
        $secret_value = $this->options->get_transient('challenge_secret_' . $action);
        if (empty($secret_value)) {
            wp_send_json(['error' => 'Challenge secret not found. Registration not initiated from this site.'], 400);
        }
        $challenge_secret = new ChallengeSecret($this->options, $action);
        $signature = $challenge_secret->sign($nonce);
        wp_send_json(['signature' => $signature]);
    }
}
