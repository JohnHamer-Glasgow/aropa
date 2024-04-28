<?php
/*
    Copyright (C) 2013 John Hamer <J.Hamer@acm.org>

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

class Allocations {
  var $allocations;
  var $byReviewer;
  var $byEssay;
  var $rLoad;
  var $eLoad;

  function __construct( $assmtID, $select = 'all', $tag = NULL ) {
    $this->allocations      = array( );
    $this->byReviewer       = array( );
    $this->byEssay          = array( );
    $this->assmtID          = $assmtID;
    $this->rLoad            = array( );
    $this->eLoad            = array( );

    ensureDBconnected( 'Allocations::Allocations' );

    if( ! in_array( $select, array( 'all', 'fixedOnly', 'marks' ) ) )
      reportCriticalError( 'Allocations::Allocations', "Bad value for select - $select" );

    $q = 'SELECT allocID, tag, reviewer, author, marks, lastViewed, lastMarked, lastResponse, lastSeen, status'
      . " FROM Allocation WHERE assmtID = $assmtID";
    if( $select == 'fixedOnly' )
      $q .= ' AND ( lastViewed IS NOT NULL OR lastMarked IS NOT NULL )';
    else if( $select == 'marks' )
      $q .= ' AND marks IS NOT NULL';

    if( ! empty( $tag ) )
      $q .= ' AND tag = ' . quote_smart( $tag );

    $rs = checked_mysql_query( $q . " order by allocID" );
    while( $row = $rs->fetch_assoc() )
      if( empty( $tag ) || $tag == $row['tag'] ) {
        parse_str($row['marks'] ?? '', $marks);
        $row['marks'] = $marks;
	$row['fixed'] = true;
	$this->addRecord( $row );
      } else {
        incr( $this->rLoad, $row['reviewer'] );
        incr( $this->eLoad, $row['author']   );
      }
  }

  function reset( ) {
    foreach( $this->allocations as $key => $alloc )
      if( ! isset( $alloc['fixed'] ) ) {
	unset( $this->byReviewer[ $alloc['reviewer'] ] );
	unset( $this->byEssay[ $alloc['author'] ] );
	unset( $this->allocations[ $key ] );
      }
  }

  function essayCount( $r ) {
    return isset( $this->byReviewer[ $r ] ) ? count($this->byReviewer[ $r ]) : 0;
  }

  function reviewCount( $e ) {
    return isset( $this->byEssay[ $e ] ) ? count($this->byEssay[ $e ]) : 0;
  }

  function allocationCount( ) {
    return count( $this->allocations );
  }

  function reviewerLoad( $r ) {
    $load = $this->essayCount( $r );
    if( isset( $this->rLoad[ $r ] ) )
      $load += $this->rLoad[ $r ];
    return $load;
  }

  function essayLoad( $e ) {
    $load = $this->reviewCount( $e );
    if( isset( $this->eLoad[ $e ] ) )
      $load += $this->eLoad[ $e ];
    return $load;
  }


  function reviewsPerEssay( ) {
    $numReviews = array( );
    foreach( $this->byEssay as $e => $rs ) {
      $numReviews[ $e ] = 0;
      foreach( $rs as $a )
        if( ! empty( $a['marks'] ) )
          $numReviews[ $e ]++;
    }
    return $numReviews;
  }


  function alreadyAllocated( $r, $e ) {
    return
         isset( $this->byReviewer[ $r ] )
      && isset( $this->byReviewer[ $r ][ $e ] );
  }

  function addRecord( $record ) {
    $this->allocations[] = $record;
    $r = $record['reviewer'];
    $e = $record['author'];
    $this->byReviewer[ $r ][ $e ] =& $record;
    $this->byEssay[    $e ][ $r ] =& $record;
  }

  function add( $r, $e, $tag = null ) {
    if( $this->alreadyAllocated( $r, $e ) )
      return false;

    $this->addRecord( array( 'reviewer' => $r, 'author' => $e, 'tag' => $tag ) );
    return true;
  }

  function addFixed( $r, $e ) {
    if( $this->alreadyAllocated( $r, $e ) )
      return false;

    $this->addRecord( array( 'reviewer' => $r, 'author' => $e, 'fixed'=>true ) );
    return true;
  }

  function isViewed( $a ) {
    return isset( $a['lastViewed'] ) || isset( $a['lastMarked'] );
  }
  
  function isFixed( $a ) {
    return $this->isViewed( $a ) || isset( $a['fixed'] );
  }

  function canRemove( $r, $e ) {
    $a =& $this->byReviewer[ $r ][ $e ];
    return ! isset( $a['lastViewed'] ) && ! isset( $a['lastMarked'] );
  }
  

  function exchange( $r, $e ) {
    //- REQUIRE: alreadyAllocated( $r, $e )
    shuffle( $this->allocations );
    foreach( $this->allocations as $a ) {
      if( $this->isFixed( $a ) )
        continue;
      $rr = $a['reviewer'];
      $ee = $a['essay'];
      if( $this->alreadyAllocated( $rr, $e ) )
        continue;
      $this->remove( $r,  $e  );
      $this->remove( $rr, $ee );
      $this->add( $r, $ee );
      $this->add( $rr, $e );
      return $a;
    }
    return false;
  }

  function remove( $r, $e ) {
    //- REQUIRE: alreadyAllocated( $r, $e )
    if( $this->canRemove( $r, $e ) ) {
      $a =& $this->byReviewer[ $r ][ $e ];
      $k = array_search( $a, $this->allocations );
      unset( $this->allocations[ $k ] );
      unset( $this->byReviewer[ $r ][ $e ] );
      unset( $this->byEssay[    $e ][ $r ] );
    }
  }


  function save( $tag = '' ) {
    ensureDBconnected( 'Allocations::save' );

    if( empty( $tag ) )
      $matchTag = '';
    else
      $matchTag = ' AND tag = ' . quote_smart( $tag );

    require __DIR__ . '/vendor/autoload.php';
    Logger::configure('log4php-config.xml');
    $logger = Logger::getLogger("Allocations");
    $logger->info("Saving allocations: assmtID=" . $this->assmtID . ', tag=' . $tag);

    checked_mysql_query(
      'insert into AllocationAudit (assmtID, tag, reviewer, author)'
      . ' select assmtID, tag, reviewer, author from Allocation'
      . ' where assmtID = ' . $this->assmtID . $matchTag);

    checked_mysql_query( 'DELETE FROM Allocation WHERE'
                         . ' assmtID = ' . $this->assmtID
                         . ' AND lastViewed IS NULL'
                         . ' AND lastMarked IS NULL'
                         . $matchTag );

    $values = array( );
    $updateTag = array( );
    foreach( $this->allocations as $a )
      if( ! $this->isFixed( $a ) && ! empty( $a['reviewer'] ) && ! empty( $a['author'] ) ) {
	$t = $a['tag'] == null ? $tag : $a['tag'];
        $values[] = "($this->assmtID, $a[reviewer], $a[author]," . quote_smart( $t ) . ')';
      }

    if( count($values) > 0 )
      checked_mysql_query( 'INSERT INTO Allocation '
                           . '(assmtID, reviewer, author, tag)'
                           . ' VALUES ' . join(',', $values)
			   . ' ON DUPLICATE KEY UPDATE tag = VALUES(tag)');


    $now = date('Y-m-d H:i:s' );
    checked_mysql_query( 'REPLACE INTO LastMod SET'
                         . " assmtID = " . $this->assmtID . ", lastMod = now()" );
  }


  function nameOfReviewer( $userID ) { return nameOf( $this->assmtID, $userID, $this->byReviewer, $this->byEssay, 'reviewer'); }
  function nameOfAuthor(   $userID ) { return nameOf( $this->assmtID, $userID, $this->byReviewer, $this->byEssay, 'author'); }

  function showByReviewer( $cid, $addCaption = true ) {
    $reviewers   = array_keys( $this->byReviewer );
    $reviewerIDs = empty( $reviewers ) ? array( )
      : array_combine( $reviewers, array_map( array($this, 'nameOfReviewer'), $reviewers));

    $hasPlaceholder = array_flip(fetchAll('select x.who, sum(e.isPlaceholder) as p, sum(1 - e.isPlaceholder) as s'
					  . ' from Extension x'
					  . ' inner join Essay e on'
					  .        ' e.assmtID = x.assmtID'
					  .        ' and e.author = x.who'
					  .        ' and x.submissionEnd is not null'
					  . ' where x.assmtID = ' . $this->assmtID
					  . ' group by x.who'
					  . ' having p > 0 and s = 0',
					  'who'));
    
    $tbody = HTML::tbody( );
    $nAuthors = 0;
    asort( $reviewerIDs );
    $nStarted = 0;
    $nComplete = 0;
    $needsFootnote = false;
    $needToAddPlaceholderText = false;
    foreach( $reviewerIDs as $r => $who ) {
      $tr = HTML::tr( HTML::td( callback_url( $who,
					      "showReviewer&cid=$cid&assmtID=" . $this->assmtID
					      . "&reviewer=$r" )));
      $essays = array_keys( $this->byReviewer[ $r ] );
      $nMarked = 0;
      $nAuthors = max(count($essays), $nAuthors);
      foreach( $essays as $e ) {
        $a =& $this->byEssay[ $e ][ $r ];
	$hasResponse = empty( $a['lastResponse'] ) ? '' : '#';
	if( $hasResponse )
	  $needsFootnote = true;

        $cell = isset( $a['lastViewed'] ) ? HTML::b( ) : HTML( );

	if( isset( $a['lastMarked'] ) ) {
          $nMarked++;
	  if ($a['status'] == 'complete')
	    $cell->pushContent(HTML::raw('&#10003;')); // CHECK MARK
	  else {
	    $cell->pushContent('+');
	    $somePartial = true;
	  }
	  if( isset( $a['lastSeen'] ) ) {
	    $someSeen = true;
	    $cell->pushContent( '*' );
	  }
	}

	$cell->unshiftContent( HTML( $this->nameOfAuthor( $e ) ) );

	if (isset($hasPlaceholder[$e])) {
	  $td = HTML::td(array('style'=>'background-color: lightblue'), $cell);
	  $needToAddPlaceholderText = true;
	} else
	  $td = HTML::td($cell);
	$tr->pushContent($td);
      }

      if( $nMarked == count( $essays ) )
        $nComplete++;
      else if( $nMarked > 0 )
        $nStarted++;

      $tbody->pushContent( $tr );
    }

    if( $tbody->IsEmpty( ) )
      return warning( _('There are no allocations'));
    else {
      $table = table( HTML::colgroup( ),
		      HTML::colgroup(array('span'=>$nAuthors)));
      if( !isset($this->isReviewsFor) )
	$this->isReviewsFor = fetchOne("SELECT isReviewsFor FROM Assignment WHERE assmtID=" . $this->assmtID,
				       'isReviewsFor');

      if( empty($this->isReviewsFor) )
	$table->pushContent( HTML::thead( HTML::tr( HTML::th(_('Reviewer')),
						    HTML::th(array('colspan'=>$nAuthors, 'align'=>'center'), _('Authors')))),
			     HTML::tr( HTML::td( array('colspan'=>$nAuthors+1),
						 _('Bold entries indicate the reviewer has viewed the submission. '),
						 _('Tick marks indicate a review has been received.'),
						 isset($somePartial) ? _('+ indicates an incomplete review.') : '',
						 isset( $someSeen ) ? _(' * indicates the review has been read.') : '')),
			     $tbody);
      else
	$table->pushContent( HTML::thead( HTML::tr( HTML::th(_('Marker')),
						    HTML::th(array('colspan'=>$nAuthors, 'align'=>'center'), _('Reviews')))),
			     HTML::tr( HTML::td( array('colspan'=>$nAuthors+1),
						 _('Bold entries indicate the marker has viewed the review. '),
						 _('Tick marks indicate review marking has been received.'),
						 isset($somePartial) ? _(' + indicates an incomplete review.') : '',
						 isset( $someSeen ) ? _(' * indicate the review marking has been read.') : '')),
			     $tbody);
      
      if( $needsFootnote )
	$table->pushContent( HTML::tr( HTML::td( array('colspan'=>$nAuthors+1),
						 _('Entries marked with # indicate the author has responded to reviewer comments' ))));

      if( $needToAddPlaceholderText )
	$table->pushContent( HTML::tr( HTML::td( array('colspan'=>$nAuthors+1,
						       'style'=>'background-color: lightblue'),
						 _('Outstanding submission extensions are indicated with a light blue background'))));

      if( $addCaption )
	$caption = HTML::h3(_('Allocations by reviewer'), ' &mdash; ');
      else
	$caption = HTML();

      $caption->pushContent(HTML::small(Sprintf_('%d total; %d not started; %d partially complete; %d completed',
						 count($reviewers), 
						 (count($reviewers) - $nStarted - $nComplete),
						 $nStarted,
						 $nComplete)));

      return HTML( $caption, $table );
    }
  }

  function showByEssay( $cid, $addCaption = true ) {
    $essays = array_keys( $this->byEssay );
    $essayIDs = empty( $essays ) ? array( )
      : array_combine( $essays, array_map( array($this, 'nameOfAuthor'), $essays));
    asort( $essayIDs );

    $hasPlaceholder = array_flip(fetchAll('select x.who, sum(e.isPlaceholder) as p, sum(1 - e.isPlaceholder) as s'
					  . ' from Extension x'
					  . ' inner join Essay e on'
					  .        ' e.assmtID = x.assmtID'
					  .        ' and e.author = x.who'
					  .        ' and x.submissionEnd is not null'
					  . ' where x.assmtID = ' . $this->assmtID
					  . ' group by x.who'
					  . ' having p > 0 and s = 0',
					  'who'));

    $nStarted = 0;
    $nComplete = 0;
    $needsFootnote = false;
    $needToAddPlaceholderText = false;
    $nReviewers = 0;
    $tbody = HTML::tbody( );
    foreach( $essayIDs as $e => $who ) {
      $cb = callback_url($who,
			 "showEssay&cid=$cid&assmtID=" . $this->assmtID
			 . "&author=$e");
      if (isset($hasPlaceholder[$e])) {
	$td = HTML::td(array('style' => 'background-color: lightblue'), $cb);
	$needToAddPlaceholderText = true;
      } else
	$td = HTML::td($cb);
      $tr = HTML::tr($td);
      $reviewers = array_keys( $this->byEssay[ $e ] );
      $nMarked = 0;
      $nReviewers = max( count($reviewers), $nReviewers );
      foreach( $reviewers as $r ) {
        $a =& $this->byEssay[ $e ][ $r ];
	$hasResponse = empty( $a['lastResponse'] ) ? '' : '#';
	if( $hasResponse )
	  $needsFootnote = true;
	
        $cell = isset( $a['lastViewed'] ) ? HTML::b( ) : HTML( );

	if( isset( $a['lastMarked'] ) ) {
          $nMarked++;
	  if ($a['status'] == 'complete')
	    $cell->pushContent(HTML::raw('&#10003;')); // CHECK MARK
	  else {
	    $cell->pushContent('+');
	    $somePartial = true;
	  }
	  if( isset( $a['lastSeen'] ) ) {
	    $someSeen = true;
	    $cell->pushContent( '*' );
	  }
	} 

	$cell->unshiftContent( HTML( $this->nameOfReviewer( $r )));

	$tr->pushContent( HTML::td( $cell ) );
      }

      if( $nMarked == count( $reviewers ) )
        $nComplete++;
      else if( $nMarked > 0 )
        $nStarted++;

      $tbody->pushContent( $tr );
    }

    if( $tbody->IsEmpty( ) )
      return warning( _('There are no allocations'));
    else {
      $table = table( HTML::colgroup( ),
		      HTML::colgroup(array('span'=>$nReviewers)));

      if( !isset($this->isReviewsFor) )
	$this->isReviewsFor = fetchOne("SELECT isReviewsFor FROM Assignment WHERE assmtID=" . $this->assmtID,
				       'isReviewsFor');
      if( empty($this->isReviewsFor) )
	$table->pushContent( HTML::thead( HTML::tr( HTML::th(_('Author')),
						    HTML::th(array('colspan'=>$nReviewers, 'align'=>'center'), _('Reviews')))),
			     HTML::tr( HTML::td( array('colspan'=>$nReviewers+1),
						 _('Bold entries indicate the reviewer has viewed the submission.'),
						 _(' Tick marks indicate a review has been received.'),
						 isset($somePartial) ? _('+ indicates an incomplete review.') : '',
						 isset( $someSeen ) ? _(' * indicates the review has been read.') : '')),
			     $tbody);
      else
	$table->pushContent( HTML::thead( HTML::tr( HTML::th(_('Review')),
						    HTML::th(array('colspan'=>$nReviewers, 'align'=>'center'), _('Markers')))),
			     HTML::tr( HTML::td( array('colspan'=>$nReviewers+1),
						 _('Bold entries indicate the marker has viewed the review.'),
						 _(' Tick marks indicate a review mark has been received.'),
						 isset($somePartial) ? _(' + indicates an incomplete review.') : '',
						 isset( $someSeen ) ? _(' * indicates the review marking has been read.') : '')),
			     $tbody);

      
      if( $needsFootnote )
	$table->pushContent( HTML::tr( HTML::td( array('colspan'=>$nReviewers+1),
						 _('Entries marked with # indicate the author has responded to reviewer comments' ))));

      if( $needToAddPlaceholderText )
	$table->pushContent(HTML::tr(HTML::td(array('colspan'=>$nAuthors+1,
						    'style'=>'background-color: lightblue'),
					      _('Outstanding submission extensions are indicated with a light blue background'))));
      
      if( $addCaption )
	$caption = HTML::h3(_('Allocations by author'), ' &mdash; ');
      else
	$caption = HTML();

      $caption->pushContent(HTML::small(Sprintf_('%d total; %d with no reviews; %d with some reviews; %d with all reviews.',
						 count($essays),
						 (count($essays) - $nStarted - $nComplete),
						 $nStarted,
						 $nComplete)));

      return HTML( $caption, $table );
    }
  }
}

function incr( &$xs, $i ) {
  if( ! isset( $xs[ $i ] ) )
    $xs[ $i ] = 1;
  else
    $xs[ $i ]++;
}

function in_alloc_list( $a, &$allocs ) {
  foreach( $allocs as $aa )
    if( $a['author'] == $aa['author'] && $a['reviewer'] == $aa['reviewer'] )
      return $aa;
  return false;
}

function nameOf( $assmtID, $userID, $reviewers, $essays, $authorOrReviewer ) {
  static $authorsAre = array( );
  static $reviewersAre = array( );
  static $reviewMap = array( );
  static $userMap = array( );
  static $groupMap = array( );
  if( ! isset( $authorsAre[ $assmtID ] ) ) {
    $assmt = fetchOne( "SELECT courseID, isReviewsFor, authorsAre, reviewersAre FROM Assignment where assmtID = $assmtID" );
    if (isset( $assmt['isReviewsFor'])) {
      if ($assmt['authorsAre'] == 'review')
	$authorsAre[ $assmtID ] = 'review';
      else if (fetchOne("select reviewersAre from Assignment where assmtID = $assmt[isReviewsFor]", 'reviewersAre') == 'group')
	$authorsAre[$assmtID] = 'group';
      else
	$authorsAre[$assmtID] = 'user';
    } else if( $assmt['authorsAre'] == 'group' )
      $authorsAre[ $assmtID ] = 'group';
    else
      $authorsAre[ $assmtID ] = 'user';
    
    if( $assmt['reviewersAre'] == 'group' )
      $reviewersAre[ $assmtID ] = 'group';
    else
      $reviewersAre[ $assmtID ] = 'user';

    $reviewMap[ $assmtID ] = array( );
    if( $authorsAre[ $assmtID ] == 'review' ) {
      $es = array( );
      $rs = array( );
      $allocs = fetchAll( "SELECT allocID, author, reviewer FROM Allocation WHERE assmtID = $assmt[isReviewsFor]" );
      foreach( $allocs as $row ) {
	$es[ $row['author'] ] = true;
	$rs[ $row['reviewer'] ] = true;
      }
      
      foreach( $allocs as $row )
	$reviewMap[ $assmtID ][ $row['allocID'] ]
	= nameOf( $assmt['isReviewsFor'], $row['reviewer'], $rs, $es, 'reviewer' )
	. '/'
	. nameOf( $assmt['isReviewsFor'], $row['author'], $rs, $es, 'author' );
    }
    
    $who = array( );
    if( $reviewersAre[ $assmtID ] == 'user' )
      foreach( array_keys( $reviewers ) as $r )
	$who[ $r ] = true;
    
    if( $authorsAre[ $assmtID ] == 'user' )
      foreach( array_keys( $essays ) as $a )
	$who[ $a ] = true;
    
    $userMap[ $assmtID ] = array( );
    if (!empty($who)) {
      $cid = classIdToCid($assmt['courseID']);
      if (primaryRole($_SESSION['classes'][$cid]['roles']) == 'guest') {
	foreach (array_keys($who) as $userId)
	  $userMap[$assmtID][$userId] = "u-$userId";
      } else {
	$rs = checked_mysql_query( 'SELECT userID, uident FROM User WHERE userID IN ('
				   . join(',', array_keys( $who ) ) . ')' );
	while( $row = $rs->fetch_assoc() )
	  $userMap[ $assmtID ][ $row['userID'] ] = $row['uident'];
      }
    }
    
    $groupMap[$assmtID] = array();
    if ($authorsAre[$assmtID] == 'group' || $reviewersAre[$assmtID] == 'group') {
      if (!empty($assmt['isReviewsFor']))
	$groupAssmtId = $assmt['isReviewsFor'];
      else
	$groupAssmtId = $assmtID;
      $rs = checked_mysql_query('select gu.groupID, gu.userID, g.gname'
				. ' from GroupUser gu'
				. ' left join `Groups` g on gu.groupID = g.groupID'
				. " where g.assmtID = $groupAssmtId");
      while ($row = $rs->fetch_assoc()) {
	$groupMap[$assmtID][$row['userID']] = $row['gname'];
	$groupMap[$assmtID][-$row['groupID']] = $row['gname'];
      }
    }
  }
  
  switch( $authorOrReviewer == 'author' ? $authorsAre[ $assmtID ] : $reviewersAre[ $assmtID ] ) {
  case 'group':
    if( isset( $groupMap[ $assmtID ][ $userID ] ) )
      return $groupMap[ $assmtID ][$userID];
    return "Group$userID";
  case 'review':
    if( isset( $reviewMap[ $assmtID ][ $userID ] ) )
      return $reviewMap[ $assmtID ][ $userID ];
    return "(unknown)";
  default:
    if( isset( $userMap[ $assmtID ][ $userID ] ) )
      return $userMap[ $assmtID ][ $userID ];
    $assmt = fetchOne( "SELECT assmtID, courseID, isReviewsFor, authorsAre, reviewersAre FROM Assignment where assmtID = $assmtID" );
    return userIdentity( $userID, $assmt, $authorOrReviewer );
  }
}
