<?php
/*
Plugin Name: WordPress Premium Plugins
Plugin URI: http://www.aa-team.com
Description: Boost your WordPress Experience with over 6,551 Premium Plugins! Search for Premium Plugins right into Codecanyon Marketplace or just browse through the most Popular plugins from Codecanyon. 
Version: 1.0
Author: AA-Team
Author URI: https://codecanyon.net/user/aa-team
*/

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}
/**
 * Current PPS version
 */
if ( ! defined( 'PPS_VERSION' ) ) {
	/**
	 *
	 */
	define( 'PPS_VERSION', '1.0' );
}
/**
 * PPS starts here. Manager sets mode, adds required wp hooks and loads required object of structure
 *
 * Manager controls and access to all modules and classes of PPS.
 *
 * @package PPS
 * @since   1.0
 */

class PremiumPluginsSearch {
	/**
	 * Set status/mode for PPS.
	 *
	 * It depends on what functionality is required from PPS to work with current page/part of WP.
	 *
	 * Possible values:
	 *  none - current status is unknown, default mode;
	 *  page - simple wp page;
	 *  admin_page - wp dashboard;
	 *  admin_frontend_editor - PPS front end editor version;
	 *  admin_settings_page - settings page
	 *  page_editable - inline version for iframe in front end editor;
	 *
	 * @since 1.0
	 * @var string
	 */
	private $mode = 'none';
	
	/**
	 * Enables PPS to act as the theme plugin.
	 *
	 * @since 1.0
	 * @var bool
	 */
	 
	private $is_as_theme = false;
	/**
	 * PPS is network plugin or not.
	 * @since 1.0
	 * @var bool
	 */
	private $is_network_plugin = null;
	
	/**
	 * List of paths.
	 *
	 * @since 1.0
	 * @var array
	 */
	private $paths = array();

	/**
	 * Set updater mode
	 * @since 1.0
	 * @var bool
	 */
	private $disable_updater = false;
	
	/**
	 * Modules and objects instances list
	 * @since 1.0
	 * @var array
	 */
	private $factory = array();
	
	/**
	 * File name for components manifest file.
	 *
	 * @since 4.4
	 * @var string
	 */
	private $components_manifest = 'components.json';
	
	/**
	 * @var string
	 */
	public $plugin_name = 'Additional Variation Images Plugin for WooCommerce';
	public $plugin_desc = 'Showcase product variations by adding any number of additional images for each product variation!';
	public $localizationName = 'PPS';
	public $alias = 'PPS';

	/**
	 * The dashboard object
	 */
	public $dashboard = null;

	/**
	 * The admin object
	 */
	public $admin = null;
	public $frontend = null;
	
	/**
	 * The wp_filesystem object
	 */
	public $wp_filesystem = null;
	
	/**
	 * The wpbd object
	 */
	public $db = null;
	
	public $updater_dev = null;

	private $node_cache_life = 0;
	

	/**
	 * Constructor loads API functions, defines paths and adds required wp actions
	 *
	 * @since  1.0
	 */
	public function __construct() 
	{
		global $wpdb;

		if( defined('UPDATER_DEV') ) {
			$this->updater_dev = (string) UPDATER_DEV;
		}
	
		$dir = dirname( __FILE__ );
		$upload_dir = wp_upload_dir();
		
		/**
		 * Define path settings for PPS.
		 */
		$this->setPaths( array(
			'APP_ROOT' 				=> $dir,
			'WP_ROOT' 				=> preg_replace( '/$\//', '', ABSPATH ),
			'APP_DIR' 				=> basename( $dir ),
			'CONFIG_DIR' 			=> $dir . '/config',
			'ASSETS_DIR' 			=> $dir . '/views',
			'ASSETS_DIR_NAME' 		=> 'views',
			'TEMPLATES_DIR_NAME' 	=> 'templates',
			'SHORTCODES_DIR_NAME' 	=> 'shortcodes',
			'APP_URL'  				=> plugin_dir_url( __FILE__ ),
			'TEMPLATES_URL'  		=> plugin_dir_url( __FILE__ ) . 'templates/',
			'SHORTCODES_URL'  		=> plugin_dir_url( __FILE__ ) . 'shortcodes/',
			'HELPERS_DIR' 			=> $dir . '/include/helpers',
			'DASHBOARD_DIR' 		=> $dir . '/include/dashboard',
			'ADMIN_DIR' 			=> $dir . '/include/admin',
			'FRONTEND_DIR' 			=> $dir . '/include/frontend',
			'INCLUDE_DIR' 			=> $dir . '/include',
			'TEMPLATES_DIR' 		=> $dir . '/templates',
			'INCLUDE_DIR_NAME' 		=> 'include',
			'PARAMS_DIR' 			=> $dir . '/include/params',
			'VENDORS_DIR' 			=> $dir . '/include/classes/vendors',
			'UPLOAD_BASE_DIR'  		=> $upload_dir['basedir'],
			'UPLOAD_BASE_URL'  		=> $upload_dir['baseurl'],
		) );

		// Add hooks
		add_action( 'plugins_loaded', array( $this, 'pluginsLoaded' ), 9 );
		add_action( 'init', array( $this, 'init' ), 9 );
		
		// load WP_Filesystem 
		include_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$this->wp_filesystem = $wp_filesystem;
		
		register_activation_hook( __FILE__, array( $this, 'install' ) );
	}

