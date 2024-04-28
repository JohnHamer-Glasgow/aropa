$(function() {
    $('.addAlloc').click( function() {
	var id = $(this).parents('tr').find('td:first').attr('id');
	var inp = $(document.createElement('input')).attr('type', 'text').attr('size', 6).attr('name', 'newAlloc[' + id + '][]');
	inp.addClass('form-control');
	inp.typeahead( {source: src} );
	$(this).before( inp );
    } );

    $('#allocs :input').typeahead( {source: src} );
});
