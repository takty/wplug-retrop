<?php
/**
 * Retrop Importer: Versatile XLSX Importer
 *
 * @package Wplug Retrop
 * @author Takuto Yanagida
 * @version 2021-09-02
 */

namespace wplug\retrop;

require_once ABSPATH . 'wp-admin/includes/import.php';
if ( ! class_exists( '\WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) {
		require $class_wp_importer;
	}
}
if ( ! class_exists( '\WP_Importer' ) ) {
	return;
}

require_once __DIR__ . '/assets/util.php';
require_once __DIR__ . '/assets/class-ajax.php';
require_once __DIR__ . '/inc/class-registerer.php';

/**
 * Retrop importer.
 */
class Retrop_Importer extends \WP_Importer {

	/**
	 * Instances.
	 *
	 * @var 1.0
	 */
	private static $instance = array();

	/**
	 * Registers the importer.
	 *
	 * @param string $id   The ID of the importer.
	 * @param array  $args {
	 *     Array of arguments.
	 *
	 *     @type string   'url_to'             Base URL.
	 *     @type string   'post_type'          Post type of imported posts.
	 *     @type array    'structs'            The structure of files to be imported.
	 *     @type callable 'post_filter'        Post filter.
	 *     @type bool     'can_auto_add_terms' Whether the importer can add new terms.
	 *     @type array    'labels'             Labels.
	 * }
	 */
	public static function register( string $id, array $args = array() ) {
		self::$instance[] = new Retrop_Importer( $id, $args );
	}

	/**
	 * The ID of the importer.
	 *
	 * @var 1.0
	 */
	private $id;

	/**
	 * The structure of files to be imported.
	 *
	 * @var 1.0
	 */
	private $json_structs;

	/**
	 * Base URL.
	 *
	 * @var 1.0
	 */
	private $url_to;

	/**
	 * Post filter.
	 *
	 * @var 1.0
	 */
	private $post_filter;

	/**
	 * Labels.
	 *
	 * @var 1.0
	 */
	private $labels;

	/**
	 * Whether the importer can add new terms.
	 *
	 * @var 1.0
	 */
	private $can_auto_add_terms = false;

	/**
	 * An instance of the Registerer.
	 *
	 * @var 1.0
	 */
	private $registerer;

	/**
	 * Ajax request URL.
	 *
	 * @var 1.0
	 */
	private $ajax_request_url;

	/**
	 * Constructor.
	 *
	 * @access private
	 *
	 * @param string $id   ID.
	 * @param array  $args {
	 *     Array of arguments.
	 *
	 *     @type string   'url_to'             Base URL.
	 *     @type string   'post_type'          Post type of imported posts.
	 *     @type array    'structs'            The structure of files to be imported.
	 *     @type callable 'post_filter'        Post filter.
	 *     @type bool     'can_auto_add_terms' Whether the importer can add new terms.
	 *     @type array    'labels'             Labels.
	 * }
	 */
	private function __construct( string $id, array $args ) {
		$this->id           = 'retrop_import_' . $id;
		$this->json_structs = wp_json_encode( $args['structs'] );
		$this->url_to       = ( ! isset( $args['url_to'] ) || false === $args['url_to'] ) ? get_file_uri( __DIR__ ) : $args['url_to'];
		if ( isset( $args['post_filter'] ) ) {
			$this->post_filter = $args['post_filter'];
		}
		if ( isset( $args['can_auto_add_terms'] ) ) {
			$this->can_auto_add_terms = $args['can_auto_add_terms'];
		}
		$this->labels = array(
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
		);
		if ( isset( $args['labels'] ) ) {
			$this->labels = array_merge( $this->labels, $args['labels'] );
		}
		$this->initialize();
		$this->registerer       = new Registerer( $args['post_type'], $args['structs'], $this->labels, $this->post_filter );
		$this->ajax_request_url = $this->initialize_ajax();
	}

	/**
	 * Initializes the importer.
	 *
	 * @access private
	 */
	private function initialize() {
		$GLOBALS[ $this->id ] = $this;
		register_importer(
			$this->id,
			$this->labels['name'],
			$this->labels['description'],
			array( $GLOBALS[ $this->id ], 'dispatch' )
		);
	}

	/**
	 * Initializes Ajax.
	 *
	 * @access private
	 *
	 * @return string Ajax URL.
	 */
	private function initialize_ajax(): string {
		$ajax = new Ajax(
			$this->id,
			function () {
				$cont = file_get_contents( 'php://input' );
				$data = json_decode( $cont, true );
				if ( isset( $data['index'] ) && 0 === $data['index'] ) {
					add_filter(
						'http_request_timeout',
						function () {
							return 60;
						}
					);
					do_action( 'import_start' );
					wp_suspend_cache_invalidation( true );
				}
				if ( isset( $data['msg'] ) && 'finished' === $data['msg'] ) {
					wp_suspend_cache_invalidation( false );
					wp_import_cleanup( (int) $data['file_id'] );
					wp_cache_flush();
					do_action( 'import_end' );
				} else {
					set_time_limit( 0 );
					$msg = $this->registerer->process_item( $data['item'], $data['file_name'], $data['add_term'] );
					return array( 'msg' => $msg );
				}
			},
			false
		);
		return $ajax->get_url();
	}


