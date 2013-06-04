<?php
/**
 * Titanium Cloud Library
 *
 * @category  Library
 * @package   Titanium_Cloud
 * @author    Joel Herron <http://h3r2on.com>
 * @copyright 2013 Joel Herron
 * @license   MIT License http://www.opensource.org/licenses/mit-license.php
 * @version   Release: 1.0.1
 * @link      https://github.com/h3r2on/Titanium-Cloud
 */

class Titanium_Cloud {

	protected $api_url;
	protected $app_key;
	protected $email;
	protected $password;
	protected $_cookie		= '/tmp/appcookie';
	protected $_errors;

	function __construct() {
		$this->ci =& get_instance();

		$this->cookie .= time() .'.txt';

		$this->ci->load->config('titanium_cloud');
		$this->ci->load->library('session');

		$this->_init();
	}

	/**
	 * @access private
	 * @return void
	 */
	private function _init() {
		$this->api_url = $this->ci->config->item('api_url');
		$this->app_key	= $this->ci->config->item('app_key');
		$this->auditlog = $this->ci->config->item('audit_log');
	}

	/**
	 * @access public
	 * @param string $url
	 * @param string $method
	 * @param object $data
	 * @param bool $secure
	 * @return array 
	 */
	function send_request($url, $method, $data, $secure=TRUE)
	{
		$url = $this->_build_url($url, $secure) . ($method=='GET' ? '&'.http_build_query($data) : '');

		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->_cookie);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->_cookie);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

		switch ($method)
		{
			case 'GET':
				curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
				break;
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, TRUE);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
				break;
			case 'PUT':
				curl_setopt($ch, CURLOPT_PUT, TRUE);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
				break;
			case 'DELETE':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
				break;
		}
		 
		$output = curl_exec($ch);

		if ($output == FALSE)
		{
			return curl_error($ch);
		}
		
		return json_decode($output, true);
	}

	/**
	 * @access public
	 * @param string $email
	 * @param string $passord
	 * @return bool
	 */
	function login($email, $password)
	{
		$user_info = $this->_authenticate($email, $password);
		if($user_info){
			$this->_audit("Successful login: ".$user_info['cn']."(".$email.") from ".$this->ci->input->ip_address());

			// Set the session data
			$customdata = array(
				'id' 				=> $user_info['id'],
				'email' 		=> $email,
				'cn' 				=> $user_info['cn'],
				'role' 			=> $user_info['role'],
				'logged_in' => TRUE
			);

			$this->ci->session->set_userdata($customdata);

			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * @access public
	 * @return bool
	 */
	function is_authenticated()
	{
		if($this->ci->session->userdata('logged_in')) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * @access public
	 * @return void
	 */
	function logout()
	{
		// Just set logged_in to FALSE and then destroy everything for good measure
		$this->ci->session->set_userdata(array('logged_in' => FALSE));
		$this->ci->session->sess_destroy();	
	}

	/**
	 * @access protected
	 * @param string $email
	 * @param string $password
	 * @return array
	 */
	protected function _authenticate($email, $password)
	{
		$login = array(
			'login'    => $email, 
			'password' => $password
		);

		$ch = curl_init($this->_build_url('users/login.json'));
		 
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->_cookie);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->_cookie);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_POST, TRUE);			 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $login);
		 
		$login = curl_exec($ch);
		if($login == FALSE) {
			print_r(curl_error($ch));
		} 

		$response = json_decode($login, true);

		$user = $response['response']['users'][0];

		return array(
			'id' 		=> $user['id'],
			'cn' 		=> $user['first_name'] . ' ' . $user['last_name'],
			'role'	=> $user['role'],
			'email'	=> $user['email']
		);
	}

	/**
	 * @access private
	 * @param string $msg
	 * @return bool
	 */
	private function _audit($msg){
			$date = date('Y/m/d H:i:s');
			if( ! file_put_contents($this->auditlog, $date.": ".$msg."\n",FILE_APPEND)) {
					log_message('info', 'Error opening audit log '.$this->auditlog);
					return FALSE;
			}
			return TRUE;
	}

	/**
	 * @access protected
	 * @param string $url
	 * @param overload bool $secure
	 * @return string
	 */ 
 	protected function _build_url($url, $secure=TRUE)
	{
		$final_url = '';
		$final_url  = ($secure === TRUE) ? 'https://' : 'http://';
		$final_url .= $this->api_url;
		$final_url .= $url . '?key=' . $this->app_key;

		return $final_url;
	}
}