<?php
/**
 * Safe wp-config.php Manager
 *
 * Provides safe, marker-delimited constant management and salt rotation with automatic backups.
 * Strictly writes hardcoded predefined constants; accepts no arbitrary input.
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
 * Class WPSG_Wp_Config_Manager
 */
class WPSG_Wp_Config_Manager {

	/**
	 * Marker comment for injected constants.
	 */
	const MARKER_BEGIN = "/* BEGIN SiteCheckupPro-Config */\n";
	const MARKER_END   = "/* END SiteCheckupPro-Config */\n";

	/**
	 * Locate wp-config.php path (handles standard webroot and one level above webroot).
	 *
	 * @return string|false
	 */
	public static function get_config_path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}

		if ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return dirname( ABSPATH ) . '/wp-config.php';
		}

		return false;
	}

	/**
	 * Check if wp-config.php is writable.
	 *
	 * @return bool
	 */
	public static function is_writable() {
		$path = self::get_config_path();
		return ( $path && wp_is_writable( $path ) );
	}

	/**
	 * Create timestamped safety backup of wp-config.php.
	 *
	 * @return string|false Path to backup or false.
	 */
	public static function backup_config() {
		$config_path = self::get_config_path();
		if ( ! $config_path || ! file_exists( $config_path ) ) {
			return false;
		}

		$backup_dir  = WPSG_Htaccess_Manager::get_backup_dir();
		$token       = wp_generate_password( 32, false, false );
		$backup_file = $backup_dir . 'wp-config-' . gmdate( 'Ymd-His' ) . '-' . $token . '.bak';

		if ( copy( $config_path, $backup_file ) ) {
			return $backup_file;
		}

		return false;
	}

	/**
	 * Update or add predefined security constants (e.g. DISALLOW_FILE_EDIT).
	 *
	 * @param array $constants Associative array of constant_name => boolean_value.
	 * @return array Array with 'success' (bool), 'message' (string).
	 */
	public static function update_constants( array $constants ) {
		$config_path = self::get_config_path();
		if ( ! $config_path || ! wp_is_writable( $config_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php is not writable or could not be located.', 'site-checkup-pro' ),
			);
		}

		// Only allow whitelisted security constants.
		$allowed = array( 'DISALLOW_FILE_EDIT', 'DISALLOW_FILE_MODS', 'WP_DEBUG_DISPLAY' );
		$clean   = array();
		foreach ( $constants as $key => $val ) {
			if ( in_array( $key, $allowed, true ) ) {
				$clean[ $key ] = (bool) $val;
			}
		}

		if ( empty( $clean ) ) {
			return array(
				'success' => false,
				'message' => __( 'No valid security constants specified.', 'site-checkup-pro' ),
			);
		}

		// Backup first.
		$backup_path = self::backup_config();
		if ( ! $backup_path ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create safety backup of wp-config.php.', 'site-checkup-pro' ),
			);
		}

		$content = file_get_contents( $config_path );

		// Remove existing SiteCheckupPro block if present.
		$pattern = '/' . preg_quote( self::MARKER_BEGIN, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '/s';
		$content = preg_replace( $pattern, '', $content );

		// Build new block.
		$block = self::MARKER_BEGIN;
		foreach ( $clean as $k => $v ) {
			$val_str = $v ? 'true' : 'false';
			$block  .= "if ( ! defined( '{$k}' ) ) { define( '{$k}', {$val_str} ); }\n";
		}
		$block .= self::MARKER_END;

		// Insert immediately before /* That's all, stop editing! Happy publishing. */
		$stop_editing_needle = "/* That's all, stop editing!";
		if ( false !== strpos( $content, $stop_editing_needle ) ) {
			$new_content = str_replace( $stop_editing_needle, $block . $stop_editing_needle, $content );
		} else {
			// Fallback: insert after <?php
			$new_content = preg_replace( '/^<\?php\s+/m', "<?php\n" . $block, $content, 1 );
		}

		$written = file_put_contents( $config_path, $new_content );

		if ( false === $written ) {
			// Rollback.
			copy( $backup_path, $config_path );
			return array(
				'success' => false,
				'message' => __( 'Failed to write updated constants to wp-config.php. Rolled back.', 'site-checkup-pro' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Security constants updated successfully in wp-config.php.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Rotate WordPress security salts and auth keys.
	 *
	 * @return array Array with 'success' (bool), 'message' (string).
	 */
	public static function rotate_salts() {
		$config_path = self::get_config_path();
		if ( ! $config_path || ! wp_is_writable( $config_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php is not writable.', 'site-checkup-pro' ),
			);
		}

		// Safety backup.
		$backup_path = self::backup_config();
		if ( ! $backup_path ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create safety backup of wp-config.php.', 'site-checkup-pro' ),
			);
		}

		$content = file_get_contents( $config_path );

		$salts = array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);

		// Generate cryptographically secure salts without quotes or backreferences.
		foreach ( $salts as $salt_key ) {
			$new_salt = wp_generate_password( 64, true, false );

			// Replace existing define statement on its own line safely without preg backreference issues.
			$regex = '/^[ \t]*define\s*\(\s*[\'"]' . preg_quote( $salt_key, '/' ) . '[\'"].*?\);[ \t]*$/m';
			if ( preg_match( $regex, $content ) ) {
				$content = preg_replace_callback( $regex, function () use ( $salt_key, $new_salt ) {
					return "define( '{$salt_key}', " . var_export( $new_salt, true ) . " );";
				}, $content );
			}
		}

		$written = file_put_contents( $config_path, $content );

		if ( false === $written ) {
			copy( $backup_path, $config_path );
			return array(
				'success' => false,
				'message' => __( 'Failed to write updated salts. Restored from backup.', 'site-checkup-pro' ),
			);
		}

		update_option( 'wpsg_salts_last_rotated', time() );

		return array(
			'success' => true,
			'message' => __( 'All 8 WordPress security salts rotated successfully.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Diff preview for wp-config.php changes (shows only constants being modified; never shows passwords or salts).
	 *
	 * @param array $constants Array of constants.
	 * @return array
	 */
	public static function get_diff_preview( array $constants ) {
		$block = self::MARKER_BEGIN;
		foreach ( $constants as $k => $v ) {
			$val_str = $v ? 'true' : 'false';
			$block  .= "if ( ! defined( '{$k}' ) ) { define( '{$k}', {$val_str} ); }\n";
		}
		$block .= self::MARKER_END;

		return array(
			'file'         => 'wp-config.php',
			'insert_block' => $block,
			'description'  => __( 'The following configuration block will be inserted into wp-config.php.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Check if a constant is present in the wp-config.php file content.
	 *
	 * @param string $constant_name Name of constant.
	 * @return bool
	 */
	public static function has_constant_in_file( $constant_name ) {
		$config_path = self::get_config_path();
		if ( ! $config_path || ! file_exists( $config_path ) ) {
			return false;
		}

		$content = file_get_contents( $config_path );
		if ( false === $content ) {
			return false;
		}

		return (bool) preg_match( "/define\s*\(\s*['\"]" . preg_quote( $constant_name, '/' ) . "['\"]\s*,\s*(true|1)\s*\)/i", $content );
	}
}
