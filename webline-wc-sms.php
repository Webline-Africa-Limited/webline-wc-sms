<?php
/**
 * Plugin Name: Webline WooCommerce SMS
 * Plugin URI: https://webline.africa
 * Description: Sends SMS notifications for WooCommerce order status changes.
 * Version: 1.0.1
 * Author: Webline Africa Limited
 * Author URI: https://webline.africa
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: webline-wc-sms
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends an SMS message using the Webline Africa API.
 *
 * @param string $phone The recipient's phone number.
 * @param string $message The message to send.
 * @param int $order_id The order ID.
 *
 * @return string|WP_Error The API response or a WP_Error object on failure.
 */
function webline_wc_send_sms( $phone, $message, $order_id = 0 ) {
	$options = get_option( 'webline_wc_sms_settings' );
    $senderid = isset( $options['sender_id'] ) ? $options['sender_id'] : 'TAARIFA'; // Use setting, default to 'TAARIFA'
    $api_username = isset( $options['api_key'] ) ? $options['api_key'] : ''; // Use setting

	// Sanitize phone number (remove non-numeric characters)
    $phone = preg_replace( '/[^0-9]/', '', $phone );

    // Validate phone number (basic check for length)
    if ( strlen( $phone ) < 9 || strlen( $phone ) > 15 ) {
        return new WP_Error( 'invalid_phone_number', __( 'Invalid phone number format.', 'webline-wc-sms' ) );
    }

    $curl = curl_init();

    curl_setopt_array( $curl, array(
        CURLOPT_URL           => 'https://sms.webline.africa/api/v3/sms/send?recipient=' . $phone . '&sender_id=' . $senderid . '&message=' . urlencode( $message ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => array(
            "Authorization: Bearer $api_username"
        ),
    ) );

    $response = curl_exec($curl);
    $error = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if (false === $response) {
        return new WP_Error('curl_error', $error);
    }

    // Decode JSON response
    $response_data = json_decode($response, true);

    // Check if the API request was successful
    $status = ($http_code === 200 && isset($response_data['success']) && $response_data['success'] === true);
    
    if (!$status) {
        return new WP_Error('api_error', isset($response_data['message']) ? $response_data['message'] : 'Unknown error');
    }

    return $response;
}

/**
 * Placeholder function to be called when the order status changes.
 *
 * @param int $order_id The order ID.
 * @param string $old_status The old order status.
 * @param string $new_status The new order status.
 */

function webline_wc_order_status_changed( $order_id, $old_status, $new_status ) {
    // Get the order object.
    $order = wc_get_order( $order_id );

    // Get the billing phone number.
    $phone = $order->get_billing_phone();

    //check if phone is available
    if(empty($phone)){
        error_log('No phone number for order ID: ' . $order_id);
        return;
    }

    // Get the SMS template from settings.
    $options = get_option( 'webline_wc_sms_settings' );
    $template = isset( $options['sms_template'] ) ? $options['sms_template'] : __( 'Your order #{order_id} status has changed from {old_status} to {new_status}.', 'webline-wc-sms' );

    // Replace tags in the template.
    $message = str_replace(
        array( '{order_id}', '{old_status}', '{new_status}' ),
        array( $order_id, $old_status, $new_status ),
        $template
    );

    // Send the SMS.
    $result = webline_wc_send_sms( $phone, $message, $order_id );

    if ( is_wp_error( $result ) ) {
        error_log( 'SMS Sending Failed: ' . $result->get_error_message() );
    } else {
        error_log( 'SMS Sent Successfully: ' . $result );
    }
}

add_action( 'woocommerce_order_status_changed', 'webline_wc_order_status_changed', 10, 3 );

/**
 * Add settings page to WooCommerce menu.
 */
function webline_wc_sms_add_settings_page() {
    add_submenu_page(
        'woocommerce', // Parent menu slug (WooCommerce).
        __( 'Webline WC SMS Settings', 'webline-wc-sms' ), // Page title.
        __( 'Webline SMS', 'webline-wc-sms' ), // Menu title.
        'manage_options', // Capability required to access the settings page.
        'webline-wc-sms-settings', // Menu slug.
        'webline_wc_sms_settings_page_content' // Callback function to render the settings page.
    );
}
add_action( 'admin_menu', 'webline_wc_sms_add_settings_page' );

/**
 * Render the settings page content.
 */
function webline_wc_sms_settings_page_content() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        
        <div class="notice notice-info">
            <p>
                <?php 
                _e('To use this plugin, you need:', 'webline-wc-sms');
                echo '<ul style="list-style-type: disc; margin-left: 20px;">';
                echo '<li>' . __('An account on <a href="https://webline.africa" target="_blank">webline.africa</a>', 'webline-wc-sms') . '</li>';
                echo '<li>' . __('An API key from your Webline Africa dashboard', 'webline-wc-sms') . '</li>';
                echo '</ul>';
                ?>
            </p>
        </div>

        <form action="options.php" method="post">
            <?php
            settings_fields( 'webline_wc_sms_settings' );
            do_settings_sections( 'webline-wc-sms-settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Register settings, sections, and fields.
 */
function webline_wc_sms_register_settings() {
    // Register the settings.
    register_setting(
        'webline_wc_sms_settings',
        'webline_wc_sms_settings',
        'webline_wc_sms_sanitize_settings'
    );

    // Add settings section.
    add_settings_section(
        'webline_wc_sms_general_section',
        __( 'General Settings', 'webline-wc-sms' ),
        null,
        'webline-wc-sms-settings'
    );

    // Add API Key field.
    add_settings_field(
        'webline_wc_sms_api_key',
        __( 'API Key', 'webline-wc-sms' ),
        'webline_wc_sms_api_key_field_callback',
        'webline-wc-sms-settings',
        'webline_wc_sms_general_section'
    );

    // Add Sender ID field.
    add_settings_field(
        'webline_wc_sms_sender_id',
        __( 'Sender ID', 'webline-wc-sms' ),
        'webline_wc_sms_sender_id_field_callback',
        'webline-wc-sms-settings',
        'webline_wc_sms_general_section'
    );
}
add_action( 'admin_init', 'webline_wc_sms_register_settings' );

/**
 * Register SMS Template field in settings.
 */
function webline_wc_sms_register_template_field() {
    // Add SMS Template field.
    add_settings_field(
        'webline_wc_sms_template',
        __( 'SMS Template', 'webline-wc-sms' ),
        'webline_wc_sms_template_field_callback',
        'webline-wc-sms-settings',
        'webline_wc_sms_general_section'
    );
}
add_action( 'admin_init', 'webline_wc_sms_register_template_field' );

/**
 * Render the API Key field.
 *
 * @param array $args Arguments passed from add_settings_field().
 */
function webline_wc_sms_api_key_field_callback( $args ) {
    $options = get_option( 'webline_wc_sms_settings' );
    $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
    ?>
    <input type="text" id="webline_wc_sms_api_key" name="webline_wc_sms_settings[api_key]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
    <p class="description">
        <?php _e('Enter your API key from your <a href="https://webline.africa" target="_blank">Webline Africa</a> dashboard. If you don\'t have an account, please sign up to get your API key.', 'webline-wc-sms'); ?>
    </p>
    <?php
}

/**
 * Render the Sender ID field.
 *
 * @param array $args Arguments passed from add_settings_field().
 */
function webline_wc_sms_sender_id_field_callback( $args ) {
    $options = get_option( 'webline_wc_sms_settings' );
    $sender_id = isset( $options['sender_id'] ) ? $options['sender_id'] : '';
    ?>
    <input type="text" id="webline_wc_sms_sender_id" name="webline_wc_sms_settings[sender_id]" value="<?php echo esc_attr( $sender_id ); ?>" class="regular-text">
    <?php
}

/**
 * Render the SMS Template field.
 *
 * @param array $args Arguments passed from add_settings_field().
 */
function webline_wc_sms_template_field_callback( $args ) {
    $options = get_option( 'webline_wc_sms_settings' );
    $template = isset( $options['sms_template'] ) ? $options['sms_template'] : __( 'Your order #{order_id} status has changed from {old_status} to {new_status}.', 'webline-wc-sms' );
    ?>
    <textarea id="webline_wc_sms_template" name="webline_wc_sms_settings[sms_template]" rows="5" class="large-text"><?php echo esc_textarea( $template ); ?></textarea>
    <p class="description">
        <?php _e( 'Use the following tags in your SMS template:', 'webline-wc-sms' ); ?>
        <ul style="list-style-type: disc; margin-left: 20px;">
            <li><code>{order_id}</code> - <?php _e( 'Order ID', 'webline-wc-sms' ); ?></li>
            <li><code>{old_status}</code> - <?php _e( 'Old Order Status', 'webline-wc-sms' ); ?></li>
            <li><code>{new_status}</code> - <?php _e( 'New Order Status', 'webline-wc-sms' ); ?></li>
        </ul>
    </p>
    <?php
}

/**
 * Sanitize settings before saving.
 *
 * @param array $input The unsanitized settings values.
 *
 * @return array The sanitized settings values.
 */
function webline_wc_sms_sanitize_settings( $input ) {
    $sanitized_input = array();

    if ( isset( $input['api_key'] ) ) {
        $sanitized_input['api_key'] = sanitize_text_field( $input['api_key'] );
    }

    if ( isset( $input['sender_id'] ) ) {
        $sanitized_input['sender_id'] = sanitize_text_field( $input['sender_id'] );
    }

    if ( isset( $input['sms_template'] ) ) {
        $sanitized_input['sms_template'] = wp_kses_post( $input['sms_template'] );
    }

    return $sanitized_input;
}
