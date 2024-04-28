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

function viewInstructorAssignment($cid, $assmt) {
  $class = $_SESSION['classes'][ $cid ];
  $assmtID = (int)$assmt['assmtID'];
  global $gHaveInstructor;
  global $gHaveAdmin;
  $uneditable = $class['cactive'] == 0 || ! $gHaveInstructor;

  $nav = HTML::div(array('class'=>"btn-group-vertical btn-group-sm"));

  if (emptyDate($assmt['submissionEnd']) && empty($assmt['aname']))
    $activateAttrs = array('disabled'=>true,
			   'title'=>_('The assignment cannot be activated until it has a name and a submission date has been set'));
  else if (emptyDate($assmt['submissionEnd']))
    $activateAttrs = array('disabled'=>true,
			   'title'=>_('The assignment cannot be activated until it a submission date has been set'));
  else if (empty($assmt['aname']))
    $activateAttrs = array('disabled'=>true,
			   'title'=>_('The assignment cannot be activated until it has a name'));
  else
    $activateAttrs = array();

  if( ! $uneditable )
    $nav->pushContent($assmt['isActive']
		      ? formButton( _('Deactivate'),
				    "deactivate&assmtID=$assmtID&cid=$cid")
		      : formButton( _('Activate'),
				    "activate&assmtID=$assmtID&cid=$cid",
				    '',
				    $activateAttrs));
  $actionMap = array( 'editAssignment'  => _('Edit assignment'),
		      'editRubricA'     => _('Edit rubric'),
		      'labelRubricA'    => _('Label rubric'),
		      'setupAllocation' => empty($assmt['allocationType'])
		                              ? _('Specify allocations')
   		                              : _('Change allocations'),
		      'instructorAuthor' => _('Submit as an author'),
		      'addExtensions'   => _('Manage extensions'),
		      'viewSubmissions' => _('Monitor submissions'),
		      'populateSubmissions' => _('Populate submissions'),
		      'viewAllocations' => _('Monitor reviewing'),
		      'viewAllFeedback' => _('View all reviews'),
		      'downloadUploads' => _('Download all submissions'),
		      'calcGrades'      => _('View marks'),
		      'viewReview'      => _('View review marking'),
		      'createReview'    => _('Create review marking'),
		      'delete'          => _('Delete assignment'),
		      'loginAsStudent'  => _('Impersonate other user'));

  if( $uneditable ) {
    $actionMap['editRubricA'] = _('View rubric');
    unset($actionMap['editAssignment']);
    unset($actionMap['setupAllocation']);
    unset($actionMap['labelRubricA']);
    unset($actionMap['addExtensions']);
    unset($actionMap['instructorAuthor']);
    unset($actionMap['createReview']);
    unset($actionMap['populateSubmissions']);
  }

  if (!$gHaveAdmin)
    unset($actionMap['populateSubmissions']);
  
  if( ! $gHaveInstructor )
    unset( $actionMap['loginAsStudent'] );

  if( empty( $assmt['allocationsDone'] ) ) {
    unset( $actionMap['viewAllocations'] );
    unset( $actionMap['viewAllFeedback'] );
    unset( $actionMap['calcGrades'] );
  }

  if( ! empty( $assmt['isReviewsFor'] ) ) {
    $actionMap['editAssignment'] = $assmt['authorsAre'] == 'review' ? _('Edit review marking') : _('Edit reviewer marking');
    $actionMap['viewAllocations'] = _('Monitor marking');
    $actionMap['viewAllFeedback'] = _('View all marking');
    unset( $actionMap['viewSubmissions'] );
    unset( $actionMap['downloadUploads'] );
    unset( $actionMap['createReview'] );
    unset( $actionMap['viewReview'] );
  } else {
    $rr = fetchOne( "SELECT assmtID FROM Assignment WHERE isReviewsFor = $assmtID", 'assmtID' );
    if( empty( $rr ) ) {
      unset( $actionMap['viewReview'] );
      if( ! $assmt['hasReviewEvaluation'] )
	unset( $actionMap['createReview'] );
    } else
      unset( $actionMap['createReview'] );
  }

  if (empty($assmt['rubricID']) || (empty($assmt['markItems']) && empty($assmt['commentItems'])))
    unset($actionMap['labelRubricA']);

  if( empty( $assmt['markItems'] ) )
    unset( $actionMap['calcGrades'] );

  if( ! fetchOne( "SELECT author FROM Author WHERE assmtID=$assmtID AND author=$_SESSION[userID]", 'author' ) )
    unset( $actionMap['instructorAuthor'] );

  if ($assmt['category'] == 'successful' || $assmt['category'] == 'aborted')
    $actionMap['delete'] = _('Purge submissions');
  
  if( $uneditable || $assmt['isActive'] )
    unset( $actionMap['delete'] );

  foreach( $actionMap as $action => $title ) {
    if( $action == 'viewReview' )
      $req = "viewAsst&cid=$cid&assmtID=$rr";
    else {
      $req = "$action&cid=$cid&assmtID=$assmtID";
      if( $action == 'setupAllocation' ) {
	if( $assmt['authorsAre'] == 'group' || $assmt['reviewersAre'] == 'group' )
	  $req .= '&type=groups';
	else if( ! empty( $assmt['allocationType'] ) )
	  $req .= "&type=$assmt[allocationType]";
      }
    }
    $nav->pushContent(Button($title, $req));
  }

  $rgt = HTML::div( array('id'=> "rightContent"),
		    pendingMessages( ),
		    assmtHeading('', $assmt));

  $nMarkers = fetchOne( "SELECT count(*) AS n FROM Reviewer WHERE assmtID = $assmtID", 'n' );

  $notes = HTML::ul( );
  if( $class['cactive'] == 0 )
    $notes->pushContent( HTML::li( _('The class for this assignment is inactive.  No changes can be made.') ));
  else {
    if( ! fetchOne( "SELECT * FROM UserCourse WHERE (roles&1)<>0 AND courseID=$class[courseID] LIMIT 1" ) )
      $notes->pushContent( HTML::li( _('No students have been registered for this class.  Use "Edit Class List" on the class page to add them.')));

    if( empty($assmt['rubricID']) )
      $notes->pushContent( HTML::li( _('The rubric has not been created yet. Use "Edit rubric" to create it') ));
    else {
      if( empty( $assmt['markItems'] ) && empty( $assmt['commentItems'] ) && $assmt['nReviewFiles'] == 0 )
	$notes->pushContent( HTML::li( _('The rubric has no input items.  Use "Edit rubric" to add some.') ));
      else {
	/*
	if( $assmt['showMarksInFeedback'] && empty( $assmt['markItems'] ) )
	  $notes->pushContent( HTML::li( _('The rubric has no mark items; marks cannot be shown in feedback.  Use "Edit rubric" to add mark items, or "Edit assignment" to turn off marks in feedback.')));
	if( ! $assmt['showMarksInFeedback'] && empty( $assmt['commentItems'] ) )
	  $notes->pushContent( HTML::li( _('The rubric has no comment items; only marks are available for feedback.  Use "Edit rubric" to add comment items, or "Edit assignment" to turn on marks in feedback.')));
	*/
      }
    }

    if( empty($assmt['allocationType']) )
      $notes->pushContent( HTML::li( _('Allocations have not yet been setup.  Use "Specify Allocations" to set up allocations.')));
    else {
      if ($assmt['allocationType'] == 'manual' && fetchOne("select count(*) as n from Allocation where assmtID = $assmtID", 'n') == 0)
	$notes->pushContent(HTML::li(Sprintf_('No manual allocations have been provided. Use <q>Specify Allocations</q> to enter them.')));
      
      if( ($assmt['allocationType'] == 'same tags' || $assmt['allocationType'] == 'other tags' ) && empty( $assmt['tags'] ) )
	$notes->pushContent( HTML::li( Sprintf_('Allocations are setup using tags, but no tags are specified.  Use <q>Specify Allocations</q> to set the tags.') ));
      if( in_array( $assmt['allocationType'], array( 'normal', 'same tags', 'other tags') ) && $assmt['nPerReviewer'] < 1 && $assmt['reviewersAre'] != 'other' )
	$notes->pushContent( HTML::li( Sprintf_('The number of allocations per reviewer is not specified.  Use <q>Specify Allocations</q> to enter the desired number.') ));
      if( $assmt['reviewersAre'] == 'other' && $nMarkers == 0 )
	$notes->pushContent( HTML::li( Sprintf_('No markers have been given for the assignment.  Use <q>Specify Allocations</q> to select the markers')));
    }

    if( empty( $assmt['isReviewsFor'] ) && empty($assmt['submissionRequirements']) )
      $notes->pushContent( HTML::li( _('No submission requirements have been specified.  Use "Edit Assignment" to add them.')));

    if( ! $assmt['isActive'] && ! nowBetween( $assmt['reviewEnd'], null) )
      $notes->pushContent( HTML::li( _('The assignment is not active. Please activate it when you are ready to start.')));

    if( empty( $assmt['isReviewsFor'] ) ) {
      if(emptyDate($assmt['submissionEnd']))
	$notes->pushContent( HTML::li( _('A submission date has not been set.  Use "Edit Assignment" to set a date. This must be done before the assignment can be activated.') ));

      if(emptyDate($assmt['reviewEnd']))
	$notes->pushContent( HTML::li( _('A review date has not been set.  Use "Edit Assignment" to set a date.') ));

      if(!emptyDate( $assmt['submissionEnd']) && !emptyDate($assmt['reviewEnd']) && $assmt['submissionEnd'] > $assmt['reviewEnd'])
	$notes->pushContent( HTML::li( _('The review date is before submissions end.  Use "Edit Assignment" to correct the dates.') ));
    } else {
      if( emptyDate( $assmt['reviewEnd'] ) )
	$notes->pushContent( HTML::li( _('The completion date for review marking has not been set.  Use "Edit Review Marking" to set it.') ));
      else if( $assmt['submissionEnd'] >= $assmt['reviewEnd'] )
	$notes->pushContent( HTML::li( _('The review marking due date is before reviewing ends.  Use "Edit Review Marking" to correct the date.') ));
    }
  }

  if( ! $uneditable && ! $notes->isEmpty( ) )
    $rgt->pushContent( warning($notes) );
  if ($assmt['isActive'])
    $rgt->pushContent(!empty($assmt['whenActivated'])
		      ? Sprintf_('The assignment was activated at <b>%s</b>', formatTimestamp($assmt['whenActivated']))
		      : _('The assignment is currently active'),
		      HTML::br( ));

  if( empty( $assmt['isReviewsFor'] ) ) {
    $rgt->pushContent( emptyDate( $assmt['submissionEnd'] )
		       ? Sprintf_( 'Submissions end at <b>an unspecified date</b>' )
		       : Sprintf_( 'Submissions end at <b>%s</b>', formatDateString( $assmt['submissionEnd'] )),
		       HTML::br( ));

    $rgt->pushContent( emptyDate( $assmt['reviewEnd'] )
		       ? Sprintf_( 'Reviews end at <b>an unspecified date</b>')
		       : Sprintf_( 'Reviews end at <b>%s</b>', formatDateString( $assmt['reviewEnd'] )),
		       HTML::br( ));
    $rgt->pushContent(HTML::br());

    if( ! empty($assmt['submissionRequirements']) )
      $rgt->pushContent( _('Submission requirements are: '), describeSubmissions( $assmt['submissionRequirements'] ) );

    $rgt->pushContent( HTML::raw( $assmt['showMarksInFeedback']
				  ? _('Authors should see <b>comments and marks</b> in the feedback from the reviewers')
				  : _('Authors should see <b>comments only</b> in the feedback from the reviewers')),
		       HTML::br( ));

    $rgt->pushContent( HTML::raw( $assmt['selfReview']
				  ? _('Authors <b>will</b> review their own work')
				  : _('Authors <b>will not</b> review their own work')),
		       HTML::br( ));
    $rgt->pushContent( HTML::raw( $assmt['anonymousReview']
				  ? _('Author identity <b>will not</b> be revealed to reviewers')
				  : _('Author identity <b>will</b> be revealed to reviewers')),
		       HTML::br( ));
  } else {
    $rgt->pushContent( emptyDate( $assmt['submissionEnd'] )
		       ? Sprintf_( 'Review marking starts at <b>an unspecified date</b>' )
		       : Sprintf_( 'Review marking starts at <b>%s</b>', formatDateString( $assmt['submissionEnd'] )),
		       HTML::br( ));
    $rgt->pushContent( emptyDate( $assmt['reviewEnd'] )
		       ? Sprintf_( 'Review marking ends at <b>an unspecified date</b>')
		       : Sprintf_( 'Review marking ends at <b>%s</b>', formatDateString( $assmt['reviewEnd'] )),
		       HTML::br( ));
  
    $rgt->pushContent( HTML::raw( $assmt['anonymousReview']
				  ? _('Reviewer identity <b>will not</b> be revealed to markers')
				  : _('Reviewer identity <b>will</b> be revealed to markers')),
		       HTML::br( ));
    $rgt->pushContent( HTML::raw( $assmt['showMarksInFeedback']
				  ? _('Reviewers should see <b>comments and marks</b> in the feedback from the markers')
				  : _('Reviewers should see <b>comments only</b> in the feedback from the markers')),
		       HTML::br( ));
  }

  if( ! empty( $assmt['allocationType'] ) ) {
    switch( $assmt['allocationType'] ) {
    case 'manual':
      $rgt->pushContent(Sprintf_('Allocations are <b>manual</b>'), HTML::br());
      break;
    case 'streams':
      $rgt->pushContent(Sprintf_('Allocations are <b>by streams</b>'), HTML::br());
      break;
    case 'normal':
    case 'same tags':
    case 'other tags':
      $tags = '';
      if ($assmt['allocationType'] == 'other tags')
	$tags = Sprintf_(' within <b>different</b> tags');
      else if ($assmt['allocationType'] == 'same tags')
	$tags = Sprintf_(' within the <b>same</b> tag');
      if ($assmt['authorsAre'] != 'group')
	$rgt->pushContent(Sprintf_('Allocations are <b>random</b>'), $tags, HTML::br());
      $rgt->pushContent(authorsAreDescription($assmtID, $assmt['authorsAre'], $assmt['isReviewsFor']), HTML::br( ));
      
      if( ! empty( $assmt['isReviewsFor'] ) ) {
	switch( $assmt['reviewersAre'] ) {
	case 'all':    $rgt->pushContent(Sprintf_('Markers are <b>those who uploaded a submission</b>'), br()); break;
	case 'submit': $rgt->pushContent(Sprintf_('Markers are <b>those who wrote a review</b>'), br()); break;
	case 'other':  
	  break;
	default: $rgt->pushContent(Sprintf_('Markers are <b>not specified</b>'), br());
	}
	if( $assmt['nPerReviewer'] > 0 )
	  $rgt->pushContent(Sprintf_(ngettext('Each marker should mark <b>one review</b>',
					      'Each marker should mark <b>%d reviews</b>', $assmt['nPerReviewer']),
				     $assmt['nPerReviewer']),
			    br());
      } else {
	switch( $assmt['reviewersAre'] ) {
	case 'all':    $rgt->pushContent(Sprintf_('Reviewers are <b>everyone in the class</b>'), br()); break;
	case 'submit':
	  if ($assmt['authorsAre'] == 'group')
	    $rgt->pushContent(Sprintf_('Reviewers are <b>all members of groups that upload a submission</b>'), br());
	  else
	    $rgt->pushContent(Sprintf_('Reviewers are <b>those who upload a submission</b>'), br());
	  break;
	case 'group':  $rgt->pushContent(Sprintf_('Reviewers are <b>groups</b>'), br()); break;
	case 'other':  
	  break;
	default: $rgt->pushContent(Sprintf_('Reviewers are <b>not specified</b>'), br());
	}
      
	if ($assmt['nPerReviewer'] > 0)
	  $rgt->pushContent(Sprintf_(ngettext('Each reviewer should mark <b>one submission</b>',
					      'Each reviewer should mark <b>%d submissions</b>', $assmt['nPerReviewer']),
				     $assmt['nPerReviewer']),
			    br());
      }

      if( $nMarkers > 0 )
	$rgt->pushContent(Sprintf_(ngettext('The assignment has <b>one marker</b>',
					    'The assignment has <b>%d markers</b>', $nMarkers), $nMarkers ), br());
    }

    if( ! empty( $assmt['isReviewsFor'] ) )
      switch( $assmt['restrictFeedback'] ) {
      case 'all':
	$rgt->pushContent(Sprintf_('Only reviewers who have completed <b>all their review marking</b> will see feedback'), br());
	break;
      case 'some':
	$rgt->pushContent(Sprintf_('Only reviewers who have completed <b>at least one review marking</b> will see feedback'), br());
	break;
      case 'none':
	break;
      }
    else
      switch( $assmt['restrictFeedback'] ) {
      case 'all':
	$rgt->pushContent(Sprintf_('Only authors who have completed <b>all their allocated reviews</b> will see feedback'), br());
	break;
      case 'some':
	$rgt->pushContent(Sprintf_('Only authors who have completed <b>at least one review</b> will see feedback'), br());
	break;
      case 'none':
	break;
      }

    $rgt->pushContent(HTML::br());

    if( ! empty( $assmt['whenActivated'] ) ) {
      if( empty($assmt['isReviewsFor']) ) {
	list( $expected, $recd ) = submissionsExpectedAndReceived( $assmtID, $assmt['authorsAre'] );
	if( nowBetween($assmt['submissionEnd'], null) )
	  $rgt->pushContent( Sprintf_( ngettext( '<b>One</b> file were uploaded',
						 '<b>%1$d</b> files were uploaded', $recd),
				       $recd),
			     $expected > $recd ? Sprintf_( ' (expected <b>%d</b>)', $expected ) : '',
			     br());
	else
	  $rgt->pushContent( Sprintf_( ngettext( '<b>One</b> file has been uploaded, as at <b>%2$s</b>',
						 '<b>%1$d</b> files have been uploaded, as at <b>%2$s</b>', $recd),
				       $recd, formatTimestamp( time() )),
			     $expected > $recd ? Sprintf_( ' (expecting <b>%d</b>)', $expected ) : '',
			     br());
      }
      else {
	$expected = fetchOne("SELECT COUNT(*) AS n FROM Allocation WHERE assmtID=$assmt[isReviewsFor]", 'n');
	$recd = fetchOne("SELECT COUNT(*) AS n FROM Allocation WHERE assmtID=$assmt[isReviewsFor] AND lastMarked IS NOT NULL", 'n');
	if( nowBetween($assmt['submissionEnd'], null) )
	  $rgt->pushContent( br(),
			     Sprintf_( ngettext( '<b>One</b> review was written',
						 '<b>%1$d</b> reviews were written</b>', $recd),
				       $recd),
			     $expected > $recd ? Sprintf_( ' (expected <b>%d</b>)', $expected ) : '',
			     br());
	else
	  $rgt->pushContent( br(),
			     Sprintf_( ngettext( '<b>One</b> review has been written, as at <b>%2$s</b>',
						 '<b>%1$d</b> reviews have been written, as at <b>%2$s</b>', $recd),
				       $recd, formatTimestamp( time() )),
			     $expected > $recd ? Sprintf_( ' (expecting <b>%d</b>)', $expected ) : '',
			     br());
      }

      $nSextn = 0;
      $nSexpired = 0;
      foreach (fetchAll('select n.who, n.submissionEnd, sum(1 - e.isPlaceholder) as s, sum(isPlaceholder) as p'
			. ' from Extension n'
			. ' inner join Essay e on n.who = e.author and n.assmtID = e.assmtID'
			. ' where n.submissionEnd is not null'
			. " and n.assmtID = $assmtID"
			. ' group by n.who') as $extn)
	if (nowBetween($extn['submissionEnd'], null)) {
	  if ($extn['s'] == 0)
	    $nSexpired++;
	} else if ($extn['p'] > 0)
	  $nSextn++;
	
      if ($nSextn > 0)
	$rgt->pushContent(Sprintf_(ngettext('<b>One</b> submission extension is still active',
					    '<b>%1$d</b> submission extensions are still active',
					    $nSextn),
				   $nSextn),
			  HTML::br());
      if ($nSexpired > 0)
	$rgt->pushContent(Sprintf_(ngettext('<b>One</b> student missed their submission extension deadline',
					    '<b>%1$d</b> students missed their submission extension deadlines',
					    $nSexpired),
				   $nSexpired),
			  HTML::br());
      if (nowBetween($assmt['submissionEnd'], null)) {
	$nRextn = fetchOne("select count(distinct e.who) as n from Extension e"
			   . " inner join Allocation l on e.who = l.reviewer and e.assmtID = l.assmtID"
			   . " where l.lastMarked is null and e.assmtID = $assmtID and e.reviewEnd > "
			   . quote_smart(date_to_mysql(date('Y-m-d H:i:s', time()))), 'n');
	if ($nRextn > 0)
	  $rgt->pushContent(Sprintf_(ngettext('<b>One</b> review extension is still active',
					      '<b>%1$d</b> review extensions are still active',
					      $nRextn),
				     $nRextn),
			    HTML::br());
      }
    }

    if( ! empty($assmt['allocationsDone']) ) {
      $n = fetchOne("SELECT COUNT(*) AS n FROM Allocation WHERE assmtID=$assmtID", 'n' );
      $rgt->pushContent( Sprintf_( ngettext( '<b>One</b> allocation record was created on <b>%2$s</b>',
					     '<b>%1$d</b> allocation records were created on <b>%2$s</b>', $n),
				   $n, formatTimestamp( $assmt['allocationsDone'] )),
			 br());
      $nReviews = fetchOne("SELECT COUNT(*) AS n FROM Allocation WHERE assmtID=$assmtID AND lastMarked IS NOT NULL", 'n' );
      if( nowBetween($assmt['reviewEnd'], null) )
	if( empty($assmt['isReviewsFor']) )
	  $rgt->pushContent( Sprintf_( ngettext( '<b>One</b> review was written',
						 '<b>%1$d</b> reviews were written', $nReviews),
				       $nReviews),
			     br());
	else
	  $rgt->pushContent( Sprintf_( ngettext( '<b>One</b> review was marked',
						 '<b>%1$d</b> reviews were marked', $nReviews),
				       $nReviews),
			     br());
      else
	if( empty($assmt['isReviewsFor']) )
	  $rgt->pushContent( Sprintf_( ngettext( '<b>One</b> review has been written, as at <b>%2$s</b>',
						 '<b>%1$d</b> reviews have been written, as at <b>%2$s</b>', $n),
				       $nReviews, formatTimestamp( time() )),
			     br());
	else
	  $rgt->pushContent( Sprintf_( ngettext( '<b>One</b> review has been marked, as at <b>%2$s</b>',
						 '<b>%1$d</b> reviews have been marked, as at <b>%2$s</b>', $n),
				       $nReviews, formatTimestamp( time() )),
			     br());
    }
  }

  $rgt->pushContent(assignmentCategory($cid, $assmt));

  return HTML(HTML::div(array('id'=>'sideBar'), $nav),
	      $rgt);
}

