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

/*
  Aropa commands that do not require a user SESSION or database
  connection go in here.
 */

if( isset( $_REQUEST['t'] ) )
  switch( $_REQUEST['t'] ) {
  case 'png':
    $dir = 'resources/img';
    $type = 'image/png';
    break;
  case 'jpg':
    $dir = 'resources/img';
    $type = 'image/jpeg';
    break;
  case 'tiff':
    $dir = 'resources/img';
    $type = 'image/tiff';
    break;
  case 'gif':
    $dir = 'resources/img';
    $type = 'image/gif';
    break;
  case 'style':
    $dir  = 'resources/css';
    $type = 'text/css';
    break;
  case 'script':
    $dir  = 'styles';
    $type = 'application/javascript';
    break;
}

if( ! isset( $type ) )
  exit;

if( ! isset( $_REQUEST['f'] ) || strpos( $_REQUEST['f'], '/' ) !== false )
  exit;

$file = dirname(__FILE__) . "/" . $dir . "/" . $_REQUEST['f'];
if( ! is_readable( $file ) )
  exit;

header("Expires: " . gmdate("D, d M Y H:i:s", time()+315360000) . " GMT");
header('Cache-control: public, max-age=315360000');
header('Accept-Ranges: bytes');
header('Content-Length: ' . filesize($file) );
header('Content-Type: ' . $type);
readfile( $file );
exit;
