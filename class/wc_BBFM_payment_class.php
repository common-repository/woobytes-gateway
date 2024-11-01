<?php


class wc_BBFM_payment
{

    var $BBFM_API_URL = 'https://obyte-for-merchants.com/api/ask_payment.php';
    var $CASHBACK_API_URL = 'https://byte.money/new_purchase';

    function __construct()
    {

        global $BBFM_WC_Logger;// our WC_Logger instance


        if( get_option( 'wc_bbfm_enable' ) ){

            global $wc_BBFM_settings;

            // Add this Gateway to WooCommerce
            add_filter('woocommerce_payment_gateways', array( $this, 'woocommerce_add_bbfm_gateway' ) );

            // Display payment unit on the order details table
            add_action('woocommerce_order_details_after_order_table', array( $this, 'display_unit_byteball_explorer_link'), 10, 1);

            // Display Obyte payment button on thankyou page
            add_action('wp_enqueue_scripts', array( $this, 'load_paybutton_scripts') );
            add_action('woocommerce_thankyou', array( $this, 'render_paybutton') );

            add_filter('woocommerce_get_formatted_order_total', array( $this, 'display_total_in_byteball' ), 10, 2 );

            // Payment listener/API hook
            add_action('woocommerce_api_wc_gateway_bbfm', array(
                $this,
                'handle_bbfm_notifications'
            ));


            // New order customer notification

            // if( get_option( 'wc_bbfm_new_order_customer_notif' , $wc_BBFM_settings->defaults[ 'new_order_customer_notif' ] ) ){ // does not trigger when option is not yet defined (!?)
            if( get_option( 'wc_bbfm_new_order_customer_notif' , true ) ){

                // woocommerce_new_order hook is too early and woocommerce_thankyou hook could trigger many times, so we choose woocommerce_checkout_order_processed hook

                add_action( 'woocommerce_checkout_order_processed', array( $this, 'new_order_customer_notification' ), 10, 1 );

            }



            // cashback program
            if( get_option( 'wc_bbfm_partner' ) ){

                // add customer Obyte address to checkout fields
                add_filter( 'woocommerce_checkout_fields' , array( $this, 'add_customer_byteball_address_field') );

                // validate Obyte address
                add_action('woocommerce_checkout_process',  array( $this, 'check_order_byteball_address' ) );

                // Display field value on the order edit page
                add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_order_byteball_address'), 10, 1 );

                // Make casback api request as soon as an order is set to completed
                add_action( 'woocommerce_order_status_completed', array( $this, 'make_cashback_api_request'), 10, 1 );

            }

        }


    }



    function new_order_customer_notification( $order_id ) {

        // Get an instance of the WC_Order object
        $order = wc_get_order( $order_id );

        // Only for "pending" order status
        if( ! $order->has_status( 'pending' ) ) return;

        // Only for Obyte payments
        if( $order->get_payment_method() !== 'bbfm' ) return;

        // check that customer invoice email is well defined
        $wc_customer_invoice_email = WC()->mailer()->get_emails()['WC_Email_Customer_Invoice'];
        if( empty( $wc_customer_invoice_email ) ) return;

        // Send mail to customer
        $wc_customer_invoice_email->trigger( $order_id, $order );

    }