function authorsAreDescription($assmtID, $authorsAre, $isReviewsFor) {
  switch( $authorsAre ) {
  case 'all':
    $n = fetchOne("SELECT count(*) AS n FROM Author WHERE assmtID = $assmtID", 'n');
    if( $n > 0 )
      return Sprintf_(ngettext('Authors are <b>everyone in the class plus one ad-hoc author</b>',
			       'Authors are <b>everyone in the class plus %d ad-hoc authors</b>', $n), $n);
    else
      return Sprintf_('Authors are <b>everyone in the class</b>');
    break;
  case 'group': return Sprintf_('Authors are <b>groups</b>');
  case 'review':  return Sprintf_('Marks will given for <b>individual reviews from assignment #%d</b>', $isReviewsFor);
  case 'reviewer': return Sprintf_('Markers will assess <b>all reviews</b> written by each reviewer');
  case 'other': return Sprintf_('Authors are <b>listed separately</b>');
  default: return Sprintf_('Authors are <b>not specified</b>');
  }
}

function assignmentCategory( $cid, $assmt ) {
  global $gHaveAdmin;
  if( !$gHaveAdmin)
    return '';
  
  extraHeader('$("#category").change(function() {$.ajax({
 url: "' . $_SERVER['PHP_SELF'] . '",
 data: {action: "setCategory", cid: ' . $cid . ', a: ' . $assmt['assmtID'] . ', c: $("#category").val()},
 type: "POST",
 async: true})});', 'onload');
  $select = HTML::select(array('class'=>'form-control', 'id'=>'category'));
  foreach( array(''=>'', 'test'=>'Test', 'successful'=>'Successful', 'aborted'=>'Aborted', 'unused'=>'Unused') as $value => $text)
    $select->pushContent( HTML::option(array('value'=>$value,
					     'selected'=>$assmt['category']==$value),
				       $text));
  return FormGroup('category',
		   _('Category'),
		   $select);
}


