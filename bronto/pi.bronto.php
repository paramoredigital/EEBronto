<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * Bronto Plugin
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Plugin
 * @author		Jesse Bunch (Paramore)
 * @link		http://paramore.is/
 */

$plugin_info = array(
	'pi_name'		=> 'Bronto',
	'pi_version'	=> '1.0',
	'pi_author'		=> 'Jesse Bunch (Paramore)',
	'pi_author_url'	=> 'http://paramore.is/',
	'pi_description'=> 'Performs various interactions with the Bronto API',
	'pi_usage'		=> Bronto::usage()
);


class Bronto {

	public $return_data;
	private $EE;

	/**
	 * Constructor
	 * @author Jesse Bunch
	*/
	public function __construct() {
		$this->EE =& get_instance();
	}
	
	/**
	 * Subscribes a contact, optionally sending them an email.
	 * @param string email
	 * @param string list_id separate multiple IDs with a pipe
	 * @param string custom:field_name 
	 * @param yes/no prefers_html 
	 * @param string source API source ID
	 * @return void
	 * @author Jesse Bunch
	*/
	public function add_or_update_contact() {
		
		$this->EE->load->model('bronto_model');

		// Fetch params
		$email = $this->EE->TMPL->fetch_param('email');
		$list_ids = explode('|', $this->EE->TMPL->fetch_param('list_id', ''));
		$custom_fields = $this->_fetch_custom_fields();
		$prefers_html = $this->EE->TMPL->fetch_param('prefers_html', 'yes');
		$source = $this->EE->TMPL->fetch_param('source', '');

		// List IDs should be an array
		if (!is_array($list_ids)) {
			$list_ids = array($list_ids);
		}
		
		// Message Preference
		$message_pref = Bronto_model::MESSAGE_PREF_HTML;
		if ($prefers_html == 'no') {
			$message_pref = Bronto_model::MESSAGE_PREF_TEXT_ONLY;
		}

		// Add to the queue
		$_SESSION['bronto_contacts_queue'][] = array(
			'email' => $email,
			'message_pref' => $message_pref,
			'custom_source' => $source,
			'custom_fields' => $custom_fields,
			'list_ids' => $list_ids
		);

		return $this->return_data = '';

	}
	
	/**
	 * Actually sends the queued requests to Bronto
	 * @param string api_key REQUIRED 
	 * @param string message_id To send a trigger, provide the message IDs. Separate multiple messages with a pipe.
	 * @param string from_name
	 * @param string from_email
	 * @return string Parsed template results
	 * @author Jesse Bunch
	*/
	public function submit() {

		$this->EE->load->model('bronto_model');
		
		// Must have contacts
		if (empty($_SESSION['bronto_contacts_queue'])) {
			return $this->EE->TMPL->no_results();
		}
		
		// Fetch params
		$api_key = $this->EE->TMPL->fetch_param('api_key', FALSE);
		$message_ids = explode('|', $this->EE->TMPL->fetch_param('message_id', ''));
		$from_name = $this->EE->TMPL->fetch_param('from_name', '');
		$from_email = $this->EE->TMPL->fetch_param('from_email', '');

		// Message IDs should be an array
		if (!empty($message_ids) AND !is_array($message_ids)) {
			$message_ids = array($message_ids);
		}

		// API Key is a must
		if (empty($api_key)) {
			return $this->return_data = 'You must provide an API Key!';
		}

		// Set API Key
		$this->EE->bronto_model->api_token = $api_key;

		// Subscribe contacts!
		$subscribe_results = $this->EE->bronto_model->add_or_update_contacts(
			$_SESSION['bronto_contacts_queue']
		);

		// Send messages?
		if (count($message_ids)) {
			
			$contact_ids = array();
			
			// Make sure we only attempt email sending
			// to folks who were successfully subscribed
			foreach($subscribe_results as $subscribe_result) {
				if (!$subscribe_result['isError']) {
					$contact_ids[] = $subscribe_result['id'];
				}
			}
			
			// If we have successes, send the messages
			if (count($contact_ids)) {
				$delivery_results = $this->EE->bronto_model->send_message(
					$message_ids,
					$contact_ids,
					$from_name,
					$from_email
				);	
			}

		}

		// Create result vars
		$data_vars = array();
		foreach($subscribe_results as $index => $subscribe_result) {
			$data_vars[] = array(
				'success' => (!$subscribe_result['isError']),
				'is_new' => @$subscribe_result['isNew'],
				'contact_id' => @$subscribe_result['id'],
				'error_string' => @$subscribe_result['errorString'],
				'contact' => array($_SESSION['bronto_contacts_queue'][$index]),
			);
		}
		
		// Parse variables
		$return_string = $this->EE->TMPL->parse_variables(
			$this->EE->TMPL->tagdata,
			$data_vars
		);

		// Clean up
		unset($_SESSION['bronto_contacts_queue']);

		// Return parsed string
		return $return_string;

	}

