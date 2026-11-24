<?php
/**
 * Clean up when the plugin is deleted from the Plugins screen.
 *
 * @package AlibiewiConsole
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'alc_settings' );
delete_option( 'alc_schema' );
delete_option( 'alc_client_role_hash' );

// AI assistant data.
foreach ( array( 'alc_ai_db', 'alc_ai_index_meta', 'alc_ai_log', 'alc_ai_usage', 'alc_ai_last_error' ) as $alc_option ) {
	delete_option( $alc_option );
}
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}alc_ai_chunks" );

// Leave the Site Owner role in place if anyone still has it, so nobody is locked out.
if ( get_role( 'alc_client' ) && ! get_users( array( 'role' => 'alc_client', 'number' => 1, 'fields' => 'ID' ) ) ) {
	remove_role( 'alc_client' );
}
delete_transient( 'alc_woo_month_sales' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_alc\_%' OR option_name LIKE '\_transient\_timeout\_alc\_%'" );
