<?php
namespace wplug\retrop;

/**
 *
 * Utilities for Retrop
 *
 * @author Takuto Yanagida
 * @version 2021-07-08
 *
 */


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

const FS_TYPES = [ FS_TYPE_TITLE, FS_TYPE_CONTENT, FS_TYPE_META, FS_TYPE_DATE, FS_TYPE_DATE_GMT, FS_TYPE_SLUG, FS_TYPE_MENU_ORDER, FS_TYPE_TERM, FS_TYPE_THUMBNAIL_URL, FS_TYPE_MEDIA ];

// for FS_TYPE_META
const FS_KEY    = 'key';
const FS_FILTER = 'filter';

const FS_FILTER_CONTENT       = 'post_content';        // for Importer
const FS_FILTER_CONTENT_MEDIA = 'post_content_media';  // for Importer & Exporter
const FS_FILTER_NORM_DATE     = 'norm_date';           // for Importer
const FS_FILTER_NL2BR         = 'nl2br';               // for Importer
const FS_FILTER_MEDIA_URL     = 'media_url';           // for Importer & Exporter
const FS_FILTER_SLUG          = 'post_name';           // for Importer

// for FS_TYPE_TERM
const FS_TAXONOMY  = 'taxonomy';
const FS_AUTO_ADD  = 'auto_add';
const FS_CONV      = 'conv';
const FS_NORM_SLUG = 'norm_slug';
const FS_RAW       = 'raw';


function make_digest( $text ) {
	$text = normalize_key_text( $text );
	$text = str_replace( ' ', '', $text );
	return hash( 'sha224', $text );
}

function normalize_key_text( $text ) {
	$text = strip_tags( trim( $text ) );
	$text = mb_convert_kana( $text, 'rnasKV' );
	$text = mb_strtolower( $text );
	$text = preg_replace( '/[\s!-\/:-@[-`{-~]|[、。，．・：；？！´｀¨＾￣＿―‐／＼～∥｜…‥‘’“”（）〔〕［］｛｝〈〉《》「」『』【】＊※]/u', ' ', $text );
	$text = preg_replace( '/\s(?=\s)/', '', $text );
	$text = trim( $text );
	return $text;
}

function normalize_date( $str ) {
	$str = mb_convert_kana( $str, 'n', 'utf-8' );
	$nums = preg_split( '/\D/', $str );
	$vals = [];
	foreach ( $nums as $num ) {
		$v = (int) trim( $num );
		if ( $v !== 0 ) $vals[] = $v;
	}
	if ( 3 <= count( $vals ) ) {
		$str = sprintf( '%04d-%02d-%02d', $vals[0], $vals[1], $vals[2] );
	} else if ( count( $vals ) === 2 ) {
		$str = sprintf( '%04d-%02d', $vals[0], $vals[1] );
	} else if ( count( $vals ) === 1 ) {
		$str = sprintf( '%04d', $vals[0] );
	}
	return $str;
}


// -----------------------------------------------------------------------------


function get_file_uri( $path ) {
	$path = wp_normalize_path( $path );

	if ( is_child_theme() ) {
		$theme_path = wp_normalize_path( defined( 'CHILD_THEME_PATH' ) ? CHILD_THEME_PATH : get_stylesheet_directory() );
		$theme_uri  = get_stylesheet_directory_uri();

		// When child theme is used, and libraries exist in the parent theme
		$tlen = strlen( $theme_path );
		$len  = strlen( $path );
		if ( $tlen >= $len || 0 !== strncmp( $theme_path . $path[ $tlen ], $path, $tlen + 1 ) ) {
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

function abs_url( $base, $rel ) {
	if ( parse_url( $rel, PHP_URL_SCHEME ) != '' ) return $rel;
	$base = trailingslashit( $base );
	if ( $rel[0] === '#' || $rel[0] === '?' ) return $base . $rel;

	$pu = parse_url( $base );
	$scheme = isset( $pu['scheme'] ) ? $pu['scheme'] . '://' : '';
	$host   = isset( $pu['host'] )   ? $pu['host']           : '';
	$port   = isset( $pu['port'] )   ? ':' . $pu['port']     : '';
	$path   = isset( $pu['path'] )   ? $pu['path']           : '';

	$path = preg_replace( '#/[^/]*$#', '', $path );
	if ( $rel[0] === '/' ) $path = '';
	$abs = "$host$port$path/$rel";
	$re = [ '#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#' ];
	for ( $n = 1; $n > 0; $abs = preg_replace( $re, '/', $abs, -1, $n ) ) {}
	return $scheme . $abs;
}
