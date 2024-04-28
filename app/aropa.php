<?php
/*
    Copyright (C) 2004-2021 John Hamer <J.Hamer@acm.org>

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
$CmdActions = array( );
require_once 'core.php';

ob_start( );

function toggleLogQueries( ) {
  if( isset( $_SESSION['logQueries'] ) )
    unset( $_SESSION['logQueries'] );
  else
    $_SESSION['logQueries'] = true;
  return homePage( );
}

function callback_url( $text, $action, $target = array( ) ) {
  if( isset( $_REQUEST['omitMenu'] ))
    $action .= '&omitMenu';
  return HTML::a( array('href'=>$_SERVER['PHP_SELF'] . "?action=$action") + $target,
                  $text );
}

function redirect_url( $text, $action, $target = array( ) ) {
  if( isset( $_REQUEST['omitMenu'] ))
    $action .= '&omitMenu';
  return HTML::button(array('onclick'=>"window.location.replace('$_SERVER[PHP_SELF]?action=$action')") + $target,
		      $text );
}

function Button( $text, $action, $args = array( ) ) {
  if( isset( $_REQUEST['omitMenu'] ))
    $action .= '&omitMenu';
  $a = HTML::a(array('href'=>$_SERVER['PHP_SELF'] . "?action=$action",
		     'role'=>'button') + $args,
	       $text);
  $a->setInClass('btn');
  $a->setInClass('btn-default');
  return $a;
}

function RedirectButton( $text, $action, $args = array( ) ) {
  if( isset( $_REQUEST['omitMenu'] ))
    $action .= '&omitMenu';
  $a = HTML::a(array('onclick'=>"window.location.replace('$_SERVER[PHP_SELF]?action=$action')",
		     'role'=>'button') + $args,
	       $text);
  $a->setInClass('btn');
  $a->setInClass('btn-default');
  return $a;
}

function ButtonToolbar() {
  $group = HTML::div(array('class'=>"btn-toolbar",
			 'role'=>"group"));
  foreach (func_get_args() as $button)
    $group->pushContent($button);
  return $group;		   
}

function Radio($name, $content, $value, $checked, $attrs = array()) {
  return HTML::div(array('class'=>'radio'),
		   HTML::label(HTML::input(array('type'=>'radio',
						 'name'=>$name,
						 'checked'=>$checked,
						 'value'=>$value) + $attrs,
					   $content)));
}

function RadioGroup($name, $label, $current, $entries, $attrs = array()) {
 $group = HTML::div(array('class'=>'radio-group'));		      
  foreach ($entries as $value => $content)
    $group->pushContent(Radio($name,
			      $content,
			      $value,
			      $current == $value,
			      $attrs));
  return HTML($label == null ? '' : HTML::label($label), $group);
}

function FormGroup($name, $label, $control, $placeholder = null, $help = null)
{
  $control->setInClass('form-control');
  $control->setAttr('name', $name);
  $control->setAttr('placeholder', $placeholder == null ? $label : $placeholder);
  return HTML::div(array('class'=>'form-group'),
		   HTML::label(array('for'=>$name), $label),
		   $control,
		   $help ? HTML::span(array('class'=>'help-block'), $help) : '');
}

function FormGroupSmall($name, $label, $control, $placeholder = null)
{
  $control->setInClass('form-control');
  $control->setAttr('style', 'width:auto !important; display:inline-block;');
  $control->setAttr('name', $name);
  if ($placeholder != null)
    $control->setAttr('placeholder', $placeholder);
  return HTML::div(array('class'=>'form-group'),
		   HTML::label($label, $control));
}

function yesNoSelection( $name, $selected, $yes = 'yes', $no = 'no', $attrs = array( ) ) {
  return HTML::select( array('name'=>$name) + $attrs,
		       HTML::option( array('value'=>1,
					   'selected'=>$selected), $yes),
		       HTML::option( array('value'=>0,
					   'selected'=>!$selected), $no));
}

function selectOption( $name, $options ) {
  $select = HTML::select( array('name'=>$name) );
  foreach( $options as $value => $attrs )
    $select->pushContent( HTML::option( $attrs, $value ) );
  return $select;
}

function selectOption2( $name, $default, $options ) {
  $select = HTML::select( array('name'=>$name,
				'class'=>'form-control') );
  foreach( $options as $value => $desc )
    $select->pushContent( HTML::option( array('value'=>$value,
					      'selected'=>$value==$default),
					$desc ) );
  return $select;
}

function formButton( $text, $action, $tooltip = "", $args = array( ) ) {
  if( isset( $_REQUEST['omitMenu'] ))
    $action .= '&omitMenu';

  $args['href'] = $_SERVER['PHP_SELF'] . "?action=$action";
  $args['role'] = 'button';
  if( ! empty( $tooltip ) ) $args['title'] = $tooltip;
  $a = HTML::a($args, $text);
  $a->setInClass('btn');
  $a->setInClass('btn-primary');
  return $a;
}

function BackButton() {
  return HTML::a(array('class'=>'btn btn-primary',
		       'href'=>'javascript:history.back()'),
		 _('Back'));
}

function CancelButton() {
  return HTML::a(array('class'=>'btn btn-primary',
		       'href'=>'javascript:history.back()'),
		 _('Cancel'));
}

function maybeFormButton( $disable, $text, $action, $tooltip = "" ) {
  if( $disable ) {
    global $gJQueryUI;
    $gJQueryUI = true;
    return HTML::input( array( 'type' => "button",
			       'value'=> $text,
			       'class'=>'btn btn-primary',
			       'disabled' => 'true' ));
  } else
    return formButton( $text, $action, $tooltip );
}

function br( ) {
  return HTML::br( array('style'=>'clear: both') );
}

function onclickButton( $text, $onclick ) {
  global $gJQueryUI;
  $gJQueryUI = true;
  return HTML::button( array('onclick'=>$onclick,
			     'class'=>'btn btn-primary'),
		       $text );
}

function submitButton($value, $name = null, $id = null) {
  global $gJQueryUI;
  $gJQueryUI = true;
  $input = HTML::input(array('type'=>'submit',
			     'value'=>$value,
			     'class'=>'btn btn-primary'));
  if($name != null)
    $input->setAttr('name', $name);
  if($id != null)
    $input->setAttr('id', $id);
  return $input;
}

$gJQueryUI = false;

function table( ) {
  $table = new HtmlElement('table');
  $table->_init2(func_get_args());
  $table->setAttr('class', 'table table-striped table-condensed');
  return $table;
}

function assmtHeading( $title, &$assmt ) {
  if( ! empty( $title ) )
    $title .= ': ';
  $aname = trim($assmt['aname']) == '' ? _('(unnamed assignment)') : $assmt['aname'];
  return HTML::h2($title, $aname, HTML::small(' (#' . $assmt['assmtID'], ')'));
}


function calendarAbsoluteFromGregorian( $date ) {
  //- The number of days elapsed between the Gregorian date 12/31/1 BC
  //- and $date.  The Gregorian date Sunday, December 31, 1 BC is
  //- imaginary.
  $priorYears = $date['year'] - 1;
  return $date['yday']
    + 365 * $priorYears
    + (int)($priorYears/4)
    - (int)($priorYears/100)
    + (int)($priorYears/400)
    ;
}

function emptyDate( $date ) {
  return empty( $date ) || $date == 'NULL' || $date == '0000-00-00 00:00:00';
}


function chooseDate( /*...*/ ) {
  foreach( func_get_args() as $date )
    if( ! emptyDate( $date ) )
      return $date;
  return null;
}

function formatTimestamp($timestamp) {
  if (is_string($timestamp)) {
    if (emptyDate($timestamp))
      return '';
    if (isset($_SESSION['TZ']))
      $timestamp .= " " . $_SESSION['TZ'];
    $time = strtotime($timestamp);
  } else
    $time = $timestamp;
  
  return formatDateString(date('%Y-%m-%d %H:%M:%S', $time));
}

function formatDateString( $mySQLdate, $format = 'full' ) {
  if( emptyDate( $mySQLdate ) )
    return "";

  $n = sscanf( $mySQLdate, '%d-%d-%d %d:%d:%d', $y,$m,$d,$h,$mi,$s );
  if( $n == 3 ) {
    $h = $mi = $s = 0;
    $n = 6;
  }

  if( $n == 6 && $y != 0 )
    return formatTimeString( mktime($h,$mi,$s,$m,$d,$y), $format );
  else
    return "";
}

function formatTimeString( $time, $format = 'full' ) {
  $date = getdate( $time );
  if( $format == 'full' ) {
    if ($date['hours'] == 0 && $date['minutes'] == 0)
      $hour = 'midnight';
    else if ($date['hours'] == 12 && $date['minutes'] == 0)
      $hour = 'noon';
    else
      $hour = $date['minutes'] == 0 ? date('ga', $time) : date('g:ia', $time);
  } else
    $hour = '';

  if( $format != 'full' )
    return date( $format, $time );
  else
    return $hour . date( ' l j M Y', $time );
}


function date_to_mysql( $str ) {
  $str = trim( $str );
  if( ! empty( $str ) ) {
    $d = strtotime( $str );
    if( $d === false || $d == -1 )
      return NULL;
    else
      return date('Y-m-d H:i:s', $d);
  }
  return NULL;
}


function date_and_time( $str, $dfltTime = '12:00' ) {
  $time = strtotime( $str );
  if( $time == false || $time == -1 )
    return array( '', $dfltTime );
  else
    return explode( ' ', date('Y-m-d h:ia', $time ));
}

function ToRFC3339($str, $TZ = '') {
  if (empty($str)) return '';
  $t = strtotime($str . $TZ);
  return $t === false || $t == -1 ? '' : date('Y-m-d\TH:i:s', $t);
}

function nowBetween( $start, $end, $now = null ) {
  if( $now == null )
    $now = time( );
  $nowS = date('Y-m-d H:i:s', $now );
  if( ! emptyDate($start) && $nowS < $start )
    return false;
  // if both dates are empty, then we have no way of telling if $now is between them, default to no
  else if( emptyDate($start) && emptyDate($end) )
    return false;
  else
    return emptyDate($end) || $nowS <= $end;
}


function itemsToString( $arr ) {
  $str = "";
  if( $arr && is_array( $arr ) )
    foreach( $arr as $k => $v )
      $str .= rawurlencode($k)
      . '='
      . rawurlencode( is_array($v) ? join(',', $v) : $v )
      . '&';
  return rtrim( $str, '&' );
}


function stringToItems( $str ) {
  parse_str( $str ?? '', $arr );
  foreach( $arr as $k => $v )
    $arr[ $k ] = explode( ',', $v );
  return $arr;
}


function itemCode( $idx, $numItems ) {
  $code = '';
  while( $numItems > 0 ) {
    $code = chr( ord('A') + ($idx % 26) ) . $code;
    $idx = (int)($idx / 26);
    $numItems = (int) ($numItems / 26);
  }
  return $code;
}

function changedMarks( $oldStr, $newStr, $allItems ) {
  parse_str( $oldStr ?? '', $oldMarks );
  parse_str( $newStr ?? '', $newMarks );
  $changes = '';
  foreach( $newMarks as $item => $mark )
    if( ! isset( $oldMarks[ $item ] ) || $oldMarks[ $item ] != $mark )
      $changes .= ' ' . itemCode( array_search($item, $allItems), count($allItems) ) . '=' . $mark . ';';
  return rtrim( $changes, ';' );
}


//- Check the posted marks are permitted for this rubric and returns a
//- list of any missing items.  Generates a security alert if the posted
//- data is illegal.
function checkPostedMarks( $markItems ) {
  /*
  if( ! isset( $_POST['mark'] ) )
    $_POST['mark'] = array( );

  if( ! is_array( $_POST['mark'] ) )
    securityAlert( '_POST[mark] is not array' );

  $msg = array( );
  foreach( $_POST['mark'] as $item => $g )
    if( ! isset( $markItems[ $item ] ) || ! in_array( $g, $markItems[ $item ] ) ) {
      addPendingMessage( _('Some unrecognised marks were submitted') );
      break;
    }

  $diff = array_diff( array_keys( $markItems ), array_keys( $_POST['mark'] ));
  if( ! empty( $diff ) )
    addPendingMessage( _('Your review is incomplete.  Please re-mark it, and fill in marks for all items.'));
  */
}


//- Check the posted choices are permitted for this rubric
function checkPostedChoices( $choiceItems ) {
  if( ! isset( $_POST['choice'] ) )
    $_POST['choice'] = array( );
  else {
    if( ! is_array( $_POST['choice'] ) )
      securityAlert( 'POST[choice] is not array' );

    if( empty( $choiceItems ) && ! empty( $_POST['choice'] ) )
      securityAlert( 'Choices should not be present' );

    foreach( $_POST['choice'] as $item => $c )
      if( ! isset( $choiceItems[ $item ] ) || ! in_array( $c, $choiceItems[ $item ] ) )
	return array( _('Some unrecognised choice value were submitted') );
  }
  return array( );
}

function commentLabels($assmt) {
  parse_str($assmt['commentItems'] ?? '', $commentItems);
  if (!empty($assmt['commentLabels'])) {
    parse_str($assmt['commentLabels'] ?? '', $commentLabels);
    foreach ($commentLabels as $item => $label)
      if (isset($commentItems[$item]) && !empty(trim($label)))
	$commentItems[$item] = $label;
  }
  
  return $commentItems;
}

function isFeedbackAssignment( $assmt ) {
  return empty( $assmt[ 'markItems'   ] )
    &&   empty( $assmt[ 'choiceItems'  ] )
    &&   empty( $assmt[ 'commentItems' ] )
    &&   empty( $assmt[ 'nReviewFiles' ] );
}



$CmdActions['login']        = array( 'load' =>'login.php' );
$CmdActions['authenticate'] = array( 'load' =>'login.php' );
$CmdActions['logout']       = array( 'load' =>'login.php');
$CmdActions['dismissMessage'] = array();

function setPreference( $attrib, $value ) {
  if( ! isset( $_SESSION['prefs'] ) )
    $_SESSION['prefs'] = array( );
  $_SESSION['prefs'][ $attrib ] = $value;

  $userID = (int)$_SESSION['userID'];
 
  ensureDBconnected( 'setPreference' );

  $rs = checked_mysql_query( "SELECT prefs FROM User WHERE userID = $userID" );
  $acct = $rs->fetch_assoc();
  if( ! $acct )
    return;

  parse_str($acct['prefs'] ?? '', $prefs);
  $prefs[ $attrib ] = $value; //- add or replace
  checked_mysql_query( 'UPDATE User SET prefs = ' . quote_smart( itemsToString( $prefs ) )
                       . " WHERE userID = $userID" );
}

function getPreference( $attrib, $default = NULL ) {
  if( isset( $_SESSION['prefs'] ) )
    return $_SESSION['prefs'][ $attrib ];
  else
    return $default;
}


function expand_latest( $path ) {
  static $simpleExpansions = array('<<adb>>'=>'./adb',
                                   '<<se250>>'=>'./adb/se250' );

  //- Replace <<latest>> with the last matching file/directory in that
  //- part.  I.e., take the prefix before <<latest>>, glob() prefix*,
  //- take the last match, and append the suffix after <<latest>>.  This
  //- is to support the ADB directory structure, in which each
  //- submission is stored separately.  E.g.,
  //-  <<se250>>/A2/unsorted/<<author>>/Submission<<latest>>/Files
  foreach( $simpleExpansions as $pat => $exp ) {
    $p = strpos($path, $pat );
    if( $p !== false )
      $path = substr( $path, 0, $p ) . $exp . substr( $path, $p + strlen($pat) );
  }

  $p = strpos($path, '<<latest>>' );
  if( $p !== false ) {
    $prefix = substr( $path, 0, $p );
    $suffix = substr( $path, $p + strlen('<<latest>>') );
    $matches = glob( "$prefix*");
    if( $matches && count($matches) > 0 )
      $path = substr($path, 0, $p) . substr(array_pop($matches), $p) . $suffix;
  }
  return $path;
}

