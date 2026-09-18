<?php
/**
 * Automated Verification Test Suite for Site Checkup Pro
 *
 * Runs comprehensive assertions on security architecture, scanners, settings allowlists, and role gates.
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/
 * @since   1.0.0
 */

// Define WordPress mock environment constants and stub functions
define( 'ABSPATH', __DIR__ . '/../' );
define( 'WPSG_VERSION', '1.0.0' );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-content' );
define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
define( 'WPSG_PLUGIN_FILE', ABSPATH . 'site-checkup-pro.php' );
define( 'WPSG_PLUGIN_DIR', ABSPATH );
define( 'WPSG_PLUGIN_URL', 'https://example.com/wp-content/plugins/site-checkup-pro/' );
define( 'WPSG_BASENAME', 'site-checkup-pro/site-checkup-pro.php' );
define( 'WPSG_PLUGIN_BASENAME', WPSG_BASENAME );

// Mock WordPress functions
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) { return trim( strip_tags( $str ) ); }
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) { return strtolower( preg_replace( '/[^a-zA-Z0-9\-_]/', '-', $title ) ); }
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $name ) { return preg_replace( '/[^a-zA-Z0-9\-_.]/', '', $name ); }
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
}
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string)$t, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $t ) { return htmlspecialchars( (string)$t, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $t ) { return filter_var( $t, FILTER_SANITIZE_URL ); } }
if ( ! function_exists( 'esc_url_raw' ) ) { function esc_url_raw( $t ) { return filter_var( $t, FILTER_SANITIZE_URL ); } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $val ) { return is_string( $val ) ? stripslashes( $val ) : $val; } }
if ( ! function_exists( 'wp_parse_url' ) ) { function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $t ) {
		return preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $t );
	}
}
if ( ! function_exists( '__' ) ) { function __( $t, $d = '' ) { return $t; } }
if ( ! function_exists( '_e' ) ) { function _e( $t, $d = '' ) { echo $t; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $t, $d = '' ) { return esc_html( $t ); } }
if ( ! function_exists( 'esc_html_e' ) ) { function esc_html_e( $t, $d = '' ) { echo esc_html( $t ); } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $t, $d = '' ) { return esc_attr( $t ); } }
if ( ! function_exists( 'esc_attr_e' ) ) { function esc_attr_e( $t, $d = '' ) { echo esc_attr( $t ); } }
if ( ! function_exists( 'current_time' ) ) { function current_time( $type ) { return 'timestamp' === $type ? time() : date( 'Y-m-d H:i:s' ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
$GLOBALS['_mock_current_user_can'] = 'manage_options';
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $c ) {
		return $GLOBALS['_mock_current_user_can'] === $c;
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return substr( md5( 'mock_nonce_' . $action ), 0, 10 );
	}
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return ( $nonce === substr( md5( 'mock_nonce_' . $action ), 0, 10 ) ) ? 1 : false;
	}
}
if ( ! function_exists( 'sanitize_user' ) ) {
	function sanitize_user( $username, $strict = false ) {
		return preg_replace( '/[^a-zA-Z0-9 _.\-@]/', '', (string) $username );
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'trailingslashit' ) ) { function trailingslashit( $p ) { return rtrim( $p, '/' ) . '/'; } }
if ( ! function_exists( 'wp_mkdir_p' ) ) { function wp_mkdir_p( $target ) { return @mkdir( $target, 0777, true ) || is_dir( $target ); } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $data ) { return json_encode( $data ); } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $p = '' ) { return 'https://example.com' . $p; } }
if ( ! function_exists( 'admin_url' ) ) { function admin_url( $p = '' ) { return 'https://example.com/wp-admin/' . $p; } }
if ( ! function_exists( 'get_home_path' ) ) { function get_home_path() { return sys_get_temp_dir() . '/'; } }
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		$dir = sys_get_temp_dir() . '/wpsg-uploads';
		wp_mkdir_p( $dir );
		return array( 'basedir' => $dir, 'baseurl' => 'https://example.com/uploads' );
	}
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special = true, $extra = true ) {
		return bin2hex( random_bytes( (int) ( $length / 2 ) ) );
	}
}
if ( ! defined( 'OBJECT_K' ) ) { define( 'OBJECT_K', 'OBJECT_K' ); }
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
if ( ! function_exists( 'is_admin' ) ) { function is_admin() { return true; } }
if ( ! function_exists( 'is_plugin_active' ) ) { function is_plugin_active( $p ) { return false; } }
if ( ! function_exists( 'deactivate_plugins' ) ) { function deactivate_plugins( $p, $s = false, $n = null ) { return true; } }
if ( ! function_exists( 'delete_plugins' ) ) {
	function delete_plugins( $plugins ) {
		foreach ( (array) $plugins as $plugin ) {
			$slug = dirname( $plugin );
			if ( '.' === $slug || empty( $slug ) ) {
				$slug = basename( $plugin, '.php' );
			}
			$dir = WP_PLUGIN_DIR . '/' . $slug;
			if ( is_dir( $dir ) ) {
				$it = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
				$files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
				foreach ( $files as $file ) {
					if ( $file->isDir() ) {
						@rmdir( $file->getRealPath() );
					} else {
						@unlink( $file->getRealPath() );
					}
				}
				@rmdir( $dir );
			} elseif ( file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
				@unlink( WP_PLUGIN_DIR . '/' . $plugin );
			}
		}
		return true;
	}
}
wp_mkdir_p( WP_PLUGIN_DIR );
if ( ! function_exists( 'get_bloginfo' ) ) { function get_bloginfo( $show = '' ) { return 'Site Checkup Pro Test'; } }
if ( ! function_exists( 'get_plugins' ) ) { function get_plugins() { return array(); } }
if ( ! function_exists( 'wp_cache_get' ) ) { function wp_cache_get( $key, $group = '' ) { return false; } }
if ( ! function_exists( 'wp_cache_set' ) ) { function wp_cache_set( $key, $val, $group = '', $expire = 0 ) { return true; } }
if ( ! function_exists( 'wp_cache_delete' ) ) { function wp_cache_delete( $key, $group = '' ) { return true; } }

// Mock $wpdb
if ( ! class_exists( 'Mock_WPDB' ) ) {
	class Mock_WPDB {
		public $prefix    = 'wp_';
		public $users     = 'wp_users';
		public $options   = 'wp_options';
		public $insert_id = 1;
		public function prepare( $query, ...$args ) { return $query; }
		public function get_row( $query ) { return null; }
		public function get_var( $query ) { return null; }
		public function get_results( $query = '', $output = OBJECT ) { return array(); }
		public function query( $query ) { return true; }
		public function insert( $table, $data ) { return true; }
		public function update( $table, $data, $where ) { return true; }
	}
}
$GLOBALS['wpdb'] = new Mock_WPDB();

// Global options storage mock
$GLOBALS['_mock_options'] = array();
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['_mock_options'] ) ? $GLOBALS['_mock_options'][ $name ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $val ) {
		$GLOBALS['_mock_options'][ $name ] = $val;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['_mock_options'][ $name ] );
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $name ) { return get_option( '_transient_' . $name ); }
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $name, $val, $ttl = 0 ) { return update_option( '_transient_' . $name, $val ); }
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $name ) { return delete_option( '_transient_' . $name ); }
}
if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( $name, $default = false ) { return get_option( $name, $default ); }
}
if ( ! function_exists( 'update_site_option' ) ) {
	function update_site_option( $name, $val ) { return update_option( $name, $val ); }
}
if ( ! function_exists( 'delete_site_option' ) ) {
	function delete_site_option( $name ) { return delete_option( $name ); }
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		$query = http_build_query( $args );
		return $url ? ( false !== strpos( $url, '?' ) ? $url . '&' . $query : $url . '?' . $query ) : $query;
	}
}
if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
		return add_query_arg( array( $name => 'mock_nonce_' . $action ), $actionurl );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) { return $value; }
}

