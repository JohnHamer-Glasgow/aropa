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
if (file_exists('local-config.php'))
  require_once 'local-config.php';
else
  require_once 'config.php';
require_once 'compat.php';
require_once 'HtmlElement.php';

if( defined( "AROPA_SESSION_NAME" ) )
  session_name( AROPA_SESSION_NAME );
else
  session_name('AROPA');

// gettext-readiness
if( ! function_exists("_") ) {
  function _($x) { return $x; }
  function ngettext($sing, $plural, $n) {
    return $n == 1 ? $sing : $plural;
  }
  function gettext( $x ) { return $x; }
}
function gettext_noop( $x ) { return $x; }
function Sprintf_( /*...*/ ) {
  $args = func_get_args( );
  $fmt = array_shift( $args );
  $hargs = array( );
  foreach( $args as $a )
    $hargs[] = htmlentities( $a ?? '');
  return HTML::raw( vsprintf( $fmt, $hargs ));
}


$CmdActions['error'] = array('title' =>_('Error message'));
function error( ) {
  $code = isset( $_REQUEST['code'] ) ? $_REQUEST['code'] : 0;
  switch( $code ) {
  case 1:
    return HTML( HTML::h1( _('Sorry, a software error has occurred') ),
                 warning( _('This is probably due to a programming error.  The system is unable to complete the current operation.  An error report has been logged.') ));

  case 2:
    return HTML(HTML::h1(_('Unable to connect to the database server')),
		warning(_('This may be just a temporary problem, and may be due to the daily backup routine (which can take some time). Please try again later, and if the problem is still present then contact your instructor.')));

  case 3:
    //- These are almost all caused by the BACK button.  Logging out
    //- just makes the problem worse.
    return HTML( HTML::h1( _('Invalid operation') ),
                 warning( _('The action you have attempted cannot be performed.')));
//     return HTML( HTML::h1('Security alert'),
//                  HTML::p('An error occurred that could be the result of the deliberate abuse of this system.'),
//                  HTML::p('Monitoring has been turned on for this session.  Any further discrepancies will be logged.')
//                  );

  case 4:
    return HTML( HTML::h1( _('Security alert')),
                 warning( _('A second security anomoly has occurred. This session has been terminated.  Please contact the system administrator.' )));

  default:
    securityAlert( 'bad error code' );
  }
}


function Aropa( $case = 'sentence' ) {
  if( $case == 'sentence' )
    return HTML::raw('Arop&auml;');
  elseif( $case == 'caps' )
    return HTML::raw('AROP&Auml;');
  else
    return HTML::raw('arop&auml;');
}


function stripslashes_deep( $value ) {
  return is_array( $value )
    ? array_map( 'stripslashes_deep', $value )
    : stripslashes( $value );
}

if( (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc())
    || (ini_get('magic_quotes_sybase') && strtolower(ini_get('magic_quotes_sybase')) != "off")
    ) {
  $_GET     = stripslashes_deep( $_GET );
  $_POST    = stripslashes_deep( $_POST );
  $_COOKIE  = stripslashes_deep( $_COOKIE );
  $_REQUEST = stripslashes_deep( $_REQUEST );
} 

//ini_set( 'zlib.output_compression', 1 );

if( ( isset( $_REQUEST['download'] ) || isset( $_REQUEST['essay'] ) )
    && strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE' ) !== false
    ) {
  //- see last entry in http://php3.de/manual/en/function.session-cache-limiter.php
  session_cache_limiter('public');
} else {
  //- We don't want any caching of pages, as pretty much everything is dynamic.
  session_cache_limiter( 'nocache' );
}
//session_cache_expire( 360 );
session_set_cookie_params(0); // This should expire the session when the browser closes
ini_set( 'session.gc_maxlifetime', 10*60*60 ); //- 10 hours
ini_set( 'session.cookie_lifetime', 10*60*60 );

ini_set( 'memory_limit',  '64M' );


