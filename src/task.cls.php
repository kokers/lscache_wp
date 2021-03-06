<?php
/**
 * The cron task class.
 *
 * @since      	1.1.3
 * @since  		1.5 Moved into /inc
 */
namespace LiteSpeed ;

defined( 'WPINC' ) || exit ;

class Task extends Instance
{
	protected static $_instance ;

	const HOOK_CRAWLER = 'litespeed_crawl_trigger' ;
	const HOOK_AVATAR = 'litespeed_avatar_trigger' ;
	const HOOK_IMGOPTM = 'litespeed_imgoptm_trigger' ;
	const HOOK_IMGOPTM_AUTO_REQUEST = 'litespeed_imgoptm_auto_request_trigger' ;
	const HOOK_CCSS = 'litespeed_ccss_trigger' ;
	const HOOK_IMG_PLACEHOLDER = 'litespeed_img_placeholder_trigger' ;
	const FITLER_CRAWLER = 'litespeed_crawl_filter' ;
	const FITLER = 'litespeed_filter' ;

	/**
	 * Init
	 *
	 * @since  1.6
	 * @access protected
	 */
	protected function __construct()
	{
		Debug2::debug2( '[Task] init' ) ;

		add_filter( 'cron_schedules', array( $this, 'lscache_cron_filter' ) ) ;

		// Register crawler cron
		if ( Conf::val( Base::O_CRAWLER ) && Router::can_crawl() ) {
			// keep cron intval filter
			$this->_schedule_filter_crawler() ;

			// cron hook
			add_action( self::HOOK_CRAWLER, __NAMESPACE__ . '\Crawler::start' ) ;
		}

		// Register img optimization fetch ( always fetch immediately )
		if ( Conf::val( Base::O_IMG_OPTM_CRON ) ) {
			self::schedule_filter_imgoptm() ;

			add_action( self::HOOK_IMGOPTM, __NAMESPACE__ . '\Img_Optm::cron_pull' ) ;
		}

		// Image optm auto request
		if ( Conf::val( Base::O_IMG_OPTM_AUTO ) ) {
			self::schedule_filter_imgoptm_auto_request() ;

			add_action( self::HOOK_IMGOPTM_AUTO_REQUEST, __NAMESPACE__ . '\Img_Optm::cron_auto_request' ) ;
		}

		// Register ccss generation
		if ( Conf::val( Base::O_OPTM_CCSS_ASYNC ) ) {
			self::schedule_filter_ccss() ;

			add_action( self::HOOK_CCSS, __NAMESPACE__ . '\CSS::cron_ccss' ) ;
		}

		// Register image placeholder generation
		if ( Conf::val( Base::O_MEDIA_PLACEHOLDER_RESP_ASYNC ) ) {
			self::schedule_filter_placeholder() ;

			add_action( self::HOOK_IMG_PLACEHOLDER, __NAMESPACE__ . '\Placeholder::cron' ) ;
		}

		// Register avatar warm up
		if ( Conf::val( Base::O_DISCUSS_AVATAR_CRON ) ) {
			self::schedule_filter_avatar() ;

			add_action( self::HOOK_AVATAR, __NAMESPACE__ . '\Avatar::cron' ) ;
		}
	}

	/**
	 * todo: still need?
	 *
	 * @since 1.1.0
	 * @access public
	 */
	public static function update( $options = false )
	{
		$id = Base::O_CRAWLER ;
		if ( $options && isset( $options[ $id ] ) ) {
			$is_active = $options[$id] ;
		}
		else {
			$is_active = Conf::val( $id ) ;
		}

		if ( ! $is_active ) {
			self::clear() ;
		}

	}

	/**
	 * Schedule cron img optm auto request
	 *
	 * @since 2.4.1
	 * @access public
	 */
	public static function schedule_filter_imgoptm_auto_request()
	{
		// Schedule event here to see if it can lost again or not
		if( ! wp_next_scheduled( self::HOOK_IMGOPTM_AUTO_REQUEST ) ) {
			Debug2::debug( 'Cron log: ......img optm auto request cron hook register......' ) ;
			wp_schedule_event( time(), self::FITLER, self::HOOK_IMGOPTM_AUTO_REQUEST ) ;
		}
	}

