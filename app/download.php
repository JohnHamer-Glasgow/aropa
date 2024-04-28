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

//- downloadables returns a list of the documents in the submission.
//- Each entry in the list is either the inline text of the submission
//- or a link that performs the actual download.
//-
//- $allocIdx - optional (pass in -1 if the downloads are not being
//- requested by a reviewer).  This is a small integer meaningful to the
//- reviewer (i.e., their first review is 1, then 2, etc.)  Several
//- download items may share the same $allocIdx.
//-

function downloadables( $author, $identity, $allocIdx, $assmt, $cid ) {
  $anonymous
    =  ! empty( $author )
    && ( ! isset( $assmt['anonymousReview'] ) || $assmt['anonymousReview'] == 1 );

  //- Does anyone use ADB with Aropa any more?
  $path = false;
  if( ! empty( $assmt['basepath'] ) ) {
    $file = expand_latest( str_replace( '<<author>>', $identity, $assmt['basepath'] ) );
    if( file_exists( $file ) )
      $path = $file;
  }

  if( ! empty( $assmt['isReviewsFor'] ) )
    $div = downloadablesReviews( $assmt['authorsAre'], $author, $identity, $allocIdx, $assmt['isReviewsFor'], $assmt, $anonymous );
  else if( $path !== false ) {
    $ul = HTML::ul( ); 
    downloadablesFile( $ul, array($path), $author, $identity, $allocIdx, $assmt['assmtID'],
		       $assmt['aname'], $anonymous, $cid );
    $div = HTML::div( array('class'=>'download-list'), $ul );
  } else {
    $ul = HTML::ul( );
    downloadablesEssay( $ul, $author, $assmt, $identity, $allocIdx, $anonymous, $cid );
    $div = HTML::div( array('class'=>'download-list'), $ul );
  }

  if( $div->isEmpty( ) ) {
    updateLastViewed( $author, $assmt['assmtID'] );
    return warning( _('There are no documents available to download') );
  } else
    return $div;
}

function downloadablesEssay( &$ul, $author, $assmt, $identity, $allocIdx, $anonymous, $cid ) {
  if ($author < 0) {
    //- The "author" is a groupID
    $realIDs = fetchAll('select userID from GroupUser'
			. " where assmtID = $assmt[assmtID] and groupID = -$author",
			'userID');
  } else if ($assmt['authorsAre'] == 'group')
    $realIDs = $assmt['author-group-members'];
  else
    $realIDs = array($author);
  
  if (empty($realIDs))
    return;

  $rs = checked_mysql_query( 'SELECT essayID, IF(extn="inline-text",essay,"") as essay, tag, author, compressed, reqIndex, url, extn, description, whenUploaded, isPlaceholder FROM Essay'
			     . ' WHERE author IN (' . join(',', $realIDs) . ')'
                             . " AND assmtID = $assmt[assmtID]"
			     . ' AND ( essay IS NOT NULL OR url IS NOT NULL )'
			     . ' ORDER BY reqIndex, isPlaceholder asc');

  $idList = array( );
  while( $row = $rs->fetch_assoc() ) {
    if (!empty($idList) && $row['isPlaceholder'] )
      continue;

    $extn = $row['extn'] == 'inline-text' ? '.html' : fileExtension($row['description']);
    $name = "document-$allocIdx" . numberToAlpha($row['reqIndex']) . $extn;

    $id = $row['essayID'];
    recordDownloadInSession( array( 'essay'   =>$id,
                                    'assmtID' =>$assmt['assmtID'],
				    'url'     =>$row['url'],
                                    'author'  =>$author,
				    'identity'=>$identity,
                                    'name'    =>$name,
                                    'extn'    =>$row['extn'],
                                    'allocIdx'=>$allocIdx,
                                    'anon'    =>$anonymous ));
    $idList[] = $id;
    if( $row['extn'] == 'inline-text' ) {
      require_once 'XMLcleaner.php';
      $text = $row['compressed'] ? gzuncompress( $row['essay'] ) : $row['essay'];
      $item = HTML::div( array('class'=>'download-text'),
			 HTML::raw( cleanupHTML( $text )));
      updateLastViewed( $author, $assmt['assmtID'] );
    } else
      $item = formButton( _('Download'), "download&essay=$id&cid=$cid");

    $ul->pushContent( HTML::li( HTML::span( array('class'=>'download-description'),
					    "(", (int)$row['reqIndex'],  ") ", $name),
				empty( $row['tag'] ) ? '' : Sprintf_(', tagged <q>%s</q>', $row['tag'] ),
				' ', $item));
  }

  if( count( $idList ) > 1 ) {
    $i = recordDownloadInSession( array( 'essays'  =>$idList,
					 'assmtID' =>$assmt['assmtID'],
					 'author'  =>$author,
					 'identity'=>$identity,
					 'allocIdx'=>$allocIdx,
					 'name'    =>$assmt['aname'],
					 'anon'    =>$anonymous ));
    //- Generate a unique code for the download URL, to defeat any
    //- HTTP caches
    $crc = sprintf( '%u', crc32( $author . filename_safe( $assmt['aname'] ) . $allocIdx ));
    $ul->pushContent( HTML::li( callback_url('Download a ZIP archive of these files',
					     "download&download=$i&oid=$crc&cid=$cid",
					     array('class'=>'download-zip'))));
  }
}


