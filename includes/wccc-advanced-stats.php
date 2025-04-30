<?php
defined( 'ABSPATH' ) || exit;

class WCCC_Advanced_Stats_Page {

    /** Register our hook */
    public static function init() {
        // ► erst warten, bis WP das Admin-Menü aufbaut
        add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
    }

    /** Sub-Menü anlegen (jetzt ist current_user_can verfügbar) */
    public static function register_page() {
        add_submenu_page(
            'wccc-settings',                // parent-slug
            'Erweiterte Statistik',
            'Erweiterte Statistik',
            'manage_options',
            'wccc-advanced-stats',
            [ __CLASS__, 'render' ]
        );
    }

    /**  Frontend  */
    public static function render() {
        // Zeitraum aus URL (nur weitergeleitet zum JS / REST – hier egal)
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date( 'Y-m-d', strtotime('-30 days') );
        $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : date( 'Y-m-d' );
        ?>
        <div class="wrap wccc-admin">
            <h1>Erweiterte Statistik</h1>

            <!-- Datumsfilter (optional) -->
            <form method="get" style="margin-bottom:20px;">
                <input type="hidden" name="page" value="wccc-advanced-stats" />
                Von: <input type="date"  name="from" value="<?php echo esc_attr($from); ?>" />
                Bis: <input type="date"  name="to"   value="<?php echo esc_attr($to); ?>" />
                <button class="button">Anzeigen</button>
            </form>

            <!-- KPI-Cards -->
            <div class="wccc-stat-grid">
                <div class="card"><h4>Gesamt</h4><p id="wccc_kpi_total">--</p></div>
                <div class="card"><h4>Success-Rate</h4><p id="wccc_kpi_success">--</p></div>
                <div class="card"><h4>Gesparte Zeit</h4><p id="wccc_kpi_saved">--</p></div>
            </div>

            <!-- Charts -->
            <canvas id="wccc_chart_total"  height="120"></canvas>

            <canvas id="wccc_chart_donut"
                    width="160" height="160"
                    style="max-width:280px;margin-top:20px"></canvas>

            <h3 style="margin-top:40px">Fehler nach Plugin</h3>
            <canvas id="wccc_chart_plugins" height="140"></canvas>

            <h3 style="margin-top:40px">Heatmap Fehler (Wochentag × Stunde)</h3>
            <canvas id="wccc_chart_heat" height="240"></canvas>
        </div>
        <?php
    }
}

WCCC_Advanced_Stats_Page::init();
