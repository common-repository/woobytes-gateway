<?php

class wc_BBFM_settings
{

    var $admin_page_slug = 'wc_bbfm_menu';

    var $defaults = array(

        'cashback_address_msg' => 'Enter here your <strong>byteball address</strong> (or just your <strong>email address</strong> if you do not have byteball address yet) if you want to enjoy your byteball cashback!',

        'before_paybutton_msg' => '',

        'after_paybutton_msg' => '<p>After you made the payment, it will take about 5-15 minutes for it to be confirmed by the network.<br>Our server will process it, you do not need to keep this page open.</p>',

        'new_order_customer_notif' => 1,

    );



    function __construct()
    {

        // global $BBFM_WC_Logger;// our WC_Logger instance

        // add *our* admin settings page
        add_action('admin_menu', array( $this, 'add_settings_menu' ), 10 );// priority 10 to be triggered before add_report_menu
        add_action('admin_init', array( $this, 'settings_api_init' ) );

        // add settings link to admin plugins page
        add_filter( 'plugin_action_links_' . WC_BBFM_BASENAME, array( $this, 'plugin_settings_link' ) );

        // unset standard woocommerce settings page
        add_filter( 'woocommerce_get_sections_checkout', array( $this, 'wcslider_all_settings' ) );

        // admin notice to go on plugin settings page
        add_action( 'admin_notices', array( $this, 'go_to_settings_admin_notice' ) );


        // add byteball bytes as possible main currency
        add_filter( 'woocommerce_currencies', array( $this, 'add_bb_currency' ) );
        add_filter('woocommerce_currency_symbol', array( $this, 'add_bb_currency_symbol' ), 10, 2);


    }


    function add_bb_currency( $bb_currency ) {

        $bb_currency['B'] = __( 'Byteball Bytes', 'woocommerce' );
        $bb_currency['MB'] = __( 'Byteball MBytes', 'woocommerce' );
        $bb_currency['GB'] = __( 'Byteball GBytes', 'woocommerce' );

        return $bb_currency;

    }




    function add_bb_currency_symbol( $custom_currency_symbol, $custom_currency ) {

        switch( $custom_currency ) {
        case 'B': $custom_currency_symbol = 'Bytes';
            break;
        case 'MB': $custom_currency_symbol = 'MBytes';
            break;
        case 'GB': $custom_currency_symbol = 'GBytes';
            break;
        }

        return $custom_currency_symbol;
    }


    function wcslider_all_settings( $settings ) {

        unset( $settings[ 'bbfm' ] );

        return $settings;

    }


    function go_to_settings_admin_notice() {

        $screen = get_current_screen();

        // global $wc_BBFM_tools;
        // $wc_BBFM_tools->log( 'debug', "screen : " . wc_print_r( $screen, true) );

        // notice only if never seen settings page or now reading settings page
        if( ! get_option( "wc_bbfm_seen_settings_page" ) and $screen->id != 'toplevel_page_' . $this->admin_page_slug){

            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e("Thank you for choosing WooBytes as payment gateway.<br>The plugin is almost ready!<br>Please visit the plugin <a href=\"admin.php?page=" . $this->admin_page_slug . "\">settings page</a> to setup the plugin.", 'bbfm-woocommerce') ?></p>
            </div>
            <?php

        }
    }


    function plugin_settings_link( $links ) {

        $settings_link = '<a href="admin.php?page=' . $this->admin_page_slug . '">' . __( 'Settings' ) . '</a>';
        array_push( $links, $settings_link );

        global $wc_BBFM_report;
        $report_link = '<a href="admin.php?page=' . $wc_BBFM_report->admin_page_slug . '">' . __( 'Report' ) . '</a>';
        array_push( $links, $report_link );

        return $links;

    }


    function add_settings_menu(){

        add_menu_page('WooBytes', 'WooBytes', 'manage_options', $this->admin_page_slug );
        add_submenu_page( $this->admin_page_slug, 'Settings', 'Settings', 'manage_options', $this->admin_page_slug, array( $this, 'render_settings_page' ) );

    }


