<?php
/*
  Copyright (C) 2016 John Hamer <J.Hamer@cs.auckland.ac.nz>

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

if( $gHaveInstructor || $gHaveStudent || $gHaveMarker ) {
  $CmdActions['viewStudentAsst']
    = array( 'load'  =>'showAllocations.php' );
  $CmdActions['download']
    = array( 'load'  =>'download.php' );
  $CmdActions['select']
    = array( 'call'=>'selectAssignment',
	     'load'  =>'showAllocations.php' );
  $CmdActions['showComments']
    = array( 'title' =>_('Show reviewer comments'),
	     'load'  =>'showComments.php' );
  $CmdActions['showReviewerComments']
    = array( 'title' =>_('Show reviewer comments'),
	     'load'  =>'showReviewerComments.php' );
  $CmdActions['showReviewFile']
    = array( 'load'  =>'download.php' );
  $CmdActions['view']
    = array( 'call'=>'viewEssay',
	     'title' =>_('View submission'),
	     'load'  =>'viewEssay.php' );
  $CmdActions['viewAndMark']
    = array( 'title' =>_('View and mark submission'),
	     'load'  =>'viewEssay.php' );
  $CmdActions['mark']
    = array( 'call'=>'markEssay',
	     'title' =>_('Mark submission'),
	     'load'  =>'markEssay.php' );
  $CmdActions['showMarks']
    = array( 'title' =>'Show marks',
	     'load'  =>'updateEssay.php' );
  $CmdActions['lock']
    = array( 'call'=>'lockAllocations',
	     'load'  =>'markEssay.php' );
  $CmdActions['update']
    = array( 'call'=>'updateEssay',
	     'load'  =>'updateEssay.php' );
  $CmdActions['reviewUploads']
    = array( 'load'  =>'uploadSubmissions.php' );
  $CmdActions['upload']
    = array( 'load'  =>'uploadSubmissions.php' );
  $CmdActions['saveUploads']
    = array( 'load'  =>'uploadSubmissions.php' );
  $CmdActions['deleteUpload']
    = array( 'load'  =>'uploadSubmissions.php' );
  $CmdActions['respondToComments']
    = array( 'title' =>_('Respond to comments'),
	     'load'  =>'showComments.php' );
}