function userErrorHandler( $errno, $errmsg, $filename, $linenum, $vars = null) {
  if (error_reporting() == 0 || in_array($errno, array(E_NOTICE, E_WARNING)))
    return;
  $dt = date("Y-m-d H:i:s");

  $errortype = array(E_ERROR           => "Error",
		     E_WARNING         => "Warning",
		     E_PARSE           => "Parsing Error",
		     E_NOTICE          => "Notice",
		     E_CORE_ERROR      => "Core Error",
		     E_CORE_WARNING    => "Core Warning",
		     E_COMPILE_ERROR   => "Compile Error",
		     E_COMPILE_WARNING => "Compile Warning",
		     E_USER_ERROR      => "User Error",
		     E_USER_WARNING    => "User Warning",
		     E_USER_NOTICE     => "User Notice");
  if (defined('E_STRICT'))
    $errortype[E_STRICT] = "Strict";

  $msg = "PHP " . (isset( $errortype[$errno] ) ? $errortype[$errno] : $errno) . "\n"
    . "\tDate:\t$dt\n"
    . "\tMsg:\t$errmsg\n"
    . "\tFile:\t$filename\n"
    . "\tLine:\t$linenum\n"
    . "\tBacktrace:\n" . get_backtrace( ) . "\n";

  if( $vars && in_array($errno, array( E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE ) ) )
    $msg .= "\tVars:\t" . print_r( $vars, true );

  //  $msg .= "\tSESSION:\t" . print_r( $_SESSION, true ) . "\n";

  if( isset( $_REQUEST ) ) {
    if( isset( $_REQUEST['passwd'] ) )
      $_REQUEST['passwd'] = '...';
    if( isset( $_REQUEST['password'] ) )
      $_REQUEST['password'] = '...';
    $msg .= "\tREQUEST:\t" . print_r( $_REQUEST, true ) . "\n";
  }

  switch( LOG_ERRORS ) {
  case 'debug':
    Debug($msg);
    break;
  case 'email':
    if (defined('ADMIN_EMAIL')) {
      mail(ADMIN_EMAIL, '[AROPA: PHP ' . $errortype[$errno] . ']', $msg);
      break;
    } // else fall through to log
  case 'log':
      error_log($msg, 0);
      break;
  }
  exit;
}

if( defined('LOG_ERRORS') ) {
  set_error_handler("userErrorHandler");
}


function get_backtrace( ) {
  $bt = "";
  if( function_exists('debug_backtrace') ) {
    foreach( debug_backtrace() as $arr ) {
      $bt .= $arr['function'];
      if( isset( $arr['file'] ) )
	$bt .= " [" . basename($arr['file']) . ": " . $arr['line'] . "]";
      $bt .= "\n";
    }
  }
  return $bt;
}

function reportCriticalError($location, $msg) {
  if (!isset($_SESSION['criticalError']))
    $_SESSION['criticalError'] = array();
  if (!isset($_SESSION['criticalError'][$location])) {
    ini_set('syslog.filter', 'raw');
    openlog('AROPA', LOG_PID, LOG_USER);
    $backtrace = get_backtrace();
    syslog(LOG_ERR, "$msg\n$backtrace");
    $_SESSION['criticalError'][$location] = true;
  }
      
  printDocumentAndExit(_('Internal error'),
		       HTML(HTML::h1(_('Sorry, an error occurred while processing that request.')),
			    warning(_('This may be just a temporary problem. Try again. If that doesn\'t work, then log off and back on. If the problem is still present then contact your instructor.'))));
}


function securityAlert( $problem ) {
  reportCriticalError('SECURITY', $problem);
}

$currentMethod = 'home';
function ensureDBconnected($whereAmI = null) {
  global $currentMethod;
  if ($whereAmI !== null)
    $currentMethod = $whereAmI;
  static $db = null;
  if ($db == null) {
    $db = new mysqli(AROPA_DB_HOST, AROPA_DB_USER, AROPA_DB_PASSWORD, AROPA_DB_DATABASE, AROPA_DB_PORT);
    if ($db->connect_errno)
      printDocumentAndExit(_('Database unavailable'),
			   HTML(HTML::h1(_('Unable to connect to the database server: '), $db->connect_error),
				warning(_('This may be just a temporary problem.  Try again later, and if the problem is still present then contact your instructor.'))));
  }

  $db->set_charset('utf8mb4');
  return $db;
}

global $query_trace;
$query_trace = defined('QUERY_TRACE') && QUERY_TRACE;

function QueryTrace($on = true) {
  global $query_trace;
  $old_value = $query_trace;
  $query_trace = $on;
  return $old_value;
}  

