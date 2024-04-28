<?php
/*
    Copyright (C) 2017 John Hamer <J.Hamer@acm.org>

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

  // *** WARNING: Do not use table aliases in any SQL queries that could be executed during allocation generation.
  // *** Or if you do, ensure they are added to the LOCK TABLES.

/* Call anytime to create or update Allocation records for any
   allocationType except 'manual' */
function doUnsupervisedAllocation( $assmt ) {
  $assmtID = (int)$assmt['assmtID'];

  require_once 'Allocations.php';

  switch( $assmt['allocationType'] ) {
  case 'manual':
    break;

  case 'normal':
    doUnsupervisedAllocation2( $assmtID,
			       findAllAuthors( $assmt ),
			       findAllReviewers( $assmt ),
			       $assmt['nPerReviewer'],
			       'STUDENT' );
    break;

  case 'same tags':
    $rs = checked_mysql_query( "SELECT DISTINCT author, IFNULL(tag,'MISSING-TAG') AS tag FROM Essay WHERE assmtID=$assmtID" );
    $es_by_tag = array( );
    while( $row = $rs->fetch_assoc() )
      $es_by_tag[ strtoupper( $row['tag'] ) ][] = $row['author'];
    foreach( $es_by_tag as $tag => $es )
      doUnsupervisedAllocation2( $assmtID, $es, $es, $assmt['nPerReviewer'], $tag );
    break;

  case 'other tags':
    $allocs = new Allocations( $assmtID, 'fixedOnly' );
    if( $allocs->allocationCount( ) > 0) {
      require_once 'AllocTagOther.php';
      $tagAllocator = new TagOtherAllocator( $assmt );
      $tagAllocator->allocate( );
    } else {
      require_once 'otherTagAllocation.php';
      $rs = checked_mysql_query( "SELECT DISTINCT author, IFNULL(tag,'MISSING-TAG') AS tag FROM Essay"
				 . " WHERE assmtID=$assmtID" );
      $esByTag = array( );
      while( $row = $rs->fetch_assoc() )
	$esByTag[strtoupper($row['tag'])][] = $row['author'];
      uasort($esByTag, 'compareByLength');
      $tagGroupSizes = array();
      foreach ($esByTag as $es)
	$tagGroupSizes[] = count($es);
      $allocBitArray = allocateOtherTags($tagGroupSizes, $assmt['nPerReviewer'] );
      $authors = array();
      $tagMap = array();
      foreach ($esByTag as $tag => $es)
	foreach ($es as $author) {
	  $authors[] = $author;
	  $tagMap[$author] = $tag;
	}
      for ($x = 0; $x < $allocBitArray->width; $x++)
	for ($y = 0; $y < $allocBitArray->width; $y++)
	  if ($allocBitArray->get($x, $y))
	    $allocs->add($authors[$y], $authors[$x], $tagMap[$authors[$x]]);
      $allocs->save();
    }
    break;

  case 'streams':
    /* Should we use only reviewers in $assmt['nStreams']? */
    $rs_by_stream = array( );
    $essays = array( );
    $rs = checked_mysql_query( "SELECT who, stream FROM Stream WHERE assmtID=$assmtID" );
    while( $row = $rs->fetch_assoc() ) {
      $rs_by_stream[ $row['stream'] ][] = $row['who'];
      $essays[ $row['who'] ] = true;
    }
    $es = array_keys( $essays );
    foreach( $rs_by_stream as $stream => $s )
      doUnsupervisedAllocation2( $assmtID, $es, $s, 1, "stream-$stream" );
    break;

  case 'response':
    doResponseReviewAllocation( $assmt );
  }

  allocateAuthors($assmtID, $assmt);
  if ($assmt['isReviewsFor'])
    allocateReviewMarkingMarkers($assmt);
  else
    allocateMarkers($assmtID, $assmt);
  
  checked_mysql_query( "UPDATE Assignment SET allocationsDone = now()"
		       . " WHERE assmtID = $assmtID" );
}

function allocateAuthors($assmtID, $assmt) {
  $allocs = new Allocations($assmtID, 'fixedOnly', 'AUTHOR');
  $extraAuthors = fetchAll("select Essay.author"
			   . " from Essay"
			   . " inner join Author on Essay.assmtID = Author.assmtID and Essay.author = Author.author"
			   . " where Essay.assmtID = $assmtID",
			   'author');
  if (empty($extraAuthors))
    return;
  
  $reviewers = findAllReviewers($assmt);
  foreach ($extraAuthors as $a) {
    foreach ($reviewers as $r)
      $allocs->add($r, $a);
  }
  
  $allocs->save('AUTHOR');
}

function allocateReviewMarkingMarkers($assmt) {
  $markers = findAllMarkers($assmt);
  if (empty($markers)) return;
  
  $allocs = new Allocations($assmt['assmtID'], 'fixedOnly', 'MARKER');
  switch ($assmt['authorsAre']) {
  case 'review':
    $authors =
      fetchAll(
	"select distinct Essay.author"
	. " from Essay left join Author on Essay.assmtID = Author.assmtID and Essay.author = Author.author"
	. " where Essay.assmtID = $assmt[isReviewsFor] and Author.author is null",
	'author');
    $splits = array_combine($markers, array_chunk($authors, ceil(count($authors) / count($markers))));
    foreach ($splits as $marker => $split)
      foreach ($split as $author) {
	foreach (
	  fetchAll(
	    "select allocID from Allocation"
	    . " where assmtID = $assmt[isReviewsFor] and author = $author and lastMarked is not null",
	    'allocID') as $allocID)
	  if ($allocs->reviewCount($allocID) == 0)
	    $allocs->add($marker, $allocID);
      }
    break;
  case 'reviewer':
    allocateEvenly(
      $allocs,
      $markers,
      fetchAll(
	"select distinct reviewer"
	. " from Allocation"
	. " where assmtID = $assmt[isReviewsFor] and lastMarked is not null",
	'reviewer'));
  }

  $allocs->save('MARKER');
}


function allocateMarkers( $assmtID, $assmt ) {
  require_once 'Allocations.php';

  $allocs = new Allocations( $assmtID, 'fixedOnly', 'MARKER' );
  $markers = findAllMarkers( $assmt );
  if( !empty($markers) ) {
    $authors = findAllAuthors( $assmt );
    if( $assmt['reviewerMarking'] == 'markAll' ) {
      foreach( $markers as $m )
	foreach( $authors as $a )
	  $allocs->add( $m, $a );
    } else
      allocateEvenly($allocs, $markers, $authors);
  }

  $allocs->save( 'MARKER' );
}

function allocateEvenly($allocs, $markers, $authors) {
  $load = array();
  foreach ($markers as $marker) {
    $n = $allocs->reviewerLoad($marker);
    if (!isset($load[$n]))
      $load[$n] = array();
    $load[$n][] = $marker;
  }

  foreach ($authors as $author)
    if ($allocs->reviewCount($author) == 0) {
      ksort($load);
      reset($load);
      $lk = key($load);
      $l = current($load);
      reset($l);
      $mk = key($l);
      $marker = current($l);
      $load[$lk + 1][] = $marker;
      unset($load[$lk][$mk]);
      if (empty($load[$lk]))
	unset($load[$lk]);
      $allocs->add($marker, $author);
    }
}


function compareByLength($a, $b) {
  return count($b) - count($a);
}

function doResponseReviewAllocation( $assmt ) {
  if( empty($assmt['isReviewsFor']))
    return;

  $origAssmtID = (int)$assmt['isReviewsFor'];
  $origAssmt = fetchOne("SELECT authorsAre, reviewersAre FROM Assignment WHERE assmtID=$origAssmtID");
  if ($origAssmt == null)
    return;

  require_once 'Allocations.php';
  $allocs = new Allocations( $assmt['assmtID'], 'fixedOnly' );
  if( $origAssmt['authorsAre'] == 'group') {
    require_once 'Groups.php';
    $groups = new Groups($origAssmtID);
  }

  $rs = checked_mysql_query("select allocID, author from Allocation"
			    . " where assmtID = $origAssmtID"
			    . " and author <> reviewer"
		      	    . ($assmt['reviewersAre'] == 'submit' ? "" : " and lastMarked is not null")
			    . " and ifnull(tag,'') <> 'MARKER'");
  while ($row = $rs->fetch_assoc())
    if (isset($groups) )
      foreach ($groups->members($row['author']) as $m)
	$allocs->add($m, $row['allocID']);
    else
      $allocs->add($row['author'], $row['allocID']);
  $allocs->save('STUDENT');
}


