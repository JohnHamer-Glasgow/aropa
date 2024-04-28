<?php
/*
    Copyright (C) 2013 John Hamer <J.Hamer@cs.auckland.ac.nz>

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

require_once 'Groups.php';

function editGroups( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  if( $assmt['reviewersAre'] != 'group' && $assmt['authorsAre'] != 'group' )
    return HTML( assmtHeading( _('Edit groups'), $assmt ),
		 warning( _('This assignment does not use groups.'),
			  _('You must first set the allocation type to Groups (under Setup Allocations).' )));

  $groups = new Groups( $assmtID );

  $groupMemberArea = HTML::textarea( array('name'=>'members', 'rows'=>20, 'cols'=>55) );
  foreach( $groups->groupToMembers as $g => $members ) {
    foreach( $members as $who )
      $groupMemberArea->pushContent( ' ', $groups->userIDtoUident[ $who ] );
    $groupMemberArea->pushContent( "\n" );
  }

  $groupNameArea = HTML::textarea( array('name'=>'gnames', 'rows'=>20, 'cols'=>10),
				   join( "\n", array_keys( $groups->gnameToGroupID ) ) );
  
  return HTML( assmtHeading( _('Groups'), $assmt ),
	       HTML::form( array('method' =>'post',
                                 'action' => "$_SERVER[PHP_SELF]?action=saveGroups&cid=$cid&assmtID=$assmtID" ),
                           HTML::p( _('Each line should contain a list of user identifiers, separated by space, comma or semi-colon.')),
			   HTML::p( _('Optionally, the name of the group can be entered in the corresponding line on the second text area.') ),
                           HTML::div( array('style'=>'float: left'), $groupMemberArea ),
			   HTML::div( array('style'=>'float: left'), $groupNameArea ),
                           submitButton( _('Save groups') ),
                           CancelButton()));
}



function saveGroups( ) {
  list( $cid, $assmtID, $members, $gnames ) = checkREQUEST( '_cid', '_assmtID', 'members', 'gnames' );
 
  ensureDBconnected( 'saveGroups' );

  $assmt = fetchOne( "SELECT groups FROM Assignment WHERE assmtID = $assmtID AND courseID = " . cidToClassId( $cid ) );
  if( ! $assmt )
    return warning( Sprintf_('There is no assignment #%d', $assmtID));

  $groups = array( );
  $uident = array( );
  foreach( explode( "\n", $members ) as $line ) {
    $line = trim($line);
    $names = preg_split( '/[[:space:],;]/', $line, -1, PREG_SPLIT_NO_EMPTY );
    $groups[] = $names;
    foreach( $names as $who )
      $uident[ $who ] = true;
  }

  $gvalues = array( );
  foreach( explode( "\n", $gnames ) as $lineno => $gname )
    $gvalues[] = "($assmtID," . ($lineno+1) . ',' . quote_smart( trim($gname) ) . ')';

  require_once 'users.php';
  $uids = identitiesToUserIDs( array_keys( $uident ) );
  $mvalues = array( );
  foreach( $groups as $lineno => $members ) {
    foreach( $members as $who )
      $mvalues[] = "($assmtID, " . ($lineno+1) . ',' . $uids[ $who ] . ')';
  }

  checked_mysql_query( "DELETE FROM `Groups` WHERE assmtID = $assmtID" );
  checked_mysql_query( "DELETE FROM GroupUser WHERE assmtID = $assmtID" );
  if( ! empty( $gvalues ) )
    checked_mysql_query( 'INSERT INTO `Groups` (assmtID, groupID, gname) VALUES ' . join(',', $gvalues) );
  if( ! empty( $mvalues ) )
    checked_mysql_query( 'INSERT INTO GroupUser (assmtID, groupID, userID) VALUES ' . join(',', $mvalues) );

  $others = fetchAll( 'SELECT uident FROM GroupUser g'
		      . ' LEFT JOIN Assignment a ON a.assmtID = g.assmtID'
		      . ' LEFT JOIN UserCourse u ON g.userID = u.userID AND a.courseID = u.courseID'
		      . ' LEFT JOIN User r ON g.userID = r.userID'
		      . " WHERE u.userID IS NULL AND g.assmtID = $assmtID", 'uident' );
  if( count( $others ) > 0 )
    addWarningMessage( _('The following names are not in the class list: ', join(',', $others )));

  $now = quote_smart(date('Y-m-d H:i:s'));
  checked_mysql_query( 'REPLACE INTO LastMod SET'
                       . " assmtID = $assmtID, lastMod = $now" );

  redirect( "viewAsst&assmtID=$assmtID&cid=$cid" );
}


function manageGroups( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  if( $assmt['groups'] == '1-1' )
    return HTML( assmtHeading( _('Edit groups'), $assmt ),
		 warning( _('This assignment does not use groups.'),
			  _('You must first edit the assignment and set the Groups field.' )),
		 BackButton());

  $groups = new Groups( $assmtID );

  $html = HTML( assmtHeading( _('Groups'), $assmt ) );  
  if( $assmt['groups'][0] == 'n' )
    $html->pushContent( $groups->showByAuthor( $cid, true ));

  if( $assmt['groups'][2] == 'n' )
    $html->pushContent( $groups->showByReviewer( $cid, true ));

  $html->pushContent(formButton(_('Edit groups'), "editGroups&cid=$cid&assmtID=$assmtID"),
		     BackButton());
  return $html;
}
