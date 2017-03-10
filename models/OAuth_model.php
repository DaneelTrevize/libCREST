<?php
Class OAuth_Model extends CI_Model {
	
	
	public function __construct()
	{
		parent::__construct();
	}// __construct()
	
	
	public function logged_in()
	{
		return isset( $_SESSION['crest']['expiry'] );
	}// logged_in()
	
	public function expect_login( $redirect/*, $scopes*/ )
	{
		$_SESSION['crest']['redirect'] = $redirect;
		
		$_SESSION['crest']['requested_scopes'] = array(
			'fleetRead',
			'fleetWrite'	// Need to track scopes of current token, request combo of previous and desired extras?
		);//$scopes;
		
	}// expect_login()
	
	public function expecting_login()
	{
		return isset( $_SESSION['crest']['redirect'] );
	}// expecting_login()
	
	public function get_requested_scopes()
	{
		return $_SESSION['crest']['requested_scopes'];
	}// get_requested_scopes()
	
	public function setup_login_state()
	{
		$state = bin2hex( openssl_random_pseudo_bytes(32) );	//	32 bytes of entropy, 64 hex chars, avoid risk of early null byte
		
		$_SESSION['crest']['auth_state'] = $state;
		
		return $state;
	}// setup_login_state()
	
	public function get_login_state()
	{
		return isset( $_SESSION['crest']['auth_state'] ) ? $_SESSION['crest']['auth_state'] : NULL;
	}// get_login_state()
	
	public function finish_login( $response )
	{
		self::store_tokens( $response );
		
		$location = $_SESSION['crest']['redirect'];
		unset( $_SESSION['crest']['redirect'] );
		return $location;
	}// finish_login()
	
	public function store_tokens( $response )
	{
		$_SESSION['crest']['auth_token'] = $response['access_token'];
		$_SESSION['crest']['refresh_token'] = $response['refresh_token'];
		$_SESSION['crest']['expiry'] = time() + (60*20);	// 20 minutes
	}// store_tokens()
	
	public function get_auth_token()
	{
		return $_SESSION['crest']['auth_token'];
	}// get_auth_token()
	
	public function need_refreshing()
	{
		return time() >= $_SESSION['crest']['expiry'];
	}// need_refreshing()
	
	public function get_refresh_token()
	{
		return $_SESSION['crest']['refresh_token'];
	}// get_refresh_token()
	
}// OAuth_Model
?>
