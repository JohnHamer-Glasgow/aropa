<?php
/*
    Copyright (C) 2014 John Hamer <John.Hamer@glasgow.ac.uk>

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

if( $gHaveAdmin ) {
  $CmdActions['importLTC']
    = array( 'load'=>'import-LTC.php' );

  $CmdActions['essaysToFiles'] = array('load' => 'essaysToFiles.php');
  $CmdActions['fixupEssayUrl'] = array('load' => 'essaysToFiles.php');
  
  $CmdActions['cloneAsTestClass'] = array( );

  $CmdActions['unusedUsers']
    = array( 'name'  =>_('Orphaned users'),
	     'menu'  =>'advanced',
	     'load'=>'editUser.php' );

  $CmdActions['mergeUsers']
    = array( 'name'  =>_('Merge users'),
	     'menu'  =>'advanced',
	     'load'=>'editUser.php' );

  if( function_exists('showCurrentUsers' ) )
    $CmdActions['showCurrentUsers']
      = array( 'name'=>_('Show Current Users'),
	       'menu'  =>'advanced');

  $CmdActions['showInstitutions']
    = array( 'name'  =>_('Institutions'),
	     'menu'  =>'advanced',
	     'load'  =>'editInstitutions.php' );
  $CmdActions['editInstitution']
    = array( 'title' =>_('Edit Institution'),
	     'load'  =>'editInstitutions.php' );
  $CmdActions['saveInstitution']
    = array( 'load'  =>'editInstitutions.php' );
  $CmdActions['moveClass']
    = array( 'load'  =>'editInstitutions.php' );
  $CmdActions['changeInstitution']
    = array( 'load'  => 'editInstitutions.php' );
  $CmdActions['normaliseUserRecords']
    = array( 'load'  => 'editInstitutions.php' );

  $CmdActions['showUsers']
    = array( 'name'=>'Users',
	     'menu'=>'advanced',
	     'load'=>'editUser.php');
  $CmdActions['editUser']
    = array( 'load'=>'editUser.php');
  $CmdActions['saveUser']
    = array( 'load'=>'editUser.php');
  $CmdActions['jsonUser']
    = array( 'load'=>'editUser.php');
  
  $CmdActions['showClasses']
    = array( 'name'  =>_('Classes'),
	     'load'  =>'editCourses.php',
	     'menu'  =>'advanced' );
  $CmdActions['saveClasses']
    = array( 'load'  =>'editCourses.php' );
  $CmdActions['deleteClass']
    = array( 'load'  =>'editCourses.php');

  $CmdActions['browseRubrics']
    = array('name'   =>_('Browse rubrics'),
	    'menu'   =>'advanced',
	    'load'   =>'editRubric.php');
  $CmdActions['viewRubricA']
    = array( 'load'  =>'editRubric.php' );
  $CmdActions['viewRubricS']
    = array( 'load'  =>'editRubric.php' );

  $CmdActions['impersonateAnyUser']
    = array( 'name'=>_('Impersonate any user'),
	     'call'=>'loginAsStudent',
	     'menu'=>'special',
	     'load' => 'loginAsStudent.php' );
  $CmdActions['jsonIdent']
    = array( 'load' => 'loginAsStudent.php' );

  
  $CmdActions['showSessionAudit']
    = array( 'name'  =>'Session audit',
	     'load'  =>'showSessionAudit.php',
	     'menu'  =>'advanced' );
  
  $CmdActions['logQueries']
    = array( 'name'=>_('Log Queries'),
	     'call'=>'toggleLogQueries',
	     'menu'=>'special' );

  $CmdActions['backup']
    = array( 'name'=>_('Export'),
	     'call'=>'exportData',
	     'load'=>'dumpDatabase.php',
	     'menu'=>'special' );

  $CmdActions['dump_session']
    = array('name'  =>_('Show $_SESSION'),
	    'menu'  =>'special' );

  $CmdActions['reports']
    = array('name'  =>_('Reports'),
	    'load'  => 'Reports.php',
	    'menu'  =>'special' );

  $CmdActions['exportClass']
    = array('load'=>'exportClass.php');
  $CmdActions['doExportClass']
    = array('load'=>'exportClass.php');
  $CmdActions['importClass']
    = array('name'=>_('Import XML class'),
	    'menu'=>'special',
	    'load'=>'exportClass.php');
  $CmdActions['doImportClass']
    = array('load'=>'exportClass.php');
  $CmdActions['checkImportClassName']
    = array('load'=>'exportClass.php');
  $CmdActions['populateSubmissions'] = array();
}


/* ENTRY POINT */
function cloneAsTestClass( ) {
  list( $cid, $instID, $cname, $owner, $cident, $subject, $p, $dateShift )
    = checkREQUEST( '_cid', '?_instID', '?cname', '?owner', '?cident', '?subject', '?p', '?d' );
  
  $classID = cidToClassId( $cid );
  if( $classID == -1 )
    return warning( _('That class is not available') );

  if( $instID != 0 && !empty($owner) && !empty($cname) && !empty($p) ) {
    $newClassID = cloneAsTestClassInternal( $classID, $instID, $cname, $owner, $cident, $subject, $p, $dateShift );
    if( $instID == $_SESSION['instID'] ) {
      loadClasses( true );
      redirect( "selectClass&cid=" . classIDToCid( $newClassID ) );
    } else
      redirect( 'home' );
  } else {
    if ($instID != 0 || !empty($owner) || !empty($cname) || !empty($p) )
      $msg = warning(_('Please fill in all fields'));
    else
      $msg = '';

    if( $instID == 0 ) $instID = $_SESSION['instID'];
    $institutions = HTML::select( array('name'=>'instID') );
    foreach( fetchAll( 'SELECT instID, longname FROM Institution ORDER BY longname ASC' ) as $inst )
      $institutions->pushContent( HTML::option( array('value'=>$inst['instID'],
						      'selected'=>$instID==$inst['instID']),
						$inst['longname'] ));

    if( ! is_array( $dateShift ) ) $dateShift = array( );
    $assmts = HTML::ul(array('class'=>'list-unstyled'));
    foreach (fetchAll("select assmtID, aname from Assignment where courseID=$classID order by aname" ) as $assmt) {
      if (!isset($dateShift[$assmt['assmtID']]))
	$dateShift[$assmt['assmtID']] = 'none';
      $assmts->pushContent(HTML::li($assmt['aname'], ' ', dateDiffPicker($assmt['assmtID'], $dateShift)));
    }
    
    return HTML(HTML::h1(Sprintf_('Clone test class from %s', className($cid)) ),
		$msg,
		HTML::form(array('method'=>'post',
				 'action'=>"$_SERVER[PHP_SELF]?action=cloneAsTestClass&cid=$cid"),
			   HiddenInputs(array('subject' => $_SESSION['classes'][$cid]['subject'])),
			   FormGroup('instID',
				     _('Institution'),
				     $institutions),
			   FormGroup('owner',
				     _('Owner'),
				     HTML::input(array('type'=>'text', 'value'=>$owner))),
			   FormGroup('cname',
				     _('Class name'),
				     HTML::input(array('type'=>'text', 'value'=>$cname ))),
			   FormGroup('cident',
				     _('Access code'),
				     HTML::input(array('type'=>'text', 'value'=>$cident ))),
			   FormGroup('p',
				     _('Student name prefix'),
				     HTML::input(array('type'=>'text', 'value'=>$p))),
			   HTML::h3(_('Shift dates for assignments')),
			   $assmts,
			   ButtonToolbar(submitButton(_('Create')),
					 CancelButton())));
  }
}