    function make_cashback_api_request( $order_id ) {

        global $wc_BBFM_tools;

        // log
        $wc_BBFM_tools->log( 'debug', "order_id $order_id completed" );

        $partner = get_option( 'wc_bbfm_partner' );

        if( ! $partner ){
            $wc_BBFM_tools->log( 'debug', "no partner name found" );
            return;
        }

        $address = get_post_meta( $order_id, '_billing_byteball_address', true);

        if( ! $address ){
            $wc_BBFM_tools->log( 'debug', "no _billing_byteball_address found" );
            return;
        }


        /*
         * set up request
         */

        $wc_order = new WC_Order($order_id);

        if( $wc_order->get_payment_method() == 'bbfm' ){
            $currency = 'GBYTE';
            $currency_amount = get_post_meta( $order_id, '_wc_bbfm_received_amount', true);
            $currency_amount = $currency_amount * (1/1000000000);
        }
        else{
            $currency = get_woocommerce_currency();
            $currency_amount = $wc_order->get_total();
        }

        $data = array(
            'partner' => $partner,
            'partner_key' => get_option( 'wc_bbfm_partner_key' ),
            'customer' => $wc_order->get_customer_id(),
            'order_id' => $order_id,
            'description' => 'woocommerce sale',// ?
            'merchant' => $partner, // ?
            'address' => $address,
            'currency' => $currency,
            'currency_amount' => $currency_amount,
            'partner_cashback_percentage' => get_option( 'wc_bbfm_partner_cashback_percent', '0' ),
            'purchase_unit' => get_post_meta( $order_id, '_wc_bbfm_receive_unit', true),
        );


        // for cashback server specific config
        add_action( 'http_api_curl', array( $this, 'customize_curl_options' ) );


        /*
         * make request
         */


        // logs
        $log_msg = "** cashback request( $order_id ) ***";
        $log_msg .= " post data : " . wc_print_r( $data, true );
        $wc_BBFM_tools->log( 'debug', $log_msg );

        $response = wp_remote_post( $this->CASHBACK_API_URL, array( 'body' => $data ) );

        // logs
        $log_msg = "*** response cashback request( $order_id ) ***";
        $log_msg .= " " . wc_print_r( $response, true );
        $wc_BBFM_tools->log( 'debug', $log_msg );


        // error
        if( is_wp_error( $response ) ){

            $wc_order->add_order_note(__('Curl error on cashback api request', 'bbfm-woocommerce') .' : ' . $response->get_error_message() );

            update_post_meta( $order_id, '_wc_bbfm_cashback_result', 'error' );
            update_post_meta( $order_id, '_wc_bbfm_cashback_error', $response->get_error_message() );

            return;

        }

        $returned_body_json = $response[ 'body' ];
        $returned_body = json_decode( $returned_body_json, true );

        if( $returned_body[ 'result' ] == 'error' ){
            $cashback_error_msg = $returned_body[ 'error' ];
            $wc_order->add_order_note(__('Error returned on cashback api request', 'bbfm-woocommerce') .' : ' . $cashback_error_msg );
            update_post_meta( $order_id, '_wc_bbfm_cashback_error', sanitize_text_field( $cashback_error_msg ) );
        }
        else if( $returned_body[ 'result' ] == 'ok' ){
            $cashback_amount = $returned_body[ 'cashback_amount' ];
            $cashback_unit = $returned_body[ 'unit' ];

            $wc_order->add_order_note(__('Cashback request has been processed', 'bbfm-woocommerce') .' -  cashback byte amount : ' . $cashback_amount . ' - cashback unit : ' . $cashback_unit );

            update_post_meta( $order_id, '_wc_bbfm_cashback_amount', sanitize_text_field( $cashback_amount ) );
            update_post_meta( $order_id, '_wc_bbfm_cashback_unit', sanitize_text_field( $cashback_unit ) );
        }
        else{
            $wc_order->add_order_note( 'Unhandled returned result on cashback API request : ' . $returned_body[ 'result' ] );
        }

        update_post_meta( $order_id, '_wc_bbfm_cashback_result', sanitize_text_field( $returned_body[ 'result' ] ) );

        return;


    }