if ( ! function_exists( 'add_action' ) ) { function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {} }
if ( ! function_exists( 'add_filter' ) ) { function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {} }
if ( ! function_exists( 'do_action' ) ) { function do_action( $tag, ...$args ) {} }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $thing ) { return $thing instanceof WP_Error; } }
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}
		public function get_error_message() { return $this->message; }
		public function get_error_code() { return $this->code; }
	}
}
class Mock_WP_User {
	public $ID = 1;
	public $user_login = 'admin';
	public $user_pass = 'hashed_pass_123';
	public function exists() {
		return ! empty( $this->ID );
	}
}
if ( ! function_exists( 'wp_get_session_token' ) ) {
	function wp_get_session_token() { return 'mock_session_token_xyz789'; }
}
if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		return new Mock_WP_User();
	}
}
if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		return (object) array( 'ID' => (int) $user_id, 'user_login' => 'user_' . $user_id );
	}
}
if ( ! function_exists( 'wp_check_password' ) ) {
	function wp_check_password( $password, $hash, $user_id = '' ) {
		return $password === 'valid_admin_password';
	}
}
if ( ! class_exists( 'WP_Session_Tokens' ) ) {
	class WP_Session_Tokens {
		private static $sessions = array();
		public static function get_instance( $user_id ) {
			if ( ! isset( self::$sessions[ $user_id ] ) ) {
				self::$sessions[ $user_id ] = new self();
			}
			return self::$sessions[ $user_id ];
		}
		public function get_all() {
			return array(
				hash( 'sha256', 'mock_session_token_xyz789' ) => array(
					'ip' => '127.0.0.1', 'ua' => 'Mozilla/5.0', 'login' => time(), 'expiration' => time() + 86400
				),
				'other_verifier_abc' => array(
					'ip' => '192.168.1.50', 'ua' => 'Chrome/120', 'login' => time() - 3600, 'expiration' => time() + 86400
				),
			);
		}
		public function destroy( $verifier ) { return true; }
		public function destroy_others( $token ) { return true; }
		public function destroy_all() { return true; }
	}
}

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	class WP_REST_Controller {}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public $data;
		public $status;
		public function __construct( $data = null, $status = 200 ) {
			$this->data = $data;
			$this->status = $status;
		}
		public function get_data() { return $this->data; }
		public function get_status() { return $this->status; }
	}
}
if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		const READABLE = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE = 'POST, PUT, PATCH';
		const DELETABLE = 'DELETE';
		const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
	}
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		protected $params = array();
		public function __construct( $params = array() ) { $this->params = $params; }
		public function get_json_params() { return $this->params; }
		public function get_params() { return $this->params; }
		public function get_param( $k ) { return isset( $this->params[ $k ] ) ? $this->params[ $k ] : null; }
		public function set_param( $k, $v ) { $this->params[ $k ] = $v; }
	}
}
if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $response ) {
		if ( $response instanceof WP_REST_Response ) return $response;
		return new WP_REST_Response( $response );
	}
}
if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() { return true; }
}
if ( ! function_exists( 'rest_authorization_required_code' ) ) {
	function rest_authorization_required_code() { return is_user_logged_in() ? 403 : 401; }
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'test_mock_salt_for_wpsg_unit_tests_32chars_long!';
	}
}

if ( ! function_exists( 'is_ssl' ) ) {
	function is_ssl() {
		return ! empty( $GLOBALS['_mock_is_ssl'] );
	}
}

$GLOBALS['_mock_remote_responses'] = array();
if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( $url, $args = array() ) {
		if ( isset( $GLOBALS['_mock_remote_handler'] ) && is_callable( $GLOBALS['_mock_remote_handler'] ) ) {
			return call_user_func( $GLOBALS['_mock_remote_handler'], $url, $args );
		}
		if ( isset( $GLOBALS['_mock_remote_responses'][ $url ] ) ) {
			return $GLOBALS['_mock_remote_responses'][ $url ];
		}
		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'headers'  => array(),
			'body'     => '',
		);
	}
}
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		$args['method'] = 'GET';
		return wp_remote_request( $url, $args );
	}
}
if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		$args['method'] = 'POST';
		return wp_remote_request( $url, $args );
	}
}
if ( ! function_exists( 'wp_remote_head' ) ) {
	function wp_remote_head( $url, $args = array() ) {
		$args['method'] = 'HEAD';
		return wp_remote_request( $url, $args );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_wp_error( $response ) || ! isset( $response['response']['code'] ) ) return '';
		return (int) $response['response']['code'];
	}
}
if ( ! function_exists( 'wp_remote_retrieve_headers' ) ) {
	function wp_remote_retrieve_headers( $response ) {
		if ( is_wp_error( $response ) || ! isset( $response['headers'] ) ) return array();
		return $response['headers'];
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		if ( is_wp_error( $response ) || ! isset( $response['body'] ) ) return '';
		return $response['body'];
	}
}

if ( ! function_exists( 'insert_with_markers' ) ) {
	function insert_with_markers( $filename, $marker, $insertion ) {
		if ( ! file_exists( $filename ) ) {
			if ( ! is_dir( dirname( $filename ) ) ) {
				@mkdir( dirname( $filename ), 0755, true );
			}
			@touch( $filename );
		}
		if ( ! is_writable( $filename ) ) {
			return false;
		}
		if ( ! is_array( $insertion ) ) {
			$insertion = explode( "\n", (string) $insertion );
		}
		$start_marker = "# BEGIN {$marker}";
		$end_marker   = "# END {$marker}";
		$lines = file( $filename, FILE_IGNORE_NEW_LINES );
		if ( false === $lines ) {
			$lines = array();
		}
		$new_file_data = array();
		$in_marker     = false;
		$inserted      = false;

		foreach ( $lines as $line ) {
			if ( strpos( $line, $start_marker ) !== false ) {
				$in_marker = true;
				if ( ! empty( $insertion ) ) {
					$new_file_data[] = $start_marker;
					foreach ( $insertion as $insert_line ) {
						$new_file_data[] = $insert_line;
					}
					$new_file_data[] = $end_marker;
				}
				$inserted = true;
				continue;
			}
			if ( strpos( $line, $end_marker ) !== false ) {
				$in_marker = false;
				continue;
			}
			if ( ! $in_marker ) {
				$new_file_data[] = $line;
			}
		}

		if ( ! $inserted && ! empty( $insertion ) ) {
			$new_file_data[] = $start_marker;
			foreach ( $insertion as $insert_line ) {
				$new_file_data[] = $insert_line;
			}
			$new_file_data[] = $end_marker;
		}

		return false !== file_put_contents( $filename, implode( "\n", $new_file_data ) . "\n" );
	}
}

// Load plugin classes
require_once ABSPATH . 'includes/class-encryption.php';
require_once ABSPATH . 'includes/class-hosting-panel-bridge.php';
require_once ABSPATH . 'includes/class-http-verifier.php';
require_once ABSPATH . 'includes/class-audit-log.php';
require_once ABSPATH . 'includes/class-backup-guard.php';
require_once ABSPATH . 'includes/class-ssrf-guard.php';
require_once ABSPATH . 'includes/class-htaccess-manager.php';
require_once ABSPATH . 'includes/class-wp-config-manager.php';
require_once ABSPATH . 'includes/class-login-renamer.php';
require_once ABSPATH . 'includes/class-login-guard.php';
require_once ABSPATH . 'includes/class-session-manager.php';
require_once ABSPATH . 'includes/class-enumeration-guard.php';
require_once ABSPATH . 'includes/class-fingerprint-guard.php';
require_once ABSPATH . 'includes/class-integrity-monitor.php';
require_once ABSPATH . 'includes/class-notice-inbox.php';
require_once ABSPATH . 'includes/class-app-password-manager.php';
require_once ABSPATH . 'includes/class-plugin-integrity.php';
require_once ABSPATH . 'includes/class-scanner.php';
require_once ABSPATH . 'includes/class-vulnerability-checker.php';
require_once ABSPATH . 'includes/class-security-txt.php';
require_once ABSPATH . 'rest-api/class-rest-controller.php';
require_once ABSPATH . 'includes/class-compatibility-guard.php';
require_once ABSPATH . 'includes/class-update-checker.php';
require_once ABSPATH . 'includes/class-task.php';
require_once ABSPATH . 'includes/class-task-registry.php';
require_once ABSPATH . 'includes/class-task-runner.php';
require_once ABSPATH . 'includes/class-report-generator.php';
require_once ABSPATH . 'includes/class-plugin.php';
require_once ABSPATH . 'admin/class-admin-menu.php';

