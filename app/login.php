<?php
/*
    Copyright (C) 2016 John Hamer <jham005@gmail.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

function login( ) {
  require_once 'LTI2.php';

  if( isLTISessionRequest( ) ) {
    Debug($_POST);
    require_once 'users.php';
    if (isset($_POST['custom_at']))
      $andAt = " and instID = " . quote_smart((int)$_POST['custom_at']);
    else if (isset($_REQUEST['at']))
      $andAt = " and instID = " . quote_smart((int)$_REQUEST['at']);
    else 
      $andAt = '';

    foreach (fetchAll("SELECT instID, features FROM Institution WHERE isActive $andAt" ) as $inst ) {
      parse_str( $inst['features'] ?? '', $features );
      if( isset( $features['LTI_CONSUMER_KEY'] )
	  && $features['LTI_CONSUMER_KEY'] == $_POST['oauth_consumer_key']
	  && $_POST['oauth_signature'] == getOAuthSignature( $_POST,
							     getRequestURL( ),
							     $_SERVER['REQUEST_METHOD'],
							     $features['LTI_SECRET'] ) ) {

	$toolState = toolState();
	if (isset($_POST['tool_state'])) {
	  if ($_POST['tool_state'] != $toolState) {
	    Debug("Tool state does not match: expected=$toolState, POST=$_POST[tool_state]");
	    break;
	  } else
	    Debug("Tool state matches; looking for user identity");
	} else if (isset($_POST['relaunch_url'])) {
	  $redirect = $_POST['relaunch_url'] . "?platform_state=$_POST[platform_state]&tool_state=$toolState";
	  Debug("This is a LTI 1.1.2 anonymous initial launch; redirecting to $redirect");
	  redirect($redirect);
	} else
	  Debug("This is a plain LTI 1.1 launch");
	
	$instID = (int)$inst['instID'];
	// Best case: LTI gives us an institutional identifier in the ext_user_username field
        $userIdField = $features['LTI_USER_ID_FIELD'];
        if (empty($userIdField))
          $userIdField = 'ext_user_username';
        $uident = $_POST[$userIdField];
	Debug("Looking for username using $userIdField=$uident");

	if ($uident)
	  $acct = fetchOne(
	    'select u.instID, userID, uident, status, username, features, prefs'
	    . ' from User u inner join Institution i on u.instID = i.instID where uident = ' . quote_smart($uident)
	    . " and u.instID = $instID" );
	if (!$acct && !empty($_POST['lis_person_contact_email_primary'])) {
	  // Try harder. LTI gives us a few other fields to work out the user's identity from.
	  $email = $_POST['lis_person_contact_email_primary'];
	  Debug("Looking for user name using lis_person_contact_email_primary=$email");
	  $userID = fetchOne(
	    'select e.userID from EmailAlias e'
	    . ' inner join User u on e.userID = u.userID'
	    . " where instID = $instID and e.email = " . quote_smart($email),
	    'userID');
	  
	  // Look to see if the email name has a known uident before the @
	  if( ! $userID ) {
	    $p0 = strpos( $email, '@' );
	    if( $p0 !== false ) {
	      $uident = trim( substr( $email, 0, $p0 ) );
	      if( ! empty( $uident ) )
		$userID = fetchOne( "SELECT userID FROM User WHERE instID=$instID AND uident=" . quote_smart( $uident ), 'userID' );
	    }
	  }
	  
	  if( ! $userID && $instID == 2 && function_exists( 'ldap_connect' ) ) {// *GU specific*
	    $con = @ldap_connect( $features['LDAP_SERVER'] );
	    if( $con ) {
	      $sr = @ldap_search( $con, 'o=Gla', "mail=$email", array('uid') ); // *GU specific*
	      if( $sr ) {
		if( @ldap_count_entries( $con, $sr ) == 1 ) {
		  $rec = @ldap_get_entries( $con, $sr );
		  $guid = $rec[0]['uid'][0]; // *GU specific*
		  if( ! empty( $guid ) ) {
		    $userID = uidentToUserID( $guid, $instID );
		    if( $userID )
		      checked_mysql_query( 'INSERT IGNORE INTO EmailAlias (email,userID) VALUES (' . quote_smart($email) . ",$userID)" );
		  }
		}
		@ldap_free_result( $sr );
	      }
	    }
	  }
	  
	  if( $userID )
	    $acct = fetchOne( 'SELECT u.instID, features, u.userID, status, uident, username, prefs FROM User u'
			      . " LEFT JOIN Institution i ON u.instID=i.instID WHERE userID=$userID" );
	}
	  
	if( $acct ) {
	  Debug("Success; logging in user $acct[userID]");
	  loginUser( $acct['instID'], $acct['userID'], $acct['status'], $acct['uident'], $acct['username'], $acct['prefs'], $features['TIMEZONE']);
	  redirect('home');
	} else
	  Debug("No user identity found");
      }
    }
  }
  
  if( defined( 'USE_FACEBOOK' ) && USE_FACEBOOK ) {
    require_once 'facebook.php';

    $facebook = new Facebook( array( 'appId' => '235123306511577',
				     'secret' => '77490f51cdd311009c3daa0b904b53ad',
				     'fileUpload' => false ));
    $fbuser = $facebook->getUser( );
    if( $fbuser != 0 ) {
      $acct = fetchOne( 'SELECT User.instID as instID, features, userID, status, uident, username, prefs'
			. ' FROM User LEFT JOIN Institution ON User.instID=Institution.instID'
			. ' WHERE fbUID = ' . quote_smart( $fbuser ) );
      if( $acct ) {
	parse_str( $row['features'] ?? '', $features );
	loginUser( $acct['instID'], $acct['userID'], $acct['status'], $acct['uident'], $acct['username'], $acct['prefs'], $features['TIMEZONE'] );
	redirect( 'home' );
      }
    }
  }

  if( isset( $_REQUEST['at'] ) ) {
    if( is_numeric( $_REQUEST['at'] ) )
      $where = ' WHERE i.instID = ' . (int)$_REQUEST['at'];
    else
      $where = ' WHERE shortname = ' . quote_smart( $_REQUEST['at'] );
  } else
    $where = ' WHERE i.isActive';

  $rs = checked_mysql_query( 'SELECT i.instID, longname, features, MAX(lastModified) AS m'
			     . ' FROM Institution i LEFT JOIN Course c ON i.instID=c.instID '
			     . ' LEFT JOIN Assignment a ON a.courseID=c.courseID'
			     . $where
			     . ' GROUP BY i.instID ORDER BY m DESC' );

  if( $rs->num_rows == 0 ) {
    if( isset( $_REQUEST['at'] ) )
      redirect( 'login' );
    else
      return warning( _('No institutions have been registered.  Please contact the system administrator') );
  } else if( $rs->num_rows == 1 ) {
    $row = $rs->fetch_assoc();
    loginInstitution( $row['instID'], $row['features'] );
  } else {
    extraHeader('chosen.jquery.min.js', 'script');
    extraHeader('chosen.css', 'css');
    extraHeader('$("#at").chosen({width: "100%"})', 'onload');
    $instList = HTML::div(array('class'=>'institutions col-lg-12 col-md-12 col-sm-12 col-xs-12'));
    $allInst = array();
    while( $row = $rs->fetch_assoc() ) {
      $allInst[$row['instID']] = $row['longname'];
      if (count($allInst) <= 12)
	$instList->pushContent(HTML::div(array('class'=>'institution col-lg-4 col-md-4 col-sm-4 col-xs-12'),
					 callback_url(HTML::div(array('class'=>'institution-wrapper'),
								HTML::img(array('src'=>"$_SERVER[PHP_SELF]?action=logo&instID=$row[instID]"))),
						      "login&at=$row[instID]")));
    }
  }
  
  asort($allInst);
  $select = HTML::select(array('class'=>'form-control',
			       'id'=>'at',
			       'name'=>'at',
			       'data-placeholder'=>_('Select your institution')));
  foreach ($allInst as $instID => $longname)
    $select->pushContent(HTML::option(array('value'=>$instID), $longname));

  $content = HTML(HeaderDiv(_('Select your institution')),
		  HTML::div(array('class'=>'container'),
			    HTML::div(array('class'=>'row'),
				      HTML::div(array('class'=>'main extra-padding col-lg-10 col-lg-offset-1 col-md-12 col-sm-12 col-xs-12'),
						HTML::div(array('class'=>'content'),
							  HTML::div(array('class'=>'col-lg-12 col-md-12 col-sm-12 col-xs-12'),
								    HTML::h4(_('Select your institution from the drop-down list or click on the logo below'))),
							  
							  HTML::form(array('class'=>'form-inline',
									   'method'=>'post',
									   'action'=>"$_SERVER[PHP_SELF]?action=login"),
								     HTML::div(array('class'=>'institution-select col-lg-6 col-md-6 col-sm-8 col-xs-9'),
									       $select),
								     HTML::div(array('class'=>'institution-btn col-lg-4 col-md-4 col-sm-4 col-xs-3'),
									       submitButton(_('Select')))),
							  $instList)))),
		  FooterDiv());
  printDocumentAndExit(_('Select institution'), $content);
}


function preflightCheck( ) {
  return JavaScript('document.cookie = "TEST=ok; path=/";
if( document.cookie.indexOf( "TEST=ok" ) < 0 ) {
  alert("Your browser does not appear to have cookies enabled.  Please enable cookies to login.");
} else {
  var date = new Date();
  date.setTime(date.getTime()-1);
  document.cookie = "TEST=ok; expires=" + date.toGMTString() + "; path=/";
}');
  //IfJavaScript( false, warning(_('Javascript is not enabled.'))));
}



function loginInstitution( $instID, $featureStr ) {
  parse_str( $featureStr ?? '', $features );

  if( isset( $features['NETACCOUNT'] ) ) {
    //- NetAccount may have already authenticated the user for us
    $ident = getenv('REMOTE_IDENT');
    $addr  = getenv('REMOTE_ADDR');
  
    $cmd = "./hoozit.cgi $addr '$ident'";
    $upi = strtolower( trim( exec( EscapeShellCmd( $cmd ) ) ) );
    
    if( ! empty( $upi ) && $upi != 'na' ) {
      if( checkPassword( $instID, $features, $upi, null, true ) )
	redirect( 'home' );
    }
  }

  if( isset( $features['TRUST_SERVER_AUTH'] ) &&
      isset( $_SERVER['REMOTE_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
    if( checkPassword( $instID, $features, $_SERVER['REMOTE_USER'], $_SERVER['PHP_AUTH_PW'], true ) )
      redirect( 'home' );
  }

  return loginForm( $instID );
}


function loginForm( $instID, $msg = '' ) {
  $systemMessages = HTML();
  foreach (glob('messages/*.txt') as $message) // */
    $systemMessages->pushContent(file_get_contents($message));

  $content = HTML(preflightCheck( ),
		  $systemMessages,
		  HTML::form(array('class'=>'form-signin',
				   'method'=>'post',
				   'id'=>'loginForm',
				   'action'=>$_SERVER['PHP_SELF']),
			     HiddenInputs( array('action'=>'authenticate',
						 'instID'=>$instID) ),
			     pendingMessages(),
			     HTML::h2(array('class'=>"form-signin-heading"),
				      fetchOne( "select longname from Institution where instID = $instID")),
			     HTML::label(array('for'=>'username', 'class'=>'sr-only'),
					 _('User name')),
			     HTML::input(array('type'=>'text',
					       'class'=>'form-control',
					       'name'=>'username',
					       'required'=>true,
					       'autofocus'=>true,
					       'placeholder'=>_('User name'))),
			     HTML::label(array('for'=>'password', 'class'=>'sr-only'), _('Password')),
			     HTML::input(array('type'=>'password',
					       'class'=>'form-control',
					       'required'=>true,
					       'autocomplete'=>'off',
					       'placeholder'=>_('Password'),
					       'name'=>'password')),
			     HTML::button(array('class'=>'btn btn-lg btn-primary btn-block',
						'type'=>'submit'),
					  _('Login'))),
		  HTML::div( array('id'=>'loginMessage'), $msg ));
  $body = HTML::body(HeaderDiv(''),
		     HTML::div(array('class'=>"container"),
			       HTML::div(array('class'=>'row signin-row'),
					 HTML::div(array('class'=>'main signin-wrapper col-lg-4 col-lg-offset-1 col-md-6 col-sm-8 col-xs-12'),
						   HTML::div(array('class'=>"content"), $content)))),
		     FooterDiv(true));
  printDocumentAndExit('Login', $body);
}

