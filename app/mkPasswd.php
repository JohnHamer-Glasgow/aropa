<?php
//- File: mkPasswd.php
//- Purpose: Generate a random (or pseudo-random) password consisting of
//- upper and lower-case ASCII letters and digits.

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

define('PW_LENGTH', 7);

function intsToString( $ints ) {
  $s = "";
  foreach( $ints as $i ) {
    if( $i < 26 )
      $s .= chr( $i + ord('a') );
    elseif( $i < 52 )
      $s .= chr( $i - 26 + ord('A') );
    elseif( $i < 62 )
      $s .= chr( $i - 52 + ord('0') );
  }
  return $s;
}

function mkPasswd( ) {
  $ints = array( );
  if( ini_get('allow_url_fopen') )
    //- Ask www.random.org for PW_LENGTH quality random integers between 0 and 61
    $ints = explode( "\t", file_get_contents('http://www.random.org/cgi-bin/randnum?num=' . PW_LENGTH .'&min=0&max=62&col=' . PW_LENGTH) );
  
  while( count( $ints ) < PW_LENGTH )
    //- Fallback: use the PHP random number generator
    $ints[] = mt_rand( 0, 61 );
  
  return intsToString( $ints );
}
$passwd = mkPasswd( );
echo "<q>$passwd</q>";
