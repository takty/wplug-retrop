<?php
/**
 *
 * Admin for the Template for Publications Static Pages
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-03-06
 *
 */


function setup_page_template_admin() {
	\st\Bimeson::get_instance()->enqueue_script();
	\st\Bimeson::get_instance()->add_meta_box( '業績リスト', 'page' );

	add_action( 'save_post_page', function ( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		\st\Bimeson::get_instance()->save_mata_box( $post_id );
	} );
}
