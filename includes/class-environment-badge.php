<?php
/**
 * Environment Badge
 *
 * Provides manual environment tagging (Production, Staging, Development) with smart domain
 * heuristics and displays a persistent color-coded safety badge in the WordPress admin bar.
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
 * Class WPSG_Environment_Badge
 */
class WPSG_Environment_Badge {

	/**
	 * Option key for stored environment type.
	 */
	const OPTION_KEY = 'wpsg_environment_type';

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Environment_Badge|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Environment_Badge
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
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_badge' ), 90 );
		add_action( 'admin_head', array( $this, 'output_badge_styles' ) );
		add_action( 'wp_head', array( $this, 'output_badge_styles' ) );
	}

	/**
	 * Suggest environment type based on domain heuristics and core environment constants.
	 * Never auto-applies without user confirmation.
	 *
	 * @return string ('production', 'staging', 'development')
	 */
	public static function suggest_environment() {
		// 1. Core wp_get_environment_type() if available (WP 5.5+)
		if ( function_exists( 'wp_get_environment_type' ) ) {
			$core_env = wp_get_environment_type();
			if ( 'local' === $core_env || 'development' === $core_env ) {
				return 'development';
			}
			if ( 'staging' === $core_env ) {
				return 'staging';
			}
			if ( 'production' === $core_env ) {
				return 'production';
			}
		}

		// 2. Domain heuristics
		$host = '';
		if ( isset( $_SERVER['HTTP_HOST'] ) && ! empty( $_SERVER['HTTP_HOST'] ) ) {
			$host = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
		} elseif ( function_exists( 'home_url' ) ) {
			$parsed = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( $parsed ) {
				$host = strtolower( $parsed );
			}
		}

		if ( $host ) {
			if (
				false !== strpos( $host, '.local' ) ||
				false !== strpos( $host, '.test' ) ||
				false !== strpos( $host, '.ddev.site' ) ||
				false !== strpos( $host, '.lndo.site' ) ||
				false !== strpos( $host, 'localhost' ) ||
				'127.0.0.1' === $host ||
				'::1' === $host
			) {
				return 'development';
			}

			if (
				0 === strpos( $host, 'staging.' ) ||
				0 === strpos( $host, 'stage.' ) ||
				0 === strpos( $host, 'dev.' ) ||
				0 === strpos( $host, 'test.' ) ||
				false !== strpos( $host, '-staging.' ) ||
				false !== strpos( $host, '.staging.' )
			) {
				return 'staging';
			}
		}

		return 'production';
	}

	/**
	 * Get current confirmed or active environment.
	 *
	 * @return string
	 */
	public static function get_environment() {
		$saved = get_option( self::OPTION_KEY, '' );
		if ( in_array( $saved, array( 'production', 'staging', 'development' ), true ) ) {
			return $saved;
		}
		return self::suggest_environment();
	}

	/**
	 * Alias for get_environment().
	 *
	 * @return string
	 */
	public static function get_environment_type() {
		return self::get_environment();
	}

	/**
	 * Check if environment has been explicitly confirmed and saved by admin.
	 *
	 * @return bool
	 */
	public static function is_confirmed() {
		$saved = get_option( self::OPTION_KEY, '' );
		return in_array( $saved, array( 'production', 'staging', 'development' ), true );
	}

	/**
	 * Render color-coded badge in the WordPress top admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
	 */
	public function add_admin_bar_badge( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		$env       = self::get_environment();
		$confirmed = self::is_confirmed();

		$labels = array(
			'production'  => __( 'Production', 'site-checkup-pro' ),
			'staging'     => __( 'Staging', 'site-checkup-pro' ),
			'development' => __( 'Development', 'site-checkup-pro' ),
		);

		$label = isset( $labels[ $env ] ) ? $labels[ $env ] : ucfirst( $env );
		/* translators: %s: environment name */
		$confirmed_title = sprintf( __( 'Site Environment: %s', 'site-checkup-pro' ), $label );
		/* translators: %s: environment name */
		$unconfirmed_title = sprintf( __( 'Suggested Environment: %s (Unconfirmed)', 'site-checkup-pro' ), $label );

		$title = sprintf(
			'<span class="wpsg-adminbar-badge wpsg-badge-%1$s" title="%2$s"><span class="wpsg-badge-dot"></span>%3$s%4$s</span>',
			esc_attr( $env ),
			esc_attr( $confirmed ? $confirmed_title : $unconfirmed_title ),
			esc_html( $label ),
			$confirmed ? '' : ' <span class="wpsg-unconfirmed-mark">?</span>'
		);

		$wp_admin_bar->add_node( array(
			'id'    => 'wpsg-env-badge',
			'title' => $title,
			'href'  => admin_url( 'admin.php?page=site-checkup-pro#settings' ),
			'meta'  => array(
				'class' => 'wpsg-env-badge-item',
			),
		) );
	}

	/**
	 * Output inline CSS styles for admin bar badge.
	 */
	public function output_badge_styles() {
		if ( ! is_admin_bar_showing() ) {
			return;
		}
		?>
		<style id="wpsg-env-badge-css">
			#wpadminbar .wpsg-env-badge-item > a {
				padding: 0 8px !important;
				display: flex !important;
				align-items: center !important;
			}
			.wpsg-adminbar-badge {
				display: inline-flex;
				align-items: center;
				gap: 5px;
				font-size: 11px;
				font-weight: 700;
				letter-spacing: 0.04em;
				text-transform: uppercase;
				padding: 2px 7px;
				border-radius: 4px;
				line-height: 1.4;
			}
			.wpsg-badge-dot {
				width: 6px;
				height: 6px;
				border-radius: 50%;
				display: inline-block;
			}
			/* Red for Production */
			.wpsg-badge-production {
				background: rgba(239, 68, 68, 0.18);
				color: #fca5a5;
				border: 1px solid rgba(239, 68, 68, 0.4);
			}
			.wpsg-badge-production .wpsg-badge-dot {
				background: #ef4444;
				box-shadow: 0 0 6px #ef4444;
			}
			/* Amber for Staging */
			.wpsg-badge-staging {
				background: rgba(245, 158, 11, 0.18);
				color: #fde68a;
				border: 1px solid rgba(245, 158, 11, 0.4);
			}
			.wpsg-badge-staging .wpsg-badge-dot {
				background: #f59e0b;
				box-shadow: 0 0 6px #f59e0b;
			}
			/* Gray for Development */
			.wpsg-badge-development {
				background: rgba(107, 114, 128, 0.22);
				color: #e5e7eb;
				border: 1px solid rgba(156, 163, 175, 0.35);
			}
			.wpsg-badge-development .wpsg-badge-dot {
				background: #9ca3af;
			}
			.wpsg-unconfirmed-mark {
				font-size: 10px;
				opacity: 0.75;
				margin-left: 2px;
			}
		</style>
		<?php
	}
}
