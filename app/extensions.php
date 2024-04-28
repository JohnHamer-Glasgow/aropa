<?php
/*
    Copyright (C) 2018 John Hamer <J.Hamer@acm.org>

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

  // QueryTrace();

function addExtensions( ) {
  list( $assmt, $assmtID, $cid ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  if( strstr( $assmt['allocationType'], 'tag' ) !== false ) {
    $tagMessage  = message(_('This assignment uses tagged allocations.  Please indicate the tag associated with each submission extension.'));
    $tags = preg_split("/[;,[:space:]]/", $assmt['tags'], -1, PREG_SPLIT_NO_EMPTY);
  } else
    $tagMessage = '';

  $uploaded = fetchAll('select distinct author'
		       .' from Essay e inner join Extension x on e.assmtID = x.assmtID and e.author = x.who'
		       . " where x.assmtID = $assmtID and isPlaceholder = 0",
		       'author');

  $reviewed = fetchAll('select distinct reviewer'
		       . ' from Allocation a inner join Extension x on a.assmtID = x.assmtID and a.reviewer = x.who'
		       . " where x.assmtID = $assmtID and lastMarked is not null",
		       'reviewer');

  $rs = checked_mysql_query( 'select who, submissionEnd, reviewEnd, whenMade, ifnull(uident, concat("u-", userID)) as uident, tag'
			     . ' from Extension e left join User u on e.who = u.userID'
			     . " where assmtID = $assmtID");
  datetimeWidget();

  $tbody = HTML::tbody();
  $n = 0;
  while ($row = $rs->fetch_assoc())
    $tbody->pushContent(makeExtensionRow($n++,
					 $uploaded,
					 $reviewed,
					 $tags,
					 $row['uident'],
					 $row['who'],
					 $row['submissionEnd'],
					 $row['reviewEnd'],
					 $row['tag'],
					 $row['whenMade']));
  
  if( ! $tbody->isEmpty( ) )
    $instruct = HTML::p( _('To cancel an extension, clear the student name') );
  else
    $instruct = '';

  $tbody->pushContent(makeExtensionRow($n++, $uploaded, $reviewed, $tags, '', 0, '', '', '', ''));

  $thead = HTML::tr( HTML::th(_('Student')));
  if (!$assmt['isReviewsFor'])
    $thead->pushContent(HTML::th(_('Submission due')));
  $thead->pushContent(HTML::th(_('Reviewing due')));
  if (isset($tags))
    $thead->pushContent(HTML::th(_('Tag')));
  $thead->pushContent(HTML::th(_('Date granted')));
  $table = table(array('id'=>'extensions'), HTML::thead($thead), $tbody);

  extraHeader('extension.js', 'script');
  extraHeader('$("input[name^=\'uident\']").change(updateTitles)', 'onload');
  extraHeader('$("#extensions input").blur(addRow)', 'onload');
  autoCompleteWidget("jsonExtn&cid=$cid&assmtID=$assmtID");
  return
    HTML(assmtHeading( _('Manage extensions'), $assmt ),
	 pendingMessages(),
	 HTML::form(array('name'=>'edit',
			  'method'=>'post',
			  'class'=>'form',
			  'action'=>"$_SERVER[PHP_SELF]?action=saveExtensions" ),
		    HiddenInputs(array('assmtID'=>$assmtID, 'cid'=>$cid)),
		    $tagMessage,
		    $table,
		    $instruct,
		    ButtonToolbar(submitButton(_('Save changes')),
				  formButton(_('Cancel'), "viewAsst&assmtID=$assmtID&cid=$cid"))));
}

function makeExtensionRow($n, $uploaded, $reviewed, $tags, $uident, $who, $submissionEnd, $reviewEnd, $tag, $whenMade) {
  $uidentAttr = array('class'=>'typeahead',
		      'data-provide'=>'typeahead');
  $dateAttr = array();
  $sdate = ToRFC3339($submissionEnd);
  $rdate = ToRFC3339($reviewEnd);
  $submitDetails =
    $sdate == ''
    ? array()
    : (in_array($who, $uploaded)
       ? array('readonly'=>'readonly', 'title'=>sprintf(_('%s has uploaded a submission'), $uident))
       : array('title'=>sprintf(_('No submission has been uploaded by %s yet'), $uident)));
  $reviewDetails =
    $rdate == ''
    ? array()
    : (in_array($who, $reviewed)
       ? array('readonly'=>'readonly', 'title'=>sprintf(_('%s has written at least one review'), $uident))
       : array('title'=>sprintf(_('No reviews have been written by %s yet'), $uident)));
  $tr = HTML::tr( HTML::td(inputBox('text', "uident[$n]", $uident, $uidentAttr)));
  if (!$assmt['isReviewsFor'])
    $tr->pushContent(HTML::td(inputBox('datetime-local-like', "sdate[$n]", $sdate, $dateAttr + $submitDetails)));
  $tr->pushContent(HTML::td(inputBox('datetime-local-like', "rdate[$n]", $rdate, $dateAttr + $reviewDetails)));
  if (isset($tags)) {
    $sel = HTML::select(array('name'=>"tag[$n]"));
    foreach ($tags as $t)
      $sel->pushContent(HTML::option(array('selected'=>$tag==$t), $t));
    $tr->pushContent(HTML::td($sel));
  }
  
  $tr->pushContent(HTML::td(inputBox('datetime-local-like',
				     "whenMade[$n]",
				     ToRFC3339($whenMade, $_SESSION['TZ']),
				     array('value'=>$whenMade, 'readonly'=>'readonly'))));
  return $tr;
}

function inputBox($type, $name, $value, $attr = array()) {
  $attr['type']  = $type;
  $attr['value'] = $value;
  if ($name[0] == '*') {
    $name = substr($name, 1);
    $attr['id'] = $name;
  }
  $attr['name'] = $name;
  $input = HTML::input($attr);
  $input->setInClass('form-control');
  return $input;
}

function jsonExtn( ) {
  list( $cid, $assmtID, $term ) = checkREQUEST( '_cid', '_assmtID', 'query' );
  $matches = fetchAll( 'select uident from User u inner join UserCourse uc on u.userID = uc.userID'
		       . ' where uident LIKE ' . quote_smart( "%$term%" ) 
		       . ' and courseID = ' . cidToClassId( $cid )
		       . ' and (roles&3) <> 0'
		       . ' limit 20',
		       'uident');
  if( count( $matches ) < 20 )
    $matches += fetchAll( 'select uident from Author a inner join User u on userID = author'
			  . ' where uident like ' . quote_smart( "%$term%" ) 
			  . " and assmtID = $assmtID"
			  . ' limit ' . (20 - count($matches)),
			  'uident' );
  if( count( $matches ) < 20 )
    $matches += fetchAll( 'select uident from Reviewer inner join User on userID = reviewer'
			  . ' where uident like ' . quote_smart( "%$term%" ) 
			  . " and assmtID = $assmtID"
			  . ' limit '. (20 - count($matches)),
			  'uident' );
  echo json_encode( $matches );
  exit;
}

function jsonHasUploaded() {
  list($cid, $assmtID, $uident) = checkREQUEST('_cid', '_assmtID', 'uident');
  if (trim($uident) == '') {
    $hasUploaded = '';
    $hasReviewed = '';
  } else {  
    require_once('users.php');
    $uident = normaliseUident($uident);
    $classID = cidToClassId($cid);
    $hasUploaded =
      fetchOne('select 1 from Essay e'
	       . ' inner join Assignment a on a.assmtID = e.assmtID'
	       . ' inner join User u on e.author = u.userID'
	       . " where e.assmtID = $assmtID and courseID = $classID and isPlaceholder = 0 and uident = " . quote_smart($uident))
      ? sprintf(_('%s has uploaded a submission'), $uident)
      : sprintf(_('No submission has been uploaded by %s yet'), $uident);
    $hasReviewed =
      fetchOne('select 1 from Allocation e'
	       . ' inner join User u on e.author = u.userID'
	       . " where e.assmtID = $assmtID and lastMarked is not null and uident = " . quote_smart($uident))
      ? sprintf(_('%s has written at least one review'), $uident)
      : sprintf(_('No reviews have been written by %s yet'), $uident);
  }

  echo json_encode(array($hasUploaded, $hasReviewed));
  exit;
}

function saveExtensions( ) {
  list( $assmt, $assmtID, $cid, $courseID ) = selectAssmt( );
  if( ! $assmt )
    return missingAssmt( );

  $now = quote_smart( date('Y-m-d H:i:s') );

  $authorList = array( );
  $reviewList = array( );
  $rs = checked_mysql_query( 'select author, uident'
			     . ' from Author inner join User on author = userID'
			     . " where assmtID = $assmtID" );
  while( $row = $rs->fetch_assoc() )
    $authorList[ strtolower($row['uident']) ] = $row['author'];
    
  $rs = checked_mysql_query( 'select reviewer, uident'
			     . ' from Reviewer inner join User on reviewer = userID'
			     . " where assmtID = $assmtID" );
  while( $row = $rs->fetch_assoc() )
    $reviewList[ strtolower($row['uident']) ] = $row['reviewer'];
  
  if( $assmt['authorsAre'] != 'other' || $assmt['reviewersAre'] != 'other' ) {
    $rs = checked_mysql_query( "select uc.userID, u.uident"
			       . ' from UserCourse uc inner join User u on uc.userID = u.userID'
			       . " where courseID = $courseID AND (roles&3)<>0" );
    while( $row = $rs->fetch_assoc() ) {
      if( $assmt['authorsAre'] != 'other' )
	$authorList[ strtolower($row['uident']) ] = $row['userID'];
      if( $assmt['reviewersAre'] != 'other' )
	$reviewList[ strtolower($row['uident']) ] = $row['userID'];
    }
  }

  $values = array( );
  $placeholders = array( );
  $warnings = false;
  require_once 'users.php';
  foreach( $_REQUEST['uident'] as $n => $uident ) {
    $uident = strtolower(normaliseUident($uident));
    if( empty( $uident ) )
      continue;

    if( isset( $authorList[ $uident ] ) )
      $who = $authorList[ $uident ];
    else if( isset( $reviewList[ $uident ] ) )
      $who = $reviewList[ $uident ];
    else {
      addWarningMessage( Sprintf_('There is no user <q>%s</q> associated with this assignment', $uident) );
      $warnings = true;
      continue;
    }

    if( ! empty( $_REQUEST['sdate'][$n] ) && ! isset( $authorList[ $uident ] ) ) {
      addPendingMessage( Sprintf_('<q>%s</q> is not registered as an author for this assignment', $uident ));
      $warnings = true;
    }
    
    if( ! empty( $_REQUEST['rdate'][$n] ) && ! isset( $reviewList[ $uident ] ) ) {
      addPendingMessage( Sprintf_('<q>%s</q> is not registered as a reviewer for this assignment', $uident ));
      $warnings = true;
    }
    
    $sdate = date_to_mysql( $_REQUEST['sdate'][$n] );
    if( $sdate !== NULL ) {
      $tag = quote_smart( isset( $_REQUEST['tag'][$n] ) ? $_REQUEST['tag'][$n] : null );
      $submitEnd = quote_smart( $sdate );

      $haveSubmitted =
	empty($_REQUEST['whenMade'][$n])
	? fetchOne('select essayID from Essay' . " where assmtID = $assmtID and not isPlaceholder and author = $who limit 1")
	: false;
      if ($haveSubmitted) {
	addPendingMessage(Sprintf_('<q>%s</q> has already uploaded a submission. This existing submission will be hidden from reviewers unless you cancel the extension.', $uident));
	$warnings = true;
      }

      $haveAllocated =
	empty($_REQUEST['whenMade'][$n])
	? fetchOne('select allocID from Allocation' . " where assmtID = $assmtID and author = $who limit 1")
	: false;
      if (!$haveAllocated)
	$reallocationRequired = true;

      $desc = quote_smart(sprintf(_('This is a placeholder for a late submission, which is due at %s'),
				  formatDateString($sdate)));
      $placeholders[] = "($assmtID, $who, 1, false, $desc, 'inline-text', $tag, $desc, 1, now())";
    } else {
      $submitEnd = 'NULL';
      $tag = 'NULL';
    }

    $rdate = date_to_mysql( $_REQUEST['rdate'][$n] );
    if( $rdate )
      $reviewEnd = quote_smart( $rdate );
    else
      $reviewEnd = 'NULL';

    $whenMade = 'now()';
    if (!empty($_REQUEST['whenMade'][$n]))
      $whenMade = quote_smart(date_to_mysql($_REQUEST['whenMade'][$n] . $_SESSION['TZ']));
    
    $values[] = "($assmtID,$who,$submitEnd,$reviewEnd,$tag,$whenMade)";
  }

  checked_mysql_query("delete from Extension where assmtID = $assmtID" );
  if (! empty($values))
    checked_mysql_query('replace into Extension'
			. ' (assmtID, who, submissionEnd, reviewEnd, tag, whenMade) values '
			. join(',', $values));
  
  checked_mysql_query("delete from Essay where assmtID = $assmtID and isPlaceholder");
  if (! empty($placeholders)) {
    checked_mysql_query('replace into Essay (assmtID, author, reqIndex, compressed, description, extn, tag, essay, isPlaceholder, whenUploaded) values '
			. join(',', $placeholders));
    if (isset($reallocationRequired) && nowBetween($assmt['submissionEnd'], null)) {
      require_once 'doAllocation.php';
      doUnsupervisedAllocation($assmt);
    }
  }

  if( $warnings )
    redirect('addExtensions', "assmtID=$assmtID&cid=$cid");
  else
    redirect('viewAsst', "assmtID=$assmtID&cid=$cid");
}
