<?php
/**
 * Plugin Name:     WooBytes gateway
 * Plugin URI:      https://github.com/ByteFan/Byteball-for-Woocommerce
 * Description:     Accept Obyte bytes on your WooCommerce-powered website with Obyte-for-Merchants
 * Version:         1.2.0
 * Author:          Obyte for Merchants
 * Author URI:      https://obyte-for-merchants.com/
 * Text Domain:     bbfm-woocommerce
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 */



if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!defined('WC_BBFM_VERSION')) {
    define( 'WC_BBFM_VERSION', '1.2.0' );
}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );


/*
 * activate the admin notice to go on plugin settings page
 */

function wc_bbfm_plugin_activate() {

    # check PHP
    // if (version_compare(PHP_VERSION, '5.4', '<')) {
    //     deactivate_plugins(basename(__FILE__));
    //     wp_die(__('<p><strong>WooBytes Gateway</strong> plugin requires PHP version 5.4 or greater.</p>', 'bbfm-woocommerce'));
    // }

    update_option("wc_bbfm_seen_settings_page", false);

}
register_activation_hook( __FILE__, 'wc_bbfm_plugin_activate' );



if ( is_plugin_active( 'woocommerce/woocommerce.php') || class_exists( 'WooCommerce' )) {

    define( 'WC_BBFM_PATH', plugin_dir_url( __FILE__ ) );
    define( 'WC_BBFM_BASENAME', plugin_basename( __FILE__ ) );
    define( 'WC_BBFM_CLASS_PATH', plugin_dir_path(__FILE__) . 'class/' );


    // wc_BBFM_tools class
    require_once(WC_BBFM_CLASS_PATH . 'wc_BBFM_tools_class.php');
    $wc_BBFM_tools = new wc_BBFM_tools;


    // wc_BBFM_payment class
    require_once(WC_BBFM_CLASS_PATH . 'wc_BBFM_payment_class.php');
    $wc_BBFM_payment = new wc_BBFM_payment;


    // wc_BBFM_settings class
    require_once(WC_BBFM_CLASS_PATH . 'wc_BBFM_settings_class.php');
    $wc_BBFM_settings = new wc_BBFM_settings;


    // WP_List_Table is not loaded automatically so we need to load it in our application
    if( ! class_exists( 'WP_List_Table' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
    }

    // wc_BBFM_report class
    require_once(WC_BBFM_CLASS_PATH . 'wc_BBFM_report_class.php');
    $wc_BBFM_report = new wc_BBFM_report;

    // to save logs in DB (WC doc says to put it in wp-config.php but it is not really a plugin territory)
    if( ! defined( 'WC_LOG_HANDLER' ) ) define( 'WC_LOG_HANDLER', 'WC_Log_Handler_DB' );




    function bbfm_woocommerce_init()
    {

        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }


        /*
         * check woocommerce version
         */

        global $woocommerce;
        $wc_bbfm_woocommerce_required_version = '3.0';

        if ( version_compare($woocommerce->version, $wc_bbfm_woocommerce_required_version, '<') ) {

            deactivate_plugins( plugin_basename( __FILE__ ) );

            wp_die( __( 'WooBytes Gateway requires WooCommerce ' . $wc_bbfm_woocommerce_required_version . ' or higher', 'bbfm-woocommerce' ) . '<br>' . '<a href="' . admin_url( 'plugins.php' ) . '">back to plugins</a>' );

        }


        /*
         * setup logs
         */

        global $BBFM_WC_Logger;

        // $BBFM_WC_Logger = new WC_Logger( null, get_option('wc_bbfm_log_level', true) );
        $BBFM_WC_Logger = new WC_Logger();
        // define( 'WC_BBFM_LOG_SRC', 'BB_for_Woo' );


        /**
         * Obyte Payment Gateway
         */

        class WC_Gateway_BBFM extends WC_Payment_Gateway{

            public function __construct(){

                if( get_option( 'wc_bbfm_partner' ) ){
                    $description = __("Pay with bytes and earn a <strong>" . (20 + get_option( 'wc_bbfm_partner_cashback_percent' ) * 2) . "% cashback</strong>!", 'bbfm-woocommerce');
                }
                else{
                    $description = __("Pay with bytes.");
                }
                if( get_option( 'wc_bbfm_display_powered_by' ) ){
                    $description .= '<br>' . __("Powered by ", 'bbfm-woocommerce'). "<a href='https://obyte-for-merchants.com/' target='_blank'>obyte-for-merchants.com</a>";
                }

                $this->id                = 'bbfm';
                $this->icon              = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/byteball.png';
                $this->has_fields        = false;
                $this->order_button_text = __('Pay with bytes', 'bbfm-woocommerce');
                $this->title             = 'Obyte bytes';
                $this->enable            = get_option( 'wc_bbfm_enable' );
                $this->description       = $description;

                add_action('woocommerce_receipt_' . $this->id, array(
                    $this,
                    'receipt_page'
                ));


            }


            function add_customer_byteball_address_field( $fields ) {
                 $fields['billing']['byteball_address'] = array(
                    'label'     => __('Obyte address', 'bbfm-woocommerce'),
                    'placeholder'   => _x('enter your cashback Obyte address', 'placeholder', 'woocommerce'),
                    'required'  => true,
                    'class'     => array('form-row-wide'),
                    'clear'     => true,
                 );

                 return $fields;
            }


            function display_admin_order_byteball_address( $order ){
                echo '<p><strong>'.__('Cashback Obyte address').':</strong> ' . get_post_meta( $order->get_id(), '_byteball_address', true ) . '</p>';
            }

            public function init_form_fields()
            {
                $this->form_fields = array(

                );
            }


            public function process_payment( $order_id ){
                global $woocommerce, $wc_BBFM_payment, $wc_BBFM_tools;

                $order = new WC_Order($order_id);
                $ask_payment_json = $wc_BBFM_payment->ask_payment( $order_id );
                $ask_payment = json_decode( $ask_payment_json, true );
                $BBaddress = $ask_payment[ 'BBaddress' ];
                $amount_BB_asked = $ask_payment[ 'amount_BB_asked' ];

                // ask_payment result = nok
                if( $ask_payment[ 'result' ] == 'nok' ){
                    wc_add_notice( 'Error returned when asking for Obyte payment address : ' . $ask_payment[ 'error_msg' ], 'error' );
                    return;
                }

                // ask_payment result = 'completed'
                if( $ask_payment[ 'result' ] == 'completed' ){
                    // check received_amount (!)
                    if( $amount_BB_asked != get_post_meta( $order_id, '_wc_bbfm_amount_BB_asked', true ) ){
                        $error_msg = 'Received amount does not match asked amount';
                        $order->update_status('failed', __($error_msg, 'bbfm-woocommerce'));
                        wc_add_notice( $error_msg, 'error' );
                    }
                    else{
                        $order->update_status('completed');
                    }
                    return;
                }

                // ask_payment result = 'processing'
                if( $ask_payment[ 'result' ] == 'processing' ){
                    $order->update_status('processing');
                    wc_add_notice( 'The payment of this order is already processing...', 'error' );
                    return;
                }

                // ask_payment result unknown
                if( $ask_payment[ 'result' ] != 'ok' ){
                    wc_add_notice( 'Unknown result value when asking for Obyte payment address : ' . $ask_payment[ 'result' ], 'error' );
                    return;
                }

                // check $BBaddress
                if (! $BBaddress){
                    $error_msg = "Could not generate new payment Obyte address. Note to webmaster: Contact us on https://obyte.org/discord";
                    wc_add_notice($error_msg, 'error');
                    return;
                }
                else if( ! $wc_BBFM_tools->check_BB_address( $BBaddress ) ){
                    $error_msg = "Received invalid payment Obyte address. Note to webmaster: Contact us on https://obyte.org/discord";
                    wc_add_notice($error_msg, 'error');
                    return;
                }

                // check $amount_BB_asked
                if( ! preg_match( "@^[0-9]{1,12}$@", $amount_BB_asked ) ){
                    $error_msg = "Received invalid byte amount. Note to webmaster: Contact us on https://obyte.org/discord";
                    wc_add_notice($error_msg, 'error');
                    return;
                }

                // register order payment infos
                update_post_meta( $order_id, '_wc_bbfm_BBaddress', $BBaddress );
                update_post_meta( $order_id, '_wc_bbfm_amount_BB_asked', $amount_BB_asked );

                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url( $order ),

                );

            }

        }

    }

    add_action('plugins_loaded', 'bbfm_woocommerce_init' );

}else{

    add_action( 'admin_notices', 'wc_bbfm_admin_notice_woocommerce_needed' );

}


function wc_bbfm_admin_notice_woocommerce_needed(){

    ?>
    <div class="notice notice-error is-dismissible">
        <p><strong><?php _e( 'WooBytes Gateway requires WooCommerce to be activated', 'bbfm-woocommerce' ); ?></strong></p>
    </div>
    <?php

}

