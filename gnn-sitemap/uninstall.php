<?php
if ( ! defined('WP_UNINSTALL_PLUGIN') ) { exit; }

delete_option( 'gnn_sitemap_settings' );

if ( is_multisite() ) {
    global $wpdb;
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        delete_option( 'gnn_sitemap_settings' );
        restore_current_blog();
    }
}
