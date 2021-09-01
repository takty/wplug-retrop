<?php
/**
 * Ajax
 *
 * @package Wplug Retrop
 * @author Takuto Yanagida
 * @version 2021-09-02
 */

namespace wplug\retrop;

/**
 * Ajax.
 */
class Ajax {

	/**
	 * Action.
	 *
	 * @var 1.0
	 */
	private $action;

	/**
	 * Response.
	 *
	 * @var 1.0
	 */
	private $response;

	/**
	 * Nonce
	 *
	 * @var 1.0
	 */
	private $nonce;

	/**
	 * Constructor.
	 *
	 * @param string   $action   Ajax action.
	 * @param callable $response Function called when receive message.
	 * @param bool     $public   Whether this ajax is public.
	 * @param ?string  $nonce    Nonce.
	 */
	public function __construct( string $action, $response, bool $public = false, ?string $nonce = null ) {
		if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $action ) ) {
			wp_die( 'Invalid string for ' . esc_html( $action ) . '.' );
		}
		$this->action   = $action;
		$this->response = $response;
		$this->nonce    = ( null === $nonce ) ? $action : $nonce;

		add_action( "wp_ajax_$action", array( $this, 'cb_ajax_action' ) );
		if ( $public ) {
			add_action( "wp_ajax_nopriv_$action", array( $this, 'cb_ajax_action' ) );
		}
	}

	/**
	 * Gets the URL of this ajax.
	 *
	 * @param array $query Query arguments.
	 * @return string URL.
	 */
	public function get_url( array $query = array() ): string {
		$query['action'] = $this->action;
		$query['nonce']  = wp_create_nonce( $this->nonce );

		$url = admin_url( 'admin-ajax.php' );
		foreach ( $query as $key => $value ) {
			$url = add_query_arg( $key, $value, $url );
		}
		return $url;
	}

	/**
	 * Callback function for 'wp_ajax_nopriv_{$_REQUEST[‘action’]}' action.
	 */
	public function cb_ajax_action() {
		check_ajax_referer( $this->nonce, 'nonce' );
		nocache_headers();

		$res = call_user_func( $this->response );
		if ( is_array( $res ) ) {
			wp_send_json( $res );
		} else {
			echo esc_html( $res );
			die;
		}
	}

}
