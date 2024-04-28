<?php

/*print "<pre>";

print "Raw POST Parameters:\n\n";
ksort( $_POST );
$_POST['oauth_consumer_key'] = trim($_POST['oauth_consumer_key']);
foreach( $_POST as $key => $value ) {
  print "$key='$value'\n";
}
*/

require_once 'core.php';
if( VerifyLTISession2( ) && ! empty( $_POST['lis_person_contact_email_primary'] ) ) {
  require_once 'users.php';
  $instID = 2; // *GU specific*
  $email = $_POST['lis_person_contact_email_primary'];
  $userID = null;

  $p0 = stripos( $email, '@student.gla.ac.uk' ); // *GU specific*
  if( $p0 !== false ) {
    $guid = trim( substr( $email, 0, $p0 ) );
    if( ! empty( $guid ) )
      $userID = uidentToUserID( $guid, $instID );
  } else {
    $userID = fetchOne( 'SELECT userID FROM EmailAlias WHERE email=' . quote_smart( $email ), 'userID' );
    if( ! $userID && function_exists( 'ldap_connect' ) ) {
      $con = ldap_connect( 'ldap://130.209.13.173' ); // *GU specific*
      if( $con ) {
	$sr = ldap_search( $con, 'o=Gla', "mail=$email", array('uid') ); // *GU specific*
	if( $sr ) {
	  if( ldap_count_entries( $con, $sr ) == 1 ) {
	    $rec = ldap_get_entries( $con, $sr );
	    $guid = $rec[0]['uid'][0]; // *GU specific*
	    if( ! empty( $guid ) ) {
	      $userID = uidentToUserID( $guid, $instID );
	      if( $userID )
		checked_mysql_query( 'INSERT IGNORE INTO EmailAlias (email,userID) VALUES (' . quote_smart($email) . ",$userID)" );
	    }
	  }
	  ldap_free_result( $sr );
	}
      }
    }
  }

  if( $userID ) {
    require_once 'login.php';
    $acct = fetchOne( 'SELECT u.instID, features, u.userID, status, uident, username, prefs FROM User u'
		      . " LEFT JOIN Institution i ON u.instID=i.instID WHERE userID=$userID" );
    if( $acct ) {
      parse_str( $acct['features'] ?? '', $features );
      if( defined('USE_DATABASE_SESSION') && USE_DATABASE_SESSION )
	require_once 'session.php';
      session_start( );
      loginUser( $acct['instID'], $acct['userID'], $acct['status'], $acct['uident'], $acct['username'], $acct['prefs'], $features['TIMEZONE']);
      redirect('home');
    }
  }
  //  print "Verified, but unable to login using $email\n";
} else
  //  print "Not verified, email=&lt;$_POST[lis_person_contact_email_primary]&gt;\n";
  ;
redirect('login&at=GLA');

//print "</pre>";

/*
Raw POST Parameters:

context_id=925
context_label=LTI2
context_title=Aropä LTI Test course
ext_ims_lis_memberships_id=080bc7a1c17a49c7170010edb88aac7877467884ffedd42d311c72b22fb79911:::26237:::10
ext_ims_lis_memberships_url=http://services.moodle.gla.ac.uk/mod/basiclti/service.php
ext_lms=moodle-1
ext_submit=Press to launch this activity
launch_presentation_locale=en_utf8
lis_person_contact_email_primary=John.Hamer@glasgow.ac.uk
lti_message_type=basic-lti-launch-request
lti_version=LTI-1p0
oauth_callback=about:blank
oauth_consumer_key=7989d14fb744cf36c302e9572ee9aed3 
oauth_nonce=eec75443d7e04aa4d38f5b2427a9b14a
oauth_signature=Ol/GOzWW7hbtrP7JnAEhV9HJkpg=
oauth_signature_method=HMAC-SHA1
oauth_timestamp=1345115699
oauth_version=1.0
resource_link_description=For testing purposes only
resource_link_id=10
resource_link_title=Link to Aropä
roles=Learner
user_id=26237
 */

function getSecret( $consumer_key ) {
  return $consumer_key == '7989d14fb744cf36c302e9572ee9aed3' ? 'Nzk4OWQx' : '???';
}

