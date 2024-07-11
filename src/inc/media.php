<?php
/**
 * Retrop (Media)
 *
 * @package Wplug Retrop
 * @author Takuto Yanagida
 * @version 2024-07-11
 */

namespace wplug\retrop;

/**
 * Inserts an uploaded file to the media library.
 *
 * @access private
 *
 * @param string $url     URL of uploaded file.
 * @param int    $post_id Post ID.
 * @param int    $timeout The timeout for the request to download the file.
 * @param string $msg     Returned message.
 * @return int New media ID.
 */
function _insert_attachment_from_url( string $url, int $post_id, int $timeout, string &$msg ): int {
	$temp = download_url( $url, $timeout );
	if ( is_wp_error( $temp ) ) {
		if ( $msg ) {
			$msg .= '<p>Error: ' . esc_html( $temp->get_error_message() ) . '</p>';
		}
		return false;
	}
	$file = array(
		'name'     => basename( $url ),
		'tmp_name' => $temp,
	);

	$attachment_id = media_handle_sideload( $file, $post_id );

	if ( is_wp_error( $attachment_id ) ) {
		if ( $msg ) {
			$msg .= '<p>Error: ' . esc_html( $attachment_id->get_error_message() ) . '</p>';
		}
		if ( is_file( $temp ) ) {
			unlink( $temp );  // phpcs:ignore
		}
		return false;
	}
	return $attachment_id;
}

/**
 * Retrieves the URL of a full size image.
 *
 * @access private
 *
 * @param array $orig_urls Original URLs.
 * @return string The URL.
 */
function _get_full_size_url( array $orig_urls ): string {
	usort(
		$orig_urls,
		function ( $a, $b ) {
			// The shortest might be the full size url.
			if ( strlen( $a ) === strlen( $b ) ) {
				return 0;
			}
			return ( strlen( $a ) < strlen( $b ) ) ? -1 : 1;
		}
	);
	return $orig_urls[0];
}


// -----------------------------------------------------------------------------


/**
 * Converts the URL of a media file.
 *
 * @param int    $new_id    New media ID.
 * @param array  $orig_urls Original URLs.
 * @param string $cls       CSS classes.
 * @return string Converted URL.
 */
function _convert_url( int $new_id, array $orig_urls, string $cls ): string {
	if ( false === $new_id ) {
		$url = _get_full_size_url( $orig_urls );
		return array( false, $url );
	}
	if ( empty( $cls ) ) {
		$url = wp_get_attachment_url( $new_id );
	} else {
		$cs   = explode( ' ', $cls );
		$size = 'full';
		foreach ( $cs as $c ) {
			if ( strpos( $c, 'size-' ) === 0 ) {
				$size = str_replace( 'size-', '', $c );
			}
		}
		$ais = wp_get_attachment_image_src( $new_id, $size );
		$url = $ais[0];
	}
	return $url;
}

/**
 * Replaces image media ID class.
 *
 * @param mixed $elm A DOM element.
 * @param int   $mid Media ID.
 */
function _replace_image_media_id_class( $elm, int $mid ) {
	$cls     = explode( ' ', $elm->class );
	$new_cls = array();

	$correct_id_cl = 'wp-image-' . $mid;

	$has_id      = false;
	$wrong_id_cl = false;

	foreach ( $cls as $cl ) {
		if ( strpos( $cl, 'wp-image-' ) === 0 ) {
			$has_id = true;
			if ( $cl !== $correct_id_cl ) {
				$wrong_id_cl = $cl;
			}
		} else {
			$new_cls[] = $cl;
		}
	}
	if ( $has_id && false === $wrong_id_cl ) {
		return;
	}
	$new_cls[]  = $correct_id_cl;
	$elm->class = implode( ' ', $new_cls );
}


// -----------------------------------------------------------------------------


/**
 * Extracts media URLs.
 *
 * @access private
 *
 * @param string $val     DOM string.
 * @param array  $id2urls The array of media ID to its URL.
 */
function _extract_media( string $val, array &$id2urls ) {
	$dom = str_get_html( $val );
	if ( false === $dom ) {
		$dom = str_get_html( '<html><body>' . $val . '</body></html>' );
	}
	if ( false === $dom ) {
		return;
	}
	foreach ( $dom->find( 'img' ) as &$elm ) {
		_add_media( $id2urls, $elm->src );
	}
	foreach ( $dom->find( 'a' ) as &$elm ) {
		_add_media( $id2urls, $elm->href );
	}
	$dom->clear();
	unset( $dom );
}

/**
 * Adds a media URL.
 *
 * @access private
 *
 * @param array  $id2urls The array of media ID to its URL.
 * @param string $url     URL of a media.
 */
function _add_media( array &$id2urls, string $url ) {
	$id_url = _get_media_id( $url );
	if ( null === $id_url ) {
		return;
	}
	$id   = $id_url['id'];
	$furl = $id_url['url'];
	if ( ! isset( $id2urls[ $id ] ) ) {
		$id2urls[ $id ] = array();
	}
	$id2urls[ $id ][ $furl ] = true;
	$id2urls[ $id ][ $url ]  = true;
}

/**
 * Retrieves a media ID from a URL.
 *
 * @access private
 *
 * @param string $url URL.
 * @return array A pair of media ID and URL.
 */
function _get_media_id( string $url ): ?array {
	$ud         = wp_upload_dir();
	$upload_url = $ud['baseurl'];
	if ( strpos( $url, $upload_url ) !== 0 ) {
		return null;
	}
	$id_url = _search_media_id( $url, $upload_url );
	if ( null !== $id_url ) {
		return $id_url;
	}
	$full_url = preg_replace( '/(-[0-9]+x[0-9]+)(\.[^.]+){0,1}$/i', '${2}', $url );
	if ( $url === $full_url ) {
		return null;
	}
	return _search_media_id( $full_url, $upload_url );
}

/**
 * Searches a media ID.
 *
 * @param string $url        URL.
 * @param string $upload_url Upload URL.
 * @return array A pair of media ID and URL.
 */
function _search_media_id( string $url, string $upload_url ): ?array {
	global $wpdb;

	$attached_file = str_replace( $upload_url . '/', '', $url );

	$q  = $wpdb->prepare(  // phpcs:ignore
		"SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_wp_attached_file' AND meta_value='%s' LIMIT 1;",  // phpcs:ignore
		$attached_file
	);
	$id = (int) $wpdb->get_var( $q );  // phpcs:ignore
	if ( 0 === $id ) {
		return null;
	}
	return array(
		'id'  => $id,
		'url' => $url,
	);
}