function downloadablesFile( &$ul, $files, $author, $identity, $allocIdx, $assmtID, $assmtName, $anonymous, $cid ) {
  if( $files === false )
    //- glob() return false if there are no matches
    return;

  foreach( $files as $f ) {
    $f = rtrim( $f, '/' );
    if( $f == '.' || $f == '..' )
      continue;

    $fname = basename( $f );
    if( $anonymous )
      $fname = preg_replace( "/" . preg_quote( $identity ) . "/i", _('Author'), $fname );

    $i = recordDownloadInSession( array( 'file'    =>$f,
					 'assmtID' =>$assmtID,
					 'name'    =>$assmtName,
					 'allocIdx'=>$allocIdx,
                                         'author'  =>$author,
					 'identity'=>$identity,
					 'anon'    =>$anonymous ));
    //- Generate a unique code for the download URL, to defeat any
    //- HTTP caches
    $crc = sprintf( '%u', crc32( $f . $author . $assmtName . $allocIdx ));

    if( is_dir( $f ) )
      downloadablesFile( $ul,
			 glob( "$f/*" ),
			 $author,
			 $identity,
			 $allocIdx,
			 $assmtID,
			 $assmtName,
			 $anonymous );
    else
      $ul->pushContent( HTML::li( HTML::div( array('class'=>'download-description'), $fname),
				  HTML::div( array('class'=>'download-link'),
					     callback_url( _('Download'), "download&download=$i&oid=$crc&cid=$cid" ))));
  }
}



function downloadablesReviews( $authorsAre, $author, $identity, $allocIdx, $isReviewsFor, $assmt, $anonymous ) {
  if( $authorsAre == 'reviewer' )
    return downloadAllReviews( $isReviewsFor, $assmt['assmtID'], $author, $identity, $anonymous );
  else
    return downloadSingleReview( $assmt, $author, $identity, $allocIdx, $anonymous );
}


function recordDownloadInSession( $fid ) {
  if( ! isset( $_SESSION['download'] ) )
    $_SESSION['download'] = array( );

  $i = array_search( $fid, $_SESSION['download'] );
  if( $i === false ) {
    $i = count($_SESSION['download']);
    $_SESSION['download'][ $i ] = $fid;
  }

  return $i;
}


