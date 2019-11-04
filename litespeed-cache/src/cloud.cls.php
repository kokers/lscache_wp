<?php
/**
 * Cloud service cls
 *
 * @since      3.0
 */
namespace LiteSpeed;
defined( 'WPINC' ) || exit;

class Cloud extends Base
{
	protected static $_instance;

	const DB_HASH = 'hash';

	const CLOUD_SERVER = 'https://apidev.quic.cloud';

	const SVC_D_NODES 			= 'd/nodes';
	const SVC_D_SYNC_CONF 		= 'd/sync_conf';
	const SVC_D_USAGE 			= 'd/usage';
	const SVC_CCSS 				= 'ccss' ;
	const SVC_PLACEHOLDER 		= 'placeholder' ;
	const SVC_LQIP 				= 'lqip' ;
	const SVC_ENV_REPORT		= 'env_report' ;
	const SVC_IMG_OPTM			= 'img_optm' ;
	const SVC_PAGESCORE			= 'pagescore' ;
	const SVC_CDN				= 'cdn' ;

	const CENTER_SVC_SET = array(
		self::SVC_D_NODES,
		self::SVC_D_SYNC_CONF,
		self::SVC_D_USAGE,
	);

	const SERVICES = array(
		self::SVC_IMG_OPTM,
		self::SVC_CCSS,
		self::SVC_LQIP,
		self::SVC_CDN,
		self::SVC_PLACEHOLDER,
		self::SVC_PAGESCORE,
		'sitehealth',
	);

	const TYPE_GEN_KEY 		= 'gen_key';
	const TYPE_SYNC_USAGE 	= 'sync_usage';

	private $_api_key;
	private $_summary;

	/**
	 * Init
	 *
	 * @since  3.0
	 */
	protected function __construct()
	{
		$this->_api_key = Conf::val( Base::O_API_KEY );
		$this->_summary = self::get_summary();
	}

	/**
	 * Get allowance of current service
	 *
	 * @since  3.0
	 * @access private
	 */
	public function allowance( $service )
	{
		$this->_sync_usage();

		if ( empty( $this->_summary[ 'usage.' . $service ] ) ) {
			return 0;
		}

		return $this->_summary[ 'usage.' . $service ][ 'quota' ] - $this->_summary[ 'usage.' . $service ][ 'used' ];
	}

	/**
	 * Sync Cloud usage summary data
	 *
	 * @since  3.0
	 * @access private
	 */
	private function _sync_usage()
	{
		$usage = $this->_post( self::SVC_D_USAGE );
		if ( ! $usage ) {
			return;
		}

		Log::debug( '[Cloud] _sync_usage ' . json_encode( $usage ) );

		foreach ( self::SERVICES as $v ) {
			$this->_summary[ 'usage.' . $v ] = ! empty( $usage[ $v ] ) ? $usage[ $v ] : false;
		}
		self::save_summary( $this->_summary );

		$msg = __( 'Sync credit allowance with Cloud Server successfully.', 'litespeed-cache' ) ;
		Admin_Display::succeed( $msg ) ;
	}

