<?php
namespace st;

/**
 *
 * Bimeson (Admin)
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-10-23
 *
 */


require_once __DIR__ . '/../../stinc/admin/media-picker.php';


class Bimeson_List {

	const NS            = 'bimeson_list';
	const PT            = 'bimeson_list';
	const NOT_MODIFIED  = '<NOT MODIFIED>';

	const FLD_MEDIA     = '_bimeson_media';
	const FLD_ADD_TAX   = '_bimeson_add_tax';
	const FLD_ADD_TERM  = '_bimeson_add_term';
	const FLD_ITEMS     = '_bimeson_items';

	const LBL_POST_TYPE = '業績リスト';
	const LBL_ADD_TAX   = '分類自体を追加';
	const LBL_ADD_TERM  = '分類に項目を追加';
	const LBL_UPDATE    = 'リストを更新';

	private $_tax;

	public function __construct( $tax ) {
		$this->_tax = $tax;
		register_post_type( self::PT, [
			'label'         => self::LBL_POST_TYPE,
			'labels'        => [],
			'public'        => true,
			'show_ui'       => true,
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-analytics',
			'has_archive'   => false,
			'rewrite'       => false,
			'supports'      => [ 'title' ],
		] );
		add_action( 'admin_menu',            [ $this, '_cb_admin_menu' ] );
		add_action( 'save_post_' . self::PT, [ $this, '_cb_save_post' ] );
	}


	// -------------------------------------------------------------------------

	public function _cb_admin_menu() {
		add_action( 'admin_print_scripts', [ $this, '_cb_enqueue_script_media' ] );
		if ( \st\page_template_admin\is_post_type( self::PT ) ) {
			$this->_cb_enqueue_script();
			add_meta_box( 'bimeson_mb', self::LBL_POST_TYPE, [ $this, '_cb_output_html_list' ], self::PT, 'normal', 'high' );
		}
	}

	public function _cb_enqueue_script( $url_to = false ) {
		global $pagenow;
		if ( $pagenow !== 'post.php' && $pagenow !== 'post-new.php' ) return;
		if ( ! \st\page_template_admin\is_post_type( self::PT ) ) return;

		if ( $url_to === false ) $url_to = \st\get_file_uri( __DIR__ );
		$url_to = untrailingslashit( $url_to );

		\st\media_picker\enqueue_script_for_admin( $url_to . '/../../stinc/admin/' );
		wp_enqueue_style(  self::NS, $url_to . '/asset/bm-list.min.css' );
		wp_enqueue_script( self::NS, $url_to . '/asset/bm-list.min.js' );
		wp_enqueue_script( 'xlsx', $url_to . '/asset/xlsx.full.min.js' );
	}

	public function _cb_enqueue_script_media() {
		global $pagenow;
		if ( $pagenow !== 'post.php' && $pagenow !== 'post-new.php' ) return;
		if ( ! \st\page_template_admin\is_post_type( self::PT ) ) return;

		$post_id = \st\page_template_admin\get_post_id();
		wp_enqueue_media( [ 'post' => $post_id ] );
	}

	public function _cb_output_html_list() {
		wp_nonce_field( 'bimeson_list', 'bimeson_list_nonce' );
		\st\media_picker\output_html( self::FLD_MEDIA, false );
?>
		<div>
			<div class="bimeson_list_edit_row">
				<span class="bimeson_list_loading_spin"><span></span></span>
				<label><input type="checkbox" name="<?php echo self::FLD_ADD_TAX ?>" value="true"><?php echo self::LBL_ADD_TAX ?></label>
				<label><input type="checkbox" name="<?php echo self::FLD_ADD_TERM ?>" value="true"><?php echo self::LBL_ADD_TERM ?></label>
				<a href="javascript:void(0);" class="bimeson_list_filter_button button button-primary button-large"><?php echo self::LBL_UPDATE ?></a>
			</div>
			<input type="hidden" id="<?php echo self::FLD_ITEMS ?>" name="<?php echo self::FLD_ITEMS ?>" value="<?php echo esc_attr( self::NOT_MODIFIED ) ?>" />
		</div>
<?php
	}