    function load_paybutton_scripts() {

        global $wc_BBFM_tools;

        $order_id = null;

        if( isset( $_REQUEST["order-received"] ) ){
            $order_id = $_REQUEST["order-received"];
        }
        else if( isset( $_REQUEST["key"] ) ){
            $order_key = wc_clean( $_REQUEST["key"] );
            $order_id = wc_get_order_id_by_order_key( $order_key );
        }

        if( $order_id ){

            $data = $this->set_payment_data( $order_id );

            $wc_BBFM_tools->log( 'debug', 'paybutton data : ' . wc_print_r( $data, true ) );

            wp_enqueue_style(
                'bbfm_style',
                WC_BBFM_PATH . 'bbfm-style.css'
            );

            wp_enqueue_script(
                'bbfm_payment_button',
                'https://obyte-for-merchants.com/api/payment-button.js'
            );

            wp_localize_script( 'bbfm_payment_button', 'bbfm_params', $data );

        }

    }


    function render_paybutton( $order_id ){

        $order = new WC_Order($order_id);

        if( $order->get_payment_method() == 'bbfm' ){

            global $wc_BBFM_settings;

            echo "<div id=\"bbfm_before_paybutton_info\">" . get_option( 'wc_bbfm_before_paybutton_msg', $wc_BBFM_settings->defaults[ 'before_paybutton_msg' ] ) . "</div>";

            echo "<div id=\"bbfm_container\"></div>";// and just let the bbfm's magic js operate !

            echo "<div id=\"bbfm_after_paybutton_info\">" . get_option( 'wc_bbfm_after_paybutton_msg', $wc_BBFM_settings->defaults[ 'after_paybutton_msg' ] ) . "</div>";

            global $wc_BBFM_tools;
            $wc_BBFM_tools->log( 'debug', 'render paybutton' );

        }

    }


    function display_total_in_byteball( $formatted_total, $order ){

        if( $order->get_payment_method() == 'bbfm' and get_post_meta( $order->id, '_wc_bbfm_amount_BB_asked', true ) ){

            global $wc_BBFM_tools;

            // $formatted_total = $formatted_total . ' ( ' . number_format_i18n( get_post_meta( $order->id, '_wc_bbfm_amount_BB_asked', true ) ) . ' bytes )';
            $formatted_total = $formatted_total . ' (' . $wc_BBFM_tools->byte_format( get_post_meta( $order->id, '_wc_bbfm_amount_BB_asked', true ) ) . ')';

        }

        return $formatted_total;

    }





    function check_order_byteball_address() {

        global $wc_BBFM_tools;

        if ( isset( $_POST['billing_byteball_address'] ) and $_POST['billing_byteball_address'] ){

            // if( ! $wc_BBFM_tools->check_BB_address( $_POST['billing_byteball_address'] ) ){
            //     wc_add_notice( 'Invalid Obyte address' , 'error' );
            // }

            if( ! $wc_BBFM_tools->check_BB_address( $_POST['billing_byteball_address'] ) ){

                if ( ! is_email( $_POST['billing_byteball_address'] ) ){

                    wc_add_notice( 'Invalid <strong>cashback address</strong> (should be a valid Obyte or email address).' , 'error' );

                }

            }

        }
        else if( isset( $_POST[ 'payment_method' ] ) and $_POST[ 'payment_method' ] == 'bbfm' ){

            wc_add_notice( "You must enter a <strong>cashback address</strong> if you pay with bytes ( otherwise you won't receive any cashback! )." , 'error' );


        }
    }


    function add_invalid_class_to_byteball_address_field( $fields ){

        $fields['billing']['billing_byteball_address']['required'] = true;
        $fields['billing']['billing_byteball_address']['label'] .= '<abbr class="required" title="requis">*</abbr>';

        return $fields;

    }


    function add_customer_byteball_address_field( $fields ) {

        global $wc_BBFM_settings;

        $cashback_addr_label = "<strong>* Cashback address *</strong><br>" . get_option( 'wc_bbfm_cashback_addr_msg', $wc_BBFM_settings->defaults[ 'cashback_address_msg' ] );

         $fields['billing']['billing_byteball_address'] = array(
            'label'     => $cashback_addr_label,
            'placeholder'   => _x('your Obyte or email address', 'placeholder', 'woocommerce'),
            'required'  => false,
            'class'     => array('form-row-wide'),
            'clear'     => true,
            'validate'  => false,
         );

         return $fields;

    }


