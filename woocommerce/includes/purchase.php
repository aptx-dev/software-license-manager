<?php

/**
 * SLM Plus WooCommerce Integration
 * @package   SLM Plus
 * @author    Michel Velis
 * @license   GPL-2.0+
 * @link      http://epikly.com
 * @since     4.5.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $post, $woocommerce, $product;

// Retrieve plugin options
$slm_options = get_option('slm_plugin_options');
$affect_downloads = isset($slm_options['slm_woo_affect_downloads']) && $slm_options['slm_woo_affect_downloads'] == '1';

// Hooks for WooCommerce Integration
// Add license management for orders that are marked as completed
add_action('woocommerce_order_status_completed', 'slm_order_completed', 81);

// Set downloadable product permissions expiration based on license expiration if the option is enabled
if ($affect_downloads) {
    add_action('woocommerce_order_status_completed', 'wc_slm_access_expiration', 82);
}

// Add additional license management after order completion
add_action('woocommerce_order_status_completed', 'wc_slm_on_complete_purchase', 10);

// Display license key information more nicely in the order item meta table
add_action('woocommerce_after_order_itemmeta', 'slm_display_nice_item_meta', 10, 3);

/**
 * Display order meta data in Order items table in a user-friendly way.
 *
 * @param int $item_id The item ID.
 * @param WC_Order_Item $item The item object.
 * @param WC_Product $product The product object.
 *
 * @since 4.5.5
 */
function slm_display_nice_item_meta($item_id, $item, $product)
{
    // Fetch the metadata associated with the license key
    if ($meta_data = wc_get_order_item_meta($item_id, '_slm_lic_key', false)) {
?>
        <div class="view">
            <table cellspacing="0" class="display_meta">
                <?php
                $admin_link = esc_url(get_admin_url() . 'admin.php?page=slm_manage_license&edit_record=');
                foreach ($meta_data as $meta) :
                    $lic_key = sanitize_text_field($meta);
                    $lic_id = wc_slm_get_license_id($lic_key);
                    if (!empty($lic_id)) {
                        $cur_link = sprintf(
                            '<a href="%s" target="_blank">%s</a>',
                            esc_url($admin_link . $lic_id),
                            esc_html($lic_key)
                        );
                    } else {
                        $cur_link = sprintf(
                            '%s - %s',
                            esc_html($lic_key),
                            esc_html__('License no longer exists', 'slm-plus')
                        );
                    }
                ?>
                    <tr>
                        <th><?php echo esc_html__('License Key:', 'slm-plus'); ?></th>
                        <td><?php echo esc_url($cur_link); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php
    }
}


function wc_slm_on_complete_purchase($order_id)
{
    // Write to the log that the function is being called, useful for debugging.
    //SLM_Helper_Class::write_log('Loading wc_slm_on_complete_purchase for Order ID: ' . intval($order_id));

    // Check if the essential constants are defined before proceeding.
    if (defined('SLM_SITE_HOME_URL') && defined('WOO_SLM_API_SECRET') && SLM_SITE_HOME_URL !== '' && WOO_SLM_API_SECRET !== '') {
        // Sanitize the order ID and create license keys.
        //SLM_Helper_Class::write_log('startign to create lic for order: ' . intval($order_id));

        wc_slm_create_license_keys(absint($order_id));
    } else {
        //SLM_Helper_Class::write_log('Error, not constants for Order ID: ' . intval($order_id));
    }
}

