<?php
/**
 * Removes plugin settings during uninstall.
 *
 * @package Slug_Free_Permalinks
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ptid_permalink_settings' );
delete_option( 'ptid_rewrite_marker_version' );
delete_option( 'ptid_rewrite_signature' );
delete_option( 'ptid_rewrite_hard_flush_pending' );
flush_rewrite_rules();
