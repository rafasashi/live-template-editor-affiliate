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
		
		add_action('ltple_settings_fields', array($this, 'settings_fields' ) );
		
		add_action( 'ltple_admin_menu' , array( $this, 'add_menu_items' ) );	
	}

	/**
	 * Build settings fields
	 * @return array Fields to be displayed on settings page
	 */
	public function settings_fields ($settings) {

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

		return $settings;
	}
	
	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_items () {
		
		//add menu in wordpress dashboard

		add_submenu_page(
			'ltple-settings',
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