    function display_admin_order_byteball_address( $order ){
        echo '<p><strong>'.__('Cashback Obyte address').':</strong> ' . get_post_meta( $order->get_id(), '_billing_byteball_address', true ) . '</p>';
    }


    /*
     * ask BBFM server for a Obyte payment address
     */

    function ask_payment( $order_id ){
        global $wc_BBFM_tools;

        $data = $this->set_payment_data( $order_id );

        // logs
        $log_msg = "** ask_payment( $order_id ) ***";
        $log_msg .= " post data : " . wc_print_r( $data, true );
        $wc_BBFM_tools->log( 'debug', $log_msg );

        $response = wp_remote_post( $this->BBFM_API_URL, array( 'body' => $data ) );

        // logs
        $log_msg = "*** response ask_payment( $order_id ) ***";
        $log_msg .= " " . wc_print_r( $response, true );
        $wc_BBFM_tools->log( 'debug', $log_msg );


        // error
        if( is_wp_error( $response ) ){
            $return_error = $this->return_error( $response->get_error_message() );
            return $return_error;
        }

        return $response[ 'body' ];
    }


    /*
     * handle notif received fom BBFM server
     */

    function handle_bbfm_notifications(){

        global $wc_BBFM_tools;

        # logs
        $wc_BBFM_tools->log( 'debug', 'notification received : ' . wc_print_r( $_REQUEST, true ) );

        /*
         * notification IP security
         */


        # check notif IP
        $notif_IP = $_SERVER[ 'REMOTE_ADDR' ];
        $allowed_notif_IPs = get_option( 'wc_bbfm_allowed_notif_IPs', array('178.128.243.234') ); //default BBFM server IP

        if( ! in_array( $notif_IP, $allowed_notif_IPs ) ){
            // logs
            $wc_BBFM_tools->log( 'debug', 'unallowed notif IP ' . $notif_IP . ' : notification will be ignored' );
            wp_die( 'unallowed notif IP', 403 );
        }

        # logs
        $wc_BBFM_tools->log( 'debug', 'allowed notif IP ' . $notif_IP );

        # manage allowed_notif_IPs changes
        $new_allowed_notif_IPs = isset($_REQUEST['allowed_notif_IPs']) ? $_REQUEST['allowed_notif_IPs'] : "";

        if( $new_allowed_notif_IPs ){
            $update_allowed_notif_IPs = array( $notif_IP ); // always keep current notif IP

            # and then add new IPs
            foreach( $new_allowed_notif_IPs as $IP ){
                if( ! in_array( $IP, $update_allowed_notif_IPs ) ){
                    $update_allowed_notif_IPs[] = $IP;
                }
            }
            update_option( 'wc_bbfm_allowed_notif_IPs', $update_allowed_notif_IPs );
        }

        # first read secret key bbfm should be the only one to know
        $secret_key = isset($_REQUEST['secret_key']) ? $_REQUEST['secret_key'] : "";

        if( $secret_key ){

            $callback_secret = get_option("wc_bbfm_callback_secret");

            # bad secret_key
            if( $callback_secret != $secret_key ){
                $wc_BBFM_tools->log( 'debug', 'notif secret_key not equal to registered callback_secret (' . $callback_secret . ')' );
                wp_die( 'bad secret key', 403 );
            }

            # now authentified notif
            $order_id = isset($_REQUEST['order_UID']) ? $_REQUEST['order_UID'] : "";
            $result = isset($_REQUEST['result']) ? $_REQUEST['result'] : "";
            $cashback_result = isset($_REQUEST['cashback_result']) ? $_REQUEST['cashback_result'] : "";


            if( $result ){
                # order payment status
                $amount_asked_in_currency = $this->sanitize_and_register_input( 'amount_asked_in_currency', $order_id );
                $currency_B_rate = $this->sanitize_and_register_input( 'currency_B_rate', $order_id );
                $received_amount = $this->sanitize_and_register_input( 'received_amount', $order_id );
                $receive_unit = $this->sanitize_and_register_input( 'receive_unit', $order_id );
                $fee = $this->sanitize_and_register_input( 'fee', $order_id );
                $amount_sent = $this->sanitize_and_register_input( 'amount_sent', $order_id );
                $unit = $this->sanitize_and_register_input( 'unit', $order_id );
                $result = $this->sanitize_and_register_input( 'result', $order_id );

                $wc_order = new WC_Order( $order_id );

                # result nok
                if( $result == 'nok' ){

                    $error_msg = $this->sanitize_and_register_input( 'error_msg', $order_id );

                    $wc_order->update_status('failed', __($error_msg, 'bbfm-woocommerce'));

                    wp_die( 'ok', 200 );

                }

                # result receiving
                if( $result == 'receiving' ){
                    $wc_order->update_status('on-hold', __( 'Costumer payment has been received by BBFM server (not yet network confirmed).', 'bbfm-woocommerce' ));
                    wp_die( 'ok', 200 );
                }

                # result received
                if( $result == 'received' ){
                    $wc_order->add_order_note(__('Costumer payment has been confirmed by the network.', 'bbfm-woocommerce'));
                    wp_die( 'ok', 200 );
                }

                # result unconfirmed
                if( $result == 'unconfirmed' ){
                    $wc_order->add_order_note(__('Payment has been sent to you but is still waiting for network confirmation.', 'bbfm-woocommerce'));
                    wp_die( 'ok', 200 );
                }

                # result completed
                if( $result == 'ok' ){
                    # check received_amount (!)
                    if( $received_amount != get_option('_wc_bbfm_amount_BB_asked', true) ){
                        $error_msg = 'Received amount does not match asked amount';
                        $wc_order->update_status('failed', __($error_msg, 'bbfm-woocommerce'));
                    }
                    else{
                        $wc_order->add_order_note(__('Payment completed', 'bbfm-woocommerce'));
                        $wc_order->payment_complete( $unit );
                    }
                    wp_die( 'ok', 200 );
                }

                $wc_BBFM_tools->log( 'debug', 'not handled result' );
                wp_die( 'not handled result', 200 );

            }
            else if( $cashback_result ){

                /*
                 * cashback status
                 */

                $cashback_result = $this->sanitize_and_register_input( 'cashback_result', $order_id );
                $cashback_error_msg = $this->sanitize_and_register_input( 'cashback_error_msg', $order_id );
                $cashback_amount = $this->sanitize_and_register_input( 'cashback_amount', $order_id );
                $cashback_unit = $this->sanitize_and_register_input( 'cashback_unit', $order_id );
                // $cashback_notified = $this->sanitize_and_register_input( 'cashback_notified', $order_id );

                $wc_order = new WC_Order( $order_id );

                # cashback_result ok
                if( $cashback_result == 'ok' ){
                    $wc_order->add_order_note( __('Cashback successfully processed', 'bbfm-woocommerce') . ". $cashback_amount bytes sent on unit $cashback_unit" );
                    wp_die( 'ok', 200 );
                }

                # cashback_result error
                if( $cashback_result == 'error' ){
                    $wc_order->add_order_note(__('Error on cashback api request', 'bbfm-woocommerce') . ' : ' . $cashback_error_msg );
                    wp_die( 'ok', 200 );
                }

                $wc_BBFM_tools->log( 'debug', 'not handled cashback result' );
                wp_die( 'not handled cashback result', 200 );

            }

        }

        $wc_BBFM_tools->log( 'debug', 'Unauthorized request' );
        wp_die( 'Unauthorized', 401 );


    }// handle_bbfm_notifications


