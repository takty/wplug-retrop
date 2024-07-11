<?php
/**
 * Retrop Registerer
 *
 * @package Wplug Retrop
 * @author Takuto Yanagida
 * @version 2024-07-11
 */

namespace wplug\retrop;

require_once __DIR__ . '/../assets/util.php';
require_once __DIR__ . '/../assets/date-field.php';
require_once __DIR__ . '/../assets/simple_html_dom.php';
require_once __DIR__ . '/media.php';

/**
 * Registerer.
 */
class Registerer {

	const PMK_DIGEST      = '_digest';
	const PMK_IMPORT_FROM = '_import_from';

	/**
	 * Post type of imported posts.
	 *
	 * @var 1.0
	 */
	private $post_type;

	/**
	 * Array of field types to structures.
	 *
	 * @var 1.0
	 */
	private $type2structs;

	/**
	 * Required columns.
	 *
	 * @var 1.0
	 */
	private $required_cols;

	/**
	 * Columns for digests.
	 *
	 * @var 1.0
	 */
	private $digest_cols;

	/**
	 * Media columns.
	 *
	 * @var 1.0
	 */
	private $media_col = false;

	/**
	 * Labels.
	 *
	 * @var 1.0
	 */
	private $labels;

	/**
	 * Post filter.
	 *
	 * @var 1.0
	 */
	private $post_filter;

	/**
	 * Debug string.
	 *
	 * @var 1.0
	 */
	private $debug = '';

	/**
	 * Media IDs table.
	 *
	 * @var 1.0
	 */
	private $media_orig_to_cur;

	/**
	 * Constructor.
	 *
	 * @param string   $post_type   Post type of imported posts.
	 * @param array    $structs     The structure.
	 * @param array    $labels      Labels.
	 * @param callable $post_filter Post filter.
	 */
	public function __construct( string $post_type, array $structs, array $labels = array(), $post_filter = null ) {
		$this->post_type     = $post_type;
		$this->type2structs  = $this->extract_type_struct( $structs );
		$this->required_cols = $this->extract_columns( $structs, FS_REQUIRED );
		$this->digest_cols   = $this->extract_columns( $structs, FS_FOR_DIGEST );
		$this->labels        = $labels;
		$this->post_filter   = $post_filter;

		if ( isset( $this->type2structs[ FS_TYPE_MEDIA ] ) ) {
			$keys = array_keys( $this->type2structs[ FS_TYPE_MEDIA ] );
			if ( count( $keys ) ) {
				$this->media_col = $keys[0];
			}
		}
		$this->media_orig_to_cur = get_option( "retrop_registerer_media_$post_type", array() );
	}

	/**
	 * Splits a column name.
	 *
	 * @access private
	 *
	 * @param string $col Column name.
	 * @return string Column name.
	 */
	private function split_column_name( string $col ): string {
		$cs = explode( '|', $col );
		return $cs[0];
	}

	/**
	 * Extracts the array of field types to structures.
	 *
	 * @access private
	 *
	 * @param array $structs The structure.
	 * @return array Array of field types to structures.
	 */
	private function extract_type_struct( array $structs ): array {
		$t2ss = array();
		foreach ( FS_TYPES as $t ) {
			$t2ss[ $t ] = array();
		}
		foreach ( $structs as $col => $s ) {
			if ( ! isset( $s[ FS_TYPE ] ) ) {
				continue;
			}
			$type = $s[ FS_TYPE ];

			$t2ss[ $type ][ $col ] = $s;
		}
		return $t2ss;
	}

	/**
	 * Extracts the column of a specific type.
	 *
	 * @access private
	 *
	 * @param array  $structs The structure.
	 * @param string $key     Key name.
	 * @return array A column.
	 */
	private function extract_columns( array $structs, string $key ): array {
		$cols = array();
		foreach ( $structs as $col => $s ) {
			if ( ! isset( $s[ $key ] ) || true !== $s[ $key ] ) {
				continue;
			}
			$cols[] = $col;
		}
		return $cols;
	}

