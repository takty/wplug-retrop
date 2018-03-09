<?php
namespace st;

/**
 *
 * Functions and Definitions for Bimeson
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-03-08
 *
 */


require_once __DIR__ . '/../../stinc/system/field.php';
require_once __DIR__ . '/bm-admin.php';
require_once __DIR__ . '/bm-taxonomy.php';
require_once __DIR__ . '/bm-list.php';


class Bimeson {

	const KEY_OMIT_FIRST = '_bimeson_pub_first_key_omitted';
	const KEY_ITEMS      = '_bimeson_pub_items';

	const FLD_BODY       = '_body';
	const FLD_DATE       = '_date';
	const FLD_DOI        = '_doi';
	const FLD_LINK_URL   = '_link_url';
	const FLD_LINK_TITLE = '_link_title';

	const FLD_DATE_NUM   = '_date_num';
	const FLD_CAT_KEY    = '_cat_key';
	const FLD_INDEX      = '_index';

	static private $_instance = null;
	static public function get_instance() {
		if ( self::$_instance === null ) self::$_instance = new Bimeson();
		return self::$_instance;
	}

	private $_key;
	private $_tax   = null;
	private $_admin = null;
	private $_list  = null;

	private function __construct() {}

	public function initialize( $key, $taxonomy = false, $sub_tax_base = false ) {
		$this->_key  = $key;
		$this->_tax  = new Bimeson_Taxonomy( Bimeson_List::PT, [ 'taxonomy' => '分類', 'omit_last_cat' => '一番最後の分類を省略', 'hide from view' => '閲覧画面から隠す' ], $taxonomy, $sub_tax_base );
		$this->_list = new Bimeson_List( $this->_tax );

		$this->_add_shortcodes();

		if ( is_admin() ) $this->_admin = new Bimeson_Admin( $this, $this->_tax, $key );
	}

	private function _add_shortcodes() {
		add_shortcode( 'publication', function ( $atts, $content = null ) {
			global $post;
			$omit_first_cat = get_post_meta( $post->ID, self::KEY_OMIT_FIRST, true );
			$items = $this->_get_list_items( $post->ID );
			ob_start();
?>
			<div class="bimeson-pub-filter">
				<?php $this->the_filter( $omit_first_cat ); ?>
			</div>
			<div class="bimeson-pub-content stile">
				<?php $this->the_list( $items, $omit_first_cat ); ?>
			</div>
<?php
			$ret = ob_get_contents();
			ob_end_clean();
			return $ret;
		} );
	}


	// -------------------------------------------------------------------------

	public function enqueue_script( $url_to = false ) {
		if ( $url_to === false ) $url_to = \st\get_file_uri( __DIR__ );
		$url_to = untrailingslashit( $url_to );

		if ( is_admin() ) {
			$this->_admin->enqueue_script( $url_to );
		} else {
			wp_enqueue_style(  'bimeson', $url_to . '/asset/pub-filter.min.css' );
			wp_enqueue_script( 'bimeson', $url_to . '/asset/pub-filter.min.js' );
		}
	}

	public function get_sub_taxonomies() {
		return $this->_tax->get_sub_taxonomies();
	}

	public function add_meta_box( $label, $screen ) {
		$this->_admin->add_meta_box( $label, $screen );
	}

	public function save_mata_box( $post_id ) {
		$this->_admin->save_mata_box( $post_id );
	}

	public function the_filter( $omit_first_cat ) {
		$this->_tax->the_filter( $omit_first_cat );
	}


	// -------------------------------------------------------------------------

	private function _get_list_items( $post_id ) {
		$list_id = get_post_meta( $post_id, Bimeson_Admin::KEY_LIST_ID, true );
		$items_json = get_post_meta( $list_id, self::KEY_ITEMS, true );
		return json_decode( $items_json, true );
	}

	private function _sort_items( array &$items ) {
		$this->_first_cat_omitted = false;

		$rs_to_slugs       = $this->_tax->get_root_slugs_to_sub_slugs();
		$rs_to_depths      = $this->_tax->get_root_slugs_to_sub_depths();
		$slug_to_ancestors = $this->_tax->get_sub_slug_to_ancestors();

		foreach ( $items as $idx => &$it ) {
			$it[ self::FLD_CAT_KEY ] = $this->_make_cat_key( $it, $rs_to_slugs, $rs_to_depths, $slug_to_ancestors );
			$it[ self::FLD_INDEX ]   = $idx;
		}
		usort( $items, [ $this, '_cb_compare_item' ] );
	}

