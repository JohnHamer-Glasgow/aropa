<?php
/*
    Copyright (C) 2018 John Hamer <J.Hamer@cs.auckland.ac.nz>

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

class RubricTransform {
  var $_document;		//- input; rubric HTML
  var $_edit;			//- input; boolean (are we editing or displaying?)
  var $_alloc;			//- input; current allocation record (for $AUTHOR, $REVIEWER)
  var $_radioValues;		//- input; existing marks
  var $_commentValues;		//- input; existing comment text
  var $_reviewFiles;            //- input; existing file reviews
  var $_radioGroup;
  var $_radioCode;		//- index of radio button in this group
  var $_commentGroup;		//- index of comment
  var $_fileGroup;		//- index of file review
  var $_radioItems;             //- map of radio group ID -> #items
  var $_commentItems;           //- map of comment ID -> section name
  var $_radioLabels;            //- map of radio group ID -> section name
  var $_section;		//- innerHTML from most recent H1..H6 section

  function __construct( $rubric,
			$edit     = 'edit',
			$marks    = array( ),
			$comments = array( ),
			$reviewFiles = array( ),
			$alloc    = array( )) {
    $this->_document = new DOMDocument( );
    libxml_use_internal_errors(true);
    libxml_clear_errors( );
    @$this->_document->loadHTML( "<html>$rubric</html>" );
    $this->_edit          = $edit;
    $this->_radioValues   = $marks;
    $this->_commentValues = $comments;
    $this->_reviewFiles   = $reviewFiles;
    $this->_alloc         = $alloc;
  }

  function transform( ) {
    $this->_radioCode = 0;
    $this->_radioGroup = 0;
    $this->_commentGroup = 0;
    $this->_fileGroup = 0;
    $this->_radioItems = array( );
    $this->_commentItems = array( );

    $this->_section = '';
    $this->_radioLabels = array( );
    $this->_commentLabels = array( );
    $this->recentLabel = "";

    for( $node = $this->_document; $node; $node = $next ) {
      $next = succ( $node );
      
      if ($node->nodeType == XML_TEXT_NODE && strlen($this->recentLabel) < 5)
	$this->recentLabel = preg_replace('/([^?!.]*.).*/', '\\1', $node->wholeText);

      switch( $node->nodeName ) {
      case "img":
	$src = $node->getAttribute("src");
	if( $src )
	  if( stripos( $src, "/textBlock.jpg" ) !== false ) {
	    $comment = $this->makeComment( $node );
	    if( $comment ) {
	      $node->parentNode->replaceChild( $comment, $node );
	      $this->_radioCode = 0;
	      $this->_section = '';
	    }
	  } else
	    $this->fixupImagePath( $src, $node );
	$next = succ($next);
	break;

      case "input":
	$node->removeAttribute('disabled');
	if( $node->getAttribute("type") == 'radio' ) {
	  if( $node->parentNode->nodeName == 'span' || $node->parentNode->nodeName == 'p' )
	    //- The rubric editor adds a coloured span (or p) around each radio button, which we remove here
	    $node->parentNode->removeAttribute('style');
	    $node->removeAttribute('style');
	  $this->fixupRadio( $node );
	} else if( $node->getAttribute("type") == 'file' )
	  $this->fixupFile( $node );
	break;
	
      case "h1": case "h2": case "h3":
      case "h4": case "h5": case "h6":
      case 'hr':
	$this->_radioCode = 0;
	$this->_section = $node->nodeValue;
	$this->recentLabel = '';
	break;
      }
    }
  }

  function fixupFile($node) {
    $item = $this->_fileGroup++;

    $node->setAttribute('type', 'file');
    $node->setAttribute('name', "file[$item]");
    $node->setAttribute('id', "file_$item");
    
    if (isset($this->_reviewFiles[$item])) {
      $span = $this->_document->createElement('span');
      foreach ($this->_reviewFiles[$item] as $reviewFile) {
	// These are the existing review files.  Add them as links
	$link = $this->_document->createElement('a');
	$crc = sprintf('%u', crc32( "$_SESSION[userID]:$reviewFile[reviewFileID]"));
	$r = getReviewFileIndex($reviewFile['reviewFileID'], $item, $reviewFile['uident']);
	$cid = isset($_REQUEST['cid']) ? "&cid=$_REQUEST[cid]" : '';
	$link->setAttribute('href', "$_SERVER[PHP_SELF]?action=showReviewFile$cid&r=$r&oid=$crc");
	$link->setAttribute('class', "button");
	$link->appendChild($this->_document->createTextNode("View review file #" . ($reviewFile['item'] + 1)));
	$span->appendChild($link);

	if( $this->_edit == 'edit' ) {
	  static $loaded = false;
	  if( ! $loaded ) {
	    extraHeader( 'toggleButton.js', 'script' );
	    $loaded = true;
	  }
	  $btn = $this->_document->createElement('button');
	  $btn->setAttribute( 'type', 'button' );
	  $btn->setAttribute( 'class', 'button' );
	  $btn->setAttribute( 'id', "replace_$item" );
	  $btn->setAttribute( 'onclick', "toggleDisplay($item);return false;" );
	  $btn->appendChild( $this->_document->createTextNode( "Replace this review file" ) );

	  $span->appendChild( $btn );
	}

      }
      if( $span->firstChild ) {
	if( $this->_edit == 'edit' ) {
	  $n = $this->_document->createElement( 'input' );
	  $n->setAttribute( 'type', 'file');
	  $n->setAttribute( 'name', "file[$item]" );
	  $n->setAttribute( 'id', "file_$item" );
	  $n->setAttribute( 'style', "display: none" );
	  $span->appendChild( $n );
	}
	$node->parentNode->replaceChild( $span, $node );
	$replaced = true;
      }
    }

    if( $this->_edit != 'edit' && ! isset( $replaced ) ) {
      // Remove $node, the input element.  No further file reviews are expected.
      $node->parentNode->replaceChild( $this->_document->createTextNode( "(The review file is missing)" ), $node );
    }
  }

  //- Add the code ('value' attribute) and checked status of a radio
  //- button
  function fixupRadio( $node ) {
    if( $this->_radioCode == 0 ) {
      $this->_radioGroup++;
      $this->_radioLabels[ $this->_radioGroup ] = $this->_section ? $this->_section : "Button group $this->_radioGroup";
    }
    $this->_radioCode++;

    $id = $this->_radioGroup;

    if( ! isset( $this->_radioItems[ $id ] ) )
      $this->_radioItems[ $id ] = array( );
    $this->_radioItems[ $id ] = $this->_radioCode;

    $node->setAttribute( 'name', "mark[$id]" );

    $node->removeAttribute('disabled');
    $node->removeAttribute('checked');

    $value = $this->_radioCode;
    $node->setAttribute( 'value', $value );

    if ($this->_edit == 'edit' || $this->_edit == 'show') {
      if (isset($this->_radioValues[$id] ) && $value == $this->_radioValues[$id])
	$node->setAttribute('checked', 'checked');
    } else {
      if( isset( $this->_radioValues[ $id ] ) ) {
        if( isset( $this->_radioValues[ $id ][ $value ] ) )
          $count = $this->_radioValues[ $id ][ $value ];
        else
          $count = 0;
      } else
        $count = '-';

      $mark = $this->_document->createElement('span');
      $mark->appendChild( $this->_document->createTextNode( "[$count]" ) );
      if( $count > 0 )
        $mark->setAttribute('class', 'feedbackMark radioMark' );
      else
        $mark->setAttribute('class', 'radioMark' );
	
      $node->parentNode->replaceChild( $mark, $node );
    }
  }


  //- $AUTHOR or $REVIEWER images
  function fixupImagePath( $src, $node ) {
    if( strpos( $src, '$AUTHOR' ) !== false || strpos( $src, '$REVIEWER' ) !== false )
      $node->setAttribute('width', "150" );

    if( isset( $this->_alloc['author'] ) )
      $src = str_replace( '$AUTHOR', $this->_alloc['author'], $src );

    if( isset( $this->_alloc['reviewer'] ) )
      $src = str_replace( '$REVIEWER', $this->_alloc['reviewer'], $src );

    if( ! isset( $_SESSION['img'] ) )
      $_SESSION['img'] = array( );
    $i = array_search( $src, $_SESSION['img'] );
    if( $i === false ) {
      $_SESSION['img'][] = $src;
      $i = count( $_SESSION['img'] ) - 1;
    }
    $node->setAttribute('src', "$_SERVER[PHP_SELF]?action=img&img=$i" );
  }


  function makeComment($node) {
    $id = $this->_commentGroup++;

    $this->_commentItems[$id] = "Comment " . ($id+1);
    $this->recentLabel = '';

    $text = "";
    $comments = $this->_document->createElement('div');
    $comments->setAttribute('class', "comment");
    if (isset($this->_commentValues[$id]))
      foreach ($this->_commentValues[$id] as $c) {
	if (!empty($c['text']))
	  $text .= "$c[text]\n";
	else if (!empty($c['comments'])) {
	  $fieldset = $this->_document->createElement('fieldset');
	  if (isset($c['madeByIdentity'])) {
	    $legend = $this->_document->createElement('legend');
	    $legend->appendChild($this->_document->createTextNode("Written by $c[madeByIdentity]"));
	    $fieldset->appendChild($legend);
	  }

	  $cdoc = new DOMDocument();
	  $cdoc->loadHTML(MaybeTransformText($c['comments'])->asXML());
	  foreach ($cdoc->getElementsByTagName('body') as $body)
	    $fieldset->appendChild($this->_document->importNode($body, true));
	  $comments->appendChild($fieldset);
	}
      }
    
    $result = $this->_document->createElement('div');
    $result->appendChild($comments);
    if ($this->_edit == 'edit') {
      $textarea = $this->_document->createElement('textarea', $text);
      $textarea->setAttribute('name', "comment[$id]");

      $style = $node->getAttribute('style');
      if ($node->hasAttribute('height'))
	$height = (int)$node->getAttribute('height');
      else if (preg_match('/height\s*:\s*[\'"]?\s*(\d+)\s*(pt|pc|em|ex|px)?/', $style, $matches))
	$height = cssUnitsToPt($matches[1], $matches[2]);

      if ($node->hasAttribute('width'))
	$width = (int)$node->getAttribute('width');
      else if (preg_match('/width\s*:\s*[\'"]?\s*(\d+)\s*(pt|pc|em|ex|px)?/', $style, $matches))
	$width = cssUnitsToPt($matches[1], $matches[2]);

      if (isset($height))
	$textarea->setAttribute('rows', max(1, round($height/12.0))); //- one row is typically 12pt
      if (isset($width))
	$textarea->setAttribute('cols', max(1, round($width/6.0))); //- one character width is typically 6pt

      $result->appendChild($textarea);
    }

    return $result;;
  }
}

