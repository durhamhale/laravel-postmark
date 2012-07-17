<?php
 
return array(
	
	/*
	|--------------------------------------------------------------------------
	| API Key
	|--------------------------------------------------------------------------
	|
	| Required to use the API. Leave blank to send “test” emails that don’t 
	| actually get delivered to the recipient. 
	|
	*/
	
	'api_key' => '',

	/*
	|--------------------------------------------------------------------------
	| Sender Details
	|--------------------------------------------------------------------------
	|
	| You should have a registered and confirmed sender signature address 
	| to send from. These details can be entered below, although they can be 
	| dynamically updated via the bundle api.
	|
	*/

	'from_email' => '',
	'from_name' => '',

	/*
	|--------------------------------------------------------------------------
	| Maximum Attachment Size- bytes
	|--------------------------------------------------------------------------
	|
	| Postmark limits the maximum size of all attachments to 10MB. This value
	| should be be changes if you wish to lower the attachment size limit.
	|
	*/

	'max_attachment_size' => '10485760',

);