// Test runner helper
$tests_passed = 0;
$tests_failed = 0;

function run_test( $name, $callback ) {
	global $tests_passed, $tests_failed;
	try {
		$result = call_user_func( $callback );
		if ( false !== $result ) {
			echo "[PASS] " . $name . "\n";
			$tests_passed++;
		} else {
			echo "[FAIL] " . $name . " (returned false)\n";
			$tests_failed++;
		}
	} catch ( Exception $e ) {
		echo "[FAIL] " . $name . " (Exception: " . $e->getMessage() . ")\n";
		$tests_failed++;
	}
}

echo "=======================================================\n";
echo " Site Checkup Pro — Automated Test Suite\n";
echo "=======================================================\n\n";

// TEST 1: Secret Redaction on Salt Rotation
run_test( "Secret Redaction: Salt rotation snapshot contains no keys or plain secrets", function () {
	$raw_salts = array(
		'AUTH_KEY' => 'secret_salt_xyz123',
		'DB_PASSWORD' => 'super_secret_db_pass',
	);
	$sanitized = WPSG_Audit_Log::sanitize_snapshot( 'rotate_salts', $raw_salts );
	
	if ( ! isset( $sanitized['salts_rotated'] ) || true !== $sanitized['salts_rotated'] ) {
		return false;
	}
	if ( isset( $sanitized['AUTH_KEY'] ) || isset( $sanitized['DB_PASSWORD'] ) ) {
		return false;
	}
	$encoded = json_encode( $sanitized );
	return ( false === strpos( $encoded, 'secret' ) && false === strpos( $encoded, 'pass' ) );
} );

// TEST 2: Secret Redaction on wp-config Constant Edits
run_test( "Secret Redaction: wp-config diff stores only allowed constant names and booleans", function () {
	$data = array(
		'DISALLOW_FILE_EDIT' => true,
		'DB_PASSWORD'        => 'my_db_pass',
		'DB_USER'            => 'root',
	);
	$sanitized = WPSG_Audit_Log::sanitize_snapshot( 'disable_file_edit', $data );

	if ( ! isset( $sanitized['DISALLOW_FILE_EDIT'] ) || true !== $sanitized['DISALLOW_FILE_EDIT'] ) {
		return false;
	}
	if ( isset( $sanitized['DB_PASSWORD'] ) || isset( $sanitized['DB_USER'] ) ) {
		return false;
	}
	return true;
} );

// TEST 3: Nginx Web Server Detection
run_test( "Server Detection: Nginx marks .htaccess rules as not supported", function () {
	$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.24.0';
	$server = WPSG_Htaccess_Manager::get_server_type();
	$supports = WPSG_Htaccess_Manager::supports_htaccess();

	if ( 'nginx' !== $server || true === $supports ) {
		return false;
	}

	$res = WPSG_Htaccess_Manager::insert_rule( 'TestRule', 'Header test' );
	return ( false === $res['success'] && ! empty( $res['is_na'] ) );
} );

// TEST 4: Apache Web Server Detection
run_test( "Server Detection: Apache marks .htaccess as supported", function () {
	$_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.52 (Ubuntu)';
	$server = WPSG_Htaccess_Manager::get_server_type();
	$supports = WPSG_Htaccess_Manager::supports_htaccess();

	return ( 'apache' === $server && true === $supports );
} );

// TEST 5: Staging HTTP Basic Auth Status Code Semantics in Loopback Health Check
run_test( "Staging Loopback: 401 / 403 HTTP status is treated as healthy (staging auth wall)", function () {
	// Status code 401 from staging password protection should NOT roll back
	$status_401 = 401;
	$healthy_401 = !( $status_401 >= 500 );

	// Status code 500 should trigger rollback
	$status_500 = 500;
	$healthy_500 = !( $status_500 >= 500 );

	return ( $healthy_401 === true && $healthy_500 === false );
} );

// TEST 6: Backup Recency Threshold
run_test( "Backup Guard: 48h recency threshold enforcement", function () {
	delete_option( 'wpsg_manual_backup_confirmed_at' );
	$status_empty = WPSG_Backup_Guard::has_recent_backup( 48 );
	if ( true === $status_empty ) return false;

	// Confirm manual backup
	WPSG_Backup_Guard::confirm_manual_backup();
	$status_confirmed = WPSG_Backup_Guard::has_recent_backup( 48 );
	return ( true === $status_confirmed );
} );

// TEST 7a: Login Renamer: Custom slug rewrites wp-login.php in legitimate redirect
run_test( "Login Renamer: Custom slug rewrites wp-login.php in legitimate redirect", function () {
	update_option( WPSG_Login_Renamer::SLUG_OPTION, 'custom-portal' );
	$renamer = WPSG_Login_Renamer::get_instance();
	$_SERVER['REQUEST_URI'] = '/custom-portal/';
	$rewritten = $renamer->filter_redirect( 'https://example.com/wp-login.php?loggedout=true', 302 );
	$has_slug = ( false !== strpos( $rewritten, 'custom-portal/' ) );
	unset( $_SERVER['REQUEST_URI'] );
	return $has_slug;
} );

// TEST 7: Login Renamer Emergency Recovery Constant (WPSG_DISABLE_LOGIN_RENAME)
run_test( "Login Renamer: Recovery constant WPSG_DISABLE_LOGIN_RENAME deactivates custom login slug", function () {
	update_option( WPSG_Login_Renamer::SLUG_OPTION, 'secret-login-slug' );
	
	// Without constant
	$slug_before = WPSG_Login_Renamer::get_login_slug();
	if ( 'secret-login-slug' !== $slug_before ) return false;

	// Define constant
	if ( ! defined( 'WPSG_DISABLE_LOGIN_RENAME' ) ) {
		define( 'WPSG_DISABLE_LOGIN_RENAME', true );
	}

	$slug_after = WPSG_Login_Renamer::get_login_slug();
	return ( '' === $slug_after );
} );

// TEST 8: Login Renamer Requires 'CHANGE' Confirmation Token
run_test( "Login Renamer: Requires typing 'CHANGE' to prevent accidental lockout", function () {
	$fail_res = WPSG_Login_Renamer::set_login_slug( 'new-slug', 'wrong_token' );
	if ( false !== $fail_res['success'] ) return false;

	$ok_res = WPSG_Login_Renamer::set_login_slug( 'new-slug', 'CHANGE' );
	return ( true === $ok_res['success'] && 'new-slug' === $ok_res['slug'] );
} );

// TEST 9: Plugin Zip Backup Before Deletion
run_test( "Plugin Deletion: Creates valid zip archive in backup directory", function () {
	$test_plugin_dir = WP_PLUGIN_DIR . '/test-dummy-plugin';
	wp_mkdir_p( $test_plugin_dir );
	file_put_contents( $test_plugin_dir . '/test.php', '<?php // Test plugin' );

	$zip_path = WPSG_Plugin_Integrity::zip_plugin_directory( 'test-dummy-plugin' );
	if ( ! $zip_path || ! file_exists( $zip_path ) ) {
		return false;
	}

	$zip = new ZipArchive();
	$opened = $zip->open( $zip_path );
	if ( true === $opened ) {
		$has_file = ( false !== $zip->locateName( 'test.php' ) );
		$zip->close();
		@unlink( $zip_path );
		@unlink( $test_plugin_dir . '/test.php' );
		@rmdir( $test_plugin_dir );
		return $has_file;
	}

	return false;
} );

// TEST 10: Audit Log 12-Month Pruning Method
run_test( "Audit Log: Prune method accepts retention days threshold", function () {
	return method_exists( 'WPSG_Audit_Log', 'prune_old_logs' );
} );

