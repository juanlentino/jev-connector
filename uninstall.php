<?php
/**
 * Removes plugin options on uninstall.
 *
 * The connector credential is registered by core on this plugin's behalf and
 * is not used by anything else, so it goes too.
 *
 * @package JevConnector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// jevc_settings is left over from 0.2.x, which had a settings screen.
foreach ( array( 'jevc_settings', 'connectors_typesafe_api_key' ) as $jevc_option ) {
	delete_option( $jevc_option );
	delete_site_option( $jevc_option );
}

unset( $jevc_option );
