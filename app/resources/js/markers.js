function maybeAddTableRow(tableID, maybe) {
    var last = $('#' + tableID).find('tr:last');
    if( maybe && last.find('input[type="text"]').val().trim() == '')
	return;
    
    var row = last.clone();
    row.find('input[type="radio"]').attr('checked', false);
    row.find('input[type="text"]').val('').on('blur', function() { maybeAddTableRow(tableID, true);});
    row.find('input').each(function() {
	var ma =  this.name.match( /^(\w*)\[(\d+)\]$/ );
	if (ma != null)
	    this.name = ma[1] + "[" + (parseInt(ma[2]) + 1) + "]";
    });
    last.after(row);
}

function quickMarkerLoad( ) {
    var tbody = $('#people-table');
    var lines = $('#quickMarkerArea')
	.val()
	.split(/\s*\n\s*/)
	.sort()
	.filter(function(el,i,a) { return el.length > 0 && i == a.indexOf(el); });
    var m = tbody.find('tr:last td:first input').attr('name').match( /people\[(\d+)\]/ );
    var n = m ? parseInt(m[1])+1 : 0;
    for (var i = 0; i < lines.length; i++, n++) {
	var tr = $('<tr>');
	tr.append($('<td>')
		  .append($('<input>')
			  .attr({type: 'text',
				 name: 'people[' + n + ']',
				 value: lines[i]})
			  .on('blur', function() { maybeAddTableRow(tableID, true);})
			  .addClass('form-control')));
	addRole(tr, n, 8, 'instructor', false);
	addRole(tr, n, 4, 'guest', false);
	addRole(tr, n, 2, 'marker', true);
	tbody.append(tr);
    }
}

function addRole(tr, n, value, desc, checked) {
    return tr
	.append($('<td>')
		.append($('<label>')
			.append($('<input>')
				.attr({type: 'radio',
				       name: 'role[' + n + ']',
				       value: value})
				.css('display', 'none')
				.prop('checked', checked))
			.append(' ' + desc)
			.addClass('btn btn-default radio' + (checked ? ' checked' : ''))));
}
