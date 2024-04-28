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

require_once 'Allocations.php';
require_once 'Groups.php';

class GradeCalc {
  var $assmtID;
  var $allocs;
  var $grades;
  var $weights;
  var $numMarked;
  var $numberOfReviews;
  var $markItems;
  var $markGrades;
  var $anomolies;
  var $avgMethodByItem;
  
  function __construct( $assmtID, &$allocs, $markItems, $markGrades ) {
    $this->assmtID         = $assmtID;
    $this->allocs          = $allocs;
    if( count( $allocs->byReviewer ) > 0 )
      $this->numberOfReviews = $allocs->allocationCount( ) / count( $allocs->byReviewer );
    $this->markItems       = $markItems;
    $this->markGrades      = $markGrades;

    $this->grades    = array( );
    $this->weights   = array( );
    $this->anomolies = array( );

    $this->avgMethodByItem = array( );
    /*
    foreach( $this->markItems as $item => $gMax )
      $this->avgMethodByItem[ $item ] = 'mean';
    */

    $this->numMarked = array( );
    foreach( $allocs->byReviewer as $r => $rs ) {
      $n = 0;
      foreach( $rs as $a )
        if( ! $this->isIgnored( $a ) )
          $n++;
      $this->numMarked[ $r ] = $n;
    }
  }


  function avgMethod( $item ) {
    $qual = isset( $this->avgMethodByItem[ $item ] ) ? $this->avgMethodByItem[ $item ] : 'undecided';
    $g = $_SESSION['grades-' . $this->assmtID ];
    if( $qual == 'undecided' ) {
      if( isset( $g['avgMethod'] ) ) {
        //- Let the user decide
        if( is_array( $g['avgMethod'] ) )
          //- case-by-case decision
          $qual = $g['avgMethod'][ $item ];
        else
          //- blanket policy
          $qual = $g['avgMethod'];
      } else
        $qual = 'mean';
    }
    return $qual;
  }


  //- Items that do not contribute to the weight calculations
  function isExcluded( $item ) {
    $g = 'grades-' . $this->assmtID;
    return isset( $_SESSION[$g]['exclude'] ) && isset( $_SESSION[$g]['exclude'][ $item ] );
  }

   //- Reviewers whose marks are ignored */
  function isBlacklisted( $reviewer ) {
    $g = 'grades-' . $this->assmtID;
    return isset( $_SESSION[$g]['blacklist'] ) && isset( $_SESSION[$g]['blacklist'][ $reviewer ] );
  }
  
  //- Allocations to ignore
  function isIgnored( &$alloc ) {
    return $this->isBlacklisted( $alloc['reviewer'] )
      || empty( $alloc['marks'] )
      || $this->ignoreAlloc( $alloc );
  }

  function ignoreAlloc( $alloc ) {
    $g = 'grades-' . $this->assmtID;
    return isset( $_SESSION[$g]['ignore'] ) && in_array( $alloc['allocID'], $_SESSION[$g]['ignore'] );
  }

  //- we have a grade for each item of each essay
  function updateGrades( $trackAnomolies = false ) {
    foreach( $this->allocs->byEssay as $e => $rs ) {
      $weightedGrade = array( ); //- by item
      foreach( $rs as $r => $a )
        if( ! $this->isIgnored( $a ) ) {
	  foreach( $this->markItems as $item => $nValues ) {
            if( $this->isExcluded( $item ) )
              continue;
	    
	    if( empty( $a['marks'][ $item ] ) )
	      continue;

	    $g = (int)$a['marks'][ $item ];

            if( $g < 0 || $g > $nValues ) {
	      if( $trackAnomolies )
		$this->anomolies[] = $a;
	      continue;
	    }
	    
	    if( isset( $this->markGrades[ $item ] ) && isset( $this->markGrades[ $item ][ $g-1 ] ) )
	      $mg = $this->markGrades[ $item ][ $g-1 ];
	    else
	      $mg = $g;
	    
	    if( ! isset( $this->weights[ $r ] ) )
	      $this->weights[ $r ] = 1.0;
	    
	    $weightedGrade[ $item ][ $mg ] += $this->weights[ $r ];
	  }
        }
      foreach( $weightedGrade as $item => $wg )
	$this->grades[ $e ][ $item ] = waverage( $wg, $this->avgMethod( $item ) );
    }
  }


  function reviewerWeight( $rs ) {
    $sumSq = 0.0;
    foreach( $rs as $e => $a )
      if( ! $this->isIgnored( $a ) )
	foreach( $this->markItems as $item => $nValues ) {
          if( $this->isExcluded( $item ) || $nValues == 0 )
	    continue;
	  $cmark = $this->grades[ $e ][ $item ] + 0.0;
	  $rmark = $a['marks'][ $item ] + 0.0;
	  $sumSq += sqr( ($cmark - $rmark)/$nValues );
	}
    return empty( $rs ) ? 0.0 : $sumSq / count($rs);
  }


