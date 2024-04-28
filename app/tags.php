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

/**
 * AJAX method.  Parses the input in $_REQUEST['tags'] and returns two
 * sets of people to populate the submission and review text boxes in
 * doAllocation.
 */
function calcTags( ) {
  ensureDBconnected( 'calcTags' );
  $rs = checked_mysql_query( 'SELECT assmtID, '
                             . 'aname,'
			     . 'isReviewsFor,'
                             . 'basepath,'
                             . 'authorsAreReviewers,'
                             . 'groups,'
                             . 'tags '
                             . 'FROM Assignment '
                             . 'WHERE assmtID = ' . quote_smart($assmtID));
  $assmt = $rs->fetch_assoc();
  if( $assmt ) {
    $tags  = explode(";", $_REQUEST['tags']);
    $tagsA = explode(",", $tags[0]);
    $tagsB = explode(",", $tags[1]);
    print( join("\n", findPeople($assmt, $tagsA))
	   . ";"
	   . join("\n", findPeople($assmt, $tagsB, $tagA)));
  }
  exit;
}


/**
 * Searches through Essay to find the people with the set of input
 * tags. When the tag "Not submission" is present, will instead find
 * people with tags that are not in $otherTags.
 */
function findPeople( $assmt, $tags, $otherTags = array( ) ) {
  if( in_array("All tags", $tags) )
    $restrictions = " AND tag != ''";
  else if( in_array("Not submission", $tags )) {
    if( in_array("All tags", $otherTags ))
      return array( );

    $restrictions = " AND tag NOT IN (" . join(",", array_map('quote_smart', $otherTags)) . ')';
  } else
    $restrictions = " AND tag IN (" . join(",", array_map('quote_smart', $tags)) . ')';

  $names = array( );
  $rs = checked_mysql_query( 'SELECT author from Essay WHERE assmtID = '
			     . quote_smart($assmt['assmtID'])
			     . $restrictions );
  while( $row = $rs->fetch_assoc() )
    $names[] = $row['author'];
  return array_unique( $names );
}
