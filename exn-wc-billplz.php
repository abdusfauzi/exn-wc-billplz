<?php
/**
 * Plugin Name: WooCommerce Billplz
 * Plugin URI: https://abdusfauzi.com
 * Description: The new payment gateway in Malaysia Asia to grow your business with Billplz payment solutions: FPX, Maybank, RHB, CIMB, Bank Islam, etc.
 * Author: Exnano Creative
 * Author URI: https://abdusfauzi.com
 * Version: 1.2.0
 * Tested up to: 4.5.2
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

    define( 'EXN_WC_BILLPLZ_PATH', plugin_dir_path( __FILE__ ) );
    define( 'EXN_WC_BILLPLZ_URL', plugin_dir_url( __FILE__ ) );

    /**
     * Require Billplz class file to ensure WooCommerce can load it
     *
     */
    require_once( 'class/class-exn-wc-billplz.php' );

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


/**
 * Run WP_GitHub_Updater to check for updates
 *
 */
function exn_wc_billplz_updater() {
	include_once 'updater/updater.php';

	if ( is_admin() ) { // note the use of is_admin() to double check that this is happening in the admin
        $repo = 'abdusfauzi/exn-wc-billplz';

		$config = array(
			'slug' => plugin_basename( __FILE__ ),
			'proper_folder_name' => 'exn-wc-billplz',
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


/**
 * Replace pay button on my-account with Billplz url and add new button (change payment method)
 *
 * @param array $actions
 * @param object $order
 * @return array
 */
function replace_pay_button( $actions, $order ) {
    $payment_method = $order->payment_method;

    if ( $payment_method ) {
        if ( isset( $payment_gateways[ $payment_method ] ) ) {
            $bill_url = $payment_gateways[ $payment_method ]->get_transaction_url( $order );
        }
    }

    if ( array_key_exists( 'pay', $actions ) && !empty( $bill_url ) ) {
        $actions['change_pay_method'] = array(
            'url' => $actions['pay']['url'],
            'name' => __( 'Change Payment', 'exn-wc-billplz' )
        );

        $actions['pay']['url'] = $bill_url;
    }

    if ( array_key_exists( 'cancel', $actions ) ) {
        $temp = $actions['cancel'];
        unset( $actions['cancel'] );

        $actions['cancel'] = array(
            'url' => $temp['url'],
            'name' => '&times;'
        );
    }

    return $actions;
}
add_filter( 'woocommerce_my_account_my_orders_actions', 'replace_pay_button', 10, 2 );


/**
 * Run migrate_data after updating to latest version
 *
 */
function exn_wc_billplz_data_check() {
    $meta = exn_wc_billplz_get_meta();

    if ( sizeof( $meta )>0 ) {
        add_action( 'admin_notices', 'exn_wc_billplz_need_migration' );
        add_action( 'in_admin_footer', 'exn_wc_billplz_js' );
        add_action( 'wp_ajax_exn_wc_billplz_migrate', 'exn_wc_billplz_run_migration' );
    }
}
add_action( 'plugins_loaded', 'exn_wc_billplz_data_check' );

function exn_wc_billplz_need_migration() {
    $message = '<div id="exn-wc-billplz-notice" class="notice notice-error">';
    $message .= '<p>' . sprintf( __( 'WooCommerce Billplz: Your order data need to do some update, due to certain data is outdated. %sClick here to update!%s', 'exn-wc-billplz' ), '<a id="exn-wc-billplz-migrate" class="button" href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=exn_wc_billplz">', '</a>' ) . '</p>';
    $message .= '</div>';
    echo $message;
}

function exn_wc_billplz_get_meta() {
    global $wpdb;
    // Query postmeta for _transaction_id to get post_id, and post_id == order_id
    $meta = $wpdb->get_results( "SELECT * FROM `" . $wpdb->postmeta . "` WHERE meta_key='_billplz_id'" );

    return $meta;
}

function exn_wc_billplz_run_migration() {
    $meta = exn_wc_billplz_get_meta();
    $return = array( 'continue' => false );

    if ( sizeof( $meta )>0 ) {
        // Update the Order _transaction_id meta
        update_post_meta( $meta[0]->post_id, '_transaction_id', $meta[0]->meta_value );
        // Then, delete the previous _billplz_id & _billplz_url meta
        delete_post_meta( $meta[0]->post_id, '_billplz_id' );
        delete_post_meta( $meta[0]->post_id, '_billplz_url' );

        $return = array(
            'post_id' => $meta[0]->post_id,
            'continue' => true
        );
    }

    // $return  = array( 'process' => 'start' );
    wp_send_json( $return );
}

function exn_wc_billplz_js() {
    ?>
    <script>
    (function($) {
        var currentUrl = window.location.protocol+'//'+location.hostname+location.pathname.substr(0, location.pathname.lastIndexOf("/"))+"/";

        var createButton = $( '#exn-wc-billplz-migrate' );
        var noticeMessage = $( '#exn-wc-billplz-notice' );
        var processing = 0;

        function migrateData() {
            $.ajax({
                type: 'POST',
                cache: false,
                url: ajaxurl,
                data: { action: 'exn_wc_billplz_migrate' },
                success: function( response ) {
                    console.log( response );
                    if ( response.continue===true ) {
                        processing++;
                        $( '.count' ).html( processing );
                        migrateData();
                    } else {
                        $( '.exn-status' ).html( 'Processing Done (' + processing + '). Thank you.' ).after( '<p><a href="#" class="button" onclick="window.location.reload(); return false;">Refresh page.</a></p>' );
                        $( '.notice').removeClass( 'notice-error' ).addClass( 'notice-success' );
                    }
                }
            });
        }

        createButton.on( 'click', function(e) {
            e.preventDefault();
            console.log( 'Migration starts..' );
            $( '.exn-status' ).remove();
            $( this ).hide();
            noticeMessage.append( '<p class="exn-status"><img src="'+currentUrl+'images/loading.gif"> Processing... (<span class="count">0</span>)</p>' );

            migrateData();
        });
    }(jQuery));
    </script>
    <?php
}
