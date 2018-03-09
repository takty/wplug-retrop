<?php
namespace st;

/**
 *
 * Bimeson (Admin)
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-03-08
 *
 */


require_once __DIR__ . '/../../stinc/admin/media-picker.php';


class Bimeson_Admin {

	const NS           = 'bimeson_list';
	const NOT_MODIFIED = '<NOT MODIFIED>';

	const KEY_JSON_PARAMS       = '_bimeson_json_params';
	const KEY_LIST_ID           = '_bimeson_list_id';
	const KEY_FIRST_KEY_OMITTED = '_bimeson_pub_first_key_omitted';
	const KEY_ITEMS             = '_bimeson_pub_items';
	const KEY_KEY_ORDER         = '_bimeson_pub_key_order';
	const KEY_KEY_ANCESTOR      = '_bimeson_pub_key_ancestor';

	const FLD_MEDIA = '_bimeson_media';

	private $_core;
	private $_tax;
	private $_key;

	public function __construct( $core, $tax, $key ) {
		$this->_core = $core;
		$this->_tax  = $tax;
		$this->_key  = $key;
	}

	public function enqueue_script( $url_to = false ) {
		if ( $url_to === false ) $url_to = \st\get_file_uri( __DIR__ );
		$url_to = untrailingslashit( $url_to );

		$post_id = \st\page_template_admin\get_post_id();
		$post_type = \st\page_template_admin\get_post_type( $post_id );
		if ( $post_type === Bimeson_List::PT ) {
		} else {
			wp_enqueue_style(  self::NS . '_filter_admin', $url_to . '/asset/pub-filter-admin.min.css' );
			wp_enqueue_script( self::NS . '_filter_admin', $url_to . '/asset/pub-filter-admin.min.js' );
		}
	}



	// -------------------------------------------------------------------------





















	// -----------------------------------------------------------------------------

	public function add_meta_box( $label, $screen ) {
		\add_meta_box( "{$this->_key}_mb", $label, [ $this, '_cb_output_html' ], $screen );
	}

	public function save_mata_box( $post_id ) {
		if ( ! isset( $_POST["{$this->_key}_nonce"] ) ) return;
		if ( ! wp_verify_nonce( $_POST["{$this->_key}_nonce"], $this->_key ) ) return;

		$state = $this->_tax->get_filter_state_from_post();
		update_post_meta( $post_id, self::KEY_JSON_PARAMS, json_encode( $state ) );
		$omit = empty( $_POST[ Bimeson::KEY_OMIT_FIRST ] ) ? 0 : 1;
		update_post_meta( $post_id, Bimeson::KEY_OMIT_FIRST, $omit );

		$list_id = (int) $_POST[ self::KEY_LIST_ID ];
	}

	public function _cb_output_html( $post ) {  // Private
		wp_nonce_field( $this->_key, "{$this->_key}_nonce" );

		$bls = get_posts( [
			'post_type' => Bimeson_List::PT,
			'posts_per_page' => -1,
		] );

		$list_id = get_post_meta( $post->ID, self::KEY_LIST_ID, true );
		$omit = get_post_meta( $post->ID, Bimeson::KEY_OMIT_FIRST, true );
?>
		<div id="<?php echo $this->_key ?>_body">
			<div class="<?php echo self::NS ?>_filter_row">
		<select id="<?php echo self::KEY_LIST_ID ?>" name="<?php echo self::KEY_LIST_ID ?>">
<?php
		foreach ( $bls as $bl ) {
			$_title = esc_html( $bl->post_title );
			$_id = $bl->ID;
			echo "<option value=\"$_id\" " . selected( $list_id, $bl->ID, false ) . ">$_title</option>";
		}
?>
		</select>
				<label for="<?php echo self::KEY_FIRST_KEY_OMITTED ?>"><input type="checkbox" name="<?php echo self::KEY_FIRST_KEY_OMITTED ?>" id="<?php echo self::KEY_FIRST_KEY_OMITTED ?>" <?php checked( $omit, 1 ) ?>/>一番最初のキーを省略</label>
			</div>
			<div class="<?php echo self::NS ?>_filter_row">
				<?php $this->_core->the_filter( false ); ?>
			</div>
		</div>
	<?php
	}

}