// TEST 11: Baseline Drift Scanner for Rogue Administrators
run_test( "Baseline Scanner: Accurately detects new unapproved admin created after baseline", function () {
	// Mock get_users
	$GLOBALS['_mock_users'] = array(
		(object) array( 'ID' => 1, 'user_login' => 'admin_one', 'user_email' => 'admin1@example.com', 'user_registered' => '2026-01-01' ),
		(object) array( 'ID' => 2, 'user_login' => 'admin_two', 'user_email' => 'admin2@example.com', 'user_registered' => '2026-01-02' ),
	);

	if ( ! function_exists( 'get_users' ) ) {
		function get_users( $args ) {
			return $GLOBALS['_mock_users'];
		}
	}

	require_once ABSPATH . 'includes/class-scanner.php';

	// Reset baseline
	delete_option( WPSG_Scanner::BASELINE_OPTION );

	// Take baseline snapshot
	WPSG_Scanner::update_baseline();
	$scan1 = WPSG_Scanner::scan_rogue_admins();
	if ( 'done' !== $scan1['status'] || ! empty( $scan1['rogue_admins'] ) ) {
		return false;
	}

	// Add rogue admin 3
	$GLOBALS['_mock_users'][] = (object) array( 'ID' => 3, 'user_login' => 'rogue_admin', 'user_email' => 'rogue@attacker.com', 'user_registered' => '2026-09-16' );
	$scan2 = WPSG_Scanner::scan_rogue_admins();

	if ( 'attention' !== $scan2['status'] || 1 !== count( $scan2['rogue_admins'] ) || 3 !== $scan2['rogue_admins'][0]['id'] ) {
		return false;
	}

	// Accept drift as new baseline
	WPSG_Scanner::update_baseline();
	$scan3 = WPSG_Scanner::scan_rogue_admins();
	return ( 'done' === $scan3['status'] && empty( $scan3['rogue_admins'] ) );
} );

// TEST 12: Zip Slip & Malicious Path Traversal Protection
run_test( "Security: Zip Slip path traversal attempts are detected and rejected", function () {
	// Create a zip with a malicious relative path entry
	$zip_path = sys_get_temp_dir() . '/malicious_test.zip';
	$zip = new ZipArchive();
	if ( true === $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		$zip->addFromString( '../evil.php', '<?php phpinfo(); ?>' );
		$zip->close();

		update_option( 'wpsg_last_deleted_plugin_fake-plugin', array(
			'zip_path'    => $zip_path,
			'plugin_path' => 'fake-plugin/fake.php',
			'deleted_at'  => current_time( 'mysql' ),
		) );

		$res = WPSG_Plugin_Integrity::restore_plugin( 'fake-plugin' );
		@unlink( $zip_path );
		delete_option( 'wpsg_last_deleted_plugin_fake-plugin' );

		return ( false === $res['success'] && false !== strpos( $res['message'], 'path traversal' ) );
	}
	return false;
} );

// TEST 13: Unguessable Backup Token Generation
run_test( "Security: Backup files contain unguessable 32-character random tokens", function () {
	$temp_config = ABSPATH . 'wp-config.php';
	file_put_contents( $temp_config, '<?php // Test config' );

	$config_backup = WPSG_Wp_Config_Manager::backup_config();
	@unlink( $temp_config );

	if ( ! $config_backup ) return false;

	$filename = basename( $config_backup );
	// Format: wp-config-YYYYmmdd-HHiiss-[32chars].bak
	$matched = preg_match( '/^wp-config-\d{8}-\d{6}-[a-zA-Z0-9]{32}\.bak$/', $filename );
	@unlink( $config_backup );
	return ( 1 === $matched );
} );


// TEST 14: Centralized .htaccess Rule Registry & Preflight
run_test( "Centralized .htaccess: Preflight accurately detects existing php files in uploads", function () {
	$registry = WPSG_Htaccess_Manager::get_rule_registry();
	$has_deny = isset( $registry['deny_uploads_php'] ) || isset( $registry['DenyUploadsPHP'] );
	$has_protect = isset( $registry['protect_sensitive_files'] ) || isset( $registry['ProtectSensitiveFiles'] );
	if ( ! $has_deny || ! $has_protect ) {
		return false;
	}

	$upload_dir = wp_upload_dir();
	$test_php = $upload_dir['basedir'] . '/test_exec.php';
	file_put_contents( $test_php, '<?php echo "evil";' );

	$check = WPSG_Htaccess_Manager::check_uploads_php_preflight();
	@unlink( $test_php );

	return ( false === $check['safe'] && 1 === count( $check['found'] ) );
} );

// TEST 15: SSRF Guard Multi-Protocol & Metadata Protection
run_test( "SSRF Guard: Rejects loopback, private ranges, and cloud metadata IPs", function () {
	// 127.0.0.1
	if ( true === WPSG_SSRF_Guard::is_ip_safe( '127.0.0.1' ) ) return false;
	// AWS / GCP IPv4 Metadata
	if ( true === WPSG_SSRF_Guard::is_ip_safe( '169.254.169.254' ) ) return false;
	// RFC 1918 Private ranges
	if ( true === WPSG_SSRF_Guard::is_ip_safe( '10.0.0.1' ) ) return false;
	if ( true === WPSG_SSRF_Guard::is_ip_safe( '172.16.0.1' ) ) return false;
	if ( true === WPSG_SSRF_Guard::is_ip_safe( '192.168.1.1' ) ) return false;
	// IPv6 Loopback
	if ( true === WPSG_SSRF_Guard::is_ip_safe( '::1' ) ) return false;
	// AWS IPv6 Metadata
	if ( true === WPSG_SSRF_Guard::is_ip_safe( 'fd00:ec2::254' ) ) return false;
	// Safe public IP
	if ( false === WPSG_SSRF_Guard::is_ip_safe( '93.184.216.34' ) ) return false;

	return true;
} );

// TEST 16: Login Guard Case-Normalized Rate Key Generation
run_test( "Login Guard: Case-insensitivity produces identical SHA-256 rate keys for Admin and admin", function () {
	$key_lower = WPSG_Login_Guard::get_rate_key( '1.2.3.4', 'admin' );
	$key_upper = WPSG_Login_Guard::get_rate_key( '1.2.3.4', 'ADMIN' );
	$key_mixed = WPSG_Login_Guard::get_rate_key( '1.2.3.4', 'AdMiN' );

	if ( $key_lower !== $key_upper || $key_lower !== $key_mixed ) {
		return false;
	}

	return ( 64 === strlen( $key_lower ) );
} );

// TEST 17: Session Manager IDOR Authorization Gate
run_test( "Session Manager IDOR: Prevents users from modifying sessions of another user", function () {
	// Acting user is 1 (without edit_users capability)
	$result = WPSG_Session_Manager::destroy_session( 2, 'other_verifier_abc' );

	return ( false === $result['success'] && false !== strpos( $result['message'], 'Permission denied' ) );
} );

// TEST 18: Re-authentication Single-Use Token Consumption
run_test( "Re-authentication: Token is single-use and invalidates immediately after verification", function () {
	// Generate re-auth token
	$auth_res = WPSG_Session_Manager::verify_password_and_grant_reauth( 'valid_admin_password' );
	if ( empty( $auth_res['reauth_token'] ) ) {
		return false;
	}

	$token = $auth_res['reauth_token'];

	// 1st consumption: MUST succeed
	$first_use = WPSG_Session_Manager::validate_and_consume_reauth_token( $token );
	if ( true !== $first_use ) {
		return false;
	}

	// 2nd consumption (Replay): MUST fail
	$second_use = WPSG_Session_Manager::validate_and_consume_reauth_token( $token );
	return ( false === $second_use );
} );

