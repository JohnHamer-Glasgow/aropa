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

function changeDatabase( ) {
  ensureDBconnected( 'changeDatabase' );

  $ul = HTML::ul( );
  $rs = checked_mysql_query( 'SHOW DATABASES' );
  while( $row = $rs->fetch_row() ) {
    $d = $row[0];
    if( isAropaDatabase( $d ) ) {
      $li = HTML::li( callback_url( $d, 'setDatabase&database=' . $d ) );
      if( $d == AROPA_DB_DATABASE )
	$li->pushContent( ' (default)' );
      if( isset( $_SESSION['database'] ) && $d == $_SESSION['database'] )
	$li->pushContent( ' (currently selected)' );
      $ul->pushContent( $li );
    }
  }

  return HTML( HTML::h1( 'Change database' ),
               HTML::p( 'Select the database you wish to use:' ),
	       $ul );
}


function setDatabase( ) {
  list( $database ) = checkREQUEST( 'database' );

  $rs = checked_mysql_query( 'SHOW DATABASES' );
  while( $row = $rs->fetch_row() ) {
    $d = $row[0];
    if( isAropaDatabase( $d ) && $d == $database ) {
      logoutReviewer( );
      unset( $_SESSION['usingGroups'] );
      $_SESSION['database'] = $database;
      redirect( 'main' );
    }
  }
  securityAlert( 'Attempt to select unknown or invalid database - ' . $database );
}

function isAropaDatabase( $name ) {
  ensureDBconnected( 'isAropaDatabase' );
  static $aropaTables = array( 'user',
			       'userinstitution',
			       'usercourse',
			       'institution',
			       'course',
			       'assignment',
			       'lastmod',
			       'groups',
			       'extension',
			       'reviewer',
			       'essay',
			       'allocation',
			       'markaudit',
			       'comment',
			       'survey',
			       'participants',
			       'surveydata',
			       'surveycomment',
			       'session',
			       'sessionaudit',
			       'semester',
			       'rubric' );
  $rs = checked_mysql_query( "SHOW TABLES FROM $name" );
  $tables = array( );
  while( $row = $rs->fetch_row() )
    $tables[] = strtolower( $row[0] );
  $diff = array_diff( $aropaTables, $tables );
  return empty( $diff );
}
