<?php
/*
    Copyright (C) 2018 John Hamer <J.Hamer@acm.org>

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

class Responses {
  var $xml;
  var $responses;
  var $markGrades;
  
  function __construct($title, $assmt, $left, $right) {
    parse_str($assmt['markItems'] ?? '', $markItems);
    parse_str($assmt['markLabels'] ?? '', $markLabels);
    $commentItems = commentLabels($assmt);
    $this->markGrades = stringToItems($assmt['markGrades']);

    libxml_use_internal_errors(true);

    $this->xml = new DOMDocument();
    $results = $this->xml->createElement('results');
    $this->xml->appendChild($results);
    $results->appendChild($this->xml->createElement('title'))->appendChild($this->xml->createTextNode($title));
    $results->appendChild($this->xml->createElement('left'))->appendChild($this->xml->createTextNode($left));
    $results->appendChild($this->xml->createElement('right'))->appendChild($this->xml->createTextNode($right));

    $marking = $this->xml->createElement('mark-items');
    $results->appendChild($marking);
    foreach ($markItems as $item => $n) {
      $marking->appendChild($ml = $this->xml->createElement('mark'));
      $ml->setAttribute('item', $item);
      if (isset($markLabels[$item]))
	$ml->setAttribute('label', $markLabels[$item]);
      else
	$ml->setAttribute('label', "Mark-$item");
    }

    $commenting = $this->xml->createElement('comment-items');
    $results->appendChild($commenting);
    foreach ($commentItems as $item => $label) {
      $commenting->appendChild($cl = $this->xml->createElement('comment'));
      $cl->setAttribute('item', $item + 1);
      $cl->setAttribute('label', $label);
    }

    $this->responses = $this->xml->createElement('responses');
    $results->appendChild($this->responses);
  }

  function newResponse($ids) {
    $response = $this->xml->createElement('response');
    $this->responses->appendChild($response);
    foreach ($ids as $f => $id)
      $response->setAttribute($f, $id);
    return $response;
  }

  function addMarkStr($response, $markStr) {
    parse_str($markStr ?? '', $marks);
    $this->addMarks($response, $marks);
  }

  function addMarks($response, $marks) {
    foreach ($marks as $item => $mark) {
      $m = $this->xml->createElement('mark');
      $response->appendChild($m);
      $m->setAttribute('item', $item);
      if (isset($this->markGrades[$item][$mark - 1]))
	$n = $this->markGrades[$item][$mark - 1];
      else
	$n = $mark;
      if (is_numeric($n))
	$m->setAttribute('t', 'n');
      $m->appendChild($this->xml->createTextNode($n));
    }
  }

  function addComment($response, $item, $comment) {
    $c = $this->xml->createElement('comment');
    $response->appendChild($c);
    $c->setAttribute('item', $item + 1);
    $c->appendChild($this->xml->createTextNode(html_entity_decode(strip_tags($comment))));
  }
}

  /* xml:
    <results title="...">
      <mark-items>
        <mark item="..." label="..." />
      </mark-items>
      <comment-items>
        <comment item="..." label="...' />
      </comment-items>
      <response [author="..." reviewer="..."] | [pid="..."]>
        <mark item="...">
	...
	</mark>
	<comment item="...">
	...
	</comment>
      </response>
    </results>
   */

function toXlsx($xml, $xslt, $filename, $template = 'template.xlsx') {
  $xsltDocument = new DOMDocument('1.0', 'utf-8');
  $xsltDocument->load("templates/$xslt");
  $xsltProcessor = new XSLTProcessor();
  $xsltProcessor->registerPHPFunctions('cellRef0');
  $xsltProcessor->importStylesheet($xsltDocument);
  $newContent = $xsltProcessor->transformToXML($xml);
  
  $tmpFile = tempnam(sys_get_temp_dir(), "xlsx");
  if (!@copy("templates/$template", $tmpFile))
    return false;

  $zipArchive = new ZipArchive();
  $zipArchive->open($tmpFile);
  $zipArchive->addFromString("xl/worksheets/sheet1.xml", $newContent);
  $zipArchive->addFromString("docProps/core.xml", coreProperties());
  $zipArchive->close();

  while( ob_end_clean( ) )
    //- Discard any earlier HTML or other headers
    ;

  $fp = fopen($tmpFile, 'rb');
  header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
  header("Content-Disposition: attachment; filename=$filename;" );
  fpassthru($fp);
  fclose($fp);
  unlink($tmpFile);
  exit;
}

function cellRef0($n) {
  if ($n < 26)
    return chr(ord('A') + $n);
  else
    return cellRef0($n / 26 - 1) . cellRef0($n % 26);
}

function coreProperties() {
  return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties
    xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:dcterms="http://purl.org/dc/terms/"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:creator>Aropa Peer Review</dc:creator>
  <cp:lastModifiedBy>' . $_SESSION['username'] . '</cp:lastModifiedBy>
  <dcterms:modified xsi:type="dcterms:W3CDTF">' . date(DATE_W3C) . '</dcterms:modified>
</cp:coreProperties>';
}