//- These are the common combinations of file types for uploading
//- submissions.  Used by editAssignment and uploadSubmissions
global $gStandardFileTypes;
$gStandardFileTypes = array( 'PDF/Word'=>'pdf,doc,docx,rtf',
			     'PDF'=>'pdf',
			     'Word'=>'doc,docx,rtf',
			     'PDF/Word/OpenDoc'=>'pdf,doc,docx,rtf,odt',
			     'Excel'=>'xls,xlsx',
			     'Powerpoint'=>'ppt,pptx',
			     'ZIP archive'=>'zip',
			     'Anything'=>'any',
			     'Other (specify file extension)'=>'other');


function sortOrder( $a, $b ) {
  $cmp = +1;
  foreach( $_SESSION['sortOrder'] as $key ) {
    if( $key == '-' )
      $cmp = -1;
    elseif( isset( $a[ $key ] ) && isset( $b[ $key ] ) ) {
      if( $a[$key] > $b[$key] )
        return $cmp;
      elseif( $a[$key] < $b[$key] )
        return -$cmp;
      else
        $cmp = +1;
    }
  }
  return 0;
}

function maybeUpdateSortOrder( $initialOrder ) {
  //- Allow the sort order to be set by the caller, through $_REQUEST
  if( ! isset( $_SESSION['sortOrder'] ) )
    $_SESSION['sortOrder'] = $initialOrder;
  else
    $_SESSION['sortOrder'] = array_unique( $_SESSION['sortOrder'] + $initialOrder );

  if( isset( $_REQUEST['orderBy'] ) ) {
    list( $a, $b ) = $_SESSION['sortOrder'];
    if( $_REQUEST['orderBy'] == $a )
      array_unshift( $_SESSION['sortOrder'], '-' );
    elseif( $a == '-' && $_REQUEST['orderBy'] == $b )
      array_shift( $_SESSION['sortOrder'] );
    else
      $_SESSION['sortOrder'] = array_unique( array($_REQUEST['orderBy']) + $_SESSION['sortOrder'] );
    unset( $_REQUEST['orderBy'] );
  }
}


function userIdentity($userID, $assmt, $authorOrReviewer) {
  if (empty($userID))
    return "";

  ensureDBconnected('userIdentity');

  if ($userID < 0) {
    $assmtID = $assmt['assmtID'];
    if (!isset( $_SESSION['group-identities']))
      $_SESSION['group-identities'] = array();
    if (!isset( $_SESSION['group-identities'][$assmtID]))
      $_SESSION['group-identities'][$assmtID] = array();
    if (isset($_SESSION['group-identities'][$assmtID][$userID]))
      return $_SESSION['group-identities'][$assmtID][$userID];
    
    if (!empty($assmt['isReviewsFor']))
      $groupAssmtId = $assmt['isReviewsFor'];
    else
      $groupAssmtId = $assmt['assmtID'];
    
    $gname = fetchOne('select gname from GroupUser gu left join `Groups` g on gu.groupID = g.groupID and gu.assmtID = g.assmtID'
		      . ' where gu.groupID = ' . -$userID . " and gu.assmtID = $groupAssmtId",
		      'gname');
    $identity = $gname ? $gname : "Group$userID";
    $_SESSION['group-identities'][$assmtID][$userID] = $identity;
  } else {
    if (!isset( $_SESSION['identities']))
      $_SESSION['identities'] = array();
    if (isset($_SESSION['identities'][$userID]))
      return $_SESSION['identities'][$userID];

    if (!empty($assmt['isReviewsFor']) && $assmt['authorsAre'] == 'review' && $authorOrReviewer == 'author') {
      //- Review evaluation activity, where an individual review is being evaluated.
      list($a, $r) = fetchRow("select author, reviewer from Allocation where allocID = $userID");
      $origAssmt = fetchOne("select assmtID, isReviewsFor, authorsAre, courseID from Assignment where assmtID = $assmt[isReviewsFor]");
      $identity = userIdentity($r, $origAssmt, 'reviewer') . '/' . userIdentity($a, $origAssmt, 'author');
    } else {
      if (empty($assmt['courseID']))
	Debug("No courseID provided: assmtID=$assmtID; " . get_backtrace());
      if (primaryRole($_SESSION['classes'][classIdToCid($assmt['courseID'])]['roles']) == 'guest')
	$identity = "u-$userID";
      else {
	$uident = fetchOne( "SELECT uident FROM User WHERE userID = $userID", 'uident' );
	$identity = $uident ? $uident : "u-$userID";
      }
    }
    
    if (count($_SESSION['identities']) > 100)
      unset($_SESSION['identities'][array_rand($_SESSION['identities'])]);
    $_SESSION['identities'][$userID] = $identity;
  }
  
  return $identity;
}



function AnonymiseAuthor( &$madeByMap, $author, $prefix ) {
  if( $author == $_SESSION['userID'] )
    return '(you)';

  if( ! isset( $madeByMap[ $author ] ) ) {
    $nextAuthor = count( $madeByMap ) + 1;
    $madeByMap[ $author ] = $nextAuthor;
  }
  return $prefix . $madeByMap[ $author ];
}


//- Check for required fields sent in $_REQUEST.  $spec is an array of
//- $_REQUEST argument names.  Prefix an argument with '_' for integer
//- values.  Prefix with '?' if the argument is optional.  Use '?_'
//- for both.  Returns zero for missing optional integer arguments,
//- null for other missing arguments.
//- e.g.
//-   list( $cid, $assmtID ) = checkREQUEST( '_cid', '?_assmtID' );
//- ($cid and $assmtID are guaranteed to be integers; $assmtID will be
//- 0 if not given).
function checkREQUEST( ) {
  $ret = array( );
  foreach( func_get_args() as $arg ) {
    $opt = $arg[0] == '?';
    if( $opt ) $arg = substr( $arg, 1 );

    $int = $arg[0] == '_';
    if( $int ) $arg = substr( $arg, 1 );

    if( ! isset( $_REQUEST[ $arg ] ) ) {
      if( ! $opt )
	securityAlert( "Missing argument: $arg" );
      $val = $int ? 0 : null;
    } else {
      if( $int && ! $opt && ! is_numeric( $_REQUEST[ $arg ] ) )
	securityAlert( "Expected integer argument: $arg = $_REQUEST[$arg]" );
      $val = $int ? intval( $_REQUEST[ $arg ] ) : $_REQUEST[ $arg ];
    }
    
    $ret[] = $val;
  }
  return $ret;
}

$CmdActions['img'] = array('call'=>'showImage');
function showImage( ) {
  list( $img ) = checkREQUEST( 'img' );
  if( ! isset( $_SESSION['img'][ $img ] ) )
    securityAlert( "Request for unregistered image - $img" );
  header( 'Location: ' .  $_SESSION['img'][ $img ] );
  exit;
}

$CmdActions['logo'] = array('call'=>'instLogo');
function instLogo( ) {
  list( $instID ) = checkREQUEST( '_instID' );
  while( ob_end_clean( ) )
    ;
  $logo = fetchOne( "SELECT logo, logoType FROM Institution WHERE instID = $instID" );
  if( ! empty( $logo['logoType'] ) ) {
    header('Accept-Ranges: bytes');
    header("Content-Type: $logo[logoType]");
    header('Content-Length: ' . strlen($logo['logo']) );
    echo $logo['logo'];
  } else {
    header( 'HTTP/1.1 303 See Other' );
    header( 'Location: opencmd.php?f=nologo.png&t=png' );
  }
  exit;
}

$CmdActions['home']
= array('call'=>'homePage',
	'name'  =>'Home',
	'menu'  =>'main' );

function isTestClass($cname) {
  if (isDisusedClass($cname) || isArchiveClass($ctest))
    return false;
  else
    return strpos($cname, 'TEST') !== false || preg_match('/\btest\b/i', $cname);
}

function isArchiveClass($cname) {
  return !empty($cname) && $cname[0] == '+';
}