function wc_slm_create_license_keys($order_id)
{
    // Write initial log for debugging purposes
    //SLM_Helper_Class::write_log('inside wc_slm_create_license_keys for Order ID: ' . intval($order_id));

    // Get the order and relevant user details
    $order = wc_get_order($order_id);
    if (!$order) {
        //SLM_Helper_Class::write_log('Order ID ' . $order_id . ' not found.');
        return; // Stop if the order does not exist
    }

    $purchase_id_ = $order->get_id();
    //SLM_Helper_Class::write_log('Purchase ID: ' . $purchase_id_);

    global $user_id;
    $user_id = $order->get_user_id();
    //SLM_Helper_Class::write_log('User ID: ' . $user_id);

    if (!$user_id) {
        //SLM_Helper_Class::write_log('User ID not found for Order ID: ' . $order_id);
        return; // Stop if user ID cannot be found
    }

    // Get user details
    $user_meta = get_user_meta($user_id);
    $payment_meta = array(
        'user_info' => array(
            'first_name' => isset($user_meta['billing_first_name'][0]) ? sanitize_text_field($user_meta['billing_first_name'][0]) : '',
            'last_name' => isset($user_meta['billing_last_name'][0]) ? sanitize_text_field($user_meta['billing_last_name'][0]) : '',
            'email' => isset($user_meta['billing_email'][0]) ? sanitize_email($user_meta['billing_email'][0]) : '',
            'company' => isset($user_meta['billing_company'][0]) ? sanitize_text_field($user_meta['billing_company'][0]) : ''
        )
    );


    // Initialize licenses array
    $licenses = array();
    $items = $order->get_items();

    foreach ($items as $item_key => $values) {

        $product_id = $values->get_product_id();
        $product = $values->get_product();
        if ($product->is_type('slm_license')) {
            $download_quantity = absint($values->get_quantity());
            $order_item_lic_keys = $values->get_meta('_slm_lic_key', false);
            $lic_to_add = $download_quantity - count($order_item_lic_keys);

            // Generate license keys only if necessary
            for ($i = 1; $i <= $lic_to_add; $i++) {
                $expiration = '';
                $renewal_period = wc_slm_get_licensing_renewal_period($product_id);
                $renewal_term = wc_slm_get_licensing_renewal_period_term($product_id);

                // Set expiration date
                if ($renewal_term === 'onetime') {
                    $expiration = '0000-00-00';
                } else {
                    $expiration = date('Y-m-d', strtotime('+' . $renewal_period . ' ' . sanitize_text_field($renewal_term)));
                }

                // Log renewal details
                //SLM_Helper_Class::write_log('Renewal Period: ' . $renewal_period);
                //SLM_Helper_Class::write_log('Expiration Date: ' . $expiration);
                //SLM_Helper_Class::write_log('Renewal Term: ' . $renewal_term);


                // Collect product details
                $item_data = $values->get_data();
                $product_name = $item_data['name'];
                $_license_current_version = get_post_meta($product_id, '_license_current_version', true);
                $_license_until_version = get_post_meta($product_id, '_license_until_version', true);
                $amount_of_licenses_devices = wc_slm_get_devices_allowed($product_id);
                $license_type = get_post_meta($product_id, '_license_type', true);
                $lic_item_ref = get_post_meta($product_id, '_license_item_reference', true);
                $transaction_id = wc_get_payment_transaction_id($order_id);
                $sites_allowed = wc_slm_get_sites_allowed($product_id);

                // Prepare API parameters for license creation
                $api_params = array(
                    'slm_action' => 'slm_create_new',
                    'secret_key' => KEY_API,
                    'first_name' => $payment_meta['user_info']['first_name'],
                    'last_name' => $payment_meta['user_info']['last_name'],
                    'email' => $payment_meta['user_info']['email'],
                    'company_name' => $payment_meta['user_info']['company'],
                    'purchase_id_' => $purchase_id_,
                    'product_ref' => $product_id,
                    'txn_id' => $transaction_id,
                    'max_allowed_domains' => $sites_allowed,
                    'max_allowed_devices' => $amount_of_licenses_devices,
                    'date_created' => current_time('Y-m-d'),
                    'date_expiry' => $expiration,
                    'slm_billing_length' => $renewal_period,
                    'slm_billing_interval' => $renewal_term,
                    'until' => $_license_until_version,
                    'current_ver' => $_license_current_version,
                    'subscr_id' => $order->get_customer_id(),
                    'lic_type' => $license_type,
                    'item_reference' => $lic_item_ref,
                );

                // Send the request to create a license key
                $url = esc_url_raw(SLM_SITE_HOME_URL) . '?' . http_build_query($api_params);

                //SLM_Helper_Class::write_log('URL: ' . $url);

                $response = wp_safe_remote_get($url, array('timeout' => 20, 'sslverify' => false));
                $license_key = wc_slm_get_license_key($response);

                // If a license key is generated, save it
                if ($license_key) {
                    $licenses[] = array(
                        'item' => sanitize_text_field($product_name),
                        'key' => sanitize_text_field($license_key),
                        'expires' => $expiration,
                        'type' => sanitize_text_field($license_type),
                        'item_ref' => $lic_item_ref,
                        'slm_billing_length' => $renewal_period,
                        'slm_billing_interval' => $renewal_term,
                        'status' => 'pending',
                        'version' => $_license_current_version,
                        'until' => $_license_until_version
                    );

                    $item_id = $values->get_id();

                    // Update order meta with license details
                    $order = wc_get_order($order_id);
                    if ($order) {
                        $order->update_meta_data('License Key', sanitize_text_field($license_key));
                        $order->update_meta_data('License Type', sanitize_text_field($license_type)); // Save the license type
                        $order->save(); // Save changes to the order
                    }

                    // Update order item meta with license details
                    $order_item = new WC_Order_Item_Product($item_id);
                    if ($order_item) {
                        $order_item->update_meta_data('License Key', sanitize_text_field($license_key));
                        $order_item->update_meta_data('License Type', sanitize_text_field($license_type));
                        $order_item->update_meta_data('Current Ver.', sanitize_text_field($_license_current_version));
                        $order_item->update_meta_data('Until Ver.', sanitize_text_field($_license_until_version));
                        $order_item->update_meta_data('Max Devices', sanitize_text_field($amount_of_licenses_devices));
                        $order_item->update_meta_data('Max Domains', sanitize_text_field($sites_allowed));
                        $order_item->save(); // Save changes to the order item
                    }
                }
            }
        }
    }
}