function setCategory( ) {
  list($cid, $assmtID, $categ) = checkREQUEST('_cid', '_a', 'c');
  ensureDBconnected('setCategory');
  checked_mysql_query('UPDATE Assignment SET category=' . quote_smart($categ)
		      . " WHERE assmtID=$assmtID AND courseID=" . cidToClassId($cid));
  exit;
}


function submissionsExpectedAndReceived( $assmtID, $authorsAre ) {
  switch( $authorsAre ) {
  case 'all':
    $expecting = fetchOne( "SELECT COUNT(*) AS n FROM UserCourse"
			   . " WHERE (roles&1)!=0 AND courseID IN"
			   . " (SELECT courseID FROM Assignment WHERE assmtID=$assmtID)", 'n' );
    $submissions = fetchOne( "SELECT COUNT(DISTINCT author) AS n FROM Essay WHERE assmtID=$assmtID and isPlaceholder = 0", 'n');
    break;
  case 'group':
    $expecting = fetchOne( "SELECT COUNT(DISTINCT groupID) AS n FROM GroupUser WHERE assmtID=$assmtID", 'n' );
    $submissions = fetchOne( "SELECT COUNT(DISTINCT groupID) AS n"
			     . " FROM Essay e INNER JOIN GroupUser g"
			     . " ON e.author=g.userID AND e.assmtID=g.assmtID"
			     . " WHERE e.assmtID=$assmtID and isPlaceholder = 0", 'n');
    break;
  case 'other':
    $expecting = fetchOne( "SELECT COUNT(*) AS n FROM Author WHERE assmtID=$assmtID", 'n' );
    $submissions = fetchOne( "SELECT COUNT(DISTINCT author) AS n FROM Essay WHERE assmtID=$assmtID and isPlaceholder = 0", 'n');
    break;
  default:
    $expecting = $submissions = 0;
    break;
  }
  return array( $expecting, $submissions );
}


