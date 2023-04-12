<?php

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'LTPLE_Client_Object' ) ) return;
	
class LTPLE_Affiliate extends LTPLE_Client_Object {

	/**
	 * The single instance of LTPLE_Addon.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	var $parent;
	var $list;
	var $status;
	var $view;
	
	var $pourcent_price = 30;
	var $pourcent_fee 	= 20;

	/**
	 * Constructor function
	 */
	public function __construct ( $file='', $parent, $version = '1.0.0' ) {
	
		$this->parent 	= $parent;

		$this->_version = $version;
		$this->_token	= md5($file);
		
		$this->message = '';

		// Load plugin environment variables
		
		$this->file 		= $file;
		$this->dir 			= dirname( $this->file );
		$this->views   		= trailingslashit( $this->dir ) . 'views';
		$this->vendor  		= WP_CONTENT_DIR . '/vendor';
		$this->assets_dir 	= trailingslashit( $this->dir ) . 'assets';
		$this->assets_url 	= home_url( trailingslashit( str_replace( ABSPATH, '', $this->dir ))  . 'assets/' );
		
		//$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$this->script_suffix = '';

		register_activation_hook( $this->file, array( $this, 'install' ) );
		
		// Load frontend JS & CSS
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS
		
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );		
		
		$this->settings = new LTPLE_Affiliate_Settings( $this->parent );
		
		$this->admin 	= new LTPLE_Affiliate_Admin_API( $this );
		
		$this->parent->register_post_type( 'affiliate-commission', __( 'Affiliate commissions', 'live-template-editor-affiliate' ), __( 'Affiliate commission', 'live-template-editor-affiliate' ), '', array(

			'public' 				=> false,
			'publicly_queryable' 	=> false,
			'exclude_from_search' 	=> true,
			'show_ui' 				=> true,
			'show_in_menu'		 	=> 'affiliate-commission',
			'show_in_nav_menus' 	=> false,
			'query_var' 			=> true,
			'can_export' 			=> true,
			'rewrite' 				=> false,
			'capability_type' 		=> 'post',
			'has_archive' 			=> false,
			'hierarchical' 			=> false,
			'show_in_rest' 			=> true,
			//'supports' 			=> array( 'title', 'editor', 'author', 'excerpt', 'comments', 'thumbnail','page-attributes' ),
			'supports' 				=> array( 'title','author' ),
			'menu_position' 		=> 5,
			'menu_icon' 			=> 'dashicons-admin-post',
		));
		
		$this->parent->register_taxonomy( 'commission-status', __( 'Payout', 'live-template-editor-affiliate' ), __( 'Commision status', 'live-template-editor-affiliate' ),  array('affiliate-commission'), array(
			'hierarchical' 			=> false,
			'public' 				=> false,
			'show_ui' 				=> true,
			'show_in_nav_menus' 	=> false,
			'show_tagcloud' 		=> false,
			'meta_box_cb' 			=> null,
			'show_admin_column' 	=> true,
			'update_count_callback' => '',
			'show_in_rest'          => true,
			'rewrite' 				=> false,
			'sort' 					=> '',
		));		
		
		add_action( 'add_meta_boxes', function(){
		
			$this->parent->admin->add_meta_box (
			
				'commission_amount',
				__( 'Amount', 'live-template-editor-client' ), 
				array("affiliate-commission"),
				'side'
			);
			
			$this->parent->admin->add_meta_box (
			
				'tagsdiv-commission-status',
				__( 'Payout', 'live-template-editor-client' ), 
				array("affiliate-commission"),
				'side'
			);
			
			$this->parent->admin->add_meta_box (
			
				'commission_details',
				__( 'Commission Details', 'live-template-editor-client' ), 
				array("affiliate-commission"),
				'advanced'
			);
		});
		
		add_action( 'wp_loaded', array($this,'get_commission_status'));

		add_action( 'ltple_loaded', array( $this, 'init_affiliate' ));
		
		add_action( 'ltple_ref_user_added', array( $this, 'ref_user_added' ));
		
		add_action( 'ltple_ref_users_bulk_added', array( $this, 'ref_users_bulk_added' ));
		
		add_action( 'ltple_list_programs', function(){
			
			$this->parent->programs->list['affiliate'] = 'Affiliate';
		});

		add_action( 'ltple_newsletter_campaign_triggers', function($triggers){
			
			$triggers['ltple_affiliate_approved'] = 'Affiliate Approved';
			
			return $triggers;
		});
		
