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

defined('ABSPATH') || exit;

if (!function_exists('wccc_fs')) {
    function wccc_fs() {
        global $wccc_fs;
        if (!isset($wccc_fs)) {
            require_once dirname(__FILE__) . '/vendor/freemius/start.php';
            $wccc_fs = fs_dynamic_init([
                'id'                  => '18748',
                'slug'                => 'WooCronCleaner',
                'premium_slug'        => 'croncleaner-premium',
                'type'                => 'plugin',
                'public_key'          => 'pk_20496afe330729b3a6ec41ac7d0a6',
                'is_premium'          => true,
                'premium_suffix'      => 'WooCronCleaner Pro',
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'trial'               => [
                    'days'               => 7,
                    'is_require_payment' => true,
                ],
                'menu' => [
                    'slug'    => 'wccc-settings',
                    'account' => true,
                    'contact' => true,
                    'pricing' => true,
                ],
            ]);
        }
        return $wccc_fs;
    }
    wccc_fs();
    do_action('wccc_fs_loaded');
}

add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo 'WooCommerce Cron Cleaner benötigt WooCommerce. Bitte installieren und aktivieren Sie WooCommerce.';
            echo '</p></div>';
        });
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'includes/class-wccc-cron-cleaner.php';
    new WCCC_Cron_Cleaner_Pro();
});

wccc_fs()->add_filter('connect_message', function($message) {
    return sprintf(
        '<h3 style="text-align: center;">%s</h3><p style="text-align: center;">%s</p>',
        'Optimiere deine WooCommerce Cron-Jobs',
        'Aktiviere das Plugin für automatische Updates und Premium-Funktionen'
    );
});




function wccc_send_email_report() {
    if (!function_exists('wccc_fs') || !wccc_fs()->can_use_premium_code()) return;

    $opts = get_option('wccc_email_report');
    $admin_email = $opts['email'] ?? get_option('admin_email');
    $log = get_option('wccc_cleanup_log', []);
    if (empty($log)) return;

    $latest = end($log);
    $message = "<h2>Woo Cron Cleaner – Tagesbericht</h2>";
    $message .= "<p><strong>Letzte Bereinigung:</strong> " . esc_html($latest['time']) . "</p>";
    $message .= "<p><strong>Gelöschte Jobs:</strong> " . intval($latest['count']) . "</p>";

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($admin_email, 'Woo Cron Cleaner – Tagesbericht', $message, $headers);
}

add_action('wccc_daily_email_report', 'wccc_send_email_report');

add_action('admin_post_wccc_send_test_mail', function() {
    check_admin_referer('wccc_send_test');
    wccc_send_email_report();
    wp_redirect(add_query_arg('wccc_test_sent', '1', admin_url('admin.php?page=wccc-settings')));
    exit;
});

if (isset($_GET['wccc_test_sent'])) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success is-dismissible"><p>Test-E-Mail wurde gesendet.</p></div>';
    });
}


add_action('wccc_cleanup_event', function () {
    if (class_exists('WCCC_Cron_Cleaner_Pro')) {
        $instance = new WCCC_Cron_Cleaner_Pro();
        $instance->clean_cron_jobs(false);
    }
});



add_action('wccc_daily_email_report', 'wccc_send_email_report');

if (!wp_next_scheduled('wccc_daily_email_report')) {
    $timestamp = strtotime('today 08:00');
    wp_schedule_event($timestamp, 'daily', 'wccc_daily_email_report');
}


