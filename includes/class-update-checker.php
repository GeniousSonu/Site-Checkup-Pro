<?php
/**
 * Self-Hosted Plugin Update Checker
 *
 * Integrates YahnisElsts/plugin-update-checker for the self-hosted distribution
 * channel, fetching version metadata from update-info.json.
 * Excluded from WordPress.org directory releases per Guideline 8.
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/site-checkup-pro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Class WPSG_Update_Checker
 */
class WPSG_Update_Checker {

	/**
	 * Default metadata JSON endpoint on GitHub.
	 *
	 * @var string
	 */
	const METADATA_URL = 'https://raw.githubusercontent.com/GeniousSonu/Site-Checkup-Pro/main/update-info.json';

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Main plugin file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug = 'site-checkup-pro';

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private $current_version;

	/**
	 * Underlying PUC instance.
	 *
	 * @var object|null
	 */
	private $puc = null;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Main plugin file path.
	 * @param string $version     Current plugin version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = function_exists( 'plugin_basename' ) ? plugin_basename( $plugin_file ) : basename( $plugin_file );
		$this->current_version = $version;

		$this->init_puc();
	}

	/**
	 * Initialize YahnisElsts/plugin-update-checker library.
	 */
	private function init_puc() {
		$puc_loader = dirname( __FILE__ ) . '/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $puc_loader ) ) {
			return;
		}

		require_once $puc_loader;

		if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		// Allow metadata URL override via constant or filter for local testing & staging.
		$metadata_url = defined( 'WPSG_UPDATE_METADATA_URL' ) ? WPSG_UPDATE_METADATA_URL : self::METADATA_URL;
		if ( function_exists( 'apply_filters' ) ) {
			$metadata_url = apply_filters( 'wpsg_update_metadata_url', $metadata_url );
		}

		$this->puc = PucFactory::buildUpdateChecker(
			$metadata_url,
			$this->plugin_file,
			$this->plugin_slug
		);

		// Security & Capability Gating:
		// Ensure only users with 'update_plugins' capability can see/receive update payloads in admin context.
		if ( $this->puc && function_exists( 'add_filter' ) ) {
			add_filter( 'puc_request_info_result-' . $this->plugin_slug, array( $this, 'filter_update_info_capability' ), 10, 2 );
			add_filter( 'puc_check_now_button_styles-' . $this->plugin_slug, array( $this, 'filter_button_styles' ) );
		}
	}

	/**
	 * Filter update metadata to enforce 'update_plugins' capability.
	 *
	 * @param object|null $info   Plugin info object.
	 * @param mixed       $result Raw HTTP response.
	 * @return object|null
	 */
	public function filter_update_info_capability( $info, $result = null ) {
		if ( function_exists( 'is_admin' ) && is_admin() && function_exists( 'current_user_can' ) ) {
			if ( ! current_user_can( 'update_plugins' ) ) {
				return null;
			}
		}
		return $info;
	}

	/**
	 * Style the PUC manual check button if rendered.
	 *
	 * @param array $styles Existing styles.
	 * @return array
	 */
	public function filter_button_styles( $styles ) {
		return $styles;
	}

	/**
	 * Get the underlying PUC instance.
	 *
	 * @return object|null
	 */
	public function get_puc() {
		return $this->puc;
	}

	/**
	 * Manually trigger update check.
	 *
	 * @return object|null
	 */
	public function request_update() {
		if ( $this->puc && method_exists( $this->puc, 'requestUpdate' ) ) {
			return $this->puc->requestUpdate();
		}
		return null;
	}

	/**
	 * Fetch remote metadata (compatibility wrapper).
	 *
	 * @param bool $force_refresh Whether to bypass cache.
	 * @return object|false
	 */
	public function get_remote_metadata( $force_refresh = false ) {
		if ( $this->puc && method_exists( $this->puc, 'requestInfo' ) ) {
			$info = $this->puc->requestInfo();
			return ! empty( $info ) ? $info : false;
		}
		return false;
	}
}