    function render_settings_page(){

        update_option("wc_bbfm_seen_settings_page", true);

        ?>
        <div class="wrap">
            <h1>Byte payments options</h1>
            <?php settings_errors(); ?>
            <form method="POST" action="options.php">
            <?php

            settings_fields( 'byteball_options' );	//pass slug name of page, also referred
                                                    //to in Settings API as option group name
            do_settings_sections( 'byteball_options' ); 	//pass slug name of page

            submit_button();

            ?>
            </form>
        </div>
        <?php

    }


    function settings_api_init() {

        // add_settings_section

        add_settings_section(
            'general_section',
            'General options',
            '',
            'byteball_options'
        );

        add_settings_section(
            'cashback_prg_section',
            'Byteball Cashback Program',
            array( $this, 'cashback_section_callback'),
            'byteball_options'
        );

        add_settings_section(
            'cashback_api_section',
            'Byteball Cashback API advanced settings',
            array( $this, 'cashback_api_callback'),
            'byteball_options'
        );

        add_settings_section(
            'logs_section',
            'Logs',
            array( $this, 'logs_section_callback'),
            'byteball_options'
        );


        // add_settings_field

        // general_section

        add_settings_field(
            'wc_bbfm_enable',
            'Enable byte payments',
            array( $this, 'wc_bbfm_enable_callback' ),
            'byteball_options',
            'general_section'
        );

        add_settings_field(
            'wc_bbfm_merchant_email',
            'Email notification address',
            array( $this, 'wc_bbfm_merchant_email_callback' ),
            'byteball_options',
            'general_section'
        );

        add_settings_field(
            'wc_bbfm_byteball_address',
            'Byteball address',
            array( $this, 'wc_bbfm_byteball_address_callback' ),
            'byteball_options',
            'general_section'
        );

        add_settings_field(
            'wc_bbfm_display_powered_by',
            "'Power by' label (optional)",
            array( $this, 'wc_bbfm_display_powered_by_callback' ),
            'byteball_options',
            'general_section'
        );


        add_settings_field(
            'wc_bbfm_new_order_customer_notif',
            "Send new order details email to customer",
            array( $this, 'wc_bbfm_new_order_customer_notif_callback' ),
            'byteball_options',
            'general_section'
        );

        add_settings_field(
            'wc_bbfm_before_paybutton_msg',
            "Message before pay button",
            array( $this, 'wc_bbfm_before_paybutton_msg_callback' ),
            'byteball_options',
            'general_section'
        );

        add_settings_field(
            'wc_bbfm_after_paybutton_msg',
            "Message after pay button",
            array( $this, 'wc_bbfm_after_paybutton_msg_callback' ),
            'byteball_options',
            'general_section'
        );


        // cashback_prg_section

        add_settings_field(
            'wc_bbfm_partner',
            'Partner name',
            array( $this, 'wc_bbfm_partner_callback' ),
            'byteball_options',
            'cashback_prg_section'
        );

        add_settings_field(
            'wc_bbfm_partner_key',
            'Partner key',
            array( $this, 'wc_bbfm_partner_key_callback' ),
            'byteball_options',
            'cashback_prg_section'
        );

        add_settings_field(
            'wc_bbfm_partner_cashback_percent',
            'Partner percent',
            array( $this, 'wc_bbfm_partner_cashback_percent_callback' ),
            'byteball_options',
            'cashback_prg_section'
        );

        add_settings_field(
            'wc_bbfm_cashback_addr_msg',
            'Cashback address message',
            array( $this, 'wc_bbfm_cashback_addr_msg_callback' ),
            'byteball_options',
            'cashback_prg_section'
        );

        add_settings_field(
            'wc_bbfm_partner_SSL_CIPHER_LIST',
            'SSL cipher list',
            array( $this, 'wc_bbfm_partner_SSL_CIPHER_LIST_callback' ),
            'byteball_options',
            'cashback_api_section'
        );

        add_settings_field(
            'wc_bbfm_allowed_notif_IPs',
            'Allowed notification IPs',
            array( $this, 'wc_bbfm_allowed_notif_IPs_callback' ),
            'byteball_options',
            'cashback_api_section'
        );

        add_settings_field(
            'wc_bbfm_log_enable',
            'Enable logs',
            array( $this, 'wc_bbfm_log_enable_callback' ),
            'byteball_options',
            'logs_section'
        );

        // add_settings_field(
        //     'wc_bbfm_log_level',
        //     'Logs level',
        //     array( $this, 'wc_bbfm_log_level_callback' ),
        //     'byteball_options',
        //     'logs_section'
        // );


        // register_setting

        register_setting( 'byteball_options', 'wc_bbfm_enable' );
        register_setting( 'byteball_options', 'wc_bbfm_merchant_email', array( $this, 'wc_bbfm_merchant_email_validate') );
        register_setting( 'byteball_options', 'wc_bbfm_byteball_address', array( $this, 'wc_bbfm_byteball_address_validate' ) );
        register_setting( 'byteball_options', 'wc_bbfm_display_powered_by' );
        register_setting( 'byteball_options', 'wc_bbfm_new_order_customer_notif' );
        register_setting( 'byteball_options', 'wc_bbfm_before_paybutton_msg' );
        register_setting( 'byteball_options', 'wc_bbfm_after_paybutton_msg' );


        register_setting( 'byteball_options', 'wc_bbfm_partner', array( $this, 'wc_bbfm_partner_validate' ) );
        register_setting( 'byteball_options', 'wc_bbfm_partner_key', array( $this, 'wc_bbfm_partner_key_validate' ) );
        register_setting( 'byteball_options', 'wc_bbfm_partner_cashback_percent', array( $this, 'wc_bbfm_partner_cashback_percent_validate' ) );
        register_setting( 'byteball_options', 'wc_bbfm_cashback_addr_msg' );

        register_setting( 'byteball_options', 'wc_bbfm_partner_SSL_CIPHER_LIST' );
        register_setting( 'byteball_options', 'wc_bbfm_allowed_notif_IPs', array( $this, 'wc_bbfm_newlines_to_array' ) );

        register_setting( 'byteball_options', 'wc_bbfm_log_enable' );

        // register_setting( 'byteball_options', 'wc_bbfm_log_level' );

    }


