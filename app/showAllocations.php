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


function studentClassView( $cid, $class ) {
  loadAvailableAssignments( $class );
  $ul = HTML::ul( );
  foreach( $_SESSION['availableAssignments'] as $aID => $assmt )
    if( $assmt['courseID'] == $class['courseID'] )
      $ul->pushContent( HTML::li( callback_url( $assmt['aname'], "viewStudentAsst&cid=$cid&aID=$aID" ),
				  Sprintf_(': submission end: %s; reviewing end: %s',
					   formatDateString( $assmt['submissionEnd'] ),
					   formatDateString( $assmt['reviewEnd'] ))));
  
  if( $ul->isEmpty( ) )
    $ul->pushContent( HTML::li( _('You do not currently have any assignments')));

  return HTML(pendingMessages(),
	      HTML::h1(className($cid)),
	      $ul);
}

function viewStudentAsst( ) {
  list($cid, $aID) = checkREQUEST('_cid', '_aID');

  if (!isset($_SESSION['availableAssignments'][$aID]))
    securityAlert('Attempt to select non-existent assignment');
  return viewStudentAssignment($cid, $aID);
}

function viewStudentAssignment($cid, $aID) {
  $assmt = $_SESSION['availableAssignments'][$aID];
  return HTML::div(array('class'=>'list-page'),
		   pendingMessages(),
		   HTML::h1($assmt['aname'], ' ', HTML::small('(', className($cid), ')')),
		   showUploading($cid, $aID, $assmt),
		   showReviewing($cid, $aID, $assmt),
		   showFeedback($cid, $aID, $assmt));
}


function showUploading( $cid, $aID, $assmt ) {
  $div = HTML::div();
  $title = _('Submit documents');
  if( ! $assmt['can-upload'] )
    $div->pushContent(message(_('You are not required to upload a submission for this assignment')));
  else if( nowBetween( null, $assmt['submissionEnd'] ) ) {
    if( count( $assmt['uploaded-essays'] ) > 0 ) {
      $title = _('You have submitted documents');
      $ul = HTML::ul();
      foreach( $assmt['uploaded-essays'] as $essay ) {
	$desc = $essay['description'];
	if ($essay['extn'] == 'inline-text' && strpos($desc, 'Aropa') === false)
	  $desc = HTML(HTML::q($desc), HTML::raw(_(', entered in the Arop&auml; editor')));
	$ul->pushContent(HTML::li($desc,
				  _(' at '),
				  empty($essay['whenUploaded'])
				  ? _('an unknown time')
				  : formatTimestamp($essay['whenUploaded'])));
      }
      $div->pushContent($ul);
      $link = _('Click here to check or change your submitted documents');
    } else {
      $title = _('You still need to submit documents');
      $link = _('Click here to submit documents for this assignment');
    }

    $div->pushContent(Button($link, "upload&aID=$aID&cid=$cid"));
    if( ! emptyDate( $assmt['submissionEnd'] ) )
      $div->pushContent(message(Sprintf_('Submissions are due by %s',
					 formatDateString( $assmt['submissionEnd']))));

  } else {
    $div->pushContent(message(Sprintf_('Submissions for <q>%s</q> closed on %s',
				       $assmt['aname'],
				       formatDateString( $assmt['submissionEnd'] ))));
    if( count( $assmt['uploaded-essays'] ) > 0 )
      $div->pushContent(Button(_('Click here to view the documents you submitted for this assignment'),
			       "reviewUploads&aID=$aID&cid=$cid"));
  }
  
  return HTML(HTML::h2($title), $div, HTML::hr());
}

