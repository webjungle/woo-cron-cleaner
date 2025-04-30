<?php
/**
 * Plugin Name: Woo Cron Cleaner
 * Description: Automatische Bereinigung von WooCommerce Cron-Jobs mit Premium-Funktionen
 * Version: 1.0
 * Author: webjungle.ch
 * Requires PHP: 7.4
 * Requires at least: WordPress 5.6
 * Requires WooCommerce: 5.0
 */

defined( 'ABSPATH' ) || exit;

// Freemius initialization...
if ( ! function_exists( 'wccc_fs' ) ) {
    function wccc_fs() {
        global $wccc_fs;
        if ( ! isset( $wccc_fs ) ) {
            require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
            $wccc_fs = fs_dynamic_init( array(
                // config...
            ) );
        }
        return $wccc_fs;
    }
    wccc_fs();
    do_action( 'wccc_fs_loaded' );
}

// Core class
require_once __DIR__ . '/includes/class-wccc-cron-cleaner.php';

// Advanced Stats page
require_once __DIR__ . '/includes/wccc-advanced-stats.php';
require_once __DIR__ . '/includes/class-wccc-stats-api.php';

// Bootstrap
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        new WCCC_Cron_Cleaner_Pro();
    }
} );
