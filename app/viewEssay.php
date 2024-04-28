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

function viewAndMark( ) {
  list( $s, $cid ) = checkREQUEST( '_s', '_cid' );

  $_REQUEST['omitMenu'] = 1;

  if( isset( $_REQUEST['orientation'] ) )
    setPreference( 'orientation', $_REQUEST['orientation'] );

  $orientation = getOrientation( );
  return HTML::FRAMESET( array($orientation=>"50%, 50%"),
                         HTML::FRAME( array('src'=>$_SERVER['PHP_SELF'] . "?action=view&s=$s&cid=$cid&omitMenu") ),
                         HTML::FRAME( array('src'=>$_SERVER['PHP_SELF'] . "?action=mark&s=$s&cid=$cid&omitMenu") ));
}

function getOrientation( ) {
  return getPreference( 'orientation', 'cols' ) == 'cols' ? 'cols' : 'rows';
}


function viewEssay() {
  list($s, $aID, $cid) = checkREQUEST('_s', '_aID', '_cid');

  if (empty($_SESSION['allocations'][$s]))
    securityAlert("unknown essay - $s");

  require_once 'download.php';

  $alloc = $_SESSION['allocations'][$s];
  $author = $alloc['author'];
  $assmtID = $alloc['assmtID'];

  if (!isset($_SESSION['availableAssignments'][$aID]))
    return reportCriticalError('viewEssay', 'cannot find assignment matching allocation');

  $assmt = $_SESSION['availableAssignments'][$aID];

  // Add this string to any URLs that we don't want cached
  $anticache = dechex(time());
  $back = isset($_REQUEST['omitMenu'])
    ? ''
    : formButton(_('Back'), "viewStudentAsst&cid=$cid&aID=$aID&$anticache");

  $identity = userIdentity($author, $assmt, 'author');
  $idx = $alloc['allocIdx'];
  $title = $assmt['anonymousReview'] ? "allocation #$idx" : $identity;
  return HTML(HTML::h1(Sprintf_('Documents for %s', $title)),
	      downloadables($author, $identity, $idx, $assmt, $cid),
	      $back);
}
