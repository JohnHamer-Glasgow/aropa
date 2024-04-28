<?php
include_once 'core.php';
include_once 'download.php'; // For filename_safe

function essaysToFiles() {
  ini_set('memory_limit', '512M');
  $count = 0;
  foreach (
    fetchAll(
      "select essayID from Essay e inner join Assignment a on e.assmtID = a.assmtID"
      . " where a.category <> '' and e.extn <> 'inline-text' and e.essay is not null",
      'essayID') as $essayID) {
    set_time_limit(20);
    $row = fetchOne(
      'select e.assmtID, e.essayID, e.reqIndex, e.extn, e.description, e.essay, e.overflow, u.uident'
      . ' from Essay e inner join User u on e.author = u.userID'
      . " where essayID = $essayID");
    $count++;
    $raw = $row['essay'];
    if ($row['overflow']) {
      $overflow = checked_mysql_query("select data from Overflow where essayID = $essayID order by seq");
      while ($chunk = $overflow->fetch_row())
	$raw .= $chunk[0];
    }
    
    $p = strrpos($row['description'], '.');
    $ext = $p === false ? '' : substr($row['description'], $p);
    $essay = $row['compressed'] ? gzuncompress($raw) : $raw;
    mkdir("essays/$row[assmtID]", 0777, true);
    $author = filename_safe($row['uident']);
    $path = "essays/$row[assmtID]/$row[assmtID]-$author-$essayID-$row[reqIndex]$ext";
    file_put_contents($path, $essay);
    checked_mysql_query("update Essay set url = " . quote_smart($path) . ", essay = null, overflow = false where essayID = $essayID");
    checked_mysql_query("delete from Overflow where essayID = $essayID");
  }
  
  return HTML(
    HTML::h1(_('Essays to files')),
    HTML::p(Sprintf_('Moved %d essays to disk', $count)));
}

function fixupEssayUrl() {
  $rs = checked_mysql_query("select e.essayID, e.assmtID, e.reqIndex, e.description, u.uident from Essay e inner join User u on e.author = u.userID where e.url like '%file://%'");
  $count = 0;
  while ($row = $rs->fetch_assoc()) {
    $p = strrpos($row['description'], '.');
    $ext = $p === false ? '' : substr($row['description'], $p);
    $path = "essays/$row[assmtID]/$row[assmtID]-$row[uident]-$row[essayID]-$row[reqIndex]$ext";
    checked_mysql_query("update Essay set url = " . quote_smart($path) . " where essayID = $row[essayID]");
    $count++;
  }
  
  return HTML(
    HTML::h1(_('Fixup Essay URLs')),
    HTML::p(Sprintf_('Fixed %d URLs', $count)));
}