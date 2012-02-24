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
 * Bronto Plugin Model
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Plugin
 * @author		Jesse Bunch (Paramore)
 * @link		http://paramore.is/
 */


class Bronto_model {

	/**
	 * Bronto URLs
	 * @param string constants
	 * @author Jesse Bunch
	*/
	const BRONTO_WSDL = 'https://api.bronto.com/v4?wsdl';
	const BRONTO_URL_HTTPS = 'https://api.bronto.com/v4';
	const BRONTO_URL_HTTP = 'http://api.bronto.com/v4';
	
	/**
	 * Message preferences
	 * @author Jesse Bunch
	*/
	const MESSAGE_PREF_HTML = 'html';
	const MESSAGE_PREF_TEXT_ONLY = 'text';

	/**
	 * Provided by the plugin, this authenticates a user
	 * to a particular bronto account
	 * @param string
	 * @author Jesse Bunch
	*/
	public $api_token;

	/**
	 * Our EE instance
	 * @param object
	 * @author Jesse Bunch
	*/
	private $EE;

	/**
	 * Instance of our SOAP client
	 * @param SoapClient
	 * @author Jesse Bunch
	*/
	private $soap_client;

	/**
	 * Provided by the bronto api after a successful login
	 * @param string
	 * @author Jesse Bunch
	*/
	private $session_id;

    
	/**
	 * Constructor
	 * @author Jesse Bunch
	*/
	public function __construct() {

		$this->EE =& get_instance();
		
		// Create the SOAP client
		$this->soap_client = new SoapClient(
			self::BRONTO_WSDL,
			array('trace' => 1, 'encoding' => 'UTF-8')
		);
		
		// Set the API Location
		$this->soap_client->__setLocation(self::BRONTO_URL_HTTPS);

	}
	
	/**
	 * Authenticates the client to the bronto API
	 * @param string $api_token
	 * @return bool
	 * @author Jesse Bunch
	*/
	public function authenticate($api_token = FALSE) {

		// Already authenticated?
		if ($this->session_id) {
			return TRUE;
		}
		
		// Validate API Token
		if (empty($this->api_token) && empty($api_token)) {
			return FALSE;
		}
		
		// New API token?
		if (!empty($api_token)) {
			$this->api_token = $api_token;
		}
		
		// Fetch the session ID
		$this->session_id = $this->soap_client->login(
			array('apiToken' => $this->api_token)
		)->return;

		// Send session ID in all request headers
		$this->soap_client->__setSoapHeaders(array(
			new SoapHeader(
				self::BRONTO_URL_HTTP,
				'sessionHeader',
				array('sessionId' => $this->session_id)
			)
		));

		return TRUE;

	}

	/**
	 * Adds a contact to the Bronto database
	 * @param string $email
	 * @param string $message_pref Either MESSAGE_PREF_HTML or MESSAGE_PREF_TEXT_ONLY
	 * @param string $custom_source
	 * @param array $custom_fields Key/Value field data
	 * @param array $list_ids 
	 * @return mixed bool|string The contact ID
	 * @author Jesse Bunch
	*/
	public function add_or_update_contacts($contacts) {

		$contact_data = array();
		foreach($contacts as $contact) {
			
			// Extract vars
			$email = $contact['email'];
			$message_pref = $contact['message_pref'];
			$custom_source = $contact['custom_source'];
			$custom_fields = $contact['custom_fields'];
			$list_ids = $contact['list_ids'];

			// Default message preference
			// We can't specify a constant in the method sig.
			if (empty($message_pref)) {
				$message_pref = self::MESSAGE_PREF_HTML;
			}
			
			// Map custom fields to their field ID
			$field_data = array();
			if (count($custom_fields)) {

				// Get the field IDs
				$field_ids = $this->_retrieve_custom_field_ids(
					array_keys($custom_fields)
				);

				// Map custom fields
				foreach($custom_fields as $field_name => $field_value) {
					$field_data[] = array(
						'fieldId' => $field_ids[$field_name],
						'content' => $field_value
					);
				}

			}

			// Put together contact data
			$contact_data[] = array(
				'email' => $email,
				'msgPref' => $message_pref,
				'customSource' => $custom_source,
				'fields' => $field_data,
				'listIds' => $list_ids
			);

		}

		// Get the result
		return $this->_send_to_bronto(
			'addOrUpdateContacts',
			$contact_data
		);

	}

