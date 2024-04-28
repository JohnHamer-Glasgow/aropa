<?php
/*
    Copyright (C) 2009 John Hamer <J.Hamer@cs.auckland.ac.nz>

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

    Code in this module is derived from PHPwiki v1.2.11
*/

class Stack {
  var $items = array();
  var $size = 0;
  
  function push($item) {
    $this->items[$this->size] = $item;
    $this->size++;
    return true;
  }  
   
  function pop() {
    if ($this->size == 0)
      return false; // stack is empty
    
    $this->size--;
    return $this->items[$this->size];
  }  
   
  function cnt() {
    return $this->size;
  }  

  function top() {
    if($this->size)
      return $this->items[$this->size - 1];
    else
      return '';
  }

  function bot( ) {
    return $this->size ? $this->items[ 0 ] : '';
  }
}  

global $stack;
$stack = new Stack( );

/* 
   Wiki HTML output can, at any given time, be in only one mode.
   It will be something like Unordered List, Preformatted Text,
   plain text etc. When we change modes we have to issue close tags
   for one mode and start tags for another.
   
   $tag ... HTML tag to insert
   $tagtype ... ZERO_LEVEL - close all open tags before inserting $tag
                NESTED_LEVEL - close tags until depths match
   $level ... nesting level (depth) of $tag
		 nesting is arbitrary limited to 10 levels
*/
define("ZERO_LEVEL", 0);
define("NESTED_LEVEL", 1);

function SetHTMLOutputMode( $tag, $tagtype, $level ) {
  global $stack;
  $retvar = '';

  if( $level > 10 )
    // arbitrarily limit tag nesting
    //ExitWiki(gettext ("Nesting depth exceeded in SetHTMLOutputMode"));
    // Now, instead of crapping out when we encounter a deeply
    // nested list item, we just clamp the the maximum depth.
    $level = 10;
  
  if( $tagtype == ZERO_LEVEL ) {
    // empty the stack until $level == 0;
    if ($tag == $stack->top())
      return; // same tag? -> nothing to do
    
    while ($stack->cnt() > 0) {
      $closetag = $stack->pop();
      $retvar .= "</$closetag>\n";
    }
   
    if ($tag) {
      $retvar .= "<$tag>\n";
      $stack->push($tag);
    }

  } else if ($tagtype == NESTED_LEVEL) {
    if( $level < $stack->cnt() ) {
      // $tag has fewer nestings (old: tabs) than stack,
      // reduce stack to that tab count
      while ($stack->cnt() > $level) {
	$closetag = $stack->pop();
	if( $closetag == false )
	  //echo "bounds error in tag stack";
	  break;
	
	$retvar .= "</$closetag>\n";
      }

      // if list type isn't the same,
      // back up one more and push new tag
      if ($tag != $stack->top()) {
	$closetag = $stack->pop();
	$retvar .= "</$closetag><$tag>\n";
	$stack->push($tag);
      }
      
    } else if ($level > $stack->cnt()) {
      // Test for and close top level elements which are not allowed to contain
      // other block-level elements.
      if ($stack->cnt() == 1 and
	  preg_match('/^(p|pre|h\d)$/i', $stack->top()))
	{
	  $closetag = $stack->pop();
	  $retvar .= "</$closetag>";
	}
      
      // we add the diff to the stack
      // stack might be zero
      if( $stack->cnt() < $level ) {
	while( $stack->cnt() < $level - 1 ) {
	  // This is a bit of a hack:
	  //
	  // We're not nested deep enough, and have to make up
	  // some kind of block element to nest within.
	  //
	  // Currently, this can only happen for nested list
	  // element (either <ul> <ol> or <dl>).  What we used
	  // to do here is to open extra lists of whatever
	  // type was requested.  This would result in invalid
	  // HTML, since and list is not allowed to contain
	  // another list without first containing a list
	  // item.  ("<ul><ul><li>Item</ul></ul>" is invalid.)
	  //
	  // So now, when we need extra list elements, we use
	  // a <dl>, and open it with an empty <dd>.
	  
	  $retvar .= "<dl><dd>";
	  $stack->push('dl');
	}
	
	$retvar .= "<$tag>\n";
	$stack->push($tag);
      }
   
    } else { // $level == $stack->cnt()
      if ($tag == $stack->top())
	return; // same tag? -> nothing to do
      else {
	// different tag - close old one, add new one
	$closetag = $stack->pop();
	$retvar .= "</$closetag>\n";
	$retvar .= "<$tag>\n";
	$stack->push($tag);
      }
    }
  } else
    reportCriticalError("SetHTMLOutputMode", "Passed bad tag type: $tagtype");
  
  return $retvar;
}