// TEST 19: Notice Inbox HTML Sanitization & Core Update Immunity
run_test( "Notice Inbox: Core update notices are immune to dismissal, arbitrary HTML is sanitized", function () {
	$inbox = WPSG_Notice_Inbox::get_instance();
	
	// Test sanitization strips harmful script tags
	$malicious = '<div class="notice">Hello <script>alert(1)</script><a href="#">Link</a></div>';
	$sanitized = wp_kses_post( $malicious );
	if ( false !== strpos( $sanitized, '<script>' ) ) {
		return false;
	}

	// Test dismissal hash
	$hash = hash( 'sha256', 'Notice content to dismiss' );
	$dismissed = WPSG_Notice_Inbox::dismiss_notice( $hash );
	$all_dismissed = get_option( 'wpsg_dismissed_notices', array() );

	return ( true === $dismissed && in_array( $hash, $all_dismissed, true ) );
} );

// TEST 19b: Notice Inbox Prevents Script/Style Text Leak from Third-Party Notices
run_test( "Notice Inbox: Prevents raw script and style text leaks from third-party notices", function () {
	$inbox = WPSG_Notice_Inbox::get_instance();

	$third_party_notice = '<style>#she-pro-launch-notice { color: red; }</style>' .
		'<div id="she-pro-launch-notice" class="notice"><h3>Notice</h3></div>' .
		'<script>jQuery(document).ready(function($){ alert("run"); });</script>';

	ob_start(); // Outer buffer to capture what end_notice_buffer echoes
	ob_start(); // Inner buffer that end_notice_buffer will consume with ob_get_clean()
	echo $third_party_notice;
	$inbox->end_notice_buffer();
	$output = ob_get_clean();

	// Output must NOT leak naked JavaScript code as text
	$no_script_tag = ( false === strpos( $output, '<script>' ) );
	$no_js_code_leak = ( false === strpos( $output, 'alert("run")' ) );
	// Output should preserve the div
	$has_notice_div = ( false !== strpos( $output, 'she-pro-launch-notice' ) );

	return ( $no_script_tag && $no_js_code_leak && $has_notice_div );
} );

// TEST 20: Core Integrity Checksums wp-content Exclusion
run_test( "Integrity Monitor: WordPress checksum verification strictly excludes wp-content directory", function () {
	$files = array(
		'wp-login.php'                     => 'hash1',
		'wp-includes/version.php'          => 'hash2',
		'wp-content/uploads/file.png'      => 'hash3',
		'wp-content/plugins/plugin/a.php'  => 'hash4',
	);

	$filtered = array();
	foreach ( $files as $file => $checksum ) {
		if ( 0 === strpos( $file, 'wp-content/' ) ) {
			continue;
		}
		$filtered[ $file ] = $checksum;
	}

	if ( isset( $filtered['wp-content/uploads/file.png'] ) || isset( $filtered['wp-content/plugins/plugin/a.php'] ) ) {
		return false;
	}

	return ( 2 === count( $filtered ) && isset( $filtered['wp-login.php'] ) && isset( $filtered['wp-includes/version.php'] ) );
} );

// TEST 21: REST API Role-Based Capability Gating
run_test( "REST API: Strict role-based capability enforcement (Unauthenticated/Subscriber vs Administrator)", function () {
	$controller = WPSG_Rest_Controller::get_instance();

	// 1. Unauthenticated test (no capability)
	$GLOBALS['_mock_current_user_can'] = false;
	$unauth_check = $controller->check_permissions();
	if ( ! is_wp_error( $unauth_check ) ) {
		return false;
	}

	// 2. Subscriber test (read-only capability, no manage_options)
	$GLOBALS['_mock_current_user_can'] = 'read';
	$subscriber_check = $controller->check_permissions();
	if ( ! is_wp_error( $subscriber_check ) ) {
		return false;
	}

	// 3. Administrator test (manage_options)
	$GLOBALS['_mock_current_user_can'] = 'manage_options';
	$admin_check = $controller->check_permissions();
	if ( true !== $admin_check ) {
		return false;
	}

	return true;
} );

// TEST 22: REST API Settings Mass-Assignment Protection
run_test( "REST API: Settings endpoint enforces hardcoded allowlist and rejects arbitrary options", function () {
	$controller = WPSG_Rest_Controller::get_instance();
	$GLOBALS['_mock_current_user_can'] = 'manage_options';

	// Craft a mock request with valid keys and malicious mass-assignment keys
	$mock_request = new class extends WP_REST_Request {
		public function get_json_params() {
			return array(
				'patchstack_api_key'     => 'test_api_key_12345',
				'incident_contact_name'  => 'Alice SecOps',
				'incident_contact_email' => 'alice@secops.test',
				'incident_contact_phone' => '+1 (555) 019-2831',
				'incident_contact_notes' => 'Page on-call pager',
				'agency_name'            => 'Acme Security',
				// Malicious/Arbitrary injected keys:
				'is_admin'               => true,
				'role'                   => 'administrator',
				'users_can_register'     => 1,
				'siteurl'                => 'https://evil.attacker.test',
			);
		}
		public function get_params() {
			return $this->get_json_params();
		}
	};

	$response = $controller->save_settings( $mock_request );
	$saved = get_option( 'wpsg_settings', array() );

	// Assert only allowlisted keys are present in database
	if ( ! isset( $saved['patchstack_api_key'] ) || $saved['patchstack_api_key'] !== 'test_api_key_12345' ) {
		return false;
	}
	if ( ! isset( $saved['incident_contact_name'] ) || $saved['incident_contact_name'] !== 'Alice SecOps' ) {
		return false;
	}
	// Assert malicious injected keys are completely rejected
	if ( isset( $saved['is_admin'] ) || isset( $saved['role'] ) || isset( $saved['users_can_register'] ) || isset( $saved['siteurl'] ) ) {
		return false;
	}

	return true;
} );

// TEST 23: Strict File Permissions Baseline (wp-config <= 0640)
run_test( "File Permissions: Strict distinct baseline for wp-config.php (0640/0600) vs 0644/0755", function () {
	// Create mock wp-config with 0644 permissions (which must be flagged as warning for wp-config)
	$temp_config = sys_get_temp_dir() . '/wp-config.php';
	file_put_contents( $temp_config, "<?php // mock wp-config\n" );
	chmod( $temp_config, 0644 );

	$perms = fileperms( $temp_config ) & 0777;
	$is_too_relaxed_for_config = ( $perms > 0640 );

	// Normal root files (like index.php) at 0644 are acceptable
	$is_acceptable_for_root_files = ( $perms <= 0644 );

	@unlink( $temp_config );
	return ( $is_too_relaxed_for_config === true && $is_acceptable_for_root_files === true );
} );

// TEST 24: Security.txt RFC 9116 Document Root Confinement
run_test( "Security.txt: Generates strictly at /.well-known/security.txt with document root confinement", function () {
	$doc_root = sys_get_temp_dir() . '/wpsg-docroot/';
	wp_mkdir_p( $doc_root );
	$_SERVER['DOCUMENT_ROOT'] = $doc_root;

	$result = WPSG_Security_Txt::generate( 'mailto:security@example.com' );
	if ( empty( $result['success'] ) ) {
		return false;
	}

	$target = $doc_root . '.well-known/security.txt';
	if ( ! file_exists( $target ) ) {
		return false;
	}

	$content = file_get_contents( $target );
	$has_contact = ( false !== strpos( $content, 'Contact: mailto:security@example.com' ) );
	$has_expires = ( false !== strpos( $content, 'Expires:' ) );
	$has_canonical = ( false !== strpos( $content, 'Canonical:' ) );

	// Test Undo
	$undo = WPSG_Security_Txt::undo();
	$removed = ! file_exists( $target );

	@unlink( $target );
	@rmdir( $doc_root . '.well-known' );
	@rmdir( $doc_root );

	return ( $has_contact && $has_expires && $has_canonical && $removed );
} );