function wc_slm_get_license_key($response)
{
    // Check for error in the response
    if (is_wp_error($response)) {
        return false;
    }

    // Retrieve response body
    $response_body = wp_remote_retrieve_body($response);

    if (empty($response_body)) {
        return false; // If response body is empty, return false
    }

    // Decode JSON while handling potential errors
    $decoded_data = json_decode($response_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // Handle JSON decoding error appropriately, e.g., log the error
        //SLM_Helper_Class::write_log('Failed to decode JSON response: ' . json_last_error_msg());
        return false;
    }

    // Remove invalid control characters from response data (control chars except line feeds, tabs, etc.)
    $cleaned_data = preg_replace('/[\x00-\x1F\x7F]/', '', json_encode($decoded_data, JSON_UNESCAPED_UNICODE));

    if ($cleaned_data === false) {
        // If the cleaning fails, return false
        //SLM_Helper_Class::write_log('Failed to clean the JSON response body.');
        return false;
    }

    // Decode cleaned JSON back to PHP associative array
    $license_data = json_decode($cleaned_data);

    if (!isset($license_data->key)) {
        return false; // Key is not set
    }

    // Return the license key
    return $license_data->key;
}

function wc_slm_get_license_id($license)
{
    global $wpdb;

    // Prepare the SQL statement to prevent SQL injection
    $table_name = $wpdb->prefix . 'lic_key_tbl';
    $query = $wpdb->prepare(
        "SELECT ID FROM {$table_name} WHERE license_key = %s ORDER BY id DESC LIMIT 1",
        $license
    );

    // Fetch the result as an object
    $license_id = $wpdb->get_var($query);

    // Return the license ID if found, otherwise return false
    return $license_id ? intval($license_id) : false;
}

function wc_slm_access_expiration($order_id, $lic_expiry = '')
{
    global $wpdb;

    // Fetch the WooCommerce order object
    $order = wc_get_order($order_id);
    if (!$order) {
        return; // If the order doesn't exist, return early
    }

    // Loop through each item in the order
    foreach ($order->get_items() as $item_key => $item_details) {
        $product_id = $item_details->get_product_id();
        $product = wc_get_product($product_id);

        // Check if the product is of type 'slm_license'
        if ($product && $product->is_type('slm_license')) {

            // Get the existing license key attached to the order item
            $order_item_lic_key = $item_details->get_meta('_slm_lic_key', true);
            if (!empty($order_item_lic_key)) {

                // Fetch license data using a custom helper function
                $licence = get_licence_by_key($order_item_lic_key);
                if (!empty($licence)) {
                    // Retrieve and format the license expiry date
                    $lic_expiry = $licence['date_expiry'];
                    if ($lic_expiry === '0000-00-00') {
                        $lic_expiry = 'NULL';
                    } else {
                        $lic_expiry = $wpdb->prepare('%s', $lic_expiry);
                    }

                    // Prepare the SQL query using placeholders
                    $table_name = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
                    $query = $wpdb->prepare(
                        "UPDATE {$table_name} 
                        SET access_expires = {$lic_expiry} 
                        WHERE order_id = %d AND product_id = %d",
                        $order_id,
                        $product_id
                    );

                    // Execute the query
                    $wpdb->query($query);
                }
            }
        }
    }

    // Optionally, log the query for debugging (commented out by default)
    ////SLM_Helper_Class::write_log('log:' . $query);
}


