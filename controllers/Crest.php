<?php
class Crest extends CI_Controller {
	
	
	public function __construct()
	{
		parent::__construct();
		$this->config->load('ccp_api');
		$this->load->model('OAuth_Model');
		$this->load->library( 'LibCREST', $this->config->item('crest_params') );
	}// __construct()
	
	
	public function login()		// Set up local state (for XSRF prevention) before calling external CCP URL
	{
		if( !$this->OAuth_Model->expecting_login() )
		{
			$this->session->set_flashdata( 'flash_message', 'Invalid CREST flow. Please ensure any bookmarks are still valid.' );
			log_message( 'error', 'Crest controller: Invalid CREST flow. Not expecting login().' );
			redirect('portal', 'location');
		}
		
		$scopes = $this->OAuth_Model->get_requested_scopes();
		
		$state = $this->OAuth_Model->setup_login_state();
		
		$url = $this->libcrest->get_authentication_url( $scopes, $state );
		
		redirect($url, 'location');
	}// login()
	
	public function verify()	// Registered callback URL for CREST
	{
		$state = $this->input->get('state');
		$code = $this->input->get('code');
		
		$local_state = $this->OAuth_Model->get_login_state();
		if( $local_state === NULL )
		{
			$this->session->set_flashdata( 'flash_message', 'Expired CREST state. Please avoid navigating Back during CREST actions.' );
			log_message( 'error', 'Crest controller: Expired CREST state. $state:'. $state . ', $code:' .$code );
			redirect('portal', 'location');
		}
		
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
		
		$location = $this->OAuth_Model->finish_login( $response );
		
		redirect($location, 'location');
		
	}// verify()
	
}// Crest
?>