function showReviewing( $cid, $aID, $assmt ) {
  if (!$assmt['expecting-reviewing'])
    return;
  $reviewDiv = HTML::div();
  $reviewStart = $assmt['submissionEnd'];
  $reviewEnd   = $assmt['reviewEnd'];
  $reviewingStarted = nowBetween( $reviewStart, null );
  $reviewingEnded   = nowBetween( $reviewEnd, null );

  if( $reviewingStarted ) {
    loadPendingAllocations($assmt);
    $noneIncomplete = true;
    $someUnlocked   = false;
    $dl = HTML( );
    $seen = array( );
    $section = $reviewingEnded
      ? array( gettext_noop('Marked') )
      : array( gettext_noop('Pending'),
	       gettext_noop('Ready to review'),
	       gettext_noop('Ready to view'),
	       gettext_noop('Completed') );
    foreach( $section as $ready ) {
      $ul = HTML::div();
      foreach( $_SESSION['allocations'] as $i => $alloc )
	if( ! isset( $seen[ $i ] )
	    && $alloc['assmtID'] == $assmt['assmtID']
	    && $alloc['reviewer'] == $assmt['review-group']
	    ) {
	  if( showAllocInThisGroup( $alloc, $ready, isFeedbackAssignment( $assmt ) ) ) {
	    $seen[ $i ] = true;
	    $ul->pushContent( showThisAllocation( $assmt, $aID,  $i, $cid ) );
	    if( $ready != 'Completed' ) $noneIncomplete = false;
	    if( ! $alloc['locked'] )    $someUnlocked   = true;
	  }
	}
      
      if( ! $ul->isEmpty( ) ) {
	if( $assmt['allowLocking'] && ! $reviewingEnded && $noneIncomplete && $someUnlocked )
	  $ul->pushContent(Button(_('Lock your reviews'),
                                  "lock&cid=$cid&aID=$aID",
				  array('title'=>_('Locking means you cannot make any change to your reviews, but will let you (and others) view feedback before the review period is complete.'))));
	$ul->pushContent(br());
	if( ! $reviewingEnded ) $dl->pushContent(HTML::h4(_($ready)));
	$dl->pushContent( $ul );
      }
    }
    if( $dl->isEmpty( ) )
      $dl->pushContent(_('You have no allocations to review'));
    else {
      if( empty($assmt['basepath']) ) {
	require_once 'download.php';
	$crc = sprintf( '%u', crc32( $_SESSION['userID'] . $assmt['assmtID']) );
	$d = recordDownloadInSession( array( 'all'=>true,
					     'assmtID'=>$assmt['assmtID'],
					     'courseID'=>$assmt['courseID'],
					     'name'   =>$assmt['aname'],
					     'anon'   =>$assmt['anonymousReview']));
	if( ! $reviewingEnded && empty( $assmt['isReviewsFor'] ) )
	  $dl->pushContent(HTML::span(array('class'=>'glyphicon glyphicon-download')),
			   Button(_('Download all submissions'),
				  "download&download=$d&oid=$crc&cid=$cid&aID=$aID",
				  array('title'=>_('The submissions for review will be downloaded as a ZIP file'))));
      }
    }
  } else
    $reviewDiv->pushContent(message(Sprintf_('Reviewing starts at %s',
					     formatDateString($reviewStart))));
  
  if( ! emptyDate( $reviewEnd ) )
    $due = $reviewingEnded
      ? Sprintf_('Reviewing ended at %s', formatDateString( $reviewEnd ))
      : Sprintf_('Reviews are due by %s', formatDateString( $reviewEnd ));
  else
    $due = Sprintf_('Reviewing for <q>%s</q>', $assmt['aname'] );
  
  $reviewDiv->pushContent(HTML::p($due), $dl);

  return HTML(HTML::h2(_('Your reviewing allocations')), $reviewDiv, HTML::hr());
}

