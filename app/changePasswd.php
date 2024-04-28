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

function changePassword() {
  $userID = $_SESSION['userID'];

  $old = '';
  $warn = '';
  if (isset($_SESSION['tmpLogin'])) {
    $title = _('You must set your password before using the system.');
    $submit =  _('Set password');
  } else {
    $title = Sprintf_('Change password for %s', $_SESSION['uident']);
    $submit = _('Change password');
    if( ! isset( $_SESSION['revert'] ) || $_SESSION['userID'] == $_SESSION['revert']['userID'] )
      $old = FormGroup('oldpassword',
		       _('Old password'),
		       HTML::input(array('type'=>'password',
					 'autofocus'=>true)));
    else
      $warn = warning( _('You are using Impersonate Other User to change the password for someone other than yourself.'));
  }

  extraHeader('$("#submit").prop("disabled", true);
    $("#password, #verify").keyup(function() {
        $("#submit").prop("disabled", this.value == "" || $("#password").val() != $("#verify").val());
    })', 'onload');

  return HTML(HTML::h1($title),
	      pendingMessages(),
	      $warn,
	      HTML::form( array('method'=>'post',
				'class'=>'form',
				'action'=>"$_SERVER[PHP_SELF]?action=savePassword" ),
			  $old,
			  FormGroup('password',
				    _('New password'),
				    HTML::input(array('type'=>'password',
						      'id'=>'password',
						      'autofocus'=>$old == ''))),
			  FormGroup('verify',
				    _('Verify new password'),
				    HTML::input(array('type'=>'password',
						      'id'=>'verify')),
				    _('Type the same password again')),
			  ButtonToolbar(submitButton($submit, null, 'submit'),
					isset($_SESSION['tmpLogin'] ) ? '' : BackButton())));
}

function savePassword() {
  list($password, $verify, $oldpassword) = checkREQUEST('password', 'verify', '?oldpassword');
  $userID = $_SESSION['userID'];

  $revert = isset($_SESSION['revert']) && $_SESSION['userID'] != $_SESSION['revert']['userID'];

  if(! isset($_SESSION['tmpLogin']) && ! $revert && ! isset($_REQUEST['oldpassword']))
    securityAlert('missing form data');

  if( $password != $verify ) {
    addWarningMessage(_('The new password was not typed the same each time.  Please try again'));
    redirect('changePassword');
  }

  ensureDBconnected('savePassword');
  
  $acct = fetchOne("select passwd from User where userID = $userID");
  if(! $acct)
    return warning(_('No user account available; unable to change password.'));

  if(! $revert && ! isset($_SESSION['tmpLogin']) && crypt( $oldpassword, $acct['passwd']) != $acct['passwd']) {
    addWarningMessage(_('You did not enter your old password correctly'));
    redirect('changePassword');
  }
      
  $passwd = empty( $password ) ? '' : crypt($password);
  checked_mysql_query('update User set passwd = ' . quote_smart($passwd)
		      . ', lastChanged=now()'
		      . " where userID = $userID" );
  addPendingMessage(_('Your password has been changed'));
  unset($_SESSION['tmpLogin']);
  redirect('home');
}


function setEmail( ) {
  $userID = $_SESSION['userID'];

  if (isset($_SESSION['revert'] ) && $userID != $_SESSION['revert']['userID'])
    return HTML(HTML::h1(_('You cannot change email when impersonating another user') ),
		message(_('You must revert to your own account before changing your email.')),
		BackButton());

  $form = HTML::form(array('method'=>'post',
			   'action'=>"$_SERVER[PHP_SELF]?action=setEmail"));

  $user = fetchOne("SELECT emailVerify, pendingEmail FROM User WHERE userID = $userID");

  $msg = '';
  if (! empty( $user['emailVerify'] ) && isset( $_REQUEST['verify'])) {
    if ($user['emailVerify'] == $_REQUEST['verify']) {
      checked_mysql_query(makeUpdateQuery('User',
					  array('email'       => $user['pendingEmail'],
						'emailVerify' => null,
						'pendingEmail'=>null))
			  . " WHERE userID = $userID");
      addPendingMessage(_('Your email address has been updated to '), $user['pendingEmail']);
      redirect('home');
    } else {
      $pendingEmail = $user['pendingEmail'];
      $msg = warning(_('The email verification code you entered is not the one emailed to you.  Please enter it again.'));
    }
  } else if (isset($_REQUEST['email'])) {
    $code = makeRandomCode();
    checked_mysql_query(makeUpdateQuery('User',
					array('pendingEmail' => $_REQUEST['email'],
					      'emailVerify'  => $code ))
			. " WHERE userID = $userID");

    mail($_REQUEST['email'],
	 _('Your Aropa email verification code'),
	 sprintf( _('Thank you for providing your email address.

Your verification code is: %s

To save your email address, return to Aropa and enter this code into the email verification field and press the Confirm button.'),
		  $code));
    $pendingEmail = $_REQUEST['email'];
    $msg = HTML(_('A verification code has been sent to the email address you entered.'),
		_(' Please enter the verification code to confirm your email address.'),
		br());
  }
  
  if (isset($pendingEmail))
    $form->pushContent($msg,
		       FormGroup('verify',
				 _('Verification code'),
				 HTML::input(array('type'=>'text'))));
  else
    $form->pushContent(FormGroup('email',
				 _('Enter your email address'),
				 HTML::input(array('type'=>'email'))));
  
  $form->pushContent(ButtonToolbar(submitButton(_('Confirm')),
				   CancelButton()));
  return HTML(HTML::h1(_('Change email for '), $_SESSION['uident']),
	      $form);
}

function makeRandomCode() {
  $code = '';
  $chars = "abcdefghijkmnopqrstuvwxyzABCDEFGHIJKMNOPQRSTUVWXYZ023456789"; 
  $len = strlen( $chars );
  for( $r = mt_rand( ); $r > 0; $r = (int)($r/$len) )
    $code .= $chars[ $r % $len ];
  return $code;
}
