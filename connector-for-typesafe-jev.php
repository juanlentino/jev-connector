<?php
/**
 * Plugin Name:       Connector for TypeSafe Jev
 * Plugin URI:        https://github.com/juanlentino/jev-connector
 * Description:       Connects WordPress to the TypeSafe System One API (Jev) through the core Connectors API. A PHP client, question builders, a REST proxy, and hooks so themes and plugins can ask typed questions and get structured, confidence-scored answers. No screens of its own.
 * Version:           0.3.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Juan Lentino
 * Author URI:        https://juanlentino.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       connector-for-typesafe-jev
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.3.0';
const PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/includes/class-exception.php';
require_once __DIR__ . '/includes/class-question.php';
require_once __DIR__ . '/includes/class-response.php';
require_once __DIR__ . '/includes/class-cache.php';
require_once __DIR__ . '/includes/class-connector.php';
require_once __DIR__ . '/includes/class-client.php';
require_once __DIR__ . '/includes/class-rest-controller.php';
require_once __DIR__ . '/includes/functions.php';

/**
 * Boot the plugin.
 *
 * @return void
 */
function bootstrap(): void {
	Connector::register();
	add_action(
		'rest_api_init',
		static function (): void {
			( new REST_Controller() )->register_routes();
		}
	);
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );
