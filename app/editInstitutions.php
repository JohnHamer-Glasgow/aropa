<?php
/*
    Copyright (C) 2014 John Hamer <J.Hamer@cs.auckland.ac.nz>

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

function showInstitutions( ) {
  ensureDBconnected( 'showInstitutions' );

  $inst = fetchAll( 'SELECT instID, longname, shortname, features, isActive'
		    . ' FROM Institution' );
  foreach( $inst as &$row )
    $row['lastModified'] = fetchOne("SELECT MAX(lastModified) as m FROM Assignment a JOIN Course c ON a.courseID=c.courseID"
					 . " WHERE c.instID=$row[instID]", 'm');
  usort( $inst, 'instInModifiedOrder' );

  $table = table( HTML::tr( HTML::th(_('Name')),
			    HTML::th(),
			    HTML::th(),
			    HTML::th(_('Last modified')),
			    HTML::th()));
  foreach( $inst as &$row ) {
    $name = $row['longname'];
    if( empty( $name ) ) $name = $row['shortname'];
    if( empty( $name ) ) $name = "Institution-$row[instID]";
    parse_str($row['features'] ?? '', $features);
    $tr = HTML::tr( HTML::td( $name ),
		    HTML::td( formButton(_('Edit'), "editInstitution&id=$row[instID]") ),
		    HTML::td( $_SESSION['instID'] == $row['instID']
			      ? _('Selected')
			      :  formButton($row['isActive'] ? _('Select') : _('(inactive)'),
					    "changeInstitution&id=$row[instID]")),
		    HTML::td( formatDateString($row['lastModified'])),
		    HTML::td( isset( $features['UIDENT_REGEX'])
			      ? formButton( _('Check user names'), "normaliseUserRecords&id=$row[instID]")
			      : ''));
    $table->pushContent( $tr );
  }

  return HTML::div( array('class'=>'list-page'),
		    HTML::h1( _('Institutions') ),
		    _('Select or edit an institution, or '),
		    formButton( _('create a new one'), 'editInstitution' ),
		    HTML::br(),
		    $table,
		    BackButton());
}

function instInModifiedOrder($a, $b) {
  return strtotime($b['lastModified'] ?? '') - strtotime($a['lastModified'] ?? '');
}

function changeInstitution( ) {
  list($instID) = checkREQUEST('_id');
  if( $_SESSION['instID'] != $instID ) {
    if( $_SESSION['status'] != 'superuser' || fetchOne("select shortname from Institution where instID=$instID" ) === null )
      return warning( _('Unable to change institution') );

    $userID = $_SESSION['userID'];
    $status = $_SESSION['status'];
    $uident = $_SESSION['uident'];
    $tz     = $_SESSION['TZ'];
    if( isset( $_SESSION['username'] ) )
      $username = $_SESSION['username'];

    $_SESSION = array('instID' => $instID,
		      'User-Agent' => $_SERVER["HTTP_USER_AGENT"],
		      'TZ' => $tz,
		      'userID' => $userID,
		      'status' => $status,
		      'uident' => $uident);
    if( isset( $username ) )
      $_SESSION['username'] = $username;

    if( function_exists( 'date_default_timezone_set' ) && institutionHasFeature('TIMEZONE') )
      date_default_timezone_set( getInstitution( )->features['TIMEZONE'] );

    loadClasses( );
  }
  return homePage();
}


function editInstitution( ) {
  ensureDBconnected( 'editInstitution' );
  if( isset( $_REQUEST['id'] ) ) {
    $instID = (int)$_REQUEST['id'];
    $heading = 'Edit institution';
    $rs = checked_mysql_query( "SELECT * FROM Institution WHERE instID = $instID" );
    $inst = $rs->fetch_assoc();
    if( ! $inst )
      return HTML( HTML::h1( _('Cannot edit, as there is (suddenly?) no such institution!') ),
		   BackButton());
  } else {
    $instID = 0;
    $heading = _('Adding a new institution');
    $inst = array( 'shortname'=>'',
		   'instID'=>0,
		   'longname'=>'',
		   'isActive'=>false,
		   'features'=>'');
  }

  // Also give a list of administrators for this institution.
  $rs = checked_mysql_query("select userID, uident from User where status = 'administrator' and instID = $instID");
  $admins = "";
  while( $row = $rs->fetch_assoc() )
    $admins .= $row['uident'] . "\n";

  parse_str($inst['features'] ?? '', $features);

  return HTML(HTML::h1($heading),
	      HTML::form(array('method' =>'post',
			       'class'=>'form',
			       'enctype'=>'multipart/form-data',
			       'action' => "{$_SERVER['PHP_SELF']}?action=saveInstitution" ),
			 HiddenInputs( array('id'=>$instID )),
			 HTML::h2(_('Institution details')),
			 FormGroup('longname',
				   _('Long name'),
				   HTML::input(array('type'=>'text',
						     'value'=>$inst['longname']))),
			 HTML::div(array('class'=>'form-inline'),
				   FormGroup('shortname',
					     _('Short name'),
					     HTML::input(array('type'=>'text',
							       'value'=>$inst['shortname'])))),
			 HTML::div(array('class'=>'form-inline'),
				   FormGroup('isActive',
					     _('Active?'),
					     HTML::input(array('type'=>'checkbox',
							       'checked'=>$inst['isActive']!=0)))),
			 FormGroup('logo',
				   _('Logo'),
				   HTML::input(array('type'=>'file',
						     'accept'=>'image/*'))),
			 HTML::img(array('src'=>"{$_SERVER['PHP_SELF']}?action=logo&instID=$instID",
					 'alt'=>'No logo')),
			 HTML::h2(_('Features')),
			 FormGroup('TIMEZONE',
				   _('Time zone'),
				   timezoneSelector($features['TIMEZONE'])),
			 FormGroup('UIDENT_REGEX',
				   _('Uident regular expression'),
				   HTML::input(array('type'=>'text',
						     'value'=>$features['UIDENT_REGEX']))),
			 FormGroup('UIDENT_FORMAT',
				   _('Uident sprintf format'),
				   HTML::input(array('type'=>'text',
						     'value'=>$features['UIDENT_FORMAT']))),
			 FormGroup('UIDENT_EXCEPT',
				   _('Uident exception regular expression'),
				   HTML::input(array('type'=>'text',
						     'value'=>$features['UIDENT_EXCEPT']))),
			 FormGroup('LTI_CONSUMER_KEY',
				   _('LTI consumer key'),
				   HTML::input(array('type'=>'text',
						     'value'=>$features['LTI_CONSUMER_KEY']))),
			 FormGroup('LTI_SECRET',
				   _('LTI secret'),
				   HTML::input(array('type'=>'text',
						     'value'=>$features['LTI_SECRET']))),
			 FormGroup('LTI_USER_ID_FIELD',
				   _('LTI user ID field'),
				   HTML::input(array('type'=>'text',
						     'value'=>$features['LTI_USER_ID_FIELD']))),
			 FormGroup('IMAP_SERVER',
				   _('IMAP server:port'),
				   HTML::input(array('type'=>'text',
						     'value'=>$features['IMAP_SERVER']))),
			 FormGroup('IMAP_USERNAME_PAT',
				   _('IMAP username pattern'),
				   HTML::input( array('type'=>'text',
						      'value'=>$features['IMAP_USERNAME_PAT']))),
			 FormGroup('LDAP_SERVER',
				   _('LDAP server'),
				   HTML::input(array('type'=>'text',
						     'value'=>$features['LDAP_SERVER']))),
			 FormGroup('LDAP_BASE_DN',
				   _('LDAP base DN'),
				   HTML::input(array('type'=>'text',
						     'value'=>$features['LDAP_BASE_DN']))),
			 FormGroup('CATALOG',
				   _('Class catalog method'),
				   HTML::input(array('type'=>'text',
						     'value'=>$features['CATALOG']))),
			 HTML::h2(_('Administrators for this institution')),
			 _('Administrators are able to view all classes as guests.'),
			 HTML::br( ),
			 HTML::textarea(array('name'=>'admins',
					      'rows'=>'10',
					      'cols'=>'20'),
					$admins),
			 ButtonToolbar(submitButton(_('Save'), "saveInstitution&id=$instID"),
				       CancelButton())));
}


function saveInstitution( ) {
  list( $instID ) = checkREQUEST( '_id' );
  $db = ensureDBconnected( 'saveInstitution' );
  
  $upd = array( );
  if( isset( $_REQUEST['longname'] ) )
    $upd['longname'] = $_REQUEST['longname'];

  if( isset( $_REQUEST['shortname'] ) )
    $upd['shortname'] = $_REQUEST['shortname'];

  if( isset( $_FILES['logo'] ) && $_FILES['logo']['error'] == UPLOAD_ERR_OK ) {
    $info = pathinfo( $_FILES['logo']['name'] );
    $tmp = $_FILES['logo']['tmp_name'];
    if( ! is_uploaded_file( $tmp ) )
      securityAlert( 'possible upload attack' );
    $upd['logo'] = file_get_contents( $tmp );
    $upd['logoType'] = $_FILES['logo']['type'];
  }

  $features = array( );
  foreach( array('TIMEZONE', 'UIDENT_REGEX', 'UIDENT_FORMAT', 'UIDENT_EXCEPT',
		 'LTI_CONSUMER_KEY', 'LTI_SECRET', 'LTI_USER_ID_FIELD',
		 'LDAP_SERVER', 'LDAP_BASE_DN', 'IMAP_SERVER', 'IMAP_USERNAME_PAT',
		 'CATALOG') as $f )
    if( isset( $_REQUEST[$f] ) && trim( $_REQUEST[$f] ) != '' )
      $features[ $f ] = $_REQUEST[ $f ];
  $upd['features'] = itemsToString( $features );
  
  $upd['isActive'] = $_REQUEST['isActive'] == 'on';

  if( $instID == 0 ) {
    checked_mysql_query( makeInsertQuery('Institution', $upd) );
    $instID = $db->insert_id;
  } else
    checked_mysql_query( makeUpdateQuery('Institution', $upd)
			 . ' WHERE instID = ' . $instID );

  require_once 'users.php';
  if( isset( $_REQUEST['admins'] ) ) {
    $admins = array( );
    foreach( explode( "\n", $_REQUEST['admins'] ) as $line ) {
      $who = normaliseUident( $line );
      if( ! empty($who) && $who[0] != '#' )
	$admins[ $who ] = true;
    }
    $userIDs = identitiesToUserIDs(array_keys($admins), $instID);
    $userIDs[] = 0;
    //- Downgrade any current administrators not in the list
    checked_mysql_query('update User set status = "active"'
			 . " where status = 'administrator'"
			 . ' and userID not in (' . join(',', $userIDs) . ')');
    checked_mysql_query('update User set status = "administrator"'
			 . ' where userID in (' . join(',', $userIDs) . ')');
  }
  
  redirect('showInstitutions' );
}
  

function normaliseUserRecords( ) {
  list( $instID ) = checkREQUEST( '_id' );
  $db = ensureDBconnected( 'normaliseUserRecords' );
  require_once 'users.php';

  $rs = checked_mysql_query( "SELECT uident, userID, username FROM User WHERE instID=$instID" );
  $toMerge = HTML::ul( );
  $errant = HTML::ul( );
  while( $row = $rs->fetch_assoc() ) {
    set_time_limit( 1 );
    $uident = strtolower( $row['uident'] );
    $norm = normaliseUident( $uident );
    if( $norm !== $uident ) {
      if( ! isset( $_REQUEST['update'] ) )
	$errant->pushContent( HTML::li( "$uident -> $norm" ));
      else {
	if( empty( $row['username'] ) )
	  checked_mysql_query( 'UPDATE IGNORE User SET uident=' . quote_smart( $norm )
			       . ',username=' . quote_smart($uident)
			       . " WHERE userID=$row[userID]" );
	else
	  checked_mysql_query( 'UPDATE IGNORE User SET uident=' . quote_smart( $norm )
			       . " WHERE userID=$row[userID]" );
	if( $db->affected_rows != 0 )
	  $errant->pushContent( HTML::li( "$uident -> $norm" ));
	else {
	  // We must already have a user with a uident of $norm; they
	  // are probably the same person, so offer to merge
	  $other = fetchOne( "SELECT userID FROM User WHERE instID=$instID AND uident=" . quote_smart( $norm ),
			     'userID' );
	  if( ! empty( $other ) ) {
	    $toMerge->pushContent( HTML::li( "$uident ($row[userID]) -> $norm ($other)") );
	    if( isset( $_REQUEST['merge'] ) ) {
	      require_once 'editUser.php';
	      mergeTwoUsers( $other, $row['userID'] );
	    }
	  }
	}
      }
    }
  }
  $html = HTML( HTML::h1( _('Normalise user records') ) );
  if( isset( $_REQUEST['update'] ) ) {
    if( ! $errant->isEmpty( ) )
      $html->pushContent( HTML::h2( _('The following accounts were renamed:' )),
			  $errant );
    if( ! $toMerge->isEmpty( ) ) {
      if( isset( $_REQUEST['merge'] ) )
	$html->pushContent( HTML::h2( _('The following accounts were merged:' )),
			    $toMerge );
      else
	$html->pushContent( HTML::h2( _('The following accounts clash:') ),
			    $toMerge,
			    formButton( _('Merge these accounts'),
					"normaliseUserRecords&id=$instID&update&merge" ));
    }
  } else if( ! $errant->isEmpty( ) )
    $html->pushContent( HTML::h2( _('The following accounts require renaming to conform to the institution format:') ),
			$errant,
			formButton( _('Rename these accounts'),
				    "normaliseUserRecords&id=$instID&update" ));
  else
    $html->pushContent( HTML::h2( _('No account renaming is required') ));
  return HTML( $html,
	       BackButton());
}

/*
  Eastern Time           America/New_York
  Central Time           America/Chicago
  Mountain Time          America/Denver
  Mountain Time (no DST) America/Phoenix
  Pacific Time           America/Los_Angeles
  Alaska Time            America/Anchorage
  Hawaii-Aleutian        America/Adak
  Hawaii-Aleutian Time (no DST) Pacific/Honolulu

TimeZones = [
    'Dateline Standard Time' => 'Etc/GMT+12',
    'UTC-11' => 'Etc/GMT+11',
    'Aleutian Standard Time' => 'America/Adak',
    'Hawaiian Standard Time' => 'Etc/GMT+10',
    'Marquesas Standard Time' => 'Pacific/Marquesas',
    'Alaskan Standard Time' => 'America/Anchorage America/Juneau America/Nome America/Sitka America/Yakutat',
    'UTC-09' => 'Etc/GMT+9',
    'Pacific Standard Time (Mexico)' => 'America/Tijuana America/Santa_Isabel',
    'UTC-08' => 'Etc/GMT+8',
    'Pacific Standard Time' => 'PST8PDT',
    'US Mountain Standard Time' => 'Etc/GMT+7',
    'Mountain Standard Time (Mexico)' => 'America/Chihuahua America/Mazatlan',
    'Mountain Standard Time' => 'MST7MDT',
    'Central America Standard Time' => 'Etc/GMT+6',
    'Central Standard Time' => 'CST6CDT',
    'Easter Island Standard Time' => 'Pacific/Easter',
    'Central Standard Time (Mexico)' => 'America/Mexico_City America/Bahia_Banderas America/Merida America/Monterrey',
    'Canada Central Standard Time' => 'America/Regina America/Swift_Current',
    'SA Pacific Standard Time' => 'Etc/GMT+5',
    'Eastern Standard Time (Mexico)' => 'America/Cancun',
    'Eastern Standard Time' => 'EST5EDT',
    'Haiti Standard Time' => 'America/Port-au-Prince',
    'Cuba Standard Time' => 'America/Havana',
    'US Eastern Standard Time' => 'America/Indianapolis America/Indiana/Marengo America/Indiana/Vevay',
    'Paraguay Standard Time' => 'America/Asuncion',
    'Atlantic Standard Time' => 'America/Thule',
    'Venezuela Standard Time' => 'America/Caracas',
    'Central Brazilian Standard Time' => 'America/Cuiaba America/Campo_Grande',
    'SA Western Standard Time' => 'Etc/GMT+4',
    'Pacific SA Standard Time' => 'America/Santiago',
    'Turks And Caicos Standard Time' => 'America/Grand_Turk',
    'Newfoundland Standard Time' => 'America/St_Johns',
    'Tocantins Standard Time' => 'America/Araguaina',
    'E. South America Standard Time' => 'America/Sao_Paulo',
    'SA Eastern Standard Time' => 'Etc/GMT+3',
    'Argentina Standard Time' => 'America/Buenos_Aires America/Argentina/La_Rioja America/Argentina/Rio_Gallegos America/Argentina/Salta America/Argentina/San_Juan America/Argentina/San_Luis America/Argentina/Tucuman America/Argentina/Ushuaia America/Catamarca America/Cordoba America/Jujuy America/Mendoza',
    'Greenland Standard Time' => 'America/Godthab',
    'Montevideo Standard Time' => 'America/Montevideo',
    'Magallanes Standard Time' => 'America/Punta_Arenas',
    'Saint Pierre Standard Time' => 'America/Miquelon',
    'Bahia Standard Time' => 'America/Bahia',
    'UTC-02' => 'Etc/GMT+2',
    'Azores Standard Time' => 'Atlantic/Azores',
    'Cape Verde Standard Time' => 'Etc/GMT+1',
    'UTC' => 'Etc/GMT Etc/UTC',
    'GMT Standard Time' => 'Europe/Lisbon Atlantic/Madeira',
    'Greenwich Standard Time' => 'Africa/Lome',
    'W. Europe Standard Time' => 'Europe/Vatican',
    'Central Europe Standard Time' => 'Europe/Bratislava',
    'Romance Standard Time' => 'Europe/Paris',
    'Morocco Standard Time' => 'Africa/Casablanca',
    'Sao Tome Standard Time' => 'Africa/Sao_Tome',
    'Central European Standard Time' => 'Europe/Warsaw',
    'W. Central Africa Standard Time' => 'Etc/GMT-1',
    'Jordan Standard Time' => 'Asia/Amman',
    'GTB Standard Time' => 'Europe/Bucharest',
    'Middle East Standard Time' => 'Asia/Beirut',
    'Egypt Standard Time' => 'Africa/Cairo',
    'E. Europe Standard Time' => 'Europe/Chisinau',
    'Syria Standard Time' => 'Asia/Damascus',
    'West Bank Standard Time' => 'Asia/Hebron Asia/Gaza',
    'South Africa Standard Time' => 'Etc/GMT-2',
    'FLE Standard Time' => 'Europe/Kiev Europe/Uzhgorod Europe/Zaporozhye',
    'Israel Standard Time' => 'Asia/Jerusalem',
    'Kaliningrad Standard Time' => 'Europe/Kaliningrad',
    'Sudan Standard Time' => 'Africa/Khartoum',
    'Libya Standard Time' => 'Africa/Tripoli',
    'Namibia Standard Time' => 'Africa/Windhoek',
    'Arabic Standard Time' => 'Asia/Baghdad',
    'Turkey Standard Time' => 'Europe/Istanbul',
    'Arab Standard Time' => 'Asia/Aden',
    'Belarus Standard Time' => 'Europe/Minsk',
    'Russian Standard Time' => 'Europe/Simferopol',
    'E. Africa Standard Time' => 'Etc/GMT-3',
    'Iran Standard Time' => 'Asia/Tehran',
    'Arabian Standard Time' => 'Etc/GMT-4',
    'Astrakhan Standard Time' => 'Europe/Astrakhan Europe/Ulyanovsk',
    'Azerbaijan Standard Time' => 'Asia/Baku',
    'Russia Time Zone 3' => 'Europe/Samara',
    'Mauritius Standard Time' => 'Indian/Mahe',
    'Saratov Standard Time' => 'Europe/Saratov',
    'Georgian Standard Time' => 'Asia/Tbilisi',
    'Caucasus Standard Time' => 'Asia/Yerevan',
    'Afghanistan Standard Time' => 'Asia/Kabul',
    'West Asia Standard Time' => 'Etc/GMT-5',
    'Ekaterinburg Standard Time' => 'Asia/Yekaterinburg',
    'Pakistan Standard Time' => 'Asia/Karachi',
    'India Standard Time' => 'Asia/Calcutta',
    'Sri Lanka Standard Time' => 'Asia/Colombo',
    'Nepal Standard Time' => 'Asia/Katmandu',
    'Central Asia Standard Time' => 'Etc/GMT-6',
    'Bangladesh Standard Time' => 'Asia/Thimphu',
    'Omsk Standard Time' => 'Asia/Omsk',
    'Myanmar Standard Time' => 'Asia/Rangoon',
    'SE Asia Standard Time' => 'Etc/GMT-7',
    'Altai Standard Time' => 'Asia/Barnaul',
    'W. Mongolia Standard Time' => 'Asia/Hovd',
    'North Asia Standard Time' => 'Asia/Krasnoyarsk Asia/Novokuznetsk',
    'N. Central Asia Standard Time' => 'Asia/Novosibirsk',
    'Tomsk Standard Time' => 'Asia/Tomsk',
    'China Standard Time' => 'Asia/Macau',
    'North Asia East Standard Time' => 'Asia/Irkutsk',
    'Singapore Standard Time' => 'Etc/GMT-8',
    'W. Australia Standard Time' => 'Australia/Perth',
    'Taipei Standard Time' => 'Asia/Taipei',
    'Ulaanbaatar Standard Time' => 'Asia/Ulaanbaatar Asia/Choibalsan',
    'Aus Central W. Standard Time' => 'Australia/Eucla',
    'Transbaikal Standard Time' => 'Asia/Chita',
    'Tokyo Standard Time' => 'Etc/GMT-9',
    'North Korea Standard Time' => 'Asia/Pyongyang',
    'Korea Standard Time' => 'Asia/Seoul',
    'Yakutsk Standard Time' => 'Asia/Yakutsk Asia/Khandyga',
    'Cen. Australia Standard Time' => 'Australia/Adelaide Australia/Broken_Hill',
    'AUS Central Standard Time' => 'Australia/Darwin',
    'E. Australia Standard Time' => 'Australia/Brisbane Australia/Lindeman',
    'AUS Eastern Standard Time' => 'Australia/Sydney Australia/Melbourne',
    'West Pacific Standard Time' => 'Etc/GMT-10',
    'Tasmania Standard Time' => 'Australia/Hobart Australia/Currie',
    'Vladivostok Standard Time' => 'Asia/Vladivostok Asia/Ust-Nera',
    'Lord Howe Standard Time' => 'Australia/Lord_Howe',
    'Bougainville Standard Time' => 'Pacific/Bougainville',
    'Russia Time Zone 10' => 'Asia/Srednekolymsk',
    'Magadan Standard Time' => 'Asia/Magadan',
    'Norfolk Standard Time' => 'Pacific/Norfolk',
    'Sakhalin Standard Time' => 'Asia/Sakhalin',
    'Central Pacific Standard Time' => 'Etc/GMT-11',
    'Russia Time Zone 11' => 'Asia/Kamchatka Asia/Anadyr',
    'New Zealand Standard Time' => 'Pacific/Auckland',
    'UTC+12' => 'Etc/GMT-12',
    'Fiji Standard Time' => 'Pacific/Fiji',
    'Chatham Islands Standard Time' => 'Pacific/Chatham',
    'UTC+13' => 'Etc/GMT-13',
    'Tonga Standard Time' => 'Pacific/Tongatapu',
    'Samoa Standard Time' => 'Pacific/Apia',
    'Line Islands Standard Time' => 'Etc/GMT-14'
];
 */