// Register email report settings
add_action('admin_init', function () {
    register_setting('wccc_settings_group', 'wccc_email_report');

    add_settings_section('wccc_email_section', 'E-Mail-Bericht (nur Pro)', function () {
        echo '<p>Konfiguriere den automatischen E-Mail-Tagesbericht über bereinigte Cronjobs.</p>';
    }, 'wccc-settings');

    if (function_exists('wccc_fs') && wccc_fs()->can_use_premium_code()) {
        add_settings_field('wccc_email_report_active', 'E-Mail-Bericht aktivieren', function () {
            $enabled = get_option('wccc_email_report')['active'] ?? false;
            echo '<input type="checkbox" name="wccc_email_report[active]" value="1" ' . checked($enabled, true, false) . '>';
        }, 'wccc-settings', 'wccc_email_section');

        add_settings_field('wccc_email_report_address', 'Empfängeradresse', function () {
            $email = get_option('wccc_email_report')['email'] ?? get_option('admin_email');
            echo '<input type="email" name="wccc_email_report[email]" value="' . esc_attr($email) . '" class="regular-text">';
        }, 'wccc-settings', 'wccc_email_section');

        add_settings_field('wccc_email_report_time', 'Uhrzeit (HH:MM)', function () {
            $time = get_option('wccc_email_report')['time'] ?? '08:00';
            echo '<input type="time" name="wccc_email_report[time]" value="' . esc_attr($time) . '" class="small-text">';
        }, 'wccc-settings', 'wccc_email_section');
    } else {
        add_settings_field('wccc_email_report_disabled', 'Nicht verfügbar', function () {
            echo '<p><em>Nur in der <strong>Pro-Version</strong> verfügbar.</em></p>';
        }, 'wccc-settings', 'wccc_email_section');
    }
});

// Schedule based on time setting
add_action('init', function () {
    $opts = get_option('wccc_email_report');
    $time = $opts['time'] ?? '08:00';
    $hhmm = explode(':', $time);
    if (count($hhmm) === 2) {
        $timestamp = strtotime('today ' . $time);
        if (!wp_next_scheduled('wccc_daily_email_report')) {
            wp_schedule_event($timestamp, 'daily', 'wccc_daily_email_report');
        }
    }
});

// Filter: only send if active
add_action('wccc_daily_email_report', function () {
    $opts = get_option('wccc_email_report');
    if (!function_exists('wccc_fs') || !wccc_fs()->can_use_premium_code()) return;
    if (empty($opts['active'])) return;
    wccc_send_email_report();
});


// Add setting for adminbar toggle
add_action('admin_init', function () {
    register_setting('wccc_settings_group', 'wccc_adminbar_enabled');
    add_settings_field('wccc_adminbar_enabled', 'Adminbar-Shortcut anzeigen', function () {
        $enabled = get_option('wccc_adminbar_enabled', true);
        echo '<input type="checkbox" name="wccc_adminbar_enabled" value="1" ' . checked($enabled, true, false) . '>';
    }, 'wccc-settings', 'wccc_main_section');
});

// Adminbar shortcut logic
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!is_admin() || !current_user_can('manage_options')) return;
    if (!get_option('wccc_adminbar_enabled', true)) return;

    $wp_admin_bar->add_node([
        'id' => 'wccc_main',
        'title' => '🔧 Cron Cleaner',
        'href' => admin_url('admin.php?page=wccc-settings'),
    ]);

    $wp_admin_bar->add_node([
        'id' => 'wccc_cronjobs',
        'parent' => 'wccc_main',
        'title' => '🕒 Geplante Cronjobs',
        'href' => admin_url('admin.php?page=wccc-cron-overview'),
    ]);

    $wp_admin_bar->add_node([
        'id' => 'wccc_manual_clean',
        'parent' => 'wccc_main',
        'title' => '▶️ Jetzt bereinigen',
        'href' => wp_nonce_url(admin_url('admin-post.php?action=wccc_manual_cleanup'), 'wccc_manual_cleanup_action'),
    ]);
}, 99);

// Manual cleanup handler (admin-post fallback)
add_action('admin_post_wccc_manual_cleanup', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.');
    }

    if (!check_admin_referer('wccc_manual_cleanup_action')) {
        wp_die('Ungültige Anfrage.');
    }

    $plugin = new WCCC_Cron_Cleaner_Pro();
    $plugin->clean_cron_jobs(true);

    wp_redirect(add_query_arg('wccc_cleanup_done', '1', admin_url('admin.php?page=wccc-settings')));
    exit;
});


// Inject darkmode toggle script into settings page
add_action('admin_head', function () {
    if (isset($_GET['page']) && $_GET['page'] === 'wccc-settings') {
        echo "<script>
        document.addEventListener('DOMContentLoaded', function () {
            const saved = localStorage.getItem('wccc_darkmode');
            if (saved === 'on') {
                document.body.classList.add('wccc-dark');
            }
        });
        </script>";
    }
});