function isNotArchiveClass($cname) {
  return !isArchiveClass($cname);
}

function isDisusedClass( $cname ) {
  return empty($cname) || $cname[0] == '*';
}

function isNotTestOrDisusedClass( $cname ) {
  return !isTestClass($cname) && ! isDisusedClass($cname) && !isArchiveClass($cname);
}

function sortAssmtBySubmissionEnd($a1, $a2) {
  return strcmp($a1['submissionEnd'], $a2['submissionEnd']);
}

function sortAssmtByReviewEnd($a1, $a2) {
  return strcmp($a1['reviewEnd'], $a2['reviewEnd']);
}


function homePage( $msg = null ) {
  global $gHaveAdmin;
  if (count($_SESSION['classes']) == 1) {
    $class = current($_SESSION['classes']);
    if ($class['roles'] == 1)
      return studentClassHome($msg, $class);
  }
      
  $ul = HTML::ul(array('class'=>"list-unstyled"));
  if ($gHaveAdmin) {
    if (isset($_REQUEST['full']))
      adminFullHomePage($ul);
    else {
      adminShortHomePage($ul);
      $ul->pushContent(HTML::li(Button(_('Full class list'), 'home&full')));
    }
  } else {
    homePageClassList($ul, 'isNotArchiveClass'); 
    $ul->pushContent(HTML::hr());
    homePageClassList($ul, 'isArchiveClass');
 }

  if( $ul->isEmpty( ) )
    $clist = _('You are not registered for any classes');
  else
    $clist = $ul;

  if( false && ! isset( $_SESSION['revert'] ) )
    $email = HTML( HTML::h1( _('Email address') ),
		   HTML::p( _('If you would like to provide an email address, follow this link') ),
		   HTML::ul( HTML::li( callback_url( _('Provide email'), 'setEmail' ))));
  else
    $email = '';
		 
  $content = HTML::div( array('class'=>'list-page'),
			isset( $_SESSION['inactive'] ) ? warning( _('This account is inactive')) : '',
			pendingMessages( $msg ),
			HTML::h1( _('Your classes')),
			$clist,
			$email);
  return HTML( homeSidebar(),
	       HTML::div(array('id'=>'rightContent'), $content),
	       br( ));
}

function adminShortHomePage($ul) {
  $classOrder = array();
  $clist = array();
  $now = time();
  $nextWeek = $now + 7 * 24 * 60 * 60;
  $lastWeek = $now - 7 * 24 * 60 * 60;
  $rs =
    checked_mysql_query(
      'select assmtID, courseID, isActive, submissionEnd, date_format(submissionEnd, "%D %b %Y") as d'
      . ' from Assignment'
      . ' where submissionEnd > date_sub(now(), interval 2 year)'
      . ' order by submissionEnd asc');
  $assmtsByClass = array();
  while ($row = $rs->fetch_assoc())
    $assmtsByClass[$row['courseID']][] = $row;
  foreach ($_SESSION['classes'] as $cid => $class)
    if (isset($assmtsByClass[$class['courseID']]))
      $clist[$cid] =
	HTML::li(
	  HTML::b(callback_url($class['cname'], 'selectClass&cid=' . $cid, array('style'=>"color:blue"))),
	  ': ',
	  HTML::raw('&nbsp;&nbsp;'),
	  assignmentLinks($cid, $assmtsByClass[$class['courseID']], $nextWeek, $lastWeek, $classOrder));

  arsort($classOrder);
  foreach ($classOrder as $cid => $date_ignored)
    $ul->pushContent($clist[$cid]);
}

function adminFullHomePage($ul) {
  homePageClassList($ul, 'isNotTestOrDisusedClass');
  $ul->pushContent(HTML::hr());
  homePageClassList($ul, 'isTestClass');
  $ul->pushContent(HTML::hr());
  homePageClassList($ul, 'isDisusedClass');
  $ul->pushContent(HTML::hr());
  homePageClassList($ul, 'isArchiveClass');
}

function studentClassHome($msg, $class) {
  require_once 'showAllocations.php';
  loadAvailableAssignments($class);

  if (count($_SESSION['availableAssignments']) == 1)
    $content = viewStudentAssignment(classIDToCid($class['courseID']), 0);
  else {
    $readyToSubmit = array();
    $readyToReview = array();
    $readyToFeedback = array();
    foreach ($_SESSION['availableAssignments'] as $aID => $assmt) {
      if ($assmt['can-upload'] && nowBetween(null, $assmt['submissionEnd']))
	$readyToSubmit[$aID] = $assmt;
      else if ($assmt['expecting-reviewing'] && nowBetween($assmt['submissionEnd'], $assmt['reviewEnd']))
	$readyToFeedback[$aID] = $assmt;
      else if ($assmt['expecting-feedback'] && nowBetween($assmt['reviewEnd'], null))
	$readyToFeedback[$aID] = $assmt;
    }
    
    uasort($readyToSubmit,   'sortAssmtBySubmissionEnd');
    uasort($readyToReview,   'sortAssmtByReviewEnd');
    uasort($readyToFeedback, 'sortAssmtBySubmissionEnd');

    $message = empty($readyToSubmit) && empty($readyToReview) && empty($readyToFeedback)
      ? HTML::h3(_('There are no assignments currently available.'))
      : '';
    
    $content = HTML::div(HTML::h1($class['cname']),
			 $message,
			 studentClassHomeSection(_('Submissions due'),    $readyToSubmit,   'submissionEnd'),
			 studentClassHomeSection(_('Reviews due'),        $readyToReview,   'reviewEnd'),
			 studentClassHomeSection(_('Feedback available'), $readyToFeedback, null));
  }

  return HTML(homeSidebar(),
	      HTML::div(array('id'=>'rightContent'),
			pendingMessages($msg),
			$content));
}

function studentClassHomeSection($title, $assmts, $date) {
  if (empty($assmts))
    return '';
  $ul = HTML::ul();
  foreach ($assmts as $aID => $assmt) {
    $cid = classIDToCid($assmt['courseID']);
    $ul->pushContent(HTML::li(callback_url(className($cid), "selectClass&cid=$cid"),
			      ": ",
			      callback_url("$assmt[aname]."
					   . ($date == null ? '' : " Due " . formatDateString($assmt[$date])),
					   "viewStudentAsst&cid=$cid&aID=$aID")));
  }

  return HTML(HTML::h3($title), $ul);
}

function homePageClassList($ul, $filter) {
  $classOrder = array();
  $clist = array();
  $now = time();
  $nextWeek = $now + 7 * 24 * 60 * 60;
  $lastWeek = $now - 7 * 24 * 60 * 60;
  global $gHaveInstructor;
  foreach ($_SESSION['classes'] as $cid => $row) {
    if (!call_user_func($filter, $row['cname']))
      continue;
    // Display classes along with the next few assignments due.
    // Order classes by date of next assignment.
    $clist[$cid] =
      HTML::li(
	HTML::b(callback_url($row['cname'], 'selectClass&cid=' . $cid, array('style'=>"color:blue"))),
	': ',
	HTML::raw('&nbsp;&nbsp;'),
	assignmentLinks(
	  $cid,
	  fetchAll('select assmtID, isActive, submissionEnd, date_format(submissionEnd, "%D %b %Y") as d from Assignment'
		   . " where courseID=$row[courseID] and submissionEnd is not null" . ($gHaveInstructor ? '' : ' and isActive = 1')
		   . ' order by submissionEnd asc'),
	  $nextWeek,
	  $lastWeek,
	  $classOrder));
  }

  arsort($classOrder);
  foreach ($classOrder as $cid => $date_ignored)
    $ul->pushContent($clist[$cid]);
  foreach ($_SESSION['classes'] as $cid => $row) {
    if (!call_user_func( $filter, $row['cname']))
      continue;
    if (!isset( $classOrder[$cid]))
      $ul->pushContent(
	HTML::li(HTML::b(callback_url($row['cname'], 'selectClass&cid=' . $cid, array('style'=>"color:blue")))));
  }
}