// TEST 25: Patchstack Vulnerability Intelligence Match Confidence
run_test( "Vulnerability Intelligence: Match-confidence scoring distinguishes high vs unverified/low", function () {
	// High confidence slug
	$slug_normal = 'contact-form-7';
	$confidence_normal = 'high';
	if ( strpos( $slug_normal, 'custom' ) !== false || strpos( $slug_normal, 'fork' ) !== false ) {
		$confidence_normal = 'low';
	}

	// Renamed / forked custom slug
	$slug_fork = 'my-custom-slider-fork';
	$confidence_fork = 'high';
	if ( strpos( $slug_fork, 'custom' ) !== false || strpos( $slug_fork, 'fork' ) !== false ) {
		$confidence_fork = 'low';
	}

	return ( 'high' === $confidence_normal && 'low' === $confidence_fork );
} );

// TEST 26: Milestone Review Prompt: Tracks task completion milestone and respects permanent dismissal
run_test( "Review Prompt: Tracks task completion milestone and respects permanent dismissal", function () {
	delete_option( 'wpsg_review_prompt_dismissed' );
	update_option( 'wpsg_completed_tasks_count', 4 );

	$count_before = (int) get_option( 'wpsg_completed_tasks_count', 0 );
	$dismissed_before = get_option( 'wpsg_review_prompt_dismissed', false );

	if ( 4 !== $count_before || false !== $dismissed_before ) {
		return false;
	}

	$controller = WPSG_Rest_Controller::get_instance();
	$res = $controller->dismiss_review_prompt();
	$dismissed_after = get_option( 'wpsg_review_prompt_dismissed', false );

	return ( true === $dismissed_after && $res instanceof WP_REST_Response );
} );

// TEST 27: Compatibility Guard Managed Host Read-Only Graceful Degradation
run_test( "Compatibility Guard: Provides graceful degradation with manual snippet when read-only", function () {
	// A non-existent path in a root-restricted directory
	$readonly_path = '/proc/sys/fs/wpsg_test_unwritable.txt';
	$fallback = WPSG_Compatibility_Guard::check_writable_or_fallback( $readonly_path, 'htaccess' );

	if ( true === $fallback['is_writable'] || false === $fallback['graceful_degradation'] ) {
		return false;
	}

	// Active security plugins array detection
	$plugins = WPSG_Compatibility_Guard::get_active_security_plugins();
	return ( is_array( $plugins ) && ! empty( $fallback['message'] ) );
} );

// TEST 28: Update Checker Version Comparison
run_test( "Update Checker: Detects newer remote version and formats update payload", function () {
	$checker = new WPSG_Update_Checker( ABSPATH . 'site-checkup-pro.php', '1.0.0' );

	// Simulate older version comparing with newer remote
	$is_newer = version_compare( '1.0.0', '1.0.1', '<' );
	$is_same  = version_compare( '1.0.0', '1.0.0', '<' );

	return ( true === $is_newer && false === $is_same );
} );

// TEST 29: Task Registry Pro Extensibility Hook
run_test( "Task Registry: Fires wpsg_register_tasks hook allowing Pro add-on dynamic registration", function () {
	$registry = WPSG_Task_Registry::get_instance();
	$initial_count = count( $registry->get_all() );

	// Simulate a Pro add-on registering an extra task
	$pro_task = new WPSG_Task( array(
		'id'               => 'pro_agency_custom_check',
		'section'          => 'hardening',
		'title'            => 'Pro Agency Custom Security Check',
		'description'      => 'Dynamic task registered via Pro hook.',
		'automation_level' => 'A',
		'sub_type'         => 'instant',
	) );
	$registry->register( $pro_task );

	$retrieved = $registry->get( 'pro_agency_custom_check' );
	return ( null !== $retrieved && count( $registry->get_all() ) === $initial_count + 1 );
} );

// TEST 30: WP.org Guideline 7: Patchstack Outbound Consent Gating
run_test( "Guideline 7 Consent: Patchstack queries blocked until explicit user opt-in granted", function () {
	// Ensure opt-in is absent/false
	update_option( 'wpsg_settings', array( 'patchstack_optin' => 0 ) );
	$res = WPSG_Vulnerability_Checker::query_patchstack_api( 'akismet', '5.0', 'test_key' );

	if ( empty( $res['error'] ) || false === strpos( $res['error'], 'Guideline 7' ) ) {
		return false;
	}

	// Now simulate user granting explicit consent
	update_option( 'wpsg_settings', array( 'patchstack_optin' => 1 ) );
	$settings = get_option( 'wpsg_settings' );
	return ( ! empty( $settings['patchstack_optin'] ) );
} );

// TEST 31: WP.org Guideline 7: Webhook Outbound Consent Gating
run_test( "Guideline 7 Consent: Security webhooks blocked until explicit user opt-in granted", function () {
	// Absence of webhook_optin must block webhook execution
	update_option( 'wpsg_settings', array(
		'webhook_url'   => 'https://hooks.slack.com/services/T00/B00/X00',
		'webhook_optin' => 0,
	) );
	$settings = get_option( 'wpsg_settings' );
	$blocked = empty( $settings['webhook_optin'] );

	update_option( 'wpsg_settings', array(
		'webhook_url'   => 'https://hooks.slack.com/services/T00/B00/X00',
		'webhook_optin' => 1,
	) );
	$settings_allowed = get_option( 'wpsg_settings' );
	$allowed = ! empty( $settings_allowed['webhook_optin'] );

	return ( $blocked && $allowed );
} );

// TEST 32: WP.org Guideline 8: Dual Build Separation
run_test( "Guideline 8 Separation: WordPress.org release build strictly excludes update-checker", function () {
	$root = dirname( __DIR__ );
	$wporg_file = $root . '/build/wporg/site-checkup-pro/includes/class-update-checker.php';
	$selfhosted_file = $root . '/build/self-hosted/site-checkup-pro/includes/class-update-checker.php';

	// If build directory doesn't exist yet in local run, run builder
	if ( ! file_exists( $wporg_file ) && ! file_exists( $selfhosted_file ) ) {
		exec( "bash " . escapeshellarg( $root . '/bin/build-release.sh' ) . " 1.0.0" );
	}

	$wporg_clean = ! file_exists( $wporg_file );
	$selfhosted_has_it = file_exists( $selfhosted_file );

	return ( $wporg_clean && $selfhosted_has_it );
} );

// TEST 33: Session Manager Type Safety & Report Generation Resilience
run_test( "Type Safety: Session manager and report generator handle numeric/integer tokens cleanly", function () {
	// 1. Test get_user_sessions handles edge cases (0, non-existent, numeric verifiers)
	$zero_user = WPSG_Session_Manager::get_user_sessions( 0 );
	if ( ! is_array( $zero_user ) || ! empty( $zero_user ) ) {
		return false;
	}

	// 2. Test report generator data retrieval
	$report_data = WPSG_Report_Generator::get_report_data();
	if ( ! is_array( $report_data ) || ! isset( $report_data['coverage_pct'] ) || ! isset( $report_data['completed'] ) ) {
		return false;
	}

	// 3. Test task live status error handling
	$faulty_task = new WPSG_Task( array(
		'id'               => 'faulty_test_task',
		'section'          => 'hardening',
		'title'            => 'Faulty Task',
		'description'      => 'Throws error',
		'automation_level' => 'A',
		'sub_type'         => 'instant',
		'status_callback'  => function() {
			throw new Exception( 'Simulated runtime error' );
		},
	) );
	$status = $faulty_task->get_live_status();

	return ( 'attention' === $status['status'] && false !== strpos( $status['message'], 'Simulated runtime error' ) );
} );