	public function _cb_save_post( $post_id ) {
		if ( ! isset( $_POST['bimeson_list_nonce'] ) || ! wp_verify_nonce( $_POST['bimeson_list_nonce'], 'bimeson_list' ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		\st\media_picker\save_post( $post_id, self::FLD_MEDIA );

		$json_items = $_POST[ self::FLD_ITEMS ];
		if ( $json_items !== self::NOT_MODIFIED ) {
			$items = json_decode( stripslashes( $json_items ), true );

			$add_tax  = isset( $_POST[ self::FLD_ADD_TAX  ] ) && ( $_POST[ self::FLD_ADD_TAX  ] === 'true' );
			$add_term = isset( $_POST[ self::FLD_ADD_TERM ] ) && ( $_POST[ self::FLD_ADD_TERM ] === 'true' );
			if ( $add_tax || $add_term ) $this->_process_terms( $items, $add_tax, $add_term );

			$this->_process_items( $items );
			$json_items = json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			update_post_meta( $post_id, self::FLD_ITEMS, addslashes( $json_items ) );  // Because the meta value is passed through the stripslashes() function upon being stored.
		}
	}


	// -------------------------------------------------------------------------

	private function _process_terms( $items, $add_taxonomies = false, $add_terms = false ) {
		$roots_subs = $this->_tax->get_root_slugs_to_sub_slugs();
		$new_tax_term = [];
		$new_term = [];

		foreach ( $items as $item ) {
			foreach ( $item as $key => $vals ) {
				if ( $key[0] === '_' ) continue;
				if ( ! is_array( $vals ) ) $vals = [ $vals ];
				if ( ! isset( $roots_subs[ $key ] ) ) {
					if ( ! isset( $new_tax_term[ $key ] ) ) $new_tax_term[ $key ] = [];
					foreach ( $vals as $v ) {
						if ( ! in_array( $v, $new_tax_term[ $key ], true ) ) $new_tax_term[ $key ][] = $v;
					}
				} else {
					$slugs = $roots_subs[ $key ];
					if ( ! isset( $new_term[ $key ] ) ) $new_term[ $key ] = [];
					foreach ( $vals as $v ) {
						if ( ! in_array( $v, $slugs, true ) && ! in_array( $v, $new_term[ $key ], true ) ) $new_term[ $key ][] = $v;
					}
				}
			}
		}
		if ( $add_taxonomies ) {
			foreach ( $new_tax_term as $slug => $terms ) {
				wp_insert_term( $slug, $this->_tax->get_taxonomy(), [ 'slug' => $slug ] );

				$sub_tax = $this->_tax->term_to_taxonomy( $slug );
				$this->_tax->register_sub_tax( $sub_tax, $sub_tax );

				if ( $add_terms ) {
					foreach ( $terms as $t ) {
						$ret = wp_insert_term( $t, $sub_tax, [ 'slug' => $t ] );
						if ( is_array( $ret ) ) {
							if ( ! isset( $roots_subs[ $slug ] ) ) $roots_subs[ $slug ] = [];
							$roots_subs[ $slug ][] = $t;
						}
					}
				}
			}
		}
		if ( $add_terms ) {
			foreach ( $new_term as $slug => $terms ) {
				$sub_tax = $this->_tax->term_to_taxonomy( $slug );

				if ( taxonomy_exists( $sub_tax ) ) {
					foreach ( $terms as $t ) {
						$ret = wp_insert_term( $t, $sub_tax, [ 'slug' => $t ] );
						if ( is_array( $ret ) ) {
							if ( ! isset( $roots_subs[ $slug ] ) ) $roots_subs[ $slug ] = [];
							$roots_subs[ $slug ][] = $t;
						}
					}
				}
			}
		}
	}

	private function _process_items( &$items ) {
		foreach ( $items as &$item ) {
			$date = ( ! empty( $item[ Bimeson::FLD_DATE ] ) ) ? \st\field\normalize_date( $item[ Bimeson::FLD_DATE ] ) : '';
			if ( $date ) {
				$date_num = str_pad( str_replace( '-', '', $date ), 8, '9', STR_PAD_RIGHT );
				$item[ Bimeson::FLD_DATE_NUM ] = $date_num;
			}
			unset( $item[ Bimeson::FLD_DATE ] );
		}
	}

}
