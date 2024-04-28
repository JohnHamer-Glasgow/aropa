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


function viewRubricA( ) {
  list( $rubricID ) = checkREQUEST( '_rubricID' );
  
  require_once 'BlockParser.php';
  list( $xml ) = TransformRubricByID( $rubricID );
  return HTML( $xml,
	       BackButton());
}

function editRubricA( ) {
  list( $assmtID, $cid ) = checkREQUEST( '_assmtID', '_cid' );

  $row = fetchOne( 'SELECT r.*, allocationsDone, isReviewsFor from Rubric r'
		   . ' INNER JOIN Assignment a ON a.rubricID = r.rubricID'
		   . ' INNER JOIN Course c     ON c.courseID = a.courseID'
		   . " WHERE assmtID=$assmtID" );
  $warn = '';
  if( ! $row )
    $row = array( 'rubricID'    => 0,
		  'rubric'      => '',
		  'copiedFrom'  => null,
		  'lastEdited'  => time());
  else if( ! empty($row['allocationsDone']) )
    $warn = warning(_('Allocation record have already been created for this assignment.  If you change the rubric, you may invalidate existing reviews.'));

  if( ! empty( $row['isReviewsFor'] ) )
    extraHeader( '$(".mainScreenContent").css("background-color", "#ccffff")', 'onload');
  return editRubricCommon( $row, 'assignment', $assmtID, $cid, $warn );
}


function editRubricCommon( $rubric, $rubricType, $id, $cid, $warning = '') {
  ensureDBconnected( 'editRubricCommon' );

  $rs = checked_mysql_query( "SELECT IFNULL(aname, rname) as aname, IFNULL(cname, '') as cname, r.rubricID FROM Rubric r"
			     . " INNER JOIN Assignment t ON t.rubricID=r.rubricID"
			     . ' INNER JOIN Course c ON c.courseID=t.courseID'
			     . " WHERE rubricType = '$rubricType'"
			     . ' AND (CASE sharing WHEN "none" THEN c.courseID IN (' . join(',', myClasses()) . ')'
			     .                  " WHEN 'colleagues' THEN c.instID = $_SESSION[instID]"
			     .                  ' ELSE true'
			     . ' END)'
			     . " ORDER BY cname, aname, r.rubricID");
  $select = HTML::select( array( 'onchange' => "useRubric('$_SERVER[PHP_SELF]')",
				 'class'=>'form-control',
				 'id'       => "rubricSelection" ),
			  HTML::option( array('value'=>0), '(Create new rubric)' ));
  $seen = array( );
  while( $row = $rs->fetch_assoc() )
    if( ! isset( $seen[ $row['rubricID'] ] ) ) {
      $seen[ $row['rubricID'] ] = true;
      $properties = array( 'value' => $row['rubricID']);
      if( $rubric['rubricID'] == $row['rubricID'] )
	$properties['selected'] = true;
      // Some assignment names repeat the class name; we remove it here.
      $aname = trim( str_ireplace( $row['cname'], '', $row['aname'] ), " -,;:/" );
      $name = trim( "$row[cname], $aname", " ,");
      if( $name != '' )
	$select->pushContent( HTML::option( $properties, $name));
    }

  extraHeader('rubric.js', 'script');

  $hiddenInputs = HTML( );
  foreach( array( 'rubricID'   => $rubric['rubricID'],
		  'cid'        => $cid,
		  'rubricType' => $rubricType,
		  'id'         => $id,
		  'isXML'      => ! empty($rubric['rubricXML']) )
	   as $field => $value )
    $hiddenInputs->pushContent( HTML::input( array('type'=>'hidden',
						   'name'=>$field,
						   'id'=>$field,
						   'value'=> $value)));

  if( empty( $rubric['rubricXML'] ) ) {
    require_once 'transform.php';
    $xmlRubric = PHPwikiToXml( $rubric['rubric'] );
  } else
    $xmlRubric = $rubric['rubricXML'];

  $back = "viewAsst&assmtID=$id";

  global $gHaveInstructor;
  if( ! $gHaveInstructor )
    $warning = message(_('As you have only guest access rights for this class, you cannot save any changes to the rubric.'));
  return HTML::form(array( 'name' => "edit",
			   'class'=>'form',
			   'method'=>'post',
			   'action'=>"$_SERVER[PHP_SELF]?action=saveRubric"),
		    $hiddenInputs,
		    $warning,
		    FormGroup('rubric',
			      _('Select existing rubric: '),
			      $select),
		    HTML::textarea(array('cols'=>120,
					 'rows'=>25,
					 'name'=>'rubric',
					 'id'=>'rubric'),
				   $xmlRubric),
		    EnableTinyMCE_RubricEditor(),
		    ButtonToolbar($gHaveInstructor ? submitButton( _('Save')) : '',
				  CancelButton()));
}

