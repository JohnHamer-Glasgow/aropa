<?php
/*
    Copyright (C) 2010 John Hamer <J.Hamer@cs.auckland.ac.nz>

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
$coreattr = array('ID', 'CLASS', 'STYLE', 'TITLE');

global $validNodes;
$validNodes
= array('A'       => array('HREF'),
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

        'P'          => null,
        'BR'         => array('CLEAR'),
        'ADDRESS'    => null,
        'HR'         => array('WIDTH', 'SIZE', 'NOSHADE'),
        'BLOCKQUOTE' => null,
        'PRE'        => null,

        'INS' => null,
        'DEL' => null,

        'DIV'   => null,
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

        'UL' => null,
        'LI' => null,
        'OL' => null,
        'DL' => null,
        'DT' => null,
        'DD' => null,

        'H1'=>null,
        'H2'=>null,
        'H3'=>null,
        'H4'=>null,
        'H5'=>null,
        'H6'=>null,
        );

global $validEntities;
$validEntities
= array('AElig', 'Aacute', 'Acirc', 'Agrave', 'Alpha', 'Aring',
        'Atilde', 'Auml', 'Beta', 'Ccedil', 'Chi', 'Dagger', 'Delta',
        'ETH', 'Eacute', 'Ecirc', 'Egrave', 'Epsilon', 'Eta', 'Euml',
        'Gamma', 'Iacute', 'Icirc', 'Igrave', 'Iota', 'Iuml', 'Kappa',
        'Lambda', 'Mu', 'Ntilde', 'Nu', 'OElig', 'Oacute', 'Ocirc',
        'Ograve', 'Omega', 'Omicron', 'Oslash', 'Otilde', 'Ouml', 'Phi',
        'Pi', 'Prime', 'Psi', 'Rho', 'Scaron', 'Sigma', 'THORN', 'Tau',
        'Theta', 'Uacute', 'Ucirc', 'Ugrave', 'Upsilon', 'Uuml', 'Xi',
        'Yacute', 'Yuml', 'Zeta', 'aacute', 'acirc', 'acute', 'aelig',
        'agrave', 'alefsym', 'alpha', 'amp', 'and', 'ang', 'aring',
        'asymp', 'atilde', 'auml', 'bdquo', 'beta', 'brvbar', 'bull',
        'cap', 'ccedil', 'cedil', 'cent', 'chi', 'circ', 'clubs',
        'cong', 'copy', 'crarr', 'cup', 'curren', 'dArr', 'dagger',
        'darr', 'deg', 'delta', 'diams', 'divide', 'eacute', 'ecirc',
        'egrave', 'empty', 'emsp', 'ensp', 'epsilon', 'equiv', 'eta',
        'eth', 'euml', 'euro', 'exist', 'fnof', 'forall', 'frac12',
        'frac14', 'frac34', 'frasl', 'gamma', 'ge', 'gt', 'hArr',
        'harr', 'hearts', 'hellip', 'iacute', 'icirc', 'iexcl',
        'igrave', 'image', 'infin', 'int', 'iota', 'iquest', 'isin',
        'iuml', 'kappa', 'lArr', 'lambda', 'lang', 'laquo', 'larr',
        'lceil', 'ldquo', 'le', 'lfloor', 'lowast', 'loz', 'lrm',
        'lsaquo', 'lsquo', 'lt', 'macr', 'mdash', 'micro', 'middot',
        'minus', 'mu', 'nabla', 'nbsp', 'ndash', 'ne', 'ni', 'not',
        'notin', 'nsub', 'ntilde', 'nu', 'oacute', 'ocirc', 'oelig',
        'ograve', 'oline', 'omega', 'omicron', 'oplus', 'or', 'ordf',
        'ordm', 'oslash', 'otilde', 'otimes', 'ouml', 'para', 'part',
        'permil', 'perp', 'phi', 'pi', 'piv', 'plusmn', 'pound',
        'prime', 'prod', 'prop', 'psi', 'quot', 'rArr', 'radic', 'rang',
        'raquo', 'rarr', 'rceil', 'rdquo', 'real', 'reg', 'rfloor',
        'rho', 'rlm', 'rsaquo', 'rsquo', 'sbquo', 'scaron', 'sdot',
        'sect', 'shy', 'sigma', 'sigmaf', 'sim', 'spades', 'sub',
        'sube', 'sum', 'sup', 'sup1', 'sup2', 'sup3', 'supe', 'szlig',
        'tau', 'there4', 'theta', 'thetasym', 'thinsp', 'thorn',
        'tilde', 'times', 'trade', 'uArr', 'uacute', 'uarr', 'ucirc',
        'ugrave', 'uml', 'upsih', 'upsilon', 'uuml', 'weierp', 'xi',
        'yacute', 'yen', 'yuml', 'zeta', 'zwj', 'zwnj'
        );


//- Used to filter HTML read from a form, to protect against attackers
//- sending potentially damaging HTML.  This version does not give any
//- warning if the HTML is bad; it just ignores tags and attributes
//- that are not known to be valid.  If the HTML is ill-formed, any
//- outstanding closing tags are added and the remaining input
//- ignored.
class XMLcleaner {
  var $cleanXML;
  var $openTags;
  var $pure;

  function __construct( ) {
    $this->cleanXML = "";
    $this->openTags = array( );
    $this->pure = true;
  }

  function startElement( $parser, $node, $attrs ) {
    global $validNodes;
    $okAttrs = "";
    if( array_key_exists( $node, $validNodes ) ) {
      $spec = $validNodes[ $node ];
      global $coreattr;
      foreach( $attrs as $a => $aas )
	if( in_array( $a, $coreattr )
	    || ( ! empty( $spec ) && in_array( $a, $spec ) )
	    )
	  $okAttrs .= " $a='" . str_replace( '\'', '&#39;', $aas ) . "'";
        else
          $this->pure = false;
      $this->cleanXML .= "<$node$okAttrs>";
      $this->openTags[] = $node;
    } else {
      //echo "Unrecognised: $node<br/>";
      $this->pure = false;
    }
  }

  function endElement( $parser, $node ) {
    global $validNodes;
    if( array_key_exists( $node, $validNodes ) ) {
      $this->cleanXML .= "</$node>";
      array_pop( $this->openTags );
    }
  }

  function characterData( $parser, $data ) {
    //- Something funny is going on with the XML parser: &lt; &gt; and
    //- &amp; are turned into <, > and &, while other entities appear as
    //- &nbsp; etc.  No doubt this feature will change in later
    //- versions, but that should not affect the code here (other than
    //- to make it redundant).
    switch( $data ) {
    case '<': $data = '&lt;'; break;
    case '>': $data = '&gt;'; break;
    case '&': $data = '&amp;'; break;
    }      
    $this->cleanXML .= $data;
  }

  function defaultHandler( $parser, $data ) {
    global $validEntities;
    if( substr($data, 0, 1) == "&"
        && substr($data, -1, 1) == ";"
        && in_array( substr($data, 1, -1), $validEntities )
        )
      $this->cleanXML .= $data;
    return true;
  }

  /*
  -- not needed
  function externalEntity( $parser, $name, $base, $system_id, $public_id ) {
    global $validEntities;
    if( ! in_array( $name, $validEntities ) )
      $validEntities[] = $name;
    return TRUE;
  }
  */

  function clean( $data ) {
    $parser = xml_parser_create( );
    xml_set_object($parser, $this);
    xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, true );
    xml_parser_set_option( $parser, XML_OPTION_SKIP_WHITE,   true );
    
    xml_set_element_handler( $parser, 'startElement', 'endElement' );
    xml_set_character_data_handler( $parser, 'characterData' );
    //xml_set_external_entity_ref_handler(  $parser, 'externalEntity' ); -- not needed
    xml_set_default_handler(        $parser, 'defaultHandler' );

    if( !xml_parse( $parser, preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data ), true ) ) {
      while( ! empty( $this->openTags ) )
	$this->cleanXML .= "</" . array_pop( $this->openTags ) . ">";
      $this->pure = false;
    }

    xml_parser_free( $parser );
    return $this->cleanXML;
  }
}


