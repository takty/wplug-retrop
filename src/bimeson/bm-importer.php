<?php
namespace st;

/**
 *
 * Bimeson Importer
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-03-05
 *
 */


if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) return;

require_once ABSPATH . 'wp-admin/includes/import.php';
if ( ! class_exists( '\WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) require $class_wp_importer;
}
if ( ! class_exists( '\WP_Importer' ) ) return;


class Bimeson_Importer extends \WP_Importer {

	static public function register( $tax, $args ) {
		$GLOBALS['bimeson_import'] = new Bimeson_Importer( $tax, $args );
		register_importer(
			'bimeson', 'Bimeson',
			__('Import <strong>publications</strong> from a Excel file.', 'bimeson-importer'),
			[ $GLOBALS['bimeson_import'], 'dispatch' ]
		);
	}

	private $_tax;
	private $_additional_langs;
	private $_id;
	private $_file_name;
	private $_add_taxonomies = false;
	private $_add_terms = false;
	private $_items = [];

	public function __construct( $tax, $args ) {
		$this->_tax = $tax;
		$this->_additional_langs = isset( $args['additional_langs'] ) ? $args['additional_langs'] : [];
		if ( ! is_array( $this->_additional_langs ) ) $this->_additional_langs = [ $this->_additional_langs ];
	}

	public function dispatch() {
		wp_enqueue_script( 'bimeson', get_template_directory_uri() . '/lib/stinc/bimeson2/asset/bm-loader.min.js' );
		wp_enqueue_script( 'xlsx', get_template_directory_uri() . '/lib/stinc/bimeson2/asset/xlsx.full.min.js' );

		$this->_header();

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
		switch ( $step ) {
			case 0:
				$this->_greet();
				break;
			case 1:
				check_admin_referer( 'import-upload' );
				if ( $this->_handle_upload() ) $this->_parse_upload();
				break;
			case 2:
				check_admin_referer( 'import-bimeson' );
				$this->_id             = (int) $_POST['import_id'];
				$this->_file_name      = pathinfo( get_attached_file( $this->_id ), PATHINFO_FILENAME );
				$this->_add_taxonomies = ( ! empty( $_POST['add_terms'] ) && $_POST['add_terms'] === 'taxonomy' );
				$this->_add_terms      = ( ! empty( $_POST['add_terms'] ) && $_POST['add_terms'] === 'term' );
				set_time_limit(0);
				$this->_import( stripslashes( $_POST['bimeson_items'] ) );
				break;
		}

		$this->_footer();
	}

	private function _header() {
		echo '<div class="wrap">';
		echo '<h2>' . __( 'Import Bimeson', 'bimeson-importer' ) . '</h2>';
	}

	private function _footer() {
		echo '</div>';
	}


	// Step 0 ------------------------------------------------------------------

	private function _greet() {
		echo '<div class="narrow">';
		echo '<p>'.__( 'Howdy! Upload your Bimeson Excel (xlsx) file and we&#8217;ll import the publications into this site.', 'bimeson-importer' ).'</p>';
		echo '<p>'.__( 'Choose a Excel (.xlsx) file to upload, then click Upload file and import.', 'bimeson-importer' ).'</p>';
		wp_import_upload_form( 'admin.php?import=bimeson&amp;step=1' );
		echo '</div>';
	}


	// Step 1 ------------------------------------------------------------------

	private function _handle_upload() {
		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'bimeson-importer' ) . '</strong><br />';
			echo esc_html( $file['error'] ) . '</p>';
			return false;
		} else if ( ! file_exists( $file['file'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'bimeson-importer' ) . '</strong><br />';
			printf( __( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', 'bimeson-importer' ), esc_html( $file['file'] ) );
			echo '</p>';
			return false;
		}
		$this->_id = (int) $file['id'];
		return true;
	}

	private function _parse_upload() {
		$url = wp_get_attachment_url( $this->_id );
?>
<form action="<?php echo admin_url( 'admin.php?import=bimeson&amp;step=2' ); ?>" method="post" name="form">
	<?php wp_nonce_field( 'import-bimeson' ); ?>
	<input type="hidden" name="import_id" value="<?php echo $this->_id; ?>" />
	<input type="hidden" name="bimeson_items" id="bimeson-items" value="" />
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			BIMESON.loadFiles(['<?php echo $url ?>'], '#bimeson-items', function (successAll) {
				if (successAll) document.form.submit.disabled = false;
				else document.getElementById('error').style.display = 'block';
			});
		});
	</script>

	<h3><?php _e( 'Add Terms', 'bimeson-importer' ); ?></h3>
	<p>
		<input type="radio" value="taxonomy" name="add_terms" id="add-taxonomies-terms" />
		<label for="add-taxonomies-terms"><?php _e( 'Add taxonomies (categories) and terms that import file contains', 'bimeson-importer' ); ?></label>
	</p>
	<p>
		<input type="radio" value="term" name="add_terms" id="add-terms" />
		<label for="add-terms"><?php _e( 'Add terms that import file contains', 'bimeson-importer' ); ?></label>
	</p>

	<p class="submit"><input type="submit" name="submit" disabled class="button" value="<?php esc_attr_e( 'Submit', 'bimeson-importer' ); ?>" /></p>
</form>
<?php
		echo '<p id="error" style="display: none;"><strong>' . __( 'Sorry, there has been an error.', 'bimeson-importer' ) . '</strong><br />';
	}


	// Step 2 ------------------------------------------------------------------

	private function _import( $json ) {
		add_filter( 'http_request_timeout', function ( $val ) { return 60; } );

		$this->_import_start( $json );
		wp_suspend_cache_invalidation( true );
		$roots_subs = $this->_tax->get_root_slugs_to_sub_slugs();
		$this->_process_terms( $roots_subs );
		$this->_process_items( $roots_subs );
		wp_suspend_cache_invalidation( false );
		$this->_import_end();
	}

	private function _import_start( $json ) {
		$data = json_decode( $json, true );
		if ( $data === null ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'bimeson-importer' ) . '</strong><br />';
			echo __( 'The file is wrong, please try again.', 'bimeson-importer' ) . '</p>';
			$this->_footer();
			die();
		}
		$this->_items = $data;

		do_action( 'import_start' );
	}

	private function _import_end() {
		wp_import_cleanup( $this->_id );
		wp_cache_flush();

		echo '<p>' . __( 'All done.', 'bimeson-importer' ) . ' <a href="' . admin_url() . '">' . __( 'Have fun!', 'bimeson-importer' ) . '</a>' . '</p>';

		do_action( 'import_end' );
	}

	private function _process_terms( &$roots_subs ) {
		$new_tax_term = [];
		$new_term = [];

		foreach ( $this->_items as $item ) {
			foreach ( $item as $key => $vals ) {
				if ( ! is_array( $vals ) ) $vals = [ $vals ];
				if ( $key[0] === '_' ) continue;
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
		if ( $this->_add_taxonomies ) {
			foreach ( $new_tax_term as $slug => $terms ) {
				wp_insert_term( $slug, $this->_tax->get_taxonomy(), [ 'slug' => $slug ] );

				$sub_tax = $this->_tax->term_to_taxonomy( $slug );
				$this->_tax->register_sub_tax( $sub_tax, $sub_tax );

				foreach ( $terms as $t ) {
					$ret = wp_insert_term( $t, $sub_tax, [ 'slug' => $t ] );
					if ( is_array( $ret ) ) {
						if ( ! isset( $roots_subs[ $slug ] ) ) $roots_subs[ $slug ] = [];
						$roots_subs[ $slug ][] = $t;
					}
				}
			}
		}
		if ( $this->_add_terms ) {
			foreach ( $new_term as $slug => $terms ) {
				$sub_tax = $this->_tax->term_to_taxonomy( $slug );

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

	private function _process_items( $roots_subs ) {
		foreach ( $this->_items as $item ) {
			$body = ( ! empty( $item[Bimeson::FLD_BODY] ) ) ? trim( $item[Bimeson::FLD_BODY] ) : '';
			if ( empty( $body ) ) continue;

			$date       = ( ! empty( $item[Bimeson::FLD_DATE] ) )       ? \st\field\normalize_date( $item[Bimeson::FLD_DATE] ) : '';
			$date_num   = str_pad( str_replace( '-', '', $date ), 8, '9', STR_PAD_RIGHT );
			$doi        = ( ! empty( $item[Bimeson::FLD_DOI] ) )        ? $item[Bimeson::FLD_DOI] : '';
			$link_url   = ( ! empty( $item[Bimeson::FLD_LINK_URL] ) )   ? $item[Bimeson::FLD_LINK_URL] : '';
			$link_title = ( ! empty( $item[Bimeson::FLD_LINK_TITLE] ) ) ? $item[Bimeson::FLD_LINK_TITLE] : '';

			$a_bodeis = [];
			foreach ( $this->_additional_langs as $al ) {
				$b = ( ! empty( $item[ Bimeson::FLD_BODY . "_$al" ] ) ) ? $item[ Bimeson::FLD_BODY . "_$al" ] : '';
				if ( ! empty( $b ) ) $a_bodeis[ $al ] = $b;
			}

			$digest = $this->make_digest( $body );
			$title = $this->make_title( $body, $date );

			$olds = get_posts( [
				'post_type' => 'bimeson',
				'meta_query' => [ [
					'key'   => Bimeson::FLD_DIGEST,
					'value' => $digest,
				] ],
			] );
			$old = false;
			if ( ! empty( $olds ) ) $old = $olds[0];
			$args = [
				'post_content' => $body,
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_type'    => 'bimeson',
			];
			if ( $old !== false ) $args['ID'] = $old->ID;
			$post_id = wp_insert_post( $args );
			if ( $post_id === 0 ) continue;

			add_post_meta( $post_id, Bimeson::FLD_DATE,        $date );
			add_post_meta( $post_id, Bimeson::FLD_DOI,         $doi );
			add_post_meta( $post_id, Bimeson::FLD_LINK_URL,    $link_url );
			add_post_meta( $post_id, Bimeson::FLD_LINK_TITLE,  $link_title );

			add_post_meta( $post_id, Bimeson::FLD_DATE_NUM,    $date_num );
			add_post_meta( $post_id, Bimeson::FLD_IMPORT_FROM, $this->_file_name );
			add_post_meta( $post_id, Bimeson::FLD_DIGEST,      $digest );

			foreach ( $a_bodeis as $l => $cont ) {
				add_post_meta( $post_id, "_post_content_$l", wp_kses_post( $cont ) );
			}
			foreach ( $item as $key => $vals ) {
				if ( $key[0] === '_' ) continue;
				if ( ! isset( $roots_subs[ $key ] ) ) continue;
				if ( ! is_array( $vals ) ) $vals = [ $vals ];
				$sub_tax = $this->_tax->term_to_taxonomy( $key );
				$slugs = [];
				foreach ( $vals as $v ) {
					if ( in_array( $v, $roots_subs[ $key ], true ) ) $slugs[] = $v;
				}
				if ( ! empty( $slugs ) ) wp_add_object_terms( $post_id, $slugs, $sub_tax );
			}
			echo '<p>' . ( $old === false ? 'New ' : 'Updated ' );
			echo wp_kses_post( $body ) . '</p>';
		}
	}

	private function make_title( $body, $date ) {
		$body = $this->normalize_body( $body );
		$words = explode( ' ', $body );
		$first_word = empty( $words ) ? '' : $words[0];
		$dp = explode( '-', $date );
		$year = ( 0 < count( $dp ) ) ? $dp[0] : '';
		return $first_word . $year;
	}

	private function make_digest( $body ) {
		$body = $this->normalize_body( $body );
		$body = str_replace( ' ', '', $body );
		return hash( 'sha224', $body );
	}

	private function normalize_body( $body ) {
		$body = strip_tags( trim( $body ) );
		$body = mb_convert_kana( $body, 'rnasKV' );
		$body = mb_strtolower( $body );
		$body = preg_replace( '/[\s!-\/:-@[-`{-~]|[、。，．・：；？！´｀¨＾￣＿―‐／＼～∥｜…‥‘’“”（）〔〕［］｛｝〈〉《》「」『』【】＊※]/u', ' ', $body );
		$body = preg_replace( '/\s(?=\s)/', '', $body );
		$body = trim( $body );
		return $body;
	}

}
