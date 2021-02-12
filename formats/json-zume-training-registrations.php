<?php

/**
 * LOAD DATA TYPE FORMAT
 */
if ( defined( 'ABSPATH' ) ) { // confirm wp is loaded
    class Zume_Training_JSON_Registrations
    {

        public $token = 'json_zume_training';
        public $label = 'JSON (Zume)';

        public function format($format)
        {
            /* Build base template of a format*/
            $format[$this->token] = get_dt_metrics_export_base_format();

            /* Add key and label for format */
            $format[$this->token]['key'] = $this->token;
            $format[$this->token]['label'] = $this->label;

            // remove raw
            $format[$this->token]['locations'] = [
                'all' => [
                    'admin2' => 'All',
                ],
            ];

            $format[$this->token]['types'] = [
                'registrations' => [
                    'by_day' => [
                        'key' => 'by_day',
                        'label' => 'Registrations by day'
                    ],
                ],
            ];
            return $format;
        }

        public function format_class($classes)
        {
            $classes[$this->token] = __CLASS__;
            return $classes;
        }

        public function create($response)
        {

            if (!isset($response['type']['registrations'], $response['configuration'], $response['destination'])) {
                return false;
            }

            $args = [
                'timestamp' => current_time('Y-m-d H:i:s'),
                'columns' => [],
                'rows' => [],
                'export' => $response,
                'link' => '',
                'key' => '',
            ];

            /**
             * Create results according to selected type
             */
            if ('by_day' === $response['type']['registrations']) {
                $args['rows'] = $this->query_by_day();
                $args['columns'] = array_keys($args['rows'][0]);
            }

            // kill if no results
            if (empty($args['rows'])) {
                echo '<div class="notice notice-warning is-dismissible">
                     <p>No results found for this configuration. Likely, there are no records for the countries you specified. Could not generate csv file.</p>
                 </div>';
                return $response['configuration'] ?? 0;
            }

            // destination
            $one_time_key = hash('sha256', get_current_user_id() . time() . dt_get_site_id() . rand(0, 999));
            $postid = $response['configuration'];
            switch ($response['destination']) {
                case 'expiring48':
                    $args['link'] = esc_url(plugin_dir_url(__FILE__)) . esc_url(basename(__FILE__)) . '?expiring48=' . esc_attr($one_time_key);
                    $args['key'] = $one_time_key;
                    set_transient('metrics_exports_' . $one_time_key, $args, 60 . 60 . 48);
                    echo '<div class="notice notice-warning is-dismissible">
                             <p>
                                 Link expiring in 48 hours:<br>
                                 <a href="' . esc_url(plugin_dir_url(__FILE__)) . esc_url(basename(__FILE__)) . '?expiring48=' . esc_attr($one_time_key) . '"
                                 target="_blank">' . esc_url(plugin_dir_url(__FILE__)) . esc_url(basename(__FILE__)) . '?expiring48=' . esc_attr($one_time_key) . '
                                 </a>
                             </p>
                         </div>';
                    break;
                case 'expiring360':
                    $args['link'] = esc_url(plugin_dir_url(__FILE__)) . esc_url(basename(__FILE__)) . '?expiring360=' . esc_attr($one_time_key);
                    $args['key'] = $one_time_key;
                    set_transient('metrics_exports_' . $one_time_key, $args, 60 . 60 . 360);
                    echo '<div class="notice notice-warning is-dismissible">
                             <p>
                                 Link expiring in 15 days:<br>
                                 <a href="' . esc_url(plugin_dir_url(__FILE__)) . esc_url(basename(__FILE__)) . '?expiring360=' . esc_attr($one_time_key) . '"
                                 target="_blank">' . esc_url(plugin_dir_url(__FILE__)) . esc_url(basename(__FILE__)) . '?expiring360=' . esc_attr($one_time_key) . '
                                 </a>
                             </p>
                         </div>';
                    break;
                case 'download':
                    $args['link'] = esc_url(plugin_dir_url(__FILE__)) . esc_url(basename(__FILE__)) . '?download=' . esc_attr($one_time_key);
                    $args['key'] = $one_time_key;
                    update_post_meta($postid, 'download_' . $one_time_key, $args);
                    echo '<div class="notice notice-warning is-dismissible">
                             <p>
                                 One time download link:<br>
                                 ' . esc_url(plugin_dir_url(__FILE__)) . esc_url(basename(__FILE__)) . '?download=' . esc_attr($one_time_key) . '
                             </p>
                         </div>';
                    break;
                case 'permanent':
                    $args['link'] = esc_url(plugin_dir_url(__FILE__)) . esc_url(basename(__FILE__)) . '?permanent=' . esc_attr($one_time_key);
                    $args['key'] = $one_time_key;
                    update_post_meta($postid, 'permanent_' . $one_time_key, $args);
                    echo '<div class="notice notice-warning is-dismissible">
                             <p>
                                 Permanent link (must be deleted manually):<br>
                                 <a href="' . esc_url(plugin_dir_url(__FILE__)) . esc_url(basename(__FILE__)) . '?permanent=' . esc_attr($one_time_key) . '"
                                 target="_blank">' . esc_url(plugin_dir_url(__FILE__)) . esc_url(basename(__FILE__)) . '?permanent=' . esc_attr($one_time_key) . '
                                 </a>
                             </p>
                         </div>';
                    break;
            }

            // return configuration selection from before export
            return $response['configuration'] ?? 0; // return int config id, so ui reloads on same config
        }

        public function update($key, array $args)
        {
            if (empty($key)) {
                return false;
            }
            if (!isset($args['timestamp'], $args['link'], $args['export'], $args['export']['configuration'], $args['export']['destination'], $args['export']['type']['registrations'])) {
                return false;
            }

            $args['timestamp'] = current_time('Y-m-d H:i:s');

            /**
             * Create results according to selected type
             */
            if ('by_day' === $args['export']['type']['registrations']) {
                $args['rows'] = $this->query_by_day();
                $args['columns'] = array_keys($args['rows'][0]);
            }

            // update destination
            $postid = $args['export']['configuration'];
            switch ($args['export']['destination']) {
                case 'expiring48':
                    set_transient('metrics_exports_' . $key, $args, 60 . 60 . 48);
                    break;
                case 'expiring360':
                    set_transient('metrics_exports_' . $key, $args, 60 . 60 . 360);
                    break;
                case 'download':
                    update_post_meta($postid, 'download_' . $key, $args);
                    break;
                case 'permanent':
                    update_post_meta($postid, 'permanent_' . $key, $args);
                    break;
            }

            return $args;
        }

        public function query_by_day()
        {
            global $wpdb;
            $results = $wpdb->get_results("
                    SELECT DATE_FORMAT(user_registered,'%Y-%m-%d') as date, COUNT(ID) as count FROM $wpdb->users GROUP BY DATE_FORMAT(user_registered,'%Y-%m-%d');
                ", ARRAY_A);
            return $results;
        }

        private static $_instance = null;

        public static function instance()
        {
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        public function __construct()
        {
            add_filter('dt_metrics_export_format', [$this, 'format'], 10, 1);
            add_filter('dt_metrics_export_register_format_class', [$this, 'format_class'], 10, 1);
        } // End __construct()
    }

    Zume_Training_JSON_Registrations::instance();
}


/**
 * CREATE JSON FILE
 */
if ( !defined( 'ABSPATH' )) {

    // @codingStandardsIgnoreLine
    require($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'); // loads the wp framework when called

    dt_write_log('post wp-load');
    dt_write_log(defined( 'ABSPATH' ));

    if ( isset( $_GET['expiring48'] ) || isset( $_GET['expiring360'] ) ) {

        $token = isset( $_GET['expiring48'] ) ? sanitize_text_field( wp_unslash( $_GET['expiring48'] ) ) : sanitize_text_field( wp_unslash( $_GET['expiring360'] ) );
        $results = get_transient( 'metrics_exports_' . $token );

        header( 'Content-type: application/json' );

        if (empty( $results )) {
            echo json_encode( [ 'status' => 'FAIL' ] );
            return;
        }

        echo json_encode( $results );
        exit;
    }
    else if ( isset( $_GET['download'] ) ) {
        global $wpdb;

        $token = sanitize_text_field( wp_unslash( $_GET['download'] ) );

        $raw = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key = %s LIMIT 1", 'download_' . $token ), ARRAY_A );

        if ( empty( $raw ) ) {
            echo 'No link found';
            return;
        }
        $results = maybe_unserialize( $raw['meta_value'] );

        delete_post_meta( $raw['post_id'], $raw['meta_key'] ); // delete after collection

        header( 'Content-type: application/json' );

        if (empty( $results )) {
            echo json_encode( [ 'status' => 'FAIL' ] );
            return;
        }

        echo json_encode( $results );
        exit;
    }
    else if ( isset( $_GET['permanent'] ) ) {
        global $wpdb;

        // test if key exists
        $token = sanitize_text_field( wp_unslash( $_GET['permanent'] ) );
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = %s", 'permanent_' . $token ) );
        if ( empty( $raw ) ) {
            echo 'No link found';
            return;
        }

        $query = $wpdb->get_results("
                   SELECT DATE_FORMAT(user_registered,'%Y-%m-%d') as date, COUNT(ID) as count FROM $wpdb->users GROUP BY DATE_FORMAT(user_registered,'%Y-%m-%d');
                ", ARRAY_A);

        $results =[];
        $results['timestamp'] = current_time('Y-m-d H:i:s');
        $results['rows'] = $query;
        $results['columns'] = array_keys($results['rows'][0]);

        header( 'Content-type: application/json' );

        if (empty( $results )) {
            echo json_encode( [ 'status' => 'FAIL' ] );
            return;
        }

        echo json_encode( $results );
        exit;
    }
    else {
        echo 'parameters not set correctly';
        return;
    }
}
