function checkNames(names) {
    updateUnassigned();
    $.getJSON( 'aropa.php',
	       { action: 'checkNames',
		 cid:    cid,
		 names:  names },
	       function( unrecognised ) {
		   if( unrecognised )
		       alert( unrecognised );
	       } );
}

function getGroups(cid) {
    var sel = $('#selectAssmt option:selected');
    if( $('#groups tbody tr').length == 0 || confirm($('#getGroupsPrompt').val()) )
	sel.each( function() {
	    $.getJSON( 'aropa.php',
		       { action: 'getGroups',
			 cid: cid,
			 assmtID: $(this).val( ) },
		       loadGroups );
	});
}

function loadGroups( groups ) {
    var tbody = $('#groups tbody');
    tbody.empty( );
    for( var i = 0; i < groups.length; i++ ) {
	var groupID = i + 1;
	var tr = $(document.createElement('tr'));
	var input = $(document.createElement('input'))
	    .attr('type', 'text')
	    .attr('size',10)
	    .attr('value', groups[i][0])
	    .attr('name', 'gname[' + groupID + ']');
	$(document.createElement('td')).html( input ).appendTo( tr );
	var input = $(document.createElement('input'))
	    .attr('type', 'text')
	    .attr('size', 80)
	    .attr('value', groups[i][1])
	    .attr('name', 'members[' + groupID + ']');
	$(document.createElement('td')).html( input ).appendTo( tr );
	tbody.append( tr );
    }
}

function setDefaultGroupName(gname) {
    if (gname.attr('value').trim() == '') {
	gname.attr('value', "Group-" + gname.attr('name').replace('gname[', '').replace(']', ''));
	gname.select();
    }
}

function maybeAddRow( ) {
    var tbody = $('#groups tbody');
    if(tbody.find('tr:last td input[name^="gname"]').val().trim() == '')
	return;
    var groupID = tbody[0].rows.length + 1;
    var tr = $(document.createElement('tr'));
    var input = $(document.createElement('input'))
	.attr('type', 'text')
	.attr('size',10)
	.attr('value', '')
	.on('focus', function() { setDefaultGroupName($(this)); })
	.attr('name', 'gname[' + groupID + ']');
    $(document.createElement('td')).html( input ).appendTo( tr );
    var input = $(document.createElement('input'))
	.attr('type', 'text')
	.attr('size',80)
	.on('blur', function() {checkNames(this.value);})
	.on('focus', maybeAddRow)
	.attr('value', '')
	.attr('name', 'members[' + groupID + ']');
    $(document.createElement('td')).html( input ).appendTo( tr );
    tbody.append( tr );
}

function updateUnassigned() {
    var all = [];
    $("input[name^='member']").each(function() {
	var names = $(this).val().split(/[ ,;]/);
	for(var i = 0; i < names.length; i++)
	    if(names[i].trim() != '') all.push(names[i]);
    });
    
    $('#unassigned')
	.children()
	.each(function() {
	    if (jQuery.inArray($(this).text(), all) != -1)
		$(this).hide();
	    else
		$(this).show();
	});
}


function quickGroupLoad( ) {
    var groups = [];
    var lines = $('#quickGroupArea').val().split("\n");
    var nameFirst = $('#groupNameIsFirst').prop('checked');
    for( var i = 0; i < lines.length; i++ ) {
	if( nameFirst ) {
	    var m = lines[i].match(/\s*([\w-:]+)[,;\s]*(.*)/);
	    if( m )
		groups[i] = [ m[1], m[2] ];
	} else
	    groups[ i ] = [ "Group-" + (i+1), lines[i] ];
    }
    loadGroups( groups );
}