function FooterDiv($fixedFooter = false) {
  ExtraHeader('
    var initialScreenSize = window.innerHeight;
    window.addEventListener("resize", function() {
        if(window.innerHeight < 340 && window.innerHeight < initialScreenSize){
            $("footer").hide();
        }
        else{
            $("footer").show();
        }
    })',
	      'onload');
  $sticky = $fixedFooter ? array('class'=>"sticky-footer") : '';
  return
    HTML::footer($sticky,
		 HTML::div(array('class'=>"footer-content container"),
			   HTML::div(array('class'=>"col-lg-10 col-lg-offset-1"),
				     HTML::div(array('class'=>"col-lg-12 col-md-12 col-sm-12 col-xs-12 no-padding"),
					       HTML::div(array('class'=>"quick-links col-lg-4 col-md-4 col-sm-12 col-xs-12"),
							 HTML::a(array('href'=>"http://www.dcs.gla.ac.uk/~hcp/aropa/index.html"),
								 _('Documentation')),
							 HTML::br(),
							 HTML::a(array('href'=>"http://www.dcs.gla.ac.uk/~hcp/aropa/instructors.html"),
								 _('Information for instructors')),
							 HTML::br(),
							 HTML::a(array('href'=>"http://www.dcs.gla.ac.uk/~hcp/aropa/students.html"),
								 _('Information for students'))),
					       HTML::div(array('class'=>"aropa-info col-lg-4 col-md-4 col-sm-12 col-xs-12"),
							 Aropa(),
							 _(' has been designed by John Hamer and Helen Purchase, who provide support in both the use of the system, as well as the design of peer-review activities.')),
					       HTML::div(array('class'=>"contact col-lg-4 col-md-4 col-sm-12 col-xs-12"),
							 _('If you would like to use '), Aropa(), _(' in one of your classes, please contact us at:'),
							 HTML::br(),
							 HTML::span(array('class'=>"glyphicon glyphicon-envelope")),
							 HTML::a(array('href'=>'mailto:john.hamer@glasgow.ac.uk'),
								 'john.hamer@glasgow.ac.uk'),
							 HTML::br(),
							 HTML::span(array('class'=>"glyphicon glyphicon-envelope")),
							 HTML::a(array('href'=>'mailto:helen.purchase@glasgow.ac.uk'),
								 'helen.purchase@glasgow.ac.uk'))))));
}

