<?php
/*
    Copyright (C) 2017 John Hamer <J.Hamer@cs.auckland.ac.nz>

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

function showReviewerComments( ) {
  list( $s, $cid, $aID ) = checkREQUEST( '_s', '_cid', '_aID' );
	
  if( ! isset( $_SESSION['allocations'][ $s ] ) )
    securityAlert( "unknown essay - $s" );
  
  $allocSession = $_SESSION['allocations'][ $s ];

  $assmt = false;
  if(isset($_SESSION['availableAssignments']))
    foreach( $_SESSION['availableAssignments'] as $a )
      if( $allocSession['assmtID'] == $a['assmtID'] ) {
	$assmt = $a;
	break;
      }
  if( ! $assmt )
    reportCriticalError( 'showReviewerComments',
			 'Unable to find assignment ' . $allocSession['assmtID'] );

  $now = date('Y-m-d H:i:s');
  if( ! emptyDate( $assmt['feedbackEnd'] )
      && $assmt['feedbackEnd'] < $now )
    //- Hmm: showFeedback() already makes this check.  The caller may
    //- have clicked on an old link.
    return warning( _('Sorry, feedback on this assignment is no longer available for viewing'));

  require_once 'BlockParser.php';
  
  $feedbackStart = chooseDate( $assmt['feedbackStart'],  $assmt['reviewEnd'], $now );
  if( $feedbackStart <= $now )
    $locked = '';
  else
    $locked = ' AND locked';
  
  $visibleReviewers = preg_split("/[ \t,;]+/", $assmt['visibleReviewers'], -1, PREG_SPLIT_NO_EMPTY);
  $visibleRs = '';
  $joinUser = '';
  if( count( $visibleReviewers ) != 0 ) {
    $visibleRs = ' and ( u.uident in ('
      .   join(',', array_map('quote_smart', $visibleReviewers))
      .   ") OR l.reviewer = $_SESSION[userID] )";
    $joinUser = ' inner join User u on l.reviewer = u.userID';
  }

  if ($assmt['reviewersAre'] == 'group')
    $rs = checked_mysql_query("select * from Allocation"
			      . " where author = $allocSession[author]"
			      . " and assmtID = $allocSession[assmtID]"
			      . ' order by allocID asc');
  else
    $rs = checked_mysql_query("select l.* from Allocation l $joinUser"
			      . " inner join UserCourse uc on l.reviewer = uc.userID and uc.courseID = $assmt[courseID]"
			      . " where l.author = $allocSession[author]"
			      . " and l.assmtID = $allocSession[assmtID]"
			      . " and uc.roles in (1, 2)"
			      . $locked
			      . $visibleRs
			      . ' order by allocID ASC' );
  
  $reviews = array();
  while ($alloc = $rs->fetch_assoc()) {
    $marks = array();
    if ($assmt['showMarksInFeedback'])
      parse_str($alloc['marks'] ?? '', $marks);
    
    $comments  = array();
    $cs = checked_mysql_query('select item, comments'
                              . " from Comment where allocID = $alloc[allocID]"
                              . ' order by whenMade desc');
    while ($row = $cs->fetch_assoc())
      $comments[$row['item']][] = $row;

    $reviewFiles  = array();
    $cs = checked_mysql_query('select item, description'
                              . " from ReviewFile where allocID = $alloc[allocID]"
                              . ' order by whenUploaded desc');
    while ($row = $cs->fetch_assoc())
      $reviewFiles[$row['item']][] = $row;
    
    if (!empty($marks) || !empty($comments) || !empty($reviewFiles)) {
      list($xml) = TransformRubricByID($assmt['rubricID'], $marks, $comments, $reviewFiles, 'show');
      $reviews[] = HTML::form($xml);
    }
  }

  switch (count($reviews)) {
  case 0:
    $content = _('There is no feedback available.');
    break;
  case 1:
    $content = $reviews[0];
    break;
  default:
    extraHeader('bootstrap.min.js', 'js');
    $content = HTML::div(array('id'=>'feedback',
                            'class'=>'panel-group'));
    foreach ($reviews as $n => $review)
      $content->pushContent(HTML::div(array('class'=>'panel panel-default'),
                                      HTML::div(array('class'=>'panel-heading'),
                                                HTML::h4(array('class'=>'panel-title'),
                                                         HTML::a(array('role'=>'button',
                                                                       'data-toggle'=>'collapse',
                                                                       'data-parent'=>'#feedback',
                                                                       'href'=>"#u-$n"),
                                                                 "Reviewer " . ($n + 1)))),
                                      HTML::div(array('id'=>"u-$n",
                                                      'class'=>'panel-collapse collapse'),
                                                $review)));
  }

  extraHeader('$("input").click(function(){return false;})', 'onload');
  return HTML(HTML::h2(Sprintf_('Reviews of submission #%d', $allocSession['allocIdx'])),
	      $assmt['showMarksInFeedback'] ? '' : message(_('Note that this assignment has been configured not to show marks in this feedback.')),
              $content,
              HTML::br(),
              BackButton());
}
