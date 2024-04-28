<?php
/*
    Copyright (C) 2016 John Hamer <J.Hamer@cs.auckland.ac.nz>

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

function markEssay( ) {
  list( $s, $aID, $cid ) = checkREQUEST( '_s', '_aID', '_cid' );

  if( empty( $_SESSION['allocations'][ $s ] ) )
    securityAlert( "unknown essay - $s" );

  $alloc =& $_SESSION['allocations'][ $s ];

  if( ! isset( $_SESSION['availableAssignments'][ $aID ] ) )
    reportCriticalError( 'markEssay', 'cannot find assignment matching allocation' );

  // Add this string to any URLs that we don't want cached
  $anticache = dechex( time() );

  $assmt = $_SESSION['availableAssignments'][ $aID ];

  //- Read the allocation from disk, just to be sure we have the most
  //- recent version.  This is probably only an issue with group
  //- reviews.
  $diskAlloc = fetchOne( 'SELECT assmtID, allocID, author, reviewer, locked, marks'
			 . " FROM Allocation WHERE allocID = $alloc[allocID]");
  if( ! $diskAlloc )
    reportCriticalError( 'markEssay', "Unable to find allocation " . $alloc['allocID'] );
  
  $now = date('Y-m-d H:i:s', time() );
  if( ! emptyDate($assmt['reviewEnd']) && $now > $assmt['reviewEnd'] )
    return HTML( warning( _('No further marking is permitted, as the review period for this assignment has ended')),
		 BackButton() );

  if( $diskAlloc['locked'] )
    return HTML( warning( _('No further marking is permitted, as this allocation has been locked (to allow early viewing of feedback)')),
		 BackButton() );

  $xml = rubricForAllocID( $diskAlloc, 'edit', $assmt['review-group-members'] );

  $omit = isset( $_REQUEST['omitMenu'] ) ? '&omitMenu' : '';
  $idx = $alloc['allocIdx'];
  if( isFeedbackAssignment( $assmt ) )
    return HTML( HTML::h1( Sprintf_('View allocation #%d for assignment <q>%s</q>',
				    $idx, $assmt['aname'] )),
                 HTML::hr( ),
                 $xml );
  
  $attrs = array('method'=>'post',
		 'id'=>'form',
		 'onsubmit'=>'return checkInputs()',
		 'action'=>"$_SERVER[PHP_SELF]?action=update&s=$s&cid=$cid&aID=$aID$omit" );
  if( $assmt['nReviewFiles'] > 0 )
    $attrs['enctype'] = 'multipart/form-data';
  
  $identity = userIdentity($diskAlloc['author'], $assmt, 'author');
  $title = $assmt['anonymousReview'] ? "allocation #$idx" : $identity;
  extraHeader('check-inputs.js', 'script');
  return HTML(HTML::h1(Sprintf_('Marking for %s', $title)),
	      HTML::hr(),
	      HTML::noscript(HTML::blockquote(HTML::em(HTML::raw(_('Your browser is not using Javascript.  The Arop&auml; editor is disabled. '))),
					      HTML::b(HTML::raw(_('If you previously entered comments using the Arop&auml; editor, they will appear in HTML format.'))),
					      HTML::hr())),
	      EnableTinyMCE('Mark'),
	      HTML::form($attrs,
			 Modal('incomplete-dialog',
			       _('Your review is incomplete'),
			       HTML::p(_('Not all fields have been entered. If you save now, you will need to return later to complete your review. '),
				       HTML::b(_('Are you sure you want to save now?'))),
			       HTML::button(array('type'=>'button',
						  'class'=>'btn',
						  'data-dismiss'=>'modal',
						  'onclick'=>'$("#form").submit()'),
					    _('Save')),
			       array('onclick'=>'saveAnyway = false')),
			 $xml,
			 ButtonToolbar(submitButton(_('Save')), CancelButton())));
}


function rubricForAllocID( $alloc, $editable, $reviewerGroups ) {
  ensureDBconnected( 'rubricForAllocID' );

  $assmt = fetchOne( "SELECT rubricID from Assignment WHERE assmtID = $alloc[assmtID]");
  if( ! $assmt )
    return warning( _('Unable to find the assignment!'));

  parse_str($alloc['marks'] ?? '', $marks);

  $madeByMap = array( );

  $comments = array( );
  $rs = checked_mysql_query( 'SELECT item, comments, whenMade, madeBy, IFNULL(uident, CONCAT("u-", userID)) AS uident'
			     . ' FROM Comment LEFT JOIN User ON madeBy = userID'
                             . " WHERE allocID = $alloc[allocID]"
			     . ' ORDER BY whenMade ASC' );
  while( $c = $rs->fetch_assoc() ) {
    if( $c['madeBy'] == $_SESSION['userID'] ) {
      $c['text'] = $c['comments'];
      unset( $c['comments'] );
    } else
      $c['madeByIdentity'] = in_array($c['madeBy'], $reviewerGroups) ? $c['uident'] : AnonymiseAuthor( $madeByMap, $c['madeBy'], 'R-' ); //- R- because this is a comment by a reviewer
    if (!isset($comments[$c['item']]))
      $comments[$c['item']] = array();
    $comments[$c['item']][] = $c;
  }

  $reviewFiles = array( );
  $rs = checked_mysql_query( 'SELECT reviewFileID, item, madeBy FROM ReviewFile'
                             . " WHERE allocID = $alloc[allocID]"
			     . ' ORDER BY whenUploaded ASC' );
  while( $c = $rs->fetch_assoc() ) {
    $c['madeBy'] = AnonymiseAuthor($madeByMap, $c['madeBy'], 'R-'); //- Should always be You.
    $reviewFiles[$c['item']] = array($c);
  }

  require_once 'BlockParser.php';
  list( $xml ) = TransformRubricByID( $assmt['rubricID'], $marks, $comments, $reviewFiles, $editable, $alloc );
  return $xml;
}


function lockAllocations( ) {
  list( $aID, $cid ) = checkREQUEST( '_aID', '_cid' );

  if( ! isset( $_SESSION['availableAssignments'][ $aID ] ) )
    securityAlert( 'bad assmtIdx' );

  $assmt =& $_SESSION['availableAssignments'][ $aID ];

  if( ! isset( $_REQUEST['confirm'] )) {
    return HTML( warning( _('Please confirm you wish to lock your reviews')),
		 HTML::p( _('Locking your reviews will prevent you from making any further changes.
However, locking will allow other reviewers and authors to view your feedback,
and you will also be able to view feedback from other reviewers who have locked their work.')),

		 // *** If there are marks missing, warn the user the reviewing is incomplete.
		 HTML::p( _('Regardless of whether you lock or not, feedback can be viewed after the review period has ended.' )),
		 HTML::p( HTML::b( _('You cannot undo this action.')) ),
		 formButton( _('Go ahead and lock my reviews'), "lock&cid=$cid&confirm&aID=$aID" ),
		 CancelButton());
  } else {
    checked_mysql_query( 'UPDATE Allocation SET locked=TRUE'
			 . " WHERE assmtID = $assmt[assmtID]"
			 . " AND reviewer = $_SESSION[userID]");
    foreach( $_SESSION['allocations'] as $i => $alloc )
      if( $alloc['assmtID'] == $assmt['assmtID'] )
	$_SESSION['allocations'][ $i ][ 'locked' ] = true;

    addPendingMessage( Sprintf_('Your reviews for <q>%s</q> are now locked', $assmt['aname']));

    redirect( 'viewStudentAsst', "cid=$cid&aID=$aID" );
  }
}
