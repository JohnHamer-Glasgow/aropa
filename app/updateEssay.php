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

function updateEssay( ) {
  list( $s, $aID, $cid ) = checkREQUEST( '_s', '_aID', '_cid' );

  if( empty( $_SESSION['allocations'][ $s ] ) )
    securityAlert( 'updateEssay', "unknown essay - $s" );

  $alloc = $_SESSION['allocations'][ $s ];

  if( ! isset( $_SESSION['availableAssignments'][ $aID ] ) )
    return reportCriticalError( 'updateEssay', 'cannot find assignment matching allocation' );
  $assmt =  $_SESSION['availableAssignments'][ $aID ];

  if( ! emptyDate($assmt['reviewEnd']) && ! nowBetween( null, $assmt['reviewEnd'] ) )
    return warning( Sprintf_('Reviewing for this assignment finished on %s', formatDateString( $assmt['reviewEnd'] )));
  else if( ! emptyDate($assmt['submissionEnd']) && ! nowBetween( $assmt['submissionEnd'], null ) )
    return warning( Sprintf_('Reviewing for this assignment does not start until %s', formatDateString( $assmt['submissionEnd'] )));

  ensureDBconnected( 'updateEssay' );

  //- Comments are all in $_POST['comment'][item]
  //- Marks    are all in $_POST['mark'   ][item]
  $marksPosted = array();
  if (isset($_POST['mark'])) {
    if( ! is_array( $_POST['mark'] ) )
      securityAlert( 'updateEssay', 'POST[mark] is not an array' );
    foreach ($_POST['mark'] as $item => $m)
      if (isset($assmt['mark-items'][$item]))
	$marksPosted[$item] = $m;
  }

  if (count($assmt['mark-items']) == 0)
    $markStatus = 'none';
  else if (count($marksPosted) == 0)
    $markStatus = 'not-started'; // i.e. "do not update"
  else {
    $markStatus = 'complete';
    foreach (array_keys($assmt['mark-items']) as $item)
      if (!isset($marksPosted[$item])) {
	$markStatus = 'partial';
	break;
      }

    $marks = itemsToString($marksPosted);
    checked_mysql_query("update Allocation set lastMarked = now(), marks = " . quote_smart($marks)
			. " where allocID = $alloc[allocID]");
    $_SESSION['allocations'][$s]['marks'] = $marks;
  }

  $commentsPosted = array();
  if (isset($_POST['comment'])) {
    if (! is_array($_POST['comment']))
      securityAlert('updateEssay', 'POST[comment] is not an array');

    foreach ($_POST['comment'] as $item => $c)
      if (isset($assmt['comment-items'][$item]) && trim($c) != "")
	$commentsPosted[$item] = preg_replace('/(<|&lt;)!--.*?--(>|&gt;)/', '', $c);
  }

  if (count($assmt['comment-items']) == 0)
    $commentStatus = 'none';
  else if (count($commentsPosted) == 0)
    $commentStatus = 'not-started'; // i.e. "do not update"
  else {
    $commentStatus = 'complete';
    foreach (array_keys($assmt['comment-items']) as $item)
      if (!isset($commentsPosted[$item]) ) {
	$commentStatus = 'partial';
	break;
      }
    $values = array();
    $newItems = array();
    foreach ($commentsPosted as $item => $comment) {
      $newItems[] = quote_smart($item);
      $values[] = '('
	. $alloc['allocID'] . ','
	. quote_smart($item) . ','
	. quote_smart($comment) . ','
	. $_SESSION['userID'] . ','
	. 'now()'
	. ')';
    }

    if (count($values) > 0)
      checked_mysql_query('replace into Comment (allocID, item, comments, madeBy, whenMade)'
			  . ' values ' . join(',', $values) );
  }

  $upload = array( );
  if( isset( $_FILES['file']['error'] ) ) {
    require_once 'uploadSubmissions.php'; // for maybeCompress
    foreach( $_FILES['file']['error'] as $item => $error ) {
      $tmp = $_FILES['file']['tmp_name'][ $item ];
      if( ! $tmp )
	continue;
      $info = pathinfo( $_FILES['file']['name'][ $item ] );

      switch( $error ) {
      case UPLOAD_ERR_OK:
	if( ! is_uploaded_file( $tmp ) )
	  securityAlert( 'possible upload attack' );
	list( $isCompressed, $contents ) = maybeCompress( file_get_contents( $tmp ) );
	$upload[ (int)$item ] = array( 'contents'    => $contents,
				       'compressed'  => $isCompressed,
				       'description' => $info['basename'],
				       'extn'        => $_FILES['file']['type'][ $item ] ? $_FILES['file']['type'][ $item ] : $info['extension'] );
	break;
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
	addWarningMessage( Sprintf_('The file <q>%s</q> is too large to upload.', $info['basename']));
	break;

      case UPLOAD_ERR_PARTIAL:
	addWarningMessage( Sprintf_('The file <q>%s</q> was only partially uploaded (perhaps you cancelled it after starting the upload?).', $info['basename']));
	break;

      case UPLOAD_ERR_NO_FILE:
	addWarningMessage( _('The file <q>%s</q> was not uploaded', $info['basename']) );
	break;
	
      case UPLOAD_ERR_EXTENSION:
      case UPLOAD_ERR_NO_TMP_DIR:
      case UPLOAD_ERR_CANT_WRITE:
	addWarningMessage( _('The file <q>%s</q> could not be uploaded (error code #%d).', $info['basename'], $error) );
	break;
      }
    }

    $db = ensureDBconnected('updateEssay');
    //- Many MySQL servers are configured with a max_packet_size of 1Mb.
    //- We may need to allow larger files that that, hence the ReviewFileOverflow table.
    $MAX_CHUNK = 600*1024; //- Allow (ample) room for quote expansion
    $std = array('allocID' => $alloc['allocID'],
		 'madeBy'  => $_SESSION['userID']);
    foreach( $upload as $item => $u ) {
      if( strlen( $u['contents'] ) > $MAX_CHUNK ) {
	$overflow = substr( $u['contents'], $MAX_CHUNK );
	$u['contents'] = substr( $u['contents'], 0, $MAX_CHUNK );
	$u['overflow'] = true;
      } else
	$overflow = '';
      checked_mysql_query( 'DELETE FROM ReviewFileOverflow USING ReviewFileOverflow'
			   . ' LEFT JOIN ReviewFile ON ReviewFileOverflow.reviewFileID=ReviewFile.reviewFileID'
			   . " WHERE allocID=$alloc[allocID] AND item=$item" );
      checked_mysql_query( makeReplaceQuery( 'ReviewFile', $std + $u + array('item'=>$item)));
      $reviewFileID = $db->insert_id;
      for( $block = 0; strlen( $overflow ) > $block; $block += $MAX_CHUNK )
	checked_mysql_query( "INSERT INTO ReviewFileOverflow (reviewFileID, seq, data) VALUES ($reviewFileID, $block, " . quote_smart(substr($overflow, $block, $MAX_CHUNK)) . ")");
    }
  }

  if ($assmt['nReviewFiles'] == 0)
    $reviewFileStatus = 'none';
  else {
    $nReviewFiles = fetchOne("select count(*) as n from ReviewFile where allocID = $alloc[allocID]", 'n');
    if ($nReviewFiles >= $assmt['nReviewFiles'])
      $reviewFileStatus = 'complete';
    else {
      addWarningMessage( _('You have not uploaded a required review file.') );
      $reviewFileStatus = $nReviewFiles > 0 ? 'partial' : 'not-started';
    }
  }

  $status = combineStatus($markStatus, combineStatus($commentStatus, $reviewFileStatus));
  if ($status == 'none') $status = 'complete';
  if ($status != 'not-started') {
    checked_mysql_query(makeUpdateQuery('Allocation',
					array('status' => $status),
					array('lastMarked' => 'now()'))
			. " where allocID = $alloc[allocID]");
    $_SESSION['allocations'][$s]['lastMarked'] = formatTimestamp(time());
    $_SESSION['allocations'][$s]['status'] = $status;
  }
  
  redirect( 'showMarks', "s=$s&cid=$cid&aID=$aID" );
}

