<?php
/**
 * Migration Readiness Check
 *
 * Scans the database using safe, bounded queries for hardcoded absolute URLs embedded
 * specifically inside PHP serialized data. Surfaces affected tables/rows and provides
 * actionable WP-CLI guidance without modifying any data.
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
 * Class WPSG_Migration_Readiness
 */
class WPSG_Migration_Readiness {

	/**
	 * Run bounded scan for hardcoded absolute URLs inside serialized database strings.
	 *
	 * @return array
	 */
	public static function scan() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array(
				'summary'  => array( 'total_findings' => 0, 'risk_level' => 'low' ),
				'findings' => array(),
				'guidance' => self::get_guidance(),
			);
		}

		$options_table  = ! empty( $wpdb->options ) ? $wpdb->options : $wpdb->prefix . 'options';
		$postmeta_table = ! empty( $wpdb->postmeta ) ? $wpdb->postmeta : $wpdb->prefix . 'postmeta';

		// Get current domain/host to inspect
		$site_host = '';
		if ( function_exists( 'home_url' ) ) {
			$parsed = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( $parsed ) {
				$site_host = strtolower( $parsed );
			}
		}
		if ( empty( $site_host ) && isset( $_SERVER['HTTP_HOST'] ) ) {
			$site_host = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
		}

		$findings          = array();
		$options_count     = 0;
		$postmeta_count    = 0;

		// 1. Scan wp_options (bounded limit 250)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$option_rows = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$options_table} 
			WHERE (option_value LIKE '%http://%' OR option_value LIKE '%https://%') 
			LIMIT 250",
			ARRAY_A
		);

		if ( ! empty( $option_rows ) ) {
			foreach ( $option_rows as $row ) {
				$val = $row['option_value'];
				if ( self::is_serialized_string( $val ) ) {
					// Check if contains domain or URL protocol
					if ( empty( $site_host ) || false !== stripos( $val, $site_host ) ) {
						$options_count++;
						$findings[] = array(
							'table'      => 'options',
							'identifier' => sanitize_key( $row['option_name'] ),
							'sample'     => self::extract_url_snippet( $val ),
							'issue'      => __( 'Absolute URL inside serialized option value', 'site-checkup-pro' ),
						);
					}
				}
			}
		}

		// 2. Scan wp_postmeta (bounded limit 250)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$postmeta_rows = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value FROM {$postmeta_table} 
			WHERE (meta_value LIKE '%http://%' OR meta_value LIKE '%https://%') 
			LIMIT 250",
			ARRAY_A
		);

		if ( ! empty( $postmeta_rows ) ) {
			foreach ( $postmeta_rows as $row ) {
				$val = $row['meta_value'];
				if ( self::is_serialized_string( $val ) ) {
					if ( empty( $site_host ) || false !== stripos( $val, $site_host ) ) {
						$postmeta_count++;
						$findings[] = array(
							'table'      => 'postmeta',
							'identifier' => 'Post #' . absint( $row['post_id'] ) . ' (' . sanitize_key( $row['meta_key'] ) . ')',
							'sample'     => self::extract_url_snippet( $val ),
							'issue'      => __( 'Absolute URL inside serialized postmeta', 'site-checkup-pro' ),
						);
					}
				}
			}
		}

		$total_findings = $options_count + $postmeta_count;

		return array(
			'summary' => array(
				'total_findings'  => $total_findings,
				'options_count'   => $options_count,
				'postmeta_count'  => $postmeta_count,
				'scanned_host'    => $site_host ? $site_host : 'Generic absolute URLs',
				'risk_level'      => $total_findings > 0 ? 'attention' : 'done',
				'is_ready'        => 0 === $total_findings,
			),
			'findings' => array_slice( $findings, 0, 50 ), // Cap return list at 50 to prevent huge payloads
			'guidance' => self::get_guidance( $site_host ),
			'scanned_at' => current_time( 'mysql' ),
		);
	}

	/**
	 * Check if a string is serialized PHP data.
	 *
	 * @param string $data Data string.
	 * @return bool
	 */
	public static function is_serialized_string( $data ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 ) {
			return false;
		}
		if ( ':' !== $data[1] ) {
			return false;
		}
		$lastc = substr( $data, -1 );
		if ( ';' !== $lastc && '}' !== $lastc ) {
			return false;
		}
		$token = $data[0];
		switch ( $token ) {
			case 's':
				if ( '"' !== substr( $data, -2, 1 ) ) {
					return false;
				}
				// fall through
			case 'a':
			case 'O':
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
			case 'b':
			case 'i':
			case 'd':
				return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$/", $data );
		}
		return false;
	}

	/**
	 * Extract a short snippet showing the URL in context.
	 *
	 * @param string $data Serialized string.
	 * @return string
	 */
	private static function extract_url_snippet( $data ) {
		if ( preg_match( '/s:\d+:"(https?:\/\/[^"]+)"/i', $data, $matches ) ) {
			return sanitize_text_field( substr( $matches[0], 0, 80 ) );
		}
		if ( preg_match( '/https?:\/\/[^\s\'"]+/i', $data, $matches ) ) {
			return sanitize_text_field( substr( $matches[0], 0, 80 ) );
		}
		return 'Serialized data containing absolute URL';
	}

	/**
	 * Actionable WP-CLI and migration guidance.
	 *
	 * @param string $site_host Current host.
	 * @return array
	 */
	public static function get_guidance( $site_host = '' ) {
		$old_url = $site_host ? 'https://' . $site_host : 'https://oldsite.com';
		return array(
			'title'       => __( 'Why Serialized URLs Break Migrations', 'site-checkup-pro' ),
			'explanation' => __( 'PHP serialized strings encode exact character counts (e.g. s:21:"https://oldsite.com"). If you perform a standard SQL search-and-replace, the new domain length will mismatch the declared byte count, causing PHP unserialize() to fail silently. This corrupts widgets, page builders, and theme options.', 'site-checkup-pro' ),
			'cli_command' => sprintf( 'wp search-replace "%s" "https://newsite.com" --all-tables --precise', esc_attr( $old_url ) ),
			'recommended_tools' => array(
				'WP-CLI search-replace (Official CLI method with serialization recalculation)',
				'Better Search Replace (WordPress plugin for browser-based safe replacement)',
				'WP Migrate DB Pro / Migrate Guru (Dedicated migration engines)',
			),
		);
	}
}