global $recentQueries;
$recentQueries = array();

function checked_mysql_query($query, $buffering = 'buffered') {
  global $currentMethod;
  $db = ensureDBconnected();

  global $recentQueries;
  if ($buffering == 'buffered')
    $rs = $db->query($query);
  else {
    $db->real_query($query);
    $rs = $db->use_result();
  }

  global $query_trace;
  if ($query_trace)
    error_log($query, 0);

  if (isset($_SESSION['logQueries']))
    Debug($query);

  if (! $rs)
    reportCriticalError($currentMethod, "Query failed:\n$query\nError: " . $db->error . "\nRecent queries:\n" . join("\n", $recentQueries));
  $recentQueries[] = $query;
  return $rs;
}

function fetchAll( $query, $field = null ) {
  $all = array( );
  $rs = checked_mysql_query( $query );
  while( $row = $rs->fetch_assoc() )
    $all[] = $field ? $row[ $field ] : $row;
  return $all;
}

function fetchOne($query, $field = null) {
  $rs = checked_mysql_query($query);
  $row = $rs->fetch_assoc();
  if ($row)
    return $field ? $row[$field] : $row;
  else
    return null;
}

function fetchRow($query) {
  $rs = checked_mysql_query($query);
  return $rs->fetch_row();
}


function quote_smart( $value, $forceString = false ) {
  if( is_null( $value ) )
    return 'NULL';
  else if( $forceString || is_string( $value ) ) {
    $db = ensureDBconnected( );
    return "'" . $db->real_escape_string( $value ) . "'";
  } else if( is_bool( $value ) )
    return $value ? 1 : 0;
  else if( is_numeric( $value ) )
    return $value;
  else
    reportCriticalError('quote_smart', 'Bad value - ' . gettype($value) . ' ' . print_r($value, true));
}

function makeInsertQuery($table, $data, $unquotedData = array()) {
   return
    "insert into $table("
     . join(',', array_merge(array_keys($data), array_keys($unquotedData)))
    . ') values ('
    . join(',', array_map('quote_smart', $data) + $unquotedData)
    . ')';
}

function makeInsertIgnoreQuery($table, $data, $unquotedData = array()) {
   return
    "insert ignore into $table("
     . join(',', array_merge(array_keys($data), array_keys($unquotedData)))
    . ') values ('
    . join(',', array_map('quote_smart', $data) + $unquotedData)
    . ')';
}

function makeReplaceQuery( $table, $data ) {
   return
    "REPLACE INTO $table("
    . join(',', array_keys( $data ))
    . ')VALUES('
    . join(',', array_map( 'quote_smart', $data ))
    . ')';
}

function makeUpdateQuery($table, $data, $unquotedData = array()) {
  $q = "update $table set ";
  $sep = "";
  foreach ($data as $field => $value) {
    $q .= "$sep$field=" . quote_smart($value);
    $sep = ",";
  }

  foreach ($unquotedData as $field => $value) {
    $q .= "$sep$field=$value";
    $sep = ",";
  }

  return $q;
}


function getInstitution($instID = null) {
  if ($instID == null)
    $instID = (int)$_SESSION['instID'];

  if (!isset($_SESSION['inst']) || $_SESSION['inst']->instID != $instID) {
    $rs = checked_mysql_query('SELECT instID, longname, shortname, features FROM Institution '
			      . ' WHERE instID = ' . (int)$instID);
    $inst = $rs->fetch_object();
    if (! $inst)
      $inst = (object)array('instID'=>$instID, 'longname'=>'?', 'shortname'=>'?', 'features'=>'');
    parse_str($inst->features ?? '', $features);
    unset($features['LTI_SECRET']);
    unset($features['LTI_CONSUMER_KEY']);
    unset($features['LDAP_SERVER']);
    unset($features['LDAP_BASE_DN']);
    $inst->features = $features;
    $_SESSION['inst'] = $inst;
  }
  
  return $_SESSION['inst'];
}

function institutionHasFeature( $f, $instID = null ) {
  $inst = getInstitution( $instID );
  return isset( $inst->features ) && isset( $inst->features[ $f ] );
}