    /*
     * callbacks
     */

    function cashback_section_callback(){

        echo "<p>Let your customers benefit from a <strong>minimum 10% cashback</strong> for every order completed in your website!</p>";
        echo "<p>The cashback will be automatically sent in bytes to your customer as soon as his order is completed.</p>";
        echo "<p>Contact <a href='mailto:byteball@byteball.org'>the byteball team</a> if you want to be part of the <a href='https://medium.com/byteball/byteball-cashback-program-9c717b8d3173' target='_blank'>cashback program</a> and get a partner name and partner key.</p>";

    }

    function cashback_api_callback(){

        echo "<p>These parameters should not be modified unless for specific config reasons.</p>";

    }

    function logs_section_callback(){

        echo "<p>For debugging purpose.</p>";
        echo "<p>You can enable here the logs of the plugins actions and select a log level.</p>";
        echo "<p>You will see these logs on the <a href='" . admin_url() . "admin.php?page=wc-status&tab=logs' target='_blank'>Woocommerce status logs page</a>.</p>";
    }

    function wc_bbfm_enable_callback() {
        echo '<input name="wc_bbfm_enable" id="wc_bbfm_enable" type="checkbox" value="1"' . checked( get_option( 'wc_bbfm_enable' ) , 1, false ) .'/>';
    }

    function wc_bbfm_display_powered_by_callback() {
        echo '<input name="wc_bbfm_display_powered_by" id="wc_bbfm_display_powered_by" type="checkbox" value="1"' . checked( get_option( 'wc_bbfm_display_powered_by' ) , 1, false ) .'/><br>This will display \'Powered by obyte-for-merchants.com\' mention on checkout page and on the bytes payment button.';
    }

