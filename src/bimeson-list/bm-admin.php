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


class Bimeson_Admin {

	const NS = 'bimeson_admin';

	const FLD_JSON_PARAMS = '_bimeson_json_params';
	const FLD_LIST_ID     = '_bimeson_list_id';

	const FLD_COUNT                   = '_bimeson_count';
	const FLD_SORT_BY_DATE_FIRST      = '_bimeson_sort_by_date_first';
	const FLD_SHOW_FILTER             = '_bimeson_show_filter';
	const FLD_OMIT_HEAD_OF_SINGLE_CAT = '_bimeson_omit_head_of_single_cat';
	const FLD_YEAR_START              = '_bimeson_year_start';
	const FLD_YEAR_END                = '_bimeson_year_end';

	const LBL_COUNT                   = '表示件数指定（見出無し）';
	const LBL_SORT_BY_DATE_FIRST      = '最初に年で並び替える';
	const LBL_SHOW_FILTER             = 'フィルターを表示';
	const LBL_OMIT_HEAD_OF_SINGLE_CAT = '1つしかない分類の見出しを省略';
	const LBL_YEAR                    = '表示期間（年）';

	private $_core;
	private $_tax;

	public function __construct( $core, $tax ) {
		$this->_core = $core;
		$this->_tax  = $tax;
	}

	public function enqueue_script( $url_to = false ) {
		if ( $url_to === false ) $url_to = \st\get_file_uri( __DIR__ );
		$url_to = untrailingslashit( $url_to );

		$post_id = \st\page_template_admin\get_post_id();
		$post_type = \st\page_template_admin\get_post_type( $post_id );
		if ( $post_type === Bimeson_List::PT ) {
		} else {
			wp_enqueue_style(  self::NS . '_filter_admin', $url_to . '/asset/bm-admin.min.css' );
			wp_enqueue_script( self::NS . '_filter_admin', $url_to . '/asset/bm-admin.min.js' );
		}
	}


	// -----------------------------------------------------------------------------

	public function add_meta_box( $label, $screen ) {
		\add_meta_box( "bimeson_admin_mb", $label, [ $this, '_cb_output_html' ], $screen );
	}

	public function save_mata_box( $post_id ) {
		if ( ! isset( $_POST["bimeson_admin_nonce"] ) ) return;
		if ( ! wp_verify_nonce( $_POST["bimeson_admin_nonce"], 'bimeson_admin' ) ) return;

		$state = $this->_tax->get_filter_state_from_post();
		update_post_meta( $post_id, self::FLD_JSON_PARAMS, json_encode( $state ) );

		$group = empty( $_POST[ self::FLD_SORT_BY_DATE_FIRST ] ) ? 'false' : 'true';
		update_post_meta( $post_id, self::FLD_SORT_BY_DATE_FIRST, $group );
		$show_filter = empty( $_POST[ self::FLD_SHOW_FILTER ] ) ? 'false' : 'true';
		update_post_meta( $post_id, self::FLD_SHOW_FILTER, $show_filter );
		$omit_single_cat = empty( $_POST[ self::FLD_OMIT_HEAD_OF_SINGLE_CAT ] ) ? 'false' : 'true';
		update_post_meta( $post_id, self::FLD_OMIT_HEAD_OF_SINGLE_CAT, $omit_single_cat );

		\st\field\save_post_meta( $post_id, self::FLD_COUNT );
		\st\field\save_post_meta( $post_id, self::FLD_YEAR_START );
		\st\field\save_post_meta( $post_id, self::FLD_YEAR_END );

		\st\field\save_post_meta( $post_id, self::FLD_LIST_ID );
	}