function loadPendingAllocations($assmt) {
  // require: reviewing started
  // *** Fails for groups - Essay is recorded under the actual userID of the author, not the group ID
  // *** However, we don't currently support extensions for author groups anyway :-(
  if ($assmt['authorsAre'] == 'group')
    return;
  $authors = array();
  foreach ($_SESSION['allocations'] as $idx => $alloc)
    if ($alloc['assmtID'] == $assmt['assmtID'] && $alloc['reviewer'] == $_SESSION['userID']) {
      $authors[$alloc['author']] = $idx;
      $_SESSION['allocations'][$idx]['pending'] = false;
    }

  if (empty($authors))
    return;
  foreach (fetchAll('select x.who, x.submissionEnd, sum(e.isPlaceholder) as p, sum(1 - e.isPlaceholder) as s'
		    . ' from Extension x'
		    . ' inner join Essay e on'
		    .        ' e.assmtID = x.assmtID'
		    .        ' and e.author = x.who'
		    .        ' and x.submissionEnd is not null'
		    . " where x.assmtID = $assmt[assmtID]"
		    . ' and x.who in (' . join(',', array_keys($authors)) . ')'
		    . ' group by x.who')
	   as $pending) {
    unset($text);
    $a = _('This author has been granted an extension until ') . formatDateString($pending['submissionEnd']);
    $b = _('This author was granted an extension, but no submission was received');
    if (nowBetween(null, $pending['submissionEnd'])) {
      if ($pending['p'] > 0)
	$text = $a;
      else if ($pending['s'] == 0)
	$text = $b;
    } else if ($pending['s'] == 0)
      $text = $b;
    if (isset($text)) {
      $idx = $authors[$pending['who']];
      $_SESSION['allocations'][$idx]['pending'] = true;
      $_SESSION['allocations'][$idx]['pending-text'] = $text;
    }
  }
}


function showAllocInThisGroup( $alloc, $ready, $isFeedbackOnly ) {
  if( $isFeedbackOnly )
    return $ready == 'Ready to view';

  if ($ready == 'Pending')
    // Waiting for an assignment extension
    return $alloc['pending'];

  switch( $ready ) {
  case 'Marked': return true; // The reviewing period has ended
  case 'Ready to view':
    return empty($alloc['lastViewed']);
  case 'Ready to review':
    return !empty($alloc['lastViewed']) && $alloc['status'] != 'complete';
  case 'Completed':
    return $alloc['status'] == 'complete';
  }
  return false; //- "Cannot happen"
}


function showFeedback( $cid, $aID, $assmt ) {
  if (!$assmt['expecting-feedback'])
    return;
  $table = HTML::div();
  $feedbackStart = chooseDate( $assmt['feedbackStart'], $assmt['reviewEnd'] );
  if( nowBetween( $feedbackStart, $assmt['feedbackEnd'] ) )
    if( $assmt['have-feedback'] ) {
      if( ( $assmt['restrictFeedback'] == 'all'
	    && ! empty( $assmt['missed-some-feedback'] )
	    && count( $assmt['gave-some-feedback'] ) < $assmt['nPerReviewer'] )
	  ||
	  ( $assmt['restrictFeedback'] == 'some' && empty( $assmt['gave-some-feedback'] ) )
	  )
	$table->pushContent(message(_('You cannot view your feedback because you did not complete your allocated reviews.')));
      else {
	//- The link will show consolidated reviewing for all allocations to this author
	if( !empty($assmt['isReviewsFor']) ) {
	  foreach( $_SESSION['availableAssignments'] as $origAssmt )
	    if( $origAssmt['assmtID'] == $assmt['isReviewsFor'] ) {
	      $linkText = Sprintf_('Click here for feedback on the reviews you wrote for %s',
				   $origAssmt['aname']);
	      break;
	    }
	}
	if( !isset($linkText) )
	  $linkText = Sprintf_('Click here for feedback on your submission for %s', $assmt['aname']);
	$table->pushContent(Button($linkText, "showComments&aID=$aID&cid=$cid"));
      }
    } else
      $table->pushContent(message(_('No feedback has been provided by reviewers')));
  else
    $table->pushContent(message(Sprintf_('Feedback will be available from %s',
					 formatDateString($feedbackStart))));

  return HTML(HTML::h2(_('Feedback on your submission')), $table, HTML::hr());
}