	/**
	 * ping clouds to find the fastest node
	 *
	 * @since  3.0
	 * @access private
	 */
	private function _detect_cloud( $service )
	{
		// Check if the stored server needs to be refreshed
		if ( ! empty( $this->_summary[ 'server.' . $service ] ) && ! empty( $this->_summary[ 'server_date.' . $service ] ) && $this->_summary[ 'server_date.' . $service ] < time() + 86400 * 30 ) {
			return $this->_summary[ 'server.' . $service ];
		}

		if ( ! $service || ! in_array( $service, self::SERVICES ) ) {
			$msg = __( 'Cloud Error', 'litespeed-cache' ) . ': ' . $service;
			Admin_Display::error( $msg );
			return false;
		}

		// Send request to Quic Online Service
		$json = $this->_post( self::SVC_D_NODES, array( 'svc' => $service ) );

		// Check if get list correctly
		if ( empty( $json[ 'list' ] ) || ! is_array( $json[ 'list' ] ) ) {
			Log::debug( '[Cloud] request cloud list failed: ', $json );

			if ( $json ) {
				$msg = __( 'Cloud Error', 'litespeed-cache' ) . ": [Service] $service [Info] " . $json;
				Admin_Display::error( $msg );
			}
			return false;
		}

		// Ping closest cloud
		$speed_list = array();
		foreach ( $json[ 'list' ] as $v ) {
			$speed_list[ $v ] = Utility::ping( $v );
		}
		$min = min( $speed_list );

		if ( $min == 99999 ) {
			Log::debug( '[Cloud] failed to ping all clouds' );
			return false;
		}

		// Random pick same time range ip (230ms 250ms)
		$range_len = strlen( $min );
		$range_num = substr( $min, 0, 1 );
		$valid_clouds = array();
		foreach ($speed_list as $node => $speed ) {
			if ( strlen( $speed ) == $range_len && substr( $speed, 0, 1 ) == $range_num ) {
				$valid_clouds[] = $node;
			}
		}

		if ( ! $valid_clouds ) {
			$msg = __( 'Cloud Error', 'litespeed-cache' ) . ": [Service] $service [Info] " . __( 'No available Cloud Node.', 'litespeed-cache' );
			Admin_Display::error( $msg );
			return false;
		}

		Log::debug( '[Cloud] Closest nodes list', $valid_clouds );

		$closest = $valid_clouds[ array_rand( $valid_clouds ) ];

		Log::debug( '[Cloud] Chose node: ' . $closest );

		// store data into option locally
		$this->_summary[ 'server.' . $service ] = $closest;
		$this->_summary[ 'server_date.' . $service ] = time();
		self::save_summary( $this->_summary );

		return $this->_summary[ 'server.' . $service ];
	}

	/**
	 * Get data from QUIC cloud server
	 *
	 * @since  3.0
	 * @access public
	 */
	public static function get( $service, $data = array() )
	{
		$instance = self::get_instance();
		return $instance->_get( $service, $data );
	}

	/**
	 * Get data from QUIC cloud server
	 *
	 * @since  3.0
	 * @access private
	 */
	private function _get( $service, $data = false )
	{
		$service_tag = $service;
		if ( ! empty( $data[ 'action' ] ) ) {
			$service_tag .= '-' . $data[ 'action' ];
		}

		if ( ! $this->_maybe_cloud( $service_tag ) ) {
			return;
		}

		$server = $this->_detect_cloud( $service );
		if ( ! $server ) {
			return;
		}

		$url = $server . '/' . $service;

		if ( $data ) {
			$url .= '?' . http_build_query( $data );
		}

		Log::debug( '[Cloud] getting from : ' . $url );

		$this->_summary[ 'curr_request.' . $service_tag ] = time();
		self::save_summary( $this->_summary );

		$response = wp_remote_get( $url, array( 'timeout' => 15, 'sslverify' => false ) );

		return $this->_parse_response( $response, $service, $service_tag );
	}

	/**
	 * Check if is able to do cloud request or not
	 *
	 * @since  3.0
	 * @access private
	 */
	private function _maybe_cloud( $service_tag )
	{
		if ( ! $this->_api_key ) {
			$msg = sprintf( __( 'The Cloud API key need to be set first to use online service. <a %s>Click here to Setting page</a>.', 'litespeed-cache' ), ' href="' . admin_url('admin.php?page=litespeed-general') . '" ' );
			Admin_Display::error( $msg );
			return false;
		}

		// Limit frequent unfinished request to 5min
		if ( ! empty( $this->_summary[ 'curr_request.' . $service_tag ] ) ) {
			$expired = $this->_summary[ 'curr_request.' . $service_tag ] + 300 - time();
			if ( $expired > 0 ) {
				Log::debug( "[Cloud] ❌ try [$service_tag] after $expired seconds" );

				$msg = __( 'Cloud Error', 'litespeed-cache' ) . ': ' . sprintf( __( 'Please try after %1$s for service %2$s.', 'litespeed-cache' ), Utility::readable_time( $expired, 0, 0 ), '<code>' . $service_tag . '</code>' );
				Admin_Display::error( $msg );
				return false;
			}
		}

		return true;
	}

