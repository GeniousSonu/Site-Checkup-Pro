<?php
/**
 * REST API Security Auditor
 *
 * Enumerates all registered WordPress REST API routes, analyzes permission callbacks,
 * resolves originating plugins/themes, and flags potential security exposure.
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
 * Class WPSG_Rest_Auditor
 */
class WPSG_Rest_Auditor {

	/**
	 * Run complete REST API security audit.
	 *
	 * @return array
	 */
	public static function audit_routes() {
		// Ensure WP REST Server is initialized.
		$server = rest_get_server();
		if ( empty( $server ) ) {
			if ( class_exists( 'WP_REST_Server' ) ) {
				$server = new WP_REST_Server();
				do_action( 'rest_api_init', $server );
			}
		}

		$routes = ! empty( $server ) && method_exists( $server, 'get_routes' ) ? $server->get_routes() : array();

		// If still empty (e.g. called in CLI or early hook), trigger rest_api_init and retry.
		if ( empty( $routes ) && function_exists( 'rest_get_server' ) ) {
			global $wp_rest_server;
			if ( empty( $wp_rest_server ) && class_exists( 'WP_REST_Server' ) ) {
				$wp_rest_server = new WP_REST_Server();
				do_action( 'rest_api_init', $wp_rest_server );
			}
			if ( ! empty( $wp_rest_server ) ) {
				$routes = $wp_rest_server->get_routes();
			}
		}

		$audited_endpoints = array();
		$public_count      = 0;
		$protected_count   = 0;
		$review_count      = 0;
		$critical_count    = 0;

		foreach ( $routes as $route_pattern => $endpoints ) {
			if ( ! is_array( $endpoints ) ) {
				continue;
			}

			foreach ( $endpoints as $endpoint ) {
				$methods = isset( $endpoint['methods'] ) ? self::normalize_methods( $endpoint['methods'] ) : array( 'GET' );
				$cb      = isset( $endpoint['permission_callback'] ) ? $endpoint['permission_callback'] : null;

				$auth_assessment = self::analyze_permission_callback( $cb );
				$source_info     = self::resolve_source( $route_pattern, $endpoint );
				$risk_level      = self::calculate_risk_level( $methods, $auth_assessment['type'], $route_pattern );

				if ( 'public' === $auth_assessment['type'] ) {
					$public_count++;
				} elseif ( 'protected' === $auth_assessment['type'] ) {
					$protected_count++;
				} else {
					$review_count++;
				}

				if ( 'critical' === $risk_level || 'high' === $risk_level ) {
					$critical_count++;
				}

				$audited_endpoints[] = array(
					'route'       => $route_pattern,
					'methods'     => implode( ', ', $methods ),
					'status'      => $auth_assessment['type'], // 'public', 'protected', 'needs_review'
					'status_text' => $auth_assessment['label'],
					'detail'      => $auth_assessment['detail'],
					'source'      => $source_info['source'],
					'source_type' => $source_info['type'],
					'risk_level'  => $risk_level, // 'critical', 'high', 'medium', 'low'
				);
			}
		}

		return array(
			'summary' => array(
				'total_routes'    => count( $routes ),
				'total_endpoints' => count( $audited_endpoints ),
				'public'          => $public_count,
				'protected'       => $protected_count,
				'needs_review'    => $review_count,
				'high_risk'       => $critical_count,
			),
			'endpoints' => $audited_endpoints,
			'audited_at' => current_time( 'mysql' ),
		);
	}

	/**
	 * Normalize REST methods into an array of string method names.
	 *
	 * @param mixed $methods Methods representation.
	 * @return array
	 */
	public static function normalize_methods( $methods ) {
		if ( is_array( $methods ) ) {
			return array_keys( array_filter( $methods ) );
		}
		if ( is_string( $methods ) ) {
			return array_map( 'trim', explode( ',', $methods ) );
		}
		return array( 'GET' );
	}