	/**
	 * Returns a key/value array of tag paramters that
	 * begin with "custom:"
	 * @return array
	 * @author Jesse Bunch
	*/
	private function _fetch_custom_fields() {

		// Get all params
		$all_params = $this->EE->TMPL->tagparams;

		// Pull out params that start with "custom:"
		$custom_fields = array();
		if (is_array($all_params) && count($all_params)) {
			foreach ($all_params as $key => $val) {
				if (strncmp($key, 'custom:', 7) == 0) {
					$custom_fields[substr($key, 7)] = $val;
				}
			}					
		}

		return $custom_fields;

	}

	// ------------------------------------------------------------------------
	
	/**
	 * Plugin Usage
	 * @author Jesse Bunch
	*/
	public static function usage() {
		ob_start();
?>
	
	exp:bronto:add_or_update_contact
	Adds the provided contact to the submission queue.
	* string email
	* string list_id separate multiple IDs with a pipe
	* string custom:field_name 
	* yes/no prefers_html 
	* string source API source ID 

	Returns: nothing

	exp:bronto:submit
	Submits all queued contacts to Bronto for subscription
	* string api_key REQUIRED 
	* string message_id To send a trigger, provide the message IDs. Separate multiple messages with a pipe.
	* string from_name
	* string from_email

	Returns: Subscription Results.
		- string contact_id
		- bool is_new
		- bool success
		- contact
			- string email
			- string message_pref (html|text)
			- string custom_source

	Example:

	{exp:bronto:add_or_update_contact
		email="bunch.jesse@gmail.com"
		list_id="0bba03ec000000000000000000000003e539"
	}

	{exp:bronto:add_or_update_contact
		email="jbunch@paramore.is"
		custom:first_name="Jesse"
		custom:last_name="Bunch"
		list_id="0bba03ec000000000000000000000003e539"
	}

	{exp:bronto:add_or_update_contact
		email="jbunchparamore.is"
		custom:first_name="Jesse"
		custom:last_name="Bunch"
		list_id="0bba03ec000000000000000000000003e539"
	}

	{exp:bronto:submit
		from_name="Paramore Test"
		from_email="test@paramore.is"
		message_id="0bba03eb0000000000000000000000083d02"
		api_key="JLKJH6789876TYUIKJVB"
	}

		{if no_results}No contacts to add!{/if}
		
		{if success}
			Contact ID: {contact_id}<br>
			Success: {if success}Yes{if:else}No{/if}<br>
			Is New: {if is_new}Yes{if:else}No{/if}<br>
			{contact}
				Email: {email}<br>
				Message Preference: {message_pref}<br>
				API Source: {custom_source}<br>
			{/contact}
		{if:else}
			Error: {error_string}<br>
		{/if}

		-------------------<br>

	{/exp:bronto:submit}

<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}
}


/* End of file pi.bronto.php */
/* Location: /system/expressionengine/third_party/bronto/pi.bronto.php */