<?php
/**
 * Runs on Uninstall of Software License Manager
 *
 * @package   Software License Manager
 * @author    Michel Velis
 * @license   GPL-2.0+
 * @link      http://epikly.com
 */
global $wpdb;
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

//***Installer variables***
$lic_key_table      = SLM_TBL_LICENSE_KEYS;
$lic_domain_table   = SLM_TBL_LIC_DOMAIN;
$lic_devices_table  = SLM_TBL_LIC_DEVICES;

$charset_collate = '';
if (!empty($wpdb->charset)){
    $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
}
else{
    $charset_collate = "DEFAULT CHARSET=utf8";
}
if (!empty($wpdb->collate)){
    $charset_collate .= " COLLATE $wpdb->collate";
}

$lk_tbl_sql = "CREATE TABLE " . $lic_key_table . " (
      id int(12) NOT NULL auto_increment,
      license_key varchar(255) NOT NULL,
      max_allowed_domains int(40) NOT NULL,
      max_allowed_devices int(40) NOT NULL,
      lic_status ENUM('pending', 'active', 'blocked', 'expired') NOT NULL DEFAULT 'pending',
      lic_type ENUM('none', 'subscription', 'lifetime') NOT NULL DEFAULT 'subscription',
      first_name varchar(32) NOT NULL default '',
      last_name varchar(32) NOT NULL default '',
      email varchar(64) NOT NULL,
      company_name varchar(100) NOT NULL default '',
      txn_id varchar(64) NOT NULL default '',
      manual_reset_count varchar(128) NOT NULL default '',
      purchase_id_ varchar(255) NOT NULL default '',
      date_created date NOT NULL DEFAULT '0000-00-00',
      date_activated date NOT NULL DEFAULT '0000-00-00',
      date_renewed date NOT NULL DEFAULT '0000-00-00',
      date_expiry date NOT NULL DEFAULT '0000-00-00',
      product_ref varchar(255) NOT NULL default '',
      until varchar(255) NOT NULL default '',
      subscr_id varchar(128) NOT NULL default '',
      PRIMARY KEY (id)
      )" . $charset_collate . ";";
dbDelta($lk_tbl_sql);

$ld_tbl_sql = "CREATE TABLE " .$lic_domain_table. " (
      id INT NOT NULL AUTO_INCREMENT ,
      lic_key_id INT NOT NULL ,
      lic_key varchar(255) NOT NULL ,
      registered_domain text NOT NULL ,
      registered_devices text NOT NULL ,
      item_reference varchar(255) NOT NULL,
      PRIMARY KEY ( id )
      )" . $charset_collate . ";";
dbDelta($ld_tbl_sql);

$ldv_tbl_sql = "CREATE TABLE " .$lic_devices_table. " (
      id INT NOT NULL AUTO_INCREMENT ,
      lic_key_id INT NOT NULL ,
      lic_key varchar(255) NOT NULL ,
      registered_devices text NOT NULL ,
      registered_domain text NOT NULL ,
      item_reference varchar(255) NOT NULL,
      PRIMARY KEY ( id )
      )" . $charset_collate . ";";
dbDelta($ldv_tbl_sql);
update_option("slm_db_version", SLM_DB_VERSION);

// Add default options
$options = array(
    'lic_creation_secret'     => SLM_Utility::create_secret_keys(),
    'lic_prefix'              => 'SLM-',
    'default_max_domains'     => '2',
    'default_max_devices'     => '2',
    'lic_verification_secret' => SLM_Utility::create_secret_keys(),
    'enable_debug'            => '',
    'slm_woo'                 => '',
    'slm_wpestores'           => '',
    'slm_dl_manager'          => '', //Download Manager
);
add_option('slm_plugin_options', $options);