function assignmentLinks($cid, $assmtList, $nextWeek, $lastWeek, &$classOrder) {
  $alinks = HTML::span();
  if (empty($assmtList))
    $alinks->pushContent('(no assignments)');
  else {
    //- Display the most recently completed and next two upcoming assignments
    $firstFuture = count($assmtList) - 1;
    foreach ($assmtList as $i => $assmt )
      if ($now < strtotime($assmt['submissionEnd']))
	$firstFuture = $i;
    if (count($assmtList) - $firstFuture < 3)
      $start = max(0, count($assmtList) - 3);
    else
      $start = max(0, $firstFuture);
    $end = min($start + 3, count($assmtList));
    
    for ($i = $start; $i < $end; $i++) {
      $assmt = $assmtList[$i];
      $submitEnd = strtotime($assmt['submissionEnd']);
      $colour = $submitEnd > $lastWeek && $submitEnd < $nextWeek ? 'red' : 'blue';
      if (!$assmt['isActive']) $colour = 'black';
      $alinks->pushContent(
	callback_url(
	  "$assmt[d]; ",
	  "viewAssignment&cid=$cid&assmtID=$assmt[assmtID]",
	  array('style'=>"color:$colour")));
    }

    if ($end < count( $assmtList))
      $alinks->pushContent(HTML::raw(' &hellip'));
    if ($start != 0)
      $alinks->unshiftContent(HTML::raw('&hellip;; '));
    $classOrder[$cid] = $assmtList[$firstFuture < 0 ? $start : $firstFuture]['submissionEnd'];
  }

  return $alinks;
}


$CmdActions['viewAssignment'] = array();
function viewAssignment() {
  list ($assmt, $assmtID, $cid, $courseID) = selectAssmt();
  if (! isset($_SESSION['classes']) || ! isset($_SESSION['classes'][$cid]))
    return homePage(_('There was a problem finding the class you selected.  Please try again'));

  $class = $_SESSION['classes'][$cid];
  switch (primaryRole($class['roles'])) {
  case 'guest':
  case 'instructor':
    require_once 'viewAssignment.php';
    return viewInstructorAssignment($cid, $assmt);
  case 'student':
  case 'marker':
    require_once 'showAllocations.php';
    loadAvailableAssignments($class);
    if (isset($_SESSION['availableAssignments']))
      foreach ($_SESSION['availableAssignments'] as $aID => $avail)
        if ($assmt['assmtID'] == $avail['assmtID'])
          return viewStudentAssignment($cid, $aID);
  case 'other':
    return warning(_('You do not have any access rights set up for this class'));
  }
}

$CmdActions[ 'changePassword'] =
  array( 'load' => 'changePasswd.php',
	 'menu' => 'special',
	 'name'=> _('Change password'));
$CmdActions[ 'savePassword'] =
  array( 'load' => 'changePasswd.php' );
$CmdActions[ 'setEmail'] =
  array( 'load' => 'changePasswd.php' );


function addPendingMessage( ) {
  $msg = HTML( );
  foreach( func_get_args() as $arg )
    $msg->pushContent( $arg );

  if( ! isset( $_SESSION['message'] ) )
    $_SESSION['message'] = array( $msg );
  else
    $_SESSION['message'][] = $msg;
}

function addWarningMessage( ) {
  $msg = HTML( );
  foreach( func_get_args() as $arg )
    $msg->pushContent( $arg );

  if( ! isset( $_SESSION['warning'] ) )
    $_SESSION['warning'] = array( $msg );
  else
    $_SESSION['warning'][] = $msg;
}

function pendingMessages( $extraMsg = null ) {
  $msgs = array( );
  
  if( isset( $_SESSION['message'] ) ) {
    if( is_array( $_SESSION['message'] ) )
      foreach( $_SESSION['message'] as $message )
	$msgs[] = $message;
    else
      $msgs[] = $_SESSION['message'];
    unset( $_SESSION['message'] );
  }

  $warns = array( );
  if( isset( $_SESSION['warning'] ) ) {
    if( is_array( $_SESSION['warning'] ) )
      foreach( $_SESSION['warning'] as $message )
	$warns[] = $message;
    else
      $warns = $_SESSION['warning'];
    unset( $_SESSION['warning'] );
  }

  if( $extraMsg != null )
    $msgs[] = $extraMsg;

  if( count( $msgs ) == 1 )
    $messages = message( $msgs[ 0 ] );
  else if( count( $msgs ) > 1 ) {
    $ul = HTML::ul( );
    foreach( $msgs as $m )
      $ul->pushContent( HTML::li( $m ) );
    $messages = message( $ul );
  }

  if( count( $warns ) == 1 )
    $warnings = warning( $warns[ 0 ] );
  else if( count( $warns ) > 1 ) {
    $ul = HTML::ul( );
    foreach( $warns as $w )
      $ul->pushContent( HTML::li( $w ) );
    $warnings = warning( $ul );
  }

  return HTML( $warnings, $messages );
}

function homeSidebar( ) {
  global $CmdActions;

  $nav = HTML::div(array('class'=>"btn-group-vertical btn-group-sm"));
  foreach( $CmdActions as $action => $cmd )
    if( is_array( $cmd )
	&& isset( $cmd['name'] )
	&& isset( $cmd['menu'] )
	&& ( $cmd['menu'] == 'advanced' || $cmd['menu'] == 'special' )
	)
      $nav->pushContent(Button($cmd['name'], $action));
  
  if( $nav->isEmpty( ) )
    return '';
  else
    return HTML::div(array('id'=>"sideBar"), $nav);
}

function loadClasses( $force = false, $restrict = -1 ) {
  if( $force )
    unset( $_SESSION['classes'] );

  if( ! isset( $_SESSION['userID'] ) )
    return;

  if( ! isset( $_SESSION['classes'] ) ) {

    if( $_SESSION['status'] == 'inactive' ) {
      $_SESSION['classes'] = array( );
      addWarningMessage(_('This account is inactive. No classes will be shown.'));
    } else {
      ensureDBconnected( 'loadClasses' );
      $csByID = array( );

      if( $restrict != -1 )
	//- Used when impersonating, to view only the selected class
	$cond = ' AND c.courseID = ' . (int)$restrict;
      else
	$cond = '';

      $cactice = ' AND cactive';

      if ($restrict == -1
	  && isset($_SESSION['status'])
	  && ($_SESSION['status'] == 'superuser' || $_SESSION['status'] == 'administrator')) {
	//- Non-impersonated admin see all classes.  Additional roles will be picked up as usual.
	$role = $_SESSION['status'] == 'superuser' ? 8 : 4;
	foreach (fetchAll("select courseID, $role as roles, cname, cactive, cident, subject from Course"
			  . ' where instID = '. (int)$_SESSION['instID'] . $cactive ) as $c) {
	  $c['reviewer'] = array();
	  $c['author'] = array();
	  $csByID[$c['courseID']] = $c;
	}
      }
      
      $whoEtc = (int)$_SESSION['userID'] . " AND c.instID = " . (int)$_SESSION['instID'];
      foreach( fetchAll( 'SELECT UserCourse.courseID, roles, cname, cactive, cident, subject'
			 . ' FROM UserCourse NATURAL JOIN Course c'
			 . " WHERE userID = $whoEtc $cactive$cond") as $c ) {
	$c['reviewer'] = array( );
	$c['author'] = array( );
	$csByID[ $c['courseID'] ] = $c;
      }
      
      foreach( fetchAll( 'SELECT a.courseID, a.assmtID, 2 AS roles, cname, cactive, cident, subject'
			 . ' FROM Reviewer NATURAL JOIN Assignment a'
			 . ' INNER JOIN Course c ON c.courseID = a.courseID'
			 . " WHERE reviewer = $whoEtc") as $row ) {
	if( ! isset( $csByID[ $row['courseID']] ) )
	  $csByID[ $row['courseID'] ] = $row;
	else
	  $csByID[ $row['courseID'] ]['roles'] |= 2;
	$csByID[ $row['courseID'] ]['reviewer'][] = $row['assmtID'];
      }

      foreach( fetchAll( 'SELECT a.courseID, a.assmtID, 1 AS roles, cname, cactive, cident, subject'
			 . ' FROM Author NATURAL JOIN Assignment a'
			 . ' INNER JOIN Course c ON c.courseID = a.courseID'
			 . " WHERE author = $whoEtc") as $row ) {
	if( ! isset( $csByID[ $row['courseID'] ] ) )
	  $csByID[ $row['courseID'] ] = $row;
	else
	  $csByID[ $row['courseID'] ]['roles'] |= 1;
	$csByID[ $row['courseID'] ]['author'][] = $row['assmtID'];
      }
      
      $classes = array_values( $csByID );
      uasort( $classes, 'class_order' );
      $_SESSION['classes'] = $classes;
    }
  }
}


