// Snippets to demo how LibCREST is used.

	private function refresh_token()
	{
		if( $this->CI->CREST_model->need_refreshing() )
		{
			$response = $this->CI->libcrest->refresh_access_token( $this->CI->CREST_model->get_refresh_token() );
			if( $response === FALSE )
			{
				// Error on bad access token refreshing
				self::log_error_user( 'failure to refresh access token.' );
				return FALSE;
			}
			
			$this->CI->CREST_model->store_tokens( $response );
			return TRUE;
		}
	}// refresh_token()
  

	public function set_fleet_motd( $fleet_scheduled_details )
	{
		self::refresh_token();
		
		$motd = self::generate_MOTD( $fleet_scheduled_details );
		
		$put_array = array(
			'isFreeMove' => TRUE,
			'motd' => $motd
		);
		
		$response = $this->CI->libcrest->do_call( $this->CI->CREST_model->get_auth_token(), 'PUT', self::get_fleet_url(), $put_array, TRUE );
		return $response;
	}// set_fleet_motd()