function VerifyLTISession( ) {
  if( ! is_array( $_POST )
      || ! is_string( $_POST['oauth_nonce'] )
      || ! is_string( $_POST['oauth_consumer_key'] )
      || ! isset( $_POST['oauth_signature'] )
      || $_POST['oauth_signature_method'] != 'HMAC-SHA1'
      || ! registerNonce( $_POST['oauth_nonce'], $_POST['oauth_consumer_key'] )
      || ! isset( $_POST['oauth_timestamp'] )
      || $_POST['oauth_timestamp'] < time( )-3600
      )
    return false;

  //getOAuthSignatureNB( $_POST, getRequestURL( ), $_SERVER['REQUEST_METHOD'], getSecret( $_POST['oauth_consumer_key']) );

  $server = new OAuthServer($store);
  $method = new OAuthSignatureMethod_HMAC_SHA1();
        $server->add_signature_method($method);
  
  return $_POST['oauth_signature'] == getOAuthSignature( $_POST,
							 getRequestURL( ),
							 $_SERVER['REQUEST_METHOD'],
							 getSecret( $_POST['oauth_consumer_key']) );
}


function isLTISessionRequest( ) {
  return is_array( $_POST )
    && is_string( $_POST['oauth_nonce'] )
    && is_string( $_POST['oauth_consumer_key'] )
    && isset( $_POST['oauth_signature'] )
    && $_POST['oauth_signature_method'] == 'HMAC-SHA1'
    && registerNonce( $_POST['oauth_nonce'], $_POST['oauth_consumer_key'] )
    && isset( $_POST['oauth_timestamp'] )
    && $_POST['oauth_timestamp'] > time( )-3600
    && ! empty( $_POST['user_id'] )
    ;

  return $_POST['oauth_signature'] == getOAuthSignature( $_POST,
							 getRequestURL( ),
							 $_SERVER['REQUEST_METHOD'],
							 getSecret( $_POST['oauth_consumer_key']) );
}

function VerifyLTISession2( ) {
  $store = new TrivialOAuthDataStore( );
  $store->add_consumer('7989d14fb744cf36c302e9572ee9aed3', 'Nzk4OWQx');
  
  $server = new OAuthServer($store);
  $method = new OAuthSignatureMethod_HMAC_SHA1();
  $server->add_signature_method($method);
  $request = OAuthRequest::from_request();
  $basestring = $request->get_signature_base_string();
  try {
    $server->verify_request($request);
    return true;
  } catch( Exception $e ) {
    return false;
  }
}

function getRequestURL( ) {
  if( $_SERVER['HTTPS'] == 'on') {
    $pageURL = 'https';
    $port = '443';
  } else {
    $pageURL = 'http';
    $port = '80';
  }

  if( $_SERVER['SERVER_PORT'] != $port )
    $url = "$pageURL://" . strtolower( $_SERVER['SERVER_NAME'] ) . ":$_SERVER[SERVER_PORT]$_SERVER[REQUEST_URI]";
  else
    $url = "$pageURL://" . strtolower( $_SERVER['SERVER_NAME'] ) . "$_SERVER[REQUEST_URI]";
  return $url;
}



function getOAuthSignature( $params, $endpoint, $method, $oauth_consumer_secret ) {
  if( strpos( $endpoint, '?' ) !== false ) {
    // Add any GET params to the OAuth parameters for signing.
    list( $endpoint, $getparams ) = explode( '?', $endpoint, 2 );
    $getparams = explode( '&', $getparams );
    foreach( $getparams as $p ) {
      list( $k, $v ) = explode( '=', $p, 2 );
      $params[$k] = $v; //rawurldecode( $v ) );
    }
  }
  unset( $params['oauth_signature'] );
  ksort( $params );

  $parts = OAuthUtil::urlencode_rfc3986( array( strtoupper($method), $endpoint, $params ) );


  $args = array(  );
  foreach( $params as $k => $v )
      $args[] = rfc3986encode( $k ) . '=' . rfc3986encode( $v );
  $basestring =  strtoupper($method). '&' . rfc3986encode($endpoint) . '&' . rfc3986encode( join( '&', $args ) );
  $signingkey = implode('&', OAuthUtil::urlencode_rfc3986( array($oauth_consumer_secret, "") ));
  $sig = base64_encode( hash_hmac('sha1', $basestring, $signingkey, true) );

  //  print htmlentities( "OAuth:\n\turl=$endpoint\n\tbasestring=$basestring\n\tsigningkey=$signingkey\n\tsig=$sig\n\texpected=$_POST[oauth_signature]\n" );

  getOAuthSignature2($params, $endpoint, $method, $oauth_consumer_secret);

  return $sig;
}