    function wc_bbfm_new_order_customer_notif_callback() {
        echo '<input name="wc_bbfm_new_order_customer_notif" id="wc_bbfm_new_order_customer_notif" type="checkbox" value="1"' . checked( get_option( 'wc_bbfm_new_order_customer_notif' , $this->defaults[ 'new_order_customer_notif' ] ) , 1, false ) .'/><br>A notification email with order details and <strong>* payment link *</strong> will automatically be sent to customer when a new order is created.';
    }

    function wc_bbfm_merchant_email_callback() {
        echo '<input size="40" name="wc_bbfm_merchant_email" id="wc_bbfm_merchant_email" type="text" value="' . get_option( 'wc_bbfm_merchant_email' ) . '" /><br>Enter the email address on which you want to be notified of your byte payments.';
    }


    function wc_bbfm_byteball_address_callback() {
        echo '<input size="40" name="wc_bbfm_byteball_address" id="wc_bbfm_byteball_address" type="text" value="' . get_option( 'wc_bbfm_byteball_address' ) . '" /><br>Your unique byteball merchant address to receive your payments.';
    }


    function wc_bbfm_partner_callback() {
        echo '<input size="40" name="wc_bbfm_partner" id="wc_bbfm_partner" type="text" value="' . get_option( 'wc_bbfm_partner' ) . '" />';
    }


    function wc_bbfm_partner_key_callback() {
        echo '<input size="40" name="wc_bbfm_partner_key" id="wc_bbfm_partner_key" type="text" value="' . get_option( 'wc_bbfm_partner_key' ) . '" />';
    }

    function wc_bbfm_cashback_addr_msg_callback() {
        echo '<textarea name="wc_bbfm_cashback_addr_msg" rows="3" wrap="soft" id="wc_bbfm_cashback_addr_msg" style="width: 95%"/>' . get_option( 'wc_bbfm_cashback_addr_msg', $this->defaults[ 'cashback_address_msg' ] ) . '</textarea>
            <br/>HTML allowed - This message is displayed below the *cashback address* field.';
    }

    function wc_bbfm_before_paybutton_msg_callback() {
        echo '<textarea name="wc_bbfm_before_paybutton_msg" rows="3" wrap="soft" id="wc_bbfm_before_paybutton_msg" style="width: 95%"/>' . get_option( 'wc_bbfm_before_paybutton_msg', $this->defaults[ 'before_paybutton_msg' ] ) . '</textarea>
            <br/>HTML allowed - This message is displayed above the QR code pay button.';
    }

    function wc_bbfm_after_paybutton_msg_callback() {
        echo '<textarea name="wc_bbfm_after_paybutton_msg" rows="3" wrap="soft" id="wc_bbfm_after_paybutton_msg" style="width: 95%"/>' . get_option( 'wc_bbfm_after_paybutton_msg', $this->defaults[ 'after_paybutton_msg' ] ) . '</textarea>
            <br/>HTML allowed - This message is displayed below the QR code pay button.';
    }

    function wc_bbfm_partner_SSL_CIPHER_LIST_callback() {
        echo '<input size="40" name="wc_bbfm_partner_SSL_CIPHER_LIST" id="wc_bbfm_partner_SSL_CIPHER_LIST" type="text" value="' . get_option( 'wc_bbfm_partner_SSL_CIPHER_LIST', 'ecdhe_ecdsa_aes_256_sha' ) . '" />';
    }

    function wc_bbfm_allowed_notif_IPs_callback() {
        echo '<textarea id="wc_bbfm_allowed_notif_IPs" name="wc_bbfm_allowed_notif_IPs" rows="10" cols="25">' . implode(get_option('wc_bbfm_allowed_notif_IPs'), "\n") . '</textarea>';
    }

    function wc_bbfm_partner_cashback_percent_callback() {
        echo '<input size="1" maxlength="2" name="wc_bbfm_partner_cashback_percent" id="wc_bbfm_partner_cashback_percent" type="text" value="' . get_option( 'wc_bbfm_partner_cashback_percent' ) . '" />%<br>The percentage of the amount you want to pay to the customer out of your own funds in addition to the regular cashback. Byteball will add the same percentage out of the distribution fund (merchant match). Default it 0. You have to deposit the funds in advance in order to fund this option.';
    }

