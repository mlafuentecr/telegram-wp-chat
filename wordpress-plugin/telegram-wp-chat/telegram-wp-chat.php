<?php
/**
 * Plugin Name: Telegram WP Chat
 * Plugin URI: https://example.com/
 * Description: Chat flotante para WordPress con aprobación por Telegram antes de iniciar la conversación.
 * Version: 1.0.0
 * Author: Codex
 * License: GPL-2.0-or-later
 * Text Domain: telegram-wp-chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TWC_PLUGIN_FILE', __FILE__ );
define( 'TWC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'TWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TWC_VERSION', '1.0.0' );

require_once TWC_PLUGIN_PATH . 'includes/class-twc-plugin.php';

register_activation_hook( __FILE__, array( 'TWC_Plugin', 'activate' ) );

TWC_Plugin::instance();
