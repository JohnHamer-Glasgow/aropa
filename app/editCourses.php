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

function showClasses( ) {
  ensureDBconnected( 'showClasses' );

  $table = table( array('id'=>'class-list') );
  $table->pushContent( HTML::tr( HTML::th( _('Name (include semester)')),
				 HTML::th( _('Access code')),
				 HTML::th( _('Instructor(s)')),
				 HTML::th( _('Active?'))));

  $instructorsByClass = array( );
  foreach( fetchAll( 'SELECT uident, courseID FROM UserCourse NATURAL JOIN User'
		     . ' WHERE (roles&8) <> 0'
		     . " AND instID = $_SESSION[instID]" ) as $row )
    $instructorsByClass[ $row['courseID'] ] .= "$row[uident]; ";

  // maxID is used to determine which table rows were added by the
  // user and which are existing table rows
  $rs = checked_mysql_query( 'SELECT courseID, cactive, cname, cident FROM Course'
			     . " WHERE instID = $_SESSION[instID]"
			     . ' ORDER BY cname' );
  $maxID = 0;
  while( $row = $rs->fetch_assoc() ) {
    $maxID = max( $maxID, $row['courseID'] );
    $row['instruct'] = $instructorsByClass[ $row['courseID'] ];
    $table->pushContent( makeEditClassTR( $row ));
  }
  $table->pushContent( makeEditClassTR( array('courseID'=>$maxID+1,
					       'cname'=>'',
					       'cident'=>'',
					       'instruct'=>'',
					       'cactive'=>1)));
  extraHeader('addRow.js', 'script');

  if( isset( $_REQUEST['saved'] ) )
    //- redirect after saving
    $saved = message( _('Changes saved') );

  return HTML( $saved,
	       HTML::h1( Sprintf_( 'Classes at <q>%s</q>', getInstitution( )->longname)),
	       HTML::form( array('method'=>'post',
				 'action'=>"$_SERVER[PHP_SELF]?action=saveClasses"),
			   HiddenInputs( array('maxID'=>$maxID) ),
			   $table,
			   submitButton( _('Save changes') ),
			   CancelButton()));
}


function makeEditClassTR( $row ) {
  return HTML::tr( HTML::td( HTML::input( array('type'=>'text',
						'name'=>"cname[$row[courseID]]",
						'size'=>30,
						'value'=>$row['cname'],
						'onblur'=>"maybeAddTableRow('class-list', true)")),
			     HTML::td( HTML::input( array('type'=>'text',
							  'name'=>"cident[$row[courseID]]",
							  'size'=>10,
							  'value'=>$row['cident'] ))),
			     HTML::td( HTML::input( array('type'=>'text',
							  'name'=>"instruct[$row[courseID]]",
							  'size'=>20,
							  'value'=>$row['instruct']))),
			     HTML::td( yesNoSelection( "cactive[$row[courseID]]", $row['cactive'] ))));
}


function saveClasses( ) {
  list( $maxID ) = checkREQUEST( '_maxID' );

  require_once 'users.php';

  $db = ensureDBconnected('saveClasses');

  $ivalues = array( );
  foreach( $_REQUEST['cname'] as $courseID => $cname ) {
    $courseID = (int)$courseID;
    if( $courseID > $maxID ) {
      if( trim($cname) == "" )
	continue;
      checked_mysql_query( makeInsertQuery( 'Course',
					    array('instID'=>$_SESSION['instID'],
						  'cname' => $cname,
						  'cident'=> $_REQUEST['cident'][ $courseID ],
						  'cactive'=>(int)$_REQUEST['cactive'][ $courseID ])));
      $courseID = $db->insert_id;
      addPendingMessage( Sprintf_('Added the class %s', $cname) );
    } else {
      checked_mysql_query( makeUpdateQuery( 'Course',
					    array('cname' => $cname,
						  'cident'=> $_REQUEST['cident'][ $courseID ],
						  'cactive'=>(int)$_REQUEST['cactive'][ $courseID ]))
			   . " WHERE courseID = $courseID" );
      addPendingMessage( Sprintf_('Edited the class %s', $cname) );
    }

    foreach( identitiesToUserIDs( preg_split( "/[ ,;\t]/", $_REQUEST['instruct'][ $courseID ], -1, PREG_SPLIT_NO_EMPTY ) )
	     as $userID )
      $ivalues[] = "($courseID, $userID, 8)";

  }
  
  if( ! empty( $ivalues ) ) {
    checked_mysql_query( 'UPDATE UserCourse NATURAL JOIN Course'
			 . ' SET roles = roles&7'
			 . ' WHERE (roles&8)<>0'
			 . " AND instID = $_SESSION[instID]" );
    checked_mysql_query( 'INSERT INTO UserCourse (courseID, userID, roles) VALUES'
			 . join(',', $ivalues)
			 . ' ON DUPLICATE KEY UPDATE roles = roles|8');
  }

  loadClasses( true );

  redirect( 'home' );
}



