<?php
/*
    Copyright (C) 2012 John Hamer <J.Hamer@acm.org>

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

class TagOtherAllocator {
  var $allocs;
  var $authors;
  var $reviewers;
  var $essayTags;
  var $nR;

  //  const BADNESS_LIGHT = 0;
  //  const BADNESS_HEAVY = 1;
  //  const BADNESS_ESSAY = 2;
  const BADNESS_MAX   = PHP_INT_MAX;
  
  function __construct( $assmt ) {
    $assmtID = $assmt['assmtID'];
    $this->nR = $assmt['nPerReviewer'];
    $tags = fetchAll( "SELECT DISTINCT IFNULL(tag,'MISSING-TAG') AS tag FROM Essay WHERE assmtID=$assmtID", 'tag' );
    // We could use explode(';', $assmt['tags']), but then we should
    //   have to worry about unused tags and essays submitted (somehow)
    //   with a different tag.
    $this->essayTags = array( );
    $rs = checked_mysql_query( "SELECT DISTINCT author, IFNULL(tag,'MISSING-TAG') AS tag FROM Essay WHERE assmtID=$assmtID" );
    while( $row = $rs->fetch_assoc() )
      $this->essayTags[ $row['author'] ] = strtoupper( $row['tag'] );
    
    require_once 'Allocations.php';
    $this->allocs = new Allocations( $assmtID, 'fixedOnly' );
    $this->reviewers = findAllReviewers( $assmt );
    $this->authors   = findAllAuthors( $assmt );
  }

  function allocate( ) {
    $bestTrial = PHP_INT_MAX;
    for( $i = 0; $i < 10 && $bestTrial != 0; $i++ ) {
      $seed = make_seed( );
      $nE = $this->doOneTrial( $seed );
      if( $nE < $bestTrial ) {
	$bestTrial = $nE;
	$bestTrialSeed = $seed;
      }
    }
    $this->doOneTrial( $bestTrialSeed );
    $this->allocs->save( );
  }
  
  function findBestMove( $minBad ) {
    $bad = self::BADNESS_MAX;
    $reviewers = random_permutation( $this->reviewers );
    foreach( $this->authors as $e )
      foreach( $reviewers as $r ) {
	if( $r == $e || $this->allocs->alreadyAllocated( $r, $e ) )
	  continue;
	$b_re = $this->badness( $r, $e );
	if( $b_re < $bad ) {
	  $bestR = $r;
	  $bestE = $e;
	  if( $b_re == $minBad )
	    return array( $bestR, $bestE, $minBad );
	  $bad = $b_re;
	}
      }
    return array( $bestR, $bestE, $bad );
  }
  
  
  function badness( $r, $e ) {
    if( $r == $e )
      //- Absolutely forbidden.  Self-review is handled separately.
      return self::BADNESS_MAX;

    $cR = $this->allocs->essayCount( $r );
    if( $cR >= $this->nR )
      //- Never allow reviewers to be over-worked.
      //- This should really be a policy decision.
      return self::BADNESS_MAX;

    $cE = $this->allocs->reviewCount( $e );
    if( $cR >= $this->nR )
      //- We don't want essays to be over-reviewed, but can live with this if necessary.
      return (2 * $cR - $this->nR + 1) * count($this->authors);

    if( $this->essayTags[ $e ] == $this->essayTags[ $r ] )
      //- Try to avoid allocating someone to the same tag as they submitted under.
      //- This should really be a policy decision.
      return count($this->authors);

    //- Prefer lightly loaded reviewers and essays.
    return $cR + $cE;
  }


  function doOneTrial( $seed ) {
    sort( $this->authors );
    sort( $this->reviewers );
    $this->allocs->reset( );
    $minBad = 0;
    $nReqd = count( $this->reviewers ) * $this->nR - $this->allocs->allocationCount( );
    mt_srand( $seed );
    set_time_limit( 30 );
    for( $i = 0; $i < $nReqd; $i++ ) {
      list( $bestR, $bestE, $minBad ) = $this->findBestMove( $minBad );
      if( $minBad == self::BADNESS_MAX )
	break;
      $this->allocs->add( $bestR, $bestE, $this->essayTags[ $bestE ] );
    }
    return $this->nErrors( );
  }


  function nErrors( ) {
    $nE = 0;
    foreach( $this->reviewers as $r ) {
      $d = $this->allocs->essayCount( $r ) - $this->nR;
      $nE += $d * $d;
    }
    foreach( $this->authors as $e ) {
      $d = $this->allocs->reviewCount( $e ) - $this->nR;
      $nE += $d * $d;
    }
    return $nE;
  }
};


function make_seed( ) {
  return mt_rand( );

  //- From php-reference-manual/function.srand.html
  //  list($usec, $sec) = explode(' ', microtime( ));
  //  return (int)$sec ^ ((int)$usec * 100000);
}
