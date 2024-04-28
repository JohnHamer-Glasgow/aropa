function popupViewRubric( php_self ) {
  var sel = document.getElementById('rubricSelection');
  if( sel && sel.value > 0 )
    window.open( php_self + '?action=showRubric&rubric=' + sel.value );
}

var _xmlhttp;
try {
  _xmlhttp = new XMLHttpRequest();
} catch (e) {
  try {
    _xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
  } catch (e) {
    try {
      _xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    } catch (e) { }}}

function useRubric( php_self ) {
    if( ! _xmlhttp )
	return;

    var rub = document.getElementById('rubric');
    var cid = document.getElementById('cid');
    var sel = document.getElementById('rubricSelection');
    if( rub && cid && sel ) {

      if( tinyMCE && tinyMCE.activeEditor.isDirty( ) && ! confirm('Replace your current rubric?') )
	return;

      _xmlhttp.open( "GET",
		     php_self + "?action=rawRubric&rubricID=" + sel.value + '&cid=' + cid.value,
		     false );
      _xmlhttp.send( null );
      
      if( _xmlhttp.status == 200 ) {
	  if( tinyMCE )
	      tinyMCE.activeEditor.setContent( _xmlhttp.responseText );
	  else {
	      rub.innerHTML = _xmlhttp.responseText;
	  }
	  if( tinyMCE ) tinyMCE.activeEditor.isNotDirty = true;
	  var rubricID = document.getElementById( 'rubricID' );
	  if( rubricID )
	      rubricID.value = sel.value;
      }
    }
}

function identifyButtonGroups(editor) {
    var succ = function(node) {
	if (! node) return null;
	
	if (node.firstChild)
	    return node.firstChild;
	
	if (node.nextSibling)
	    return node.nextSibling;
	
	do {
	    node = node.parentNode;
	} while (node && ! node.nextSibling);
	if (node)
	    return node.nextSibling;
	else
	    return null;
    };
    
    var seenRadio = false;
    var colour = 'green';
    for( var n = document.getElementById(editor.id + "_ifr"); n; n = succ(n) ) {
	if (n.contentDocument)
	    n = n.contentDocument.body;
	else if (n.contentWindow)
	    n = n.contentWindow.document.body;
	else
	switch (n.nodeName) {
	case 'INPUT':
	    if (n.type == 'radio') {
		if (! seenRadio) {
		    colour = colour == 'green' ? 'blue' : 'green';
		    seenRadio = true;
		}
		tinymce.DOM.setStyle( n, 'outline', '2px dotted ' + colour);
		if (n.parentNode.nodeName == 'SPAN' || n.parentNode.nodeName == 'P')
		    //- The rubric editor used to add a coloured span (or p) around each radio button, which we remove here
		    n.parentNode.removeAttribute('style');
	    }
	    break;
	case 'H1': case 'H2': case 'H3': case 'H4':
	case 'H5': case 'H6': case 'HR':
	    seenRadio = false;
	    break;
	case 'IMG':
	    if (n.getAttribute("src").indexOf("textBlock.jpg" ) != -1)
		seenRadio = false;
	}
    }
};

function identifyButtonGroupsRefresh(editor) {
    identifyButtonGroups(editor);
    setTimeout(function(){identifyButtonGroupsRefresh(editor);}, 2000);
}
