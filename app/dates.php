<?php
/*
 * Created on 23/05/2008 by Andrew Hay
 *
 *	Returns a date an hour in advance from the input date, used in Edit Assignment page.
 *
 */

$time = strtotime($_REQUEST['date']);
if( !empty($time) ) {
  $time = $time + 1*60*60;
  print( date('g:ia M d, Y',$time));
}
