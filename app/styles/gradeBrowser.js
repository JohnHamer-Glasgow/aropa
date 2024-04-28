var _gradeData = null;
var _allocData = null;
var _reviewerData = null;

if( !Array.prototype.indexOf )
    Array.prototype.indexOf = function(obj, start) {
	for (var i = (start || 0), j = this.length; i < j; i++) {
            if (this[i] === obj) { return i; }
	}
	return -1;
    }

function arraySorter( a, b, key, order ) {
    var k1 = parseFloat( a[ key ] );
    var k2 = parseFloat( b[ key ] );
    if( isNaN( k1 ) || isNaN( k2 ) ) {
	k1 = a[ key ];
	k2 = b[ key ];
    }
    return k1 == k2 ? 0 : k1 > k2 ? order : - order;
}


var _gradeKey = 0;		// author field
var _gradeOrder = 1;
function sortGrades( field ) {
    if( field == _gradeKey )
	_gradeOrder *= -1;
    else
	_gradeOrder = 1;
    _gradeKey = field;

    loadGrades( );
}

function loadGrades( ) {
    _gradeData.sort( function (a, b) { return arraySorter(a, b, _gradeKey, _gradeOrder); } );
    
    var tbody = $('#authorTableBody');
    tbody.empty( );
    
    for( var i = 0; i < _gradeData.length; ++i ) {
	var tr = $(document.createElement('tr'));
	var author = _gradeData[i][0];
	var link = $(document.createElement('a')).text(author).click( showByAuthor );
	tr.append( $(document.createElement('td')).append(link) );
	for( var j = 1; j < 4; j++ )
	    $(document.createElement('td')).text( _gradeData[i][j] ).appendTo(tr);
	tbody.append( tr );
    }
}


var _reviewerKey = 0;		// reviewer field
var _reviewerOrder = 1;
function sortReviewers( field ) {
    if( field == _reviewerKey )
	_reviewerOrder *= -1;
    else
	_reviewerOrder = 1;
    _reviewerKey = field;

    loadReviewers( );
}

function haveField( f ) {
    for( var i = 0; i < _reviewerData.length-1; ++i )
	if( _reviewerData[i][f] != _reviewerData[i+1][f] )
	    return true;
    return false;
}

function haveWeights(  ) { return haveField( 1 ); }
function haveComments( ) { return haveField( 3 ); }
function makeVisible( field, vis ) {
    if( vis )
	field.show( );
    else
	field.hide( );
}

function loadReviewers( ) {
    var haveWs = haveWeights( );
    makeVisible( $('#colWeight'), haveWs );

    var haveCs = haveComments( );
    makeVisible( $('#colComments'), haveCs );
    
    _reviewerData.sort( function (a, b) { return arraySorter(a, b, _reviewerKey, _reviewerOrder); } );    
    
    var tbody = $('#reviewerTableBody');
    tbody.empty( );
    
    for( var i = 0; i < _reviewerData.length; ++i ) {
	var tr = $(document.createElement('tr'));
	var reviewer = _reviewerData[i][0];
	var link = $(document.createElement('a')).text( reviewer ).click( showByReviewer );
	tr.append( $(document.createElement('td')).append(link) );
	if( haveWs )
	    $(document.createElement('td')).text( _reviewerData[i][1] ).appendTo(tr);
	 $(document.createElement('td')).text( _reviewerData[i][2] ).appendTo(tr);
	if( haveCs ) {
	 //   $(document.createElement('td')).text( _reviewerData[i][3] ).appendTo(tr);
	    var link2 = $(document.createElement('a')).text( _reviewerData[i][3] ).click( showCommentsByReviewer );
	    link2.attr('rID', _reviewerData[i][4]);
	    tr.append( $(document.createElement('td')).append(link2) );
	}
	tbody.append( tr );
    }
}


function showByAuthor( e ) {
    var author = e.target.innerHTML;
    var page = $('#authorDetailTemplate');
    $('#authorDetailTemplateName').text(author);
    var tbody = page.find('tbody');
    tbody.empty( );
    for( var i = 0; i < _allocData.length; ++i )
	if( _allocData[i][1] == author ) {
	    var reviewer = _allocData[i][2];
	    //- if the reviewer is on the blacklist, highlight this (use strikethru?)
	    var tr = $(document.createElement('tr'));
	    var link = $(document.createElement('a')).text( reviewer ).click( showByReviewer );
	    tr.append( $(document.createElement('td')).append(link) );
	    for( var j = 5; j < _allocData[i].length; ++j )
		$(document.createElement('td')).text( _allocData[i][j] ).appendTo( tr );

	    var link2 = $(document.createElement('a')).text( _allocData[i][3] ).click( showCommentsByAllocID );
	    link2.attr('allocID', _allocData[i][0] );
	    tr.append( $(document.createElement('td')).append(link2) );

	    var input = $(document.createElement('input')).attr('type', 'checkbox');
	    input.change( updateExclusionsByAuthor );
	    if( _allocData[i][4]==1 )
		input.attr( 'checked', true );
	    tr.append( $(document.createElement('td')).append(input) );
	    tbody.append( tr );
	}
    page.modal('show');
}

