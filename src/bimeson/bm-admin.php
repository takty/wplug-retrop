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


require_once __DIR__ . '/bm-importer.php';


class Bimeson_Admin {

	private $_additional_langs;
	private $_tax = null;

	public function __construct( $additional_langs, $bm_tax ) {
		$this->_additional_langs = $additional_langs;
		$this->_tax = $bm_tax;

		add_action( 'wp_loaded',                        [ $this, '_cb_wp_loaded' ] );
		add_action( 'admin_menu',                       [ $this, '_cb_admin_menu' ] );
		add_action( 'save_post_' . Bimeson::PT_BIMESON, [ $this, '_cb_save_post' ] );
		if ( class_exists( '\st\Bimeson_Importer' ) ) {
			\st\Bimeson_Importer::register( $this->_tax, [ 'additional_langs' => $this->_additional_langs ] );
		}
	}

	public function _cb_wp_loaded() {
		$cs = [ 'cb', 'title' ];
		$cs[] = [ 'label' => '公開日付', 'name' => Bimeson::FLD_DATE, 'width' => '10%', 'value' => 'esc_html' ];
		foreach ( $this->_tax->get_root_slugs() as $taxonomy ) {
			$cs[] = [ 'name' => $this->_tax->term_to_taxonomy( $taxonomy ), 'width' => '14%' ];
		}
		$cs[] = [ 'name' => 'post_lang', 'width' => '10%' ];
		$cs[] = 'date';
		$cs[] = [ 'label' => 'インポート元', 'name' => Bimeson::FLD_IMPORT_FROM, 'width' => '10%', 'value' => 'esc_html' ];
		\st\field\set_admin_columns( 'bimeson', $cs, [ Bimeson::FLD_DATE, Bimeson::FLD_IMPORT_FROM ] );
	}

	public function _cb_admin_menu() {
		if ( \st\page_template_admin\is_post_type( Bimeson::PT_BIMESON ) ) {
			foreach ( $this->_additional_langs as $al ) {
				\st\field\add_rich_editor_meta_box( "_post_content_$al", "本文 [$al]", 'bimeson' );
			}
			add_meta_box( 'bimeson_mb', '業績情報', [ $this, '_cb_output_html' ], 'bimeson', 'side', 'high' );
		}
	}

	public function _cb_output_html() {
		wp_nonce_field( 'bimeson_data', 'bimeson_data_nonce' );
		$post_id = get_the_ID();

		$date       = get_post_meta( $post_id, Bimeson::FLD_DATE,       true );
		$doi        = get_post_meta( $post_id, Bimeson::FLD_DOI,        true );
		$link_url   = get_post_meta( $post_id, Bimeson::FLD_LINK_URL,   true );
		$link_title = get_post_meta( $post_id, Bimeson::FLD_LINK_TITLE, true );

		\st\field\output_input_row( '公開日付', Bimeson::FLD_DATE, $date );
		\st\field\output_input_row( 'DOI', Bimeson::FLD_DOI, $doi );
		\st\field\output_input_row( 'リンクURL', Bimeson::FLD_LINK_URL, $link_url );
		\st\field\output_input_row( 'リンク表題', Bimeson::FLD_LINK_TITLE, $link_title );
	}

	public function _cb_save_post( $post_id ) {
		if ( ! isset( $_POST['bimeson_data_nonce'] ) || ! wp_verify_nonce( $_POST['bimeson_data_nonce'], 'bimeson_data' ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		foreach ( $this->_additional_langs as $al ) {
			\st\field\save_rich_editor_meta_box( $post_id, "_post_content_$al" );
		}
		$date = get_post_meta( $post_id, Bimeson::FLD_DATE, true );
		$date_num = str_pad( str_replace( '-', '', \st\field\normalize_date( $date ) ), 8, '9', STR_PAD_RIGHT );
		update_post_meta( $post_id, Bimeson::FLD_DATE_NUM, $date_num );

		\st\field\save_post_meta( $post_id, Bimeson::FLD_DATE, '\\st\\field\\normalize_date' );
		\st\field\save_post_meta( $post_id, Bimeson::FLD_DOI );
		\st\field\save_post_meta( $post_id, Bimeson::FLD_LINK_URL );
		\st\field\save_post_meta( $post_id, Bimeson::FLD_LINK_TITLE );
	}

}
