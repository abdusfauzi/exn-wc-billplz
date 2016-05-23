<?php
/**
 * Plugin Name: WooCommerce Billplz
 * Plugin URI: https://abdusfauzi.com
 * Description: The new payment gateway in Malaysia Asia to grow your business with Billplz payment solutions: FPX, Maybank, RHB, CIMB, Bank Islam, etc.
 * Author: Exnano Creative
 * Author URI: https://abdusfauzi.com
 * Version: 1.1.23
 * License: MIT
 * Text Domain: exn-wc-billplz
 * For callback : http://websitedomain/wc-api/EXN_WC_Billplz
 */

/**
 * Load Billplz gateway plugin function
 *
 * @return mixed
 */
function exn_wc_billplz_gateway_load() {
    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'exn_wc_billplz_woocommerce_fallback_notice' );
        return;
    }

    /**
     * Add Billplz gateway to ensure WooCommerce can load it
     *
     * @param array $methods
     * @return array
     */
    function exn_wc_billplz_add_gateway( $methods ) {
        $methods[] = 'EXN_WC_Billplz';
        return $methods;
    }
    add_filter( 'woocommerce_payment_gateways', 'exn_wc_billplz_add_gateway' );

    /**
     * Define the Billplz gateway
     *
     */
    class EXN_WC_Billplz extends WC_Payment_Gateway {

        /**
         * Construct the Billplz gateway class
         *
         * @global mixed $woocommerce
         */
        public function __construct() {
            global $woocommerce;

            $this->id = 'billplz';
            $this->methods = 'exn_wc_billplz';
            $this->has_fields = false;
            $this->method_title = __( 'Billplz', 'exn-wc-billplz' );

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user setting variables.
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->secret_key = $this->settings['secret_key'];
            $this->collection_id = $this->settings['collection_id'];
            $this->collection_id_created = $this->settings['collection_id_created'];
            $this->enable_sms = $this->settings['enable_sms'];
            $this->sandbox = $this->settings['sandbox'];

            // Actions.
            add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

            //save setting configuration
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Payment listener/API hook : http://websitedomain/wc-api/EXN_WC_Billplz
            add_action( 'woocommerce_api_' . $this->methods, array( $this, 'callback_url' ) );

            // add_action( 'wp_ajax_' . $this->methods, array( $this, 'ajax_create_collection') );
            // add_action( 'in_admin_footer', array( $this, 'set_ajax_js' ) );

            $this->api_url();
            $this->check_requirements();

            // add_action( 'admin_notices', array( $this, 'display_debug' ) );

        }

        public function api_url() {
            if ( $this->sandbox=='yes' ) {
                $this->pay_url = 'https://billplz-staging.herokuapp.com/api/v3/';
            } else {
                $this->pay_url = 'https://www.billplz.com/api/v3/';
            }
        }

        /**
    	 * Get gateway icon.
    	 * @return string
    	 */
    	public function get_icon() {
    		$icon_html = '<img src="' . plugins_url( 'assets/billplz-logo-64.png', __FILE__ ) . '" alt="' . esc_attr__( 'Billplz', 'woocommerce' ) . '" height="32" style="height:32px;"/>';

    		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    	}

        /**
    	 * Return the gateway's description.
    	 *
    	 * @return string
    	 */
    	public function get_description() {
            $description = $this->description  . '</p><p style="background-color:#fff;padding:1rem;text-align:center;border-radius:3px;"><img src="' . plugins_url( 'assets/billplz-banks.png', __FILE__ ) . '" style="max-width:247px;"></p>';

    		return apply_filters( 'woocommerce_gateway_description', $description, $this->id );
    	}

        /**
         * Checking if this gateway is enabled and available in the user's country.
         *
         * @return bool
         */
        public function is_valid_for_use() {
            if ( !in_array( get_woocommerce_currency(), array( 'MYR' ) ) ) {
                return false;
            }

            return true;
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis.
         *
         */
        public function admin_options() {
            ?>
            <h3><?php _e( 'Billplz Online Payment', 'exn-wc-billplz' ); ?></h3>
            <p><?php _e( 'Billplz Online Payment works by sending the user to Billplz to enter their payment information.', 'exn-wc-billplz' ); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table><!--/.form-table-->
            <?php
        }

        /**
         * Gateway Settings Form Fields.
         *
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'exn-wc-billplz' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Billplz', 'exn-wc-billplz' ),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __( 'Title', 'exn-wc-billplz' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'exn-wc-billplz' ),
                    'default' => __( 'Billplz Malaysia', 'exn-wc-billplz' ),
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => __( 'Description', 'exn-wc-billplz' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'exn-wc-billplz' ),
                    'default' => __( 'Pay through Malaysian online banking such as FPX, CIMBClicks, Maybank2u, RHB Now, Bank Islam, etc.', 'exn-wc-billplz' ),
                    'desc_tip' => true
                ),
                'secret_key' => array(
                    'title' => __( 'API Secret Key', 'exn-wc-billplz' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your Billplz API Secret Key.', 'exn-wc-billplz' ) . ' ' . sprintf( __( 'You can get this information in: %sBillplz Settings%s.', 'exn-wc-billplz' ), '<a href="https://www.billplz.com/enterprise/setting" target="_blank">', '</a>' ),
                    'default' => ''
                ),
                'collection_id' => array(
                    'title' => __( 'Collection ID', 'exn-wc-billplz' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your Billplz Collection ID.', 'exn-wc-billplz' ) . ' ' . sprintf( __( 'You can create or get this information in: %sBillplz Account%s.', 'exn-wc-billplz' ), '<a href="https://www.billplz.com/enterprise/billing" target="_blank">', '</a>' ),
                    'default' => ''
                ),
                'enable_sms' => array(
                    'title' => __( 'SMS Notification', 'exn-wc-billplz' ),
                    'type' => 'checkbox',
                    'description' => 'Attention! For every sms sent, RM0.15 fee will be charged from your Billplz credit.',
                    'label' => __( 'Enable SMS notifcation', 'exn-wc-billplz' ),
                    'default' => 'no'
                ),
                'sandbox' => array(
                    'title' => __( 'Sandbox API', 'exn-wc-billplz' ),
                    'type' => 'checkbox',
                    'description' => '<a href="https://billplz-staging.herokuapp.com" target="_blank">Create development account</a>. Username: <code>billplz</code> &amp; Password: <code>onepiece</code>. Please replace the API Secret Key and Collection ID too.',
                    'label' => __( 'Replace API with sandbox API url => https://billplz-staging.herokuapp.com', 'exn-wc-billplz' ),
                    'default' => 'no'
                )

            );
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {
            $order = new WC_Order( $order_id );

            $total = $order->order_total;
            $decimals = get_option( 'woocommerce_price_num_decimals' );

            // Checking and reformatting Total to non-decimal value (RM1 to 100 Cents)
            if ( $decimals > 2 ) {
                // i'm not sure who will use 3 decimals for RM
                $total = floor( $total * 100 );
            } elseif ( $decimals==0 || $decimals==2 ) {
                // set Total to cents, simple, RM1.05 x 100 = 105 cents or without decimal, RM1 x 100 = 100 cents
                $total = $total * 100;
            } else {
                // if there is 1 decimal just times 10
                $total = $total * 10;
            }

            if ( sizeof( $order->get_items() ) > 0 ) {
                foreach ( $order->get_items() as $item ) {
                    if ( $item['qty'] ) {
                        $item_names[] = $item['name'] . ' x ' . $item['qty'];
                    }
                }
            }

            $description = sprintf( __( 'Order %s' , 'woocommerce'), $order->get_order_number() ) . " - " . implode( ', ', $item_names );

            $payload = array(
                'collection_id'     => $this->collection_id,
                'email'             => $order->billing_email,
                'name'              => $order->billing_first_name." ".$order->billing_last_name,
                'amount'            => $total,
                'description'       => $description,
                'callback_url'      => home_url( '/wc-api/EXN_WC_Billplz' ),
                'redirect_url'      => $order->get_checkout_payment_url( true ),
                'deliver'           => true,
                'reference_1_label' => 'ID',
                'reference_2_label' => 'View Order',
                'reference_1'       => $order->id,
                'reference_2'       => $order->get_view_order_url()
            );

            // Check if SMS Notification is enabled
            if ( $this->enable_sms=='yes' ) {
                $phone = $order->billing_phone;

                // Make sure phone number starts with +60
                if ($phone[0]=='0') {
                    $phone = "+6" . $phone;
                } else {
                    $phone = "+60" . $phone;
                }

                // Add mobile/phone to $payload
                $payload['mobile'] = $phone;
            }

			$curl_url = $this->pay_url . 'bills';

            // Send this payload to Billplz for processing
        	$response = wp_remote_post( $curl_url, array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $this->secret_key. ':' )
                ),
        		'method'    => 'POST',
        		'body'      => http_build_query( $payload ),
        		'timeout'   => 90
        	) );

            if ( !is_wp_error( $response ) ) {
                // Retrieve the body's resopnse if no errors found
                $billplz = json_decode( wp_remote_retrieve_body( $response ) );

                update_post_meta( $order->id, '_billplz_id', $billplz->id );
                update_post_meta( $order->id, '_billplz_url', $billplz->url );

                // Reduce stock levels
        		$order->reduce_order_stock();

                // Remove cart
        		WC()->cart->empty_cart();

                if ( $billplz->state == "pending" ) {
                    $order->add_order_note( 'Billplz Payment Status: PENDING'.'<br>Transaction ID: ' . $billplz->id . '<br>' . $billplz->url );
                    $order->update_status( 'pending' );
                }

                return array(
        			'result'   => 'success',
        			'redirect' => $billplz->url
        		);
            }
        }

        /**
         * Output for the order received page.
         *
         */
        public function receipt_page( $order_id ) {
            $order = new WC_Order( $order_id );
            $bill_id = $_GET['billplz']['id'];

            // Recheck why the payment is still pending
            if ( $order->status=='pending' ) {
                // Request from Billplz the payment status
                $curl_url = $this->pay_url . 'bills/' . $bill_id;

                // Send this payload to Billplz for processing
            	$response = wp_remote_post( $curl_url, array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode( $this->secret_key. ':' )
                    ),
                    'method'    => 'GET',
            		'timeout'   => 90
            	) );

                if ( !is_wp_error( $response ) ) {
                    // Retrieve the body's resopnse if no errors found
                    $billplz = json_decode( wp_remote_retrieve_body( $response ) );

                    if ( $billplz->paid ) {
                        $order->add_order_note( 'Billplz Payment Status: SUCCESSFUL'.'<br>Transaction ID: ' . $billplz->id . '<br><a href="' . $billplz->url .'">View Bill</a>' );
                        $order->update_status( 'completed' );
                        wp_redirect( $order->get_checkout_order_received_url() );
                        exit();
                    } else {
                        echo '<p>This order is still pending for payment. You may click the button below to retry again.</p>';
                        echo '<p>';

                        if ( $billplz->state=='due' ) {
                            echo '<a href="' . $billplz->url .'" class="success button" target="_self">Retry Payment</a> <a href="' . $order->get_checkout_payment_url( false ) . '" class="button" target="_self">Change Payment Method</a>';
                        } else {
                            echo '<a href="' . $billplz->url .'" class="success button" target="_self">Retry Payment</a>';
                        }

                        echo '</p>';
                    }
                }
            } elseif ( $order->status=='completed' ) {
                wp_redirect( $order->get_checkout_order_received_url() );
                exit();
            }
        }

        /**
         * Check for Billplz callback_url Response
         *
         * @access public
         * @return void
         */
        function callback_url() {
            global $wpdb;

            if ( !empty( $_POST['id'] ) ) {

                // Grab bill's id from Billplz $_POST request
                $bill_id = $_POST['id'];

                // Query postmeta for _billplz_id to get post_id, and post_id == order_id
                $meta = $wpdb->get_results( "SELECT * FROM `" . $wpdb->postmeta . "` WHERE meta_key='_billplz_id' AND meta_value='" . esc_sql( $bill_id ) . "'" );

        		if (is_array($meta) && !empty($meta) && isset($meta[0])) {
        			$meta = $meta[0];
        		}
        		if (is_object($meta)) {
        			$order_id = $meta->post_id;
        		}

                $order = new WC_Order( $order_id );

                // Request from Billplz the payment status
                $curl_url = $this->pay_url . 'bills/' . $bill_id;

                // Send this payload to Billplz for processing
            	$response = wp_remote_post( $curl_url, array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode( $this->secret_key. ':' )
                    ),
                    'method'    => 'GET',
            		'timeout'   => 90
            	) );

                if ( !is_wp_error( $response ) ) {
                    // Retrieve the body's resopnse if no errors found
                    $billplz = json_decode( wp_remote_retrieve_body( $response ) );

                    if ( $billplz->paid ) {
                        $order->add_order_note( '[Callback] Billplz Payment Status: SUCCESSFUL'.'<br>Transaction ID: ' . $billplz->id . '<br><a href="' . $billplz->url .'">View Bill</a>' );
                        $order->update_status( 'completed' );
                        exit();
                    } else {
                        $order->add_order_note( 'Billplz Payment Status: PENDING'.'<br>Transaction ID: ' . $billplz->id . '<br><a href="' . $billplz->url .'">View Bill</a>' );
                        exit();
                    }
                }
            } else {
                wp_die( "Billplz Request Failure" );
            }
        }

        /* Development for future features
        public function sms_enabled() {

        }

        public function set_ajax_js() {
            ?>
            <script>
            (function($) {
                var currentUrl = window.location.protocol+'//'+location.hostname+location.pathname.substr(0, location.pathname.lastIndexOf("/"))+"/";
                var data = {
                    action: 'exn_wc_billplz'
                };

                var createButton = $( '#woocommerce_billplz_create_collection_id' );

                createButton.on('click', function(e) {
                    e.preventDefault();
                    $('span.exn-status').remove();

                    // console.log( ajaxurl );

                    $(this).hide().after('<span class="exn-status updated"><img src="'+currentUrl+'images/loading.gif"> Creating...</span>');

                    $.post(
                        ajaxurl,
                        data,
                        function(response) {
                            console.log(response);
                            $('span.exn-status').html(response);
                            createButton.show();
                        }
                    );

                    // #woocommerce_billplz_collection_id
                    // #mainform .submit
                });

            }(jQuery));
            </script>
            <?php
        }

        public function ajax_create_collection() {

        }
        */

        /**
         * Check Billplz Requirements
         *
         */
        public function check_requirements() {
            // Checking if secret_key is not empty.
            if ( empty( $this->secret_key ) ) {
                add_action( 'admin_notices', array( $this, 'secret_key_missing_message' ) );
            }

            // Checking if collection_id is not empty.
            if ( empty( $this->collection_id ) ) {
                add_action( 'admin_notices', array( $this, 'collection_id_missing_message' ) );
            }
        }

        /**
         * Adds error message when not configured the app_key.
         *
         */
        public function secret_key_missing_message() {
            $message = '<div class="error">';
            $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should insert your API Secret Key in Billplz. %sClick here to configure!%s' , 'exn-wc-billplz' ), '<a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=' . $this->methods . '">', '</a>' ) . '</p>';
            $message .= '</div>';
            echo $message;
        }

        /**
         * Adds error message when not configured the app_secret.
         *
         */
        public function collection_id_missing_message() {
            $message = '<div class="error">';
            $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should insert your Collection ID to start receiving payments. %sClick here to configure!%s' , 'exn-wc-billplz' ), '<a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=' . $this->methods . '">', '</a>' ) . '</p>';
            $message .= '</div>';
            echo $message;
        }

        public function display_debug() {
            echo '<div class="notice notice-info"><pre>';
            print_r( $this->pay_url );
            echo '</pre></div>';
        }

    }
    // END Class

    // Replace pay button on my-account with Billplz url and add new button (change payment method)
    function replace_pay_button( $actions, $order ) {
        $bill_url = get_post_meta( $order->id, '_billplz_url', true );

        if ( array_key_exists( 'pay', $actions ) && !empty( $bill_url ) ) {
            $actions['change_pay_method'] = array(
                'url' => $actions['pay']['url'],
                'name' => __( 'Change Payment', 'exn-wc-billplz' )
            );

            $actions['pay']['url'] = $bill_url;
        }

        $temp = $actions['cancel'];
        unset( $actions['cancel'] );

        $actions['cancel'] = array(
            'url' => $temp['url'],
            'name' => '&times;'
        );

        return $actions;
    }
    add_filter( 'woocommerce_my_account_my_orders_actions', 'replace_pay_button', 10, 2 );
}
//Load the function
add_action( 'plugins_loaded', 'exn_wc_billplz_gateway_load' );