		add_action( 'ltple_user_loaded', function(){

			// get user affiliate
			
			$this->parent->user->is_affiliate = $this->parent->programs->has_program('affiliate', $this->parent->user->ID, $this->parent->user->programs);
			
			if( $this->parent->user->is_affiliate ){

				$this->parent->user->affiliate_clicks 		= $this->get_affiliate_counter($this->parent->user->ID, 'clicks');
				$this->parent->user->affiliate_referrals 	= $this->get_affiliate_counter($this->parent->user->ID, 'referrals');
				$this->parent->user->affiliate_commission 	= $this->get_affiliate_counter($this->parent->user->ID, 'commission');
			}
			
			if( is_admin() ){
				
				// get user referrals
		
				$this->parent->user->referrals = get_user_meta($this->parent->user->ID,$this->parent->_base . 'referrals',true);

				if(strpos($_SERVER['SCRIPT_NAME'],'user-edit.php') > 0 && isset($_REQUEST['user_id']) ){
					
					$this->parent->editedUser->programs 			= json_decode( get_user_meta( $this->parent->editedUser->ID, $this->parent->_base . 'user-programs',true) );
					$this->parent->editedUser->affiliate_clicks 	= $this->get_affiliate_counter($this->parent->editedUser->ID, 'clicks');
					$this->parent->editedUser->affiliate_referrals 	= $this->get_affiliate_counter($this->parent->editedUser->ID, 'referrals');
					$this->parent->editedUser->affiliate_commission = $this->get_affiliate_counter($this->parent->editedUser->ID, 'commission');
					$this->parent->editedUser->referrals 			= get_user_meta($this->parent->editedUser->ID,$this->parent->_base . 'referrals',true);
				}
			}
		});

		add_action('ltple_plan_subscribed', array($this,'set_affiliate_commission'),10,2);
		
		// add panel shortocode
		
		add_shortcode('ltple-client-affiliate', array( $this , 'get_panel_shortcode' ) );	
			
		// add columns
		
		add_filter('manage_affiliate-commission_posts_columns', array( $this, 'filter_columns'));
		add_action('manage_affiliate-commission_posts_custom_column', array( $this, 'add_column_content'), 10, 2);
				
		// add panel url
		
		add_filter( 'ltple_urls', array( $this, 'get_panel_url'));			
		
		// add link to theme menu
		
