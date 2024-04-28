function rowIsEmpty( row ) {
  for( var i = 0; i < row.cells.length; i++ ) {
    var e = row.cells[i].firstChild;
    if( e && e.type == 'text' && e.value != '' )
      return false;
  }
  return true;
}

function addRow() {
    var table = document.getElementById('extensions');
    if( ! table )
	return;
    
    var rowCount = table.tBodies[0].rows.length;
    if (rowCount == 0) {
	$(table).append('<tr><td><input class="typeahead" data-provide="typeahead" name="uident[0]"/></td><td></td></tr>');
	rowCount = 1;
    }

    var lastRow  = table.tBodies[0].rows[rowCount-1];
    if( rowIsEmpty(lastRow) )
	return;

    var row = table.insertRow(-1);
    for( var i = 0; i < lastRow.cells.length; i++ )
	if( lastRow.cells[i].firstChild ) {
	    var e = lastRow.cells[i].firstChild.cloneNode(true);
	    row.insertCell(-1).appendChild(e);
	    
	    var ma =  e.name.match( /^(\w*)\[(\d+)\]$/ );
	    if( ma != null ) {
		e.name = ma[1] + "[" + (parseInt(ma[2]) + 1) + "]";
		if (ma[1] == 'uident') {
		    var assmtID = $('input[name="assmtID"]').val();
		    var cid = $('input[name="cid"]').val();
		    $(e).removeData('typeahead')
			.typeahead({ajax: 'aropa.php?action=jsonExtn&cid=' + cid + '&assmtID=' + assmtID})
			.change(updateTitles);
		} else if (ma[1] == 'sdate' || ma[1] == 'rdate')
		    $(e).attr('readonly', false)
			.attr('type', 'text')
			.attr('autocomplete', 'off')
			.val(function(i,v) {return v.replace(/(\d\d\d\d-\d\d-\d\d)T(\d\d:\d\d):\d\d/, '$1 $2');})
			.datetimepicker({format: 'yyyy-mm-dd hh:ii'});
	    }

	    if( e.nodeName == 'INPUT' )
		switch( e.type ) {
		case 'select':
		    e.selectedIndex = -1;
		    break;
		case 'text':
		    e.value = '';
		    e.onblur = addRow;
		}
	}
}

function updateTitles(event) {
    var assmtID = $('input[name="assmtID"]').val();
    var cid = $('input[name="cid"]').val();
    $.getJSON('aropa.php',
	      { action: 'jsonHasUploaded',
		cid: cid,
		assmtID: assmtID,
		uident: $(event.target).val() },
	      function(data) {
		  var tr = $(event.target).closest('tr');
		  tr.find('input[name^="sdate"]').attr('title', data[0]);
		  tr.find('input[name^="rdate"]').attr('title', data[1]);
	      });
}
