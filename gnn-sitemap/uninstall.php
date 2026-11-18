<?php
if ( ! defined('WP_UNINSTALL_PLUGIN') ) { exit; }

delete_option( 'gnn_sitemap_settings' );
delete_transient( 'gnn_sitemap_health' );
delete_transient( 'gnn_sitemap_gh_release' );

if ( is_multisite() ) {
    delete_site_option( 'gnn_sitemap_network_defaults' );
    $site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( (int) $site_id );
        delete_option( 'gnn_sitemap_settings' );
        delete_transient( 'gnn_sitemap_health' );
        restore_current_blog();
    }
}
