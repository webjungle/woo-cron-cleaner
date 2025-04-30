<?php
/**
 * class-wccc-cron-cleaner.php
 *
 * Core functionality for Woo Cron Cleaner Pro.
 */

defined('ABSPATH') || exit;

class WCCC_Cron_Cleaner_Pro {
    private $options;
    private $defaults = array(
        'clean_completed' => true,
        'clean_failed'    => true,
        'days_to_keep'    => 1,
        'interval'        => 'daily',
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

        if (wccc_fs()->can_use_premium_code()) {
            add_action('init', array($this, 'schedule_cleanup'));
            add_action('wccc_cleanup_event', array($this, 'clean_cron_jobs'));
        }
    }

    public function add_admin_page() {
        // Haupt-Menü und Dashboard-Seite
        add_menu_page(
            __('Woo Cron Cleaner', 'wccc'),
            __('WC Cron Cleaner', 'wccc'),
            'manage_options',
            'wccc-settings',
            array($this, 'render_admin_page'),
            'dashicons-backup',
            80
        );
        // Dashboard als erstes Sub-Menü (zeigt die Hauptseite)
        add_submenu_page(
            'wccc-settings',
            __('Dashboard', 'wccc'),
            __('Dashboard', 'wccc'),
            'manage_options',
            'wccc-settings',
            array($this, 'render_admin_page')
        );
        // Cron-Übersicht
        add_submenu_page(
            'wccc-settings',
            __('Geplante Cronjobs', 'wccc'),
            __('Cron-Übersicht', 'wccc'),
            'manage_options',
            'wccc-cron-overview',
            array($this, 'render_cron_page')
        );
        // Erweiterte Statistik
        add_submenu_page(
            'wccc-settings',
            __('Erweiterte Statistik', 'wccc'),
            __('Erweiterte Statistik', 'wccc'),
            'manage_options',
            'wccc-advanced-stats',
            array($this, 'render_advanced_stats_page')
        );
    }