//- The XML parser bails if it encounters an undeclared entity such as
//- &amp;.  We could declare all the official, named HTML entities, but
//- there are a lot of them (see $validEntities).  This declareEntities
//- function returns code to declare just the ones that appear in $data.
//- Note that the XML parser seems to fail if the CDATA qualifier is
//- given, and in any event the entity value is ignored.  This is not a
//- problem, as defaultHandler picks up the entity name in the source,
//- and passes it through unchanged (provided it appears valid).
function declareEntities( $data ) {
  $entityDecl = "";
  for( $pos = strpos( $data, '&', 0 )
         ; $pos !== false
         ; $pos = strpos( $data, '&', $pos+1 )
       ) {
    $semi = strpos( $data, ';', $pos+1 );
    if( $semi !== false ) {
      global $validEntities;
      $entity = substr( $data, $pos+1, $semi-$pos-1 );
      if( in_array( $entity, $validEntities ) )
        $entityDecl .= "<!ENTITY $entity \"\">\n";
    }
  }
  return $entityDecl;
}


function cleanupHTML( $data ) {
  //$data = html_entity_decode( $data, ENT_QUOTES );
  $data = preg_replace('/(<|&lt;)!--.*?--(>|&gt;)/', '', $data);
  $xml = new XMLcleaner( );
  $clean = $xml->clean( '<?xml version=\'1.0\'?>
<!DOCTYPE foo [' . declareEntities( $data ) . ']>
<div>' . $data . '</div>' );
  return $xml->pure ? $data : $clean;
}

/*
function stripHTMLcomments( $html ) {
  while( ($p0 = strpos( $html, "<!--" )) !== false )
    $html = substr_replace( $html, '', $p0, strpos( $html, "-->", $p0+4 ) - $p0 + 3);
  return $html;
}
*/

//echo cleanupHTML( file_get_contents('../troublesome-msword-input.html') );
//echo '<pre>' . htmlentities(stripHTMLcomments( file_get_contents('../troublesome-msword-input.html') )) . '</pre>';
//echo '<pre>' . htmlentities(stripHTMLcomments( '<!--comment #1--><p>content that we want</p><!--comment #2--><p>more content</p>' )) . '</pre>';
//echo cleanupHTML( '<!--comment #1--><p>content that we want</p><!--comment #2--><p>more content</p>' );
//echo cleanupHTML( '<strong>&quot;quotation&quot;</strong> <br /><p><em>em</em></p><p>&lt;= arrow.</p><p>--&nbsp;--</p>' );

/*

//- echo cleanupHTML( "<b>
//- text in bold
//- </b>
//- <b class=\"'1'\">text in text</b>
//- <span class='main'
//-  type=\"radio\"
//-   value='2'>
//- </spann>" );
//- 

echo cleanupHTML( '<p>Testing:</p>
<p><strong>bold</strong></p>
<p><em>italic</em></p>
<p><u>underline</u></p>
<p><strike>strike</strike></p>
<p>&nbsp;</p>
<div align="left">left aligned words and more words words and more words words and more words words and more words words and more words words and more words words and more words</div>
<p>&nbsp;</p>
<p align="center">center aligned words and more words words and more words words and more words words and more words words and more words words and more words words and more words <br /></p>
<p align="right">rightaligned words and more words words and more words words and more words words and more words words and more words words and more words words and more words</p>
<p align="justify">justified words and more words words and more words words and more words words and more words words and more words words and more words words and more words</p>
<ul>
<li>bullet list</li>
<li>second item</li>
</ul>
<ol>
<li>numeric list</li>
<li>second item</li>
</ol><p>paragraph</p>
<address>address</address>
<pre>preformatted - space  2   3    4     5     6      7        8</pre>
<h1>Heading 1</h1>
<h2>heading 2</h2>
<blockquote>
<p>indented</p>
<blockquote>
<p>indented again</p>
</blockquote>
<p>outdented</p>
</blockquote>
<p><img src="tinymce/jscripts/tiny_mce/plugins/emotions/images/smiley-cry.gif" border="0" alt="Cry" title="Cry" />
<p>&lt;</p>
<p>&gt;</p>
<p>&amp;</p>
<br />
</p>');
*/
