# Webline WooCommerce SMS

Webline WooCommerce SMS is a WordPress plugin that sends SMS notifications for WooCommerce order status changes. It integrates with the Webline Africa SMS API to provide seamless communication with your customers.

## Features

- Sends SMS notifications when WooCommerce order statuses change.
- Customizable SMS templates with placeholders for order details.
- Easy-to-configure settings for API key, sender ID, and SMS templates.
- Supports WooCommerce order management.

## Requirements

- WordPress 5.0 or higher.
- WooCommerce 3.0 or higher.
- A Webline Africa account with an API key.

## Installation

1. Download the plugin files and upload them to the `/wp-content/plugins/webline-wc-sms` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **WooCommerce > Webline SMS** to configure the plugin settings.

## Configuration

1. Go to **WooCommerce > Webline SMS** in the WordPress admin dashboard.
2. Enter your Webline Africa API key and sender ID.
3. Customize the SMS template using the following placeholders:
   - `{order_id}`: The WooCommerce order ID.
   - `{old_status}`: The previous order status.
   - `{new_status}`: The updated order status.
4. Save your settings.

## Usage

Once configured, the plugin will automatically send SMS notifications to customers when their order status changes. The SMS content will be based on the template you set in the plugin settings.

## Example SMS Template

```
Your order #{order_id} status has changed from {old_status} to {new_status}.
```

## Support

For support, please visit [Webline Africa](https://webline.africa) or contact us at support@webline.africa.

## License

This plugin is licensed under the GPL-2.0+ license. See the [LICENSE](http://www.gnu.org/licenses/gpl-2.0.txt) file for more details.
