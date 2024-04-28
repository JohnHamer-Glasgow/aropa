<?php
/*
  Copyright (C) 2017 John Hamer <J.Hamer@acm.org>

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
  General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307
  USA
*/

//- Edit a new or existing assignment.
function newAssignment( ) {
  list( $cid ) = checkREQUEST( '_cid' );
  $assmt = array( 'courseID'        => cidToClassId( $cid ),
		  'anonymousReview' => 1,
		  'selfReview'      => 0,
		  'restrictFeedback'=>'none',
		  'showMarksInFeedback'=>1);
  return editAssignmentCommon( $assmt, null, $cid );
}


function createReview( ) {
  list( $cid, $assmtID ) = checkREQUEST( '_cid', '_assmtID' );

  $assmt = fetchOne( 'SELECT aname FROM Assignment'
		     . " WHERE assmtID = $assmtID"
		     . ' AND courseID = ' . cidToClassId( $cid ) );
  if( ! $assmt )
    return warning( Sprintf_('There is no assignment #%d for the class <q>%s</q>', $assmtID, className( $cid )));

  $assmtReview = array( 'courseID'     	=> cidToClassId( $cid ),
			'anonymousReview' => 1,
			'selfReview'      => 0,
			'showMarksInFeedback' => 1,
			'authorsAre'    => 'review',
			'isReviewsFor' 	=> $assmtID,
			'aname'         => Sprintf_('Review marking for %s', $assmt['aname']));

  return editAssignmentReview( $assmtReview, null, $cid );
}



function editAssignment( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );

  if( ! $assmt )
    return warning( sprintf( _('There is no assignment #%d for the class %s'), $code, className( $cid )));

  return $assmt['isReviewsFor']
    ? editAssignmentReview( $assmt, $assmtID, $cid )
    : editAssignmentCommon( $assmt, $assmtID, $cid );
}