function editClass( ) {
  if( isBlessed( ) == -1 )
    return warning( _('You do not have rights to create or edit classes' ));

  global $gHaveAdmin;
  if( ! $gHaveAdmin )
    //- Only an administrator can set the class owner
    $_REQUEST['owner'] = '';

  list( $cid ) = checkREQUEST('_cid');
  
  $db = ensureDBconnected( 'editClass' );

  if( ! empty( $_REQUEST['cname'] ) ) {
    //- We are saving the details for a new class

    if( fetchOne( 'SELECT courseID FROM Course'
		  . ' WHERE instID = ' . (int)$_SESSION['instID']
		  . ($_REQUEST['courseID'] == 0 ? '' : ' AND courseID <> ' . cidToClassId( $cid ) )
		  . ' AND cname = ' . quote_smart( $_REQUEST['cname'] ) ) ) {
      addPendingMessage(Sprintf_('The class name <q>%s</q> is already in use.  Please choose another name.',
				 $_REQUEST['cname'] ) );
      return editClassCommon($cid, $_REQUEST );
    }

    if( $_REQUEST['courseID'] == 0 ) {
      //- Saving details for a new class.
      $fields = array('cname'   => trim($_REQUEST['cname']),
		      'cident'  => trim($_REQUEST['cident']),
		      'subject' => trim($_REQUEST['subject']),
		      'instID'  => $_SESSION['instID'],
		      'cactive' => 1);
      if( ! empty( $_REQUEST['owner'] ) ) {
	require_once 'users.php';
	$fields['cuserID'] = uidentToUserID( $_REQUEST['owner'] );
      } else
	$fields['cuserID'] = $_SESSION['userID'];

      checked_mysql_query( makeInsertQuery( 'Course', $fields) );
      $courseID = $db->insert_id;
    } else {
      //- Editing an existing class
      $courseID =  cidToClassId( $cid );
      $fields = array('cname'   => $_REQUEST['cname'],
		      'subject' => $_REQUEST['subject'],
		      'cident'  => $_REQUEST['cident'] );
      if( ! empty( $_REQUEST['owner'] ) ) {
	require_once 'users.php';
	$fields['cuserID'] = uidentToUserID( $_REQUEST['owner'] );
      }
      
      checked_mysql_query( makeUpdateQuery( 'Course', $fields ) . " WHERE courseID = $courseID" );
    }

    //- The class owner is always the instructor
    if( isset( $fields['cuserID'] ) )
      checked_mysql_query( "INSERT INTO UserCourse SET userID=$fields[cuserID], courseID=$courseID, roles=8"
			   . " ON DUPLICATE KEY UPDATE roles=8" );

    //- Force reload of classes
    loadClasses( true );
    redirect( 'selectClass&cid=' . classIDToCid( $courseID ) );
  }
  
  $courseID = cidToClassId( $cid );
  $row = fetchOne( "SELECT * FROM Course WHERE courseID = $courseID" );
  if( $row )
    return editClassCommon( $cid, $row );
  else
    return warning( _('There was a problem finding the class you selected.') );
}