function saveRubric( ) {
  list( $rubricID, $cid, $rubric, $rubricType, $id, $isXML )
    = checkREQUEST( '_rubricID', '_cid', 'rubric', 'rubricType', '_id', '_isXML' );

  $table = 'Assignment';
  $tblID = 'assmtID';

  $data = array( );
  if( $isXML )
    $data['rubricXML'] = $rubric;
  else {
    require_once 'transform.php';
    $data['rubric']    = $rubric;
    $data['rubricXML'] = PHPwikiToXml( $rubric );
  }
  
  $db = ensureDBconnected('saveRubric');

  // If rubricID is an existing rubric (!=0) and is used by more than
  // one assignment, then check whether it has changed.  If
  // the current version does not differ, then we can avoid making a
  // new entry.
  if( $rubricID == 0 )
    $action = 'insert';
  else {
    $row = fetchOne( "SELECT rubric, rubricXML FROM Rubric WHERE rubricID = $rubricID"
		     . " AND EXISTS (SELECT * FROM $table WHERE rubricID = $rubricID AND $tblID<>$id)");
    if( ! $row )
      $action = 'update';
    else {
      // require_once 'transform.php';
      // $xmlRow  = toXML( $row['rubricXML'] );
      // $xmlData = toXML( $data['rubricXML'] );
      // if( sameXML( $xmlRow, $xmlData ) || sameXML( toXML( PHPwikiToXml( $row['rubric'] ) ), $xmlData ) )
      // 	$action = 'skip';
      // else {
	$action = 'insert';
	$data['copiedFrom'] = $rubricID;
	//}
    }
  }
  
  $data['rname'] = null;
  switch( $action ) {
  case 'insert':
    $data['owner']       = $_SESSION['userID'];
    $data['rubricType']  = $rubricType;
    checked_mysql_query(makeInsertQuery('Rubric', $data, array('createdDate' => 'now()')));
    $rubricID = $db->insert_id;
    break;
  case 'update':
    checked_mysql_query(makeUpdateQuery('Rubric', $data, array('lastEdited' => 'now()'))
					. " WHERE rubricID = $rubricID");   
    break;
  case 'skip':
    break;
  }
  
  //- In all cases, we need to writeback the rubricID and items to the
  //- activity table
  require_once 'BlockParser.php';
  list( $xml, $markItems, $radioLabels, $commentItems, $nReviewFile ) = TransformRubric( $data['rubricXML'] );
  $upd = array('markItems'   => itemsToString( $markItems ),
	       'commentItems'=> itemsToString( $commentItems ),
	       'nReviewFiles'=> $nReviewFile,
	       'rubricID'    => $rubricID );
  checked_mysql_query( makeUpdateQuery($table, $upd)
		       . " WHERE $tblID = $id"
		       . ' AND courseID = ' . cidToClassId( $cid ));
  redirect("viewAsst");
}



