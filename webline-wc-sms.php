<?php
/**
 * Plugin Name: Webline WC SMS
 * Plugin URI: https://webline.africa
 * Description: Sends SMS notifications for WooCommerce order status changes.
 * Version: 1.0.0
 * Author: Webline Africa
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

	// Send SMS to admin if enabled and phone number is set
    $options = get_option( 'webline_wc_sms_settings' );
    $admin_phone = isset( $options['admin_phone'] ) ? $options['admin_phone'] : '';
	$admin_notifications_enabled = isset($options['enable_admin_notifications']) ? $options['enable_admin_notifications'] : false;

    if ( $admin_notifications_enabled && ! empty( $admin_phone ) ) {
        $admin_message = sprintf( __( 'Order #%d status changed from %s to %s.', 'webline-wc-sms' ), $order_id, $old_status, $new_status );
        $admin_result = webline_wc_send_sms( $admin_phone, $admin_message );

        if ( is_wp_error( $admin_result ) ) {
            error_log( 'Admin SMS Sending Failed: ' . $admin_result->get_error_message() );
        } else {
            error_log( 'Admin SMS Sent Successfully: ' . $admin_result );
        }
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
        <form action="options.php" method="post">
            <?php
            settings_fields( 'webline_wc_sms_settings' ); // Output settings fields.
            do_settings_sections( 'webline-wc-sms-settings' ); // Output settings sections.
            submit_button(); // Output submit button.
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
        'webline_wc_sms_settings', // Option group.
        'webline_wc_sms_settings', // Option name.
        'webline_wc_sms_sanitize_settings' // Sanitize callback.
    );

    // Add settings section.
    add_settings_section(
        'webline_wc_sms_general_section', // Section ID.
        __( 'General Settings', 'webline-wc-sms' ), // Section title.
        null, // Callback function (not needed for this section).
        'webline-wc-sms-settings' // Page slug.
    );

    // Add API Key field.
    add_settings_field(
        'webline_wc_sms_api_key', // Field ID.
        __( 'API Key', 'webline-wc-sms' ), // Field title.
        'webline_wc_sms_api_key_field_callback', // Callback function to render the field.
        'webline-wc-sms-settings', // Page slug.
        'webline_wc_sms_general_section' // Section ID.
    );

    // Add Sender ID field.
    add_settings_field(
        'webline_wc_sms_sender_id', // Field ID.
        __( 'Sender ID', 'webline-wc-sms' ), // Field title.
        'webline_wc_sms_sender_id_field_callback', // Callback function to render the field.
        'webline-wc-sms-settings', // Page slug.
        'webline_wc_sms_general_section' // Section ID.
    );

    // Add Admin Phone Number field.
    add_settings_field(
        'webline_wc_sms_admin_phone', // Field ID.
        __( 'Admin Phone Number', 'webline-wc-sms' ), // Field title.
        'webline_wc_sms_admin_phone_field_callback', // Callback function to render the field.
        'webline-wc-sms-settings', // Page slug.
        'webline_wc_sms_general_section' // Section ID.
    );

	// Add Enable Admin Notifications field.
    add_settings_field(
        'webline_wc_sms_enable_admin_notifications', // Field ID.
        __( 'Enable Admin Notifications', 'webline-wc-sms' ), // Field title.
        'webline_wc_sms_enable_admin_notifications_field_callback', // Callback function
        'webline-wc-sms-settings', // Page slug.
        'webline_wc_sms_general_section' // Section ID.
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
    <?php
}

/**
 * Render the Admin Phone Number field.
 *
 * @param array $args Arguments passed from add_settings_field().
 */
function webline_wc_sms_admin_phone_field_callback( $args ) {
    $options = get_option( 'webline_wc_sms_settings' );
    $admin_phone = isset( $options['admin_phone'] ) ? $options['admin_phone'] : '';
    ?>
    <input type="text" id="webline_wc_sms_admin_phone" name="webline_wc_sms_settings[admin_phone]" value="<?php echo esc_attr( $admin_phone ); ?>" class="regular-text">
	<p class="description"><?php _e( 'Enter the phone number to receive admin notifications (optional).', 'webline-wc-sms' ); ?></p>
    <?php
}

/**
 * Render the Enable Admin Notifications field.
 *
 * @param array $args
 */
function webline_wc_sms_enable_admin_notifications_field_callback($args){
	$options = get_option( 'webline_wc_sms_settings' );
    $enabled = isset( $options['enable_admin_notifications'] ) ? $options['enable_admin_notifications'] : '';
    ?>
    <input type="checkbox" id="webline_wc_sms_enable_admin_notifications" name="webline_wc_sms_settings[enable_admin_notifications]" value="1" <?php checked( 1, $enabled, true ); ?>>
    <label for="webline_wc_sms_enable_admin_notifications"><?php _e( 'Send SMS notifications to the admin when the order status changes.', 'webline-wc-sms' ); ?></label>
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

    if ( isset( $input['admin_phone'] ) ) {
        $sanitized_input['admin_phone'] = sanitize_text_field( $input['admin_phone'] );
    }

	if ( isset( $input['enable_admin_notifications'] ) ) {
        $sanitized_input['enable_admin_notifications'] = 1;
    } else {
		$sanitized_input['enable_admin_notifications'] = 0;
	}

    return $sanitized_input;
}
