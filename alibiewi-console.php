<?php
/**
 * Plugin Name:       Al-Ibiewi Studio Console
 * Plugin URI:        https://al-ibiewi.com.ng
 * Description:       A branded login, a clean client dashboard, client mode, white-label controls and a Claude-powered website assistant, for websites built by Al-Ibiewi Studio.
 * Version:           1.5.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Al-Ibiewi Studio
 * Author URI:        https://al-ibiewi.com.ng
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       alibiewi-console
 * Domain Path:       /languages
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

define( 'ALC_VERSION', '1.5.0' );
define( 'ALC_FILE', __FILE__ );
define( 'ALC_PATH', plugin_dir_path( __FILE__ ) );
define( 'ALC_URL', plugin_dir_url( __FILE__ ) );

require_once ALC_PATH . 'includes/class-alc-settings.php';
require_once ALC_PATH . 'includes/helpers.php';
require_once ALC_PATH . 'includes/class-alc-brand.php';
require_once ALC_PATH . 'includes/class-alc-admin-theme.php';
require_once ALC_PATH . 'includes/class-alc-login.php';
require_once ALC_PATH . 'includes/class-alc-login-guard.php';
require_once ALC_PATH . 'includes/class-alc-dashboard.php';
require_once ALC_PATH . 'includes/class-alc-branding.php';
require_once ALC_PATH . 'includes/class-alc-client-mode.php';
require_once ALC_PATH . 'includes/class-alc-ai-index.php';
require_once ALC_PATH . 'includes/class-alc-ai.php';

if ( is_admin() ) {
	require_once ALC_PATH . 'includes/class-alc-admin-settings.php';
}

/**
 * Boot every module once all plugins are loaded.
 */
function alc_boot() {
	alc_maybe_upgrade();
	ALC_Brand::init();
	ALC_Admin_Theme::init();
	ALC_Login::init();
	ALC_Login_Guard::init();
	ALC_Dashboard::init();
	ALC_Branding::init();
	ALC_Client_Mode::init();
	ALC_AI::init();

	if ( is_admin() ) {
		ALC_Admin_Settings::init();
	}
}
add_action( 'plugins_loaded', 'alc_boot' );

/**
 * 1.1 switched brand defaults to "match the website". Move untouched 1.0
 * installs over so they pick up the site's own look.
 */
function alc_maybe_upgrade() {
	if ( (int) get_option( 'alc_schema', 1 ) >= 2 ) {
		return;
	}
	$saved = get_option( ALC_Settings::OPTION );
	if ( is_array( $saved ) ) {
		if ( isset( $saved['accent'] ) && '#C44332' === strtoupper( (string) $saved['accent'] ) ) {
			$saved['accent'] = '';
		}
		if ( isset( $saved['panel_bg'] ) && '#080808' === strtoupper( (string) $saved['panel_bg'] ) ) {
			$saved['panel_bg'] = '';
		}
		if ( isset( $saved['font'] ) && 'bricolage' === $saved['font'] ) {
			$saved['font'] = 'site';
		}
		update_option( ALC_Settings::OPTION, $saved );
		ALC_Settings::flush();
	}
	update_option( 'alc_schema', 2 );
}

/**
 * Load translations on init, as WordPress 6.7+ expects.
 */
function alc_load_textdomain() {
	load_plugin_textdomain( 'alibiewi-console', false, dirname( plugin_basename( ALC_FILE ) ) . '/languages' );
}
add_action( 'init', 'alc_load_textdomain' );

/**
 * Store defaults on first activation without touching existing settings.
 */
function alc_activate() {
	if ( false === get_option( ALC_Settings::OPTION ) ) {
		add_option( ALC_Settings::OPTION, ALC_Settings::defaults() );
	}
}
register_activation_hook( __FILE__, 'alc_activate' );