	/**
	 * Analyze the permission_callback to classify protection status.
	 *
	 * @param mixed $callback Permission callback.
	 * @return array
	 */
	public static function analyze_permission_callback( $callback ) {
		// 1. No callback or explicitly public
		if ( empty( $callback ) ) {
			return array(
				'type'   => 'public',
				'label'  => __( 'Publicly Accessible', 'site-checkup-pro' ),
				'detail' => __( 'No permission_callback registered (open to unauthenticated access).', 'site-checkup-pro' ),
			);
		}

		if ( is_string( $callback ) && '__return_true' === $callback ) {
			return array(
				'type'   => 'public',
				'label'  => __( 'Publicly Accessible', 'site-checkup-pro' ),
				'detail' => __( 'Explicit __return_true callback registered.', 'site-checkup-pro' ),
			);
		}

		if ( is_string( $callback ) && '__return_false' === $callback ) {
			return array(
				'type'   => 'protected',
				'label'  => __( 'Protected', 'site-checkup-pro' ),
				'detail' => __( 'Explicit __return_false callback registered (always forbidden).', 'site-checkup-pro' ),
			);
		}

		if ( is_string( $callback ) && 'is_user_logged_in' === $callback ) {
			return array(
				'type'   => 'protected',
				'label'  => __( 'Protected', 'site-checkup-pro' ),
				'detail' => __( 'Requires logged-in WordPress user.', 'site-checkup-pro' ),
			);
		}

		// 2. Class Method or Array Callback
		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			$target = $callback[0];
			$method = $callback[1];
			$class_name = is_object( $target ) ? get_class( $target ) : (string) $target;

			// Check known permission check patterns
			if ( false !== stripos( $method, 'permission' ) || false !== stripos( $method, 'check' ) || false !== stripos( $method, 'auth' ) ) {
				// Core WP REST Controllers
				if ( is_subclass_of( $class_name, 'WP_REST_Controller' ) || false !== strpos( $class_name, 'WP_REST_' ) ) {
					// Certain core read controllers permit public reading
					if ( 'get_items_permissions_check' === $method || 'get_item_permissions_check' === $method ) {
						if ( false !== strpos( $class_name, 'Posts' ) || false !== strpos( $class_name, 'Terms' ) || false !== strpos( $class_name, 'Attachments' ) ) {
							return array(
								'type'   => 'public',
								'label'  => __( 'Publicly Accessible', 'site-checkup-pro' ),
								'detail' => sprintf( '%s::%s (Core public read check)', $class_name, $method ),
							);
						}
					}
					return array(
						'type'   => 'protected',
						'label'  => __( 'Protected', 'site-checkup-pro' ),
						'detail' => sprintf( '%s::%s (WP_REST_Controller capability guard)', $class_name, $method ),
					);
				}

				return array(
					'type'   => 'protected',
					'label'  => __( 'Protected', 'site-checkup-pro' ),
					'detail' => sprintf( '%s::%s', $class_name, $method ),
				);
			}

			// Use reflection if available
			try {
				if ( class_exists( $class_name ) && method_exists( $class_name, $method ) ) {
					$ref = new ReflectionMethod( $class_name, $method );
					$code = self::get_reflection_code( $ref );
					if ( false !== stripos( $code, 'current_user_can' ) || false !== stripos( $code, 'user_can' ) || false !== stripos( $code, 'is_user_logged_in' ) ) {
						return array(
							'type'   => 'protected',
							'label'  => __( 'Protected', 'site-checkup-pro' ),
							'detail' => sprintf( '%s::%s (Verified capability check)', $class_name, $method ),
						);
					}
				}
			} catch ( Exception $e ) {
				// Ignore reflection errors.
			}

			return array(
				'type'   => 'needs_review',
				'label'  => __( 'Needs Review', 'site-checkup-pro' ),
				'detail' => sprintf( '%s::%s (Custom verification callback)', $class_name, $method ),
			);
		}

		// 3. Closure
		if ( $callback instanceof Closure ) {
			try {
				$ref  = new ReflectionFunction( $callback );
				$code = self::get_reflection_code( $ref );
				if ( false !== stripos( $code, 'current_user_can' ) || false !== stripos( $code, 'user_can' ) || false !== stripos( $code, 'is_user_logged_in' ) ) {
					return array(
						'type'   => 'protected',
						'label'  => __( 'Protected', 'site-checkup-pro' ),
						'detail' => __( 'Closure with capability/auth verification check', 'site-checkup-pro' ),
					);
				}
				if ( false !== stripos( $code, 'return true' ) && false === stripos( $code, 'current_user_can' ) ) {
					return array(
						'type'   => 'public',
						'label'  => __( 'Publicly Accessible', 'site-checkup-pro' ),
						'detail' => __( 'Closure unconditionally returning true', 'site-checkup-pro' ),
					);
				}
			} catch ( Exception $e ) {
				// Ignore reflection errors.
			}

			return array(
				'type'   => 'needs_review',
				'label'  => __( 'Needs Review', 'site-checkup-pro' ),
				'detail' => __( 'Anonymous closure callback (inspect code)', 'site-checkup-pro' ),
			);
		}

