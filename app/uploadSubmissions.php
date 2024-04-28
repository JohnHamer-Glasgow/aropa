<?php
/*
    Copyright (C) 2016 John Hamer <J.Hamer@acm.org>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

require 'Util.php';

function reviewUploads( ) {
  list( $cid, $aID ) = checkREQUEST( '_cid', '_aID' );

  if( isset( $_SESSION['availableAssignments'][ $aID ] ) )
    $assmt = $_SESSION['availableAssignments'][ $aID ];

  if( ! isset( $assmt ) )
    return warning( _('You do not have access to that assignment') );

  $assmtID = (int)$assmt['assmtID'];
  $author = current( $assmt['author-group-members'] ); //- i.e. first

  $authorOrGroupName = userIdentity( $author, $assmt, 'author' );

  require_once 'download.php';
  return HTML(HTML::h1(Sprintf_('Documents uploaded for %s by %s', $assmt['aname'], $authorOrGroupName)),
	      downloadables($assmt['author-group'], $authorOrGroupName, -1, $assmt, $cid),
	      BackButton());
}


function upload( ) {
  list( $cid, $aID ) = checkREQUEST( '_cid', '_aID' );

  require_once 'download.php';
  ensureDBconnected( 'upload' );

  if( isset( $_SESSION['availableAssignments'][ $aID ] ) )
    $assmt = $_SESSION['availableAssignments'][ $aID ];

  global $gHaveInstructor;

  if( ! isset( $assmt ) )
    return warning( _('You do not have access to that assignment') );

  $warning = '';
  if( ! nowBetween( null, $assmt['submissionEnd'] ))
    if( $gHaveInstructor )
      $warning = message( _('You are uploading a submission outside the normal submission period') );
    else
      return warning( _('That assignment is not available for uploading documents'));
  
  $assmtID = (int)$assmt['assmtID'];
  $author = $_SESSION['userID'];

  $selectedTag = '';
  $existing = array( );
  if ($assmt['authorsAre'] == 'group')
    $rs = checked_mysql_query('SELECT * FROM Essay'
			      . " WHERE assmtID = $assmtID"
			      . " AND author in (" . join(',', $assmt['author-group-members']) . ")"
			      . ' AND whenUploaded IS NOT NULL');
  else
    $rs = checked_mysql_query('SELECT * FROM Essay'
			      . " WHERE assmtID = $assmtID"
			      . " AND author = $author"
			      . ' AND whenUploaded IS NOT NULL');
  while( $row = $rs->fetch_assoc() ) {
    $existing[ (int)$row['reqIndex'] ] = $row;
    recordDownloadInSession( array( 'essay'   =>$row['essayID'],
                                    'assmtID' =>$assmtID,
				    'url'     =>$row['url'],
                                    'author'  =>$row['author'],
				    'identity'=>$_SESSION['uident'],
                                    'name'    =>$row['description'],
                                    'extn'    =>$row['extn'],
                                    'allocIdx'=>-1,
                                    'anon'    =>false ));
    if( ! empty( $row['tag'] ) )
      $selectedTag = $row['tag'];
  }

  $tags = array( );
  if( strstr( $assmt['allocationType'], 'tag' ) !== false )
    foreach( preg_split("/[;,[:space:]]/", $assmt['tags'], -1, PREG_SPLIT_NO_EMPTY) as $tag )
      $tags[ trim($tag) ] = true;

  $tagSelector = ! empty($tags)
    ? HTML( _('Select a tag for your assignment: '),
	    makeTagSelect( array_keys($tags), $selectedTag ),
	    Javascript('function validate(f) {
if(f.tag.selectedIndex==0) {
  f.tag.focus();
  alert("Please select a tag");
  return false;
}
return checkSize(f);
}'),
	    HTML::br())
    : Javascript('function validate(f){ return checkSize(f); }');

  $maxFileSize = memToBytes( ini_get('upload_max_filesize') );
  if( empty( $maxFileSize ) )
    $maxFileSize = 1e6;

  $h1 = _('Upload documents');

  $form = HTML::form( array('method' =>'post',
			    'enctype'=>'multipart/form-data',
			    'onsubmit'=>'return validate(this)',
			    'action' => "$_SERVER[PHP_SELF]?action=saveUploads&cid=$cid&aID=$aID" ),
		      HiddenInputs( array('MAX_FILE_SIZE'=>$maxFileSize )),
		      Javascript('function checkSize(f) {
if( f.getElementsByTagName ) {
  var inputs = f.getElementsByTagName("input");
  for( var i = 0; i < inputs.length; i++ )
        if( inputs[i].type == "file" && inputs[i].files )
           for( var j = 0; j < inputs[i].files.length; j++ )
             if( inputs[i].files[j].size > f.MAX_FILE_SIZE.value ) {
               alert("Uploaded files must be less than " + (f.MAX_FILE_SIZE.value/1024/1024) + "Mb");
               return false;
           }
}
return true;
}'),
		      $tagSelector);
  $reqIndex = 1;
  foreach( preg_split( "/\n/", $assmt['submissionRequirements'], -1, PREG_SPLIT_NO_EMPTY ) as $requireStr ) {
    if( isset( $existing[ $reqIndex ] ) )
      $existingSubmission = $existing[ $reqIndex ];
    else
      unset( $existingSubmission );

    //- $requireStr is expected to look like:
    //-    [file | url | inline] ","  [require | optional | oneof] "," ...urlencode'd additional key=value requirements...
    list($type, $required, $argStr) = explode( ",", $requireStr );
    parse_str($argStr ?? '', $args);
    switch( $type ) {
    case 'file':
      $defaultPrompt = _('Select a file to upload.');
      if( $args['extn'] == 'other' )
	$defaultPrompt = Sprintf_('Select a file of type <q>%s</q> to upload.', $args['other'] );
      else if( $args['extn'] != 'any' ) {
	global $gStandardFileTypes;
	foreach( $gStandardFileTypes as $desc => $extn )
	  if( $args['extn'] == $extn ) {
	    $defaultPrompt = Sprintf_('Select a <q>%s</q> file to upload.', $desc );
	    break;
	  }
      }
      
      $regexp = makeJavascriptRegexp( $args['extn'], $args['other'] );
      $attrs = array( 'type'=>'file',
		      'name'=>"file[$reqIndex]",
		      'onchange'=>"if(!$regexp.test(this.value))alert('" . _('This does not appear to be the correct type of file.  Please check carefully.') . "')" );
      if( isset( $args['mimetype'] ) )
	$attrs['accept'] = $args['mimetype'];
      $upload = HTML::input( $attrs );
      if( isset( $existingSubmission ) && ! $existingSubmission['isPlaceholder'] )
	$upload = HTML( $upload, HTML::br( ),
			Sprintf_('You have previously uploaded <q>%s</q>. ', $existingSubmission[ 'description' ]),
			Button( 'Click here to check this submission.',
				"download&essay=" . $existingSubmission['essayID'] . "&cid=$cid"));
      break;

    case 'url':
      $defaultPrompt = _('Enter the URL for your submission');
      $value =  $existingSubmission['url'] ? $existingSubmission['url'] : 'http://';
      $upload = HTML::input( array('type'=>'text', 'size'=>80, 'name'=>"url[$reqIndex]", 'value'=>$value ));
      break;

    case 'text':
      $h1 = Sprintf_('Write your submission for %s in the editor below', $assmt['aname'] );
      $defaultPrompt = _('Type or copy-and-paste in your submission');
      $rows = max( 30, (int)$args['rows'] );
      $cols = max( 80, (int)$args['cols'] );
      $text = isset( $existingSubmission )
	&& ! $existingSubmission['isPlaceholder']
	&& $existingSubmission['extn'] == 'inline-text'
	&& ! empty( $existingSubmission['essay']  )
	? $existingSubmission['essay']
	: '';
      if( ! empty($text) && $existingSubmission['compressed'] )
	$text = gzuncompress( $text );

      $upload = HTML(HTML::textarea( array('name'=>"text[$reqIndex]", 'rows'=>$rows, 'cols'=>$cols), $text ),
		     EnableTinyMCE('Upload'));
    }

    $prompt = isset( $args['prompt'] ) && $args['prompt'] != '(default)' ? $args['prompt'] : $defaultPrompt;
    if( $required == 'oneof' ) {
      $prompt = HTML( ! isset( $firstAlternative ) ? _('EITHER: ') : _('OR: '), $prompt );
      $firstAlternative = false;
    }
    $form->pushContent( HTML::h2( $prompt ), $upload );
    $reqIndex++;
  }

  $form->pushContent(HTML::br(),
		     HTML::br(),
		     ButtonToolbar(submitButton(_('Save')),
				   CancelButton()));

  require_once 'BlockParser.php';

  return HTML( HTML::h1( $h1 ),
	       $warning,
	       HTML::br( ),
	       HTML::div( array('id'=>'submission-text'), MaybeTransformText( $assmt['submissionText'] ) ),
	       HTML::br( ),
	       $form );
}

function makeJavascriptRegexp( $extn, $other ) {
  if( $extn == 'other' )
    $extn = $other;
  switch( $extn ) {
  case 'any':
    return "//";
  default:
    $pat = array( );
    foreach( explode(",", $extn ) as $e )
      $pat[] = "($e)";
    return "new RegExp('\\." . join('|', $pat) . "\$','i')";
  }
}


function makeTagSelect( $tags, $defaultTag ) {
  $select = HTML::select( array('name'=>'tag'),
			  HTML::option(array('selected'=>empty($defaultTag), 'value'=>''), '(Select a tag)') );
  foreach( $tags as $t )
    $select->pushContent( HTML::option(array('selected'=> strcasecmp($t, $defaultTag) == 0 ), $t) );
  return $select;
}

function saveUploads( ) {
  list( $aID, $cid ) = checkREQUEST( '_aID', '_cid' );

  $db = ensureDBconnected( 'saveUploads' );

  if( isset( $_SESSION['availableAssignments'][ $aID ] ) )
    $assmt = &$_SESSION['availableAssignments'][ $aID ];

  global $gHaveInstructor;

  if( ! $gHaveInstructor )
    if( ! isset( $assmt )
	||
	! nowBetween(null, $assmt['submissionEnd'])
	)
      return HTML( warning( _('Sorry, this assignment is not available for uploads') ),
		   BackButton());
  
  $assmtID = $assmt['assmtID'];

  $author = $_SESSION['userID'];

  // checked_mysql_query( 'SET max_allowed_packet=2M' );
  $maxFileSize = memToBytes( ini_get('upload_max_filesize') );
  if( empty( $maxFileSize ) )
    $maxFileSize = 1e6;

  $upload = array( );
  if( isset( $_FILES['file']['error'] ) )
    foreach( $_FILES['file']['error'] as $reqIndex => $error ) {
      $tmp = $_FILES['file']['tmp_name'][ $reqIndex ];
      if( ! $tmp )
	continue;
      $info = pathinfo( $_FILES['file']['name'][ $reqIndex ] );

      switch( $error ) {
      case UPLOAD_ERR_OK:
	if( ! is_uploaded_file( $tmp ) )
	  securityAlert( 'possible upload attack' );
	list( $isCompressed, $essay ) = maybeCompress( file_get_contents( $tmp ) );
	$upload[ (int)$reqIndex ] = array( 'essay'       => $essay,
					   'compressed'  => $isCompressed,
					   'description' => $info['basename'],
					   'extn'        => $_FILES['file']['type'][ $reqIndex ] ? $_FILES['file']['type'][ $reqIndex ] : $info['extension'] );
	break;
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
	addWarningMessage( Sprintf_('The file <q>%s</q> could not be uploaded because it exceeds the maximum permitted size (%dMb).', $info['basename'], $maxFileSize/1024/1024));
	break;

      case UPLOAD_ERR_PARTIAL:
	addWarningMessage( Sprintf_('The file <q>%s</q> was only partially uploaded (perhaps you cancelled it after starting the upload?).', $info['basename']));
	break;

      case UPLOAD_ERR_NO_FILE:
	addWarningMessage( _('The file <q>%s</q> was not uploaded', $info['basename']) );
	break;
	
      case UPLOAD_ERR_EXTENSION:
      case UPLOAD_ERR_NO_TMP_DIR:
      case UPLOAD_ERR_CANT_WRITE:
	addWarningMessage( _('The file <q>%s</q> could not be uploaded (error code #%d).', $info['basename'], $error) );
	break;
      }
    }

  if( isset( $_REQUEST['url'] ) )
    foreach( $_REQUEST['url'] as $reqIndex => $url )
      $upload[ (int)$reqIndex ] = array('url'=>$url,
					'description'=>$url);

  if( isset( $_REQUEST['text'] ) )
    foreach( $_REQUEST['text'] as $reqIndex => $text ) {
      $words = str_word_count( preg_replace( '/&\w+;/', '', str_replace( '&nbsp;', ' ', strip_tags($text) )));
      if( $words > 0 ) {
	list( $isCompressed, $essay ) = maybeCompress( $text );
	$upload[(int)$reqIndex] = array('essay'=> $essay,
					'compressed'=>$isCompressed,
					//- Description needs to be a string, so can't use Sprintf_ here
					'description'=>sprintf(ngettext('One word', '%d words', $words), $words),
					'extn'=>'inline-text');
      }
    }


  $tag = trim( $_REQUEST['tag'] );
  if( stripos( $assmt['allocationType'], 'tag' ) !== false ) {
    // The tag must be in $assmt['tags']
    $assmtTags = preg_split("/[,;[:space:]]+/", $assmt['tags'], -1, PREG_SPLIT_NO_EMPTY);
    foreach( $assmtTags as $t )
      if( strcasecmp( $tag, $t ) == 0 ) {
	$checkedTag = $t;
	break;
      }
    if( ! isset( $checkedTag ) && count( $assmtTags ) > 0 ) {
      addPendingMessage( _('You did not select a tag for your submission.  Please do the upload again, and select the appropriate tag.') );
      $checkedTag = 'MISSING'; //$assmtTags[0];
    }

    if( $checkedTag != 'MISSING' || ! empty( $upload ) )
      // Change any existing uploads to the specified tag type.
      checked_mysql_query( 'UPDATE Essay SET tag = ' . quote_smart($checkedTag)
			   . " WHERE assmtID = $assmtID"
			   . " AND author = $author" );
  }

  if( empty( $upload ) )
    return HTML( warning( _('No documents were uploaded!'),
			  HTML::p( _('Possible reasons include:') ),
			  HTML::ul( HTML::li( _('you pressed Save before selecting any files;')),
				    HTML::li( _('the file name was incorrectly typed;')),
				    HTML::li( Sprintf_('the file size exceeds the maximum permitted (%dMb);', $maxFileSize/1024/1024)),
				    HTML::li( _('you did not type or paste anything into the editor.'))),
			  HTML::p( _('Please try again.'))),
		 upload( ) );

  checked_mysql_query( "DELETE FROM Essay WHERE assmtID = $assmtID"
		       . ' AND author in (' . join(',', $assmt['author-group-members']) . ')'
		       . ' AND (isPlaceholder = 1 OR reqIndex IN (' . join(',', array_keys( $upload )) . '))' );
  $std = array( 'assmtID'      => $assmtID,
		'author'       => $author,
		'isPlaceholder'=>0);
  if( isset( $checkedTag ) )
    $std['tag'] = $checkedTag;

  //- Many MySQL servers are configured with a max_packet_size of 1Mb.
  //- We need to allow larger files that that, hence the Overflow table.
  $MAX_CHUNK = 600*1024; //- Allow (ample) room for quote expansion
  foreach( $upload as $reqIndex => $u ) {
    if( strlen( $u['essay'] ) > $MAX_CHUNK ) {
      $overflow = substr( $u['essay'], $MAX_CHUNK );
      $u['essay'] = substr( $u['essay'], 0, $MAX_CHUNK );
      $u['overflow'] = true;
    } else
      $overflow = '';
    checked_mysql_query(makeInsertQuery('Essay',
					array_merge($std, $u, array('reqIndex'=>$reqIndex)),
					array('whenUploaded'=>'now()')));
    $essayID = $db->insert_id;
    for( $block = 0; strlen( $overflow ) > $block; $block += $MAX_CHUNK )
      checked_mysql_query( "INSERT INTO Overflow (essayID, data) VALUES ($essayID, " . quote_smart(substr($overflow, $block, $MAX_CHUNK)) . ")");
    
    /*
    if( isset( $checkedTag ) )
      addPendingMessage( Sprintf_('Uploaded <q>%s</q> with the tag <q>%s</q>', gettext($u['description']), $checkedTag ));
    else
      addPendingMessage( Sprintf_('Uploaded <q>%s</q>', gettext($u['description']) ));
    // *** Provide a receipt code, for the student to use as proof of submission
    */
  }
    
  require_once  'monitor-reviewing.php'; // for makeExtnPREG

  // Check submission requirements have been met
  $rs = checked_mysql_query( 'SELECT essayID, reqIndex, description, whenUploaded FROM Essay'
			     . " WHERE assmtID = $assmtID AND author = $author"
			     . ' AND IFNULL(isPlaceholder,0)<>1 AND reqIndex IS NOT NULL');
  $essays = array( );
  $assmt['uploaded-essays'] = array( );
  while( $row = $rs->fetch_assoc() ) {
    $essays[ $row['reqIndex'] ] = $row['description'];
    $assmt['uploaded-essays'][] = $row;
  }
  
  if( empty( $essays ) )
    addWarningMessage( _('You have not uploaded any documents') );
  else {
    $reqIndex = 1;
    foreach( preg_split( "/\n/", $assmt['submissionRequirements'], -1, PREG_SPLIT_NO_EMPTY ) as $requireStr ) {
      //- $requireStr is expected to look like:
      //-    [file | url | inline] ","  [require | optional] "," ...urlencode'd additional key=value requirements...
      list($type, $required, $argStr) = explode( ",", $requireStr );
      parse_str($argStr ?? '', $args);
      if( ! isset( $essays[ $reqIndex ] ) ) {
	if( $required == 'require' )
	  addWarningMessage( Sprintf_('You have not uploaded a document for requirement #%d: <q>%s</q>', $args['prompt'] ));
      } else {
	$desc = $essays[ $reqIndex ];
	if( $type == 'file' && preg_match( makeExtnPREG( $args['extn'], $args['other'] ), $desc ) <= 0 )
	  addWarningMessage( Sprintf_('The uploaded file <q>%s</q> does not match the expected file type', $desc));
      }
      $reqIndex++;
    }
  }
 
  if( $gHaveInstructor )
    redirect( 'viewAsst', "cid=$cid&assmtID=$assmtID" );
  else
    redirect( 'viewStudentAsst', "cid=$cid&aID=$aID" );
}

function deleteUpload( ) {
  list( $essayID, $aID, $cid ) = checkREQUEST( '_id', '_aID', '_cid' );

  ensureDBconnected( 'deleteUpload' );

  if( isset( $_SESSION['availableAssignments'][ $aID ] ))
    $assmt = $a;

  if( ! isset( $assmt ) || ! nowBetween(null, $assmt['submissionEnd'] ))
    return HTML( warning( _('You cannot delete that document')), upload( ));

  $assmtID = $assmt['assmtID'];

  if( ! isset( $_REQUEST['confirmed'] ) ) {
    $desc = fetchOne( 'SELECT description FROM Essay'
		      . " WHERE id = $essayID",
		      'description' );
    return HTML( HTML::h1( Sprintf_('Confirm deletion of <q>%s</q>', $desc )),
                 formButton( _('Confirm'), "deleteUpload&id=$essayID&confirmed&cid=$cid&aID=$aID"),
		 formButton( _('Cancel'), "viewStudentAsst&cid=$cid&aID=$aID"));
  } else {
    checked_mysql_query( "DELETE FROM Essay WHERE id = $essayID" );
    redirect( 'upload', "assmtID=$assmtID&cid=$cid" );
  }
}