//- $i is the index of $alloc in $_SESSION['allocations'].  Used to find
//- $alloc in viewEssay.
function showThisAllocation( &$assmt, $aID, $i, $cid ) {
  $alloc = $_SESSION['allocations'][ $i ];

  if( ! empty( $alloc['allocIdx'] ) )
    $idx = '' . $alloc['allocIdx'];
  else
    $idx = '?'; //- "cannot happen"

  // Add this string to any URLs that we don't want cached
  $anticache = dechex( time() );

  if( $assmt['anonymousReview'] )
    $view = Sprintf_('Allocation %s', $idx);
  else
    $view = Sprintf_('Submission from %s', userIdentity( $alloc['author'], $assmt, 'author' ));

  if (!nowBetween($assmt['submissionEnd'], $assmt['reviewEnd']))
    $canEdit = false;
  else
    $canEdit = !$alloc['locked'];

  $row = HTML::div(array('class'=>'row'));
  $row->pushContent(HTML::label(array('for'=>"alloc-$i",
				      'class'=>'col-md-2'),
				$view));
  $buttons = HTML::div(array('class'=>'btn-toolbar',
			     'id'=>"alloc-$i",
			     'role'=>'group'));
  if ($alloc['pending'])
    $buttons->pushContent($alloc['pending-text']);
  else if( empty( $alloc['lastViewed'] ) && empty( $alloc['lastMarked'] ) )
    $buttons->pushContent(Button(_('View submission'), "view&cid=$cid&aID=$aID&s=$i&$anticache"));
  else {
    $buttons->pushContent(Button(_('View submission'),
				 "view&cid=$cid&aID=$aID&s=$i&$anticache",
				 array('title'=>_('Last viewed ') . formatTimestamp($alloc['lastViewed']))));
    if( $canEdit ) {
      if( emptyDate( $alloc['lastMarked'] ) )
	$buttons->pushContent(Button(_('Write your review'),
				     "mark&cid=$cid&aID=$aID&s=$i&$anticache"));
      else
	$buttons->pushContent(Button($alloc['status'] == 'partial'
				     ? _('Complete your review')
				     : _('Modify your review'),
				     "mark&cid=$cid&aID=$aID&s=$i&$anticache",
				     array('title'=>_('Last changed ') . formatTimestamp($alloc['lastMarked']))));
    }
  }

  $now = date('Y-m-d H:i:s');
  if( emptyDate( $assmt['feedbackEnd'] ) || $now < $assmt['feedbackEnd'] )
    $canSeeFeedback = $alloc['locked'] || $now >= chooseDate( $assmt['feedbackStart'],  $assmt['reviewEnd'], $now );
  else
    $canSeeFeedback = false;

  if( $assmt['restrictFeedback'] != 'none' && ! isset( $assmt['gave-some-feedback'][ $alloc['allocID'] ] ) )
    $canSeeFeedback = false;

  if( $canSeeFeedback )
    $buttons->pushContent(Button(_('Read all reviews of this submission'),
				 "showReviewerComments&cid=$cid&aID=$aID&s=$i&$anticache"));
  $row->pushContent($buttons);
  return HTML::div(array('class'=>'form-inline', 'role'=>'group'), $row);
}

