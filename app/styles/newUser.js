$(function() {
    var name = $("#name"),
    email = $("#email"),
    password = $("#password"),
    allFields = $([]).add(name).add(email).add(password),
    tips = $(".validateTips");

    function updateTips(t) {
	tips.text(t).addClass('ui-state-highlight');
	setTimeout(function() { tips.removeClass('ui-state-highlight', 1500); }, 500);
    }
    
    function checkLength(o,n,min,max) {	
	if( o.val().length > max || o.val().length < min ) {
	    o.addClass('ui-state-error');
	    updateTips("Length of " + n + " must be between "+min+" and "+max+".");
	    return false;
	} else
	    return true;
    }
    
    function checkRegexp(o,regexp,n) {	
	if( ! regexp.test( o.val() ) ) {
	    o.addClass('ui-state-error');
	    updateTips( n );
	    return false;
	} else
	    return true;
    }
    
    $("#dialog-form").dialog({
	autoOpen: false,
	height: 300,
	width: 350,
	modal: true,
	buttons: {
	    'Create a new user': function() {
		var bValid = true;
		allFields.removeClass('ui-state-error');
		
		bValid = bValid && checkLength(uident, "uident",0,80);
		bValid = bValid && checkLength(email, "email",0,80);

		bValid = bValid && checkRegexp(name,/^([0-9a-z_@])+$/i, "Username may consist of a-z, 0-9, underscores, @");
		// From jquery.validate.js (by joern), contributed by Scott Gonzalez: http://projects.scottsplayground.com/email_address_validation/
		bValid = bValid && checkRegexp(email,/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i, "Please enter a valid email address");
		bValid = bValid && checkRegexp(password,/^([0-9a-zA-Z])+$/, "Passwords must be letters and digits only");
		
		if (bValid) {
		    $('#users tbody').append('<tr>' +
					     '<td>' + name.val() + '</td>' + 
					     '<td>' + email.val() + '</td>' + 
					     '<td>' + password.val() + '</td>' +
					     '</tr>'); 
		    $(this).dialog('close');
		}
	    },
	    Cancel: function() {
		$(this).dialog('close');
	    }
	},
	close: function() {
	    allFields.val('').removeClass('ui-state-error');
	}
    });
    
    
		
    $('#create-user')
	.button()
	.click(function() {
	    $('#dialog-form').dialog('open');
	});
    
});