function getOAuthSignature2($params, $endpoint, $method, $oauth_consumer_secret) {
  $basestring = $method.'&';
  //IMS code uses str_replace('+',' ',str_replace('%7E', '~', rawurlencode($input))); for RFC 3986
  if(strpos($endpoint,'?')) {
    // get params have to be put into the OAuth parameters rather than the URL for sdigning.
    list($endpoint, $getparams) = explode('?', $endpoint,2);
    $getparams = explode('&',$getparams);
    foreach($getparams as $p) {
      list($k,$v) = explode('=',$p,2);
      $params[$k] = rawurldecode($v);
    }
  }
  $basestring .= rfc3986encode($endpoint).'&'; // PHP manual says rawurlencode is RFC 3986, need to check.
  ksort($params);
  foreach($params as $k=>$v) {
    $basestring .= rfc3986encode($k.'='.rfc3986encode($v).'&');
  }
  // Strip away last encoded '&';
  $basestring = substr($basestring, 0, strlen($basestring)-3);
  //echo "\n<p>\n$basestring\n</p>\n";
  $signingkey = rfc3986encode($oauth_consumer_secret).'&';
  $computed_signature = base64_encode(hash_hmac('sha1', 
						$basestring, $signingkey, true));
  //  print htmlentities( "Niall: basestring=$basestring\nOAuth=$computed_signature=\n" );

  return $computed_signature;
}

// function testOAuthSig( ) {
//   $params = array('oauth_consumer_key'=> 'dpf43f3p2l4k3l03',
// 		  'oauth_token' => 'nnch734d00sl2jdk',
// 		  'oauth_nonce' => 'kllo9940pd9333jh',
// 		  'oauth_timestamp' => '1191242096',
// 		  'oauth_signature_method' => 'HMAC-SHA1',
// 		  'oauth_version' => '1.0',
// 		  'size' => 'original',
// 		  'file' => 'vacation.jpg');
//   print 'tR3+Ty81lMeYAr/Fid0kMTYa/WM= =? ' . getOAuthSignature($params, 'http://photos.example.net/photos', 'GET', 'kd94hf93k423kf44&pfkkdhi9sl3r4s00') . "\n";
// }
 

function retrieveRoster( $params ) {
  if( ! isset( $params['ext_ims_lis_memberships_url'] ) || ! isset( $params['ext_ims_lis_memberships_id'] ) )
    return array( );

  $data = array( 'oauth_version'          => '1.0',
		 'oauth_nonce'            => md5( time( ) . $params['user_id'] ),
		 'oauth_timestamp'        => time( ),
		 'lti_version'            => 'LTI-1p0',
		 'oauth_callback'         => 'about:blank',
		 'oauth_signature_method' => 'HMAC-SHA1',
		 'lti_message_type'       => 'basic-lis-readmembershipsforcontext',
		 'id'                     => $params['ext_ims_lis_memberships_id'],
		 'oauth_consumer_key'     => $params['oauth_consumer_key'],
		 );
    
  $url = $params['ext_ims_lis_memberships_url'];
  $data['oauth_signature'] = getOAuthSignature($data, $url, 'POST', getSecret( $params['oauth_consumer_key'] ));
  $retval = SendPostRequest( $url, http_build_query($data) );
  $response = new SimpleXmlElement( $retval );
  return $response; //->xpath('//memberships');
}


function SendPostRequest( $url, $data ) {
  $fp = @fopen( $url, 'rb', false,
                stream_context_create(array('http' => array( 'method' => 'POST',
                                                             'content' => $data
                                                             ))));
  if( ! $fp )
    throw new Exception("Problem with $url, $php_errormsg");
  $response = @stream_get_contents( $fp );
  fclose( $fp );
  if( ! $response )
    throw new Exception("Problem reading data from $url, $php_errormsg");
  return $response;
}


function rfc3986encode( $input ) {
  // Characters in the unreserved character set as defined by
  // [RFC3986], Section 2.3 (ALPHA, DIGIT, "-", ".", "_", "~") MUST
  // NOT be encoded.
  // rawurlencode "Returns a string in which all non-alphanumeric
  // characters except -_.~ have been replaced with a percent (%) sign
  // followed by two hex digits."
  // Prior to PHP 5.3.4, rawurlencode encoded ~ as %7E
  return str_replace('%7E', '~', rawurlencode($input));
}


function registerNonce( $nonce, $consumer_key ) {
  // Need a SQL table to store these, plus some garbage collection.
  return true;
}


//- From OAuth.php, moodle2 source code
$OAuth_last_computed_siguature = false;

/* Generic exception class
 */
class OAuthException extends Exception {
  // pass
}

class OAuthConsumer {
  public $key;
  public $secret;

  function __construct($key, $secret, $callback_url=NULL) {
    $this->key = $key;
    $this->secret = $secret;
    $this->callback_url = $callback_url;
  }