function describeSubmissions( $reqmts ) {
  $ul = HTML::ul( );
  foreach( explode( "\n", $reqmts) as $requireStr ) {
    //- $requireStr is expected to look like:
    //-    [file | url | inline] ","  [require | optional] "," ...urlencode'd additional key=value requirements...
    list($type, $required, $argStr) = explode( ",", $requireStr );
    parse_str($argStr ?? '', $args);
    $li = HTML::li( );
    if( ! empty($args['prompt']) && $args['prompt'] != '(default)' )
      $li->pushContent( HTML::q( $args['prompt'] ), ', ' );
    else if( $required == 'oneof' ) {
      $li->pushContent( ! isset( $firstAlternative ) ? _('EITHER ') : _('OR ') );
      $firstAlternative = false;
    }
    switch( $type ) {
    case 'file':
      $li->pushContent( Sprintf_( 'a file of type %s', matchFileType( $args['extn'], $args['other'] ) ));
      break;
    case 'text':
      $li->pushContent( Sprintf_('using the Arop&auml; editor' ));
      break;
    }

    $ul->pushContent( $li );
  }
  if( $ul->isEmpty( ) )
    return '';
  else
    return $ul;
}

function matchFileType( $extn, $other ) {
  global $gStandardFileTypes;
  foreach( $gStandardFileTypes as $desc => $e )
    if( $e == $extn ) {
      if( $e == 'other' )
	return $other;
      else
	return $desc;
    }
  return $extn;
}