function newClass( ) {
  //- The cid is only needed to prove the user has instructor access
  //- rights for _some_ class.
  //- 2011-07-08: restrict new class creation to administrators and "blessed" instructors
  $rights = isBlessed( );
  if( $rights != -1 )
    return editClassCommon( classIDToCid( $rights ), array('courseID'=>0, 'cname'=>'', 'cident'=>'', 'cuserID'=>-1));
  else
    return warning( _('You do not have rights to create a new class') );
}


function classInUseScript($cid, $courseId) {
  return '$("#cname").change(function() {
$.get("' . "$_SERVER[PHP_SELF]?action=classNameInUse" . "\", { cid: $cid, cname: this.value, courseID: $courseId },"
    . 'function(data) {
 if($.parseJSON(data))
    $("#inUse").show();
 else
    $("#inUse").hide();
})})';
}


function editClassCommon( $cid, $class ) {
  $cid = (int)$cid;
  extraHeader(classInUseScript($cid, $class['courseID']), 'onload');

  global $gHaveAdmin;
  if( $class['cuserID'] != -1 )
    $owner = fetchOne( "SELECT uident FROM User WHERE userID=" . (int)$class['cuserID'], 'uident' );
  else
    $owner = '';
  if ($gHaveAdmin) {
    $ownerHTML =
      FormGroup(
	'owner',
	_('Class owner'),
	HTML::input(
	  array('type' => 'text',
		'class' => 'typeahead',
		'id' => 'owner',
		'data-provide' => 'typeahead',
		'value' => $owner)));
    autoCompleteWidget('jsonIdent');
  } else
    $ownerHTML = HTML::label(Sprintf_('Class owner: %s', $owner));

  global $subjects;
  $subjectSelection = HTML::select(array('class'=>'form control'));
  foreach ($subjects as $s) {
    $opt = HTML::option($s);
    if ($s == $class['subject'])
      $opt->setAttr('selected', 'selected');
    $subjectSelection->pushContent($opt);
  }
  
  return HTML(HTML::h1($class['courseID'] == 0 ? _('Add a new class') :_('Edit class details')),
	      pendingMessages(),
	      HTML::form(array('method' =>'post',
			       'action' => "$_SERVER[PHP_SELF]?action=editClass" ),
			 HiddenInputs(array('cid'=>$cid, 'courseID'=>$class['courseID'])),
			 FormGroup('cname',
				   _('Class name'),
				   HTML::input(array('type'=>'text', 'id'=>'cname', 'value'=>$class['cname'])),
				   null,
				   _('The class name should usually include the name of the course, the year and the semester.')),
			 HTML::div(array('id'=>'inUse', 'style'=>'display:none'),
				   warning(_('You have entered the same name as an existing class.  Please choose another name.'))),
			 FormGroup('cident',
				   _('Access code'),
				   HTML::input(array('type'=>'text', 'value'=>$class['cident'])),
				   null,
				   _('The Access code is a temporary password for first-time users, who must immediately set their own password.')),
			 $ownerHTML,
                         FormGroup('subject', _('Subject area'), $subjectSelection),
			 HTML::br(),
			 ButtonToolbar(submitButton(_('Save')),
				       CancelButton())));
}

function classNameInUse( ) {
  if( empty( $_REQUEST['cname'] ) )
    $inuse = false;
  else
    $inuse = fetchOne( 'SELECT cname FROM Course'
		       . ' WHERE instID=' . (int)$_SESSION['instID']
		       . ' AND courseID <> ' . (int)$_REQUEST['courseID']
		       . ' AND cname = ' . quote_smart( $_REQUEST['cname'] ));
  echo json_encode( $inuse );
  exit;
}