  function __toString() {
    return "OAuthConsumer[key=$this->key,secret=$this->secret]";
  }
}

class OAuthToken {
  // access tokens and request tokens
  public $key;
  public $secret;

  /**
   * key = the token
   * secret = the token secret
   */
  function __construct($key, $secret) {
    $this->key = $key;
    $this->secret = $secret;
  }

  /**
   * generates the basic string serialization of a token that a server
   * would respond to request_token and access_token calls with
   */
  function to_string() {
    return "oauth_token=" .
           OAuthUtil::urlencode_rfc3986($this->key) .
           "&oauth_token_secret=" .
           OAuthUtil::urlencode_rfc3986($this->secret);
  }

  function __toString() {
    return $this->to_string();
  }
}

class OAuthSignatureMethod {
  public function check_signature(&$request, $consumer, $token, $signature) {
    $built = $this->build_signature($request, $consumer, $token);
    return $built == $signature;
  }
}

class OAuthSignatureMethod_HMAC_SHA1 extends OAuthSignatureMethod {
  function get_name() {
    return "HMAC-SHA1";
  }

  public function build_signature($request, $consumer, $token) {
    global $OAuth_last_computed_signature;
    $OAuth_last_computed_signature = false;

    $base_string = $request->get_signature_base_string();
    $request->base_string = $base_string;

    $key_parts = array(
      $consumer->secret,
      ($token) ? $token->secret : ""
    );

    $key_parts = OAuthUtil::urlencode_rfc3986($key_parts);
    $key = implode('&', $key_parts);

    $computed_signature = base64_encode(hash_hmac('sha1', $base_string, $key, true));
    $OAuth_last_computed_signature = $computed_signature;
    return $computed_signature;
  }

}

class OAuthSignatureMethod_PLAINTEXT extends OAuthSignatureMethod {
  public function get_name() {
    return "PLAINTEXT";
  }

  public function build_signature($request, $consumer, $token) {
    $sig = array(
      OAuthUtil::urlencode_rfc3986($consumer->secret)
    );

    if ($token) {
      array_push($sig, OAuthUtil::urlencode_rfc3986($token->secret));
    } else {
      array_push($sig, '');
    }

    $raw = implode("&", $sig);
    // for debug purposes
    $request->base_string = $raw;

    return OAuthUtil::urlencode_rfc3986($raw);
  }
}

class OAuthSignatureMethod_RSA_SHA1 extends OAuthSignatureMethod {
  public function get_name() {
    return "RSA-SHA1";
  }

  protected function fetch_public_cert(&$request) {
    // not implemented yet, ideas are:
    // (1) do a lookup in a table of trusted certs keyed off of consumer
    // (2) fetch via http using a url provided by the requester
    // (3) some sort of specific discovery code based on request
    //
    // either way should return a string representation of the certificate
    throw Exception("fetch_public_cert not implemented");
  }

  protected function fetch_private_cert(&$request) {
    // not implemented yet, ideas are:
    // (1) do a lookup in a table of trusted certs keyed off of consumer
    //
    // either way should return a string representation of the certificate
    throw Exception("fetch_private_cert not implemented");
  }

  public function build_signature(&$request, $consumer, $token) {
    $base_string = $request->get_signature_base_string();
    $request->base_string = $base_string;

    // Fetch the private key cert based on the request
    $cert = $this->fetch_private_cert($request);

    // Pull the private key ID from the certificate
    $privatekeyid = openssl_get_privatekey($cert);

    // Sign using the key
    $ok = openssl_sign($base_string, $signature, $privatekeyid);

    // Release the key resource
    openssl_free_key($privatekeyid);

    return base64_encode($signature);
  }

  public function check_signature(&$request, $consumer, $token, $signature) {
    $decoded_sig = base64_decode($signature);

    $base_string = $request->get_signature_base_string();

    // Fetch the public key cert based on the request
    $cert = $this->fetch_public_cert($request);

    // Pull the public key ID from the certificate
    $publickeyid = openssl_get_publickey($cert);

    // Check the computed signature against the one passed in the query
    $ok = openssl_verify($base_string, $decoded_sig, $publickeyid);

    // Release the key resource
    openssl_free_key($publickeyid);

    return $ok == 1;
  }
}

class OAuthRequest {
  private $parameters;
  private $http_method;
  private $http_url;
  // for debug purposes
  public $base_string;
  public static $version = '1.0';
  public static $POST_INPUT = 'php://input';