function instructorAuthor( ) {
  list( $cid, $assmtID ) = checkREQUEST( '_cid', '_assmtID' );

  if( ! isset( $_SESSION['classes'][ $cid ] ) )
    return warning( _('You do not have access to that class'));

  $class = $_SESSION['classes'][ $cid ];
  require_once 'showAllocations.php';
  loadAvailableAssignments( $class );
  foreach( $_SESSION['availableAssignments'] as $aID => $assmt )
    if( $assmt['assmtID'] == $assmtID ) {
      $_REQUEST['aID'] = $aID;
      require_once 'uploadSubmissions.php';
      return upload( );
    }
  return HTML( warning( _('You do not have access to the selected assignment')),
	       BackButton());
}



function activateAssignment( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );
  checked_mysql_query( "UPDATE Assignment SET isActive = 1, whenActivated=IFNULL(whenActivated,NOW()) WHERE assmtID = $assmtID" );
  addPendingMessage( _('The assignment is now active') );
  redirect( 'viewAsst', "assmtID=$assmtID&cid=$cid" );
}


function deactivateAssignment( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );
  checked_mysql_query( "UPDATE Assignment SET isActive = 0 WHERE assmtID = $assmtID" );
  addPendingMessage( _('The assignment is now inactive') );
  redirect( 'viewAsst', "assmtID=$assmtID&cid=$cid" );
}


