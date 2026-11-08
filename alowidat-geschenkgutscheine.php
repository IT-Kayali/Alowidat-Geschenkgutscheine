<?php
/**
 * Plugin Name: Alowidat Geschenkgutscheine
 * Plugin URI: https://alowidat.de/
 * Description: Digitale Geschenkgutscheine für WooCommerce mit Shortcodes, Restguthaben, steuerneutraler Guthabenanrechnung und Verwaltung.
 * Version: 1.4.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.4
 * Author: IT-Kayali
 * Text Domain: alowidat-geschenkgutscheine
 * License: GPL-2.0-or-later
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ALW_GC_VERSION', '1.4.0' );
define( 'ALW_GC_FILE', __FILE__ );
define( 'ALW_GC_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALW_GC_URL', plugin_dir_url( __FILE__ ) );

require_once ALW_GC_DIR . 'includes/class-alw-gift-db.php';

register_activation_hook( __FILE__, array( 'ALW_Gift_DB', 'install' ) );

add_action( 'before_woocommerce_init', static function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ALW_GC_FILE, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ALW_GC_FILE, false );
    }
} );

add_action( 'plugins_loaded', static function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', static function () {
            if ( current_user_can( 'activate_plugins' ) ) {
                echo '<div class="notice notice-error"><p>Alowidat Geschenkgutscheine benötigt WooCommerce.</p></div>';
            }
        } );
        return;
    }

    require_once ALW_GC_DIR . 'includes/class-alw-gift-cards.php';
    require_once ALW_GC_DIR . 'includes/class-alw-gift-account.php';
    require_once ALW_GC_DIR . 'includes/class-alw-gift-admin.php';
    ALW_Gift_Cards::init();
    ALW_Gift_Account::init();
    if ( is_admin() ) {
        ALW_Gift_Admin::init();
    }
}, 20 );