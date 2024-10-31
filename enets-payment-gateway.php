<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/*
Plugin Name: eNets IPG
Plugin URI: enets.oganro.net
Description: eNets Payment Gateway from Oganro (Pvt)Ltd.
Version: 1.0
Author: Oganro
Author URI: www.oganro.com
*/

//-----------------------------------------------------
// Initiating Methods to run on plugin activation
// ----------------------------------------------------
register_activation_hook( __FILE__, 'ogn_data_table' );


global $jal_db_version;
$jal_db_version = '1.0';

//-----------------------------------------------------
// Methods to create database table
// ----------------------------------------------------
function ogn_data_table() {
	
	$plugin_path = plugin_dir_path( __FILE__ );
	$file = $plugin_path.'includes/auth.php';
  	if(file_exists($file)){
  		include 'includes/auth.php';
  		$auth = new Auth();
  		$auth->check_auth();
  		if ( !$auth->get_status() ) {
  			deactivate_plugins( plugin_basename( __FILE__ ) );
			if($auth->get_code() == 2){
				wp_die( "<h1>".ucfirst($auth->get_message())."</h1><br>Visit <a href='http://www.oganro.com/plugin/profile'>www.oganro.com/profile</a> and change the domain" ,"Activation Error","ltr" );
			}else{
				wp_die( "<h1>".ucfirst($auth->get_message())."</h1><br>Visit <a href='http://www.oganro.com'>www.oganro.com</a> for more info" ,"Activation Error","ltr" );
			}
		}
  	}else{
  		deactivate_plugins( plugin_basename( __FILE__ ) );
  		wp_die( "<h1>Buy serial key to activate this plugin</h1><br><img src=".site_url('wp-content/plugins/sampath_paycorp_ipg/support.jpg')." style='width:700px;height:auto;' /><p>Visit <a href='http://www.oganro.com/plugins'>www.oganro.com/plugins</a> to buy this plugin<p>" ,"Activation Error","ltr" );
  	}
	
	global $wpdb;
	global $jal_db_version;

	$table_name = $wpdb->prefix . 'ogn_enets_ipg';
	$charset_collate = '';

	if ( ! empty( $wpdb->charset ) ) {
		$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
	}

	if ( ! empty( $wpdb->collate ) ) {
		$charset_collate .= " COLLATE {$wpdb->collate}";
	}

	$sql = "CREATE TABLE $table_name (
	id int(9) NOT NULL AUTO_INCREMENT,
	transaction_id VARCHAR(30) NOT NULL,
	merchant_reference_no VARCHAR(20) NOT NULL,
	mid VARCHAR(20) NOT NULL,
	amount VARCHAR(20) NOT NULL,
	payment_method VARCHAR(20) NOT NULL,
	txn_date VARCHAR(20) NOT NULL,
	txn_time VARCHAR(20) NOT NULL,
	error_code VARCHAR(20) NOT NULL,
	status VARCHAR(6) NOT NULL,
	UNIQUE KEY id (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'jal_db_version', $jal_db_version );
}

//-----------------------------------------------------
// Initiating Methods to run after plugin loaded
// ----------------------------------------------------
add_action('plugins_loaded', 'ogn_woocommerce_enets_payment_gateway', 0);

