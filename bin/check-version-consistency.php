<?php
/**
 * Version Consistency Guard for Site Checkup Pro
 *
 * Verifies that the version string is identical across:
 * 1. Plugin Header in site-checkup-pro.php ('Version: X.Y.Z')
 * 2. PHP Constant in site-checkup-pro.php ('define( "WPSG_VERSION", "X.Y.Z" )')
 * 3. WordPress.org readme.txt ('Stable tag: X.Y.Z')
 * 4. Git Tag (if running in CI or passed via --tag=vX.Y.Z)
 *
 * Usage:
 *   php bin/check-version-consistency.php
 *   php bin/check-version-consistency.php --tag=v1.0.0
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 */

$root_dir = dirname( __DIR__ );
$main_file = $root_dir . '/site-checkup-pro.php';
$readme_file = $root_dir . '/readme.txt';

if ( ! file_exists( $main_file ) ) {
	fwrite( STDERR, "[ERROR] Main plugin file not found: {$main_file}\n" );
	exit( 1 );
}
if ( ! file_exists( $readme_file ) ) {
	fwrite( STDERR, "[ERROR] readme.txt file not found: {$readme_file}\n" );
	exit( 1 );
}

$main_content   = file_get_contents( $main_file );
$readme_content = file_get_contents( $readme_file );

// 1. Extract Plugin Header Version
$header_version = null;
if ( preg_match( '/^[ \t\/*#]*Version:\s*([0-9]+\.[0-9]+(?:\.[0-9]+)?(?:-[a-zA-Z0-9.]+)?)/mi', $main_content, $matches ) ) {
	$header_version = trim( $matches[1] );
}

// 2. Extract WPSG_VERSION Constant
$constant_version = null;
if ( preg_match( "/define\(\s*['\"]WPSG_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\);/", $main_content, $matches ) ) {
	$constant_version = trim( $matches[1] );
}

// 3. Extract readme.txt Stable Tag
$readme_version = null;
if ( preg_match( '/^[ \t]*Stable tag:\s*([0-9]+\.[0-9]+(?:\.[0-9]+)?(?:-[a-zA-Z0-9.]+)?)/mi', $readme_content, $matches ) ) {
	$readme_version = trim( $matches[1] );
}

// 4. Extract update-info.json Version
$json_file    = $root_dir . '/update-info.json';
$json_version = null;
if ( file_exists( $json_file ) ) {
	$json_data = json_decode( file_get_contents( $json_file ), true );
	if ( is_array( $json_data ) && ! empty( $json_data['version'] ) ) {
		$json_version = trim( $json_data['version'] );
	}
}

// 5. Extract Git Tag if provided via CLI flag or GITHUB_REF_NAME
$git_tag_version = null;
$expected_tag    = null;

foreach ( $argv as $arg ) {
	if ( 0 === strpos( $arg, '--tag=' ) ) {
		$expected_tag = substr( $arg, 6 );
	}
}

if ( empty( $expected_tag ) && ! empty( getenv( 'GITHUB_REF_NAME' ) ) ) {
	$env_ref = getenv( 'GITHUB_REF_NAME' );
	if ( 0 === strpos( $env_ref, 'v' ) ) {
		$expected_tag = $env_ref;
	}
}

if ( ! empty( $expected_tag ) ) {
	$git_tag_version = ltrim( trim( $expected_tag ), 'v' );
}

echo "=======================================================\n";
echo " Site Checkup Pro — Version Consistency Guard\n";
echo "=======================================================\n\n";

$versions = array(
	'site-checkup-pro.php Header'   => $header_version,
	'site-checkup-pro.php Constant' => $constant_version,
	'readme.txt Stable tag'         => $readme_version,
	'update-info.json Version'      => $json_version,
);

if ( null !== $git_tag_version ) {
	$versions['Git Release Tag'] = $git_tag_version;
}

$has_error = false;
$baseline  = $header_version;

foreach ( $versions as $source => $ver ) {
	if ( empty( $ver ) ) {
		echo sprintf( "  %-32s : [EMPTY / NOT FOUND]\n", $source );
		$has_error = true;
	} elseif ( $ver !== $baseline ) {
		echo sprintf( "  %-32s : %s  <-- MISMATCH (expected %s)\n", $source, $ver, $baseline );
		$has_error = true;
	} else {
		echo sprintf( "  %-32s : %s  [OK]\n", $source, $ver );
	}
}

echo "\n";

if ( $has_error ) {
	fwrite( STDERR, "=======================================================\n" );
	fwrite( STDERR, " [FAIL] Version consistency check failed!\n" );
	fwrite( STDERR, " Every release must have strictly identical version numbers.\n" );
	fwrite( STDERR, " Please synchronize all files before committing or deploying.\n" );
	fwrite( STDERR, "=======================================================\n" );
	exit( 1 );
}

echo "=======================================================\n";
echo " [PASS] All version declarations match: {$baseline}\n";
echo "=======================================================\n";
exit( 0 );