function loadAvailableAssignments( $class ) {
  if( empty($_SESSION['userID']) ) {
    $_SESSION['availableAssignments'] = array();
    return;
  }

  ensureDBconnected( 'loadAvailableAssignments' );

  $now = date('Y-m-d H:i:s');

  if( isset( $_SESSION['availableAssignments'] )
      && isset( $_SESSION['upgraded'] )
      && $_SESSION['assignments-for-class'] == $class['courseID']
      ) {
    //- Look to see if any of the assignments we have cached in
    //- $_SESSION have changed beneath us.  The lastMod table is updated
    //- in Allocation::save and editGroups.php(saveGroups).
    if( ! isset( $_SESSION['lastMod'] ) )
      $_SESSION['lastMod'] = array( );

    if( isset( $_SESSION['session-start'] ) )
      $recent = ' AND lastMod > ' . quote_smart($_SESSION['session-start']);
    else
      $recent = '';

    $updateNeeded = array( );
    $rs = checked_mysql_query( "SELECT m.assmtID, m.lastMod"
			       . " FROM LastMod m JOIN Assignment a ON m.assmtID=a.assmtID"
			       . " WHERE a.courseID = $class[courseID]" . $recent );
    while( $row = $rs->fetch_assoc() ) {
      if( ! isset( $_SESSION['lastMod'][ $row['assmtID'] ] )
          || $_SESSION['lastMod'][ $row['assmtID'] ] < $row['lastMod']
          )
        $updateNeeded[] = $row['assmtID'];
      $_SESSION['lastMod'][ $row['assmtID'] ] = $row['lastMod'];
    }

    if( count( $updateNeeded ) == 0 )
      return;

    unset( $_SESSION['golive'] ); //- simple minded, but should be reliable
    purgeSession( $updateNeeded );

    $cond = ' AND assmtID IN (' . join(',', $updateNeeded) . ')';

  } else {
    $cond = '';

    $_SESSION['upgraded'] = true;
    $_SESSION['assignments-for-class'] = $class['courseID'];
    $_SESSION['availableAssignments'] = array( );
    $_SESSION['allocations']          = array( );
    $_SESSION['not-yet-started']      = array( );
    $_SESSION['session-start'] = $now;
  }

  $userID = (int)$_SESSION['userID'];

  $rs = checked_mysql_query( 'SELECT * FROM Assignment'
			     . ' WHERE courseID = ' . (int)$class['courseID']
			     . ' AND isActive' . $cond
			     . ' ORDER BY submissionEnd DESC');
  while( $assmt = $rs->fetch_assoc() ) {
    $assmtID = $assmt['assmtID'];

    $assmt['expecting-feedback']  = $assmt['selfReview'];
    $assmt['expecting-reviewing'] = $assmt['selfReview'];

    if (!empty($assmt['isReviewsFor'])) {
      $isReviewsFor = fetchOne("select * from Assignment where assmtID = $assmt[isReviewsFor]");
      $authorsAreGroup = $isReviewsFor['reviewersAre'] == 'group';
      $isGroup = $authorsAreGroup;
      $groupAssmtId = $assmt['isReviewsFor'];
    } else {
      $authorsAreGroup = $assmt['authorsAre'] == 'group';
      $isGroup = $assmt['reviewersAre'] == 'group' || $assmt['authorsAre'] == 'group';
      $groupAssmtId = $assmtID;
    }
    
    if ($isGroup) {
      $groupID = fetchOne('select groupID from GroupUser'
			  . " where assmtID = $groupAssmtId and userID = $userID",
			  'groupID');
      if ($groupID)
	$groupMembers = fetchAll("select userID from GroupUser"
				 . " where assmtID = $groupAssmtId AND groupID = $groupID",
				 'userID');
      if (empty($groupMembers))
	$groupMembers = array(0); // Avoid SQL syntax errors when forming query
    }

    if( $assmt['reviewersAre'] == 'group' && $groupID ) {
      $assmt['review-group'] = -$groupID;
      $assmt['review-group-members'] = $groupMembers;
    } else {
      $assmt['review-group'] = $userID;
      $assmt['review-group-members'] = array($userID);
    }

    if ($authorsAreGroup && $groupID) {
      $assmt['author-group'] = -$groupID;
      $assmt['author-group-members'] = $groupMembers;
    } else if ($assmt['authorsAre'] == 'review') {
      $assmt['author-group'] = $userID;
      $assmt['author-group-members'] = fetchAll("select allocID FROM Allocation"
						. " where assmtID = $assmt[isReviewsFor]"
						. " and reviewer = $userID",
						'allocID');
    } else {
      $assmt['author-group'] = $userID;
      $assmt['author-group-members'] = array($userID);
    }
    
    $assmt['gave-some-feedback'] = array( );
    $assmt['missed-some-feedback'] = array( );
    $assmt['have-feedback'] = false;

    if ($assmt['authorsAre'] == 'review') {
      if ($assmt['anonymousReview'])
	$query = 'select l.*, l2.reviewer as l2reviewer from Allocation l'
	  . ' inner join Allocation l2 on l.author = l2.allocID'
	  . " where (l.reviewer = " . $assmt['review-group'] . " or l2.reviewer = $userID)"
	  . " and l.assmtID = $assmtID"
	  . " order by l.assmtID, l2.author asc";
      else
	$query = 'select l.*, l2.reviewer as l2reviewer from Allocation l'
	  . ' inner join Allocation l2 on l.author = l2.allocID'
	  . ' left join User u on u.userID = l2.author'
	  . " where (l.reviewer = " . $assmt['review-group'] . " or l2.reviewer = $userID)"
	  . " and l.assmtID = $assmtID"
	  . ' order by l.assmtID, ifnull(u.uident, concat("u-", l2.author)) asc';
    } else
      $query = 'select * from Allocation'
	. " where (reviewer = " . $assmt['review-group']
	.     " or author = " . $assmt['author-group'] . ")"
	. " and assmtID = $assmtID"
	. ' order by assmtID, ' . ($assmt['anonymousReview'] ? 'allocID' : 'author') . ' asc';

    $rsA = checked_mysql_query($query);
    while( $alloc = $rsA->fetch_assoc() ) {
      $alloc['has-feedback'] = false;
      $authorsToCheck[] = $alloc['author'];
      $author = $assmt['authorsAre'] == 'review' ? $alloc['l2reviewer'] : $alloc['author'];
      if ($author == $assmt['author-group'] || in_array($author, $assmt['author-group-members'])) {
	$assmt['expecting-feedback'] = true;
	if (!empty($alloc['lastMarked'])) {
	  if ($assmt['showMarksInFeedback'])
	    $alloc['has-feedback'] = true;
	  else
	    $alloc['has-feedback'] =
	      ($assmt['nReviewFiles'] > 0 && fetchOne("select count(*) as n from ReviewFile where allocId = $alloc[allocID] limit 1", 'n') > 0) ||
	      (!empty($assmt['commentItems']) && fetchOne("select count(*) as n from Comment where allocID = $alloc[allocID] limit 1", 'n') > 0);
	}
	
	if ($alloc['has-feedback']) $assmt['have-feedback'] = true;
      }

      if ($alloc['reviewer'] == $assmt['review-group']) {
	$assmt['expecting-reviewing'] = true;
	$assmt['expecting-feedback'] = true;
	if (empty($alloc['lastMarked']))
	  $assmt['missed-some-feedback'][$alloc['allocID']] = true;
	else
	  $assmt['gave-some-feedback'][$alloc['allocID']] = true;
      }

      $_SESSION['allocations'][firstFreeIndex($_SESSION['allocations'])] = $alloc;
    }

    parse_str($assmt['markItems'] ?? '', $markItems);
    $assmt['mark-items'] = $markItems;
    $assmt['comment-items'] = commentLabels($assmt);

    //- Check for any time extension granted for this assignment
    $ext = fetchOne( 'SELECT submissionEnd, reviewEnd FROM Extension'
		     . " WHERE assmtID = $assmtID AND who = $userID" );
    if( $ext ) {
      if( ! emptyDate( $ext['reviewEnd'] )
	  && ! emptyDate( $assmt['reviewEnd'] )
	  && $ext['reviewEnd'] > $assmt['reviewEnd'] )
	$assmt['reviewEnd'] = $ext['reviewEnd'];

      if( ! emptyDate( $ext['submissionEnd'] )
	  && ! emptyDate( $assmt['submissionEnd'] )
	  && $ext['submissionEnd'] > $assmt['submissionEnd'] )
	$assmt['submissionEnd'] = $ext['submissionEnd'];
    }

    //- Authors are either all students in the class, or an ad-hoc list.  See homePage, in aropa.php
    if( $assmt['isReviewsFor'] )
      $assmt['can-upload'] = false;
    else
      $assmt['can-upload'] =
	($assmt['authorsAre'] != 'other' && ($class['roles']&1) != 0)
	||
	( $assmt['authorsAre'] == 'other'
	  && fetchOne("SELECT author FROM Author WHERE assmtID=$assmtID AND author=$userID", 'author'));
    
    if( $assmt['can-upload'] )
      $assmt['uploaded-essays'] = fetchAll( "SELECT essayID, reqIndex, extn, description, whenUploaded FROM Essay"
					    . " WHERE assmtID=$assmtID"
					    . " AND author IN (" . join(',', $assmt['author-group-members'] ) . ")"
					    . " AND IFNULL(isPlaceholder,0) <> 1" );

    if( $assmt['can-upload'] || $assmt['expecting-reviewing'] || $assmt['expecting-feedback'] )
      $_SESSION['availableAssignments'][] = $assmt;
  }

  //- Generate a small "allocation number" for each review, using a
  //- separate sequence for each assignment.
  foreach( $_SESSION['availableAssignments'] as $assmt ) {
    $idx = 1;
    foreach( $_SESSION['allocations'] as $i => $a )
      if( $assmt['assmtID'] == $a['assmtID'] && $a['reviewer'] == $assmt['review-group'] )
	$_SESSION['allocations'][ $i ]['allocIdx'] = $idx++;
  }

  updateSelfReview( $class );
}


