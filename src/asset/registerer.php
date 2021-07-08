<?php
namespace wplug\retrop;
/**
 *
 * Retrop Registerer
 *
 * @author Takuto Yanagida
 * @version 2021-07-08
 *
 */


require_once __DIR__ . '/util.php';
require_once __DIR__ . '/simple_html_dom.php';


class Registerer {

	const PMK_DIGEST      = '_digest';
	const PMK_IMPORT_FROM = '_import_from';

	private $_post_type;
	private $_type2structs;
	private $_required_cols;
	private $_digest_cols;
	private $_media_col = false;
	private $_labels;
	private $_post_filter;
	private $_debug = '';

	public function __construct( $post_type, $structs, $labels = [], $post_filter = null ) {
		$this->_post_type       = $post_type;
		$this->_type2structs    = $this->extract_type_struct( $structs );
		$this->_required_cols   = $this->extract_columns( $structs, FS_REQUIRED );
		$this->_digest_cols     = $this->extract_columns( $structs, FS_FOR_DIGEST );
		$this->_labels          = $labels;
		$this->_post_filter     = $post_filter;

		if ( isset( $this->_type2structs[ FS_TYPE_MEDIA ] ) ) {
			$keys = array_keys( $this->_type2structs[ FS_TYPE_MEDIA ] );
			if ( count( $keys ) ) $this->_media_col = $keys[0];
		}
		$this->_media_orig_to_cur = get_option( "retrop_registerer_media_$post_type", [] );
	}

	private function split_column_name( $col ) {
		$cs = explode( '|', $col );
		return $cs[0];
	}

	private function extract_type_struct( $structs ) {
		$t2ss = [];
		foreach ( FS_TYPES as $t ) $t2ss[ $t ] = [];

		foreach ( $structs as $col => $s ) {
			if ( ! isset( $s[ FS_TYPE ] ) ) continue;
			$type = $s[ FS_TYPE ];
			$t2ss[ $type ][ $col ] = $s;
		}
		return $t2ss;
	}

	private function extract_columns( $structs, $skey ) {
		$cols = [];
		foreach ( $structs as $col => $s ) {
			if ( ! isset( $s[ $skey ] ) || $s[ $skey ] !== true ) continue;
			$cols[] = $col;
		}
		return $cols;
	}

	private function is_required_field_filled( $item ) {
		foreach ( $this->_required_cols as $col ) {
			if ( ! isset( $item[ $col ] ) ) return false;
			if ( is_array( $item[ $col ] ) ) {
				if ( empty( $item[ $col ] ) ) return false;
			} else {
				if ( empty( trim( $item[ $col ] ) ) ) return false;
			}
		}
		return true;
	}

	private function get_digested_text( $item ) {
		$text = '';
		foreach ( $this->_digest_cols as $col ) {
			if ( ! isset( $item[ $col ] ) ) continue;
			$text .= trim( $item[ $col ] );
		}
		return $text;
	}


	// -------------------------------------------------------------------------


	private function get_media_id( $orig_id ) {
		if ( ! isset( $this->_media_orig_to_cur[ $orig_id ] ) ) return false;

		$aid = $this->_media_orig_to_cur[ $orig_id ];
		$p = get_post( $aid );
		if ( $p === null || $p->post_type !== 'attachment' ) {
			unset( $this->_media_orig_to_cur[ $orig_id ] );
			return false;
		}
		return $aid;
	}

	private function add_media_id( $orig_id, $id ) {
		$this->_media_orig_to_cur[ $orig_id ] = $id;

		$post_type = $this->_post_type;
		update_option( "retrop_registerer_media_$post_type", $this->_media_orig_to_cur );
	}

	private function convert_media_id( $orig_id, $orig_urls, $post_id, &$msg ) {
		$orig_url = $this->get_full_size_url( $orig_urls );
		$aid = $this->get_media_id( $orig_id );
		if ( $aid !== false ) {
			$msg .= '<p>The media file (#' . $orig_id . '): ' . esc_html( $orig_url ) . ' already exists.</p>';
		} else {
			$msg .= '<p>Try to download the media (#' . $orig_id . '): ' . esc_html( $orig_url ) . '</p>';
			$aid = $this->insert_attachment_from_url( $orig_url, $post_id, 60, $msg );
			if( $aid !== false) {
				$msg .= '<p>Download finished (new#' . $aid . ')</p>';
				$this->add_media_id( $orig_id, $aid );
			} else {
				$msg .= '<p>Failuer!</p>';
			}
		}
		return $aid;
	}

