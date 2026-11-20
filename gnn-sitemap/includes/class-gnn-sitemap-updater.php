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
    private $requires     = '5.5';
    private $tested       = '7.1';
    private $requires_php = '7.4';

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
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
            return array();
        }

        $html_url = isset( $body['html_url'] ) ? esc_url_raw( $body['html_url'], array( 'https' ) ) : '';
        $download = $this->get_package_url( isset( $body['assets'] ) ? $body['assets'] : array() );

        $release = array(
            'version'   => ltrim( (string) $body['tag_name'], 'v' ),
            'download'  => $download,
            'changelog' => isset( $body['body'] ) ? (string) $body['body'] : '',
            'html_url'  => $html_url,
            'published' => isset( $body['published_at'] ) ? (string) $body['published_at'] : '',
        );

        // Only cache complete, valid release responses with a verified package URL.
        if ( $download ) {
            set_transient( $this->cache_key, $release, 12 * HOUR_IN_SECONDS );
        }
        return $release;
    }

    /**
     * Extracts and sanitizes the attached .zip release asset URL.
     */
    private function get_package_url( $assets ) {
        if ( ! is_array( $assets ) ) {
            return '';
        }

        $allowed_hosts = array(
            'github.com',
            'objects.githubusercontent.com',
            'release-assets.githubusercontent.com',
        );

        // Only use the .zip asset attached by release.yml (already named "gnn-sitemap").
        // GitHub's auto-generated zipball ("{repo}-{tag}" folder name) is intentionally
        // never used as a fallback: without a folder rename step, installing it would
        // put the plugin under the wrong slug.
        foreach ( $assets as $asset ) {
            if ( empty( $asset['browser_download_url'] ) || ! is_string( $asset['browser_download_url'] ) ) {
                continue;
            }
            $url  = esc_url_raw( $asset['browser_download_url'], array( 'https' ) );
            $host = $url ? strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) : '';
            if ( $url && in_array( $host, $allowed_hosts, true ) && substr( $url, -4 ) === '.zip' ) {
                return $url;
            }
        }

        return '';
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
                'slug'         => $this->slug,
                'plugin'       => $this->plugin_slug,
                'new_version'  => $release['version'],
                'url'          => $release['html_url'],
                'package'      => $release['download'],
                'requires'     => $this->requires,
                'tested'       => $this->tested,
                'requires_php' => $this->requires_php,
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
            'requires'      => $this->requires,
            'tested'        => $this->tested,
            'requires_php'  => $this->requires_php,
            'last_updated'  => $release['published'],
            'sections'      => array(
                'changelog' => wpautop( wp_kses_post( $release['changelog'] ) ),
            ),
            'download_link' => $release['download'],
        );
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