/**
 * Get License by Key
 * 
 * @param string $licence_key License key to fetch the record for.
 * @return array|false Returns license data array if found, false otherwise.
 */
function get_licence_by_key($licence_key)
{
    global $wpdb;

    // Check if license key is empty and sanitize input
    if (empty($licence_key)) {
        return false;
    }
    $licence_key = esc_attr($licence_key);

    // Prepare and execute the SQL query
    $lic_keys_table = SLM_TBL_LICENSE_KEYS;
    $sql_prep = $wpdb->prepare(
        "SELECT * FROM {$lic_keys_table} WHERE license_key = %s ORDER BY id DESC LIMIT 1",
        $licence_key
    );

    // Fetch the record and return as an associative array
    $record = $wpdb->get_row($sql_prep, ARRAY_A);
    return $record ? $record : false;
}


/**
 * Get Allowed Number of Sites for a Product
 * 
 * @param int $product_id WooCommerce Product ID.
 * @return int|false Returns the number of allowed domains if set, false otherwise.
 */
function wc_slm_get_sites_allowed($product_id)
{
    // Get the number of allowed domains for a product
    $wc_slm_sites_allowed = absint(get_post_meta($product_id, '_domain_licenses', true));

    // Return false if no value is set, otherwise return the value
    return !empty($wc_slm_sites_allowed) ? $wc_slm_sites_allowed : false;
}


/**
 * Get Number of Allowed Devices
 * 
 * @param int $product_id Product ID.
 * @return int|false Number of allowed devices or false if not set.
 */
function wc_slm_get_devices_allowed($product_id)
{
    $_devices_licenses = absint(get_post_meta($product_id, '_devices_licenses', true));
    return !empty($_devices_licenses) ? $_devices_licenses : false;
}


/**
 * Get Licensing Renewal Period
 * 
 * @param int $product_id Product ID.
 * @return int Licensing renewal period in days or 0 if not set.
 */
function wc_slm_get_licensing_renewal_period($product_id)
{
    $_license_renewal_period_lenght = absint(get_post_meta($product_id, '_license_renewal_period_length', true));
    return !empty($_license_renewal_period_lenght) ? $_license_renewal_period_lenght : 0;
}

/**
 * Get Licensing Renewal Period Term
 * 
 * @param int $product_id Product ID.
 * @return string Renewal period term (e.g., days, months, years) or empty string if not set.
 */
function wc_slm_get_licensing_renewal_period_term($product_id)
{
    $term = get_post_meta($product_id, '_license_renewal_period_term', true);
    return !empty($term) ? sanitize_text_field($term) : '';
}

/**
 * Get Payment Transaction ID
 * 
 * @param int $order_id WooCommerce Order ID.
 * @return string|null Transaction ID or null if not found.
 */
function wc_get_payment_transaction_id($order_id)
{
    return get_post_meta($order_id, '_transaction_id', true);
}

/**
 * Handle Order Completion Actions
 * 
 * @param int $order_id WooCommerce Order ID.
 * @return void
 */
function slm_order_completed($order_id)
{
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $order_billing_email = $order->get_billing_email();

    // If billing email is empty, use current user's email
    if (empty($order_billing_email)) {
        $current_user = wp_get_current_user();
        if ($current_user->exists()) {
            $order_billing_email = $current_user->user_email;
        }
    }

    // Create the note text
    if (!empty($order_billing_email)) {
        $note = sprintf(
            // Translators: %1$s is the mailto link, %2$s is the plain email address
            __("Order confirmation email sent to: <a href='mailto:%1\$s'>%2\$s</a>", 'slm-plus'),
            esc_attr($order_billing_email),
            esc_html($order_billing_email)
        );

        // Add the note to the order and save
        $order->add_order_note($note);
        $order->save();
    }
}


/**
 * Display License Details on the Order Details Page for Customers
 * 
 * @param WC_Order $order The WooCommerce Order object.
 * @return void
 */