	private function insert_attachment_from_url( $url, $post_id = 0, $timeout, &$msg ) {
		$temp = download_url( $url, $timeout );
		if ( is_wp_error( $temp ) ) {
			if ( $msg ) $msg .= '<p>Error: ' . esc_html( $temp->get_error_message() ) . '</p>';
			return false;
		}
		$file = [ 'name' => basename( $url ), 'tmp_name' => $temp ];
		$attachment_id = media_handle_sideload( $file, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			if ( $msg ) $msg .= '<p>Error: ' . esc_html( $attachment_id->get_error_message() ) . '</p>';
			@unlink( $temp );
			return false;
		}
		return $attachment_id;
	}

	private function get_full_size_url( $orig_urls ) {
		usort( $orig_urls, function ( $a, $b ) {  // The shortest might be the full size url.
			if ( strlen( $a ) === strlen( $b ) ) return 0;
			return ( strlen( $a ) < strlen( $b ) ) ? -1 : 1;
		} );
		return $orig_urls[0];
	}


	// -------------------------------------------------------------------------


	public function process_item( $item, $file_name, $is_term_inserted = false ) {
		if ( ! $this->is_required_field_filled( $item ) ) return false;
		$digested_text = $this->get_digested_text( $item );
		$digest = make_digest( $digested_text );

		$olds = get_posts( [
			'post_type' => $this->_post_type,
			'meta_query' => [ [ 'key' => self::PMK_DIGEST, 'value' => $digest ] ],
		] );
		$post_id = empty( $olds ) ? false : $olds[0]->ID;
		$exists = $post_id !== false;

		if ( $this->_media_col && ! empty( $item[ $this->_media_col ] ) ) {
			$val = $item[ $this->_media_col ];
			$media = json_decode( $val, true );
			$item[ $this->_media_col ] = $media;
		}
		$title = $this->get_post_title( $item );
		$msg = '';

		if ( $post_id === false ) {  // Add a new post
			$args = [
				'post_type'    => $this->_post_type,
				'post_title'   => $title,
				'post_content' => 'A dummy text for inserting.',
			];
			$post_id = wp_insert_post( $args );
			if ( $post_id === 0 ) return false;
			$args = [
				'ID'            => $post_id,
				'post_type'     => $this->_post_type,
				'post_title'    => $title,
				'post_content'  => $this->get_post_content( $item, $post_id, $msg ),
				'post_date'     => $this->get_post_date( $item ),
				'post_date_gmt' => $this->get_post_date_gmt( $item ),
				'post_name'     => $this->get_post_name( $item ),
				'menu_order'    => $this->get_menu_order( $item ),
				'post_status'   => 'publish',
			];
			$post_id = wp_insert_post( $args );  // Insert again for assigning media to the page
		} else {  // Update the old post
			$args = [
				'ID'            => $post_id,
				'post_type'     => $this->_post_type,
				'post_title'    => $title,
				'post_content'  => $this->get_post_content( $item, $post_id, $msg ),
				'post_date'     => $this->get_post_date( $item ),
				'post_date_gmt' => $this->get_post_date_gmt( $item ),
				'post_name'     => $this->get_post_name( $item ),
				'menu_order'    => $this->get_menu_order( $item ),
				'post_status'   => 'publish',
			];
			$post_id = wp_insert_post( $args );  // Insert again for assigning media to the page
			if ( $post_id === 0 ) return false;
		}
		update_post_meta( $post_id, self::PMK_IMPORT_FROM, $file_name );
		update_post_meta( $post_id, self::PMK_DIGEST,      $digest );

		$this->update_post_metas( $item, $post_id, $msg );
		$this->add_terms( $item, $post_id, $is_term_inserted );
		$msg .= $this->update_post_thumbnail( $item, $post_id );
		if ( $this->_post_filter ) call_user_func( $this->_post_filter, $post_id );

		$msg .= '<p>' . $this->_labels[ $exists ? 'updated' : 'new' ] . ': ';
		$msg .= wp_kses_post( $digested_text ) . '</p>';
		$msg .= $this->_debug;
		return $msg;
	}

	private function filter( $item, $val, $filter, $post_id = false, &$msg ) {
		if ( $filter ) {
			switch ( $filter ) {
			case FS_FILTER_CONTENT:
				$val = str_replace( '\n', PHP_EOL, $val );  // '\n' is '\' + 'n', but not \n.
				$val = wp_kses_post( $val );
				break;
			case FS_FILTER_CONTENT_MEDIA:
				$val = str_replace( '\n', PHP_EOL, $val );  // '\n' is '\' + 'n', but not \n.
				$val = wp_kses_post( $val );
				$val = $this->_filter_content_media( $val, $item, $post_id, $msg );
				break;
			case FS_FILTER_NORM_DATE:
				$val = str_replace( '\n', PHP_EOL, $val );  // '\n' is '\' + 'n', but not \n.
				$val = normalize_date( $val );
				break;
			case FS_FILTER_NL2BR:
				// Do not add "\n" because WP recognizes "\n" as a paragraph separator.
				$val = str_replace( ['\n\n', '\n\n'], '\n&nbsp;\n', $val );
				$val = str_replace( '\n', '<br />', $val );
				break;
			case FS_FILTER_MEDIA_URL:
				$id_urls = json_decode( $val, true );
				$id = array_keys( $id_urls )[0];
				$val = $this->convert_media_id( $id, $id_urls[ $id ], $post_id, $msg );
				if ( $val === false ) $val = '';
				break;
			default:
				$val = str_replace( '\n', PHP_EOL, $val );  // '\n' is '\' + 'n', but not \n.
				if ( is_callable( $filter ) ) $val = call_user_func( $filter, $val );
				break;
			}
		} else {
			$val = str_replace( '\n', PHP_EOL, $val );  // '\n' is '\' + 'n', but not \n.
		}
		return $val;
	}