function updateSelfReview( $class ) {
  $db = ensureDBconnected('updateSelfReview');
  foreach( $_SESSION['availableAssignments'] as $assmt )
    if( $assmt['selfReview'] ) {
      if( $assmt['authorsAre'] == 'other' && ! in_array( $assmt['assmtID'], $class['author'] ) )
	//- Only self-review when the student is actually an author
	continue;

      if( $assmt['authorsAre'] == 'review' || $assmt['authorsAre'] == 'reviewer' )
	continue;

      if( empty($assmt['uploaded-essays']) )
	continue;

      $found = false;
      foreach( $_SESSION['allocations'] as $alloc )
        if( $alloc['assmtID'] == $assmt['assmtID'] && $alloc['author'] == $alloc['reviewer'] ) {
          $found = true;
          break;
        }
      if( ! $found ) {
        $self = $_SESSION['userID'];

        checked_mysql_query( 'INSERT INTO Allocation '
                             . '(assmtID, reviewer, author, tag) '
                             . 'VALUES ('
                             . $assmt['assmtID'] . ','
                             . $self . ','
                             . $self . ','
                             . '\'SELF\''
                             . ')' );
	$n = 0;
	foreach( $_SESSION['allocations'] as $alloc )
	  if( $alloc['assmtID'] == $assmt['assmtID'] )
	    $n = max( $n, $alloc['allocIdx'] );

        $_SESSION['allocations'][] = array('allocID' => $db->insert_id,
					   'assmtID' => $assmt['assmtID'],
                                           'reviewer'=> $self,
                                           'author'  => $self,
                                           'tag'     => 'SELF',
					   'allocIdx' => $n+1,
                                           'lastViewed'     =>NULL,
                                           'lastMarked'     =>NULL,
                                           'lastResponse'   =>NULL,
                                           'locked'         =>false,
                                           'marks'          =>NULL,
                                           'choices'        =>NULL,
                                           'feedbackMarks'  =>NULL,
                                           'feedbackChoices'=>NULL,
                                           'lastFeedback'   =>NULL );
      }
    }
}

function purgeSession( $taintedIDs ) {
  $stillAvail = array( );
  foreach( $_SESSION['availableAssignments'] as $assmt )
    if( ! in_array( $assmt['assmtID'], $taintedIDs ) )
      $stillAvail[] = $assmt;
  $_SESSION['availableAssignments'] = $stillAvail;

  $stillNotYet = array( );
  foreach( $_SESSION['not-yet-started'] as $assmt )
    if( ! in_array( $assmt['assmtID'], $taintedIDs ) )
      $stillNotYet[] = $assmt;
  $_SESSION['not-yet-started'] = $stillNotYet;

  foreach( $_SESSION['allocations'] as $idx => $alloc )
    //- Leave any unused allocation keys in place
    if( in_array( $alloc['assmtID'], $taintedIDs ) )
      $_SESSION['allocations'][ $idx ] = NULL;
}


function firstFreeIndex( $arr ) {
  for( $i = 0; $i < count( $arr ); $i++ )
    if( empty( $arr[ $i ] ) )
      return $i;
  return count( $arr );
}