	/**
	 * Gets updater instance.
	 *
	 * @return AATeam_Product_Updater
	 */
	public function product_updater()
	{
		require_once( $this->path( 'ASSETS_DIR', 'class-updater.php' ) );
		
		if( class_exists('PPS_AATeam_Product_Updater') ){
			$product_data = get_plugin_data( $this->path( 'APP_ROOT', 'plugin.php' ), false );
			new PPS_AATeam_Product_Updater( $this, $product_data['Version'], 'azon-addon-js-composer', 'azon-addon-js-composer/plugin.php' );
		}
	}

	/**
	 * Callback function WP plugin_loaded action hook. Loads locale
	 *
	 * @since  1.0
	 * @access public
	 */
	public function pluginsLoaded() 
	{
		// Setup locale
		do_action( 'PPS_plugins_loaded' );
		load_plugin_textdomain( 'PPS', false, $this->path( 'APP_DIR', 'locale' ) );
	}

	/**
	 * Callback function for WP init action hook. Sets PPS mode and loads required objects.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return void
	 */
	public function init() 
	{
		do_action( 'PPS_before_init' );
		
		global $wpdb;
		$this->db = $wpdb;

		// Set current mode
		$this->setMode();
		
		// Load the admin menu hook
		$this->addMenuPageHooks();
 
		/**
		 * if is admin and not frontend.
		 */
		if( $this->mode === 'admin' ) {
		}
		
		do_action( 'PPS_after_init' );
	}

	public function install()
	{
	}
	
	public function addMenuPageHooks() 
	{
		if ( current_user_can( 'manage_options' ) ) {
			add_action( 'admin_menu', array( &$this, 'addMenuPage' ) );
		}
	}
	
	public function addMenuPage() 
	{
		$page = add_menu_page( __( "Search For Premium Plugins" ),
			__( "Premium Plugins" ),
			'manage_options',
			'PPS',
			array( &$this, 'render' ),
			$this->assetUrl( 'img/plug.png' ) 
		);
 
		add_action( "admin_print_styles", array( &$this, 'adminLoad' ) );
	}
	
	/**
	 * Set up the enqueue for the CSS & JavaScript files.
	 *
	 */
	public function adminLoad()
	{
		wp_enqueue_style( 'PPS-core', $this->assetUrl( 'css/admin.css' ), array(), PPS_VERSION );
		wp_enqueue_script( 'PPS-script', $this->assetUrl( 'js/app.js' ), array(), PPS_VERSION );

		wp_localize_script( 'PPS-script', 'PPS', array(
			'alias' => 'PPS-',
			'ref' => apply_filters( 'PPS_ref', 'AA-Team' ),
			'api_key' => apply_filters( 'PPS_api_key', 'NkBnMIOHmGyEE0VGTA2MdBXBg2KfB8V1' ),
			'categs' => $this->get_categs( 'cc' )
		) );
	}

	/**
	 * Create Render points.
	 *
	 * Loaded interface depends on which page is requested by client from server and request parameters like PPS_action.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @return void
	 */
	public function render()
	{
		echo '<div id="PPS-app"></div>';
	}

	public function get_categs( $site='cc' )
	{
		$file = $this->wp_filesystem->get_contents(  $this->path( 'ASSETS_DIR', 'json/' . ( $site ) . '.categs.js' ) );
		if( !$file ){
			$file = file_get_contents(  $this->path( 'ASSETS_DIR', 'json/' . ( $site ) . '.categs.js' ) );
		}

		$json = json_decode( $file, true );
		if( $json ){
			return $json['categories'];
		}

		return array();
	}
	
	/**
	 * Set PPS mode.
	 *
	 * Mode depends on which page is requested by client from server and request parameters like PPS_action.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @return void
	 */
	protected function setMode() 
	{
		if ( is_admin() ) {
			$this->mode = 'admin';
		} else {
			$this->mode = 'frontend';
		}
	}

