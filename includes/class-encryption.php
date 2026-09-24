<?php
/**
 * Cryptographic Encryption Service
 *
 * Implements authenticated encryption for sensitive integration credentials
 * (e.g. Hosting Panel API tokens) using sodium_crypto_secretbox with HKDF key derivation,
 * falling back to OpenSSL AES-256-GCM / AES-256-CBC with HMAC-SHA256 authentication.
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
 * Class WPSG_Encryption
 */
class WPSG_Encryption {

	/**
	 * HKDF Info context string for hosting panel credential encryption.
	 */
	const HKDF_INFO_HOSTING_PANEL = 'wpsg_hosting_panel_encryption_v1';

	/**
	 * HKDF Info context string for WPScan API token encryption.
	 */
	const HKDF_INFO_WPSCAN = 'wpsg_wpscan_encryption_v1';

	/**
	 * HKDF Info context string for NVD API key encryption.
	 */
	const HKDF_INFO_NVD = 'wpsg_nvd_encryption_v1';

	/**
	 * HKDF Info context string for Patchstack API key encryption.
	 */
	const HKDF_INFO_PATCHSTACK = 'wpsg_patchstack_encryption_v1';

	/**
	 * Derive a dedicated 256-bit encryption key using HKDF from a WordPress salt.
	 *
	 * Isolates keys across functional domains to prevent key-reuse vulnerabilities.
	 *
	 * @param string $info Context-specific info string.
	 * @return string 32-byte binary key.
	 */
	public static function derive_key( $info = self::HKDF_INFO_HOSTING_PANEL ) {
		$raw_salt = defined( 'AUTH_SALT' ) && AUTH_SALT ? AUTH_SALT : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpsg_default_salt_key' );

		if ( function_exists( 'hash_hkdf' ) ) {
			return hash_hkdf( 'sha256', $raw_salt, 32, $info, 'wpsg_salt_context' );
		}

		// Fallback for environments lacking hash_hkdf
		return substr( hash_hmac( 'sha256', $info, $raw_salt, true ), 0, 32 );
	}

	/**
	 * Encrypt plaintext string using authenticated encryption.
	 *
	 * Returns base64-encoded payload prefixed with cipher indicator.
	 *
	 * @param string $plaintext Data to encrypt.
	 * @param string $info      HKDF context string.
	 * @return string Base64-encoded ciphertext or empty string on failure.
	 */
	public static function encrypt( $plaintext, $info = self::HKDF_INFO_HOSTING_PANEL ) {
		if ( ! is_string( $plaintext ) || '' === $plaintext ) {
			return '';
		}

		$key = self::derive_key( $info );

		// 1. Sodium secretbox (Primary)
		if ( function_exists( 'sodium_crypto_secretbox' ) && function_exists( 'random_bytes' ) ) {
			try {
				$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
				$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
				return 'sod:' . base64_encode( $nonce . $ciphertext );
			} catch ( Exception $e ) {
				// Fall through to OpenSSL
			}
		}

		// 2. OpenSSL AES-256-GCM (Secondary)
		if ( function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
			$iv         = random_bytes( 12 );
			$tag        = '';
			$ciphertext = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
			if ( false !== $ciphertext ) {
				return 'gcm:' . base64_encode( $iv . $tag . $ciphertext );
			}
		}

		// 3. OpenSSL AES-256-CBC with HMAC-SHA256 (Fallback)
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv         = random_bytes( 16 );
			$ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
			if ( false !== $ciphertext ) {
				$hmac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );
				return 'cbc:' . base64_encode( $iv . $hmac . $ciphertext );
			}
		}

		return '';
	}

	/**
	 * Decrypt base64-encoded ciphertext payload.
	 *
	 * @param string $payload Encrypted string with cipher prefix.
	 * @param string $info    HKDF context string.
	 * @return string Decrypted plaintext or empty string on failure.
	 */
	public static function decrypt( $payload, $info = self::HKDF_INFO_HOSTING_PANEL ) {
		if ( ! is_string( $payload ) || '' === $payload ) {
			return '';
		}

		$parts = explode( ':', $payload, 2 );
		if ( 2 !== count( $parts ) ) {
			return '';
		}

		$scheme = $parts[0];
		$raw    = base64_decode( $parts[1], true );
		if ( false === $raw ) {
			return '';
		}

		$key = self::derive_key( $info );

		if ( 'sod' === $scheme && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$nonce_len = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			if ( strlen( $raw ) < $nonce_len ) {
				return '';
			}
			$nonce      = substr( $raw, 0, $nonce_len );
			$ciphertext = substr( $raw, $nonce_len );
			$decrypted  = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
			return false !== $decrypted ? $decrypted : '';
		}

		if ( 'gcm' === $scheme && function_exists( 'openssl_decrypt' ) ) {
			if ( strlen( $raw ) < 28 ) { // 12 bytes IV + 16 bytes tag
				return '';
			}
			$iv         = substr( $raw, 0, 12 );
			$tag        = substr( $raw, 12, 16 );
			$ciphertext = substr( $raw, 28 );
			$decrypted  = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			return false !== $decrypted ? $decrypted : '';
		}

		if ( 'cbc' === $scheme && function_exists( 'openssl_decrypt' ) ) {
			if ( strlen( $raw ) < 48 ) { // 16 bytes IV + 32 bytes HMAC
				return '';
			}
			$iv            = substr( $raw, 0, 16 );
			$expected_hmac = substr( $raw, 16, 32 );
			$ciphertext    = substr( $raw, 48 );
			$computed_hmac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );

			if ( ! hash_equals( $expected_hmac, $computed_hmac ) ) {
				return ''; // Tampered payload
			}

			$decrypted = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
			return false !== $decrypted ? $decrypted : '';
		}

		return '';
	}
}
