Subscribing Contacts
-
exp:bronto:add_or_update_contact
Adds the provided contact to the submission queue.
* string email
* string list_id separate multiple IDs with a pipe
* string custom:field_name 
* yes/no prefers_html 
* string source API source ID 

Returns: nothing

Submitting Requests
-
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

Example
-
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