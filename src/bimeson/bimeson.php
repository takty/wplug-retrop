<?php
namespace st;

/**
 *
 * Functions and Definitions for Bimeson
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-03-06
 *
 */


require_once __DIR__ . '/../../stinc/system/field.php';
require_once __DIR__ . '/bm-taxonomy.php';
require_once __DIR__ . '/bm-admin.php';


class Bimeson {

	const FLD_BODY        = '_body';
	const FLD_DATE        = '_date';
	const FLD_DOI         = '_doi';
	const FLD_LINK_URL    = '_link_url';
	const FLD_LINK_TITLE  = '_link_title';

	const FLD_DATE_NUM    = '_date_num';
	const FLD_IMPORT_FROM = '_import_from';
	const FLD_DIGEST      = '_digest';

	static private $_instance = null;
	static public function get_instance() {
		if ( self::$_instance === null ) self::$_instance = new Bimeson();
		return self::$_instance;
	}

	private $_additional_langs;
	private $_tax   = null;
	private $_admin = null;

	private function __construct() {}

	public function initialize( $additional_langs = [], $taxonomy = false, $sub_tax_base = false ) {
		$this->_additional_langs = $additional_langs;
		$this->_tax = new Bimeson_Taxonomy( [ 'taxonomy' => '分類' ], $taxonomy, $sub_tax_base );

		$this->_register_post_type();
		$this->_add_shortcodes();

		if ( is_admin() ) $this->_admin = new Bimeson_Admin( $this->_additional_langs, $this->_tax );
	}

	private function _register_post_type() {
		register_post_type( 'bimeson', [
			'label'         => '業績',
			'labels'        => [],
			'public'        => true,
			'show_ui'       => true,
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-analytics',
			'has_archive'   => false,
			'rewrite'       => false,
			'supports'      => [ 'title', 'editor' ],
		] );
	}

	private function _add_shortcodes() {
		add_shortcode( 'publication', function ( $atts, $content = null ) {
			$params = [
				'date'        => '',
				'date_start'  => '',
				'date_end'    => '',
				'count'       => '-1',
				'show_filter' => ''
			];
			$rss = $this->_tax->get_root_slugs();
			foreach ( $rss as $rs ) {
				$params[ $rs ] = '';
			}
			$atts = shortcode_atts( $params, $atts );

			$tq = [];
			$al = '';
			if ( class_exists( '\st\Multilang' ) ) {
				$ml = \st\Multilang::get_instance();
				$al = $ml->get_site_lang();
				if ( ! in_array( $al, $this->_additional_langs, true ) ) $al = '';
				$tq[] = $ml->get_tax_query();
			}

			foreach ( $rss as $rs ) {
				if ( empty( $atts[ $rs ] ) ) continue;
				$sub_tax = $this->_tax->term_to_taxonomy( $rs );
				$tmp = str_replace( ' ', '', $atts[ $rs ] );
				$slugs = explode( ',', $tmp );
				$tq[] = [
					'taxonomy' => $sub_tax,
					'field'    => 'slug',
					'terms'    => $slugs,
				];
			}

			$mq = [];
			if ( ! empty( $atts['date'] ) ) {
				$date_s = $this->_normalize_date( $atts['date'], '0' );
				$date_e = $this->_normalize_date( $atts['date'], '9' );

				$mq['meta_date'] = [
					'key'     => self::FLD_DATE_NUM,
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
					'value'   => [ $date_s, $date_e ],
				];
			}
			if ( ! empty( $atts['date_start'] ) && ! empty( $atts['date_end'] ) ) {
				$date_s = $this->_normalize_date( $atts['date_start'], '0' );
				$date_e = $this->_normalize_date( $atts['date_end'], '9' );

				$mq['meta_date'] = [
					'key'     => self::FLD_DATE_NUM,
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
					'value'   => [ $date_s, $date_e ],
				];
			}
			if ( ! empty( $atts['date_start'] ) && empty( $atts['date_end'] ) ) {
				$mq['meta_date'] = [
					'key'     => self::FLD_DATE_NUM,
					'type'    => 'NUMERIC',
					'compare' => '>=',
					'value'   => $this->_normalize_date( $atts['date_start'], '0' ),
				];
			}
			if ( empty( $atts['date_start'] ) && ! empty( $atts['date_end'] ) ) {
				$mq['meta_date'] = [
					'key'     => self::FLD_DATE_NUM,
					'type'    => 'NUMERIC',
					'compare' => '<=',
					'value'   => $this->_normalize_date( $atts['date_end'], '9' ),
				];
			}
			if ( ! isset( $mq['meta_date'] ) ) {
				$mq['meta_date'] = [
					'key'     => self::FLD_DATE_NUM,
					'type'    => 'NUMERIC',
				];
			}
			$ps = get_posts( [
				'post_type' => 'bimeson',
				'posts_per_page' => intval( $atts['count'] ),
				'tax_query' => $tq,
				'meta_query' => $mq,
				'orderby' => [ 'meta_date' => 'desc', 'date' => 'desc' ]
			] );
			if ( count( $ps ) === 0 ) return '';

			ob_start();
			if ( ! empty( $atts['show_filter'] ) ) {
				$tmp = str_replace( ' ', '', $atts['show_filter'] );
				$slugs = explode( ',', $tmp );
				$this->the_filter( $slugs );
			}

			if ( ! is_null( $content ) ) {
				$content = str_replace( '<p></p>', '', balanceTags( $content, true ) );
				echo $content;
			}
			$this->_echo_list( $ps, $al );
			$ret = ob_get_contents();
			ob_end_clean();
			return $ret;
		} );
	}

