<?php

class BlockonomicsAPI
{
    const BASE_URL = 'https://www.blockonomics.co';
    const NEW_ADDRESS_URL = 'https://www.blockonomics.co/api/new_address';
    const PRICE_URL = 'https://www.blockonomics.co/api/price';
    const ADDRESS_URL = 'https://www.blockonomics.co/api/address?only_xpub=true&get_callback=true';
    const SET_CALLBACK_URL = 'https://www.blockonomics.co/api/update_callback';
    const GET_CALLBACKS_URL = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';

    public function __construct()
    {
        $this->api_key = $this->get_api_key();
    }

    public function get_api_key()
    {
        return edd_get_option('edd_blockonomics_api_key');
    }

    public function test_new_address_gen($response)
    {
        $error_str = '';
        $callback_secret = edd_get_option("edd_blockonomics_callback_secret");
        $response = $this->new_address($this->api_key, $callback_secret, true);
        if ($response->response_code!=200){ 
            $error_str = $response->response_message;
        }
        return $error_str;
    }

    public function new_address($api_key, $secret, $reset=false)
    {
        if($reset)
        {
            $get_params = "?match_callback=$secret&reset=1";
        } 
        else
        {
            $get_params = "?match_callback=$secret";
        }
        $url = BlockonomicsAPI::NEW_ADDRESS_URL.$get_params;
        $response = $this->post($url, $api_key, '', 8);
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        if (wp_remote_retrieve_body($response))
        {
          $body = json_decode(wp_remote_retrieve_body($response));
          $responseObj->{'response_message'} = isset($body->message) ? $body->message : '';
          $responseObj->{'address'} = isset($body->address) ? $body->address : '';
        }
        return $responseObj;
    }

    public function get_price($currency)
    {
    	$url = BlockonomicsAPI::PRICE_URL. "?currency=$currency";
        $response = $this->get($url);
        return json_decode(wp_remote_retrieve_body($response))->price;
    }

    public function get_xpubs($api_key)
    {
    	$url = BlockonomicsAPI::ADDRESS_URL;
        $response = $this->get($url, $api_key);
        return json_decode(wp_remote_retrieve_body($response));
    }

    public function update_callback($callback_url, $xpub)
    {
    	$url = BlockonomicsAPI::SET_CALLBACK_URL;
    	$body = json_encode(array('callback' => $callback_url, 'xpub' => $xpub));
    	$response = $this->post($url, $this->api_key, $body);
        $responseObj = json_decode(wp_remote_retrieve_body($response));
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        return json_decode(wp_remote_retrieve_body($response));
    }

    public function get_callbacks()
    {
    	$url = BlockonomicsAPI::GET_CALLBACKS_URL;
    	$response = $this->get($url, $this->api_key);
        return $response;
    }

    public function check_get_callbacks_response_code($response){
        $error_str = '';
        //TODO: Check This: WE should actually check code for timeout
        if (!wp_remote_retrieve_response_code($response)) {
            $error_str = __('Your server is blocking outgoing HTTPS calls', 'edd-blockonomics');
        }
        elseif (wp_remote_retrieve_response_code($response)==401)
            $error_str = __('API Key is incorrect', 'edd-blockonomics');
        elseif (wp_remote_retrieve_response_code($response)!=200)
            $error_str = $response->data;
        return $error_str;
    }

    public function check_get_callbacks_response_body ($response){
        $error_str = '';
        $response_body = json_decode(wp_remote_retrieve_body($response));

        //if merchant doesn't have any xPubs on his Blockonomics account
        if (!isset($response_body) || count($response_body) == 0)
        {
            $error_str = __('Please add a new store on blockonomics website', 'edd-blockonomics');
        }
        //if merchant has at least one xPub on his Blockonomics account
        elseif (count($response_body) >= 1)
        {
            $error_str = $this->examine_server_callback_urls($response_body);
        }
        return $error_str;
    }

    // checks each existing xpub callback URL to update and/or use
    public function examine_server_callback_urls($response_body)
    {
        $callback_secret = edd_get_option("edd_blockonomics_callback_secret");
        $api_url = add_query_arg('edd-listener', 'blockonomics', home_url() );
        $wordpress_callback_url = add_query_arg('secret', $callback_secret, $api_url);
        $base_url = preg_replace('/https?:\/\//', '', $api_url);
        $available_xpub = '';
        $partial_match = '';
        //Go through all xpubs on the server and examine their callback url
        foreach($response_body as $one_response){
            $server_callback_url = isset($one_response->callback) ? $one_response->callback : '';
            $server_base_url = preg_replace('/https?:\/\//', '', $server_callback_url);
            $xpub = isset($one_response->address) ? $one_response->address : '';
            if(!$server_callback_url){
                // No callback
                $available_xpub = $xpub;
            }else if($server_callback_url == $wordpress_callback_url){
                // Exact match
                return '';
            }
            else if(strpos($server_base_url, $base_url) === 0 ){
                // Partial Match - Only secret or protocol differ
                $partial_match = $xpub;
            }
        }
        // Use the available xpub
        if($partial_match || $available_xpub){
          $update_xpub = $partial_match ? $partial_match : $available_xpub;
            $response = $this->update_callback($wordpress_callback_url, $update_xpub);
            if ($response->response_code != 200) {
                return $response->message;
            }
            return '';
        }
        // No match and no empty callback
        $error_str = __("Please add a new store on blockonomics website", 'edd-blockonomics');
        return $error_str;
    }

    public function check_callback_urls_or_set_one($response) 
    {
        //chek the current callback and detect any potential errors
        $error_str = $this->check_get_callbacks_response_code($response);
        if(!$error_str){
            //if needed, set the callback.
            $error_str = $this->check_get_callbacks_response_body($response);
        }
        return $error_str;
    }

    private function get($url, $api_key = '')
    {
    	$headers = $this->set_headers($api_key);

        $response = wp_remote_get( $url, array(
            'method' => 'GET',
            'headers' => $headers
            )
        );

        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo __("Something went wrong", 'edd-blockonomics').": ".$error_message;
        }else{
            return $response;
        }
    }

    private function post($url, $api_key = '', $body = '', $timeout = '')
    {
    	$headers = $this->set_headers($api_key);

        $data = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body
            );
        if($timeout){
            $data['timeout'] = $timeout;
        }

        $response = wp_remote_post( $url, $data );
        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo "Something went wrong: $error_message";
        }else{
            return $response;
        }
    }

    private function set_headers($api_key)
    {
    	if($api_key){
    		return 'Authorization: Bearer ' . $api_key;
    	}else{
    		return '';
    	}
    }

    // Runs when the Blockonomics Test Setup button is clicked
    // Returns any errors or false if no errors
    public function testSetup()
    {
        return $this->test_one_crypto();
    }

    public function test_one_crypto()
    {
        $response = $this->get_callbacks();
        $error_str = $this->check_callback_urls_or_set_one($response);
        if (!$error_str)
        {
            //Everything OK ! Test address generation
            $error_str = $this->test_new_address_gen($crypto, $response);
        }
        if($error_str) {
            return $error_str;
        }
        // No errors
        return false;
    }

    
}
