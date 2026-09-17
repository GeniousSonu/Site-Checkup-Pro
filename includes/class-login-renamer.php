<?php
/**
* Login URL Renamer & WPS Hide Login Bridge
 *
 * Provides safe detection of existing login-protection plugins (WPS Hide Login, iThemes, etc.).
 * Emergency recovery strictly requires filesystem access via the WPSG_DISABLE_LOGIN_RENAME constant in wp-config.php.
 * Zero query-string backdoor bypasses.
 *
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
 * Class WPSG_Login_Renamer
 */
class WPSG_Login_Renamer {

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Login_Renamer|null
	 */
	private static $instance = null;

	/**
	 * Option name for custom slug.
	 */
	const SLUG_OPTION = 'wpsg_login_slug';

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Login_Renamer
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
	private function __construct() {
		// Emergency lockout recovery: if WPSG_DISABLE_LOGIN_RENAME is defined in wp-config.php, disable immediately!
		if ( defined( 'WPSG_DISABLE_LOGIN_RENAME' ) && WPSG_DISABLE_LOGIN_RENAME ) {
			return;
		}

		$slug = self::get_login_slug();
		if ( empty( $slug ) ) {
			return; // Feature is disabled by default.
		}

		// If a dedicated plugin like WPS Hide Login is active, defer to it.
		if ( self::is_wps_hide_login_active() ) {
			return;
		}

		// Register rewrites and interceptors.
		add_action( 'init', array( $this, 'intercept_custom_login' ), 1 );
		add_action( 'login_init', array( $this, 'block_default_wp_login' ), 1 );
		add_filter( 'site_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'network_site_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'wp_redirect', array( $this, 'filter_redirect' ), 10, 2 );
	}

	/**
	 * Get active custom login slug (empty string if disabled).
	 *
	 * @return string
	 */
	public static function get_login_slug() {
		// Recovery check.
		if ( defined( 'WPSG_DISABLE_LOGIN_RENAME' ) && WPSG_DISABLE_LOGIN_RENAME ) {
			return '';
		}
		return sanitize_title( get_option( self::SLUG_OPTION, '' ) );
	}

	/**
	 * Check if WPS Hide Login is active.
	 *
	 * @return bool
	 */
	public static function is_wps_hide_login_active() {
		return is_plugin_active( 'wps-hide-login/wps-hide-login.php' ) || class_exists( 'WPS_Hide_Login' );
	}

	/**
	 * Detect any conflicting login-protection or login-renaming plugins.
	 *
	 * @return string|false Name of conflicting plugin or false.
	 */
	public static function get_conflicting_plugin() {
		if ( self::is_wps_hide_login_active() ) {
			return 'WPS Hide Login';
		}

		if ( is_plugin_active( 'better-wp-security/better-wp-security.php' ) || defined( 'ITSEC_CORE_DIR' ) ) {
			return 'Solid Security (iThemes)';
		}

		if ( is_plugin_active( 'all-in-one-wp-security-and-firewall/wp-security.php' ) || defined( 'AIO_WP_SECURITY_VERSION' ) ) {
			return 'All-In-One Security (AIOS)';
		}

		if ( is_plugin_active( 'wp-cerber/wp-cerber.php' ) || defined( 'CERBER_VER' ) ) {
			return 'WP Cerber Security';
		}

		return false;
	}