//- download: ADMIN and REVIEWER entry point.
//- require $_REQUEST['download'], index into $_SESSION['download']
//-     or  $_REQUEST['essay'], index into the Essay table
//- The latter was added in 2005-10-07 in an attempt to control
//- over-enthusiastic caching behaviour: the URL
//- https://.../?action=download&download=0 is not a unique code;
//- e.g., it can change between sessions, meaning a different thing in
//- each.  The Aropa system formerly used nocache, but Internet
//- Explorer requires downloads sent over a https link to be cachable
//- (I don't know why, but there you have it).
function download( ) {
  return downloadItem( getDownload( ) );

}
function downloadItem( $d ) {
  $cid = (int)$_REQUEST['cid'];

  updateLastViewed( isset($d['author']) ? $d['author'] : null, $d['assmtID'] );

  if( isset( $d['file'] ) )
    return downloadFile( $d['file'], $d['author'], $d['identity'], $d['allocIdx'], $d['name'], $d['anon'] ); //- no return
  else if( isset( $d['reviewer'] ) )
    return downloadAllReviews( $d['isReviewsFor'], $d['assmtID'], $d['author'], $d['identity'], $d['anon'] );
  //  else if( isset( $d['singleReview'] ) )
  //    return downloadSingleReview( $d['assmtID'], $d['author'], $d['identity'], $d['anon'] );
  else if( isset( $d['essay'] ) )
    return downloadEssay( $d['essay'], $d['author'], $d['identity'], $d['extn'], $d['name'], $d['allocIdx'], $d['anon'], $cid );
  else if( isset( $d['essays'] ) )
    return downloadEssayZIP( $d['assmtID'], $d['essays'], $d['author'], $d['identity'], $d['allocIdx'], $d['name'], $d['anon'] );
  else if( isset( $d['all'] ) )
    return downloadAllEssaysZIP( $d['assmtID'], $d['courseID'], $d['name'], $d['anon'], $cid );
  else
    return warning( 'Unexpected: cannot download' ); //- "cannot happen"
}


function getDownload( ) {
  if( empty( $_SESSION['download'] ) )
    securityAlert( 'no downloads established' );
  $download =& $_SESSION['download'];

  if( isset( $_REQUEST['download'] ) ) {
    if( empty( $download[ $_REQUEST['download'] ] ) )
      securityAlert( 'non-existent download id' );
    $d = $download[ $_REQUEST['download'] ];
  } else if( isset( $_REQUEST['essay'] ) ) {
    foreach( $download as $dd )
      if( $dd['essay'] == $_REQUEST['essay'] ) {
	$d = $dd;
	break;
      }
  }
  if( ! isset( $d ) )
    securityAlert( 'missing download id' );
  return $d;
}

// Mark all allocations by the current reviewer to this author as
// viewed.
function updateLastViewed( $author, $assmtID ) {
  if( ! isset( $_SESSION['availableAssignments'] ) || ! isset( $_SESSION['allocations'] ) )
    return;

  $reviewer = $_SESSION['userID'];
  foreach( $_SESSION['availableAssignments'] as $assmt )
    if( $assmt['assmtID'] == $assmtID ) {
      $reviewer = $assmt['review-group'];
      break;
    }

  $allocIDs = array( );
  $now = date('Y-m-d H:i:s');
  foreach( $_SESSION['allocations'] as $i => $alloc )
    if( $alloc['assmtID'] == $assmtID
	&& ( empty($author) || $alloc['author'] == $author )
	&& $alloc['reviewer'] == $reviewer
	) {
      $_SESSION['allocations'][ $i ]['lastViewed'] = time();
      $allocIDs[] = $alloc['allocID'];
    }

  ensureDBconnected( 'updateLastViewedFor' );
  if( ! empty($allocIDs) )
    checked_mysql_query( 'UPDATE Allocation '
			 . "SET lastViewed = now()"
			 . ' WHERE allocID IN (' . join(",", $allocIDs ) . ')' );
}