function isBlessed( ) {
  if( ! isset( $_SESSION ) || ! isset( $_SESSION['userID'] ) )
    return -1;

  if( ! isset( $_SESSION['isBlessed'] ) ) {
    global $gHaveAdmin;
    if( $gHaveAdmin )
      $cond = ""; //- any class will do!
    else
      $cond = "AND cuserID = " . (int)$_SESSION['userID'];
    $classID = fetchOne( "SELECT courseID FROM Course WHERE cactive $cond LIMIT 1", 'courseID' );
    if( $classID !== null )
      $_SESSION['isBlessed'] = $classID;
    else
      $_SESSION['isBlessed'] = -1;
  }
  return $_SESSION['isBlessed'];
}

function class_order( $c1, $c2 ) {
  return strcasecmp( $c1['cname'], $c2['cname'] );
}


$CmdActions['selectClass'] = array( );

function selectClass( ) {
  list( $cid ) = checkREQUEST( '_cid' );

  if( ! isset( $_SESSION['classes'] ) || ! isset( $_SESSION['classes'][ $cid ] ) )
    return homePage( _('There was a problem finding the class you selected.  Please try again') );

  $class = $_SESSION['classes'][ $cid ];

  switch( primaryRole( $class['roles'] ) ) {
  case 'guest':
  case 'instructor':
    return instructorClassView( $cid, $class );
  case 'student':
  case 'marker':
    require_once 'showAllocations.php';
    return studentClassView( $cid, $class );
  case 'other':
    return warning( _('You do not have any access rights set up for this class'));
  }
}


function primaryRole( $roles ) {
  if( ($roles&8) != 0 )
    return 'instructor';
  if( ($roles&4)!=0 )
    return 'guest';
  if( ($roles&2)!=0 )
    return 'marker';
  if( ($roles&1)!=0 )
    return 'student';
  return 'other';
}

$CmdActions['studentPage'] = array( );
function studentPage( ) {
  list( $cid ) = checkREQUEST( '_cid' );

  if( ! isset( $_SESSION['classes'] ) || ! isset( $_SESSION['classes'][ $cid ] ) )
    return homePage( _('There was a problem finding the class you selected.  Please try again') );

  $class = $_SESSION['classes'][ $cid ];
  if( ($class['roles'] & 3) != 0 ) {
    require_once 'showAllocations.php';
    return studentClassView( $cid, $class );
  } else
    return warning(_('You do not have access rights set up to view this class as a student') );
}

function instructorClassView( $cid, $class ) {
  $rs = checked_mysql_query( 'SELECT aname, assmtID, submissionEnd, reviewEnd, isActive FROM Assignment'
			     . ' WHERE courseID = ' . (int)$class['courseID']
			     . ' AND isReviewsFor IS NULL'
			     . ' ORDER BY submissionEnd DESC');

  $ul = HTML::ul(array('class'=>"list-unstyled"));
  $n = 0;
  while( $row = $rs->fetch_assoc() ) {
    if( trim($row['aname']) == '' )
      $row['aname'] = '(unnamed assignment)';
    $li = HTML::li( HTML::span(callback_url($row['aname'], "viewAssignment&cid=$cid&assmtID=$row[assmtID]"),
			       ': submission end: ', formatDateString( $row['submissionEnd'] ),
			       '; reviewing end: ', formatDateString( $row['reviewEnd'] )) );
    if( ! $row['isActive'] ) {
      $li->setAttr( 'class', 'inactive' );
      $li->setAttr( 'title', _('The assignment is inactive') );
    }

    $rrs = checked_mysql_query( "SELECT aname, assmtID, reviewEnd, isActive FROM Assignment WHERE isReviewsFor = $row[assmtID]" );
    while( $rr = $rrs->fetch_assoc() ) {
      $rrli = HTML::li( HTML::span( callback_url( $rr['aname'], "viewAssignment&cid=$cid&assmtID=$rr[assmtID]" ),
				    '; reviewing end: ', formatDateString( $rr['reviewEnd'] )));
      if( ! $rr['isActive'] ) {
	$rrli->setAttr( 'class', 'inactive-assignment' );
	$rrli->setAttr( 'title', _('The review marking assignment is inactive') );
      }
      $li->pushContent( HTML::ul( $rrli ));
    }

    $ul->pushContent( $li );
    $n++;
  }
  if( $ul->isEmpty( ) )
    $aSection = '';
  else
    $aSection = HTML(HTML::h2(_('Assignments')), $ul);

  $stats = array('guest'=>0, 'marker'=>0, 'student'=>0);
  foreach( fetchAll( 'SELECT count(*) AS n,'
		     . ' IF((roles&8)<>0,"instructor", IF((roles&4)<>0,"guest", IF((roles&2)<>0,"marker",IF((roles&1)<>0,"student","other")))) AS mainRole'
		     . ' FROM UserCourse'
		     . " WHERE courseID = $class[courseID]"
		     . ' GROUP BY mainRole ORDER BY mainRole ASC' ) as $na )
    $stats[ $na['mainRole'] ] = $na['n'];

  $instructors = fetchAll( 'SELECT IFNULL(uident, CONCAT("u-", UserCourse.userID)) AS instructor'
			   . ' FROM UserCourse LEFT JOIN User ON User.userID = UserCourse.userID'
			   . " WHERE courseID = $class[courseID]"
			   . ' AND (roles&8) <> 0',
			   'instructor' );

  $subject = empty($class['subject']) ? _('not specified') : $class['subject'];
  
  
  return HTML( instructorSideBar($cid),
	       HTML::div( array('id'=>"rightContent", 'class'=>'list-page'),
			  pendingMessages( ),
			  HTML::h1($class['cname'], HTML::small(" (#$class[courseID])")),
			  HTML::p( count($instructors) == 1
				   ? Sprintf_('The instructor is %s', $instructors[0])
				   : Sprintf_('The instructors are %s', join(', ', $instructors))),
			  HTML::p( _('The class has '),
				   $stats['guest'] == 0 ? ''
				   : sprintf(ngettext('one guest',
						      '%d guests',
						      $stats['guest']),
					     $stats['guest']),
				   $stats['guest'] > 0 && $stats['marker'] > 0 ? _(', ') : '',
				   $stats['marker'] == 0 ? ''
				   : sprintf(ngettext(' one marker',
						      ' %d markers',
						      $stats['marker']),
					     $stats['marker']),
				   $stats['guest'] + $stats['marker'] > 0 ? _(' and ') : '',
				   sprintf(ngettext(' one student',
						    ' %d students',
						    $stats['student']),
					   $stats['student'])),
			  HTML::p(_('The subject is '), callback_url($subject, "editClass&cid=$cid")),
			  $aSection));
}


function instructorSidebar($cid) {
  $nav = HTML::div(array('class'=>"btn-group-vertical btn-group-sm"));
  $actionList = array( 'editClass'       => _('Edit class details') ,
		       'quickClassList'  => _('Edit class list'),
		       'resetPassword'   => _('Reset password'),
		       'newAssignment'   => _('Create assignment'),
		       'loginAsStudent'  => _('Impersonate other user'),
		       'cloneClass'      => _('Copy this class'),
		       'cloneAsTestClass'=> _('Clone as test class'),
		       'deleteClass'     => _('Delete this class'),
		       'exportClass'     => _('Export class as XML'));
  if (!empty($_SESSION['classes'][$cid]['cident']))
    $actionList['resetClassAccess'] = _('Reset class access');
  if (isBlessed() == -1)
    unset($actionList['cloneClass']);
  foreach( $actionList as $link => $name )
    if( findCommand( $link ) )
      $nav->pushContent(Button($name, "$link&cid=$cid"));
  if( $nav->isEmpty( ) )
    return '';
  else
    return HTML::div(array('id'=>'sideBar'), $nav);
}