function deleteAssignment( ) {
  list($assmt, $assmtID, $cid) = selectAssmt('isActive', 'category');
  if (! $assmt)
    return missingAssmt();
  
  if ($assmt['isActive'])
    return HTML(warning(_('The assignment is still active.  It must be de-activated before it can be deleted.')),
		BackButton());

  $hardDeletionPossible = $assmt['category'] == 'test' || $assmt['category'] == 'unused';
  
  if (! isset($_REQUEST['confirmed'])) {
    if (!$hardDeletionPossible)
      return HTML(
	assmtHeading(_('Purging'), $assmt),
	HTML::p(formButton(_('Remove all submissions'), "delete&confirmed&purge&assmtID=$assmtID&cid=$cid")),
	HTML::p(_('(As this is a completed assignment, allocations and reviews will be retained).')),
	HTML::br(),
	formButton(_('Cancel'), "viewAsst&assmtID=$assmtID&cid=$cid"));
    else
      return HTML(
	assmtHeading(_('Deleting'), $assmt),
	HTML::ul(HTML::li(formButton(_('Delete the whole assignment'), "delete&confirmed&assmtID=$assmtID&cid=$cid")),
		 HTML::li(formButton(_('Just purge the submissions'), "delete&confirmed&purge&assmtID=$assmtID&cid=$cid"))),
	HTML::br(),
	formButton(_('Cancel'), "viewAsst&assmtID=$assmtID&cid=$cid"));
  }

  ensureDBconnected('deleteAssignment');

  if (!$hardDeletionPossible || isset($_REQUEST['purge'])) {
    $essay = quote_smart('This submission was purged on ' . date(DATE_RFC822));
    checked_mysql_query("update Essay set compressed = false, overflow = false, extn = 'inline-text', url = null, essay = $essay where assmtID = $assmtID");
    checked_mysql_query("delete from Overflow using Overflow left join Essay on Overflow.essayID = Essay.essayID where Essay.assmtID = $assmtID");
    if (is_dir("essays/$assmtID")) {
      foreach (glob("essays/$assmtID/*") as $file)
	if (is_file($file))
	  unlink($file);
      rmdir("essays/$assmtID");
    }

    checked_mysql_query("update Assignment set aname = concat('*', aname) where assmtID = $assmtID");    
    addPendingMessage(_('The assignment submissions have been purged, and now just contain placeholders.'));
    redirect("viewAsst&assmtID=$assmtID&cid=$cid");
  } else {
    require_once 'Delete.php';
    hardDeleteAssignment($assmtID);
    addPendingMessage(Sprintf_('Assignment <q>%s</q> (#%d) has been deleted',
			       $assmt['aname'],
			       $assmtID));
    redirect("selectClass&cid=$cid");
  }
}