function timezoneSelector( $selected ) {
  $select = HTML::select(array('name'=>'TIMEZONE'));
   
  $zones = array( );
  foreach( timezone_identifiers_list( ) as $zone ) {
    $zoneX = explode( '/', $zone );
    $continent = isset( $zoneX[0] ) ? $zoneX[0] : '';
    $city      = isset( $zoneX[1] ) ? $zoneX[1] : '';
    $zones[ $continent ][ $city ] = $zone;
  }

  ksort( $zones );
  foreach( $zones as $continent => $cities )
    if( in_array( $continent, array('Africa', 'America', 'Asia', 'Atlantic',
				    'Australia', 'Europe', 'Indian', 'Pacific' ))) {
      $optgroup = HTML::optgroup( array('label'=>$continent) );
      unset( $cities[''] );
      ksort( $cities );
      foreach( $cities as $zone )
	$optgroup->pushContent( HTML::option(array('value'=>$zone,
						   'selected'=>$zone==$selected),
					     str_replace('_', ' ', $zone)));
      $select->pushContent( $optgroup );
    }
  return $select;
}


function moveClass( ) {
  list( $cid, $inst ) = checkREQUEST( '_cid', 'inst' );

  $instID = fetchOne( 'SELECT instID FROM Institution WHERE shortname=' . quote_smart( $inst ), 'instID' );
  if( $instID == null )
    return warning( Sprintf_('No such institution: <q>%s</q>', $inst ) );

  $courseID = cidToClassId( $cid );
  checked_mysql_query( "UPDATE Course SET instID=$instID WHERE courseID=$courseID" );

  // update User.instID for all users in UserCourse.  Ignore any conflicts
  checked_mysql_query( "UPDATE User SET instID=$instID WHERE userID IN (SELECT userID FROM UserCourse WHERE courseID=$courseID) AND status<>'administrator'" );

  return Sprintf_('Class %d moved to institution %d', $courseID, $instID );
}