	/**
	 * Post data to QUIC.cloud server
	 *
	 * @since  3.0
	 * @access public
	 */
	public static function post( $service, $data = false, $time_out = false, $need_hash = false )
	{
		$instance = self::get_instance();
		return $instance->_post( $service, $data, $time_out, $need_hash );
	}

	/**
	 * Post data to cloud server
	 *
	 * @since  3.0
	 * @access private
	 */
	private function _post( $service, $data = false, $time_out = false, $need_hash = false )
	{
		$service_tag = $service;
		if ( ! empty( $data[ 'action' ] ) ) {
			$service_tag .= '-' . $data[ 'action' ];
		}

		if ( ! $this->_maybe_cloud( $service_tag ) ) {
			return;
		}

		if ( in_array( $service, self::CENTER_SVC_SET ) ) {
			$server = self::CLOUD_SERVER;
		}
		else {
			$server = $this->_detect_cloud( $service );
			if ( ! $server ) {
				return;
			}
		}

		$url = $server . '/' . $service;

		Log::debug( '[Cloud] posting to : ' . $url );

		$param = array(
			'site_url'		=> home_url(),
			'domain_key'	=> $this->_api_key,
			'v'				=> Core::VER,
			'data' 			=> $data,
		);
		if ( $need_hash ) {
			$param[ 'hash' ] = $this->_hash_make();
		}
		/**
		 * Extended timeout to avoid cUrl 28 timeout issue as we need callback validation
		 * @since 1.6.4
		 */
		$this->_summary[ 'curr_request.' . $service_tag ] = time();
		self::save_summary( $this->_summary );

		$response = wp_remote_post( $url, array( 'body' => $param, 'timeout' => $time_out ?: 15, 'sslverify' => false ) );

		return $this->_parse_response( $response, $service, $service_tag );
	}

	/**
	 * Parse response JSON
	 * Mark the request successful if the response status is ok
	 *
	 * @since  3.0
	 */
	private function _parse_response( $response, $service, $service_tag )
	{
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			Log::debug( '[Cloud] failed to request: ' . $error_message );

			$msg = __( 'Failed to request via WordPress', 'litespeed-cache' ) . ': ' . $error_message;
			Admin_Display::error( $msg );
			return false;
		}

		$json = json_decode( $response[ 'body' ], true );

		if ( ! is_array( $json ) ) {
			Log::debug( '[Cloud] failed to decode response json: ' . $response[ 'body' ] );

			$msg = __( 'Failed to request via WordPress', 'litespeed-cache' ) . ': ' . $response[ 'body' ];
			Admin_Display::error( $msg );

			return false;
		}

		if ( ! empty( $json[ '_503' ] ) ) {
			Log::debug( '[Cloud] service 503 unavailable temporarily. ' . $json[ '_503' ] );

			$msg = __( 'We are working hard to improve your online service experience. The service will be unavailable while we work. We apologize for any inconvenience.', 'litespeed-cache' );
			$msg .= ' ' . $json[ '_503' ];
			Admin_Display::error( $msg );

			return false;
		}

		if ( ! empty( $json[ '_info' ] ) ) {
			Log::debug( '[Cloud] _info: ' . $json[ '_info' ] );
			$msg = __( 'Message from QUIC.cloud server', 'litespeed-cache' ) . ': ' . $json[ '_info' ];
			$msg .= $this->_parse_link( $json );
			Admin_Display::info( $msg );
			unset( $json[ '_info' ] );
		}

