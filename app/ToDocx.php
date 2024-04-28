<?php
/*
    Copyright (C) 2015 John Hamer <J.Hamer@acm.org>

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

function WriteReviewsToDocx( $assmt, $cid, $allocs, $selectedAuthor, $selectedReviewer, $template, $stylesheet ) {
  ini_set( 'memory_limit', '512M' );
  $assmtID = $assmt['assmtID'];
  parse_str( $assmt['markItems'] ?? '', $markItems );
  $markGrades = stringToItems( $assmt['markGrades'] );
  parse_str( $assmt['markLabels'] ?? '', $markLabels );
  $commentItems = commentLabels($assmt);

  libxml_use_internal_errors(true);
  $BASE = ($_SERVER['HTTPS'] == 'on' ? "https" : "http") . "://$_SERVER[SERVER_NAME]";

  $commentMap = array();

  $xml = new DOMDocument();
  $feedback = $xml->createElement('feedback');
  $xml->appendChild($feedback);
  $feedback->appendChild($xml->createElement('assignment', htmlspecialchars($assmt['aname'])));
  $feedback->appendChild($xml->createElement('class', htmlspecialchars(className($cid))));
  
  $marking = $xml->createElement('mark-labels');
  foreach( $markItems as $item => $n ) {
    $ml = $xml->createElement('mark');
    $ml->setAttribute('item', $item);
    if( isset($markLabels[$item]) )
      $ml->appendChild( $xml->createElement('label', $markLabels[$item]) );
    if( isset($markGrades) ) {
      $gs = $xml->createElement('grades');
      for( $i = 0; $i < (int)$n; $i++ )
	if( isset($markGrades[$item][$i]) ) {
	  $g = $xml->createElement('grade');
	  $g->setAttribute('value', $i+1);
	  $g->setAttribute('score', $markGrades[$item][$i] );
	  $gs->appendChild($g);
	}
      $ml->appendChild($gs);
    }
    $marking->appendChild($ml);
  }  
  $feedback->appendChild($marking);
  
  $comments = $xml->createElement('comment-labels');
  foreach( $commentItems as $item => $label ) {
    $c = $xml->createElement('comment', $label);
    $c->setAttribute('item', $item);
    $comments->appendChild($c);
  }
  $feedback->appendChild($comments);

  $reviews = $xml->createElement('reviews');
  $feedback->appendChild($reviews);
  foreach( $allocs->allocations as $row )
    if( (empty($selectedAuthor) || $row['author'] == $selectedAuthor)
	&& (empty($selectedReviewer) || $row['reviewer'] == $selectedReviewer)) {
      $a = $xml->createElement('review');
      $reviews->appendChild($a);
      $a->setAttribute('author', $allocs->nameOfAuthor($row['author']));
      $a->setAttribute('reviewer', $allocs->nameOfReviewer($row['reviewer']));
      if( ! empty($row['marks']) ) {
	$marks = $xml->createElement('marks');
	$a->appendChild($marks);
	foreach( $row['marks'] as $item => $mark ) {
	  $m = $xml->createElement('mark');
	  $marks->appendChild($m);
	  $m->setAttribute('item', $item);
	  $m->setAttribute('mark', $mark);
	}
      }
      
      $xcs = $xml->createElement('comments');
      $a->appendChild($xcs);
      $cs = checked_mysql_query('SELECT item, comments, IFNULL(uident, CONCAT("u-", userID)) as madeBy, whenMade'
				. ' FROM Comment c INNER JOIN User u ON c.madeBy=u.userID'
				. " WHERE allocID=$row[allocID]" );
      while( $comment = $cs->fetch_assoc() ) {
	$clean = str_replace('&nbsp;', ' ', $comment['comments']);
	$c = $xml->createElement('comment');
	$xcs->appendChild($c);
	$dom = new DOMDocument('1.0', 'utf-8');
	$dom->standalone = true;
	$dom->loadHTML("<!DOCTYPE html>\n<html id='root'><head><base href='$BASE'></head><body>$clean</body></html>");
	$root = $dom->getElementById('root');
	//      $root->removeAttribute('id');
	$c->setAttribute('chunk', "alt" . count($commentMap));
	$commentMap[] = "<!DOCTYPE html>\n" . $dom->saveXML($root);
	$c->setAttribute('item', $comment['item']);
	$c->setAttribute('madeBy', $comment['madeBy']);
	$c->setAttribute('whenMade', formatTimestamp($comment['whenMade']));
      }
    }

  $xsltDocument = new DOMDocument('1.0', 'utf-8');
  $xsltDocument->load("templates/$stylesheet");
  $xsltProcessor = new XSLTProcessor();
  $xsltProcessor->importStylesheet($xsltDocument);
  
  $newContent = $xsltProcessor->transformToXML($xml);

  $tmpFile = tempnam("/tmp", "TMP-$assmtID.docx");
  if (!@copy("templates/$template", $tmpFile))
    return false;

  $zipArchive = new ZipArchive();
  $zipArchive->open($tmpFile);
  $zipArchive->addFromString("word/document.xml", $newContent);
  $rels = new SimpleXMLElement($zipArchive->getFromName('word/_rels/document.xml.rels'));

  foreach( $commentMap as $id => $html) {
    $filename = "import-$id.html";
    $zipArchive->addFromString("word/$filename", $html);
    $r = $rels->addChild('Relationship');
    $r->addAttribute('Id', "alt$id");
    $r->addAttribute('TargetMode', 'Internal');
    $r->addAttribute('Type', "http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk");
    $r->addAttribute('Target', $filename);
  }

  $zipArchive->addFromString('word/_rels/document.xml.rels', $rels->saveXML());
  $ctypes = new SimpleXMLElement($zipArchive->getFromName('[Content_Types].xml'));
  $ch = $ctypes->addChild('Default');
  $ch->addAttribute('Extension', "html");
  $ch->addAttribute('ContentType', "application/xhtml+xml");
  $zipArchive->addFromString('[Content_Types].xml', $ctypes->saveXML());
  $zipArchive->close();

  return $tmpFile;
}
