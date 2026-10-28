<?php
/**
 * Removes the plugin's data on uninstall. The download plugins' logs are left untouched.
 *
 * @package DownloadGuard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'dlgd_settings' );
delete_metadata( 'user', 0, 'dlgd_blocked', '', true );
delete_metadata( 'user', 0, 'dlgd_unblocked', '', true );
