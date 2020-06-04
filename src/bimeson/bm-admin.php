<?php
namespace st;

/**
 *
 * Bimeson (Admin)
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2020-06-04
 *
 */


require_once __DIR__ . '/bm-importer.php';


class Bimeson_Admin {

	private $_tax;
	private $_additional_langs;
	private $_post_lang_tax;
	private $_default_post_lang_slug;
	private $_is_current_post_content_empty;

	public function __construct( $bm_tax, $additional_langs, $post_lang_tax = false, $default_post_lang_slug = false ) {
		$this->_tax                    = $bm_tax;
		$this->_additional_langs       = $additional_langs;
		$this->_post_lang_tax          = $post_lang_tax;
		$this->_default_post_lang_slug = $default_post_lang_slug;

		add_action( 'wp_loaded',                        [ $this, '_cb_wp_loaded' ] );
		add_action( 'admin_menu',                       [ $this, '_cb_admin_menu' ] );
		add_filter( 'the_content',                      [ $this, '_cb_the_content' ] );
		add_filter( 'wp_insert_post_data',              [ $this, '_cb_wp_insert_post_data' ], 10, 2 );
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
		$cs[] = 'date';
		$cs[] = [ 'label' => 'インポート元', 'name' => Bimeson::FLD_IMPORT_FROM, 'width' => '10%', 'value' => 'esc_html' ];
		\st\field\set_admin_columns( 'bimeson', $cs, [ Bimeson::FLD_DATE, Bimeson::FLD_IMPORT_FROM ] );
	}

	public function _cb_admin_menu() {
		if ( \st\is_post_type( Bimeson::PT_BIMESON ) ) {
			foreach ( $this->_additional_langs as $al ) {
				\st\field\add_rich_editor_meta_box( "_post_content_$al", "本文 [$al]", 'bimeson' );
			}
			add_meta_box( 'bimeson_mb', '業績情報', [ $this, '_cb_output_html' ], 'bimeson', 'side', 'high' );
		}
	}

	public function _cb_the_content( $content ) {
		$post = get_post();
		if ( $post->post_type === Bimeson::PT_BIMESON ) {
			if ( self::_is_empty( $content ) ) {
				foreach ( $this->_additional_langs as $al ) {
					$key = "_post_content_$al";
					$c = get_post_meta( $post->ID, $key, true );
					if ( ! self::_is_empty( $c ) ) return $c;
				}
			}
		}
		return $content;
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

	public function _cb_wp_insert_post_data( $data, $postarr ) {
		if ( $postarr['post_type'] === Bimeson::PT_BIMESON ) {
			$this->_is_current_post_content_empty = self::_is_empty( $data['post_content'] );
			if ( $this->_is_current_post_content_empty ) $data['post_content'] = '';
		}
		return $data;
	}

	public function _cb_save_post( $post_id ) {
		if ( ! isset( $_POST['bimeson_data_nonce'] ) || ! wp_verify_nonce( $_POST['bimeson_data_nonce'], 'bimeson_data' ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		foreach ( $this->_additional_langs as $al ) {
			\st\field\save_rich_editor_meta_box( $post_id, "_post_content_$al" );
		}
		$date = isset( $_POST[ Bimeson::FLD_DATE ] ) ? $_POST[ Bimeson::FLD_DATE ] : '';
		$date_num = str_pad( str_replace( '-', '', \st\field\normalize_date( $date ) ), 8, '9', STR_PAD_RIGHT );
		update_post_meta( $post_id, Bimeson::FLD_DATE_NUM, $date_num );

		\st\field\save_post_meta( $post_id, Bimeson::FLD_DATE, '\\st\\field\\normalize_date' );
		\st\field\save_post_meta( $post_id, Bimeson::FLD_DOI );
		\st\field\save_post_meta( $post_id, Bimeson::FLD_LINK_URL );
		\st\field\save_post_meta( $post_id, Bimeson::FLD_LINK_TITLE );

		if ( $this->_post_lang_tax !== false ) $this->_set_post_lang_terms( $post_id );
	}

	private function _set_post_lang_terms( $post_id ) {
		$post_langs = [];
		if ( ! $this->_is_current_post_content_empty ) {
			$post_langs[] = $this->_default_post_lang_slug;
		}
		foreach ( $this->_additional_langs as $al ) {
			$key = "_post_content_$al";
			$is_empty = isset( $_POST[ $key ] ) ? self::_is_empty( $_POST[ $key ] ) : true;
			if ( ! $is_empty ) $post_langs[] = $al;
		}
		wp_set_object_terms( $post_id, $post_langs, $this->_post_lang_tax );
	}

	private static function _is_empty( $content ) {
		return empty( trim( wp_strip_all_tags( $content, true ) ) );
	}

}
