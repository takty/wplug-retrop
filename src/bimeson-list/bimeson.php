<?php
namespace st;

/**
 *
 * Functions and Definitions for Bimeson
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-02-23
 *
 */


require_once __DIR__ . '/../system/field.php';

require_once __DIR__ . '/bm-admin.php';
require_once __DIR__ . '/bm-taxonomy.php';


class Bimeson {

	const KEY_OMIT_FIRST = '_bimeson_pub_first_key_omitted';
	const KEY_ITEMS      = '_bimeson_pub_items';

	const FLD_SORT_KEY   = '__sortkey__';
	const FLD_BODY       = 'body';
	const FLD_DOI        = 'doi';
	const FLD_LINK_URL   = 'link-url';
	const FLD_LINK_TITLE = 'link-title';

	static private $_embed_keys = [ self::FLD_SORT_KEY, self::FLD_BODY, self::FLD_DOI, self::FLD_LINK_URL, self::FLD_LINK_TITLE ];

	static private $_instance = null;
	static public function get_instance() {
		if ( self::$_instance === null ) self::$_instance = new Bimeson();
		return self::$_instance;
	}

	private $_key;
	private $_admin = null;
	private $_tax   = null;

	private function __construct() {}

	public function initialize( $key, $taxonomy = false ) {
		$this->_key   = $key;
		$this->_admin = new Bimeson_Admin( $this, $key );
		$this->_tax   = new Bimeson_Taxonomy( $taxonomy );
	}

	public function enqueue_script( $url_to ) {
		if ( is_admin() ) {
			$this->_admin->enqueue_script( $url_to );
		} else {
			wp_enqueue_style(  'bimeson', $url_to . '/asset/pub-filter.css' );
			wp_enqueue_script( 'bimeson', $url_to . '/asset/pub-filter.js' );
		}
	}

	public function add_meta_box( $label, $screen ) {
		$this->_admin->add_meta_box( $label, $screen );
	}

	public function save_mata_box( $post_id ) {
		$this->_admin->save_mata_box( $post_id );
	}

	public function get_pub_key_ancestor() {
		return $this->_tax->get_pub_key_ancestor();
	}

	public function get_pub_key_order() {
		return $this->_tax->get_pub_key_order();
	}

	public function the_filter( $omit ) {
		$slug_to_term_ids = $this->_tax->get_slug_to_child_term_ids( $omit, true );
		foreach ( $slug_to_term_ids as $slug => $ids ) {
			if ( $slug !== '' ) $this->_tax->show_key_checkboxes( $ids, $slug );
		}
	}


	// -------------------------------------------------------------------------

	public function the_pub_list_section() {
		global $post;
		$omit  = get_post_meta( $post->ID, self::KEY_OMIT_FIRST, true );
		$items = json_decode( get_post_meta( $post->ID, self::KEY_ITEMS, true ), true );
		?>
			<section>
				<div class="section-inner">
					<div class="entry-content">
						<div class="bimeson-pub-filter">
							<?php $this->the_filter( $omit ); ?>
						</div>
						<div class="bimeson-pub-content stile">
							<?php $this->the_pub_list( $items, $omit ); ?>
						</div>
					</div>
				</div>
			</section>
		<?php
	}

