<?php
/*
Plugin Name: GNN Sitemap
Description: Uses WordPress core sitemap infrastructure. Adds a /sitemap.xml alias and lets you choose which post types, taxonomies, and users are included from the admin panel.
Version: 1.0.2
Author: GNN
Requires at least: 5.5
Requires PHP: 7.4
Tested up to: 6.6
Text Domain: gnn-sitemap
Domain Path: /languages
*/

if ( ! defined('ABSPATH') ) { exit; }

const GNN_SITEMAP_OPT = 'gnn_sitemap_settings';

// GNN plugin standard: file/version constants (used by action links and updater).
define( 'GNN_SITEMAP_FILE', __FILE__ );
define( 'GNN_SITEMAP_VERSION', trim( (string) @file_get_contents( __DIR__ . '/VERSION' ) ) ?: '1.0.2' );

add_action( 'init', function () {
    load_plugin_textdomain( 'gnn-sitemap', false, dirname( plugin_basename( GNN_SITEMAP_FILE ) ) . '/languages' );
}, 0 );

require_once __DIR__ . '/includes/class-gnn-sitemap-updater.php';
add_action( 'init', function () {
    new GNN_Sitemap_Updater( GNN_SITEMAP_FILE, GNN_SITEMAP_VERSION, 'BigDesigner', 'gnn-sitemap' );
} );

if ( defined('WP_CLI') && WP_CLI ) {
    require_once __DIR__ . '/includes/class-gnn-sitemap-cli.php';
}

/** ---------- Helpers ---------- */

/**
 * Forces a fresh sitemap: flushes rewrite rules and, when detected, purges
 * well-known caching plugins/hosts so a stale or previously-broken sitemap
 * response (e.g. mangled by a minifier) isn't served from cache. Shared by
 * the "Force Regenerate" button and the WP-CLI `flush` command.
 */
function gnn_sitemap_force_regenerate() {
    flush_rewrite_rules( true );

    if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
    if ( function_exists( 'w3tc_flush_all' ) ) { w3tc_flush_all(); }
    if ( function_exists( 'wp_cache_clear_cache' ) ) { wp_cache_clear_cache(); }
    if ( function_exists( 'litespeed_purge_all' ) ) { litespeed_purge_all(); }
    if ( class_exists( 'WpeCommon' ) ) {
        if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) { WpeCommon::purge_varnish_cache(); }
        if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) { WpeCommon::purge_memcached(); }
    }

    do_action( 'gnn_sitemap_force_regenerate' );
}

function gnn_sitemap_safe_array_of_names( $arr ) {
    // Core may return a flat array ["post","page"] or an assoc array ["post"=>obj, "page"=>obj].
    if ( ! is_array( $arr ) ) { return array(); }
    $keys     = array_keys( $arr );
    $is_assoc = array_keys( $keys ) !== $keys; // rough check
    if ( $is_assoc ) {
        // assoc: keys are the slugs
        return array_values( array_map( 'sanitize_key', array_keys( $arr ) ) );
    }
    // flat array
    return array_values( array_map( 'sanitize_key', $arr ) );
}

/**
 * On multisite, if a network admin has saved a default template (see
 * gnn_sitemap_render_network_settings_page), use it as the base for sites
 * that have not saved their own settings yet.
 */
function gnn_sitemap_network_default_settings() {
    if ( ! is_multisite() ) { return null; }
    $net = get_site_option( 'gnn_sitemap_network_defaults' );
    return is_array( $net ) ? $net : null;
}