function doUnsupervisedAllocation2( $assmtID, $essays, $reviewers, $nPerReviewer, $tag ) {
  require_once 'Allocations.php';
  $allocs = new Allocations( $assmtID, 'fixedOnly', $tag );

  $reviewAssmtID = fetchOne("SELECT isReviewsFor FROM Assignment WHERE assmtID=$assmtID", 'isReviewsFor');
  if( $reviewAssmtID != null )
    $groups = new ReviewMarkingGroups( $reviewAssmtID );
  else {
    require_once 'Groups.php';
    $groups = new Groups( $assmtID );
  }

  $essays = array_unique( $essays );
  $reviewers = array_unique( $reviewers );
  sort( $essays );
  sort( $reviewers );
  if( $essays == $reviewers && $allocs->allocationCount( ) == 0 ) {
    $nAllocs = min($nPerReviewer, count($essays) - 1);
    // Easy case: allocate each essay to the reviewers immediately following
    $essays = random_permutation( $essays );
    for( $i = 0; $i < count($essays); $i++ ) {
      $e = $essays[ $i ];
      for( $j = 0; $j < $nAllocs; $j++ )
        $allocs->add( $e, $essays[ ($i + $j + 1) % count($essays) ] );
    }

    $allocs->save( $tag );
    return;
  }

  $nAllocs = min($nPerReviewer, count($essays));

  //  Group the essays by reviewCount
  $essayMap = array();
  for( $n = 0; $n < $nAllocs; $n++ )
    $essayMap[$n] = array();
  foreach( $essays as $e ) {
    $n = $allocs->reviewCount($e);
    if( ! isset($essayMap[$n]) )
      $essayMap[$n] = array();
    $essayMap[$n][] = $e;
  }

  shuffle( $reviewers );

  //  Group the reviewers by essayCount
  $reviewerMap = array();
  for( $n = 0; $n < $nAllocs; $n++ )
    $reviewerMap[$n] = array();
  foreach( $reviewers as $r ) {
    $n = $allocs->essayCount($r);
    if( $n >= $nAllocs ) continue; // reviewer has full quota already
    $reviewerMap[$n][] = $r;
  }

  foreach( $reviewerMap as $nR => &$rs ) {
    foreach( array_keys($rs) as $rk ) {
      $r = $rs[$rk];
      unset( $rs[$rk] );
      foreach( $essayMap as $nE => &$es ) {
	$found = false;
	foreach( $es as $ek => $e ) {
	  if( $e != $r && !$groups->overlap( $e, $r ) && $allocs->add( $r, $e ) ) {
	    unset( $es[$ek] );
	    $essayMap[ $nE + 1 ][] = $e;
	    if( $nR + 1 < $nAllocs )
	      $reviewerMap[ $nR + 1 ][] = $r;
	    $found = true;
	    break;
	  }
	}
	if( $found ) break;
      }
    }
  }

  $allocs->save( $tag );
}

class ReviewMarkingGroups {
  var $AtoE;
  var $AtoR;

  function __construct( $assmtID ) {
    $allocs = new Allocations( $assmtID );
    $this->AtoE = array();
    foreach( $allocs->allocations as $alloc ) {
      $this->AtoE[ $alloc['allocID'] ] = $alloc['author'];
      $this->AtoR[ $alloc['allocID'] ] = $alloc['reviewer'];
    }
  }

  function overlap( $a, $r ) {
    return $this->AtoE[$a] == $r || $this->AtoR[$a] == $r;
  }
}


function findAllMarkers( $assmt ) {
  return fetchAll( "SELECT reviewer FROM Reviewer WHERE assmtID = $assmt[assmtID]",
		   'reviewer' );
}

function findAllReviewers( $assmt ) {
  $assmtID = $assmt['assmtID'];
  if( ! empty( $assmt['isReviewsFor'] ) ) {
    switch( $assmt['reviewersAre'] ) {
    case 'all':
      //- All who submitted to the original assmt
      if( fetchOne( "SELECT authorsAre FROM Assignment WHERE assmtID = $assmt[isReviewsFor]", 'authorsAre' ) == 'group' ) {
	//- Find the group of every Essay and include all the members of that group
	$submitters = fetchAll( 'SELECT DISTINCT GroupUser.groupID AS groupID FROM Essay LEFT JOIN GroupUser'
				. ' ON Essay.author = GroupUser.userID AND Essay.assmtID = GroupUser.assmtID'
				. " WHERE GroupUser.groupID IS NOT NULL AND Essay.assmtID = $assmt[isReviewsFor]",
				'groupID' );
	$submitters[] = 0;
	return fetchAll( 'SELECT userID FROM GroupUser WHERE groupID IN (' . join(',', $submitters) . ')'
			 . " AND assmtID = $assmt[isReviewsFor]",
			 'userID' );
      } else
	return fetchAll( "SELECT DISTINCT author FROM Essay WHERE assmtID = $assmt[isReviewsFor]",
			 'author' );

    case 'submit':
      //- Those who reviewed the original assmt
      return fetchAll( "SELECT DISTINCT reviewer FROM Allocation WHERE lastMarked IS NOT NULL AND assmtID=$assmt[isReviewsFor]",
		       'reviewer' );

    case 'other':
      //- Just the markers
      return array( );
    }
  } else
    switch( $assmt['reviewersAre'] ) {
    case 'all':
      return fetchAll( 'SELECT userID FROM UserCourse'
		       . ' WHERE courseID = ' . $assmt['courseID']
		       . ' AND (roles&1) <> 0',
		       'userID' );

    case 'submit':
      if( $assmt['authorsAre'] == 'group' ) {
	//- Find the group of every Essay and include all the members of that group
	$submitters = fetchAll( 'SELECT DISTINCT GroupUser.groupID AS groupID FROM Essay LEFT JOIN GroupUser'
				. ' ON Essay.author = GroupUser.userID AND Essay.assmtID = GroupUser.assmtID'
				. " WHERE GroupUser.groupID IS NOT NULL AND Essay.assmtID = $assmtID",
				'groupID' );
	$submitters[] = 0;
	return fetchAll( 'SELECT userID FROM GroupUser WHERE groupID IN (' . join(',', $submitters) . ')'
			 . " AND assmtID = $assmtID",
			 'userID' );
      } else
	return fetchAll("select distinct Essay.author"
			. " from Essay left join Author on Essay.assmtID = Author.assmtID and Essay.author = Author.author"
			. " where Essay.assmtID = $assmtID and Author.author is null",
			'author');

    case 'other':
      //- The Reviewers table is for markers selected to review for this assignment.
      //- We don't need to add them here, as they get added on in a separate step.
      return array( ); //fetchAll( "SELECT reviewer FROM Reviewer WHERE assmtID = $assmtID", 'reviewer' );

    case 'group':
      // Eliminate groups who did not submit anything
      return fetchAll( 'SELECT DISTINCT -groupID AS reviewer FROM GroupUser'
		       . ' LEFT JOIN Essay ON Essay.author = GroupUser.userID AND Essay.assmtID = GroupUser.assmtID'
		       . ' WHERE Essay.author IS NOT NULL'
		       . " AND GroupUser.assmtID = $assmtID",
		       'reviewer' );
    }

  return array( );
}



function findAllAuthors( $assmt ) {
  $assmtID = $assmt['assmtID'];
  switch( $assmt['authorsAre'] ) {
  case 'all':
    return fetchAll("select distinct Essay.author"
		    . " from Essay left join Author on Essay.assmtID = Author.assmtID and Essay.author = Author.author"
		    . " where Essay.assmtID = $assmtID and Author.author is null",
		    'author');

  case 'other':
    return fetchAll( "SELECT author FROM Author WHERE assmtID = $assmtID",
		     'author' );

  case 'group':
    return fetchAll( 'SELECT DISTINCT -groupID AS author FROM'
		     . ' Essay LEFT JOIN GroupUser ON Essay.assmtID=GroupUser.assmtID AND Essay.author=GroupUser.userID'
		     . " WHERE Essay.assmtID = $assmtID",
		     'author' );

  case 'review':
    return fetchAll( 'SELECT allocID FROM Allocation WHERE lastMarked IS NOT NULL AND assmtID= '. (int)$assmt['isReviewsFor'],
		     'allocID' );

  case 'reviewer':
    return fetchAll( 'SELECT DISTINCT reviewer FROM Allocation WHERE lastMarked IS NOT NULL AND assmtID= ' . (int)$assmt['isReviewsFor'],
		     'reviewer' );
  }

  return array( );
}



