<?php
defined('ABSPATH') || exit;

class WCCC_Cron_Cleaner_Pro {

    private $options;
    private $defaults = array(
        'clean_completed' => true,
        'clean_failed' => true,
        'days_to_keep' => 1,
        'interval' => 'daily'
    );

    public function __construct() {
        $this->options = wp_parse_args(get_option('wccc_settings', array()), $this->defaults);
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_manual_cleanup'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    public function add_admin_page() {
        add_menu_page(
            'WooCommerce Cron Cleaner Pro',
            'WC Cron Cleaner',
            'manage_options',
            'wccc-settings',
            array($this, 'render_admin_page'),
            'dashicons-backup',
            80
        );
        add_submenu_page(
            'wccc-settings',
            'Geplante Cronjobs',
            'Geplante Cronjobs',
            'manage_options',
            'wccc-cron-overview',
            array($this, 'render_cron_page')
        );
        add_submenu_page(
            'wccc-settings',
            'Erweiterte Statistik',
            'Erweiterte Statistik',
            'manage_options',
            'wccc-advanced-stats',
            [$this, 'render_advanced_stats_page']
        );
    }

    public function enqueue_admin_styles( $hook ) {
    
        if ( strpos( $hook, 'wccc-settings' ) !== false ||
             strpos( $hook, 'wccc-cron-overview' ) !== false ) {
    
            // → Pfad für filemtime()
            $base = plugin_dir_path( dirname( __FILE__ ) );   // …/woo-cron-cleaner/
    
            // → URL fürs Frontend   (plugin_dir_url() nutzt dasselbe Root)
            $base_url = plugin_dir_url( dirname( __FILE__ ) );
    
            wp_enqueue_script(
                'wccc-dashboard',
                plugins_url( 'assets/js/stats-dashboard.js', dirname( __DIR__ ) ),
                [ 'chartjs', 'chartjs-matrix' ],  // beide als deps
                '1.0',
                true
            );
            // CDN für Matrix-Plugin
            wp_enqueue_script( 'chartjs-matrix',
                'https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@3.0.0', [], null, true );

            wp_enqueue_style(
                'wccc-admin-css',
                $base_url . 'assets/css/admin.css',           // ✅ URL richtig
                [],
                filemtime( $base . 'assets/css/admin.css' )   // ✅ Pfad richtig
            );
    
            wp_enqueue_script(
                'wccc-darkmode-toggle',
                $base_url . 'assets/css/darkmode-toggle.js',  // ✅ URL richtig
                [],
                filemtime( $base . 'assets/css/darkmode-toggle.js' ),
                true
            );
        }
        // Chart-Libs & Dashboard-JS nur auf Statistik-Seite laden
        if ( strpos( $hook, 'wccc-advanced-stats' ) !== false ) {
            wp_enqueue_script( 'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js', [], null, true );
            wp_enqueue_script( 'chartjs-matrix',
                'https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@3', [], null, true );
            wp_enqueue_script( 'wccc-dashboard',
                plugins_url( 'assets/js/stats-dashboard.js', dirname( __DIR__ ) ),
                [ 'chartjs', 'chartjs-matrix' ], '1.0', true );
        }
    }

    
    public function render_cron_page() {
        $cron = _get_cron_array();
        $paused = get_option('wccc_paused_hooks', []);
        $now = time();
        $plugins = [];

        foreach ($cron as $timestamp => $hooks) {
            foreach ($hooks as $hook => $entries) {
                $plugin = $this->detect_plugin_from_hook($hook);
                $plugins[$plugin] = $plugin;
            }
        }

        ksort($plugins);
        $selected = $_GET['wccc_plugin'] ?? '';

        echo '<div class="wrap wccc-admin">';
        echo '<div class="wccc-dark-toggle">';
        echo '<button type="button" id="wccc-toggle-darkmode"><span>🌙 Dark Mode</span></button>';
        echo '</div>';
        echo '<h1>Geplante Cronjobs</h1>';
        echo '<form method="get" class="wccc-plugin-filter">';
        echo '<input type="hidden" name="page" value="wccc-cron-overview">';
        echo '<label for="wccc_plugin">Filtern nach Plugin:</label> ';
        echo '<select name="wccc_plugin" id="wccc_plugin" onchange="this.form.submit()">';
        echo '<option value="">Alle Plugins</option>';
        foreach ($plugins as $key => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($key), selected($selected, $key, false), esc_html($label));
        }
        echo '</select>';
        echo '</form>';

