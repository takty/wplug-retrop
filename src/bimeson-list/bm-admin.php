<?php
namespace st;

/**
 *
 * Bimeson (Admin)
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-03-06
 *
 */


require_once __DIR__ . '/../../stinc/admin/media-picker.php';


class Bimeson_Admin {

	const NS           = 'bimeson_admin';
	const NOT_MODIFIED = '<NOT MODIFIED>';

	const KEY_JSON_PARAMS       = '_bimeson_json_params';
	const KEY_LANG              = '_bimeson_pub_lang';
	const KEY_FIRST_KEY_OMITTED = '_bimeson_pub_first_key_omitted';
	const KEY_ITEMS             = '_bimeson_pub_items';
	const KEY_KEY_ORDER         = '_bimeson_pub_key_order';
	const KEY_KEY_ANCESTOR      = '_bimeson_pub_key_ancestor';

	private $_core;
	private $_key;

	public function __construct( $core,  $key ) {
		$this->_core = $core;
		$this->_key  = $key;
	}

	public function enqueue_script( $url_to ) {
		if ( is_admin() ) {
			\st\media_picker\enqueue_script_for_admin( $url_to . '/../admin/' );
			wp_enqueue_style(  self::NS, $url_to . '/asset/pub-loader.css' );
			wp_enqueue_script( self::NS, $url_to . '/asset/pub-loader.js' );
			wp_enqueue_script( 'xlsx', $url_to . '/asset/xlsx.full.min.js' );
		}
	}

	public function add_meta_box( $label, $screen ) {
		\add_meta_box( "{$this->_key}_mb", $label, [ $this, '_cb_output_html' ], $screen );
	}

	public function save_mata_box( $post_id ) {
		if ( ! isset( $_POST["{$this->_key}_nonce"] ) ) return;
		if ( ! wp_verify_nonce( $_POST["{$this->_key}_nonce"], $this->_key ) ) return;
		\st\media_picker\save_post( $post_id, "{$this->_key}_pubfile_multi" );
		$this->_save_post( $post_id );
	}


	// -----------------------------------------------------------------------------

	public function _cb_output_html( $post ) {  // Private
		wp_nonce_field( $this->_key, "{$this->_key}_nonce" );
		\st\media_picker\output_html( $this->_key . '_pubfile_multi', false );

		$lang = get_post_meta( $post->ID, self::KEY_LANG, true );
		$omit = get_post_meta( $post->ID, Bimeson::KEY_OMIT_FIRST, true );

		$_json_params           = esc_attr( get_post_meta( $post->ID, self::KEY_JSON_PARAMS, true ) );
		$_json_pub_key_ancestor = esc_attr( json_encode( $this->_core->get_pub_key_ancestor() ) );
		$_json_pub_key_order    = esc_attr( json_encode( $this->_core->get_pub_key_order() ) );
	?>
		<div id="<?php echo $this->_key ?>_body">
			<div class="<?php echo self::NS ?>_filter_row">
				<?php $this->_core->the_filter( $omit ); ?>
			</div>
			<div class="<?php echo self::NS ?>_filter_row">
				<select id="<?php echo self::KEY_LANG ?>" name="<?php echo self::KEY_LANG ?>">
					<option value="ja" <?php selected( $lang, 'ja' ); ?>>日本語用</option>
					<option value="en" <?php selected( $lang, 'en' ); ?>>英語用</option>
				</select>
				<label for="<?php echo self::KEY_FIRST_KEY_OMITTED ?>"><input type="checkbox" name="<?php echo self::KEY_FIRST_KEY_OMITTED ?>" id="<?php echo self::KEY_FIRST_KEY_OMITTED ?>" <?php checked( $omit, 1 ) ?>/>一番最初のキーを省略</label>
			</div>
			<div class="<?php echo self::NS ?>_edit_row">
				<span class="<?php echo self::NS ?>_filter_loading"><span></span></span><a href="javascript:void(0);" class="<?php echo self::NS ?>_filter button">絞り込んで登録</a>
			</div>
			<input type="hidden" id="<?php echo self::KEY_KEY_ANCESTOR ?>" name="<?php echo self::KEY_KEY_ANCESTOR ?>" value="<?php echo $_json_pub_key_ancestor ?>" />
			<input type="hidden" id="<?php echo self::KEY_KEY_ORDER ?>" name="<?php echo self::KEY_KEY_ORDER ?>" value="<?php echo $_json_pub_key_order ?>" />
			<input type="hidden" id="<?php echo self::KEY_JSON_PARAMS ?>" name="<?php echo self::KEY_JSON_PARAMS ?>" value="<?php echo $_json_params ?>" />
			<input type="hidden" id="<?php echo self::KEY_ITEMS ?>" name="<?php echo self::KEY_ITEMS ?>" value="<?php echo esc_attr( self::NOT_MODIFIED ) ?>" />
		</div>
	<?php
	}

	private function _save_post( $post_id ) {
		$items = $_POST[self::KEY_ITEMS];
		if ( $items !== self::NOT_MODIFIED ) {
			update_post_meta( $post_id, self::KEY_ITEMS, $items );
		}
		update_post_meta( $post_id, self::KEY_JSON_PARAMS, $_POST[self::KEY_JSON_PARAMS] );
		update_post_meta( $post_id, self::KEY_LANG,    $_POST[self::KEY_LANG] );
		$omit = empty( $_POST[Bimeson::KEY_OMIT_FIRST] ) ? 0 : 1;
		update_post_meta( $post_id, Bimeson::KEY_OMIT_FIRST, $omit );
	}

}
