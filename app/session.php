<?php
/*
    Copyright (C) 2018 John Hamer <J.Hamer@cs.auckland.ac.nz>

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

function my_session_open( $save_path, $session_name ) {
  return true;
}


function my_session_close( ) {
  return true;
}


function my_session_read( $id ) {
  $db = new mysqli(AROPA_DB_HOST, AROPA_DB_USER, AROPA_DB_PASSWORD, AROPA_DB_DATABASE);
  if ($db->connect_errno)
    return '';
  $rs = $db->query('SELECT data FROM Session WHERE id=' . quote_smart( $id ));
  if ($rs)
    $row = $rs->fetch_row();
  return $row ? gzuncompress( $row[0] ) : '';
}


function my_session_write( $id, $data ) {
  $userID = isset( $_SESSION['userID'] ) ? (int)$_SESSION['userID'] : 0;
  $db = new mysqli(AROPA_DB_HOST, AROPA_DB_USER, AROPA_DB_PASSWORD, AROPA_DB_DATABASE);
  return $db->connect_errno == 0 &&
    $db->query("REPLACE INTO Session (id,userID,data,lastUsed,ip) VALUES ("
	       . quote_smart( $id )     . ','
	       . $userID . ','
	       . quote_smart( gzcompress($data, 9) )   . ','
	       . quote_smart( time( ) ) . ','
	       . quote_smart( $_SERVER['REMOTE_ADDR'] )
	       . ')') !== false;
}


function my_session_destroy( $id ) {
  $db = new mysqli(AROPA_DB_HOST, AROPA_DB_USER, AROPA_DB_PASSWORD, AROPA_DB_DATABASE);
  return $db->connect_errno == 0 && $db->query( "DELETE FROM Session WHERE id=" . quote_smart( $id ) ) !== false;
}


function my_session_gc( $maxlifetime ) {
  $db = new mysqli(AROPA_DB_HOST, AROPA_DB_USER, AROPA_DB_PASSWORD, AROPA_DB_DATABASE);
  if ($db->connect_errno) return;
  $threshold = time( ) - $maxlifetime;
  $db->real_query( "SELECT id, userID, lastUsed, ip FROM Session"
			 . ' WHERE userID IS NULL OR lastUsed < ' . quote_smart( $threshold ));
  $rs = $db->use_result();
  $ids = array( );
  $logouts = array( );
  while( $row = $rs->fetch_assoc() ) {
    if( ! empty( $row['userID'] ) )
      $logouts[] = '('
	. quote_smart( $row['userID'] ) . ','
	. quote_smart( date('Y-m-d H:i:s', $row['lastUsed'] ) ) . ','
        . quote_smart( $row['ip'] ) . ','
	. '\'logout\''
	. ')';
    $ids[] = quote_smart( $row['id'] );
  }
  $rs->free_result();
  
  if( ! empty( $logouts ) )
    $db->query( "INSERT INTO SessionAudit (userID,eventTime,ip,event)"
		. ' VALUES ' . join(',', $logouts));

  if( empty( $ids ) )
    return true;
  else
    return $db->query('DELETE FROM Session WHERE id IN (' . join(',', $ids) . ')') !== false;
}

function currentSessions( ) {
  $db = new mysqli(AROPA_DB_HOST, AROPA_DB_USER, AROPA_DB_PASSWORD, AROPA_DB_DATABASE);
  if ($db->connect_errno) return;
  $rs = $db->real_query("SELECT uident, userID, lastUsed, ip FROM Session NATURAL JOIN User"
			. ' WHERE userID IS NOT NULL ORDER BY lastUsed DESC');
  $rs = $db->use_result();
  $sessions = array( );
  while( $row = $rs->fetch_assoc() )
    $sessions[] = $row;
  $rs->free_result();
  return $sessions;
}


function showCurrentUsers( ) {
  $gethost = isset( $_REQUEST['showhost'] );
  $host = $gethost
    ? callback_url( _('Host'), "showCurrentUsers", array('title'=>_("Click to show IP address")))
    : callback_url( _('IP'), "showCurrentUsers&showhost", array('title'=>_('Click to show host name')));
  $table = table( HTML::tr( HTML::th('Name'), HTML::th('When'), HTML::th($host) ) );
  foreach( currentSessions( ) as $s )
    $table->pushContent( HTML::tr( HTML::td( $s['uident'] ),
                                   HTML::td( formatTimeString( $s['lastUsed'] ) ),
                                   HTML::td( $gethost ? gethostbyaddr( $s['ip'] ) : $s['ip'] )) );
  return HTML( HTML::h1( 'Current users' ), $table, BackButton() );
}

if( defined( "AROPA_SESSION_NAME" ) )
  session_name( AROPA_SESSION_NAME );
else
  session_name('AROPA');

session_set_save_handler( 'my_session_open',
                          'my_session_close',
                          'my_session_read',
                          'my_session_write',
                          'my_session_destroy',
                          'my_session_gc' );
