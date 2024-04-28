<?php
/*
    Copyright (C) 2015 John Hamer <J.Hamer@cs.auckland.ac.nz>

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

class Groups {
  var $userToGroup;
  var $groupToMembers;

  var $userIDtoUident;
  var $uidentToUserID;
  var $groupIDtoGname;
  var $gnameToGroupID;

  function __construct( $assmtID = false ) {
    $this->userToGroup     = array( );
    $this->groupToMembers  = array( );
    $this->userIDtoUident = array( );
    $this->uidentToUserID = array( );
    $this->groupIDtoGname = array( );
    $this->gnameToGroupID = array( );

    if( $assmtID !== false ) {
      ensureDBconnected( 'Groups::Groups' );

      $rs = checked_mysql_query( 'SELECT DISTINCT GroupUser.userID, -GroupUser.groupID as groupID, IFNULL(uident, CONCAT("u-", GroupUser.userID)) AS uident, gname FROM GroupUser'
				 . ' LEFT JOIN User ON GroupUser.userID = User.userID'
				 . ' inner join `Groups` on GroupUser.groupID = `Groups`.groupID'
                                 . " WHERE `Groups`.assmtID = $assmtID" );
      while( $row = $rs->fetch_assoc() ) {
	$this->addUserToGroup( $row['userID'], $row['groupID'] );
	$this->userIDtoUident[ $row['userID'] ] = $row['uident'];
	$this->uidentToUserID[ $row['uident'] ] = $row['userID'];
	$this->groupIDtoGname[ $row['groupID'] ] = $row['gname'] ? $row['gname'] : "Group$row[groupID]";
	$this->gnameToGroupID[ $row['gname'] ] = $row['groupID'];
      }
    }
  }

  function addUserToGroup( $userID, $groupID ) {
    if( ! isset( $this->groupToMembers[ $groupID ] ) )
      $this->groupToMembers[ $groupID ] = array( );

    $this->deleteFromAllGroups( $userID );

    $this->groupToMembers[ $groupID ][] = $userID;
    $this->userToGroup[ $userID ] = $groupID;
  }
   
  function deleteFromAllGroups( $userID ) {
    if( isset( $this->userToGroup[ $userID ] ) ) {
      $groupID = $this->userToGroup[ $userID ];
      $pos = array_search( $r, $this->groupToMembers[ $groupID ] );
      if( $pos !== false )
	array_splice( $this->groupToMembers[ $groupID ], $pos, 1 );
      unset( $this->userToGroup[ $userID ] );
    }
  }

  function members( $groupID ) {
    if( isset( $this->groupToMembers[ $groupID ] ) )
      return $this->groupToMembers[ $groupID ];
    else
      return array( $groupID );
  }
   
  function overlap( $g1, $g2 ) {
    $m1 = $this->members( $g1 );
    $m2 = $this->members( $g2 );
    foreach( $m1 as $m )
      if( in_array( $m, $m2 ) )
	return true;
    return false;
  }

  function save( $assmtID ) {
    $values = array();
    $newUsers = array();
    $courseId = fetchOne("select courseID from Assignment where assmtID = $assmtID", 'courseID');
    $userClass = fetchAll("select userID from UserCourse where courseID = $courseId", 'userID');
    foreach ($this->userToGroup as $userId => $groupId) {
      $values[] = "(" . -$groupId . ", $userId)";
      if (!in_array($userId, $userClass))
	$newUsers[] = "($courseID, $userId, 1)";
    }

    checked_mysql_query("delete from GroupUser where assmtID = $assmtId");
    if (!empty($values))
      checked_mysql_query('insert into GroupUser (groupID, userID) values ' . join(',', $values));
    if (!empty($newUsers))
      checked_mysql_query('insert into UserCourse (courseID, userID, roles) values ' . join(',', $newUsers));
  }
}