function downloadAllReviews( $isReviewsFor, $assmtID, $author, $identity, $anonymous ) {
  require_once 'BlockParser.php';

  ensureDBconnected( 'downloadAllReviews' );

  $origAssmt = fetchOne("select * from Assignment where assmtID = $isReviewsFor");
  $markGrades = stringToItems( $origAssmt['markGrades'] );
  parse_str($origAssmt['markLabels'] ?? '', $markLabels);
  parse_str($origAssmt['markItems'] ?? '', $markItems);
  $commentLabels = commentLabels($origAssmt);
  $markKeys = array_keys( $markItems );

  $rs = checked_mysql_query( 'SELECT author, item, comments, marks'
			     . ' FROM Comment c'
			     . ' LEFT JOIN Allocation a ON c.allocID=a.allocID'
			     . " WHERE assmtID = $isReviewsFor"
			     . " AND reviewer = $author" //- sic.
			     . ' ORDER BY author, item' );

  $anon = array( );
  $div = HTML::div( array('id'=>'feedback') );
  $currentA = null;
  while( $row = $rs->fetch_assoc() ) {
    if( $currentA != $row['author'] ) {
      $currentA = $row['author'];
      $who = $anonymous ? AnonymiseAuthor( $anon, $currentA, 'Author-' ) : userIdentity( $currentA, array('assmtID'=> $isReviewsFor), 'reviewer');
      $div->pushContent( HTML::h3( Sprintf_('In reviewing %s', $who )));

      $aident = $anonymous ? 'Author' : userIdentity( $row['author'], $origAssmt, 'author' );
      $div->pushContent( HTML::h3('Submission reviewed'),
			 downloadables( $row['author'], $aident, -1, $origAssmt, (int)$_REQUEST['cid'] ));

      parse_str($row['marks'] ?? '', $marks);
      if( ! empty( $markItems ) ) {
	$markGradesTable = table( HTML::tr( HTML::th('Item'), HTML::th('Mark')) );
	foreach( $markKeys as $item ) {
	  $m = $marks[ $item ] - 1;
	  if( isset( $markGrades[ $item ] ) && isset( $markGrades[ $item ][ $m ] ) )
	    $mark = $markGrades[ $item ][ $m ];
	  else
	    $mark = $marks[ $item ];
	  $max = is_array( $markGrades[$item] ) ? '(/' . max($markGrades[ $item ]) . ')' : '';
	  $markGradesTable->pushContent( HTML::tr( HTML::td( $markLabels[ $item ], $max ),
						   HTML::td( $mark ) ) );
	}
	$div->pushContent( $markGradesTable );
      }
    }
 
    if (!empty($commentLabels[$row['item']]))
      $label = $commentLabels[$row['item']];
    else
      $label = "Comment-" . ($row['item'] + 1);
    $div->pushContent(HTML::p(HTML::span(array('class'=>'itemFB'), $label),
			      HTML::div(array('class'=>'commentFB'),
					MaybeTransformText($row['comments']))));
  }

  updateLastViewed($author, $assmtID);


  $html = HTML( );

  if( $div->isEmpty( ) )
    return HTML::h2( $anonymous
		     ? _('No comments were made by this reviewer')
		     : Sprintf_('No comments were made by %s', $identity ));
  else
    return HTML( HTML::h2( $anonymous
			   ? _('Comments made by this reviewer')
			   : Sprintf_('Comments made by %s', $identity)),
		 $div );
}

function downloadSingleReview( $assmt, $allocID, $identity, $allocIdx, $anonymous ) {
  $origAssmt = fetchOne("SELECT * FROM Assignment WHERE assmtID=$assmt[isReviewsFor]");
  parse_str($origAssmt['markItems'] ?? '', $markItems );
  $markGrades = stringToItems( $origAssmt['markGrades'] );
  parse_str($origAssmt['markLabels'] ?? '', $markLabels);
  $commentLabels = commentLabels($origAssmt);
  $markKeys = array_keys( $markItems );

  ensureDBconnected( 'downloadSingleReview' );
  $alloc = fetchOne( "SELECT * FROM Allocation WHERE allocID=$allocID" );
  $div = HTML::div( array('id'=>'feedback') );

  parse_str($alloc['marks'] ?? '', $marks);
  if( ! empty( $markItems ) && ! empty($marks) ) {
    $markGradesTable = table( HTML::tr( HTML::th('Item'), HTML::th('Mark') ));
    foreach( $markKeys as $item ) {
      $m = $marks[ $item ] - 1;
      if( isset( $markGrades[ $item ] ) && isset( $markGrades[ $item ][ $m ] ) )
	$mark = $markGrades[ $item ][ $m ];
      else
	$mark = $marks[ $item ];
      $max = is_array( $markGrades[$item] ) ? '(/' . max($markGrades[ $item ]) . ')' : '';
      $markGradesTable->pushContent( HTML::tr( HTML::td( $markLabels[ $item ], $max ),
					       HTML::td( $mark ) ) );
    }
    $div->pushContent( $markGradesTable );
  }

  require_once 'BlockParser.php';

  $rs = checked_mysql_query( "SELECT * FROM Comment WHERE allocID=$allocID ORDER BY item" );
  while ($row = $rs->fetch_assoc()) {
    if (isset($commentLabels[ $row['item']]))
      $label = $commentLabels[ $row['item']];
    else
      $label = "Comment-" . ($row['item'] + 1);
    $div->pushContent(HTML::p(HTML::span(array('class'=>'itemFB'), $label),
			      HTML::div(array('class'=>'commentFB'),
					MaybeTransformText( $row['comments']))));
  }
  
  //- This is a review of a specific allocation, so the "author" is an allocID
  updateLastViewed( $allocID, $assmt['assmtID'] );

  if( $div->isEmpty( ) )
    return HTML::h2( $anonymous
		     ? _('No feedback was provided in this review')
		     : Sprintf_('No feedback was provided by %s', $identity ));
  else {
    $aident = $anonymous ? 'Author' : userIdentity( $alloc['author'], $origAssmt, 'author' );
    $div->pushContent( HTML::h2('Submission reviewed'),
		       downloadables( $alloc['author'], $aident, -1, $origAssmt, (int)$_REQUEST['cid'] ));
    return HTML( HTML::h2( $anonymous
			   ? _('Feedback from this review')
			   : Sprintf_('Feedback from %s', $identity)),
		 $div );
  }
}