        echo '<table class="wccc-cron-table">';
        echo '<thead><tr><th>Hook</th><th>Ausführung</th><th>Plugin</th><th>Wiederholung</th><th>Status</th></tr></thead><tbody>';

        foreach ($cron as $timestamp => $hooks) {
            foreach ($hooks as $hook => $entries) {
                $plugin = $this->detect_plugin_from_hook($hook);
                if (!empty($selected) && $plugin !== $selected) continue;

                $time = $timestamp;
                $missed = $time < $now;
                $is_paused = in_array($hook, $paused);
                $intervals = array_column($entries, 'interval');

                $status = $is_paused
                    ? '<span class="wccc-paused-label">⏸️ Pausiert</span>'
                    : ($missed ? '<span class="wccc-badge-overdue">⚠️ Überfällig</span>' : '<span class="wccc-badge-active">🟢 Aktiv</span>');

                echo '<tr>';
                echo '<td><code>' . esc_html($hook) . '</code></td>';
                echo '<td>' . date_i18n('d.m.Y H:i', $time) . '</td>';
                echo '<td>' . esc_html($plugin) . '</td>';
                echo '<td>' . esc_html(implode(', ', $intervals)) . '</td>';
                echo '<td>' . $status . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table></div>';
    }
    
    public function render_advanced_stats_page() {
        $log   = get_option('wccc_cleanup_log', []);
        $total = array_sum( array_column( $log, 'count' ) );
        $days  = [];
    
        // Zugriffe pro Tag der letzten 30 Tage errechnen
        foreach ( $log as $entry ) {
            $day = date_i18n( 'd.m.Y', strtotime( $entry['time'] ) );
            $days[ $day ] = ( $days[ $day ] ?? 0 ) + $entry['count'];
        }
        krsort( $days );                     // Jüngste zuerst
        $days = array_slice( $days, 0, 30 ); // max 30 Einträge
    
        echo '<div class="wrap wccc-admin">';
        echo '<h1>Erweiterte Statistik</h1>';
    
        echo '<div class="wccc-section"><h2>Gesamt</h2>';
        echo '<p><strong>Summe gelöschter Cronjobs:</strong> ' . number_format_i18n( $total ) . '</p>';
        echo '</div>';
    
        echo '<div class="wccc-section"><h2>Letzte 30 Tage</h2>';
        echo '<table class="wccc-cron-table">';
        echo '<thead><tr><th>Datum</th><th>Gelöscht</th></tr></thead><tbody>';
        foreach ( $days as $day => $cnt ) {
            printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $day ), number_format_i18n( $cnt ) );
        }
        echo '</tbody></table></div>';
    