/**
 * If WooCommerce plugin is not available
 *
 */
function exn_wc_billplz_woocommerce_fallback_notice() {
    $message = '<div class="error">';
    $message .= '<p>' . __( 'WooCommerce Billplz Gateway depends on the last version of <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> to work!' , 'exn-wc-billplz' ) . '</p>';
    $message .= '</div>';
    echo $message;
}


function exn_wc_billplz_updater() {
	include_once 'updater/updater.php';

	if ( is_admin() ) { // note the use of is_admin() to double check that this is happening in the admin
        $repo = 'abdusfauzi/exn-wc-billplz';

		$config = array(
			'slug' => plugin_basename( __FILE__ ),
			'proper_folder_name' => 'github-updater',
			'api_url' => 'https://api.github.com/repos/' . $repo,
			'raw_url' => 'https://raw.github.com/' . $repo . '/master',
			'github_url' => 'https://github.com/' . $repo,
			'zip_url' => 'https://github.com/' . $repo . '/archive/master.zip',
			'sslverify' => true,
			'requires' => '4.0',
			'tested' => '4.5.2',
			'readme' => 'README.md',
			'access_token' => '',
		);

		new WP_GitHub_Updater( $config );

	}
}
add_action( 'init', 'exn_wc_billplz_updater' );
