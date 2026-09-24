<?php
/**
 * Weekly Changelog Digest
 *
 * Aggregates pending plugin and theme updates from WordPress update transients,
 * extracts available changelog notes, delivers weekly alerts via WPSG_Alert_Dispatcher,
 * and renders live update intelligence in the dashboard.
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/
 * @since   1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Changelog_Digest
 */
class WPSG_Changelog_Digest {

	/**
	 * Hook name for scheduled weekly cron.
	 */
	const CRON_HOOK = 'wpsg_weekly_changelog_digest';

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Changelog_Digest|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Changelog_Digest
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'send_scheduled_digest' ) );
		$this->ensure_scheduled();
	}

	/**
	 * Ensure the weekly cron event is scheduled.
	 */
	public function ensure_scheduled() {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			if ( function_exists( 'wp_schedule_event' ) ) {
				wp_schedule_event( time() + 86400, 'weekly', self::CRON_HOOK );
			}
		}
	}

	/**
	 * Compile live changelog digest from WordPress core update transients.
	 *
	 * @return array
	 */
	public static function compile_digest() {
		$plugin_updates = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_plugins' ) : null;
		$theme_updates  = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_themes' ) : null;

		$all_plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$digest_items = array();

		// 1. Process plugin updates
		if ( is_object( $plugin_updates ) && ! empty( $plugin_updates->response ) && is_array( $plugin_updates->response ) ) {
			foreach ( $plugin_updates->response as $plugin_file => $update_data ) {
				$plugin_info = isset( $all_plugins[ $plugin_file ] ) ? $all_plugins[ $plugin_file ] : array();
				$name        = ! empty( $plugin_info['Name'] ) ? $plugin_info['Name'] : $plugin_file;
				$cur_ver     = ! empty( $plugin_info['Version'] ) ? $plugin_info['Version'] : 'Unknown';
				$new_ver     = is_object( $update_data ) && isset( $update_data->new_version ) ? $update_data->new_version : ( is_array( $update_data ) && isset( $update_data['new_version'] ) ? $update_data['new_version'] : 'New' );
				$upgrade_notice = is_object( $update_data ) && isset( $update_data->upgrade_notice ) ? $update_data->upgrade_notice : '';

				$digest_items[] = array(
					'type'            => 'plugin',
					'name'            => sanitize_text_field( $name ),
					'file'            => sanitize_text_field( $plugin_file ),
					'current_version' => sanitize_text_field( $cur_ver ),
					'new_version'     => sanitize_text_field( $new_ver ),
					'upgrade_notice'  => sanitize_text_field( wp_strip_all_tags( (string) $upgrade_notice ) ),
					'tested_wp'       => is_object( $update_data ) && isset( $update_data->tested ) ? $update_data->tested : '',
				);
			}
		}

		// 2. Process theme updates
		if ( is_object( $theme_updates ) && ! empty( $theme_updates->response ) && is_array( $theme_updates->response ) ) {
			foreach ( $theme_updates->response as $theme_slug => $theme_data ) {
				$cur_ver = 'Unknown';
				if ( function_exists( 'wp_get_theme' ) ) {
					$th = wp_get_theme( $theme_slug );
					if ( $th->exists() ) {
						$cur_ver = $th->get( 'Version' );
					}
				}
				$new_ver = is_array( $theme_data ) && isset( $theme_data['new_version'] ) ? $theme_data['new_version'] : ( is_object( $theme_data ) && isset( $theme_data->new_version ) ? $theme_data->new_version : 'New' );

				$digest_items[] = array(
					'type'            => 'theme',
					'name'            => ucfirst( sanitize_text_field( $theme_slug ) ),
					'file'            => sanitize_text_field( $theme_slug ),
					'current_version' => sanitize_text_field( $cur_ver ),
					'new_version'     => sanitize_text_field( $new_ver ),
					'upgrade_notice'  => '',
					'tested_wp'       => '',
				);
			}
		}

		return array(
			'summary' => array(
				'total_updates'   => count( $digest_items ),
				'has_updates'     => count( $digest_items ) > 0,
				'last_checked_at' => current_time( 'mysql' ),
			),
			'items' => $digest_items,
		);
	}

	/**
	 * Cron callback to dispatch weekly changelog digest notification.
	 */
	public function send_scheduled_digest() {
		$digest = self::compile_digest();
		if ( empty( $digest['summary']['has_updates'] ) ) {
			return false;
		}

		if ( class_exists( 'WPSG_Alert_Dispatcher' ) ) {
			$total = $digest['summary']['total_updates'];
			$msg   = sprintf(
				/* translators: %d: total available updates */
				__( 'Weekly Changelog Digest: %d update(s) available for installed plugins and themes.', 'site-checkup-pro' ),
				$total
			);
			return WPSG_Alert_Dispatcher::dispatch( 'weekly_changelog_digest', $msg, $digest['items'] );
		}

		return false;
	}
}
