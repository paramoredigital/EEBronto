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
		
		// Initialize the debug log
		$debug_log = array();
		
		// Must have contacts
		if (empty($_SESSION['bronto_contacts_queue'])) {
			return $this->EE->TMPL->no_results();
		}
		
		// Fetch params
		$api_key = $this->EE->TMPL->fetch_param('api_key', FALSE);
		$message_id_string = $this->EE->TMPL->fetch_param('message_id', '');
		$message_ids = explode('|', $message_id_string);
		$from_name = $this->EE->TMPL->fetch_param('from_name', '');
		$from_email = $this->EE->TMPL->fetch_param('from_email', '');
		$debug_log[] = "API Key: $api_key / Message ID(s): $message_id_string / From: $from_name <$from_email>";

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
		$debug_log[] = var_export($subscribe_results, TRUE);
		$debug_log[] = $this->EE->bronto_model->soap_client->__getLastRequest();

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
				$debug_log[] = var_export($delivery_results, TRUE);
				$debug_log[] = $this->EE->bronto_model->soap_client->__getLastRequest();
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
				'debug_log' => implode("\n", $debug_log)
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
	
	See the github README for detailed instructions.

<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}
}


/* End of file pi.bronto.php */
/* Location: /system/expressionengine/third_party/bronto/pi.bronto.php */