	/**
	 * Sends the specified contact(s) the specified message(s)
	 * @param array $message_ids
	 * @param array $contact_ids
	 * @param string $from_name
	 * @param string $from_email
	 * @return mixed string|bool The ID of the delivery
	 * @author Jesse Bunch
	*/
	public function send_message($message_ids, $contact_ids, $from_name, $from_email) {
		
		// Set the delivery date to now
		$delivery_date = date('c');
	
		// Message IDs should be an array
		if (!is_array($message_ids)) {
			$message_ids = array($message_ids);
		}

		// Contact IDs should be an array
		if (!is_array($contact_ids)) {
			$contact_ids = array($contact_ids);
		}

		// Compose the contact data
		$contact_data = array();
		foreach($contact_ids as $contact_id) {
			$contact_data[] = array(
				'type' => 'contact',
				'id' => $contact_id
			);
		}

		// Compose the delivery data
		$delivery_data = array();
		foreach($message_ids as $message_id) {
			$delivery_data[] = array(
				'start' => $delivery_date,
				'messageId' => $message_id,
				'fromName' => $from_name,
				'fromEmail' => $from_email,
				'recipients' => $contact_data,
			);
		}

		// Get the result
		return $this->_send_to_bronto(
			'addDeliveries',
			$delivery_data
		);

	}

	/**
	 * Retrieves the field IDs for the array of internal field
	 * names you provide.
	 * @param array $field_names Value = internal_name
	 * @return array Key = internal_name, Value = field_id
	 * @author Jesse Bunch
	*/
	private function _retrieve_custom_field_ids($field_names) {
		
		static $field_cache;

		// Generate cache key
		$cache_key = base64_encode(serialize($field_names));
		
		// Check cache
		if (isset($field_cache[$cache_key])) {
			return $field_cache[$cache_key];
		}

		// Create filter
		$query_filter = array();
		foreach($field_names as $field_name) {
			$query_filter[] = array(
				$field_name => array(
					'operator' => 'Contains',
					'value' => $field_name
				)
			);
		}

		// Compose the query
		$query_data = array(
			'pageNumber' => 1,
			'filter' => $query_filter
		);

		// Get results
		$this->authenticate();
		$fields = $this->soap_client
			->readFields($query_data)
			->return;

		// Return array of IDs
		$return_fields = array();
		foreach($fields as $field) {
			$return_fields[$field->name] = $field->id;
		}

		// Cache
		$field_cache[$cache_key] = $return_fields;
		
		// Return the fields
		return $return_fields;

	}

	/**
	 * Generic method for firing off requests to bronto
	 * @param string $method WSDL method to call
	 * @param array $data Method params
	 * @return array The bronto results for each item
	 *  - id
	 *  - isNew
	 *  - errorCode
	 *  - isError
	 *  - errorString (only if isError == true)
	 * @author Jesse Bunch
	*/
	private function _send_to_bronto($method, $data) {

		// Authenticate
		$this->authenticate();
		
		// Fire!
		$bronto_results = $this->soap_client
			->$method($data)
			->return
			->results;
		
		// Make sure results are an array
		if (!is_array($bronto_results)) {
			$bronto_results = array($bronto_results);
		}

		// Create result set
		$result_set = array();
		foreach ($bronto_results as $bronto_result) {
			
			// Add info to result set
			$result_set[] = (array)$bronto_result;

		}
		
		// Return results
		return $result_set;

	}
	
}

/* End of file bronto_model.php */
/* Location: /system/expressionengine/third_party/bronto/models/bronto_model.php */