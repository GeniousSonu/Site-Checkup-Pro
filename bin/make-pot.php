<?php
/**
 * Translation Template (POT) Generator for Site Checkup Pro
 *
 * Scans all plugin PHP files for __(), _e(), esc_html__(), esc_attr__(), esc_html_e(),
 * and esc_attr_e() using text domain 'site-checkup-pro' and compiles languages/site-checkup-pro.pot.
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/
 * @since   1.0.0
 */

// CLI check
if ( php_sapi_name() !== 'cli' ) {
	die( "CLI only.\n" );
}

$root_dir   = dirname( __DIR__ );
$lang_dir   = $root_dir . '/languages';
$output_pot = $lang_dir . '/site-checkup-pro.pot';

if ( ! is_dir( $lang_dir ) ) {
	mkdir( $lang_dir, 0755, true );
}

$files_to_scan = array();
$iterator      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root_dir ) );

foreach ( $iterator as $file ) {
	if ( $file->isDir() ) {
		continue;
	}
	$path = $file->getPathname();
	// Exclude vendor, tests, .git, docs, bin
	if ( preg_match( '/\/(tests|\.git|docs|bin|node_modules|languages)\//', $path ) ) {
		continue;
	}
	if ( substr( $path, -4 ) === '.php' ) {
		$files_to_scan[] = $path;
	}
}

$entries = array(); // msgid => array of references [file:line]

$pattern = '/(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\s*\(\s*([\'"])(.*?)\1\s*,\s*[\'"]site-checkup-pro[\'"]\s*\)/s';

foreach ( $files_to_scan as $file_path ) {
	$content = file_get_contents( $file_path );
	$rel_path = str_replace( $root_dir . '/', '', $file_path );

	// Match line by line or find offsets
	if ( preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $matches[2] as $idx => $match ) {
			$raw_msgid = $match[0];
			$offset    = $match[1];
			// Calculate line number
			$line = substr_count( substr( $content, 0, $offset ), "\n" ) + 1;

			// Normalize string
			$clean_msgid = stripcslashes( $raw_msgid );
			if ( ! isset( $entries[ $clean_msgid ] ) ) {
				$entries[ $clean_msgid ] = array();
			}
			$entries[ $clean_msgid ][] = "{$rel_path}:{$line}";
		}
	}
}

ksort( $entries );

$date = date( 'Y-m-d H:iO' );
$pot  = <<<POT
# Copyright (C) 2026 SK Sahinur Islam
# This file is distributed under the GPLv2 or later.
msgid ""
msgstr ""
"Project-Id-Version: Site Checkup Pro 1.0.0\\n"
"Report-Msgid-Bugs-To: https://github.com/GeniousSonu/site-checkup-pro/issues\\n"
"POT-Creation-Date: {$date}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"PO-Revision-Date: 2026-YEAR-MO-DA HO:MI+ZONE\\n"
"Last-Translator: SK Sahinur Islam <https://www.genioussonu.me/>\\n"
"Language-Team: English <support@genioussonu.me>\\n"
"X-Generator: Site Checkup Pro POT Generator\\n"
"X-Domain: site-checkup-pro\\n"

POT;

foreach ( $entries as $msgid => $refs ) {
	$pot .= "\n";
	foreach ( $refs as $ref ) {
		$pot .= "#: {$ref}\n";
	}
	// Escape quotes for po format
	$escaped_msgid = str_replace( array( '\\', '"' ), array( '\\\\', '\"' ), $msgid );
	// Handle multiline
	if ( strpos( $escaped_msgid, "\n" ) !== false ) {
		$lines = explode( "\n", $escaped_msgid );
		$pot .= "msgid \"\"\n";
		foreach ( $lines as $l ) {
			$pot .= "\"{$l}\\n\"\n";
		}
	} else {
		$pot .= "msgid \"{$escaped_msgid}\"\n";
	}
	$pot .= "msgstr \"\"\n";
}

file_put_contents( $output_pot, $pot );
echo "Generated POT file: {$output_pot} with " . count( $entries ) . " strings.\n";