function setupAllocation( ) {
  list( $assmt, $assmtID, $cid, $courseID ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  ensureDBconnected( 'setupAllocation' );

  if (!empty($assmt['isReviewsFor']))
    $action = 'allocateRatingReview';
  else if (isset($_REQUEST['type'])) {
    $action = 'allocateNormally';
    switch ($_REQUEST['type']) {
    case 'normal':
      $allocType = 'normal';
      break;
      
    case 'tags':
    case 'same tags':
    case 'other tags':
      $allocType =
	in_array($assmt['allocationType'], array('same tags', 'other tags'))
	? $assmt['allocationType']
	: 'same tags';
      break;
      
    case 'streams':
      $allocType = 'streams';
      $action = 'allocateUsingStreams';
      break;
      
    case 'manual':
      $allocType = 'manual';
      $action = 'allocateManually';
      break;
      
    case 'groups':
      $allocType = 'groups';
      $action = 'allocateGroups';
    }
  }

  if (isset($action))
    redirect($action, "assmtID=$assmtID&cid=$cid&allocType=$allocType");

  if( $assmt['allocationsDone'] )
    $warn = warning( _('Allocation records have already been created for this assignment.  Changing the allocation parameters may invalidate existing reviews.'));
  else
    $warn = '';

  return HTML(assmtHeading(_('Set up allocations'), $assmt),
	      $warn,
	      HTML::p(_('An allocation is an author-reviewer pair')),
	      HTML::p(_('Allocations can be specified in one of the following ways:'),
		      HTML::dl(HTML::dt(_('Randomly')),
			       HTML::dd(_('Reviewers are allocated to author submissions randomly after the submission deadline')),
			       HTML::dt(_('Tags')),
			       HTML::dd(_('Authors tag their submissions, and reviewers are allocated submissions with either the same or a different tag as their own')),
			       HTML::dt(_('By Groups')),
			       HTML::dd(_('Students are arranged into groups, allowing for a single submission per group')),
			       HTML::dt( _('Manually')),
			       HTML::dd(_('The instructor has complete control over the allocations, and enters author-reviewer pairs by hand')))),
	      HTML::p(_('Allocation records will be created after the submission deadline.')),
	      ButtonToolbar(formButton(_('Allocate randomly'),
				       "setupAllocation&assmtID=$assmtID&cid=$cid&type=normal" ),
			    formButton( _('Allocate using tags'),
					"setupAllocation&assmtID=$assmtID&cid=$cid&type=tags" ),
			    formButton( _('Allocate using groups'),
					"setupAllocation&assmtID=$assmtID&cid=$cid&type=groups" ),
			    formButton( _('Allocate manually'),
					 "setupAllocation&assmtID=$assmtID&cid=$cid&type=manual" ),
			    formButton( _('Cancel'),
					 "viewAsst&assmtID=$assmtID&cid=$cid" )));
}

function allocateNormally( ) {
  list( $assmt, $assmtID, $cid, $courseID ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  if (isset($_REQUEST['allocType']))
    $allocType = $_REQUEST['allocType'];
  else
    $allocType = $assmt['allocationType'];
  if ($allocType == 'tags') $allocType = 'same tags';
  
  if ($assmt['allocationsDone'] && ($assmt['authorsAre'] == 'group' || $assmt['reviewersAre'] == 'group'))
    return allocateGroups();

  ensureDBconnected( 'allocateNormally' );

  $authors = fetchAll( 'SELECT IFNULL(uident, CONCAT("u-", userID)) AS author'
		       . ' FROM Author LEFT JOIN User ON Author.author=User.userID'
		       . " WHERE assmtID = $assmtID",
		       'author' );

  $reviewers = getAssignmentReviewers( $assmtID );
  $markers = getCourseMarkers( $courseID );
  $haveMarkers = !empty( $reviewers ) || !empty($markers);

  $nPerReviewer = $assmt['nPerReviewer'];
  if( $nPerReviewer <= 0 )
    $nPerReviewer = '';

  $usingTags = in_array($allocType, array('same tags', 'other tags'));

  if( $usingTags )
    $tagFields = HTML(HTML::h3(_('Author tags')),
		      HTML::p(_('Authors will tag the files they upload, and submissions will be allocated to a reviewer who
submitted under the same or different tag.  This option assumes authors are also reviewers.')),
		      FormGroup('tags',
				_('Enter the author tags here.  Each tag must be one word, separated by a comma.' ),
				HTML::input(array('value'=>$assmt['tags'],
						  'data-role'=>'tagsinput',
						  'disabled'=>!$usingTags)),
				_('Tags')),
		      HTML(HTML::label(_('Allocate reviewers')),
			   HTML::div(array('class'=>'radio-group'),
				     Radio('sameTags',
					   _('According to the same tags'),
					   1,
					   $allocType == 'same tags'),
				     Radio('sameTags',
					   _('According to different tags'),
					   0,
					   $allocType != 'same tags')),
			   HTML::p(_('Note that the allocation of submissions according to different tags will only be perfect if the number of submissions in each category is suitably balanced (with no dominant tag). If this is not the case, some students will inevitably review submissions in their own category: '), Aropa(), _(' will do its best to minimise the number of these students.'))));
  else
    $tagFields = '';

  $authorsAre = $assmt['authorsAre'];
  if( $authorsAre == 'all' && ! fetchOne("SELECT * FROM Author WHERE assmtID=$assmtID LIMIT 1" ) )
    $authorsAre = 'class-only';

  // This can arise when changing allocation type from group to normal
  if (!in_array($authorsAre, array('all', 'class-only', 'other')))
    $authorsAre = 'class-only';

  if( $assmt['allocationsDone'] )
    $warn = warning( _('Allocation records have already been created for this assignment.  Changing the allocation parameters may invalidate existing reviews.'));
  else
    $warn = '';

  extraHeader('$("input[name=\'authorsAre\']").change(function(){
$("#authors")
  .prop("disabled", this.value == "class-only");
})', 'onload');
  extraHeader('$("input[name=\'reviewersAre\']").change(function(){
$("#nPerReviewer")
  .prop("disabled", this.value == "other")
  .prop("required", this.value != "other");
})', 'onload');

  if ($usingTags && $assmt['reviewersAre'] == 'all')
    $assmt['reviewersAre'] = 'submit';
  $reviewerRadios =
    HTML(
      HTML::label(_('Reviewers are')),
      Radio('reviewersAre', _('All students who submit'), 'submit', $assmt['reviewersAre'] == 'submit'),
      Radio(
	'reviewersAre',
	_('All the students in the class'),
	'all',
	$assmt['reviewersAre'] == 'all',
	$usingTags ? array('disabled' => 'disabled', 'title' => _('This option is not supported when using tags')) : array()));
  if ($haveMarkers)
    $reviewerRadios->pushContent(
      Radio('reviewersAre', _('Just the selected markers (below)'), 'other', $assmt['reviewersAre'] == 'other'));
  
  $authorsAreElement = $usingTags ? ''
    : RadioGroup('authorsAre',
		 _('Authors are'),
		 $authorsAre,
		 array('class-only' =>_('All students in the class'),
		       'all'        =>_('All students in the class, plus the non-students below (all reviewers will review submissions by these non-students)'),
		       'other'      =>_('Just the non-student authors below')));

  $extraAuthors = join(", ", $authors);
  $nonStudentAuthors = $usingTags ? ''
    : FormGroup('authors',
		_('Non-student authors (optional) '),
		HTML::input(array('type' => 'text',
				  'id' => 'authors',
				  'value' => $extraAuthors,
				  'disabled' => $authorsAre == 'class-only',
				  'data-role' => 'tagsinput')),
		' ');

  extraHeader('bootstrap-tagsinput.js', 'js');
  extraHeader('bootstrap-tagsinput.css', 'css');
  if ($assmt['allocationsDone'])
    $changeType = _('Manually adjust allocations');
  else
    $changeType = _('Change allocation type');
  return HTML(assmtHeading($usingTags ? _('Tagged allocations') : _('Random allocations'), $assmt),
	      $warn,
	      HTML::form(array('name'=>'edit',
			       'method'=>'post',
			       'action'=>"$_SERVER[PHP_SELF]?action=saveNormalAllocations"),
			 HiddenInputs(array('assmtID'=>$assmtID, 'cid'=>$cid, 'allocType'=>$allocType)),
			 $authorsAreElement,
			 $reviewerRadios,
			 FormGroup('nPerReviewer',
				   _('Number of student submissions each reviewer should mark'),
				   HTML::input(array('type'=>'number',
						     'autofocus'=>true,
						     'min'=>1,
						     'style'=>'width: 6em',
						     'disabled'=>$assmt['reviewersAre'] == 'other',
						     'required'=>$assmt['reviewersAre'] != 'other',
						     'value'=>$nPerReviewer,
						     'id'=>'nPerReviewer')),
				   ' '),
			 $tagFields,
			 makeMarkerEntry(_('Markers for this assignment'),
					 $markers,
					 $reviewers,
					 $assmt['reviewerMarking']),
			 br(),
			 $nonStudentAuthors,
			 $assmt['allocationsDone'] ? '' : HTML::p( _('Allocation records will be created after the submission deadline')),
			 ButtonToolbar(submitButton(_('Save'), 'save'),
				       submitButton($changeType, 'change'),
				       formButton(_('Cancel'), "viewAsst&assmtID=$assmtID&cid=$cid"))));
}


function makeMarkerEntry($legend, $markers, $reviewers, $reviewerMarking) {
  if (empty($reviewers) && empty($markers))
    return '';
  $div = HTML::div();
  if (!empty($legend)) {
    $div->pushContent(HTML::h3($legend));
    if (count($markers) > 1)
      $div->pushContent(HTML::h4(_('Select the non-student markers to use')));
  }

  $div->pushContent(markersSelection($markers, $reviewers));
  if (count($markers) > 1) {
    $div->pushContent(
      RadioGroup(
	'reviewerMarking',
	null,
	$reviewerMarking,
	array('split' => _('Each marks an equal share'), 'markAll' => _('Each marks everything'))));
    extraHeader(
      '$("#selectAllMarkers").click(function() { $("input[name^=markers]").prop("checked", this.checked); })',
      'onload');
  } else
    $div->pushContent(HiddenInputs(array('reviewerMarking', 'markAll')));
  return $div;
}

function markersSelection($markers, $reviewers) {
  $div = HTML::div(array('class'=>'checkbox'));
  if (count($markers) > 2)
    $div->pushContent(
      HTML::label(
	HTML::input(array('type'=>'checkbox', 'id' => 'selectAllMarkers')),
	_('[Select all]')),
      HTML::br());

  foreach ($markers as $userID => $name) {
    $div->pushContent(
      HTML::label(
	HTML::input(
	  array('type'=>'checkbox',
		'name'=>"markers[$userID]",
		'checked'=>isset($reviewers[$userID]))),
	$name),
      HTML::br());
    unset($reviewers[$userID]);
  }

  foreach ($reviewers as $userID => $name)
    $div->pushContent(
      HTML::label(
	HTML::input(
	  array('type'=>'checkbox',
		'name'=>"markers[$userID]",
		'checked'=>true))),
      $name . _(' (WARNING: unregistered; please update the Class List)'));
  return $div;
}

function saveNormalAllocations( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  $db = ensureDBconnected( 'saveNormalAllocations' );
  require_once 'users.php';

  $toAdd = array( );
  $toDel = array_flip( fetchAll( "SELECT author FROM Author WHERE assmtID=$assmtID", 'author' ) );
  if( isset( $_REQUEST['authors'] ) ) {
    $authors = array( );
    foreach (preg_split("/[\s,;]+/", $_REQUEST['authors'], 0, PREG_SPLIT_NO_EMPTY) as $who)
      $authors[] = $who;

    foreach( identitiesToUserIDs( $authors ) as $userID )
      if( ! isset( $toDel[ $userID ] ) )
	$toAdd[ $userID ] = true;
      else
	unset( $toDel[ $userID ] );
  }
  if( ! empty( $toDel ) ) {
    checked_mysql_query( "DELETE FROM Author WHERE assmtID=$assmtID"
			 . ' AND author IN (' . join(',', array_keys( $toDel )) . ')');
    $reallocationNeeded = true;
  }
  if( ! empty( $toAdd ) ) {
    $values = array( );
    foreach( array_keys( $toAdd ) as $userID )
      $values[] = "($assmtID,$userID)";
    checked_mysql_query( 'INSERT INTO Author (assmtID, author) VALUES '
			 . join(',', $values));
    $reallocationNeeded = true;
  }

  if( updateReviewers( $assmtID, $_REQUEST['markers'] ) )
    $reallocationNeeded = true;

  $data = array( );

  if( isset( $_REQUEST['nPerReviewer'] ) )
    $data['nPerReviewer'] = (int)$_REQUEST['nPerReviewer'];

  $data['nPerSubmission'] = 0; // This is no longer used; clear any legacy settings.

  if( isset( $_REQUEST['authorsAre'] ) && in_array( $_REQUEST['authorsAre'], array('all', 'class-only', 'group', 'other' )) ) {
    if( $_REQUEST['authorsAre'] == 'class-only' )
      //- 'class-only' is an abbreviation for 'all' that disables the non-student author input area
      $data['authorsAre'] = 'all';
    else
      $data['authorsAre'] = $_REQUEST['authorsAre'];
  }

  if( isset( $_REQUEST['reviewersAre'] ) && in_array( $_REQUEST['reviewersAre'], array('all', 'submit', 'group', 'other') )) {
    $data['reviewersAre'] = $_REQUEST['reviewersAre'];
    if( $data['reviewersAre'] == 'other' )
      $data['nPerReviewer'] = 0;
  }

  if( isset( $_REQUEST['nStreams'] ) && $_REQUEST['nStreams'] > 0 )
    $data['nStreams'] = (int)$_REQUEST['nStreams'];

  if (isset($_REQUEST['sameTags']))
    $data['allocationType'] = $_REQUEST['sameTags'] == 1 ? 'same tags' : 'other tags';
  else {
    if (in_array($_REQUEST['allocType'], array('normal', 'streams', 'manual')))
      $data['allocationType'] = $_REQUEST['allocType'];
    else
      $data['allocationType'] = 'normal';
  }

  if( isset( $_REQUEST['reviewerMarking'] ) )
    $data['reviewerMarking'] = $_REQUEST['reviewerMarking'] == 'markAll' ? 'markAll' : 'split';

  $data['tags'] = trim($_REQUEST['tags']);

  foreach( $data as $key => $value )
    if( $assmt[ $key ] == $value )
      unset( $data[ $key ] );
    else
      $assmt[ $key ] = $value;

  if( ! empty($data) ) {
    checked_mysql_query( makeUpdateQuery('Assignment', $data) . " WHERE assmtID = $assmtID" );
    if( $db->affected_rows != 0 )
      $reallocationNeeded = true;

    if( $reallocationNeeded && nowBetween( $assmt['submissionEnd'], null ) ) {
      //$assmt = fetchOne( "SELECT * FROM Assignment WHERE assmtID = $assmtID" );
      //doUnsupervisedAllocation( $assmt );  ***Don't reallocate here; it's probably too late to save!
    }
  }

  allocateMarkers($assmtID, $assmt);

  if( isset( $_REQUEST['change'] ) ) {
    if ($assmt['allocationsDone'])
      redirect( 'setupAllocation', "cid=$cid&assmtID=$assmtID&type=manual" );
    else
      redirect( 'setupAllocation', "cid=$cid&assmtID=$assmtID" );
  } else if( isset( $_REQUEST['adjust'] ) )
    redirect( 'adjustAllocations', "cid=$cid&assmtID=$assmtID" );
  else
    redirect( 'viewAsst', "cid=$cid&assmtID=$assmtID" );
}


function updateReviewers( $assmtID, $markers ) {
  if( ! is_array( $markers ) )
    $markers = array( );

  $wanted = array_keys( $markers );
  $current = fetchAll( "SELECT reviewer FROM Reviewer WHERE assmtID=$assmtID", 'reviewer' );
  $toAdd = array_diff( $wanted, $current );
  $toDel = array_diff( $current, $wanted );

  $reallocationNeeded = false;
  if( ! empty( $toDel ) ) {
    checked_mysql_query( "DELETE FROM Reviewer WHERE assmtID=$assmtID"
			 . ' AND reviewer IN (' . join(',', $toDel ) . ')');
    $reallocationNeeded = true;
  }

  if( ! empty( $toAdd ) ) {
    $values = array( );
    foreach( $toAdd as $userID )
      $values[] = "($assmtID, $userID)";
    checked_mysql_query( 'INSERT INTO Reviewer (assmtID, reviewer) VALUES '
			 . join(',', $values));
    $reallocationNeeded = true;
  }
  return $reallocationNeeded;
}



function allocateUsingStreams( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  ensureDBconnected( 'allocateUsingStreams' );

  $nStreams = 6;
  $streams = array( );
  for( $i = 1; $i <= $nStreams; $i++ )
    $streams[ $i ] = "";

  $rs = checked_mysql_query( 'SELECT IFNULL(uident, CONCAT("u-", userID)) AS uident, IFNULL(stream,0) AS stream'
			     . ' FROM Stream LEFT JOIN User ON who=userID'
			     . " WHERE assmtID = $assmtID" );
  while( $row = $rs->fetch_assoc() ) {
    $s = $row['stream'] < 1 || $row['stream'] > $nStreams ? 0 : $row['stream'];
    $streams[ $s ] .= $row['uident'] . "\n";
  }

  $table = table( );
  $tr = HTML::tr( );
  foreach( $streams as $i => $ss )
    $tr->pushContent( HTML::th( $i == 0 ? _("Pending") : _('Stream ') . $i ) );
  $table->pushContent( $tr );

  $tr = HTML::tr( );
  foreach( $streams as $i => $ss )
    $tr->pushContent( HTML::td( HTML::textarea( array('rows'=>20, 'cols'=>10,
						      'name'=>"stream[$i]"),
						$ss )));
  $table->pushContent( $tr );

  if( $assmt['allocationsDone'] )
    $warn = warning( _('Allocation records have already been created for this assignment.  Changing the allocation parameters may invalidate existing reviews.'));
  else
    $warn = '';

  if ($assmt['allocationsDone'])
    $changeType = _('Manually adjust allocations');
  else
    $changeType = _('Change allocation type');
  return HTML(assmtHeading(_('Stream allocations'), $assmt),
	      $warn,
	      HTML::p(_('Enter the reviewer names in each stream, one per line')),
	      HTML::p(_('Use as many streams as you need; leave the others blank')),
	      HTML::form(array('name'=>'edit',
			       'method'=>'post',
			       'action'=>"$_SERVER[PHP_SELF]?action=saveStreamAllocations"),
			 HiddenInputs(array('assmtID'=>$assmtID, 'cid'=>$cid)),
			 $table,
			 submitButton(_('Save streams')),
			 submitButton($changeType, 'change'),
			 formButton(_('Cancel'), "viewAsst&assmtID=$assmtID&cid=$cid")));
}


function saveStreamAllocations( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  require_once 'users.php';

  $values = array( );
  foreach( $_REQUEST['stream'] as $i => $text ) {
    $authors = array( );
    foreach( explode( "\n", $text ) as $line ) {
      $who = trim($line);
      if( ! empty($who) && $who[0] != '#' )
	$authors[] = $who;
    }
    foreach( identitiesToUserIDs( $authors ) as $who )
      $values[$who] = "($assmtID, $who, " . (int)$i . ')';
  }

  checked_mysql_query( "DELETE FROM Stream WHERE assmtID = $assmtID" );
  if( ! empty( $values ) )
    checked_mysql_query( 'INSERT INTO Stream (assmtID, who, stream) VALUES '
			 . join(',', $values));

  checked_mysql_query("update Assignment set allocationType = 'streams' where assmtID = $assmtID");
  
  if( nowBetween( $assmt['submissionEnd'], null ) ) // *** UNSAFE
    doUnsupervisedAllocation( $assmt );

  if( isset( $_REQUEST['change'] ) ) {
    if ($assmt['allocationsDone'])
      redirect( 'setupAllocation', "cid=$cid&assmtID=$assmtID&type=manual" );
    else
      redirect( 'setupAllocation', "cid=$cid&assmtID=$assmtID" );
  } else if( isset( $_REQUEST['adjust'] ) )
    redirect( 'adjustAllocations', "cid=$cid&assmtID=$assmtID" );
  else
    redirect( 'viewAsst', "assmtID=$assmtID&cid=$cid" );
}


function allocateManually( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( ); //warning("Unable to find assignment #$assmtID for the class ", HTML::q( className( $cid ) ));

  if( isset( $_REQUEST['allocOrder'] ) ) {
    $order = $_REQUEST['allocOrder'];
    $_SESSION['allocOrder'] = $order;
  } else if( isset( $_SESSION['allocOrder'] ) )
    $order = $_SESSION['allocOrder'];
  else
    $order = 'reviewer';

  if( $order != 'reviewer' && $order != 'submission' )
    securityAlert( 'bad allocOrder' );

  ensureDBconnected( 'allocateManually' );

  require_once 'Allocations.php';
  $allocs = new Allocations( $assmtID, 'all' );

  $existing = "";
  if( $order == 'reviewer' ) {
    $rs = array_keys( $allocs->byReviewer );
    foreach( $rs as $r ) {
      $existing .= $allocs->nameOfReviewer( $r ) . ': ';
      if( isset( $allocs->byReviewer[ $r ] ) ) {
	$es = array_keys( $allocs->byReviewer[ $r ] );
	foreach( $es as $e )
	  $existing .= $allocs->nameOfAuthor( $e ). ', ';
      }
      $existing .= "\n";
    }
  } else {
    $es = array_keys( $allocs->byEssay );
    foreach( $es as $e ) {
      $existing .= $allocs->nameOfAuthor( $e ) . ': ';
      if( isset( $allocs->byEssay[ $e ] ) ) {
	$rs = array_keys( $allocs->byEssay[ $e ] );
	foreach( $rs as $r )
	  $existing .= $allocs->nameOfReviewer( $r ) . ', ';
      }
      $existing .= "\n";
    }
  }

  if( $order == 'reviewer' )
    $instruct = HTML::p(_('Each individual line should be of the form'),
			HTML::blockquote(_('reviewer author author ...')),
			_('Or you can '),
			RedirectButton(_('enter by author'),
				       "allocateManually&assmtID=$assmtID&cid=$cid&allocOrder=submission"));
  else
    $instruct = HTML::p(_('Each individual line should be of the form'),
			HTML::blockquote(_('author reviewer reviewer ...')),
			_('Or you can '),
			RedirectButton(_('enter by reviewer'),
				       "allocateManually&assmtID=$assmtID&cid=$cid&allocOrder=reviewer"));

  if( ! empty( $existing ) )
    $instruct->pushContent( HTML::p( _('The existing manual allocations are shown.  If you change them, they will be replaced.' )));

  $rs = checked_mysql_query( 'SELECT DISTINCT author, IFNULL(uident, CONCAT("u-",userID)) AS uident'
			     . ' FROM Essay LEFT JOIN User ON Essay.author=User.userID'
			     . " WHERE assmtID = $assmtID");
  $unallocAuthor = array( );
  while( $row = $rs->fetch_assoc() )
    if( empty( $allocs->byEssay[ $row['author'] ] ) )
      $unallocAuthor[] = $row['uident'];

  if( ! $assmt['authorsAreReviewers'] ) {
    $rs = checked_mysql_query( 'select r.reviewer, u.uident'
			       . ' from Reviewer r inner join User u on r.reviewer = u.userID'
			       . " where r.assmtID = $assmtID");
    $unallocReviewer = array( );
    while( $row = $rs->fetch_assoc() )
      if( empty( $allocs->byReviewer[ $row['reviewer'] ] ) )
	$unallocReviewer[] = $row['uident'];
  }

  $th = HTML::tr( );
  $td = HTML::tr( );
  if( ! empty($unallocAuthor) ) {
    if( $assmt['authorsAreReviewers'] )
      $th->pushContent( HTML::th( _('Unallocated')));
    else
      $th->pushContent( HTML::th( HTML::raw(_('Unallocated<br/>authors'))));
    $td->pushContent( HTML::td( HTML::pre( join("\n", $unallocAuthor ))));
  }
  if( ! empty($unallocReviewer) ) {
    $th->pushContent( HTML::th( HTML::raw(_('Unallocated<br/>reviewers'))));
    $td->pushContent( HTML::td( HTML::pre( join("\n", $unallocReviewer))));
  }
  $missing = $th->IsEmpty( ) ? '' : table( $th, $td );

  if ($assmt['allocationsDone'])
    $changeType = _('Manually adjust allocations');
  else
    $changeType = _('Change allocation type');
  return HTML(assmtHeading(_('Manual allocations'), $assmt ),
	      HTML::p( _('Enter the allocations in the text area below.')),
	      $instruct,
	      HTML::form(array('method'=>'post',
			       'class'=>'form',
			       'action'=>"$_SERVER[PHP_SELF]?action=saveManualAllocations&assmtID=$assmtID&allocOrder=$order&cid=$cid"),
			 HTML::textarea( array('name'=>'allocations',
					       'rows'=>10, 'cols'=>60 ),
					 $existing ),
			 ButtonToolbar(submitButton(_('Save')),
				       submitButton($changeType, 'change'),
				       formButton(_('Cancel'), "viewAsst&assmtID=$assmtID&cid=$cid"))),
	      br(),
	      HTML::div($missing));
}



function saveManualAllocations( ) {
  list ($allocations, $allocOrder)
    = checkREQUEST('allocations', 'allocOrder');
  list ($assmt, $assmtID, $cid, $courseID) = selectAssmt();
  if (!$assmt)
    return missingAssmt();

  $order = $_REQUEST['allocOrder'];
  require_once 'users.php';

  $essaysFrom = empty($assmt['essaysFrom']) ? $assmtID : $assmt['essaysFrom'];

  $nameList = array();
  $allLHS = array();
  $allRHS = array();
  foreach (explode("\n", $allocations) as $line) {
    $line = strtolower(trim($line));
    if (empty($line) || $line[0] == '#')
      continue;
    $names = preg_split('/[[:space:],;:]/', $line, -1, PREG_SPLIT_NO_EMPTY);
    $nameList[] = $names;
    $allLHS[array_shift($names)] = true;
    foreach ($names as $n)
      $allRHS[$n] = true;
  }

  if ($order == 'reviewer') {
    $allReviewers = $allLHS;
    $allAuthors = $allRHS;
  } else {
    $allAuthors = $allLHS;
    $allReviewers = $allRHS;
  }
  
  $groupNames = array();
  $userNames = array();
  if ($assmt['authorsAre'] == 'group') {
    $groupNames = $allAuthors;
    $normaliseAuthor = 'normaliseGroup';
  } else {
    $userNames = $allAuthors;
    $normaliseAuthor = 'normaliseUident';
  }

  if ($assmt['reviewersAre'] == 'group') {
    $groupNames = array_merge($groupNames, $allReviewers);
    $normaliseReviewer = 'normaliseGroup';
  } else {
    $userNames = array_merge($userNames, $allReviewers);
    $normaliseReviewer = 'normaliseUident';
  }


  $idMap = array();
  if (!empty($groupNames))
    foreach (fetchAll("select groupID, gname from `Groups` where assmtID = $assmtID") as $g)
      $idMap[normaliseGroup($g['gname'])] = -$g['groupID'];

  if (!empty($userNames)) {
    foreach (fetchAll("select uc.userID, u.uident from UserCourse uc inner join User u on uc.userID = u.userID where uc.courseID = $courseID") as $u)
      $idMap[$u['uident']] = $u['userID'];
    foreach (fetchAll("select u.userID, u.uident from Author a inner join User u on a.author = u.userID where a.assmtID = $assmtID") as $u)
      $idMap[$u['uident']] = $u['userID'];
    foreach (fetchAll("select u.userID, u.uident from Reviewer r inner join User u on r.reviewer = u.userID where r.assmtID = $assmtID") as $u)
      $idMap[$u['uident']] = $u['userID'];
    foreach (fetchAll("select distinct u.userID, u.uident from Essay e inner join User u on e.author = u.userID where e.assmtID = $assmtID") as $u)
      $idMap[$u['uident']] = $u['userID'];
    foreach (fetchAll("select distinct u.userID, u.uident from Allocation l inner join User u on l.reviewer = u.userID where l.assmtID = $assmtID") as $u)
      $idMap[$u['uident']] = $u['userID'];
  }
  

  $unrecognised = array();
  require_once 'Allocations.php';
  $allocs = new Allocations($assmtID, 'fixedOnly', 'MANUAL');
  foreach ($nameList as $names) {
    $lhs = array_shift($names);
    if( $order == 'reviewer' ) {
        $rname = call_user_func($normaliseReviewer, $lhs);
        if (!isset($idMap[$rname]))
            $unrecognised[$rname] = true;
        else
          foreach ($names as $n) {
              $aname = call_user_func($normaliseAuthor, $n);
              if (!isset($idMap[$aname]))
                  $unrecognised[$aname] = true;
              else
                  $allocs->add($idMap[$rname], $idMap[$aname]);
          }
      } else {
        $aname = call_user_func($normaliseAuthor, $lhs);
        if (!isset($idMap[$aname]))
            $unrecognised[$aname] = true;
        else
            foreach ($names as $n) {
                $rname = call_user_func($normaliseReviewer, $n);
                if (!isset($idMap[$rname]))
                    $unrecognised[$rname] = true;
                else
                    $allocs->add($idMap[$rname], $idMap[$aname]);
            }
    }
  }

  if (!empty($unrecognised))
    addWarningMessage(_('The following names were not recognised: '), join(', ', array_keys($unrecognised)));
  
  checked_mysql_query("update Assignment set allocationType = 'manual' where assmtID = $assmtID");
  $allocs->save( 'MANUAL' );

  if (empty($allocs->allocations))
    addWarningMessage(_('No manual allocations were entered.'));
  
  if( isset( $_REQUEST['change'] ) ) {
    if ($assmt['allocationsDone'])
      redirect( 'setupAllocation', "cid=$cid&assmtID=$assmtID&type=manual" );
    else
      redirect( 'setupAllocation', "cid=$cid&assmtID=$assmtID" );
  } else if( isset( $_REQUEST['adjust'] ) )
    redirect( 'adjustAllocations', "cid=$cid&assmtID=$assmtID" );
  else
    redirect( 'viewAsst', "assmtID=$assmtID&cid=$cid" );
}

function normaliseGroup($gname) {
  return strtolower(trim($gname));
}



function adjustAllocations( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  if( isset( $_REQUEST['allocOrder'] ) ) {
    $order = $_REQUEST['allocOrder'];
    $_SESSION['allocOrder'] = $order;
  } else if( isset( $_SESSION['allocOrder'] ) )
    $order = $_SESSION['allocOrder'];
  else
    $order = 'reviewer';

  if( $order != 'reviewer' && $order != 'submission' )
    securityAlert( 'bad allocOrder' );

  ensureDBconnected( 'adjustAllocations' );

  require_once 'users.php';
  require_once 'Allocations.php';
  $allocs = new Allocations( $assmtID, 'all' );

  $markers = userIDsToIdentities( findAllMarkers( $assmt ) );

  if( $order == 'reviewer' ) {
    $reviewers   = array_keys( $allocs->byReviewer );
    $reviewerIDs = count( $reviewers ) == 0 || count( $allocs ) == 0 ? array( ) :
      array_combine( $reviewers, array_map( array($allocs, 'nameOfReviewer'), $reviewers));

    foreach( $markers as $id => $uident )
      $reviewerIDs[ $id ] = $uident;

    $table = table( array('id'=>'allocs'), HTML::tr( HTML::th( _('Reviewer') ), HTML::th( _('Allocations') )));
    foreach( $reviewerIDs as $r => $who ) {
      $es = isset( $allocs->byReviewer[ $r ] ) ? array_keys( $allocs->byReviewer[ $r ] ) : array( );
      $td = HTML::td( );
      foreach( $es as $e )
	$td->pushContent( adjustmentCell( $allocs->byEssay[ $e ][ $r ], $allocs->nameOfAuthor( $e ) ), ' ');
      $td->pushContent( HTML::a( array('class'=>'addAlloc'), HTML::raw('&oplus;') ));
      $table->pushContent( HTML::tr( HTML::td( array('id'=>$r), $who ), $td ));
    }
  } else {
    $essays = array_keys( $allocs->byEssay );
    $essayIDs = empty( $essays ) ? array( )
      : array_combine( $essays, array_map( array($allocs, 'nameOfAuthor'), $essays));

    $table = table( array('id'=>'allocs'), HTML::tr( HTML::th( _('Author'),  HTML::th( _('Reviewers') ) )));
    foreach( $essayIDs as $e => $who ) {
      $rs = array_keys( $allocs->byEssay[ $e ] );
      $td = HTML::td( );
      foreach( $rs as $r )
	$td->pushContent( adjustmentCell( $allocs->byEssay[ $e ][ $r ], $allocs->nameOfReviewer( $r )), ' ');
      $td->pushContent( HTML::a( array('class'=>'addAlloc'), HTML::raw('&oplus;') ));
      $table->pushContent( HTML::tr( HTML::td( array('id'=>$e), $who ), $td ));
    }
  }

  extraHeader('adjustAllocations.js', 'script');
  extraHeader('bootstrap.min.js', 'js');
  extraHeader('bootstrap-typeahead.js', 'js');

  $src = array( );
  if( $order == 'reviewer' ) {
    $authors = findAllAuthors( $assmt );
    if( $assmt['authorsAre'] == 'group' ) {
      $gname = array( );
      $rs = checked_mysql_query( "SELECT groupID, gname FROM `Groups` WHERE assmtID = $assmtID" );
      while( $row = $rs->fetch_assoc() )
	$gname[ -$row['groupID'] ] = $row['gname'];
      $aidents = array( );
      foreach( $authors as $e )
	$aidents[ $e ] = isset( $gname[ $e ] ) ? $gname[ $e ] : "Group$e";
    } else
      $aidents = userIDsToIdentities( $authors );

    foreach( $aidents as $e => $uident )
      $src[] = "'" . addcslashes( $uident, "'\\" ) . "'";

  } else {
    foreach( userIDsToIdentities( findAllReviewers( $assmt ) ) as $r => $uident )
      $src[] = "'" . addcslashes( $uident, "'\\" ) . "'";
    foreach( $markers as $r => $uident )
      $src[] = "'" . addcslashes( $uident, "'\\" ) . "'";
  }

  if( $order == 'reviewer' )
    $instruct = RedirectButton( _('View by author'),
				"adjustAllocations&assmtID=$assmtID&cid=$cid&allocOrder=submission" );
  else
    $instruct = RedirectButton( _('View by reviewer'),
				"adjustAllocations&assmtID=$assmtID&cid=$cid&allocOrder=reviewer" );

  return HTML(assmtHeading(_('Adjust allocations'), $assmt),
	      ButtonToolbar($instruct),
	      JavaScript('var src = [' . join(',', $src) . '];'),
	      HTML::form(array('method'=>'post',
			       'class'=>'form-inline',
			       'action'=>"$_SERVER[PHP_SELF]?action=saveAdjustAllocations"),
			 HiddenInputs(array('assmtID'=>$assmtID,
					    'allocOrder'=>$order,
					    'cid'=>$cid)),
			 $table,
			 ButtonToolbar(submitButton(_('Save changes')),
				       formButton(_('Cancel'), "viewAsst&assmtID=$assmtID&cid=$cid"))),
	      HTML::div(array('class'=>'alert alert-info',
			      'role'=>'alert'),
			_('Less commonly used options appear below.')),
	      RedirectButton(_('Re-run the automatic allocation generator'),
			     "regenerateAllocations&assmtID=$assmtID&cid=$cid"));
}

function regenerateAllocations( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  checked_mysql_query( 'LOCK TABLE Assignment WRITE, Allocation WRITE, Allocation a READ, Allocation b READ,'
		       . ' LastMod WRITE, User READ,'
		       . ' AllocationAudit WRITE,'
		       . ' UserCourse READ, Essay READ, Reviewer READ, Author READ, Stream READ,'
		       . ' `Groups` READ, GroupUser READ');
  doUnsupervisedAllocation( $assmt );
  allocateMarkers($assmtID, $assmt);
  checked_mysql_query( 'UNLOCK TABLES' );
  redirect( 'viewAllocations', "assmtID=$assmtID&cid=$cid" );
}



function adjustmentCell( $a, $name ) {
  if( ! empty( $a['lastMarked'] ) )
    return HTML::u( $name );
  else if( ! empty( $a['lastMarked'] ) )
    return HTML::b( $name );
  else
    return HTML::input( array('class'=>'form-control', 'type'=>'text', 'size'=>6, 'value'=>$name, 'name'=>"alloc[$a[allocID]]") );
}


function saveAdjustAllocations( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  // $_REQUEST has alloc[allocID] for updates to existing allocations
  //           and newAlloc[userID][] for new allocations for userID (these are added by adjustAllocations.js)
  $order = $_REQUEST['allocOrder'];
  $updates = $_REQUEST['alloc'];
  if( ! is_array( $updates ) ) $updates = array( );
  $inserts = $_REQUEST['newAlloc'];
  if( ! is_array( $inserts ) ) $inserts = array( );

  require_once 'users.php';

  if( $order == 'reviewer' ) {
    $lhs = $assmt['reviewersAre'] == 'group' ? 'group' : 'user';
    $rhs = $assmt['authorsAre']   == 'group' ? 'group' : 'user';
  } else {
    $lhs = $assmt['authorsAre']   == 'group' ? 'group' : 'user';
    $rhs = $assmt['reviewersAre'] == 'group' ? 'group' : 'user';
  }

  $allIdents = array( );
  if( $rhs == 'user' ) {
    foreach( $updates as $uident )
      $allIdents[ $uident ] = true;
    foreach( $inserts as $who => $names )
      foreach( $names as $uident )
	$allIdents[ $uident ] = true;
    $rhsMap = identitiesToUserIDs( array_keys( $allIdents ) );
  } else {
    $rhsMap = array( );
    if( $assmt['authorsAre'] == 'group' || $assmt['reviewersAre'] == 'group' ) {
      $rs = checked_mysql_query( "SELECT groupID, gname FROM `Groups` WHERE assmtID = $assmtID" );
      while( $row = $rs->fetch_assoc() )
	$rhsMap[ strtolower( trim($row['gname'])) ] = -$row['groupID'];
    }
  }

  $authorOrReviewer = $order == 'reviewer' ? 'author' : 'reviewer';
  foreach( $updates as $allocID => $ident ) {
    $ident = strtolower( trim( $ident ) );
    if( empty( $ident ) )
      checked_mysql_query( "DELETE FROM Allocation WHERE allocID = " . (int)$allocID
			   . " AND lastMarked IS NULL"
			   . " AND assmtID = $assmtID" );
    else
      checked_mysql_query( "UPDATE IGNORE Allocation SET $authorOrReviewer = " . (int)$rhsMap[ $ident ]
			   . " WHERE allocID = " . (int)$allocID
			   . " AND lastViewed IS NULL"
			   . " AND lastMarked IS NULL"
			   . " AND assmtID = $assmtID" );
  }

  foreach( $inserts as $who => $names )
    foreach( $names as $ident ) {
      $ident = strtolower( trim( $ident ) );
      if( $ident != '' ) {
	if( $order == 'reviewer' ) {
	  $reviewer = (int)$who;
	  $author = (int)$rhsMap[ $ident ];
	} else {
	  $author = (int)$who;
	  $reviewer = (int)$rhsMap[ $ident ];
	}
	checked_mysql_query( "INSERT IGNORE INTO Allocation (assmtID, reviewer, author, tag)"
			     . " VALUES ($assmtID, $reviewer, $author, 'MANUAL')" );
      }
    }

  $now = quote_smart( date('Y-m-d H:i:s') );
  checked_mysql_query( 'UPDATE Assignment'
		       . " SET allocationsDone = $now"
		       . " WHERE assmtID = $assmtID" );

  redirect( 'viewAsst', "assmtID=$assmtID&cid=$cid" );
}


function allocateGroups() {
  list( $assmt, $assmtID, $cid, $courseID ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  $groups  = fetchAll( "SELECT groupID, gname FROM `Groups` WHERE assmtID=$assmtID"
		       . ' ORDER BY LENGTH(gname), gname' );
  $members = array( );

  if( empty( $groups ) ) {
    $groups[] = array('groupID'=>1);
    $members[1] = array( );
  }

  $rs = checked_mysql_query( 'SELECT groupID, IFNULL(uident, CONCAT("u-", GroupUser.userID)) AS who'
			     . ' FROM GroupUser LEFT JOIN User ON GroupUser.userID=User.userID'
			     . " WHERE assmtID=$assmtID" );
  while( $row = $rs->fetch_assoc() ) {
    if( ! isset( $members[ $row['groupID'] ] ) )
      $members[ $row['groupID'] ] = array( );
    $members[ $row['groupID'] ][] = $row['who'];
  }

  $tbody = HTML::tbody( );
  foreach( $groups as $g ) {
    $groupID = $g['groupID'];
    if( ! isset( $members[ $groupID ] ) )
      $members[ $groupID ] = array( );
    $tbody->pushContent(HTML::tr(HTML::td(HTML::input(array('type'=>'text',
							    'name'=>"gname[$groupID]",
							    'class'=>'incr',
							    'onblur' => 'updateUnassigned()',
							    'onfocus'=>'setDefaultGroupName($(this))',
							    'size'=>10,
							    'value'=> empty($g['gname']) ? "Group-$groupID" : $g['gname']))),
				 HTML::td(HTML::input(array('type'=>'text',
							    'name'=>"members[$groupID]",
							    'size'=>80,
							    'onfocus'=>'maybeAddRow()',
							    'value'=>join(', ', $members[$groupID]))))));
  }

  extraHeader('groups.js', 'js' );
  extraHeader('$(\'input[name^="members"]\').on("blur", function() {checkNames(this.value);});', 'onload' );

  $nPerReviewer = $assmt['nPerReviewer'];
  if( $nPerReviewer <= 0 )
    $nPerReviewer = '';

  $unassignedList = HTML::ul(array('id'=>'unassigned'));
  foreach( fetchAll( 'SELECT uident FROM UserCourse uc'
		     . ' LEFT JOIN User u ON uc.userID=u.userID'
		     . ' LEFT JOIN Assignment a ON a.courseID = uc.courseID'
		     . ' LEFT JOIN GroupUser g ON uc.userID=g.userID AND a.assmtID=g.assmtID'
		     . " WHERE uc.courseID = $courseID"
		     . " AND a.assmtID = $assmtID"
		     . ' AND (roles&1) = 1'
		     . ' AND g.userID IS NULL'
		     . ' ORDER BY uident',
		     'uident' ) as $name)
    $unassignedList->pushContent( HTML::li( HTML::span( array('class'=>'uident'), $name )));
  if( $unassignedList->isEmpty( ) )
    $unassigned = message(_('All students have been assigned a group'));
  else
    $unassigned = HTML( HTML::h3(_('The following students have not yet been assigned to any group:')),
			$unassignedList );

  if( $assmt['allocationsDone'] )
    $warn = warning( _('Allocation records have already been created for this assignment.  Changing the allocation parameters may invalidate existing reviews.'));
  else
    $warn = '';

  $select = HTML::select( array('id'=>'selectAssmt',
				'class'=>'btn',
				'onchange'=>"getGroups($cid)") );
  foreach( fetchAll( 'SELECT DISTINCT g.assmtID, aname FROM GroupUser g LEFT JOIN Assignment a ON g.assmtID=a.assmtID'
		     . " WHERE g.assmtID <> $assmtID AND courseID = " . cidToClassId( $cid ) ) as $otherAssmt )
    $select->pushContent( HTML::option( array('value'=>$otherAssmt['assmtID'] ), $otherAssmt['aname'] ) );
  if( ! $select->isEmpty( ) ) {
    $select->unshiftContent( HTML::option( array('value'=>0), _('(none)') ));
    $loadFromAssignment = HTML( _('Copy groups from assignment '), $select);
  } else
    $loadFromAssignment = '';

  $quickGroupEntry = Modal('quick-group-dialog',
			   _('Quick group entry'),
			   HTML(HTML::h3(_('Paste the groups into the text area below, one line per group')),
				HTML::textarea(array('id'=>'quickGroupArea',
						     'rows'=>20,
						     'cols'=>80)),
				HTML::div(array('class'=>'form-inline'),
					  FormGroup('groupNameIsFirst',
						    _('Lines start with the group name'),
						    HTML::input(array('type'=>'checkbox',
								      'id'=>'groupNameIsFirst'))))),
			   HTML::button(array('type'=>'button',
					      'class'=>'btn',
					      'onclick'=>'quickGroupLoad()'),
					_('Load groups')));

  if ($assmt['allocationsDone'])
    $changeType = _('Manually adjust allocations');
  else
    $changeType = _('Change allocation type');
  return HTML(assmtHeading(_('Group allocations'), $assmt),
	      $warn,
	      HTML::p(_('Enter the students for each group, separated by a comma or space.')),
	      HTML::form(array('method'=>'post',
			       'class'=>'form',
			       'action'=>"$_SERVER[PHP_SELF]?action=saveAllocateGroups"),
			 HiddenInputs(array('assmtID'=>$assmtID, 'cid'=>$cid)),
			 HTML::input(array('type'=>'hidden',
					   'id'=>'getGroupsPrompt',
					   'value'=>_('Are you sure you wish to replace the existing groups?'))),
			 JavaScript("var cid=$cid;"),
			 table(array('id'=>'groups'),
			       HTML::thead(HTML::tr(HTML::th(_('Group')), HTML::th(_('Members')))),
			       $tbody),
			 HTML::button(array('type'=>'button',
					    'class'=>'btn',
					    'title'=> _('Allows cut-and-paste of group membership'),
					    'data-toggle'=>'modal',
					    'data-target'=>'#quick-group-dialog'),
				      _('Quick group entry')),
			 $loadFromAssignment,
			 HTML::br(),
			 HTML::br(),
			 HTML::p(_('In this allocation method, students work in groups, and jointly produce a single artifact to submit. '),
				 _('Any member of the group can submit on behalf of the whole group.')),
			 HTML::h3(_('Reviewing')),
			 RadioGroup('reviewersAre',
				    null,
				    $assmt['reviewersAre'],
				    array('submit' => _('Reviewers are all students who belong to a group that has submitted'),
					  'group'  => _('Each submitting group writes a joint review'),
					  'all'    => _('Reviewers are everyone in the class'),
					  'other'  => _('Reviewers are just the selected markers below'))),
			 FormGroup('nPerReviewer',
				   _('Number of submissions each reviewer/group should mark'),
				   HTML::input(array('type'=>'number',
						     'min'=>1,
						     'id' => 'nPerReviewer',
						     'style'=>'width: 6em',
						     'required'=>$assmt['reviewersAre'] != 'other',
						     'value'=>$nPerReviewer)),
				   ' '),
			 JavaScript('$("input[name=reviewersAre]").change(function() {
 $("#nPerReviewer").attr("required", this.value != "other");
})'),
			 makeMarkerEntry( _('Additional markers for this assignment'),
					  getCourseMarkers( $courseID ),
					  getAssignmentReviewers( $assmtID ),
					  $assmt['reviewerMarking'] ),
			 ButtonToolbar(submitButton(_('Save')),
				       submitButton($changeType, 'change'),
				       formButton(_('Cancel'), "viewAsst&assmtID=$assmtID&cid=$cid"))),
	      $quickGroupEntry,
	      $unassigned);
}

function getAssignmentReviewers( $assmtID ) {
  $rs = checked_mysql_query( 'SELECT userID, IFNULL(uident, CONCAT("u-", userID))'
			     . ' FROM Reviewer LEFT JOIN User ON reviewer=userID'
			     . " WHERE assmtID = $assmtID");
  $reviewers = array( );
  while( list($userID, $r) = $rs->fetch_row() )
    $reviewers[ $userID ] = $r;
  return $reviewers;
}

function getCourseMarkers( $courseID ) {
  $rs = checked_mysql_query( 'SELECT UserCourse.userID, IFNULL(uident, CONCAT("u-", UserCourse.userID))'
			     . ' FROM UserCourse LEFT JOIN User ON UserCourse.userID=User.userID'
			     . " WHERE courseID=$courseID AND (roles&2) <> 0" );
  $markers = array( );
  while( list($userID, $m) = $rs->fetch_row() )
    $markers[ $userID ] = $m;
  
  return $markers;
}


function getGroups( ) {
  list( $assmtID, $cid ) = checkREQUEST( '_assmtID', '_cid' );
  $groups = array( );
  foreach( fetchAll( 'SELECT uident, IFNULL(gname,CONCAT("Group-", gu.groupID)) as gname FROM GroupUser gu LEFT JOIN User u ON gu.userID = u.userID'
		     . ' LEFT JOIN `Groups` g ON g.groupID = gu.groupID AND g.assmtID = gu.assmtID'
		     . " WHERE gu.assmtID = $assmtID" ) as $row )
    $groups[ $row['gname'] ][] = $row['uident'];

  $jgs = array( );
  foreach( $groups as $gname => $members )
    $jgs[] = array( $gname, join( ', ', $members ) );
  echo json_encode( $jgs );
  exit;
}

function saveAllocateGroups( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  require_once 'users.php';
  $mvalues = array( );
  $nonEmptyGroups = array( );
  if( isset( $_REQUEST['members'] ) && is_array( $_REQUEST['members'] ) ) {
    $courseId = cidToClassId($cid);
    $classMembers = fetchAll("select userID from UserCourse where courseID = $courseId", 'userID');
    $newMembers = array();
    foreach( $_REQUEST['members'] as $groupID => $line )
      if( is_int( $groupID ) )
	foreach( identitiesToUserIDs( preg_split( '/[[:space:],;]/', $line, -1, PREG_SPLIT_NO_EMPTY ) ) as $userID ) {
	  $mvalues[] = "($assmtID,$groupID,$userID)";
	  $nonEmptyGroups[ $groupID ] = true;
	  if (!in_array($userID, $classMembers))
	    $newMembers[] = "($courseId, $userID, 1)";
	}
    checked_mysql_query( "DELETE FROM GroupUser WHERE assmtID = $assmtID" );
    if( ! empty( $mvalues ) )
      checked_mysql_query( 'INSERT INTO GroupUser (assmtID,groupID,userID) VALUES ' . join(',', $mvalues) );
    if (!empty($newMembers))
      checked_mysql_query('insert into UserCourse (courseID, userID, roles) values ' . join(',', $newMembers));
  }

  $reallocationNeeded = updateReviewers( $assmtID, $_REQUEST['markers'] );

  $gvalues = array( );
  foreach( array_keys( $nonEmptyGroups ) as $groupID ) {
    $gname = isset( $_REQUEST['gname'][ $groupID ] ) ? trim($_REQUEST['gname'][ $groupID ]) : '';
    if( empty($gname) )
      $gname = "Group-$groupID";
    $gvalues[] = "($assmtID,$groupID," . quote_smart( $gname ) . ')';
  }
  checked_mysql_query( "DELETE FROM `Groups` WHERE assmtID = $assmtID" );
  if( ! empty( $gvalues ) )
    checked_mysql_query( 'INSERT INTO `Groups` (assmtID,groupID,gname) VALUES ' . join(',', $gvalues) );

  $data = array('allocationType'=>'normal',
		'authorsAre'=>'group');
  if (in_array($_REQUEST['authorsAre'], array('all', 'group')))
    $data['authorsAre'] = $_REQUEST['authorsAre'];

  if( in_array( $_REQUEST['reviewersAre'], array('all', 'submit', 'group', 'other') ) )
    $data['reviewersAre'] = $_REQUEST['reviewersAre'];

  if( isset( $_REQUEST['reviewerMarking'] ) )
    $data['reviewerMarking'] = $_REQUEST['reviewerMarking'] == 'markAll' ? 'markAll' : 'split';


  if( isset( $_REQUEST['nPerReviewer'] ) && $_REQUEST['nPerReviewer'] > 0 )
    $data['nPerReviewer'] = (int)$_REQUEST['nPerReviewer'];
  else
    $data['nPerReviewer'] = 0;

  $data['nPerSubmission'] = 0;

  if( count( $data ) > 0 )
    checked_mysql_query( makeUpdateQuery( 'Assignment', $data )
			 . " WHERE assmtID = $assmtID" );

  //if( $assmt['isActive'] && ($reallocationNeeded || nowBetween( $assmt['submissionEnd'], null ) )) {
  //  $assmt = fetchOne( "SELECT * FROM Assignment WHERE assmtID = $assmtID" );
  //  doUnsupervisedAllocation( $assmt );
  //}

  if( isset( $_REQUEST['change'] ) ) {
    if ($assmt['allocationsDone'])
      redirect( 'setupAllocation', "cid=$cid&assmtID=$assmtID&type=manual" );
    else
      redirect( 'setupAllocation', "cid=$cid&assmtID=$assmtID" );
  } else if( isset( $_REQUEST['adjust'] ) )
    redirect( 'adjustAllocations', "cid=$cid&assmtID=$assmtID" );
  else
    redirect( 'viewAsst', "assmtID=$assmtID&cid=$cid" );
}


function allocateRatingReview( ) {
  list ($assmt, $assmtID, $cid, $courseID) = selectAssmt();
  if (!$assmt)
    return missingAssmt();

  $isReviewsFor = (int)$assmt['isReviewsFor'];

  ensureDBconnected('allocateRatingReview');

  if (isset($_REQUEST['reviewersAre'])) {
    updateReviewers($assmtID, $_REQUEST['markers']);

    $data = array();

    if (isset($_REQUEST['nPerReviewer']))
      $data['nPerReviewer'] = (int)$_REQUEST['nPerReviewer'];

    $data['reviewerMarking'] = 'split'; // Only option available, as of 2018-07-20
    $abusedReviewMarking = array('split' => 'review', 'markAll' => 'reviewer');
    if (isset($abusedReviewMarking[$_REQUEST['reviewerMarking']]))
      $data['authorsAre'] = $abusedReviewMarking[$_REQUEST['authorsAre']];
    
    if (in_array($_REQUEST['reviewersAre'], array('all', 'submit', 'other')))
      $data['reviewersAre'] = $_REQUEST['reviewersAre'];

    if (in_array($_REQUEST['authorsAre'], array('review', 'reviewer')))
      $data['authorsAre'] = $_REQUEST['authorsAre'];

    if ($_REQUEST['reviewersAre'] != 'other' && $_REQUEST['authorsAre'] == 'response') {
      $data['allocationType'] = 'response';
      $data['authorsAre'] = 'review';
    } else
      $data['allocationType'] = 'normal';

    if (!empty($data))
      checked_mysql_query(makeUpdateQuery('Assignment', $data) . " WHERE assmtID = $assmtID");

    if ($assmt['isActive'] && nowBetween($assmt['submissionEnd'], null))
      doUnsupervisedAllocation(fetchOne("select * from Assignment where assmtID = $assmtID"));

    if (isset($_REQUEST['adjust']))
      redirect('adjustAllocations', "cid=$cid&assmtID=$assmtID");
    else
      redirect('viewAsst', "cid=$cid&assmtID=$assmtID");
  } else {
    $reviewers = getAssignmentReviewers($assmtID);
    $markers = getCourseMarkers($courseID);
    $haveMarkers = !empty($reviewers) || !empty($markers);

    $nPerReviewer = $assmt['nPerReviewer'];
    if ($nPerReviewer <= 0)
      $nPerReviewer = 2;

    if ($assmt['authorsAre'] == 'all')
      $assmt['authorsAre'] = 'review';

    if ($assmt['allocationsDone'])
      $warn = warning(
	_('Allocation records have already been created for this assignment.  Changing the allocation parameters may invalidate existing reviews.'));
    else
      $warn = '';

    $reviewerSelect =
      HTML::div(
	array('class' => 'radio-group'),
	HTML::h3(_('Review markers are')),
	Radio(
	  'reviewersAre',
	  _('Everyone who reviewed in the original assignment'),
	  'submit',
	  $assmt['reviewersAre'] == 'submit'),
	Radio(
	  'reviewersAre',
	  _('Everyone who submitted in the original assignment'),
	  'all',
	  $assmt['reviewersAre'] == 'all'));
    if ($haveMarkers)
      $reviewerSelect->pushContent(
	Radio(
	  'reviewersAre',
	  _('Selected markers'),
	  'other',
	  $assmt['reviewersAre'] == 'other'));
    $studentAuthorsAre =
      HTML::div(
	array('class' => 'radio-group', 'id' => 'studentAuthorsAre'),
	HTML::h3(_('Review markers will mark')),
	Radio(
	  'authorsAre',
	  _('Reviews of their own work'),
	  'response',
	  $assmt['allocationType'] == 'response'),
	Radio(
	  'authorsAre',
	  HTML::div(
	    array('class' => 'form-inline'),
	    HTML::div(
	      array('class' => 'form-group'),
	      _('The reviews written by '),
	      HTML::input(
		array(
		  'type' => 'number',
		  'class' => 'form-control',
		  'style' => 'width: 5em',
		  'min' => 1,
		  'id' => 'per-reviewer',
		  'value' => $nPerReviewer,
		  'name' => 'nPerReviewer')),
	      _(' reviewers'))),
	  'reviewer',
	  $assmt['allocationType'] != 'response' && $assmt['authorsAre'] == 'reviewer'),
	Radio(
	  'authorsAre',
	  HTML::div(
	    array('class' => 'form-inline'),
	    _('A random selection of '),
	    HTML::div(
	      array('class' => 'form-group'),
	      HTML::input(
		array(
		  'type' => 'number',
		  'class' => 'form-control',
		  'style' => 'width: 5em',
		  'min' => 1,
		  'id' => 'per-review',
		  'value' => $nPerReviewer,
		  'name' => 'nPerReviewer')),
	      _(' reviews'))),
	  'review',
	  $assmt['allocationType'] != 'response' && $assmt['authorsAre'] == 'review'));

    $authorsAre = $assmt['reviewersAre'] == 'other' ? $assmt['authorsAre'] : '';
    $markingTeam = 
      HTML::div(
	array('id' => 'markingTeam'),
	HTML::h3(_('Marking team')),
	markersSelection($markers, $reviewers),
	RadioGroup(
	  'authorsAre',
	  null,
	  $authorsAre,
	  array('review' => _('An equal share of reviews'), 'reviewer' => _('An equal share of reviewers'))));
    
    extraHeader('reviewMarking.js', 'js');
    extraHeader('$("input[name=reviewersAre]").change(reviewersAreChanged)', 'onload');
    extraHeader('reviewersAreChanged(0)', 'onload');
    extraHeader('$("input[name=authorsAre]").change(authorsAreChanged)', 'onload');
    extraHeader('authorsAreChanged()', 'onload');

    $adjust = $assmt['allocationsDone'] ? submitButton( _('Adjust allocations'), 'adjust') : '';
    return HTML(
      assmtHeading(_('Allocations'), $assmt),
      $warn,
      HTML::form(
	array(
	  'name' => 'edit',
	  'class' => 'form',
	  'method' => 'post',
	  'action' => "$_SERVER[PHP_SELF]?action=allocateRatingReview"),
	HiddenInputs(array('assmtID'=>$assmtID, 'cid'=>$cid)),
	$reviewerSelect,
	$studentAuthorsAre,
	$markingTeam,
	br(),
	$assmt['allocationsDone'] ? '' : message(_('Allocation records will be created after the original assignment reviewing deadline')),
	ButtonToolbar(
	  submitButton(_('Save changes'), 'save'),
	  $adjust,
	  formButton(_('Cancel'), "viewAsst&assmtID=$assmtID&cid=$cid"))));
  }
}

function formInline() {
  $form = HTML::div(array('class' => "form-inline"),
		    HTML::div(array('class' => "form-group"),
			      HTML::label("A"),
			      HTML::input(array('type' => "text", 'class' => "form-control", 'placeholder' => "Fe"))));
}

function random_permutation( $arr ) {
  $copy = array( );
  while( ! empty( $arr ) ) {
    $i = array_rand( $arr );
    array_push( $copy, $arr[ $i ] );
    unset( $arr[ $i ] );
  }
  return $copy;
}