function dateDiffPicker( $assmtID, $dateShift ) {
  return HTML::select( array('name'=>"d[$assmtID]"),
		       HTML::option( array('value'=>'pre',  'selected'=>$dateShift[$assmtID] == 'pre'), _('Pre-review')),
		       HTML::option( array('value'=>'mid',  'selected'=>$dateShift[$assmtID] == 'mid'), _('Mid-review')),
		       HTML::option( array('value'=>'post', 'selected'=>$dateShift[$assmtID] == 'post'), _('Post-review')),
		       HTML::option( array('value'=>'none', 'selected'=>$dateShift[$assmtID] == 'none'), _('No change')));
}

function adjustDates( &$row, $dateDiff, $fields ) {
  if( $dateDiff )
    foreach( $fields as $dateField )
      if( ! empty( $row[$dateField] ) )
	$row[ $dateField ] = date('Y-m-d H:i:s', strtotime($row[$dateField]) + $dateDiff);
}


function cloneAsTestClassInternal($baseCourseID, $instID, $cname, $owner, $cident, $subject, $unamePat, $dateShift ) {
  require_once 'users.php';

  set_time_limit(0);

  $userMap = array( );
  $ownerID = uidentToUserID( $owner, $instID );

  $db = ensureDBconnected();

  // Ensure we generate new user names that don't clash with any
  // existing ones. Take the largest number that matches the user name
  // pattern, and generate new user names with larger numeric suffixes.
  $offset = intval( preg_replace( '/.*\D(\d+)$/', '$1',
				  fetchOne( "SELECT MAX(uident) AS u FROM User WHERE instID=$instID AND uident RLIKE "
					    . quote_smart('^' . preg_quote($unamePat) . '[[:digit:]]+$'), 'u' ) ));

  $newCourseID = fetchOne( "SELECT courseID FROM Course WHERE instID=$instID AND cname=" . quote_smart($cname), 'courseID');
  if( empty( $newCourseID ) ) {
    checked_mysql_query( "INSERT INTO Course (instID,cname,cident,subject,cuserID) VALUES ($instID,"
                         . quote_smart($cname)
			 . "," . quote_smart($cident)
			 . "," . quote_smart($subject)
			 . ",$ownerID)" );
    $newCourseID = $db->insert_id;
  }

  foreach( fetchAll( "SELECT userID FROM UserCourse WHERE courseID=$baseCourseID AND roles=1" ) as $uc )
    mapUser( $uc['userID'], $instID, $userMap, $unamePat, $offset );

  $oldNewMap = array();
  $oldReviewRating = array();
  foreach( fetchAll( "SELECT * FROM Assignment WHERE courseID=$baseCourseID ORDER BY submissionEnd ASC" ) as $assmt ) {
    switch( $dateShift[ $assmt['assmtID'] ] ) {
    case 'pre':
      $dateDiff = strtotime('+ 1 day') - strtotime($assmt['submissionEnd']);
      break;
    case 'mid':
      $dateDiff = strtotime('- 1 day') - strtotime($assmt['submissionEnd']);
      break;
    case 'post':
      $dateDiff = strtotime('- 1 day') - strtotime($assmt['reviewEnd']);
      break;
    default:
      $dateDiff = null;
    }

    $baseAssmtID = $assmt['assmtID'];
    unset( $assmt['assmtID'] );
    $assmt['courseID'] = $newCourseID;
    adjustDates( $assmt, $dateDiff, array('submissionEnd', 'reviewEnd'));
    checked_mysql_query( makeInsertQuery( 'Assignment', $assmt ) );
    $newAssmtID = $db->insert_id;
    $oldNewMap[$baseAssmtID] = $newAssmtID;
    if (!empty($assmt['isReviewsFor']))
      $oldReviewRating[$newAssmtID] = $assmt['isReviewsFor'];

    foreach( fetchAll( "SELECT * FROM Essay WHERE assmtID=$baseAssmtID" ) as $essay ) {
      $oldEssayID = $essay['essayID'];
      unset( $essay['essayID'] );
      $essay['assmtID'] = $newAssmtID;
      $essay['author'] = mapUser( $essay['author'], $instID, $userMap, $unamePat, $offset );
      adjustDates( $essay, $dateDiff, array('lastDownloaded' ) );
      checked_mysql_query( makeInsertQuery( 'Essay', $essay ) );
      $newEssayID = $db->insert_id;
      checked_mysql_query( "INSERT INTO Overflow (essayID,seq,data) SELECT $newEssayID,seq,data FROM Overflow WHERE essayID=$oldEssayID" );
    }

    foreach( fetchAll( "SELECT * FROM Allocation WHERE assmtID=$baseAssmtID" ) as $alloc ) {
      $baseAllocID = $alloc['allocID'];
      unset( $alloc['allocID'] );
      $alloc['assmtID'] = $newAssmtID;
      $alloc['author']   = mapUser( $alloc['author'],   $instID, $userMap, $unamePat, $offset );
      $alloc['reviewer'] = mapUser( $alloc['reviewer'], $instID, $userMap, $unamePat, $offset );
      adjustDates( $alloc, $dateDiff, array('lastViewed', 'lastMarked', 'lastResponse', 'lastSeen'));
      checked_mysql_query( makeInsertQuery( 'Allocation', $alloc ) );
      $newAllocID = $db->insert_id;

      foreach( fetchAll( "SELECT * FROM Comment WHERE allocID=$baseAllocID" ) as $comment ) {
	$comment['allocID'] = $newAllocID;
	$comment['madeBy'] = mapUser( $comment['madeBy'], $instID, $userMap, $unamePat, $offset );
	adjustDates( $comment, $dateDiff, array('whenMade'));
	checked_mysql_query( makeInsertQuery( 'Comment', $comment ) );
      }
    }

    foreach (fetchAll("select * from Extension where assmtID = $baseAssmtID") as $extension)
      if (isset($userMap[$extension['who']])) {
	$extension['assmtID'] = $newAssmtID;
	$extension['who'] = $userMap[$extension['who']];
	adjustDates($extension, $dateDiff, array('submissionEnd', 'reviewEnd', 'whenMade'));
	checked_mysql_query(makeInsertQuery('Extension', $extension));
      }

    $groupIdMap = array();
    foreach (fetchAll("select * from `Groups` where assmtID = $baseAssmtID") as $group) {
      $baseGroupID = $group['groupID'];
      unset($group['groupID']);
      $group['assmtID'] = $newAssmtID;
      checked_mysql_query(makeInsertQuery('`Groups`', $group));
      $groupIdMap[$baseGroupID] = $db->insert_id;
    }
    
    $groupUsers = array();
    foreach (fetchAll("select groupID, userID from GroupUser where assmtID = $baseAssmtID") as $gu)
      if (isset($groupIdMap[$gu['groupID']]) && isset($userMap[$gu['userID']]))
	$groupUsers[] = "($newAssmtID," . $groupIdMap[$gu['groupID']] . ',' . $userMap[$gu['userID']] . ')';
    if (!empty($groupUsers))
      checked_mysql_query('insert into GroupUser (assmtID, groupID, userID)'
			  . ' values ' . join(',', $groupUsers));
  }

  foreach ($oldReviewRating as $new => $old)
    checked_mysql_query( 'update Assignment set isReviewsFor = ' . quote_smart($oldNewMap[$old])
			 . " where assmtID = $new");


  $values = array( );
  foreach( $userMap as $newUserID )
    $values[] = "($newUserID, $newCourseID)";
  if( ! empty( $values ) )
    checked_mysql_query( 'INSERT IGNORE INTO UserCourse (userID,courseID) VALUES ' . join($values, ',') );

  checked_mysql_query( "INSERT IGNORE INTO UserCourse (userID,courseID,roles) VALUES ($ownerID, $newCourseID, 8)" );
  return $newCourseID;
}