  function __construct($http_method, $http_url, $parameters=NULL) {
    @$parameters or $parameters = array();
    $this->parameters = $parameters;
    $this->http_method = $http_method;
    $this->http_url = $http_url;
  }


  /**
   * attempt to build up a request from what was passed to the server
   */
  public static function from_request($http_method=NULL, $http_url=NULL, $parameters=NULL) {
    $scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on")
              ? 'http'
              : 'https';
    $port = "";
    if ( $_SERVER['SERVER_PORT'] != "80" && $_SERVER['SERVER_PORT'] != "443" &&
        strpos(':', $_SERVER['HTTP_HOST']) < 0 ) {
      $port =  ':' . $_SERVER['SERVER_PORT'] ;
    }
    @$http_url or $http_url = $scheme .
                              '://' . $_SERVER['HTTP_HOST'] .
                              $port .
                              $_SERVER['REQUEST_URI'];
    @$http_method or $http_method = $_SERVER['REQUEST_METHOD'];

    // We weren't handed any parameters, so let's find the ones relevant to
    // this request.
    // If you run XML-RPC or similar you should use this to provide your own
    // parsed parameter-list
    if (!$parameters) {
      // Find request headers
      $request_headers = OAuthUtil::get_headers();

      // Parse the query-string to find GET parameters
      $parameters = OAuthUtil::parse_parameters($_SERVER['QUERY_STRING']);

      $ourpost = $_POST;
      $parameters = array_merge($parameters, $ourpost);

      // We have a Authorization-header with OAuth data. Parse the header
      // and add those overriding any duplicates from GET or POST
      if (@substr($request_headers['Authorization'], 0, 6) == "OAuth ") {
        $header_parameters = OAuthUtil::split_header(
          $request_headers['Authorization']
        );
        $parameters = array_merge($parameters, $header_parameters);
      }

    }

    return new OAuthRequest($http_method, $http_url, $parameters);
  }

  /**
   * pretty much a helper function to set up the request
   */
  public static function from_consumer_and_token($consumer, $token, $http_method, $http_url, $parameters=NULL) {
    @$parameters or $parameters = array();
    $defaults = array("oauth_version" => OAuthRequest::$version,
                      "oauth_nonce" => OAuthRequest::generate_nonce(),
                      "oauth_timestamp" => OAuthRequest::generate_timestamp(),
                      "oauth_consumer_key" => $consumer->key);
    if ($token)
      $defaults['oauth_token'] = $token->key;

    $parameters = array_merge($defaults, $parameters);

    // Parse the query-string to find and add GET parameters
    $parts = parse_url($http_url);
    if ( $parts['query'] ) {
      $qparms = OAuthUtil::parse_parameters($parts['query']);
      $parameters = array_merge($qparms, $parameters);
    }
     

    return new OAuthRequest($http_method, $http_url, $parameters);
  }

  public function set_parameter($name, $value, $allow_duplicates = true) {
    if ($allow_duplicates && isset($this->parameters[$name])) {
      // We have already added parameter(s) with this name, so add to the list
      if (is_scalar($this->parameters[$name])) {
        // This is the first duplicate, so transform scalar (string)
        // into an array so we can add the duplicates
        $this->parameters[$name] = array($this->parameters[$name]);
      }

      $this->parameters[$name][] = $value;
    } else {
      $this->parameters[$name] = $value;
    }
  }

  public function get_parameter($name) {
    return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
  }

  public function get_parameters() {
    return $this->parameters;
  }

  public function unset_parameter($name) {
    unset($this->parameters[$name]);
  }

  /**
   * The request parameters, sorted and concatenated into a normalized string.
   * @return string
   */
  public function get_signable_parameters() {
    // Grab all parameters
    $params = $this->parameters;

    // Remove oauth_signature if present
    // Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.")
    if (isset($params['oauth_signature'])) {
      unset($params['oauth_signature']);
    }

    return OAuthUtil::build_http_query($params);
  }

  /**
   * Returns the base string of this request
   *
   * The base string defined as the method, the url
   * and the parameters (normalized), each urlencoded
   * and the concated with &.
   */
  public function get_signature_base_string() {
    $parts = array(
      $this->get_normalized_http_method(),
      $this->get_normalized_http_url(),
      $this->get_signable_parameters()
    );

    $parts = OAuthUtil::urlencode_rfc3986($parts);

    return implode('&', $parts);
  }

  /**
   * just uppercases the http method
   */
  public function get_normalized_http_method() {
    return strtoupper($this->http_method);
  }