function plural( $n, $what ) {
  if( (int)$n == 1 )
    return "$n $what";
  else
    return "$n ${what}s";
}

function findCommand( $act ) {
  global $CmdActions;
  if( isset( $CmdActions[ $act ] ) ) {
    $cmd = $CmdActions[ $act ];
    if( ! isset( $cmd['call'] ) )
      $cmd['call'] = $act;
    return $cmd;
  }
  return false;
}

function runCommand( $cmd ) {
  try {
    if( isset( $cmd['load'] ) )
      require_once $cmd['load'];

    return HTML( call_user_func( $cmd['call'] ));
  } catch( Exception $ex ) {
    return HTML(
      HTML::h1(_('The action was not able to be performed because of an error')),
      HTML::p($ex->getMessage()));
  }
}

/*
  ClassID mapping
 */
function cidToClassId( $cid ) {
  if( isset( $_SESSION['classes'] ) && isset( $_SESSION['classes'][ $cid ] ) )
    return $_SESSION['classes'][ $cid ][ 'courseID' ];
  else
    return -1; //- this should fail to match any valid courseID
}

function classIDToCid( $courseID ) {
  if( isset( $_SESSION['classes'] ) )
    foreach( $_SESSION['classes'] as $cid => $class )
      if( $courseID == $class['courseID'] )
	return $cid;
  return null;
}

function className( $cid ) {
  if( isset( $_SESSION['classes'] ) && isset( $_SESSION['classes'][ $cid ] ) )
    return $_SESSION['classes'][ $cid ]['cname'];
  else
    return "?";
}


function assmtName( $cid, $assmtID ) {
  if( isset( $_SESSION['availableAssignments'] ) )
    foreach( $_SESSION['availableAssignments'] as $a )
      if( $a['assmtID'] == $assmtID ) {
	$assmt = $a;
	break;
      }

  if( ! isset( $assmt ) )
    $assmt = current( fetchAll( "SELECT aname FROM Assignment WHERE assmtID = $assmtID"
				. ' AND courseID = ' . cidToClassId( $cid )));
  if( empty( $assmt ) || empty( $assmt['aname'] ) )
    return sprintf(_('Assignment #%d'), $assmtID);
  else
    return $assmt['aname'];
}

function selectAssmt( ) {
  list( $assmtID, $cid ) = checkREQUEST( '_assmtID', '_cid' );

  $fields = func_get_args();
  if( empty( $fields ) )
    $fields[] = '*';
  else {
    $fields[] = 'aname';
    $fields[] = 'assmtID';
    $fields[] = 'isReviewsFor';
  }
  $courseID = cidToClassId( $cid );
  $assmt = fetchOne( 'SELECT ' . join(',', array_unique( $fields ))
		     . ' FROM Assignment'
		     . " WHERE assmtID = $assmtID AND courseID = $courseID" );
  if( ! empty( $assmt['isReviewsFor'] ) )
    extraHeader( '$(".mainScreenContent").css("background-color", "#ccffff")', 'onload');
  return array( $assmt, $assmtID, $cid, $courseID );
}

function missingAssmt( ) {
  $cid = (int)$_REQUEST['cid'];
  return HTML(warning( _('You cannot access the selected assignment') ),
	      BackButton());
}


function localeSelection( ) {
  if( ! function_exists( 'bindtextdomain' ) )
    return '';
  static $languages = array('In English'=>'en', 'På svenska'=>'sv_SE');
  $curr = 'en';
  if( ! empty( $_SESSION['locale'] ) )
    $curr = $_SESSION['locale'];
  $ul = HTML::ul( );
  foreach( $languages as $name => $locale )
    if( $locale != $curr )
      $ul->pushContent( HTML::li( HTML::a( array('href'=>"$_SERVER[REQUEST_URI]&locale=$locale"), $name)));
  if( $ul->isEmpty( ) )
    return '';
  else
    return HTML::div( array('id'=>'locale'), $ul );
}

function check_golive( ) {
  if( ! isset( $_SESSION['golive'] ) ) {
    $_SESSION['golive'] = array( );
    $now = quote_smart( date('Y-m-d H:i:s') );
    //- We need to check the institution, as that's where our time zone comes from
    $rs = checked_mysql_query( 'SELECT assmtID, submissionEnd FROM Assignment a LEFT JOIN Course c ON a.courseID=c.courseID WHERE'
			       . " instID = $_SESSION[instID] AND"
			       . " isActive AND allocationsDone IS NULL AND submissionEnd < $now + INTERVAL 2 HOUR" );
    while( $row = $rs->fetch_assoc() )
      $_SESSION['golive'][ $row['assmtID'] ] = $row['submissionEnd'];
  }

  $done = array( );
  $now = date('Y-m-d H:i:s');
  foreach( $_SESSION['golive'] as $assmtID => $golive )
    if( $golive < $now ) {
      $done[] = $assmtID;
      checked_mysql_query( 'LOCK TABLE Assignment WRITE, Allocation WRITE, Allocation a READ, Allocation b READ, LastMod WRITE, User READ, UserCourse READ, Essay READ, Reviewer READ, Author READ, Stream READ'
			   . ', `Groups` READ, GroupUser READ, AllocationAudit WRITE');
      /* There is a serious race condition here (consisting of
	 everyone who has logged in during the past two hours), so we
	 re-read allocationsDone inside the table lock to avoid
	 calling doUnsupervisedAllocation more than once.  Note that
	 doUnsupervisedAllocation always sets allocationsDone. */
      $rs = checked_mysql_query( 'SELECT * FROM Assignment'
				 . " WHERE assmtID = $assmtID"
				 . " AND isActive AND allocationsDone IS NULL AND submissionEnd < '$now'");
      $row = $rs->fetch_assoc();
      if( $row ) {
	require_once 'doAllocation.php';
	doUnsupervisedAllocation( $row );
	QueryTrace(false);
      }
      checked_mysql_query( 'UNLOCK TABLES' );
    }
  foreach( $done as $assmtID )
    unset( $_SESSION['golive'][ $assmtID ] );
}

/* ----------------------------------- */

$noDatabase = $_REQUEST['action'] == 'error' && $_REQUEST['code'] == 2;

if( $noDatabase ) {
  //- If the MySQL server is down, we will be unable to connect.  This
  //- will generate an error redirect, and we end up here.  If we were
  //- to load session.php, another error redirect would result, ad
  //- infinitum (well, until the server redirect limit is reached).
  $_SESSION = array( );
} else {
  if( defined('USE_DATABASE_SESSION') && USE_DATABASE_SESSION )
    require_once 'session.php';

  session_start( );

  if( function_exists( 'date_default_timezone_set' ) && institutionHasFeature('TIMEZONE') )
    date_default_timezone_set( getInstitution( )->features['TIMEZONE'] );

  if( isset( $_REQUEST['locale'] ) )
    $_SESSION['locale'] = $_REQUEST['locale'];

  if( isset( $_SESSION['locale'] ) && function_exists('bindtextdomain') ) {
    putenv( "LC_ALL=$_SESSION[locale]" );
    setlocale( LC_ALL, $_SESSION['locale'] );
    bindtextdomain('messages', './locale' );
    textdomain( 'messages' );
  }

  //- Here we first learn if we are using a different database to the
  //- default
  if (!empty($_SESSION['database']) && $_SESSION['database'] != AROPA_DB_DATABASE) {
    $db = ensureDBconnected('');
    if (!$db->select_db($_SESSION['database']))
      reportCriticalError('Aropa', 'Could not select database: ' . $db->error);
  }

  //- Look for any assignments that may have just become active
  if( isset( $_SESSION['userID'] ) )
    check_golive( );
}