function mapUser( $userID, $instID, &$map, $unamePat, $offset ) {
  if( ! isset( $map[$userID] ) )
    $map[ $userID ] = uidentToUserID( sprintf( "$unamePat%02d", count( $map ) + $offset + 1 ), $instID );
  return $map[ $userID ];
}


function dump_session( ) {
  $sess = $_SESSION;
  unset( $sess['revert'] );
  return HTML::pre( print_r( $sess, true ) );
}

/* ENTRY POINT */
function populateSubmissions() {
  list($assmt, $assmtId, $cid) = selectAssmt();
  
  if (!$assmt)
    return warning(Sprintf_('There is no assignment #%d for the class <q>%s</q>', $assmtID, className($cid)));


  $existing =
    fetchAll("select uident from Essay e inner join User u on e.author = u.userID where e.assmtID = $assmtId", 'uident');
  
  if (empty($_REQUEST['ids']))    
    return
      HTML(
	assmtHeading(_('Populate submissions'), $assmt),
	HTML::form(
	  array(
	    'method'=>'post',
	    'class' =>'form',
	    'enctype'=>'multipart/form-data',
	    'action'=>"$_SERVER[PHP_SELF]?action=populateSubmissions&assmtID=$assmtId&cid=$cid"),
	  FormGroup(
	    'ids',
	    _('Enter the names of submitting students'),
	    HTML::textarea(array('type' => 'text', 'rows' => 30))),
	  ButtonToolbar(
	    submitButton(_('Save')),
	    CancelButton())),
	HTML::h2(_('The following students have already submitted:')),
	HTML::p(HTML::raw(join("<br/>", $existing))));
  
  require_once 'users.php';
  $count = 0;
  foreach (linesToToUserIDs($_REQUEST['ids']) as $userId)
    if (!in_array($userId, $existing)) {
      checked_mysql_query(
	'insert into Essay (assmtID, reqIndex, author, extn, description, whenUploaded, compressed, essay) values'
	. " ($assmtId, 1, $userId, 'inline-text', 'Automatic submission', now(), false, 'This submission was automatically generated for testing purposes.')");
      $count++;
    }

  addPendingMessage(Sprintf_('Uploaded %d submissions', $count));
  redirect("viewAsst&assmtID=$assmtId&cid=$cid");
}
