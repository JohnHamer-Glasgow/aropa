<?php
/*
    Copyright (C) 2014 John Hamer <J.Hamer@acm.org>

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

function allocateOtherTags($tagGroupSizes, $nPerReviewer) {
  $max = $tagGroupSizes[0];
  $Size = array_sum($tagGroupSizes);
  if ($Size == 0) return;
  $allocs = new AllocBitArray($Size);

  for ($i = 0; $i < $Size; $i++)
    for ($a = 0; $a < $nPerReviewer; $a++)
      $allocs->set($max + $i + $a, $i, true);

  $overlap = 2*$max - $Size + $nPerReviewer - 1;
  if ($overlap > 0) {
    for ($o1 = 1; $o1 < $nPerReviewer; $o1++)
      for ($o2 = $o1; $o2 < $nPerReviewer; $o2++) {
	$x0 = $max + $nPerReviewer - $o2 - 1;
	$y0 = $Size - $o1;
	$x1 = ($x0 + $max) % $Size;
	$y1 = $y0 - $max - 1;
	$allocs->exch($x0, $y0, $max);
      }
  }

  $G = array();
  $g = 0;
  foreach ($tagGroupSizes as $tgs) {
    for ($i = 0; $i < $tgs; $i++)
      $G[] = $g;
    $g++;
  }

  $bins = array();
  for ($y = 0; $y < $Size; $y++)
    for ($x = 0; $x < $Size; $x++)
      if ($allocs->get($x, $y))
	$bins[$G[$x]][$y]++;

  $tries = 0;
  $N = count($tagGroupSizes);
  for ($Tn = $nPerReviewer; $Tn > 1; $tries++) {
    $changed = false;
    for ($y = 0; $y < $Size; $y++)
      for ($x = 0; $x < $Size; $x++)
	if ($allocs->get($x, $y) && $bins[$G[$x]][$y] == $Tn) {
	  $found = false;
	  for ($k = $y + 1; $k < $Size && !$found; $k++) {
	    $g0 = 0;
	    for ($h = 0; $h < $N; $g0 += $n, $h++) {
	      $n = $tagGroupSizes[$h];
	      if ($h != $G[$x] &&
		  $h != $G[$y] &&
		  $G[$x] != $G[$k] &&
		  $bins[$h][$k] >= $Tn) {
		$found = true;
		for ($l = 0; $l < $n; $l++)
		  if ($allocs->get($l + $g0, $k) &&
		      !$allocs->get($l + $g0, $y) &&
		      !$allocs->get($x, $k)) {
		    $allocs->exch2($x, $y, $l + $g0, $k);
		    $changed = $tries < 4;
		    $bins[$G[$x]][$y]--;
		    $bins[$h][$y]++;
		    $bins[$G[$x]][$k]++;
		    $bins[$h][$k]--;
		    break;
		  }
		break;
	      }
	    }
	  }
	  if ($found) break;
	}
    if (!$changed)
      {
	$Tn--;
	$tries = 0;
      }
  }

  return $allocs;
}

class AllocBitArray {
  var $bits;
  var $width;
  
  function __construct($width) {
    $this->width = $width;
    $this->bits = array();
    $n = (int)($width * $width / 32)+1;
    for ($i = 0; $i < $n; $i++)
      $this->bits[$i] = 0;
  }
  
  function set($x, $y, $value) {
    $x %= $this->width;
    $y %= $this->width;
    $i = $x * $this->width + $y;
    $a = (int)($i / 32);
    $b = $i % 32;
    if ($value)
      $this->bits[$a] |= 1 << $b;
    else
      $this->bits[$a] &= ~(1 << $b);
  }
  
  function get($x, $y) {
    $x = $x % $this->width;
    $y = $y % $this->width;
    $i = $x * $this->width + $y;
    $a = (int)($i / 32);
    $b = $i % 32;
    return ($this->bits[$a] & (1 << $b)) != 0;
  } 

  function swap($x0, $y0, $x1, $y1) {
    $tmp = $this->get($x0, $y0);
    $this->set($x0, $y0, $this->get($x1, $y1));
    $this->set($x1, $y1, $tmp);
  }

  function exch2($x0, $y0, $x1, $y1) {
    $this->swap($x0, $y0, $x0, $y1);
    $this->swap($x1, $y0, $x1, $y1);
  }

  function exch($x0, $y0, $d) {
    $this->swap($x0, $y0, $x0, $y0 + $d);
    $this->swap($x0 + $d, $y0 + $d, $x0 + $d, $y0);
  }

  function dump() {
    for ($y = 0; $y < $this->width; $y++) {
      for ($x = 0; $x < $this->width; $x++)
	echo $this->get($x, $y) ? 'X' : '.';
      echo "\n";
    }
  }
}
