$(function() {
    $('select[name^="fileExtn"]').each( function( i, e ) {
	showHideOther($(e));
    } );
    $('select[name^="fileExtn"]').change( function( ) {
	showHideOther($(this));
    } );

    $("#file-group").find(":input").attr('disabled', $("#editorOnly").prop('checked'));
    $('#submission-div :radio').change( function( ) {
	$("#file-group").find(':input').attr('disabled', $("#editorOnly").prop('checked'));
    } );
});

function showHideOther(el) {
    var other = $('#' + el.attr('name')
		  .replace('Extn', 'Other')
		  .replace('[', '\\[')
		  .replace(']', '\\]'));
    if( el.val() == 'other' )
	other.show( );
    else
	other.hide( );
}

function addFileEntry( ) {
    var fileNo = $('#file-ol li').length;
    var li = $('#file-ol :first').clone(true);
    var remove = $('<div class="col-sm-1"><button type="button" class="btn btn-default" aria-label="Remove">' +
		   '<span class="glyphicon glyphicon-trash" aria-hidden="true"></span>' +
		   '</button></div>');
    remove.find("button").click(function(){li.remove();});
    li.find('.form-group')
	.append(remove);
    li.appendTo('#file-ol')
	.find(':input, :hidden, label')
	.each(function() {
	    if (this.nodeName == 'LABEL')
		Rename(this, 'for', fileNo);
	    Rename(this, 'name', fileNo);
	    Rename(this, 'id', fileNo);
	    if (this.nodeName == 'SELECT')
		$(this).change( function() { showHideOther($(this)); });
	});
}

var nameRegexp = new RegExp( /^(\w*)\[(\d+)\]$/ );
function Rename(el, attr, seq) {
    var ma = nameRegexp.exec($(el).attr(attr));
    if (ma != null)
	$(el).attr(attr, ma[1] + "[" + seq + "]");
}
