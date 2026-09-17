<?php
/**
 * Self-Hosted Plugin Update Checker
 *
 * Provides native in-dashboard update notifications and 1-click updates for
 * self-hosted distribution installations via genioussonu.me prior to or alongside
 * WordPress.org directory updates. Ready to support future Pro add-on licensing.
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Update_Checker
 */
class WPSG_Update_Checker {

	/**
	 * Remote metadata JSON endpoint URL.
	 *
	 * @var string
	 */
	private $metadata_url = 'https://www.genioussonu.me/plugin/site-checkup-pro/update-info.json';

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug = 'site-checkup-pro';

	/**
	 * Current version.
	 *
	 * @var string
	 */
	private $current_version;

	/**
	 * Transient cache key.
	 *
	 * @var string
	 */
	private $cache_key = 'wpsg_self_hosted_update_info';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Main plugin file path.
	 * @param string $version     Current plugin version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->current_version = $version;

		// 1. Hook into WordPress update transient pipeline.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );

		// 2. Hook into WordPress plugin details modal (Thickbox).
		add_filter( 'plugins_api', array( $this, 'inject_plugin_information' ), 20, 3 );

		// 3. Clear cached update transient after upgrade.
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );

		// 4. Ensure plugin is present in update_plugins transient for auto-update support.
		add_filter( 'site_transient_update_plugins', array( $this, 'filter_update_plugins_transient' ) );

		// 5. Allow automatic background updates if enabled by site admin.
		add_filter( 'auto_update_plugin', array( $this, 'filter_auto_update_plugin' ), 10, 2 );
	}

	/**
	 * Fetch remote update metadata from genioussonu.me (cached for 12 hours).
	 *
	 * @param bool $force_refresh Force network request.
	 * @return object|false
	 */
	public function get_remote_metadata( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( $this->cache_key );
			if ( false !== $cached && is_object( $cached ) ) {
				return $cached;
			}
		}

		$response = wp_remote_get(
			$this->metadata_url,
			array(
				'timeout'    => 10,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; SiteCheckupPro/' . $this->current_version . '; ' . home_url(),
				'headers'    => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( empty( $data ) || ! is_object( $data ) || empty( $data->version ) ) {
			return false;
		}

		// Cache for 12 hours.
		set_transient( $this->cache_key, $data, 12 * HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Check if a newer version is available and inject it into WordPress update list.
	 *
	 * @param object $transient Update transient object.
	 * @return object
	 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->get_remote_metadata();
		if ( ! $remote || empty( $remote->version ) || empty( $remote->download_url ) ) {
			return $transient;
		}

		if ( version_compare( $this->current_version, $remote->version, '<' ) ) {
			$item = (object) array(
				'id'            => 'wpsg-' . $this->plugin_slug,
				'slug'          => $this->plugin_slug,
				'plugin'        => $this->plugin_basename,
				'new_version'   => $remote->version,
				'url'           => ! empty( $remote->homepage ) ? $remote->homepage : 'https://www.genioussonu.me/plugin/site-checkup-pro/',
				'package'       => $remote->download_url,
				'icons'         => ! empty( $remote->icons ) ? (array) $remote->icons : array(),
				'banners'       => ! empty( $remote->banners ) ? (array) $remote->banners : array(),
				'tested'        => ! empty( $remote->tested ) ? $remote->tested : '6.7',
				'requires_php'  => ! empty( $remote->requires_php ) ? $remote->requires_php : '7.4',
				'compatibility' => new stdClass(),
			);

			$transient->response[ $this->plugin_basename ] = $item;
		} else {
			$item = (object) array(
				'id'            => 'wpsg-' . $this->plugin_slug,
				'slug'          => $this->plugin_slug,
				'plugin'        => $this->plugin_basename,
				'new_version'   => $this->current_version,
				'url'           => ! empty( $remote->homepage ) ? $remote->homepage : 'https://www.genioussonu.me/plugin/site-checkup-pro/',
				'package'       => '',
				'icons'         => ! empty( $remote->icons ) ? (array) $remote->icons : array(),
				'banners'       => ! empty( $remote->banners ) ? (array) $remote->banners : array(),
				'tested'        => ! empty( $remote->tested ) ? $remote->tested : '6.7',
				'requires_php'  => ! empty( $remote->requires_php ) ? $remote->requires_php : '7.4',
				'compatibility' => new stdClass(),
			);

			$transient->no_update[ $this->plugin_basename ] = $item;
		}

		return $transient;
	}

	/**
	 * Provide plugin information when user clicks "View version X details" in dashboard.
	 *
	 * @param false|object|array $result Default result.
	 * @param string             $action Action being performed.
	 * @param object             $args   Arguments object.
	 * @return object|false
	 */
	public function inject_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$remote = $this->get_remote_metadata();
		if ( ! $remote ) {
			return $result;
		}

		$info = new stdClass();
		$info->name           = ! empty( $remote->name ) ? $remote->name : 'Site Checkup Pro';
		$info->slug           = $this->plugin_slug;
		$info->version        = $remote->version;
		$info->author         = ! empty( $remote->author ) ? $remote->author : '<a href="https://www.genioussonu.me/">SK Sahinur Islam</a>';
		$info->author_profile = 'https://profiles.wordpress.org/genioussonu/';
		$info->homepage       = ! empty( $remote->homepage ) ? $remote->homepage : 'https://www.genioussonu.me/plugin/site-checkup-pro/';
		$info->requires       = ! empty( $remote->requires ) ? $remote->requires : '5.8';
		$info->tested         = ! empty( $remote->tested ) ? $remote->tested : '6.7';
		$info->requires_php   = ! empty( $remote->requires_php ) ? $remote->requires_php : '7.4';
		$info->download_link  = ! empty( $remote->download_url ) ? $remote->download_url : '';
		$info->last_updated   = ! empty( $remote->last_updated ) ? $remote->last_updated : gmdate( 'Y-m-d' );
		$info->sections       = ! empty( $remote->sections ) ? (array) $remote->sections : array(
			'description' => 'Complete security audit, site hardening checklist, and vulnerability scanner for WordPress.',
		);

		return $info;
	}

	/**
	 * Clear cache on upgrade.
	 *
	 * @param object $upgrader Upgrader instance.
	 * @param array  $options  Upgrade options.
	 */
	public function clear_cache( $upgrader, $options ) {
		if ( isset( $options['action'] ) && 'update' === $options['action'] && isset( $options['type'] ) && 'plugin' === $options['type'] ) {
			delete_transient( $this->cache_key );
		}
	}

	/**
	 * Ensure the plugin is tracked in update_plugins transient (either response or no_update).
	 * This flags 'update-supported' => true in WP_Plugins_List_Table, enabling the Auto-updates column.
	 *
	 * @param object|false $transient The update_plugins transient.
	 * @return object|false
	 */
	public function filter_update_plugins_transient( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		if ( ! isset( $transient->response[ $this->plugin_basename ] ) && ! isset( $transient->no_update[ $this->plugin_basename ] ) ) {
			$transient->no_update[ $this->plugin_basename ] = (object) array(
				'id'            => 'wpsg-' . $this->plugin_slug,
				'slug'          => $this->plugin_slug,
				'plugin'        => $this->plugin_basename,
				'new_version'   => $this->current_version,
				'url'           => 'https://www.genioussonu.me/plugin/site-checkup-pro/',
				'package'       => '',
				'icons'         => array(),
				'banners'       => array(),
				'tested'        => '6.7',
				'requires_php'  => '7.4',
				'compatibility' => new stdClass(),
			);
		}

		return $transient;
	}

	/**
	 * Filter whether this plugin should be automatically updated in the background.
	 * Respects WordPress's standard auto_update_plugins option.
	 *
	 * @param bool|null $update Whether to update.
	 * @param object    $item   The plugin update item.
	 * @return bool|null
	 */
	public function filter_auto_update_plugin( $update, $item ) {
		if ( isset( $item->plugin ) && $this->plugin_basename === $item->plugin ) {
			$auto_updates = (array) get_site_option( 'auto_update_plugins', array() );
			return in_array( $this->plugin_basename, $auto_updates, true );
		}
		return $update;
	}
}