function addMarks( &$marks, $mstr ) {
  parse_str($mstr ?? '', $marksP);
  foreach( $marksP as $item => $m ) {
    if( ! isset( $marks[ $item ] ) )
      $marks[ $item ] = array( );
    if( ! isset( $marks[ $item ][ $m ] ) )
      $marks[ $item ][ $m ] = 0;
    $marks[ $item ][ $m ]++;
  }
}


function redirect( $action, $args = "" ) {
  if( ! empty( $args ) )
    $action .= '&' . $args;
  $omit = isset( $_REQUEST['omitMenu'] ) ? '&omitMenu' : '';
  header( 'HTTP/1.1 303 See Other' );
  header( "Location: aropa.php?action=$action$omit" );

  global $debug;
  $_SESSION['debug'] = $debug;
  exit;
}

$debug = HTML( );
global $gAreDebugging;
$gAreDebugging = defined('ARE_DEBUGGING') && ARE_DEBUGGING;

function Debug($text) {
  global $gAreDebugging;
  if (!$gAreDebugging)
    return;
  if (strpos($_SERVER['HTTP_USER_AGENT'], 'FirePHP') !== false && file_exists('fb.php')) {
    require_once 'fb.php';
    fb($text);
  } else {
    global $debug;
    if (is_array($text) || is_object($text))
      $text = print_r($text, true);
    if (defined('LOG_ERRORS') && LOG_ERRORS == 'debug')
      $debug->pushContent(HTML::pre($text));
    error_log($text, 0);
  }
}

function warning( ) {
  $msg = HTML();
  foreach( func_get_args() as $arg )
    if( $arg != null )
      $msg->pushContent($arg);
  if( $msg->isEmpty())
    return '';
  return HTML::div(array('class'=>'alert alert-warning',
			 'role'=>'alert'),
		   HTML::span(array('class'=>'glyphicon glyphicon-warning-sign',
				    'aria-hidden')),
		   HTML::span(array('class'=>'sr-only'), _('Warning: ')),
		   $msg);
}


function message( ) {
  $msg = HTML();
  foreach( func_get_args() as $arg )
    if( $arg != null )
      $msg->pushContent($arg);
  if( $msg->isEmpty())
    return '';
  return HTML::div(array('class'=>'alert alert-info',
			 'role'=>'alert'),
		   HTML::span(array('class'=>'glyphicon glyphicon-flag',
				    'aria-hidden')),
		   HTML::span(array('class'=>'sr-only'), _('Note: ')),
		   $msg);
}

$gExtraOnload = array( );
$gExtraScript = array( );
$gExtraCSS    = array( );
$gExtraJS     = array( );
function extraHeader( $extra, $type ) {
  global $gExtraOnload;
  global $gExtraScript;
  global $gExtraCSS;
  global $gExtraJS;
  switch( $type ) {
  case 'onload': $gExtraOnload[] = $extra; break;
  case 'script': $gExtraScript[$extra] = true; break;
  case 'css':    $gExtraCSS[$extra] = true; break;
  case 'js':     $gExtraJS[$extra] = true; break;
  }
}

$gTypeaheadAjax = null;
function autoCompleteWidget($action) {
  global $gTypeaheadAjax;
  $gTypeaheadAjax = $action;
}

$gDatetimeWidget = false;
function datetimeWidget() {
  global $gDatetimeWidget;
  $gDatetimeWidget = true;
}

