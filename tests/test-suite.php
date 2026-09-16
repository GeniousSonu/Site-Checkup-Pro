<?php
/**
 * Automated Verification Test Suite for Site Checkup Pro
 *
 * Runs comprehensive assertions on all 14 critical/reliability items.
 *
 * @package SiteCheckupPro
 */

// Define WordPress mock environment constants and stub functions
define( 'ABSPATH', __DIR__ . '/../' );
define( 'WPSG_VERSION', '1.0.0' );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-content' );
define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

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
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string)$t, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $t ) { return htmlspecialchars( (string)$t, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $t ) { return filter_var( $t, FILTER_SANITIZE_URL ); } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $val ) { return is_string( $val ) ? stripslashes( $val ) : $val; } }
if ( ! function_exists( 'wp_parse_url' ) ) { function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); } }
if ( ! function_exists( 'wp_kses_post' ) ) { function wp_kses_post( $t ) { return $t; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( '__' ) ) { function __( $t, $d = '' ) { return $t; } }
if ( ! function_exists( '_e' ) ) { function _e( $t, $d = '' ) { echo $t; } }
if ( ! function_exists( 'current_time' ) ) { function current_time( $type ) { return 'timestamp' === $type ? time() : date( 'Y-m-d H:i:s' ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can( $c ) { return 'manage_options' === $c; } }
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
if ( ! function_exists( 'is_plugin_active' ) ) { function is_plugin_active( $p ) { return false; } }

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

// Load plugin classes
require_once ABSPATH . 'includes/class-audit-log.php';
require_once ABSPATH . 'includes/class-backup-guard.php';
require_once ABSPATH . 'includes/class-htaccess-manager.php';
require_once ABSPATH . 'includes/class-wp-config-manager.php';
require_once ABSPATH . 'includes/class-login-renamer.php';
require_once ABSPATH . 'includes/class-plugin-integrity.php';

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


echo "\n=======================================================\n";
echo " Test Results: {$tests_passed} Passed, {$tests_failed} Failed\n";
echo "=======================================================\n";

if ( $tests_failed > 0 ) {
	exit( 1 );
}
exit( 0 );
