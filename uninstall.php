<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('ptid_permalink_settings');
