// Copy the grade from the first item into the remaining items for the same group
$(function() {
    var re = /^i\[(\w+)\]\[(\w+)\]$/;
    $('input[name^="i"]').change(function() {
	var m1 = re.exec( $(this).attr('name') );
	var grade = $(this).val( );
	if( m1 ) {
	    $('input[name^="i["]').each(function(i,inp) {
		var input = $(inp);
		var m2 = re.exec(input.attr('name'));
		if( m2 && parseInt(m2[1]) > parseInt(m1[1]) && m1[2] == m2[2]
		    && (input.val() == '' || input.hasClass('soft') )
		  ) {
		    input.val( grade );
		    input.addClass('soft');
		}
	    });
	}
	$(this).removeClass('soft');
    });
});
