<?php
function cloneClass( ) {
  list( $baseCourseID, $newCourseID, $unamePat ) = checkREQUEST( '_base', '_dest', 'p' );

  // ALSO:
  // UPDATE Essay SET whenUploaded = NOW()-INTERVAL FLOOR( (14-RAND()*3)*60*24) MINUTE, description=REPLACE(description,'RGU','GLA') WHERE assmtID=327;
  // UPDATE Assignment SET  allocationsDone ='2012-08-03 06:12' WHERE assmtID=327;

  set_time_limit( 600 );

  if( $baseCourseID == $newCourseID )
    return warning("Base and new class cannot be the same");

  $unamePat .= '%02d';

  $userMap = array( );

  $db = ensureDBconnected();

  foreach( fetchAll( "SELECT userID FROM UserCourse WHERE courseID=$baseCourseID AND roles=1" ) as $uc )
    mapUser( $uc['userID'], $userMap, $unamePat );

  $assmtMap = array();
  foreach( fetchAll( "SELECT * FROM Assignment WHERE courseID=$baseCourseID order by assmtID asc" ) as $assmt ) {
    $baseAssmtID = $assmt['assmtID'];
    unset( $assmt['assmtID'] );
    $assmt['courseID'] = $newCourseID;
    if (isset($assmtMap[$assmt['isReviewsFor']]))
      $assmt['isReviewsFor'] = $assmtMap[$assmt['isReviewsFor']];
    else
      unset($assmt['isReviewsFor']);

    checked_mysql_query( makeInsertQuery( 'Assignment', $assmt ) );
    $newAssmtID = $db->insert_id;
    $assmtMap[$baseAssmtID] = $newAssmtID;
    foreach( fetchAll( "SELECT * FROM Essay WHERE assmtID=$baseAssmtID" ) as $essay ) {
      unset( $essay['essayID'] );
      $essay['assmtID'] = $newAssmtID;
      $essay['author'] = mapUser( $essay['author'], $userMap, $unamePat );
      checked_mysql_query( makeInsertQuery( 'Essay', $essay ) );
    }

    foreach( fetchAll( "SELECT * FROM Allocation WHERE assmtID=$baseAssmtID" ) as $alloc ) {
      $baseAllocID = $alloc['allocID'];
      unset( $alloc['allocID'] );
      $alloc['assmtID'] = $newAssmtID;
      $alloc['author'] = mapUser( $alloc['author'], $userMap, $unamePat );
      $alloc['reviewer'] = mapUser( $alloc['reviewer'], $userMap, $unamePat );
      checked_mysql_query( makeInsertQuery( 'Allocation', $alloc ) );
      $newAllocID = $db->insert_id;

      foreach( fetchAll( "SELECT * FROM Comment WHERE allocID=$baseAllocID" ) as $comment ) {
	$comment['allocID'] = $newAllocID;
	$comment['madeBy'] = mapUser( $comment['madeBy'], $userMap, $unamePat );
	checked_mysql_query( makeInsertQuery( 'Comment', $comment ) );
      }
    }
  }

  $values = array( );
  foreach( $userMap as $newUserID )
    $values[] = "($newUserID, $newCourseID)";
  if( ! empty( $values ) )
    checked_mysql_query( 'INSERT IGNORE INTO UserCourse (userID, courseID) VALUES ' . join($values, ',') );

  redirect( 'home' );
}

function mapUser( $userID, &$map, $unamePat ) {
  require_once 'users.php';
  if( ! isset( $map[$userID] ) )
    $map[ $userID ] = uidentToUserID( sprintf( $unamePat, count( $map ) + 1 ) );
  return $map[ $userID ];
}