function cssUnitsToPt( $size, $unit ) {
  switch( strtolower(trim($unit)) ) {
  default:
  case "": case "px": case "pt":
    return $size;
  case "em": case "pc":
    return $size * 12;
  case "ex":
    return $size * 6;
  }
}


function succ( $node ) {
  if( $node->firstChild )
    return $node->firstChild;

  if( $node->nextSibling )
    return $node->nextSibling;

  do {
    $node = $node->parentNode;
  } while( $node && ! $node->nextSibling );

  if( $node )
    return $node->nextSibling;
  else
    return null;
}


  //- Transform:
  //  <img src="...$AUTHOR...">, <img src="...$REVIEWER...">
  // into:
  //  <img src=$_SERVER['PHP_SELF']?action=img&img=$i" width=150 />

  //  <img src=".../images/textBlock.jpg" style="width:?px height:?px;" />
  // into
  //  <textarea rows=ROWS cols=COLS name="comment[$name]">$text</textarea>
  // if comments, preceed with:
  //  <table><tr><td>madeBy-1</td><td>$comment-1</td></tr>...</table>

  //  <input type="radio" />
  // into
  //  <input type="radio" name="mark[$name]" value="$value" checked />
  // if not editing: <b>[$count]</b>
  

require_once 'transform.php';