function combineStatus($a, $b) {
  if ($a == 'none') return $b;
  if ($b == 'none') return $a;
  if ($a == $b) return $a;
  return 'partial';
}

function showMarks( ) {
  list( $s, $aID, $cid ) = checkREQUEST( '_s', '_aID', '_cid' );

  if( empty( $_SESSION['allocations'][ $s ] ) )
    securityAlert( "unknown essay - $s" );

  $alloc = $_SESSION['allocations'][ $s ];

  if( ! isset( $_SESSION['availableAssignments'][ $aID ] ) )
    return reportCriticalError( 'showMarks', 'cannot find assignment matching allocation' );
  $assmt = $_SESSION['availableAssignments'][ $aID ];

  $comments[] = array();
  $cs = checked_mysql_query('select item, comments from Comment'
			    . " where allocID = $alloc[allocID]"
			    . ' order by whenMade desc');
  while ($row = $cs->fetch_assoc())
    $comments[$row['item']][] = $row;

  $reviewFiles = array();
  if ($assmt['nReviewFiles'] > 0) {
    $cs = checked_mysql_query('select reviewFileID, item from ReviewFile'
			      . " where allocID = $alloc[allocID]"
			      . ' order by whenUploaded desc');
    while ($row = $cs->fetch_assoc())
      $reviewFiles[(int)$row['item']][] = $row;
  }

  parse_str($alloc['marks'] ?? '', $marks);

  require_once 'BlockParser.php';
  list($xml) = TransformRubricByID($assmt['rubricID'], $marks, $comments, $reviewFiles, 'show', array('author'=>$_SESSION['userID']));

  //- ***only give RE-mark option if (a) not locked and (b) reviewEnd has not passed
  if (!$alloc['locked'] && nowBetween( null, $assmt['reviewEnd'])) {
    $remarkLink = formButton(_('Re-mark'), "mark&cid=$cid&aID=$aID&s=$s" );
    $prompt = _('Please check your responses, and use the re-mark button below if you need to make changes');
  } else {
    $remarkLink = '';
    $prompt = _('Please check your responses.  Note that it is no longer possible to change your review.');
  }

  $idx = $alloc['allocIdx'];
  $title = $assmt['anonymousReview'] ? "allocation #$idx" : userIdentity($alloc['author'], $assmt, 'author');
  return HTML(HTML::h1(Sprintf_('Summary of marking for %s', $title)),
	      HTML::p(_('Your submitted review is shown below. '), $prompt),
	      pendingMessages(), br(),
	      ButtonToolbar($remarkLink,
			    formButton(_('Finished'), "viewStudentAsst&cid=$cid&aID=$aID")),
	      HTML::hr(),
	      $xml);
}
