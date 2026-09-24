<?php

namespace WPForms\Vendor\ProductApi\Auth;

use WPForms\Vendor\ProductApi\Options;
/**
 * Installation ID class.
 *
 * @since 1.0.0
 */
class InstallationId
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
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Options $options Options instance.
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }
    /**
     * Get the installation ID.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get()
    {
        $installation_id = $this->options->get('wp_installation_id');
        if (!empty($installation_id)) {
            return $installation_id;
        }
        $installation_id = $this->create();
        $this->options->update(['wp_installation_id' => $installation_id]);
        return $installation_id;
    }
    /**
     * Create a unique installation ID.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function create()
    {
        $data = \sprintf('%s%s%s', \defined('WPForms\\Vendor\\AUTH_KEY') ? AUTH_KEY : '', \defined('WPForms\\Vendor\\SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '', \defined('WPForms\\Vendor\\LOGGED_IN_KEY') ? LOGGED_IN_KEY : '');
        if (\trim($data) === '') {
            $data = \bin2hex(\random_bytes(32));
        }
        $hash = \hash('sha256', $data);
        return \substr($hash, 0, 30);
    }
}
