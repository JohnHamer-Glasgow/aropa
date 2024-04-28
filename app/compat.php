<?php
if( !function_exists("http_build_query") ) {
  function http_build_query( $data, $int_prefix="", $subarray_pfix="", $level=0 ) {
    $s = "";
    ($SEP = ini_get("arg_separator.output")) or ($SEP = "&");
    foreach( $data as $index => $value ) {
      if( $subarray_pfix ) {
	if( $level )
	  $index = "[" . $index . "]";
	$index = $subarray_pfix . $index;
      } else if( is_int($index) && strlen($int_prefix) )
	$index = $int_prefix . $index;
      
      if( is_array($value) )
	$s .= http_build_query($value, "", $index, $level + 1);
      else // or just literal URL parameter
	$s .= $SEP . $index . "=" . urlencode($value);
    }
    if( !$subarray_pfix )
      $s = substr($s, strlen($SEP));
    return($s);
  }
}

if( !function_exists("hex2bin") ) {
  function hex2bin( $h ) {
    return pack("H*" , $h);
  }
}
