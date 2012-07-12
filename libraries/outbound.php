<?php

namespace Postmark;

/**
 * Laravel bundle for the Postmark API (http://postmarkapp.com)
 *
 * @package		Postmark
 * @author		Durham Hale <durham@durhamhale.com>
 * @link 		http://github.com/durhamhale/laravel-postmark
 */
class Outbound {
	
	private $api_key = '';
	
	private $to = array();
	private $cc = array();
	private $bcc = array();
	private $from = array();
	private $reply_to = array();
	
	private $subject = '';
	
	private $html = '';
	private $text = '';

	private $tag = array();

	private $headers = array();

	public function __construct()
	{
		$config = \Config::get('postmark');

		$this->api_key = $config['api_key'];

		if(empty($config['api_key']))
		{
			$this->api_key = 'POSTMARK_API_TEST';
		}

		$this->reset();
	
		$this->from($config['from_email'], $config['from_name']);
	}

	public static function init()
	{
		return new self();
	}

	public function reset()
	{
		$this->to = array();
		$this->cc = array();
		$this->bcc = array();
		$this->from = array();
		$this->reply_to = array();
	
		$this->subject = '';
	
		$this->html = '';
		$this->text = '';

		$this->tag = array();

		$this->headers = array();
	}

	public function to($email, $name = '')
	{
		$this->add_email('to', $email, $name);

		return $this;
	}

	public function cc($email, $name = '')
	{
		$this->add_email('cc', $email, $name);

		return $this;
	}

	public function bcc($email, $name = '')
	{
		$this->add_email('bcc', $email, $name);

		return $this;
	}

	public function from($email, $name = '')
	{
		if(!$this->validate_email($email))
		{
			throw new \Exception('Invalid email address: from - '.$email);
		}

		$this->from = array(
			'email' => trim($email), 
			'name' => $name
		);

		return $this;
	}

	public function from_name($name)
	{
		$this->from['name'] = $name;

		return $this;
	}

	public function reply_to($email, $name = '')
	{
		if(!$this->validate_email($email))
		{
			throw new \Exception('Invalid email address: reply_to - '.$email);
		}

		$this->reply_to = array(
			'email' => trim($email), 
			'name' => $name
		);

		return $this;
	}

	private function add_email($type, $email, $name)
	{
		if(!$this->validate_email($email))
		{
			throw new \Exception('Invalid email address: '.$type.' - '.$email);
		}

		$data = array(
			'email' => trim($email), 
			'name' => $name
		);
		
		if(isset($this->$type))
		{
			$this->{$type}[] = $data;	
		}
		else
		{
			throw new \Exception('Invalid recipient type "'.$type.'".');
		}
	}

	public function subject($subject)
	{
		$this->subject = $subject;
		
		return $this;
	}

	public function html($message)
	{
		$this->html = $message;
		
		return $this;
	}

	public function text($message)
	{
		$this->text = $message;
		
		return $this;
	}

	public function tag($tag)
	{
		$this->tag = $tag;
		
		return $this;
	}

	public function header($name, $value)
	{
		$this->headers[$name] = $value;
		
		return $this;
	}

	private function format_address($email, $name = '')
	{
		return (!empty($name))
			? '"'.str_replace('"', '', $name).'" <'.$email.'>'
			: $email;
	}

	public function send()
	{
		// Validate required data
		if(empty($this->from['email']))
		{
			throw new \Exception('Data missing: from email address');
		}

		if((!isset($this->to[0]['email'])) || empty($this->to[0]['email']))
		{
			throw new \Exception('Data missing: to email address');
		}

		if(empty($this->subject))
		{
			throw new \Exception('Data missing: subject');
		}

		// Format data to be posted
		$data = array(
			'Subject' => $this->subject,
			'From' => $this->format_address($this->from['email'], $this->from['name']),
		);
		
		foreach(array('to', 'cc', 'bcc') as $type)
		{
			if(!empty($this->$type))
			{
				$addresses = array();
		
				foreach($this->$type as $address)
				{
					$addresses[] = $this->format_address($address['email'], $address['name']);
				}

				$data[ucfirst($type)] = implode(', ', $addresses);
			}
		}

		if(!empty($this->reply_to))
		{
			$data['ReplyTo'] = $this->format_address($this->reply_to['email'], $this->reply_to['name']);
		}

		if(!empty($this->html))
		{
			$data['HtmlBody'] = $this->html;
		}

		if(!empty($this->text))
		{
			$data['TextBody'] = $this->text;
		}

		if(!empty($this->tag))
		{
			$data['Tag'] = $this->tag;
		}

		if(!empty($this->headers))
		{
			$data['Headers'] = array();

			foreach($this->headers as $name => $value)
			{
				$data['Headers'][] = array(
					'Name' => $name, 
					'Value' => $value
				);
			}
		}

		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'X-Postmark-Server-Token: '.$this->api_key
		);

		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, 'https://api.postmarkapp.com/email');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		$curl_response = curl_exec($curl);
		$curl_error = curl_error($curl);
		$curl_info = curl_getinfo($curl);

		curl_close($curl);

		if($curl_error !== '')
		{
			throw new \Exception($curl_error);
		}
		
		if($curl_info['http_code'] != 200)
		{
			throw new \Exception('Postmark error - HTTP code: '.$curl_info['http_code'].' - Response '.$curl_response, $curl_info['http_code']);
		}
		
		return json_decode($curl_response)->MessageID;
	}

	private function validate_email($email)
	{
		// http://php.net/manual/en/function.filter-var.php
		// return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
		// filter_var proved to be unworthy (passed foo..bar@domain.com as valid),
		// and was therefore replace with
		$regex = "/^([\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+\.)*[\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,6})|(\d{1,3}\.){3}\d{1,3}(\:\d{1,5})?)$/i";
		// from http://fightingforalostcause.net/misc/2006/compare-email-regex.php
		return (preg_match($regex, $email) === 1);
	}

}