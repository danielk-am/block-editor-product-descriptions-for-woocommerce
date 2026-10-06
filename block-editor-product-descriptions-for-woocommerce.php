<?php
/**
 * Plugin Name: Block Editor Product Descriptions for WooCommerce
 * Plugin URI: https://github.com/danielk-am/block-editor-product-descriptions-for-woocommerce
 * Description: Enable the block editor (Gutenberg) for WooCommerce product descriptions and short descriptions, without switching the whole product screen.
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Daniel Kam
 * Author URI: https://danielk.am
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: block-editor-product-descriptions-for-woocommerce
 * WC requires at least: 11.1
 * WC tested up to: 11.1
 *
 * @package Block_Editor_Product_Descriptions
 */

defined( 'ABSPATH' ) || exit;

define( 'PDBLOCKS_VERSION', '1.0.0' );
define( 'PDBLOCKS_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-pdblocks-plugin.php';

PDBlocks_Plugin::init();