	/**
	 * Set or update custom login slug.
	 * Requires explicit 'CONFIRM' verification token.
	 *
	 * @param string $new_slug   Desired slug.
	 * @param string $confirm    Must equal 'CHANGE' to prevent accidental lockout.
	 * @return array
	 */
	public static function set_login_slug( $new_slug, $confirm ) {
		if ( 'CHANGE' !== trim( $confirm ) ) {
			return array(
				'success' => false,
				'message' => __( 'Confirmation token mismatch. You must type "CHANGE" to confirm altering the login URL.', 'site-checkup-pro' ),
			);
		}

		$conflict = self::get_conflicting_plugin();
		if ( false !== $conflict ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: plugin name */
					__( 'Cannot activate built-in login renamer: conflicting security plugin detected (%s). Please use that plugin to manage login URLs.', 'site-checkup-pro' ),
					$conflict
				),
			);
		}

		$clean_slug = sanitize_title( $new_slug );
		if ( empty( $clean_slug ) || in_array( $clean_slug, array( 'admin', 'wp-admin', 'login', 'wp-login', 'dashboard' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please specify a valid, unique custom slug (cannot use default WordPress reserved paths).', 'site-checkup-pro' ),
			);
		}

		update_option( self::SLUG_OPTION, $clean_slug );

		return array(
			'success'   => true,
			'slug'      => $clean_slug,
			'login_url' => home_url( '/' . $clean_slug . '/' ),
			'message'   => sprintf(
				/* translators: %s: new login URL */
				__( 'Login URL successfully changed to: %s. In case of emergency, define("WPSG_DISABLE_LOGIN_RENAME", true); in wp-config.php to restore default login.', 'site-checkup-pro' ),
				home_url( '/' . $clean_slug . '/' )
			),
		);
	}

	/**
	 * Disable the custom login URL and revert to default wp-login.php.
	 *
	 * @return array
	 */
	public static function disable_login_rename() {
		delete_option( self::SLUG_OPTION );

		return array(
			'success' => true,
			'message' => __( 'Custom login URL disabled. Default wp-login.php restored.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Intercept requests to the custom login slug and load wp-login.php.
	 */
	public function intercept_custom_login() {
		$slug = self::get_login_slug();
		if ( empty( $slug ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_uri = trim( wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );

		if ( $request_uri === $slug ) {
			// Require WordPress core login logic cleanly.
			status_header( 200 );
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
	}

	/**
	 * Block direct requests to wp-login.php when custom slug is active.
	 */
	public function block_default_wp_login() {
		$slug = self::get_login_slug();
		if ( empty( $slug ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		// Allow logout, postpass, and registration actions to continue without 404 if needed.
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( in_array( $action, array( 'postpass', 'logout' ), true ) ) {
			return;
		}

		if ( false !== strpos( $request_uri, 'wp-login.php' ) ) {
			// Redirect or 404 directly.
			global $wp_query;
			if ( is_object( $wp_query ) ) {
				$wp_query->set_404();
			}
			status_header( 404 );
			nocache_headers();
			$template = get_404_template();
			if ( $template && file_exists( $template ) ) {
				include $template;
			} else {
				wp_die( esc_html__( 'Page not found.', 'site-checkup-pro' ), '', array( 'response' => 404 ) );
			}
			exit;
		}
	}

	/**
	 * Rewrite login_url calls to use custom slug.
	 *
	 * @param string $url    URL.
	 * @param string $path   Path.
	 * @param string $scheme Scheme.
	 * @return string
	 */
	public function filter_login_url( $url, $path, $scheme ) {
		if ( 'login' === $scheme || 'login_post' === $scheme || false !== strpos( $url, 'wp-login.php' ) ) {
			$slug = self::get_login_slug();
			if ( ! empty( $slug ) ) {
				$query = wp_parse_url( $url, PHP_URL_QUERY );
				$url   = home_url( '/' . $slug . '/' );
				if ( ! empty( $query ) ) {
					$url .= '?' . $query;
				}
			}
		}
		return $url;
	}

	/**
	 * Filter redirects to ensure wp-login.php redirects point to custom slug.
	 *
	 * @param string $location Location.
	 * @param int    $status   HTTP Status.
	 * @return string
	 */
	public function filter_redirect( $location, $status ) {
		if ( false !== strpos( $location, 'wp-login.php' ) ) {
			$slug = self::get_login_slug();
			if ( ! empty( $slug ) ) {
				$location = str_replace( 'wp-login.php', $slug . '/', $location );
			}
		}
		return $location;
	}
}
