<?php
/**
 * Plugin Name: Easy Digital Downloads - Blockonomics
 * Description: Accept Bitcoin Payments on your EasyDigitalDownloads-powered website with Blockonomics
 * Version: 1.5.1
 * Author: Blockonomics
 * Author URI: https://www.blockonomics.co
 * License: MIT
 * Text Domain: edd-blockonomics
 * Domain Path: /languages/
 */

/*  Copyright 2017 Blockonomics Inc.

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_Blockonomics 
{
  public function __construct() 
  {
    if( ! function_exists( 'edd_get_option' ) ) 
    {
      return;
    }

    if( class_exists( 'EDD_License' ) && is_admin() ) 
    {
      $license = new EDD_License( __FILE__, 'Blockonomics Payment Gateway', '1.0.0', 'Blockonomics' );
    }

    $this->includes();
    $this->generate_secret_and_callback();

    add_action( 'init',                         array( $this, 'textdomain' ) );
    add_action( 'edd_gateway_blockonomics',         array( $this, 'process_payment' ) );
    add_action( 'init',                         array( $this, 'listener' ) );
    //	add_action( 'template_redirect',            array( $this, 'process_failed_payment' ) );
    //	add_action( 'parse_query',                  array( $this, 'strip_order_arg' ) );
    add_action( 'edd_blockonomics_cc_form',         '__return_false' );

    add_filter( 'edd_payment_gateways',         array( $this, 'register_gateway' ) );
    add_filter( 'edd_currencies',               array( $this, 'currencies' ) );
    add_filter( 'edd_sanitize_amount_decimals', array( $this, 'btc_decimals' ) );
    add_filter( 'edd_format_amount_decimals',   array( $this, 'btc_decimals' ) );
    add_filter( 'edd_settings_gateways',        array( $this, 'settings' ) );
    add_filter( 'edd_settings_sections_gateways', array( $this, 'register_gateway_section') );
    add_filter( 'edd_accepted_payment_icons',  array($this, 'pw_edd_payment_icon'));
  }

  public function includes() 
  {
    if( ! class_exists( 'Blockonomics' ) ) 
    {
      require_once( plugin_dir_path( __FILE__ ) . 'php/Blockonomics.php' );
    }
  }

  public function textdomain() 
  {
    // Set filter for plugin's languages directory
    $lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
    $lang_dir = apply_filters( 'edd_blockonomics_languages_directory', $lang_dir );

    // Traditional WordPress plugin locale filter
    $locale        = apply_filters( 'plugin_locale',  get_locale(), 'edd-blockonomics' );
    $mofile        = sprintf( '%1$s-%2$s.mo', 'edd-blockonomics', $locale );

    // Setup paths to current locale file
    $mofile_local  = $lang_dir . $mofile;
    $mofile_global = WP_LANG_DIR . '/edd-blockonomics/' . $mofile;

    if ( file_exists( $mofile_global ) ) 
    {
      load_textdomain( 'edd-blockonomics', $mofile_global );
    }
    elseif( file_exists( $mofile_local ) ) 
    {
      load_textdomain( 'edd-blockonomics', $mofile_local );
    }
    else
    {
      // Load the default language files
      load_plugin_textdomain( 'edd-blockonomics', false, $lang_dir );
    }
  }

  public function register_gateway( $gateways ) 
  {

    $gateways['blockonomics'] = array(
      'checkout_label'  => __( 'Bitcoin', 'edd-blockonomics' ),
      'admin_label'     => __( 'Blockonomics', 'edd-blockonomics' ),
      'supports'        => array( 'buy_now' )
    );

    return $gateways;
  }

  function pw_edd_payment_icon($icons) 
  {
    $icon_url = plugins_url('img/bitcoin.png', __FILE__);
    $icons[$icon_url] = 'Bitcoin';
    return $icons;
  }

  public function register_gateway_section( $gateway_sections ) 
  {
    $gateway_sections['blockonomics'] = __( 'Blockonomics', 'edd-blockonomics' );
    return $gateway_sections;
  }

  function generate_secret_and_callback($generate_new = false)
  {
    $callback_secret = edd_get_option('edd_blockonomics_callback_secret', '');
    if ( empty( $callback_secret) || $generate_new ) 
    {
      $callback_secret = sha1(openssl_random_pseudo_bytes(20));
      edd_update_option("edd_blockonomics_callback_secret", $callback_secret);
    }

    $callback_url = add_query_arg( array( 'edd-listener' => 'blockonomics', 'secret' => $callback_secret ), home_url() );
    edd_update_option('edd_blockonomics_callback_url', $callback_url);
  }

  public function process_payment( $purchase_data ) 
  {
    global $edd_options;

    $api_key = trim(edd_get_option('edd_blockonomics_api_key', ''));
    if( empty ($api_key) ) 
    {
      edd_set_error( 'edd_blockonomics_api_invalid', __( 'Please enter your Blockonomics API key and secret in Settings', 'edd-blockonomics' ) );
      edd_send_back_to_checkout( '?payment-mode=blockonomics' );
    }

    // Collect payment data
    $payment_data = array(
      'price'         => $purchase_data['price'],
      'date'          => $purchase_data['date'],
      'user_email'    => $purchase_data['user_email'],
      'purchase_key'  => $purchase_data['purchase_key'],
      'currency'      => edd_get_currency(),
      'downloads'     => $purchase_data['downloads'],
      'user_info'     => $purchase_data['user_info'],
      'cart_details'  => $purchase_data['cart_details'],
      'gateway'       => 'blockonomics',
      'status'        => 'pending'
    );

    // Record the pending payment
    $payment_id = edd_insert_payment( $payment_data );

    // Check payment
    if ( ! $payment_id ) 
    {
      // Record the error
      edd_record_gateway_error( __( 'Payment Error', 'edd-blockonomics' ), sprintf( __( 'Payment creation failed, Payment data: %s', 'edd-blockonomics' ), json_encode( $payment_data ) ), $payment_id );
      // Problems? send back
      edd_send_back_to_checkout( '?payment-mode=blockonomics' );

    }
    else
    {
      try 
      {
        $callback_secret = trim(edd_get_option('edd_blockonomics_callback_secret', ''));
        $blockonomics = new Blockonomics;
        $responseObj = $blockonomics->new_address($api_key, $callback_secret);
        $price = $blockonomics->get_price(edd_get_currency());

        if($responseObj->response_code != 'HTTP/1.1 200 OK') 
        {
          $this->displayError($woocommerce);
          return;
        }

        $address = $responseObj->address;

        $blockonomics_orders = edd_get_option('edd_blockonomics_orders');
        $order = array(
          'value'              => $purchase_data['price'],
          'satoshi'            => intval(1.0e8*$purchase_data['price']/$price),
          'currency'           => edd_get_currency(),
          'order_id'            => $payment_id,
          'status'             => -1,
          'timestamp'          => time(),
          'txid'               => ''
        );

        $blockonomics_orders[$address] = $order;
        edd_update_option('edd_blockonomics_orders', $blockonomics_orders);

        //Update post parameters to make them available in the listner method.
        update_post_meta($payment_id, 'blockonomics_address', $address);
        $invoice_url = add_query_arg( array( 'edd-listener' => 'blockonomics', 'show_order' => $address ), home_url() );
        wp_redirect($invoice_url); 
        exit;

      } 
      catch ( Blockonomics_Exception $e ) 
      {
        $error  = json_decode( $e->getResponse() );

        if ( isset( $error->errors ) && is_array( $error->errors ) ) 
        {
          foreach( $error->errors as $error ) 
          {
            edd_set_error( 'edd_blockonomics_exception', sprintf( __( 'Error: %s', 'edd-blockonomics' ), $error ) );
          }
        }
        elseif( isset( $error->error ) ) 
        {
          edd_set_error( 'edd_blockonomics_exception', $error->error );
        }

        edd_send_back_to_checkout( '?payment-mode=blockonomics' );
      }
    }
  }

  function update_callback_url($callback_url, $xPub, $blockonomics)
  {
    $blockonomics->update_callback(
      edd_get_option('edd_blockonomics_api_key'),
      $callback_url,
      $xPub
    );
  }

  /**
   * Check the status of callback urls
   * If no xPubs set, return
   * If one xPub is set without callback url, set the url
   * If more than one xPubs are set, give instructions on integrating to multiple sites
   * @return Strin Count of found xPubs
   */
  function check_callback_urls()
  {
    $blockonomics = new Blockonomics;
    $responseObj = $blockonomics->get_xpubs(edd_get_option('edd_blockonomics_api_key'));

    // No xPubs set
    if (count($responseObj) == 0)
    {
      return "0";
    }

    // One xPub set
    if (count($responseObj) == 1)
    {
      $callback_secret = trim(edd_get_option('edd_blockonomics_callback_secret'));
      $callback_url = add_query_arg( array( 'edd-listener' => 'blockonomics', 'secret' => $callback_secret ), home_url() );

      // No Callback URL set, set one
      if(!$responseObj[0]->callback || $responseObj[0]->callback == null)
      {
        update_callback_url($callback_url, $responseObj[0]->address, $blockonomics);
        return "1";
      }
      // One xPub with one Callback URL
      else
      {
        if($responseObj[0]->callback == $callback_url)
        {
          return "1";
        }

        // Check if only secret differs
        $callback_without_secret =  add_query_arg( 'edd-listener', 'blockonomics', home_url());
        if(strpos($responseObj[0]->callback, $callback_without_secret ) !== false)
        {
          update_callback_url($callback_url, $responseObj[0]->address, $blockonomics);
          return "1";
        }

        return "2";
      }
    }

    if (count($responseObj) > 1)
    {
      $callback_secret = edd_get_option('edd_blockonomics_callback_secret');
      $callback_url = add_query_arg('edd-listener', 'blockonomics', home_url());
      $callback_url = add_query_arg('secret', $callback_secret, $callback_url);

      // Check if callback url is set
      foreach ($responseObj as $resObj) {
        if($resObj->callback == $callback_url)
        {
          return "1";
        }
      }
      return "2";
    }
  }

  function testSetup()
  {
    $blockonomics = new Blockonomics;
    $responseObj = $blockonomics->new_address(edd_get_option('edd_blockonomics_api_key'), 
      edd_get_option("edd_blockonomics_callback_secret"), true);

    error_log(print_r($responseObj, true));

    if(!ini_get('allow_url_fopen')) 
    {
      $error_str = __('<i>allow_url_fopen</i> is not enabled, please enable this in php.ini', 'edd-blockonomics');

    }  
    elseif( !isset($responseObj->response_code)) 
    {
      $error_str = __('Your webhost is blocking outgoing HTTPS connections. Blockonomics requires an outgoing HTTPS POST (port 443) to generate new address. Check with your webhosting provider to allow this.', 'edd-blockonomics');
    } 
    else 
    {
      switch ($responseObj->response_code) 
      {
      case 'HTTP/1.1 200 OK':
        break;

      case 'HTTP/1.1 401 Unauthorized': 
      {
        $error_str = __('API Key is incorrect. Make sure that the API key set in admin Blockonomics module configuration is correct.', 'edd-blockonomics');
        break;
      }

      case 'HTTP/1.1 500 Internal Server Error': 
      {
        if(isset($responseObj->message)) 
        {
          $error_code = $responseObj->message;

          switch ($error_code) {
          case "Could not find matching xpub":
            $error_str = __('There is a problem in the Callback URL. Make sure that you have set your Callback URL from the admin Blockonomics module configuration to your Merchants > Settings.', 'edd-blockonomics');
            break;
          case "This require you to add an xpub in your wallet watcher":
            $error_str = __('There is a problem in the XPUB. Make sure that the you have added an address to Wallet Watcher > Address Watcher. If you have added an address make sure that it is an XPUB address and not a Bitcoin address.', 'edd-blockonomics');
            break;
          default:
            $error_str = $responseObj->message;
          }
          break;
        } 
        else 
        {
          $error_str = $responseObj->response_code;
          break;
        }
      }

      default:
      $error_str = $responseObj->response_code;
      break;
      }
    }

    if(isset($error_str)) 
    {
      return $error_str;
    }

    // No errors
    return false;
  }

  public function listener() 
  {
    if( empty( $_GET['edd-listener'] ) ) 
    {
      return;
    }

    if( 'blockonomics' != $_GET['edd-listener'] ) 
    {
      return;
    }

    $action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : "";
    if( !empty($action) )
    {
      if($action == "update_callback")
      {
        $this->generate_secret_and_callback(true);
        exit;
      }
      else if ($action == "test_setup")
      {
        $urls_count = $this->check_callback_urls();

        if($urls_count == '2')
        {
          $message = __("Seems that you have set multiple xPubs or you already have a Callback URL set. <a href='https://blockonomics.freshdesk.com/support/solutions/articles/33000209399-merchants-integrating-multiple-websites' target='_blank'>Here is a guide</a> to setup multiple websites.", 'edd-blockonomics');
          exit($message);
        }

        $setup_errors = $this->testSetup();

        if($setup_errors)
        {
          $message = __($setup_errors . '</p><p>For more information, please consult <a href="https://blockonomics.freshdesk.com/support/solutions/articles/33000215104-troubleshooting-unable-to-generate-new-address" target="_blank">this troubleshooting article</a></p>', 'edd-blockonomics');
          exit($message);
        }
        else
        {
          $message = __('Congrats ! Setup is all done', 'edd-blockonomics');
          exit($message);
        }
      }
    }

    $orders = edd_get_option('edd_blockonomics_orders');
    $address = isset($_REQUEST["show_order"]) ? $_REQUEST["show_order"] : "";
    if ($address) 
    {
      include plugin_dir_path(__FILE__)."order.php";
      exit();
    }

    $address = isset($_REQUEST["finish_order"]) ? $_REQUEST["finish_order"] : "";
    if ($address) 
    {
      error_log('finish order');
      $order = $orders[$address];
      echo $order['order_id'];
      error_log(print_r($order, true));
      error_log(edd_get_success_page_uri());
      wp_redirect(edd_get_success_page_uri());
      exit();
    }

    $address = isset($_REQUEST['get_order']) ? $_REQUEST['get_order'] : "";
    error_log('Inside listener() method.');
    error_log(print_r($address, true));

    if ($address) 
    {
      error_log('Get Order.');
      exit(json_encode($orders[$address]));
    }

    try 
    {
      $callback_secret = edd_get_option("edd_blockonomics_callback_secret");
      $secret = isset($_REQUEST['secret']) ? $_REQUEST['secret'] : "";
      error_log('Inside status check.');
      error_log(print_r($secret, true));
      error_log(print_r($callback_secret, true));

      if ($callback_secret  && $callback_secret == $secret) 
      {
        $addr = $_REQUEST['addr'];
        $order = $orders[$addr];
        $order_id = $order['order_id'];

        error_log(print_r($order_id, true));
        error_log(print_r($order, true));

        if ($order_id) 
        {
          $status = intval($_REQUEST['status']);
          $existing_status = $order['status'];
          $timestamp = $order['timestamp'];
          $time_period = edd_get_option("edd_blockonomics_timeperiod", 10) *60;

          if ($status == 0 && time() > $timestamp + $time_period) 
          {
            $minutes = (time() - $timestamp)/60;
            edd_record_gateway_error(__("Warning: Payment arrived after $minutes minutes. Received BTC may not match current bitcoin price", 'edd-blockonomics'));
          }
          elseif ($status == 2) 
          {
            update_post_meta($order_id, 'paid_btc_amount', $_REQUEST['value']/1.0e8);
            if ($order['satoshi'] > $_REQUEST['value']) 
            {
              $status = -2; //Payment error , amount not matching
              edd_record_gateway_error(__('Paid BTC amount less than expected.','edd-blockonomics'));
              edd_update_payment_status($order_id, 'failed');
            }
            else
            {
              if ($order['satoshi'] < $_REQUEST['value']) 
              {
                edd_insert_payment_note(__('Overpayment of BTC amount', 'edd-blockonomics'));
              }

              edd_insert_payment_note(__('Payment completed', 'edd-blockonomics'));
              edd_set_payment_transaction_id( $payment_id, $order['txid']);
              edd_update_payment_status($order_id, 'publish' );
            }
          }

          $order['txid'] =  $_REQUEST['txid'];
          $order['status'] = $status;
          $orders[$addr] = $order;
          error_log('tx id.');
          error_log(print_r($order['txid'] , true));

          if ($existing_status == -1) 
          {
            update_post_meta($order_id, 'blockonomics_txid', $order['txid']);
            update_post_meta($order_id, 'expected_btc_amount', $order['satoshi']/1.0e8);
          }

          edd_update_option('blockonomics_orders', $orders);
        }
      }
    } 
    catch ( Blockonomics_Exception $e ) 
    {
      $error = json_decode( $e->getResponse() );

      if( isset( $error->errors ) ) 
      {
        foreach( $error->errors as $error ) 
        {
          edd_record_gateway_error( __( 'Blockonomics Error', 'edd-blockonomics' ), 'Message: ' . $error );
        }
      } elseif( isset( $error->error ) ) 
      {
        edd_record_gateway_error( __( 'Blockonomics Error', 'edd-blockonomics' ), 'Message: ' . $error->error );
      }

      die('blockonomics exception error');
    }
  }

  public function currencies( $currencies ) 
  {
    $currencies['BTC'] = __( 'Bitcoin', 'edd-blockonomics' );
    return $currencies;
  }

  function btc_decimals( $decimals = 2 ) 
  {
    global $edd_options;
    $currency = edd_get_currency();

    switch ( $currency ) 
    {
    case 'BTC' :
      $decimals = 8;
      break;
    }

    return $decimals;
  }


  public function settings( $settings ) 
  {
    $listener_url = add_query_arg(array( 'edd-listener' => 'blockonomics', 'action' => 'update_callback') ,home_url());
    $callback_refresh = __( 'CALLBACK URL', 'edd-blockonomics' ).'<a href="javascript:update_callback()" 
      id="generate-callback" style="font:400 20px/1 dashicons;margin-left: 7px;position:relative;text-decoration: none;" title="Generate New Callback URL">&#xf463;<a>
    <script type="text/javascript">
function update_callback()
{
  $.post( "'.$listener_url.'", function( data ) { location.reload(); });
}
  </script>
';
    $blockonomics_settings = array(
      array(
        'id'      => 'edd_blockonomics_api_key',
        'name'    => __( 'BLOCKONOMICS API KEY', 'edd-blockonomics' ),
        'type'    => 'text'
      ),
      array(
        'id'      => 'edd_blockonomics_callback_url',
        'name'    => $callback_refresh,
        'readonly' => true,
        'type'    => 'text'
      ),
      array(
        'id'      => 'edd_blockonomics_accept_altcoins',
        'name'    => __('Accept Altcoin Payments (Using Shapeshift)', 'edd-blockonomics'),
        'type'    => 'checkbox'
      ),
      array(
        'id'      => 'edd_blockonomics_payment_countdown_time',
        'name'    => __('Time period of countdown timer on payment page (in minutes)', 'edd-blockonomics'),
        'type'    => 'select',
        'options' => array(
          '10' => '10',
          '15' => '15',
          '20' => '20',
          '25' => '25',
          '30' => '30'
        )
      ),
      array(
        'id'      => 'edd_blockonomics_testsetup',
        'name'    => '',
        'type'    => 'testsetup',
      )
    );

    $blockonomics_settings = apply_filters('edd_blockonomics_settings', $blockonomics_settings);
    $settings['blockonomics'] = $blockonomics_settings;
    return $settings;
  }
}

function edd_testsetup_callback() 
{
  $listener_url = add_query_arg(array( 'edd-listener' => 'blockonomics', 'action' => 'test_setup') ,home_url());

  printf('<p>
    <input type="button" onclick="test_setup()" class="button-primary" name="test-setup-submit" value="Test Setup" style="max-width:90px;">
    </p>
    <div id="dialog" title="Testing setup.">
      <p id="dialog-text"></p>
    </div>
<script type="text/javascript">

$(function() {
  $("#dialog").dialog({
    autoOpen : false
  });
});

function test_setup()
{
  $.post( "'.$listener_url.'", function( data ) 
  { 
    $("#dialog-text").html(data); 
    $("#dialog").dialog("open");
  });
}
</script>
');
}


function edd_blockonomics_init() 
{
  $edd_blockonomics = new EDD_Blockonomics;
}
add_action( 'plugins_loaded', 'edd_blockonomics_init' );