function editAssignmentCommon( &$assmt, $assmtID, $cid ) {
  ensureDBconnected( 'editAssignmentCommon' );

  require 'Util.php';
  $maxFileSize = memToBytes(ini_get('upload_max_filesize'));
  if (empty($maxFileSize))
    $maxFileSize = 1e6;

  $saveCancel = ButtonToolbar(submitButton(_('Save')),
			      CancelButton());
  datetimeWidget();

  $submitDate = ToRFC3339($assmt['submissionEnd']);
  $reviewDate = ToRFC3339($assmt['reviewEnd']);
  $identification = HTML(FormGroup('aname',
				   _('Assignment name'),
				   HTML::input(array( 'type'=>'text',
						      'autofocus'=>true,
						      'value'=>$assmt['aname']))),
			 FormGroup('submissionEnd',
				   _('Author submissions end'),
				    HTML::input(array('type'=>'datetime-local-like',
						      'id'=>'submissionEnd',
						      // 'disabled'=>!empty($assmt['allocationsDone']),
						      'title'=>!empty($assmt['allocationsDone']) ? _('The submission date cannot be changed as allocations have already been made for this assignment.') : '',
						      'value'=>$submitDate))),
			 FormGroup('reviewEnd',
				   _('Reviewing ends'),
				   HTML::input(array('type'=>'datetime-local-like',
						     'id'=>'reviewEnd',
						     'value'=>$reviewDate))));

  $spec = array( );
  $typesUsed = array();
  foreach( preg_split( "/\n/", $assmt['submissionRequirements'], -1, PREG_SPLIT_NO_EMPTY) as $line ) {
    list ($type, $reqd, $argStr) = explode( ",", $line );
    parse_str($argStr ?? '', $args);
    $typesUsed[$type] = true;
    $spec[] = array('type'=>$type, 'reqd'=>$reqd, 'args'=>$args);
  }

  $warn = count($typesUsed) > 1
    ? warning(HTML::raw(_('This assignment currently allows both file and Arop&auml; editor submissions.'
			  . ' This combination is no longer supported.'
			  . ' When you Save, the submission requirement will be changed to one or the other,'
			  . ' depending on your selection below.')))
    : '';

  global $gStandardFileTypes;

  $editorOnly = count($spec) == 1 && $spec[0]['type'] == 'text';

  if( empty( $spec ) )
    $spec[] = array('type'=>'file', 'reqd'=>'required', 'args'=>array());

  extraHeader( 'fileSpec.js', 'script' );
  $fileList = HTML::ul(array('class'=>'form-horizontal list-group',
			     'id'=>'file-ol'));
  $nFiles = 0;
  foreach ($spec as $n => $s)
    $fileList->pushContent(HTML::li(HTML::div(array('class'=>'form-group'),
					      HTML::div(array('class'=>'col-sm-6'),
							selectFileExtn($n, $s['args']['extn'])),
					      HTML::label(array('for'=>"fileOther[$n]",
								'class'=>'control-label sr-only'),
							  _('File type')),
					      HTML::div(array('class'=>'col-sm-4'),
							HTML::input(array('type'=>'text',
									  'class'=>'form-control',
									  'name'=>"fileOther[$n]",
									  'id'=>"fileOther[$n]",
									  'placeholder'=>_('File extension'),
									  'value'=>$s['args']['other'],
									  'title'=>_('Enter a regular expression for the file type')))))));

  

  $documentFormats = HTML(HTML::h3(_('Submission requirements')),
			  $warn,
			  HTML::div(array('class'=>'radio',
					  'id'=>'submission-div'),
				    Radio('editorOnly',
					  HTML(Sprintf_('Authors must type their submission into the Arop&auml; editor. '),
					       _('Only simple document formatting is supported.')),
					  1,
					  $editorOnly,
					  array('id'=>'editorOnly')),
				    HTML::div(array('class'=>'radio'),
					      HTML::label(HTML::input(array('type'=>'radio',
									    'name'=>'editorOnly',
									    'value'=>0,
									    'checked'=>!$editorOnly)),
							  _('Authors must upload a file of the following type')),
					      HTML::div(array('class'=>'form-group',
							      'id'=>'file-group'),
							$fileList,
							HTML::button(array('onclick'=>'addFileEntry(); return false;',
									   'class'=>'btn'),
								     _('Add another file'))))));
  
  extraHeader('addRow.js', 'script');
  $submission = HTML(HTML::h3(_('Submission instructions')),
		     FormGroup('submissionText',
			       _('Enter instructions to display to authors before they submit their assignment'),
			       HTML::textarea( array('type'=>'text'),
					       $assmt['submissionText'])),
		     JavaScript("", array('src'=>'tinymce/js/tinymce/tinymce.min.js')),
		     JavaScript("tinymce.init({
  selector: 'textarea',
  height: 500,
  plugins: [
    'advlist autolink autosave codesample emoticons hr lists link charmap print',
    'searchreplace table paste'
  ],
  menu: {
    edit: {title: 'Edit', items: 'undo redo | cut copy paste pastetext | selectall'},
    insert: {title: 'Insert', items: 'link media | template hr'},
    format: {title: 'Format', items: 'bold italic underline strikethrough superscript subscript | formats | removeformat'},
    table: {title: 'Table', items: 'inserttable tableprops deletetable | cell row column'}
  },
  toolbar: 'undo redo | styleselect | bold italic | bullist numlist outdent indent | link emoticons codesample'
});"),
		     $documentFormats);
  
  $reviewing = HTML(HTML::h3(_('Reviewing and feedback')),
		    HTML::h4(_('Show marks in feedback')),
		    HTML::div(array('class'=>'radio-group'),
			      Radio('showMarksInFeedback',
				    _('Authors should see comments and marks in the feedback from the reviewers'),
				    1,
				    $assmt['showMarksInFeedback']==1),
			      Radio('showMarksInFeedback',
				    _('Only comments will be shown in the feedback from the reviewers'),
				    0,
				    $assmt['showMarksInFeedback']!=1)),
		    HTML::h4(_('Restrict feedback')),
		    HTML::div(array('class'=>'radio-group'),
			      Radio('restrictFeedback',
				    _('All authors see feedback from reviewers'),
				    'none',
				    $assmt['restrictFeedback']=='none'),
			      Radio('restrictFeedback',
				    HTML::raw(_('Only authors who have completed <b>at least one</b> review see feedback')),
				    'some',
				    $assmt['restrictFeedback']=='some'),
			      Radio('restrictFeedback',
				    HTML::raw(_('Only authors who have completed <b>all</b> their reviews see feedback')),
				    'all',
				    $assmt['restrictFeedback']=='all')),
		    HTML::h4(_('Anonymous review')),
		    HTML::div(array('class'=>'radio-group'),
			      Radio('anonymousReview',
				    _('Author identity will not be revealed to reviewers'),
				    1,
				    $assmt['anonymousReview']==1),
			      Radio('anonymousReview',
				    _('Reviewers will be shown the name of the Author'),
				    0,
				    $assmt['anonymousReview']!=1)),
		    HTML::h4(_('Self-review')),
		    HTML::div(array('class'=>'radio-group'),
			      Radio('selfReview',
				    _('No self-review'),
				    0,
				    $assmt['selfReview']!=1)),
			      Radio('selfReview',
				    _('Authors will review their own work'),
				    1,
				    $assmt['selfReview']==1),
		    $saveCancel,
		    HTML::div(array('class'=>'alert alert-info',
				    'role'=>'alert'),
			      _('Less commonly used options appear below.')),
		    HTML::div(array('class'=>'bg-info'),
			      HTML::h4(_('Review marking')),
			      HTML::p(_('Optionally, you can mark the reviews.  This will allow you (or your students or markers) to read the
reviews written during the peer review activity, and provide some constructive feedback or assess their quality.')),
			      HTML::p(_('Selecting the "Reviews will be marked" option below will enable a "Create review marking" button on the assignment page, which you can then use to set up a review marking assignment.')),
			      HTML::div(array('class'=>'radio-group'),
					Radio('hasReviewEvaluation',
					      _('Reviews will not be marked'),
					      0,
					      $assmt['hasReviewEvaluation']!=1)),
					Radio('hasReviewEvaluation',
					      _('Reviews will be marked using a separate Review Marking assignment'),
					      1,
					      $assmt['hasReviewEvaluation']==1),
                              HTML::br(),
			      HTML::h4(_('Restricted review viewing')),
			      HTML::raw(_('Optionally, you can specify those reviews a reviewer can see after they complete their own reviewing.
This is particularly useful as part of a training process, but not recommended for typical use of Arop&auml; for peer-assessment.
The default is that reviewers can see all the other reviews of a submission that they have themselves reviewed.
To restrict review viewing, enter one or more usernames, separated by semi-colons (;)')),
			      FormGroup('visibleReviewers',
					_('Visible reviewers'),
					HTML::input( array( 'type'=>'text', 'value'=>$assmt['visibleReviewers']))),
                              HTML::br(),
			      HTML::h4(_('Review locking')),
			      _('Optionally, you can turn on reviewer locking. This will allow students to lock their reviews before the review end date, and view feedback from other students who have also locked their reviews.'),
			      HTML::div(array('class'=>'radio-group'),
					Radio('allowLocking',
					      _('No reviewer locking'),
					      0,
					      $assmt['allowLocking']!=1),
					Radio('allowLocking',
					      _('Allow reviewers to lock their reviews'),
					      1,
					      $assmt['allowLocking']==1)),
                              HTML::br(),
                              HTML::h4(_('Bulk upload of submissions')),
                              HTML::p(_('Optionally, you can upload all the submissions in a ZIP file. The submissions must each be labelled with the student identifier (as recorded in the class list).')),
                              HiddenInputs(array('MAX_FILE_SIZE'=>$maxFileSize)),
                              Javascript('function checkSize(f) {
if (f.getElementsByTagName) {
  var inputs = f.getElementsByTagName("input");
  for (var i = 0; i < inputs.length; i++)
        if (inputs[i].type == "file" && inputs[i].files)
           for (var j = 0; j < inputs[i].files.length; j++)
             if (inputs[i].files[j].size > f.MAX_FILE_SIZE.value) {
               alert("Uploaded files must be less than " + (f.MAX_FILE_SIZE.value/1024/1024) + "Mb");
               return false;
           }
}
return true;
}'),
                              HTML::label(array('class'=>'btn btn-default btn-file'),
                                          _('Browse'),
                                          HTML::input(array('type'=>'file',
                                                            'name'=>'bulk',
                                                            'accept'=>'application/zip,application/x-zip,application/x-zip-compressed',
                                                            'style'=>'display:none',
                                                            'onchange'=>"$('#upload-file-info').html($(this).val())"))),
                              HTML::span(array('class'=>'label label-info', 'id'=>"upload-file-info")),
                              HTML::p(_('After uploading, you should check the submissions (using the \'Monitor submissions\' page).')),
                              !empty($assmtID) && fetchOne("select 1 from Essay where assmtID = $assmtID limit 1")
                              ? warning( _('Bulk Upload will first delete all existing submissions.'))
                              : ''));
  
  $form = HTML::form(array('method'=>'post',
                           'class' =>'form',
                           'enctype'=>'multipart/form-data',
                           'action'=>"$_SERVER[PHP_SELF]?action=saveAssignment&cid=$cid"));
  $warning = '';
  if (isset($assmtID)) {
    $form->pushContent(HiddenInputs(array('assmtID'=>$assmtID)));
    if (fetchOne("select 1 from Assignment where isReviewsFor = $assmtID and isActive and allocationsDone is not null"))
      $warning = warning(_('You have an active Review Marking assignment. Note that any changes you make here may have a knock-on affect to that assignment.'));
  }
   
  $form->pushContent($warning,
		     $identification,
		     $submission,
		     $reviewing,
		     $saveCancel);
  return $form;
}


function selectFileExtn( $n, $extn ) {
  global $gStandardFileTypes;
  $select = HTML::select(array('name'=>"fileExtn[$n]",
			       'class'=>'form-control'));
  foreach( $gStandardFileTypes as $label => $spec )
    $select->pushContent( HTML::option(array('label'=>$label,
					     'value'=>$spec,
					     'selected'=>$extn==$spec),
				       $label));
  return $select;
}


//- REQUIRE: $assmt['isReviewsFor'] non-null
function editAssignmentReview( $assmt, $assmtID, $cid ) {
  ensureDBconnected( 'editAssignmentReview' );
  $origAssmt = fetchOne( 'SELECT * FROM Assignment'
			 . " WHERE assmtID = $assmt[isReviewsFor]" );
  if( ! $origAssmt )
    return warning( Sprintf_( 'The assignment being reviewed (#%d) does not exist!', $assmt['isReviewsFor'] ));

  datetimeWidget();

  $reviewDate = ToRFC3339($assmt['reviewEnd']);
  $identification = HTML(HTML::h3(_('Main details')),
			 FormGroup('reviewEnd',
				   _('Review marking ends'),
				   HTML::input(array('type'=>'datetime-local-like',
						     'id'=>'reviewEnd',
						     'min'=>ToRFC3339($origAssmt['reviewEnd']),
						     'value'=>$reviewDate))),
			 HTML::p( Sprintf_('This date must be after %s',
					   formatDateString( $origAssmt['reviewEnd']))));
  
  $reviewing = HTML(HTML::h3(_('Reviewing and feedback')),
		    HTML::h4(_('Anonymous review')),
		    HTML::div(array('class'=>'radio'),
			      Radio('anonymousReview',
				    _('Reviewer identity will not be revealed to markers.'),
				    1,
				    $assmt['anonymousReview'] == 1),
			      Radio('anonymousReview',
				    _('Reviewer identity will be revealed to markers.'),
				    0,
				    $assmt['anonymousReview'] != 1)),
		    HTML::h4(_('Show marks in feedback')),
		    HTML::div(array('class'=>'radio'),
			      Radio('showMarksInFeedback',
				    _('Reviewers should see comments and marks in the feedback from the markers'),
				    1,
				    $assmt['showMarksInFeedback'] == 1),
			      Radio('showMarksInFeedback',
				    _('Reviewers should see comments only in the feedback from the markers'),
				    0,
				    $assmt['showMarksInFeedback'] != 1)));
  $form = HTML::form( array( 'name'=>'edit',
                             'method'=>'post',
                             'action'=>"$_SERVER[PHP_SELF]?action=saveAssignment&cid=$cid" ) );
  if( isset($assmtID) )
    $form->pushContent( HiddenInputs( array('assmtID'=>$assmtID )));

  $form->pushContent( HiddenInputs( array('isReviewsFor'=>$origAssmt['assmtID'],
					  'origName'=>$origAssmt['aname'],
					  'submissionEnd'=>$origAssmt['reviewEnd'],
					  'cid'=>$cid)));

  $form->pushContent( HTML::h1( $assmt['aname'] ),
		      HTML::p( HTML::raw(_('This page allows the details for a <q>review marking</q> assignment to be specified. This allows markers to mark existing reviews.'))),
		      $identification,
		      $reviewing,
                      ButtonToolbar(submitButton(_('Save')),
				    CancelButton()));
  return $form;
}


function saveAssignment( ) {
  $db = ensureDBconnected( 'saveAssignment', array('cid') );
  $cid = (int)$_REQUEST['cid'];

  $fields = array('aname' => _('(unnamed assignment)') );
  if( isset( $_REQUEST['aname'] ) )
    $fields['aname']           = trim( $_REQUEST['aname'] );
  if( isset( $_REQUEST['selfReview'] ) )
    $fields['selfReview']      = $_REQUEST['selfReview'] ? 1 : 0;
  if( isset( $_REQUEST['submissionText'] ) )
    $fields['submissionText']  = trim( $_REQUEST['submissionText'] );
  if( isset( $_REQUEST['submissionEnd'] ) )
    $fields['submissionEnd']   = date_to_mysql( $_REQUEST['submissionEnd'] );
  if( isset( $_REQUEST['reviewEnd'] ) )
    $fields['reviewEnd']       = date_to_mysql( $_REQUEST['reviewEnd'] );
  if( isset( $_REQUEST['basepath'] ) )
    $fields['basepath']        = trim( $_REQUEST['basepath'] );
  if( isset( $_REQUEST['anonymousReview'] ) )
    $fields['anonymousReview'] = $_REQUEST['anonymousReview'] ? 1 : 0;
  if( isset( $_REQUEST['showMarksInFeedback'] ) )
    $fields['showMarksInFeedback'] = $_REQUEST['showMarksInFeedback'] ? 1 : 0;
  if( isset( $_REQUEST['showReviewMarkingFeedback'] ) )
    $fields['showReviewMarkingFeedback'] = $_REQUEST['showReviewMarkingFeedback'] ? 1 : 0;
  if( isset( $_REQUEST['restrictFeedback'] ) )
    $fields['restrictFeedback'] = $_REQUEST['restrictFeedback']=='all' ? 'all' : ($_REQUEST['restrictFeedback']=='some' ? 'some' : 'none');
  if( isset( $_REQUEST['hasReviewEvaluation'] ) )
    $fields['hasReviewEvaluation'] = $_REQUEST['hasReviewEvaluation'] ? 1 : 0;
  if( isset( $_REQUEST['visibleReviewers'] ) )
    $fields['visibleReviewers'] = $_REQUEST['visibleReviewers'];
  if( isset( $_REQUEST['allowLocking'] ) )
    $fields['allowLocking']= (int)$_REQUEST['allowLocking'];

  if( isset( $_REQUEST['isReviewsFor'] ) ) {
    $fields['isReviewsFor'] = (int)$_REQUEST['isReviewsFor'];
    if( isset( $_REQUEST['origName'] ) ) {
      if( $_REQUEST['authorsAre'] == 'review' )
	$fields['aname'] = sprintf( _('Review marking for %s'), $_REQUEST['origName'] );
      else
	$fields['aname'] = sprintf( _('Reviewer marking for %s'), $_REQUEST['origName'] );
    }
  }

  $spec = array( );
  if (isset($_REQUEST['editorOnly']) && (int)$_REQUEST['editorOnly'] == 1)
    $spec[] = "text,required";
  else if (is_array($_REQUEST['fileExtn']))
    foreach ($_REQUEST["fileExtn"] as $n => $extn)
      if (! empty($extn)) {
	$args = array('extn'=>$extn, 'other'=>$_REQUEST['fileOther'][$n]);
	$spec[] = "file,required," . itemsToString($args);
      }

  $fields['submissionRequirements'] = join( "\n", $spec );

  //- Creating a new assignment or copying an existing assignment.
  if( empty( $_REQUEST['assmtID'] ) ) {
    $classId = cidToClassId( $cid );
    $fields['courseID'] = $classId;
    checked_mysql_query(makeInsertQuery('Assignment', $fields, array('whenCreated' => 'now()')));
    $assmtID = $db->insert_id;

    /*
    //- If this is a review assignment and the original uses groups,
    //- copy the groups from the original
    if( ! empty($fields['isReviewsFor']) && $fields['groups'][0] == 'n' ) {
      $rs = checked_mysql_query( 'SELECT who, reviewGroup FROM `Groups`'
				 . ' WHERE assmtID = ' . quote_smart( $fields['isReviewsFor'] ));
      $values = array( );
      while( $row = $rs->fetch_assoc() )
	$values[] = '(' . $assmtID
          . ',' . quote_smart( $row['who'] )
          . ',' . quote_smart( $row['reviewGroup'] )
          . ')';
      if( ! empty($values) )
	checked_mysql_query( 'INSERT INTO `Groups` (assmtID, who, authorGroup) VALUES '
			     . join(',', $values));
    }
    */
  } else {
    // Updating an existing assignment.
    $assmtID = (int)$_REQUEST['assmtID'];
    $classId = cidToClassId( $cid );
    checked_mysql_query( makeUpdateQuery('Assignment', $fields )
                         . " WHERE assmtID = $assmtID"
			 . " AND courseID = $classId");

    $assmt = fetchOne( 'SELECT * FROM Assignment'
		       . " WHERE assmtID = $assmtID"
		       . " AND courseID = $classId");
    if( $assmt ) {
      //- If the review end date changes, update any review-of-reviews assignment to start from then
      checked_mysql_query('update Assignment'
			  . ' set allocationsDone = null'
			  . " where isReviewsFor = $assmtID"
			  . ' and submissionEnd < ' . quote_smart($assmtID['reviewEnd']));
      checked_mysql_query('update Assignment'
			  . ' set submissionEnd = ' . quote_smart($assmt['reviewEnd'])
			  . ', aname = case authorsAre'
			  . " when 'review'   then " . quote_smart(sprintf(_('Review marking for %s'), $assmt['aname']))
			  . " when 'reviewer' then " . quote_smart(sprintf(_('Reviewer marking for %s'), $assmt['aname']))
			  . ' else aname end'
			  . " where isReviewsFor = $assmtID");
      
      if (nowBetween($assmt['submissionEnd'], null) && !$assmt['allocationsDone']) {
	require_once 'doAllocation.php';
	doUnsupervisedAllocation($assmt);
      } else if (nowBetween(null, $assmt['submissionEnd']) && $assmt['allocationsDone'])
	checked_mysql_query('update Assignment set allocationsDone = null'
			    . " where assmtID = $assmtID");
    }
  }

  require_once 'BulkUpload.php';
  bulkUpload($assmtID, $classId);
  
  //- Ensure any special reviewers are able to do their job, by
  //- registering them in the Reviewer table.
  require_once 'users.php';
  $values = array( );
  $visibleReviewerIDs = identitiesToUserIDs( preg_split("/[ \t,;]+/", $fields['visibleReviewers'], -1, PREG_SPLIT_NO_EMPTY) );
  foreach( $visibleReviewerIDs as $userID )
    $values[] = "($userID, $assmtID)";
  if( ! empty( $values ) )
    checked_mysql_query( "INSERT IGNORE INTO Reviewer (reviewer, assmtID) VALUES "
			 . join(",", $values) );
  
  $now = quote_smart(date('Y-m-d H:i:s'));
  checked_mysql_query( 'REPLACE INTO LastMod'
                       . " SET assmtID = $assmtID, lastMod = now()" );

  redirect( 'viewAsst', "assmtID=$assmtID&cid=$cid" );
}



//--------------------//
//- UI constructors --//
//--------------------//

function makeAuthorReviewerSelector( $field, $selected ) {
  $select = HTML::select( array('name'=>$field) );
  foreach( array('all', 'submit', 'group', 'other') as $option )
    $select->pushContent( HTML::option(array('value'=>$option,
					     'selected'=>$selected==$option)));
  return $select;
}


/*
function makeClassSelector( $courseID ) {
  $courseSelect = HTML::select( array('name'=>'class') );
  $rs = checked_mysql_query( 'SELECT courseID, cname FROM Course'
                             . ' WHERE instID = ' . (int)$_SESSION['instID'] );
  $someSel = false;
  $haveClassSelect = false;
  while( $row = $rs->fetch_assoc() ) {
    $haveClassSelect = true;
    $sel = $row['courseID'] == $courseID;
    if( $sel )
      $someSel = true;
    $classSelect->pushContent( HTML::option( array('value'=>$row['courseID'],
						    'selected'=>$sel),
					      $row['cname'] ) );
  }
  if( ! $someSel )
    $classSelect->pushContent( HTML::option( array('value'=>0,
						    'selected'=>true),
					      "" ) );
  $classInput = HTML::input( array('type'=>'text',
				    'name'=>'class2',
				    'value'=> ($someSel ? "" : $current) ) );

  return $haveClassSelect
    ? HTML::span( $classSelect, ' or ', $classInput )
    : $classInput;
}
*/

function mkDateInput( $title, $field, $current ) {
  return HTML( HTML::label( array('for'=>$field), $title ),
	       HTML::input( array('type'=>'text',
				  'name'=>$field,
				  'size'=>'22',
				  'value'=>formatDateString( $current ) )));
}
