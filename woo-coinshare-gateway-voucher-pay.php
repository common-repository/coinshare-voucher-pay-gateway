<?php
/*
Plugin Name: Coinshare Voucher-Pay Gateway 
Plugin URI: https://www.coinshop.network
Description: Voucher-Pay - Payment gateway for woocommerce
Version: 1.0
Author: Coinshare LTD
Author URI: https://www.coinshare.network
*/

if ( 
  in_array( 
    'woocommerce/woocommerce.php', 
    apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) 
  ) 
) {
    add_action('plugins_loaded', 'WCGVP_woocommerce_vpayments_init', 0);
add_action('init', 'check_for_vpayment');

function check_for_vpayment()
{
    WC()->payment_gateways();
    do_action('check_vpayments_payment');
    do_action('order_completed_payment');
    
    
}

function WCGVP_woocommerce_vpayments_init()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;
    
    class WC_vpayments extends WC_Payment_Gateway
    {
        public function __construct()
        {
	         
			
            $this->id            = 'vpayments';
             $this->method_title = 'Coinshare Gateway';
            $this->method_description = 'Voucher-Pay Gateway permette agli utenti della community Coinshare di effettuare acquisti ricevendo Cashback o sconti in Voucher.';
	
	$this->icon = apply_filters('woocommerce_voucher_pay_icon', plugins_url('img/voucher.png', __FILE__));
	
            $this->has_fields    = false;
            
            $this->init_form_fields();
            $this->init_settings();
            
            $this->title                          = $this->settings['title']; 
	    $this->desctiption                = $this->settings['desctiption']; 
	    $this->vpayments_shipping    = $this->settings['vpayments_shipping'];
            $this->vpayments_api_key    = $this->settings['vpayments_api_key'];
	    $this->vpayments_cashback  = $this->settings['vpayments_cashback'];
            $this->initializePaymentUrl     = 'https://api.coinshop.network/coinshop/app/json/public/getVpayButtonForm';
            $this->getTransactionStatusUrl  = 'https://api.coinshop.network/coinshop/app/json/public/getVpayButtonForm';
            
            $this->msg['message'] = "";
            $this->msg['class']   = "";
            
            add_action('check_vpayments_payment', array(
                &$this,
                'check_vpayments_response'
            ));
            
            add_action('order_completed_payment', array(
                &$this,
                'order_completed_callback'
            ));
            /*add_action('woocommerce_payment_complete_order_status', array(
                &$this,
                'order_completed_callback'
            ), 10, 2);*/
            
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                    &$this,
                    'process_admin_options'
                ));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(
                    &$this,
                    'process_admin_options'
                ));
            }
            add_action('woocommerce_receipt_vpayments', array(
                &$this,
                'receipt_page'
            ));
        }
        function init_form_fields()
        {
            
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Attiva/Disattiva', 'vpayments'),
                    'type' => 'checkbox',
                    'label' => __('Abilita il modulo Voucher-Pay.', 'vpayments'),
                    'default' => 'no'
                ),
		'title' => array(
                    'title' => __('Title', 'vpayments'),
                    'type' => 'text',
                    'description' => __('Opzione utente mostrata durante il checkout.', 'vpayments'),
                    'desc_tip' => false,
                    'default' => __('Voucher-Pay', 'vpayments'),
                ),
                'description' => array(
                    'title' => __('Description', 'vpayments'),
                    'type' => 'textarea',
                    'description' => __('Descrizione opzione utente mostrata durante il checkout.', 'vpayments'),
                    'default' => __('', 'vpayments'),
                ),
                'vpayments_api_key' => array(
                    'title' => __('API Key:', 'vpayments'),
                    'type' => 'text',
                    'description' => __('Accedi al Coinshop nella sezione -> Voucher-Pay -> Settings', 'vpayments')
                ),
		 'vpayments_cashback' => array(
                    'title' => __('Cashback o Voucher OFF', 'vpayments'),
                    'type' => 'double',
                    'description' => __('ex. 0.10 => 10% di Cashback o sconto in Voucher', 'vpayments'),
                    'default' => '0.0'
                ),
		 'vpayments_shipping' => array(
                    'title' => __('Includi spese di spedizione', 'vpayments'),
                    'type' => 'checkbox',
                    'label' => __('Se abilitato le spese di spedizione rientreranno nelle logiche di cashback e sconto in voucher.', 'vpayments'),
                    'default' => 'no'
                ),
            );
        }
        
        public function admin_options()
        {
	        
	        $cart_url = get_permalink( wc_get_page_id( 'cart' ) );
		$checkout_url = get_permalink( wc_get_page_id( 'checkout' ) );
			
            echo '<h3>' . __('Coinshare Voucher-Pay Gateway', 'vpayments') . '</h3>';
            echo '<p>' . __('Sistema di Pagamento Affiliati CoinShop') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
	    echo '<p><div class="woocommerce_message">Cashback: <strong>'.( $this->vpayments_cashback *  100).' %</strong> in Voucher.</div></p>';
            echo '<p><div class="woocommerce_message">Set Url Callback with: <strong>'.$cart_url.'</strong> and ask for passed variable <strong>"object_id" in get</strong> and ask for passed variable <strong>trx_id</strong>.</div></p>';
             echo '<p><div class="woocommerce_message">Set Url IPN with: <strong>'.$checkout_url.'check_vpayments_payment</strong>.</div></p>';
            
        }
        
        /**
         *  There are no payment fields for vpayments, but we want to show the description if set.
         **/
        function payment_fields()
        {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }
        /**
         * Receipt Page
         **/
        function receipt_page($order)
        {
            echo $this->WCGVP_generate_vpayments_form($order);
        }
        /**
         * Generate vpayments button link
         **/
        public function WCGVP_generate_vpayments_form($order_id)
        {

            global $woocommerce;
            $order = new WC_Order($order_id);
            
            $params                     = array();
            $params['api_key'] = $this->vpayments_api_key;
            //$params['merchantPassword'] = $this->vpayments_merchant_secret;
           // $params['paymentMethod']    = "SC_PAY_USING_CBK";
            
            //$params['redirectTo']             = base64_encode($this->get_return_url($order));
         /*   $params['jsonCart']['cart_id']    = $order_id;
            $params['jsonCart']['total']      = $order->get_total();
            $params['jsonCart']['currency']   = "EUR"; //$order->currency;
            $params['jsonCart']['shipping']   = $order->get_shipping_total();
            $params['jsonCart']['additional'] = $order->get_total_tax();
            $params['jsonCart']['discount']   = $order->get_total_discount();*/
	
	// print_r($this->vpayments_shipping);
	
	if($this->vpayments_shipping == 'yes')
		$cashback = $order->get_total() * $this->vpayments_cashback;
	else
		$cashback = ( $order->get_total() -  $order->get_shipping_total() )  * $this->vpayments_cashback;
		
	

	$params['object_id']    = $order_id;
	$params['amount_voucher']      = $cashback;
	$params['amount_cash']      =  $order->get_total() - $cashback;
	$params['locale'] = "it";
            
            
            
            $oggetti = "";
            
            
            $items = $order->get_items();
            
            
            foreach ($items as $item) {
	        	$productId  = $item->get_product_id();
	        	
                $product    = new WC_Product($productId);
                
                $title = trim(preg_replace('/\s+/', ' ', (htmlentities(strip_tags($product->get_name()),ENT_QUOTES))));
                $oggetti .= $title.",";
	        }
	        $oggetti = substr($oggetti,0,-1);
	        
	        $params['object_name']      = "".$oggetti;
           // $params['amount']      = $order->get_total();
            /*$items = $order->get_items();
            
            
            
            $i = 0;

            foreach ($items as $item) {
                $productId                                       = $item->get_product_id();
                $product                                         = new WC_Product($productId);
                $productImage                                    = wp_get_attachment_image_src(get_post_thumbnail_id($productId), 'single-post-thumbnail');
                $productImage                                    = (is_array($productImage) && count($productImage) > 0) ? $productImage[0] : null;
                $params['jsonCart']['values'][$i]['title']       = trim(preg_replace('/\s+/', ' ', (htmlentities(strip_tags($product->get_name()),ENT_QUOTES))));
                $params['jsonCart']['values'][$i]['description'] = trim(preg_replace('/\s+/', ' ', (htmlentities(strip_tags($product->get_description()),ENT_QUOTES))));
                $params['jsonCart']['values'][$i]['quantity']    = $item->get_quantity();
                $params['jsonCart']['values'][$i]['image']       = $productImage;
                $params['jsonCart']['values'][$i]['amount']      = $item->get_subtotal();
                $params['jsonCart']['values'][$i]['shipping']    = 0;
                $params['jsonCart']['values'][$i]['additional']  = $item->get_subtotal_tax();
                
                $vendorToken = get_post_meta($productId, "smart_cash_vendor_token", true);
                if (isset($vendorToken) && !empty($vendorToken)) {
                    $params['jsonCart']['values'][$i]['employee'] = $vendorToken;
                }
                
                $i++;
                unset($product, $productImage, $productId, $vendorToken);
            }*/
            
            
            //$params['jsonCart'] = base64_encode(json_encode($params['jsonCart']));
            
            $authUrl = $this->initializePaymentUrl . '?' . http_build_query($params);
            $result  = wp_remote_retrieve_body( wp_remote_get($authUrl));
	   //  $body_response   = wp_remote_retrieve_body($result);

            $result  = json_decode(  $result , 1);
            
           // print_r($params);

           // print_r($result);

           // die();
            
           if(!isset($result['button'])){
            	wc_add_notice("vpayments - Server connection error",'error');
                return;
            }
	    
             print($result['button']);
            //print("<script type = 'text/javascript'> location.href = '".$authUrl."';</script>");
            return;
            
            
        }
        /**
         * Process the payment and return the result
         **/
        
        function order_completed_callback()
        {
	        global $woocommerce;
	        $isAuthorized      = false;	   
	        
		$key = sanitize_text_field(  (isset($_REQUEST['key']) && !empty($_REQUEST['key'])) ? $_REQUEST['key'] : "" );     
		$order_id = sanitize_text_field ( (isset($_REQUEST['object_id']) && !empty($_REQUEST['object_id'])) ? $_REQUEST['object_id'] : "" );
	        $transaction_token = sanitize_text_field  ( (isset($_REQUEST['trx_id']) && !empty($_REQUEST['trx_id'])) ? $_REQUEST['trx_id'] : "" );
	     
	        
		// print_r("order_completed_callback: ".$key);
		 // print_r("order_completed_callback: ".$order_id);
		 // Sprint_r("order_completed_callback: ".$transaction_token);
		
	        $order = new WC_Order($order_id);
	        
		// print_r("order_completed_callback: ".$order->get_status());
		
	        if (!empty($order_id) && $order && $order->get_status() == "processing")
	        {
		        $isAuthorized         = true;
		        $this->msg['message'] = "We will be shipping your order to you soon.";
				$this->msg['class']   = 'woocommerce_message';
				$status = "success";
	        }
	        elseif(!empty($order_id) && $order && $order->get_status() != "processing" )
	        {
		        $this->msg['class']   = 'woocommerce_error';
			$this->msg['message'] = "Repeat your order.";
			$status = "error";
	        }
	        
	        if (empty($key) && !empty($transaction_token))
		{
			add_action('the_content', array(
				&$this,
				'showMessage'
			));
                    
                    $url_back = $this->get_return_url($order)."&trx_id=".$transaction_token."&object_id=".$order_id."&status=".$status;
                    
                    //&sc_transaction_status=failure&sc_transaction_token=72694JgFy0K6RljaKfL7ow13WOFs7GJgpjZt_4&sc_cart_id=72&sc_order_id=
              print("<script type = 'text/javascript'> location.href = '".$url_back."';</script>");
            return;
			}
			add_action('the_content', array(
                        &$this,
                        'showMessage'
                    ));		
                    
        }
        function process_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);

            $payment_page = get_permalink( woocommerce_get_page_id( 'pay' ) );
            if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ) $payment_page = str_replace( 'http:', 'https:', $payment_page );
            
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $payment_page))
            );
            
            
            
            
        }
        
        /**
         * Check for valid vpayments server callback
         **/
        function check_vpayments_response()
        {
	       
	        
            global $woocommerce;

            $isAuthorized      = false;
	        $transaction_token =  sanitize_text_field ( (isset($_REQUEST['trx_id']) && !empty($_REQUEST['trx_id'])) ? $_REQUEST['trx_id'] : "" );
            $order_id          =  sanitize_text_field ( (isset($_REQUEST['object_id']) && !empty($_REQUEST['object_id'])) ? $_REQUEST['object_id'] : "" );
            $status          = sanitize_text_field ( (isset($_REQUEST['status']) && !empty($_REQUEST['status'])) ? $_REQUEST['status'] : "" );
            
            
		// print_r("check_vpayments_response: ".$status);
		// print_r("check_vpayments_response: ".$order_id);
		// print_r("check_vpayments_response: ".$transaction_token);
		
		
            if (isset($transaction_token) && !empty($transaction_token) ) {

                if(($order_id) == null){
                    $this->msg['class']   = 'woocommerce_error';
                                $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                    add_action('the_content', array(
                        &$this,
                        'showMessage'
                    ));
                    return;
                }
                $order = new WC_Order($order_id);
                if ($order && $order->get_status() == 'pending') {
                    
                        
                      		
                       
                            
                            
                            if ($status == "success") {
                                $isAuthorized         = true;
                                $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
                                $this->msg['class']   = 'woocommerce_message';
                                if ($order->get_status() != 'processing') {
                                    
                                    
                                    $order->payment_complete();
                                    $order->add_order_note('Payment Completed.Transaction Code : ' . $transaction_token);
                                    $order->add_order_note($this->msg['message']);
                                    $woocommerce->cart->empty_cart();
                                    
                                }
                            } else {
                                $this->msg['class']   = 'woocommerce_error';
                                $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                $order->add_order_note('Payment Declined.Transaction Code : ' . $transaction_token);
                            }
                            
                            
                        
                        
                        
                     
                    
                    
                    if ($isAuthorized == false) {
                        $order->update_status('failed');
                        $order->add_order_note('Failed');
                        $order->add_order_note($this->msg['message']);
                    }
                    add_action('the_content', array(
                        &$this,
                        'showMessage'
                    ));
                }
                
                
                
                
                
                
            }
            
        }
        
        function showMessage($content)
        {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
        }
        // get all pages
        
    }
    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_vpayments_gateway($methods)
    {
        $methods[] = 'WC_vpayments';
        return $methods;
    }
    
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_vpayments_gateway');
}




}
