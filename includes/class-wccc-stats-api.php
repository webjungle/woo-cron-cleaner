<?php
// Woo Cron Cleaner – REST-API für Dashboard-Statistiken
defined( 'ABSPATH' ) || exit;

class WCCC_Stats_API {

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register' ] );
    }

    public static function register() {
        register_rest_route(
            'wccc/v1',
            '/stats',
            [
                'methods'  => 'GET',
                'callback' => [ __CLASS__, 'data' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );
    }

    public static function data( WP_REST_Request $req ) {
        $from = $req->get_param( 'from' ) ?: date( 'Y-m-d', strtotime( '-30 days' ) );
        $to   = $req->get_param( 'to' )   ?: date( 'Y-m-d' );

        // Log-Daten holen
        $log    = get_option( 'wccc_cleanup_log', [] );
        $errors = get_option( 'wccc_error_log',  [] );

        // Grund-Aggregationen
        $perDay = $failedDay = [];
        $total = $failedTotal = 0;

        foreach ( $log as $row ) {
            $ts = strtotime( $row['time'] );
            if ( $ts < strtotime( "$from 00:00:00" ) || $ts > strtotime( "$to 23:59:59" ) ) {
                continue;
            }
            $d             = date( 'Y-m-d', $ts );
            $perDay[$d]    = ( $perDay[$d] ?? 0 ) + $row['count'];
            $total        += $row['count'];
        }

        foreach ( $errors as $e ) {
            $ts = strtotime( $e['time'] );
            if ( $ts < strtotime( "$from 00:00:00" ) || $ts > strtotime( "$to 23:59:59" ) ) {
                continue;
            }
            $d                 = date( 'Y-m-d', $ts );
            $failedDay[$d]     = ( $failedDay[$d] ?? 0 ) + 1;
            $failedTotal++;
        }

        // Fehler nach Plugin
        $byPlugin = [];
        foreach ( $errors as $e ) {
            $ts = strtotime( $e['time'] );
            if ( $ts < strtotime( "$from 00:00:00" ) || $ts > strtotime( "$to 23:59:59" ) ) {
                continue;
            }
            $byPlugin[ $e['plugin'] ] = ( $byPlugin[ $e['plugin'] ] ?? 0 ) + 1;
        }
        arsort( $byPlugin );
        $byPlugin = array_slice( $byPlugin, 0, 5, true );

        // Heatmap-Matrix
        $heat = [];
        foreach ( $errors as $e ) {
            $ts = strtotime( $e['time'] );
            if ( $ts < strtotime( "$from 00:00:00" ) || $ts > strtotime( "$to 23:59:59" ) ) {
                continue;
            }
            $w = (int) date( 'w', $ts );      // 0-6
            $h = (int) date( 'G', $ts );      // 0-23
            $heat[$w][$h] = ( $heat[$w][$h] ?? 0 ) + 1;
        }

        $successRate  = $total ? round( ( $total - $failedTotal ) / $total * 100 ) : 100;
        $secondsSaved = round( $total * 0.3, 1 );   // 0,3 s je Cron

        return rest_ensure_response( [
            'labels'      => array_keys( $perDay ),
            'total'       => array_values( $perDay ),
            'failed'      => array_values( array_intersect_key( $failedDay, $perDay ) ),
            'successRate' => $successRate,
            'totals'      => [ 'complete' => $total - $failedTotal, 'failed' => $failedTotal ],
            'plugins'     => $byPlugin,
            'heat'        => $heat,
            'savedSec'    => $secondsSaved,
        ] );
    }
}
WCCC_Stats_API::init();