function gnn_sitemap_default_settings() {
    $pts_all = get_post_types( array( 'public' => true ), 'names' );
    $tax_all = get_taxonomies( array( 'public' => true ), 'names' );
    $network = gnn_sitemap_network_default_settings();

    if ( $network ) {
        return array(
            'post_types'    => array_values( array_intersect( (array) ( $network['post_types'] ?? $pts_all ), $pts_all ) ),
            'taxonomies'    => array_values( array_intersect( (array) ( $network['taxonomies'] ?? $tax_all ), $tax_all ) ),
            'include_users' => isset( $network['include_users'] ) ? (int) (bool) $network['include_users'] : 1,
            'excluded_ids'  => array(),
        );
    }

    // Default: every public post type & taxonomy + users enabled.
    return array(
        'post_types'    => array_values( $pts_all ),
        'taxonomies'    => array_values( $tax_all ),
        'include_users' => 1,
        'excluded_ids'  => array(),
    );
}

function gnn_sitemap_get_settings() {
    $saved    = get_option( GNN_SITEMAP_OPT, array() );
    $defaults = gnn_sitemap_default_settings();
    $settings = wp_parse_args( is_array($saved) ? $saved : array(), $defaults );

    // Whitelist: only currently-registered public post types/taxonomies survive.
    $valid_pts = get_post_types( array( 'public' => true ), 'names' );
    $valid_tx  = get_taxonomies( array( 'public' => true ), 'names' );

    $settings['post_types']    = array_values( array_intersect( array_map('sanitize_key',(array)$settings['post_types']), $valid_pts ) );
    $settings['taxonomies']    = array_values( array_intersect( array_map('sanitize_key',(array)$settings['taxonomies']), $valid_tx ) );
    $settings['include_users'] = ! empty( $settings['include_users'] ) ? 1 : 0;
    $settings['excluded_ids']  = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $settings['excluded_ids'] ?? array() ) ) ) ) );

    return $settings;
}

/** ---------- Conflict detection with other plugins ---------- */

/**
 * The core wp-sitemaps infrastructure can be turned off by another plugin
 * (Yoast, Rank Math, AIOSEO, SEOPress, etc.) via the `wp_sitemaps_enabled`
 * filter. When that happens, redirecting /sitemap.xml -> /wp-sitemap.xml
 * would just produce a 404, so we skip registering the alias entirely.
 */
function gnn_sitemap_core_sitemaps_enabled() {
    return (bool) apply_filters( 'wp_sitemaps_enabled', true );
}

/**
 * Detects well-known SEO plugins. These typically ship their own sitemap
 * infrastructure and disable the core one; we surface their name (and a
 * link to their settings screen) so the admin can investigate.
 */
function gnn_sitemap_detect_conflicting_plugins() {
    $found = array();

    if ( defined('WPSEO_VERSION') ) {
        $found['Yoast SEO'] = 'wpseo_dashboard';
    }
    if ( class_exists('RankMath') || defined('RANK_MATH_VERSION') ) {
        $found['Rank Math'] = 'rank-math';
    }
    if ( defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin') ) {
        $found['All in One SEO'] = 'aioseo';
    }
    if ( defined('SEOPRESS_VERSION') ) {
        $found['SEOPress'] = 'seopress-sitemap';
    }
    if ( defined('SQUIRRLY_WP_FILE') ) {
        $found['Squirrly SEO'] = 'squirrly';
    }

    return $found;
}

/**
 * Builds a link to the conflicting plugin's settings screen when a known
 * slug is available; falls back to the generic Plugins screen otherwise.
 */
function gnn_sitemap_conflicting_plugin_link( $plugin_name, $page_slug ) {
    $url = $page_slug ? admin_url( 'admin.php?page=' . $page_slug ) : admin_url( 'plugins.php' );
    return '<a href="' . esc_url( $url ) . '">' . esc_html( $plugin_name ) . '</a>';
}

