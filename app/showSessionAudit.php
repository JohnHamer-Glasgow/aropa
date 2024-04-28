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

define('ONE_BLOCK', 24*60*60 );
function showSessionAudit( ) {
  $now = time( );

  if (isset($_REQUEST['auditDate']))
    $auditMax = strtotime($_REQUEST['auditDate']);
  else if (isset($_REQUEST['auditMax']))
    $auditMax = (int)$_REQUEST['auditMax'];
  else
    $auditMax = $now;

  //- 1 day's worth of data?
  $auditMin = $auditMax - ONE_BLOCK;
  ensureDBconnected('showSessionAudit');

  if (isset($_REQUEST['auditUser']))
    $who = trim($_REQUEST['auditUser']);

  $html = HTML();

  $q = 'select longname, uident, unix_timestamp(eventTime) as eventTime, ip, event, browser, server'
    . ' from SessionAudit a left join User u on a.userID = u.userID'
    . ' left join Institution i on i.instID = u.instID';
  if (isset($who)) {
    $html->pushContent(HTML::h1('Session audit for ', HTML::q($who)));
    $q .= ' WHERE uident=' . quote_smart($who);
    $day = '';
  } else {
    $html->pushContent(HTML::h1('Session audit for 24 hours to ', formatTimeString($auditMax)));
    $q .= " WHERE UNIX_TIMESTAMP(eventTime) BETWEEN $auditMin and $auditMax";
    $day = date('M j, Y', $auditMin);
  }

  $rs = checked_mysql_query($q);
  $table = table(HTML::tr(HTML::th(_('Time')),
			  HTML::th(_('User')),
                          HTML::th(_('Institution')),
			  HTML::th(_('IP')),
			  HTML::th(_('Event')),
			  HTML::th(_('Server'))));
  
  while ($row = $rs->fetch_assoc()) {
    $when = $row['eventTime'];
    if (date('M j, Y', $when) != $day) {
      $fmt = 'g:ia M j, Y';
      $day = date('M j, Y', $when);
    } else
      $fmt = 'g:ia';

    $table->pushContent(HTML::tr(array('title'=>$row['browser']),
				 HTML::td(date($fmt, $row['eventTime'])),
				 HTML::td($row['uident']),
                                 HTML::td($row['longname']),
				 HTML::td($row['ip']),
				 HTML::td($row['event']),
				 HTML::td($row['server'])));
  }

  $buttons = HTML::div( );
  if( empty( $who ) ) {
    $buttons->pushContent(formButton(_('Previous day'),
                                     'showSessionAudit&auditMax=' . ($auditMax - ONE_BLOCK)));
    if( $auditMax < $now )
      $buttons->pushContent(formButton(_('Next day'),
                                       'showSessionAudit&auditMax=' . ($auditMax + ONE_BLOCK)));
  }

  $buttons->pushContent(HTML::form(array('method'=>'post',
                                         'action'=> $_SERVER['REQUEST_URI']),
                                   HTML::input(array('type'=>'submit',
                                                     'value'=>'Jump to date')),
                                   HTML::input(array('type'=>'date',
                                                     'name'=>'auditDate'))));
  
  $buttons->pushContent(HTML::form(array('method'=>'post',
					 'action'=> $_SERVER['REQUEST_URI']),
				   HTML::input(array('type'=>'submit',
						     'value'=>'Selected user')),
				   HTML::input(array('type'=>'text',
						     'class'=>'typeahead',
						     'name'=>'auditUser'))));
  autoCompleteWidget("jsonUser");
  $buttons->pushContent(formButton('Home', 'home'));
  $html->pushContent(HTML::tt($table), $buttons);
  return $html;
}
