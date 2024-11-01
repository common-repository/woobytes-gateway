<?php

class wc_BBFM_tools
{
    function __construct() {

    }

    function check_BB_address( $address ){

        if( preg_match( "@^[0-9A-Z]{32}$@", $address ) or $address == 'NO-SENDING-ADDRESS-ON-TEST-MODE' ){
            return true;
        }else{
            return false;
        }

    }


    function log( $level, $msg ){

        if( get_option('wc_bbfm_log_enable', true) ){

            global $BBFM_WC_Logger;

            $BBFM_WC_Logger->log( $level, $msg, array( 'source' => 'Woobytes' ));

        }

    }


    function byte_format( $val ){

        if( $val < pow( 10, 3 ) ){

            $result = $val . ' Bytes';

        }else if( $val < pow( 10, 4 ) ){

            $result = number_format_i18n( $val / pow( 10, 3 ), 2) . ' KBytes';

        }else if( $val < pow( 10, 5 ) ){

            $result = number_format_i18n( $val / pow( 10, 3 ), 1) . ' KBytes';

        }else if( $val < pow( 10, 6 ) ){

            $result = number_format_i18n( $val / pow( 10, 3 ), 0) . ' KBytes';

        }else if( $val < pow( 10, 7 ) ){

            $result = number_format_i18n( $val / pow( 10, 6 ), 2) . ' MBytes';

        }else if( $val < pow( 10, 8 ) ){

            $result = number_format_i18n( $val / pow( 10, 6 ), 1) . ' MBytes';

        }else if( $val < pow( 10, 9 ) ){

            $result = number_format_i18n( $val / pow( 10, 6 ), 0) . ' MBytes';

        }else if( $val < pow( 10, 10 ) ){

            $result = number_format_i18n( $val / pow( 10, 9 ), 2) . ' GBytes';

        }else if( $val < pow( 10, 11 ) ){

            $result = number_format_i18n( $val / pow( 10, 9 ), 1) . ' GBytes';

        }else{

            $result = number_format_i18n( $val / pow( 10, 9 ), 0) . ' GBytes';

        }

        return $result;

    }

}