add_action( 'admin_notices', function () {
    if ( ! current_user_can('manage_options') ) { return; }

    $conflicts     = gnn_sitemap_detect_conflicting_plugins();
    $core_disabled = ! gnn_sitemap_core_sitemaps_enabled();

    if ( ! $conflicts && ! $core_disabled ) { return; }

    $links = array();
    foreach ( $conflicts as $name => $slug ) {
        $links[] = gnn_sitemap_conflicting_plugin_link( $name, $slug );
    }
    $links_html = implode( ', ', $links );

    $prefix = '<strong>' . esc_html__( 'GNN Sitemap:', 'gnn-sitemap' ) . '</strong> ';

    if ( $core_disabled ) {
        $msg = $prefix . esc_html__( 'The core sitemap infrastructure was disabled by another plugin, so the /sitemap.xml alias redirect is disabled.', 'gnn-sitemap' );
        if ( $links_html ) {
            $msg .= ' ' . sprintf(
                /* translators: %s: comma-separated list of plugin name links */
                esc_html__( 'Detected: %s.', 'gnn-sitemap' ),
                $links_html
            );
        }
    } else {
        $msg = $prefix . sprintf(
            /* translators: %s: comma-separated list of plugin name links */
            esc_html__( 'Another sitemap-generating plugin was detected (%s). If you notice a conflict (duplicate sitemap, robots.txt line, etc.), you can disable this plugin.', 'gnn-sitemap' ),
            $links_html
        );
    }

    echo '<div class="notice notice-warning is-dismissible"><p>' . $msg . '</p></div>';
} );

/** ---------- /sitemap.xml alias (rewrite + redirect) ---------- */

add_action( 'init', function () {
    if ( ! gnn_sitemap_alias_should_run() ) { return; }
    add_rewrite_rule( '^sitemap\.xml$', 'index.php?gnn_sitemap_redirect=1', 'top' );
}, 1 );

function gnn_sitemap_alias_should_run() {
    return gnn_sitemap_core_sitemaps_enabled();
}

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'gnn_sitemap_redirect';
    return $vars;
} );

add_action( 'template_redirect', function () {
    if ( get_query_var( 'gnn_sitemap_redirect' ) ) {
        if ( ! gnn_sitemap_alias_should_run() ) { return; }
        // Only accept GET/HEAD.
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        if ( ! in_array( $method, array('GET','HEAD'), true ) ) {
            status_header(405);
            header('Allow: GET, HEAD');
            exit;
        }
        // Avoid a headers-already-sent conflict.
        if ( ! headers_sent() ) {
            wp_safe_redirect( home_url( '/wp-sitemap.xml' ), 301, 'GNN Sitemap' );
        }
        exit;
    }
}, 0 );

/** ---------- Filter core providers based on settings ---------- */

/* Post types */
add_filter( 'wp_sitemaps_post_types', function( $core_post_types ) {
    // Safely reduce core's return value to an array of slug names.
    $core_names = gnn_sitemap_safe_array_of_names( $core_post_types );
    if ( empty( $core_names ) ) { return $core_post_types; }

    $settings = gnn_sitemap_get_settings();
    $allowed  = array_map( 'sanitize_key', (array) $settings['post_types'] );

    // Intersection: what core lists + what the admin allowed.
    $final = array_values( array_intersect( $core_names, $allowed ) );

    // Preserve core's original return shape:
    // - if core returned an assoc array, return an assoc array.
    if ( is_array($core_post_types) && array_keys($core_post_types) !== range(0, count($core_post_types)-1) ) {
        // assoc: slug => object
        $out = array();
        foreach ( $final as $slug ) {
            if ( isset( $core_post_types[ $slug ] ) ) {
                $out[ $slug ] = $core_post_types[ $slug ];
            }
        }
        return $out;
    }

    // flat array in, flat array out
    return $final;
}, 10, 1 );

/* Taxonomies */
add_filter( 'wp_sitemaps_taxonomies', function( $core_taxonomies ) {
    $core_names = gnn_sitemap_safe_array_of_names( $core_taxonomies );
    if ( empty( $core_names ) ) { return $core_taxonomies; }

    $settings = gnn_sitemap_get_settings();
    $allowed  = array_map( 'sanitize_key', (array) $settings['taxonomies'] );

    $final = array_values( array_intersect( $core_names, $allowed ) );

    if ( is_array($core_taxonomies) && array_keys($core_taxonomies) !== range(0, count($core_taxonomies)-1) ) {
        $out = array();
        foreach ( $final as $slug ) {
            if ( isset( $core_taxonomies[ $slug ] ) ) {
                $out[ $slug ] = $core_taxonomies[ $slug ];
            }
        }
        return $out;
    }
    return $final;
}, 10, 1 );