function showCommentsByAllocID( e ) {
    var allocID = $(e.target).attr('allocID');
    for( var i = 0; i < _allocData.length; i++ )
	if( _allocData[i][0] == allocID ) {
	    $('#commentDetailTemplateName').text(_allocData[i][2] + ' review of ' + _allocData[i][1] );
	    $('#commentDetailTemplateBody')
		.load( 'aropa.php?action=commentsByAlloc&assmtID=' + _assmtID + '&cid=' + _cID + '&allocID=' + allocID,
		       function( ) { $('#commentDetailTemplate').modal('show'); });
	    break;
	}
}

function showCommentsByReviewer( e ) {
    var link = $(e.target);
    var user = link.attr('rID');
    // The name of the reviewer is on the first column of the current row
    var reviewer = link.parent( ).closest('tr').find('td').first( ).text( );
    $('#commentDetailTemplateName').text('Reviews written by ' + reviewer );
    $('#commentDetailTemplateBody')
	.load( 'aropa.php?action=reviewerFeedback&assmtID=' + _assmtID + '&cid=' + _cID + '&user=' + user,
	       function( ) { $('#commentDetailTemplate').modal('show')} );
}

function updateExclusionsByReviewer( e ) {
    var exclude = e.target.checked;
    var reviewer = $('#reviewerDetailTemplateName').text();
    var author = e.target.parentNode.parentNode.firstChild.firstChild.innerHTML;
    for( var i = 0; i < _allocData.length; i++ )
	if( _allocData[i][1] == author && _allocData[i][2] == reviewer )
	    _allocData[i][4] = exclude ? 1 : 0;
    //- refresh any other tables that display this allocation
}

function updateExclusionsByAuthor( e ) {
    var exclude = e.target.checked;
    var author   = $('#authorDetailTemplateName').text();
    var reviewer = e.target.parentNode.parentNode.firstChild.firstChild.innerHTML;
    for( var i = 0; i < _allocData.length; i++ )
	if( _allocData[i][1] == author && _allocData[i][2] == reviewer )
	    _allocData[i][4] = exclude ? 1 : 0;
    //- refresh any other tables that display this allocation
}


function showByReviewer( e ) {
    var reviewer = e.target.innerHTML;
    page = $('#reviewerDetailTemplate');
    $('#reviewerDetailTemplateName').text( reviewer );
    var tbody = page.find('tbody');
    tbody.empty( );
    for( var i = 0; i < _allocData.length; ++i )
	if( _allocData[i][2] == reviewer ) {
	    var author =  _allocData[i][1];
	    var grade = jQuery.grep( _gradeData, function(a) {return a[0]==author;} );
	    if( grade.length == 0 )
		grade = [[]];
  	    var tr = $(document.createElement('tr'));
	    var link = $(document.createElement('a')).text( author ).click( showByAuthor );
	    tr.append( $(document.createElement('td')).append(link) );
	    // _allocData[i] is: [allocID, author, reviewer, comments, ignore, marks..., total]
	    // grade         is: [author, total, nReview, discr, mark...]
	    for( var j = 5; j < _allocData[i].length; ++j ) {
		var td = $(document.createElement('td')).text( _allocData[i][j] );
		if( j < _allocData[i].length - 1 )
		    td.prepend( $(document.createElement('span')).text( "[" + grade[0][j-1] + "]" ).addClass('reviewingTarget') );
		tr.append( td );
	    }
	    var link2 = $(document.createElement('a')).text( _allocData[i][3] ).click( showCommentsByAllocID );
	    link2.attr('allocID', _allocData[i][0] );
	    tr.append( $(document.createElement('td')).append(link2) );

	    var input = $(document.createElement('input')).attr('type', 'checkbox');
	    input.change( updateExclusionsByReviewer );
	    if( _allocData[i][4] == 1 )
		input.attr('checked', true );
	    $(document.createElement('td')).html( input ).appendTo( tr );
	    tbody.append( tr );
	}
    page.modal('show');
}


var _currentExcl  = Array( );
function recalcGrades( ) {
    var excl = [];
    var incl = [];
    for( var i = 0; i < _allocData.length; ++i ) {
	var allocID = _allocData[i][0];
	var idx = _currentExcl.indexOf( allocID );
	if( _allocData[i][4] == 1 && idx == -1 ) {
	    excl.push( allocID );
	    _currentExcl.push( allocID );
	} else if( _allocData[i][4] == 0 && idx != -1 ) {
	    incl.push( allocID );
	    _currentExcl.splice( idx, 1 );
	}
    }
    
    var args = {action: 'jsonGrades',
		assmtID: _assmtID,
		cid: _cID,
		excl: excl.join(','),
		incl: incl.join(','),
		tag: $("input[name=tagSelect]:checked").val(),
		weightMethod: $("input[name=weightMethod]:checked").val(),
		avgMethod: $("input[name=avgMethod]:checked").val()};
    
    $("#working").modal();
    $.ajax( {url: 'aropa.php',
	     data: args,
	     type: 'POST',
	     async: true,
	     dataType: 'json'
	    }).done( function(grades) {
		_gradeData = grades[0];
		for( var i = 0; i < _reviewerData.length; i++ ) {
		    var wt = grades[1][ _reviewerData[i][0] ];
		    if( wt == undefined ) wt = 1;
		    _reviewerData[i][1] = wt;
		}
		loadGrades( );
		loadReviewers( );
		$("#working").modal('hide');
	    });
}