global $subjects;
$subjects = array('',
                  'Academic Practice',
                  'Allied Health',
                  'Anthropology',
                  'Biology',
                  'Chemistry',
                  'Classics',
                  'Computing Science',
                  'Dentistry',
                  'Economics',
                  'Education',
                  'Engineering',
                  'English Language',
                  'English Literature',
                  'Film Studies',
                  'Finance',
                  'Geography',
                  'Geology',
                  'Health Science',
		  'History',
                  'Law',
                  'Management',
		  'Mathematics',
                  'Medicine',
                  'Modern Languages',
                  'Music',
                  'Nursing',
                  'Pharmacology',
                  'Pharmacy',
                  'Physics',
                  'Politics',
                  'Psychology',
                  'Public Health',
                  'Public Policy',
                  'Research Skills',
                  'Social Science',
                  'Sociology',
		  'Statistics',
                  'Veterinary Science',
                  'Test',
                  'Other');

function resetClassAccess() {
  QueryTrace();
  list($cid) = checkREQUEST('_cid');
  $courseID = cidToClassId($cid);
  if (isset($_REQUEST['confirm'])) {
    $db = ensureDBconnected('resetClassAccess');
    checked_mysql_query(
      'update User u inner join UserCourse uc on u.userID = uc.userID'
      . ' set u.lastChanged = null'
      . " where uc.courseID = $courseID and uc.roles = 1 and u.instID = $_SESSION[instID]");
    redirect('selectClass', "cid=$cid");
  } else 
    return HTML(
      HTML::h1(_('Reset class access')),
      HTML::p(_('This utility will restore the ability for all students in the class to login using the class access code.')),
      HTML::p(_('Usually, students cannot use the access code a second time, after they have set their password.')),
      HTML::br(),
      ButtonToolbar(Button(_('Proceed'), "resetClassAccess&confirm&cid=$cid"), CancelButton()));
}

