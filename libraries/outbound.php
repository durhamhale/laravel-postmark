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

	private $attachments = array();
	private $attachments_total_size = 0;

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

		$this->attachments = array();
		$this->attachments_total_size = 0;

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
		$this->from = array(
			'email' => trim($email), 
			'name' => $name,
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
		$this->reply_to = array(
			'email' => trim($email), 
			'name' => $name,
		);

		return $this;
	}

	private function add_email($type, $email, $name)
	{
		if(count($this->to) + count($this->cc) + count($this->bcc) === 20)
		{
			throw new RestrictionException('Recipients may not exceed 20');
		}

		$data = array(
			'email' => trim($email), 
			'name' => $name,
		);
		
		if(isset($this->$type))
		{
			$this->{$type}[] = $data;	
		}
		else
		{
			throw new ValidationException('Invalid recipient type: "'.$type.'".');
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

	public function attachment($file_name, $content, $mime_type)
	{
		$max_size = \Config::get('postmark.max_attachment_size');

		$size = strlen($content);

		if($this->attachments_total_size + $size > $max_size)
		{
			throw new RestrictionException('Attachements may not exceed '.(($max_size/1024)/1024).'MB');
		}

		$this->attachments[] = array(
			'name' => $file_name,
			'content' => base64_encode($content),
			'mime_type' => $mime_type,
		);

		$this->attachments_total_size += $size;

		return $this;
	}

	public function send()
	{
		// Validate required data
		if(empty($this->from['email']))
		{
			throw new ValidationException('Data missing: from email address');
		}

		if((!isset($this->to[0]['email'])) || empty($this->to[0]['email']))
		{
			throw new ValidationException('Data missing: to email address');
		}

		if(empty($this->subject))
		{
			throw new ValidationException('Data missing: subject');
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

		if (!empty($this->attachments))
		{
			$data['Attachments'] = array();

			foreach($this->attachments as $file)
			{
				$data['Attachments'][] = array(
					'Name' => $file['name'],
					'Content' => $file['content'],
					'ContentType' => $file['mime_type'],
				);
			}
		}

		if(!empty($this->headers))
		{
			$data['Headers'] = array();

			foreach($this->headers as $name => $value)
			{
				$data['Headers'][] = array(
					'Name' => $name, 
					'Value' => $value,
				);
			}
		}
		
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'X-Postmark-Server-Token: '.$this->api_key,
		);

		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, 'https://api.postmarkapp.com/email');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		$curl_response = curl_exec($curl);
		$curl_info = curl_getinfo($curl);

		curl_close($curl);
		
		if($curl_info['http_code'] != 200)
		{
			if(in_array($curl_info['http_code'], array(401, 422)))
			{
				$api_response = json_decode($curl_response);

				throw new APIException('Error code: '.$api_response->ErrorCode.' - Message: '.$api_response->Message, $curl_info['http_code']);
			}
			else
			{
				throw new HTTPException('HTTP code: '.$curl_info['http_code'].' - Response: '.$curl_response, $curl_info['http_code']);
			}
		}
		
		return json_decode($curl_response)->MessageID;
	}

}

class APIException extends \Exception {}
class HTTPException extends \Exception {}
class RestrictionException extends \Exception {}
class ValidationException extends \Exception {}