function labelRubricA( ) {
  list( $cid, $assmtID ) = checkREQUEST( '_cid', '_assmtID' );
  return rubricLabels( $cid, $assmtID, 'assignment', "labelRubricA&cid=$cid&assmtID=$assmtID" );
}

function rubricLabels( $cid, $id, $rubricType, $caller ) {
  $labels = fetchOne( "SELECT aname, rubricID, markItems, markLabels, markGrades, commentItems, commentLabels, isReviewsFor FROM Assignment"
		      . " WHERE assmtID = $id AND courseID = " . cidToClassId( $cid ) );

  if( ! $labels )
    return warning( _('You do not have access to the selected rubric') );

  if( ! empty( $labels['isReviewsFor'] ) )
    extraHeader( '$(".mainScreenContent").css("background-color", "#ccffff")', 'onload');
  
  if( isset( $_REQUEST['i'] ) || isset( $_REQUEST['g'] ) || isset( $_REQUEST['c'] ) ) {
    $data = array( );
    if( ! empty( $labels['markItems'] ) && is_array( $_REQUEST['i'] ) ) {
      parse_str($labels['markItems'] ?? '', $markItems);
      $markGrades = array( );
      $markLabels = array( );
      foreach( $markItems as $radioID => $nRadios ) {
	$markLabels[ $radioID ] = isset( $_REQUEST['g'][$radioID] ) ? $_REQUEST['g'][$radioID] : '';
	$markGrades[ $radioID ] = array( );
	for( $item = 0; $item < $nRadios; $item++ ) {
	  $value = trim($_REQUEST['i'][$radioID][$item]);
	  if ($value == '') $value = $item + 1;
	  $markGrades[$radioID][$item] = $value;
	}
      }
      
      $data['markGrades'] = itemsToString( $markGrades );
      $data['markLabels'] = itemsToString( $markLabels );
    }

    if (is_array($_REQUEST['c'])) {
      $commentLabels = array();
      foreach ($_REQUEST['c'] as $label)
	$commentLabels[] = $label;
      $data['commentLabels'] = itemsToString($commentLabels);
    }
    
    if( ! empty( $data ) ) {
      checked_mysql_query( makeUpdateQuery( 'Assignment', $data ) . " WHERE assmtID = $id" );
      redirect( "viewAsst", "assmtID=$id&cid=$cid" );
    }
  } else {
    require_once 'BlockParser.php';
    list($radioItems, $radioLabels) = TransformRubricByID( $labels['rubricID'] );
    $radioItemText = rubricRadioItemText( $labels['rubricID'] );
    $radios = HTML::div(array('class'=>'form-group'), HTML::p(_('Enter a label for each button group. You can also change the mark given for each button.')));
    if( ! empty( $labels['markItems'] ) ) {
      parse_str($labels['markItems'] ?? '', $markItems);
      parse_str($labels['markLabels'] ?? '', $markLabels);
      $markGrades = stringToItems( $labels['markGrades'] );
      
      foreach( $markItems as $radioID => $nValues ) {
	$radios->pushContent(FormGroup("g[$radioID]",
				       _('Button group ') . $radioID,
				       HTML::input(array('value'=>$markLabels[$radioID])),
				       _('Enter a description for this button group')));
	$radioTable = HTML::table(array('class'=>'table'));
	for( $item = 0; $item < $nValues; $item++ ) {
	  $name = "i[$radioID][$item]";
	  $radioTable->pushContent(HTML::tr(HTML::td($item + 1),
					    HTML::td(HTML::label(array('for'=>$name,
								       'class'=>'control-label'),
								 $radioItemText[$radioID][$item])),
					    HTML::td(HTML::input(array('type'=>'number',
								       'name'=>$name,
								       'class'=>'form-control',
								       'autocomplete'=>'off',
								       'min'=>0,
								       'value' => $markGrades[$radioID][$item],
								       'style'=>'width:6em',
								       'placeholder'=>$item + 1)))));
	}

	$radios->pushContent($radioTable);
      }
    }

    parse_str($labels['commentItems'] ?? '', $commentItems);
    parse_str($labels['commentLabels'] ?? '', $commentLabels);
    if (!empty($commentItems)) {
      $cLabels = HTML::div(array('class'=>'form-group'), HTML::p(_('Enter a label for each comment box.')));
      foreach ($commentItems as $item => $label) {
	if (isset($commentLabels[$item]))
	  $label = $commentLabels[$item];
	else
	  $label = '';
	$cLabels->pushContent(
	  FormGroup(
	    "c[$item]",
	    _('Comment ') . ($item + 1),
	    HTML::input(array('value' => $label)),
	    _('Enter a description for this comment')));
      }
      
      $comments = HTML(HTML::h3(_('Comment labels')), $cLabels);
    } else
      $comments = '';

    extraHeader( 'labels.js', 'script' );
    
    $cancel = "viewAsst&assmtID=$id";
    return HTML( HTML::h2(Sprintf_('Label rubric: %s <small>(#%d)</small>', $labels['aname'], $id)),
		 HTML::form(array( 'name' => "edit",
				   'method'=>'post',
				   'class'=>'form',
				   'action'=>"$_SERVER[PHP_SELF]?action=$caller"),
			    HTML::p(_('This page allows you to provide titles for your button groups and comment boxes. '
				      . 'These titles will be shown on the feedback presented to students.')),
			    $radios,
			    $comments,
			    ButtonToolbar(submitButton(_('Save')),
					  CancelButton())));
  }
}

