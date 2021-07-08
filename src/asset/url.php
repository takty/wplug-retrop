<?php
namespace wplug\retrop;
/**
 *
 * URL Utilities
 *
 * @author Takuto Yanagida
 * @version 2021-02-22
 *
 */


function _home_url( $path = '' ) {
	if ( class_exists( '\st\Multilang' ) ) {
		return \st\Multilang::get_instance()->home_url( $path );
	}
	return home_url( $path );
}


// -----------------------------------------------------------------------------


function get_current_uri( $raw = false ) {
	$host = get_server_host();
	if ( $raw && isset( $_SERVER['REQUEST_URI_ORIG'] ) ) {
		return ( is_ssl() ? 'https://' : 'http://' ) . $host . $_SERVER['REQUEST_URI_ORIG'];
	}
	return ( is_ssl() ? 'https://' : 'http://' ) . $host . $_SERVER['REQUEST_URI'];
}

function get_server_host() {
	if ( isset( $_SERVER['HTTP_X_FORWARDED_HOST'] ) ) {  // When reverse proxy exists
		return $_SERVER['HTTP_X_FORWARDED_HOST'];
	}
	return $_SERVER['HTTP_HOST'];
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


// -----------------------------------------------------------------------------


function get_first_slug( $url ) {
	$hu = _home_url( '/' );
	$temp = str_replace( $hu, '', $url );
	$ps = explode( '/', $temp );
	if ( count( $ps ) > 0 ) return $ps[0];
	return '';
}

function get_first_and_second_slug( $url ) {
	$hu = _home_url( '/' );
	$temp = str_replace( $hu, '', $url );
	$ps = explode( '/', $temp );
	$ss = ['', ''];
	if ( count( $ps ) > 0 ) $ss[0] = $ps[0];
	if ( count( $ps ) > 1 ) $ss[1] = $ps[1];
	return $ss;
}

function get_last_slug( $url ) {
	$url_ps = explode( '/', untrailingslashit( $url ) );
	return $url_ps[ count( $url_ps ) - 1 ];
}


// -----------------------------------------------------------------------------


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

function serialize_url( $pu ) {
	$scheme = isset( $pu['scheme'] )   ? $pu['scheme'] . '://' : '';
	$host   = isset( $pu['host'] )     ? $pu['host']           : '';
	$port   = isset( $pu['port'] )     ? ':' . $pu['port']     : '';
	$user   = isset( $pu['user'] )     ? $pu['user']           : '';
	$pass   = isset( $pu['pass'] )     ? ':' . $pu['pass']     : '';
	$pass   = ( $user || $pass )       ? "$pass@"              : '';
	$path   = isset( $pu['path'] )     ? $pu['path']           : '';
	$query  = isset( $pu['query'] )    ? '?' . $pu['query']    : '';
	$frag   = isset( $pu['fragment'] ) ? '#' . $pu['fragment'] : '';
	return "$scheme$user$pass$host$port$path$query$frag";
}