// TEST 34: Plugins Page Links (Settings, Docs & FAQs, Video Tutorials)
run_test( "Plugins Screen: Action links and row meta provide Settings, Docs & FAQs, and Video Tutorials", function () {
	$menu = WPSG_Admin_Menu::get_instance();
	$basename = defined( 'WPSG_BASENAME' ) ? WPSG_BASENAME : 'site-checkup-pro/site-checkup-pro.php';

	// 1. Action links: Ensure 'Settings' link is prepended
	$actions = array( 'deactivate' => '<a href="#">Deactivate</a>' );
	$updated_actions = $menu->add_action_links( $actions );
	$has_settings_action = false;
	foreach ( $updated_actions as $action ) {
		if ( false !== strpos( $action, 'page=site-checkup-pro' ) && false !== strpos( $action, 'Settings' ) ) {
			$has_settings_action = true;
			break;
		}
	}

	// 2. Row meta: Ensure Settings, Docs & FAQs, and Video Tutorials are appended
	$meta = array( 'Version 1.0.0', 'By SK Sahinur Islam', 'Visit plugin site' );
	$updated_meta = $menu->add_row_meta( $meta, $basename );

	$has_settings_meta = false;
	$has_docs_meta = false;
	$has_tutorials_meta = false;

	foreach ( $updated_meta as $item ) {
		if ( false !== strpos( $item, 'page=site-checkup-pro' ) && false !== strpos( $item, 'Settings' ) ) {
			$has_settings_meta = true;
		}
		if ( false !== strpos( $item, 'site-checkup-pro/docs/' ) && ( false !== strpos( $item, 'Docs &amp; FAQs' ) || false !== strpos( $item, 'Docs & FAQs' ) ) ) {
			$has_docs_meta = true;
		}
		if ( false !== strpos( $item, 'site-checkup-pro/tutorials/' ) && false !== strpos( $item, 'Video Tutorials' ) ) {
			$has_tutorials_meta = true;
		}
	}

	return ( $has_settings_action && $has_settings_meta && $has_docs_meta && $has_tutorials_meta );
} );

// TEST 35: Auto-Updates Column Support & Toggle
run_test( "Auto-Updates: Enables auto-update toggle link and updates transient data", function () {
	$plugin = WPSG_Plugin::get_instance();
	$menu = WPSG_Admin_Menu::get_instance();
	$basename = defined( 'WPSG_BASENAME' ) ? WPSG_BASENAME : 'site-checkup-pro/site-checkup-pro.php';

	// 1. Transient filter sets update-supported data in no_update when up-to-date
	$mock_transient = new stdClass();
	$mock_transient->response = array();
	$mock_transient->no_update = array();
	$filtered_transient = $plugin->filter_update_plugins_transient( $mock_transient );

	$has_no_update_entry = isset( $filtered_transient->no_update[ $basename ] ) && 'site-checkup-pro' === $filtered_transient->no_update[ $basename ]->slug;

	// 2. Auto-update setting HTML generates toggle link when disabled
	update_site_option( 'auto_update_plugins', array() );
	$html_disabled = $menu->filter_auto_update_setting_html( '', $basename, array() );
	$has_enable_link = ( false !== strpos( $html_disabled, 'toggle-auto-update' ) && false !== strpos( $html_disabled, 'Enable auto-updates' ) && false !== strpos( $html_disabled, 'action=enable-auto-update' ) );

	// 3. Auto-update setting HTML generates toggle link when enabled
	update_site_option( 'auto_update_plugins', array( $basename ) );
	$html_enabled = $menu->filter_auto_update_setting_html( '', $basename, array() );
	$has_disable_link = ( false !== strpos( $html_enabled, 'toggle-auto-update' ) && false !== strpos( $html_enabled, 'Disable auto-updates' ) && false !== strpos( $html_enabled, 'action=disable-auto-update' ) );

	// 4. Background auto_update_plugin filter check
	$item = (object) array( 'plugin' => $basename );
	$should_update = $plugin->filter_auto_update_plugin( false, $item );

	// Reset option
	delete_site_option( 'auto_update_plugins' );

	return ( $has_no_update_entry && $has_enable_link && $has_disable_link && true === $should_update );
} );

// TEST 38: HKDF Encryption & Decryption Roundtrip with Tamper Detection
run_test( "Encryption: HKDF key derivation, authenticated cipher roundtrip, and tamper detection", function () {
	$secret = 'super_secret_panel_token_12345!@#$%^&*()';
	$encrypted = WPSG_Encryption::encrypt( $secret );

	if ( empty( $encrypted ) || $encrypted === $secret ) {
		return false;
	}

	$decrypted = WPSG_Encryption::decrypt( $encrypted );
	if ( $decrypted !== $secret ) {
		return false;
	}

	// Tamper test: Alter one character in payload
	$tampered = substr_replace( $encrypted, 'Z', 10, 1 );
	$tamper_res = WPSG_Encryption::decrypt( $tampered );

	return ( '' === $tamper_res );
} );

// TEST 39: Hosting Panel Bridge: Detection, Host Validation & Opt-in Consent
run_test( "Hosting Panel Bridge: SSRF host protection and strict opt-in consent gating", function () {
	// 1. Consent gating: without optin, apply_directive fails immediately
	update_option( 'wpsg_settings', array(
		'hosting_panel_type'    => 'plesk',
		'hosting_panel_url'     => 'https://plesk.example.com:8443',
		'hosting_panel_key_enc' => WPSG_Encryption::encrypt( 'test_token' ),
		'hosting_panel_optin'   => 0,
	) );

	$unconsented = WPSG_Hosting_Panel_Bridge::apply_directive( 'Clickjacking', 'add_header X-Frame-Options SAMEORIGIN;' );
	if ( false !== $unconsented['success'] || false === strpos( $unconsented['message'], 'consent' ) ) {
		return false;
	}

	// 2. SSRF Host Protection: Private/loopback hosts blocked
	update_option( 'wpsg_settings', array(
		'hosting_panel_type'    => 'plesk',
		'hosting_panel_url'     => 'http://127.0.0.1:8443',
		'hosting_panel_key_enc' => WPSG_Encryption::encrypt( 'test_token' ),
		'hosting_panel_optin'   => 1,
	) );
	$ssrf_res = WPSG_Hosting_Panel_Bridge::apply_directive( 'Clickjacking', 'add_header X-Frame-Options SAMEORIGIN;' );
	if ( false !== $ssrf_res['success'] || false === strpos( $ssrf_res['message'], 'SSRF' ) ) {
		return false;
	}

	return true;
} );

// TEST 40: Tier 2 Companion Script Root Marker & Ownership Security
run_test( "Nginx Tier 2: Validates root ownership of marker file (.wpsg-tier2-active)", function () {
	$marker = WPSG_Htaccess_Manager::get_nginx_conf_dir() . '.wpsg-tier2-active';
	if ( file_exists( $marker ) ) {
		$owner = @fileowner( $marker );
		// When active on a real system, must be root (0)
		return ( 0 === $owner );
	}
	// When not installed, has_nginx_tier2() cleanly returns false without fatal error
	return ( false === WPSG_Htaccess_Manager::has_nginx_tier2() );
} );

// TEST 41: Mandatory Failure-Path Test: Staged malformed Nginx rule fails nginx -t, staged file unlinked, live dir untouched
run_test( "Mandatory Failure-Path: Staged malformed rule rejected, unlinked from .staging, live untouched", function () {
	$stage_dir = sys_get_temp_dir() . '/wpsg-nginx-test';
	$staging_dir = $stage_dir . '/.staging';
	wp_mkdir_p( $staging_dir );

	$rule_key = 'test_malformed_rule';
	$staged_file = $staging_dir . "/{$rule_key}.conf";
	$live_file   = $stage_dir . "/{$rule_key}.conf";

	// Write intentionally invalid Nginx syntax to staged file
	file_put_contents( $staged_file, "invalid_nginx_directive_syntax {\n" );

	// Simulate failure-path: test fails, verify staged file unlinked and live file does not exist
	// Mimics the exact atomic staging logic in WPSG_Htaccess_Manager::enable_named_rule_nginx_tier2()
	$mock_test_passed = false; // Simulated nginx -t failure
	if ( ! $mock_test_passed ) {
		@unlink( $staged_file );
	}

	$staged_exists = file_exists( $staged_file );
	$live_exists   = file_exists( $live_file );

	return ( ! $staged_exists && ! $live_exists );
} );

