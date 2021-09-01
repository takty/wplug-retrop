<?php
/**
 * Retrop Exporter: Versatile XLSX Exporter
 *
 * @package Wplug Retrop
 * @author Takuto Yanagida
 * @version 2021-09-02
 */

namespace wplug\retrop;

require_once __DIR__ . '/assets/util.php';
require_once __DIR__ . '/assets/simple_html_dom.php';
require_once __DIR__ . '/inc/media.php';

/**
 * Retrop exporter.
 */
class Retrop_Exporter {

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
	 *     @type string 'url_to'    Base URL.
	 *     @type string 'post_type' Post type of exported posts.
	 *     @type array  'structs'   The structure of files to be exported.
	 *     @type array  'labels'    Labels.
	 * }
	 */
	public static function register( $id, $args = array() ) {
		self::$instance[] = new Retrop_Exporter( $id, $args );
	}

	/**
	 * The ID of the exporter.
	 *
	 * @var 1.0
	 */
	private $id;

	/**
	 * The post type of posts to be exported.
	 *
	 * @var 1.0
	 */
	private $post_type;

	/**
	 * The structure of files to be exported.
	 *
	 * @var 1.0
	 */
	private $structs;

	/**
	 * Base URL.
	 *
	 * @var 1.0
	 */
	private $url_to;

	/**
	 * Labels.
	 *
	 * @var 1.0
	 */
	private $labels;

	/**
	 * Constructor.
	 *
	 * @access private
	 *
	 * @param string $id   ID.
	 * @param array  $args {
	 *     Array of arguments.
	 *
	 *     @type string 'url_to'    Base URL.
	 *     @type string 'post_type' Post type of exported posts.
	 *     @type array  'structs'   The structure of files to be exported.
	 *     @type array  'labels'    Labels.
	 * }
	 */
	private function __construct( string $id, array $args ) {
		$this->id        = 'retrop_export_' . $id;
		$this->post_type = $args['post_type'];
		$this->structs   = $this->sort_structs( $args['structs'] );
		$this->url_to    = ( ! isset( $args['url_to'] ) || false === $args['url_to'] ) ? get_file_uri( __DIR__ ) : $args['url_to'];

		$this->labels = array(
			'name'        => 'Retrop Exporter',
			'description' => 'Export data to a Excel (.xlsx) file.',
			'success'     => 'Successfully finished.',
			'failure'     => 'Sorry, there has been an error.',
		);
		if ( isset( $args['labels'] ) ) {
			$this->labels = array_merge( $this->labels, $args['labels'] );
		}
		add_action( 'admin_menu', array( $this, 'cb_admin_menu' ) );
	}

	/**
	 * Sort the structure of files to be exported.
	 *
	 * @access private
	 *
	 * @param array $structs The structure.
	 * @return array Sorted structure.
	 */
	private function sort_structs( array $structs ): array {
		$temp = array();
		foreach ( $structs as $key => $s ) {
			if ( FS_TYPE_MEDIA === $s['type'] ) {
				$temp[ $key ] = $s;
				unset( $structs[ $key ] );
			}
		}
		foreach ( $temp as $key => $s ) {
			$structs[ $key ] = $s;
		}
		return $structs;
	}

	/**
	 * Callback function for 'admin_menu' action.
	 */
	public function cb_admin_menu() {
		$label = $this->labels['name'];
		add_submenu_page( 'tools.php', $label, $label, 'level_7', $this->id, array( $this, 'cb_output_page' ) );
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

	/**
	 * Callback function for 'add_submenu_page'.
	 *
	 * @access private
	 */
	public function cb_output_page() {
		wp_enqueue_script( 'xlsx', abs_url( $this->url_to, './assets/js/xlsx.full.min.js' ), array(), '1.0', false );
		wp_enqueue_script( 'wplug-retrop-exporter', abs_url( $this->url_to, './assets/js/exporter.min.js' ), array(), '1.0', false );

		$this->header();

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
		switch ( $step ) {
			case 0:
				$this->output_option_page();
				break;
			case 1:
				check_admin_referer( 'export-option' );
				$fn  = empty( $_POST['filename'] ) ? 'export' : sanitize_text_field( wp_unslash( $_POST['filename'] ) );
				$bgn = empty( $_POST['date_bgn'] ) ? false : sanitize_text_field( wp_unslash( $_POST['date_bgn'] ) );
				$end = empty( $_POST['date_end'] ) ? false : sanitize_text_field( wp_unslash( $_POST['date_end'] ) );
				$this->output_download_page( $fn, $bgn, $end );
				break;
		}
		$this->footer();
	}

	/**
	 * Displays the option page.
	 *
	 * @access private
	 */
	private function output_option_page() {
		echo '<div class="narrow">';
		echo '<p>' . wp_kses_data( $this->labels['description'] ) . '</p>';
		?>
		<form method="post" action="<?php echo esc_url( wp_nonce_url( 'tools.php?page=' . $this->id . '&amp;step=1', 'export-option' ) ); ?>">
			<p>
				<label for="filename"><?php esc_html_e( 'File name:', 'wplug_retrop' ); ?></label>
				<input type="text" required="" class="regular-text" id="filename" name="filename">
			</p>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Date range:', 'wplug_retrop' ); ?></legend>
				<label for="date-bgn" class="label-responsive"><?php esc_html_e( 'Start date:', 'wplug_retrop' ); ?></label>
				<select name="date_bgn" id="date-bgn">
					<option value="0"><?php esc_html_e( '&mdash; Select &mdash;', 'wplug_retrop' ); ?></option>
					<?php $this->export_date_options(); ?>
				</select>
				<label for="date-end" class="label-responsive"><?php esc_html_e( 'End date:', 'wplug_retrop' ); ?></label>
				<select name="date_end" id="date-end">
					<option value="0"><?php esc_html_e( '&mdash; Select &mdash;', 'wplug_retrop' ); ?></option>
					<?php $this->export_date_options(); ?>
				</select>
			</fieldset>
			<?php submit_button( __( 'Export', 'wplug_retrop' ), 'primary' ); ?>
		</form>
		<?php
		echo '</div>';
	}

	/**
	 * Displays the option markups of export date.
	 *
	 * @access private
	 */
	private function export_date_options() {
		global $wpdb, $wp_locale;

		$months = $wpdb->get_results(  // phpcs:ignore
			$wpdb->prepare(
				"SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
				FROM $wpdb->posts
				WHERE post_type = %s AND post_status != 'auto-draft'
				ORDER BY post_date DESC",
				$this->post_type
			)
		);

		$month_count = count( $months );
		if ( ! $month_count || ( 1 === $month_count && 0 === $months[0]->month ) ) {
			return;
		}
		foreach ( $months as $date ) {
			if ( 0 === $date->year ) {
				continue;
			}
			$month = zeroise( $date->month, 2 );
			$val   = $date->year . '-' . $month;
			$label = $wp_locale->get_month( $month ) . ' ' . $date->year;
			echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>';
		}
	}

	/**
	 * Displays the download page.
	 *
	 * @access private
	 *
	 * @param string $file_name File name.
	 * @param string $bgn       Date from.
	 * @param string $end       Date to.
	 */
	private function output_download_page( string $file_name, string $bgn, string $end ) {
		$pi        = pathinfo( $file_name );
		$file_name = $pi['basename'];
		if ( empty( $pi['extension'] ) ) {
			$file_name .= '.xlsx';
		}
		$json_structs = wp_json_encode( array_keys( $this->structs ) );
		?>
		<div class="narrow">
			<p><?php echo wp_kses_data( $this->labels['description'] ); ?></p>
			<?php $this->output_data( $bgn, $end ); ?>
			<p class="submit">
				<input type="submit" name="download" id="download" class="button button-primary" value="<?php esc_html_e( 'Download Export File', 'wplug_retrop' ); ?>">
			</p>
			<div id="wplug-retrop-success" style="display: none;"><?php echo wp_kses_data( $this->labels['success'] ); ?></div>
			<div id="wplug-retrop-failure" style="display: none;"><?php echo wp_kses_data( $this->labels['failure'] ); ?></div>
			<input id="wplug-retrop-structs" type="hidden" value="<?php echo esc_attr( $json_structs ); ?>">
			<input id="wplug-retrop-filename" type="hidden" value="<?php echo esc_attr( $file_name ); ?>">
		</div>
		<?php
	}

	/**
	 * Outputs data.
	 *
	 * @access private
	 *
	 * @param string $bgn Date from.
	 * @param string $end Date to.
	 */
	private function output_data( string $bgn, string $end ) {
		$args = array(
			'post_type'      => $this->post_type,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'asc',
		);
		if ( false !== $bgn || false !== $end ) {
			$dq = array();
			if ( false !== $bgn ) {
				$bgn_ym = explode( '-', $bgn );
				$dq[]   = array(
					'after'     => array(
						'year'  => (int) $bgn_ym[0],
						'month' => (int) $bgn_ym[1],
					),
					'inclusive' => true,
				);
			}
			if ( false !== $end ) {
				$end_ym = explode( '-', $end );
				$dq[]   = array(
					'before'    => array(
						'year'  => (int) $end_ym[0],
						'month' => (int) $end_ym[1],
					),
					'inclusive' => true,
				);
			}
			if ( false !== $bgn && false !== $end ) {
				$dq['relation'] = 'AND';
			}
			$args['date_query'] = $dq;
		}
		$ps  = get_posts( $args );
		$pss = array_chunk( $ps, 20 );
		foreach ( $pss as $idx => $ps ) {
			$as = array();
			foreach ( $ps as $p ) {
				$as[] = $this->make_record_array( $p );
			}
			$js = wp_json_encode( $as, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$js = mb_ereg_replace( '&#x000d;', '', $js );
			?>
			<input type="hidden" id="wplug-retrop-chunk-<?php echo esc_attr( $idx ); ?>" value="<?php echo esc_attr( $js ); ?>">
			<?php
		}
	}

	/**
	 * Creates a record array of a post.
	 *
	 * @access private
	 *
	 * @param \WP_Post $p A post.
	 * @return array A record array.
	 */
	private function make_record_array( \WP_Post $p ): array {
		$ret     = array();
		$id2urls = array();

		foreach ( $this->structs as $key => $s ) {
			$val = '';
			switch ( $s['type'] ) {
				case FS_TYPE_TITLE:
					$val = $p->post_title;
					break;
				case FS_TYPE_CONTENT:
					$val = $p->post_content;
					if ( isset( $s[ FS_FILTER ] ) && FS_FILTER_CONTENT_MEDIA === $s[ FS_FILTER ] ) {
						_extract_media( $val, $id2urls );
					}
					break;
				case FS_TYPE_MEDIA:
					$val = array();
					foreach ( $id2urls as $id => $url ) {
						$val[ $id ] = array_keys( $url );
					}
					break;
				case FS_TYPE_META:
					$key_m = $s[ FS_KEY ];
					$val   = get_post_meta( $p->ID, $key_m, true );
					if ( isset( $s[ FS_FILTER ] ) && FS_FILTER_MEDIA_URL === $s[ FS_FILTER ] ) {
						$orig_id = (int) $val;
						$ais     = wp_get_attachment_image_src( $orig_id, 'full' );
						if ( false !== $ais ) {
							$val             = array();
							$val[ $orig_id ] = array( $ais[0] );
						}
					}
					if ( isset( $s[ FS_FILTER ] ) && FS_FILTER_CONTENT_MEDIA === $s[ FS_FILTER ] ) {
						_extract_media( $val, $id2urls );
					}
					break;
				case FS_TYPE_DATE:
					$val = $p->post_date;
					break;
				case FS_TYPE_DATE_GMT:
					$val = $p->post_date_gmt;
					break;
				case FS_TYPE_SLUG:
					$val = $p->post_name;
					break;
				case FS_TYPE_MENU_ORDER:
					$val = $p->menu_order;
					break;
				case FS_TYPE_TERM:
					$tax = $s[ FS_TAXONOMY ];
					$ts  = get_the_terms( $p->ID, $tax );
					if ( is_array( $ts ) ) {
						$slugs = array();
						foreach ( $ts as $t ) {
							$slugs[] = $t->slug;
						}
						$val = implode( ', ', $slugs );
					}
					break;
				case FS_TYPE_THUMBNAIL_URL:
					if ( ! has_post_thumbnail( $p->ID ) ) {
						break;
					}
					$id  = get_post_thumbnail_id( $p->ID );
					$ais = wp_get_attachment_image_src( $id, 'full' );
					if ( false !== $ais ) {
						$val        = array();
						$val[ $id ] = array( $ais[0] );
					}
					break;
				case FS_TYPE_ACF_PM:
					if ( function_exists( 'get_field' ) ) {
						$key_m = $s[ FS_KEY ];
						$val   = get_field( $key_m, $p->ID, false );
						if ( ! $val ) {
							$val = '';
						}
						if ( ! empty( $val ) && isset( $s[ FS_FILTER ] ) && FS_FILTER_CONTENT_MEDIA === $s[ FS_FILTER ] ) {
							_extract_media( $val, $id2urls );
						}
					}
					break;
			}
			if ( is_string( $val ) ) {
				$val = str_replace( array( "\r\n", "\r", "\n" ), '\n', $val );
			} elseif ( is_array( $val ) ) {
				$val = wp_json_encode( $val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
			$ret[] = $val;
		}
		return $ret;
	}

}
