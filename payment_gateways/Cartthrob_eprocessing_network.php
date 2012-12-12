<?php 
class Cartthrob_eprocessing_network extends Cartthrob_payment_gateway
{
	public $title = 'eprocessing_network_title';

	public $settings = array(
		array(
			'name' => 'eprocessing_network_settings_api_login',
			'short_name' => 'api_login',
			'type' => 'text'
		),
		array(
			'name' => 'eprocessing_network_settings_trans_key',
			'short_name' => 'transaction_key',
			'type' => 'text'
		),
		array(
			'name' => 'eprocessing_network_settings_email_customer',
			'short_name' => 'email_customer',
			'type' => 'radio',
			'default' => "no",
			'options' => array(
				"no"	=> "no",
				"yes"	=> "yes"
			)
		),
		array(
			'name' => "mode",
			'short_name' => 'mode',
			'type' => 'radio',
			'default' => "test",
			'options' => array(
				"test"	=> "test",
				"live"	=> "live",
				"developer" => "developer"
			)
		),


		
	);
	
	public $required_fields = array(
		'first_name',
		'last_name',
		'address',
		'city',
		'state',
		'zip',
		'phone',
		'email_address',
		'credit_card_number',
		'expiration_year',
		'expiration_month',
	);
	
	public $fields = array(
		'first_name',
		'last_name',
		'address',
		'address2',
		'city',
		'state',
		'zip',
		'phone',
		'email_address',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_address',
		'shipping_address2',
		'shipping_city',
		'shipping_state',
		'shipping_zip',
		'card_type',
		'credit_card_number',
		'CVV2',
		'expiration_year',
		'expiration_month'
	);
 
	public $hidden = array();
	public $card_types = NULL;
	
