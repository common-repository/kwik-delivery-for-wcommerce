<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Kwik Delivery Shipping Method Class
 *
 * Provides real-time shipping rates from Kwik delivery and handle order requests
 *
 * @since 1.0
 * 
 * @extends \WC_Shipping_Method
 */
class WC_Kwik_Delivery_Shipping_Method extends WC_Shipping_Method
{
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct($instance_id = 0)
	{
		$this->id                 = 'kwik_delivery';
		$this->instance_id 		  = absint($instance_id);
		$this->method_title       = __('Kwik Delivery');
		$this->method_description = __('Get your parcels delivered better, cheaper and quicker via Kwik Delivery');

		$this->supports  = array(
			'settings',
			'shipping-zones',
		);

		$this->init();

		$this->title = 'Kwik Delivery';

		$this->enabled = $this->get_option('enabled');
	}

	/**
	 * Init.
	 *
	 * Initialize kwik delivery shipping method.
	 *
	 * @since 1.0.0
	 */
	public function init()
	{
		$this->init_form_fields();
		$this->init_settings();

		// Save settings in admin if you have any defined
		add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
	}

	/**
	 * Init fields.
	 *
	 * Add fields to the kwik delivery settings page.
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields()
	{
		$pickup_state_code = WC()->countries->get_base_state();
		$pickup_country_code = WC()->countries->get_base_country();

		$pickup_city = WC()->countries->get_base_city();
		$pickup_state = WC()->countries->get_states($pickup_country_code)[$pickup_state_code];
		$pickup_base_address = WC()->countries->get_base_address();

		$this->form_fields = array(
			'enabled' => array(
				'title' 	=> __('Enable/Disable'),
				'type' 		=> 'checkbox',
				'label' 	=> __('Enable this shipping method'),
				'default' 	=> 'no',
			),
			'mode' => array(
				'title'       => 	__('Mode'),
				'type'        => 	'select',
				'description' => 	__('Default is (Sandbox), choose (Live) when your ready to start processing orders via  kwik delivery'),
				'default'     => 	'sandbox',
				'options'     => 	array('sandbox' => 'Sandbox', 'live' => 'Live'),
			),
			'sandbox_email' => array(
				'title'       => 	__('Sandbox Email Address'),
				'type'        => 	'email',
				'description' => 	__('Your Sanbox Kwik delivery email', 'woocommerce-kwik-delivery'),
				'default'     => 	__('')
			),
			'sandbox_password' => array(
				'title'       => 	__('Sandbox Password'),
				'type'        => 	'password',
				'description' => 	__('Your Sanbox account password', 'woocommerce-kwik-delivery'),
				'default'     => 	__('')
			),
			'live_email' => array(
				'title'       => 	__('Live Email Address'),
				'type'        => 	'email',
				'description' => 	__('Your Live Kwik delivery email', 'woocommerce-kwik-delivery'),
				'default'     => 	__('')
			),
			'live_password' => array(
				'title'       => 	__('Live Password'),
				'type'        => 	'password',
				'description' => 	__('Your Live account password', 'woocommerce-kwik-delivery'),
				'default'     => 	__('')
			),
			'pickup_country' => array(
				'title'       => 	__('Pickup Country'),
				'type'        => 	'select',
				'description' => 	__('Kwik delivery/pickup is only available for Nigeria'),
				'default'     => 	'NG',
				'options'     => 	array("NG" => "Nigeria", "" => "Please Select"),
			),
			'pickup_state' => array(
				'title'        =>	__('Pickup State'),
				'type'         =>	'select',
				'description'  =>	__('Kwik delivery/pickup state.'),
				'default'      =>	__('Lagos'),
				'options'      =>	array(
					"AB" => "Abia", "FC" => "Abuja Federal Capital Territory", "AD" => "Adamawa", "AK" => "Akwa Ibom", "AN" => "Anambra", "BA" => "Bauchi", "BY" => "Bayelsa", "BE" => "Benue", "BO" => "Borno", "CR" => "Cross River", "DE" => "Delta", "EB" => "Ebonyi", "ED" => "Edo", "EK" => "Ekiti", "EN" => "Enugu", "GO" => "Gombe", "IM" => "Imo", "JI" => "Jigawa", "KD" => "Kaduna", "KN" => "Kano", "KT" => "Katsina", "KE" => "Kebbi", "KO" => "Kogi", "KW" => "Kwara", "LA" => "Lagos", "NA" => "Nasarawa", "NI" => "Niger", "OG" => "Ogun", "ON" => "Ondo", "OS" => "Osun", "OY" => "Oyo", "PL" => "Plateau", "RI" => "Rivers", "SO" => "Sokoto", "TA" => "Taraba", "YO" => "Yobe", "ZA" => "Zamfara"
				)
			),

			'pickup_city' => array(
				'title'       => 	__('Pickup City'),
				'type'        => 	'text',
				'description' => 	__('The local area where the parcel will be picked up.'),
				'default'     => 	__($pickup_city)
			),
			'pickup_postcode' => array(
				'title'       => 	__('Pickup Postcode '),
				'type'        => 	'text',
				'description' => 	__('The local postcode where the parcel will be picked up.'),
				'default'     => 	__($pickup_postcode)
			),
			'pickup_base_address' => array(
				'title'       => 	__('Pickup Address'),
				'type'        => 	'text',
				'description' => 	__('The street address where the parcel will be picked up.'),
				'default'     => 	__($pickup_base_address)
			),
			'sender_name' => array(
				'title'       => 	__('Sender Name'),
				'type'        => 	'text',
				'description' => 	__("Sender Name"),
				'default'     => 	__('')
			),
			'sender_phone_number' => array(
				'title'       => 	__('Sender Phone Number'),
				'type'        => 	'text',
				'description' => 	__('Used to coordinate pickup if the kwik rider is outside attempting delivery. Must be a valid phone number'),
				'default'     => 	__('')
			),
		);
	}


	/**
	 * Calculate shipping by sending destination/items to Shipwire and parsing returned rates
	 *
	 * @since 1.0
	 * @param array $package
	 */
	public function calculate_shipping($package = array())
	{
		if ($this->get_option('enabled') == 'no') {
			return;
		}

		// country required for all shipments
		if ($package['destination']['country'] !== 'NG') {

			return;
		}

		$delivery_country_code = $package['destination']['country'];
		$delivery_state_code = $package['destination']['state'];
		$delivery_city = $package['destination']['city'];
		$delivery_postcode = $package['destination']['postcode'];
		$delivery_base_address = $package['destination']['address'];

		$delivery_state = WC()->countries->get_states($delivery_country_code)[$delivery_state_code];
		$delivery_country = WC()->countries->get_countries()[$delivery_country_code];

		if ('Lagos' !== $delivery_state) {
			wc_add_notice('Kwik Delivery only available within Lagos', 'notice');
			return;
		}

		try {
			$api = wc_kwik_delivery()->get_api();
		} catch (\Exception $e) {
			wc_add_notice(__('Kwik Delivery shipping method could not set up'), 'notice');
			wc_add_notice(__($e->getMessage()) . ' Please Contact Support', 'error');

			return;
		}

		$pickup_city = $this->get_option('pickup_city');
		$pickup_postcode = $this->get_option('pickup_postcode');
		$pickup_state = $this->get_option('pickup_state');
		$pickup_base_address = $this->get_option('pickup_base_address');
		$pickup_country = WC()->countries->get_countries()[$this->get_option('pickup_country')];

		if ($delivery_postcode == '') {

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
		} else {


			$delivery_address = $delivery_postcode . ',' . $delivery_city . ',' . $delivery_state . ',nigeria';
			$delivery_address = trim("$delivery_address");
			$delivery_addressd = trim("$delivery_base_address $delivery_city, $delivery_state, $delivery_country,$delivery_postcode");
			$delivery_coordinate = $api->get_lat_lng($delivery_address);

			if (!isset($delivery_coordinate['lat']) && !isset($delivery_coordinate['long'])) {
				$delivery_coordinate = $api->get_lat_lng("$delivery_address");
			}

			$pickup_address = $pickup_postcode . ',' . $pickup_city . ',' . $pickup_state . ',nigeria';
			$pickup_address = trim("$pickup_address");
			$pickup_addressd = trim("$pickup_base_address $pickup_city, $pickup_state, $pickup_country, $pickup_postcode");
			$pickup_coordinate = $api->get_lat_lng($pickup_address);

			if (!isset($pickup_coordinate['lat']) && !isset($pickup_coordinate['long'])) {
				$pickup_coordinate = $api->get_lat_lng("$pickup_address");
			}
		}
		$deliveries = array(
			array(
				"address" => $delivery_address,
				"latitude" => $delivery_coordinate['lat'],
				"longitude" => $delivery_coordinate['long']
			)
		);

		$pickups = array(
			array(
				"address" => $pickup_address,
				"latitude" => $pickup_coordinate['lat'],
				"longitude" => $pickup_coordinate['long']
			)
		);

		$params = array(
			'has_pickup' => 1,
			'has_delivery' => 1,
			'payment_method' => 32,
			'pickups' => $pickups,
			'deliveries' => $deliveries
		);

		try {
			$res = $api->calculate_pricing($params);
		} catch (\Exception $e) {
			wc_add_notice(__('Kwik Delivery pricing calculation could not complete'), 'notice');
			wc_add_notice(__($e->getMessage()), 'error');

			return;
		}

		$data = $res['data'];

		$handling_fee = 0;

		$cost = wc_format_decimal($data['per_task_cost']) + wc_format_decimal($handling_fee);

		$this->add_rate(array(
			'id'    	=> $this->id . $this->instance_id,
			'label' 	=> $this->title,
			'cost'  	=> $cost,
			'meta_data' => array(
				'per_task_cost'		   => $data['per_task_cost'],
				'insurance_amount'     => $data['total_no_of_tasks'],
				'total_no_of_tasks'    => $data['total_no_of_tasks'],
				'total_service_charge' => $data['total_service_charge']
			)
		));
	}
}
