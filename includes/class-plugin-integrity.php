<?php
/**
* Plugin Integrity Checker & Safe Plugin Deleter
 *
 * Scans installed plugins against WordPress.org API with transient caching.
 * Safely backs up plugin directory to ZIP before deactivating and deleting.
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
 * Class WPSG_Plugin_Integrity
 */
class WPSG_Plugin_Integrity {

	/**
	 * Transient key for caching WP.org API responses.
	 */
	const CACHE_KEY = 'wpsg_plugin_integrity_cache';

	/**
	 * Cache TTL: 24 hours.
	 */
	const CACHE_TTL = 86400;

	/**
	 * Denylist of risky/unwanted plugins typically left behind after dev/migration.
	 */
	public static $unwanted_slugs = array(
		'better-search-replace'  => 'Better Search Replace',
		'duplicate-page'         => 'Duplicate Page',
		'wp-file-manager'        => 'WP File Manager',
		'file-manager-advanced'  => 'File Manager Advanced',
		'string-locator'         => 'String Locator',
		'database-browser'       => 'Database Browser',
		'adminer'                => 'Adminer for WordPress',
	);

	/**
	 * Detect installed plugins matching the unwanted/migration denylist.
	 *
	 * @return array
	 */
	public static function detect_unwanted_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}

		$all_plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$found       = array();

		foreach ( $all_plugins as $plugin_path => $plugin_meta ) {
			$slug = dirname( $plugin_path );
			if ( '.' === $slug || empty( $slug ) ) {
				$slug = basename( $plugin_path, '.php' );
			}

			if ( array_key_exists( $slug, self::$unwanted_slugs ) ) {
				$is_active = is_plugin_active( $plugin_path );
				$found[]   = array(
					'slug'        => $slug,
					'name'        => $plugin_meta['Name'],
					'version'     => $plugin_meta['Version'],
					'is_active'   => $is_active,
					'plugin_path' => $plugin_path,
				);
			}
		}

		if ( ! empty( $found ) ) {
			$names = wp_list_pluck( $found, 'name' );
			return array(
				'status'   => 'attention',
				'plugins'  => $found,
				'message'  => sprintf(
					/* translators: 1: count of plugins, 2: list of names */
					__( 'Found %1$d unwanted migration/management plugin(s) installed: %2$s. It is recommended to remove these after use.', 'site-checkup-pro' ),
					count( $found ),
					implode( ', ', $names )
				),
			);
		}

		return array(
			'status'  => 'done',
			'plugins' => array(),
			'message' => __( 'No leftover or risky development plugins detected from the denylist.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Get directory path for storing plugin zip backups.
	 *
	 * @return string
	 */
	public static function get_plugin_backup_dir() {
		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . 'wpsg-backups/plugins/';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Ensure access-denial guards always exist across Apache, LiteSpeed, Nginx, and IIS.
		$htaccess_content = "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n";
		if ( ! file_exists( $dir . '.htaccess' ) ) {
			file_put_contents( $dir . '.htaccess', $htaccess_content );
		}

		if ( ! file_exists( $dir . 'index.php' ) ) {
			file_put_contents( $dir . 'index.php', "<?php\nhttp_response_code( 403 );\nexit;\n" );
		}

		if ( ! file_exists( $dir . 'web.config' ) ) {
			file_put_contents( $dir . 'web.config', '<configuration><system.webServer><authorization><clear /><deny users="*" /></authorization></system.webServer></configuration>' );
		}

		return $dir;
	}

	/**
	 * Zip an entire plugin directory before deletion.
	 *
	 * @param string $plugin_slug Plugin slug/directory name.
	 * @return string|false Path to created zip file or false on failure.
	 */
	public static function zip_plugin_directory( $plugin_slug ) {
		$plugin_slug = sanitize_file_name( basename( $plugin_slug ) );
		$plugin_dir  = WP_PLUGIN_DIR . '/' . $plugin_slug;
		if ( ! is_dir( $plugin_dir ) ) {
			return false;
		}

		// Verify plugin dir is strictly within WP_PLUGIN_DIR.
		$real_plugin_dir = realpath( $plugin_dir );
		$real_wp_plugins = realpath( WP_PLUGIN_DIR );
		if ( ! $real_plugin_dir || ! $real_wp_plugins || 0 !== strpos( $real_plugin_dir, $real_wp_plugins ) ) {
			return false;
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$backup_dir = self::get_plugin_backup_dir();
		$token      = wp_generate_password( 32, false, false );
		$zip_file   = $backup_dir . $plugin_slug . '-' . gmdate( 'Ymd-His' ) . '-' . $token . '.zip';

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $real_plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $files as $file ) {
			if ( ! $file->isDir() ) {
				$file_path     = $file->getRealPath();
				$relative_path = substr( $file_path, strlen( $real_plugin_dir ) + 1 );
				$zip->addFile( $file_path, $relative_path );
			}
		}

		$zip->close();

		return file_exists( $zip_file ) ? $zip_file : false;
	}

	/**
	 * Safely delete an unwanted plugin:
	 * 1. Creates full zip backup
	 * 2. Deactivates plugin
	 * 3. Deletes directory
	 *
	 * @param string $plugin_path Relative plugin file path (e.g. 'better-search-replace/better-search-replace.php').
	 * @return array Array with 'success' (bool), 'message' (string), 'zip_path' (string).
	 */
	public static function safe_delete_plugin( $plugin_path ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$clean_path = ltrim( sanitize_text_field( $plugin_path ), '/\\' );
		$slug       = dirname( $clean_path );
		if ( '.' === $slug || empty( $slug ) ) {
			$slug = basename( $clean_path, '.php' );
		}
		$slug = sanitize_file_name( basename( $slug ) );

		// Validate that the target plugin file is strictly within WP_PLUGIN_DIR.
		$full_plugin_file   = WP_PLUGIN_DIR . '/' . $clean_path;
		$real_plugin_parent = realpath( dirname( $full_plugin_file ) );
		$real_wp_plugins    = realpath( WP_PLUGIN_DIR );

		if ( ! $real_plugin_parent || ! $real_wp_plugins || 0 !== strpos( $real_plugin_parent, $real_wp_plugins ) ) {
			return array(
				'success' => false,
				'message' => __( 'Security error: Invalid plugin file path.', 'site-checkup-pro' ),
			);
		}

		// 1. Create zip backup.
		$zip_path = self::zip_plugin_directory( $slug );
		if ( ! $zip_path ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create a safety zip backup of the plugin directory. Deletion aborted.', 'site-checkup-pro' ),
			);
		}

		// 2. Deactivate plugin first.
		if ( is_plugin_active( $clean_path ) ) {
			deactivate_plugins( $clean_path, true );
		}

		// 3. Delete plugin directory.
		$result = delete_plugins( array( $clean_path ) );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		// Store last backup path in option for undo.
		update_option( 'wpsg_last_deleted_plugin_' . $slug, array(
			'zip_path'    => $zip_path,
			'plugin_path' => $clean_path,
			'deleted_at'  => current_time( 'mysql' ),
		) );

		return array(
			'success'  => true,
			'message'  => sprintf(
				/* translators: %s: slug */
				__( 'Plugin %s was backed up to zip and safely deleted.', 'site-checkup-pro' ),
				$slug
			),
			'zip_path' => $zip_path,
		);
	}

	/**
	 * Restore a previously deleted plugin from its zip backup.
	 *
	 * @param string $slug Plugin slug.
	 * @return array
	 */
	public static function restore_plugin( $slug ) {
		$slug = sanitize_file_name( basename( $slug ) );
		$data = get_option( 'wpsg_last_deleted_plugin_' . $slug );
		if ( empty( $data ) || empty( $data['zip_path'] ) || ! file_exists( $data['zip_path'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No valid zip backup found to restore this plugin.', 'site-checkup-pro' ),
			);
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return array(
				'success' => false,
				'message' => __( 'ZipArchive extension is required for restoration.', 'site-checkup-pro' ),
			);
		}

		$target_dir      = WP_PLUGIN_DIR . '/' . $slug;
		$real_wp_plugins = realpath( WP_PLUGIN_DIR );

		// Security: verify target cannot escape plugins root.
		if ( ! $real_wp_plugins ) {
			return array(
				'success' => false,
				'message' => __( 'Cannot resolve plugins directory.', 'site-checkup-pro' ),
			);
		}

		$zip = new ZipArchive();
		if ( true === $zip->open( $data['zip_path'] ) ) {
			// Zip Slip Protection: Inspect all entry paths before extraction.
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$entry_name = $zip->getNameIndex( $i );
				if ( false !== strpos( $entry_name, '../' ) || false !== strpos( $entry_name, '..\\' ) || 0 === strpos( $entry_name, '/' ) || 0 === strpos( $entry_name, '\\' ) ) {
					$zip->close();
					return array(
						'success' => false,
						'message' => __( 'Security error: Malicious path traversal entries detected in zip archive.', 'site-checkup-pro' ),
					);
				}
			}

			if ( ! is_dir( $target_dir ) ) {
				wp_mkdir_p( $target_dir );
			}

			$zip->extractTo( $target_dir );
			$zip->close();

			delete_option( 'wpsg_last_deleted_plugin_' . $slug );

			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: slug */
					__( 'Plugin %s has been restored from zip backup.', 'site-checkup-pro' ),
					$slug
				),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Failed to extract plugin zip archive.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Scan installed plugins against WordPress.org API to detect closed/abandoned plugins.
	 * Uses 24-hour transient cache to prevent performance slowdowns and rate-limiting.
	 *
	 * @param bool $force_refresh Whether to bypass transient cache.
	 * @return array
	 */
	public static function check_plugin_integrity( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}
		if ( ! function_exists( 'plugins_api' ) ) {
			if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin-install.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}
		}

		$all_plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$closed      = array();
		$total       = 0;

		foreach ( $all_plugins as $plugin_file => $data ) {
			$slug = dirname( $plugin_file );
			if ( '.' === $slug || empty( $slug ) || 'site-checkup-pro' === $slug ) {
				continue;
			}

			$total++;
			$api = plugins_api( 'plugin_information', array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			) );

			if ( is_wp_error( $api ) ) {
				// If WP.org returns Plugin Not Found, it might be premium, custom, or closed!
				if ( false !== strpos( $api->get_error_message(), 'Plugin not found' ) ) {
					// Check if plugin URI contains wordpress.org
					if ( ! empty( $data['PluginURI'] ) && false !== strpos( $data['PluginURI'], 'wordpress.org' ) ) {
						$closed[] = array(
							'slug'    => $slug,
							'name'    => $data['Name'],
							'version' => $data['Version'],
							'reason'  => __( 'Removed or closed on WordPress.org', 'site-checkup-pro' ),
						);
					}
				}
			}
		}

		$status = empty( $closed ) ? 'done' : 'attention';
		$result = array(
			'status'       => $status,
			'total_scanned'=> $total,
			'closed_count' => count( $closed ),
			'closed_list'  => $closed,
			'last_checked' => current_time( 'mysql' ),
			'message'      => empty( $closed )
				? sprintf(
					/* translators: %d: count */
					__( 'All %d installed public plugins are active and verified on WordPress.org.', 'site-checkup-pro' ),
					$total
				)
				: sprintf(
					/* translators: %d: count */
					__( 'Warning: %d plugin(s) appear to be removed or closed on WordPress.org.', 'site-checkup-pro' ),
					count( $closed )
				),
		);

		// Cache for 24 hours.
		set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );

		return $result;
	}
}