function rubricRadioItemText($rubricID) {
  require_once 'BlockParser.php';
  $rubric = fetchRubric($rubricID);
  $document = new DOMDocument();
  libxml_use_internal_errors(true);
  libxml_clear_errors();
  @$document->loadHTML("<html>$rubric</html>");

  $radioItemText = array();
  $radioCode = 0;
  $radioGroup = 0;
  for ($node = $document; $node; $node = succ($node)) {
    switch ($node->nodeName) {
    case 'input':
      if ($node->getAttribute('type') == 'radio') {
	$itemText = '';
	for ($n = succ($node); $n; $n = succ($n))
	  if (in_array($n->nodeName, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'input', 'img')))
	    break;
	  else if (strlen($itemText) > 20)
	    break;
	  else if ($n->nodeType == XML_TEXT_NODE)
	    $itemText .= $n->wholeText;

	if ($radioCode++ == 0)
	  $radioGroup++;

	if (!isset($radioItemText[$radioGroup]))
	  $radioItemText[$radioGroup] = array();
	$radioItemText[$radioGroup][] = $itemText;
      }
      
      break;

    case "h1": case "h2": case "h3":
    case "h4": case "h5": case "h6":
    case 'hr':
      $radioCode = 0;
      break;
    }
  }
  
  return $radioItemText;
}

//- AJAX call
function rawRubric( ) {
  list( $rubricID ) = checkREQUEST( '_rubricID' );
  $row = fetchOne( "SELECT rubric, rubricXML FROM Rubric WHERE rubricID = $rubricID" );
  if( $row ) {
    $rubric = $row['rubricXML'];
    if( empty( $rubric ) ) {
      require_once 'transform.php';
      $rubric = PHPwikiToXml( $row['rubric'] );
    }
  } else
    $rubric = "";

  header('Accept-Ranges: bytes');
  header('Content-Length: ' . strlen($rubric) );
  header('Content-Type: text/plain');
  echo $rubric;
  exit;
}

function toXML( $text ) {
  $xml = new DOMDocument( );
  libxml_use_internal_errors(true);
  $xml->loadHTML( "<html>$text</html>" );
  return $xml;
}

