jQuery.event.props.push('dataTransfer');
function insertImageFile( idx, file ) {
  if( file.type.match('^image/') ) {
    var fileReader = new FileReader();
    fileReader.onload = function(e) {
      tinyMCE.execCommand('mceInsertRawHTML', null, '<img src="' + e.target.result + '"/>');
    };
    fileReader.readAsDataURL(file);
  }
}

function enableImageInsertion( elt ) {
  elt.bind( 'dragenter dragover', function(e) {
      e.stopPropagation();
      e.preventDefault();
      return false;
    });
  elt.bind( 'drop', function( e ) {
      e.stopPropagation();
      e.preventDefault();
      if( e.dataTransfer && e.dataTransfer.files )
	$.each( e.dataTransfer.files, insertImageFile );
    });
  elt.bind( 'paste', function(e) {
      if( e.originalEvent && e.originalEvent.clipboardData && e.originalEvent.clipboardData.items )
	$.each( e.originalEvent.clipboardData.items, function(i,item) {
	    if( item.kind=='file' ) {
		insertImageFile(i, item.getAsFile());
		e.stopPropagation();
		e.preventDefault();
	    }
	  });
    });
}