function authenticate( ) {
  list( $instID, $username, $password )
    = checkREQUEST( '_instID', 'username', 'password' );
  $row = fetchOne( "SELECT * FROM Institution WHERE instID = $instID" );
  if( ! $row )
    // securityAlert( 'bad instID' ); 
    redirect( 'login' );

  parse_str($row['features'] ?? '', $features );
  $msg = checkPassword( $instID, $features, $username, $password, false );
  if( $msg === true )
    redirect('home');
  else
    return loginForm( $instID, $msg ); // _('The name and password you supplied could not be authenticated.  Please try again.') );
}


function checkPassword( $instID, $features, $username, $password, $validated ) {
  ensureDBconnected( 'checkPassword' );
  require_once 'users.php';
  $norm = normaliseUident( $username, $instID );
  if (empty( $norm ))
    return _('A user name must be provided.');

  $acct = fetchOne('select * from User where uident = ' . quote_smart($norm, true) . " and instID = $instID");
  // We really expect the User record to exist at this point.  They
  // get created when the instructor enters the class list, so there
  // is no reason for the account to be missing.
  if (! $acct)
    return _('User name or password is incorrect (or perhaps you have selected the wrong institution?).');
  if (! $validated)
    $validated = checkPassword2($instID, $features, $username, $password, $acct);

  if ($validated === false)
    return _('User name or password is incorrect (or perhaps you have selected the wrong institution?).');
 
  if ($validated == 'tmp'
      && !empty($acct['passwd'])
      && !emptyDate($acct['lastChanged'])
      && strtotime($acct['lastChanged']) > time() - 180 * 24 * 60 * 60) // Last set a password within the past 6 months or so
    return _('The class access code can only be used once. You must now login with your own password.');
  
  if ($validated === 'ok')
    //- Ensure User has a known, good password and that the access code route is disabled..
    checked_mysql_query('update User set passwd = ' . quote_smart(password_hash($password, PASSWORD_DEFAULT))
			. ", lastChanged = now()"
			. " where userID = $acct[userID]");
    
  loginUser($instID, $acct['userID'], $acct['status'], $acct['uident'], $acct['username'], $acct['prefs'], $features['TIMEZONE']);
  
  if ($validated === 'tmp')
    //- All the user can do for now is to set a password
    $_SESSION['tmpLogin'] = true;
  
  return true;
}