	// -------------------------------------------------------------------------


	/**
	 * Dispatches the request.
	 */
	public function dispatch() {
		wp_enqueue_script( 'wplug-retrop-importer', abs_url( $this->url_to, './assets/js/importer.min.js' ), array(), '1.0', false );
		wp_enqueue_script( 'xlsx', abs_url( $this->url_to, './assets/js/xlsx.full.min.js' ), array(), '1.0', false );

		$this->header();

		$step = isset( $_GET['step'] ) ? ( (int) sanitize_text_field( wp_unslash( $_GET['step'] ) ) ) : 0;
		switch ( $step ) {
			case 0:
				$this->greet();
				break;
			case 1:
				check_admin_referer( 'import-upload' );
				$fid = $this->handle_upload();
				if ( ! is_null( $fid ) ) {
					$this->parse_upload( $fid );
				}
				break;
		}

		$this->footer();
	}

	/**
	 * Outputs the header.
	 *
	 * @access private
	 */
	private function header() {
		echo '<div class="wrap">';
		echo '<h2>' . esc_html( $this->labels['name'] ) . '</h2>';
	}

	/**
	 * Outputs the footer.
	 *
	 * @access private
	 */
	private function footer() {
		echo '</div>';
	}


	// ----------------------------------------------------------------- Step 0.


	/**
	 * Outputs the greet message.
	 *
	 * @access private
	 */
	private function greet() {
		echo '<div class="narrow">';
		echo '<p>' . wp_kses_data( $this->labels['description'] ) . '</p>';
		echo '<p>' . wp_kses_data( $this->labels['message'] ) . '</p>';
		wp_import_upload_form( 'admin.php?import=' . $this->id . '&amp;step=1' );
		echo '</div>';
	}


	// ----------------------------------------------------------------- Step 1.


	/**
	 * Handles uploading.
	 *
	 * @access private
	 *
	 * @return ?int File ID.
	 */
	private function handle_upload(): ?int {
		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) || ! file_exists( $file['file'] ) ) {
			echo '<p><strong>' . wp_kses_data( $this->labels['failure'] ) . '</strong><br>';
			echo esc_html( $file['error'] ) . '</p>';
			return null;
		}
		return (int) $file['id'];
	}

	/**
	 * Parses uploaded file.
	 *
	 * @access private
	 *
	 * @param int $file_id File ID.
	 */
	private function parse_upload( int $file_id ) {
		$struct = $this->json_structs;
		$url    = wp_get_attachment_url( $file_id );
		$fname  = pathinfo( get_attached_file( $file_id ), PATHINFO_FILENAME );
		$ajax   = $this->ajax_request_url;
		?>
		<input type="hidden" id="wplug-retrop-load-files">
		<input type="hidden" id="wplug-retrop-structs" value="<?php echo esc_attr( $struct ); ?>">
		<input type="hidden" id="wplug-retrop-url" value="<?php echo esc_attr( $url ); ?>">
		<input type="hidden" id="wplug-retrop-file-name" value="<?php echo esc_attr( $fname ); ?>">
		<input type="hidden" id="wplug-retrop-ajax-request-url" value="<?php echo esc_attr( $ajax ); ?>">
		<input type="hidden" id="wplug-retrop-file-id" value="<?php echo esc_attr( $file_id ); ?>">

		<?php if ( $this->can_auto_add_terms ) : ?>
		<h3><?php echo esc_html( $this->labels['add_terms'] ); ?></h3>
		<p>
			<input type="radio" value="1" id="wplug-retrop-add-term">
			<label for="wplug-retrop-add-term"><?php echo wp_kses_data( $this->labels['add_terms_message'] ); ?></label>
		</p>
		<?php endif; ?>

		<p class="submit">
			<input type="submit" disabled name="wplug-retrop-submit-ajax" class="button button-primary" value="<?php esc_attr_e( 'Import', 'wplug_retrop' ); ?>">
		</p>
		<div id="response-pb" style="height:2em;background:#fcfdfd;border:1px solid #c5dbec;border-radius:5px;overflow:hidden;">
			<div style="margin:-1px;height:100%;border:1px solid #4297d7;background:#5c9ccc;width:0%;"></div>
		</div>
		<div id="response-msgs" style="margin-top:1em;max-height:50vh;overflow:auto;"></div>

		<p id="wplug-retrop-failure" style="display: none;"><strong><?php echo wp_kses_data( $this->labels['failure'] ); ?></strong></p>
		<p id="wplug-retrop-success" style="display: none;"><strong><?php echo wp_kses_data( $this->labels['success'] ); ?></strong></p>
		<?php
	}

}
