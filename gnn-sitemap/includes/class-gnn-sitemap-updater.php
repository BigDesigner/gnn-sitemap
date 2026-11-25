<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects GitHub Releases (vX.Y.Z tags) to WordPress's native plugin
 * update system following the Unified GNN GitHub Updater standard.
 */
class GNN_Sitemap_Updater {

	private $plugin_file;
	private $plugin_basename;
	private $slug;
	private $version;
	private $github_owner;
	private $github_repo;
	private $repo;
	private $transient_key = 'gnn_sitemap_gh_release';
	private $requires      = '5.5';
	private $tested        = '7.1';
	private $requires_php  = '7.4';
	private $was_active    = false;

	public function __construct( $plugin_file, $version, $github_owner = 'BigDesigner', $github_repo = 'gnn-sitemap' ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->slug            = dirname( $this->plugin_basename );
		$this->version         = $version;
		$this->github_owner    = $github_owner;
		$this->github_repo     = $github_repo;
		$this->repo            = $github_owner . '/' . $github_repo;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_install', array( $this, 'capture_active_state' ), 10, 2 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'handle_manual_check' ) );
		add_action( 'load-update-core.php', array( $this, 'clear_cache' ) );
	}

	/**
	 * Resolve the installed plugin version directly from the on-disk plugin header.
	 * Reading from disk avoids stale in-memory constants during the same-request upgrade cycle.
	 *
	 * @return string
	 */
	private function get_local_version() {
		$main_file = WP_PLUGIN_DIR . '/' . $this->plugin_basename;
		if ( file_exists( $main_file ) ) {
			if ( function_exists( 'clearstatcache' ) ) {
				clearstatcache( true, $main_file );
			}
			$headers = get_file_data( $main_file, array( 'Version' => 'Version' ), 'plugin' );
			if ( ! empty( $headers['Version'] ) ) {
				return trim( (string) $headers['Version'] );
			}
		}
		if ( defined( 'GNN_SITEMAP_VERSION' ) && GNN_SITEMAP_VERSION ) {
			return (string) GNN_SITEMAP_VERSION;
		}
		if ( ! empty( $this->version ) ) {
			return (string) $this->version;
		}
		return '0.0.0';
	}

	/**
	 * Fetch latest release metadata from GitHub API with transient caching,
	 * HTTP 302 + expanded_assets fallback, and negative caching.
	 *
	 * @return object|false
	 */
	private function get_remote_release() {
		$cached = get_transient( $this->transient_key );
		if ( is_object( $cached ) ) {
			if ( ! empty( $cached->failed ) ) {
				return false;
			}
			if ( ! empty( $cached->version ) && ! empty( $cached->package ) ) {
				return $cached;
			}
		}

		$release = $this->fetch_release_from_api();
		if ( ! $release ) {
			$release = $this->fetch_release_from_fallback();
		}

		if ( ! $release || empty( $release->version ) || empty( $release->package ) ) {
			set_transient( $this->transient_key, (object) array( 'failed' => true ), 300 );
			return false;
		}

		set_transient( $this->transient_key, $release, 12 * HOUR_IN_SECONDS );
		return $release;
	}

	/**
	 * Fetch release metadata via the GitHub Releases API.
	 *
	 * @return object|false
	 */
	private function fetch_release_from_api() {
		$url  = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
		$args = array(
			'headers' => array(
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			),
			'timeout' => 10,
		);
		if ( defined( 'GNN_SITEMAP_GH_TOKEN' ) && GNN_SITEMAP_GH_TOKEN ) {
			$args['headers']['Authorization'] = 'Bearer ' . GNN_SITEMAP_GH_TOKEN;
		}

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return false;
		}

