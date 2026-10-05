<?php
/**
 * Plugin Name: Product Description Blocks for WooCommerce
 * Plugin URI: https://github.com/danielk-am/product-description-blocks-for-woocommerce
 * Description: Write product descriptions and short descriptions with blocks on the classic product screen. Only those two boxes change: everything else saves the way it always has.
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Daniel Kam
 * Author URI: https://danielk.am
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: product-description-blocks-for-woocommerce
 * WC requires at least: 11.1
 * WC tested up to: 11.1
 *
 * @package Product_Description_Blocks
 */

defined( 'ABSPATH' ) || exit;

define( 'PDBLOCKS_VERSION', '1.0.0' );
define( 'PDBLOCKS_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-pdblocks-plugin.php';

PDBlocks_Plugin::init();