		return array(
			'type'   => 'needs_review',
			'label'  => __( 'Needs Review', 'site-checkup-pro' ),
			'detail' => is_string( $callback ) ? $callback : __( 'Dynamic callback', 'site-checkup-pro' ),
		);
	}

	/**
	 * Resolve registering source (Plugin, Theme, or WordPress Core).
	 *
	 * @param string $route Route pattern.
	 * @param array  $endpoint Endpoint definition.
	 * @return array
	 */
	public static function resolve_source( $route, $endpoint ) {
		// Core prefixes
		if ( 0 === strpos( $route, '/wp/v2' ) || 0 === strpos( $route, '/wp/v1' ) || 0 === strpos( $route, '/oembed' ) || 0 === strpos( $route, '/wp-site-health' ) ) {
			return array( 'source' => 'WordPress Core', 'type' => 'core' );
		}

		// Site Checkup Pro
		if ( 0 === strpos( $route, '/site-checkup-pro' ) ) {
			return array( 'source' => 'Site Checkup Pro', 'type' => 'plugin' );
		}

		// Inspect callback file path via reflection
		$cb = isset( $endpoint['callback'] ) ? $endpoint['callback'] : ( isset( $endpoint['permission_callback'] ) ? $endpoint['permission_callback'] : null );
		if ( ! empty( $cb ) ) {
			try {
				$file = '';
				if ( is_array( $cb ) && isset( $cb[0] ) ) {
					$ref  = new ReflectionClass( $cb[0] );
					$file = $ref->getFileName();
				} elseif ( $cb instanceof Closure || ( is_string( $cb ) && function_exists( $cb ) ) ) {
					$ref  = new ReflectionFunction( $cb );
					$file = $ref->getFileName();
				}

				if ( $file ) {
					// Check plugins directory
					if ( defined( 'WP_PLUGIN_DIR' ) && false !== strpos( $file, WP_PLUGIN_DIR ) ) {
						$rel = trim( str_replace( WP_PLUGIN_DIR, '', $file ), '/\\' );
						$parts = explode( DIRECTORY_SEPARATOR, $rel );
						$folder = ! empty( $parts[0] ) ? $parts[0] : '';
						return array( 'source' => ucwords( str_replace( array( '-', '_' ), ' ', $folder ) ), 'type' => 'plugin' );
					}
					// Check themes directory
					if ( false !== strpos( $file, '/themes/' ) ) {
						$parts = explode( '/themes/', $file );
						if ( isset( $parts[1] ) ) {
							$theme_slug = explode( '/', $parts[1] )[0];
							return array( 'source' => ucwords( str_replace( array( '-', '_' ), ' ', $theme_slug ) ) . ' (Theme)', 'type' => 'theme' );
						}
					}
				}
			} catch ( Exception $e ) {
				// Fallback to namespace extraction.
			}
		}

		// Fallback: extract namespace from route pattern (e.g. /wc/v3/... -> wc)
		$clean = trim( $route, '/' );
		$parts = explode( '/', $clean );
		$ns    = ! empty( $parts[0] ) ? $parts[0] : 'Unknown';

		return array(
			'source' => ucwords( str_replace( array( '-', '_' ), ' ', $ns ) ),
			'type'   => 'custom',
		);
	}

	/**
	 * Calculate risk level based on HTTP methods and protection status.
	 *
	 * @param array  $methods HTTP methods.
	 * @param string $status Protection status ('public', 'protected', 'needs_review').
	 * @param string $route Route pattern.
	 * @return string ('critical', 'high', 'medium', 'low')
	 */
	public static function calculate_risk_level( $methods, $status, $route ) {
		$has_write = false;
		foreach ( $methods as $m ) {
			if ( in_array( strtoupper( $m ), array( 'POST', 'PUT', 'DELETE', 'PATCH' ), true ) ) {
				$has_write = true;
				break;
			}
		}

		if ( 'public' === $status ) {
			if ( $has_write ) {
				return 'critical'; // Unauthenticated write endpoint!
			}
			// Sensitive routes
			if ( false !== strpos( $route, '/users' ) || false !== strpos( $route, '/settings' ) || false !== strpos( $route, '/backup' ) ) {
				return 'high';
			}
			return 'low'; // Normal public read (posts, pages, oembed)
		}

		if ( 'needs_review' === $status ) {
			return $has_write ? 'high' : 'medium';
		}

		return 'low';
	}

	/**
	 * Helper to get code lines from ReflectionFunction/ReflectionMethod.
	 *
	 * @param ReflectionFunctionAbstract $ref Reflection object.
	 * @return string
	 */
	private static function get_reflection_code( $ref ) {
		$file = $ref->getFileName();
		if ( ! $file || ! file_exists( $file ) ) {
			return '';
		}
		$start = $ref->getStartLine();
		$end   = $ref->getEndLine();
		if ( ! $start || ! $end || ( $end - $start > 100 ) ) {
			return '';
		}

		$lines = file( $file );
		if ( false === $lines ) {
			return '';
		}
		$slice = array_slice( $lines, $start - 1, $end - $start + 1 );
		return implode( '', $slice );
	}
}