function checkPassword2( $instID, $features, $username, $password, $acct ) {
  if (!empty($acct['passwd']) &&
      str_starts_with($acct['passwd'], '$1$') &&
      $acct['passwd'] == $crypt)
    return 'ok';

  if (!empty($acct['passwd']) && password_verify($password, $acct['passwd']))
    return 'ok';

  //- Check for a temporary course password from this institution
  $rs = checked_mysql_query('select cident from UserCourse uc left join Course c on uc.courseID = c.courseID'
			    . ' where cident is not null'
			    . " and instID = $instID"
			    . ' and (roles & 1) <> 0'
			    . " and userID = $acct[userID]");
  while( $row = $rs->fetch_assoc() )
    if( $password === $row['cident'] )
      return 'tmp';

  //- Perhaps the mail system will validate the user for us?
  if( false && function_exists( 'imap_open' )
      && ! empty( $features[ 'IMAP_SERVER' ] )
      ) {
    if( ! empty( $features['IMAP_USERNAME_PAT'] ) )
      $imapUsername = str_replace( '$USER', $username, $features['IMAP_USERNAME_PAT'] );
    else
      $imapUsername = $username;
    $imapconn = @imap_open( '{' . $features['IMAP_SERVER'] . '/imap/ssl/novalidate-cert}',
			    $imapUsername,
			    $password,
			    OP_READONLY | OP_HALFOPEN );
    if( $imapconn ) {
      imap_close( $imapconn );
      return 'ok';
    }
  }

  //- Validate using LDAP, if available.
  if( ! empty( $features['LDAP_SERVER'] )
      && ! empty( $features['LDAP_BASE_DN'] )
      && function_exists( 'ldap_connect' )
      && ! empty( $password )
      && ! empty( $username )
      ) {
    //- Some LDAP servers accept any empty password, so we explicitly
    //- check that case.
    $ld = @ldap_connect( $features['LDAP_SERVER'] );
    if( $ld ) {
      foreach( explode( ";", $features['LDAP_BASE_DN'] ) as $base_dn )
	if( strpos( $base_dn, '$USER' ) !== false ) {
	  //- $user character string escape are from RFC2253, section 2.4
	  $rdn = str_replace( '$USER', addcslashes( $username, ',# +";\\' ), $base_dn );
	  $bind = @ldap_bind( $ld, $rdn, $password );
	  if( $bind ) {
	    ldap_close( $ld );
	    return 'ok';
	  }
	}
      ldap_close( $ld );
    }
  }
  
  return false;
}

