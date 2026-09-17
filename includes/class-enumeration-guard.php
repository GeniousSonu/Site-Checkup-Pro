<?php
/**
 * User Enumeration Protection & Author Archive Guard
 *
 * Blocks REST /wp/v2/users and ?author= numeric enumeration for unauthenticated visitors,
 * while preserving list_users capability for authenticated administrators.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Enumeration_Guard
 */
class WPSG_Enumeration_Guard {

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Enumeration_Guard|null
	 */
	private static $instance = null;

	/**
	 * Option key for toggling protection.
	 */
	const OPTION_KEY = 'wpsg_block_user_enumeration';

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Enumeration_Guard
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
		if ( ! self::is_enabled() ) {
			return;
		}

		// 1. Filter REST endpoints for users.
		add_filter( 'rest_endpoints', array( $this, 'filter_rest_endpoints' ) );

		// 2. Intercept ?author= numeric query string on template_redirect.
		add_action( 'template_redirect', array( $this, 'block_author_parameter_enumeration' ) );

		// 3. Strip rel="author" links from head.
		add_action( 'wp_head', array( $this, 'filter_author_rel_head' ), 1 );
	}

	/**
	 * Check if user enumeration protection is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::OPTION_KEY, false );
	}

	/**
	 * Pre-flight compatibility check to verify if the site relies on author archives.
	 *
	 * @return array
	 */
	public static function check_compatibility() {
		$theme_has_author_template = locate_template( array( 'author.php' ) );
		$users_with_posts          = count_users();
		$author_count              = isset( $users_with_posts['avail_roles']['author'] ) ? $users_with_posts['avail_roles']['author'] : 0;

		$warnings = array();
		if ( ! empty( $theme_has_author_template ) ) {
			$warnings[] = __( 'Active theme contains an author.php template (designed for public author profiles).', 'site-checkup-pro' );
		}
		if ( $author_count > 1 ) {
			$warnings[] = sprintf(
				/* translators: %d: author count */
				__( 'Multiple authors detected (%d registered authors). Multi-author blogs usually expect public author archive pages.', 'site-checkup-pro' ),
				$author_count
			);
		}

		return array(
			'has_conflicts' => ! empty( $warnings ),
			'warnings'      => $warnings,
			'message'       => ! empty( $warnings )
				? implode( ' ', $warnings )
				: __( 'Single-author / business site detected. Safe to block author enumeration.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Filter REST endpoints to block /wp/v2/users for unauthorized visitors.
	 *
	 * @param array $endpoints Registered REST endpoints.
	 * @return array
	 */
	public function filter_rest_endpoints( $endpoints ) {
		// Allow logged-in users with list_users capability to access users endpoints.
		if ( is_user_logged_in() && current_user_can( 'list_users' ) ) {
			return $endpoints;
		}

		if ( isset( $endpoints['/wp/v2/users'] ) ) {
			unset( $endpoints['/wp/v2/users'] );
		}
		if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
			unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}

		return $endpoints;
	}

	/**
	 * Intercept ?author= numeric requests and redirect to home.
	 */
	public function block_author_parameter_enumeration() {
		if ( is_admin() ) {
			return;
		}

		// Allow logged-in users with list_users capability.
		if ( is_user_logged_in() && current_user_can( 'list_users' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['author'] ) && is_numeric( $_GET['author'] ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}

		// Block direct author archive queries if they leaked.
		if ( is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * Remove rel="author" tags from header.
	 */
	public function filter_author_rel_head() {
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
	}
}
