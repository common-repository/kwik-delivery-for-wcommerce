<?php
	
	if (!defined('ABSPATH')) exit; // Exit if accessed directly
	
	/**
		* Kwik Delivery Orders Class
		*
		* Adds order admin page customizations
		*
		* @since 1.0
	*/
	class WC_Kwik_Delivery_Orders
	{
		/** @var \WC_Kwik_Delivery_Orders single instance of this class */
		private static $instance;
		
		/**
			* Add various admin hooks/filters
			*
			* @since  1.0
		*/
		public function __construct()
		{
			/** Order Hooks */
			// add_action('init', array($this, 'register_awaiting_shipment_order_status'));
			// add_action('init', array($this, 'register_shipped_order_status'));
			
			// add bulk action to update order status for multiple orders from shipwire
			add_action('admin_footer-edit.php', array($this, 'add_order_bulk_actions'));
			add_action('load-edit.php', array($this, 'process_order_bulk_actions'));
			
			// add 'Kwik Delivery Information' order meta box
			add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
			
			// add custom actions on 'Orders' page
			// add_action('woocommerce_admin_order_actions_end', array($this, 'add_order_actions'));
			
			// add kwik delivery status to order preview modal
			// add_action('woocommerce_admin_order_preview_end', array($this, 'add_kwik_delivery_status_to_preview_modal'));
			
			// process order meta box order actions
			add_action('woocommerce_order_action_wc_kwik_delivery_update_status', array($this, 'process_order_meta_box_actions'));
			
			// add 'Update Kwik Delivery Status' order meta box order actions
			add_filter('woocommerce_order_actions', array($this, 'add_order_meta_box_actions'));
			
			// add_filter('wc_order_statuses', array($this, 'add_awaiting_shipment_to_order_statuses'));
			
			// add_filter('wc_order_statuses', array($this, 'add_shipped_to_order_statuses'));
		}
		
		// /* Register new status */
		// public function register_awaiting_shipment_order_status()
		// {
		//     register_post_status('wc-awaiting-shipment', array(
		//         'label'                     => _('Awaiting shipment'),
		//         'public'                    => true,
		//         'exclude_from_search'       => false,
		//         'show_in_admin_all_list'    => true,
		//         'show_in_admin_status_list' => true,
		//         'label_count'               => _n_noop('Awaiting shipment (%s)', 'Awaiting shipment (%s)')
		//     ));
		// }
		
		// /* Register new status*/
		// function register_shipped_order_status()
		// {
		//     register_post_status('wc-shipped', array(
		//         'label'                     => _x('Shipped', 'wdm'),
		//         'public'                    => true,
		//         'exclude_from_search'       => false,
		//         'show_in_admin_all_list'    => true,
		//         'show_in_admin_status_list' => true,
		//         'label_count'               => _n_noop('Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>')
		//     ));
		// }
		
		// public function add_awaiting_shipment_to_order_statuses($order_statuses)
		// {
		//     $new_order_statuses = array();
		//     // add new order status after processing
		//     foreach ($order_statuses as $key => $status) {
		//         $new_order_statuses[$key] = $status;
		//         if ('wc-processing' === $key) {
		//             $new_order_statuses['wc-awaiting-shipment'] = __('Awaiting shipment');
		//         }
		//     }
		//     return $new_order_statuses;
		// }
		
		// public function add_shipped_to_order_statuses($order_statuses)
		// {
		//     $new_order_statuses = array();
		//     // add new order status after completed
		//     foreach ($order_statuses as $key => $status) {
		//         $new_order_statuses[$key] = $status;
		//         if ('wc-completed' === $key) {
		//             $new_order_statuses['wc-shipped'] = __('Shipped');
		//         }
		//     }
		//     return $new_order_statuses;
		// }
		
		/**
			* Add "Update Kwik Order Status" custom bulk action to the 'Orders' page bulk action drop-down
			*
			* @since 1.0
		*/
		public function add_order_bulk_actions()
		{
			global $post_type, $post_status;
			
			if ($post_type == 'shop_order' && $post_status != 'trash') {
			?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('select[name^=action]').append(
					$('<option>').val('update_order_status').text('<?php _e('Update Order Status (via kwik delivery)'); ?>')
                    );
				});
			</script>
			<?php
			}
		}
		
		/**
			* Processes the "Export to Shipwire" & "Update Tracking" custom bulk actions on the 'Orders' page bulk action drop-down
			*
			* @since  1.0
		*/
		public function process_order_bulk_actions()
		{
			global $typenow;
			
			if ('shop_order' == $typenow) {
				// get the action
				$wp_list_table = _get_list_table('WP_Posts_List_Table');
				$action        = $wp_list_table->current_action();
				
				// return if not processing our actions
				if (!in_array($action, array('update_order_status'))) {
					return;
				}
				
				// security check
				check_admin_referer('bulk-posts');
				
				// make sure order IDs are submitted
				if (isset($_REQUEST['post'])) {
					$order_ids = array_map('absint', $_REQUEST['post']);
				}
				
				// return if there are no orders to export
				if (empty($order_ids)) {
					return;
				}
				
				// give ourselves an unlimited timeout if possible
				@set_time_limit(0);
				
				foreach ($order_ids as $order_id) {
					try {
						wc_kwik_delivery()->update_order_shipping_status($order_id);
						} catch (\Exception $e) {
					}
				}
			}
		}
		
		/**
			* Add 'Update Shipping Status' order actions to the 'Edit Order' page
			*
			* @since 1.0
			* @param array $actions
			* @return array
		*/
		public function add_order_meta_box_actions($actions)
		{
			// add update shipping status action
			$actions['wc_kwik_delivery_update_status'] = __('Update Order Status (via kwik delivery)');
			
			return $actions;
		}
		
		
		/**
			* Handle actions from the 'Edit Order' order action select box
			*
			* @since 1.0
			* @param \WC_Order $order object
		*/
		public function process_order_meta_box_actions($order)
		{
			wc_kwik_delivery()->update_order_shipping_status($order);
		}
		
		
		/**
			* Add 'Kwik Delivery Information' meta-box to 'Edit Order' page
			*
			* @since 1.0
		*/
		public function add_order_meta_box()
		{
			add_meta_box(
            'wc_kwik_delivery_order_meta_box',
            __('Kwik Delivery'),
            array($this, 'render_order_meta_box'),
            'shop_order',
            'side'
			);
		}
		
		
		/**
			* Display the 'Kwik Delivery Information' meta-box on the 'Edit Order' page
			*
			* @since 1.0
		*/
		public function render_order_meta_box()
		{
			global $post;
			
			$order = wc_get_order($post);
			
			$kwik_order_id = $order->get_meta('kwik_delivery_order_id');
			
			if ($kwik_order_id && $kwik_order_id > 0) {
				$this->show_kwik_delivery_shipment_status($order);
				} else {
				$this->shipment_order_send_form($order);
			}
		}
		
		public function show_kwik_delivery_shipment_status($order)
		{
			$kwik_order_id = $order->get_meta('kwik_delivery_order_id');
		?>
		
        <table id="wc_kwik_delivery_order_meta_box">
            <tr>
                <th><strong><?php esc_html_e('Unique Order ID') ?> : </strong></th>
                <td><?php echo esc_html((empty($kwik_order_id)) ? __('N/A') : $kwik_order_id); ?></td>
			</tr>
			
            <tr>
                <th><strong><?php esc_html_e('Pickup Status') ?> : </strong></th>
                <td>
                    <?php echo $order->get_meta('kwik_delivery_pickup_status'); ?>
				</td>
			</tr>
			
            <tr>
                <th><strong><?php esc_html_e('Delivery Status') ?> : </strong></th>
                <td>
                    <?php echo $order->get_meta('kwik_delivery_delivery_status'); ?>
				</td>
			</tr>
		</table>
		<?php
		}
		
		public function shipment_order_send_form($order)
		{
		?> 
        <p> No scheduled task for this order</p>
		<?php
		}
		
		/**
			* Gets the main loader instance.
			*
			* Ensures only one instance can be loaded.
			*
			*
			* @return \WC_Kwik_Delivery_Loader
		*/
		public static function instance()
		{
			if (null === self::$instance) {
				self::$instance = new self();
			}
			
			return self::$instance;
		}
	}
	
	// fire it up!
	return WC_Kwik_Delivery_Orders::instance();
