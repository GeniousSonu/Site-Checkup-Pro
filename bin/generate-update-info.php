<?php
/**
 * Update Metadata JSON Generator for Site Checkup Pro
 *
 * Automatically generates `update-info.json` from `readme.txt` and `site-checkup-pro.php`.
 * Provides the single source of truth for self-hosted distribution updates and the
 * in-dashboard Thickbox version details modal.
 *
 * Usage:
 *   php bin/generate-update-info.php
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 */

$root_dir    = dirname( __DIR__ );
$readme_file = $root_dir . '/readme.txt';
$main_file   = $root_dir . '/site-checkup-pro.php';
$output_file = $root_dir . '/update-info.json';

if ( ! file_exists( $readme_file ) ) {
	fwrite( STDERR, "[ERROR] readme.txt not found at {$readme_file}\n" );
	exit( 1 );
}
if ( ! file_exists( $main_file ) ) {
	fwrite( STDERR, "[ERROR] site-checkup-pro.php not found at {$main_file}\n" );
	exit( 1 );
}

$readme_content = file_get_contents( $readme_file );
$main_content   = file_get_contents( $main_file );

// 1. Extract Version
$version = '1.0.0';
if ( preg_match( '/^[ \t]*Stable tag:\s*([0-9]+\.[0-9]+(?:\.[0-9]+)?(?:-[a-zA-Z0-9.]+)?)/mi', $readme_content, $m ) ) {
	$version = trim( $m[1] );
}

// 2. Extract Requires & Tested
$requires = '5.8';
if ( preg_match( '/^[ \t]*Requires at least:\s*([0-9.]+)/mi', $readme_content, $m ) ) {
	$requires = trim( $m[1] );
}

$tested = '6.7';
if ( preg_match( '/^[ \t]*Tested up to:\s*([0-9.]+)/mi', $readme_content, $m ) ) {
	$tested = trim( $m[1] );
}

$requires_php = '7.4';
if ( preg_match( '/^[ \t]*Requires PHP:\s*([0-9.]+)/mi', $readme_content, $m ) ) {
	$requires_php = trim( $m[1] );
}

// 3. Extract Short Description
$short_desc = 'Complete security audit, site hardening checklist, and vulnerability scanner for WordPress. One-click safe hardening, login protection, and client reports.';
if ( preg_match( '/=== Description ===\s*\n+(.*?)(?:\n\n|\n==)/s', $readme_content, $m ) ) {
	$clean_desc = trim( strip_tags( $m[1] ) );
	if ( ! empty( $clean_desc ) ) {
		$short_desc = $clean_desc;
	}
}

// 4. Extract Changelog & Convert to Clean HTML
$changelog_html = '';
if ( preg_match( '/== Changelog ==\s*\n+(.*?)(?=\n==|$)/s', $readme_content, $m ) ) {
	$raw_changelog = trim( $m[1] );
	$sections = preg_split( '/(?=^=\s*[0-9.]+\s*=)/m', $raw_changelog );

	$formatted_entries = array();
	foreach ( $sections as $sec ) {
		$sec = trim( $sec );
		if ( empty( $sec ) ) {
			continue;
		}

		if ( preg_match( '/^=\s*([0-9.]+)\s*=\s*\n*(.*)$/s', $sec, $entry_match ) ) {
			$entry_version = trim( $entry_match[1] );
			$entry_body    = trim( $entry_match[2] );

			$lines = explode( "\n", $entry_body );
			$items = array();

			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}
				// Remove leading bullet marker
				$line = preg_replace( '/^[\*\-]\s*/', '', $line );
				$items[] = '<li>' . htmlspecialchars( $line, ENT_QUOTES, 'UTF-8' ) . '</li>';
			}

			$entry_html = '<h4>' . htmlspecialchars( $entry_version, ENT_QUOTES, 'UTF-8' ) . "</h4>\n";
			if ( ! empty( $items ) ) {
				$entry_html .= "<ul>\n\t" . implode( "\n\t", $items ) . "\n</ul>";
			}
			$formatted_entries[] = $entry_html;
		}
	}

	$changelog_html = implode( "\n\n", $formatted_entries );
}

if ( empty( $changelog_html ) ) {
	$changelog_html = "<h4>{$version}</h4>\n<ul>\n\t<li>Regular maintenance and security updates.</li>\n</ul>";
}

// 5. Build Metadata Structure
$metadata = array(
	'name'           => 'Site Checkup Pro',
	'slug'           => 'site-checkup-pro',
	'version'        => $version,
	'download_url'   => "https://github.com/GeniousSonu/Site-Checkup-Pro/releases/download/v{$version}/site-checkup-pro-selfhosted.zip",
	'homepage'       => 'https://www.genioussonu.me/plugin/site-checkup-pro/',
	'author'         => '<a href="https://www.genioussonu.me/">SK Sahinur Islam</a>',
	'author_profile' => 'https://profiles.wordpress.org/genioussonu/',
	'requires'       => $requires,
	'tested'         => $tested,
	'requires_php'   => $requires_php,
	'last_updated'   => gmdate( 'Y-m-d H:i:s' ),
	'upgrade_notice' => 'Recommended update for security audit improvements and bug fixes.',
	'sections'       => array(
		'description'  => '<p>' . htmlspecialchars( $short_desc, ENT_QUOTES, 'UTF-8' ) . '</p>',
		'installation' => '<p>Upload the ZIP file through the WordPress admin (Plugins &rarr; Add New &rarr; Upload Plugin), or extract to <code>wp-content/plugins/site-checkup-pro</code>.</p>',
		'changelog'    => $changelog_html,
	),
	'icons'          => array(
		'svg' => 'https://raw.githubusercontent.com/GeniousSonu/Site-Checkup-Pro/main/media/icon.svg',
		'1x'  => 'https://raw.githubusercontent.com/GeniousSonu/Site-Checkup-Pro/main/media/icon.svg',
	),
	'banners'        => array(
		'low'  => 'https://raw.githubusercontent.com/GeniousSonu/Site-Checkup-Pro/main/media/site-checkup-pro-logo.svg',
		'high' => 'https://raw.githubusercontent.com/GeniousSonu/Site-Checkup-Pro/main/media/site-checkup-pro-logo.svg',
	),
);

$json = json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";

if ( false === file_put_contents( $output_file, $json ) ) {
	fwrite( STDERR, "[ERROR] Failed to write {$output_file}\n" );
	exit( 1 );
}

echo "✓ update-info.json generated successfully for v{$version}!\n";