  function updateWeights( ) {
    $sumWeights = 0.0;
    foreach( $this->allocs->byReviewer as $r => $rs )
      if( ! $this->isBlacklisted( $r ) ) {
        $w = $this->reviewerWeight( $this->allocs->byReviewer[ $r ] );
        $this->weights[ $r ] = $w;
        $sumWeights += $w;
      }

    //- Reviewers whose raw weight comes in under this average are doing
    //- well, those above not so good.
    $avg = count( $this->allocs->byReviewer ) == 0
      ? 0
      : $sumWeights / count( $this->allocs->byReviewer );

    foreach( $this->allocs->byReviewer as $r => $rs )
      if( ! $this->isBlacklisted( $r ) ) {
        $w = $this->weights[ $r ];
        if( $w == 0.0 )
          $dampened = $this->numMarked[ $r ] == 0 ? 0.0 : 101.0;
        else
          $dampened = sqrt( $avg / $w );
        
        //- Heuristic dampening function.  Experimental.
        if( $dampened > 2.0 )
          $dampened = 2.0 + log10($dampened - 1.0);

        //- Scale down the weights of reviewers who did not complete their
        //- allocated work.
        if( ! empty( $this->numberOfReviews ) && $this->numMarked[ $r ] < $this->numberOfReviews )
          $dampened *= $this->numMarked[ $r ] / $this->numberOfReviews;
        
        $this->weights[ $r ] = $dampened;
      }
  }


  //- Compute variance on the raw mark items, not the markGrades.  This
  //- is what the students see when entering grades, not the marks
  //- assigned by the instructor afterwards.
  function calcVariance( $e ) {
    $diff = 0;
    $marks = array( );
    foreach( $this->allocs->byEssay[ $e ] as $a )
      if (!$this->isIgnored($a))
	foreach( $a['marks'] as $item => $g )
	  $marks[ $item ][] = $g;

    //- mean absolute deviation; sum(xi - mx)/n, where xi is the data, mx is the median, n is the number of elements
    //- The interpretation is: the average amount each mark varies from the median.
    //- See http://en.wikipedia.org/wiki/Absolute_deviation; taking mx as the median gives the lowest possible value.
    foreach( $marks as $item => $gs ) {
      sort( $gs );
      $len = count( $gs );
      if( $len == 0 )
	break;

      if( ($len&1) == 0 )
	$median = ( $gs[ ($len>>1)-1 ] + $gs[ $len>>1 ] ) / 2;
      else
	$median = $gs[ $len>>1 ];
      
      $mad = 0;
      foreach( $gs as $g )
	$mad += abs( $g - $median );
      $diff += $mad / $len;
    }
    return $diff;
  }


  function gradesCSV( $authorHeader, $ndp = 2 ) {
    $numReviews = $this->reviewsPerEssay( );

    $str = $authorHeader . ',';
    foreach( $this->markItems as $item => $nValues )
      $str .= $item . ',';
    $str .= _("Total,Discrepancy,Reviews\n");

    ksort( $this->grades );
    foreach( $this->grades as $e => $gs ) {
      $total = 0;
      $str .= $e . ',';
      foreach( $this->markItems as $item => $nValues )
        if( isset( $gs[ $item ] ) ) {
          $str .= FmtGrade($gs[ $item ], $ndp) . ',';
          $total += $gs[ $item ];
        } else
          $str .= 'n/a,';
      $str .= FmtGrade($total, $ndp)
        . ',' . $this->calcVariance( $e )
        . ',' . $numReviews[ $e ]
        . "\n";
    }
    return $str;
  }

  function weightsCSV( $ndp = 2 ) {
    ksort( $this->weights );
    $str = _("Reviewer,Weight,Reviews,Comment: length,word count,unique words\n");
    foreach( $this->weights as $r => $w ) {
      $comments = $this->commentStats( $r );
      $str .= $r
        . ','
        . FmtGrade( $w, $ndp )
        . ',' . $this->numMarked[ $r ]
        . ',' . $comments['chars']
        . ',' . $comments['words']
        . ',' . $comments['unique']
        . "\n";
    }
    return $str;
  }