    function wc_bbfm_log_enable_callback() {
        echo '<input name="wc_bbfm_log_enable" id="wc_bbfm_log_enable" type="checkbox" value="1"' . checked( get_option( 'wc_bbfm_log_enable' ) , 1, false ) .'/>';
    }

    function wc_bbfm_log_level_callback() {
        ?>
        <select name="wc_bbfm_log_level">
            <option value="emergency" <?php selected(get_option('wc_bbfm_log_level'), "emergency"); ?>>emergency (system is unusable)</option>
            <option value="alert" <?php selected(get_option('wc_bbfm_log_level'), "alert"); ?>>alert (action must be taken immediately)</option>
            <option value="critical" <?php selected(get_option('wc_bbfm_log_level'), "critical"); ?>>critical (critical conditions)</option>
            <option value="error" <?php selected(get_option('wc_bbfm_log_level'), "error"); ?>>error (error conditions)</option>
            <option value="warning" <?php selected(get_option('wc_bbfm_log_level'), "warning"); ?>>warning (warning conditions)</option>
            <option value="notice" <?php selected(get_option('wc_bbfm_log_level'), "notice"); ?>>notice (normal but significant condition)</option>
            <option value="debug" <?php selected(get_option('wc_bbfm_log_level'), "debug"); ?>>debug (debug-level messages)</option>
        </select>
    <?php
    }


    /*
     * validations
     */

    function wc_bbfm_merchant_email_validate( $email ) {

        $output = get_option( 'wc_bbfm_merchant_email' );

        if ( is_email( $email ) or strlen( $email ) == 0 )
        // if ( filter_var( $email, FILTER_VALIDATE_EMAIL) or strlen( $email ) == 0 )
            $output = $email;
        else
            add_settings_error( 'byteball_options', 'wc_bbfm_merchant_email', 'You have entered an invalid e-mail address.' );

        return $output;

    }


    function wc_bbfm_byteball_address_validate( $address ) {

        global $wc_BBFM_tools;

        $output = get_option( 'wc_bbfm_byteball_address' );

        if ( $wc_BBFM_tools->check_BB_address( $address ) ){
            $output = $address;
        }
        else if( strlen( $address) == 0 ){
            if( get_option( 'wc_bbfm_enable' ) ){
                add_settings_error( 'byteball_options', 'wc_bbfm_byteball_address', 'You must enter your payment address if you enable byte payments.' );
            }
            else{
                $output = $address;
            }
        }
        else{
            add_settings_error( 'byteball_options', 'wc_bbfm_byteball_address', 'You have entered an invalid byteball address.' );
        }

        return $output;

    }


    function wc_bbfm_partner_validate( $partner ) {

        $output = get_option( 'wc_bbfm_partner' );

        if ( strlen( $partner ) == 0  )
            add_settings_error( 'byteball_options', 'wc_bbfm_merchant_email', "Just note you won't benefit from cashback program if you don't enter your partner info...", 'updated' );


        return $partner;

    }

    function wc_bbfm_partner_key_validate( $key ) {

        $output = get_option( 'wc_bbfm_partner_key' );

        if ( strlen( $key ) == 0 and get_option( 'wc_bbfm_partner' ) )
            add_settings_error( 'byteball_options', 'wc_bbfm_merchant_email', 'You must enter your partner key if you have a partner name.' );
        else
            $output = $key;

        return $output;

    }

    function wc_bbfm_newlines_to_array($text) {
        return explode("\n", trim($text));
    }

    function wc_bbfm_partner_cashback_percent_validate( $percent ) {

        $max_percent = 40;

        $output = get_option( 'wc_bbfm_partner_cashback_percent' );

        if ( preg_match('/^\d*$/', $percent) ){
            if( $percent > $max_percent ){
                add_settings_error( 'byteball_options', 'wc_bbfm_partner_cashback_percent', "The cashback partner percent cannot be greater than $max_percent%." );
            }
            else{
                $output = $percent;
            }
        }
        else{
            add_settings_error( 'byteball_options', 'wc_bbfm_partner_cashback_percent', 'The cashback partner percent must be an interger.' );
        }

        return $output;

    }

}