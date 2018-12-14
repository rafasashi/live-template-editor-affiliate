<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class LTPLE_Affiliate_Settings {

	/**
	 * The single instance of LTPLE_Affiliate_Settings.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * The main plugin object.
	 * @var 	object
	 * @access  public
	 * @since 	1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array();

	public function __construct ( $parent ) {
		
		$this->parent = $parent;
		
		$this->plugin 		 	= new stdClass();
		$this->plugin->slug  	= 'live-template-editor-affiliate';
		
		add_action('ltple_plugin_settings', array($this, 'plugin_info' ) );
		
		add_action('ltple_plugin_settings', array($this, 'settings_fields' ) );
		
		add_action( 'ltple_admin_menu' , array( $this, 'add_menu_items' ) );	
	}
	
	public function plugin_info(){
		
		$this->parent->settings->addons['affiliate-program'] = array(
			
			'title' 		=> 'Affiliate Program',
			'addon_link' 	=> 'https://github.com/rafasashi/live-template-editor-affiliate',
			'addon_name' 	=> 'live-template-editor-affiliate',
			'source_url' 	=> 'https://github.com/rafasashi/live-template-editor-affiliate/archive/master.zip',
			'description'	=> 'Affiliate program inluding click tracking and commissions...',
			'author' 		=> 'Rafasashi',
			'author_link' 	=> 'https://profiles.wordpress.org/rafasashi/',
		);		
	}

	/**
	 * Build settings fields
	 * @return array Fields to be displayed on settings page
	 */
	public function settings_fields () {

		$settings = [];
		
		$settings['urls']['fields'][] = array(
		
			'id' 			=> 'affiliateSlug',
			'label'			=> __( 'Affiliate' , $this->plugin->slug ),
			'description'	=> '[ltple-client-affiliate]',
			'type'			=> 'slug',
			'placeholder'	=> __( 'affiliate', $this->plugin->slug )
		);
		
		$settings['affiliate'] = array(
		
			'title'					=> __( 'Affiliate', $this->plugin->slug ),
			'description'			=> __( 'Affiliate settings', $this->plugin->slug ),
			'fields'				=> array(		
				array(
					'id' 			=> 'affiliate_banners',
					'name' 			=> 'affiliate_banners',
					'label'			=> __( 'Affiliate banners' , $this->plugin->slug ),
					'description'	=> '',
					'inputs'		=> 'string',
					'type'			=> 'key_value',
					'placeholder'	=> ['key'=>'image title', 'value'=>'url'],
				),
			)
		);
		
		if( !empty($settings) ){
		
			foreach( $settings as $slug => $data ){
				
				if( isset($this->parent->settings->settings[$slug]['fields']) && !empty($data['fields']) ){
					
					$fields = $this->parent->settings->settings[$slug]['fields'];
					
					$this->parent->settings->settings[$slug]['fields'] = array_merge($fields,$data['fields']);
				}
				else{
					
					$this->parent->settings->settings[$slug] = $data;
				}
			}
		}
	}
	
	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_items () {
		
		//add menu in wordpress dashboard

		add_submenu_page(
			'live-template-editor-client',
			__( 'Affiliate Commissions', $this->plugin->slug ),
			__( 'Affiliate Commissions', $this->plugin->slug ),
			'edit_pages',
			'edit.php?post_type=affiliate-commission'
		);
		
		add_users_page( 
			'All Affiliates', 
			'All Affiliates', 
			'edit_pages',
			'users.php?' . $this->parent->_base .'view=affiliates'
		);
	}
}
