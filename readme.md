### How to Use

Subscribing folks to a Bronto account consists of two steps:

1. make a call to `add_or_update_contact` which will queue up the subscription request. 
2. after you've added all the contacts you want to update, make a call to `submit` to actually fire off the requests to Bronto and return the results.

### Subscribing Contacts
Calling the method doesn't actually subscribe the contact. It just adds them to the subscription queue.
To actually push the subscriptions to Bronto, you must call the `submit` plugin method.

	exp:bronto:add_or_update_contact
	* param string email
	* param string list_id separate multiple IDs with a pipe
	* param string custom:field_name 
	* param yes/no prefers_html 
	* param string source API source ID
	* return void

### Submitting Requests
Submits any previously-queued subscription requests to Bronto. Via the template parser, this method returns the
results as well as any error messages that were encountered.

	exp:bronto:submit
	* param string api_key REQUIRED 
	* param string message_id To send a trigger, provide the message IDs. Separate multiple messages with a pipe.
	* param string from_name
	* param string from_email
	* return Subscription Results.
		- string contact_id
		- bool is_new
		- bool success
		- contact
			- string email
			- string message_pref (html|text)
			- string custom_source

### Example
This example subscribes two people successfully, but the third will show
and error message since the email is invalid.

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
			Debug Log: {debug_log}
		{if:else}
			Error: {error_string}<br>
		{/if}

		-------------------<br>

	{/exp:bronto:submit}