	/**
	 * Sets version of the PPS in DB as option `PPS_VERSION`
	 *
	 * @since 1.0
	 * @access protected
	 *
	 * @return void
	 */
	protected function setVersion() 
	{
		$version = get_option( 'PPS_VERSION' );
		if ( ! is_string( $version ) || version_compare( $version, PPS_VERSION ) !== 0 ) {
			add_action( 'PPS_after_init', array( PPS_settings(), 'rebuild' ) );
			update_option( 'PPS_VERSION', PPS_VERSION );
		}
	}

	/**
	 * Get current mode for PPS.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return string
	 */
	public function mode() {
		return $this->mode;
	}

	/**
	 * Setter for paths
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @param $paths
	 */
	protected function setPaths( $paths ) {
		$this->paths = $paths;
	}

	/**
	 * Gets absolute path for file/directory in filesystem.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param $name - name of path dir
	 * @param string $file - file name or directory inside path
	 *
	 * @return string
	 */
	public function path( $name, $file = '' ) {
		$path = $this->paths[ $name ] . ( strlen( $file ) > 0 ? '/' . preg_replace( '/^\//', '', $file ) : '' );

		return apply_filters( 'PPS_path_filter', $path );
	}

	/**
	 * Set default post types. PPS editors are enabled for such kind of posts.
	 *
	 * @param array $type - list of default post types.
	 */
	public function setEditorDefaultPostTypes( array $type ) {
		$this->editor_default_post_types = $type;
	}

	/**
	 * Returns list of default post types where user can use PPS editors.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function editorDefaultPostTypes() {
		return $this->editor_default_post_types;
	}

	/**
	 * Getter for plugin name variable.
	 * @since 1.0
	 *
	 * @return string
	 */
	public function pluginName() 
	{
		return $this->plugin_name;
	}
	
	/**
	 * Get absolute url for PPS asset file.
	 *
	 * Assets are css, javascript, less files and images.
	 *
	 * @since 4.2
	 *
	 * @param $file
	 *
	 * @return string
	 */
	public function includeUrl( $file ) 
	{
		return preg_replace( '/\s/', '%20', plugins_url( $this->path( 'INCLUDE_DIR_NAME', $file ), __FILE__  ) );
	}
	
	/**
	 * Get absolute url for PPS asset file.
	 *
	 * Assets are css, javascript, less files and images.
	 *
	 * @since 4.2
	 *
	 * @param $file
	 *
	 * @return string
	 */
	public function assetUrl( $file ) 
	{
		return preg_replace( '/\s/', '%20', plugins_url( $this->path( 'ASSETS_DIR_NAME', $file ), __FILE__ ) );
	}

	/**
	 * Get absolute url for PPS asset file.
	 *
	 * Assets are css, javascript, less files and images.
	 *
	 * @since 4.2
	 *
	 * @param $file
	 *
	 * @return string
	 */
	public function templatesUrl( $file ) 
	{
		return preg_replace( '/\s/', '%20', plugins_url( $this->path( 'TEMPLATES_DIR_NAME', $file ), __FILE__ ) );
	}

	public function shortcodesUrl( $file ) 
	{
		return preg_replace( '/\s/', '%20', plugins_url( $this->path( 'SHORTCODES_DIR_NAME', $file ), __FILE__ ) );
	}
	
	public function admin_load_styles()
	{
		// admin notices - css styles
		wp_enqueue_style( 'PPS-admin-notices-style', $this->assetUrl( 'admin_notices.css' ), array(), PPS_VERSION );
	}
	
	public function admin_load_scripts() {
		wp_enqueue_media();
	}

	public function prepareForInList($v) {
		return "'".$v."'";
	}

	public function template_path()
	{
		 return apply_filters( 'PPS_template_path', 'templates/' );
	}
	
	public function get_pages()
	{
		$_pages = array();
		$pages = get_pages();
		if( $pages && count($pages) > 0 ){
			foreach ( $pages as $page ) {
				$_pages[$page->ID] = $page->post_title;
			}
		}

		return $_pages;
	}

	public function get_install_site_name()
	{
		$url = home_url();
		$parse = parse_url($url);

		return $parse['host'];
	}
	
	public function woocommerce_install()
	{
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			return true;
		}

		return false;
	}
}

/**
 * Main PPS manager.
 * @var PPS $PPS - instance of composer management.
 * @since 1.0
 */
global $PremiumPluginsSearch;
$PremiumPluginsSearch = new PremiumPluginsSearch();