		if ( ! empty( $json[ '_note' ] ) ) {
			Log::debug( '[Cloud] _note: ' . $json[ '_note' ] );
			$msg = __( 'Message from QUIC.cloud server', 'litespeed-cache' ) . ': ' . $json[ '_note' ];
			$msg .= $this->_parse_link( $json );
			Admin_Display::note( $msg );
			unset( $json[ '_note' ] );
		}

		if ( ! empty( $json[ '_success' ] ) ) {
			Log::debug( '[Cloud] _success: ' . $json[ '_success' ] );
			$msg = __( 'Good news from QUIC.cloud server', 'litespeed-cache' ) . ': ' . $json[ '_success' ];
			$msg .= $this->_parse_link( $json );
			Admin_Display::succeed( $msg );
			unset( $json[ '_success' ] );
		}

		// Upgrade is required
		if ( ! empty( $json[ '_err_req_v' ] ) ) {
			Log::debug( '[Cloud] _err_req_v: ' . $json[ '_err_req_v' ] );
			$msg = sprintf( __( '%1$s plugin version %2$s required for this action.', 'litespeed-cache' ), Core::NAME, 'v' . $json[ '_err_req_v' ] . '+' );

			// Append upgrade link
			$msg2 = ' ' . GUI::plugin_upgrade_link( Core::NAME, Core::PLUGIN_NAME, $json[ '_err_req_v' ] );

			$msg2 .= $this->_parse_link( $json );
			Admin_Display::error( $msg . $msg2 );
			return false;
		}

		// Update usage/quota if returned
		if ( ! empty( $json[ 'usage' ] ) ) {
			$this->_summary[ 'usage' . $service ] = $json[ 'usage' ];
			self::save_summary( $this->_summary );
		}

		// Parse general error msg
		if ( empty( $json[ '_res' ] ) || $json[ '_res' ] !== 'ok' ) {
			$json_msg = ! empty( $json[ '_msg' ] ) ? $json[ '_msg' ] : 'unknown';
			Log::debug( '[Cloud] ❌ _err: ' . $json_msg );

			$msg = __( 'Failed to communicate with QUIC.cloud server', 'litespeed-cache' ) . ': ' . Error::msg( $json_msg );
			$msg .= $this->_parse_link( $json );
			Admin_Display::error( $msg );

			return false;
		}

		unset( $json[ '_res' ] );
		if ( ! empty( $json[ '_msg' ] ) ) {
			unset( $json[ '_msg' ] );
		}

		$this->_summary[ 'last_request.' . $service_tag ] = $this->_summary[ 'curr_request.' . $service_tag ];
		$this->_summary[ 'curr_request.' . $service_tag ] = 0;
		self::save_summary( $this->_summary );

		if ( $json ) {
			Log::debug2( '[Cloud] response ok', $json );
		}
		else {
			Log::debug2( '[Cloud] response ok' );
		}

