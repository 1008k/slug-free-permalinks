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