function cloneClass() {
  if( isBlessed( ) == -1 )
    return warning( _('You do not have rights to create or edit classes' ));

  list($cid, $cname, $cident, $subject) = checkREQUEST( '_cid', '?cname', '?cident', '?subject' );

  if (!isset($_SESSION['classes'][$cid])) {
    addPendingMessage(warning(_('You have attempted to clone a class you do not have access to.')));
    redirect('home');
  }

  $db = ensureDBconnected('cloneClass');

  $class = $_SESSION['classes'][$cid];
  if (empty($subject)) {
    $subject = fetchOne("select subject from Course where courseId = $class[courseID]", 'subject');
    if (empty($subject))
      $subject = 'Other';
    if (empty($cname)) $cname = "Copy of $class[cname]";
    extraHeader(classInUseScript($cid, -1), 'onload');
    return HTML(HTML::h1(Sprintf_('Create a copy of %s', $class['cname'])),
		pendingMessages(),
		HTML::p(_('This operation will copy all the assignment specifications from an existing class into a new class.'
			  . ' You will still need to enter the new class list, set the assignment dates and activate the assignments.')),
		HTML::form(array('method'=>'post',
				 'action'=>"$_SERVER[PHP_SELF]?action=cloneClass"),
			   HiddenInputs(array('cid'=>$cid)),
			   FormGroup('cname',
				     _('New class name'),
				     HTML::input(array('type'=>'text',
						       'id'=>'cname',
						       'value'=>$cname,
						       'autofocus'=>true)),
				     null,
				    _('The class name should usually include the name of the course, the year and the semester.')),
			  HTML::div(array('id'=>'inUse', 'style'=>'display:none'),
				    warning(_('You have entered the same name as an existing class.  Please choose another name.'))),
			  FormGroup('cident',
				    _('Access code'),
				    HTML::input(array('type'=>'text', 'value'=>$cident)),
				    null,
				    _('The Access code is a temporary password for first-time users, who must immediately set their own password.')),
			   HTML::br(),
			   HiddenInputs(array('subject' => $subject)),
			   ButtonToolbar(submitButton(_('Save')),
					 CancelButton())));
  } else {
    if (fetchOne("select 1 from Course where instID = $_SESSION[instID]"
		 . ' AND cname = ' . quote_smart($cname)) != null) {
      addPendingMessage(warning(Sprintf_('The class name %s is in use. Please use a different name', $cname)));
      redirect("cloneClass&cid=$cid&cident=$cident&cname=$cname");
    }

    checked_mysql_query(makeInsertQuery('Course',
					array('instID'  => $_SESSION['instID'],
					      'cuserID' => $_SESSION['userID'],
					      'cname'   => $cname,
					      'cident'  => $cident,
                                              'subject' => $subject,
					      'cactive' => true)));
    $newClassId = $db->insert_id;
    $classId = $class['courseID'];
    checked_mysql_query('insert into UserCourse (courseID, userID, roles)'
			. " select $newClassId, userID, roles"
			. " from UserCourse where courseID = $classId and roles <> 1");
    loadClasses(true);

    $mappings = array();
    $assmts = fetchAll("select * from Assignment where courseID = $classId");
    foreach ($assmts as $assmt) {
      checked_mysql_query(makeInsertQuery('Assignment',
					  array('courseID' => $newClassId,
						'isActive' => false,
						'aname' => $assmt['aname'],
						'selfReview' => $assmt['selfReview'],
						'allocationType' => $assmt['allocationType'],
						'authorsAre' => $assmt['authorsAre'],
						'reviewersAre' => $assmt['reviewersAre'],
						'anonymousReview' => $assmt['anonymousReview'],
						'showMarksInFeedback' => $assmt['showMarksInFeedback'],
						'hasReviewEvaluation' => $assmt['hasReviewEvaluation'],
						'allowLocking' => $assmt['allowLocking'],
						'showReviewMarkingFeedback' => $assmt['showReviewMarkingFeedback'],
						'submissionText' => $assmt['submissionText'],
						'rubricID' => $assmt['rubricID'],
						'markItems' => $assmt['markItems'],
						'markGrades' => $assmt['markGrades'],
						'markLabels' => $assmt['markLabels'],
						'commentItems' => $assmt['commentItems'],
						'commentLabels' => $assmt['commentLabels'],
						'nReviewFiles' => $assmt['nReviewFiles'],
						'nPerReviewer' => $assmt['nPerReviewer'],
						'nPerSubmission' => $assmt['nPerSubmission'],
						'reviewerMarking' => $assmt['reviewerMarking'],
						'restrictFeedback' => $assmt['restrictFeedback'],
						'nStreams' => $assmt['nStreams'],
						'tags' => $assmt['tags'],
						'visibleReviewers' => $assmt['visibleReviewers'],
						'submissionRequirements' => $assmt['submissionRequirements'])));
      $mappings[$assmt['assmtID']] = $db->insert_id;
    }

    foreach ($assmts as $assmt)
      if (!empty($assmt['isReviewsFor']) && !empty($mappings[$assmt['isReviewsFor']]))
	checked_mysql_query('update Assignment set isReviewsFor = ' . $mappings[$assmt['isReviewsFor']] . ' where assmtID = ' . $mappings[$assmt['assmtID']]);

    redirect('selectClass&cid=' . classIDToCid($newClassId));
  }
}


function deleteClass( ) {
  list($cid) = checkREQUEST( '_cid' );
  $classId = cidToClassId($cid);
  $className = className($cid);

  if (!isset( $_REQUEST['confirm']))
    return HTML( HTML::h1(Sprintf_('Delete the class <q>%s</q>', $className)),
		 HTML::form(array('method'=>'post',
				  'action'=>"$_SERVER[PHP_SELF]?action=deleteClass"),
			    HiddenInputs(array('cid'=>$cid, 'confirm'=>true)),
			    warning(_('This action will completely delete the class, and cannot be undone.')),
			    ButtonToolbar(submitButton(_('Delete')),
					  CancelButton())));

  require_once 'Delete.php';
  hardDeleteClass($classId);
  addPendingMessage(Sprintf_('The class %s has been deleted', $className));
  loadClasses(true);
  redirect('home');
}