function printDocumentAndExit( $title, $body, $moreHeaders = "" ) {
  global $gExtraOnload;
  global $gExtraCSS;
  global $gExtraScript;
  global $gExtraJS;
  global $gTypeaheadAjax;
  global $gDatetimeWidget;

  $moreHead = HTML( );
  foreach( $gExtraCSS as $file => $true )
    $moreHead->pushContent( HTML::link( array('rel'=>"stylesheet",
					      'type'=>"text/css",
					      'href'=>"opencmd.php?t=style&f=$file")));
  foreach( $gExtraScript as $file => $true )
    $moreHead->pushContent( HTML::script( array('type'=>"text/javascript",
						'src'=>"opencmd.php?t=script&f=$file")));

  if ($gTypeaheadAjax != null) {
    $gExtraJS['bootstrap.min.js'] = true;
    $gExtraJS['bootstrap-typeahead.js'] = true;
    $gExtraOnload[] = "$('input.typeahead').typeahead({ajax: '$_SERVER[PHP_SELF]?action=$gTypeaheadAjax'})";
  }
  
  if ($gDatetimeWidget) {
    $gExtraJS['bootstrap.min.js'] = true;
    $gExtraJS['bootstrap-datetimepicker.min.js'] = true;
    $moreHead->pushContent(HTML::link(array('rel'=>"stylesheet",
					    'href'=>"resources/css/bootstrap-datetimepicker.min.css")));
    $gExtraOnload[] = "$('input[type=\"datetime-local-like\"]')
.attr('type', 'text')
.attr('autocomplete', 'off')
.val(function(i,v) {return v.replace(/(\d\d\d\d-\d\d-\d\d)T(\d\d:\d\d):\d\d/, '$1 $2');})
.datetimepicker({format: 'yyyy-mm-dd hh:ii', forceParse: false, weekStart: 0})";
  }

  foreach ($gExtraJS as $file => $true)
    $moreHead->pushContent(HTML::script(array('type'=>"text/javascript", 'src'=>"resources/js/$file")));

  if( ! empty( $gExtraOnload ) )
    $body->pushContent(Javascript("$(function(){" . join(';', $gExtraOnload) . ";});"));

  $moreHead->pushContent(HTML::link(array('rel'=>"stylesheet",
					  'href'=>"resources/css/bootstrap.min.css")),
			 HTML::link(array('rel'=>'stylesheet',
					  'type'=>'text/css',
					  'href'=>'https://fonts.googleapis.com/css?family=Roboto:400,500')),
			 HTML::link(array('rel'=>'stylesheet',
					  'type'=>'text/css',
					  'href'=>'resources/css/style.css')));

  $head = HTML::head(HTML::base(array('href'=>"$_SERVER[REQUEST_SCHEME]://$_SERVER[SERVER_NAME]:$_SERVER[SERVER_PORT]" . dirname($_SERVER['PHP_SELF']) . '/')),
		     HTML::meta(array('http-equiv'=>'Content-Type',
				       'content'   =>'text/html; charset=utf-8') ),
		      HTML::meta(array('http-equiv'=>"X-UA-Compatible", 'content'=>"IE=edge")),
		      HTML::meta(array('http-equiv'=>"Pragma", 'content'=>"no-cache")),
		      HTML::meta(array('http-equiv'=>"Expires", 'content'=>"-1")),
		      HTML::meta(array('name'=>'robots', 'content'=>'noindex, nofollow') ),
		      HTML::meta(array('name'=>"viewport",
				       'content'=>"width=device-width, initial-scale=1")),
		      HTML::meta(array('name'=>"Description",
				       'content'=>"Arop&auml: Peer review made easy")),
 		      HTML::link(array('rel' => 'shortcut icon', 'href' => "resources/img/favicon.ico")),
		      HTML::script(array('type'=>'text/javascript',
					 'src'=>'https://code.jquery.com/jquery-1.11.3.min.js' )),
		      $moreHead,
		      HTML::raw('<!--[if lt IE 9]>'),
		      HTML::script(array('src'=>"https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js")),
		      HTML::script(array('src'=>"https://oss.maxcdn.com/respond/1.4.2/respond.min.js")),
		      HTML::raw('<![endif]-->'),
		      HTML::title($title));
  
  while( ob_end_clean( ) )
    //- Discard any earlier HTML or other headers
    ;
  PrintXML(HTML(HTML::raw("<!DOCTYPE html>\n"), HTML::htmlx($head, $body)));
  exit;
}

function HeaderDiv($heading) {
  return HTML::div(array('class'=>'header-wrapper container-fluid'),
		   HTML::div(array('class'=>"container"),
			     HTML::div(array('class'=>'row'),
				       HTML::div(array('class'=>'logo col-lg-2 col-md-2 col-sm-3 col-xs-4 col-lg-offset-1'),
						 HTML::a(array('href'=>'aropa.php'),
							 HTML::div(array('class'=>'header'),
								   HTML::img(array('src'=>'resources/img/aropa-logo.png',
										   'class'=>'img img-responsive'))))),
				       HTML::div(array('class'=>'documentation col-lg-9 col-md-9 col-sm-8 col-xs-8'),
						 HTML::h3($heading)),
				       HTML::div(array('class'=>'triangle col-lg-2 col-md-2 col-sm-4 col-xs-4 col-lg-offset-1')))));
}
