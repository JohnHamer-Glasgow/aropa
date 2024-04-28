<?php
/*
    Copyright (C) 2019 John Hamer <J.Hamer@acm.org>

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

function downloadUploads( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  ini_set( 'memory_limit', '256M' );
  set_time_limit( 0 );

  require_once 'ZipLib.php';
  require_once 'Allocations.php';
  require_once 'download.php'; // For filename_safe and $extnTypes
  if ($assmt['authorsAre'] == 'group') {
    require_once 'Groups.php';
    $groups = new Groups($assmtID);
  }

  $allocs = new Allocations($assmtID);

  $comment = sprintf( _('Submissions for %s, as at %s'), $assmt['aname'], date('Y-m-d H:i:s'));
  $zip = new ZipWriter( $comment, "Aropa-submissions-$assmtID.zip" );

  ensureDBconnected('downloadUploads');
  $rs = checked_mysql_query(
    'select url, author, essay, essayID, compressed, reqIndex, extn, overflow, description'
    . ' from Essay'
    . " where assmtID = $assmtID" );
  while( $row = $rs->fetch_assoc() ) {
    if (!empty($row['url']))
      @$essay = file_get_contents($row['url']);
    else {
      $essay = $row['essay'];
      if ($row['overflow']) {
	$rs1 = checked_mysql_query("select data from Overflow where essayID = $row[essayID] order by seq");
	while ($chunk = $rs1->fetch_row())
	  $essay .= $chunk[0];
      }
    }

    $name = $row['description'] ? filename_safe( $row['description'] ) : "document-$row[reqIndex]";
    if( $row['extn'] == 'inline-text' )
      $name .= ".html";
    
    if( isset($groups) )
      $author = $groups->groupIDtoGname[ $groups->userToGroup[$row['author']] ];
    else
      $author = null;
    
    if( empty($author) )
      $author = $allocs->nameOfAuthor($row['author']);

    $content = $row['compressed'] ? gzuncompress($essay) : $essay;
    $zip->addRegularFile(filename_safe($author) . "/$name", $content);

    if (isset($_REQUEST['pdf-text']) && $row['extn'] == 'application/pdf') {
      if (!isset($parser)) {
	include 'vendor/autoload.php';
	$parser = new \Smalot\PdfParser\Parser();
      }

      try {
	$pdfText = $parser->parseContent($content)->getText();
	$zip->addRegularFile(filename_safe($author) . "/$name.txt", $pdfText);
      } catch (Exception $e) {}
    }
  }

  if (true || isset($_REQUEST['reviews'])) {
    require_once 'ToDocx.php';
    foreach( array_keys($allocs->byEssay) as $author ) {
      $authorName = filename_safe($allocs->nameOfAuthor($author));
      $docx = WriteReviewsToDocx($assmt, $cid, $allocs, $author, null, 'feedback-template.docx', 'feedback-by-author.xslt');
      $zip->addRegularFile("$authorName/Reviews.docx", file_get_contents($docx));
      unlink($docx);

      if ($assmt['nReviewFiles'] > 0) {
	global $extnTypes;
	$extnToType = array_flip($extnTypes);
	foreach (fetchAll("select r.*, a.reviewer from ReviewFile r inner join Allocation a on r.allocID = a.allocID where a.author = $author and a.assmtID = $assmtID") as $reviewFile) {
	  $contents = $reviewFile['contents'];
	  if ($reviewFile['overflow'])
	    foreach (fetchAll("select data from ReviewFileOverflow where reviewFileID = $reviewFile[reviewFileID] order by seq asc", 'data') as $overflow)
	      $contents .= $overflow;
	  if ($reviewFile['compressed'])
	    $contents = gzuncompress($contents);
	  $madeByName = filename_safe($allocs->nameOfReviewer($reviewFile['reviewer']));
	  $suffix = $assmt['nReviewFiles'] > 1 ? " ($reviewFile[item])" : "";
	  $extn = isset($extnToType[$reviewFile['extn']]) ? $extnToType[$reviewFile['extn']] : filename_safe($reviewFile['extn']);
	  $zip->addRegularFile("$authorName/review by $madeByName$suffix.$extn", $contents);
	}
      }
    }
  }

  $zip->finish( );
  exit;
}
