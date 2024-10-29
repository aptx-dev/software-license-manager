<?php

if (!defined('WPINC')) {
    die;
}

function slm_admin_tools_menu()
{
    if (isset($_POST['slm_backup_db'])) {
        slm_save_backup_to_uploads();
    }

    echo '<div class="wrap">';
    echo '<h2 class="imgh2">'. __('Admin Tools', 'slmplus') . '</h2>';
    echo '<div id="poststuff"><div id="post-body">';

    if (isset($_POST['send_deactivation_request'])) {
        $postURL = esc_url_raw($_POST['slm_deactivation_req_url']);
        $secretKeyForVerification = slm_get_option('lic_verification_secret');
        $data = array('secret_key' => $secretKeyForVerification);

        $ch = curl_init($postURL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $returnValue = curl_exec($ch);

        $msg = "";
        if ($returnValue == "Success") {
            $msg .= __('Success message returned from the remote host.', 'slmplus');
        }
        echo '<div id="message" class="updated fade"><p>';
        echo __('Request sent to the specified URL!', 'slmplus');
        echo '<br />' . esc_html($msg);
        echo '</p></div>';
    }

    if (isset($_POST['slm_clear_log'])) {
        global $wpdb, $slm_debug_logger;
        $table = SLM_TBL_LIC_LOG;
        $wpdb->query("TRUNCATE TABLE $table");
        $slm_debug_logger->reset_log_file("log.txt");
        $slm_debug_logger->reset_log_file("log-cron-job.txt");

        echo '<div id="message" class="updated fade"><p>' . esc_html__('Log was cleared successfully!', 'slmplus') . '</p></div>';
    }

    ?>
    <br />
    <div class="postbox">
        <h3 class="hndle"><label for="title"><?php _e('Send Deactivation Message for a License', 'slmplus'); ?></label></h3>
        <div class="inside">
            <form method="post" action="">
                <input name="slm_deactivation_req_url" type="text" size="100" value="<?php echo esc_attr($_POST['slm_deactivation_req_url'] ?? ''); ?>" />
                <div class="submit">
                    <input type="submit" name="send_deactivation_request" value="<?php _e('Send Request', 'slmplus'); ?>" class="button" />
                </div>
            </form>
        </div>
    </div>

    <div class="postbox">
        <h3 class="hndle"><label for="title"><?php _e('Clean Activity Log', 'slmplus'); ?></label></h3>
        <div class="inside">
            <p><?php _e('This will clear/reset license keys activities', 'slmplus'); ?></p>
            <form method="post" action="">
                <div class="submit">
                    <input type="submit" name="slm_clear_log" value="<?php _e('Clear Log', 'slmplus'); ?>" class="button" />
                </div>
            </form>
        </div>
    </div>

    <div class="postbox">
        <h3 class="hndle"><label for="title"><?php _e('Backup Database', 'slmplus'); ?></label></h3>
        <div class="inside">
            <p><?php _e('This will create a backup of the database tables related to this plugin and save it to the uploads directory.', 'slmplus'); ?></p>
            <form method="post" action="">
                <div class="submit">
                    <input type="submit" name="slm_backup_db" value="<?php _e('Create Backup', 'slmplus'); ?>" class="button" />
                </div>
            </form>

            <?php
            // Display latest backup link if available
            $backup_info = slm_get_option('slm_last_backup_info');
            if (!empty($backup_info)) {
                $backup_url = esc_url($backup_info['url']);
                $backup_date = esc_html($backup_info['date']);
                echo '<p>' . __('Last backup created on: ', 'slmplus') . $backup_date . ' - <a href="' . $backup_url . '">' . __('Download Backup', 'slmplus') . '</a></p>';
            }
            ?>
        </div>
    </div>

    <?php
    echo '</div></div>';
    echo '</div>';
}

/**
 * Generates or retrieves a unique hash for the backup directory.
 */
function slm_get_unique_hash()
{
    $hash = slm_get_option('slm_backup_dir_hash');

    if (!$hash) {
        $hash = wp_generate_password(8, false, false); // Generate random 8-character hash
        slm_update_option('slm_backup_dir_hash', $hash);
    }

    return $hash;
}

/**
 * Saves a backup of the plugin's database tables in a secure folder.
 */
function slm_save_backup_to_uploads()
{
    global $wpdb;

    // Get the upload directory
    $upload_dir = wp_upload_dir();
    $unique_hash = slm_get_unique_hash(); // Generate or retrieve the unique hash
    $slm_backup_dir = $upload_dir['basedir'] . $unique_hash;

    // Create the slm-plus folder with hash if it doesn't exist
    if (!file_exists($slm_backup_dir)) {
        wp_mkdir_p($slm_backup_dir);
    }

    // Set backup file name and path
    $backup_file = $slm_backup_dir . '/slm_plugin_backup_' . date('Y-m-d_H-i-s') . '.sql';

    // Get plugin tables
    $backup_tables = [
        SLM_TBL_LICENSE_KEYS,
        SLM_TBL_LIC_DOMAIN,
        SLM_TBL_LIC_DEVICES,
        SLM_TBL_LIC_LOG,
        SLM_TBL_EMAILS,
        SLM_TBL_LICENSE_STATUS
    ];

    $sql = "";
    foreach ($backup_tables as $table) {
        // Get table structure
        $create_table_query = $wpdb->get_results("SHOW CREATE TABLE $table", ARRAY_N);
        $sql .= "\n\n" . $create_table_query[0][1] . ";\n\n";

        // Get table data
        $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
        foreach ($rows as $row) {
            $values = array_map('esc_sql', array_values($row)); // Use esc_sql to escape the values
            $values = "'" . implode("','", $values) . "'";
            $sql .= "INSERT INTO $table VALUES ($values);\n";
        }
    }

    // Save the SQL to a file in the slm-plus folder
    if (file_put_contents($backup_file, $sql)) {
        $backup_url = $upload_dir['baseurl'] . $unique_hash . '/' . basename($backup_file);

        // Save backup info in plugin options
        $backup_info = [
            'url' => $backup_url,
            'date' => date('Y-m-d H:i:s')
        ];
        slm_update_option('slm_last_backup_info', $backup_info);

        echo '<div class="notice notice-success"><p>' . __('Backup created successfully! Download from: ', 'slmplus') . '<a href="' . esc_url($backup_url) . '">' . esc_html(basename($backup_file)) . '</a></p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . __('Error: Failed to create the backup file.', 'slmplus') . '</p></div>';
    }
}


/**
 * Retrieves an option from the slm_plugin_options.
 */
function slm_get_option($key)
{
    $options = get_option('slm_plugin_options', []);
    return $options[$key] ?? null;
}

/**
 * Updates or adds an option to the slm_plugin_options.
 */
function slm_update_option($key, $value)
{
    $options = get_option('slm_plugin_options', []);
    $options[$key] = $value;
    update_option('slm_plugin_options', $options);
}