function downloadFile( $filename, $author, $identity, $allocIdx, $assmtName, $anonymous ) {
  if( is_dir( $filename ) ) {
    require_once 'ZipLib.php';
    if( $allocIdx != -1 ) {
      $prefix = 'allocation-' . $allocIdx;
      $comment = sprintf( _("Download for assignment '%s', allocation #%d"), $assmtName, $allocIdx);
    } else {
      $prefix = ".";
      $comment = sprintf( _("Download for assignment '%s'"), $assmtName );
    }

    $zip = new ZipWriter( $comment, 'download.zip' );
    addZipFiles( array($filename), $zip, $prefix, $author, $identity, $anonymous );
    $zip->finish( );

  } else {

    $fd = fopen( $filename, 'rb' );
    if( ! $fd )
      return warning(_('The file is unreadable.  Please check with your instructor.'));

    $fname = basename( $f );
    if( $anonymous && ! empty($author) )
      $fname = preg_replace( "/" . preg_quote( $identity ) . "/i",
			     _('Author'), $fname );

    $info = pathinfo( $filename );

    $stat = fstat( $fd );

    prepareForDownload( $info['extension'], $stat[7], $fname );
    session_write_close( );
    fpassthru( $fd );
    fclose( $fd );
  }
  exit;
}


function downloadEssay($id, $author, $identity, $extn, $name, $allocIdx, $anonymous, $cid) {
  $row = fetchOne("select essay, url, compressed, description, reqIndex, overflow from Essay where essayID = $id");
  if (!$row || (empty($row['essay']) && empty($row['url'])))
    return warning(_('The selected submission is empty.'));

  if ($anonymous || empty($row['description']))
    $name = "document-$allocIdx-$row[reqIndex]" . fileExtension($row['description']);
  else
    $name = $row['description'];
  
  if ($row['extn'] == 'inline-text')
    $name = replaceExtension($name, ".html");


  if (!empty($row['url'])) {
    if ($row['compressed']) {
      @$raw = file_get_contents($row['url']);
      if ($raw === false) return essay_url_not_found($row['url'], $id, $row['description']);
      $contents = gzuncompress($raw);
      prepareForDownload($extn, strlen($contents), $row['description']);
      echo $contents;
      exit;
    } else {
      @$fd = fopen($row['url'], 'rb');
      if (!$fd) return essay_url_not_found($row['url'], $id, $row['description']);
      $stat = fstat($fd);
      prepareForDownload($extn, $stat[7], $row['description']);
      session_write_close();
      fpassthru($fd);
      fclose($fd);
    }
    
    exit;
  } else {
    ini_set('memory_limit', '512M');
    $raw = $row['essay'];
    if ($row['overflow']) {
      $rs = checked_mysql_query("select data from Overflow where essayID = $id order by seq");
      while ($chunk = $rs->fetch_row())
	$raw .= $chunk[0];
    }
  }
  
  // *** Need to handle inline-text properly ***
  // *** Display the HTML, and add a Back button (to where?) using $cid and $_REQUEST['aID'] (if given)

  if( $extn == 'inline-text' ) {
      $name .= ".html";
  }

  $essay = $row['compressed'] ? gzuncompress( $raw ) : $raw;
  prepareForDownload( $extn, strlen($essay), $name );
  echo $essay;
  exit;
}