		add_action( 'ltple_view_my_profile', function(){
			
			echo'<li style="position:relative;background:#182f42;">';
				
				echo '<a href="'. $this->parent->urls->affiliate .'"><span class="glyphicon glyphicon-usd" aria-hidden="true"></span> Affiliate Program</a>';

			echo'</li>';
		},10);
	}
	

	public function filter_columns($columns){
		
		//$columns['bulk'] = 'Edit'; // uncomment to bulk edit status
		
		return $columns;		
	}
	
	public function add_column_content($column_name, $post_id){
		
		if( $column_name === 'bulk' ) {
			
			$post = get_post($post_id);
			
			if( intval($post->post_author) == 8671 ){
				
				wp_set_object_terms($post_id,array('paid'),'commission-status',false);
			}
		}	

		return $column_name;
	}
	
	
	public function get_panel_url(){
		
		$slug = get_option( $this->parent->_base . 'affiliateSlug' );
		
		if( empty( $slug ) ){
			
			$post_id = wp_insert_post( array(
			
				'post_title' 		=> 'Affiliate',
				'post_type'     	=> 'page',
				'comment_status' 	=> 'closed',
				'ping_status' 		=> 'closed',
				'post_content' 		=> '[ltple-client-affiliate]',
				'post_status' 		=> 'publish',
				'menu_order' 		=> 0
			));
			
			$slug = update_option( $this->parent->_base . 'affiliateSlug', get_post($post_id)->post_name );
		}
		
		$this->parent->urls->affiliate = $this->parent->urls->home . '/' . $slug . '/';		
	}	
	
	public function get_panel_shortcode(){
		
		include($this->parent->views . '/navbar.php');
		
		include($this->views . '/affiliate.php');
	}

	public function get_commission_status(){

		if( is_null($this->status) ){

			$this->status = $this->get_terms( 'commission-status', array(
					
				'pending'  	=> 'Pending',
				'paid'  	=> 'Paid',
			));
		}
		
		return $this->status;
	}

	public function get_affiliate_commission_fields(){
				
		$fields=[];
		
		// get post id
		
		$post_id=get_the_ID();

		// get options
		
		$options = [];
		
		$terms = $this->get_commission_status();
		
		foreach($terms as $term){
			
			$options[$term->slug] = $term->name;
		}
		
		$terms = wp_get_post_terms( $post_id, 'commission-status' );
		
		$fields[]=array(
			"metabox" =>
				array('name'		=> "tagsdiv-commission-status"),
				'id'				=> "new-tag-commission-status",
				'name'				=> 'tax_input[commission-status]',
				'label'				=> "",
				'type'				=> 'select',
				'options'			=> $options,
				'data'				=> isset($terms[0]->slug) ? $terms[0]->slug : 'pending',
				'description'		=> ''
		);
		
		// get commission amount
		
		$fields[]=array(
		
			"metabox" =>
			
				array('name'	=>"commission_amount"),
				'id'			=>"commission_amount",
				'label'			=>"",
				'type'			=>'number',
				'placeholder'	=>"0",
				'description'	=>''
		);		
		
		// get commission details
		
		$fields[]=array(
		
			"metabox" =>
			
				array('name'	=> "commission_details"),
				'id'			=> "commission_details",
				'label'			=> "",
				'type'			=> 'textarea',
				'placeholder'	=> "JSON",
				'description'	=> ''
		);
		
		return $fields;
	}

	public function init_affiliate(){
		
		if( is_admin() ){

			add_action('load-users.php', function(){
				
				if( isset($_REQUEST['ltple_view']) ){
				
					$this->view = sanitize_title($_REQUEST['ltple_view']);
				}	
		
				// add tab in user panel
				
				add_action( 'ltple_users_tabs', array($this, 'add_affiliate_tab' ) );
				
				if( $this->view == 'affiliates' ){
				
					// filter affiliate users
					
					add_filter( 'pre_get_users', array( $this, 'filter_affiliates') );

					// custom users columns
					
					add_filter('manage_users_columns', array($this, 'update_affiliates_table'),99999, 1);
					
					add_filter('manage_users_custom_column', array($this, 'modify_affiliates_table_row'), 100, 3);	
				}
			});
			
			// get affiliate fields
			
			add_filter('affiliate-commission_custom_fields', array( $this, 'get_affiliate_commission_fields' ));

			
			// add affiliate field
			
			add_action( 'show_user_profile', array( $this, 'get_user_referrals' ) );
			add_action( 'edit_user_profile', array( $this, 'get_user_referrals' ) );		
			
			// save user programs
			
			add_action( 'personal_options_update', array( $this, 'save_user_affiliate' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_user_affiliate' ) );
		}
		else{
			
			if( strpos( $this->parent->urls->current, $this->parent->urls->affiliate ) === 0 ){
				
				$this->banners = get_option($this->parent->_base . 'affiliate_banners');
				
				if( !empty($_POST[$this->parent->_base . '_paypal_email']) ){
					
					if( $email = sanitize_email( $_POST[$this->parent->_base . '_paypal_email'] ) ){
						
						update_user_meta($this->parent->user->ID, $this->parent->_base . '_paypal_email', $email);
					}
				}
			}	
		
			if( !empty($this->parent->request->ref_id) && !$this->parent->user->loggedin ){
					
				$this->set_affiliate_counter($this->parent->request->ref_id, 'clicks', $this->parent->request->ip );
			
				do_action( 'ltple_referred_click' );
			}			
		}
	}

	public function filter_affiliates( $query ) {

		// alter the user query to add my meta_query
		
		$query->set( 'meta_query', array(
		
			array(
			
				'key' 		=> $this->parent->_base . 'user-programs',
				'value' 	=> 'affiliate',
				'compare' 	=> 'LIKE'
			),
		));
	}

	public function add_affiliate_tab(){
		
		echo '<a class="nav-tab ' . ( $this->view == 'affiliates' ? 'nav-tab-active' : '' ) . '" href="users.php?ltple_view=affiliates">Affiliates</a>';
	}

	public function update_affiliates_table($columns) {
		
		$columns=[];
		
		$columns["cb"]			= '<input type="checkbox" />';
		$columns["username"]	= 'Username';
		$columns["email"]		= 'Email';
		$columns["clicks"]		= 'Clicks';	
		$columns["referrals"]	= 'Referrals';
		$columns["commission"]	= 'Commission';
		
		return $columns;
	}

	public function modify_affiliates_table_row($row, $column_name, $user_id) {
		
		if(!isset($this->list->{$user_id})){
		
			if( empty($this->list) ){
				
				$this->list	= new stdClass();
			}			
		
			$this->list->{$user_id} 				= new stdClass();
			$this->list->{$user_id}->clicks 		= $this->get_affiliate_counter($user_id, 'clicks');
			$this->list->{$user_id}->referrals 		= $this->get_affiliate_counter($user_id, 'referrals');
			$this->list->{$user_id}->commission 	= $this->get_affiliate_counter($user_id, 'commission');
		}

		if ($column_name == "clicks") { 
				
			$row .= $this->list->{$user_id}->clicks['total'];
		}
		elseif ($column_name == "referrals") {
				
			$row .= $this->list->{$user_id}->referrals['total'];
		}
		elseif ($column_name == "commission") {
			
			$row .= '$' . $this->list->{$user_id}->commission['total'];
		}
		
		return $row;
	}

	public function get_affiliate_counter($user_id, $type = 'clicks'){
		
		$counter = get_user_meta( $user_id, $this->parent->_base . 'affiliate_'.$type, true);
		
		if( empty($counter) ){

			$counter = [];
			
			$counter['today'] = [];
			$counter['week']  = [];
			$counter['month'] = [];
			$counter['year']  = [];
			$counter['total'] = 0;
		}
		
		$z 	= date('z'); //day of the year
		$w 	= date('W'); //week of the year
		$m 	= date('m'); //month of the year
		$y 	= date('Y'); //year				
		
		// set today
		
		if(!isset($counter['today'][$y][$z])){
			
			$counter['today'] = [ $y => [ $z => [] ] ]; // reset array
		}		

		// set week
		
		if(!isset($counter['week'][$y][$w])){
			
			$counter['week'] = [ $y => [ $w => 0 ] ]; // reset array
		}
		
		// set month
		
		if(!isset($counter['month'][$y][$m])){
			
			$counter['month'][$y][$m] = 0; // append array
		}			

		// set year
		
		if(!isset($counter['year'][$y])){
			
			$counter['year'][$y] = 0; // append array
		}
		
		return $counter;
	}

	public function get_user_commission($user_id,$period='all'){
		
		$total = 0;
		
		if( $period == 'all' ){

			if( $commissions = get_posts(array(
				
				'author'      	=> $user_id,
				'post_type'   	=> 'affiliate-commission',
				'post_status' 	=> 'publish',
				'numberposts' 	=> -1,
				
			))){
				
				foreach( $commissions as $commission ){
					
					$amount = floatval(get_post_meta( $commission->ID, 'commission_amount', true ));
				
					$total += $amount;
				}
			}
		}
		
		return $total;
	}

	public function set_affiliate_counter($user_id, $type = 'clicks', $id, $counter = null){
		
		$z 	= date('z'); //day of the year
		$w 	= date('W'); //week of the year
		$m 	= date('m'); //month of the year
		$y 	= date('Y'); //year
		
		if(is_null($counter)){
			
			$counter = $this->get_affiliate_counter( $user_id, $type );
		}
		
		if( !isset($counter['today'][$y][$z]) || !in_array($id,$counter['today'][$y][$z]) ){
		
			if($type == 'commission'){
				
				$amount = explode('_',$id);
				
				$amount = floatval($amount[1]);
				
				// set today
				
				$counter['today'][$y][$z][$id] = $amount;
				
				// set week

				$counter['week'][$y][$w] += $amount;
				
				// set month
				
				$counter['month'][$y][$m] += $amount;
				
				// set year
				
				$counter['year'][$y] += $amount;
				
				// set total
				
				$counter['total'] = $this->get_user_commission($user_id);			
			}
			else{
				
				// set today
				
				$counter['today'][$y][$z][] = $id;
				
				// set week

				++$counter['week'][$y][$w];
				
				// set month
				
				++$counter['month'][$y][$m];
				
				// set year
				
				++$counter['year'][$y];
				
				// set total
				
				++$counter['total'];				
			}
			
			// update counter
			
			update_user_meta( $user_id, $this->parent->_base . 'affiliate_'.$type, $counter);
		}
		
		return $counter;
	}

	public function set_affiliate_commission($plan,$user){
		
		$currency='$';
		
		$json = json_encode($plan,JSON_PRETTY_PRINT);

		$id = md5($json);

		$user_id = $user->ID;

		$pourcent_price = $this->pourcent_price;
		$pourcent_fee 	= $this->pourcent_fee;
		
		$price 	= isset($plan['price']) ? $plan['price'] : 0;
		$fee 	= isset($plan['fee']) ? $plan['fee'] : 0;
		
		$total = ( $price + $fee );
		
		$amount =  ( ( $price * ( $pourcent_price / 100 ) ) + ( $fee * ( $pourcent_fee / 100 ) ) );
		
		if( $amount > 0 && empty($plan['upgrade']) ){
			
			$pourcent = ( $total > 0 ? ( ( $amount / $total ) * 100 ) : 0 );
			
			// handle affiliate commission
			
			if( $referredBy = get_user_meta($user_id, $this->parent->_base . 'referredBy', true) ){
				
				if( $affiliate = get_user_by('id',key($referredBy)) ){
					
					// get commission
					
					$q = get_posts(array(
					
						'name'        => $id . '_' . $amount,
						'post_type'   => 'affiliate-commission',
						'post_status' => 'publish',
						'numberposts' => 1
					));
					
					if( empty($q) ){
						
						// get pending term id
						
						$pending_id = false;
						
						$status = $this->get_commission_status();
						
						foreach( $status as $status ){
							
							if( $status->slug == 'pending' ){
								
								$pending_id = $status->term_id;
								break;
							}
						}
						
						if( $pending_id ){
					
							// insert commission

							if( $commission_id = wp_insert_post(array(
						
								'post_author' 	=> $affiliate->ID,
								'post_title' 	=> $currency . $amount . ' over ' . $currency . $total . ' (' . $pourcent . '%)',
								'post_name' 	=> $id . '_' . $amount,
								'post_type' 	=> 'affiliate-commission',
								'post_status' 	=> 'publish'
							))){

								// update commission details

								wp_set_object_terms($commission_id, $pending_id, 'commission-status' );
								
								update_post_meta( $commission_id, 'commission_details', $json);	

								update_post_meta( $commission_id, 'commission_amount', $amount);
							
								// set commission counter
							
								$this->set_affiliate_counter($affiliate->ID, 'commission', $id . '_' . $amount);
								
								// send notification
								
								$company	= ucfirst(get_bloginfo('name'));
								
								$dashboard_url = $this->parent->urls->home . '/affiliate/';
								
								$title 		= 'Commission of ' . $currency . $amount . ' from ' . $company;
								
								$content 	= '';
								$content 	.= 'Congratulations ' . ucfirst($affiliate->user_nicename) . '! You have just received a commission of ' . $currency . $amount . '. You can view the full details of this commission in your dashboard:' . PHP_EOL . PHP_EOL;

								$content 	.= '	' . $dashboard_url . '#overview' . PHP_EOL . PHP_EOL;

								$content 	.= 'We\'ll be here to help you with any step along the way. You can find answers to most questions and get in touch with us at '. $dashboard_url . '#rules' . PHP_EOL . PHP_EOL;

								$content 	.= 'Yours,' . PHP_EOL;
								$content 	.= 'The ' . $company . ' team' . PHP_EOL . PHP_EOL;

								$content 	.= '==== Commission Summary ====' . PHP_EOL . PHP_EOL;

								$content 	.= 'Plan purchased: ' . $plan['name'] . PHP_EOL;
								$content 	.= 'Total amount: ' . $currency . $total . PHP_EOL;
								$content 	.= 'Percentage: ' . $pourcent . '%' . PHP_EOL;
								$content 	.= 'Your commission: ' . $currency . $amount . PHP_EOL;
								//$content 	.= 'Customer email: ' . $plan['subscriber'] . PHP_EOL;
								
								wp_mail($affiliate->user_email, $title, $content);
								
								if( $this->parent->settings->options->emailSupport != $affiliate->user_email ){
									
									wp_mail($this->parent->settings->options->emailSupport, $title, $content);
								}
							}
						}
						else{
							
							//echo 'Error getting pending term...';
							//exit;
						}
					}
				}
			}
		}
	}

	public function get_affiliate_balance($user_id, $currency='$'){
		
		$balance = 0;
		
		$q = get_posts(array(
		
			'author'      	=> $user_id,
			'post_type'   	=> 'affiliate-commission',
			'post_status' 	=> 'publish',
			'numberposts' 	=> -1,
			'tax_query'  	=> array(
			
				array(
				
					'taxonomy' 	=> 'commission-status',
					'field' 	=> 'slug',
					'terms' 	=> 'pending',	
				),
			),
		));
		
		if( !empty($q) ){
			
			foreach( $q as $commission ){
				
				$amount = get_post_meta( $commission->ID, 'commission_amount', true );
				
				$balance += floatval($amount);
			}
		}
		
		return $currency . number_format($balance, 2, '.', '');
	}

	public function ref_user_added(){
				
		if( is_numeric( $this->parent->request->ref_id ) ){
			
			if( !empty($this->parent->users->referent->ID) && !empty($this->parent->users->referral->ID)){
			
				//set referral counter
				
				$this->set_affiliate_counter($this->parent->users->referent->ID, 'referrals', $this->parent->users->referral->ID );				
				
				// send notification
				
				$company	= ucfirst(get_bloginfo('name'));
				
				$dashboard_url = $this->parent->urls->home . '/affiliate/';
				
				$title 		= 'New referral user registration on ' . $company;
				
				$content 	= '';
				$content 	.= 'Congratulations ' . ucfirst($this->parent->users->referent->user_nicename) . '! A new user registration has been made using your affiliate ID. You can view the full details of your affiliate program in your dashboard:' . PHP_EOL . PHP_EOL;

				$content 	.= '	' . $dashboard_url . '#overview' . PHP_EOL . PHP_EOL;

				$content 	.= 'We\'ll be here to help you with any step along the way. You can find answers to most questions and get in touch with us at '. $dashboard_url . '#rules' . PHP_EOL . PHP_EOL;

				$content 	.= 'Yours,' . PHP_EOL;
				$content 	.= 'The ' . $company . ' team' . PHP_EOL . PHP_EOL;

				$content 	.= '==== Registration Summary ====' . PHP_EOL . PHP_EOL;

				$content 	.= 'Referral name: ' . ucfirst($this->parent->users->referral->user_nicename) . PHP_EOL;
				$content 	.= 'Referral email: ' . $this->parent->users->referral->user_email . PHP_EOL;
				
				wp_mail($this->parent->users->referent->user_email, $title, $content);
				
				if( $this->parent->settings->options->emailSupport != $this->parent->users->referent->user_email ){
					
					wp_mail($this->parent->settings->options->emailSupport, $title, $content);
				}
			}
		}
	}


	public function ref_users_bulk_added(){
				
		if( !empty($this->parent->users->referrals) ){
			
			if( !empty($this->parent->users->referent->ID) ){

				//set referral counter
			
				foreach($this->parent->users->referrals as $referral){

					$this->set_affiliate_counter($this->parent->users->referent->ID, 'referrals', $referral->ID );
				}
				
				// send notification
				
				$company	= ucfirst(get_bloginfo('name'));
				$count		= count($this->parent->users->referrals);
				
				$dashboard_url = $this->parent->urls->home . '/affiliate/';
				
				$title 		= 'Referral users imported on ' . $company;
				
				$content 	= '';
				$content 	.= 'Congratulations ' . ucfirst($this->parent->users->referent->user_nicename) . '! ' . $count . ' new ' . ( $count == 1 ? 'email has' : 'emails have' ) . ' been imported with your affiliate ID. You can view the full details of your affiliate program in your dashboard:' . PHP_EOL . PHP_EOL;

				$content 	.= '	' . $dashboard_url . '#overview' . PHP_EOL . PHP_EOL;

				$content 	.= 'We\'ll be here to help you with any step along the way. You can find answers to most questions and get in touch with us at '. $dashboard_url . '#rules' . PHP_EOL . PHP_EOL;

				$content 	.= 'Yours,' . PHP_EOL;
				$content 	.= 'The ' . $company . ' team' . PHP_EOL . PHP_EOL;

				$content 	.= '==== Registration Summary ====' . PHP_EOL . PHP_EOL;
				
				$i = 1;
				
				foreach( $this->parent->users->referrals as $referral){
				
					$content 	.= 'Referral : ' . $referral->user_email . ' (' . ucfirst($referral->user_nicename) . ')' . PHP_EOL;
					
					if( $i == 10 ){
						
						if( $count > $i ){
							
							$content 	.= ' and ' . ( $count - $i ). ' more...' . PHP_EOL;
						}
						
						break;
					}
					else{
						
						$i++;
					}
				}
				
				wp_mail($this->parent->users->referent->user_email, $title, $content);
				
				if( $this->parent->settings->options->emailSupport != $this->parent->users->referent->user_email ){
					
					wp_mail($this->parent->settings->options->emailSupport, $title, $content);
				}
			}
		}
	}

	public function get_affiliate_overview( $counter, $sum = false, $pre = '', $app = '' ){
		
		if( !empty($counter) ){
			
			$z 	= date('z'); //day of the year
			$w 	= date('W'); //week of the year
			$m 	= date('m'); //month of the year
			$y 	= date('Y'); //year		
				
			echo'<table class="table table-striped table-hover">';
			
				echo'<tbody>';
					
					// today
					
					echo'<tr style="font-size:18px;font-weight:bold;">';
					
						echo'<td>';
							echo'Today';
						echo'</td>';

						echo'<td>';
						
							if($sum){
								
								$today = 0;
								
								foreach( $counter['today'][$y][$z] as $value){
									
									$today += $value;
								}

								echo $pre . number_format($today, 2, '.', '').$app;
							}
							else{
								
								// count
								
								echo $pre.count($counter['today'][$y][$z]).$app;
							}
							
						echo'</td>';													
					
					echo'</tr>';
					
					// week
					
					echo'<tr>';
					
						echo'<td>';
							echo'Week';
						echo'</td>';

						echo'<td>';
							
							if($sum){
								
								echo $pre.number_format($counter['week'][$y][$w], 2, '.', '').$app;
							}
							else{
								
								echo $pre.$counter['week'][$y][$w].$app;
							}
							
						echo'</td>';													
					
					echo'</tr>';
					
					// month
					
					echo'<tr>';
					
						echo'<td>';
							echo'Month';
						echo'</td>';

						echo'<td>';
						
							if($sum){
								
								echo $pre.number_format($counter['month'][$y][$m], 2, '.', '').$app;
							}
							else{
								
								echo $pre.$counter['month'][$y][$m].$app;
							}
							
						echo'</td>';													
					
					echo'</tr>';
					
					// Total
					
					echo'<tr>';
					
						echo'<td>';
							echo'All Time';
						echo'</td>';

						echo'<td>';
						
							if($sum){
								
								echo $pre.number_format($counter['total'], 2, '.', '').$app;
							}
							else{
								
								echo $pre.$counter['total'].$app;
							}
							
						echo'</td>';													
					
					echo'</tr>';
					
				echo'</tbody>';
			
			echo'</table>';	
		}		
	}

	public function get_user_referrals( $user ) {
		
		if( current_user_can( 'administrator' ) ){
			
			echo '<div class="postbox" style="min-height:45px;">';
				
				//echo '<h3 style="margin:10px;width:300px;display: inline-block;">' . __( 'Referrals', 'live-template-editor-client' ) . '</h3>';
				
				echo '<table class="widefat fixed striped" style="border:none;">';
					
					echo '<thead>';
					
						echo'<tr>';
						
							echo'<td>';
								echo'<h3 style="margin:0;">Clicks</h3>';
							echo'</td>';
						
							echo'<td>';
								echo'<h3 style="margin:0;">Referrals</h3>';
							echo'</td>';
						
							echo'<td>';
								echo'<h3 style="margin:0;">Commission</h3>';
							echo'</td>';
						
						echo'</tr>';
					
					echo '</thead>';
					
					echo '<tbody>';
					
						echo'<tr>';
						
							echo'<td>';
							
								$this->get_affiliate_overview($this->parent->editedUser->affiliate_clicks);						
								
								if( $this->parent->user->is_admin ){

									echo '<table>';
									
										echo'<tr>';
										
											echo'<td>';
											
												echo 'Add / Remove';					
												
											echo'</td>';									
											
											echo'<td>';								
								
												echo $this->parent->admin->display_field( array(
										
													'type'				=> 'number',
													'id'				=> $this->parent->_base . 'aff_clicks',
													'description'		=> '',
													'default'			=> 0,
													
												), false, false );
												
											echo'</td>';

										echo'</tr>';
										
									echo '</table>';
								}								
								
							echo'</td>';	
							
							echo'<td>';
							
								$this->get_affiliate_overview($this->parent->editedUser->affiliate_referrals);							

								if( $this->parent->user->is_admin ){

									echo '<table>';
									
										echo'<tr>';
										
											echo'<td>';
											
												echo 'Add / Remove';					
												
											echo'</td>';									
											
											echo'<td>';								
								
												echo $this->parent->admin->display_field( array(
										
													'type'				=> 'number',
													'id'				=> $this->parent->_base . 'aff_referrals',
													'description'		=> '',
													'default'			=> 0,
													
												), false, false );
												
											echo'</td>';

										echo'</tr>';
										
									echo '</table>';
								}
								
							echo'</td>';									
							
							echo'<td>';

								$this->get_affiliate_overview($this->parent->editedUser->affiliate_commission,true,'$');																	
								
								if( $this->parent->user->is_admin ){

									echo '<table>';
									
										echo'<tr>';
										
											echo'<td>';
											
												echo 'Add / Remove';					
												
											echo'</td>';									
											
											echo'<td>';								
								
												echo $this->parent->admin->display_field( array(
										
													'type'				=> 'number',
													'id'				=> $this->parent->_base . 'aff_commission',
													'description'		=> '',
													'default'			=> 0,
													
												), false, false );
												
											echo'</td>';

										echo'</tr>';
										
									echo '</table>';
								}								
								
							echo'</td>';

						echo'</tr>';
						
						echo'<tr>';
						
							echo'<td>';
								echo'<i>';
									echo'* daily unique IPs';	
								echo'</i>';
							echo'</td>';
						
							echo'<td>';
								echo'<i>';
									echo'* new user registrations';	
								echo'</i>';
							echo'</td>';
						
							echo'<td>';
								echo'<i>';
									echo'* new plan subscriptions';	
								echo'</i>';
							echo'</td>';
						
						echo'</tr>';
						
					echo '</tbody>';

				echo '</table>';
					
			echo'</div>';
			
			echo'<div class="postbox" style="min-height:45px;">';
				
				echo'<h3 style="margin:10px;width:300px;display: inline-block;">Pending balance</h3>';
				
				echo'<div style="display:inline-block;">';
					
					echo $this->get_affiliate_balance($this->parent->editedUser->ID);
					
				echo'</div>';
				
			echo'</div>';
			
			/*
			if( !empty($this->parent->editedUser->referrals) ){
				
				echo '<div class="postbox" style="min-height:45px;">';
					
					echo '<h3 style="margin:10px;width:300px;display:inline-block;">' . __( 'All Referrals', 'live-template-editor-client' ) . '</h3>';
							
					echo '<table class="widefat fixed striped" style="border:none;">';
							
						echo '<tbody>';					
						
							$i=0;
							
							foreach($this->parent->editedUser->referrals as $id => $name){
								
								if(is_string($name)){
									
									if($i==0){
										
										echo'<tr>';
									}
									
									echo'<td>';
									
										echo'<a href="'.admin_url( 'user-edit.php' ).'?user_id='.$id.'">'.$name.'</a>';
									
									echo'</td>';

									if( $i < 4 ){
										
										++$i;
									}
									else{
										
										$i=0;
										
										echo'</tr>';
									}	
								}
							}
						
						echo '</tbody>';

					echo '</table>';
					
				echo'</div>';
			}
			*/
		}	
	}

	public function save_user_affiliate( $user_id ) {

		$field = $this->parent->_base . 'user-programs';
		
		if( isset($_POST[$field]) && in_array( 'affiliate', $_POST[$field]) ){

			do_action('ltple_affiliate_approved',$user_id);
		}
		
		$field = $this->parent->_base . 'aff_clicks';
		
		if( isset($_POST[$field]) && is_numeric($_POST[$field]) ){
			
			$amount = intval($_POST[$field]);
			
			$this->set_affiliate_counter($user_id, 'clicks', 'updated-'.time() . '_' . $amount);
		}

		$field = $this->parent->_base . 'aff_referrals';
		
		if( isset($_POST[$field]) && is_numeric($_POST[$field]) ){
			
			$amount = intval($_POST[$field]);
			
			$this->set_affiliate_counter($user_id, 'referrals', 'updated-'.time() . '_' . $amount);
		}		
		
		$field = $this->parent->_base . 'aff_commission';
		
		if( isset($_POST[$field]) && is_numeric($_POST[$field]) ){
			
			$amount = intval($_POST[$field]);
			
			$this->set_affiliate_counter($user_id, 'commission', 'updated-'.time() . '_' . $amount);
		}
	}

	public function schedule_invitations(){
		
		//TODO
	}	

	/**
	 * Wrapper function to register a new post type
	 * @param  string $post_type   Post type name
	 * @param  string $plural      Post type item plural name
	 * @param  string $single      Post type item single name
	 * @param  string $description Description of post type
	 * @return object              Post type class object
	 */
	public function register_post_type ( $post_type = '', $plural = '', $single = '', $description = '', $options = array() ) {

		if ( ! $post_type || ! $plural || ! $single ) return;

		$post_type = new LTPLE_Client_Post_Type( $post_type, $plural, $single, $description, $options );

		return $post_type;
	}

	/**
	 * Wrapper function to register a new taxonomy
	 * @param  string $taxonomy   Taxonomy name
	 * @param  string $plural     Taxonomy single name
	 * @param  string $single     Taxonomy plural name
	 * @param  array  $post_types Post types to which this taxonomy applies
	 * @return object             Taxonomy class object
	 */
	public function register_taxonomy ( $taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) return;

		$taxonomy = new LTPLE_Client_Taxonomy( $taxonomy, $plural, $single, $post_types, $taxonomy_args );

		return $taxonomy;
	}

	/**
	 * Load frontend CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function enqueue_styles () {
		
		//wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		//wp_enqueue_style( $this->_token . '-frontend' );
	
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function enqueue_scripts () {
		
		//wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		//wp_enqueue_script( $this->_token . '-frontend' );
	
	} // End enqueue_scripts ()

	/**
	 * Load admin CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_styles ( $hook = '' ) {
		
		//wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		//wp_enqueue_style( $this->_token . '-admin' );
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_scripts ( $hook = '' ) {
		
		//wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		//wp_enqueue_script( $this->_token . '-admin' );
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation () {
		
		load_plugin_textdomain( $this->settings->plugin->slug, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
		
		$domain = $this->settings->plugin->slug;

		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main LTPLE_Addon Instance
	 *
	 * Ensures only one instance of LTPLE_Addon is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see LTPLE_Addon()
	 * @return Main LTPLE_Addon instance
	 */
	public static function instance ( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

}