	public function the_pub_list( $items, $omit = false ) {
		$los = $this->_tax->get_pub_key_last_omit();

		$rs = $this->_tax->get_pub_key_roots();
		$ds = $this->_tax->get_pub_key_depths();
		$skip_first = $omit ? $ds[ $rs[0] ] : 0;
		$last_depth = $ds[ $rs[ count( $rs ) - 1 ] ];

		$dep_size_orig = 0;
		foreach ( $ds as $d ) $dep_size_orig += $d;

		$root_slugs = [];
		foreach ( $rs as $k ) {
			for ( $i = 0; $i < $ds[ $k ]; $i++ ) $root_slugs[] = $k;
		}

		$buf = [];
		$prev_dep_val = array_pad( [], $dep_size_orig, '' );

		foreach ( $items as $item ) {
			$sortkey = $this->_get_sort_key( $item, $dep_size_orig );
			$dep = -1;
			$skip_last = false;
			$dep_size = $dep_size_orig;

			for ( $d = $skip_first; $d < $dep_size_orig; $d++ ) {
				if ( isset( $los[ $sortkey[ $d ] ] ) && $los[ $sortkey[ $d ] ] ) {
					$skip_last = true;
					$dep_size = $dep_size_orig - $last_depth;
				}
				if ( $sortkey[ $d ] !== $prev_dep_val[ $d ] ) {
					$dep = $d;
					break;
				}
			}
			if ( $dep !== -1 && $dep < $dep_size ) {
				$show_heading = false;
				for ( $d = $dep; $d < $dep_size; $d++ ) {
					if ( ! empty( $sortkey[ $d ] ) ) {
						$show_heading = $this->_is_heading_shown( $sortkey[ $d ] );
						if ( $show_heading ) break;
					}
				}
				if ( $show_heading && ! empty( $buf ) ) {
					$this->_echo_list( $buf );
					$buf = [];
				}
				for ( $d = $dep; $d < $dep_size; $d++ ) {
					if ( ! empty( $sortkey[ $d ] ) ) $this->_echo_heading( $d, $sortkey[ $d ], $root_slugs[ $d ], $skip_first );
				}
			}
			for ( $d = 0; $d < $dep_size_orig; $d++ ) $prev_dep_val[ $d ] = $sortkey[ $d ];
			$buf[] = $item;
		}
		if ( ! empty( $buf ) ) $this->_echo_list( $buf );
	}


	// -------------------------------------------------------------------------

	private function _get_sort_key( $item, $dep_size ) {
		$sortkey = explode( ',', $item[ self::FLD_SORT_KEY ] );
		if ( count( $sortkey ) !== $dep_size ) {  // for invalid 'sortkey'
			$sortkey = array_pad( $sortkey, $dep_size, '' );
		}
		return $sortkey;
	}

	private function _is_heading_shown( $slug ) {
		$t = get_term_by( 'slug', $slug, $this->_tax->get_taxonomy() );
		if ( ! $t ) return false;
		return true;
	}

	private function _echo_heading( $depth, $slug, $root_slug, $skip_first, $show_no_term = false ) {
		$t = get_term_by( 'slug', $slug, $this->_tax->get_taxonomy() );
		$name = '';
		if ( $t ) {
			$name = esc_html( $t->name );
		} else {
			if ( ! $show_no_term ) return;
			$name = is_number( $slug ) ? $slug : "[$slug]";
		}
		$heading_level = $depth + 2 - $skip_first;
		$tag = ( $heading_level <= 6 ) ? "h$heading_level" : 'div';
		$data_depth = $heading_level - 1;
		echo "<$tag class=\"$root_slug\" data-depth=\"$data_depth\">$name</$tag>\n";
	}

	private function _echo_list( $items) {
		if ( count( $items ) === 1 ) {
			echo "<ul>\n";
			foreach ( $items as $it ) $this->_echo_list_item( $it );
			echo "</ul>\n";
		} else {
			echo "<ol>\n";
			foreach ( $items as $it ) $this->_echo_list_item( $it );
			echo "</ol>\n";
		}
	}

	private function _echo_list_item( $item ) {
		$cs = [];
		$tax = $this->_tax->get_taxonomy();
		foreach ( $item as $key => $val ) {
			if ( in_array( $key, self::$_embed_keys, true ) ) continue;
			foreach ( $val as $v ) $cs[] = "$tax-$v";
		}
		$cls = esc_attr( implode(' ', $cs) );

		$_link = '';
		if ( isset( $item[ self::FLD_LINK_URL ] ) ) {
			$_url   = esc_url( $item[ self::FLD_LINK_URL ] );
			$_title = isset( $item[ self::FLD_LINK_TITLE ] ) ? esc_html( $item[ self::FLD_LINK_TITLE ] ) : $_url;
			$_link  = "<div class=\"link\"><a href=\"$_url\">$_title</a></div>";
		}

		$_doi = '';
		if ( isset( $item[ self::FLD_DOI ] ) ) {
			$_url   = esc_url( 'https://doi.org/' . $item[ self::FLD_DOI ] );
			$_title = esc_html( $item[ self::FLD_DOI ] );
			$_doi   = "<div class=\"doi\">DOI: <a href=\"$_url\">$_title</a></div>";
		}

		$_sort_key = esc_attr( $item[ self::FLD_SORT_KEY ] );
		$body      = $item[ self::FLD_BODY ];
		echo "<li class=\"$cls\" data-sortkey=\"$_sort_key\">$body$_link$_doi</li>\n";
	}

}
