<?php
class Crest extends CI_Controller {
	
	
	public function __construct()
	{
		parent::__construct();
		$this->config->load('ccp_api');
		$this->load->library( 'LibCREST', $this->config->item('crest_params') );
	}// __construct()
	
	
	private function refresh_token()
	{
		if( !isset($_SESSION['crest_expiry']) )
		{
			redirect('crest/login', 'location');
		}
		
		if( time() >= $_SESSION['crest_expiry'] )
		{
			$response = $this->libcrest->refresh_access_token( $_SESSION['crest_refresh_token'] );
			if( $response === FALSE )
			{
				// Error on bad access token refreshing
				log_message( 'error', 'Crest controller: failure to refresh access token' );
			}
			
			self::reset_local_tokens( $response );
		}
	}// refresh_token()
	
	private function reset_local_tokens( $response )
	{
		$_SESSION['crest_auth_token'] = $response['access_token'];
		$_SESSION['crest_refresh_token'] = $response['refresh_token'];
		$_SESSION['crest_expiry'] = time() + (60*20);	// 20 minutes
	}// reset_local_tokens()
	
	
	public function login()		// Set up local state (for XSRF prevention) before calling external CCP URL
	{
		$scopes = array(
			'fleetRead',
			'fleetWrite'/*,
			'characterNavigationWrite'*/	// Need to track scopes of current token, request combo of previous and desired extras?
		);
		
		$state = bin2hex( openssl_random_pseudo_bytes(32) );	//	32 bytes of entropy, 64 hex chars, avoid risk of early null byte
		
		$url = $this->libcrest->get_authentication_url( $scopes, $state );
		
		$_SESSION['crest_auth_state'] = $state;
		redirect($url, 'location');
	}// login()
	
	public function verify()	// Registered callback URL for CREST
	{
		$local_state = $_SESSION['crest_auth_state'];
		$state = $_GET['state'];
		$code = $_GET['code'];
		
		$response = $this->libcrest->handle_callback( $local_state, $state, $code );
		if( $response === FALSE )
		{
			// Error on bad token verifying
			log_message( 'error', 'Crest controller: failure to verify access token' );
		}
		
		self::reset_local_tokens( $response );
		
		redirect('fleets', 'location');	// Need a redirect URL stored in session?
		
	}// verify()
	
}// Crest
?>
