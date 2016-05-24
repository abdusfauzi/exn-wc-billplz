<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define the Billplz gateway
 *
 */
class EXN_WC_Billplz extends WC_Payment_Gateway {

    public $sandbox_url  = 'https://billplz-staging.herokuapp.com/';
    public $live_url     = 'https://www.billplz.com/';

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
        $this->title                    = $this->settings['title'];
        $this->description              = $this->settings['description'];
        $this->secret_key               = $this->settings['secret_key'];
        $this->collection_id            = $this->settings['collection_id'];
        $this->collection_id_created    = $this->settings['collection_id_created'];
        $this->enable_sms               = $this->settings['enable_sms'];
        $this->sandbox                  = $this->settings['sandbox'];
        $this->sandbox_secret_key       = $this->settings['sandbox_secret_key'];
        $this->sandbox_collection_id    = $this->settings['sandbox_collection_id'];

        $this->api_url                  = $this->api_url();
        $this->active_api               = $this->active_api();

        // Actions.
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

        //save setting configuration
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // Payment listener/API hook : http://websitedomain/wc-api/EXN_WC_Billplz
        add_action( 'woocommerce_api_' . $this->methods, array( $this, 'callback_url' ) );

        $this->check_requirements();

        // add_action( 'admin_notices', array( $this, 'display_debug' ) );

    }

    /**
     * Get gateway icon.
     * @return string
     */
    public function get_icon() {
        $icon_html = '<img src="' . EXN_WC_BILLPLZ_URL . 'assets/billplz-logo-64.png" alt="' . esc_attr__( 'Billplz', 'woocommerce' ) . '" height="32" style="height:32px;"/>';

        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }

    /**
     * Return the gateway's description.
     *
     * @return string
     */
    public function get_description() {
        $description = $this->description  . '</p><p style="background-color:#fff;padding:1rem;text-align:center;border-radius:3px;"><img src="' . EXN_WC_BILLPLZ_URL . 'assets/billplz-banks.png" width="100%" style="max-width:247px;"></p>';

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
                'title' => __( 'Sandbox Mode', 'exn-wc-billplz' ),
                'type' => 'checkbox',
                'description' => '<a href="https://billplz-staging.herokuapp.com" target="_blank">Create development account</a>. Username: <code>billplz</code> &amp; Password: <code>onepiece</code>.',
                'label' => __( 'Enabled Sandbox (Test Mode)', 'exn-wc-billplz' ),
                'default' => 'no'
            ),
            'sandbox_secret_key' => array(
                'title' => __( 'Sandbox API Secret Key', 'exn-wc-billplz' ),
                'type' => 'text',
                'description' => sprintf( __( 'You can get this information in: %sSandbox Billplz Settings%s.', 'exn-wc-billplz' ), '<a href="https://billplz-staging.herokuapp.com/enterprise/setting" target="_blank">', '</a>' ),
                'default' => ''
            ),
            'sandbox_collection_id' => array(
                'title' => __( 'Sandbox Collection ID', 'exn-wc-billplz' ),
                'type' => 'text',
                'description' => sprintf( __( 'You can create or get this information in: %sSandbox Billplz Account%s.', 'exn-wc-billplz' ), '<a href="https://billplz-staging.herokuapp.com/enterprise/billing" target="_blank">', '</a>' ),
                'default' => ''
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

        // Prepare $description string
        $description = sprintf( __( 'Order %s' , 'woocommerce'), $order->get_order_number() ) . " - " . implode( ', ', $item_names );

        // Check if SMS Notification is enabled
        if ( $this->enable_sms=='yes' ) {
            $phone = $order->billing_phone;

            // Make sure phone number starts with +60
            if ($phone[0]=='0') {
                $phone = "+6" . $phone;
            } else {
                $phone = "+60" . $phone;
            }

        }

        $payload = array(
            'collection_id'     => $this->active_api['collection_id'],
            'email'             => $order->billing_email,
            'mobile'            => ( $this->enable_sms=='yes' ) ? $phone : null,
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

        $curl_url = $this->api_url . 'bills';

        // Send this payload to Billplz for processing
        $response = wp_safe_remote_post( $curl_url, $this->prepare_request( $payload ) );

        if ( !is_wp_error( $response ) ) {
            // Retrieve the body's resopnse if no errors found
            $billplz = json_decode( wp_remote_retrieve_body( $response ) );

            update_post_meta( $order->id, '_transaction_id', $billplz->id );

            // Remove cart
            WC()->cart->empty_cart();

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

        // Recheck if the payment is still pending
        if ( $order->status=='pending' ) {
            // Request from Billplz the payment status
            $curl_url = $this->api_url . 'bills/' . $bill_id;

            // Send this request to Billplz for processing
            $response = wp_safe_remote_get( $curl_url, $this->prepare_request() );

            if ( !is_wp_error( $response ) ) {
                $this->update_order_status( $response, $order );
            }
        } elseif ( $order->status=='completed' ) {
            wp_redirect( $order->get_checkout_order_received_url() );
            exit;
        }
    }

    /**
     * Check for Billplz callback_url Response
     *
     * @access public
     * @return void
     */
    function callback_url() {
        if ( !empty( $_POST['id'] ) ) {
            // Grab bill's id from Billplz $_POST request
            $bill_id = $_POST['id'];
            // Find $order_id based on $bill_id
            $order_id = $this->find_order_id( $bill_id );
            // new Object $order
            $order = new WC_Order( $order_id );

            // Recheck if the payment is still pending
            if ( $order->status=='pending' ) {
                // Request from Billplz the payment status
                $curl_url = $this->api_url . 'bills/' . $bill_id;

                // Send this request to Billplz for processing
                $response = wp_safe_remote_get( $curl_url, $this->prepare_request() );

                if ( !is_wp_error( $response ) ) {
                    $this->update_order_status( $response, $order );
                }
            }
        } else {
            wp_die( "Billplz Request Failure" );
        }
    }

    /**
     * Update $order status based on Billplz $response
     *
     */
    public function update_order_status( $response, $order ) {
        $post_request   = isset( $_POST['id'] ) ? true : false;
        $callback       = '';

        if ( $post_request ) {
            $callback = '[Callback] ';
        }

        // Retrieve the body's response if no errors found
        $billplz = json_decode( wp_remote_retrieve_body( $response ) );

        if ( $billplz->paid ) {
            if ( $order->status=='pending' ) {
                $order->add_order_note( $callback . 'Billplz Payment Status: SUCCESSFUL'.'<br>Transaction ID: ' . $billplz->id . '<br/><a href="' . $billplz->url .'" class="button">View Bill</a>' );
                $order->payment_complete( $billplz->id );

                if ( !$post_request ) {
                    wp_redirect( $order->get_checkout_order_received_url() );
                    exit;
                }
            }
        } else {
            switch( $billplz->state ) {
                case 'failed':
                    $order->update_status( 'failed', $callback . 'Billplz Payment Status: FAILED'.'<br>Transaction ID: ' . $billplz->id . '<br/><a href="' . $billplz->url .'" class="button">View Bill</a>' );
                    break;

                case 'due':
                    $order->add_order_note( $callback . 'Billplz Payment Status: DUE'.'<br>Transaction ID: ' . $billplz->id . '<br/><a href="' . $billplz->url .'" class="button">View Bill</a>' );
                    break;

                case 'overdue':
                    $order->add_order_note( $callback . 'Billplz Payment Status: OVERDUE'.'<br>Transaction ID: ' . $billplz->id . '<br/><a href="' . $billplz->url .'" class="button">View Bill</a>' );
                    break;
            }

            if ( !$post_request ) {
                echo '<p>This order is still pending for payment. You may click the button below to retry again.</p>';
                echo '<p>';

                if ( $billplz->state=='failed' ) {
                    echo '<a href="' . $billplz->url .'" class="button btn" target="_self">Retry Payment</a> <a href="' . $order->get_checkout_payment_url( false ) . '" class="button btn" target="_self">Change Payment Method</a>';
                } else {
                    echo '<a href="' . $billplz->url .'" class="button btn" target="_self">Retry Payment</a>';
                }

                echo '</p>';
            }
        }
    }

    /**
     * Get the transaction URL.
     * @param  WC_Order $order
     * @return string
     */
    public function get_transaction_url( $order ) {
        if ( $this->sandbox=='yes' ) {
            $this->view_transaction_url = $this->sandbox_url . 'bills/%s';
        } else {
            $this->view_transaction_url = $this->live_url . 'bills/%s';
        }
        return parent::get_transaction_url( $order );
    }

    /**
     * Get the api_url URL.
     * @param  WC_Order $order
     * @return string
     */
    public function api_url() {
        if ( $this->sandbox=='yes' ) {
            $url = $this->sandbox_url . '/api/v3/';
        } else {
            $url = $this->live_url . 'api/v3/';
        }

        return $url;
    }

    /**
     * prepare CURL/Remote query to the remote request URL.
     * @param  WC_Order $order
     * @return string
     */
    public function prepare_request( $payload='' ) {
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $this->active_api['secret_key'] . ':' )
            )
        );

        if ( !empty( $payload ) ) {
            $args['body'] = http_build_query( $payload );
        }

        return $args;
    }

    /**
     * set active secret_key to be used
     *
     */
    public function active_api() {
        if ( $this->sandbox=='yes' ) {
            $key['secret_key']      = $this->sandbox_secret_key;
            $key['collection_id']   = $this->sandbox_collection_id;
        } else {
            $key['secret_key']      = $this->secret_key;
            $key['collection_id']   = $this->collection_id;
        }

        return $key;
    }


    /**
     * Find $order_id from given $bill_id from Billplz
     * @param  Billplz $bill_id
     * @return string
     */
    public function find_order_id( $bill_id ) {
        global $wpdb;
        // Query postmeta for _transaction_id to get post_id, and post_id == order_id
        $meta = $wpdb->get_results( "SELECT * FROM `" . $wpdb->postmeta . "` WHERE meta_key='_transaction_id' AND meta_value='" . esc_sql( $bill_id ) . "'" );

        if (is_array($meta) && !empty($meta) && isset($meta[0])) {
            $meta = $meta[0];
        }
        if (is_object($meta)) {
            $order_id = $meta->post_id;
        }

        return $order_id;
    }


    /**
     * Check Billplz Requirements
     *
     */
    public function check_requirements() {
        // Checking if secret_key is not empty.
        if ( empty( $this->secret_key ) && empty( $this->sandbox_secret_key ) ) {
            add_action( 'admin_notices', array( $this, 'secret_key_missing_message' ) );
        }

        // Checking if collection_id is not empty.
        if ( empty( $this->collection_id ) && empty( $this->sandbox_collection_id ) ) {
            add_action( 'admin_notices', array( $this, 'collection_id_missing_message' ) );
        }
    }

    /**
     * Adds error message when not configured the app_key.
     *
     */
    public function secret_key_missing_message() {
        $message = '<div class="error">';
        $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should insert your API Secret Key in Billplz. %sClick here to configure!%s', 'exn-wc-billplz' ), '<a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=' . $this->methods . '">', '</a>' ) . '</p>';
        $message .= '</div>';
        echo $message;
    }

    /**
     * Adds error message when not configured the app_secret.
     *
     */
    public function collection_id_missing_message() {
        $message = '<div class="error">';
        $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should insert your Collection ID to start receiving payments. %sClick here to configure!%s', 'exn-wc-billplz' ), '<a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=' . $this->methods . '">', '</a>' ) . '</p>';
        $message .= '</div>';
        echo $message;
    }

    public function display_debug() {
        echo '<div class="notice notice-info"><pre>';
        print_r( $this->active_api );
        echo '</pre></div>';
    }
}
// END Class
