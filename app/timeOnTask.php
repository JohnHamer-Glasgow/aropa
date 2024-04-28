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
global $imageMap;
$imageMap = array( '[' => array(' '=>'[',
				'['=>'[',
				']'=>'#',
				'.'=>'[',
				'#'=>'#'),
		   ']' => array(' '=>']',
				'['=>'#',
				']'=>']',
				'.'=>']',
				'#'=>'#'),
		   '.' => array(' '=>'.',
				'['=>'[',
				']'=>']',
				'.'=>'.',
				'#'=>'#'),
		   ' ' => array(' '=>' ',
				'['=>'[',
				']'=>']',
				'.'=>'.',
				'#'=>'#')
		   );

function timeOnTask( ) {
  list( $assmtID, $cid ) = checkREQUEST( '_assmtID', '_cid' );

  $showDetail = ! isset( $_REQUEST['p'] );

  ensureDBconnected( 'timeOnTask' );

  $rs = checked_mysql_query( 'SELECT name, submissionEnd, reviewEnd FROM Assignment'
			     . ' WHERE assmtID = ' . quote_smart( $assmtID )
			     );
  $assmt = $rs->fetch_assoc();
  if( ! $assmt )
    return HTML::h2( Sprintf_('Error: there is no assignment #%d', $assmtID));


  if( function_exists( 'my_session_gc' ) )
    my_session_gc( (int)ini_get( 'session.gc_maxlifetime' ) );

  $tn = $assmt['reviewEnd' ];
  if( empty( $tn ) )
    $tn = date('Y-m-d H:i:s');
  $Tn = strtotime($tn);


  $t0 = $assmt['submissionEnd'];
  if( empty( $t0 ) )
    $t0 = date('Y-m-d H:i:s', $Tn - 10*24*60*60 );
  $T0 = strtotime($t0);

  $logins   = array( );
  $logouts  = array( );
  $activity = array( );

  require_once( 'Groups.php' );
  $groups = new Groups( $assmtID );

  $names = array( );
  $rs = checked_mysql_query( 'SELECT reviewer, lastViewed'
			     . ' FROM Allocation'
			     . ' WHERE lastViewed >= ' . quote_smart( $t0 )
			     . ' AND lastViewed <= '   . quote_smart( $tn )
			     . ' AND assmtID = '       . quote_smart( $assmtID ),
			     'unbuffered'
			     );
  while( $row = $rs->fetch_assoc() ) {
    $who = $row['reviewer'];
    //- If the assignment uses groups, the reviewer will be a group
    //- name.  We need to add all members of the group.
    if( isset( $groups->reviewGroup[ $who ] ) )
      $whos = $groups->reviewGroup[ $who ];
    else
      $whos = array( $who );

    foreach( $whos as $w ) {
      $names[ $w ] = 1;
      $when = $row['lastViewed'] - $T0;
      if( ! isset( $activity[ $w ] ) )
        $activity[ $w ] = array( );
      $activity[ $w ][] = $when;
    }
  }
  $rs->free_result();

  $rs = checked_mysql_query( 'SELECT userID, eventTime, event'
			     . ' FROM SessionAudit'
			     . ' WHERE eventTime >= ' . quote_smart( $t0 )
			     . ' AND   eventTime <= ' . quote_smart( $tn ),
			     'unbuffered'
			     );
  while( $row = $rs->fetch_assoc() ) {
    $who  = $row['userID'];
    if( ! isset( $names[ $who ] ) )
      continue;
    $when = $row['eventTime'] - $T0;
    if( $row['event'] == 'login' ) {
      if( ! isset( $logins[ $who ] ) )
	$logins[ $who ] = array( );
      $logins[ $who ][] = $when;
    } else {
      if( ! isset( $logouts[ $who ] ) )
	$logouts[ $who ] = array( );
      $logouts[ $who ][] = $when;
    }
  }
  $rs->free_result();

  $rs = checked_mysql_query( 'SELECT madeBy, whenMade FROM Comment'
			     . ' WHERE whenMade >= ' . quote_smart( $t0 )
			     . ' AND whenMade <= '   . quote_smart( $tn ),
			     'unbuffered'
			     );
  while( $row = $rs->fetch_assoc() ) {
    $who  = $row['madeBy'];
    if( ! isset( $names[ $who ] ) )
      continue;
    $when = $row['whenMade'] - $T0;
    if( ! isset( $activity[ $who ] ) )
      $activity[ $who ] = array( );
    $activity[ $who ][] = $when;
  }

  $width = 80; //160; //240;
  $trange = $Tn - $T0;
  $pre = HTML::pre( _('Start time '), $t0, "\n",
		    _('  End time '), $tn, "\n"
		    );
  if( $showDetail )
    $pre->pushContent( Sprintf_('Each . indicates activity lasting up to %s minutes',
				number_format( ($Tn - $T0)/$width/60, 1)),
		       "\n",
		       _('Legend: [ = login, ] = logout, . = activity, # = login/out'),
		       "\n",
		       str_repeat( ' ', 16 ),
		       '<' . str_pad( ' ' . (int)(($Tn - $T0)/60) . ' minutes ',
				      $width - 2, '-', STR_PAD_BOTH ),
		       ">\n"
		       );
  else
    $pre->pushContent( "\n", _('Name,Time(minutes)'), "\n" );
  
  ksort( $names );
  foreach( $names as $who => $dummy ) {
    $times = array( );
    if( isset( $activity[ $who ] ) )
      foreach( $activity[ $who ] as $t )
	$times[ $t ] = '.';

    if( isset( $logouts[ $who ] ) )
      foreach( $logouts[ $who ] as $t )
	$times[ $t ] = ']';

    if( isset( $logins[ $who ] ) )
      foreach( $logins[ $who ] as $t )
	$times[ $t ] = '[';
    $times[ $trange ] = ' ';

    ksort( $times );

    $total = 0;
    $state = 'inactive';
    $start        = $times[0];
    $lastActivity = $times[0];
    
    $str = str_repeat( ' ', $width+1 );
    foreach( $times as $t => $c ) {
      if( $c == ']' || $t - $lastActivity > 30*60 ) {
	//- A gap of 30 minutes with no record of activity suggests a
	//- passive logout.

	$total += max( $lastActivity - $start, 10*60 );
	//- Add a minimum of 10 minutes for each activity.  This may
	//- have been a login followed by viewing several allocations.
	//- If the allocations were viewed again later, the record of
	//- activity after the login is lost.
	$start = $t;
      }
      $lastActivity = $t;

      $n = (int)( $width * $t / $trange );
      global $imageMap;
      $str[$n] = $imageMap[$c][$str[$n]];
    }
    if( $showDetail )
      $pre->pushContent( str_pad( $who . '(' . (int)($total/60) . ')', 15 ) . ':' . $str . "\n" );
    else
      $pre->pushContent( $who, ',', (int)($total/60), "\n" );
  }
  if( $showDetail )
    $callback = callback_url( _('Show times only'), "timeOnTask&assmtID=$assmtID&p" );
  else
    $callback = callback_url( _('Show time chart'), "timeOnTask&assmtID=$assmtID" );

  return HTML( HTML::h1( _('Time on task for assignment '), HTML::q( $assmt['aname'] ) ),
	       HTML::p( $callback ),
	       $pre );
}
