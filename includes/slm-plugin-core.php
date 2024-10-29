<?php

/**
 *
 * @package   SLM Plus
 * @author    Michel Velis
 * @license   GPL-2.0+
 * @link      http://epikly.com
 */

//Includes - utilities and cron jobs
include_once(ABSPATH . 'wp-admin/includes/plugin.php');
require_once(SLM_LIB . 'slm-utility.php');
require_once(SLM_CRONS . 'slm-tasks.php');

// Filters for adding extra headers and customizing plugin row meta
add_filter('extra_plugin_headers', 'slmplus_add_extra_headers');
add_filter('plugin_row_meta', 'slmplus_filter_authors_row_meta', 1, 4);
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'slm_settings_link');

function slmplus_add_extra_headers() {
    return array('Author2');
}

function hyphenate($str) {
    return implode("-", str_split($str, 5));
}

function slmplus_filter_authors_row_meta($plugin_meta, $plugin_file, $plugin_data, $status) {
    if (!empty($plugin_data['Author2'])) {
        $plugin_meta[1] .= ', ' . $plugin_data['Author2'];
    }
    return $plugin_meta;
}

// Add settings link
function slm_settings_link($links)
{
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=slm_settings')) . '">' . __('Settings') . '</a>';
    $github_link = '<a href="' . esc_url('https://github.com/michelve/software-license-manager') . '" target="_blank">' . __('GitHub') . '</a>';
    $links[] = $settings_link;
    $links[] = $github_link;
    return $links;
}


// Includes for essential plugin components
require_once(SLM_LIB . 'slm-debug-logger.php');
require_once(SLM_LIB . 'slm-error-codes.php');
require_once(SLM_LIB . 'slm-init-time-tasks.php');
require_once(SLM_LIB . 'slm-api-listener.php');
require_once(SLM_LIB . 'slm-scripts.php');

// Admin-only includes
if (is_admin()) {
    require_once SLM_ADMIN . 'slm-admin-init.php';
}

// WP eStores integration
if (SLM_Helper_Class::slm_get_option('slm_wpestores') == 1) {
    require_once(SLM_ADMIN . 'includes/wpestores/slm-wpestores.php');
}

// Activation and deactivation hooks
function activate_software_license_manager() {
    require_once SLM_LIB . 'class-slm-activator.php';
    $slm_activator->activate();
}

function deactivate_software_license_manager() {
    require_once SLM_LIB . 'class-slm-deactivator.php';
    $slm_deactivator->deactivate();
}

function slm_get_license($lic_key_prefix = '')
{
    return strtoupper($lic_key_prefix  . hyphenate(md5(uniqid(rand(4, 10), true) . date('Y-m-d H:i:s') . time())));
}

register_activation_hook(__FILE__, 'activate_software_license_manager');
register_deactivation_hook(__FILE__, 'deactivate_software_license_manager');

// License key generator function
function slmplus_get_license($lic_key_prefix = '') {
    return strtoupper($lic_key_prefix . hyphenate(md5(uniqid(rand(4, 10), true) . date('Y-m-d H:i:s') . time())));
}

// WooCommerce integration
if (SLM_Helper_Class::slm_get_option('slm_woo') == 1 && is_plugin_active('woocommerce/woocommerce.php')) {
    require_once(SLM_WOO . 'includes/wc_licenses_class.php');
    require_once(SLM_WOO . 'includes/slm-meta-boxes.php');

    require_once SLM_WOO . 'includes/register-template.php';
	require_once SLM_WOO . 'includes/purchase.php';

    // Build WooCommerce tabs
    SLM_Utility::slm_woo_build_tab();
}


// Action hooks
add_action('init', 'slmplus_init_handler');
add_action('plugins_loaded', 'slmplus_plugins_loaded_handler');
add_action('wp_ajax_del_registered_devices', 'slmplus_del_registered_devices');
add_action('wp_ajax_del_registered_domain', 'slmplus_del_registered_domain');
add_action('wp_ajax_del_activation', 'slmplus_remove_activation');
// Initialize plugin on plugins_loaded action
add_action('plugins_loaded', array('SLM_Tabbed_Plugin', 'get_object'));

// Initialize debug logger
$slm_debug_logger = new SLM_Debug_Logger();

// Init-time tasks
function slmplus_init_handler() {
    $init_task = new SLM_Init_Time_Tasks();
    $api_listener = new SLM_API_Listener();
}

// Plugins loaded tasks
function slmplus_plugins_loaded_handler() {
    if (is_admin() && get_option('slm_db_version') != SLM_DB_VERSION) {
        require_once(SLM_LIB . 'class-slm-installer.php');
        // TODO - Implement DB update logic here
    }
}

// Singleton pattern for the plugin
class SLM_Tabbed_Plugin {
    private static $classobj = NULL;

    public static function get_object() {
        if (self::$classobj === NULL) {
            self::$classobj = new self();
        }
        return self::$classobj;
    }

    private function __construct() {}
}

// AJAX handlers
function slmplus_del_registered_domain() {
    global $wpdb;
    $id = strip_tags($_GET['id']);
    $ret = $wpdb->query($wpdb->prepare("DELETE FROM " . SLM_TBL_LIC_DOMAIN . " WHERE id = %d", $id));
    echo ($ret) ? 'success' : 'failed';
    exit;
}

function slmplus_del_registered_devices() {
    global $wpdb;
    $id = strip_tags($_GET['id']);
    $ret = $wpdb->query($wpdb->prepare("DELETE FROM " . SLM_TBL_LIC_DEVICES . " WHERE id = %d", $id));
    echo ($ret) ? 'success' : 'failed';
    exit;
}

function slmplus_remove_activation() {
    global $wpdb;
    $id = strip_tags($_GET['id']);
    $activation_type = strip_tags($_GET['activation_type']);

    $table = ($activation_type == 'Devices') ? SLM_TBL_LIC_DEVICES : SLM_TBL_LIC_DOMAIN;
    $ret = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id = %d", $id));
    echo ($ret) ? 'success' : 'failed';
    exit;
}

// Debugging functions
function wc_print_pretty($args) {
    echo '<pre>';
    print_r($args);
    echo '</pre>';
}

function wc_log($msg) {
    $log = ABSPATH . DIRECTORY_SEPARATOR . 'slm_log.txt';
    file_put_contents($log, $msg . '', FILE_APPEND);
}

