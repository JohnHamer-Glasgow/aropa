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
 
function viewSubmissions( ) {
  list ($assmt, $assmtID, $cid) = selectAssmt();
  if (! $assmt)
    missingAssmt();

  $requirements = array();
  foreach (explode("\n", $assmt['submissionRequirements']) as $requireStr) {
    //- $requireStr is expected to look like:
    //-    [file | url | inline] ","  [require | optional] "," ...urlencode'd additional key=value requirements...
    list($type, $required, $argStr) = explode(",", $requireStr);
    if (empty($required)) continue;
    parse_str($argStr ?? '', $args);
    $requirements[] = array($type, $required, $args);
  }

  $orderBy = 'author_ASC';
  $authorOrder = 'author';
  $groupOrder = 'group';
  $tagOrder = 'tag';
  $dateOrder = 'date';
  if( isset( $_REQUEST['sort'] ) )
    switch( $_REQUEST['sort'] ) {
    case 'author':     $orderBy = 'author_DESC';       $authorOrder = 'rauthor'; break;
    case 'rauthor':    $orderBy = 'author_ASC'; break;
    case 'group':      $orderBy = 'group_ASC';         $groupOrder = 'rgroup'; break;
    case 'rgroup':     $orderBy = 'group_DESC'; break;
    case 'tag':        $orderBy = 'tag_ASC';           $tagOrder = 'rtag'; break;
    case 'rtag':       $orderBy = 'tag_DESC'; break;
    case 'date':       $orderBy = 'whenUploaded_ASC';  $dateOrder = 'rdate'; break;
    case 'rdate':      $orderBy = 'whenUploaded_DESC'; break;
    }

  $sort = "viewSubmissions&cid=$cid&assmtID=$assmtID&sort";
  $assmtTags = preg_split("/[;,[:space:]]/", $assmt['tags'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
  $tr = HTML::tr( HTML::th( redirect_url( _('Author'), "$sort=$authorOrder")));
  if( $assmt['authorsAre'] == 'group' )
    $tr->pushContent( HTML::th( redirect_url( _('Group'), "$sort=$groupOrder" )));
  if( ! empty( $assmtTags ) )
    $tr->pushContent( HTML::th( redirect_url( _('Tag'), "$sort=$tagOrder" )));
  foreach( $requirements as $reqIndex => $req )
    $tr->pushContent( HTML::th( 'Document', count($requirements) > 0 ? ('#' . ($reqIndex+1)) : ''));
  $tr->pushContent( HTML::th( redirect_url( _('Uploaded'), "$sort=$dateOrder" )));
  $table = table( $tr );

  if( $assmt['authorsAre'] == 'group' )
    $rs = checked_mysql_query( 'SELECT essayID, e.author, tag, reqIndex, description, whenUploaded,'
			       . ' LENGTH(essay) AS len, IFNULL(gname, CONCAT("Group-",g.groupID)) AS gname, g.groupID FROM Essay e'
			       . ' LEFT JOIN GroupUser g ON g.userID=e.author AND g.assmtID=e.assmtID'
			       . ' LEFT JOIN `Groups` gr ON gr.groupID = g.groupID AND gr.assmtID = g.assmtID'
			       . " WHERE isPlaceholder = 0 and e.assmtID = $assmtID");
  else
    $rs = checked_mysql_query( 'SELECT essayID, e.author, tag, reqIndex, description, whenUploaded,'
			       . ' LENGTH(essay) AS len FROM Essay e'
			       . " WHERE isPlaceholder = 0 and e.assmtID = $assmtID" );
  $authorToEssays = array( );

  while( $row = $rs->fetch_assoc() ) {
    $author = userIdentity($row['author'], $assmt, 'author');
    if( ! isset( $authorToEssays[ $author ] ) )
      $authorToEssays[ $author ] = array( );
    $authorToEssays[ $author ][] = $row;
  }

  if( $orderBy == 'author_ASC' || $orderBy == 'author_DESC' )
    uksort( $authorToEssays, $orderBy );
  else
    uasort( $authorToEssays, $orderBy );

  //- we have count( $authorToEssays ) submissions
  //- if authorsAre groups, then this does not correspond to the number of groups with submissions.  Need to count distinct gnames.
  
  $expecting = array();
  switch ($assmt['authorsAre']) {
  case 'all':
    foreach (fetchAll('select userID from UserCourse'
		      . ' where (roles&1) != 0 and courseID = ' . cidToClassId( $cid )) as $expect)
      $expecting[$expect['userID']] = userIdentity($expect['userID'], $assmt, 'author');
    $submissions = count($authorToEssays);
    break;
  case 'group':
    foreach (fetchAll('select groupID, gname'
		      . ' from `Groups` g'
		      . " where assmtID = $assmtID") as $expect)
      $expecting[$expect['groupID']] = userIdentity(-$expect['groupID'], $assmt, 'author');
    $groupIDs = array();
    foreach ($authorToEssays as $essays)
      $groupIDs[$essays[0]['groupID']] = true;
    $submissions = count($groupIDs);
    break;
  case 'other':
    foreach (fetchAll("select author from Author where assmtID = $assmtID") as $expect)
      $expecting[$expect['author']] = userIdentity($expect['author'], $assmt, 'author');
    $submissions = count($authorToEssays);
    break;
  }

  $table->unshiftContent(HTML::caption(Sprintf_('%d received (expecting %d)',
						$submissions,
						count($expecting))));

  foreach ($authorToEssays as $author => $essays) {
    $table->pushContent(makeAuthorRow($requirements, $author, $assmt['authorsAre'], $assmtTags, $essays, $cid));
    if ($assmt['authorsAre'] == 'group')
      unset($expecting[$essays[0]['groupID']]);
    else
      unset($expecting[$essays[0]['author']]);
  }

  $extensions = table(HTML::tr(HTML::th(_('Author')), HTML::th(_('Extension until')), HTML::th('')));
  $haveExtensions = false;
  foreach (fetchAll('select e.author, x.submissionEnd'
		    . ' from Essay e'
		    . ' inner join Extension x on x.who = e.author and x.assmtID = e.assmtID'
		    . " where isPlaceholder = 1 and x.submissionEnd is not null and e.assmtID = $assmtID") as $p) {
    $haveExtensions = true;
    $extensions->pushContent(HTML::tr(HTML::td(userIdentity($p['author'], $assmt, 'author')),
				      HTML::td(formatDateString($p['submissionEnd'])),
				      HTML::td(nowBetween($p['submissionEnd'], null) ? HTML::b(_('Expired')) : '')));
  }
	   
  return HTML(assmtHeading(_('Submissions'), $assmt),
	      $table,
	      empty($expecting) ? '' : HTML::p(HTML::b(_('Yet to submit: ')), join(', ', $expecting)),
	      $haveExtensions
	      ? HTML(HTML::h3(callback_url(_('Extensions'),
					   "addExtensions&cid=$cid&assmtID=$assmtID")),
		     $extensions)
	      : '');
}

function author_ASC( $a, $b ) {
  return strnatcasecmp( $a, $b );
}
function group_ASC( $a, $b ) {
  if( preg_match( '/^Group-(\d+)$/', $a[0]['gname'], $na ) == 1 && preg_match( '/^Group-(\d+)$/', $b[0]['gname'], $nb ) == 1 )
    return (int)$na[1] - (int)$nb[1];
  else
    return strcasecmp( $a[0]['gname'], $b[0]['gname'] );
}
function whenUploaded_ASC( $a, $b ) {
  foreach( $a as $row )
    $maxA = isset($maxA) ? max($maxA, $row['whenUploaded']) : $row['whenUploaded'];
  foreach( $b as $row )
    $maxB = isset($maxB) ? max($maxB, $row['whenUploaded']) : $row['whenUploaded'];
  return strcmp($maxA, $maxB);
}
function tag_ASC( $a, $b ) {
  return strcasecmp( $a[0]['tag'], $b[0]['tag'] );
}

function author_DESC( $a, $b ) { return -author_ASC( $a, $b ); }
function group_DESC( $a, $b ) { return -group_ASC( $a, $b ); }
function tag_DESC( $a, $b ) { return -tag_ASC( $a, $b ); }
function whenUploaded_DESC( $a, $b ) { return -whenUploaded_ASC( $a, $b ); }

function makeAuthorRow( $requirements, $author, $authorsAre, $assmtTags, $essays, $cid ) {
  if( ! $author )
    return '';
  $tr = HTML::tr( HTML::td( $author ) );
  if( $authorsAre == 'group' )
    $tr->pushContent( HTML::td( $essays[0]['gname'] ));
  $tr->pushContent( essayTagCell( $essays, $assmtTags ) );
  foreach( $requirements as $reqIndex => $req )
    $tr->pushContent( HTML::td( essayCell( $essays, $reqIndex+1, $req, $cid )));
  foreach( $essays as $e )
    if( ! isset( $lastUpload ) || $e['whenUploaded'] > $lastUpload )
      $lastUpload = $e['whenUploaded'];
  if( isset( $lastUpload ) )
    $tr->pushContent( HTML::td( formatTimestamp( $lastUpload ) ));
  return $tr;
}

function essayCell( $essays, $reqIndex, $req, $cid ) {
  foreach( $essays as $e )
    if( $e['reqIndex'] == $reqIndex ) {
      $args = $req[2];
      switch( $req[0] ) {
      case 'file':
	if( preg_match( makeExtnPREG( $args['extn'], $args['other'] ), $e['description'] ) > 0 )
	  $desc = $e['description'];
	else
	  $desc = message( $e['description'] );
	break;
      case 'url':
	$desc = $e['url'];
	break;
      default:
	$desc = $e['description'];
	break;
      }
      return callback_url( $desc, "downloadByID&essayID=$e[essayID]&cid=$cid");
    }
  return $req[1] == 'required' ? message(_('MISSING')) : '';
}

function downloadByID( ) {
  list ($essayID, $cid) = checkREQUEST('_essayID', '_cid');
  $row = fetchOne(
    'select e.url, e.essay, e.author, a.aname, a.assmtID, e.description, e.extn, e.compressed, e.overflow'
    . ' from Essay e inner join Assignment a on e.assmtID = a.assmtID'
    . " where e.essayID = $essayID and a.courseID = " . cidToClassId($cid));
  if (!$row)
    return warning( _('Unable to find that submission') );
  if ($row['extn'] == 'inline-text') {
    $assmt = fetchOne(
      "select assmtID, isReviewsFor, authorsAre, courseID from Assignment where assmtID=$row[assmtID]");
    require_once 'XMLcleaner.php';
    $text = $row['compressed'] ? gzuncompress($row['essay']) : $row['essay'];
    if (isset( $_REQUEST['raw']))
      return HTML::div( $text );
    else
      return
	HTML(
	  HTML::h1(
	    Sprintf_(
	      'Submission from <q>%s</q> for assignment <q>%s</q>',
	      userIdentity($row['author'], $assmt, 'author'),
	      $row['aname'])),
	  HTML::div(
	    array('class'=>'download-text'),
	    HTML::raw(cleanupHTML($text))),
	  formButton(_('Return'), "viewSubmissions&cid=$cid&assmtID=$row[assmtID]"));
  } else if (!empty($row['url'])) {
    require_once 'download.php';
    if ($row['compressed']) {
      @$raw = file_get_contents($row['url']);
      if ($raw === false) essay_url_not_found($row['url'], $essayID, $row['description']);
      $contents = gzuncompress($raw);
      prepareForDownload($row['extn'], strlen($contents), $row['description']);
      session_write_close();
      echo $contents;
      exit;
    }

    @$fd = fopen($row['url'], 'rb');
    if (!$fd) return essay_url_not_found($row['url'], $essayID, $row['url']);   
    $stat = fstat($fd);
    prepareForDownload($info['extension'], $stat[7], $row['description']);
    session_write_close();
    fpassthru($fd);
    fclose($fd);
    exit;
  } else {
    require_once 'download.php';
    $raw = $row['essay'];
    if( $row['overflow'] ) {
      ini_set('memory_limit', '512M');
      $rs = checked_mysql_query("select data from Overflow where essayID = $essayID order by seq");
      while ($chunk = $rs->fetch_row())
	$raw .= $chunk[0];
    }
    
    $essay = $row['compressed'] ? gzuncompress($raw) : $raw;
    prepareForDownload($row['extn'], strlen($essay), $row['description']);
    echo $essay;
    exit;
  }
}

function makeExtnPREG( $extn, $other ) {
  if( $extn == 'other' )
    $extn = $other;
  switch( $extn ) {
  case 'any':
    return "//";
  default:
    $pat = array( );
    foreach( explode(",", $extn ) as $e )
      $pat[] = '(' . preg_quote($e) . ')';
    return "/\\." . join('|', $pat) . '$/i';
  }
}


function essayTagCell( $essays, $assmtTags ) {
  if( empty( $assmtTags ) )
    return '';

  $tag = null;
  $error = '';
  foreach( $essays as $e )
    if( $tag == null )
      $tag = trim( $e['tag'] );
    else if( $tag != $e['tag'] ) {
      $error = _('Multiple tags');
      break;
    }
  if( ! $tag )
    $error = _('No tag given');
  else {
    $found = false;
    foreach( $assmtTags as $t )
      if( strcasecmp( $e['tag'], $t ) == 0 ) {
	$found = true;
	break;
      }
    if( ! $found )
      $error = _('Unknown tag');
  }
  return HTML::td( array('title'=>$error), $tag );
}


function viewAllocations( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  $tag  = isset( $_REQUEST['tag' ] ) ? $_REQUEST['tag' ] : '';
  $view = isset( $_REQUEST['view'] ) ? $_REQUEST['view'] : 'reviewer';

  $nTags = 0;
  $rs = checked_mysql_query( "SELECT DISTINCT tag FROM Allocation WHERE assmtID=$assmtID" );
  $tagSelect = HTML::select( array('name'=>'tag',
				   'class'=>'form-control',
				   'onchange'=>"document.location.replace('$_SERVER[PHP_SELF]?action=viewAllocations&assmtID=$assmtID&cid=$cid&view=$view&tag=' + this.options[this.selectedIndex].value)") );
  while( $row = $rs->fetch_row() )
    if( ! empty( $row[0] ) ) {
      $nTags++;
      $tagSelect->pushContent( HTML::option( array('selected'=>$tag==$row[0],
						   'value'=> $row[0] ), $row[0] ) );
    }

  if( $nTags < 2 )
    $tagInput = '';
  else {
    $tagSelect->pushContent( HTML::option( array('selected'=>$tag=="", 'value'=>''), "View all" ) );
    $tagInput = HTML::form(array('method'=>'post',
				 'role' => 'form',
				 'class'=>'form-inline',
				 'action'=>"$_SERVER[PHP_SELF]?action=viewAllocations&assmtID=$assmtID&cid=$cid&view=$view" ),
			   FormGroupSmall('tag', _('Filter '), $tagSelect));
  }

  require_once 'Allocations.php';
  $allocs = new Allocations( $assmtID, 'all', $tag );

  $tagMsg = empty( $tag ) ? '' : HTML( _(' (tag '), HTML::q( $tag ), ')' );

  $buttons = ButtonToolbar();

  switch( $view ) {
  case 'reviewer':
    $buttons->pushContent(RedirectButton(_('View by author'),
					 "viewAllocations&assmtID=$assmtID&cid=$cid&view=submission&tag=" . rawurlencode($tag)));
    $table = $allocs->showByReviewer( $cid, false );
    break;
  case 'submission':
    $buttons->pushContent(RedirectButton(_('View by reviewer'),
					 "viewAllocations&assmtID=$assmtID&cid=$cid&view=reviewer&tag=" . rawurlencode($tag)));
    $table = $allocs->showByEssay( $cid, false );
    break;
  default:
    securityAlert( 'bad view index' );
  }

  $buttons->pushContent(Button(_('Download spreadsheet'), "downloadReviewing&assmtID=$assmtID&cid=$cid&view=$view&tag=$tag"));
  return HTML( assmtHeading( _('Reviewing'), $assmt ),
	       $tagInput,
	       $buttons,
	       br( ),
	       $table);
}

function downloadReviewing( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  $tag  = isset( $_REQUEST['tag' ] ) ? $_REQUEST['tag' ] : '';
  $view = isset( $_REQUEST['view'] ) ? $_REQUEST['view'] : 'reviewer';
  require_once 'Allocations.php';
  $allocs = new Allocations( $assmtID, 'all', $tag );

  header( 'Content-Type: text/csv');
  header( 'Content-Disposition: attachment; filename=reviewing-assmt' . $assmt['assmtID'] . '.csv;' );
  header( 'Connection: close');

  $nPer = (int)$assmt['nPerReviewer'];
  if( $nPer < 0 ) $nPer = 0;
  if( $view == 'reviewer' ) {
    $reviewers   = array_keys( $allocs->byReviewer );
    $reviewerIDs = array_combine( $reviewers, array_map( array($allocs, 'nameOfReviewer'), $reviewers));
    asort( $reviewerIDs );

    echo 'Reviewer' . str_repeat(',Author,Reviewed', $nPer) . "\n";
    foreach( $reviewerIDs as $r => $who ) {
      echo $who;
      $essays = array_keys( $allocs->byReviewer[ $r ] );
      foreach( $essays as $e ) {
	$a =& $allocs->byEssay[ $e ][ $r ];
	echo "," . $allocs->nameOfAuthor( $e )
	  . "," . (isset($a['lastMarked']) ? '1' : '0');
      }
      echo "\n";
    }
  } else {
    $essays   = array_keys( $allocs->byEssay );
    $authorIDs = array_combine( $essays, array_map( array($allocs, 'nameOfAuthor'), $essays));
    asort( $authorIDs );

    echo 'Author' . str_repeat(',Reviewer,Reviewed,Seen', $nPer) . "\n";
    foreach( $authorIDs as $e => $who ) {
      echo $who;
      $reviewers = array_keys( $allocs->byEssay[ $e ] );
      foreach( $reviewers as $r ) {
	$a =& $allocs->byEssay[ $e ][ $r ];
	echo "," . $allocs->nameOfReviewer( $r )
	  . "," . (isset($a['lastMarked']) ? '1' : '0')
	  . "," . (isset($a['lastSeen'])   ? '1' : '0');
      }
      echo "\n";
    }
  }
  exit;  
}


function showEssay( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  list( $author ) = checkREQUEST('_author' );
  $identity = userIdentity($author, $assmt, 'author');

  require_once 'BlockParser.php';

  maybeUpdateSortOrder( array('item') );

  ensureDBconnected( 'showEssay' );
  
  ini_set( 'memory_limit', '256M' );

  $markItems  = stringToItems( $assmt['markItems'] );
  $markGrades = stringToItems( $assmt['markGrades'] );
  parse_str($assmt['markLabels'] ?? '', $markLabels);
  $commentItems = commentLabels($assmt);

  $markKeys = array_keys( $markItems );
  $N = count( $markKeys );

  $html = HTML(HTML::h2(Sprintf_('Submission by %s for %s', $identity, $assmt['aname'])));

  require_once 'download.php';
  $html->pushContent( HTML::h3( _('Submission') ),
                      downloadables( $author, $identity, -1, $assmt, $cid ));
  
  $allocs = fetchAll( 'SELECT allocID, reviewer, lastViewed, lastMarked, lastResponse, marks'
		      . ' FROM Allocation'
		      . " WHERE assmtID = $assmtID AND author = $author");

  //- Marks
  if( count( $markItems ) > 0 ) {
    $html->pushContent( br( ), HTML::h3( _('Marks') ) );
    $table = table( );
    $tr = HTML::tr( HTML::th(_('Reviewer')) );
    foreach( $markKeys as $idx => $item ) {
      $outOf = isset( $markGrades[ $item ] ) ? '(/' . max($markGrades[ $item ]) . ')' : '';
      $th = HTML::th( itemCode( $idx, $N ), $outOf );
      if( isset( $markLabels[ $item ] ) )
	$th->setAttr('title', $markLabels[ $item ]);
      $tr->pushContent( $th );
    }
    $table->pushContent( $tr );

    foreach( $allocs as $a ) {
      parse_str($a['marks'] ?? '', $marks);
      $tr = HTML::tr( HTML::td( userIdentity( $a['reviewer'], $assmt, 'reviewer') ));
      foreach( $markKeys as $item ) {
	$m = $marks[ $item ] - 1;
	if( isset( $markGrades[ $item ] ) && isset( $markGrades[ $item ][ $m ] ) )
	  $mark = $markGrades[ $item ][ $m ];
	else
	  $mark = $marks[ $item ];
	$td = HTML::td( $mark );
	if( isset( $markLabels[ $item ] ) )
	  $td->setAttr('title', $markLabels[ $item ]);
	$tr->pushContent( $td );
      }
      $table->pushContent( $tr );
    }
    $html->pushContent( $table );
  }

  //- Comments
  $comments = array( );
  foreach( $allocs as $a ) {
    $rs2 = checked_mysql_query( 'SELECT item, comments, madeBy, whenMade FROM Comment'
				. " WHERE allocID = $a[allocID]" );
    while( $row = $rs2->fetch_assoc() ) {
      $row['comments'] = MaybeTransformText( $row['comments'] );
      $row['madeBy'] = userIdentity( $row['madeBy'], $assmt, 'reviewer' );
      $row['whenMade'] = formatTimestamp($row['whenMade']);
      $comments[] = $row;
    }
  }

  if( empty( $comments ) ) {
    if( ! empty( $assmt['commentItems'] ) )
      $html->pushContent( message(_('There are no comments')));
  } else {
    $html->pushContent( HTML::h3(_('Comments')));

    usort( $comments, 'sortOrder' );

    $table = table( );
    $headings = array( 'item' => 'Item', 'madeBy' => 'Reviewer', 'whenMade' => 'When made');
    $header = HTML::tr( );
    foreach( $headings as $key => $name )
      $header->pushContent(HTML::th(redirectButton($name,
						   "showEssay&cid=$cid&assmtID=$assmtID&author="
						   . rawurlencode($author)
						   . "&orderBy=$key") ) );

    $header->pushContent( HTML::th( _('Comment')));
    $table->pushContent( $header );

    foreach( $comments as $c ) {
      $tr = HTML::tr( );
      foreach( $headings as $key => $name )
        $tr->pushContent( HTML::td( $key == 'item' ? showCommentItem( $c, $commentItems ) : $c[ $key ] ) );
      $tr->pushContent( HTML::td( $c['comments'] ) );
      if( isset( $commentItems[ $c['item'] ] ) )
	$tr->setAttr( 'title', $commentItems[ $c['item'] ] );

      if( $c['madeBy'] == $_SESSION['userID'] )
        $tr->setAttr( 'class', "self-comment" );

      $table->pushContent( $tr );
    }
    $html->pushContent( $table );
  }

  //- Review files
  if( isset( $assmt['nReviewFiles'] ) && $assmt['nReviewFiles'] > 0 ) {
    $table = table( HTML::tr( HTML::th(_('Reviewer')), HTML::th(_('Uploaded review'))) );
    foreach( $allocs as $a ) {
      $tr = HTML::tr(HTML::td(userIdentity($a['reviewer'], $assmt, 'reviewer')));
      $fs = checked_mysql_query('select reviewFileID, description, item, madeBy from ReviewFile'
				. " where allocID=$a[allocID]"
				. ' order by whenUploaded desc');
      $td = HTML::td();
      while( $row = $fs->fetch_assoc() ) {
	$r = getReviewFileIndex($row['reviewFileID'], $row['item'], userIdentity($row['madeBy'], $assmt, 'author'));
	$crc = sprintf( '%u', crc32( "$_SESSION[userID]:$reviewFile[reviewFileID]" ) );
	$desc = trim( $row['description'] );
	$td->pushContent( callback_url( $desc != "" ? $desc : '(untitled)',
					"showReviewFile&cid=$cid&r=$r&oid=$crc" ));
      }
      if( ! $td->isEmpty( ) ) {
	$tr->pushContent( $td );
	$table->pushContent( $tr );
      }
    }
    $html->pushContent( HTML::h3( _('Review files') ), $table );
  }
  
  return $html;
}



function showReviewer( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  list( $reviewer ) = checkREQUEST('_reviewer' );
  $identity = userIdentity( $reviewer, $assmt, 'reviewer' );

  require_once 'BlockParser.php';
  require_once 'download.php';

  maybeUpdateSortOrder( array('item') );

  ensureDBconnected( 'showReviewer' );
  
  $markItems  = stringToItems( $assmt['markItems'] );
  $markGrades = stringToItems( $assmt['markGrades'] );
  parse_str($assmt['markLabels'] ?? '', $markLabels);
  $commentItems = commentLabels($assmt);

  $markKeys = array_keys( $markItems );
  $N = count( $markKeys );

  $html = HTML( HTML::h2( Sprintf_( 'Reviews by %s for %s',
				    $identity, $assmt['aname'])));

  $allocs = fetchAll( 'SELECT allocID, author, lastViewed, lastMarked, lastResponse, marks, locked'
		      . ' FROM Allocation'
		      . " WHERE assmtID = $assmtID AND reviewer = $reviewer order by allocID");

  $html->pushContent( HTML::h3( _('The following submissions were allocated for review')));
  $dl = HTML::dl( );
  $locked = false;
  foreach( $allocs as $allocIdx => $a ) {
    $who = userIdentity( $a['author'], $assmt, 'author' );
    $dl->pushContent( HTML::dt( $who ),
		      HTML::dd( downloadables( $a['author'], $who, $allocIdx + 1, $assmt, $cid )));
    if( $a['locked'] )
      $locked = true;
  }
  $html->pushContent( $dl );
  
  if( $assmt['allowLocking'] )
    if( $locked )
      $html->pushContent( HTML::p( _('These reviews have been locked. '),
				   callback_url( Sprintf_('Click here to <b>unlock</b> them'), "toggleLockReviews&cid=$cid&assmtID=$assmtID&reviewer=$reviewer&lock=0")));
    else
      $html->pushContent( HTML::p( _('These reviews have not been locked. '),
				   callback_url( Sprintf_('Click here to <b>lock</b> them'), "toggleLockReviews&cid=$cid&assmtID=$assmtID&reviewer=$reviewer&lock=1")));

  if( count( $markItems ) > 0 ) {
    $html->pushContent( HTML::h3( _('Marks')));
    $table = table( );
    $tr = HTML::tr( HTML::th(_('Author')));
    foreach( $markKeys as $idx => $item ) {
      $outOf = isset( $markGrades[ $item ] ) ? '(/' . max($markGrades[ $item ]) . ')' : '';
      $th = HTML::th( itemCode( $idx, $N ), $outOf );
      if( isset( $markLabels[ $item ] ) )
	$th->setAttr('title', $markLabels[ $item ]);
      $tr->pushContent( $th );
    }
    $table->pushContent( $tr );
    
    foreach( $allocs as $a ) {
      parse_str($a['marks'] ?? '', $marks);
      $tr = HTML::tr( HTML::td( userIdentity( $a['author'], $assmt, 'author' )));
      foreach( $markKeys as $item ) {
	$m = $marks[ $item ] - 1;
	if( isset( $markGrades[ $item ] ) && isset( $markGrades[ $item ][ $m ] ) )
	  $mark = $markGrades[ $item ][ $m ];
	else
	  $mark = $marks[ $item ];
	$td = HTML::td( $mark );
	if( isset( $markLabels[ $item ] ) )
	  $td->setAttr('title', $markLabels[ $item ]);
	$tr->pushContent( $td );
      }
      $table->pushContent( $tr );
    }
    $html->pushContent( $table );
  }

  $comments = array( );
  foreach( $allocs as $a ) {
    $rs2 = checked_mysql_query('select item, comments, whenMade, author'
			       . ' from Comment c'
			       . ' left join Allocation l ON l.allocID=c.allocID'
			       . " where c.allocID=$a[allocID]");
    while ($row = $rs2->fetch_assoc()) {
      $row['comments'] = MaybeTransformText($row['comments']);
      $row['author'] = userIdentity($row['author'], $assmt, 'author');
      $row['whenMade'] = formatTimestamp($row['whenMade']);
      $comments[] = $row;
    }
  }


  if( empty( $comments ) ) {
    if( ! empty( $assmt['commentItems'] ) )
      $html->pushContent( message(_('There are no comments')));
  } else {
    $html->pushContent( HTML::h3(_('Comments')));

    usort( $comments, 'sortOrder' );

    $table = table( );

    $headings = array('item' => 'Item', /*'madeBy',*/ 'author'=>'Author', 'whenMade'=>'When made');
    $header = HTML::tr( );
    foreach( $headings as $key => $name )
      $header->pushContent(HTML::th(redirectButton($name,
						   "showReviewer&cid=$cid&assmtID=$assmtID&reviewer="
						   . rawurlencode($reviewer)
						   . "&orderBy=$key" )));

    $header->pushContent( HTML::th( _('Comment')));
    $table->pushContent( $header );

    foreach( $comments as $c ) {
      $tr = HTML::tr( );
      foreach( $headings as $key => $name )
        $tr->pushContent( HTML::td( $key == 'item' ? showCommentItem( $c, $commentItems ) : $c[ $key ] ));
      $tr->pushContent( HTML::td( $c['comments'] ) );

      if( isset( $commentItems[ $c['item'] ] ) )
	$tr->setAttr( 'title', $commentItems[ $c['item'] ] );

      if( $c['madeBy'] == $reviewer )
        $tr->setAttr( 'class', 'self-comment' );

      $table->pushContent( $tr );
    }
    $html->pushContent( $table );
  }

  //- Review files
  if( isset( $assmt['nReviewFiles'] ) && $assmt['nReviewFiles'] > 0 ) {
    $table = table( HTML::tr( HTML::th(_('Author')), HTML::th(_('Uploaded review'))) );
    foreach( $allocs as $a ) {
      $tr = HTML::tr( HTML::td( userIdentity( $a['author'], $assmt, 'reviewer') ));
      $fs = checked_mysql_query( 'SELECT reviewFileID, description, item, madeBy FROM ReviewFile'
				 . " WHERE allocID=$a[allocID]"
				 . ' ORDER BY whenUploaded DESC' );
      $td = HTML::td( );
      while( $row = $fs->fetch_assoc() ) {
	$r = getReviewFileIndex($row['reviewFileID'], $row['item'], userIdentity($row['madeBy'], $assmt, 'author'));
	$crc = sprintf( '%u', crc32( "$_SESSION[userID]:$reviewFile[reviewFileID]" ) );
	$desc = trim( $row['description'] );
	$td->pushContent( callback_url( $desc != "" ? $desc : '(untitled)',
					"showReviewFile&cid=$cid&r=$r&oid=$crc" ));
      }
      if( ! $td->isEmpty( ) ) {
	$tr->pushContent( $td );
	$table->pushContent( $tr );
      }
    }
    $html->pushContent( HTML::h3( _('Review files') ), $table );
  }

  return HTML(updateCommentItemJS( $cid ), $html);
}


function toggleLockReviews( ) {
  list( $cid, $assmtID, $reviewer, $lock ) = checkREQUEST( '_cid', '_assmtID', '_reviewer', '_lock' );
  $lock = $lock == 1 ? 1 : 0;
  checked_mysql_query( 'UPDATE Allocation l LEFT JOIN Assignment a ON a.assmtID=l.assmtID'
		       . " SET locked = $lock"
		       . ' WHERE courseID = ' . cidToClassId( $cid )
		       . " AND l.assmtID = $assmtID AND reviewer=$reviewer" );
  redirect( 'showReviewer', "cid=$cid&assmtID=$assmtID&reviewer=$reviewer" );
}

function showCommentItem( $comment, $allItems ) {
  if( isset( $comment['allocID'] ) )
    if( ! in_array( $comment['item'], $allItems ) ) {
      $select = HTML::select( array('onchange'=>"updateCommentItem($comment[allocID], this)") );
      $select->pushContent( HTML::option( array('value'=>$comment['item'],
                                                'selected'=>true),
                                          "*$comment[item]" ) );
      foreach( $allItems as $item )
        $select->pushContent( HTML::option( array('value'=>$item,
                                                  'selected'=> $item == $comment['item'] ),
                                            $item));
      return $select;
    }
  return is_numeric( $comment['item'] ) ? $comment['item']+1 : $comment['item'];
}

function updateCommentItemJS( $cid ) {
  return JavaScript('
var xmlHttp = null;
try { xmlHttp = new XMLHttpRequest(); }
catch (e) { try { xmlHttp = new ActiveXObject("Msxml2.XMLHTTP"); }
catch (e) { xmlHttp = new ActiveXObject("Microsoft.XMLHTTP"); } }
function updateCommentItem( allocID, sel ) {
  if( xmlHttp != null && sel.selectedIndex != -1 ) {
    var item = sel.options[sel.selectedIndex].value;
    xmlHttp.open( "GET", "' . $_SERVER['PHP_SELF'] . '?action=updateCommentItem&cid=' . $cid
		    . '&allocID=" + allocID + "&item=" + encodeURIComponent(item),
                  true );
    xmlHttp.send( null );
  }
}
');
}


function updateCommentItem( ) {
  if( isset( $_REQUEST['cid'] ) && isset( $_REQUEST['allocID'] ) && isset( $_REQUEST['seq'] ) && isset( $_REQUEST['item'] ) )
    checked_mysql_query( 'UPDATE Comment'
			 . ' INNER JOIN Allocation ON Comment.allocID = Allocation.allocID'
			 . ' INNER JOIN Assignment ON Assignment.assmtID = Allocation.assmtID'
			 . ' SET item = ' . quote_smart( $_REQUEST['item'] )
                         . ' WHERE allocID = ' . (int)$_REQUEST['allocID']
			 . ' AND Assignment.courseID = ' . cidToClassId( $_REQUEST['cid'] ) );
}



function showIndividual( ) {
  list( $allocID, $cid ) = checkREQUEST( '_allocID', '_cid' );

  require_once 'BlockParser.php';

  $headings = array('item', 'madeBy', 'whenMade');
  maybeUpdateSortOrder( array('item') );

  ensureDBconnected( 'showIndividual' );

  $alloc = fetchOne('select assmtID, reviewer, author, a.userID AS author, r.userID AS reviewer, lastViewed, lastMarked, lastResponse, marks'
		    . ' from Allocation'
		    . " where allocID = $allocID");
  if( ! $alloc )
    return warning( 'No such allocation.' );

  $assmtID = $alloc['assmtID'];
  
  $assmt = fetchOne( 'SELECT '
		     . ' assmtID,'
		     . ' basepath,'
		     . ' anonymousReview,'
		     . ' isReviewsFor,'
		     . ' markItems,'
		     . ' commentItems '
		     . " FROM Assignment WHERE assmtID = $assmtID" );
  if( ! $alloc )
    return warning( 'Unable to find the associated assignment.' );

  parse_str($assmt['markItems'] ?? '', $markItems);

  $markKeys    = array_keys( $markItems );
  $N = count( $markKeys );

  $html = HTML(
    HTML::h2(
      'Reviewer: ', userIdentity($alloc['reviewer'], $assmt, 'reviewer'),
      ', author: ', userIdentity($alloc['author'], $assmt, 'author')));
  
  require_once 'download.php';

  $html->pushContent(HTML::h3('Submission'),
		     downloadables($alloc['author'], userIdentity($alloc['author'], $assmt, 'author'), -1, $assmt, $cid));

  if( count( $markItems ) > 0 ) {
    parse_str($alloc['marks'] ?? '', $marks);
    $markHeader = HTML::tr( );
    foreach( $markKeys as $idx => $item ) {
      $outOf = isset( $markGrades[ $item ] ) ? '(/' . max($markGrades[ $item ]) . ')' : '';
      $markHeader->pushContent( HTML::th( itemCode( $idx, $N ), $outOf ) );
    }
    $markRow = HTML::tr( );
    foreach( $markKeys as $item )
      $markRow->pushContent( HTML::td( isset( $marks[ $item ] ) ? $marks[ $item ] : '-' ) );
    $html->pushContent( br( ), HTML::h3( 'Marks' ),
			table( $markHeader, $markRow ));
  }

  $html->pushContent( HTML::p('Last viewed: ', formatTimestamp($alloc['lastViewed'])));
  $html->pushContent( HTML::p('Last marked: ', formatTimestamp($alloc['lastMarked'])));

  $rs2 = checked_mysql_query( 'SELECT item, comments, madeBy, whenMade'
                              . " FROM Comment WHERE allocID = $allocID");
  $comments = array( );
  while( $row = $rs2->fetch_assoc() ) {
    $row['comments'] = MaybeTransformText( $row['comments'] );
    $row['whenMade'] = formatTimestamp($row['whenMade']);
    $comments[] = $row;
  }

  if( empty( $comments ) )
    $html->pushContent( message(_('There are no comments')) );
  else {
    $html->pushContent( HTML::h3('Comments') );

    usort( $comments, 'sortOrder' );

    $table = table( );
    $header = HTML::tr( );
    foreach( $headings as $key )
      $header->pushContent( HTML::th($key) ); // callback_url( $key, "showIndividual&cid=$cid&allocID=$allocID&orderBy=$key" )));
    $header->pushContent( HTML::th( 'Comment' ) );
    $table->pushContent( $header );

    foreach( $comments as $c ) {
      $tr = HTML::tr( );
      foreach( $headings as $key )
        $tr->pushContent( HTML::td( $c[ $key ] ) );
      $tr->pushContent( HTML::td( $c['comments'] ) );

      if( $c['madeBy'] == $_SESSION['userID'] )
        $tr->setAttr( 'class', "self-comment" );

      $table->pushContent( $tr );
    }
    $html->pushContent( $table );
  }

  $legend = table( );
  foreach( $markKeys as $idx => $item )
    $legend->pushContent( HTML::tr( HTML::th( itemCode( $idx, $N ) ),
                                    HTML::td( $item ) ) );
  $html->pushContent( HTML::h3('Item legend' ), $legend );
  
  return $html;
}


function viewAllFeedback( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  $anon = isset( $_REQUEST['anon'] ) ? 1 : 0;

  if( isset( $_REQUEST['byAuthor'] ) ) {
    $action = 'authorFeedback';
    $user = 'author';
    $heading = assmtHeading( _('Reviews for'), $assmt );
    $byOther = RedirectButton(_('Sort by reviewer'),
			      "viewAllFeedback&assmtID=$assmtID&cid=$cid&byReviewer" . ($anon?'&anon=1':''));
  } else {
    $action = 'reviewerFeedback';
    $user = 'reviewer';
    $heading = assmtHeading( _('Reviews for'), $assmt );
    $byOther = RedirectButton(_('Sort by author'),
			      "viewAllFeedback&assmtID=$assmtID&cid=$cid&byAuthor" . ($anon?'&anon=1':''));
  }

  if( findCommand('feedbackDocx') )
    $generateDocx = RedirectButton( _('Download as MSWord'), "feedbackDocx&assmtID=$assmtID&cid=$cid&$user" );
  else
    $generateDocx = '';

  if( findCommand('feedbackXlsx') )
    $generateXlsx = RedirectButton( _('Download as Excel'), "feedbackXlsx&assmtID=$assmtID&cid=$cid&$user" );
  else
    $generateXlsx = '';

  //- Each review is retrieved by an AJAX call, and re-reading the Assignment is potentially costly.
  //- We avoid that by storing the current assignment in the session.
  $_SESSION['current-assmt'] = $assmt;

  if( $anon )
    $anonButton = RedirectButton(_('View names'),
				 "viewAllFeedback&assmtID=$assmtID&cid=$cid&"
				 . (isset($_REQUEST['byAuthor'])?'byAuthor':'byReviewer'));
  else
    $anonButton = RedirectButton(_('Anonymise names'),
				 "viewAllFeedback&assmtID=$assmtID&cid=$cid&anon=1&"
				 . (isset($_REQUEST['byAuthor'])?'byAuthor':'byReviewer'));

  $userIDs = fetchAll( "SELECT DISTINCT $user FROM Allocation"
		     . " WHERE assmtID = $assmtID"
		     . ' AND lastMarked IS NOT NULL',
		     $user );
  if( ! $anon ) {
    require_once 'Allocations.php';
    $allocs = new Allocations($assmtID);
    $names = array();
    foreach( $userIDs as $uid )
      $names[$uid] = $user == 'reviewer' ? $allocs->nameOfReviewer($uid) : $allocs->nameOfAuthor($uid);
  } else {
    $names = array( );
    $prefix = $user == 'author' ? 'A' : 'R';
    foreach( $userIDs as $uid )
      $names[$uid] = AnonymiseAuthor($anonMap, $uid, $prefix);
  }

  asort($names);
  extraHeader('bootstrap.min.js', 'js');
  extraHeader('$(".collapse").each(
	function() {
	    $(this).load("aropa.php",
			 {action: "' . $action . '",
			  assmtID: ' . $assmtID . ',
			  cid: ' . $cid . ',
			  anon: ' . $anon . ',
			  user: $(this).attr("id").substring(2)});
	});
', 'onload');

  $rows = HTML::div(array('id'=>'feedback',
			  'class'=>'panel-group'));
  foreach ($names as $uid => $name)
    $rows->pushContent(HTML::div(array('class'=>'panel panel-default'),
				 HTML::div(array('class'=>'panel-heading'),
					   HTML::h4(array('class'=>'panel-title'),
						    HTML::a(array('role'=>'button',
								  'data-toggle'=>'collapse',
								  'data-parent'=>'#feedback',
								  'href'=>"#u-$uid"),
							    $name))),
				 HTML::div(array('id'=>"u-$uid",
						 'class'=>'panel-collapse collapse'))));
  extraHeader('$("#showAll").on("click", function () {$("#feedback .panel-collapse").collapse("show");})',
	      'onload');
  extraHeader('$("#hideAll").on("click", function () {$("#feedback .panel-collapse").collapse("hide");})',
	      'onload');
  return HTML($heading,
	      ButtonToolbar($byOther,
			    $anonButton,
			    HTML::button(array('id'=>'showAll',
					       'class' => 'btn btn-default',
					       'role'=>'button'),
					 _('Show all')),
			    HTML::button(array('id'=>'hideAll',
					       'class' => 'btn btn-default',
					       'role'=>'button'),
					 _('Collapse all')),
			    $generateDocx,
			    $generateXlsx),
	      $rows);
}


//- All feedback written by $_REQUEST['author']
function authorFeedback( ) {
  list( $assmtID, $cid, $author, $anon ) = checkREQUEST('_assmtID', '_cid', 'user', '?_anon');

  if (!isset($_SESSION['current-assmt'])
      || $_SESSION['current-assmt']['assmtID'] != $assmtID
      || $_SESSION['current-assmt']['courseID'] != $courseID
      ) {
    list($assmt, $assmtID, $cid, $courseID) = selectAssmt();
    $_SESSION['current-assmt'] = $assmt;
  } else
    $assmt = $_SESSION['current-assmt'];

  if ($anon && isset( $_SESSION['anonRMap']))
    $anonMap = $_SESSION['anonRMap'];
  else
    $anonMap = array();

  $div = HTML::div(array('class'=>'panel-body'));
  if ($assmt) {
    require_once 'users.php';

    $markItems = stringToItems($assmt['markItems']);
    $markGrades = stringToItems($assmt['markGrades']);
    parse_str($assmt['markLabels'] ?? '', $markLabels);
    $commentsItems = commentLabels($assmt);
    
    if ($assmt['authorsAre'] == 'group' || $assmt['reviewersAre'] == 'group') {
      require_once 'Groups.php';
      $groups = new Groups($assmtID);
    }

    require_once 'BlockParser.php';
    $cs = checked_mysql_query('select allocID, reviewer, marks'
			      . ' from Allocation'
			      . " where assmtID = $assmtID"
			      . ' and lastMarked is not null'
			      . " and author = $author");
    $currentR = null;
    while ($row = $cs->fetch_assoc()) {
      $who = $row['reviewer'];
      if ($who != $currentR) {
	$currentR = $who;
	if ($who < 0 && isset($groups))
	  $reviewerName = $anon ? AnonymiseAuthor($anonMap, $who, "Group-", false) : $groups->groupIDtoGname[$who];
	else
	  $reviewerName = $anon ? AnonymiseAuthor($anonMap, $who, "R", false) : userIdentity($row['reviewer'], $assmt, 'reviewer');
	
	$div->pushContent(HTML::h4(Sprintf_('Feedback from %s', $reviewerName)));
      }

      $div->pushContent(markItemDiv($markItems, $markGrades, $markLabels, $row['marks']));
      $div->pushContent(commentItemDiv($commentsItems, $row['allocID']));
      $ul = reviewFileUL($assmt, $cid, $row['allocID']);
      if (!$ul->isEmpty())
	$div->pushContent(HTML::h5(_('Review files')), $ul);
    }
  }

  if ($anon) $_SESSION['anonRMap'] = $anonMap;
  PrintXML($div);
  exit;
}

// All reviews written by $reviewer
function reviewerFeedback( ) {
  list($assmtID, $cid, $reviewer, $anon) = checkREQUEST('_assmtID', '_cid', 'user', '?_anon');

  if (! isset($_SESSION['current-assmt'])
      || $_SESSION['current-assmt']['assmtID'] != $assmtID
      || $_SESSION['current-assmt']['courseID'] != $courseID
      ) {
    list($assmt, $assmtID, $cid, $courseID) = selectAssmt();
    $_SESSION['current-assmt'] = $assmt;
  } else
    $assmt = $_SESSION['current-assmt'];
  
  if ($anon && isset($_SESSION['anonAMap']))
    $anonMap = $_SESSION['anonAMap'];
  else
    $anonMap = array();

  $div = HTML::div(array('class'=>'panel-body'));
  if ($assmt) {
    require_once 'users.php';

    $markItems = stringToItems($assmt['markItems']);
    $markGrades = stringToItems($assmt['markGrades']);
    parse_str($assmt['markLabels'] ?? '', $markLabels);
    $commentsItems = commentLabels($assmt);
    
    if( $assmt['authorsAre'] == 'group' || $assmt['reviewersAre'] == 'group' ) {
      require_once 'Groups.php';
      $groups = new Groups( $assmtID );
    }
    
    if( $assmt['reviewersAre'] == 'group' && isset( $groups->userToGroup[ $reviewer ] ) )
      $reviewer = $groups->userToGroup[ $reviewer ];
    
    require_once 'BlockParser.php';
    $cs = checked_mysql_query('select allocID, author, marks'
			      . ' from Allocation'
			      . " where assmtID = $assmtID"
			      . ' and lastMarked is not null'
			      . " and reviewer = $reviewer");
    $currentA = null;
    while ($row = $cs->fetch_assoc()) {
      $who = $row['author'];
      if ($who != $currentA) {
	$currentA = $who;
	if ($who < 0 && isset($groups))
	  $authorName = $anon ? AnonymiseAuthor($anonMap, $who, "Group-", false) : $groups->groupIDtoGname[$who];
	else
	  $authorName = $anon ? AnonymiseAuthor($anonMap, $who, "A", false) : userIdentity($row['author'], $assmt, 'author');

	$div->pushContent(HTML::h4(Sprintf_('Review of submission by author %s', $authorName)));
      }
      
      $div->pushContent(markItemDiv($markItems, $markGrades, $markLabels, $row['marks']));
      $div->pushContent(commentItemDiv($commentsItems, $row['allocID']));
      $ul = reviewFileUL($assmt, $cid, $row['allocID']);
      if (!$ul->isEmpty())
	$div->pushContent(HTML::h5(_('Review files')), $ul);
    }
  }
  
  if ($anon) $_SESSION['anonAMap'] = $anonMap;
  PrintXML($div);
  exit;
}

function markItemDiv($markItems, $markGrades, $markLabels, $markStr) {
  parse_str($markStr ?? '', $marks);
  $allMarks = HTML::div(array('class'=>'markbox'));
  foreach (array_keys($markItems) as $item) {
    if (!isset($marks[$item]))
      $mark = 'na';
    else {
      $m = $marks[$item];
      if (isset($markGrades[$item]) && isset($markGrades[$item][$m - 1]))
	$mark = $markGrades[$item][$m - 1];
      else
	$mark = $m;
    }
    
    if (isset($markLabels[$item])) $item = $markLabels[$item];
    $allMarks->pushContent("$item: $mark", HTML::br());
  }
  
  return $allMarks;
}

function commentItemDiv($commentItems, $allocID) {
  $div = HTML::div();
  if (!empty($commentItems)) {
    $rs = checked_mysql_query("select item, comments from Comment where allocID = $allocID order by length(item), item");
    while ($crow = $rs->fetch_assoc()) {
      if (is_numeric($crow['item'])) {
	if (!empty($commentItems[$crow['item']]))
	  $item = "(" . ($crow['item'] + 1) . ") " . $commentItems[$crow['item']];
	else
	  $item = Sprintf_('Comment %d', $crow['item'] + 1);
      } else
	$item = $crow['item'];
      $div->pushContent(HTML::p(HTML::span(array('class'=>'itemFB'), $item),
				HTML::div(array('class'=>'commentFB'),
					  MaybeTransformText($crow['comments']))));
    }
  }
  
  return $div;
}

function reviewFileUL($assmt, $cid, $allocID) {
  $ul = HTML::ul();
  if (isset($assmt['nReviewFiles']) && $assmt['nReviewFiles'] > 0) {
    $rfs = checked_mysql_query('select reviewFileID, description, item, madeBy'
			       . ' from ReviewFile'
			       . " where allocID = $allocID"
			       . ' order by item asc');
    while ($rf = $rfs->fetch_assoc()) {
      $r = getReviewFileIndex($rf['reviewFileID'], $rf['item'], userIdentity($rf['madeBy'], $assmt, 'author'));
      $crc = sprintf('%u', crc32( "$_SESSION[userID]:$rf[reviewFileID]"));
      $desc = trim($rf['description']);
      $ul->pushContent(HTML::li(callback_url($desc != "" ? $desc : '(untitled)', "showReviewFile&cid=$cid&r=$r&oid=$crc")));
    }
  }
  
  return $ul;
}

function feedbackDocx( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  require_once 'ToDocx.php';
  require_once 'Allocations.php';
  $allocs = new Allocations( $assmtID );

  if( isset($_REQUEST['author']) )
    $stylesheet = 'feedback-by-author.xslt';
  else
    $stylesheet = 'feedback-by-reviewer.xslt';
  
  $tmpFile = WriteReviewsToDocx($assmt, $cid, $allocs, null, null, 'feedback-template.docx', $stylesheet );

  while( ob_end_clean( ) )
    //- Discard any earlier HTML or other headers
    ;

  $fp = fopen($tmpFile, 'rb');
  header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
  header("Content-Disposition: attachment; filename=Aropa-feedback-$assmtID.docx;" );
  fpassthru($fp);
  fclose($fp);
  unlink($tmpFile);
  exit;
}

function feedbackXlsx() {
  list ($assmt, $assmtID, $cid) = selectAssmt();
  if (!$assmt)
    return missingAssmt();

  require_once 'ToXlsx.php';
  require_once 'Allocations.php';

  if (!empty($assmt['isReviewsFor'])) {
    if ($assmt['authorsAre'] == 'review')
      $left = 'Reviewer/Author';
    else
      $left = 'Reviewer';
    $right = 'Review marker';
  } else {
    $left = 'Author';
    $right = 'Reviewer';
  }
  
  $responses = new Responses($assmt['aname'] . "; " . className($cid), $assmt, $left, $right);
  $allocs = new Allocations($assmtID, 'fixedOnly');
  foreach ($allocs->allocations as $alloc) {
    $r = $responses->newResponse(
      array(
	'author' => $allocs->nameOfAuthor($alloc['author']),
	'reviewer' => $allocs->nameOfReviewer($alloc['reviewer']),
	'time' => $alloc['lastMarked']));
    $responses->addMarks($r, $alloc['marks']);
    $cs = checked_mysql_query("select item, comments from Comment where allocID = $alloc[allocID] and madeBy = $alloc[reviewer]");
    while ($crow = $cs->fetch_assoc())
      $responses->addComment($r, $crow['item'], $crow['comments']);
  }
  
  toXlsx($responses->xml, 'assmt-results.xslt',  "Aropa-assignment-$assmtID.xlsx");
}