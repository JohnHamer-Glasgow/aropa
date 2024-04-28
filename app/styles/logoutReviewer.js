function doLogout( php_self ) {
  var xmlhttp;
  try {
    xmlhttp = new XMLHttpRequest();
  } catch (e) {
    try {
      xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
    } catch (e) {
      try {
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
      } catch (e) { }}}
  if( xmlhttp ) {
    xmlhttp.open( "GET",
                   php_self + "?action=logoutX",
                   false );
    xmlhttp.send( null );
    if( xmlhttp.status == 200 ) {
      var who = document.getElementById('whoami');
      if( who )
        who.innerHTML = xmlhttp.responseText;
    }
  }
}
