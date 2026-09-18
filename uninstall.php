<?php
/**
 * Removes plugin options on uninstall.
 *
 * @package JevConnector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'jevc_settings' );
delete_site_option( 'jevc_settings' );