/* [Aropa markup */
global $context;
$context = "";
function setContext( $c, $use = false ) {
  global $context;
  $c = trim( strip_tags($c) );
  if( $c != "" )
    $context = addcslashes( trim($c), "'\\");
  return $use ? $context : "";
}
/* Aropa markup] */ 

function PHPwikiToXml( $wiki ) {
  global $context;
  global $stack;
  $html = "";
  foreach( preg_split( "/[\n]/", $wiki ) as $wikiline ) {
    unset($tokens);
    unset($replacements);
    $ntokens = 0;
    $replacements = array();
    
    if( (!strlen($wikiline) || $wikiline == "\r") && $stack->bot() != 'pre' ) {
      // this is a blank line, send <p>
      $html .= SetHTMLOutputMode('', ZERO_LEVEL, 0);
      continue;
    }
    /* [Aropa markup
    // escape HTML metachars
    $wikiline = str_replace('&', '&amp;', $wikiline);
    $wikiline = str_replace('>', '&gt;',  $wikiline);
    $wikiline = str_replace('<', '&lt;',  $wikiline);
    Aropa markup]*/
    // %%% are linebreaks
    $wikiline = str_replace('%%%', '<br />', $wikiline);

    // bold italics (old way)
    $wikiline = preg_replace("|(''''')(.*?)(''''')|",
			     "<strong><em>\\2</em></strong>", $wikiline);

    // bold (old way)
    $wikiline = preg_replace("|(''')(.*?)(''')|",
			     "<strong>\\2</strong>", $wikiline);

    // bold
    $wikiline = preg_replace("|(__)(.*?)(__)|",
			     "<strong>\\2</strong>", $wikiline);
    
    // italics
    $wikiline = preg_replace("|('')(.*?)('')|",
			     "<em>\\2</em>", $wikiline);
    $wikiline = preg_replace("|(_)(.*?)(_)|",
			     "<em>\\2</em>", $wikiline);

    /* [Aropa markup */
    $wikiline = preg_replace("/{img\s*([^}]+)}/",
			     "<img src='\\1'>", $wikiline);
    $wikiline = preg_replace("/{-\s*\(\s*(\d+)\s*,\s*(\d+)\s*\)([^}]*)-}/e",
			     "'\\3<br/><img src=\'tinymce/jscripts/tiny_mce/plugins/aropa/img/textBlock.jpg\'"
			     . " style=\'height:' . (2*$1) . 'em; width:\\2ex\' name=\'' . \$context . '\' />'", $wikiline );
    $wikiline = preg_replace("/{-([^}]*)-}/e",
			     "'\\1<br/><img src=\'tinymce/jscripts/tiny_mce/plugins/aropa/img/textBlock.jpg\'/>'", $wikiline );
    $wikiline = preg_replace("/{([^}]*)=([^}]*)}/e",
			     "'<span><input type=\\'radio\\'/></span>'", $wikiline);
    $wikiline = preg_replace("/{([^}]*)\\*([^}]*)}/e",
			     "'<input type=\\'checkbox\\'/>'", $wikiline);
    // $wikiline = preg_replace("/{-([^}]*)-}/e",
    // 			     "'\\1<br/><img src=\'tinymce/jscripts/tiny_mce/plugins/aropa/img/textBlock.jpg\' name=\'' . \$context . '\' />'", $wikiline );
    // $wikiline = preg_replace("/{([^}]*)=([^}]*)}/e",
    // 			     "'<span><input type=\\'radio\\' name=\\'' . setContext('$1',true) . '\\' value=\'$2\'/></span>'", $wikiline);
    // $wikiline = preg_replace("/{([^}]*)\\*([^}]*)}/e",
    // 			     "'<input type=\\'checkbox\\' name=\\'' . setContext('$1',true) . '\\' value=\'$2\'/>'", $wikiline);
    $wikiline = preg_replace("/{([^}]*)}/e",
    			     "setContext(\"\\1\")", $wikiline );

    if (preg_match("/^(.*):\s*$/", $wikiline, $matches)) {
      $html .= SetHTMLOutputMode('dl', NESTED_LEVEL, 1);
      $html .= '<dt>' . $matches[1] . '</dt>';
      $wikiline = '';
    } elseif( $stack->bot() == 'dl' && preg_match( "/^\s+(.*)/", $wikiline, $matches ) ) {
      $html .= SetHTMLOutputMode('dd', NESTED_LEVEL, 2 );
      $html .= $matches[1];
      $wikiline = '';
    } elseif( preg_match( "/^\s*<\s*pre\s*>/", $wikiline ) ) {
      $html .= SetHTMLOutputMode('pre', ZERO_LEVEL, 0);
      $wikiline = '';
    } elseif( preg_match( "|^\s*<\s*/\s*pre\s*>|", $wikiline ) ) {
      $html .= SetHTMLOutputMode('p', ZERO_LEVEL, 0);
      $wikiline = '';
    } elseif( $stack->bot() == 'pre' ) {
      $wikiline = htmlspecialchars( $wikiline );
    } else
    /* Aropa markup] */ 

    //////////////////////////////////////////////////////////
    // unordered, ordered, and dictionary list  (using TAB)
    
    if (preg_match("/(^\t+)(.*?)(:\t)(.*$)/", $wikiline, $matches)) {
      // this is a dictionary list (<dl>) item
      $numtabs = strlen($matches[1]);
      $html .= SetHTMLOutputMode('dl', NESTED_LEVEL, $numtabs);
      $wikiline = '';
      if( trim($matches[2]) )
	$wikiline = '<dt>' . $matches[2];
      $wikiline .= '<dd>' . $matches[4];
    } elseif (preg_match("/(^\t+)(\*|\d+|#)/", $wikiline, $matches)) {
      // this is part of a list (<ul>, <ol>)
      $numtabs = strlen($matches[1]);
      if ($matches[2] == '*') {
	$listtag = 'ul';
      } else {
	$listtag = 'ol'; // a rather tacit assumption. oh well.
      }
      $wikiline = preg_replace("/^(\t+)(\*|\d+|#)/", "", $wikiline);
      $html .= SetHTMLOutputMode($listtag, NESTED_LEVEL, $numtabs);
      $html .= '<li>';

      //////////////////////////////////////////////////////////
      // tabless markup for unordered, ordered, and dictionary lists
      // ul/ol list types can be mixed, so we only look at the last
      // character. Changes e.g. from "**#*" to "###*" go unnoticed.
      // and wouldn't make a difference to the HTML layout anyway.

      // unordered lists <UL>: "*"
    } elseif (preg_match("/^([#*]*\*)[^#]/", $wikiline, $matches)) {
      // this is part of an unordered list
      $numtabs = strlen($matches[1]);
      $wikiline = preg_replace("/^([#*]*\*)/", '', $wikiline);
      $html .= SetHTMLOutputMode('ul', NESTED_LEVEL, $numtabs);
      $html .= '<li>';
      
      // ordered lists <OL>: "#"
    } elseif (preg_match("/^([#*]*\#)/", $wikiline, $matches)) {
      // this is part of an ordered list
      $numtabs = strlen($matches[1]);
      $wikiline = preg_replace("/^([#*]*\#)/", "", $wikiline);
      $html .= SetHTMLOutputMode('ol', NESTED_LEVEL, $numtabs);
      $html .= '<li>';

      // definition lists <DL>: ";text:text"
    } elseif (preg_match("/(^;+)(.*?):(.*$)/", $wikiline, $matches)) {
      // this is a dictionary list item
      $numtabs = strlen($matches[1]);
      $html .= SetHTMLOutputMode('dl', NESTED_LEVEL, $numtabs);
      $wikiline = '';
      if( trim($matches[2]) )
	$wikiline = '<dt>' . $matches[2];
      $wikiline .= '<dd>' . $matches[3];
      
      //////////////////////////////////////////////////////////
      // remaining modes: preformatted text, headings, normal text	
      /*
    } elseif (preg_match("/^\s+/", $wikiline)) {
      // this is preformatted text, i.e. <pre>
      $html .= SetHTMLOutputMode('pre', ZERO_LEVEL, 0);
      */
    } elseif (preg_match("/^\s*(!{1,3})[^!]/", $wikiline, $whichheading)) {
      // lines starting with !,!!,!!! are headings
      if($whichheading[1] == '!') $heading = 'h3';
      elseif($whichheading[1] == '!!') $heading = 'h2';
      elseif($whichheading[1] == '!!!') $heading = 'h1';
      $wikiline = preg_replace("/^!+/", '', $wikiline);
      /* [Aropa markup */ 
      setContext( $wikiline );
      /* Aropa markup] */ 
      $html .= SetHTMLOutputMode($heading, ZERO_LEVEL, 0);
      
    } elseif (preg_match('/^-{4,}\s*(.*?)\s*$/', $wikiline, $matches)) {
      // four or more dashes to <hr>
      // <hr> can not be contained in a
      $html .= SetHTMLOutputMode('', ZERO_LEVEL, 0) . "<hr>\n";
      if( ($wikiline = $matches[1]) != '' )
	$html .= SetHTMLOutputMode('p', ZERO_LEVEL, 0);

    } else
	// it's ordinary output if nothing else
	$html .= SetHTMLOutputMode('p', ZERO_LEVEL, 0);
    
    $html .= $wikiline . "\n";
  }
  
  $html .= SetHTMLOutputMode('', ZERO_LEVEL, 0);
 
  return $html;
}
