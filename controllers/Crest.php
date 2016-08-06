<?php
class Crest extends CI_Controller {
	
	
    public function __construct()
    {
        parent::__construct();
		$this->load->library( 'libCREST' );
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
			'fleetWrite',
			'characterNavigationWrite'
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
		
		redirect('crest', 'location');
		
	}// verify()
	
	
	public function index()
	{
		self::refresh_token();
		
		if( !isset($_SESSION['fleet_url']) )
		{
			// Generate the form to obtain the ingame Fleet URL
			echo '<html>
			<head><title>Enter Fleet URL</title></head>
			<body>
			<form action="/crest/formFleet" method="get">
			<label for="url">Enter Fleet URL (as Fleet Boss, Fleet actions dropdown -> Copy External Fleet Link)</label>
			<input type="text" name="url">
			<input type="submit">
			</form>
			</body>
			</html>';
			exit;
		}
		else
		{
			// Use the ingame Fleet URL
			$url = $_SESSION['fleet_url'];
			
			$response = $this->libcrest->do_call( $_SESSION['crest_auth_token'], 'GET', $url );
			
			$response_decoded = json_decode( $response );
			//print_r( $response_decoded );
			
			if( $response_decoded != NULL && isset( $response_decoded->members ) )
			{
				$url = $response_decoded->members->href;
				
				$response = $this->libcrest->do_call( $_SESSION['crest_auth_token'], 'GET', $url );
				
				$response_decoded = json_decode( $response );
				//print_r( $response_decoded );
				if( $response_decoded != NULL && isset( $response_decoded->items ) )
				{
					echo '<html>
					<head><title>Fleet Tracker Example</title></head>
					<body>
					<table>
					<tr><th>Name</th><th>Location</th><th>Docked at</th><th>Ship</th></tr>';
						foreach( $response_decoded->items as $member )
						{
							print "<tr><td>".$member->character->name."</td>";
							print "<td>".$member->solarSystem->name."</td>";
							if( isset($member->station) )
							{
								print "<td>".$member->station->name."</td>";
							}
							else
							{
								print "<td>Undocked</td>";
							}
							print "<td>".$member->ship->name."</td>";
							print "</tr>";
							
						}
					echo '</table>
					</body>
					</html>';
				}
			}
			
		}
		
	}// index()
	
	public function formFleet()
	{
		self::refresh_token();

		if( !isset($_GET['url']) )
		{
			echo "Need a fleet URL";
			exit();
		}

		if( !preg_match('#^https://crest-tq.eveonline.com/fleets/(\d+)/$#', $_GET['url'], $matches) )
		{
			echo "Need a valid fleet URL";
			exit();
		}

		$_SESSION['fleet_url']=$_GET['url'];
		$_SESSION['fleet_id'] = $matches[1];
		
		// Redirect back to the index to list the fleet details
		redirect('crest', 'location');
		
	}// formFleet
	
	public function forgetFleet()
	{
		unset( $_SESSION['fleet_url'] );
		unset( $_SESSION['fleet_id'] );
		
		redirect('crest', 'location');
	}// forgetFleet()
	
	public function setFleetMOTD()
	{
		self::refresh_token();
		
		$put_array = array(
			'motd' => 'testmotd<br><loc><url=showinfo:5//30002187>Amarr</url></loc>'//	'testmotd'
		);
		
		$url ='https://crest-tq.eveonline.com/'.'/fleets/'.$_SESSION['fleet_id'].'/';
		
		$response = $this->libcrest->do_call( $_SESSION['crest_auth_token'], 'PUT', $url, $put_array, TRUE );
		print_r( $response );
	}// setFleetMOTD()
	
	public function addWing()
	{
		self::refresh_token();
		
		$url ='https://crest-tq.eveonline.com/'.'/fleets/'.$_SESSION['fleet_id'].'/wings/';
		
		$response = $this->libcrest->do_call( $_SESSION['crest_auth_token'], 'POST_JSON', $url, array(), TRUE );
		print_r( $response );
	}// addWing()
	
	public function getWings()
	{
		self::refresh_token();
		
		$url ='https://crest-tq.eveonline.com/'.'/fleets/'.$_SESSION['fleet_id'].'/wings/';
		
		$response = $this->libcrest->do_call( $_SESSION['crest_auth_token'], 'GET', $url );
		//print_r( $response );
		$response_decoded = json_decode( $response );
		print_r( $response_decoded );
	}// getWings()
	
	public function deleteWing()
	{
		self::refresh_token();
		
		if( !isset($_GET['wingID']) )
		{
			// Generate the form to obtain the ingame Fleet wingID
			echo '<html>
			<head><title>Enter wingID</title></head>
			<body>
			<form action="/crest/deleteWing" method="get">
			<label for="wingID">Enter Fleet wingID</label>
			<input type="text" name="wingID">
			<input type="submit">
			</form>
			</body>
			</html>';
			exit;
		}
		else
		{
			// Use the ingame Fleet wingID
			$wingID = $_GET['wingID'];
			$url ='https://crest-tq.eveonline.com/'.'/fleets/'.$_SESSION['fleet_id'].'/wings/'.$wingID.'/';
			
			$response = $this->libcrest->do_call( $_SESSION['crest_auth_token'], 'DELETE', $url, array(), TRUE );
			print_r( $response );
		}
	}// deleteWing()
	
	
	public function setWaypoint()
	{
		self::refresh_token();
		
		$character_id = '1416844877';	// Daneel Trevize
		
		$amarr_id = 30002187;
		
		$post_array = array(
			'clearOtherWaypoints' => false,
			'first' => false,
			'solarSystem' => array(
				'href' => 'https://crest-tq.eveonline.com/solarsystems/'.$amarr_id.'/',
				'id' => $amarr_id
			)
		);
		//$post_body = json_encode( $post_array, JSON_UNESCAPED_SLASHES );
		//print_r( $post_body );
		
		$url ='https://crest-tq.eveonline.com/'.'/characters/'.$character_id.'/ui/autopilot/waypoints/';
		
		$response = $this->libcrest->do_call( $_SESSION['crest_auth_token'], 'POST_JSON', $url, $post_array, TRUE );
		print_r( $response );
	}// setWaypoint()
	
}// Crest
?>
