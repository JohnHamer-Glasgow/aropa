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

function loginAsStudent( ) {
  $warning = '';
  $courseID = -1;
  global $gHaveAdmin;
  if( isset( $_REQUEST['cid'] ) )
    $courseID = cidToClassId( (int)$_REQUEST['cid'] );
  if( ! $gHaveAdmin && $courseID == -1 )
    return warning(_('The requested operation cannot be performed'));

  require_once 'users.php';
  $uident = isset( $_REQUEST['ident'] ) ? normaliseUident( $_REQUEST['ident'] ) : '';
  if( $uident != '' ) {
    $instID = (int)$_SESSION['instID'];
    $andClass = $courseID == -1 ? "" : " AND courseID = $courseID";
    $acct = fetchOne( 'SELECT User.userID, status FROM User'
		      . ' INNER JOIN UserCourse ON UserCourse.userID = User.userID'
		      . ' WHERE uident = ' . quote_smart($uident)
		      . $andClass );
    if( ! $acct && isset( $_REQUEST['assmtID'] ) )
      $acct = fetchOne( 'SELECT userID, status FROM User'
			. ' INNER JOIN Author ON author = userID'
			. ' WHERE uident = ' . quote_smart($uident)
			. " AND assmtID = " . (int)$_REQUEST['assmtID'] );

    if( ! $acct )
      $warning = isset( $_REQUEST['cid'] )
	? warning( Sprintf_( 'There is no user <q>%s</q> in the class <q>%s</q>',
			     $_REQUEST['ident'], className( (int)$_REQUEST['cid'] )))
	: warning( Sprintf_( 'There is no user <q>%s</q>', $_REQUEST['ident'] ));
    else {
      if( isset( $_REQUEST['cid']     ) ) $_SESSION['revert-cid']     = (int)$_REQUEST['cid'];
      if( isset( $_REQUEST['assmtID'] ) ) $_SESSION['revert-assmtID'] = (int)$_REQUEST['assmtID'];
      $revert = $_SESSION;
      $_SESSION = array( );
      $_SESSION['revert'] = $revert;
      $_SESSION['User-Agent'] = $_SERVER["HTTP_USER_AGENT"];
      $_SESSION['TZ'] = $revert['TZ'];
      $_SESSION['instID'] = $instID;
      $_SESSION['uident'] = $uident;
      $_SESSION['userID'] = $acct['userID'];
      // Prevent impersonating an administrator (or superuser)
      $_SESSION['status'] = 'active';
      loadClasses( true, $courseID ); // Restrict access to just this class.

      addPendingMessage( Sprintf_('You are now viewing Arop&auml; as <q>%s</q>', $_REQUEST['ident'] ));
      if( $courseID == -1 )
	redirect('home' );
      else
	redirect('selectClass', 'cid=' . classIDToCid( $courseID ) );
    }
  }
  $hidden = array();
  $json = "jsonIdent";
  $cancel = 'home';
  if( isset( $_REQUEST['cid'] )) {
    $action = 'loginAsStudent';
    $hidden['cid'] = (int)$_REQUEST['cid'];
    $json .= "&cid=$hidden[cid]";
    $cancel = "selectClass&cid=$hidden[cid]";
  
    if( isset( $_REQUEST['assmtID'] ) ) {
      $hidden['assmtID'] = (int)$_REQUEST['assmtID'];
      $json .= "&assmtID=$hidden[assmtID]";
      $cancel = "viewAsst&assmtID=$hidden[assmtID]&cid=$hidden[cid]";
    }
  } else
    $action = 'impersonateAnyUser';

  autoCompleteWidget($json);
  return HTML( HTML::h1( 'Impersonate other user'),
	       HTML::p( HTML::raw( _('Allows instructors to see what a student sees when using Arop&auml;.'))),
	       $warning, br(),
	       HTML::form(array('method'=>'post', 'action'=>"$_SERVER[PHP_SELF]?action=$action"),
			  HiddenInputs( $hidden ),
			  FormGroup('ident',
				    _('User identifier to impersonate'),
				    HTML::input(array('type'=>'text',
						      'class'=>'typeahead',
						      'autofocus'=>true,
						      'id'=>'ident',
						      'data-provide'=>'typeahead')),
				    _('User name')),
			  ButtonToolbar(submitButton(_('Impersonate other user')),
					BackButton())));
}

// AJAX interface
function jsonIdent( ) {
  static $limit = 20;
  list( $term, $assmtID ) = checkREQUEST( 'query', '?_assmtID' );
  global $gHaveAdmin;
  if( $gHaveAdmin && !isset( $_REQUEST['cid'] ) )
    $andClass = "";
  else
    $andClass = ' AND courseID = ' . cidToClassId( (int)$_REQUEST['cid'] );

  if( $assmtID == 0 )
    $unionAssmt = "";
  else
    $unionAssmt = ' UNION (SELECT DISTINCT uident FROM User INNER JOIN Author ON userID=author'
      . ' WHERE uident LIKE ' . quote_smart( "%$term%" ) . "AND assmtID=$assmtID)";

  echo json_encode( fetchAll( '(SELECT DISTINCT uident FROM User NATURAL JOIN UserCourse'
			      . ' WHERE uident LIKE ' . quote_smart( "%$term%" ) 
			      . $andClass . ')'
			      . $unionAssmt
			      . " LIMIT $limit",
			      'uident' ));
  exit;
}