  /**
   * parses the url and rebuilds it to be
   * scheme://host/path
   */
  public function get_normalized_http_url() {
    $parts = parse_url($this->http_url);

    $port = @$parts['port'];
    $scheme = $parts['scheme'];
    $host = $parts['host'];
    $path = @$parts['path'];

    $port or $port = ($scheme == 'https') ? '443' : '80';

    if (($scheme == 'https' && $port != '443')
        || ($scheme == 'http' && $port != '80')) {
      $host = "$host:$port";
    }
    return "$scheme://$host$path";
  }

  /**
   * builds a url usable for a GET request
   */
  public function to_url() {
    $post_data = $this->to_postdata();
    $out = $this->get_normalized_http_url();
    if ($post_data) {
      $out .= '?'.$post_data;
    }
    return $out;
  }

  /**
   * builds the data one would send in a POST request
   */
  public function to_postdata() {
    return OAuthUtil::build_http_query($this->parameters);
  }

  /**
   * builds the Authorization: header
   */
  public function to_header() {
    $out ='Authorization: OAuth realm=""';
    $total = array();
    foreach ($this->parameters as $k => $v) {
      if (substr($k, 0, 5) != "oauth") continue;
      if (is_array($v)) {
        throw new OAuthException('Arrays not supported in headers');
      }
      $out .= ',' .
              OAuthUtil::urlencode_rfc3986($k) .
              '="' .
              OAuthUtil::urlencode_rfc3986($v) .
              '"';
    }
    return $out;
  }

  public function __toString() {
    return $this->to_url();
  }


  public function sign_request($signature_method, $consumer, $token) {
    $this->set_parameter(
      "oauth_signature_method",
      $signature_method->get_name(),
      false
    );
    $signature = $this->build_signature($signature_method, $consumer, $token);
    $this->set_parameter("oauth_signature", $signature, false);
  }

  public function build_signature($signature_method, $consumer, $token) {
    $signature = $signature_method->build_signature($this, $consumer, $token);
    return $signature;
  }

  /**
   * util function: current timestamp
   */
  private static function generate_timestamp() {
    return time();
  }

  /**
   * util function: current nonce
   */
  private static function generate_nonce() {
    $mt = microtime();
    $rand = mt_rand();

    return md5($mt . $rand); // md5s look nicer than numbers
  }
}

class OAuthServer {
  protected $timestamp_threshold = 300; // in seconds, five minutes
  protected $version = 1.0;             // hi blaine
  protected $signature_methods = array();

  protected $data_store;

  function __construct($data_store) {
    $this->data_store = $data_store;
  }

  public function add_signature_method($signature_method) {
    $this->signature_methods[$signature_method->get_name()] =
      $signature_method;
  }

  // high level functions

  /**
   * process a request_token request
   * returns the request token on success
   */
  public function fetch_request_token(&$request) {
    $this->get_version($request);

    $consumer = $this->get_consumer($request);

    // no token required for the initial token request
    $token = NULL;

    $this->check_signature($request, $consumer, $token);

    $new_token = $this->data_store->new_request_token($consumer);

    return $new_token;
  }

  /**
   * process an access_token request
   * returns the access token on success
   */
  public function fetch_access_token(&$request) {
    $this->get_version($request);

    $consumer = $this->get_consumer($request);

    // requires authorized request token
    $token = $this->get_token($request, $consumer, "request");


    $this->check_signature($request, $consumer, $token);

    $new_token = $this->data_store->new_access_token($token, $consumer);

    return $new_token;
  }

  /**
   * verify an api call, checks all the parameters
   */
  public function verify_request(&$request) {
    global $OAuth_last_computed_signature;
    $OAuth_last_computed_signature = false;
    $this->get_version($request);
    $consumer = $this->get_consumer($request);
    $token = $this->get_token($request, $consumer, "access");
    $this->check_signature($request, $consumer, $token);
    return array($consumer, $token);
  }

  // Internals from here
  /**
   * version 1
   */
  private function get_version(&$request) {
    $version = $request->get_parameter("oauth_version");
    if (!$version) {
      $version = 1.0;
    }
    if ($version && $version != $this->version) {
      throw new OAuthException("OAuth version '$version' not supported");
    }
    return $version;
  }

  /**
   * figure out the signature with some defaults
   */
  private function get_signature_method(&$request) {
    $signature_method =
        @$request->get_parameter("oauth_signature_method");
    if (!$signature_method) {
      $signature_method = "PLAINTEXT";
    }
    if (!in_array($signature_method,
                  array_keys($this->signature_methods))) {
      throw new OAuthException(
        "Signature method '$signature_method' not supported " .
        "try one of the following: " .
        implode(", ", array_keys($this->signature_methods))
      );
    }
    return $this->signature_methods[$signature_method];
  }

