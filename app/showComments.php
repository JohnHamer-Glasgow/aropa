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

function showComments() {
  list($aID, $cid) = checkREQUEST('_aID', '_cid');
  require_once 'BlockParser.php';

  if (!isset($_SESSION['availableAssignments'][$aID]))
    securityAlert('bad aID');
  $assmt = $_SESSION['availableAssignments'][$aID];

  $reviews = array();
  $seen = array();
  foreach ($_SESSION['allocations'] as $i => $alloc)
    if ($alloc['assmtID'] == $assmt['assmtID'] && !emptyDate($alloc['lastMarked']) && $alloc['has-feedback']) {
      $marks = array();
      if ($assmt['showMarksInFeedback'])
        parse_str($alloc['marks'] ?? '', $marks);
      
      $comments = array();
      $cs = checked_mysql_query('select item, comments'
                                . " from Comment where allocID = $alloc[allocID]"
                                . ' order by whenMade desc');
      while ($row = $cs->fetch_assoc())
        $comments[$row['item']][] = $row;

      $reviewFiles = array();
      $cs = checked_mysql_query('select item, reviewFileID, madeBy'
                                . " from ReviewFile"
				. " where allocID = $alloc[allocID]"
                                . ' order by whenUploaded desc');
      while ($row = $cs->fetch_assoc()) {
	$row['uident'] = userIdentity($row['madeBy'], $assmt, 'reviewer');
        $reviewFiles[$row['item']][] = $row;
      }

      if (!empty($marks) || !empty($comments) || !empty($reviewFiles)) {
        list($xml) = TransformRubricByID($assmt['rubricID'], $marks, $comments, $reviewFiles, 'show');
        $reviews[] = HTML::form($xml);
	$seen[] = $alloc['allocID'];
      }
    }

  if (!empty($seen))
    checked_mysql_query('update Allocation set lastSeen = now(), lastSeenBy = ' . (int)$_SESSION['userID']
			. ' where allocID in (' . join(',', $seen) . ')');
  
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
  return HTML(HTML::h2(_('Feedback on your submission')),
	      $assmt['showMarksInFeedback'] ? '' : message(_('Note that this assignment has been configured not to show marks in your feedback.')),
              $content,
              HTML::br(),
              BackButton());
}
