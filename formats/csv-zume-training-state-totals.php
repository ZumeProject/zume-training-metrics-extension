<?php


if ( defined( 'ABSPATH' ) ) { // confirm wp is loaded

    class Zume_Training_CSV_State_Trainings
    {
        public $token = 'csv_zume_state_trainings';
        public $label = 'CSV (Zume State Level Trainings)';

        /**
         * The format function builds the template of the format. From this format template, multiple configurations can
         * be created and stored.
         *
         * @note This function does not need modified for the simplest use of the export template.
         *
         * @link https://github.com/DiscipleTools/disciple-tools-metrics-export/master/includes/format-utilities.php:27 get_dt_metrics_export_base_format():
         *
         * @param $format
         * @return mixed
         */
        public function format( $format) {
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
            
            $format[$this->token]['destinations'] = [
                'permanent' => [
                    'value' => 'permanent',
                    'label' => 'Permanent Link'
                ]
            ];

            $format[$this->token]['types'] = [];

            return $format;
        }

        /**
         * This function is the create link function called by the tab "creat links".
         *
         * @note This function does not need modified for the simplest use of the export template.
         *
         * @param $response
         * @return false|int|mixed
         */
        public function create( $response) {
            if ( !isset( $response['configuration'], $response['destination'] ) ) {
                return false;
            }

            $args = [
                'timestamp' => current_time( 'Y-m-d H:i:s' ),
                'columns' => [],
                'rows' => [],
                'export' => $response,
                'link' => '',
                'key' => '',
            ];

            /**
             * Create results according to selected type
             */
            $args['rows'] = []; // deliberately empty because permanent will query below.
            $args['columns'] = array_keys( $args['rows'][0] );


            // destination
            $one_time_key = hash( 'sha256', get_current_user_id() . time() . dt_get_site_id() . rand( 0, 999 ) );
            $postid = $response['configuration'];
            switch ($response['destination']) {
                case 'permanent':
                    $args['link'] = esc_url( plugin_dir_url( __FILE__ ) ) . esc_url( basename( __FILE__ ) ) . '?permanent=' . esc_attr( $one_time_key );
                    $args['key'] = $one_time_key;
                    update_post_meta( $postid, 'permanent_' . $one_time_key, $args );
                    echo '<div class="notice notice-warning is-dismissible">
                             <p>
                                 Permanent link (must be deleted manually):<br>
                                 <a href="' . esc_url( plugin_dir_url( __FILE__ ) ) . esc_url( basename( __FILE__ ) ) . '?permanent=' . esc_attr( $one_time_key ) . '"
                                 target="_blank">' . esc_url( plugin_dir_url( __FILE__ ) ) . esc_url( basename( __FILE__ ) ) . '?permanent=' . esc_attr( $one_time_key ) . '
                                 </a>
                             </p>
                         </div>';
                    break;
            }

            // return configuration selection from before export
            return $response['configuration'] ?? 0; // return int config id, so ui reloads on same config
        }

        /**
         * This function is mainly used by the permanent link, which rebuilds each time requested.
         *
         * @note This function does not need modified for the simplest use of the export template.
         *
         * @param $key
         * @param array $args
         * @return array|false
         */
        public function update( $key, array $args) {
            if ( !isset( $args['timestamp'], $args['link'], $args['export'], $args['export']['configuration'], $args['export']['destination'] ) ) {
                return false;
            }

            // timestamp
            $args['timestamp'] = current_time( 'Y-m-d H:i:s' );

            // Create results according to selected type
            $args['rows'] = $this->query();
            $args['columns'] = array_keys( $args['rows'][0] );

            // update destination
            $postid = $args['export']['configuration'];
            switch ($args['export']['destination']) {
                case 'permanent':
                    update_post_meta( $postid, 'permanent_' . $key, $args );
                    break;
            }

            return $args;
        }

        /**
         *
         * @return array|object|null
         */
        public function query() {
            global $wpdb;
            $results = $wpdb->get_results("
                  SELECT DATE_FORMAT(user_registered,'%Y-%m-%d') as date, COUNT(ID) as Registrations FROM $wpdb->users GROUP BY DATE_FORMAT(user_registered,'%Y-%m-%d');
                ", ARRAY_A);
            return $results;
        }

        /**
         * This function builds the class used by the build tab.
         *
         * @note This function does not need modified for the simplest use of the export template.
         *
         * @param $classes
         * @return mixed
         */
        public function format_class( $classes) {
            $classes[$this->token] = __CLASS__;
            return $classes;
        }

        /**
         * Singleton and Construct Functions
         * @note This function does not need modified
         * @var null
         */
        private static $_instance = null;
        public static function instance() {
            if (is_null( self::$_instance )) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }
        public function __construct() {
            add_filter( 'dt_metrics_export_format', [ $this, 'format' ], 10, 1 );
            add_filter( 'dt_metrics_export_register_format_class', [ $this, 'format_class' ], 10, 1 );
        }
    }
    Zume_Training_CSV_State_Trainings::instance();
}


/**
 * CREATE CSV FILE
 * This section only loads if accessed directly.
 * These 4 sections support expiring48, expiring360, download, permanent links
 *
 * @note This function does not need modified for the simplest use of the export template.
 */
if ( !defined( 'ABSPATH' )) {

    // @codingStandardsIgnoreLine
    require($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'); // loads the wp framework when called

    /**
     * Lookup from available transients for matching token given in the url
     */
    if (isset( $_GET['permanent'] )) {
        global $wpdb;

        // test if key exists
        $token = sanitize_text_field( wp_unslash( $_GET['permanent'] ) );
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = %s", 'permanent_' . $token ) );
        if (empty( $raw )) {
            echo 'No link found';
            return;
        }

        $query = $wpdb->get_results("
                   SELECT grid_id, CONCAT(latitude, ',', longitude) as latlng, population, 0 as count FROM $wpdb->dt_location_grid WHERE level = 1;
                ", ARRAY_A);

        $locations = [];
        foreach( $query as $row ) {
            $locations[$row['grid_id']] = $row;
        }

        $totals = $wpdb->get_results("SELECT lg.admin1_grid_id as grid_id, COUNT(lg.admin1_grid_id) as count
            FROM $wpdb->dt_location_grid_meta as lgm
            JOIN $wpdb->dt_location_grid as lg ON lgm.grid_id=lg.grid_id
            WHERE lgm.post_type = 'trainings' AND lg.admin1_grid_id IS NOT NULL
            GROUP BY lg.admin1_grid_id;", ARRAY_A );
        $total_list = [];
        foreach( $totals as $total ){
            if ( isset( $locations[$total['grid_id']] ) ) {
                $locations[$total['grid_id']]['count'] = $total['count'];
            }
        }


        // load export header
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=dt-csv-' . strtotime( current_time('Y-m-d H:i:s')) . '.csv' );

        // build csv
        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, ['grid_id', 'latlng', 'population', 'count'] );
        foreach ($locations as $row) {
            fputcsv( $output, $row );
        }
        fpassthru( $output );
    } else {
        echo 'parameters not set correctly';
        return;
    }
}