  /**
   * try to find the consumer for the provided request's consumer key
   */
  private function get_consumer(&$request) {
    $consumer_key = @$request->get_parameter("oauth_consumer_key");
    if (!$consumer_key) {
      throw new OAuthException("Invalid consumer key");
    }

    $consumer = $this->data_store->lookup_consumer($consumer_key);
    if (!$consumer) {
      throw new OAuthException("Invalid consumer");
    }

    return $consumer;
  }

  /**
   * try to find the token for the provided request's token key
   */
  private function get_token(&$request, $consumer, $token_type="access") {
    $token_field = @$request->get_parameter('oauth_token');
    if ( !$token_field) return false;
    $token = $this->data_store->lookup_token(
      $consumer, $token_type, $token_field
    );
    if (!$token) {
      throw new OAuthException("Invalid $token_type token: $token_field");
    }
    return $token;
  }

  /**
   * all-in-one function to check the signature on a request
   * should guess the signature method appropriately
   */
  private function check_signature(&$request, $consumer, $token) {
    // this should probably be in a different method
    global $OAuth_last_computed_signature;
    $OAuth_last_computed_signature = false;

    $timestamp = @$request->get_parameter('oauth_timestamp');
    $nonce = @$request->get_parameter('oauth_nonce');

    $this->check_timestamp($timestamp);
    $this->check_nonce($consumer, $token, $nonce, $timestamp);

    $signature_method = $this->get_signature_method($request);

    $signature = $request->get_parameter('oauth_signature');
    $valid_sig = $signature_method->check_signature(
      $request,
      $consumer,
      $token,
      $signature
    );
    
    if (!$valid_sig) {
      $ex_text = "Invalid signature";
      if ( $OAuth_last_computed_signature ) {
          $ex_text = $ex_text . " ours= $OAuth_last_computed_signature yours=$signature";
      }
      throw new OAuthException($ex_text);
    }
  }

  /**
   * check that the timestamp is new enough
   */
  private function check_timestamp($timestamp) {
    // verify that timestamp is recentish
    $now = time();
    if ($now - $timestamp > $this->timestamp_threshold) {
      throw new OAuthException(
        "Expired timestamp, yours $timestamp, ours $now"
      );
    }
  }

  /**
   * check that the nonce is not repeated
   */
  private function check_nonce($consumer, $token, $nonce, $timestamp) {
    // verify that the nonce is uniqueish
    $found = $this->data_store->lookup_nonce(
      $consumer,
      $token,
      $nonce,
      $timestamp
    );
    if ($found) {
      throw new OAuthException("Nonce already used: $nonce");
    }
  }

}

class OAuthDataStore {
  function lookup_consumer($consumer_key) {
    // implement me
  }

  function lookup_token($consumer, $token_type, $token) {
    // implement me
  }

  function lookup_nonce($consumer, $token, $nonce, $timestamp) {
    // implement me
  }

  function new_request_token($consumer) {
    // return a new token attached to this consumer
  }

  function new_access_token($token, $consumer) {
    // return a new access token attached to this consumer
    // for the user associated with this token if the request token
    // is authorized
    // should also invalidate the request token
  }

}

class OAuthUtil {
  public static function urlencode_rfc3986($input) {
  if (is_array($input)) {
    return array_map(array('OAuthUtil', 'urlencode_rfc3986'), $input);
  } else if (is_scalar($input)) {
    return str_replace(
      '+',
      ' ',
      str_replace('%7E', '~', rawurlencode($input))
    );
  } else {
    return '';
  }
}


  // This decode function isn't taking into consideration the above
  // modifications to the encoding process. However, this method doesn't
  // seem to be used anywhere so leaving it as is.
  public static function urldecode_rfc3986($string) {
    return urldecode($string);
  }

  // Utility function for turning the Authorization: header into
  // parameters, has to do some unescaping
  // Can filter out any non-oauth parameters if needed (default behaviour)
  public static function split_header($header, $only_allow_oauth_parameters = true) {
    $pattern = '/(([-_a-z]*)=("([^"]*)"|([^,]*)),?)/';
    $offset = 0;
    $params = array();
    while (preg_match($pattern, $header, $matches, PREG_OFFSET_CAPTURE, $offset) > 0) {
      $match = $matches[0];
      $header_name = $matches[2][0];
      $header_content = (isset($matches[5])) ? $matches[5][0] : $matches[4][0];
      if (preg_match('/^oauth_/', $header_name) || !$only_allow_oauth_parameters) {
        $params[$header_name] = OAuthUtil::urldecode_rfc3986($header_content);
      }
      $offset = $match[1] + strlen($match[0]);
    }

    if (isset($params['realm'])) {
      unset($params['realm']);
    }

    return $params;
  }