        /*** OPTIONAL – Fehlgeschlagene Jobs nach Plugin ***/
        $errors = get_option( 'wccc_error_log', [] );
        if ( ! empty( $errors ) ) {
            $by_plugin = [];
            foreach ( $errors as $e ) {
                $by_plugin[ $e['plugin'] ] = ( $by_plugin[ $e['plugin'] ] ?? 0 ) + 1;
            }
            arsort( $by_plugin );
    
            echo '<div class="wccc-section"><h2>Fehlgeschlagene Jobs (Gesamt)</h2>';
            echo '<table class="wccc-cron-table"><thead><tr><th>Plugin</th><th>Anzahl</th></tr></thead><tbody>';
            foreach ( $by_plugin as $plg => $cnt ) {
                printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $plg ), $cnt );
            }
            echo '</tbody></table></div>';
        }
    
        echo '</div>';
    }

    private function detect_plugin_from_hook($hook) {
        if (strpos($hook, 'woocommerce') === 0) return 'WooCommerce';

        $plugins = array(
            'yoast' => 'Yoast SEO',
            'elementor' => 'Elementor',
            'gravityforms' => 'Gravity Forms',
            'wpml' => 'WPML',
            'rankmath' => 'Rank Math',
            'mailpoet' => 'MailPoet'
        );

        foreach ($plugins as $prefix => $name) {
            if (strpos($hook, $prefix) !== false) {
                return $name;
            }
        }

        return 'Unbekannt';
    }

    public function register_settings() {
        register_setting('wccc_settings_group', 'wccc_settings');
        add_settings_section('wccc_main_section', 'Bereinigungs-Einstellungen', function () {
            echo '<p>Konfigurieren Sie die automatische Bereinigung der WooCommerce Cron-Jobs</p>';
        }, 'wccc-settings');

        add_settings_field('wccc_clean_completed', 'Abgeschlossene Jobs löschen', function () {
            $val = get_option('wccc_settings')['clean_completed'] ?? true;
            echo '<input type="checkbox" name="wccc_settings[clean_completed]" value="1" ' . checked($val, true, false) . '>';
        }, 'wccc-settings', 'wccc_main_section');

        add_settings_field('wccc_clean_failed', 'Fehlgeschlagene Jobs löschen', function () {
            $val = get_option('wccc_settings')['clean_failed'] ?? true;
            echo '<input type="checkbox" name="wccc_settings[clean_failed]" value="1" ' . checked($val, true, false) . '>';
        }, 'wccc-settings', 'wccc_main_section');

        add_settings_field('wccc_days_to_keep', 'Aufbewahrungsdauer (Tage)', function () {
            $val = get_option('wccc_settings')['days_to_keep'] ?? 1;
            echo '<input type="number" name="wccc_settings[days_to_keep]" value="' . esc_attr($val) . '" class="small-text" min="0"> Tage';
        }, 'wccc-settings', 'wccc_main_section');
    }


    public function handle_manual_cleanup() {
        if (isset($_POST['wccc_manual_cleanup']) && isset($_POST['wccc_nonce']) &&
            wp_verify_nonce($_POST['wccc_nonce'], 'wccc_manual_cleanup_action')) {
            if (!current_user_can('manage_options')) {
                wp_die('Unzureichende Berechtigungen');
            }

            $this->clean_cron_jobs(true);
            wp_redirect(add_query_arg('wccc_cleanup_done', '1'));
            exit;
        }
    }


    public function render_admin_page() {
        echo '<div class="wrap wccc-admin">';
        echo '<div class="wccc-dark-toggle" style="margin-bottom:20px;display:flex;gap:12px;align-items:center;justify-content:flex-end">';
        echo '  <button type="button" id="wccc-toggle-darkmode"><span>🌙 Dark Mode</span></button>';
        echo '</div>';
        echo '<h1>Woo Cron Cleaner</h1>';

        if (isset($_GET['wccc_cleanup_done'])) {
            echo '<div class="notice notice-success"><p>Bereinigung durchgeführt!</p></div>';
        }

        echo '<div class="wccc-section">';
        echo '<h2>Sofortige Bereinigung</h2>';
        echo '<form method="post">';
        echo '<input type="hidden" name="wccc_manual_cleanup" value="1">';
        wp_nonce_field('wccc_manual_cleanup_action', 'wccc_nonce');
        echo '<p>Starte die manuelle Bereinigung sofort:</p>';
        submit_button('Jetzt bereinigen', 'primary', 'wccc_submit_cleanup');
        echo '</form></div>';

        echo '<div class="wccc-section">';
        echo '<h2>Einstellungen</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields('wccc_settings_group');
        do_settings_sections('wccc-settings');
        submit_button('Einstellungen speichern');
        echo '</form></div>';
        echo '</div>';
    }


    public function clean_cron_jobs($manual = false) {
        global $wpdb;

        $statuses = [];
        $options = get_option('wccc_settings', []);
        if (!empty($options['clean_completed'])) $statuses[] = 'complete';
        if (!empty($options['clean_failed'])) $statuses[] = 'failed';

        if (empty($statuses)) return false;

        $days = max(0, (int)($options['days_to_keep'] ?? 1));
        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $query = $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}actionscheduler_actions 
             WHERE status IN ($placeholders) 
             AND scheduled_date_gmt < %s",
            array_merge($statuses, array($date))
        );

        if (in_array('failed', $statuses)) {
            $this->log_failed_actions($date);
        }
        $result = $wpdb->query($query);
        $this->log_cleanup($result);
        return $result;
    }


    public function schedule_cleanup() {
        if (!wp_next_scheduled('wccc_cleanup_event')) {
            $interval = get_option('wccc_settings')['interval'] ?? 'daily';
            wp_schedule_event(time(), $interval, 'wccc_cleanup_event');
        }
    }


    public function display_stats() {
        $log = get_option('wccc_cleanup_log', []);
        if (empty($log)) {
            echo '<p>Noch keine Bereinigungen durchgeführt.</p>';
            return;
        }

        $total = array_sum(array_column($log, 'count'));
        $last = end($log);

        echo '<div class="wccc-stats-container">';
        echo '<div class="wccc-stat-box"><h3>Insgesamt bereinigt</h3><p>' . number_format_i18n($total) . '</p></div>';
        echo '<div class="wccc-stat-box"><h3>Letzte Bereinigung</h3><p>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last['time'])) . '</p></div>';
        echo '<div class="wccc-stat-box"><h3>Zuletzt gelöscht</h3><p>' . number_format_i18n($last['count']) . '</p></div>';
        echo '</div>';
    }

    private function log_cleanup($count) {
        $log = get_option('wccc_cleanup_log', []);
        $log[] = array(
            'time' => current_time('mysql'),
            'count' => $count
        );
        update_option('wccc_cleanup_log', array_slice($log, -50));
    }

    public function display_error_logs() {
        $error_logs = get_option('wccc_error_log', []);
        if (empty($error_logs)) {
            echo '<p>Keine Fehler in den letzten Bereinigungen gefunden.</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Zeit</th><th>Plugin</th><th>Hook</th><th>Fehlermeldung</th></tr></thead><tbody>';
        foreach ($error_logs as $log) {
            echo '<tr>';
            echo '<td>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['time'])) . '</td>';
            echo '<td>' . esc_html($log['plugin']) . '</td>';
            echo '<td><code>' . esc_html($log['hook']) . '</code></td>';
            echo '<td>' . esc_html($log['message']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private function log_failed_actions($date) {
        global $wpdb;
        $failed_actions = $wpdb->get_results($wpdb->prepare(
            "SELECT a.hook, a.args, l.message 
             FROM {$wpdb->prefix}actionscheduler_actions a
             LEFT JOIN {$wpdb->prefix}actionscheduler_logs l ON a.action_id = l.action_id
             WHERE a.status = 'failed' AND a.scheduled_date_gmt < %s
             ORDER BY a.scheduled_date_gmt DESC LIMIT 100", $date
        ));

        if (!empty($failed_actions)) {
            $log = get_option('wccc_error_log', []);
            foreach ($failed_actions as $action) {
                $plugin = $this->detect_plugin_from_hook($action->hook);
                $log_entry = array(
                    'time' => current_time('mysql'),
                    'hook' => $action->hook,
                    'plugin' => $plugin,
                    'message' => $action->message,
                    'args' => maybe_unserialize($action->args)
                );
                array_unshift($log, $log_entry);
            }
            update_option('wccc_error_log', array_slice($log, 0, 100));
        }
    }

}