  function dumpToFile( $assmtName, $filename, $ndp = 2 ) {
    while( ob_end_clean( ) )
      //- Discard any earlier HTML or other headers
      ;
    if( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE' ) !== false ) {
      //- see last entry in http://php3.de/manual/en/function.session-cache-limiter.php
      session_cache_limiter('public');
      header("Cache-Control: no-store, no-cache, must-revalidate");
      // see http://www.alagad.com/blog/post.cfm/error-internet-explorer-cannot-download-filename-from-webserver
      header("Pragma: public");
      header("Cache-Control: max-age=0");
    }
    header( 'Content-Type: text/csv' );
    header( 'Content-Disposition: attachment; filename=' . rawurlencode($filename) . ';' );
    header( 'Content-Transfer-Encoding: binary' );
    //    header( 'Connection: close');
    session_write_close( );

    echo "# Final grades for $assmtName\n";

    $numReviews = $this->reviewsPerEssay( );

    $assmt = fetchOne("select authorsAre, markLabels from Assignment where assmtID = "
			   . $this->allocs->assmtID);
    $authorsAre = $assmt['authorsAre'];

    parse_str($assmt['markLabels'] ?? '', $markLabels);

    $N = count( $this->markItems );
    if ($authorsAre == 'review')
      echo _('Reviewer,Author,');
    else if ($authorsAre == 'group')
      echo _('Author,Group,');
    else
      echo _('Author,');

    foreach( array_keys( $this->markItems ) as $idx => $item )
      echo isset($markLabels[$item]) ? "$markLabels[$item]," : "$item,";

    echo _("Total,Discrepancy,Reviews received,Weight,Reviews written,Comment word count,Unique words\n");

    if( ! isset( $this->groups ) )
      $this->groups = new Groups( $this->allocs->assmtID );

    $reviewersDone = array( );
    $lines = array();
    foreach( $this->grades as $e => $gs )
      foreach( $this->groups->members( $e ) as $who ) {
	$line = '';
        //- Each author in a group is given the same mark.  Note that
        //- if there are no groups then $this->groups->members( $e )
        //- returns array( $e )
        $total = 0;
	if ($authorsAre == 'review')
	  $line .= str_replace('/', ',', $this->allocs->nameOfAuthor( $who ));
	else if ($authorsAre == 'group')
	  $line .= userIdentity($who, $assmtID, 'author') . ',' . $this->allocs->nameOfAuthor($who);
	else
	  $line .= $this->allocs->nameOfAuthor( $who );
	$line .= ',';
        foreach( array_keys( $this->markItems ) as $idx => $item )
          if( isset( $gs[ $item ] ) ) {
            $line .= FmtGrade($gs[ $item ], $ndp) . ',';
            $total += $gs[ $item ];
          } else
            $line .= ',';
        $line .= FmtGrade($total, $ndp)
          . ',' . $this->calcVariance( $e )
          . ',' . $numReviews[ $e ]
          ;

        //- Reviewing performance
        if( isset( $this->weights[ $who ] ) ) {
	  $stats = $this->commentStats( $who );
          $line .= ',' . FmtGrade( $this->weights[ $who ], $ndp )
            .  ',' . $this->numMarked[ $who ]
            .  ',' . $stats['words']
            .  ',' . $stats['unique']
            ;
          $reviewersDone[ $who ] = 1;
        } else
          $line .= ',,,';
	
	$lines[] = "$line\n";
      }

    sort($lines);
    foreach ($lines as $line)
      echo $line;

    $commas = ",,";
    foreach( array_keys( $this->markItems ) as $idx => $item )
      $commas .= ',';

    ksort( $this->weights );
    //echo "Reviewer,Weight,Reviews,Comment word count,Unique words\n";
    foreach( $this->weights as $r => $w )
      if( ! isset( $reviewersDone[ $r ] ) ) {
        $stats = $this->commentStats( $r );
        echo $this->allocs->nameOfReviewer( $r )
          . ',' . $commas
          . ',' . FmtGrade( $w, $ndp )
          . ',' . $this->numMarked[ $r ]
	  .  ',' . $stats['words']
	  .  ',' . $stats['unique']
          . "\n";
      }
  }


  function commentStats( $r, $stat = 'all' ) {
    ensureDBconnected( 'commentStats' );

    $allocIDs = array( );
    foreach( $this->allocs->byReviewer[ $r ] as $alloc )
      $allocIDs[] = $alloc['allocID'];

    $words = 0;
    $chars = 0;
    $unique = array();
    if( ! empty( $allocIDs ) ) {
      if( ! isset( $this->groups ) )
        $this->groups = new Groups( $this->allocs->assmtID );

      $rs = checked_mysql_query( 'SELECT comments FROM Comment '
				 . 'WHERE allocID IN (' . join(',', $allocIDs) . ')'
				 . ' AND madeBy IN (' . join(',', $this->groups->members( $r ) ) . ')' );
      while( $row = $rs->fetch_assoc() ) {
	$t = strip_tags($row['comments']);
	$ws = str_word_count($t, 1);
	$chars += strlen($t);
	$words += count($ws);
	foreach ($ws as $w)
	  $unique[strtolower($w)] = true;
      }
    }

    switch( $stat ) {
    case 'chars': return $chars;
    case 'words': return $words;
    case 'unique': return $unique;
    default: return array('chars'=>$chars, 'words'=>$words, 'unique'=>count($unique));
    }
  }

  /*
  function showAnomolies( ) {
    $html = HTML( );
    if( count( $this->anomolies ) > 0 ) {
      $html->pushContent( HTML::h2( 'WARNING: Recorded grades contain anomolies!' ) );
      
      $p = HTML::p( 'The rubric grade items are: ' );
      foreach( $this->markItems as $item => $vs )
        $p->pushContent( $item, '/', max($vs), '; ' );
      $html->pushContent( $p );
      
      $ul = HTML::ul( );
      foreach( $this->anomolies as $a ) {
        $li = HTML::li( 'Reviewer ', HTML::q($a['reviewer']),
                        ', author ', HTML::q($a['author']), ': ' );
        foreach( $a['marks'] as $item => $mark )
          if( ! isset( $this->markItems[ $item ] ) || ! in_array( $mark, $this->markItems[ $item ] ) )
            $li->pushContent( HTML::b($item, '=', $mark), '; ' );
          else
            $li->pushContent( $item, '=', $mark, '; ' );
        $ul->pushContent( $li );
      }
      $html->pushContent( $ul );
    }
    return $html;
  }
  */