	private function _make_cat_key( $item, $rs_to_slugs, $rs_to_depths, $slug_to_ancestors ) {
		$cats = [];
		foreach ( $rs_to_slugs as $rs => $slugs ) {
			if ( ! isset( $rs_to_depths[ $rs ] ) ) continue;
			$depth = $rs_to_depths[ $rs ];

			list( $idx, $s ) = $this->_get_one_of_ordered_terms( $item, $rs, $slugs );
			if ( $s ) {
				if ( isset( $slug_to_ancestors[ $s ] ) ) {
					$cats = array_merge( $cats, $slug_to_ancestors[ $s ] );
				}
				$cats[] = $s;
				$depth -= count( $cats );
			}
			for ( $i = 0; $i < $depth; $i += 1 ) $cats[] = '';
		}
		return implode( ',', $cats );
	}

	private function _cb_compare_item( $a, $b ) {
		$rs_to_slugs = $this->_tax->get_root_slugs_to_sub_slugs();

		$idx = 0;
		foreach ( $rs_to_slugs as $rs => $slugs ) {
			if ( $idx === 0 && $this->_first_cat_omitted ) {
				$idx += 1;
				continue;
			}
			list( $ai, $av ) = $this->_get_one_of_ordered_terms( $a, $rs, $slugs );
			list( $bi, $bv ) = $this->_get_one_of_ordered_terms( $b, $rs, $slugs );
			if ( $av === null && $bv === null) continue;

			if ( $ai === -1 && $bi === -1) {
				$c = strcmp( $av, $bv );
				if ( $c !== 0 ) return $c;
			} else {
				if ( $ai < $bi ) return -1;
				if ( $ai > $bi ) return 1;
			}
			$idx += 1;
		}
		return $a[ self::FLD_INDEX ] < $b[ self::FLD_INDEX ] ? -1 : 1;
	}

	private function _get_one_of_ordered_terms( $item, $rs, $sub_slugs ) {
		if ( ! isset( $item[ $rs ] ) ) return [ -1, null ];
		$vs = $item[ $rs ];
		foreach ( $sub_slugs as $idx => $s ) {
			if ( in_array( $s, $vs, true ) ) return [ $idx, $s ];
		}
		return [ -1, null ];
	}


	// -------------------------------------------------------------------------

	public function the_list_section() {
		global $post;
		$omit_first_cat  = get_post_meta( $post->ID, self::KEY_OMIT_FIRST, true );
		$items = $this->_get_list_items( $post->ID );
?>
			<section>
				<div class="section-inner">
					<div class="entry-content">
						<div class="bimeson-pub-filter">
							<?php $this->the_filter( $omit_first_cat ); ?>
						</div>
						<div class="bimeson-pub-content stile">
							<?php $this->the_list( $items, $omit_first_cat ); ?>
						</div>
					</div>
				</div>
			</section>
<?php
	}

	public function the_list( $items, $omit_first_cat = false ) {
		if ( ! is_array( $items ) ) return;
		$this->_sort_items( $items );

		$rss = $this->_tax->get_root_slugs();
		$rs_to_depth  = $this->_tax->get_root_slugs_to_sub_depths();

		$hier_first = $omit_first_cat ? $rs_to_depth[ $rss[0] ] : 0;
		$last_cat_depth = $rs_to_depth[ $rss[ count( $rss ) - 1 ] ];

		$hier_size_orig = 0;
		$hier_to_rs = [];
		foreach ( $rs_to_depth as $rs => $d ) {
			$hier_size_orig += $d;
			for ( $i = 0; $i < $d; $i++ ) $hier_to_rs[] = $rs;
		}

		$slug_to_last_omit = $this->_tax->get_slug_to_last_omit();
		$buf = [];
		$prev_cat_key = array_pad( [], $hier_size_orig, '' );

		foreach ( $items as $item ) {
			$cat_key = $this->_get_cat_key( $item, $hier_size_orig );
			$hier = -1;
			$hier_size = $hier_size_orig;

			for ( $h = $hier_first; $h < $hier_size_orig; $h++ ) {
				$key = $cat_key[ $h ];
				if ( isset( $slug_to_last_omit[ $key ] ) ) {
					$hier_size = $hier_size_orig - $last_cat_depth;
				}
				if ( $key !== $prev_cat_key[ $h ] ) {
					$hier = $h;
					break;
				}
			}
			if ( $hier !== -1 && $hier < $hier_size ) {
				$is_cat_exist = false;
				for ( $h = $hier; $h < $hier_size; $h++ ) {
					if ( ! empty( $cat_key[ $h ] ) ) {
						$is_cat_exist = $this->_is_cat_exist( $hier_to_rs[ $h ], $cat_key[ $h ] );
						if ( $is_cat_exist ) break;
					}
				}
				if ( $is_cat_exist && ! empty( $buf ) ) {
					$this->_echo_list( $buf );
					$buf = [];
				}
				for ( $h = $hier; $h < $hier_size; $h++ ) {
					if ( ! empty( $cat_key[ $h ] ) ) $this->_echo_heading( $h, $hier_first, $hier_to_rs[ $h ], $cat_key[ $h ] );
				}
			}
			$prev_cat_key = $cat_key;  // Clone!
			$item['__catkey__'] = implode( ',', $cat_key );
			$buf[] = $item;
		}
		if ( ! empty( $buf ) ) $this->_echo_list( $buf );
	}

