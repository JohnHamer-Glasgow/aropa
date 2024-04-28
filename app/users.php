<?php
/*
    Copyright (C) 2016 John Hamer <J.Hamer@acm.org>

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
function normaliseUident( $username, $instID = null ) {
  $inst = getInstitution( $instID );

  $uident = strtolower(trim($username));
  if (institutionHasFeature('UIDENT_REGEX', $instID)) {
    if (institutionHasFeature('UIDENT_EXCEPT')
	&& !empty($inst->features['UIDENT_EXCEPT'])
	&& preg_match($inst->features['UIDENT_EXCEPT'], $uident))
      return $uident;

    if( preg_match( $inst->features['UIDENT_REGEX'], $uident, $matches) ) {
      foreach( $matches as $i => $match )
	if( $i > 0 && ! empty( $match ) ) {
	  if( institutionHasFeature( 'UIDENT_FORMAT', $instID ) ) {
	    $fmts = explode( ';',  $inst->features['UIDENT_FORMAT'] );
	    $fmt = count( $fmts ) >= $i ? $fmts[$i-1] : $fmts[ count($fmts) - 1 ];
	    return sprintf( $fmt, $match );
	  } else
	    return $match;
	}
    }
  }
  return $uident;
}

function uidentToUserID( $uident, $instID = null ) {
  $uident = trim( $uident );
  if( $uident == '' )
    return -1;
  else {
    $ids = identitiesToUserIDs( array( $uident ), $instID );
    return current($ids);
  }
}


function identitiesToUserIDs( $identities, $instID = null ) {
  return identitiesAndUsernamesToUserIDs( $identities, array( ), $instID );
}


function identitiesAndUsernamesToUserIDs( $identities, $usernames, $instID = null ) {
  if( empty( $identities ) )
    return array( );

  if( $instID == null )
    $instID = (int)$_SESSION['instID'];

  $normalised = array( );
  foreach( $identities as $ident )
    $normalised[ normaliseUident($ident, $instID ) ] = isset( $usernames[$ident] ) ? $usernames[$ident] : null;

  $db = ensureDBconnected('identitiesToUserIDs');
  $rs = checked_mysql_query(
    'select lower(uident) as uident, userID from User'
    . ' where uident in (' . join(',', array_map('quote_smart', array_keys($normalised))) . ')'
    . " and instID = $instID");
  /*
   The identitiesToUserIDs function *must* return an array of the same
   size as the input.  It's possible that identities have not yet
   registered, or we might be running a backup database with the User
   data stripped (if a User record is not found for a userID, Aropa will
   substitute "u-$userID" for the uident).

   If the identity doesn't exist in User, then we need to insert a new
   record so that there is a valid userID to return.  However, we
   don't do this for identities that look like "u-$userID".
  */
  $userIDs = array( );
  while( $row = $rs->fetch_assoc() )
    $userIDs[ $row['uident'] ] = $row['userID'];
  
  //- Any $identities remaining need to be created, except ones that
  //- are existing userIDs
  $identAsID = array( );
  $needed = array( );
  foreach( $normalised as $ident => $username )
    if( ! isset( $userIDs[ $ident ] ) ) {
      if( preg_match( '/^u-(\d)+$/', $ident, $match) )
	$identAsID[] = (int)$match[1];
      $needed[ $ident ] = $username;
    }

  if( ! empty( $identAsID ) ) {
    $rs2 = checked_mysql_query( 'SELECT userID FROM User'
				. ' WHERE userID IN (' . join(',', $identAsID) . ')'
				. $andInst );
    while( $row = $rs2->fetch_assoc() ) {
      $ident = 'u-' . $row['userID'];
      $userIDs[ $ident ] = $row['userID'];
      unset( $needed[ $ident ] );
    }
  }

  foreach( $needed as $uident => $username ) {
    $data = array( 'uident' => $uident,
		   'instID' => abs($instID) );
    if( ! empty( $username ) )
      $data['username'] = $username;
    checked_mysql_query( makeInsertIgnoreQuery('User', $data) );
    $userIDs[ $uident ] = $db->insert_id;
  }

  return $userIDs;
}


function linesToToUserIDs( $lines ) {
  $identities = array( );
  foreach( explode( "\n", $lines ) as $line ) {
    $who = trim($line);
    if( ! empty($who) && $who[0] != '#' )
      $identities[] = $who;
  }
  return identitiesToUserIDs( $identities );
}


function userIDsToIdentities( $userIDs ) {
  ensureDBconnected( 'userIDsToIdentities' );
  $identities = array( );
  if( ! empty( $userIDs ) ) {
    $rs = checked_mysql_query( 'SELECT uident, userID FROM User WHERE userID IN ('
			       . join(',', $userIDs) . ')');
    while( $row = $rs->fetch_assoc() )
      $identities[ $row['userID'] ] = $row['uident'];
  }
  return $identities;
}


function checkNames() {
  $cid = (int)$_REQUEST['cid'];

  $names = array( );
  foreach( preg_split( '/[[:space:],;]/', $_REQUEST['names'], -1, PREG_SPLIT_NO_EMPTY ) as $name )
    $names[ strtolower( $name ) ] = true;
    
  foreach( fetchAll( 'SELECT uident FROM UserCourse uc LEFT JOIN User u ON uc.userID = u.userID'
		     . ' WHERE uident IS NOT NULL AND (uc.roles&1) <> 0 AND courseID = ' . cidToClassId( $cid ),
		     'uident' ) as $uident ) {
    $uident = strtolower( $uident );
    unset( $names[ $uident ] );
  }

  if( ! empty( $names ) )
    echo json_encode( _('The following names do not appear in the class list: ') . join( ', ', array_keys( $names )));
  else
    echo json_encode(null);
  exit;
}