  function reviewsPerEssay() {
    $numReviews = array();
    foreach ($this->allocs->byEssay as $e => $rs) {
      $numReviews[$e] = 0;
      foreach ($rs as $a)
        if (!$this->isIgnored($a))
          $numReviews[$e]++;
    }

    return $numReviews;
  }

  /* A compact form the grade data, to send to the browser */
  function JSON_grades( $ndp = 2 ) {
    $numReviews = $this->reviewsPerEssay( );
 
    $jdata = array( );
    foreach( $this->grades as $e => $gs ) {
      $data = array( $this->allocs->nameOfAuthor( $e ), 0, $numReviews[ $e ], FmtGrade($this->calcVariance( $e ), 2));
      $total = 0;
      foreach( $this->markItems as $item => $nValues )
	if( isset( $gs[ $item ] ) ) {
	  $value = $gs[ $item ];
	  $data[] = FmtGrade( $value, $ndp );
	  $total += $value;
	} else
	  $data[] = '';
      $data[1] = FmtGrade( $total, $ndp );
      $jdata[] = $data;
    }

    return $jdata;
  }

  function JSON_reviewerWeights( $ndp = 2 ) {
    $jdata = array( );
    foreach( $this->weights as $r => $w )
      $jdata[ $this->allocs->nameOfReviewer( $r ) ] = FmtGrade( $w, $ndp );
    return $jdata;
  }

  function JSON_reviewers( ) {
    $rs = checked_mysql_query( 'select c.madeBy, c.comments'
			       . ' from Comment c inner join Allocation a on c.allocID = a.allocID' 
			       . " where a.assmtID = $this->assmtID");
    $words = array();
    $unique = array();
    while( $row = $rs->fetch_assoc() ) {
      $madeBy = $row['madeBy'];
      $ws = str_word_count(strip_tags($row['comments']), 1);
      if (!isset($words[$madeBy])) {
	$words[$madeBy] = 0;
	$unique[$madeBy] = array();
      }

      $words[$madeBy] += count($ws);
      foreach ($ws as $w)
	$unique[$madeBy][strtolower($w)] = true;
    }

    $jdata = array();
    foreach( $this->weights as $r => $w ) {
      $stats = isset($words[$r]) ? $words[$r] . " words/" . count($unique[$r]) . " unique" : '';
      $jdata[] = array( $this->allocs->nameOfReviewer( $r ),
			$this->numMarked[ $r ],
			$stats,
			$r,
			FmtGrade( $w, $ndp ) );
    }

    return $jdata;
  }


  /* A compact form the allocation data, to send to the browser */
  function JSON_allocs( ) {
    $rs = checked_mysql_query( 'select c.allocID, comments'
			       . ' from Comment c inner join Allocation a on c.allocID = a.allocID'
			       . ' where a.assmtID = ' . quote_smart( $this->assmtID ));
    $words = array();
    $unique = array();
    while( $row = $rs->fetch_assoc() ) {
      $allocId = $row['allocID'];
      $ws = str_word_count(strip_tags($row['comments']), 1);
      if (!isset($words[$allocId])) {
	$words[$allocId] = 0;
	$unique[$allocId] = array();
      }

      $words[$allocId] += count($ws);
      foreach ($ws as $w)
	$unique[$allocId][strtolower($w)] = true;
    }

    $allocs = array( );
    foreach( $this->allocs->allocations as $a ) {
      $allocId = $a['allocID'];
      $stats = isset($words[$allocId]) ? $words[$allocId] . " words/" . count($unique[$allocId]) . " unique" : '';
      $data = array( $allocId,
		     $this->allocs->nameOfAuthor( $a['author'] ),
		     $this->allocs->nameOfReviewer( $a['reviewer'] ),
		     $stats,
		     $this->ignoreAlloc( $a ) ? 1 : 0 );
      $total = 0;
      foreach( $this->markItems as $item => $nValues )
	if( isset( $a['marks'][ $item ] ) ) {
	  $mark = $a['marks'][ $item ];
	  if( isset( $this->markGrades[ $item ][ $mark-1 ] ) )
	    $value = $this->markGrades[ $item ][ $mark-1 ];
	  else
	    $value = $mark;
	  $data[] = is_numeric( $value ) ? $value+0 : $value;
	  $total += $value;
	} else
	  $data[] = '';
      $data[] = $total;
      $allocs[] = $data;
    }
    return $allocs;
  }

}

/*
  Compute the weighed average of $wdata.  $wdata should be an array
  mapping values to weights, ordered by key.
 */
function waverage( $wdata, $method = 'mean' ) {
  switch( $method ) {
  default:
  case 'mean':
    $sum = 0.0;
    $weight = 0;
    foreach( $wdata as $g => $w ) {
      $sum += $g * $w;
      $weight += $w;
    }
    return $weight == 0 ? '?' : $sum / $weight;

  case 'median':
    $WW = array_sum( $wdata ) / 2.0;

    $median = '?';
    $wsum = 0.0;
    ksort( $wdata );
    foreach( $wdata as $g => $w ) {
      $wsum += $w;
      if( $wsum > $WW ) {
	$median = $g;
	break;
      }
    }
    return $median;
    
  case 'mode':
    arsort( $wdata );
    reset( $wdata );
    return key( $wdata ); 	/* First element has the most weight */
  } 
}