	/**
	 * Checks whether all required fields are filled.
	 *
	 * @access private
	 *
	 * @param array $item Checked item.
	 * @return bool True if the fields are filled.
	 */
	private function is_required_field_filled( array $item ): bool {
		foreach ( $this->required_cols as $col ) {
			if ( ! isset( $item[ $col ] ) ) {
				return false;
			}
			if ( is_array( $item[ $col ] ) ) {
				if ( empty( $item[ $col ] ) ) {
					return false;
				}
			} else {  // phpcs:ignore
				if ( empty( trim( $item[ $col ] ) ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Retrieves a text for the digest.
	 *
	 * @access private
	 *
	 * @param array $item Item.
	 * @return string A text for the digest.
	 */
	private function get_digested_text( array $item ): string {
		$text = '';
		foreach ( $this->digest_cols as $col ) {
			if ( ! isset( $item[ $col ] ) ) {
				continue;
			}
			$text .= trim( $item[ $col ] );
		}
		return $text;
	}


	// -------------------------------------------------------------------------


	/**
	 * Retrieves the new media ID.
	 *
	 * @access private
	 *
	 * @param int $orig_id Original media ID.
	 * @return int New media ID.
	 */
	private function get_media_id( int $orig_id ): int {
		if ( ! isset( $this->media_orig_to_cur[ $orig_id ] ) ) {
			return false;
		}
		$aid = $this->media_orig_to_cur[ $orig_id ];
		$p   = get_post( $aid );
		if ( null === $p || 'attachment' !== $p->post_type ) {
			unset( $this->media_orig_to_cur[ $orig_id ] );
			return false;
		}
		return $aid;
	}

	/**
	 * Stores a new media ID.
	 *
	 * @access private
	 *
	 * @param int $orig_id Original media ID.
	 * @param int $id      New media ID.
	 */
	private function add_media_id( int $orig_id, int $id ) {
		$this->media_orig_to_cur[ $orig_id ] = $id;

		$post_type = $this->post_type;
		update_option( "retrop_registerer_media_$post_type", $this->media_orig_to_cur );
	}

	/**
	 * Converts media ID.
	 *
	 * @access private
	 *
	 * @param int    $orig_id   Original ID.
	 * @param array  $orig_urls Array of original URLs.
	 * @param int    $post_id   Post ID.
	 * @param string $msg       Returned message.
	 * @return ?int New media ID.
	 */
	private function convert_media_id( int $orig_id, array $orig_urls, int $post_id, string &$msg ): ?int {
		$orig_url = _get_full_size_url( $orig_urls );
		$aid      = $this->get_media_id( $orig_id );

		if ( false !== $aid ) {
			$msg .= '<p>The media file (#' . $orig_id . '): ' . esc_html( $orig_url ) . ' already exists.</p>';
		} else {
			$msg .= '<p>Try to download the media (#' . $orig_id . '): ' . esc_html( $orig_url ) . '</p>';

			$aid = _insert_attachment_from_url( $orig_url, $post_id, 60, $msg );
			if ( false !== $aid ) {
				$msg .= '<p>Download finished (new#' . $aid . ')</p>';
				$this->add_media_id( $orig_id, $aid );
			} else {
				$msg .= '<p>Failure!</p>';
			}
		}
		return $aid;
	}


	// -------------------------------------------------------------------------


	/**
	 * Processes an item.
	 *
	 * @param array  $item         An item to be processed.
	 * @param string $file_name    The file name of the imported file.
	 * @param bool   $add_new_term Whether to add new terms.
	 * @return bool True if succeeded.
	 */
	public function process_item( array $item, string $file_name, bool $add_new_term = false ): bool {
		if ( ! $this->is_required_field_filled( $item ) ) {
			return false;
		}
		$digested_text = $this->get_digested_text( $item );
		$digest        = make_digest( $digested_text );

		$olds = get_posts(
			array(
				'post_type'  => $this->post_type,
				'meta_query' => array(  // phpcs:ignore
					array(
						'key'   => self::PMK_DIGEST,
						'value' => $digest,
					),
				),
			)
		);

		$post_id = empty( $olds ) ? false : $olds[0]->ID;
		$exists  = false !== $post_id;

		if ( $this->media_col && ! empty( $item[ $this->media_col ] ) ) {
			$val   = $item[ $this->media_col ];
			$media = wp_json_decode( $val, true );

			$item[ $this->media_col ] = $media;
		}
		$title = $this->get_post_title( $item );
		$msg   = '';

		if ( false === $post_id ) {  // Add a new post.
			$args = array(
				'post_type'    => $this->post_type,
				'post_title'   => $title,
				'post_content' => 'A dummy text for inserting.',
			);

			$post_id = wp_insert_post( $args );
			if ( 0 === $post_id ) {
				return false;
			}

			$args = array(
				'ID'            => $post_id,
				'post_type'     => $this->post_type,
				'post_title'    => $title,
				'post_content'  => $this->get_post_content( $item, $post_id, $msg ),
				'post_date'     => $this->get_post_date( $item ),
				'post_date_gmt' => $this->get_post_date_gmt( $item ),
				'post_name'     => $this->get_post_name( $item ),
				'menu_order'    => $this->get_menu_order( $item ),
				'post_status'   => 'publish',
			);

			$post_id = wp_insert_post( $args );  // Insert again for assigning media to the page.
		} else {  // Update the old post.
			$args = array(
				'ID'            => $post_id,
				'post_type'     => $this->post_type,
				'post_title'    => $title,
				'post_content'  => $this->get_post_content( $item, $post_id, $msg ),
				'post_date'     => $this->get_post_date( $item ),
				'post_date_gmt' => $this->get_post_date_gmt( $item ),
				'post_name'     => $this->get_post_name( $item ),
				'menu_order'    => $this->get_menu_order( $item ),
				'post_status'   => 'publish',
			);

			$post_id = wp_insert_post( $args );  // Insert again for assigning media to the page.
			if ( 0 === $post_id ) {
				return false;
			}
		}
		update_post_meta( $post_id, self::PMK_IMPORT_FROM, $file_name );
		update_post_meta( $post_id, self::PMK_DIGEST, $digest );

		$this->update_post_metas( $item, $post_id, $msg );
		$this->add_terms( $item, $post_id, $add_new_term );
		$msg .= $this->update_post_thumbnail( $item, $post_id );
		if ( $this->post_filter ) {
			call_user_func( $this->post_filter, $post_id );
		}
		$msg .= '<p>' . wp_kses_data( $this->labels[ $exists ? 'updated' : 'new' ] ) . ': ';
		$msg .= wp_kses_post( $digested_text ) . '</p>';
		$msg .= $this->debug;
		return $msg;
	}

	/**
	 * Filters an item field.
	 *
	 * @access private
	 *
	 * @param array  $item    Item.
	 * @param mixed  $val     Value to be filtered.
	 * @param string $filter  Filter type.
	 * @param int    $post_id Post ID.
	 * @param string $msg     Returned message.
	 * @return mixed Filtered value.
	 */
	private function filter( array $item, $val, string $filter, int $post_id, string &$msg ) {
		if ( $filter ) {
			switch ( $filter ) {
				case FS_FILTER_CONTENT:
					$val = str_replace( '\n', PHP_EOL, $val );  // '\n' is '\' + 'n', but not \n.
					$val = wp_kses_post( $val );
					break;
				case FS_FILTER_CONTENT_MEDIA:
					$val = str_replace( '\n', PHP_EOL, $val );  // '\n' is '\' + 'n', but not \n.
					$val = wp_kses_post( $val );
					$val = $this->filter_content_media( $val, $item, $post_id, $msg );
					break;
				case FS_FILTER_NORM_DATE:
					$val = str_replace( '\n', PHP_EOL, $val );  // '\n' is '\' + 'n', but not \n.
					$val = \wplug\normalize_date( $val );
					break;
				case FS_FILTER_NL2BR:
					// Do not add "\n" because WP recognizes "\n" as a paragraph separator.
					$val = str_replace( array( '\n\n', '\n\n' ), '\n&nbsp;\n', $val );
					$val = str_replace( '\n', '<br>', $val );
					break;
				case FS_FILTER_MEDIA_URL:
					$id_urls = json_decode( $val, true );
					$id      = array_keys( $id_urls )[0];
					$val     = $this->convert_media_id( $id, $id_urls[ $id ], $post_id, $msg );
					if ( false === $val ) {
						$val = '';
					}
					break;
				default:
					$val = str_replace( '\n', PHP_EOL, $val );  // '\n' is '\' + 'n', but not \n.
					if ( is_callable( $filter ) ) {
						$val = call_user_func( $filter, $val );
					}
					break;
			}
		} else {
			$val = str_replace( '\n', PHP_EOL, $val );  // '\n' is '\' + 'n', but not \n.
		}
		return $val;
	}


	// ---- POST TITLE.


	/**
	 * Retrieves the post title.
	 *
	 * @access private
	 *
	 * @param array $item Item.
	 * @return string The title.
	 */
	private function get_post_title( array $item ): string {
		$title = '';
		foreach ( $this->type2structs[ FS_TYPE_TITLE ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) {
				continue;
			}
			$title .= $item[ $col ];
		}
		return $title;
	}


	// ---- POST CONTENT.


	/**
	 * Retrieves the post content.
	 *
	 * @access private
	 *
	 * @param array  $item    Item.
	 * @param int    $post_id Post ID.
	 * @param string $msg     Returned message.
	 * @return string The content.
	 */
	private function get_post_content( array $item, int $post_id, string &$msg ): string {
		$content = '';
		foreach ( $this->type2structs[ FS_TYPE_CONTENT ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) {
				continue;
			}
			$val = trim( $item[ $col ] );
			if ( empty( $val ) ) {
				continue;
			}
			$filter = isset( $s[ FS_FILTER ] ) ? $s[ FS_FILTER ] : false;

			$content .= $this->filter( $item, $val, $filter, $post_id, $msg );
		}
		return $content;
	}


	// ---- POST META.


	/**
	 * Updates post meta values.
	 *
	 * @access private
	 *
	 * @param array  $item    Item.
	 * @param int    $post_id Post ID.
	 * @param string $msg     Returned message.
	 */
	private function update_post_metas( array $item, int $post_id, string &$msg ) {
		foreach ( $this->type2structs[ FS_TYPE_META ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) {
				continue;
			}
			$val = trim( $item[ $col ] );
			if ( empty( $val ) ) {
				continue;
			}
			if ( ! isset( $s[ FS_KEY ] ) ) {
				continue;
			}
			$key    = $s[ FS_KEY ];
			$filter = isset( $s[ FS_FILTER ] ) ? $s[ FS_FILTER ] : false;
			update_post_meta( $post_id, $key, $this->filter( $item, $val, $filter, $post_id, $msg ) );
		}
	}


	// ---- POST DATE & DATE GMT & POST NAME & MENU ORDER.


	/**
	 * Retrieves the post date.
	 *
	 * @access private
	 *
	 * @param array $item Item.
	 * @return string The date.
	 */
	private function get_post_date( array $item ): string {
		$date = '';
		foreach ( $this->type2structs[ FS_TYPE_DATE ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) {
				continue;
			}
			$date .= $item[ $col ];
		}
		return $date;
	}

	/**
	 * Retrieves the post GMT date.
	 *
	 * @access private
	 *
	 * @param array $item Item.
	 * @return string The GMT date.
	 */
	private function get_post_date_gmt( array $item ): string {
		$date = '';
		foreach ( $this->type2structs[ FS_TYPE_DATE_GMT ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) {
				continue;
			}
			$date .= $item[ $col ];
		}
		return $date;
	}

	/**
	 * Retrieves the post name.
	 *
	 * @access private
	 *
	 * @param array $item Item.
	 * @return string The name.
	 */
	private function get_post_name( array $item ): string {
		foreach ( $this->type2structs[ FS_TYPE_SLUG ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) {
				continue;
			}
			$val = $item[ $col ];
			if ( isset( $s[ FS_FILTER ] ) && FS_FILTER_SLUG === $s[ FS_FILTER ] ) {
				$val = strtolower( $val );
				$val = preg_replace( '/[^A-Za-z0-9]/', '-', $val );
				$val = preg_replace( '/--+/', '-', $val );
			}
			return $val;
		}
		return '';
	}

	/**
	 * Retrieves the menu order.
	 *
	 * @access private
	 *
	 * @param array $item Item.
	 * @return int The menu order.
	 */
	private function get_menu_order( array $item ): int {
		$mo = 0;
		foreach ( $this->type2structs[ FS_TYPE_MENU_ORDER ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) {
				continue;
			}
			$mo = (int) $item[ $col ];
		}
		return $mo;
	}


	// ---- TERM.


	/**
	 * Assigns terms to new post.
	 *
	 * @access private
	 *
	 * @param array $item         Item.
	 * @param int   $post_id      Post ID.
	 * @param bool  $add_new_term Whether to add new terms.
	 */
	private function add_terms( $item, $post_id, $add_new_term ) {
		foreach ( $this->type2structs[ FS_TYPE_TERM ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) {
				continue;
			}
			if ( ! isset( $s[ FS_TAXONOMY ] ) ) {
				continue;
			}
			$tax = $s[ FS_TAXONOMY ];

			$vals = $item[ $col ];
			if ( ! is_array( $vals ) ) {
				$vals = array( $vals );
			}
			if ( isset( $s[ FS_CONV ] ) ) {
				$vals = $this->apply_conv_table( $vals, $s[ FS_CONV ] );
			}
			if ( isset( $s[ FS_NORM_SLUG ] ) && true === $s[ FS_NORM_SLUG ] ) {
				$vals = $this->normalize_slugs( $vals );
			}
			$this->add_term( $post_id, $vals, $tax, $add_new_term );
		}
	}

	/**
	 * Applies conversion table to values.
	 *
	 * @access private
	 *
	 * @param array $vals       Values.
	 * @param array $conv_table Conversion table.
	 * @return array Converted values.
	 */
	private function apply_conv_table( array $vals, array $conv_table ): array {
		$ret = array();
		foreach ( $vals as $val ) {
			if ( isset( $conv_table[ $val ] ) ) {
				$ret[] = $conv_table[ $val ];
			} else {
				$ret[] = $val;
			}
		}
		return $ret;
	}

	/**
	 * Normalizes slugs.
	 *
	 * @access private
	 *
	 * @param array $vals Slugs.
	 * @return array Normalized slugs.
	 */
	private function normalize_slugs( array $vals ): array {
		$ret = array();
		foreach ( $vals as $val ) {
			$ret[] = str_replace( '_', '-', $val );
		}
		return $ret;
	}

	/**
	 * Assigns terms to new post.
	 *
	 * @access private
	 *
	 * @param int    $post_id      Post ID.
	 * @param array  $vals         Term slugs.
	 * @param string $tax          Taxonomy slug.
	 * @param bool   $add_new_term Whether to add new terms.
	 */
	private function add_term( int $post_id, array $vals, string $tax, bool $add_new_term ) {
		$ts = get_terms(
			array(
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'fields'     => 'id=>slug',
			)
		);
		if ( is_wp_error( $ts ) ) {
			return;
		}
		$ts    = array_values( $ts );
		$slugs = array_filter(
			$vals,
			function ( $v ) use ( $ts ) {
				return in_array( $v, $ts, true );
			}
		);
		if ( $add_new_term && count( $slugs ) !== count( $vals ) ) {
			$ue_slugs = $this->ensure_term_existing( $vals, $ts );
			$slugs    = array_merge( $slugs, $ue_slugs );
		}
		if ( ! empty( $slugs ) ) {
			wp_set_object_terms( $post_id, $slugs, $tax );  // Replace existing terms.
		}
	}

	/**
	 * Ensures that the terms exist.
	 *
	 * @access private
	 *
	 * @param array $vals Term slugs.
	 * @param array $ts   Existing term slugs.
	 * @return array Added term slugs.
	 */
	private function ensure_term_existing( array $vals, array $ts ): array {
		$ue_slugs = array_filter(
			$vals,
			function ( $v ) use ( $ts ) {
				return ! in_array( $v, $ts, true );
			}
		);
		if ( ! empty( $ue_slugs ) ) {
			$ue_slugs = array_values( array_unique( $ue_slugs ) );
			foreach ( $ue_slugs as $slug ) {
				$ret = wp_insert_term( $slug, $tax, array( 'slug' => $slug ) );
			}
		}
		return $us_slugs;
	}


	// ---- THUMBNAIL.


	/**
	 * Updates post thumbnail.
	 *
	 * @access private
	 *
	 * @param array $item    Item.
	 * @param int   $post_id Post ID.
	 * @return string Returned message.
	 */
	private function update_post_thumbnail( array $item, int $post_id ): string {
		$msg = '';
		foreach ( $this->type2structs[ FS_TYPE_THUMBNAIL_URL ] as $col => $s ) {
			$col = $this->split_column_name( $col );
			if ( ! isset( $item[ $col ] ) ) {
				continue;
			}
			$id_urls = json_decode( $item[ $col ], true );
			$id      = array_keys( $id_urls )[0];
			$aid     = $this->convert_media_id( $id, $id_urls[ $id ], $post_id, $msg );
			if ( false !== $aid ) {
				set_post_thumbnail( $post_id, $aid );
			}
		}
		return $msg;
	}


	// ---- CONTENT MEDIA


	/**
	 * Filters media in the content.
	 *
	 * @access private
	 *
	 * @param mixed  $val     Value.
	 * @param array  $item    Item.
	 * @param int    $post_id Post ID.
	 * @param string $msg     Returned message.
	 * @return mixed Filtered value.
	 */
	private function filter_content_media( $val, array $item, int $post_id, string &$msg ) {
		if ( empty( $item[ $this->media_col ] ) ) {
			return $val;
		}
		$media = $item[ $this->media_col ];

		$targets = array();
		foreach ( $media as $id => $urls ) {
			foreach ( $urls as $url ) {
				$targets[ $url ] = $id;
			}
		}

		$dom = str_get_html( $val, true, true, DEFAULT_TARGET_CHARSET, false );
		foreach ( $dom->find( 'img' ) as $elm ) {
			$url = $elm->src;
			if ( ! isset( $targets[ $url ] ) ) {
				continue;
			}
			$id = $targets[ $url ];

			$aid = $this->convert_media_id( $orig_id, $orig_urls, $post_id, $msg );
			$url = _convert_url( $aid, $media[ $id ], $elm->class );

			$elm->src = $url;
			if ( $aid ) {
				_replace_image_media_id_class( $elm, $aid );
			}
		}
		foreach ( $dom->find( 'a' ) as $elm ) {
			$url = $elm->href;
			if ( ! isset( $targets[ $url ] ) ) {
				continue;
			}
			$id = $targets[ $url ];

			$aid = $this->convert_media_id( $orig_id, $orig_urls, $post_id, $msg );
			$url = _convert_url( $aid, $media[ $id ], $elm->class );

			$elm->href = $url;
		}
		$val = $dom->save();
		$dom->clear();
		unset( $dom );
		return $val;
	}
}