function ogn_woocommerce_enets_payment_gateway(){

	if(!class_exists('WC_Payment_Gateway')) return;

	class WC_OgnEnetsipg extends WC_Payment_Gateway{

		public function __construct(){

			$plugin_dir = plugin_dir_url(__FILE__);
			$this->id = 'enetsipg';	  
			$this->icon = apply_filters('woocommerce_Paysecure_icon', ''.$plugin_dir.'enets.png');
			$this->medthod_title = 'eNetsIPG';
			$this->has_fields = false;

			$this->init_form_fields();
			$this->init_settings(); 

			$this->title 				= $this -> settings['title'];
			$this->description 			= $this -> settings['description'];
			$this->ipg_url 				= $this -> settings['ipg_url'];
			$this->merchant_id 			= $this -> settings['merchant_id'];
			$this->um_api_type 			= $this -> settings['um_api_type'];
			$this->sucess_responce_code	= $this -> settings['sucess_responce_code'];	  
			$this->responce_url_sucess	= $this -> settings['responce_url_sucess'];
			$this->responce_url_fail	= $this -> settings['responce_url_fail'];	  	  
			$this->checkout_msg			= $this -> settings['checkout_msg'];	  

			$this->msg['message'] 		= "";
			$this->msg['class'] 		= "";

			add_action('init', array(&$this, 'ogn_check_enets_ipg_response'));	  
			  
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( &$this, 'process_admin_options' ) );
			} else {
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}

			add_action('woocommerce_receipt_'.$this->id, array(&$this, 'receipt_page'));
		}

		function init_form_fields(){
	 		
	       	$this -> form_fields = array(
	                'enabled' 	=> array(
	                    'title' 		=> __('Enable/Disable', 'ognro'),
	                    'type' 			=> 'checkbox',
	                    'label' 		=> __('Enable eNets IPG Module.', 'ognro'),
	                    'default' 		=> 'no'),
						
	                'title' 	=> array(
	                    'title' 		=> __('Title:', 'ognro'),
	                    'type'			=> 'text',
	                    'description' 	=> __('This controls the title which the user sees during checkout.', 'ognro'),
	                    'default' 		=> __('eNets IPG', 'ognro')),
					
					'description'=> array(
	                    'title' 		=> __('Description:', 'ognro'),
	                    'type'			=> 'textarea',
	                    'description' 	=> __('This controls the description which the user sees during checkout.', 'ognro'),
	                    'default' 		=> __('eNets IPG', 'ognro')),	
						
					'ipg_url' => array(
	                    'title' 		=> __('PG Domain:', 'ognro'),
	                    'type'			=> 'text',
	                    'description' 	=> __('IPG data submiting to this URL', 'ognro'),
	                    'default' 		=> __('https://www.enets.sg/enets2/enps.do', 'ognro')),	
						
					'merchant_id' => array(
	                    'title' 		=> __('PG M Id:', 'ognro'),
	                    'type'			=> 'text',
	                    'description' 	=> __('Unique ID for the merchant acc, given by bank.', 'ognro'),
	                    'default' 		=> __('', 'ognro')),
					
					'um_api_type' => array(
	                    'title' 		=> __('PG UM API Type:', 'ognro'),
	                    'type'			=> 'text',
	                    'description' 	=> __('collection of intiger numbers, given by bank.', 'ognro'),
	                    'default' 		=> __('lite', 'ognro')),
						
					'sucess_responce_code' => array(
	                    'title' 		=> __('Sucess responce code :', 'ognro'),
	                    'type'			=> 'text',
	                    'description' 	=> __('succ - Transaction Passed', 'ognro'),
	                    'default' 		=> __('succ', 'ognro')),	  
									
					'checkout_msg' => array(
	                    'title' 		=> __('Checkout Message:', 'ognro'),
	                    'type'			=> 'textarea',
	                    'description' 	=> __('Message display when checkout'),
	                    'default' 		=> __('Thank you for your order, please click the button below to pay with the secured eNets payment gateway.', 'ognro')),		
						
					'responce_url_sucess' => array(
	                    'title' 		=> __('Sucess redirect URL :', 'ognro'),
	                    'type'			=> 'text',
	                    'description' 	=> __('After payment is sucess redirecting to this page.'),
	                    'default' 		=> __('http://your-site.com/thank-you-page/', 'ognro')),
					
					'responce_url_fail' => array(
	                    'title' 		=> __('Fail redirect URL :', 'ognro'),
	                    'type'			=> 'text',
	                    'description' 	=> __('After payment if there is an error redirecting to this page.', 'ognro'),
	                    'default' 		=> __('http://your-site.com/error-page/', 'ognro'))	
	            );
	    }

	    //----------------------------------------
	    //	Generate admin panel fields
	    //----------------------------------------
		public function admin_options(){
			
			$plugin_path = plugin_dir_path( __FILE__ );
			$file = $plugin_path.'includes/auth.php';
			if(file_exists($file)){
				include 'includes/auth.php';
				$auth = new Auth();
				$auth->check_auth();
				if ( !$auth->get_status() ) {
					deactivate_plugins( plugin_basename( __FILE__ ) );
					wp_die( "<h1>".ucfirst($auth->get_message())."</h1><br>Visit <a href='http://www.oganro.com'>www.oganro.com</a> for more info" ,"Activation Error","ltr" );
				}
			}else{
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( "<h1>Buy serial key to activate this plugin</h1><br><img src=".site_url('wp-content/plugins/sampath_paycorp_ipg/support.jpg')." style='width:700px;height:auto;' /><p>Visit <a href='http://www.oganro.com/plugins'>www.oganro.com/plugins</a> to buy this plugin<p>" ,"Activation Error","ltr" );
			}

			echo '<style type="text/css">
			.wpimage {
			margin:3px;
			float:left;
			}		
			</style>';
	    	echo '<h3>'.__('eNets online payment gateway', 'ognro').'</h3>';
	        echo '<p>'.__('<a target="_blank" href="http://www.oganro.com">Oganro</a> is a fresh and dynamic web design and custom software development company with offices based in East London, Essex, Brisbane (Queensland, Australia) and in Colombo (Sri Lanka).').'</p>';
	        
	        echo '<table class="form-table">';        
	        $this->generate_settings_html();
	        echo '</table>'; 
	    }

	    function payment_fields(){
	        if($this -> description) echo wpautop(wptexturize($this -> description));
	    }

	    //----------------------------------------
	    //	Generate checkout form
	    //----------------------------------------
	    function receipt_page($order){        		
			global $woocommerce;
	        $order_details = new WC_Order($order);
	        
	        echo $this->generate_ipg_form($order);		
			echo '<br>'.$this->checkout_msg.'</b>';        
	    }

	    public function generate_ipg_form($order_id){

	    	global $wpdb;
	        global $woocommerce;
	        
	        $order          = new WC_Order($order_id);
			$productinfo    = "Order $order_id";		
	        $currency_code  = $this -> currency_code;		
			$curr_symbole 	= get_woocommerce_currency();

			$table_name = $wpdb->prefix . 'ogn_enets_ipg';		
			$check_oder = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE merchant_reference_no = '".$order_id."'" );

			if($check_oder > 0){
				$wpdb->update( 
					$table_name, 
					array( 
						'transaction_id' 		=> '',
						'mid' 					=> '',
						'amount' 				=> ($order->order_total),
						'payment_method'		=> '',
						'txn_date'				=> '',
						'txn_time'				=> '',
						'error_code'			=> '',
						'status' 				=> ''
					), 
					array( 'merchant_reference_no' => $order_id ));								
			}else{
				
				$wpdb->insert(
					$table_name, 
					array( 
						'transaction_id' 		=> '',
						'merchant_reference_no' => $order_id,
						'mid' 					=> '',
						'amount' 				=> ($order->order_total),
						'payment_method'		=> '',
						'txn_date'				=> '',
						'txn_time'				=> '',
						'error_code'			=> '',
						'status' 				=> ''
						),
						array( '%s', '%d' ) );					
			}	

			return  '<form name="cart" method="POST" action="'.$this->ipg_url.'" >'.
	    		'<input type="hidden" name="amount" value="'.($order->order_total).'" >'.
	    		'<input type="hidden" name="txnRef" value="'.$order_id.'" >'.
	    		'<input type="hidden" name="mid" value="'.$this->merchant_id.'" >'.
	    		'<input type="hidden" name="umapiType" value="'.$this->um_api_type .'" >'.
	    		'<input type="submit" class="button-alt" id="submit_ipg_payment_form" value="'.__('Pay via eNets', 'ognro').'" />'.
	    		'<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'ognro').'</a>'.
	    		'</form>';
	    }

	    function process_payment($order_id){
	        $order = new WC_Order($order_id);
	        return array('result' => 'success', 'redirect' => add_query_arg('order',           
			   $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
	        );
	    }

	    function ogn_check_enets_ipg_response(){

	    	global $wpdb;
	        global $woocommerce;

	        $resp_data = $_REQUEST;

	        if(isset($resp_data['txtRef']) && isset($resp_data['txnDate']) && isset($resp_data['txnTime'])){

        		if($resp_data['txtRef'] != ''){

        			$order 	= new WC_Order($resp_data['txtRef']);

        			if($resp_data['status'] == $this->sucess_responce_code){
	    				$table_name = $wpdb->prefix . 'ogn_enets_ipg';
						$wpdb->update( 
						$table_name, 
						array( 
							'transaction_id' 		=> $resp_data['txtRef'],					
							'mid' 					=> $resp_data['mid'],					
							'amount' 				=> $resp_data['amount'],
							'payment_method' 		=> $resp_data['payment'],
							'txn_date' 				=> $resp_data['txnDate'],
							'txn_time' 				=> $resp_data['txnTime'],
							'error_code' 			=> $resp_data['errorCode'],
							'status' 				=> $resp_data['status']
						), 
						array( 'merchant_reference_no' => $resp_data['txtRef'] ));

	                    $order->add_order_note('eNets payment successful<br/>Unnique Id from eNets IPG: '.$resp_data['txtRef']);
	                    $order->add_order_note($this->msg['message']);
	                    $woocommerce->cart->empty_cart();
						
						$mailer = $woocommerce->mailer();

						$admin_email = get_option( 'admin_email', '' );

						$message = $mailer->wrap_message(__( 'Order confirmed','woocommerce'),sprintf(__('Order '.$resp_data['txtRef'].' has been confirmed', 'woocommerce' ), $order->get_order_number(), $posted['reason_code']));	
						$mailer->send( $admin_email, sprintf( __( 'Payment for order %s confirmed', 'woocommerce' ), $order->get_order_number() ), $message );					
											
											
						$message = $mailer->wrap_message(__( 'Order confirmed','woocommerce'),sprintf(__('Order '.$resp_data['txtRef'].' has been confirmed', 'woocommerce' ), $order->get_order_number(), $posted['reason_code']));	
						$mailer->send( $order->billing_email, sprintf( __( 'Payment for order %s confirmed', 'woocommerce' ), $order->get_order_number() ), $message );

						$order->payment_complete();
						wp_redirect( $this->responce_url_sucess ); exit;

	        		}else{

						global $wpdb;
	                    $order->update_status('failed');
	                    $order->add_order_note('Failed - Code'.$resp_data['errorCode']);
	                    $order->add_order_note($this->msg['message']);
								
						$table_name = $wpdb->prefix . 'ogn_enets_ipg';	
						$wpdb->update( 
						$table_name, 
						array( 
							'transaction_id' 		=> $resp_data['txtRef'],				
							'mid' 					=> $resp_data['mid'],					
							'amount' 				=> $resp_data['amount'],
							'payment_method' 		=> $resp_data['payment'],
							'txn_date' 				=> $resp_data['txnDate'],
							'txn_time' 				=> $resp_data['txnTime'],
							'error_code' 			=> $resp_data['errorCode'],
							'status' 				=> $resp_data['status']
						), 
						array( 'merchant_reference_no' => $resp_data['txtRef'] ));

						wp_redirect( $this->responce_url_fail ); exit();
	        		}
        		}
	        }
	    }


	    function get_pages($title = false, $indent = true) {
	        $wp_pages = get_pages('sort_column=menu_order');
	        $page_list = array();
	        if ($title) $page_list[] = $title;
	        foreach ($wp_pages as $page) {
	            $prefix = '';            
	            if ($indent) {
	                $has_parent = $page->post_parent;
	                while($has_parent) {
	                    $prefix .=  ' - ';
	                    $next_page = get_page($has_parent);
	                    $has_parent = $next_page->post_parent;
	                }
	            }            
	            $page_list[$page->ID] = $prefix . $page->post_title;
	        }
	        return $page_list;
	    }
	}


    if(isset($_GET['ogn_enets_response'])){
		$WC = new WC_OgnEnetsipg();
	}

   	function woocommerce_ogn_add_enets_gateway($methods) {
		$methods[] = 'WC_OgnEnetsipg';
		return $methods;
   	}
	 	
    add_filter('woocommerce_payment_gateways', 'woocommerce_ogn_add_enets_gateway' );
}