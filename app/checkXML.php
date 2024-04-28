<?php
//- $Id: $

/*
    Copyright (C) 2005 John Hamer <J.Hamer@cs.auckland.ac.nz>

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

//- XML attributes permitted on any node
global $coreattr;
$coreattr = array('ID', 'CLASS', 'STYLE', 'TITLE' );

class XMLchecker {
  var $validNodes;
  var $errorList;

  function __construct( ) {
    $this->errorList = array( );

    $this->validNodes
      = array(
	      'ABBR'    => null,
	      'ACRONYM' => null,
	      'B'       => null,
	      'CITE'    => null,
	      'CODE'    => null,
	      'DFN'     => null,
	      'EM'      => null,
	      'FONT'    => array('FACE', 'SIZE', 'COLOR'),
	      'I'       => null,
	      'KBD'     => null,
	      'Q'       => null,
	      'SAMP'    => null,
	      'STRONG'  => null,
	      'VAR'     => null,
	      'SUB'     => null,
	      'SUP'     => null,
	      'BIG'     => null,
	      'SMALL'   => null,
	      'STRIKE'  => null,
	      'U'       => null,
	      'TT'      => null,

	      'IMG'   => array('SRC', 'HEIGHT', 'WIDTH', 'BORDER', 'ALT', 'ALIGN', 'HSPACE', 'VSPACE'),

	      'P'          => array('ALIGN'),
	      'BR'         => array('CLEAR'),
	      'ADDRESS'    => null,
	      'HR'         => array('WIDTH', 'SIZE', 'NOSHADE'),
	      'BLOCKQUOTE' => null,
	      'PRE'        => null,

	      'INS'     => null,
	      'DEL'     => null,

	      'DIV'   => array('ALIGN'),
	      'SPAN'  => array('ALIGN'),

	      'TABLE'    => array('BORDER', 'WIDTH', 'FRAME', 'CELLSPACING', 'CELLPADDING', 'RULES'),
	      'CAPTION'  => null,
	      'THEAD'    => null,
	      'TFOOT'    => array('ALIGN', 'VALIGN'),
	      'TBODY'    => array('ALIGN', 'VALIGN'),
	      'COLGROUP' => array('SPAN', 'WIDTH', 'ALIGN', 'VALIGN'),
	      'COL'      => array('SPAN', 'WIDTH', 'ALIGN', 'VALIGN'),
	      'TR'       => array('ALIGN', 'VALIGN'),
	      'TH'       => array('ROWSPAN', 'COLSPAN', 'ALIGN', 'VALIGN'),
	      'TD'       => array('ROWSPAN', 'COLSPAN', 'ALIGN', 'VALIGN'),

	      'UL'    => null,
	      'LI'    => null,
	      'OL'    => null,
	      'DL'    => null,
	      'DT'    => null,
	      'DD'    => null,

	      'H1'=>null,
	      'H2'=>null,
	      'H3'=>null,
	      'H4'=>null,
	      'H5'=>null,
	      'H6'=>null,
	      );
  }

  function startElement( $parser, $node, $attrs ) {
    if( ! array_key_exists( $node, $this->validNodes ) )
      //- unrecognised node
      $this->errorList[] = $node . " line " . xml_get_current_line_number( $parser );
    else {
      $spec = $this->validNodes[ $node ];
      global $coreattr;
      foreach( $attrs as $a => $aas )
	if( ! in_array( $a, $coreattr )
	    && ( empty( $spec ) || ! in_array( $a, $spec ) )
	    )
	  //- unrecognised attribute
	  $this->errorList[] = "$node.$a line " . xml_get_current_line_number( $parser );
    }
    return null;
  }

  function endElement( $parser, $name ) {
  }

  function characterData( $parser, $data ) {
  }

  function run( $data ) {
    $parser = xml_parser_create( );
    xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, true );
    xml_parser_set_option( $parser, XML_OPTION_SKIP_WHITE,   true );
    
    xml_set_element_handler( $parser,
			     array(&$this, 'startElement'),
			     array(&$this, 'endElement')
			     );
    xml_set_character_data_handler( $parser,
				    array(&$this, 'characterData')
				    );
    //xml_set_default_handler( $parser, 'default_handler' );
    
    if( !xml_parse( $parser, $data, true ) )
      $this->errorList[]
	= xml_error_string( xml_get_error_code( $parser ) )
	. " line " . xml_get_current_line_number( $parser );
    
    xml_parser_free( $parser );
  }
}

function default_handler( $parser, $data ) {
  echo "default_handler - $data\n";
}

//- $xml = new XMLchecker( );
//- 
//- $xml->run( "<div id='main'><b>
//- text in bold
//- </b>
//- <b border='1'>text in text</b>
//- <input class='main'
//-  type=\"radio\"
//-   value='2'>
//- </input>
//- </div>" );
//- 
//- echo "<ul>";
//- foreach( $xml->errorList as $err ) {
//-   echo "<li>$err</li>";
//- }
//- echo "</ul>";

$xml = new XMLchecker( );
echo $xml->run('<?xml version="1.0"?>
<!DOCTYPE foo [
<!ENTITY nbsp "NBSP entity">
<!ENTITY pound "POUND entity">
<!ENTITY Dagger "DAGGER entity">
]>
<div><p>Testing:</p>
<p><strong>bold</strong></p>
<p><em>italic</em></p>
<p><u>underline</u></p>
<p><strike>strike</strike></p>
<p>&nbsp;</p>
<hr width="67%" size="2" />
<div align="left">left aligned words and more words words and more words words and more words words and more words words and more words words and more words words and more words</div>
<p>&nbsp;</p>
<p align="center">center aligned words and more words words and more words words and more words words and more words words and more words words and more words words and more words <br /></p><p align="right">rightaligned words and more words words and more words words and more words words and more words words and more words words and more words words and more words</p>
<p align="justify">justified words and more words words and more words words and more words words and more words words and more words words and more words words and more words</p>
<ul>
<li>bullet list</li>
<li>second item</li>
</ul>
<ol>
<li>numeric list</li>
<li>second item</li>
</ol>
<p>paragraph</p>
<address>address</address>
<pre>preformatted - space  2   3    4     5     6      7        8</pre>
<h1>Heading 1</h1>
<h2>heading 2</h2>
<blockquote><p>indented</p>
<blockquote><p>indented again</p>
</blockquote><p>outdented</p>
</blockquote>
<p>&pound;&Dagger;</p>
<p><img src="tinymce/jscripts/tiny_mce/plugins/emotions/images/smiley-cry.gif" border="0" alt="Cry" title="Cry" />
 <br />
</p>
</div>' );

echo "<ul>";
foreach( $xml->errorList as $err ) {
  echo "<li>$err</li>";
}
echo "</ul>";