    function sanitize_and_register_input( $var_name, $order_id ){
        $value = isset($_REQUEST[ $var_name ]) ? $_REQUEST[ $var_name ] : "";
        $sanitized_value = sanitize_text_field( $value );
        update_post_meta( $order_id, '_wc_bbfm_' . $var_name, $sanitized_value );

        return $sanitized_value;
    }


    function set_payment_data( $order_id ){

        $order = new WC_Order($order_id);

        $data = array(
            'mode' => 'live',
            'mode_notif' => 'POST',
            'order_UID' => $order_id,
            'currency' => get_woocommerce_currency(),
            'merchant_return_url' => WC()->api_request_url('WC_Gateway_BBFM'),
            'amount' => $order->get_total(),
            'merchant_email' => get_option( 'wc_bbfm_merchant_email' ),
            'partner' => get_option( 'wc_bbfm_partner' ),
            'partner_key' => get_option( 'wc_bbfm_partner_key' ),
            'partner_cashback_percentage' => get_option( 'wc_bbfm_partner_cashback_percent' ),
            'customer' => $order->get_customer_id(),
            'description' => 'woocommerce sale',// ?
            'byteball_merchant_address' => get_option( 'wc_bbfm_byteball_address' ),
            'callback_secret' => $this->bbfm_callback_secret(),
            'cashback_address' => get_post_meta( $order_id, '_billing_byteball_address', true),
            'display_powered_by' => get_option( 'wc_bbfm_display_powered_by' ),
            'WC_BBFM_VERSION' => WC_BBFM_VERSION,
        );

        return $data;

    }