		$remote_version = ltrim( (string) $body['tag_name'], 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $remote_version ) ) {
			return false;
		}

		$package = $this->extract_package_url(
			isset( $body['assets'] ) ? $body['assets'] : array(),
			isset( $body['zipball_url'] ) ? $body['zipball_url'] : ''
		);
		if ( ! $package ) {
			return false;
		}

		$html_url = isset( $body['html_url'] ) ? $this->sanitize_github_url( $body['html_url'] ) : '';
		if ( ! $html_url ) {
			$html_url = 'https://github.com/' . $this->repo;
		}

		return (object) array(
			'version'   => $remote_version,
			'package'   => $package,
			'url'       => $html_url,
			'changelog' => isset( $body['body'] ) ? (string) $body['body'] : '',
			'published' => isset( $body['published_at'] ) ? (string) $body['published_at'] : '',
		);
	}

	/**
	 * Fallback when GitHub API is unavailable or rate-limited:
	 * read tag from /releases/latest 302 Location header and scrape expanded_assets.
	 *
	 * @return object|false
	 */
	private function fetch_release_from_fallback() {
		$head = wp_remote_head(
			'https://github.com/' . $this->repo . '/releases/latest',
			array(
				'redirection' => 0,
				'timeout'     => 10,
			)
		);
		if ( is_wp_error( $head ) ) {
			return false;
		}

		$headers  = wp_remote_retrieve_headers( $head );
		$location = ! empty( $headers['location'] ) ? (string) $headers['location'] : '';
		if ( ! preg_match( '#/releases/tag/([^/?\#\s"\']+)#i', $location, $m ) ) {
			return false;
		}

		$tag            = urldecode( $m[1] );
		$remote_version = ltrim( $tag, 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $remote_version ) ) {
			return false;
		}

		$package = $this->scrape_expanded_assets( $tag );
		if ( ! $package ) {
			$package = $this->sanitize_github_url(
				'https://github.com/' . $this->repo . '/archive/refs/tags/' . rawurlencode( $tag ) . '.zip'
			);
		}
		if ( ! $package ) {
			return false;
		}

		return (object) array(
			'version'   => $remote_version,
			'package'   => $package,
			'url'       => 'https://github.com/' . $this->repo . '/releases/tag/' . rawurlencode( $tag ),
			'changelog' => '',
			'published' => '',
		);
	}

	/**
	 * Scrape the .zip asset URL from GitHub's expanded_assets HTML fragment.
	 *
	 * @param string $tag Release tag.
	 * @return string|false
	 */
	private function scrape_expanded_assets( $tag ) {
		$url      = 'https://github.com/' . $this->repo . '/releases/expanded_assets/' . rawurlencode( $tag );
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$pattern = '#/' . preg_quote( $this->repo, '#' ) . '/releases/download/[^"\'\s]+\.zip#i';
		if ( preg_match( $pattern, wp_remote_retrieve_body( $response ), $m ) ) {
			return $this->sanitize_github_url( 'https://github.com' . $m[0] );
		}

		return false;
	}

	/**
	 * Prefer an attached .zip release asset; fall back to zipball_url only if none exists.
	 *
	 * @param array  $assets      Release assets array.
	 * @param string $zipball_url GitHub zipball URL.
	 * @return string|false
	 */
	private function extract_package_url( $assets, $zipball_url = '' ) {
		if ( is_array( $assets ) ) {
			foreach ( $assets as $asset ) {
				if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) || ! is_string( $asset['browser_download_url'] ) ) {
					continue;
				}
				if ( '.zip' === substr( (string) $asset['name'], -4 ) ) {
					$sanitized = $this->sanitize_github_url( $asset['browser_download_url'] );
					if ( $sanitized ) {
						return $sanitized;
					}
				}
			}
		}

		if ( ! empty( $zipball_url ) && is_string( $zipball_url ) ) {
			return $this->sanitize_github_url( $zipball_url );
		}

		return false;
	}

	/**
	 * Validate and sanitize a URL against trusted GitHub HTTPS hosts (SSRF protection).
	 *
	 * @param string $url Raw URL.
	 * @return string|false
	 */
	private function sanitize_github_url( $url ) {
		$clean_url = esc_url_raw( $url, array( 'https' ) );
		if ( empty( $clean_url ) ) {
			return false;
		}

		$parsed = wp_parse_url( $clean_url );
		if ( empty( $parsed['scheme'] ) || 'https' !== strtolower( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return false;
		}

		$host          = strtolower( $parsed['host'] );
		$allowed_hosts = array(
			'github.com',
			'api.github.com',
			'codeload.github.com',
			'objects.githubusercontent.com',
			'release-assets.githubusercontent.com',
		);

		if ( in_array( $host, $allowed_hosts, true ) ) {
			return $clean_url;
		}

		$suffix = '.githubusercontent.com';
		if ( strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix ) {
			return $clean_url;
		}

		return false;
	}

	/**
	 * Inject update availability into WordPress's update_plugins transient.
	 *
	 * @param object $transient Plugin update transient.
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		$release = $this->get_remote_release();
		if ( ! $release || empty( $release->version ) || empty( $release->package ) ) {
			return $transient;
		}

		$local_version = $this->get_local_version();

		if ( version_compare( $release->version, $local_version, '>' ) ) {
			$transient->response[ $this->plugin_basename ] = (object) array(
				'id'           => 'https://github.com/' . $this->repo,
				'slug'         => $this->slug,
				'plugin'       => $this->plugin_basename,
				'new_version'  => $release->version,
				'url'          => $release->url,
				'package'      => $release->package,
				'requires'     => $this->requires,
				'tested'       => $this->tested,
				'requires_php' => $this->requires_php,
			);
			unset( $transient->no_update[ $this->plugin_basename ] );
		} else {
			unset( $transient->response[ $this->plugin_basename ] );
			$transient->no_update[ $this->plugin_basename ] = (object) array(
				'id'          => 'https://github.com/' . $this->repo,
				'slug'        => $this->slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $local_version,
				'url'         => $release->url,
				'package'     => '',
			);
		}

		return $transient;
	}

	/**
	 * Supply plugin details modal content for WordPress Plugins screen.
	 *
	 * @param object|bool $result Result object.
	 * @param string      $action Action type.
	 * @param object      $args   Request arguments.
	 * @return object|bool
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$release = $this->get_remote_release();
		if ( ! $release || empty( $release->version ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'GNN Sitemap',
			'slug'          => $this->slug,
			'version'       => $release->version,
			'author'        => '<a href="https://github.com/BigDesigner" target="_blank" rel="noopener noreferrer">GNN</a>',
			'homepage'      => $release->url,
			'requires'      => $this->requires,
			'tested'        => $this->tested,
			'requires_php'  => $this->requires_php,
			'last_updated'  => $release->published,
			'sections'      => array(
				'changelog' => ! empty( $release->changelog ) ? wpautop( wp_kses_post( $release->changelog ) ) : '',
			),
			'download_link' => $release->package,
		);
	}

	/**
	 * Capture whether the plugin was active prior to upgrade.
	 *
	 * @param bool|WP_Error $return     Default return value.
	 * @param array         $hook_extra Extra hook arguments.
	 * @return bool|WP_Error
	 */
	public function capture_active_state( $return, $hook_extra ) {
		if ( ! empty( $hook_extra['plugin'] ) && $hook_extra['plugin'] === $this->plugin_basename ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$this->was_active = is_plugin_active( $this->plugin_basename );
		}

		return $return;
	}

	/**
	 * Normalize destination directory and restore active state after upgrade.
	 *
	 * @param bool|WP_Error $response   Installation response.
	 * @param array         $hook_extra Extra hook arguments.
	 * @param array         $result     Installation result data.
	 * @return array|bool|WP_Error
	 */
	public function after_install( $response, $hook_extra, $result ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			return $result;
		}
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $result;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
		}

		$plugin_dir  = wp_normalize_path( untrailingslashit( WP_PLUGIN_DIR . '/' . $this->slug ) );
		$destination = ! empty( $result['destination'] ) ? wp_normalize_path( untrailingslashit( $result['destination'] ) ) : '';

		if ( '' !== $destination && $destination !== $plugin_dir && $wp_filesystem && $wp_filesystem->exists( $destination ) ) {
			$wp_filesystem->move( $destination, $plugin_dir, true );
			$result['destination']      = trailingslashit( $plugin_dir );
			$result['destination_name'] = $this->slug;
		}

		wp_clean_plugins_cache( true );

		if ( $this->was_active ) {
			activate_plugin( $this->plugin_basename );
			$this->was_active = false;
		}

		delete_transient( $this->transient_key );

		return $result;
	}

	/**
	 * Handle manual "Check Updates" action link request.
	 */
	public function handle_manual_check() {
		if ( empty( $_GET['gnn_sitemap_check_update'] ) ) {
			return;
		}
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to update plugins.', 'gnn-sitemap' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'gnn_sitemap_manual_update' );

		delete_transient( $this->transient_key );
		delete_site_transient( 'update_plugins' );

		wp_safe_redirect( admin_url( 'update-core.php?force-check=1' ) );
		exit;
	}

	/**
	 * Clear cached release transient when visiting update-core.php.
	 */
	public function clear_cache() {
		delete_transient( $this->transient_key );
	}
}
