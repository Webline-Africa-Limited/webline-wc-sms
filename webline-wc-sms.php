<?php
/**
 * Plugin Name: Webline WooCommerce SMS
 * Plugin URI: https://webline.africa
 * Description: Sends SMS notifications for WooCommerce order status changes.
 * Version: 1.0.0
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
 *
 * @return string|WP_Error The API response or a WP_Error object on failure.
 */
function webline_wc_send_sms( $phone, $message ) {
	$options = get_option( 'webline_wc_sms_settings' );
    $senderid = isset( $options['sender_id'] ) ? $options['sender_id'] : 'Webline'; // Use setting, default to 'Webline'
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

    $response = curl_exec( $curl );

    if ( curl_errno( $curl ) ) {
        $error_message = curl_error( $curl );
        curl_close( $curl );
        return new WP_Error( 'curl_error', $error_message, $error_message );
    }

    curl_close( $curl );
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

    // Create the message.
    $message = sprintf( __( 'Your order #%d status has changed from %s to %s.', 'webline-wc-sms' ), $order_id, $old_status, $new_status );

    // Send the SMS.
    $result = webline_wc_send_sms( $phone, $message );

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

    return $sanitized_input;
}