	private function _normalize_date( $val, $pad_num ) {
		$val = str_replace( ['-', '/'], '', trim( $val ) );
		return str_pad( $val, 8, $pad_num, STR_PAD_RIGHT );
	}


	// -------------------------------------------------------------------------

	public function get_sub_taxonomies() {
		return $this->_tax->get_sub_taxonomies();
	}

	public function enqueue_script( $url_to = false ) {
		if ( ! is_admin() ) {
			if ( $url_to === false ) $url_to = \st\get_file_uri( __DIR__ );
			wp_enqueue_style(  'bimeson', $url_to . '/asset/bm-filter.min.css' );
			wp_enqueue_script( 'bimeson', $url_to . '/asset/bm-filter.min.js' );
		}
	}

	public function the_filter( $slugs ) {
		$slug_to_terms = [];
		$rss = $this->_tax->get_root_slugs();
		foreach ( $rss as $rs ) {
			if ( $slugs[0] === 'all' || in_array( $rs, $slugs, true ) ) {
				$sub_tax = $this->_tax->term_to_taxonomy( $rs );
				$terms = get_terms( $sub_tax, [ 'hide_empty' => 0 ] );
				$slug_to_terms[ $rs ] = $terms;
			}
		}
		foreach ( $slug_to_terms as $slug => $terms ) {
			$this->_tax->show_tax_checkboxes( $terms, $slug );
		}
	}

	private function _echo_list( $ps, $al ) {
		if ( count( $ps ) === 1 ) {
			echo "<ul data-bm=\"on\">\n";
			foreach ( $ps as $p ) $this->_echo_list_item( $p, $al );
			echo "</ul>\n";
		} else {
			echo "<ol data-bm=\"on\">\n";
			foreach ( $ps as $p ) $this->_echo_list_item( $p, $al );
			echo "</ol>\n";
		}
	}

	private function _echo_list_item( $p, $al ) {
		$cs = [];
		$rss = $this->_tax->get_root_slugs();
		foreach ( $rss as $rs ) {
			$sub_tax = $this->_tax->term_to_taxonomy( $rs );
			$ts = get_the_terms( $p->ID, $sub_tax );
			if ( $ts === false ) continue;
			foreach ( $ts as $t ) {
				$cs[] = str_replace( '_', '-', Bimeson_Taxonomy::DEFAULT_SUB_TAX_BASE . "$rs-{$t->slug}" );
			}
		}
		$cls = esc_attr( implode( ' ', $cs ) );

		$body = '';
		if ( ! empty( $al ) ) $body = \st\get_the_sub_content( "_post_content_$al", $p->ID );
		if ( empty( $body ) ) $body = $p->post_content;

		$doi = get_post_meta( $p->ID, self::FLD_DOI, true) ;
		$lurl = get_post_meta( $p->ID, self::FLD_LINK_URL, true) ;
		$ltitle = get_post_meta( $p->ID, self::FLD_LINK_TITLE, true) ;

		$_link = '';
		if ( ! empty( $lurl ) ) {
			$_url   = esc_url( $lurl );
			$_title = ( ! empty( $ltitle ) ) ? esc_html( $ltitle ) : $_url;
			$_link  = "<span class=\"link\"><a href=\"$_url\">$_title</a></span>";
		}

		$_doi = '';
		if ( ! empty( $doi ) ) {
			$_url   = esc_url( "https://doi.org/$doi" );
			$_title = esc_html( $doi );
			$_doi   = "<span class=\"doi\">DOI: <a href=\"$_url\">$_title</a></span>";
		}

		$_edit_link = '';
		if ( is_user_logged_in() && current_user_can( 'edit_post', $p->ID ) ) {
			$_edit_url = admin_url( "post.php?post={$p->ID}&action=edit" );
			$_edit_link = " <a href=\"$_edit_url\">EDIT</a>";
		}

		echo "<li class=\"$cls\"><div>";
		\st\echo_content( $body );
		echo "$_link$_doi$_edit_link</div></li>\n";
	}

}
