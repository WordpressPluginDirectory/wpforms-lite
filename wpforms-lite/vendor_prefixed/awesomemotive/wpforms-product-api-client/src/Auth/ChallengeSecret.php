<?php

namespace WPForms\Vendor\ProductApi\Auth;

use WPForms\Vendor\ProductApi\Options;
/**
 * Challenge secret for site ownership verification.
 * Used in site ownership verification flow.
 *
 * @since 1.0.0
 */
class ChallengeSecret
{
    /**
     * Options instance.
     *
     * @since 1.0.0
     *
     * @var Options
     */
    private $options;
    /**
     * Action name for the challenge.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $action;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Options $options Options instance.
     * @param string  $action  Action name for the challenge.
     */
    public function __construct(Options $options, $action)
    {
        $this->options = $options;
        $this->action = $action;
    }
    /**
     * Get the challenge secret value.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get()
    {
        $secret = $this->options->get_transient($this->get_option_key());
        if (empty($secret)) {
            $secret = $this->create();
        } else {
            // Renew the transient.
            $this->options->set_transient($this->get_option_key(), $secret, MINUTE_IN_SECONDS * 5);
        }
        return $secret;
    }
    /**
     * Compute HMAC signature for the given nonce.
     *
     * @since 1.0.0
     *
     * @param string $nonce The nonce received from server.
     *
     * @return string The HMAC signature.
     */
    public function sign($nonce)
    {
        return \hash_hmac('sha256', $nonce, $this->get());
    }
    /**
     * Create a new challenge secret.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function create()
    {
        $auth_salt = \defined('WPForms\\Vendor\\AUTH_SALT') ? AUTH_SALT : '';
        $secret = \hash('sha512', wp_generate_password(128, \true, \true) . $auth_salt . \uniqid('', \true));
        $this->options->set_transient($this->get_option_key(), $secret, MINUTE_IN_SECONDS * 5);
        return $secret;
    }
    /**
     * Get the option key for storing the challenge secret.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function get_option_key()
    {
        return 'challenge_secret_' . $this->action;
    }
}
