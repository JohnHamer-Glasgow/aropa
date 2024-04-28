<?php
/*
  Copyright (C) 2020 John Hamer <J.Hamer@acm.org>

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

require 'Util.php';

function bulkUpload($assmtID, $classId) {
  $maxFileSize = memToBytes(ini_get('upload_max_filesize'));
  if (empty($maxFileSize))
    $maxFileSize = 1e6;

  if (!isset($_FILES['bulk']['error']))
    return;
  
  $uploadFile = $_FILES['bulk']['tmp_name'];
  if (!$uploadFile)
    return;
  $info = pathinfo($_FILES['bulk']['name']);
    
  switch ($_FILES['bulk']['error']) {
  case UPLOAD_ERR_OK:
    if (!is_uploaded_file($uploadFile)) {
      securityAlert('possible upload attack');
      return;
    }
    break;
    
  case UPLOAD_ERR_INI_SIZE:
  case UPLOAD_ERR_FORM_SIZE:
    addWarningMessage(Sprintf_('The file <q>%s</q> could not be uploaded because it exceeds the maximum permitted size (%dMb).',
                               $info['basename'],
                               $maxFileSize/1024/1024));
    return;
    
  case UPLOAD_ERR_PARTIAL:
    addWarningMessage(Sprintf_('The file <q>%s</q> was only partially uploaded (perhaps you cancelled it after starting the upload?).',
                               $info['basename']));
    return;
    
  case UPLOAD_ERR_NO_FILE:
    addWarningMessage(_('The file <q>%s</q> was not uploaded', $info['basename']));
    return;
    
  case UPLOAD_ERR_EXTENSION:
  case UPLOAD_ERR_NO_TMP_DIR:
  case UPLOAD_ERR_CANT_WRITE:
    addWarningMessage(_('The file <q>%s</q> could not be uploaded (error code #%d).',
                        $info['basename'],
                        $error) );
    return;
  }

  $db = ensureDBconnected();
  
  $classList = array();
  foreach (fetchAll("select u.uident, u.userID"
                    . " from UserCourse uc inner join User u on uc.userID = u.userID"
                    . " where uc.courseID = $classId and (roles&1) = 1") as $row)
    $classList[$row['uident']] = $row['userID'];
  $za = new ZipArchive();
  $za->open($uploadFile);
  
  $MAX_CHUNK = 800 * 1024; //- Allow (ample) room for quote expansion
  
  $uploads = array();
  $perStudent = array();
  for ($i = 0; $i < $za->numFiles; $i++) {
    $entry = $za->getNameIndex($i);
    if (substr($entry, -1, 1) == '/')
      continue;
    $matches = array();
    $info = pathinfo($entry);
    foreach ($classList as $uident => $userId)
      if (!!preg_match('/\\b' . preg_quote($uident, '/') . '\\b/i', $entry)) {
        list ($isCompressed, $essay) = maybeCompress($za->getFromIndex($i));
        $perStudent[$userId]++;
        $uploads[] = array('assmtID'     => $assmtID,
                           'reqIndex'    => $perStudent[$userId],
                           'author'      => $userId,
                           'essay'       => $essay,
                           'compressed'  => $isCompressed,
                           'description' => $info['basename'],
                           'extn'        => $info['extension']);
        $matches[] = $uident;
      }
      
    if (empty($matches))
      addPendingMessage(Sprintf_('Unable to find a match for %s; this entry will be ignored', $entry));
    else if (count($matches) > 1)
      addPendingMessage(Sprintf_('%s matches several students: %s', $entry, join(", ", $matches)));
  }

  $authors = join(',', array_keys($perStudent));
  if (!empty($authors)) {
    checked_mysql_query("delete from Overflow using Overflow left join Essay on Overflow.essayID = Essay.essayID where Essay.author in ($authors) and Essay.assmtID = $assmtID");
    checked_mysql_query("delete from Essay where author in ($authors) and assmtID = $assmtID" );
  }
  
  foreach ($uploads as $u) {
    if (strlen($u['essay']) > $MAX_CHUNK) {
      $overflow = substr($u['essay'], $MAX_CHUNK);
      $u['essay'] = substr($u['essay'], 0, $MAX_CHUNK);
      $u['overflow'] = true;
    } else
      $overflow = '';
    
    checked_mysql_query(makeInsertQuery('Essay', $u, array('whenUploaded'=> 'now()')));
    $essayID = $db->insert_id;
    for ($block = 0; strlen($overflow) > $block; $block += $MAX_CHUNK)
      checked_mysql_query("insert into Overflow (essayID, data) values ($essayID, " . quote_smart(substr($overflow, $block, $MAX_CHUNK)) . ")");
  }
}
