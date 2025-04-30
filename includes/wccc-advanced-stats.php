<?php
defined('ABSPATH') || exit;

class WCCC_Advanced_Stats_Page {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_stats_submenu']);
    }

    public static function add_stats_submenu() {
        add_submenu_page(
            'wccc-settings',
            __('Erweiterte Statistik', 'wccc'),
            __('Erweiterte Statistik', 'wccc'),
            'manage_options',
            'wccc-advanced-stats',
            [__CLASS__, 'render']
        );
    }

    public static function render() {
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-d', strtotime('-30 days'));
        $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : date('Y-m-d');
        ?>
        <div class="wrap wccc-admin">
            <h1><?php esc_html_e('Erweiterte Statistik', 'wccc'); ?></h1>
            <form method="get" style="margin-bottom:20px;">
                <input type="hidden" name="page" value="wccc-advanced-stats" />
                <?php esc_html_e('Von:', 'wccc'); ?> <input type="date" name="from" value="<?php echo esc_attr($from); ?>" />
                <?php esc_html_e('Bis:', 'wccc'); ?> <input type="date" name="to"   value="<?php echo esc_attr($to); ?>" />
                <button class="button button-primary"><?php esc_html_e('Anzeigen', 'wccc'); ?></button>
            </form>
            <div class="wccc-stat-grid">
                <div class="card"><h4><?php esc_html_e('Ø pro Tag', 'wccc'); ?></h4><p id="wccc_kpi_avg_day">--</p></div>
                <div class="card"><h4><?php esc_html_e('Ø pro Woche', 'wccc'); ?></h4><p id="wccc_kpi_avg_week">--</p></div>
                <div class="card"><h4><?php esc_html_e('Ø pro Monat', 'wccc'); ?></h4><p id="wccc_kpi_avg_month">--</p></div>
                <div class="card"><h4><?php esc_html_e('Gesparte Zeit', 'wccc'); ?></h4><p id="wccc_kpi_saved">--</p></div>
            </div>
            <canvas id="wccc_chart_donut" width="160" height="160" style="max-width:280px;margin-top:20px"></canvas>
            <div class="wccc-section" style="margin-top:32px;">
                <h3><?php esc_html_e('Größte Einzel-Bereinigung', 'wccc'); ?></h3>
                <p id="wccc_peak_info">--</p>
            </div>
        </div>
        <?php
    }
}
WCCC_Advanced_Stats_Page::init();
