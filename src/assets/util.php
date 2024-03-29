<?php
/**
 * Utilities for Retrop
 *
 * @package Wplug Retrop
 * @author Takuto Yanagida
 * @version 2021-09-02
 */

namespace wplug\retrop;

const FS_FOR_DIGEST = 'for_digest';
const FS_REQUIRED   = 'required';
const FS_TYPE       = 'type';

const FS_TYPE_TITLE         = 'post_title';
const FS_TYPE_CONTENT       = 'post_content';
const FS_TYPE_META          = 'post_meta';
const FS_TYPE_DATE          = 'post_date';
const FS_TYPE_DATE_GMT      = 'post_date_gmt';
const FS_TYPE_SLUG          = 'post_name';
const FS_TYPE_MENU_ORDER    = 'menu_order';
const FS_TYPE_TERM          = 'term';
const FS_TYPE_THUMBNAIL_URL = 'thumbnail_url';
const FS_TYPE_MEDIA         = 'media';
const FS_TYPE_ACF_PM        = 'acf_pm';

const FS_TYPES = array(
	FS_TYPE_TITLE,
	FS_TYPE_CONTENT,
	FS_TYPE_META,
	FS_TYPE_DATE,
	FS_TYPE_DATE_GMT,
	FS_TYPE_SLUG,
	FS_TYPE_MENU_ORDER,
	FS_TYPE_TERM,
	FS_TYPE_THUMBNAIL_URL,
	FS_TYPE_MEDIA,
);

// for FS_TYPE_META.
const FS_KEY    = 'key';
const FS_FILTER = 'filter';

const FS_FILTER_CONTENT       = 'post_content';        // for Importer.
const FS_FILTER_CONTENT_MEDIA = 'post_content_media';  // for Importer & Exporter.
const FS_FILTER_NORM_DATE     = 'norm_date';           // for Importer.
const FS_FILTER_NL2BR         = 'nl2br';               // for Importer.
const FS_FILTER_MEDIA_URL     = 'media_url';           // for Importer & Exporter.
const FS_FILTER_SLUG          = 'post_name';           // for Importer.

// for FS_TYPE_TERM.
const FS_TAXONOMY  = 'taxonomy';
const FS_AUTO_ADD  = 'auto_add';
const FS_CONV      = 'conv';
const FS_NORM_SLUG = 'norm_slug';
const FS_RAW       = 'raw';

/**
 * Makes the digest of a text.
 *
 * @param string $text Text.
 * @return string Digest.
 */
function make_digest( string $text ): string {
	$text = normalize_key_text( $text );
	$text = str_replace( ' ', '', $text );
	return hash( 'sha224', $text );
}

/**
 * Normalizes a string.
 *
 * @param string $text The body of an item.
 * @return string Normalized string.
 */
function normalize_key_text( string $text ): string {
	$text = wp_strip_all_tags( trim( $text ) );
	$text = mb_convert_kana( $text, 'rnasKV' );
	$text = mb_strtolower( $text );
	$text = preg_replace( '/[\s!-\/:-@[-`{-~]|[、。，．・：；？！´｀¨＾￣＿―‐／＼～∥｜…‥‘’“”（）〔〕［］｛｝〈〉《》「」『』【】＊※]/u', ' ', $text );
	$text = preg_replace( '/\s(?=\s)/', '', $text );
	$text = trim( $text );
	return $text;
}


// -----------------------------------------------------------------------------


/**
 * Gets the URL of the file.
 *
 * @param string $path The path of a file.
 * @return string The URL.
 */
function get_file_uri( string $path ): string {
	$path = wp_normalize_path( $path );

	if ( is_child_theme() ) {
		$theme_path = wp_normalize_path( defined( 'CHILD_THEME_PATH' ) ? CHILD_THEME_PATH : get_stylesheet_directory() );
		$theme_uri  = get_stylesheet_directory_uri();

		// When child theme is used, and libraries exist in the parent theme.
		$len_t = strlen( $theme_path );
		$len   = strlen( $path );
		if ( $len_t >= $len || 0 !== strncmp( $theme_path . $path[ $len_t ], $path, $len_t + 1 ) ) {
			$theme_path = wp_normalize_path( defined( 'THEME_PATH' ) ? THEME_PATH : get_template_directory() );
			$theme_uri  = get_template_directory_uri();
		}
		return str_replace( $theme_path, $theme_uri, $path );
	} else {
		$theme_path = wp_normalize_path( defined( 'THEME_PATH' ) ? THEME_PATH : get_stylesheet_directory() );
		$theme_uri  = get_stylesheet_directory_uri();
		return str_replace( $theme_path, $theme_uri, $path );
	}
}

/**
 * Gets the absolute URL of the relative URL.
 *
 * @param string $base A base URL.
 * @param string $rel  A relative URL.
 * @return string The absolute URL.
 */
function abs_url( string $base, string $rel ): string {
	$scheme = wp_parse_url( $rel, PHP_URL_SCHEME );
	if ( false === $scheme || null !== $scheme ) {
		return $rel;
	}
	$base = trailingslashit( $base );
	if ( '#' === $rel[0] || '?' === $rel[0] ) {
		return $base . $rel;
	}
	$pu = wp_parse_url( $base );
	// phpcs:disable
	$scheme = isset( $pu['scheme'] ) ? $pu['scheme'] . '://' : '';
	$host   = isset( $pu['host'] )   ? $pu['host']           : '';
	$port   = isset( $pu['port'] )   ? ':' . $pu['port']     : '';
	$path   = isset( $pu['path'] )   ? $pu['path']           : '';
	// phpcs:enable
	$path = preg_replace( '#/[^/]*$#', '', $path );
	if ( '/' === $rel[0] ) {
		$path = '';
	}
	$abs = "$host$port$path/$rel";
	$re  = array( '#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#' );
	for ( $n = 1; $n > 0; $abs = preg_replace( $re, '/', $abs, -1, $n ) ) {}  // phpcs:ignore
	return $scheme . $abs;
}