if( empty( $_REQUEST['action'] ) ) {
  $_REQUEST['action'] = 'home';
}


/*
if( ! in_array( $_REQUEST['action'], array('error', 'logout', 'login', 'logo') )
    && isset( $_SESSION['User-Agent'] )
    && $_SESSION['User-Agent'] != $_SERVER['HTTP_USER_AGENT']
    )
  ;//securityAlert( 'User agent does not match session' );
*/

if (LOG_ERRORS == 'debug' && isset($_SESSION['debug'])) {
  $debug = $_SESSION['debug'];
  unset($_SESSION['debug']);
}

if( empty( $_SESSION['userID'] ) && ! in_array( $_REQUEST['action'], array('authenticate', 'error', 'logo')))
  $_REQUEST['action'] = 'login';

if( isset( $_SESSION['tmpLogin'] ) && ! in_array( $_REQUEST['action'], array('savePassword', 'logout', 'error', 'logo')))
  $_REQUEST['action'] = 'changePassword';


global $gHaveAdmin;
$gHaveAdmin = (isset( $_SESSION['status'] ) && $_SESSION['status'] == 'superuser') || (isset($_SESSION['revert']) && $_SESSION['revert']['status'] == 'superuser');
//- Allow impersonating superusers a backdoor into the full toolkit
if( $gHaveAdmin )
  require_once 'adminCmds.php';
$gHaveAdmin = isset( $_SESSION['status'] ) && $_SESSION['status'] == 'superuser';

loadClasses( );

global $gHaveInstructor;
global $gHaveGuest;
global $gHaveMarker;
global $gHaveStudent;
$gHaveInstructor = $gHaveAdmin;
$gHaveGuest      = false;
$gHaveMarker     = false;
$gHaveStudent    = false;
if( isset( $_REQUEST['cid'] )
    && isset( $_SESSION['classes'] )
    && isset( $_SESSION['classes'][ (int)$_REQUEST['cid'] ] ) ) {
  $roles = $_SESSION['classes'][ (int)$_REQUEST['cid'] ]['roles'];
  if( ($roles&8)  != 0 ) $gHaveInstructor = true;
  if( ($roles&4)  != 0 ) $gHaveGuest      = true;
  if( ($roles&2)  != 0 ) $gHaveMarker     = true;
  if( ($roles&1)  != 0 ) $gHaveStudent    = true;
}

if( isBlessed() != -1 )
  $CmdActions['newClass']
    = array( 'name' => _('Add a new class'),
	     'menu' => 'advanced',
	     'load' =>'editCourses.php');

if( $gHaveInstructor || $gHaveGuest )
  require_once 'instructorCmds.php';

if( $gHaveInstructor || $gHaveMarker || $gHaveStudent )
  require_once 'reviewerCmds.php';

if( $gHaveStudent || $gHaveMarker || $gHaveGuest || $gHaveInstructor )
  $CmdActions['download']
    = array( 'title' => _('Download a document'),
	     'load'  =>'download.php' );


/*
//- Utility for checking that the CmdActions array is fully defined
foreach( $CmdActions as $action => $cmd ) {
  $call = isset( $cmd['call'] ) ? $cmd['call'] : $action;
  if( isset( $cmd['load'] ) )
    require_once $cmd['load'];
  if( ! function_exists( $call ) )
    Debug( $action );
}
*/
$cmd = findCommand( $_REQUEST['action'] );

if( $cmd )
  $content = runCommand( $cmd );
else
  $content = HTML( HTML::h1( _('Invalid action') ), warning( _('The action you have attempted cannot be performed') ));
  //  securityAlert( "invalid action - \"$_REQUEST[action]\"" );

// If asked by the caller, also return to a previous page
if( isset( $_REQUEST['resume'] ) ) {
  $tmp = $_REQUEST;
  parse_str(rawurldecode($_REQUEST['resume']) ?? '', $_REQUEST);
  $action = empty( $_REQUEST['action'] ) ? 'home' : $_REQUEST['action'];
  $cmd2 = findCommand( $action );
  $content->pushContent( HTML::hr( ) );
  if( $cmd2 ) {
    $content->pushContent( runCommand( $cmd2 ));
  } else
    $content->pushContent( HTML::p( sprintf( _( '(Unable to perform the action %s)'), HTML::q( $action ))));
  $_REQUEST = $tmp;
}

if( isset( $cmd['title'] ) ) {
  $title = $cmd['title'];
} else if( isset( $cmd['name'] ) ) {
  $title = $cmd['name'];
} else {
  $title = HTML::raw( _('The Arop&auml; Peer Review System'));
}


function makeBreadcrumbs( ) {
  $ul = HTML::ol( array('class'=>'breadcrumb'),
		  HTML::li(callback_url(_('Home'), 'home')));
  if( isset( $_REQUEST['cid'] ) ) {
    $cid = (int)$_REQUEST['cid'];
    $ul->pushContent(HTML::li(callback_url(className( $cid ), "selectClass&cid=$cid")));

    if( isset( $_REQUEST['assmtID'] ) ) {
      $assmtID = (int)$_REQUEST['assmtID'];
      $ul->pushContent(HTML::li(callback_url(assmtName($cid, $assmtID), "viewAssignment&assmtID=$assmtID&cid=$cid")));
    } else if( isset( $_REQUEST['aID'] ) ) {
      $aID = (int) $_REQUEST['aID'];
      if( isset( $_SESSION['availableAssignments'][ $aID ] ) ) {
	$assmt = $_SESSION['availableAssignments'][ $aID ];
	$ul->pushContent(HTML::li(callback_url($assmt['aname'], "viewStudentAsst&aID=$aID&cid=$cid")));
      }
    }
  }

  return $ul;
}

if( isset( $_REQUEST['omitMenu'] ) ) {
  $body = HTML::div($content);
} else {

  if( false && ! empty( $_SESSION['username'] ) && $_SESSION['username'] != $_SESSION['uident'] )
    $whoami = HTML::b( "$_SESSION[username] ($_SESSION[uident])" );
  else if (isset($_SESSION['uident']))
    $whoami = HTML::b( $_SESSION['uident'] );
  else
    $whoami = '(unknown)';
  if( $gHaveAdmin ) $whoami = HTML( $whoami, HTML::raw(' &ndash; '), getInstitution( )->shortname );
  if( isset( $_SESSION['revert'] ) )
    $logout = callback_url(sprintf(_('>> REVERT to %s'), $_SESSION['revert']['uident']), 'logout');
  else
    $logout = callback_url(_(">> LOGOUT"), 'logout');

  $systemMessages = '';
  if (!isset($_SESSION['dismiss-message']))
    foreach (glob('messages/*.txt') as $message) {
      if ($systemMessages == '') {
	global $gExtraJS;
	$systemMessages =
	  HTML::div(
	    array('class' => 'alert alert-info alert-dismissible'),
	    HTML::a(
	      array('onclick' => "jQuery.post('$_SERVER[PHP_SELF]?action=dismissMessage')", 'class' => 'close', 'data-dismiss' => 'alert', 'aria-label' => 'close'),
	      HTML::raw('&times')));
	$gExtraJS['bootstrap.min.js'] = true;
      }
      
      $systemMessages->pushContent(file_get_contents($message));
    }
  
  $body = HTML::body(HeaderDiv(HTML(_('You are logged in as '), $whoami, ' ', HTML::small($logout))),
		     HTML::div(array('class'=>'mainScreen'),
			       makeBreadcrumbs( ),
			       HTML::div( array('class'=>'mainScreenContent'),
					  $debug,
					  $systemMessages,
					  $content ),
			       HTML::div( array('class'=>'mainScreenFooter'))));
}

printDocumentAndExit($title, $body, $moreHead);

function dismissMessage() {
  if (is_array($_SESSION))
    $_SESSION['dismiss-message'] = true;
  header('HTTP/1.0 204 No Response');
}

//ob_end_flush( );
