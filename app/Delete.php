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

function hardDeleteAssignment( $assmtID ) {
  ensureDBconnected( 'hardDeleteAssignment' );

  checked_mysql_query( "DELETE FROM LastMod   WHERE assmtID = $assmtID" );
  checked_mysql_query( "DELETE FROM Extension WHERE assmtID = $assmtID" );
  checked_mysql_query( "DELETE FROM Author    WHERE assmtID = $assmtID" );
  checked_mysql_query( "DELETE FROM Stream    WHERE assmtID = $assmtID" );
  checked_mysql_query( "DELETE FROM `Groups`  WHERE assmtID = $assmtID" );
  checked_mysql_query( "DELETE FROM GroupUser WHERE assmtID = $assmtID" );
  checked_mysql_query( "DELETE FROM Reviewer  WHERE assmtID = $assmtID" );
  
  checked_mysql_query( "DELETE FROM Overflow USING Overflow LEFT JOIN Essay ON Overflow.essayID=Essay.essayID WHERE Essay.assmtID = $assmtID" );
  checked_mysql_query( "DELETE FROM Essay    WHERE assmtID = $assmtID" );
  
  checked_mysql_query( "DELETE FROM Comment      USING Comment       LEFT JOIN Allocation ON Comment.allocID=Allocation.allocID WHERE Allocation.assmtID = $assmtID" );
  checked_mysql_query( "DELETE FROM Allocation WHERE assmtID = $assmtID" );
  
  checked_mysql_query( "DELETE FROM Assignment WHERE assmtID = $assmtID" );
}

function hardDeleteClass( $classId ) {
  foreach( fetchAll("SELECT assmtID FROM Assignment WHERE courseID=$classId", 'assmtID' ) as $assmtId )
    hardDeleteAssignment( $assmtId );
  checked_mysql_query( "DELETE FROM UserCourse where courseID=$classId" );
  checked_mysql_query( "DELETE FROM Course WHERE courseID=$classId" );
}
