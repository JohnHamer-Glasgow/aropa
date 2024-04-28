<?php
/*
    Copyright (C) 2016 John Hamer <J.Hamer@acm.org>

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
global $gRoles;
$gRoles = array('instructor'=>8, 'guest'=>4, 'marker'=>2, 'student'=>1 );

function quickClassList( ) {
  list( $cid ) = checkREQUEST( '_cid' );
  ensureDBconnected( 'quickClassList' );

  if( ! isset( $_SESSION['classes'] ) || ! isset( $_SESSION['classes'][ $cid ] ) )
    return homePage( _('There was a problem finding the class you selected.') );

  $class = $_SESSION['classes'][ $cid ];
  
  require_once 'users.php';

  if( isset( $_REQUEST['students'] ) || isset( $_REQUEST['people'] ) ) {
    $identRole = array( );
    $username = array( );
    if( isset( $_REQUEST['students'] ) )
      foreach( explode( "\n", $_REQUEST['students'] ) as $line ) {
	if( preg_match("/^\s*([-\w.@]+)\s*(?:<(.*)>\s*)?/", $line, $match ) === 1 ) {
	  $uident = normaliseUident( $match[1] );
	  $identRole[ $uident ] = 1;
	  if( ! empty( $match[2] ) )
	    $username[ $uident ] = $match[2];
	  else if( $uident !== $match[1] )
	    $username[ $uident ] = $match[1];
	}
      }

    if( isset( $_REQUEST['people'] ) )
      foreach( $_REQUEST['people'] as $idx => $p ) {
	$p = normaliseUident( $p );
	if( $p != "" ) { 
	  $role = 0;
	  if( isset( $_REQUEST['role'][ $idx ] ) )
	    $role |= (int)$_REQUEST['role'][ $idx ];
	  if( $role != 0 )
	    $identRole[ $p ] = $role;
	}
      }
    
    $courseID = (int)$class['courseID'];
    $roles = array( );
    $cuserID = fetchOne( "SELECT cuserID FROM Course WHERE courseID=$courseID", 'cuserID' );
    if( $cuserID != -1 )
      $roles[ $cuserID ] = 8; //- Force the class owner to be an instructor

    foreach( identitiesAndUsernamesToUserIDs( array_keys( $identRole ), $username ) as $ident => $userID )
      if( isset( $identRole[ $ident ] ) )
	// If a uident appears in both students and people, choose the people role
	if( ! isset( $roles[$userID] ) || $roles[$userID] < $identRole[ $ident ] )
	  $roles[ $userID ] = $identRole[ $ident ];
    
    checked_mysql_query( "DELETE FROM UserCourse WHERE courseID=$courseID" );
    if( ! empty( $roles ) ) {
      $values = array( );
      foreach( $roles as $userID => $r )
	$values[] = "($userID,$courseID,$r)";
      checked_mysql_query( 'INSERT INTO UserCourse (userID, courseID, roles)'
			   . ' VALUES ' . join(',', $values ));
    }

    redirect( 'selectClass', "cid=$cid" );
  } else {
    $students = array( );
    $people = HTML::table(array('class'=>'table', 'id'=>'people-table'));
    $n = 0;
    $classUsers = fetchAll( 'SELECT IFNULL(uident, CONCAT("u-", c.userID)) AS uident, roles, username'
			     . ' FROM UserCourse c LEFT JOIN User u ON c.userID=u.userID'
			     . ' WHERE courseID = ' . (int)$class['courseID']
			     . ' ORDER BY roles DESC, uident ASC');
    
    foreach( $classUsers as $u )
      if( $u['roles'] == 1 )
	$students[] = empty( $u['username'] ) ? $u['uident'] : "$u[uident]\t<$u[username]>";
      else {
	$tr = HTML::tr(HTML::td(HTML::input(array('type'=>'text',
						  'class'=>'form-control',
						  'name'=>"people[$n]",
						  'value'=>$u['uident']))));
	global $gRoles;
	foreach ($gRoles as $role => $code)
	  if ($role != 'student') {
	    $input = HTML::input(array('type'=>'radio',
				       'style'=>'display: none',
				       'name'=>"role[$n]",
				       'value'=>$code),
				 $role);
	    $label = HTML::label(array('class'=>'btn btn-default radio'), $input);
	    if( ($u['roles'] & $code) != 0 ) {
	      $input->setAttr('checked', true);
	      $label->setInClass('checked');
	    }
	    
	    $tr->pushContent(HTML::td($label));
	  }
	
	$people->pushContent($tr);
	$n++;
      }

    extraHeader('markers.js', 'js');
    extraHeader('maybeAddTableRow("people-table", false)', 'onload');
    extraHeader("$('#people-table').on('click', 'input[type=radio]', function() {
		$(this).closest('tr').find('.radio').removeClass('checked');
		$(this).closest('.radio').addClass('checked');})", 'onload');

    if (institutionHasFeature('CATALOG')) {
      $quickClassListEntry = Modal('class-catalog-dialog',
				   _('Load class list'),
				   HTML(FormGroup('class-code',
						  _('Course code (e.g. MED5002, TRS4081, ENG1002)'),
						  HTML::input(array('type'=>'text',
								    'class'=>'form-control',
								    'id'=>"catalog")),
						  _('Course code')),
					HTML::div(array('id' => 'not-found'), warning(_('Course code not found'))),
					message(_('Note that this list will not change automatically when the list of students changes in MyCampus or Moodle. You will need to reload the class list if there are any additions to the class.'))),
				   HTML::button(array('type'=>'button',
						      'class'=>'btn',
						      //'data-dismiss'=>'modal',
						      'onclick'=>'loadClassCatalog()'),
						_('Load class list')));
      $classCatalogButton = HTML::button(array('type'=>'button',
					       'class'=>'btn',
					       'data-toggle'=>'modal',
					       'data-target'=>'#class-catalog-dialog'),
					 _('Load class list'));
      extraHeader('$("#class-catalog-dialog").on("shown.bs.modal", function() {
    $("#not-found").hide();
    $("#catalog").css("border", "none").focus();
})', 'onload');
      $loadClassListJs = JavaScript('
function loadClassCatalog() {
$("#students").load("' . $_SERVER['PHP_SELF'] . '",
                    {action: "LoadClassCatalog", catalog: $("#catalog").val(), cid: ' . $cid . ', current: $("#students").val()},
function(response, status, xhr) {
  if (response == "" || xhr.status != 200) {
    $("#not-found").show();
    $("#catalog").css("border", "2px solid red");
  } else {
    $("#class-catalog-dialog").modal("hide");
    $("#not-found").hide();
    $("#catalog").css("border", "none");
  }
});
}');
    } else {
      $quickClassListEntry = '';
      $classCatalogButton = '';
      $loadClassListJs = '';
    }
    
    $quickMarkerEntry = Modal('quick-marker-dialog',
			      _('Quick marker entry'),
			      HTML(HTML::h3(_('Enter the markers below, one per line')),
				   HTML::textarea(array('id'=>'quickMarkerArea',
							'autofocus'=>true,
							'rows'=>10,
							'cols'=>80))),
			      HTML::button(array('type'=>'button',
						 'class'=>'btn',
						 'data-dismiss'=>'modal',
						 'onclick'=>'quickMarkerLoad()'),
					   _('Save markers')));

    return HTML( HTML::h2(Sprintf_('Class list for %s', $class['cname'])),
		 $loadClassListJs,
		 $quickMarkerEntry,
		 $quickClassListEntry,
		 HTML::style(".radio.checked { background-color: #266c8e; color: #fff!important; }"),
		 HTML::form(array('method' =>'post',
				  'class'=>'form',
				  'action' => "$_SERVER[PHP_SELF]?action=quickClassList"),
			    HiddenInputs(array('cid'=>$cid)),
			    FormGroup('students',
				      _('Students'),
				      HTML::textarea(array('id' => 'students',
							   'rows'=>15, 'cols'=>72),
						     join("\n", $students))),
			    $classCatalogButton,
			    HTML::br(),
			    HTML::br(),
			    HTML::label(_('Non-students')),
			    $people,
			    HTML::button(array('type'=>'button',
					       'class'=>'btn',
					       'data-toggle'=>'modal',
					       'data-target'=>'#quick-marker-dialog'),
					 _('Quick marker entry')),
			    HTML::br(),
			    HTML::br(),
			    ButtonToolbar(submitButton(_('Save')),
					  CancelButton())));
  }
}

// AJAX entry
function LoadClassCatalog() {
  while (ob_end_clean());
  if (institutionHasFeature('CATALOG') && isset($_REQUEST['catalog'])) {
    $catalog = $_REQUEST['catalog'];
    switch (getInstitution()->features['CATALOG']) {
    case 'GLA':
      require_once 'users.php';
      $secret = sha1($catalog . 'mysecret42');
      $sslOpt = stream_context_create(array('ssl' => array('verify_peer'       => false,
							   'verify_peer_name'  => false,
							   'allow_self_signed' => true,
							   'verify_depth'      => 0)));
      $result = @file("https://learn.gla.ac.uk/yacrs/services/coursestudentsCSV.php?actualCourseKey=$catalog&secret=$secret", false, $sslOpt);
      $current = array();
      foreach (explode( "\n", $_REQUEST['current']) as $line)
	if (preg_match("/^\s*([-\w.@]+)\s*(?:<(.*)>\s*)?/", $line, $match) === 1)
	  $current[normaliseUident($match[1])] = 1;
      header("Content-type: text/plain");
      echo "$_REQUEST[current]\n";
      echo "\n# New students loaded from $catalog\n";
      foreach ($result as $i => $line)
	if ($i > 0) {
	  // Returns a CSV, one student per line: Forename,Surname,Email Address,Telephone Number,NETWARE ACCOUNT NAME,Course Code,Course,Academic Level,Person ID
	  $fields = explode(',', $line);
	  if (count($fields) > 4 && !isset($current[normaliseUident($fields[4])]))
	    echo "$fields[4] <$fields[0] $fields[1]>\n";
	}
    }
  }

  exit();
}


function showUsers( ) {
  ensureDBconnected( 'showUsers' );
  if( isset( $_REQUEST['cid'] ) ) {
    $cid = (int)$_REQUEST['cid'];
    if( ! isset( $_SESSION['classes'] ) || ! isset( $_SESSION['classes'][ $cid ] ) )
      return homePage( _('There was a problem finding the class you selected.') );
    $class = $_SESSION['classes'][ $cid ];
  }

  static $fields = array( );
  $fields[ _('Identity') ]  = 'uident';
  $fields[ _('User name') ] = 'username';
  $fields[ _('Email') ]     = 'email';

  $PAGESIZE = 25;
  $page = isset( $_REQUEST['p'] ) ? (int)$_REQUEST['p'] : 0;
  $limit = $page*$PAGESIZE . ",$PAGESIZE";

  $order = 'uident';
  if( isset( $_REQUEST['k'] ) && in_array( $_REQUEST['k'], $fields ) )
    $order = $_REQUEST['k'];

  $constraints = array( 'instID = ' . (int)$_SESSION['instID'] );
  if( isset( $class ) )
    $constraints[] = 'courseID = ' . $class['courseID'];

  if( isset( $_REQUEST['a'] ) )
    $constraints[] = '(roles&' . (int)$_REQUEST['a'] . ')<>0';

  if( isset( $_REQUEST['m'] ) )
    $constraints[] = makeSearchClause( $_REQUEST['m'] );

  $table = table( );
  $tr = HTML::tr( );
  foreach( $fields as $name => $f )
    $tr->pushContent( HTML::th( callback_url($name, "showUsers&" . query(array('k'=>$f)))));

  $tr->pushContent( HTML::th( _('Edit?') ) );
  $table->pushContent( $tr );

  $userTable = isset( $class ) ? 'User NATURAL JOIN UserCourse' : 'User';

  $rs = checked_mysql_query( 'SELECT * FROM ' . $userTable
			     . ' WHERE ' . join(' AND ', $constraints )
			     . " ORDER BY $order"
			     . " LIMIT $limit" );
  while( $row = $rs->fetch_assoc() ) {
    $tr = HTML::tr( );
    global $gRoles;
    foreach( $gRoles as $name => $code )
      if( ($row['roles'] & $code) != 0 ) {
	$tr->setAttr('class', "ac_$name" );
	break;
      }
    foreach( $fields as $f )
      $tr->pushContent( HTML::td( $row[$f] ));
    $tr->pushContent( HTML::td( callback_url( _('Edit'), "editUser&" . query(array('id'=>$row['userID'])))));
    $table->pushContent( $tr );
  }

  $row = fetchAll( "SELECT count(*) AS n FROM $userTable WHERE " . join(' AND ', $constraints ),
		   'n' );
  $nUsers = $row ? $row[0] : 0;

  if( $nUsers <= $PAGESIZE )
    $links = "";
  else {
    $links = HTML::p( );
    $last = (int)(($nUsers-1)/$PAGESIZE);
    for( $p = 0; $p <= $last; $p++ ) {
      if( $p < 3 || abs($p-$page) < 3 || $p > $last-3 ) {
	$range = HTML::raw( $p*$PAGESIZE+1 . '&ndash;' . min($nUsers, ($p+1)*$PAGESIZE) );
	$links->pushContent( $p == $page ? $range : callback_url( $range, "showUsers&"
								  . query(array('p'=>$p))), ' ' );
	$skip = true;
      } else if( $skip ) {
	$skip = false;
	$links->pushContent( HTML::raw('&hellip; ') );
      }
    }
  }

  autoCompleteWidget(isset($cid) ? "jsonUser&cid=$cid" : 'jsonUser');
  $search = HTML::form( array('method' =>'GET',
			      'class'=>'form-inline',
			      'id'=>'userSearchForm',
			      'action' => $_SERVER['PHP_SELF']),
			HiddenInputs( array('action'=>'showUsers')),
			HTML::input(array('type'=>'search',
					  'class'=>'form-control',
					  'name'=>'m',
					  'value'=>$_REQUEST['m'])),
			submitButton(_('Search')));
  if( isset( $cid ) )
    $search->pushContent( HiddenInputs( array('cid'=>$cid) ) );

  if( ! empty($_REQUEST['m']) && isset( $class ) )
    $h1 = Sprintf_('Current user accounts matching <q>%s</q> for <q>%s</q> at <q>%s</q>',
		   $_REQUEST['m'], $class['cname'], getInstitution( )->longname);
  else if( ! empty($_REQUEST['m']) )
    $h1 = Sprintf_('Current user accounts matching <q>%s</q> at <q>%s</q>',
		   $_REQUEST['m'], getInstitution( )->longname);
  else if( isset( $class ) )
    $h1 = Sprintf_('Current user accounts for <q>%s</q> at <q>%s</q>',
		   $class['cname'], getInstitution( )->longname);
  else
    $h1 = Sprintf_('Current user accounts at %s', getInstitution( )->longname);

  return HTML(HTML::h2( $h1 ),
	      $search,
	      $table,
	      HTML::br( ),
	      $links,
	      formButton(_('Back'), 'home'));
}


function lastQuery( ) {
  $q = array( );
  foreach( array('k', 'm', 'p', 'a', 'cid') as $f )
    if( isset( $_REQUEST[ $f ] ) )
      $q[ $f ] = $_REQUEST[ $f ];
  return $q;
}

function query( $upd = array() ) {
  $q = lastQuery( );
  foreach( $upd as $f => $new )
    $q[ $f ] = $new;
  return itemsToString( $q );
}


function editUser() {
  list( $userID ) = checkREQUEST( '_id' );
  ensureDBconnected( 'editUser' );

  if( isset( $_REQUEST['cid'] ) ) {
    $cid = (int)$_REQUEST['cid'];
    if( ! isset( $_SESSION['classes'] ) || ! isset( $_SESSION['classes'][ $cid ] ) )
      return homePage( _('The class you selected does not exist.') );
    $courseID = cidToClassId( $cid );
  }
  $classClause = isset( $courseID ) ? " AND courseID = $courseID" : '';
  $userTable   = isset( $courseID ) ? 'User NATURAL JOIN UserCourse' : 'User';

  $row = fetchOne( "SELECT * FROM $userTable WHERE userID = $userID$classClause" );
  if( ! $row )
    return HTML( warning( _('Cannot edit, as there is (suddenly?) no such user!') ),
		 BackButton());

  $form = HTML::form( array('method' =>'post',
			    'action' => "$_SERVER[PHP_SELF]?action=saveUser" ),
		      HiddenInputs( array('id'=>$userID )),
		      HiddenInputs( lastQuery( ) ));
  if( isset( $cid ) )
    $form->pushContent( HiddenInputs( array('cid'=>$cid)));

  $fset = HTML::div();

  foreach( array(_('Identity') =>'uident',
		 _('User name')=>'username',
		 _('Email')    =>'email',
		 _('Password') =>'password') as $name => $f )
    $fset->pushContent(FormGroup($f,
				 $name,
				 HTML::input(array('type'=>'text',
						   'value'=>isset($row[$f]) ? $row[$f] : ''))));
  global $gHaveAdmin;
  if ($gHaveAdmin)
    $fset->pushContent(RadioGroup('status',
				  _('Status'),
				  $row['status'],
				  array('active' =>_('Active (normal)'),
					'inactive'=> _('Inactive'))));
  
  if( true || isset( $courseID ) )
    $activity = '';
  else
    $activity = HTML(HTML::h3(_('Activity')),
		     userActivity( $userID ));

  $form->pushContent( $fset,
		      $activity,
		      ButtonToolbar(submitButton( _('Save') ),
				    CancelButton()));
  return HTML( HTML::h1(_('Edit user details')), $form);
}


function userActivity( $userID ) {
  $leftJoin = 'User '
    . ' LEFT JOIN UserCourse c ON User.userID=c.userID'
    . ' LEFT JOIN Reviewer r ON User.userID=r.reviewer'
    . ' LEFT JOIN Author a ON User.userID=a.author'
    . ' LEFT JOIN Stream s ON User.userID=s.who'
    . ' LEFT JOIN GroupUser g ON User.userID=g.userID'
    . ' LEFT JOIN Essay e ON User.userID=e.author'
    . ' LEFT JOIN Allocation la ON User.userID=la.author'
    . ' LEFT JOIN Allocation lr ON User.userID=lr.reviewer';
  
  $Aactivity = array( );
  $Lactivity = array( );
  $rs2 = checked_mysql_query( 'SELECT r.assmtID, a.assmtID, s.assmtID, g.assmtID, e.assmtID,'
			      . ' la.assmtID, lr.assmtID, ma.allocID, mc.allocID'
			      . " FROM $leftJoin"
			      . " WHERE User.userID = $userID" );
  while( $row = $rs2->fetch_row() ) {
    if( $row[0] != null ) $Aactivity[$row[0]][] = 'Non-student reviewer';
    if( $row[1] != null ) $Aactivity[$row[1]][] = 'Non-student author';
    if( $row[2] != null ) $Aactivity[$row[2]][] = 'Streamed';
    if( $row[3] != null ) $Aactivity[$row[3]][] = 'Group member';
    if( $row[4] != null ) $Aactivity[$row[4]][] = 'Submitted essay';
    if( $row[5] != null ) $Aactivity[$row[5]][] = 'Allocated as author';
    if( $row[6] != null ) $Aactivity[$row[6]][] = 'Allocated as reviewer';
  }
  $acts = HTML::ul( );
  foreach( $Aactivity as $assmtID => $desc ) {
    $cid = classIDToCid( fetchOne( "SELECT courseID FROM Assignment WHERE assmtID=$assmtID",
				    'courseID' ));
    $acts->pushContent( HTML::li( callback_url( className( $cid ), "selectClass&cid=$cid" ),
				  '/',
				  callback_url( "Assignment #$assmtID", "viewAsst&assmtID=$assmtID&cid=$cid"),
				  ' (' . join(', ', array_unique( $desc )) . ')' ));
  }
  foreach( $Lactivity as $allocID => $desc )
    $acts->pushContent( HTML::li( "Allocation $allocID (" . join(', ', array_unique( $desc )) . ')' ));
  return $acts;
}

function saveUser( ) {
  list( $id, $cid ) = checkREQUEST( '_id', '?_cid' );
  ensureDBconnected( 'saveUser' );

  $upd = array( 'instID'=>$_SESSION['instID'] );
  foreach( array('uident', 'username', 'email') as $f )
    if( isset( $_REQUEST[$f] ) )
      $upd[ $f ] = $_REQUEST[$f];
  if( ! empty( $_REQUEST['password'] ) )
    $upd['passwd'] = crypt($_REQUEST['password']);

  global $gHaveAdmin;
  if ($gHaveAdmin) {
    if ($_REQUEST['status'] == 'inactive')
      $upd['status'] = 'inactive';
    else if ($_REQUEST['status'] == 'active')
      $upd['status'] = 'active';
  }

  checked_mysql_query( makeUpdateQuery('User', $upd) . " WHERE userID=$id" );

  redirect( "showUsers&" . query( ) );
}

function sumRoles( $roles ) {
  $code = 0;
  global $gRoles;
  foreach( $gRoles as $r => $n )
    if( isset( $roles[ $r ] ) )
      $code += $n;
  return $code;
}


function makeSearchClause( $pat ) {
  $patX = strpos( $pat, '*' ) === false ? "$pat*" : $pat;
  $sqlpat = strtr( $patX, array('\*' => '*',
				'\?' => '?',
				'*'  =>'%',
				'?'  =>'_',
				'%'  =>'\%',
				'_'  =>'\_'));
  return '(uident LIKE ' . quote_smart( $sqlpat ) 
    . ' OR email LIKE ' . quote_smart( $sqlpat ) . ')';
}

// AJAX interface
function jsonUser( ) {
  list ($term, $cid) = checkREQUEST('query', '?_cid');
  $clause = array(makeSearchClause($term));
  if ($cid) {
    $userClass = 'inner join UserCourse uc on u.userID = uc.userID';
    $clause[] = 'uc.courseID = ' . cidToClassId($cid);
  } else
    $userClass = '';

  echo json_encode(fetchAll("select u.uident from User u $userClass"
			    . ' where ' . join(' and ', $clause)
			    . ' limit 20',
			    'uident'));
  exit;
}


function mergeUsers( ) {
  $warning = HTML( );
  if( isset( $_REQUEST['keep'] ) && isset( $_REQUEST['drop'] ) ) {
    $keep = $_REQUEST['keep'];
    $drop = $_REQUEST['drop'];
    if( intval( $keep ) && intval( $drop ) && isset( $_REQUEST['confirm'] ) ) {
      mergeTwoUsers( $keep, $drop );
      redirect( "editUser&id=$keep" );
    } else {
      $instID = (int)$_SESSION['instID'];
      $userKeep = fetchOne( "SELECT userID, uident, username, email FROM User WHERE instID=$instID AND uident=" . quote_smart( $keep ) );
      $userDrop = fetchOne( "SELECT userID, uident, username, email FROM User WHERE instID=$instID AND uident=" . quote_smart( $drop ) );
      if( ! $userKeep )
	$warning->pushContent( warning( Sprintf_('There is no such user %s', $keep )));
      if( ! $userDrop )
	$warning->pushContent( warning( Sprintf_('There is no such user %s', $drop )));

      if( $userKeep && $userDrop ) {
	$kd = array( );
	$dd = array( );
	foreach( array( 'username', 'email', 'userID') as $f ) {
	  if( ! empty( $userKeep[$f] ) )
	    $kd[] = "$f: " . $userKeep[$f];
	  if( ! empty( $userDrop[$f] ) )
	    $dd[] = "$f: " . $userDrop[$f];
	}
	$keepDetails = $userKeep['uident'] . " (" . join(", ", $kd ) . ")";
	$dropDetails = $userDrop['uident'] . " (" . join(", ", $dd ) . ")";
	return HTML( HTML::h1( _('Confirm merge of these two user accounts')),
		     HTML::p( Sprintf_('Merging %s into user %s ', $dropDetails, $keepDetails)),
		     HTML::p( Sprintf_('This operation will remove all trace of %s, and cannot be undone', $dropDetails)),
		     ButtonToolbar(formButton( 'Confirm', "mergeUsers&keep=$userKeep[userID]&drop=$userDrop[userID]&confirm=1" ),
				   CancelButton()),
		     HTML::h2( Sprintf_('Activity for %s', $dropDetails)),
		     userActivity( $userDrop['userID'] ),
		     HTML::h2( Sprintf_('Activity for %s', $keepDetails)),
		     userActivity( $userKeep['userID'] ));
      }
    }
  }
  autoCompleteWidget('jsonUser');
  return HTML(HTML::h1(_('Merge two user accounts')),
	      $warning,
	      HTML::form(array('method' =>'post',
			       'action' => "$_SERVER[PHP_SELF]?action=mergeUsers" ),
			 FormGroup('keepUser',
				   _('User account to keep'),
				   HTML::input(array('type'=>'text',
						     'class'=>'typeahead')),
				   _('User name')),
			 FormGroup('dropUser',
				   _('User account to drop'),
				   HTML::input(array('type'=>'text',
						     'class'=>'typeahead')),
				   _('User name')),
			 ButtonToolbar(submitButton(_('Merge')),
				       CancelButton())));
}


function mergeTwoUsers( $keep, $drop ) {
  set_time_limit( 20 );
  checked_mysql_query( "DELETE FROM User WHERE userID=$drop" );

  checked_mysql_query( "UPDATE Course SET cuserID=$keep WHERE cuserID=$drop" );

  checked_mysql_query( "UPDATE IGNORE UserCourse SET userID=$keep WHERE userID=$drop" );
  checked_mysql_query( "DELETE FROM UserCourse WHERE userID=$drop" );

  checked_mysql_query( "UPDATE Rubric SET owner=$keep WHERE owner=$drop" );

  checked_mysql_query( "UPDATE IGNORE Allocation SET reviewer=$keep WHERE reviewer=$drop" );
  checked_mysql_query( "UPDATE IGNORE Allocation SET author=$keep WHERE author=$drop" );
  checked_mysql_query( "DELETE FROM Allocation WHERE reviewer=$drop or author=$drop" );

  checked_mysql_query( "UPDATE IGNORE Reviewer SET reviewer=$keep WHERE reviewer=$drop" );
  checked_mysql_query( "DELETE FROM Reviewer WHERE reviewer=$drop" );

  checked_mysql_query( "UPDATE IGNORE Author SET author=$keep WHERE author=$drop" );
  checked_mysql_query( "DELETE FROM Author WHERE author=$drop" );

  checked_mysql_query( "UPDATE IGNORE Stream SET who=$keep WHERE who=$drop" );
  checked_mysql_query( "DELETE FROM Stream WHERE who=$drop" );

  checked_mysql_query( "UPDATE IGNORE GroupUser SET userID=$keep WHERE userID=$drop" );
  checked_mysql_query( "DELETE FROM GroupUser WHERE userID=$drop" );

  checked_mysql_query( "UPDATE IGNORE Extension SET who=$keep WHERE who=$drop" );
  checked_mysql_query( "DELETE FROM Extension WHERE who=$drop" );

  checked_mysql_query( "UPDATE Essay SET author=$keep WHERE author=$drop" );

  checked_mysql_query( "UPDATE Comment SET madeBy=$keep WHERE madeBy=$drop" );

  checked_mysql_query( "DELETE FROM Session WHERE userID=$drop" );
  checked_mysql_query( "UPDATE IGNORE SessionAudit SET userID=$keep WHERE userID=$drop" );
}


function unusedUsers( ) {
  $instID = (int)$_SESSION['instID'];

  $leftJoin = 'User '
    . ' LEFT JOIN UserCourse c ON User.userID=c.userID'
    . ' LEFT JOIN Reviewer r ON User.userID=r.reviewer'
    . ' LEFT JOIN Author a ON User.userID=a.author'
    . ' LEFT JOIN Stream s ON User.userID=s.who'
    . ' LEFT JOIN GroupUser g ON User.userID=g.userID'
    . ' LEFT JOIN Essay e ON User.userID=e.author'
    . ' LEFT JOIN Allocation la ON User.userID=la.author'
    . ' LEFT JOIN Allocation lr ON User.userID=lr.reviewer'
    . ' LEFT JOIN Comment mc ON User.userID=mc.madeBy';

  if( isset( $_REQUEST['all'] ) ) {
    checked_mysql_query( 'DELETE FROM User USING ' . $leftJoin
			 . " WHERE c.userID IS NULL AND instID=$instID AND c.userID<>'administrator'" );
    redirect( 'home' );
  } else if( isset( $_REQUEST['purge'] ) ) {
    checked_mysql_query( 'DELETE FROM User USING ' . $leftJoin
			 . ' WHERE c.userID IS NULL AND r.assmtID IS NULL AND a.assmtID IS NULL'
			 . ' AND s.assmtID IS NULL AND g.assmtID IS NULL AND e.assmtID IS NULL'
			 . ' AND la.assmtID IS NULL AND lr.assmtID IS NULL'
			 . ' AND mc.allocID IS NULL' );
    redirect( 'unusedUsers' );
  }

  $rs = checked_mysql_query( 'SELECT DISTINCT uident, User.userID FROM ' . $leftJoin
			     . " WHERE c.userID IS NULL AND instID=$instID AND c.userID<>'administrator'"
			     . ' ORDER BY uident');
  $ulP = HTML::ul( );
  $ulD = HTML::ul( );
  $someToPurge = false;
  $someToDelete = false;
  while( $user = $rs->fetch_assoc() ) {
    $someToDelete = true;
    $Aactivity = array( );
    $Lactivity = array( );
    $rs2 = checked_mysql_query( 'SELECT r.assmtID, a.assmtID, s.assmtID, g.assmtID, e.assmtID,'
				. ' la.assmtID, lr.assmtID, mc.allocID'
				. ' FROM ' . $leftJoin
				. ' WHERE User.userID = ' . $user['userID'] );
    while( $row = $rs2->fetch_row() ) {
      if( $row[0] != null ) $Aactivity[$row[0]][] = 'Non-student reviewer';
      if( $row[1] != null ) $Aactivity[$row[1]][] = 'Non-student author';
      if( $row[2] != null ) $Aactivity[$row[2]][] = 'Streamed';
      if( $row[3] != null ) $Aactivity[$row[3]][] = 'Group member';
      if( $row[4] != null ) $Aactivity[$row[4]][] = 'Submitted essay';
      if( $row[5] != null ) $Aactivity[$row[5]][] = 'Allocated as author';
      if( $row[6] != null ) $Aactivity[$row[6]][] = 'Allocated as reviewer';
      if( $row[8] != null ) $Lactivity[$row[8]][] = 'Commented';
    }
    if( empty( $Aactivity ) && empty( $Lactivity )) {
      $ulP->pushContent( HTML::li( HTML::q( $user['uident'] )));
      $someToPurge = true;
    } else {
      $a = HTML::ul( );
      foreach( $Aactivity as $assmtID => $desc ) {
	$cid = classIDToCid( fetchOne( "SELECT courseID FROM Assignment WHERE assmtID=$assmtID",
					'courseID' ));
	$a->pushContent( HTML::li( callback_url( className( $cid ), "selectClass&cid=$cid" ),
				   '/',
				   callback_url( "Assignment #$assmtID", "viewAsst&assmtID=$assmtID&cid=$cid"),
				   ' (' . join(', ', array_unique( $desc )) . ')' ));
      }
      foreach( $Lactivity as $allocID => $desc )
	$a->pushContent( HTML::li( "Allocation $allocID (" . join(', ', array_unique( $desc )) . ')' ));
      $ulD->pushContent( HTML::li( callback_url( $user['uident'], "editUser&id=$user[userID]" ), ', appears in: ', $a ) );
    }
  }
  if( $ulP->isEmpty( ) && $ulD->isEmpty( ))
    return HTML( HTML::h1(_('There are no unused user accounts')),
		 formButton( _('Home'), 'home' ));
  else
    return HTML( HTML::h1(_('Users who do not appear on any class list')),
		 $ulP, $ulD,
		 ($someToPurge  ? formButton( _('Purge unused accounts'), 'unusedUsers&purge' ) : ''),
		 ($someToDelete ? formButton( _('Delete all'), 'unusedUsers&all' ) : ''),
		 HTML::br( ),
		 BackButton());
}

function resetPassword( ) {
  list( $cid ) = checkREQUEST( '_cid' );
  $courseId = cidToClassId( $cid );

  require_once 'users.php';

  $db = ensureDBconnected('resetPassword');

  $uident = trim($_REQUEST['uident']);
  if ($uident != '') {
    $instID = (int)$_SESSION['instID'];
    $userId = fetchOne("select u.userId from User u"
		       . " inner join UserCourse uc on u.userID = uc.userID"
		       . " where uc.courseId = $courseId"
		       . " and u.instID = $instID"
		       . " and u.uident = " . quote_smart($uident),
		       'userId');
    if ($userId == null) {
      addWarningMessage(_('You user you have attempted to edit is not a member of this class.'));
      redirect('resetPassword', "cid=$cid");
    }
    else {
      $normUident = normaliseUident($uident);
      if ($uident !== $normUident)
	addWarningMessage(Sprintf_('The expected form of the user name is <b>$s</b>. A user with the name <b>%s</b> may not be able to log in.',
				   $normUident,
				   $uident)); 
      $data = array();
      if (!empty($_REQUEST['password']))
	$data['passwd'] = crypt($_REQUEST['password']);
      
      if (empty($data))
	addWarningMessage(_('The password cannot be blank.'));
      else {
	checked_mysql_query(makeUpdateQuery('User', $data) . " where userID = $userId");
	if ($db->affected_rows == 0)
	  addWarningMessage(_('No changes were made.'));
	else
	  addPendingMessage(Sprintf_('Successfully changed the password for <q>%s</q>', $uident));
      }
    }
    
    redirect( 'selectClass', "cid=$cid" );
  }

  autoCompleteWidget("jsonUser&cid=$cid");
  extraHeader('setTimeout(function(){$(".autocomplete-off").val("");}, 15)', 'onload');
  return HTML(HTML::h1(_('Reset password')),
	      pendingMessages(),
	      HTML::p(_('From this page, you can change the password for a student in your class.')),
	      HTML::form(array('method'=>'post',
			       'action'=>"$_SERVER[PHP_SELF]?action=resetPassword&cid=$cid",
			       'autocomplete'=>'off'),
			 FormGroup('uident',
				   _('User name'),
				   HTML::input(array('type' => 'text',
						     'autocomplete'=>'off',
						     'class'=>'typeahead autocomplete-off',
						     'value' => "")),
				   _('User name')),
			 FormGroup('password',
				   _('New password'),
				   HTML::input(array('type' =>'password',
						     'class'=>'autocomplete-off',
						     'autocomplete'=>'off',
						     'value' => ""))),
			 ButtonToolbar(submitButton(_('Save')),
				       Button(_('Cancel'), "selectClass&cid=$cid"))));
}