    public function render_admin_page() {
        ?>
        <div class="wrap wccc-admin">
            <h1><?php esc_html_e('Woo Cron Cleaner', 'wccc'); ?></h1>
            <?php if (isset($_GET['wccc_cleanup_done'])) : ?>
                <div class="notice notice-success">
                    <p><?php echo $this->get_cleanup_message(sanitize_text_field($_GET['wccc_cleanup_done'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="wccc-section">
                <h2><?php esc_html_e('Sofortige Bereinigung', 'wccc'); ?></h2>
                <form method="post">
                    <input type="hidden" name="wccc_manual_cleanup" value="1">
                    <?php wp_nonce_field('wccc_manual_cleanup_action', 'wccc_nonce'); ?>
                    <p><?php esc_html_e('Klicken Sie auf den Button, um eine sofortige Bereinigung durchzuführen:', 'wccc'); ?></p>
                    <?php submit_button(__('Jetzt bereinigen', 'wccc'), 'primary', 'wccc_submit_cleanup'); ?>
                </form>
            </div>

            <div class="wccc-section">
                <h2><?php esc_html_e('Einstellungen', 'wccc'); ?></h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wccc_settings_group');
                    do_settings_sections('wccc-settings');
                    submit_button(__('Einstellungen speichern', 'wccc'));
                    ?>
                </form>
            </div>

            <div class="wccc-section">
                <h2><?php esc_html_e('Statistik', 'wccc'); ?></h2>
                <?php $this->display_stats(); ?>
            </div>

            <div class="wccc-section">
                <h2><?php esc_html_e('Ausstehende Jobs', 'wccc'); ?></h2>
                <?php $this->display_pending_jobs(); ?>
            </div>

            <div class="wccc-section">
                <h2><?php esc_html_e('Fehler-Logs', 'wccc'); ?></h2>
                <?php $this->display_error_logs(); ?>
            </div>
        </div>
        <?php
    }

    public function render_cron_page() {
        $cron   = _get_cron_array();
        $paused = get_option('wccc_paused_hooks', array());
        $now    = time();

        echo '<div class="wrap wccc-admin">';
        echo '<h1>' . esc_html__('Geplante Cronjobs', 'wccc') . '</h1>';
        echo '<table class="wccc-cron-table"><thead><tr>';
        echo '<th>' . esc_html__('Hook', 'wccc') . '</th>';
        echo '<th>' . esc_html__('Nächste Ausführung', 'wccc') . '</th>';
        echo '<th>' . esc_html__('Plugin', 'wccc') . '</th>';
        echo '<th>' . esc_html__('Status', 'wccc') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($cron as $ts => $hooks) {
            foreach ($hooks as $hook => $entries) {
                $time      = $ts;
                $missed    = $time < $now;
                $is_paused = in_array($hook, $paused, true);
                $plugin    = $this->detect_plugin_from_hook($hook);
                if ($is_paused) {
                    $status = '<span class="wccc-paused-label">⏸️</span>';
                } elseif ($missed) {
                    $status = '<span class="wccc-badge-overdue">⚠️</span>';
                } else {
                    $status = '<span class="wccc-badge-active">🟢</span>';
                }
                echo '<tr>';
                echo '<td><code>' . esc_html($hook) . '</code></td>';
                echo '<td>' . esc_html(date_i18n('d.m.Y H:i', $time)) . '</td>';
                echo '<td>' . esc_html($plugin) . '</td>';
                echo '<td>' . $status . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table></div>';
    }

    public function render_advanced_stats_page() {
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-d', strtotime('-30 days'));
        $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : date('Y-m-d');
        ?>
        <div class="wrap wccc-admin">
            <h1><?php esc_html_e('Erweiterte Statistik', 'wccc'); ?></h1>
            <form method="get" style="margin-bottom:20px;">
                <input type="hidden" name="page" value="wccc-advanced-stats" />
                Von: <input type="date" name="from" value="<?php echo esc_attr($from); ?>" />
                Bis: <input type="date" name="to"   value="<?php echo esc_attr($to); ?>" />
                <button class="button"><?php esc_html_e('Anzeigen', 'wccc'); ?></button>
            </form>
            <div class="wccc-stat-grid">
                <div class="card"><h4><?php esc_html_e('Gesamt', 'wccc'); ?></h4><p id="wccc_kpi_total">--</p></div>
                <div class="card"><h4><?php esc_html_e('Success-Rate', 'wccc'); ?></h4><p id="wccc_kpi_success">--</p></div>
                <div class="card"><h4><?php esc_html_e('Gesparte Zeit', 'wccc'); ?></h4><p id="wccc_kpi_saved">--</p></div>
            </div>
            <canvas id="wccc_chart_total" height="120"></canvas>
            <canvas id="wccc_chart_donut" width="160" height="160" style="max-width:280px;margin-top:20px"></canvas>
            <h3 style="margin-top:40px"><?php esc_html_e('Fehler nach Plugin', 'wccc'); ?></h3>
            <canvas id="wccc_chart_plugins" height="140"></canvas>
            <h3 style="margin-top:40px"><?php esc_html_e('Heatmap Fehler (Wochentag × Stunde)', 'wccc'); ?></h3>
            <canvas id="wccc_chart_heat" height="240"></canvas>
        </div>
        <?php
    }

    public function handle_manual_cleanup() {
        if (isset($_POST['wccc_manual_cleanup'], $_POST['wccc_nonce'])
            && check_admin_referer('wccc_manual_cleanup_action', 'wccc_nonce')) {
            if (!current_user_can('manage_options')) {
                wp_die(__('Unzureichende Berechtigungen', 'wccc'));
            }
            $res = $this->clean_cron_jobs(true);
            if ($res !== false) {
                $arg = is_array($res) ? base64_encode(wp_json_encode($res)) : '1';
                wp_redirect(add_query_arg('wccc_cleanup_done', $arg));
                exit;
            }
        }
    }

    public function register_settings() {
        register_setting('wccc_settings_group', 'wccc_settings', array($this, 'sanitize_options'));
        add_settings_section('wccc_main_section', __('Bereinigungs-Einstellungen', 'wccc'), array($this, 'render_section_info'), 'wccc-settings');
        add_settings_field('wccc_clean_completed', __('Abgeschlossene Jobs löschen', 'wccc'), array($this, 'render_completed_field'), 'wccc-settings', 'wccc_main_section');
        add_settings_field('wccc_clean_failed', __('Fehlgeschlagene Jobs löschen', 'wccc'), array($this, 'render_failed_field'), 'wccc-settings', 'wccc_main_section');
        add_settings_field('wccc_days_to_keep', __('Aufbewahrungsdauer (in Tagen)', 'wccc'), array($this, 'render_days_field'), 'wccc-settings', 'wccc_main_section');
        if (wccc_fs()->can_use_premium_code()) {
            add_settings_field('wccc_interval', __('Bereinigungsintervall', 'wccc'), array($this, 'render_interval_field'), 'wccc-settings', 'wccc_main_section');
        }
    }

    /** Section description callback */
    public function render_section_info() {
        echo '<p>' . esc_html__('Konfigurieren Sie die automatische Bereinigung der WooCommerce Cron-Jobs.', 'wccc') . '</p>';
    }

    /** Checkbox für abgeschlossene Jobs */
        /** Checkbox für abgeschlossene Jobs */
    public function render_completed_field() {
        printf(
            '<input type="checkbox" name="wccc_settings[clean_completed]" value="1" %s />',
            checked($this->options['clean_completed'], true, false)
        );
    }


    /** Checkbox für fehlgeschlagene Jobs */
    public function render_failed_field() {
        printf(
            '<input type="checkbox" name="wccc_settings[clean_failed]" value="1" %s />',
            checked($this->options['clean_failed'], true, false)
        );
    }

    /** Eingabefeld Tage */
    public function render_days_field() {
        printf(
            '<input type="number" name="wccc_settings[days_to_keep]" value="%d" min="0" class="small-text" />',
            absint($this->options['days_to_keep'])
        );
    }

    /** Dropdown Intervall */
    public function render_interval_field() {
        $intervals = array(
            'hourly'    => __('Stündlich', 'wccc'),
            'twicedaily'=> __('Zweimal täglich', 'wccc'),
            'daily'     => __('Täglich', 'wccc'),
            'weekly'    => __('Wöchentlich', 'wccc'),
        );
        echo '<select name="wccc_settings[interval]">';
        foreach ($intervals as $key => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($key),
                selected($this->options['interval'], $key, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    public function sanitize_options($input) {
        $out = $this->defaults;
        $out['clean_completed'] = !empty($input['clean_completed']);
        $out['clean_failed']    = !empty($input['clean_failed']);
        if (isset($input['days_to_keep']) && is_numeric($input['days_to_keep'])) {
            $out['days_to_keep'] = max(0, intval($input['days_to_keep']));
        }
        if (wccc_fs()->can_use_premium_code() && !empty($input['interval'])) {
            $valid = array('hourly','twicedaily','daily','weekly');
            $out['interval'] = in_array($input['interval'],$valid,true) ? $input['interval'] : $this->defaults['interval'];
        }
        return $out;
    }

    public function schedule_cleanup() {
        if (!wp_next_scheduled('wccc_cleanup_event')) {
            wp_schedule_event(time(), $this->options['interval'], 'wccc_cleanup_event');
        }
    }

    public function clean_cron_jobs($manual=false) {
        global $wpdb;
        $statuses = array();
        if ($this->options['clean_completed']) $statuses[]='complete';
        if ($this->options['clean_failed'])    $statuses[]='failed';
        if (empty($statuses)) return false;
        $days = max(0,intval($this->options['days_to_keep']));
        $date = gmdate('Y-m-d H:i:s',strtotime("-{$days} days"));
        $counts = array();
        if ($manual) {
            foreach($statuses as $s) {
                $counts[$s] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions WHERE status=%s AND scheduled_date_gmt<%s", $s, $date));
            }
        }
        $place  = implode(',',array_fill(0,count($statuses),'%s'));
        $params = array_merge($statuses,array($date));
        $query  = $wpdb->prepare("DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE status IN ($place) AND scheduled_date_gmt<%s", $params);
        $res = $wpdb->query($query);
        if(false!==$res) {
            $this->log_cleanup($res);
            if($this->options['clean_failed']) $this->log_failed_actions($date);
            return $manual?$counts:$res;
        }
        return false;
    }

    private function log_failed_actions($date) {
        global $wpdb;
        $acts = $wpdb->get_results($wpdb->prepare("SELECT a.hook,a.args,l.message FROM {$wpdb->prefix}actionscheduler_actions a LEFT JOIN {$wpdb->prefix}actionscheduler_logs l USING(action_id) WHERE a.status='failed' AND a.scheduled_date_gmt<%s ORDER BY a.scheduled_date_gmt DESC LIMIT 100", $date));
        if(empty($acts)) return;
        $log = get_option('wccc_error_log',array());
        foreach($acts as $a) {
            array_unshift($log,array(
                'time'=>current_time('mysql'),
                'hook'=>$a->hook,
                'plugin'=>$this->detect_plugin_from_hook($a->hook),
                'message'=>$a->message,
                'args'=>maybe_unserialize($a->args),
            ));
        }
        update_option('wccc_error_log',array_slice($log,0,100));
    }

    private function log_cleanup($count) {
        $log = get_option('wccc_cleanup_log',array());
        $log[] = array('time'=>current_time('mysql'),'count'=>$count);
        update_option('wccc_cleanup_log',array_slice($log,-50));
    }

    private function detect_plugin_from_hook($hook) {
        if(strpos($hook,'woocommerce')===0) return 'WooCommerce';
        $map=array('yoast'=>'Yoast SEO','elementor'=>'Elementor','gravityforms'=>'Gravity Forms','wpml'=>'WPML','rankmath'=>'Rank Math','mailpoet'=>'MailPoet');
        foreach($map as $k=>$n) if(false!==strpos($hook,$k)) return $n;
        return 'Unbekannt';
    }

    public function display_stats() {
        $log = get_option('wccc_cleanup_log',array());
        if(empty($log)) { echo '<p>Noch keine Bereinigungen durchgeführt.</p>'; return; }
        $total=$comp=$fail=0;
        foreach($log as $e){ $comp+=$e['completed']??0; $fail+=$e['failed']??0; }
        $total=$comp+$fail;
        echo '<ul>';
        echo '<li><strong>Insgesamt bereinigt:</strong> '.number_format_i18n($total).'</li>';
        echo '<li><strong>Abgeschlossene Jobs:</strong> '.number_format_i18n($comp).'</li>';
        echo '<li><strong>Fehlgeschlagene Jobs:</strong> '.number_format_i18n($fail).'</li>';
        echo '</ul>';
    }

    public function display_pending_jobs() {
        global $wpdb;
        $res=$wpdb->get_results("SELECT hook,COUNT(*) as count FROM {$wpdb->prefix}actionscheduler_actions WHERE status='pending' GROUP BY hook ORDER BY count DESC LIMIT 50");
        if(empty($res)) { echo '<p>Keine ausstehenden Jobs gefunden.</p>'; return; }
        echo '<ul>'; foreach($res as $r){ echo '<li>'.$this->detect_plugin_from_hook($r->hook).': '.number_format_i18n($r->count).'</li>'; } echo '</ul>';
    }

    public function display_error_logs() {
        $logs=get_option('wccc_error_log',array());
        if(empty($logs)) { echo '<p>Keine Fehler in den letzten Bereinigungen gefunden.</p>'; return; }
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Zeit</th><th>Plugin</th><th>Hook</th><th>Fehlermeldung</th></tr></thead><tbody>';
        foreach($logs as $l){
            echo '<tr>';
            echo '<td>'.date_i18n(get_option('date_format').' '.get_option('time_format'),strtotime($l['time'])).'</td>';
            echo '<td>'.esc_html($l['plugin']).'</td>';
            echo '<td><code>'.esc_html($l['hook']).'</code></td>';
            echo '<td>'.esc_html($l['message']).'</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public function enqueue_admin_styles($hook) {
        if(false!==strpos($hook,'wccc-settings')||false!==strpos($hook,'wccc-cron-overview')||false!==strpos($hook,'wccc-advanced-stats')){
            $plugin_dir=plugin_dir_path(__DIR__);
            $plugin_url=plugin_dir_url(__DIR__);
            wp_enqueue_style('wccc-admin-css',$plugin_url.'assets/css/admin.css',array(),filemtime($plugin_dir.'assets/css/admin.css'));
            wp_enqueue_script(
                'wccc-darkmode-toggle',
                $plugin_url . 'assets/css/darkmode-toggle.js',
                array(),
                filemtime( $plugin_dir . 'assets/css/darkmode-toggle.js' ),
                true
            );
        }
        if(false!==strpos($hook,'wccc-advanced-stats')){
            wp_enqueue_script('chartjs','https://cdn.jsdelivr.net/npm/chart.js',array(),null,true);
            wp_enqueue_script('chartjs-matrix','https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@3',array(),null,true);
            wp_enqueue_script('wccc-dashboard',plugin_dir_url(__DIR__).'assets/js/stats-dashboard.js',array('chartjs','chartjs-matrix'),'1.0',true);
        }
    }
}

add_action('plugins_loaded',function(){ if(class_exists('WooCommerce')) new WCCC_Cron_Cleaner_Pro(); });