	private function _get_cat_key( $item, $hier_size ) {
		$cat_key = explode( ',', $item[ self::FLD_CAT_KEY ] );
		if ( count( $cat_key ) !== $hier_size ) {  // for invalid 'sortkey'
			$cat_key = array_pad( $cat_key, $hier_size, '' );
		}
		return $cat_key;
	}

	private function _is_cat_exist( $root_slug, $slug ) {
		$t = get_term_by( 'slug', $slug, $this->_tax->term_to_taxonomy( $root_slug ) );
		if ( ! $t ) return false;
		return true;
	}

	private function _echo_heading( $hier, $hier_first, $root_slug, $slug ) {
		$t = get_term_by( 'slug', $slug, $this->_tax->term_to_taxonomy( $root_slug ) );
		if ( $t ) {
			$_name = esc_html( $t->name );
		} else {
			$_name = esc_html( is_numeric( $slug ) ? $slug : "[$slug]" );
		}
		$level = $hier + 2 - $hier_first;
		$tag = ( $level <= 6 ) ? "h$level" : 'div';
		$data_depth = $level - 1;
		echo "<$tag class=\"$root_slug\" data-depth=\"$data_depth\">$_name</$tag>\n";
	}

	private function _echo_list( $items ) {
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
		$sl = \st\Multilang::get_instance()->get_site_lang();
		if ( isset( $item[ self::FLD_BODY . "_$sl" ] ) ) $body = $item[ self::FLD_BODY . "_$sl" ];
		else if ( isset( $item[ self::FLD_BODY ] ) ) $body = $item[ self::FLD_BODY ];
		else {
			return;
		}

		$doi    = isset( $item[ self::FLD_DOI ] )        ? $item[ self::FLD_DOI ]        : '';
		$lurl   = isset( $item[ self::FLD_LINK_URL ] )   ? $item[ self::FLD_LINK_URL ]   : '';
		$ltitle = isset( $item[ self::FLD_LINK_TITLE ] ) ? $item[ self::FLD_LINK_TITLE ] : '';

		$_link = '';
		if ( ! empty( $lurl ) ) {
			$_url   = esc_url( $lurl );
			$_title = empty( $ltitle ) ? $_url : esc_html( $ltitle );
			$_link  = "<div class=\"link\"><a href=\"$_url\">$_title</a></div>";
		}
		$_doi = '';
		if ( ! empty( $doi ) ) {
			$_url   = esc_url( "https://doi.org/$doi" );
			$_title = esc_html( $doi );
			$_doi   = "<div class=\"doi\">DOI: <a href=\"$_url\">$_title</a></div>";
		}

		$_cls = esc_attr( implode( ' ', $this->_make_cls_array( $item ) ) );
		$_catkey = esc_attr( $item['__catkey__'] );

		echo "<li class=\"$_cls\" data-catkey=\"$_catkey\"><div>";
		echo "$body";
		echo "$_link$_doi</div></li>\n";
	}

	private function _make_cls_array( $item ) {
		$cs = [];
		$tax = $this->_tax->get_taxonomy();
		foreach ( $item as $key => $val ) {
			if ( $key[0] === '_' ) continue;
			foreach ( $val as $v ) $cs[] = "$tax-$v";
		}
		return $cs;
	}

}
