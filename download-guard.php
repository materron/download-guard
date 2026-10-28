<?php
/**
 * Plugin Name:       Download Guard
 * Plugin URI:        https://github.com/materron/download-guard
 * Description:       Stops members from draining your downloads: daily and 30-day limits per role, stricter limits for new members, bulk-download alerts and sign-up filtering. Works with Download Monitor, Easy Digital Downloads and WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Miguel Ángel Terrón Bote
 * Author URI:        https://potencia.pro
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       download-guard
 *
 * @package DownloadGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DLGD_VERSION', '1.0.0' );
define( 'DLGD_FILE', __FILE__ );
define( 'DLGD_DIR', plugin_dir_path( __FILE__ ) );

require_once DLGD_DIR . 'includes/class-dlgd-settings.php';
require_once DLGD_DIR . 'includes/class-dlgd-adapter.php';
require_once DLGD_DIR . 'includes/adapters/class-dlgd-adapter-download-monitor.php';
require_once DLGD_DIR . 'includes/adapters/class-dlgd-adapter-edd.php';
require_once DLGD_DIR . 'includes/adapters/class-dlgd-adapter-woocommerce.php';
require_once DLGD_DIR . 'includes/class-dlgd-blocks.php';
require_once DLGD_DIR . 'includes/class-dlgd-guard.php';
require_once DLGD_DIR . 'includes/class-dlgd-signup.php';
require_once DLGD_DIR . 'includes/class-dlgd-admin.php';

/**
 * Boots the plugin once all plugins are loaded, so the download plugins can be detected.
 */
function dlgd_init() {
	DLGD_Guard::init();
	DLGD_Signup::init();

	if ( is_admin() ) {
		DLGD_Admin::init();
	}
}
add_action( 'plugins_loaded', 'dlgd_init', 20 );

/**
 * Declares compatibility with WooCommerce's order tables: the plugin reads download
 * permissions and logs, never orders.
 */
function dlgd_declare_woocommerce_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DLGD_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'dlgd_declare_woocommerce_compatibility' );