	public function _cb_output_html( $post ) {
		wp_nonce_field( 'bimeson_admin', "bimeson_admin_nonce" );

		$list_id         = (int) get_post_meta( $post->ID, self::FLD_LIST_ID, true );
		$group           = get_post_meta( $post->ID, self::FLD_SORT_BY_DATE_FIRST, true );
		$show_filter     = get_post_meta( $post->ID, self::FLD_SHOW_FILTER, true );
		$omit_single_cat = get_post_meta( $post->ID, self::FLD_OMIT_HEAD_OF_SINGLE_CAT, true );

		$temp       = get_post_meta( $post->ID, self::FLD_COUNT, true );
		$count      = ( empty( $temp ) || (int) $temp < 1 ) ? '' : (int) $temp;
		$temp       = get_post_meta( $post->ID, self::FLD_YEAR_START, true );
		$year_start = ( empty( $temp ) || (int) $temp < 1970 || (int) $temp > 3000 ) ? '' : (int) $temp;
		$temp       = get_post_meta( $post->ID, self::FLD_YEAR_END, true );
		$year_end   = ( empty( $temp ) || (int) $temp < 1970 || (int) $temp > 3000 ) ? '' : (int) $temp;
?>
		<div class="<?php echo self::NS ?>">
			<div class="<?php echo self::NS ?>_setting_row">
				<?php $this->_echo_list_select( $list_id ); ?>
			</div>
			<div class="<?php echo self::NS ?>_setting_row">
				<label for="<?php echo self::FLD_COUNT ?>"><?php echo self::LBL_COUNT ?><input style="width:4rem;" type="number" size="4" name="<?php echo self::FLD_COUNT ?>" id="<?php echo self::FLD_COUNT ?>" value="<?php echo $count ?>" /></label>
				<label for="<?php echo self::FLD_SORT_BY_DATE_FIRST ?>"><input type="checkbox" name="<?php echo self::FLD_SORT_BY_DATE_FIRST ?>" id="<?php echo self::FLD_SORT_BY_DATE_FIRST ?>" value="true" <?php checked( $group, 'true' ) ?>/><?php echo self::LBL_SORT_BY_DATE_FIRST ?></label>
				<label for="<?php echo self::FLD_SHOW_FILTER ?>"><input type="checkbox" name="<?php echo self::FLD_SHOW_FILTER ?>" id="<?php echo self::FLD_SHOW_FILTER ?>" value="true" <?php checked( $show_filter, 'true' ) ?>/><?php echo self::LBL_SHOW_FILTER ?></label>
				<label for="<?php echo self::FLD_OMIT_HEAD_OF_SINGLE_CAT ?>"><input type="checkbox" name="<?php echo self::FLD_OMIT_HEAD_OF_SINGLE_CAT ?>" id="<?php echo self::FLD_OMIT_HEAD_OF_SINGLE_CAT ?>" value="true" <?php checked( $omit_single_cat, 'true' ) ?>/><?php echo self::LBL_OMIT_HEAD_OF_SINGLE_CAT ?></label>
			</div>
			<div class="<?php echo self::NS ?>_setting_row">
				<label for="<?php echo self::FLD_YEAR_START ?>"><?php echo self::LBL_YEAR ?><input style="width:5rem;" type="number" size="5" name="<?php echo self::FLD_YEAR_START ?>" value="<?php echo $year_start ?>" /></label><span>-</span>
				<input style="width:5rem;" type="number" size="5" name="<?php echo self::FLD_YEAR_END ?>" value="<?php echo $year_end ?>" />
			</div>
			<div class="<?php echo self::NS ?>_filter_row">
				<?php $this->_tax->the_filter(); ?>
			</div>
		</div>
	<?php
	}

	private function _echo_list_select( $cur_id ) {
		$bls = get_posts( [
			'post_type' => Bimeson_List::PT,
			'posts_per_page' => -1,
		] );
?>
		<select id="<?php echo self::FLD_LIST_ID ?>" name="<?php echo self::FLD_LIST_ID ?>">
<?php
		foreach ( $bls as $bl ) {
			$id = $bl->ID;
			$_title = esc_html( $bl->post_title );
			if ( empty( $_title ) ) $_title = '#' . esc_html( $id );
			echo "<option value=\"$id\" " . selected( $cur_id, $id, false ) . ">$_title</option>";
		}
?>
		</select>
<?php
	}

}
