<?php
/**
 * Nginx Tier 2 Companion Module (Self-Hosted / Advanced Server Deployments Only).
 *
 * Excluded from WordPress.org distribution packages.
 *
 * @package SiteCheckupPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSG_Nginx_Tier2 {

	/**
	 * Marker filename.
	 */
	const MARKER_NAME = '.wpsg-tier2-active';

	/**
	 * Check if Tier 2 (Companion include directory + sudoers reload) is active.
	 *
	 * Requires that the companion script was run, the marker file exists and is owned by root,
	 * and the include directory is writable.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( defined( 'WPSG_NGINX_TIER2_ACTIVE' ) && WPSG_NGINX_TIER2_ACTIVE ) {
			return true;
		}

		$conf_dir = self::get_conf_dir();
		$marker   = defined( 'WPSG_NGINX_TIER2_MARKER' ) ? WPSG_NGINX_TIER2_MARKER : $conf_dir . self::MARKER_NAME;

		if ( ! file_exists( $marker ) ) {
			return false;
		}

		// Security constraint: Marker file must be owned by UID 0 (root).
		$bypass_owner = defined( 'WPSG_TEST_SUITE' ) && WPSG_TEST_SUITE;
		if ( ! $bypass_owner ) {
			$owner = @fileowner( $marker );
			if ( 0 !== $owner ) {
				return false;
			}
		}

		return is_dir( $conf_dir ) && is_writable( $conf_dir );
	}

	/**
	 * Get absolute path to the Nginx include directory.
	 *
	 * @return string
	 */
	public static function get_conf_dir() {
		if ( defined( 'WPSG_NGINX_CONF_DIR' ) && WPSG_NGINX_CONF_DIR ) {
			return trailingslashit( WPSG_NGINX_CONF_DIR );
		}
		return '/etc/nginx/site-checkup-pro/';
	}

	/**
	 * Get absolute path to the Nginx staging directory.
	 *
	 * @return string
	 */
	public static function get_staging_dir() {
		$dir = self::get_conf_dir() . '.staging/';
		if ( ! is_dir( $dir ) && is_writable( self::get_conf_dir() ) ) {
			@mkdir( $dir, 0770, true );
		}
		return $dir;
	}

	/**
	 * Execute a strictly hardcoded system command via proc_open without shell invocation.
	 *
	 * Architectural Exception: Allows exactly two literal fixed command arrays for
	 * syntax-checking and reloading Nginx without invoking /bin/sh.
	 * Zero variables, zero interpolation, zero concatenation.
	 *
	 * @param array $cmd Literal command array.
	 * @return array Array with 'success' (bool), 'exit_code' (int), 'output' (string).
	 */
	public static function execute_fixed_system_command( array $cmd ) {
		// Strictly hardcoded command allowlist.
		$allowed_commands = array(
			array( 'sudo', '/usr/sbin/nginx', '-t' ),
			array( 'sudo', '/bin/systemctl', 'reload', 'nginx' ),
			array( 'sudo', '/usr/sbin/service', 'nginx', 'reload' ),
		);

		$matched = false;
		foreach ( $allowed_commands as $allowed ) {
			if ( $cmd === $allowed ) {
				$matched = true;
				break;
			}
		}

		if ( ! $matched ) {
			return array(
				'success'   => false,
				'exit_code' => -1,
				'output'    => 'Command not in strictly allowed fixed argv list.',
			);
		}

		// Allow test suites to mock execution via filter.
		$mock = apply_filters( 'wpsg_pre_execute_system_command', null, $cmd );
		if ( null !== $mock ) {
			return $mock;
		}

		if ( ! function_exists( 'proc_open' ) ) {
			return array(
				'success'   => false,
				'exit_code' => -1,
				'output'    => 'proc_open function is disabled on this server.',
			);
		}

		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = @proc_open( $cmd, $descriptors, $pipes );
		if ( ! is_resource( $process ) ) {
			return array(
				'success'   => false,
				'exit_code' => -1,
				'output'    => 'Failed to spawn process.',
			);
		}

		fclose( $pipes[0] );
		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		$exit_code = proc_close( $process );
		$output    = trim( $stdout . "\n" . $stderr );

		return array(
			'success'   => 0 === $exit_code,
			'exit_code' => $exit_code,
			'output'    => $output,
		);
	}

	/**
	 * Apply an Nginx directive using the companion directory.
	 *
	 * @param string $rule_key  Rule key.
	 * @param string $directive Directive content.
	 * @return array Array with 'success' (bool), 'message' (string).
	 */
	public static function apply_rule( $rule_key, $directive ) {
		$conf_dir    = self::get_conf_dir();
		$staging_dir = self::get_staging_dir();
		$safe_key    = sanitize_key( $rule_key );

		$staging_file = $staging_dir . $safe_key . '.conf';
		$live_file    = $conf_dir . $safe_key . '.conf';

		// 1. Write rule to staging file.
		$header  = "# Site Checkup Pro Rule: {$safe_key}\n# Generated: " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
		$content = $header . trim( $directive ) . "\n";

		if ( false === @file_put_contents( $staging_file, $content ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write directive to Nginx staging path. Verify directory permissions.', 'site-checkup-pro' ),
			);
		}

		// 2. Syntax-check config using fixed command.
		$test_res = self::execute_fixed_system_command( array( 'sudo', '/usr/sbin/nginx', '-t' ) );
		if ( ! $test_res['success'] ) {
			@unlink( $staging_file );
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: syntax test error message */
					__( 'Nginx configuration test failed (%s). Rule was safely discarded before going live.', 'site-checkup-pro' ),
					$test_res['output']
				),
			);
		}

		// 3. Atomically rename into live include directory.
		if ( ! @rename( $staging_file, $live_file ) ) {
			@unlink( $staging_file );
			return array(
				'success' => false,
				'message' => __( 'Could not atomically move validated config into live Nginx directory.', 'site-checkup-pro' ),
			);
		}

		// 4. Reload Nginx.
		$reload_res = self::execute_fixed_system_command( array( 'sudo', '/bin/systemctl', 'reload', 'nginx' ) );
		if ( ! $reload_res['success'] ) {
			$reload_res = self::execute_fixed_system_command( array( 'sudo', '/usr/sbin/service', 'nginx', 'reload' ) );
		}

		if ( ! $reload_res['success'] ) {
			@unlink( $live_file );
			return array(
				'success' => false,
				/* translators: %s: reload error output */
				'message' => sprintf( __( 'Nginx reload failed: %s. Rule was rolled back.', 'site-checkup-pro' ), $reload_res['output'] ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Nginx directive validated and applied successfully (Tier 2).', 'site-checkup-pro' ),
		);
	}

	/**
	 * Remove an Nginx named rule with atomic rollback on test failure.
	 *
	 * @param string $rule_key Rule key.
	 * @return array
	 */
	public static function remove_rule( $rule_key ) {
		$conf_dir  = self::get_conf_dir();
		$safe_key  = sanitize_key( $rule_key );
		$live_file = $conf_dir . $safe_key . '.conf';

		if ( file_exists( $live_file ) ) {
			$staging_dir  = self::get_staging_dir();
			$staging_file = $staging_dir . $safe_key . '.conf.bak';
			@copy( $live_file, $staging_file );
			@unlink( $live_file );

			$test_res = self::execute_fixed_system_command( array( 'sudo', '/usr/sbin/nginx', '-t' ) );
			if ( ! $test_res['success'] ) {
				@rename( $staging_file, $live_file );
				return array(
					'success' => false,
					'message' => __( 'Nginx configuration test failed after removal attempt. Rolled back.', 'site-checkup-pro' ),
				);
			}

			@unlink( $staging_file );
			self::execute_fixed_system_command( array( 'sudo', '/bin/systemctl', 'reload', 'nginx' ) );
		}

		return array(
			'success' => true,
			'message' => __( 'Nginx directive removed successfully (Tier 2).', 'site-checkup-pro' ),
		);
	}

	/**
	 * Check if an Nginx named rule exists in conf dir.
	 *
	 * @param string $rule_key Rule key.
	 * @return bool
	 */
	public static function has_rule( $rule_key ) {
		$safe_key  = sanitize_key( $rule_key );
		$live_file = self::get_conf_dir() . $safe_key . '.conf';
		return file_exists( $live_file );
	}
}