    function bbfm_callback_secret()
    {
        $callback_secret = get_option("wc_bbfm_callback_secret");

        if ( !$callback_secret ) {

            $callback_secret = sha1(openssl_random_pseudo_bytes(20));

            // logs
            global $wc_BBFM_tools;
            $wc_BBFM_tools->log( 'debug', 'new callback_secret created : ' . wc_print_r( $callback_secret, true ) );

            update_option("wc_bbfm_callback_secret", $callback_secret);

        }

        return $callback_secret;
    }


    function return_error( $msg ){

        $return = array(
            'result' => 'nok',
            'error_msg' => $msg,
        );

        return json_encode($return);

    }


    function display_unit_byteball_explorer_link( $order ){

        $receive_unit = get_post_meta($order->id, '_wc_bbfm_receive_unit', true);

        if( $receive_unit ){
            echo '<p><strong>'.__('Byte payment unit', 'bbfm-woocommerce').':</strong>  <a href ="' . 'https://explorer.obyte.org/#' . $receive_unit . '" target="blank" title="see unit in Obyte explorer" >' . $receive_unit . '</a></p>';
        }

    }


    function woocommerce_add_bbfm_gateway($methods){

        $methods[] = 'WC_Gateway_BBFM';
        return $methods;

    }


    function customize_curl_options( $curl ) {

        if ( ! $curl ) {
            return;
        }

        $curl_getinfo = curl_getinfo( $curl );

        # just for cashback server requests
        if( $curl_getinfo['url'] != $this->CASHBACK_API_URL){
            return;
        }

        // global $wc_BBFM_tools;
        // $wc_BBFM_tools->log( 'debug', 'curl_getinfo : ' . wc_print_r( curl_getinfo($curl), true ) );

        # to avoid cURL error 35: Cannot communicate securely with peer: no common encryption algorithm(s) ]
        // curl_setopt( $curl, CURLOPT_SSL_CIPHER_LIST, 'ecdhe_ecdsa_aes_256_sha' );

        $wc_bbfm_partner_SSL_CIPHER_LIST = get_option("wc_bbfm_partner_SSL_CIPHER_LIST");

        if( $wc_bbfm_partner_SSL_CIPHER_LIST ){
            curl_setopt( $curl, CURLOPT_SSL_CIPHER_LIST, $wc_bbfm_partner_SSL_CIPHER_LIST );
        }

        return;

    }


}


