<?php if( !defined('BASEPATH') ) exit ('No direct script access allowed');

/**
 * CREST library.
 *
 * @author Daneel Trevize
 */

class LibCREST
{
	
	const LOGIN_URL = 'https://login.eveonline.com/oauth/authorize';
	const TOKEN_URL = 'https://login.eveonline.com/oauth/token';
	const VERIFY_URL = 'https://login.eveonline.com/oauth/verify';
	
	private $CI;
	private $CREST_CLIENT_ID;
	private $CREST_CLIENT_SECRET;
	private $REDIRECT_URI;
	private $USER_AGENT;
	
	public function __construct( $params )
	{
		$this->CI =& get_instance();	// Assign the CodeIgniter object to a variable
		
        $this->CREST_CLIENT_ID = $params['CLIENT_ID'];
        $this->CREST_CLIENT_SECRET = $params['CLIENT_SECRET'];
        $this->REDIRECT_URI = $params['REDIRECT_URI'];
		$this->USER_AGENT = $params['USER_AGENT'];
	}// __construct()
	
	
	public function get_authentication_url( $scopes, $state )
	{
		/*
		*	We assume the application state has been verified as to not be in the middle of
		*	a previous authentication flow, and that $state is sufficiently random.
		*/
		$fields = [
			"response_type" => "code", 
			"client_id" => $this->CREST_CLIENT_ID,
			"redirect_uri" => $this->REDIRECT_URI, 
			"scope" => implode( ' ', $scopes ),
			"state" => $state
		];
		$params = self::build_params( $fields );

		$url = self::LOGIN_URL .'?'. $params;
		
		return $url;
	}// get_authentication_url()
	
	private static function build_params( $fields )
	{
		$string = '';
		foreach( $fields as $field => $value )
		{
			$string .= $string == '' ? '' : '&';
			$string .= "$field=" . rawurlencode( $value );
		}
		return $string;
	}// build_params()
	
	
	public function do_call( $accessToken, $callType, $url, $fields = array(), $return_response_code = FALSE )
	{
		if( $accessToken === null )
		{
			$header = 'Authorization: Basic ' . base64_encode( $this->CREST_CLIENT_ID . ':' . $this->CREST_CLIENT_SECRET );
		}
		else
		{
			$header = 'Authorization: Bearer ' . $accessToken;
		}
		$headers = [$header];
		
		$ch = curl_init();
		if( $ch === FALSE )
		{
			// Log an error about cURL failing?
			log_message( 'error', 'LibCREST: cURL failed to init()');
			return FALSE;
		}
		
		curl_setopt( $ch, CURLOPT_USERAGENT, $this->USER_AGENT );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, TRUE );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $callType );
		
		switch( $callType )
		{
			case 'GET':
				$fieldsString = self::build_params( $fields );
				$url = $url .'?'. $fieldsString;
				break;
			case 'POST':
				$fieldsString = self::build_params( $fields );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $fieldsString );
				break;
			case 'POST_JSON':	// Fake verb to indicate POSTing JSON rather than form fields
				curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
				$headers[] = 'Content-Type: application/json';
				if( empty($fields) )
				{
					$fieldsString = json_encode( (object) NULL );	// Force an empty Dict to be created, rather than List
				}
				else
				{
					$fieldsString = json_encode( $fields, JSON_UNESCAPED_SLASHES );
				}
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $fieldsString );
				break;
			case 'PUT':
			case 'DELETE':
				$headers[] = 'Content-Type: application/json';
				$fieldsString = json_encode( $fields, JSON_UNESCAPED_SLASHES );
				curl_setopt($ch, CURLOPT_INFILESIZE, strlen($fieldsString) );	// Instead of headers[] = 'Content-Length: ' . strlen($fieldsString); ?
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $fieldsString );
				break;
			case 'OPTIONS':
				break;
			default:
				// Log an error about invalid callType?
				log_message( 'error', 'LibCREST: invalid callType:'. $callType );
				return FALSE;
		}
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		
		$result = curl_exec( $ch );
		
		if( $result === FALSE )
		{
			// Log curl_error($ch) ?
			log_message( 'error', 'LibCREST: '. curl_error($ch) );
		}
		
		if( $return_response_code == TRUE )
		{
			$return = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		}
		else
		{
			$return = $result;
		}
		
		curl_close( $ch );
		
		return $return;
	}// do_call()
	
	
	public function handle_callback( $local_state, $state, $code )
	{
		if( $local_state != $state )
		{
			// Log an error about invalid state?
			log_message( 'error', 'LibCREST: invalid state' );
			return FALSE;
		}

		$fields = array(
			'grant_type' => 'authorization_code',
			'code' => $code
		);
		
		$access_response = $this->do_call( NULL, 'POST', self::TOKEN_URL, $fields );
		$handled_access_response = $this->handle_access_response( $access_response );
		if( $handled_access_response === FALSE )
		{
			// Log an error about failure to acquire an access token?
			log_message( 'error', 'LibCREST: failure to acquire an access token' );
			return FALSE;
		}
		
		$verify_response = $this->do_call( $handled_access_response['access_token'], 'GET', self::VERIFY_URL );
		if( $verify_response === FALSE )
		{
			// Log an error about failure to acquire a verification token?
			log_message( 'error', 'LibCREST: failure to acquire a verification token' );
			return FALSE;
		}
		
		$verify_decoded = json_decode( $verify_response, TRUE );
		if( !is_array($verify_decoded) || !isset($verify_decoded['CharacterName']) )
		{
			// Unexpected response, not a valid verification token.
			return FALSE;
		}
		
		$handled_access_response['verify_decoded'] = $verify_decoded;
		return $handled_access_response;
	}// handle_callback()
	
	private function handle_access_response( $access_response )
	{
		if( $access_response === FALSE )
		{
			// Log an error about failure to acquire an access token?
			log_message( 'error', 'LibCREST: failure to acquire an access token' );
			return FALSE;
		}
		
		$access_decoded = json_decode( $access_response, TRUE );
		if( !is_array($access_decoded) || !isset($access_decoded['access_token']) )
		{
			// Unexpected response, not a valid access token.
			return FALSE;
		}
		$access_token = $access_decoded['access_token'];
		$refresh_token = $access_decoded['refresh_token'];
		
		return array(
			'access_token' => $access_token,
			'refresh_token' => $refresh_token
		);
	}// handle_access_response()
	
	
	public function refresh_access_token( $refresh_token )
	{
		$fields = array(
			'grant_type' => 'refresh_token',
			'refresh_token' => $refresh_token
		);
		
		$access_response = $this->do_call( NULL, 'POST', self::TOKEN_URL, $fields );
		$handled_access_response = $this->handle_access_response( $access_response );
		if( $handled_access_response === FALSE )
		{
			// Log an error about failure to acquire an access token?
			log_message( 'error', 'LibCREST: failure to acquire an access token' );
			return FALSE;
		}
		
		return $handled_access_response;
	}// refresh_access_token()
	
}// LibCREST
?>