/* Users provider (author archives) */
add_filter( 'wp_sitemaps_add_provider', function( $provider, $name ) {
    // $name e.g. 'users','posts','taxonomies'...
    if ( 'users' === $name ) {
        $settings = gnn_sitemap_get_settings();
        if ( empty( $settings['include_users'] ) ) {
            // PHP 8 type-compatible: return NULL instead of false (core treats NULL as "no provider").
            return null;
        }
    }
    return $provider;
}, 10, 2 );

/* Exclude by ID: strip specific IDs from every post type query */
add_filter( 'wp_sitemaps_posts_query_args', function( $args, $post_type ) {
    $settings = gnn_sitemap_get_settings();
    if ( empty( $settings['excluded_ids'] ) ) { return $args; }

    $not_in = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
    $args['post__not_in'] = array_values( array_unique( array_merge( $not_in, $settings['excluded_ids'] ) ) );

    return $args;
}, 10, 2 );

/** ---------- Sitemap line in robots.txt (optional) ---------- */
add_filter( 'robots_txt', function( $output, $public ) {
    if ( '0' === get_option('blog_public') ) { return $output; }
    if ( ! gnn_sitemap_core_sitemaps_enabled() ) { return $output; }
    $line = 'Sitemap: ' . home_url( '/sitemap.xml' );
    // Avoid duplicates.
    if ( strpos( (string) $output, $line ) === false ) {
        $output = rtrim( (string) $output ) . "\n" . $line . "\n";
    }
    return $output;
}, 10, 2 );

/** ---------- Admin: GNN Sitemap (top-level menu, ADR #9 - position 79.107) ---------- */
add_action( 'admin_menu', function () {
    // GNN menu position standard: 79.107 (see GNN Plugin Standard). Per ADR #9 the
    // position is always a quoted string literal (avoids float-truncation collisions).
    add_menu_page(
        __( 'GNN Sitemap - Settings', 'gnn-sitemap' ),
        'GNN Sitemap',
        'manage_options',
        'sitemap-settings',
        'gnn_sitemap_render_settings_page',
        'dashicons-admin-site-alt3',
        '79.107'
    );
} );

/** ---------- Plugin row action links: Donate / Settings / Check Updates ---------- */
add_filter( 'plugin_action_links', function ( $links, $file ) {
    if ( $file !== plugin_basename( GNN_SITEMAP_FILE ) ) {
        return $links;
    }

    $donate_link = '<a href="https://buymeacoffee.com/bigdesigner" target="_blank" style="font-weight:bold; color:#d63638;">'
        . esc_html__( 'Donate', 'gnn-sitemap' ) . '</a>';

    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=sitemap-settings' ) ) . '">'
        . esc_html__( 'Settings', 'gnn-sitemap' ) . '</a>';

    // Nonce'd flag; caught by the updater class on admin_init, which clears the transient.
    $update_url  = wp_nonce_url( admin_url( 'plugins.php?gnn_sitemap_check_update=1' ), 'gnn_sitemap_manual_update' );
    $update_link = '<a href="' . esc_url( $update_url ) . '">' . esc_html__( 'Check Updates', 'gnn-sitemap' ) . '</a>';

    array_unshift( $links, $donate_link, $settings_link, $update_link );
    return $links;
}, 10, 2 );

/**
 * Toggle switch CSS — shared by both the site settings page and the network
 * settings page. WP core admin screens don't ship a ready-made toggle-switch
 * component (that CSS only comes with the block editor / @wordpress/components).
 * Rather than enqueueing a separate stylesheet, this small page-scoped inline
 * block is used instead: no external file/library, the plugin stays lightweight.
 */