		return $json;

	}

	/**
	 * Parse _links from json
	 *
	 * @since  1.6.5
	 * @since  1.6.7 Self clean the parameter
	 * @access private
	 */
	private function _parse_link( &$json )
	{
		$msg = '';

		if ( ! empty( $json[ '_links' ] ) ) {
			foreach ( $json[ '_links' ] as $v ) {
				$msg .= ' ' . sprintf( '<a href="%s" class="%s" target="_blank">%s</a>', $v[ 'link' ], ! empty( $v[ 'cls' ] ) ? $v[ 'cls' ] : '', $v[ 'title' ] );
			}

			unset( $json[ '_links' ] );
		}

		return $msg;
	}

	/**
	 * Request callback validation from Cloud
	 *
	 * @since  1.5
	 * @access public
	 */
	public function hash()
	{
		if ( empty( $_POST[ 'hash' ] ) ) {
			Log::debug( '[Cloud] Lack of hash param' );
			return self::err( 'lack_of_param' );
		}

		$key_hash = self::get_option( self::DB_HASH );
		if ( $key_hash ) { // One time usage only
			self::delete_option( self::DB_HASH );
		}

		if ( ! $key_hash || $_POST[ 'hash' ] !== md5( $key_hash ) ) {
			Log::debug( '[Cloud] __callback request hash wrong: md5(' . $key_hash . ') !== ' . $_POST[ 'hash' ] );
			return self::err( 'Error hash code' );
		}

		Control::set_nocache( 'Cloud hash validation' );

		Log::debug( '[Cloud] __callback request hash: ' . $key_hash );


		return array( 'hash' => $key_hash );
	}

	/**
	 * Redirect to QUIC to get key, if is CLI, get json [ 'domain_key' => 'asdfasdf' ]
	 *
	 * @since  3.0
	 * @access public
	 */
	public function gen_key()
	{
		$data = array(
			'hash'		=> $this->_hash_make(),
			'site_url'	=> home_url(),
			'email'		=> get_bloginfo( 'admin_email' ),
			'rest'		=> rest_get_url_prefix(),
			'src'		=> defined( 'LITESPEED_CLI' ) ? 'CLI' : 'web',
		);

		if ( ! defined( 'LITESPEED_CLI' ) ) {
			$data[ 'ref' ] = $_SERVER[ 'HTTP_REFERER' ];
			wp_redirect( self::CLOUD_SERVER . '/d/req_key?data=' . Utility::arr2str( $data ) );
			exit;
		}

		// CLI handler
		$response = wp_remote_get( self::CLOUD_SERVER . '/d/req_key?data=' . Utility::arr2str( $data ), array( 'timeout' => 300 ) );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			Log::debug( '[CLoud] failed to gen_key: ' . $error_message );
			Admin_Display::error( __( 'CLoud Error', 'litespeed-cache' ) . ': ' . $error_message );
			return;
		}

		$json = json_decode( $response[ 'body' ], true );
		if ( $json[ '_res' ] != 'ok' ) {
			Log::debug( '[CLoud] error to gen_key: ' . $json[ '_msg' ] );
			Admin_Display::error( __( 'CLoud Error', 'litespeed-cache' ) . ': ' . $json[ '_msg' ] );
			return;
		}

		// Save domain_key option
		$this->_save_api_key( $json[ 'domain_key' ] );

		Admin_Display::succeed( __( 'Generate API key successfully.', 'litespeed-cache' ) );
	}

	/**
	 * Make a hash for callback validation
	 *
	 * @since  3.0
	 */
	private function _hash_make()
	{
		$hash = Str::rrand( 16 );
		// store hash
		self::update_option( self::DB_HASH, $hash );

		return $hash;
	}

	/**
	 * Callback after generated key from QUIC.cloud
	 *
	 * @since  3.0
	 * @access private
	 */
	private function _save_api_key( $api_key )
	{
		// This doesn't need to sync QUIC conf
		Conf::get_instance()->update( Base::O_API_KEY, $api_key );

		Log::debug( '[Cloud] saved auth_key' );
	}

	/**
	 * Return succeeded response
	 *
	 * @since  3.0
	 */
	public static function ok( $data = array() )
	{
		$data[ '_res' ] = 'ok';
		return $data;
	}

	/**
	 * Return error
	 *
	 * @since  3.0
	 */
	public static function err( $code )
	{
		return array( '_res' => 'err', '_msg' => $code );
	}

	/**
	 * Handle all request actions from main cls
	 *
	 * @since  3.0
	 * @access public
	 */
	public static function handler()
	{
		$instance = self::get_instance();

		$type = Router::verify_type();

		switch ( $type ) {
			case self::TYPE_GEN_KEY :
				$instance->gen_key();
				break;

			case self::TYPE_SYNC_USAGE :
				$instance->_sync_usage();
				break;

			default:
				break;
		}

		Admin::redirect();
	}
}