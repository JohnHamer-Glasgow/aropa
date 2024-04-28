function reviewersAreChanged(delay = 500) {
    var reviewersAre = $('input[name=reviewersAre]:checked').val();
    $('#studentAuthorsAre input').prop('disabled', reviewersAre == 'other');
    $('#markingTeam input').prop('disabled', reviewersAre != 'other');
    switch (reviewersAre) {
    case 'submit':
    case 'all':
	$('#studentAuthorsAre').show(delay);
	$('#markingTeam').hide(delay);
	break;
    case 'other':
	$('#studentAuthorsAre').hide(delay);
	$('#markingTeam').show(delay);
    }
}

function authorsAreChanged() {
    var authorsAre = $('#studentAuthorsAre input:checked').val();
    $('#per-review').prop('disabled', authorsAre != 'review');
    $('#per-reviewer').prop('disabled', authorsAre != 'reviewer');
}