function sqr( $x ) { return $x*$x; }

function FmtGrade( $grade, $ndp ) {
  if( is_float( $grade ) )
    return number_format( $grade, $ndp ?? 2 );
  else if( $grade == 0 )
    return 0;
  else
    return $grade;
}


function calcGrades( ) {
  list( $assmtID, $cid ) = checkREQUEST( '_assmtID', '_cid' );

  ensureDBconnected( 'calcGrades' );
  
  $assmt = fetchOne( "SELECT * FROM Assignment WHERE assmtID = $assmtID"
		     . ' AND courseID = ' . cidToClassId( $cid ));
  if( ! $assmt )
    return warning( _('You do not have access to assignment #'), $assmtID );


  $g = "grades-$assmtID";
  if( ! isset( $_SESSION[$g] ) )
    $_SESSION[$g] = array('ignore'=>array( ),
			  'tag'=>null,
			  'weightMethod'=>'equally',
			  'avgMethod'   =>'mean');

  $gcalc = computeGrades( $assmtID, $assmt['markItems'], $assmt['markGrades'] );

  $excluded = HTML( );
  /*
  if( ! empty( $_SESSION['blacklist'] ) ) {
    $black = HTML::ul( );
    foreach( $_SESSION['blacklist'] as $reviewer )
      $black->pushContent( HTML::li( $reviewer ) );
    $excluded->pushContent( HTML::h2( 'Blacklisted reviewers'), $black );
  }
  if( ! empty( $_SESSION['ignore'] ) ) {
    $ignore = HTML::li( );
    foreach( $_SESSION['ignore'] as $alloc ) ****FIXME**** 
      $ignore->pushContent( HTML::li( $alloc['reviewer'], ' review of ', $a['author']) );
    $excluded->pushContent( HTML::h2( 'Excluded reviews' ), $ignore );
  }
  */

  $markGrades = stringToItems( $assmt['markGrades'] );
  parse_str($assmt['markLabels'] ?? '', $markLabels);
  parse_str($assmt['markItems'] ?? '', $markItems);
  /*
  $markGradesTable = table( array('id'=>'markGradeTable'),
			    HTML::tr( HTML::th( _('Radio group') ), HTML::th( _('Grade') )));
  foreach( $markItems as $group => $nValues ) {
    $markGradesTable->pushContent( HTML::tr( HTML::td( array('class'=>'group'), $markLabels[ $group ] )));
    for( $item = 0; $item < $nValues; $item++ )
      $markGradesTable->pushContent( HTML::tr( HTML::td( array('class'=>'item'),  $item + 1),
					       HTML::td( HTML::input( array('type'=>'text',
									    'value'=>$markGrades[$group][$item],
									    'size'=>4)))));
  }
  */
  extraHeader('gradeBrowser.js', 'script');
  
  $g = $_SESSION["grades-$assmtID"];

  $wOptions = array('equally' => _('Equally'));
  if( fetchOne( "SELECT assmtID FROM Assignment WHERE isReviewsFor = $assmtID" ) )
    $wOptions['reviewRating'] = _('Using review ratings');
  if( fetchOne( "SELECT count(*) AS n FROM Allocation WHERE assmtID=$assmtID GROUP BY reviewer HAVING n>1 LIMIT 1" ) )
    $wOptions['autoCalibrate'] = _('Using an auto-calibration algorithm that gives extra weight to reviewers whose reviews align well with the consensus');
  if( count( $wOptions ) > 1 )
    $weightOptions = RadioGroup('weightMethod',
				_('Weight reviewers'),
				$g['weightMethod'],
				$wOptions);
  else
    $weightOptions = $wOptions['equally'];  

  $tagOptions = array('' => 'All');
  foreach( fetchAll( "SELECT DISTINCT tag FROM Allocation WHERE assmtID=$assmtID", 'tag' ) as $tag )
    if( ! empty( $tag ) )
      $tagOptions[$tag] = $tag;
  
  if( count( $tagOptions ) <= 2 )
    $tagOption = '';
  else
    $tagOption = RadioGroup('tagSelect',
			    _('Use these allocations'),
			    $g['tag'],
			    $tagOptions);
  
  $instructions
    = HTML::div(array('class'=>'form'),
		$tagOption,
		RadioGroup('avgMethod',
			   _('Calculate aggregate marks using'),
			   $g['avgMethod'],
			   array('mean'=>_('Mean of reviewer marks'),
				 'median' => _('Median reviewer mark'),
				 'mode' => _('Mode of reviewer marks'))),
		$weightOptions,
		ButtonToolbar(HTML::button(array('type'=>'button',
						 'class'=>'btn',
						 'onclick'=>'recalcGrades(); return false;'),
					   _('Recalculate grades'))));
  
  $tr = HTML::tr(HTML::th('Reviewer'));
  $N = count($gcalc->markItems);
  foreach (array_keys($gcalc->markItems) as $idx => $item)
    $tr->pushContent(HTML::th(array('title'=>$item), itemCode($idx, $N)));
  $tr->pushContent( HTML::th(_('Total')),
		    HTML::th(_('Extent of commenting')),
		    HTML::th(_('Exclude review?')));
  $authorDetailTemplate = Modal('authorDetailTemplate',
				HTML(_('All marks given for author: '),
				     HTML::span(array('id'=>'authorDetailTemplateName'))),
				table(HTML::thead($tr), HTML::tbody()),
				'',
				array('onclick' => 'recalcGrades(); return false;'),
				_('Recalculate'));
  
  $tr = HTML::tr(HTML::th('Author'));
  $N = count($gcalc->markItems);
  foreach (array_keys($gcalc->markItems) as $idx => $item)
    $tr->pushContent(HTML::th(array('title'=>$item), itemCode($idx, $N)));
  $tr->pushContent(HTML::th(_('Total')),
		   HTML::th(_('Extent of commenting')),
		   HTML::th(_('Exclude review? ')));
  $reviewerDetailTemplate = Modal('reviewerDetailTemplate',
				  HTML(_('All marks given by reviewer: '),
				       HTML::span(array('id'=>'reviewerDetailTemplateName'))),
				  HTML(HTML::p(_('Numbers in square brackets are the average marks for the section over all reviewers.'),
					       _(' A significant discrepancy may indicate the reviewer misunderstood the grading criteria for that question.')),
				       table(HTML::thead($tr), HTML::tbody())),
				  '',
				  array('onclick' => 'recalcGrades(); return false;'),
				  _('Recalculate'));
  $commentDetailTemplate = Modal('commentDetailTemplate',
				 HTML(_('Reviewing details: '),
				      HTML::span( array('id'=>'commentDetailTemplateName'))),
				 HTML::div(array('id'=>'commentDetailTemplateBody')),
				 '');
  
  /* Author table; order must agree with JSON_grades */
  $authorTable = table(HTML::thead(HTML::tr(HTML::th(HTML::a(array('onclick'=>"sortGrades(0)"),
							     _('Author'))),
					    HTML::th(HTML::a(array('onclick'=>"sortGrades(1)"),
							     _('Aggregate mark'))),
					    HTML::th(HTML::a(array('onclick'=>"sortGrades(2)",
								   'title'=>_('The number of reviews received by the author')),
							     _('Reviews'))),
					    HTML::th(HTML::a(array('onclick'=>"sortGrades(3)",
								   'title'=>_('Discrepancy indicates the extent of disagreement between 
reviewers\' marks for an author\'s submission. A high Discrepancy may indicate that some reviews are anomalous;
you may choose to exclude highly anomalous reviews in the grade calculation.')),
							     _('Discrepancy'))))),
		       HTML::tbody(array('id'=>'authorTableBody')));
  
  /* Reviewer table; order must agree with JSON_reviewers */
  $reviewerTable = table(HTML::thead(HTML::tr(HTML::th(HTML::a(array('onclick'=>"sortReviewers(0)"),
							       _('Reviewer') )),
					      HTML::th(HTML::a(array('onclick'=>"sortReviewers(2)",
								     'title'=>_('The number of reviews written by the reviewer')),
							       _('Reviews')  )),
					      HTML::th(array('id'=>'colComments'),
						       HTML::a(array('onclick'=>"sortReviewers(3)",
								     'title'=>_('The total number of characters in comments written by the reviewer')),
							       _('Comments') )),
					      HTML::th(array('id'=>'colWeight'),
						       HTML::a(array('onclick'=>"sortReviewers(1)",
								     'title'=>_('The weighting given to this reviewer\'s marks in calculating the combined grade for each author')),
							       _('Weight'))))),
			 HTML::tbody(array('id'=>'reviewerTableBody')));
  extraHeader('bootstrap.min.js', 'js');
  extraHeader('loadGrades(); loadReviewers()', 'onload');
  extraHeader('$(document).on("show.bs.modal", ".modal", function () {
    var zIndex = 1040 + (10 * $(".modal:visible").length);
    $(this).css("z-index", zIndex);
    setTimeout(function() {
        $(".modal-backdrop").not(".modal-stack").css("z-index", zIndex - 1).addClass("modal-stack");
    }, 0);
})', 'onload');
  $working = HTML::div(array('class'=>'modal hide',
			     'id'=>'working',
			     'data-backdrop'=>'static',
			     'data-keyboard'=>'false'),
		       HTML::div(array('class'=>'modal-header'),
				 HTML::h1(_('Recalculating...'))),
		       HTML::div(array('class'=>"modal-body"),
				 HTML::div(array('class'=>'progress progress-striped active'),
					   HTML::div(array('class'=>'bar',
							   'style'=>'width: 100%;')))));

  return HTML(assmtHeading('View marks', $assmt),
	      ButtonToolbar(Button(_('Download aggregate marks'), "downloadGrades&assmtID=$assmtID&cid=$cid"),
			    Button(_('Download all marks'), "fullMarkCSV&assmtID=$assmtID&cid=$cid")),
	      HTML::br(),
	      HTML::div(HTML::ul(array('class'=>'nav nav-pills',
				       'role'=>'tablist'),
				 HTML::li(array('role'=>'presentation',
						'class'=>'active'),
					  HTML::a(array('href'=>'#authors-tab',
							'role'=>'tab',
							'aria-controls'=>'authors-tab',
							'data-toggle'=>'tab'),
						  HTML::span(_('Authors')))),
				 HTML::li(array('role'=>'presentation'),
					  HTML::a(array('href'=>'#reviewers-tab',
							'role'=>'tab',
							'aria-controls'=>'reviewers-tab',
							'data-toggle'=>'tab'),
						  HTML::span(_('Reviewers')))),
				 HTML::li(array('role'=>'presentation'),
					  HTML::a(array('href'=>'#settings-tab',
							'role'=>'tab',
							'aria-controls'=>'settings-tab',
							'data-toggle'=>'tab'),
						  HTML::span(_('Settings'))))),
			HTML::div(array('class'=>'tab-content'),
				  HTML::div(array('id'=>'authors-tab',
						  'role'=>'tabpanel',
						  'class'=>'tab-pane fade active in'),
					    $authorTable),
				  HTML::div(array('id'=>'reviewers-tab',
						  'role'=>'tabpanel',
						  'class'=>'tab-pane fade'),
					    $reviewerTable),
				  HTML::div(array('id'=>'settings-tab',
						  'role'=>'tabpanel',
						  'class'=>'tab-pane fade'),
					    $instructions,
					    $working))),
	      $authorDetailTemplate,
	      $reviewerDetailTemplate,
	      $commentDetailTemplate,
	      Javascript('_assmtID = '      . $assmtID                  . ';'),
	      Javascript('_cID = '          . $cid                      . ';'),
	      Javascript('_gradeData = '    . json_encode( $gcalc->JSON_grades( ))    . ';'),
	      Javascript('_reviewerData = ' . json_encode( $gcalc->JSON_reviewers( )) . ';'),
	      Javascript('_allocData = '    . json_encode( $gcalc->JSON_allocs( ))    . ';'));
}

function downloadGrades( ) {
  list( $assmtID, $cid ) = checkREQUEST( '_assmtID', '_cid' );

  ensureDBconnected( 'downloadGrades' );
  
  $assmt = fetchOne( "SELECT * FROM Assignment WHERE assmtID = $assmtID"
		     . ' AND courseID = ' . cidToClassId( $cid ));
  if( ! $assmt )
    return warning( _('You do not have access to assignment #'), $assmtID );

  computeGrades( $assmtID, $assmt['markItems'], $assmt['markGrades'] )->dumpToFile( $assmt['aname'], "aggregate-marks-$assmtID.csv" );
  exit;
}


function computeGrades( $assmtID, $markItemsStr, $markGradesStr ) {
  set_time_limit( 10*60 ); //- Hack; 10 minutes
  ini_set( 'memory_limit', '128M' );

  $g = $_SESSION["grades-$assmtID"];
  $tag = isset( $g['tag'] ) ? $g['tag'] : null;

  parse_str($markItemsStr ?? '', $markItems);
  $markGrades = stringToItems( $markGradesStr );

  $allocs  = new Allocations( $assmtID, 'all', $tag );
  $gcalc   = new GradeCalc( $assmtID, $allocs, $markItems, $markGrades );

  $ratingWeights = getPresetWeights( $assmtID );
  if( $ratingWeights !== false ) {
    $gcalc->weights = $ratingWeights;
    $gcalc->updateGrades( true );
  } else
    for( $iteration = 0; $iteration < 4; $iteration++ ) {
      $gcalc->updateGrades( $iteration == 0 );
      $gcalc->updateWeights( );
    }

  return $gcalc;
}


function getPresetWeights( $assmtID ) {
  $g = $_SESSION["grades-$assmtID"];
  $weightMethod = isset( $g['weightMethod'] ) ? $g['weightMethod'] : 'equally';

  switch( $weightMethod ) {
  case 'equally':
    //- This forces all weights to be 1.0 (see updateGrades)
    return array( );

  case 'autoCalibrate':
    return false;

  default:
  case 'reviewRating':
    $assmt = fetchOne( 'SELECT assmtID, markItems, markGrades FROM Assignment'
		       . " WHERE isReviewsFor = $assmtID");
    if( ! $assmt )
      return array( ); // default to equal weights
    
    $gcalc = computeGrades( $assmt['assmtID'], $assmt['markItems'], $assmt['markGrades'] );
    $weights = array( );
    foreach( $gcalc->grades as $author => $gs )
      $weights[ $author ] = array_sum( $gs );
    return $weights;
  }
}



function jsonGrades( ) {
  list( $assmtID, $cid ) = checkREQUEST( '_assmtID', '_cid');

  ensureDBconnected( 'jsonGrades' );
  
  /* allocations to ignore/un-ignore, reviewers to
     blacklist/un-blacklist, change of averaging method, change of
     weight method */
  $g = &$_SESSION["grades-$assmtID"];

  if( isset( $_REQUEST['excl'] ) )
    $g['ignore'] = array_unique( array_merge( $g['ignore'], explode( ',', $_REQUEST['excl'] )));

  if( isset( $_REQUEST['incl'] ) )
    $g['ignore'] = array_diff( $g['ignore'], explode( ',', $_REQUEST['incl'] ));

  if( isset( $_REQUEST['weightMethod'] ) )
    $g['weightMethod'] = $_REQUEST['weightMethod'];

  if( isset( $_REQUEST['avgMethod'] ) )
    $g['avgMethod'] = $_REQUEST['avgMethod'];

  if( isset( $_REQUEST['tag'] ) )
    $g['tag'] = $_REQUEST['tag'];

  /*
  if( isset( $_REQUEST['mgrades'] ) && is_array( $_REQUEST['mgrades'] ) ) {
    $mgrades = array( );
    foreach( $_REQUEST['mgrades'] as $mg ) {
      list( $item, $grade ) = $mg;
      $mgrades[ $item ] = $grade;
    }
    checked_mysql_query( 'UPDATE Assignment SET markGrades = ' . quote_smart( itemsToString( $mgrades ) )
			 . " WHERE assmtID = $assmtID AND courseID = " . cidToClassId( $cid ) );
  }
  */
  $assmt = fetchOne( 'SELECT markItems, markGrades FROM Assignment'
		     . " WHERE assmtID = $assmtID"
		     . ' AND courseID = ' . cidToClassId( $cid ));

  header('Content-Type: application/json');
  if( $assmt ) {
    $gcalc = computeGrades( $assmtID, $assmt['markItems'], $assmt['markGrades'] );
    echo json_encode( array( $gcalc->JSON_grades( ), $gcalc->JSON_reviewerWeights( )));
  } else
    echo json_encode( array(array( ), array( )));
  exit;
}



function fullMarkCSV( ) {
  list ($assmt, $assmtID, $cid) = selectAssmt();
  if (!$assmt)
    missingAssmt();

  while (ob_end_clean())
    ;
  if (strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE' ) !== false) {
    //- see last entry in http://php3.de/manual/en/function.session-cache-limiter.php
    session_cache_limiter('public');
    header("Cache-Control: no-store, no-cache, must-revalidate");
    // see http://www.alagad.com/blog/post.cfm/error-internet-explorer-cannot-download-filename-from-webserver
    header("Pragma: public");
    header("Cache-Control: max-age=0");
  }
  
  header('Content-Type: text/csv');
  header("Content-Disposition: attachment; filename=all-marks-$assmtID.csv;");
  header('Content-Transfer-Encoding: binary');
  session_write_close();
  
  echo "# All reviewing marks for $assmt[aname]\n";

  parse_str($assmt['markItems'] ?? '', $markItems);
  $markGrades = stringToItems($assmt['markGrades']);
  parse_str($assmt['markLabels'] ?? '', $markLabels);

  echo "Author,Reviewer";
  foreach (array_keys($markItems) as $item) {
    if (isset($markLabels[$item]))
      $item = $markLabels[$item];
    echo ',"' . addcslashes($item, '"') . '"';
  }
  
  echo "\n";

  require_once 'Allocations.php';
  $allocs = new Allocations($assmtID, 'marks');
  foreach ($allocs->allocations as $alloc) {
    echo $allocs->nameOfAuthor($alloc['author']) . ',' . $allocs->nameOfReviewer($alloc['reviewer']);   
    $marks = $alloc['marks'];
    foreach ($markItems as $item => $values) {
      if (!isset($marks[$item]))
	echo ',';
      else {
	$m = $marks[$item];
	if (isset($markGrades[$item]) && isset($markGrades[$item][$m-1]))
	  $mark = $markGrades[$item][$m-1];
	else
	  $mark = $m;
      }
      
      echo ",$mark";
    }
    
    echo "\n";
  }
  
  exit;
}

function commentsByAlloc( ) {
  list( $assmtID, $cid, $allocID ) = checkREQUEST( '_assmtID', '_cid', '_allocID' );

  if( ! isset( $_SESSION['current-assmt'] )
      || $_SESSION['current-assmt']['assmtID'] != $assmtID
      || $_SESSION['current-assmt']['courseID'] != $courseID
      ) {
    list( $assmt, $assmtID, $cid, $courseID ) = selectAssmt( );
    $_SESSION['current-assmt'] = $assmt;
  } else
    $assmt = $_SESSION['current-assmt'];
  
  $div = HTML::div();
  if( $assmt ) {
    $commentItems = commentLabels($assmt);
   
    require_once 'BlockParser.php';
    if( ! empty( $assmt['commentItems'] ) ) {
      $rs = checked_mysql_query( "SELECT item, comments FROM Comment WHERE allocID=$allocID ORDER BY LENGTH(item), item" );
      while( $row = $rs->fetch_assoc() ) {
	if( is_numeric( $row['item'] ) ) {
	  if( ! empty( $commentItems[ $row['item'] ] ) )
	    $item = "($row[item]) " . $commentItems[ $row['item'] ];
	  else
	    $item = Sprintf_('Comment %d', $row['item'] + 1 );
	} else
	  $item = $row['item'];
	$div->pushContent( HTML::p( HTML::span(array('class'=>'itemFB'),   $item),
				    HTML::div( array('class'=>'commentFB'),
					       MaybeTransformText( $row['comments'] ) )));
      }
    }
  }
  PrintXML( $div );
  exit;
}
