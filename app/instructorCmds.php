<?php
/*
  Copyright (C) 2012 John Hamer <J.Hamer@acm.org>

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
  General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307
  USA
*/

if( $gHaveInstructor ) {
  $CmdActions['activate']
    = array( 'call'=>'activateAssignment',
	     'load'  =>'viewAssignment.php' );
  $CmdActions['deactivate']
    = array( 'call'=>'deactivateAssignment',
	     'load'  =>'viewAssignment.php' );
  $CmdActions['delete']
    = array( 'call'=>'deleteAssignment',
	     'load'  =>'viewAssignment.php' );
  $CmdActions['newAssignment']
    = array( 'load'  =>'editAssignment.php' );
  $CmdActions['createReview']
    = array( 'load'  =>'editAssignment.php' );  
  $CmdActions['editAssignment']
    = array( 'load'  =>'editAssignment.php' );
  $CmdActions['saveAssignment']
    = array( 'load'  =>'editAssignment.php' );
  
  $CmdActions['editGroups']
    = array( 'title' =>'Edit Groups',
	     'load'  =>'editGroups.php' );
  $CmdActions['saveGroups']
    = array( 'load'  =>'editGroups.php' );

  $CmdActions['setCategory']
    = array( 'load' => 'viewAssignment.php');

  $CmdActions['setupAllocation']
    = array( 'load'  =>'doAllocation.php' );
  
  $CmdActions['allocateNormally']
    = array( 'load'  => 'doAllocation.php' );
  $CmdActions['saveNormalAllocations']
    = array( 'load'  => 'doAllocation.php' );
  $CmdActions['allocateManually']
    = array( 'load'  => 'doAllocation.php' );
  $CmdActions['saveManualAllocations']
    = array( 'load'  => 'doAllocation.php' );
  $CmdActions['allocateUsingStreams']
    = array( 'load'  => 'doAllocation.php' );
  $CmdActions['saveStreamAllocations']
    = array( 'load'  => 'doAllocation.php' );
  $CmdActions['allocateGroups']
    = array( 'load'  => 'doAllocation.php' );
  $CmdActions['getGroups']
    = array( 'load' => 'doAllocation.php' );
  $CmdActions['saveAllocateGroups']
    = array( 'load'  => 'doAllocation.php' );
  $CmdActions['allocateRatingReview']
    = array( 'load'  => 'doAllocation.php' );
  $CmdActions['adjustAllocations']
    = array( 'load'  => 'doAllocation.php' );
  $CmdActions['regenerateAllocations']
    = array( 'load'  => 'doAllocation.php' );
  $CmdActions['saveAdjustAllocations']
    = array( 'load'  => 'doAllocation.php' );

  $CmdActions['editClass']
    = array( 'load'=>'editCourses.php');
  $CmdActions['classNameInUse']
    = array( 'load'=>'editCourses.php');
  $CmdActions['cloneClass']
    = array( 'load'=>'editCourses.php');
  $CmdActions['resetClassAccess']
    = array('load'=>'editCourses.php');

  $CmdActions['jsonUser']
    = array( 'load'=>'editUser.php');
  $CmdActions['quickClassList']
    = array( 'load'=>'editUser.php');
  $CmdActions['LoadClassCatalog']
    = array( 'load'=>'editUser.php');
  $CmdActions['editUser']
    = array( 'load'=>'editUser.php');
  $CmdActions['saveUser']
    = array( 'load'=>'editUser.php');
  $CmdActions['resetPassword']
    = array( 'load'=>'editUser.php',
	     'title'=>_('Reset password'));

  $CmdActions['checkNames']
    = array( 'load'=>'users.php' );

  $CmdActions['addExtensions']
    = array( 'load'  =>'extensions.php' );
  $CmdActions['saveExtensions']
    = array( 'load'  =>'extensions.php' );
  $CmdActions['jsonExtn']
    = array( 'load' => 'extensions.php' );
  $CmdActions['jsonHasUploaded']
    = array( 'load' => 'extensions.php' );

  $CmdActions['saveRubric']
    = array( 'load'  =>'editRubric.php' );
  $CmdActions['labelRubricA']
    = array( 'load'  =>'editRubric.php' );

  $CmdActions['updateCommentItem']
    = array( 'load' => 'monitor-reviewing.php' );

  $CmdActions['instructorAuthor']
    = array( 'load' => 'viewAssignment.php' );

  $CmdActions['loginAsStudent']
    = array( 'name'  =>'Student View',
	     'load' => 'loginAsStudent.php' );
  $CmdActions['jsonIdent']
    = array( 'load' => 'loginAsStudent.php' );
}

