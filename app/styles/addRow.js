function rowIsEmpty( row ) {
  for( var i = 0; i < row.cells.length; i++ ) {
    var e = row.cells[i].firstChild;
    if( e && e.type == 'text' && e.className != 'incr' && e.value != '' )
      return false;
  }
  return true;
}

function incr( str ) {
    var match = /^(.*)(\d+)(.*)$/.exec( str );
    if( match )
	return match[1] + (parseInt(match[2]) + 1) + match[3];
    else
	return '';
}

function maybeAddTableRow( tableID, maybe ) {
  var table = document.getElementById( tableID );
  if( ! table )
    return;

  var rowCount = table.tBodies[0].rows.length;
  var lastRow  = table.tBodies[0].rows[rowCount-1];
  if( maybe && rowIsEmpty( lastRow ) )
    return;

  var row = table.insertRow(-1);
  for( var i = 0; i < lastRow.cells.length; i++ )
    if( lastRow.cells[i].firstChild ) {
      var e = lastRow.cells[i].firstChild.cloneNode( true );

      var ma =  e.name.match( /^(\w*)\[(\d+)\]$/ );
      if( ma != null )
	e.name = ma[1] + "[" + (parseInt(ma[2]) + 1) + "]";
      if( e.nodeName == 'INPUT' )
	switch( e.type ) {
	case 'checkbox':
	case 'radio':
	  e.checked = false;
	  break;
	case 'select':
	  e.selectedIndex = -1;
	  break;
	case 'text':
	    if( e.className == 'incr' )
		e.value = incr( e.value );
	    else
		e.value = '';
	  e.onblur = function() {maybeAddTableRow(tableID,true);};
	}

      row.insertCell(-1).appendChild(e);
    }
}