function loginUser( $instID, $userID, $status, $uident, $username, $prefs, $timezone ) {
  session_regenerate_id( );

  //- Retain nothing from any previous session.  Record the user's
  //- browser details.  We check each request to see that the same
  //- details are sent with the same session cookie.  This protects
  //- against many "session fixation" attacks.
  $_SESSION = array( 'User-Agent' => $_SERVER["HTTP_USER_AGENT"] );
  $_SESSION['instID'] = $instID;
  $_SESSION['userID'] = $userID;
  if (fetchOne("select * from Superusers where userID = $userID", 'userID'))
    $status = 'superuser';
  $_SESSION['status'] = $status;
  $_SESSION['uident'] = $uident;

  preg_match("/([+-])?(\d\d):(\d\d):\d\d/", fetchOne('select timediff(now(), utc_timestamp) as t', 't'), $tz);
  if (count($tz) == 4) {
    if ($tz[1] == '') $tz[1] = '+';
    $_SESSION['TZ'] = "$tz[1]$tz[2]:$tz[3]";
  } else
    $_SESSION['TZ'] = 'UTC';
  
  if( ! empty( $username ) )
    $_SESSION['username'] = $username;

  parse_str($prefs ?? '', $_SESSION['prefs']);
  checked_mysql_query(makeInsertQuery('SessionAudit',
				      array('userID'=>$userID,
					    'ip'=>$_SERVER['REMOTE_ADDR'],
					    'event'=>'login',
					    'browser'=>$_SERVER["HTTP_USER_AGENT"],
					    'server'=>AROPA_SESSION_NAME)));
  if (function_exists('date_default_timezone_set') && !empty($timezone))
    date_default_timezone_set($timezone);
}



function logout( ) {
  if( isset( $_SESSION['revert'] ) ) {
    $revert = $_SESSION['revert'];
    $cid = $revert['revert-cid'];
    $assmtID = $revert['revert-assmtID'];
    unset( $revert['revert-cid'] );
    unset( $revert['revert-assmtID'] );
    $_SESSION = $revert;
    addPendingMessage( Sprintf_('You are now viewing Arop&auml; as <q>%s</q>', $_SESSION['uident'] ));
    if( empty( $cid ) )
      redirect( 'home' );
    elseif( empty( $assmtID ) )
      redirect( 'selectClass', "cid=$cid" );
    else
      redirect( 'viewAsst', "cid=$cid&assmtID=$assmtID" );
  } else {
    ensureDBconnected( 'logout' );

    if( isset( $_SESSION['userID'] ) )
      checked_mysql_query( makeInsertQuery('SessionAudit',
					   array('userID'=>(int)$_SESSION['userID'],
						 'ip'=>$_SERVER['REMOTE_ADDR'],
						 'event'=>'logout')));
    $instID = (int)$_SESSION['instID'];

    $_SESSION = array( );
    session_destroy( );
    loginForm($instID);
  }
}
