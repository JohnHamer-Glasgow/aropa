<?php
/*
  Copyright (C) 2012 John Hamer <J.Hamer@acm.org>

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

global $fieldAlias;
$fieldAlias
//- These fields (keys) are renamed through the map identified by the value
= array( 'userID'   => 'userID', 'who'=>'userID', 'author'=>'userID', 'reviewer'=>'userID', 'owner'=>'userID', 'madeBy'=>'userID', 'participant'=>'userID', 'cuserID'=>'userID',
	 'instID'   => 'instID',
	 'courseID' => 'courseID',
	 'assmtID'  => 'assmtID', 'isReviewsFor'=>'assmtID',
	 'essayID'  => 'essayID',
	 'rubricID' => 'rubricID', 'copiedFrom'=>'rubricID',
	 'allocID'  => 'allocID' );

global $shortCode;
$shortCode
= array( 'userID'   => 'U',
	 'instID'   => 'I',
	 'courseID' => 'C',
	 'assmtID'  => 'A',
	 'rubricID' => 'R',
	 'essayID'  => 'E',
	 'allocID'  => 'L' );

  
function dumpTable( $table, $cond, &$map, $key = null ) {
  ensureDBconnected( 'dumpTable' );
  
  $rsC = checked_mysql_query( "SHOW COLUMNS FROM $table", 'unbuffered' );
  $colType = array( );
  $keyType = array( );
  while( $c = $rsC->fetch_assoc() ) {
    $colType[ $c['Field'] ] = $c['Type'];
    $keyType[ $c['Field'] ] = $c['Key'];
  }
  $rsC->free_result();

  //- The MySQL server imposes a limit on the length of a packet, typically 1Mb.
  //- Blobs larger than this need to be managed in chunks.
  $MAX_SQL_PACKET = 1048576/2 - 1000; //- 1Mb (default), less room for quoting and syntax

  if( $table == 'Essay' && ! isset( $_REQUEST['all'] ) )
    $rs = checked_mysql_query( "SELECT essayID, assmtID, reqIndex, author, 'inline-text' AS extn, description, whenUploaded, lastDownloaded, url, false AS compressed, 'Omitted from backup' AS essay, tag, false AS overflow, false AS isPlaceholder FROM Essay $cond", 'unbuffered' );
  else
    $rs = checked_mysql_query( "SELECT $table.* FROM $table $cond", 'unbuffered' );
  $nRow = 0;
  $vs = array( );
  $len = 0;
  $keys = array( -1 );
  
  global $fieldAlias;
  global $shortCode;

  while( $row = $rs->fetch_assoc() ) {
    if( $key ) {
      $map[ $key ][ $row[$key] ] = $nRow + 1;
      $keys[] = $row[$key];
    }
    
    $v = array( );
    $blob = array( );
    $primaryKey = array( );
    foreach( $row as $f => $data ) {
      
      if( $keyType[ $f ] == 'PRI' )
	//- We need to track the primary key, in case there is a large
	//- blob to insert.  Q: What happens if the table has no
	//- primary key?  Then it better not have a blob column!
	$primaryKey[] = $f . '=' . quote_smart( $data );
      
      if( $data == null )
	$v[] = 'NULL';
      else if( isset( $fieldAlias[ $f ] ) && ! ($table == 'Allocation' && $data < 0) ) {
	//- The allocation table stores negative values in reviewer
	//- and author when referring to a group.  GroupIDs are
	//- relative to the assmtID, so we don't need to map them.
	$fa = $fieldAlias[ $f ];
	if( isset( $map[ $fa ][ $data ] ) )
	  $v[] = '@' . $shortCode[ $fa ] . '+' . $map[ $fa ][ $data ];
	else
	  $v[] = 'NULL'; //***
      } else if( empty( $data ) && $colType[ $f ] == 'datetime' )
	//- fetch_assoc reads NULL datetime fields as the empty
	//- string.  MySQL turns the empty string into an "error
	//- date", 0000-00-00 00:00:00.  We don't want that.
	$v[] = 'NULL';
      else if( isBlobby( $colType[ $f ] ) && strlen( $data ) > $MAX_SQL_PACKET ) {
	//- We insert an empty string now, in case the field has a NOT
	//- NULL attribute.
	$v[] = '';
	$blob[ $f ] = $data;
      } else
	$v[] = quote_smart( $data );
    }
    
    $values = '(' . join(',', $v ) . ")";
    $len += strlen($values);
    $vs[] = $values;
    if( ! empty( $blob ) || $len > $MAX_SQL_PACKET ) {
      echo "INSERT IGNORE INTO $table (" . join(',', array_keys($colType)) . ") VALUES\n"
	. join(",\n", $vs) . ";\n";

      foreach( $blob as $f => $data ) {
	for( $start = 0; $start < strlen( $data ); $start += $MAX_SQL_PACKET )
	  echo "UPDATE $table SET $f = CONCAT($f,\n"
	    . quote_smart( substr( $data, $start, $MAX_SQL_PACKET ) )
	    . ') WHERE ' . join( 'AND', $primaryKey )
	    . ";\n";
      }
      $vs = array( );
      $len = 0;
    }
    $nRow++;
  }
  if( ! empty( $vs ) )
    echo "INSERT IGNORE INTO $table (" . join(',', array_keys($colType)) . ") VALUES\n" . join(",\n", $vs) . ";\n";
  $rs->free_result();

  echo sprintf( "-- %s, rows: %d\n\n", $table, $nRow);
  return '(' . join(',', $keys) . ')';
}


function exportData( ) {
  $date = date('Y-m-d');
  ini_set( 'memory_limit', '64M' );
  while( ob_end_clean( ) )
    //- Discard any earlier HTML or other headers
    ;
  header( 'Content-Type: text/plain' );
  header( "Content-Disposition: attachment; filename=aropa-data-inst-$_SESSION[instID]-$date.sql;" );
  header( 'Content-Transfer-Encoding: binary' );
  //  header( 'Connection: close');
  session_write_close( );

  echo sprintf( "-- Backup of Aropa database, taken by %s on %s\n",
		$_SESSION['uident'],
		date('Y-m-d H:i:s') );

  dumpTable( 'Institution', "WHERE instID = $_SESSION[instID]", $map );
  echo "SELECT @I:=MAX(instID) FROM Institution;\n";
  echo "SELECT @U:=IFNULL(MAX(userID),0) FROM User;\n";
  echo "SELECT @C:=IFNULL(MAX(courseID),0) FROM Course;\n";
  echo "SELECT @A:=IFNULL(MAX(assmtID),0) FROM Assignment;\n";
  echo "SELECT @E:=IFNULL(MAX(essayID),0) FROM Essay;\n";
  echo "SELECT @R:=IFNULL(MAX(rubricID),0) FROM Rubric;\n";
  echo "SELECT @L:=IFNULL(MAX(allocID),0) FROM Allocation;\n";

  $map = array( 'userID'    => array( ),
		'instID'    => array( $_SESSION['instID'] => 0 ),
		'courseID'  => array( ),
		'assmtID'   => array( ),
		'rubricID'  => array( ),
		'essayID'   => array( ),
		'allocID'   => array( ));

  dumpTable( 'User',         "WHERE instID = $_SESSION[instID]", $map, 'userID' );
  dumpTable( 'Rubric',
	     'INNER JOIN Assignment a ON a.rubricID = Rubric.rubricID'
	     . ' INNER JOIN Course c ON a.courseID=c.courseID'
	     . " WHERE instID = $_SESSION[instID]",
	     $map,
	     'rubricID' );
  $courseIDs = dumpTable( 'Course',     "WHERE instID=$_SESSION[instID]", $map, 'courseID' );
  $assmtIDs  = dumpTable( 'Assignment', "WHERE courseID IN $courseIDs",   $map, 'assmtID'  );
  $allocIDs  = dumpTable( 'Allocation', "WHERE assmtID IN $assmtIDs",     $map, 'allocID'  );
  dumpTable( 'UserCourse',   "WHERE courseID IN $courseIDs", $map );
  dumpTable( 'Extension',    "WHERE assmtID IN $assmtIDs", $map );
  dumpTable( 'Reviewer',     "WHERE assmtID IN $assmtIDs", $map );
  dumpTable( 'Author',       "WHERE assmtID IN $assmtIDs", $map );
  dumpTable( 'Stream',       "WHERE assmtID IN $assmtIDs", $map );
  dumpTable( '`Groups`',     "WHERE assmtID IN $assmtIDs", $map );
  dumpTable( 'Essay',        "WHERE assmtID IN $assmtIDs", $map, 'essayID' );
  dumpTable( 'GroupUser',    "WHERE assmtID IN $assmtIDs", $map );
  dumpTable( 'Comment',      "WHERE allocID IN $allocIDs", $map );
  exit;
}

function isBlobby( $col ) {
  return $col == 'mediumblob'
    ||   $col == 'mediumtext'
    ||   $col == 'longblob'
    ;
}