function gnn_sitemap_toggle_styles() {
    ?>
    <style>
        .gnn-sitemap-wrap .gnn-toggle { display: flex; align-items: center; gap: 8px; margin: 6px 0; cursor: pointer; }
        .gnn-sitemap-wrap .gnn-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
        .gnn-sitemap-wrap .gnn-toggle-slider { position: relative; width: 36px; height: 20px; background: #c3c4c7; border-radius: 10px; transition: background-color .15s ease-in-out; flex-shrink: 0; }
        .gnn-sitemap-wrap .gnn-toggle-slider::before { content: ""; position: absolute; width: 16px; height: 16px; left: 2px; top: 2px; background: #fff; border-radius: 50%; transition: transform .15s ease-in-out; }
        .gnn-sitemap-wrap .gnn-toggle input:checked + .gnn-toggle-slider { background: #2271b1; }
        .gnn-sitemap-wrap .gnn-toggle input:checked + .gnn-toggle-slider::before { transform: translateX(16px); }
        .gnn-sitemap-wrap .gnn-toggle input:focus + .gnn-toggle-slider { box-shadow: 0 0 0 1px #fff, 0 0 0 3px #2271b1; }
        .gnn-sitemap-wrap fieldset { margin-top: 8px; }
    </style>
    <?php
}

function gnn_sitemap_render_settings_page() {
    if ( ! current_user_can('manage_options') ) { return; }

    $available_pts = get_post_types( array( 'public' => true ), 'objects' );
    $available_tx  = get_taxonomies( array( 'public' => true ), 'objects' );
    $settings      = gnn_sitemap_get_settings();

    if ( isset($_POST['gnn_sitemap_regenerate']) && check_admin_referer('gnn_sitemap_regenerate') ) {
        gnn_sitemap_force_regenerate();
        echo '<div class="updated notice"><p>' . esc_html__( 'Sitemap regenerated: rewrite rules and any detected page cache were flushed.', 'gnn-sitemap' ) . '</p></div>';
    }

    if ( isset($_POST['gnn_sitemap_submit']) && check_admin_referer('gnn_sitemap_save') ) {
        $new                  = array();
        $new['post_types']    = isset($_POST['post_types']) ? array_map('sanitize_key', (array) $_POST['post_types']) : array();
        $new['taxonomies']    = isset($_POST['taxonomies']) ? array_map('sanitize_key', (array) $_POST['taxonomies']) : array();
        $new['include_users'] = ! empty($_POST['include_users']) ? 1 : 0;

        // Whitelist
        $new['post_types'] = array_values( array_intersect( $new['post_types'], array_keys( (array) $available_pts ) ) );
        $new['taxonomies'] = array_values( array_intersect( $new['taxonomies'], array_keys( (array) $available_tx ) ) );

        // Reduce a "12, 45\n78" style comma/newline/space-separated ID list to integers.
        $raw_ids             = isset($_POST['excluded_ids']) ? (string) wp_unslash($_POST['excluded_ids']) : '';
        $ids                 = preg_split( '/[\s,]+/', $raw_ids, -1, PREG_SPLIT_NO_EMPTY );
        $new['excluded_ids'] = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

        update_option( GNN_SITEMAP_OPT, $new, false ); // autoload=false
        $settings = gnn_sitemap_get_settings();
        echo '<div class="updated notice"><p>' . esc_html__( 'Settings saved.', 'gnn-sitemap' ) . '</p></div>';
    }

    $sitemap_url    = esc_url( home_url('/wp-sitemap.xml') );
    $alias_url      = esc_url( home_url('/sitemap.xml') );
    $alias_disabled = ! gnn_sitemap_alias_should_run();
    gnn_sitemap_toggle_styles();
    ?>
    <div class="wrap gnn-sitemap-wrap">
      <h1>GNN Sitemap</h1>
      <p><?php esc_html_e( "Uses core's wp-sitemap.xml output. A request to /sitemap.xml is redirected with a 301.", 'gnn-sitemap' ); ?></p>

      <table class="widefat striped" style="max-width:860px;margin-top:8px;">
        <tbody>
          <tr>
            <th scope="row"><?php esc_html_e( 'Core Sitemap', 'gnn-sitemap' ); ?></th>
            <td><a href="<?php echo $sitemap_url; ?>" target="_blank" rel="noopener"><?php echo $sitemap_url; ?></a></td>
          </tr>
          <tr>
            <th scope="row"><?php esc_html_e( 'Alias', 'gnn-sitemap' ); ?></th>
            <td>
              <?php if ( $alias_disabled ) : ?>
                <em><?php esc_html_e( 'Disabled', 'gnn-sitemap' ); ?></em> (<?php esc_html_e( 'core sitemap was disabled by another plugin', 'gnn-sitemap' ); ?>)
              <?php else : ?>
                <a href="<?php echo $alias_url; ?>" target="_blank" rel="noopener"><?php echo $alias_url; ?></a> (301 → wp-sitemap.xml)
              <?php endif; ?>
            </td>
          </tr>
        </tbody>
      </table>

      <form method="post" style="margin-top:12px;">
        <?php wp_nonce_field('gnn_sitemap_regenerate'); ?>
        <button type="submit" name="gnn_sitemap_regenerate" value="1" class="button"><?php esc_html_e( 'Force Regenerate', 'gnn-sitemap' ); ?></button>
        <span style="margin-left:8px;color:#555;"><?php esc_html_e( "If the sitemap or its stylesheet looks broken or stale (e.g. an XML parsing error), use this to flush rewrite rules and any detected page cache.", 'gnn-sitemap' ); ?></span>
      </form>

      <h2 style="margin-top:16px;"><?php esc_html_e( 'Summary', 'gnn-sitemap' ); ?></h2>
      <table class="widefat striped" style="max-width:860px;">
        <thead>
          <tr><th><?php esc_html_e( 'Source', 'gnn-sitemap' ); ?></th><th><?php esc_html_e( 'Included item count', 'gnn-sitemap' ); ?></th></tr>
        </thead>
        <tbody>
          <?php foreach ( $available_pts as $slug => $obj ):
              if ( ! in_array( $slug, (array) $settings['post_types'], true ) ) { continue; }
              $counts = wp_count_posts( $slug );
              $count  = isset( $counts->publish ) ? (int) $counts->publish : 0;
          ?>
          <tr>
            <td><?php echo esc_html( $obj->labels->name ); ?></td>
            <td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
          </tr>
          <?php endforeach; ?>
          <?php foreach ( $available_tx as $slug => $obj ):
              if ( ! in_array( $slug, (array) $settings['taxonomies'], true ) ) { continue; }
              $count = (int) wp_count_terms( array( 'taxonomy' => $slug, 'hide_empty' => true ) );
          ?>
          <tr>
            <td><?php echo esc_html( $obj->labels->name ); ?></td>
            <td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if ( ! empty( $settings['include_users'] ) ):
              $user_count = count_users();
              $count      = isset( $user_count['total_users'] ) ? (int) $user_count['total_users'] : 0;
          ?>
          <tr>
            <td><?php esc_html_e( 'Authors (users)', 'gnn-sitemap' ); ?></td>
            <td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
          </tr>
          <?php endif; ?>
          <?php if ( ! empty( $settings['excluded_ids'] ) ): ?>
          <tr>
            <td><?php esc_html_e( 'Excluded ID count', 'gnn-sitemap' ); ?></td>
            <td><?php echo esc_html( number_format_i18n( count( $settings['excluded_ids'] ) ) ); ?></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <form method="post" style="margin-top:16px;max-width:860px;">
        <?php wp_nonce_field('gnn_sitemap_save'); ?>

        <h2><?php esc_html_e( 'Post Type Selection', 'gnn-sitemap' ); ?></h2>
        <p><?php esc_html_e( 'Choose which post types to include.', 'gnn-sitemap' ); ?></p>
        <fieldset>
          <?php foreach ( $available_pts as $slug => $obj ): ?>
            <label class="gnn-toggle">
              <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($slug); ?>"
                <?php checked( in_array( $slug, (array) $settings['post_types'], true ) ); ?> />
              <span class="gnn-toggle-slider"></span>
              <span><?php echo esc_html( $obj->labels->name . " ($slug)" ); ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <h2 style="margin-top:16px;"><?php esc_html_e( 'Taxonomy Selection', 'gnn-sitemap' ); ?></h2>
        <p><?php esc_html_e( 'Choose which taxonomies to include (category, tag, etc.).', 'gnn-sitemap' ); ?></p>
        <fieldset>
          <?php foreach ( $available_tx as $slug => $obj ): ?>
            <label class="gnn-toggle">
              <input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr($slug); ?>"
                <?php checked( in_array( $slug, (array) $settings['taxonomies'], true ) ); ?> />
              <span class="gnn-toggle-slider"></span>
              <span><?php echo esc_html( $obj->labels->name . " ($slug)" ); ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <h2 style="margin-top:16px;"><?php esc_html_e( 'Users', 'gnn-sitemap' ); ?></h2>
        <label class="gnn-toggle">
          <input type="checkbox" name="include_users" value="1" <?php checked( ! empty( $settings['include_users'] ) ); ?> />
          <span class="gnn-toggle-slider"></span>
          <span><?php esc_html_e( 'Include author archives (users)', 'gnn-sitemap' ); ?></span>
        </label>

        <h2 style="margin-top:16px;"><?php esc_html_e( 'Exclude by ID', 'gnn-sitemap' ); ?></h2>
        <p>
          <?php
          printf(
              /* translators: %s: example ID list */
              esc_html__( 'Post/page IDs to exclude from the sitemap, separated by commas, spaces, or newlines (e.g. %s).', 'gnn-sitemap' ),
              '<code>12, 45, 78</code>'
          );
          ?>
        </p>
        <textarea name="excluded_ids" rows="3" class="large-text code" placeholder="12, 45, 78"><?php echo esc_textarea( implode( ', ', $settings['excluded_ids'] ) ); ?></textarea>

        <p style="margin-top:16px;">
          <button type="submit" name="gnn_sitemap_submit" class="button button-primary"><?php esc_html_e( 'Save', 'gnn-sitemap' ); ?></button>
        </p>
      </form>

      <p style="margin-top:8px;color:#555;">
        <?php
        printf(
            /* translators: %s: "Settings -> Permalinks" screen name */
            esc_html__( "Note: clicking \"Save\" on the %s screen usually fixes it if the alias isn't showing up (rewrite flush).", 'gnn-sitemap' ),
            '<em>' . esc_html__( 'Settings → Permalinks', 'gnn-sitemap' ) . '</em>'
        );
        ?>
      </p>
    </div>
    <?php
}

/** ---------- Network (multisite) default settings ---------- */
add_action( 'network_admin_menu', function () {
    add_menu_page(
        __( 'GNN Sitemap - Network Defaults', 'gnn-sitemap' ),
        'GNN Sitemap',
        'manage_network_options',
        'sitemap-settings-network',
        'gnn_sitemap_render_network_settings_page',
        'dashicons-admin-site-alt3',
        '79.107' // GNN menu position standard: 79.107 (network admin uses a separate $menu array, so no collision).
    );
} );

function gnn_sitemap_render_network_settings_page() {
    if ( ! current_user_can('manage_network_options') ) { return; }

    $available_pts = get_post_types( array( 'public' => true ), 'objects' );
    $available_tx  = get_taxonomies( array( 'public' => true ), 'objects' );
    $saved          = get_site_option( 'gnn_sitemap_network_defaults', array() );
    $settings       = wp_parse_args(
        is_array( $saved ) ? $saved : array(),
        array(
            'post_types'    => array_keys( $available_pts ),
            'taxonomies'    => array_keys( $available_tx ),
            'include_users' => 1,
        )
    );

    if ( isset($_POST['gnn_sitemap_network_submit']) && check_admin_referer('gnn_sitemap_network_save') ) {
        $new                  = array();
        $new['post_types']    = isset($_POST['post_types']) ? array_map('sanitize_key', (array) $_POST['post_types']) : array();
        $new['taxonomies']    = isset($_POST['taxonomies']) ? array_map('sanitize_key', (array) $_POST['taxonomies']) : array();
        $new['include_users'] = ! empty($_POST['include_users']) ? 1 : 0;

        $new['post_types'] = array_values( array_intersect( $new['post_types'], array_keys( (array) $available_pts ) ) );
        $new['taxonomies'] = array_values( array_intersect( $new['taxonomies'], array_keys( (array) $available_tx ) ) );

        update_site_option( 'gnn_sitemap_network_defaults', $new );
        $settings = $new;
        echo '<div class="updated notice"><p>' . esc_html__( 'Network defaults saved.', 'gnn-sitemap' ) . '</p></div>';
    }

    gnn_sitemap_toggle_styles();
    ?>
    <div class="wrap gnn-sitemap-wrap">
      <h1>GNN Sitemap — <?php esc_html_e( 'Network Defaults', 'gnn-sitemap' ); ?></h1>
      <p><?php esc_html_e( 'This template only applies as the default for sites that have not saved their own settings yet; each site can still override it from its own Settings screen.', 'gnn-sitemap' ); ?></p>

      <form method="post" style="margin-top:16px;max-width:860px;">
        <?php wp_nonce_field('gnn_sitemap_network_save'); ?>

        <h2><?php esc_html_e( 'Post Type Selection', 'gnn-sitemap' ); ?></h2>
        <fieldset>
          <?php foreach ( $available_pts as $slug => $obj ): ?>
            <label class="gnn-toggle">
              <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($slug); ?>"
                <?php checked( in_array( $slug, (array) $settings['post_types'], true ) ); ?> />
              <span class="gnn-toggle-slider"></span>
              <span><?php echo esc_html( $obj->labels->name . " ($slug)" ); ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <h2 style="margin-top:16px;"><?php esc_html_e( 'Taxonomy Selection', 'gnn-sitemap' ); ?></h2>
        <fieldset>
          <?php foreach ( $available_tx as $slug => $obj ): ?>
            <label class="gnn-toggle">
              <input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr($slug); ?>"
                <?php checked( in_array( $slug, (array) $settings['taxonomies'], true ) ); ?> />
              <span class="gnn-toggle-slider"></span>
              <span><?php echo esc_html( $obj->labels->name . " ($slug)" ); ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <h2 style="margin-top:16px;"><?php esc_html_e( 'Users', 'gnn-sitemap' ); ?></h2>
        <label class="gnn-toggle">
          <input type="checkbox" name="include_users" value="1" <?php checked( ! empty( $settings['include_users'] ) ); ?> />
          <span class="gnn-toggle-slider"></span>
          <span><?php esc_html_e( 'Include author archives (users)', 'gnn-sitemap' ); ?></span>
        </label>

        <p style="margin-top:16px;">
          <button type="submit" name="gnn_sitemap_network_submit" class="button button-primary"><?php esc_html_e( 'Save', 'gnn-sitemap' ); ?></button>
        </p>
      </form>
    </div>
    <?php
}

/** ---------- Activation / deactivation ---------- */
register_activation_hook( __FILE__, function () {
    if ( ! get_option( GNN_SITEMAP_OPT ) ) {
        add_option( GNN_SITEMAP_OPT, gnn_sitemap_default_settings(), '', false );
    }
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
