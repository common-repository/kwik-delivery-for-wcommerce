<?php
	
	if (!defined('ABSPATH')) exit; // Exit if accessed directly
	
	/**
		* Main Kwik Delivery Class.
		*
		* @class  WC_Kwik_Delivery
	*/
	class WC_Kwik_Delivery
	{
		/** @var \WC_Kwik_Delivery_API api for this plugin */
		public $api;
		
		/** @var array settings value for this plugin */
		public $settings;
		
		/** @var array order status value for this plugin */
		public $statuses;
		
		/** @var \WC_Kwik_Delivery single instance of this plugin */
		protected static $instance;
		
		/**
			* Loads functionality/admin classes and add auto schedule order hook.
			*
			* @since 1.0
		*/
		public function __construct()
		{
			// get settings
			$this->settings = maybe_unserialize(get_option('woocommerce_kwik_delivery_settings'));
			
			$this->statuses = [
            'UPCOMING',
            'STARTED',
            'ENDED',
            'FAILED',
            'ARRIVED',
            '',
            'UNASSIGNED',
            'ACCEPTED',
            'DECLINE',
            'CANCEL',
            'DELETED'
			];
			
			$this->init_plugin();
			
			$this->init_hooks();
		}
		
		/**
			* Initializes the plugin.
			*
			* @internal
			*
			* @since 2.4.0
		*/
		public function init_plugin()
		{
			$this->includes();
			
			if (is_admin()) {
				$this->admin_includes();
			}
			
			// if ( is_ajax() ) {
			// 	$this->ajax_includes();
			// } elseif ( is_admin() ) {
			// 	$this->admin_includes();
			// }
		}
		
		/**
			* Includes the necessary files.
			*
			* @since 1.0.0
		*/
		public function includes()
		{
			$plugin_path = $this->get_plugin_path();
			
			require_once $plugin_path . 'includes/class-wc-kd-api.php';
			
			require_once $plugin_path . 'includes/class-wc-kd-shipping-method.php';
		}
		
		public function admin_includes()
		{
			$plugin_path = $this->get_plugin_path();
			
			require_once $plugin_path . 'includes/class-wc-kd-orders.php';
		}
		
		/**
			* Initialize hooks.
			*
			* @since 1.0.0
		*/
		public function init_hooks()
		{
			/**
				* Actions
			*/
			
            // create order when \WC_Order::payment_complete() is called
			//   add_action('woocommerce_order_status_on-hold', array($this, 'create_order_shipping_task'));
			add_action('woocommerce_thankyou', array($this, 'create_order_shipping_task')); 
			
			
			add_action('woocommerce_shipping_init', array($this, 'load_shipping_method'));
			
			// cancel a Kwik delivery task when an order is cancelled in WC
			add_action('woocommerce_order_status_cancelled', array($this, 'cancel_order_shipping_task'));
			
			// adds tracking button(s) to the View Order page
			add_action('woocommerce_order_details_after_order_table', array($this, 'add_view_order_tracking'));
			
			/**
				* Filters
			*/
			// Add shipping icon to the shipping label
			add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'add_shipping_icon'), PHP_INT_MAX, 2);
			
			add_filter('woocommerce_checkout_fields', array($this, 'remove_address_2_checkout_fields'));
			
			add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
			
			add_filter('woocommerce_shipping_calculator_enable_city', '__return_true');
			
			add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_false');
		}
		
		/**
			* shipping_icon.
			*
			* @since   1.0.0
		*/
		function add_shipping_icon($label, $method)
		{
			if ($method->method_id == 'kwik_delivery') {
				$plugin_path = WC_KWIK_DELIVERY_MAIN_FILE;
				$logo_title = 'Kwik Delivery';
				$icon_url = plugins_url('assets/images/kwik.png', $plugin_path);
				$img = '<img class="kwik-delivery-logo"' .
                ' alt="' . $logo_title . '"' .
                ' title="' . $logo_title . '"' .
                ' style="width:25px; height:25px; display:inline;"' .
                ' src="' . $icon_url . '"' .
                '>';
				$label = $img . ' ' . $label;
			}
			
			return $label;
		}
		
		public function create_order_shipping_task($order_id)
		{
			$order = wc_get_order($order_id);
			// $order_status    = $order->get_status();
			$shipping_method = @array_shift($order->get_shipping_methods());
			
			if (strpos($shipping_method->get_method_id(), 'kwik_delivery') !== false) {
				
				$receiver_name      = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
				$receiver_email     = $order->get_billing_email();
				$receiver_phone     = $order->get_billing_phone();
				$delivery_base_address  = $order->get_shipping_address_1();
				// $delivery_address2  = $order->get_shipping_address_2();
				// $delivery_company   = $order->get_shipping_company();
				$delivery_city      = $order->get_shipping_city();
				$delivery_state_code    = $order->get_shipping_state();
				$delivery_postcode    = $order->get_shipping_postcode();
				
				
				$delivery_country_code  = $order->get_shipping_country();;
				$delivery_state = WC()->countries->get_states($delivery_country_code)[$delivery_state_code];
				$delivery_country = WC()->countries->get_countries()[$delivery_country_code];
				$payment_method = $order->get_payment_method();
				
				if($payment_method == 'cod') {
					
					$payment_methodkwik = 1048576; 
					
					}else {
					
					$payment_methodkwik = 524288; 
					
				}
				
				$sender_name         = $this->settings['sender_name'];
				$sender_phone        = $this->settings['sender_phone_number'];
				$pickup_base_address = $this->settings['pickup_base_address'];
				$pickup_city         = $this->settings['pickup_city'];
				$pickup_state        = $this->settings['pickup_state'];
				$pickup_country      = $this->settings['pickup_country'];
				$pickup_postcode      = $this->settings['pickup_postcode'];
				if (trim($pickup_country) == '') {
					$pickup_country = 'NG';
				}
				
				$todaydate =  date('Y-m-d H:i:s', time());
				$pickup_date = date('Y-m-d H:i:s', strtotime($todaydate . ' +1 day'));
				$delivery_date = date('Y-m-d H:i:s', strtotime($todaydate . ' +2 day'));
				
				$api = $this->get_api();
				
				if($delivery_postcode == '') { 
					
					$delivery_address = trim("$delivery_base_address $delivery_city, $delivery_state, $delivery_country");
					$delivery_coordinate = $api->get_lat_lng($delivery_address);
					
					if (!isset($delivery_coordinate['lat']) && !isset($delivery_coordinate['long'])) {
						$delivery_coordinate = $api->get_lat_lng("$delivery_city, $delivery_state, $delivery_country");
					}
					
					$pickup_address = trim("$pickup_base_address $pickup_city, $pickup_state, $pickup_country");
					$pickup_coordinate = $api->get_lat_lng($pickup_address);
					
					if (!isset($pickup_coordinate['lat']) && !isset($pickup_coordinate['long'])) {
						$pickup_coordinate = $api->get_lat_lng("$pickup_city, $pickup_state, $pickup_country");
					}
					
					}else {
					
					
					$delivery_address1 = $delivery_postcode . ',' . $delivery_city . ',' . $delivery_state . ',nigeria';
					$delivery_address1 = trim("$delivery_address1");
					
					$delivery_address = trim("$delivery_base_address $delivery_city, $delivery_state, $delivery_country,$delivery_postcode");
					$delivery_coordinate = $api->get_lat_lng($delivery_address1);
					
					if (!isset($delivery_coordinate['lat']) && !isset($delivery_coordinate['long'])) {
						$delivery_coordinate = $api->get_lat_lng("$delivery_address1");
					}
					
					$pickup_address1 = $pickup_postcode . ',' . $pickup_city . ',' . $pickup_state . ',nigeria';
					$pickup_address1 = trim("$pickup_address1");
					
					$pickup_address = trim("$pickup_base_address $pickup_city, $pickup_state, $pickup_country, $pickup_postcode");
					$pickup_coordinate = $api->get_lat_lng($pickup_address1);
					
					if (!isset($pickup_coordinate['lat']) && !isset($pickup_coordinate['long'])) {
						$pickup_coordinate = $api->get_lat_lng("$pickup_address1");
					}
					
				}
				
				$pickups = array(
                array(
				"name" => $sender_name,
				"phone" => $sender_phone,
				"address" => $pickup_address,
				"latitude" => $pickup_coordinate['lat'],
				"longitude" => $pickup_coordinate['long'],
				"time" => $pickup_date
                )
				);
				
				$deliveries = array(
                array(
				"name" => $receiver_name,
				"phone" => $receiver_phone,
				"email" => $receiver_email,
				"address" => $delivery_address,
				"latitude" => $delivery_coordinate['lat'],
				"longitude" => $delivery_coordinate['long'],
				"time" => $delivery_date
                )
				);
				
				if($payment_method == 'cod') {
					
					$params = array(
						'pickups'               => $pickups,
						'deliveries'            => $deliveries,
						'insurance_amount'      => $shipping_method->get_meta('insurance_amount'),
						'total_no_of_tasks'     => $shipping_method->get_meta('total_no_of_tasks'),
						'total_service_charge'  => $shipping_method->get_meta('total_service_charge'),
						'amount'                => $order->get_total(),
						'payment_method'        => $payment_methodkwik
						);
					
					}else {
					
					$params = array(
						'pickups'               => $pickups,
						'deliveries'            => $deliveries,
						'insurance_amount'      => $shipping_method->get_meta('insurance_amount'),
						'total_no_of_tasks'     => $shipping_method->get_meta('total_no_of_tasks'),
						'total_service_charge'  => $shipping_method->get_meta('total_service_charge'),
						'amount'                => $shipping_method->get_meta('per_task_cost'),
						'payment_method'        => $payment_methodkwik
					);
					
				}
				
				
				// error_log(print_r($params, true));
				$res = $api->create_task($params);
				// error_log(print_r($res, true));
				
				$order->add_order_note("Kwik Delivery: " . $res['message']);
				
				if ($res['status'] == 200) {
					$data = $res['data'];
					update_post_meta($order_id, 'kwik_delivery_order_id', $data['unique_order_id']);
					update_post_meta($order_id, 'kwik_delivery_check_status_url', $data['job_status_check_link']);
					
					// For Pickup
					update_post_meta($order_id, 'kwik_delivery_pickup_id', $data['pickups'][0]['job_id']);
					update_post_meta($order_id, 'kwik_delivery_pickup_status', $this->statuses[6]); // UNASSIGNED
					update_post_meta($order_id, 'kwik_delivery_pickup_tracking_url', $data['pickups'][0]['result_tracking_link']);
					
					// For Delivery
					update_post_meta($order_id, 'kwik_delivery_delivery_id', $data['deliveries'][0]['job_id']);
					update_post_meta($order_id, 'kwik_delivery_delivery_status', $this->statuses[6]); // UNASSIGNED
					update_post_meta($order_id, 'kwik_delivery_delivery_tracking_url', $data['pickups'][0]['result_tracking_link']);
					
					update_post_meta($order_id, 'kwik_delivery_order_response', $res);
					
					$note = sprintf(__('Shipment scheduled via Kwik delivery (Order Id: %s)'), $data['unique_order_id']);
					$order->add_order_note($note);
				}
			}
		}
		
		/**
			* Cancels an order in Kwik Delivery when it is cancelled in WooCommerce.
			*
			* @since 1.0.0
			*
			* @param int $order_id
		*/
		public function cancel_order_shipping_task($order_id)
		{
			$order = wc_get_order($order_id);
			$kwik_order_id = $order->get_meta('kwik_delivery_order_id');
			$kwik_pickup_id = $order->get_meta('kwik_delivery_pickup_id');
			$kwik_delivery_id = $order->get_meta('kwik_delivery_delivery_id');
			
			if ($kwik_order_id) {
				
				try {
					$params = [
                    'job_id' => $kwik_pickup_id . ' , ' . $kwik_delivery_id , // check if to cancel pickup task or delivery task
                    'job_status' => 9 // kwik delivery job status is 9 for a cancelled task
					];
					$this->get_api()->cancel_task($params);
					
					$order->update_status('cancelled');
					
					$order->add_order_note(__('Order has been cancelled in Kwik Delivery.'));
					} catch (Exception $exception) {
					
					$order->add_order_note(sprintf(
                    /* translators: Placeholder: %s - error message */
                    esc_html__('Unable to cancel order in Kwik Delivery: %s'),
                    $exception->getMessage()
					));
				}
			}
		}
		
		/**
			* Update an order status by fetching the order details from Kwik Delivery.
			*
			* @since 1.0.0
			*
			* @param int $order_id
		*/
		public function update_order_shipping_status($order_id)
		{
			$order = wc_get_order($order_id);
			
			$kwik_order_id = $order->get_meta('kwik_delivery_order_id');
			
			if ($kwik_order_id) {
				$res = $this->get_api()->get_order_details($kwik_order_id);
				
				if ($res['status'] == 200) {
					$data = $res['data'];
					$pickup_status = $this->statuses[$data['orders'][0]['job_status']];
					$delivery_status = $this->statuses[$data['orders'][1]['job_status']];
					
					if ($pickup_status == 'ACCEPTED') {
						$order->add_order_note("Kwik Delivery: Agent $pickup_status order");
						} elseif ($pickup_status == 'STARTED') {
						$order->add_order_note("Kwik Delivery: Agent $pickup_status order");
						} elseif ($delivery_status == 'ARRIVED') {
						$order->add_order_note("Kwik Delivery: Agent has $pickup_status destination");
						} elseif ($delivery_status == 'ENDED') {
						$order->update_status('completed', 'Kwik Delivery: Order completed successfully');
					}
					
					update_post_meta($order_id, 'kwik_delivery_pickup_status', $pickup_status);
					update_post_meta($order_id, 'kwik_delivery_delivery_status', $delivery_status);
					update_post_meta($order_id, 'kwik_delivery_order_details_response', $res);
				}
			}
		}
		
		/**
			* Adds the tracking information to the View Order page.
			*
			* @internal
			*
			* @since 2.0.0
			*
			* @param int|\WC_Order $order the order object
		*/
		public function add_view_order_tracking($order)
		{
			$order = wc_get_order($order);
			
			$pickup_tracking_url = $order->get_meta('kwik_delivery_pickup_tracking_url');
			$delivery_tracking_url = $order->get_meta('kwik_delivery_delivery_tracking_url');
			
			if (isset($pickup_tracking_url)) {
			?>
            <p class="wc-kwik-delivery-track-pickup">
                <a href="<?php echo esc_url($pickup_tracking_url); ?>" class="button" target="_blank">Track Pickup</a>
			</p>
			
			<?php
			}
			
			if (isset($delivery_tracking_url)) {
			?>
            <p class="wc-kwik-delivery-track-delivery">
                <a href="<?php echo esc_url($delivery_tracking_url); ?>" class="button" target="_blank">Track Delivery</a>
			</p>
			<?php
			}
		}
		
		public function remove_address_2_checkout_fields($fields)
		{
			unset($fields['billing']['billing_address_2']);
			unset($fields['shipping']['shipping_address_2']);
			
			return $fields;
		}
		
		/**
			* Load Shipping method.
			*
			* Load the WooCommerce shipping method class.
			*
			* @since 1.0.0
		*/
		public function load_shipping_method()
		{
			$this->shipping_method = new WC_Kwik_Delivery_Shipping_Method;
		}
		
		/**
			* Add shipping method.
			*
			* Add shipping method to the list of available shipping method..
			*
			* @since 1.0.0
		*/
		public function add_shipping_method($methods)
		{
			if (class_exists('WC_Kwik_Delivery_Shipping_Method')) :
            $methods['kwik_delivery'] = 'WC_Kwik_Delivery_Shipping_Method';
			endif;
			
			return $methods;
		}
		
		/**
			* Initializes the and returns Kwik Delivery API object.
			*
			* @since 1.0
			*
			* @return \WC_Kwik_Delivery_API instance
		*/
		public function get_api()
		{
			// return API object if already instantiated
			if (is_object($this->api)) {
				return $this->api;
			}
			
			$kwik_delivery_settings = $this->settings;
			
			// instantiate API
			return $this->api = new \WC_Kwik_Delivery_API($kwik_delivery_settings);
		}
		
		public function get_plugin_path()
		{
			return plugin_dir_path(__FILE__);
		}
		
		/**
			* Returns the main Kwik Delivery Instance.
			*
			* Ensures only one instance is/can be loaded.
			*
			* @since 1.0.0
			*
			* @return \WC_Kwik_Delivery
		*/
		public static function instance()
		{
			if (is_null(self::$instance)) {
				self::$instance = new self();
			}
			
			return self::$instance;
		}
	}
	
	
	/**
		* Returns the One True Instance of WooCommerce KwikDelivery.
		*
		* @since 1.0.0
		*
		* @return \WC_Kwik_Delivery
	*/
	function wc_kwik_delivery()
	{
		return \WC_Kwik_Delivery::instance();
	}