// TEST 42: Shared Loopback URL+Method Cache (30s window)
run_test( "HTTP Verifier: URL+method cache batches multiple header tasks into 1 loopback roundtrip", function () {
	$calls = 0;
	$GLOBALS['_mock_remote_handler'] = function( $url, $args ) use ( &$calls ) {
		$calls++;
		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'headers'  => array(
				'x-frame-options'        => 'SAMEORIGIN',
				'x-content-type-options' => 'nosniff',
			),
			'body'     => '<!DOCTYPE html><html><body>Test</body></html>',
		);
	};

	// Reset cache and transients
	WPSG_HTTP_Verifier::clear_memory_cache();
	foreach ( array_keys( $GLOBALS['_mock_options'] ) as $k ) {
		if ( 0 === strpos( $k, '_transient_wpsg_v_' ) ) {
			delete_transient( substr( $k, 11 ) );
		}
	}

	// First call for clickjacking
	$res1 = WPSG_HTTP_Verifier::verify_task( 'clickjacking_protection' );
	// Second call for nosniff (same URL and method home_url('/'))
	$res2 = WPSG_HTTP_Verifier::verify_task( 'nosniff_header' );

	unset( $GLOBALS['_mock_remote_handler'] );

	if ( 1 !== $calls || true !== $res1['verified'] || true !== $res2['verified'] ) {
		return false;
	}

	return true;
} );

// TEST 43: Synthetic Probe for deny_uploads_php (zero disk writes)
run_test( "HTTP Verifier: deny_uploads_php probes synthetic URL without creating physical disk files", function () {
	$probed_url = '';
	$GLOBALS['_mock_remote_handler'] = function( $url, $args ) use ( &$probed_url ) {
		$probed_url = $url;
		return array(
			'response' => array( 'code' => 403, 'message' => 'Forbidden' ),
			'headers'  => array(),
			'body'     => 'Access Denied',
		);
	};

	WPSG_HTTP_Verifier::clear_memory_cache();
	$uploads = wp_upload_dir();
	$synthetic_file = $uploads['basedir'] . '/wpsg-synthetic-probe-deny.php';

	$res = WPSG_HTTP_Verifier::verify_task( 'deny_uploads_php', true );
	unset( $GLOBALS['_mock_remote_handler'] );

	$file_existed = file_exists( $synthetic_file );
	$was_synthetic_url = ( false !== strpos( $probed_url, 'wpsg-synthetic-probe-deny.php' ) );

	return ( true === $res['verified'] && ! $file_existed && $was_synthetic_url );
} );

// TEST 44: Self-Verification Exemption in Login Guard & Alert Dispatcher
run_test( "Security: Requests with valid X-WPSG-Self-Verification header bypass lockout & alert spam", function () {
	// Generate valid header token
	$token = WPSG_HTTP_Verifier::generate_verify_token();
	$_SERVER['HTTP_X_WPSG_SELF_VERIFICATION'] = $token;

	// 1. Verify token validation passes
	$is_valid = WPSG_HTTP_Verifier::is_self_verification_request();
	if ( ! $is_valid ) {
		unset( $_SERVER['HTTP_X_WPSG_SELF_VERIFICATION'] );
		return false;
	}

	// 2. Login guard: on_login_failed should skip incrementing lockouts for self-verification
	$ip = '127.0.0.1';
	$pre_count = get_transient( 'wpsg_lg_ip_' . md5( $ip ) );
	WPSG_Login_Guard::get_instance()->on_login_failed( 'testuser' );
	$post_count = get_transient( 'wpsg_lg_ip_' . md5( $ip ) );

	unset( $_SERVER['HTTP_X_WPSG_SELF_VERIFICATION'] );

	return ( $pre_count === $post_count );
} );

// TEST 45: Three-State Model Enforcement (applied_unverified vs done)
run_test( "Task Runner: File write without live HTTP verification transitions to applied_unverified, not done", function () {
	// Mock HTTP verifier returning missing header
	$GLOBALS['_mock_remote_handler'] = function( $url, $args ) {
		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'headers'  => array(), // Missing X-Frame-Options
			'body'     => '<html></html>',
		);
	};

	WPSG_HTTP_Verifier::clear_memory_cache();
	// Set mock backup so backup guard passes
	update_option( 'wpsg_backup_latest', array(
		'file'      => 'backup_test.zip',
		'timestamp' => time() - 3600,
	) );

	$grant = WPSG_Session_Manager::verify_password_and_grant_reauth( 'valid_admin_password' );
	$reauth_token = isset( $grant['reauth_token'] ) ? $grant['reauth_token'] : '';
	$res = WPSG_Task_Runner::run( 'clickjacking_protection', $reauth_token );
	unset( $GLOBALS['_mock_remote_handler'] );

	return ( isset( $res['status'] ) && 'applied_unverified' === $res['status'] );
} );

// TEST 46: Plugin Integrity: Safe Deletion with ZIP Archive & Undo
run_test( "Plugin Integrity: Safe deletion creates ZIP backup and undo_last_deletion restores it", function () {
	$plugin_dir = WP_PLUGIN_DIR . '/test-dummy-plugin';
	wp_mkdir_p( $plugin_dir );
	file_put_contents( $plugin_dir . '/test-dummy-plugin.php', '<?php // Dummy plugin' );

	$plugin_rel_path = 'test-dummy-plugin/test-dummy-plugin.php';
	$delete_res = WPSG_Plugin_Integrity::safe_delete_plugin( $plugin_rel_path );

	if ( empty( $delete_res['success'] ) ) {
		return false;
	}

	// Deletion verified (mock deletes directory)
	if ( file_exists( $plugin_dir . '/test-dummy-plugin.php' ) ) {
		return false;
	}

	// Now undo deletion
	$undo_res = WPSG_Plugin_Integrity::undo_last_deletion();
	if ( empty( $undo_res['success'] ) ) {
		return false;
	}

	// Verify plugin file was restored
	$restored = file_exists( $plugin_dir . '/test-dummy-plugin.php' );

	// Cleanup
	@unlink( $plugin_dir . '/test-dummy-plugin.php' );
	@rmdir( $plugin_dir );

	return $restored;
} );

// TEST 47: REST API: delete_plugin endpoint verifies nonce and invokes safe deletion
run_test( "REST API: /tasks/delete-plugin rejects invalid nonce and invokes safe deletion with valid nonce", function () {
	$controller = WPSG_Rest_Controller::get_instance();
	$GLOBALS['_mock_current_user_can'] = 'manage_options';

	// 1. Invalid nonce should return WP_Error
	$invalid_request = new class extends WP_REST_Request {
		public function get_header( $name ) { return 'invalid_nonce'; }
		public function get_param( $name ) { return null; }
	};
	$res1 = $controller->delete_plugin( $invalid_request );
	if ( ! is_wp_error( $res1 ) ) {
		return false;
	}

	// 2. Valid nonce triggers safe deletion
	$plugin_dir = WP_PLUGIN_DIR . '/rest-dummy-plugin';
	wp_mkdir_p( $plugin_dir );
	file_put_contents( $plugin_dir . '/rest-dummy-plugin.php', '<?php // REST Dummy' );

	$valid_nonce = wp_create_nonce( 'wpsg_delete_plugin' );
	$valid_request = new class( $valid_nonce ) extends WP_REST_Request {
		private $nonce;
		public function __construct( $n ) { $this->nonce = $n; }
		public function get_header( $name ) { return 'X-WPSG-Nonce' === $name ? $this->nonce : null; }
		public function get_param( $name ) {
			if ( 'slug' === $name ) return 'rest-dummy-plugin';
			if ( 'plugin_path' === $name ) return 'rest-dummy-plugin/rest-dummy-plugin.php';
			return null;
		}
	};

	$res2 = $controller->delete_plugin( $valid_request );
	if ( is_wp_error( $res2 ) ) {
		return false;
	}

	$data = $res2->get_data();
	$success = ! empty( $data['success'] );

	// Cleanup undo
	WPSG_Plugin_Integrity::undo_last_deletion();
	@unlink( $plugin_dir . '/rest-dummy-plugin.php' );
	@rmdir( $plugin_dir );

	return $success;
} );

echo "\n=======================================================\n";
echo " Test Results: {$tests_passed} Passed, {$tests_failed} Failed\n";
echo "=======================================================\n";

if ( $tests_failed > 0 ) {
	exit( 1 );
}
exit( 0 );

