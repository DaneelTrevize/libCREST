<?php
class Crest extends CI_Controller {
	
	
	public function __construct()
	{
		parent::__construct();
		$this->config->load('ccp_api');
		$this->load->model('CREST_model');
		$this->load->library( 'LibCREST', $this->config->item('crest_params') );
	}// __construct()
	
	
	public function login()		// Set up local state (for XSRF prevention) before calling external CCP URL
	{
		if( !$this->CREST_model->expecting_login() )
		{
			$this->session->set_flashdata( 'flash_message', 'Invalid CREST flow. Please ensure any bookmarks are still valid.' );
			log_message( 'error', 'Crest controller: Invalid CREST flow. Not expecting login().' );
			redirect('portal', 'location');
		}
		
		$scopes = array(
			'fleetRead',
			'fleetWrite'/*,
			'characterNavigationWrite'*/	// Need to track scopes of current token, request combo of previous and desired extras?
		);
		
		$state = $this->CREST_model->setup_login_state();
		
		$url = $this->libcrest->get_authentication_url( $scopes, $state );
		
		redirect($url, 'location');
	}// login()
	
	public function verify()	// Registered callback URL for CREST
	{
		$local_state = $this->CREST_model->get_login_state();
		$state = $_GET['state'];
		$code = $_GET['code'];
		
		if( $state == NULL || $state == '' || $code == NULL || $code == '' )
		{
			// Redirect to login?
			$this->session->set_flashdata( 'flash_message', 'Invalid CREST flow. Please ensure any bookmarks are still valid.' );
			log_message( 'error', 'Crest controller: Invalid CREST flow. $state:'. $state . ', $code:' .$code );
			redirect('portal', 'location');
		}
		
		$response = $this->libcrest->handle_callback( $local_state, $state, $code );
		if( $response === FALSE )
		{
			// Error on bad token verifying
			$this->session->set_flashdata( 'flash_message', 'CREST login failed.' );
			log_message( 'error', 'Crest controller: failure to verify access token' );
			redirect('portal', 'location');
		}
		
		$location = $this->CREST_model->finish_login( $response );
		
		redirect($location, 'location');
		
	}// verify()
	
}// Crest
?>