	// ---- POST TITLE


	private function get_post_title( $item ) {
		$title = '';
		foreach ( $this->_type2structs[ FS_TYPE_TITLE ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) continue;
			$title .= $item[ $col ];
		}
		return $title;
	}


	// ---- POST CONTENT


	private function get_post_content( $item, $post_id, &$msg ) {
		$content = '';
		foreach ( $this->_type2structs[ FS_TYPE_CONTENT ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) continue;
			$val = trim( $item[ $col ] );
			if ( empty( $val ) ) continue;

			$filter = isset( $s[ FS_FILTER ] ) ? $s[ FS_FILTER ] : false;
			$content .= $this->filter( $item, $val, $filter, $post_id, $msg );
		}
		return $content;
	}


	// ---- POST META


	private function update_post_metas( $item, $post_id, &$msg ) {
		foreach ( $this->_type2structs[ FS_TYPE_META ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) continue;
			$val = trim( $item[ $col ] );
			if ( empty( $val ) ) continue;

			if ( ! isset( $s[ FS_KEY ] ) ) continue;
			$key = $s[ FS_KEY ];
			$filter = isset( $s[ FS_FILTER ] ) ? $s[ FS_FILTER ] : false;
			update_post_meta( $post_id, $key, $this->filter( $item, $val, $filter, $post_id, $msg ) );
		}
		return true;
	}


	// ---- POST DATE & DATE GMT & POST NAME & MENU ORDER


	private function get_post_date( $item ) {
		$date = '';
		foreach ( $this->_type2structs[ FS_TYPE_DATE ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) continue;
			$date .= $item[ $col ];
		}
		return $date;
	}

	private function get_post_date_gmt( $item ) {
		$date = '';
		foreach ( $this->_type2structs[ FS_TYPE_DATE_GMT ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) continue;
			$date .= $item[ $col ];
		}
		return $date;
	}

	private function get_post_name( $item ) {
		foreach ( $this->_type2structs[ FS_TYPE_SLUG ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) continue;
			$val = $item[ $col ];
			if ( isset( $s[ FS_FILTER ] ) && $s[ FS_FILTER ] === FS_FILTER_SLUG ) {
				$val = strtolower( $val );
				$val = preg_replace( '/[^A-Za-z0-9]/', '-', $val );
				$val = preg_replace( '/--+/', '-', $val );
			}
			return $val;
		}
		return '';
	}

	private function get_menu_order( $item ) {
		$mo = 0;
		foreach ( $this->_type2structs[ FS_TYPE_MENU_ORDER ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) continue;
			$mo = intval( $item[ $col ] );
		}
		return $mo;
	}


	// ---- TERM


	private function add_terms( $item, $post_id, $is_term_inserted ) {
		foreach ( $this->_type2structs[ FS_TYPE_TERM ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) continue;

			if ( ! isset( $s[ FS_TAXONOMY ] ) ) continue;
			$tax = $s[ FS_TAXONOMY ];

			$vals = $item[ $col ];
			if ( ! is_array( $vals ) ) $vals = [ $vals ];

			if ( isset( $s[ FS_CONV ] ) ) $vals = $this->apply_conv_table( $vals, $s[ FS_CONV ] );
			if ( isset( $s[ FS_NORM_SLUG ] ) && $s[ FS_NORM_SLUG ] === true ) {
				$vals = $this->normalize_slugs( $vals );
			}
			$this->add_term( $post_id, $vals, $tax, $is_term_inserted );
		}
		return true;
	}

	private function apply_conv_table( $vals, $conv_table ) {
		$ret = [];
		foreach ( $vals as $val ) {
			if ( isset( $conv_table[ $val ] ) ) {
				$ret[] = $conv_table[ $val ];
			} else {
				$ret[] = $val;
			}
		}
		return $ret;
	}

	private function normalize_slugs( $vals ) {
		$ret = [];
		foreach ( $vals as $val ) {
			$ret[] = str_replace( '_', '-', $val );
		}
		return $ret;
	}