function slm_order_details($order)
{
    // Get all the items from the order
    $items = $order->get_items();
    $licences = [];

    foreach ($items as $item_key => $item_details) {
        $product = $item_details->get_product();
        // Check if product is of type 'slm_license'
        if ($product->is_type('slm_license')) {
            // Retrieve license keys and types from the order item meta
            $lic_keys = wc_get_order_item_meta($item_details->get_id(), 'License Key', false);
            $lic_types = wc_get_order_item_meta($item_details->get_id(), 'License Type', false);

            if ($lic_keys && $lic_types) {
                $licenses_data = array_map(function ($keys, $types) {
                    return [
                        'lic_key' => sanitize_text_field($keys),
                        'lic_type' => sanitize_text_field($types),
                    ];
                }, $lic_keys, $lic_types);

                $licences = array_merge($licences, $licenses_data);
            }
        }
    }

    // Display license details if available
    if (!empty($licences)) {
        echo '
            <h2 class="woocommerce-order-details__title">' . esc_html__('License Details', 'slm-plus') . '</h2>
            <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                <thead>
                    <tr>
                        <th class="woocommerce-table__product-name product-name">' . esc_html__('License Key', 'slm-plus') . '</th>
                        <th class="woocommerce-table__product-table product-total">' . esc_html__('Type', 'slm-plus') . '</th>
                    </tr>
                </thead>
                <tbody>
        ';
        foreach ($licences as $lic_row) {
            echo '
                    <tr class="woocommerce-table__line-item order_item">
                        <td class="woocommerce-table__product-name product-name">
                            ' . esc_html($lic_row['lic_key']) . ' - 
                            <a href="' . esc_url(get_permalink(wc_get_page_id('myaccount'))) . 'my-licenses">' . esc_html__('View My Licenses', 'slm-plus') . '</a>
                        </td>
                        <td class="woocommerce-table__product-total product-total">
                            ' . esc_html($lic_row['lic_type']) . '
                        </td>
                    </tr>
            ';
        }
        echo '
                </tbody>
            </table>
        ';
    }
}
add_action('woocommerce_order_details_after_order_table', 'slm_order_details');


add_action('woocommerce_email_before_order_table', 'slm_add_license_to_order_confirmation', 20, 4);

/**
 * Adds license key information to the order confirmation email.
 *
 * @param WC_Order $order Order object.
 * @param bool $sent_to_admin Whether the email is sent to the admin.
 * @param bool $plain_text Whether the email is in plain text.
 * @param WC_Email $email Email object.
 */
function slm_add_license_to_order_confirmation($order, $sent_to_admin, $plain_text, $email)
{
    // Only display the license information in customer completed order emails
    if ($email->id !== 'customer_completed_order') {
        return;
    }

    // Fetch the order items
    $items = $order->get_items();
    $licenses = [];

    // Iterate through items to find licenses
    foreach ($items as $item_key => $item_details) {
        $product = $item_details->get_product();
        // Check if the product type is 'slm_license'
        if ($product && $product->is_type('slm_license')) {
            $meta_data = wc_get_order_item_meta($item_details->get_id(), '_slm_lic_key', false);

            // Store license information in an array
            foreach ($meta_data as $meta_row) {
                $licenses[] = [
                    'product' => sanitize_text_field($product->get_name()),
                    'lic_key' => sanitize_text_field($meta_row),
                ];
            }
        }
    }

    // If there are licenses, add them to the email
    if (!empty($licenses)) {
    ?>
        <h2><?php echo esc_html__('License Keys', 'slm-plus'); ?></h2>
        <table class="td cart_slm slm_licenses_table" cellspacing="0" cellpadding="6" border="1" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; margin-bottom: 40px;">
            <thead>
                <tr>
                    <th class="td" colspan="2" scope="col" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;">
                        <?php echo esc_html__('Product', 'slm-plus'); ?>
                    </th>
                    <th class="td" scope="col" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;">
                        <?php echo esc_html__('License Key', 'slm-plus'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($licenses as $license) : ?>
                    <tr>
                        <td class="td" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;">
                            <?php echo esc_html($license['product']); ?>
                        </td>
                        <td class="td" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;">
                            <?php echo esc_html($license['lic_key']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <br><br>
<?php
    }
}
