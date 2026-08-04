<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Connects GitHub Releases (vX.Y.Z tags) to WordPress's native plugin
 * update system. No third-party update server required.
 */
class GNN_Sitemap_Updater {

    private $plugin_file;
    private $plugin_slug;   // "gnn-sitemap/gnn-sitemap.php"
    private $slug;          // "gnn-sitemap"
    private $version;
    private $github_owner;
    private $github_repo;
    private $cache_key;

    public function __construct( $plugin_file, $version, $github_owner, $github_repo ) {
        $this->plugin_file  = $plugin_file;
        $this->plugin_slug  = plugin_basename( $plugin_file );
        $this->slug         = dirname( $this->plugin_slug );
        $this->version      = $version;
        $this->github_owner = $github_owner;
        $this->github_repo  = $github_repo;
        $this->cache_key    = 'gnn_sitemap_gh_release';

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'fix_folder_name' ), 10, 3 );
        add_action( 'admin_init', array( $this, 'maybe_handle_manual_check' ) );
    }

    private function get_release() {
        $cached = get_transient( $this->cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $url  = "https://api.github.com/repos/{$this->github_owner}/{$this->github_repo}/releases/latest";
        $args = array(
            'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress/' . get_bloginfo('version') ),
            'timeout' => 10,
        );
        if ( defined('GNN_SITEMAP_GH_TOKEN') && GNN_SITEMAP_GH_TOKEN ) {
            $args['headers']['Authorization'] = 'Bearer ' . GNN_SITEMAP_GH_TOKEN;
        }

        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            set_transient( $this->cache_key, array(), HOUR_IN_SECONDS ); // short-lived negative cache
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['tag_name'] ) ) {
            set_transient( $this->cache_key, array(), HOUR_IN_SECONDS );
            return array();
        }

        $release = array(
            'version'     => ltrim( $body['tag_name'], 'v' ),
            'download'    => isset( $body['zipball_url'] ) ? $body['zipball_url'] : '',
            'changelog'   => isset( $body['body'] ) ? $body['body'] : '',
            'html_url'    => isset( $body['html_url'] ) ? $body['html_url'] : '',
            'published'   => isset( $body['published_at'] ) ? $body['published_at'] : '',
        );

        // If the release has an attached .zip asset (added by release.yml), prefer it over the zipball.
        if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
            foreach ( $body['assets'] as $asset ) {
                if ( isset( $asset['browser_download_url'] ) && substr( $asset['browser_download_url'], -4 ) === '.zip' ) {
                    $release['download'] = $asset['browser_download_url'];
                    break;
                }
            }
        }

        set_transient( $this->cache_key, $release, 12 * HOUR_IN_SECONDS );
        return $release;
    }

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_release();
        if ( empty( $release['version'] ) || empty( $release['download'] ) ) {
            return $transient;
        }

        if ( version_compare( $release['version'], $this->version, '>' ) ) {
            $item = (object) array(
                'slug'        => $this->slug,
                'plugin'      => $this->plugin_slug,
                'new_version' => $release['version'],
                'url'         => $release['html_url'],
                'package'     => $release['download'],
            );
            $transient->response[ $this->plugin_slug ] = $item;
        }

        return $transient;
    }

    public function plugins_api( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
            return $result;
        }

        $release = $this->get_release();
        if ( empty( $release['version'] ) ) {
            return $result;
        }

        return (object) array(
            'name'          => 'GNN Sitemap',
            'slug'          => $this->slug,
            'version'       => $release['version'],
            'author'        => 'GNN',
            'homepage'      => $release['html_url'],
            'sections'      => array(
                'changelog' => wpautop( wp_kses_post( $release['changelog'] ) ),
            ),
            'download_link' => $release['download'],
        );
    }

    /**
     * Our release .zip (built by release.yml) already ships with the correct
     * "gnn-sitemap" top-level folder, so this is normally a no-op. It only
     * kicks in as a fallback if GitHub's auto-generated zipball
     * ("{repo}-{tag}") is ever used instead, which would otherwise install
     * under the wrong folder name and make WordPress treat it as a brand
     * new plugin.
     *
     * Note: this must NOT call activate_plugin()/include the plugin file.
     * The current request already has this plugin's functions/constants
     * loaded in memory; re-including the file here would redeclare them and
     * fatal mid-upgrade, leaving the plugin directory in a broken state
     * ("Plugin file does not exist"). Since the folder path is unchanged in
     * the normal case, WordPress's own "active_plugins" option entry is
     * still valid — nothing needs to be reactivated.
     */
    public function fix_folder_name( $response, $hook_extra, $result ) {
        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $response;
        }

        global $wp_filesystem;

        $target_dir = trailingslashit( WP_PLUGIN_DIR ) . $this->slug;
        $source_dir = $result['destination'];

        if ( $source_dir !== $target_dir && $wp_filesystem && $wp_filesystem->exists( $source_dir ) ) {
            $wp_filesystem->move( $source_dir, $target_dir, true );
            $result['destination'] = $target_dir;
        }

        return $result;
    }

    public function maybe_handle_manual_check() {
        if ( empty( $_GET['gnn_sitemap_check_update'] ) ) {
            return;
        }
        if ( ! current_user_can('manage_options') ) {
            return;
        }
        check_admin_referer( 'gnn_sitemap_manual_update' );

        delete_transient( $this->cache_key );
        delete_site_transient( 'update_plugins' );

        wp_safe_redirect( admin_url( 'update-core.php?force-check=1' ) );
        exit;
    }
}
