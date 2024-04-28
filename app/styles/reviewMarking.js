function reviewersAreChanged() {
    switch ($("#reviewersAre input:checked").val()) {
    case 'submit':
    case 'all':
	$('#student-review-markers').show(500);
	$('#marking-team').hide(500);
	break;
    case 'other':
	$('#student-review-markers').hide(500);
	$('#marking-team').show(500);
    }
}

function authorsAreChanged() {
    $('#per-review').attr('disabled', $('#per-reviewer:checked').val());
    $('#per-reviewer').attr('disabled', $('#per-review:checked').val());
}