  // helper to try to sort out headers for people who aren't running apache
  public static function get_headers() {
    if (function_exists('apache_request_headers')) {
      // we need this to get the actual Authorization: header
      // because apache tends to tell us it doesn't exist
      return apache_request_headers();
    }
    // otherwise we don't have apache and are just going to have to hope
    // that $_SERVER actually contains what we need
    $out = array();
    foreach ($_SERVER as $key => $value) {
      if (substr($key, 0, 5) == "HTTP_") {
        // this is chaos, basically it is just there to capitalize the first
        // letter of every word that is not an initial HTTP and strip HTTP
        // code from przemek
        $key = str_replace(
          " ",
          "-",
          ucwords(strtolower(str_replace("_", " ", substr($key, 5))))
        );
        $out[$key] = $value;
      }
    }
    return $out;
  }

  // This function takes a input like a=b&a=c&d=e and returns the parsed
  // parameters like this
  // array('a' => array('b','c'), 'd' => 'e')
  public static function parse_parameters( $input ) {
    if (!isset($input) || !$input) return array();

    $pairs = explode('&', $input);

    $parsed_parameters = array();
    foreach ($pairs as $pair) {
      $split = explode('=', $pair, 2);
      $parameter = OAuthUtil::urldecode_rfc3986($split[0]);
      $value = isset($split[1]) ? OAuthUtil::urldecode_rfc3986($split[1]) : '';

      if (isset($parsed_parameters[$parameter])) {
        // We have already recieved parameter(s) with this name, so add to the list
        // of parameters with this name

        if (is_scalar($parsed_parameters[$parameter])) {
          // This is the first duplicate, so transform scalar (string) into an array
          // so we can add the duplicates
          $parsed_parameters[$parameter] = array($parsed_parameters[$parameter]);
        }

        $parsed_parameters[$parameter][] = $value;
      } else {
        $parsed_parameters[$parameter] = $value;
      }
    }
    return $parsed_parameters;
  }

  public static function build_http_query($params) {
    if (!$params) return '';

    // Urlencode both keys and values
    $keys = OAuthUtil::urlencode_rfc3986(array_keys($params));
    $values = OAuthUtil::urlencode_rfc3986(array_values($params));
    $params = array_combine($keys, $values);

    // Parameters are sorted by name, using lexicographical byte value ordering.
    // Ref: Spec: 9.1.1 (1)
    uksort($params, 'strcmp');

    $pairs = array();
    foreach ($params as $parameter => $value) {
      if (is_array($value)) {
        // If two or more parameters share the same name, they are sorted by their value
        // Ref: Spec: 9.1.1 (1)
        natsort($value);
        foreach ($value as $duplicate_value) {
          $pairs[] = $parameter . '=' . $duplicate_value;
        }
      } else {
        $pairs[] = $parameter . '=' . $value;
      }
    }
    // For each parameter, the name is separated from the corresponding value by an '=' character (ASCII code 61)
    // Each name-value pair is separated by an '&' character (ASCII code 38)
    return implode('&', $pairs);
  }
}


//namespace moodle\local\ltiprovider;

/**
 * A Trivial memory-based store - no support for tokens
 */
class TrivialOAuthDataStore extends OAuthDataStore {
    private $consumers = array();

    function add_consumer($consumer_key, $consumer_secret) {
        $this->consumers[$consumer_key] = $consumer_secret;
    }

    function lookup_consumer($consumer_key) {
        if ( strpos($consumer_key, "http://" ) === 0 ) {
            $consumer = new OAuthConsumer($consumer_key,"secret", NULL);
            return $consumer;
        }
        if ( $this->consumers[$consumer_key] ) {
            $consumer = new OAuthConsumer($consumer_key,$this->consumers[$consumer_key], NULL);
            return $consumer;
        }
        return NULL;
    }

    function lookup_token($consumer, $token_type, $token) {
        return new OAuthToken($consumer, "");
    }

    // Return NULL if the nonce has not been used
    // Return $nonce if the nonce was previously used
    function lookup_nonce($consumer, $token, $nonce, $timestamp) {
        // Should add some clever logic to keep nonces from
        // being reused - for no we are really trusting
    // that the timestamp will save us
        return NULL;
    }

    function new_request_token($consumer) {
        return NULL;
    }

    function new_access_token($token, $consumer) {
        return NULL;
    }
}
