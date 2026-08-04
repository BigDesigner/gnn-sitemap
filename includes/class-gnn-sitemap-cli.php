<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * WP-CLI: wp gnn-sitemap <command>
 */
class GNN_Sitemap_CLI {

    /**
     * Flushes rewrite rules (same effect as "Permalinks -> Save").
     *
     * ## EXAMPLES
     *     wp gnn-sitemap flush
     */
    public function flush( $args, $assoc_args ) {
        flush_rewrite_rules( true );
        WP_CLI::success( 'Rewrite rules flushed.' );
    }

    /**
     * Lists the current settings (post types, taxonomies, users, excluded ids) as a table.
     *
     * ## EXAMPLES
     *     wp gnn-sitemap status
     */
    public function status( $args, $assoc_args ) {
        $settings = gnn_sitemap_get_settings();

        $rows = array(
            array( 'setting' => 'post_types', 'value' => implode( ', ', $settings['post_types'] ) ),
            array( 'setting' => 'taxonomies', 'value' => implode( ', ', $settings['taxonomies'] ) ),
            array( 'setting' => 'include_users', 'value' => $settings['include_users'] ? 'yes' : 'no' ),
            array( 'setting' => 'excluded_ids', 'value' => implode( ', ', $settings['excluded_ids'] ) ),
            array( 'setting' => 'core_sitemaps_enabled', 'value' => gnn_sitemap_core_sitemaps_enabled() ? 'yes' : 'no' ),
        );

        WP_CLI\Utils\format_items( 'table', $rows, array( 'setting', 'value' ) );
    }
}

if ( defined('WP_CLI') && WP_CLI ) {
    WP_CLI::add_command( 'gnn-sitemap', 'GNN_Sitemap_CLI' );
}
