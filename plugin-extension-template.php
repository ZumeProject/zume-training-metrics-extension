<?php
/**
 * Plugin Name: Zúme Training - Metrics Extension
 * Plugin URI: https://github.com/ZumeProject/zume-training-metrics-extension
 * Description: Zúme Training - Metrics Extension provides data from the inside of the Training System to Data Studio.
 * Version:  0.1
 * Author URI: https://github.com/DiscipleTools
 * GitHub Plugin URI: https://github.com/ZumeProject/zume-training-metrics-extension
 * Requires at least: 4.7.0
 * (Requires 4.7+ because of the integration of the REST API at 4.7 and the security requirements of this milestone version.)
 * Tested up to: 5.6
 */

if ( is_admin() && isset( $_GET['page'] ) && 'dt_metrics_export' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // confirm this is the admin area and the metrics plugin

    add_action('dt_metrics_export_loaded', function () { // load after the metrics export is loaded
        $format_files = scandir( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'formats/' );
        if ( !empty( $format_files )) {
            foreach ($format_files as $file) {
                if (substr( $file, -4, '4' ) === '.php') {
                    require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'formats/' . $file );
                }
            }
        }
    });

}