function essay_url_not_found($url, $essayId, $description) {
  return warning(Sprintf_('The file %s cannot be found.', $description));
}

function filename_safe( $str ) {
  return strtr( $str,
		"\01\02\03\04\05\06\07\10\11\12\13\14\15\16\17\20\21\22\23\24\25\26\27\30\31\32\33\34\35\36\37\\\":/'*<>?|",
		"-----------------------------------------" );
}


function downloadEssayZIP( $assmtID, $idList, $author, $identity, $allocIdx, $assmtName, $anon ) {
  require_once 'ZipLib.php';

  if( $allocIdx != -1 ) {
    $prefix = "allocation-$allocIdx/";
    $zip = new ZipWriter(sprintf(_("Download for assignment '%s', allocation #%d"), $assmtName, $allocIdx), "allocation-$allocIdx.zip");
  } else {
    $prefix = "";
    $zip = new ZipWriter(sprintf(_("Download for assignment '%d'"), $assmtName), "allocation.zip");
  }

  foreach( $_SESSION['availableAssignments'] as $a )
    if( $a['assmtID'] == $assmtID ) {
      $assmt = $a;
      break;
    }

  $rs = checked_mysql_query( 'SELECT essayID, overflow, description, essay, compressed, url, reqIndex, extn, author FROM Essay'
                             . ' WHERE essayID IN (' . join( ",", $idList ) . ')');
  while( $row = $rs->fetch_assoc() ) {
    $extn = $row['extn'] == 'inline-text' ? '.html' : fileExtension($row['description']);
    $name = "document-$allocIdx" . numberToAlpha($row['reqIndex']) . $extn;

    if( ! empty( $row['essay'] ) ) {
      $raw = $row['essay'];
      if( $row['overflow'] ) {
	$rs = checked_mysql_query( "SELECT data FROM Overflow WHERE essayID = $row[essayID] ORDER BY seq" );
	while( $chunk = $rs->fetch_row() )
	  $raw .= $chunk[0];
      }
    } else if (!empty($row['url']))
      @$raw = file_get_contents($row['url']);
    else
      continue;
    $contents = $row['compressed'] ? gzuncompress( $raw ) : $raw;
    $zip->addRegularFile($prefix . filename_safe($name), $contents);
  }
  $zip->finish( );
  exit;
}


function fileExtension($name) {
  $n = strrpos($name, '.');
  return ($n === false) ? '' : substr($name, $n);
}


function numberToAlpha($n) {
  $n = intval($n);
  $alpha = '';
  while ($n > 0) {
    $p = ($n - 1) % 26;
    $n = intval(($n - $p) / 26);
    $alpha = chr(97 + $p) . $alpha;
  }

  return $alpha;
}


function downloadAllEssaysZIP( $assmtID, $classID, $assmtName, $anon ) {
  require_once 'ZipLib.php';
  $zip = new ZipWriter( "Documents for assignment '$assmtName'",
			filename_safe( $assmtName ) . '-allocations.zip' );

  $reviewer = $_SESSION['userID'];
  foreach( $_SESSION['availableAssignments'] as $a )
    if( $a['assmtID'] == $assmtID ) {
      $reviewer = $a['review-group'];
      $assmt = $a;
      break;
    }

  $allocIdxMap = array(-1 => -1);
  foreach( $_SESSION['allocations'] as $alloc )
    if( $alloc['assmtID'] == $assmtID && $alloc['reviewer'] == $reviewer ) {
	$allocIdxMap[ $alloc['author'] ] = $alloc['allocIdx'];
    }

  $rs = checked_mysql_query('select essayID, overflow, description, essay, compressed, url, extn, reqIndex, author'
			    . ' from Essay'
			    . " where assmtID = $assmtID"
			    . ' and author in (' . join(',', array_keys($allocIdxMap)) . ')');
  while( $row = $rs->fetch_assoc() ) {
    $allocIdx = $allocIdxMap[$row['author']];
    $extn = $row['extn'] == 'inline-text' ? '.html' : fileExtension($row['description']);
    $name = "document-$allocIdx" . numberToAlpha($row['reqIndex']) . $extn;

    if( ! empty( $row['essay'] ) ) {
      $raw = $row['essay'];
      if( $row['overflow'] ) {
	$rs = checked_mysql_query( "SELECT data FROM Overflow WHERE essayID = $row[essayID] ORDER BY seq" );
	while( $chunk = $rs->fetch_row() )
	  $raw .= $chunk[0];
      }
    } else if (!empty($row['url']))
      @$raw = file_get_contents($row['url']);
    else
      continue;
    $contents = $row['compressed'] ? gzuncompress( $raw ) : $raw;
    $zip->addRegularFile( 'allocation-' . $allocIdxMap[ $row['author'] ] . '/' . filename_safe($name),
			  $contents );
  }
  
  $zip->finish( );
  exit;
}