	public function initialize()
	{
 
		$this->_x_type 					= ($this->plugin_settings('transaction_settings') ? $this->plugin_settings('transaction_settings') : 'AUTH_CAPTURE' );
 		$this->_x_test_request         	= 	"FALSE";

		 		
		$this->api_login = $this->plugin_settings('api_login');
		$this->transaction_key = $this->plugin_settings('transaction_key');
 
		if ($this->plugin_settings('mode') == "test") 
		{
			$this->_x_test_request         	= "TRUE";
 		}
 
	}
	/**
	 * process_payment
	 *
 	 * @param string $credit_card_number 
	 * @return mixed | array | bool An array of error / success messages  is returned, or FALSE if all fails.
	 * @author Chris Newton
	 * @access public
	 * @since 1.0.0
	 */
	public function process_payment($credit_card_number)
	{
 		$post_array = array(
			"x_login"         				=> $this->api_login,
			"x_tran_key"           			=> $this->transaction_key,
			"x_version"           	 		=> "3.1",
			"x_test_request"    		   	=> $this->_x_test_request,
			"x_delim_data"    	    	 	=> "TRUE",
			"x_delim_char"                => ",",
		    "x_encap_char"                => "|", 
			"x_relay_response"				=> "FALSE",
			"x_first_name"       	     	=> $this->order('first_name'),
			"x_last_name"       	     	=> $this->order('last_name'),
			"x_address"      		      	=> $this->order('address')." ".$this->order('address2'),
			"x_city"            	    	=> $this->order('city'),
			"x_state"              		  	=> $this->order('state'),
			"x_description"					=> $this->order('description'),
			"x_zip"            		    	=> $this->order('zip'),
			"x_country"            		   	=> $this->alpha2_country_code(($this->order('country_code') ? $this->order('country_code') : 'USA')),
			'x_ship_to_first_name'			=> ($this->order('shipping_first_name')) ? $this->order('shipping_first_name') : $this->order('first_name'),
			'x_ship_to_last_name'			=> ($this->order('shipping_last_name')) ? $this->order('shipping_last_name') : $this->order('last_name'),
			'x_ship_to_address'				=> ($this->order('shipping_address')) ? $this->order('shipping_address').' '.$this->order('shipping_address2') : $this->order('address').' '.$this->order('address2'),
			'x_ship_to_city'				=> ($this->order('shipping_city')) ? $this->order('shipping_city') : $this->order('city'),
			'x_ship_to_state'				=> ($this->order('shipping_state')) ? $this->order('shipping_state') : $this->order('state'),
			'x_ship_to_zip'					=> ($this->order('shipping_zip')) ? $this->order('shipping_zip') : $this->order('zip'),
			"x_phone"          		      	=> $this->order('phone'),
			"x_email"          		      	=> $this->order('email_address'),
			"x_cust_id"          		   	=> $this->order('member_id'),
			"x_invoice_num"					=> time().strtoupper(substr($this->order('last_name'), 0, 3)),
			"x_company"						=> $this->order('company'),
			"x_email_customer"    		 	=> ($this->plugin_settings('email_customer') == "yes") ? "TRUE" : "FALSE",
			"x_amount"               	 	=> number_format($this->total(),2,'.',''),
			"x_method"               	 	=> "CC",
			"x_type"                 		=> $this->_x_type,  // set to AUTH_CAPTURE for money capturing transactions
			"x_card_num"             		=> $credit_card_number,
			"x_card_code"             		=> $this->order('CVV2'),
			"x_exp_date"             		=> str_pad($this->order('expiration_month'), 2, '0', STR_PAD_LEFT).'/'.$this->year_2($this->order('expiration_year')),
			"x_tax"							=> $this->order('tax'),
			"x_freight"						=> $this->order('shipping'),
		);
	
		reset($post_array);
		$data='';
		while (list ($key, $val) = each($post_array)) 
		{
			$data .= $key . "=" . urlencode($val) . "&";
		}
		
		$auth['authorized']	 	= FALSE; 
		$auth['declined'] 		= FALSE; 
		$auth['transaction_id']	= NULL;
		$auth['failed']			= TRUE; 
		$auth['error_message']	= "";
		
 		$ch = curl_init();
 		curl_setopt($ch, CURLOPT_URL, "https://www.eprocessingnetwork.com/cgi-bin/an/transact.pl");
		curl_setopt($ch, CURLOPT_HEADER, 0); 		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data); 
		$connect = curl_exec($ch); 
 		
  		if (!$connect)
		{
 			$auth['error_message'] = $this->lang('curl_gateway_failure')." ".  curl_error($ch);
 
			return $auth; 
		}
		
		curl_close ($ch);
		$response = $this->csv2array($connect, ",", "|"); 
		$answer = explode(',',$response[0]);
		
		$answer_status = substr($answer[4],0,8);

		
		switch($response[0])
		{
			case 1: 
				$auth['authorized'] 	= TRUE; 
				$auth['failed']			= FALSE; 
				$auth['transaction_id'] = @$response[6];
			break;
			case 2:
				$auth['authorized']	 	= FALSE; 
				$auth['declined'] 		= TRUE; 
				$auth['transaction_id']	= NULL;
				$auth['failed']			= FALSE; 
				$auth['error_message']	= @$response[3];
			break;
			case 3: 
				$auth['authorized']	 	= FALSE; 
				$auth['declined'] 		= FALSE; 
				$auth['transaction_id']	= NULL;
				$auth['failed']			= TRUE; 
				$auth['error_message']	= @$response[3];
				break;
			break;
			default:
				$auth['failed']			= TRUE; 
				$auth['error_message']	= $this->lang('eprocessing_network_error_1') . $response[0];
		}
		return $auth;
	}
	// END Auth
	
	function csv2array($input,$delimiter=',',$enclosure='"',$escape='\\')
	{ 
	    $fields = explode($enclosure.$delimiter.$enclosure,substr($input,1,-1)); 
	
	    foreach ($fields as $key=>$value) 
		{
	        $fields[$key]=str_replace($escape.$enclosure,$enclosure,$value); 
		}
	    return($fields); 
	}

	
}
// END Class