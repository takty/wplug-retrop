<?php
namespace st;
use \st\retrop as R;
/**
 *
 * Retrop Importer: Versatile XLSX Importer
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2020-03-27
 *
 */


require_once ABSPATH . 'wp-admin/includes/import.php';
if ( ! class_exists( '\WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) require $class_wp_importer;
}
if ( ! class_exists( '\WP_Importer' ) ) return;

require_once __DIR__ . '/asset/util.php';
require_once __DIR__ . '/asset/registerer.php';
require_once __DIR__ . '/../../stinc/system/ajax.php';


class Retrop_Importer extends \WP_Importer {

	static private $_instance = [];

	static public function register( $id, $args = [] ) {
		self::$_instance[] = new Retrop_Importer( $id, $args );
	}

	private $_id;
	private $_json_structs;
	private $_url_to;
	private $_post_filter;
	private $_labels;

	private $_can_auto_add_terms = false;
	private $_registerer;

	private $_auto_add_terms = false;
	private $_ajax_request_url;

	public function __construct( $id, $args ) {
		$this->_id           = 'retrop_import_' . $id;
		$this->_json_structs = json_encode( $args['structs'] );
		$this->_url_to       = ( ! isset( $args['url_to'] ) || $args['url_to'] === false ) ? \st\get_file_uri( __DIR__ ) : $args['url_to'];
		if ( isset( $args['post_filter'] ) ) $this->_post_filter = $args['post_filter'];

		if ( isset( $args['can_auto_add_terms'] ) ) $this->_can_auto_add_terms = $args['can_auto_add_terms'];

		$this->_labels = [
			'name'              => 'Retrop Importer',
			'description'       => 'Import data from a Excel (.xlsx) file.',
			'message'           => 'Choose a Excel (.xlsx) file to upload, then click Upload file and import.',
			'failure'           => 'Sorry, there has been an error.',
			'success'           => 'Successfully finished.',
			'error_wrong_file'  => 'The file is wrong, please try again.',
			'all_done'          => 'All done.',
			'add_terms'         => 'Add Terms',
			'add_terms_message' => 'Add terms that import file contains',
			'updated'           => 'Updated',
			'new'               => 'New',
		];
		if ( isset( $args[ 'labels' ] ) ) $this->_labels = array_merge( $this->_labels, $args['labels'] );

		$this->initialize();
		$this->_registerer = new Registerer( $args['post_type'], $args['structs'], $this->_labels, $this->_post_filter );
		$this->_ajax_request_url = $this->initialize_ajax();
	}

	private function initialize() {
		$GLOBALS[ $this->_id ] = $this;
		register_importer(
			$this->_id,
			$this->_labels['name'],
			$this->_labels['description'],
			[ $GLOBALS[ $this->_id ], 'dispatch' ]
		);
	}

	private function initialize_ajax() {
		$ajax = new \st\Ajax( $this->_id, function () {
			$cont = file_get_contents( 'php://input' );
			$data = json_decode( $cont, true );
			if ( isset( $data['index'] ) && $data['index'] === 0 ) {
				add_filter( 'http_request_timeout', function () { return 60; } );
				do_action( 'import_start' );
				wp_suspend_cache_invalidation( true );
			}
			if ( isset( $data['msg'] ) && $data['msg'] === 'finished' ) {
				wp_suspend_cache_invalidation( false );
				wp_import_cleanup( (int) $data['file_id'] );
				wp_cache_flush();
				do_action( 'import_end' );
			} else {
				set_time_limit(0);
				$msg = $this->_registerer->process_item( $data['item'], $data['file_name'], $data['add_term'] );
				return [ 'msg' => $msg ];
			}
		}, false );
		return $ajax->get_url();
	}


	// -------------------------------------------------------------------------


	public function dispatch() {
		wp_enqueue_script( 'retrop-importer', \st\abs_url( $this->_url_to, './asset/importer.min.js' ) );
		wp_enqueue_script( 'xlsx', \st\abs_url( $this->_url_to, './asset/xlsx.full.min.js' ) );

		$this->_header();

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
		switch ( $step ) {
			case 0:
				$this->_greet();
				break;
			case 1:
				check_admin_referer( 'import-upload' );
				$fid = $this->_handle_upload();
				if ( $fid !== false ) $this->_parse_upload( $fid );
				break;
		}

		$this->_footer();
	}

	private function _header() {
		echo '<div class="wrap">';
		echo '<h2>' . $this->_labels['name'] . '</h2>';
	}

	private function _footer() {
		echo '</div>';
	}


	// Step 0 ------------------------------------------------------------------


	private function _greet() {
		echo '<div class="narrow">';
		echo '<p>' . $this->_labels['description'] . '</p>';
		echo '<p>' . $this->_labels['message'] . '</p>';
		wp_import_upload_form( 'admin.php?import=' . $this->_id . '&amp;step=1' );
		echo '</div>';
	}


	// Step 1 ------------------------------------------------------------------


	private function _handle_upload() {
		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) || ! file_exists( $file['file'] ) ) {
			echo '<p><strong>' . $this->_labels['failure'] . '</strong><br />';
			echo esc_html( $file['error'] ) . '</p>';
			return false;
		}
		return (int) $file['id'];
	}

	private function _parse_upload( $file_id ) {
		$_jstr  = esc_attr( $this->_json_structs );
		$_url   = esc_attr( wp_get_attachment_url( $file_id ) );
		$_fname = esc_attr( pathinfo( get_attached_file( $file_id ), PATHINFO_FILENAME ) );
		$_ajax  = esc_attr( $this->_ajax_request_url );
?>
		<input type="hidden" id="retrop-load-files" />
		<input type="hidden" id="retrop-structs" value="<?php echo $_jstr ?>" />
		<input type="hidden" id="retrop-url" value="<?php echo $_url ?>" />
		<input type="hidden" id="retrop-file-name" value="<?php echo $_fname ?>" />
		<input type="hidden" id="retrop-ajax-request-url" value="<?php echo $_ajax ?>" />
		<input type="hidden" id="retrop-file-id" value="<?php echo $file_id; ?>" />

<?php if ( $this->_can_auto_add_terms ) : ?>
		<h3><?php echo $this->_labels['add_terms'] ?></h3>
		<p>
			<input type="radio" value="1" id="retrop-add-term" />
			<label for="retrop-add-term"><?php echo $this->_labels['add_terms_message'] ?></label>
		</p>
<?php endif; ?>

		<p class="submit">
			<input type="submit" disabled name="retrop-submit-ajax" class="button button-primary" value="<?php esc_attr_e( 'Import' ); ?>" />
		</p>
		<div id="response-pb" style="height:2em;background:#fcfdfd;border:1px solid #c5dbec;border-radius:5px;overflow:hidden;">
			<div style="margin:-1px;height:100%;border:1px solid #4297d7;background:#5c9ccc;width:0%;"></div>
		</div>
		<div id="response-msgs" style="margin-top:1em;max-height:50vh;overflow:auto;"></div>

		<p id="retrop-failure" style="display: none;"><strong><?php echo $this->_labels['failure'] ?></strong></p>
		<p id="retrop-success" style="display: none;"><strong><?php echo $this->_labels['success'] ?></strong></p>
<?php
	}

}