global $extnTypes;
$extnTypes = array('class' =>'application/octet-stream',
		   'doc'   =>'application/msword',
		   'docx'  =>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		   'exe'   =>'application/octet-stream',
		   'gif'   =>'image/gif',
		   'htm'   =>'text/html',
		   'html'  =>'text/html',
		   'java'  =>'text/plain',
		   'jpeg'  =>'image/jpg',
		   'jpg'   =>'image/jpg',
		   'pdf'   =>'application/pdf',
		   'png'   =>'image/png',
		   'ppt'   =>'application/vnd.ms-powerpoint',
		   'pptx'  =>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		   'ps'    =>'application/postscript',
		   'text'  =>'text/plain',
		   'txt'   =>'text/plain',
		   'xls'   =>'application/vnd.ms-excel',
		   'xlsx'  =>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		   'zip'   =>'application/x-zip');

function prepareForDownload( $extn, $size, $fname = null ) {
  global $extnTypes;
  $extn = strtolower( $extn );
  if( isset( $extnTypes[ $extn ] ) )
    $ctype = $extnTypes[ $extn ];
  else if( in_array( $extn, $extnTypes ) )
    $ctype = $extn;
  else
    $ctype = 'application/force-download';

  while( ob_end_clean( ) )
    //- Discard any earlier HTML or other headers
    ;

  header( 'Content-Type: ' . $ctype );
  if( ! empty( $fname ) )
    header( 'Content-Disposition: attachment; filename=' . str_replace('+', '_', urlencode( $fname )) . ';' );
  header( 'Content-Length: ' . $size );
  header( 'Content-Transfer-Encoding: binary');
  header( 'Connection: close');
}


function addZipFiles( $files, &$zip, $prefix, $author, $identity, $anonymous ) {
  if( $files === false )
    //- glob() returns false if there are no matches
    return;

  foreach( $files as $f ) {
    $f = rtrim( $f, '/' );
    if( $f == '.' || $f == '..' )
      continue;
    $fname = basename( $f );
    if( $anonymous && ! empty($author) )
      $fname = preg_replace( "/" . preg_quote( $identity ) . "/i",
			     _('Author'), $fname );

    if( is_dir( $f ) )
      addZipFiles( glob( "$f/*" ), $zip, "$prefix/$fname", $author, $identity, $anonymous );
    else {
      $contents = file_get_contents( $f );
      if( $contents )
        $zip->addRegularFile( "$prefix/$fname", $contents );
    }
  }
}


function showReviewFile( ) {
  $r = (int)$_REQUEST['r'];
  if( ! isset( $_SESSION['reviewFile'][ $r ] ) )
    return warning(_('The file review number is invalid.'));
  $info = $_SESSION['reviewFile'][ $r ];
  $id = (int)$info['id'];
  $row = fetchOne( "SELECT description, contents, overflow, compressed, extn FROM ReviewFile WHERE reviewFileID = $id" );
  if( ! $row )
    return warning( _('The selected review file cannot be found.' ));

  $raw = $row['contents'];
  if( $row['overflow'] ) {
    $rs = checked_mysql_query( "SELECT data FROM ReviewFileOverflow WHERE reviewFileID = $id ORDER BY seq" );
    while( $chunk = $rs->fetch_row() )
      $raw .= $chunk[0];
  }

  $review = $row['compressed'] ? gzuncompress( $raw ) : $raw;
  prepareForDownload( $row['extn'], strlen($review), $row['description'] ? $row['description'] : 'untitled' );
  echo $review;
  exit;
}