function sameXML( $xmlA, $xmlB ) {
  if( $xmlA == null || $xmlB == null )
    return $xmlA == $xmlB;

  if( $xmlA->nodeName != $xmlB->nodeName )
    return false;

  if( $xmlA->nodeType != $xmlB->nodeType )
    return false;

  if( $xmlA->nodeType == XML_TEXT_NODE )
    if( trim(preg_replace('/\s\s+/', ' ', $xmlA->wholeText)) != trim(preg_replace('/\s\s+/', ' ', $xmlB->wholeText)) )
      return false;

  $aA = $xmlA->attributes;
  $aB = $xmlB->attributes;
  if( $aA == null || $aB == null ) {
    if( $aA != $aB )
      return false;
  } else {
    if( $aA->length != $aB->length )
      return false;
    foreach( $aA as $name => $attr ) {
      if( ! sameXML( $aB->getNamedItem( $name ), $attr ) )
	return false;
    }
  }

  for( $cA = skipEmptyNodes( $xmlA->firstChild),
	 $cB = skipEmptyNodes( $xmlB->firstChild )
	 ; $cA || $cB
	 ; $cA = skipEmptyNodes( $cA->nextSibling ),
	 $cB = skipEmptyNodes( $cB->nextSibling )
       )
    if( ! sameXML( $cA, $cB ) )
      return false;
  return true;
}

function skipEmptyNodes( $node ) {
  while( $node && $node->nodeType == XML_TEXT_NODE && trim($node->wholeText) == '' )
    $node = $node->nextSibling;
  return $node;
}

function myClasses() {
  $classes = array( -1 );
  foreach( $_SESSION['classes'] as $class )
    $classes[] = $class['courseID'];
  return $classes;
}

function browseRubrics( ) {
  if( isset( $_REQUEST['share'] ) && is_array( $_REQUEST['share'] ) ) {
    $share = array( 'none'=>array(), 'colleagues'=>array(), 'everyone'=>array() );
    foreach( $_REQUEST['share'] as $rubricId => $sharing )
      //- Perhaps check that $rubricId is for an Assignment in $_SESSION['classes']
      $share[ $sharing ][] = $rubricId;

    foreach( array('none', 'colleagues', 'everyone') as $sharing )
      if( ! empty( $share[$sharing] ) )
	checked_mysql_query( "UPDATE Rubric SET sharing = '$sharing' WHERE rubricID IN (" . join($share[$sharing], ',') . ')');
    addPendingMessage( _('Rubric sharing saved') );
    redirect('home');
  } else {
    $table = table( HTML::tr( HTML::th('Assignment (class)'), HTML::th('Sharing')));
    foreach( fetchAll( 'SELECT r.rubricID, IFNULL(rname,aname) AS rname, cname, a.courseID, sharing'
		       . ' FROM Rubric r LEFT JOIN Assignment a ON r.rubricID=a.rubricID'
		       . ' LEFT JOIN Course c ON a.courseID=c.courseID'
		       . ' WHERE c.courseID IS NULL OR c.courseID IN (' . join(',', myClasses()) . ')'
		       . ' ORDER BY rname, cname'
		       ) as $row ) {
      $class = empty( $row['cname'] ) ? '' : " ($row[cname])";
      $name = trim( "$row[rname]$class" );
      if( ! empty( $name ) )
	//*** Add option to delete rubrics?
	$table->pushContent( HTML::tr( HTML::td( callback_url( $name, "viewRubricA&rubricID=$row[rubricID]", array('target'=>'_new'))),
				       HTML::td( selectOption2( "share[$row[rubricID]]",
								$row['sharing'],
								array('none'      =>_('Do not share'),
								      'colleagues'=>_('Share with colleagues at my institution'),
								      'everyone'  =>_('Share with everyone'))))));
    }
    
    return HTML(HTML::h1(_('Browse rubrics')),
		HTML::form(array( 'name' => "edit",
				  'method'=>'post',
				  'action'=>"$_SERVER[PHP_SELF]?action=browseRubrics"),
			   $table,
			   ButtonToolbar(submitButton('Save sharing choices'),
					 CancelButton())));
  }
}
