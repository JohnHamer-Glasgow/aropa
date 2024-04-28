function toggleDisplay( item ) {
    var r = document.getElementById( "replace_" + item );
    var f = document.getElementById( "file_" + item );
    if( r ) r.setAttribute("style", "display: none" );
    if( f ) f.setAttribute("style", "display: inline" );
}