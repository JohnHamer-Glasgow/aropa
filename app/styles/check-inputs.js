var saveAnyway = false;

function checkInputs() {
    if (saveAnyway)
	return true;
    tinyMCE.triggerSave();
    var okR = checkMissingRadioGroups();
    var okT = checkMissingTextareas();
    if (!okR || !okT) {
	$("#incomplete-dialog").modal('show');
	saveAnyway = true;
	return false;
    } else
	return true;
}

function checkMissingTextareas( ) {
    var ok = true;
    $("div .mce-tinymce").each(function(n, e) {
	e = $(e);
	var blank = e.find("iframe").contents().find('body').text().trim() == "";
        if (blank)
            ok = false;
	e.css('border', blank ? '2px solid red' : '')
    });
    return ok;
}

function checkMissingRadioGroups( ) {
    var rgs = {};
    $("form input[type='radio']").each(function(n, e) {
        if ($(e).prop('checked'))
	    rgs[e.name] = true;
	else if (rgs[e.name] == undefined)
	    rgs[e.name] = false;
    });
    var ok = true;
    $.each(rgs, function(key, value) {
	if (! value)
	    ok = false;
	var g = $("form input[type='radio'][name='" + key + "']");
	g.css('outline', value ? '' : '2px solid red');
	if (! value)
	    g.one('click', checkMissingRadioGroups);
    });
    return ok;
}