	private function add_term( $post_id, $vals, $tax, $is_term_inserted ) {
		$ts = get_terms( $tax, [ 'hide_empty' => false, 'fields' => 'id=>slug' ] );
		if ( is_wp_error( $ts ) ) return;
		$ts = array_values( $ts );

		$slugs = array_filter( $vals, function ( $v ) use ( $ts ) {
			return in_array( $v, $ts, true );
		} );
		if ( $is_term_inserted && count( $slugs ) !== count( $vals ) ) {
			$ue_slugs = $this->ensure_term_existing( $vals, $ts );
			$slugs = array_merge( $slugs, $ue_slugs );
		}
		if ( ! empty( $slugs ) ) wp_set_object_terms( $post_id, $slugs, $tax );  // Replace existing terms
	}

	private function ensure_term_existing( $vals, $ts ) {
		$ue_slugs = array_filter( $vals, function ( $v ) use ( $ts ) {
			return ! in_array( $v, $ts, true );
		} );
		if ( ! empty( $ue_slugs ) ) {
			$ue_slugs = array_values( array_unique( $ue_slugs ) );
			foreach ( $ue_slugs as $slug ) {
				$ret = wp_insert_term( $slug, $tax, [ 'slug' => $slug ] );
			}
		}
		return $us_slugs;
	}


	// ---- THUMBNAIL


	private function update_post_thumbnail( $item, $post_id ) {
		$msg = '';
		foreach ( $this->_type2structs[ FS_TYPE_THUMBNAIL_URL ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) continue;

			$id_urls = json_decode( $item[ $col ], true );
			$id = array_keys( $id_urls )[0];
			$aid = $this->convert_media_id( $id, $id_urls[ $id ], $post_id, $msg );
			if ( $aid !== false ) {
				set_post_thumbnail( $post_id, $aid );
			}
		}
		return $msg;
	}


	// ---- CONTENT MEDIA


	private function _filter_content_media( $val, $item, $post_id, &$msg ) {
		if ( empty( $item[ $this->_media_col ] ) ) return $val;
		$media = $item[ $this->_media_col ];

		$targets = [];
		foreach ( $media as $id => $urls ) {
			foreach ( $urls as $url ) $targets[ $url ] = $id;
		}

		$dom = str_get_html( $val, true, true, DEFAULT_TARGET_CHARSET, false );
		foreach ( $dom->find( 'img' ) as $elm ) {
			$url = $elm->src;
			if ( ! isset( $targets[ $url ] ) ) continue;
			$id = $targets[ $url ];
			list( $aid, $url ) = $this->_convert_url( (int) $id, $media[ $id ], $elm->class, $post_id, $msg );
			$elm->src = $url;
			if ( $aid ) $this->_replace_image_media_id_class( $elm, $aid );
		}
		foreach ( $dom->find( 'a' ) as $elm ) {
			$url = $elm->href;
			if ( ! isset( $targets[ $url ] ) ) continue;
			$id = $targets[ $url ];
			list( $aid, $url ) = $this->_convert_url( (int) $id, $media[ $id ], $elm->class, $post_id, $msg );
			$elm->href = $url;
		}
		$val = $dom->save();
		$dom->clear();
		unset($dom);
		return $val;
	}

	private function _convert_url( $orig_id, $orig_urls, $class, $post_id, &$msg ) {
		$aid = $this->convert_media_id( $orig_id, $orig_urls, $post_id, $msg );
		if ( $aid === false ) {
			$url = $this->get_full_size_url( $orig_urls );
			return [ false, $url ];
		}
		if ( empty( $class ) ) {
			$url = wp_get_attachment_url( $aid );
		} else {
			$cs = explode( ' ', $class );
			$size = 'full';
			foreach ( $cs as $c ) {
				if ( strpos( $c, 'size-' ) === 0 ) {
					$size = str_replace( 'size-', '', $c );
				}
			}
			$ais = wp_get_attachment_image_src( $aid, $size );
			$url = $ais[0];
		}
		return [ $aid, $url ];
	}

	private function _replace_image_media_id_class( $elm, $mid ) {
		$cls     = explode( ' ', $elm->class );
		$new_cls = [];

		$correct_id_cl = 'wp-image-' . $mid;

		$has_id      = false;
		$wrong_id_cl = false;

		foreach ( $cls as $cl ) {
			if ( strpos( $cl, 'wp-image-' ) === 0 ) {
				$has_id = true;
				if ( $cl !== $correct_id_cl ) $wrong_id_cl = $cl;
			} else {
				$new_cls[] = $cl;
			}
		}
		if ( $has_id && $wrong_id_cl === false ) return;

		$new_cls[] = $correct_id_cl;
		$elm->class = implode( ' ', $new_cls );
	}

}