if( $gHaveInstructor || $gHaveGuest ) {
  $CmdActions['editRubricA']
    = array( 'load'  =>'editRubric.php' );
  $CmdActions['editRubricS']
    = array( 'load'  =>'editRubric.php' );
  $CmdActions['rawRubric']
    = array( 'load'  =>'editRubric.php' );

  $CmdActions['list']
    = array( 'call'=>'listAssignments',
	     'load'  =>'listAssignments.php' );
  $CmdActions['viewAsst']
    = array( 'call'=>'viewAssignment',
	     'load'  =>'viewAssignment.php' );

  $CmdActions['manageGroups']
    = array( 'title' =>'Manage Groups',
	     'load'  =>'editGroups.php' );

  $CmdActions['downloadUploads']
    = array( 'load'  =>'downloadUploads.php' );
  $CmdActions['timeOnTask']
    = array( 'load'  =>'timeOnTask.php' );
  
  $CmdActions['viewSubmissions']
    = array( 'load'  =>'monitor-reviewing.php' );
  $CmdActions['viewAllFeedback']
    = array( 'load'  =>'monitor-reviewing.php' );
  $CmdActions['authorFeedback']
    = array( 'load'  =>'monitor-reviewing.php' );
  $CmdActions['reviewerFeedback']
    = array( 'load'  =>'monitor-reviewing.php' );
  $CmdActions['showReviewer']
    = array( 'load'  =>'monitor-reviewing.php' );
  $CmdActions['toggleLockReviews']
    = array( 'load'  =>'monitor-reviewing.php' );
  $CmdActions['showIndividual']
    = array( 'load'  =>'monitor-reviewing.php' );
  $CmdActions['showEssay']
    = array( 'load'  =>'monitor-reviewing.php' );
  $CmdActions['downloadByID']
    = array( 'load'  =>'monitor-reviewing.php' );
  $CmdActions['downloadReviewing']
    = array( 'load'  =>'monitor-reviewing.php' );
  if( class_exists('XSLTProcessor') ) {
    $CmdActions['feedbackDocx']
      = array( 'load'  =>'monitor-reviewing.php' );
    $CmdActions['feedbackXlsx']
      = array( 'load'  =>'monitor-reviewing.php' );
  }
  
  $CmdActions['download']
    = array( 'load'  =>'download.php' );
    
  $CmdActions['viewAllocations']
    = array( 'load'  =>'monitor-reviewing.php' );

  $CmdActions['calcGrades']
    = array( 'load'  =>'calcGrades.php' );
  $CmdActions['downloadGrades']
    = array( 'load'  =>'calcGrades.php' );
  $CmdActions['fullMarkCSV']
    = array( 'load'  =>'calcGrades.php' );
  $CmdActions['jsonGrades']
    = array( 'load'  =>'calcGrades.php' );
  $CmdActions['commentsByAlloc']
    = array( 'load'  =>'calcGrades.php' );

  $CmdActions['showUsers']
    = array( 'name'=>'View users',
	     'menu'=>'advanced',
	     'load'=>'editUser.php');

  $CmdActions['calcTags']
    = array( 'load'  =>'tags.php' );
}