function fetchRubric( $rubricID ) {
  $rubricID = (int)$rubricID;
  ensureDBconnected( 'fetchRubric' );
  $row = fetchOne( "SELECT rubric, rubricXML FROM Rubric WHERE rubricID = $rubricID" );
  $rubric = '<p>(empty)</p>';
  if( $row ) {
    $rubric = $row['rubricXML'];
    if( empty( $rubric ) )
      $rubric = PHPwikiToXml( $row['rubric'] );
  }
  return $rubric;
}


function TransformRubricByID( $rubricID, $marks = array(), $comments = array(), $reviewFiles = array( ), $edit = 'no edit', $alloc = array() ) {
  return TransformRubric( fetchRubric( $rubricID ), $marks, $comments, $reviewFiles, $edit, $alloc );
}


function TransformRubric( $rubric, $marks = array(), $comments = array(), $reviewFiles = array( ), $edit = 'no edit', $alloc = array() ) {
  $rubric = preg_replace('/(<|&lt;)!--.*?--(>|&gt;)/', '', $rubric);
  $r = new RubricTransform( $rubric, $edit, $marks, $comments, $reviewFiles, $alloc );
  $r->transform( );
  $r->_document->removeChild($r->_document->doctype);
  if ($r->_document->firstChild->firstChild)
    $r->_document->replaceChild($r->_document->firstChild->firstChild, $r->_document->firstChild);
  return array(HTML::raw(str_replace(array('<body>', '</body>'), array('<div>', '</div>'), $r->_document->saveHTML())),
	       $r->_radioItems,
	       $r->_radioLabels,
	       $r->_commentItems,
	       $r->_fileGroup );
}


function MaybeTransformText( $str ) {
  if( isHTML( $str ) ) {
    require_once( 'XMLcleaner.php' );
    return HTML::raw( cleanupHTML( $str ) );
  } else
    return HTML::raw( PHPwikiToXml( $str ) );
}

function isHTML( $str ) {
  return strpos( $str, '<' ) == 0          //- Starts with <
    || strpos( $str, '</'     ) !== false  //- or contains one of these
    || strpos( $str, '&nbsp;' ) !== false
    || strpos( $str, '&quot;' ) !== false
    || strpos( $str, '&#39;'  ) !== false
    || strpos( $str, '<br />' ) !== false
    || strpos( $str, '<img'   ) !== false
  ;
}

//-]
function getReviewFileIndex($id, $item, $uident) {
  if (!isset( $_SESSION['reviewFile']))
    $_SESSION['reviewFile'] = array();
  foreach ($_SESSION['reviewFile'] as $idx => $val)
    if ($val['id'] == $id && $val['item'] == $item)
      return $idx;
  $_SESSION['reviewFile'][] = array('id' => (int)$id, 'item' => $item, 'name' => $uident);
  return count($_SESSION['reviewFile']) - 1;
}