	/**
	 * Schedule cron img optimization
	 *
	 * @since 1.6.1
	 * @access public
	 */
	public static function schedule_filter_imgoptm()
	{
		// Schedule event here to see if it can lost again or not
		if( ! wp_next_scheduled( self::HOOK_IMGOPTM ) ) {
			Debug2::debug( 'Cron log: ......img optimization cron hook register......' ) ;
			wp_schedule_event( time(), self::FITLER, self::HOOK_IMGOPTM ) ;
		}
	}

	/**
	 * Schedule cron ccss generation
	 *
	 * @since 2.3
	 * @access public
	 */
	public static function schedule_filter_ccss()
	{
		// Schedule event here to see if it can lost again or not
		if( ! wp_next_scheduled( self::HOOK_CCSS ) ) {
			Debug2::debug( 'Cron log: ......ccss cron hook register......' ) ;
			wp_schedule_event( time(), self::FITLER, self::HOOK_CCSS ) ;
		}
	}

	/**
	 * Schedule cron image placeholder generation
	 *
	 * @since 2.5.1
	 * @access public
	 */
	public static function schedule_filter_placeholder()
	{
		// Schedule event here to see if it can lost again or not
		if( ! wp_next_scheduled( self::HOOK_IMG_PLACEHOLDER ) ) {
			Debug2::debug( 'Cron log: ......image placeholder cron hook register......' ) ;
			wp_schedule_event( time(), self::FITLER, self::HOOK_IMG_PLACEHOLDER ) ;
		}
	}

	/**
	 * Schedule cron avatar
	 *
	 * @since 3.0
	 * @access public
	 */
	public static function schedule_filter_avatar()
	{
		// Schedule event here to see if it can lost again or not
		if( ! wp_next_scheduled( self::HOOK_AVATAR ) ) {
			Debug2::debug( 'Cron log: ......avatar cron hook register......' ) ;
			wp_schedule_event( time(), self::FITLER, self::HOOK_AVATAR ) ;
		}
	}

	/**
	 * Schedule cron crawler
	 *
	 * @since 1.1.0
	 * @access private
	 */
	private function _schedule_filter_crawler()
	{
		add_filter( 'cron_schedules', array( $this, 'lscache_cron_filter_crawler' ) ) ;

		// Schedule event here to see if it can lost again or not
		if( ! wp_next_scheduled( self::HOOK_CRAWLER ) ) {
			Debug2::debug( 'Crawler cron log: ......cron hook register......' ) ;
			wp_schedule_event( time(), self::FITLER_CRAWLER, self::HOOK_CRAWLER ) ;
		}
	}

	/**
	 * Register cron interval imgoptm
	 *
	 * @since 1.6.1
	 * @access public
	 * @param array $schedules WP Hook
	 */
	public function lscache_cron_filter( $schedules )
	{
		if ( ! array_key_exists( self::FITLER, $schedules ) ) {
			$schedules[ self::FITLER ] = array(
				'interval' => 60,
				'display'  => __( 'Every Minute', 'litespeed-cache' ),
			) ;
		}
		return $schedules ;
	}

	/**
	 * Register cron interval
	 *
	 * @since 1.1.0
	 * @access public
	 * @param array $schedules WP Hook
	 */
	public function lscache_cron_filter_crawler( $schedules )
	{
		$interval = Conf::val( Base::O_CRAWLER_RUN_INTERVAL ) ;
		// $wp_schedules = wp_get_schedules() ;
		if ( ! array_key_exists( self::FITLER_CRAWLER, $schedules ) ) {
			// 	Debug2::debug('Crawler cron log: ......cron filter '.$interval.' added......') ;
			$schedules[ self::FITLER_CRAWLER ] = array(
				'interval' => $interval,
				'display'  => __( 'LiteSpeed Cache Custom Cron Crawler', 'litespeed-cache' ),
			) ;
		}
		return $schedules ;
	}

	/**
	 * Clear cron
	 *
	 * @since 1.1.0
	 * @access public
	 */
	public static function clear()
	{
		Debug2::debug( 'Crawler cron log: ......cron hook cleared......' ) ;
		wp_clear_scheduled_hook( self::HOOK_CRAWLER ) ;
	}

}