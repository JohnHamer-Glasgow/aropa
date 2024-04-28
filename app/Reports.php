<?php
/*
    Copyright (C) 2020 John Hamer <J.Hamer@cs.auckland.ac.nz>

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

global $queries;
$queries = array();

$queries['Unclassified assignments']
= "select reviewEnd, longname, cname, aname, assmtID, c.courseID, cname
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join Institution i on i.instID = c.instID
where a.category=''
order by isnull(reviewEnd), reviewEnd asc";

$date = quote_smart(isset($_REQUEST['d']) ? $_REQUEST['d'] : '2017-06-30');
$queries["Assignment status, to $date"]
= "select count(*), category from Assignment where reviewEnd <= $date group by category";

$queries["Active courses with no subject"]
= "select i.longname, c.courseID, c.cname
from Course c
inner join Institution i on i.instID = c.instID
where c.subject = '' and c.cactive and i.isActive
order by i.longname, c.cname";

$limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 20;
$queries["Top $limit assignments by submission size (warning: SLOW!)"]
= "select i.longname, c.cname, a.aname, a.submissionEnd, u.uident as owner, sum(octet_length(e.essay)) + ifnull(sum(octet_length(o.data)), 0) as size
 from Essay e
 left join Overflow o on e.essayID = o.essayID
 inner join Assignment a on e.assmtID = a.assmtID
 inner join Course c on a.courseID = c.courseID
 left join User u on u.userID = c.cuserID
 inner join Institution i on c.instID = i.instID
 group by a.assmtID
 order by size desc
 limit $limit";

$queries["Assignments using non-student authors"]
= "select i.longname, c.courseID, c.cname, a.assmtID, a.aname, a.authorsAre
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join Institution i on i.instID = c.instID
where a.category = 'successful' and exists (select * from Author au where au.assmtID = a.assmtID)
order by i.longname, c.cname, a.aname";

$queries['Orphaned authors (not on the class list)']
= "select a.reviewEnd, cname, c.courseID, aname, a.assmtID, c.instID, count(*)
 from Allocation l
 inner join Assignment a on a.assmtID = l.assmtID
 inner join Course c on c.courseID = a.courseID
 left join UserCourse uc on uc.userID = l.author and uc.courseID = a.courseID
 where uc.userID is null and a.category <> 'test' and a.authorsAre <> 'group'
 group by a.assmtID
 order by a.reviewEnd desc";

$queries['Orphaned reviewers (not on the class list)']
= "select a.reviewEnd, cname, c.courseID, aname, a.assmtID, c.instID, count(*)
 from Allocation l
 inner join Assignment a on a.assmtID = l.assmtID
 inner join Course c on c.courseID = a.courseID
 left join UserCourse uc on uc.userID = l.reviewer and uc.courseID = a.courseID
 where uc.userID is null and a.category <> 'test' and a.reviewersAre <> 'group'
 group by a.assmtID
 order by a.reviewEnd desc";

$queries['Successful, ERM - Jan year start']
= "select year(reviewEnd) as year, longname, count(*)
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join Institution i on i.instID = c.instID
where a.category='successful' and isReviewsFor is null
group by i.instID, year
order by year desc, longname";

$queries['Successful, ERM - Sept year start']
= "select year(date_add(reviewEnd, interval -8 month)) as year, longname, count(*)
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join Institution i on i.instID = c.instID
where a.category='successful' and isReviewsFor is null
group by i.instID, year
order by year desc, longname";

$queries['Successful RM - Jan year start']
= "select year(reviewEnd) as year, longname, count(*)
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join Institution i on i.instID=c.instID
where a.category='successful' and isReviewsFor is not null
group by i.instID, year
order by year desc, longname";

$queries['Successful RM - Sept year start']
= "select year(date_add(reviewEnd, interval -8 month)) as year, longname, count(*)
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join Institution i on i.instID=c.instID
where a.category='successful' and isReviewsFor is not null
group by i.instID, year
order by year desc, longname";

$queries['Instructors of successful assignments']
= "select uident, longname, cname, a.courseID, aname, assmtID, reviewEnd
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join Institution i on i.instID = c.instID
inner join UserCourse uc on uc.courseID = c.courseID
inner join User u on u.userID = uc.userID
where (roles&8) <> 0 and category = 'successful'
order by reviewEnd desc";

$queries['Course owners of successful assignments']
= "select uident, longname, cname, a.courseID, aname, assmtID, reviewEnd
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join Institution i on i.instID = c.instID
inner join User u on u.userID = c.cuserID
where a.category = 'successful'
order by reviewEnd desc";

$queries['Instructor course count by Sept year start']
= "select u.uident, i.longname, year(date_add(reviewEnd, interval -8 month)) as year, count(distinct c.courseID) as n
from User u
inner join Institution i on u.instID = i.instID
inner join Assignment a
inner join Course c on a.courseID = c.courseID
inner join UserCourse uc on uc.courseID = c.courseID and uc.userID = u.userID and (uc.roles&8) <> 0
where a.category = 'successful'
group by u.userID, year
order by year desc, u.uident";

$queries['Instructor course count by Jan year start']
= "select u.uident, i.longname, year(reviewEnd) as year, count(distinct c.courseID) as n
from User u
inner join Institution i on u.instID = i.instID
inner join Assignment a
inner join Course c on a.courseID = c.courseID
inner join UserCourse uc on uc.courseID = c.courseID and uc.userID = u.userID and (uc.roles&8) <> 0
where a.category = 'successful'
group by u.userID, year
order by year desc, u.uident";

$queries['Instructors who have set more than one peer-review activity']
= "select u.uident, count(distinct a.assmtID) as n
from User u
inner join Assignment a
inner join Course c on a.courseID = c.courseID
inner join UserCourse uc on uc.courseID = c.courseID and uc.userID = u.userID and (uc.roles&8) <> 0
where a.category = 'successful'
group by u.uident
having n > 1";

$queries['Largest class size associated with a successful assignment in last two years']
= "select aname, cname, count(distinct author) as n
from Essay e
inner join Assignment a on a.assmtID = e.assmtID
inner join Course c on a.courseID = c.courseID
where a.category = 'successful' and date_add(reviewEnd, interval 2 year) >= now()
group by a.assmtID
order by n";

$queries['Class composition']
= "select longname as Institution,
          cname,
          c.courseID,
          aname,
          a.assmtID,
          a.reviewEnd, 
          case uc.roles when 1 then 'students' when 2 then 'markers' when 4 then 'guests' when 8 then 'instructors' else 'other' end as role,
          count(*) as n
from Assignment a
inner join UserCourse uc on a.courseID = uc.courseID
inner join Course c on uc.courseID = c.courseID
inner join Institution i on i.instID = c.instID
where a.category = 'successful'
group by a.assmtID, uc.roles
order by i.longname, cname, aname";

$queries['Reviewers by institution - Jan year start']
= "select year(reviewEnd) as year, longname, count(distinct reviewer)
from Assignment a
inner join Allocation l on a.assmtID = l.assmtID
inner join UserCourse uc on uc.courseID = a.courseID and l.reviewer = uc.userID
inner join Course c on a.courseID = c.courseID
inner join Institution i on c.instID = i.instID
where category = 'successful' and uc.roles = 1 and lastMarked is not null
group by i.instID, year
order by year desc, longname";

$queries['Reviewers by institution - Sept year start']
= "select year(date_add(reviewEnd, interval -8 month)) as year, longname, count(distinct reviewer)
from Assignment a
inner join Allocation l on a.assmtID = l.assmtID
inner join UserCourse uc on uc.courseID = a.courseID and l.reviewer = uc.userID
inner join Course c on a.courseID = c.courseID
inner join Institution i on c.instID = i.instID
where category = 'successful' and uc.roles = 1 and lastMarked is not null
group by i.instID, year
order by year desc, longname";

$queries['Read review by institution - Jan year start']
= "select year(reviewEnd) as year, longname, count(distinct author)
from Assignment a
inner join Allocation l on a.assmtID = l.assmtID
inner join UserCourse uc on uc.courseID = a.courseID and l.reviewer = uc.userID
inner join Course c on a.courseID = c.courseID
inner join Institution i on c.instID = i.instID
where category = 'successful' and uc.roles = 1 and lastSeen is not null
group by i.instID, year
order by year desc, longname";

$queries['Read review by institution - Sept year start']
= "select year(date_add(reviewEnd, interval -8 month)) as year, longname, count(distinct author)
from Assignment a
inner join Allocation l on a.assmtID = l.assmtID
inner join UserCourse uc on uc.courseID = a.courseID and l.reviewer = uc.userID
inner join Course c on a.courseID = c.courseID
inner join Institution i on c.instID = i.instID
where category = 'successful' and uc.roles = 1 and lastSeen is not null
group by i.instID, year
order by year desc, longname";

$queries['Count of unique students who have used the system to write at least one review']
= "select longname, year(date_add(a.reviewEnd, interval -8 month)) as year, count(distinct reviewer)
from Assignment a
inner join Allocation l on a.assmtID = l.assmtID
inner join UserCourse uc on uc.courseID = a.courseID and l.reviewer = uc.userID
inner join Course c on a.courseID = c.courseID
inner join Institution i on c.instID = i.instID
where category = 'successful' and uc.roles = 1 and lastMarked is not null
group by i.instID, year";

$queries['Count of unique students who have used the system to write at least one review in the last four calendar years']
= "select longname, count(distinct reviewer)
from Assignment a
inner join Allocation l on a.assmtID = l.assmtID
inner join UserCourse uc on uc.courseID = a.courseID and l.reviewer = uc.userID
inner join Course c on a.courseID = c.courseID
inner join Institution i on c.instID = i.instID
where category = 'successful' and uc.roles = 1 and lastMarked is not null and date_add(reviewEnd, interval 4 year) >= now()
group by i.instID";

$queries['Submissions expected, group, ERM']
= "select reviewEnd, a.assmtID, aname, a.courseID, cname, count(*)
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join GroupUser gu on a.assmtID = gu.assmtID
where category = 'successful' and a.authorsAre = 'group' and isReviewsFor is null
group by assmtID
order by reviewEnd desc";

$queries['Submissions expected, non-group, ERM']
= "select reviewEnd, a.assmtID, aname, a.courseID, cname, count(*)
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join UserCourse uc on uc.courseID = a.courseID
where category = 'successful' and a.authorsAre = 'all' and (roles&1) = 1 and isReviewsFor is null
group by assmtID
order by reviewEnd desc";

$queries['Number of submissions']
= "select reviewEnd, a.assmtID, aname, a.courseID, cname, count(distinct author)
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join Essay e on e.assmtID = a.assmtID
where category = 'successful' and isReviewsFor is null
group by assmtID
order by reviewEnd desc";

$queries['Review allocation type']
= "select reviewEnd, a.assmtID, aname, a.courseID, cname, allocationType
from Assignment a
inner join Course c on a.courseID = c.courseID
where category = 'successful' and isReviewsFor is null
order by reviewEnd desc";

$queries['Number of review allocations made']
= "select reviewEnd, aname, a.assmtID, a.courseID, cname, count(*)
from Assignment a
inner join Course co on a.courseID = co.courseID
inner join Allocation c on a.assmtID = c.assmtID
where a.category='successful'
group by a.assmtID
order by reviewEnd desc";

$queries['Number of reviews written, by assignment']
= "select reviewEnd, aname, a.assmtID, a.courseID, cname, count(*)
from Assignment a
inner join Course co on a.courseID = co.courseID
inner join Allocation c on a.assmtID = c.assmtID
where a.category = 'successful' and c.lastMarked is not null
group by a.assmtID
order by reviewEnd desc";

$queries['Number of reviews written by institution  - Sept year start']
= "select year(date_add(reviewEnd, interval -8 month)) as year, longname, count(*)
from Assignment a
inner join Course co on a.courseID = co.courseID
inner join Institution i on co.instID = i.instID
inner join Allocation c on a.assmtID = c.assmtID
where a.category = 'successful' and c.lastMarked is not null
group by i.instID, year
order by year desc, longname";

$queries['Number of reviews written by institution  - Jan year start']
= "select year(reviewEnd) as year, longname, count(*)
from Assignment a
inner join Course co on a.courseID = co.courseID
inner join Institution i on co.instID = i.instID
inner join Allocation c on a.assmtID = c.assmtID
where a.category = 'successful' and c.lastMarked is not null
group by i.instID, year
order by year desc, longname";

$queries['Number of submissions read but not reviewed']
= "select reviewEnd, aname, a.assmtID, a.courseID, cname, count(*)
from Assignment a
inner join Course co on a.courseID = co.courseID
inner join Allocation c on a.assmtID = c.assmtID
where a.category = 'successful' and lastMarked is null and lastViewed is not null
group by a.assmtID
order by reviewEnd desc";

$queries['Number of reviews read']
= "select reviewEnd, aname, a.assmtID, a.courseID, cname, count(*)
from Assignment a
inner join Course co on a.courseID = co.courseID
inner join Allocation c on a.assmtID = c.assmtID
where a.category = 'successful' and lastMarked is not null and lastSeen is not null
group by a.assmtID
order by reviewEnd desc";

$queries['Number of submission extensions']
= "select a.reviewEnd, aname, a.assmtID, a.courseID, cname, count(*)
from Assignment a
inner join Course co on a.courseID = co.courseID
inner join Extension c on a.assmtID = c.assmtID
where a.category = 'successful' and c.submissionEnd is not null
group by a.assmtID
order by reviewEnd desc";

$queries['Number of review extensions']
= "select a.reviewEnd, aname, a.assmtID, a.courseID, cname, count(*)
from Assignment a
inner join Course co on a.courseID = co.courseID
inner join Extension c on a.assmtID = c.assmtID
where a.category = 'successful' and c.reviewEnd is not null
group by a.assmtID
order by reviewEnd desc";

$queries['Number of markers']
= "select reviewEnd, aname, a.assmtID, a.courseID, cname, count(distinct reviewer)
from Assignment a
inner join Course co on a.courseID = co.courseID
inner join Allocation c on a.assmtID = c.assmtID
where a.category = 'successful' and tag = 'MARKER'
group by a.assmtID
order by reviewEnd desc";

$queries['Days for reviewing']
= "select reviewEnd, aname, assmtID, a.courseID, cname, datediff(reviewEnd, submissionEnd)
from Assignment a
inner join Course c on a.courseID = c.courseID
where category = 'successful'
order by reviewEnd desc";

$queries['Mark, comment and upload items in rubric']
= "select reviewEnd, aname, assmtID, a.courseID, cname, nReviewFiles, length(commentItems) - length(replace(commentItems, '=', '')) + 1 as  nCommentItems, length(markItems) - length(replace(markItems, '&', '')) + 1 as nMarkItems
from Assignment a
inner join Course c on a.courseID = c.courseID
where category = 'successful'
order by reviewEnd desc";

$queries['Assignment configuration']
= "select
 a.reviewEnd,
 i.longname,
 a.aname,
 a.assmtID,
 a.courseID,
 c.cname,
 c.subject,
 u.uident,
 a.nPerReviewer,
 if(a.authorsAre = 'group',
    (select count(*) / count(distinct l.reviewer) from Allocation l where l.assmtID = a.assmtID),
    (select count(*) / count(distinct l.reviewer) from Allocation l left join UserCourse uc on l.reviewer = uc.userID where uc.courseID = a.courseID and l.assmtID = a.assmtID and (uc.roles & 1) <> 0)) as calculatedReviewerLoad,
 ((length(a.submissionRequirements) - length(replace(a.submissionRequirements, 'file,', ''))) div length('file,')) as submissionFiles,
 substring(
  replace(
    substring(
      a.submissionRequirements,
      locate('extn=', a.submissionRequirements),
      locate('&', substring(a.submissionRequirements, locate('extn=', a.submissionRequirements))) - 1),
    '%2C',
    ','),
  6) as submissionType,
 length(a.markItems) - length(replace(a.markItems, '=', '')) as markItems,
 length(a.commentItems) - length(replace(a.commentItems, '=', '')) as commentItems,
 a.nReviewFiles,
 a.anonymousReview,
 if(a.allocationType = 'response',
    'response',
    case authorsAre when 'all' then 'students' when 'group' then 'students' when 'other' then 'non-students' else authorsAre end) as submissionsAre,
 case reviewersAre when 'all' then 'everyone' when 'other' then 'markers' else 'submit' end as reviewersAre,
 if(a.allocationType like '%tags', 'tagged', if(authorsAre = 'group', 'group', 'individual')) as submissionsDoneBy,
 case a.allocationType when 'same tags' then 'within tags' when 'other tags' then 'between tags' else if(reviewersAre = 'group', 'group', 'individual') end as reviewsDoneBy,
 if(a.allocationType = 'manual', 'manual', 'random') as allocationMethod,
 if(ifnull(a.visibleReviewers, '') = '', 'no', 'yes') as restrictedReviewerViewing,
 exists (select * from Allocation l inner join UserCourse luc on luc.userID = l.reviewer where l.assmtID = a.assmtID and luc.roles <> 1) as tutorMarking,
 datediff(a.reviewEnd, a.submissionEnd) as daysForReviewing,
 exists (select * from Extension e where e.assmtID = a.assmtiD) as extensionsGiven,
 (select count(*) from UserCourse uc where uc.courseID = c.courseID and (roles & 1) <> 0) as classSize,
 (select count(distinct author) from Essay e where e.assmtID = a.assmtID) as submissionsMade,
 (select count(*) from Allocation l where l.assmtID = a.assmtID and lastMarked is not null) as reviewsWritten,
 a.markGrades,
 a.showMarksInFeedback,
 a.restrictFeedback,
 a.selfReview
from Assignment a
inner join Course c on a.courseID = c.courseID
inner join User u on c.cuserID = u.userID
inner join Institution i on i.instID = c.instID
where category = 'successful'
order by reviewEnd desc";

$queries['Average length of comment per review']
= "select reviewEnd, aname, a.assmtID, a.courseID, cname, avg(length(comments))
from Assignment a
inner join Course co on a.courseID = co.courseID
inner join Allocation i on i.assmtID = a.assmtID
inner join Comment c on i.allocID = c.allocID
where category = 'successful' and lastMarked is not null
group by a.assmtID
order by reviewEnd desc";

function reports() {
  global $queries;

  if (isset($_REQUEST['q']) && isset($queries[$_REQUEST['q']])) {
    set_time_limit(0);
    ini_set('memory_limit', '256M');
    ini_set('mysql.connect_timeout', '0');
    ini_set('max_execution_time', '0');
    $timeout = 3153600; // = 1 year in seconds
    ini_set('default_socket_timeout', $timeout);
    ini_set('mysqlnd.net_read_timeout', $timeout);
    $db = ensureDBconnected('reports');
    $db->query("set global connect_timeout=$timeout");
    $db->query("set session net_read_timeout=$timeout");
    $db->query("set session net_write_timeout=$timeout");

    $sql = $queries[$_REQUEST['q']];
    $rs = checked_mysql_query($sql);

    if ($_REQUEST['f'] == 'csv') {
      $haveHeader = false;
      ob_start();
      $stdout = fopen('php://output', 'w');
      while ($row = $rs->fetch_assoc()) {
	adjust($row);
	if (!$haveHeader) {
	  $str .= fputcsv($stdout, array_keys($row));
	  $haveHeader = true;
	}

	fputcsv($stdout, $row);
      }

      return HTML::pre(ob_get_clean());
    }

    $table = HTML::table(array('class'=>'table'));
    while ($row = $rs->fetch_assoc()) {
      adjust($row);
      if ($table->isEmpty()) {
	$tr = HTML::tr();
	foreach ($row as $col => $val)
	  $tr->pushContent(HTML::th($col));
	
	$table->pushContent($tr);
      }

      $tr = HTML::tr();
      $links = array('aname' => "viewAsst&assmtID=$row[assmtID]",
		     'cname' => "editClass");
      if (isset($row['courseID']) && isset($row['cname'])) {
	$cid = classIDToCid($row['courseID']);
	if (empty($cid)) {
	  $_SESSION['classes'][] = array('courseID' => $row['courseID'],
					 'roles'    => 8,
					 'cname'    => $row['cname'],
					 'subject'  => $row['subject'],
					 'reviewer' => array(),
					 'author'   => array(),
					 'cactive'  => true);
	  $cid = classIDToCid($row['courseID']);
	}
      }

      foreach ($row as $col => $val)
	$tr->pushContent(HTML::td(isset($links[$col]) ? callback_url($val, "$links[$col]&cid=$cid") : $val));

      $table->pushContent($tr);
    }
    
    return HTML(HTML::h2(array('title'=>$sql), $_REQUEST['q']),
		BackButton(),
		$table,
		BackButton());
  } else {
    $table = table();
    foreach ($queries as $title => $sql)
      $table->pushContent(HTML::tr(HTML::th($title),
				   HTML::td(Button('HTML', "reports&f=html&q=$title")),
				   HTML::td(Button('CSV', "reports&f=csv&q=$title"))));
    return HTML(HTML::h2(_('Reports')),
		BackButton(),
		$table);
  }
}

function adjust(&$row) {
  if (isset($row['markGrades'])) {
    $markGrades = stringToItems($row['markGrades']);
    $row['markGradesChanged'] = (isset($row['markGrades']) && !areDefaultMarks($markGrades)) ? 'yes' : 'no';
    $ms = array();
    foreach ($markGrades as $m)
      $ms[] = join(',', $m);
    $row['markGrades'] = join('&', $ms);
    //      unset($row['markGrades']);
  }
}

function areDefaultMarks($markGrades) {
  foreach ($markGrades as $marks) {
    foreach ($marks as $i => $n)
      if ($n != '' && $i != $n)
	return false;
  }
  
  return true;
}