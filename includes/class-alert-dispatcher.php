<?php
/**
* Event-Driven Alert Dispatcher & Webhook Router
 *
 * Dispatches notifications for security events via wp_mail and SSRF-hardened webhooks.
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
 * Class WPSG_Alert_Dispatcher
 */
class WPSG_Alert_Dispatcher {

	/**
	 * Dispatch a security alert.
	 *
	 * @param string $event_type Event key (e.g. 'rogue_admin', 'login_spike', 'uploads_php_detected').
	 * @param string $message    Human-readable description.
	 * @param array  $context    Contextual metadata.
	 * @return bool True if dispatched.
	 */
	public static function dispatch( $event_type, $message, array $context = array() ) {
		// Exclude internal self-verification probes from triggering alert notifications.
		if ( class_exists( 'WPSG_HTTP_Verifier' ) && WPSG_HTTP_Verifier::is_self_verification_request() ) {
			return false;
		}

		// Throttle identical alerts to at most 1 every 15 minutes to prevent alert storms.
		$throttle_key = 'wpsg_alert_throt_' . sanitize_key( $event_type );
		if ( get_transient( $throttle_key ) ) {
			return false;
		}
		set_transient( $throttle_key, true, 900 );

		$settings = get_option( 'wpsg_alert_settings', array() );
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		$email_recipient = ! empty( $settings['alert_email'] ) ? sanitize_email( $settings['alert_email'] ) : get_option( 'admin_email' );

		// 1. Email notification via wp_mail
		$subject = sprintf( '[%1$s Security Alert] %2$s', $site_name, sanitize_text_field( $event_type ) );
		$body    = sprintf(
			"Site Checkup Pro Security Alert\n" .
			"Website: %s (%s)\n" .
			"Time: %s\n" .
			"Event: %s\n\n" .
			"Message:\n%s\n\n" .
			"Context:\n%s\n\n" .
			"Please log in to your dashboard to inspect details.\n",
			$site_name,
			$site_url,
			current_time( 'mysql' ),
			strtoupper( $event_type ),
			$message,
			wp_json_encode( $context, JSON_PRETTY_PRINT )
		);

		if ( is_email( $email_recipient ) ) {
			wp_mail( $email_recipient, $subject, $body );
		}

		// 2. Webhook notification (SSRF-protected & WP.org Guideline 7 consent-gated)
		$plugin_settings = get_option( 'wpsg_settings', array() );
		$webhook_optin   = ! empty( $plugin_settings['webhook_optin'] ) || ! empty( $settings['webhook_optin'] );
		$raw_webhook_url = ! empty( $plugin_settings['webhook_url'] ) ? $plugin_settings['webhook_url'] : ( ! empty( $settings['webhook_url'] ) ? $settings['webhook_url'] : '' );

		if ( $webhook_optin && ! empty( $raw_webhook_url ) && class_exists( 'WPSG_SSRF_Guard' ) ) {
			$webhook_url = esc_url_raw( $raw_webhook_url );
			$payload     = array(
				'event'      => sanitize_key( $event_type ),
				'site_name'  => $site_name,
				'site_url'   => $site_url,
				'timestamp'  => current_time( 'mysql' ),
				'message'    => sanitize_text_field( $message ),
				'context'    => $context,
			);

			WPSG_SSRF_Guard::safe_remote_post(
				$webhook_url,
				array(
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( $payload ),
					'timeout' => 5,
				)
			);
		}

		return true;
	}
}
