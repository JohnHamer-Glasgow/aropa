<?php
require_once 'Encoding.php';
require_once 'Allocations.php';

function importClass( ) {
  extraHeader( '$("#cname").change( function( ) {
$.get("' . "$_SERVER[PHP_SELF]?action=checkImportClassName" . "\", { cname: this.value },"
	       . 'function(data) {
 if($.parseJSON(data))
    $("#inUse").show();
 else
    $("#inUse").hide();
})})', 'onload' );

  extraHeader( '$("#orig").change( function() { $("#prefix").attr("disabled", $(this).is(":checked"));})', 'onload');

  return HTML(HTML::h1('Import class from XML'),
	      HTML::form(array('method'=>'post',
			       'enctype'=>'multipart/form-data',
			       'action'=>"$_SERVER[PHP_SELF]?action=doImportClass"),
			 FormGroup('cname',
				   _('Name for the new class (leave blank to keep the original name)'),
				   HTML::input(array('type'=>'text',
						     'id'=>'cname')),
				   _('Class name')),
			 HTML::div(array('id'=>'inUse', 'style'=>'display:none'),
				   warning(_('This will merge the import with an existing class.'))),
			 HTML::div(array('class'=>'checkbox'),
				   HTML::label(HTML::input(array('type'=>'checkbox',
								 'name'=>'G')),
					       _('Import groups')),
				   HTML::label(HTML::input(array('type'=>'checkbox',
								 'name'=>'X')),
					       _('Import extensions')),
				   HTML::label(HTML::input(array('type'=>'checkbox',
								 'name'=>'E')),
					       _('Import submissions')),
				   HTML::label(HTML::input(array('type'=>'checkbox',
								 'name'=>'A')),
					       _('Import allocations')),
				   HTML::h3(_('User names')),
				   HTML::label(HTML::input(array('type'=>'checkbox',
								 'id'=>'orig',
								 'name'=>'orig')),
					       _('Keep original user names')),
				   HTML::br(),
				   _('OR'),
				   HTML::br(),
				   HTML::label(_('Generate new user names with this prefix and a numeric suffix'),
					       HTML::input(array('type'=>'text',
								 'id'=>'prefix',
								 'name'=>'prefix')))),
			 HTML::label(array('for'=>'file'),
				     _('Select the class XML file to import: ')),
			 HTML::input(array('type'=>'file', 'name'=>'file')),
			 ButtonToolbar(submitButton( _('Import')),
				       formButton(_('Cancel'), "home"))));
}

function checkImportClassName( ) {
  if( empty( $_REQUEST['cname'] ) )
    $inuse = false;
  else
    $inuse = fetchOne( 'SELECT cname FROM Course'
		       . ' WHERE instID=' . (int)$_SESSION['instID']
		       . ' AND cname = ' . quote_smart( $_REQUEST['cname'] ));
  echo json_encode( $inuse );
  exit;
}

class UserIdMap {
  var $_IdToIdent;
  var $_IdentToId;
  var $_originalUid;
  var $_prefix;
  var $_offset;
  var $_instId = null;
  function __construct( $prefix, $instID = null ) {
    require_once 'users.php';

    if( ! $instID )  $instID = $_SESSION['instID'];
    $this->_prefix = $prefix;
    if( $prefix )
      // Ensure we generate new user names that don't clash with any
      // existing ones. Take the largest number that matches the user name
      // pattern, and generate new user names with larger numeric suffixes.
      $this->_offset = intval( preg_replace( '/.*\D(\d+)$/', '$1',
					     fetchOne( "SELECT MAX(uident) AS u FROM User WHERE instID=$instID AND uident RLIKE "
						       . quote_smart('^' . preg_quote($prefix) . '[[:digit:]]+$'), 'u' ) ));


    $this->_instId = $instID;
    $this->_IdToIdent = array( );
    $this->_IdentToId = array( );
    $this->_uidMap = array( );
    $this->_reverseUidMap = array( );
  }

  function effectiveUident( $uid ) {
    if( ! isset( $this->_uidMap[ $uid ] ) ) {
      if( $this->_prefix )
	$uident = sprintf( $this->_prefix."%02d", count( $this->_uidMap ) + $this->_offset + 1 );
      else
	$uident = $uid;
      $this->_uidMap[ $uid ] = $uident;
      $this->_reverseUidMap[ $uident ] = $uid;
    }
    return $this->_uidMap[ $uid ];
  }

  function originalUident( $uid ) {
    return $this->_reverseUidMap[ $uid ];
  }

  function getUserId( $uid ) {
    $uident = $this->effectiveUident( $uid );
    if( ! isset( $this->_IdentToId[ $uident ] ) ) {
      $userId = uidentToUserID( $uident, $this->_instID );
      $this->_IdentToId[ $uident ] = $userId;
    }
    return $this->_IdentToId[ $uident ];
  }

  function getUserIds( $uidents, $usernames = array( ) ) {
    $unknown = array( );
    $origMap = array( );
    foreach( $uidents as $uid ) {
      $uident = $this->effectiveUident( $uid );
      if( ! isset( $this->_IdentToId[ $uident ] ) ) {
	$unknown[] = $uident;
      }
    }
    foreach( identitiesAndUsernamesToUserIDs( $unknown, $usernames, $this->_instId ) as $uident => $userId )
      $this->_IdentToId[ $uident ] = $userId;
    
    $userIds = array( );
    foreach( $uidents as $uid ) {
      $uident = $this->effectiveUident( $uid );
      $userIds[ $uident ] = $this->_IdentToId[ $uident ];
    }
    return $userIds;
  }
}


function doImportClass( ) {
  list( $cname, $orig, $prefix, $importAllocations, $importEssays, $importGroups, $importExtensions )
    = checkREQUEST( '?cname', '?orig', '?prefix', '?A', '?E', '?G', '?X' );
 
  $error = $_FILES['file']['error'];
  $tmp = $_FILES['file']['tmp_name'];
  if( $error != UPLOAD_ERR_OK || ! $tmp || !is_uploaded_file( $tmp ) )
    return HTML( warning(_('No XML file was uploaded')),
		 importClass( ) );
  
  set_time_limit( 0 );
  ini_set( 'memory_limit', '64M' );
  libxml_use_internal_errors(true);
  $xml = new SimpleXMLElement( file_get_contents( $tmp ), LIBXML_NOCDATA );
  if( ! $xml ) {
    $warn = HTML::ul();
    foreach( libxml_get_errors( ) as $error )
      $warn->pushContent( HTML::li($error->message) );
    return HTML( warning( _('Failed to load the XML file'), $warn ),
		 importClass( ) );
  }
  if( ! $xml->name )
    return HTML( warning( _('That class cannot be imported as the XML is not in the correct format.') ),
		 importClass( ) );

  if( trim($cname) == '' ) $cname = "".$xml->name;

  $userIdMap = new UserIdMap( $orig=='on' ? '' : $prefix );

  $db = ensureDBconnected('doImportClass');

  $classId = fetchOne("SELECT courseID FROM Course WHERE instID=$_SESSION[instID] AND cname="
		      . quote_smart($cname), 'courseID');
  if( ! $classId ) {
    $data = array('instID'=>$_SESSION['instID'],
		  'cname' =>$cname,
		  'cident'=>"".$xml->accessCode);
    if( $xml->owner )
      $data['cuserID'] = $userIdMap->getUserId( $xml->owner );
    checked_mysql_query( makeInsertQuery('Course', $data ));
    $classId = $db->insert_id;
    loadClasses( true );
  }

  $users = array( );
  $usernames = array( );
  $emails = array( );
  foreach( $xml->users->user as $u ) {
    $attrs = $u->attributes();
    if( ! isset( $attrs->ident ) ) continue;
    $uid = "".$attrs->ident;
    $users[$uid] = getRole( "".$u->role );
    if( $orig == 'on' ) {
      if( $u->name ) $usernames[$uid] = "".$u->name;
      if( $u->email ) $emails[$uid] = "".$u->email;
    }
  }
  $userIds = $userIdMap->getUserIds( array_keys($users), $usernames );
  $values = array( );
  foreach( $userIds as $uid => $userId )
    $values[] = "($classId,$userId," . $users[$userIdMap->originalUident($uid)] . ")";

  checked_mysql_query( "INSERT IGNORE INTO UserCourse (courseId,userID,roles) VALUES " . join(",", $values));
  
  foreach( $xml->assignments->assignment as $assmt )
    importAssignment( $assmt, $classId, $userIdMap, $importAllocations, $importEssays, $importGroups, $importExtensions );

  addPendingMessage( _('Import succeeded') );

  $cid = classIDToCid( $classId );

  redirect( "selectClass&cid=$cid" );
}

function importAssignment( $assmt, $classId, $userIdMap, $importAllocations, $importEssays, $importGroups, $importExtensions, $isReviewsFor = null, $reviewIdMap = array() ) {
  $allocationType = empty($assmt->allocationType) ? null : (string)$assmt->allocationType;
  $data = array('aname'            => "".$assmt->name,
		'courseId'         => $classId,
		'isActive'         => isset( $assmt->isActive ),
		'whenCreated'      => $assmt->whenCreated,
		'selfReview'       => (bool)$assmt->selfReview,
		'submissionEnd'    => "".$assmt->submissionsDue,
		'reviewEnd'        => "".$assmt->reviewsDue,
		'allocationType'   => $allocationType,
		'authorsAre'       => "".$assmt->authorsAre,
		'reviewersAre'     => "".$assmt->reviewersAre,
		'anonymousReview'  => (bool)$assmt->anonymousReview,
		'showMarksInFeedback' => (bool)$assmt->showMarksInFeedback,
		'allowLocking'     => (bool)$assmt->allowLocking,
		'nPerReviewer'     => (int)$assmt->nPerReviewer,
		'restrictFeedback' => (bool)$assmt->restrictFeedback,
		'allocationsDone'  => $assmt->allocationsDone,
		'whenActivated'    => $assmt->whenActivated,
		'category'         => "".$assmt->category,
		'submissionText'   => "".$assmt->submission->instructions,
		'submissionRequirements' => getRequirements( $assmt->submission->requirements ));
  
  if( $isReviewsFor != null )
    $data['isReviewsFor'] = $isReviewsFor;

  if( ! empty( $assmt->tags ) )
    $data['tags'] = getSimpleItems( $assmt->tags, 'tag' );
  
  if( ! empty( $assmt->visibleReviewers ) )
    $data['visibleReviewers'] = getSimpleItems( $assmt->visibleReviewers, 'reviewer' );

  $db = ensureDBconnected('importAssignment');

  if( ! empty( $assmt->rubric ) ) {
    $rubric = $assmt->rubric;
    $rubricType = empty($rubric->rubricType) ? 'assignment' : "".$rubric->rubricType;
    $lastEdited = empty($rubric->lastEdited) ? null : $rubric->lastEdited;
    $sharing    = empty($rubric->sharing)    ? 'none' : "".$rubric->sharing;
    $rdata = array('rubricXML'   => "".$rubric->rubricXml,
		   'rname'       => "".$rubric->name,
		   'owner'       => $userIdMap->getUserId( "".$rubric->owner ),
		   'rubricType'  => $rubricType,
		   'createdDate' => $rubric->createdDate,
		   'lastEdited'  => $lastEdited,
		   'sharing'     => $sharing);
    checked_mysql_query( makeInsertQuery('Rubric', $rdata) );
    $data['rubricID']     = $db->insert_id;
    $data['markItems']    = getItemString( $assmt->rubric->markItems );
    $data['markLabels']   = getItemString( $assmt->rubric->markLabels );
    $data['commentItems'] = getItemString( $assmt->rubric->commentItems );
    $data['commentLabels'] = getItemString( $assmt->rubric->commentLabels );
    $data['nReviewFiles'] = (int) "".$assmt->rubric->nReviewFiles;
  }

  checked_mysql_query( makeInsertQuery('Assignment', $data ) );
  $assmtId = $db->insert_id;

  $groupMap = new GroupMap( $assmtId );
  
  if( $importGroups == 'on' && isset( $assmt->groups ) )
    insertGroups( $assmt->groups, $assmtId, $userIdMap, $groupMap );
  
  if( $importExtensions == 'on' && isset( $assmt->extensions ) )
    insertExtensions( $assmt->extensions, $assmtId, $userIdMap );
  
  if( $importAllocations == 'on' && isset( $assmt->allocations ) )
    $myReviewIdMap = insertAllocations( $assmt->allocations, $assmtId, $assmt, $userIdMap, $groupMap, $reviewIdMap );
  
  if( $importEssays == 'on' && isset( $assmt->submissions ) )
    insertSubmissions( $assmt->submissions, $assmtId, $userIdMap );

  if( !empty($assmt->reviewMarking) )
    foreach( $assmt->reviewMarking->assignment as $reviewMarking )
      importAssignment( $reviewMarking, $classId, $userIdMap, $importAllocations, $importEssays, $importGroups, $importExtensions, $assmtId, $myReviewIdMap );
}


function exportClass( ) {
  list( $cid ) = checkREQUEST( '_cid' );
  return HTML(HTML::h1('Export this class as XML'),
	      HTML::form( array('method'=>'post',
				'class'=>'form-inline',
				'action'=>"$_SERVER[PHP_SELF]?action=doExportClass"),
			  HiddenInputs( array('cid'=>$cid) ),
			  FormGroup('A',
				     _('Include allocations?'),
				    HTML::input(array('type'=>'checkbox',
						      'name'=>'A'))),
			  FormGroup('E',
				    _('Include submissions?'),
				    HTML::input(array('type'=>'checkbox',
						      'name'=>'E'))),
			  ButtonToolbar(submitButton( _('Export')),
					formButton( _('Cancel'), "selectClass&cid=$cid"))));
}

function doExportClass( ) {
  list( $cid ) = checkREQUEST( '_cid' );
  $classId = cidToClassId( $cid );

  $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF8' standalone='yes'?>\n<aropa/>");

  $class = fetchOne( "SELECT cname, cident, uident FROM Course c LEFT JOIN User u ON c.cident=u.userID"
		     . " WHERE courseID=$classId" );
  
  $xml->name = $class['cname'];
  if( ! empty($class['cident']) )
    $xml->accessCode = $class['cident'];
  if( ! empty($class['uident']) )
    $xml->owner = $class['uident'];
  
  addClassList( $xml, $classId );

  $xAssmts = $xml->addChild('assignments');
  $rs = checked_mysql_query( "SELECT * FROM Assignment WHERE courseID=$classId AND isReviewsFor IS NULL" );
  while( $assmt = $rs->fetch_assoc() )
    addAssignment( $assmt, $xAssmts );

  while( ob_end_clean( ) )
    //- Discard any earlier HTML or other headers
    ;

  header("Content-type: text/xml");
  header("Content-Disposition: attachment; filename=Aropa-class-$classId.xml;" );
  echo $xml->asXML();
  exit;
}

function addAssignment( $assmt, $xAssmts ) {
  $xa = addAssigmentDetails( $xAssmts, $assmt );
  if( ! empty($assmt['rubricID'] ) )
    addRubric( $xa, $assmt );
  addSubmissions( $xa, $assmt );
  addReviewers( $xa, $assmt['assmtID'] );
  addExtraAuthors( $xa, $assmt['assmtID'] );
  addGroups( $xa, $assmt['assmtID'] );
  addExtensions( $xa, $assmt['assmtID'] );
  if( $_REQUEST['A'] == 'on' )
    addAllocations( $xa, $assmt );  
  if( $_REQUEST['E'] == 'on' )
    addEssays( $xa, $assmt['assmtID'] );

  $xReviewMarking = $xa->addChild('reviewMarking');
  $rs = checked_mysql_query( "SELECT * FROM Assignment WHERE isReviewsFor=$assmt[assmtID]" );
  while( $reviewMarking = $rs->fetch_assoc() )
    addAssignment( $reviewMarking, $xReviewMarking );
}

function addClassList( $xml, $classId ) {
  $xUsers = $xml->addChild('users');
  $rs = checked_mysql_query( "SELECT uident, roles, username, email"
			     . " FROM UserCourse uc INNER JOIN User u ON uc.userId=u.userId"
			     . " WHERE courseID=$classId" );
  while( $row = $rs->fetch_assoc() ) {
    $xu = $xUsers->addChild('user');
    $xu->addAttribute('ident', $row['uident']);
    if( ($row['roles']&8) != 0 ) $xu->addChild('role', 'instructor');
    if( ($row['roles']&4) != 0 ) $xu->addChild('role', 'guest');
    if( ($row['roles']&2) != 0 ) $xu->addChild('role', 'marker');
    if( ! empty($row['username']) ) $xu->name = $row['username'];
    if( ! empty($row['email']) ) $xu->email = $row['email'];
  }
}

function getRole( $role ) {
  switch( $role ) {
  case 'instructor': return 8;
  case 'guest': return 4;
  case 'marker': return 2;
  default: return 1;
  }
}

function addAssigmentDetails( $xml, $assmt ) {
  $xa = $xml->addChild('assignment');
  $xa->name                = $assmt['aname'];
  if( $assmt['isActive'] )
    $xa->addChild('isActive');
  $xa->whenCreated         = $assmt['whenCreated'];
  $xa->selfReview          = $assmt['selfReview'] ? 1 : 0;
  $xa->submissionsDue      = $assmt['submissionEnd'];
  $xa->reviewsDue          = $assmt['reviewEnd'];
  $xa->allocationType      = $assmt['allocationType'];
  $xa->authorsAre          = $assmt['authorsAre'];
  $xa->reviewersAre        = $assmt['reviewersAre'];
  $xa->anonymousReview     = $assmt['anonymous'] ? 1 : 0;
  $xa->showMarksInFeedback = $assmt['showMarksInFeedback'] ? 1 : 0;
  $xa->allowLocking        =  $assmt['allowLocking'] ? 1 : 0;
  $xa->nPerReviewer        = $assmt['nPerReviewer'];
  $xa->category            = $assmt['category'];
  if( ! empty($args['reviewerMarking']) )
    $xa->reviewerMarking   = $assmt['reviewerMarking'];
  $xa->restrictFeedback    = $assmt['restrictFeedback'];
  if( ! empty($assmt['allocationsDone']) )
    $xa->allocationsDone = $assmt['allocationsDone'];
  if( ! empty($assmt['whenActivated']) )
    $xa->whenActivated = $assmt['whenActivated'];
  if( ! empty( $assmt['tags'] ) ) {
    $xtag = $xa->addChild('tags');
    foreach( preg_split("/[;,[:space:]]/", $assmt['tags'], -1, PREG_SPLIT_NO_EMPTY) as $tag )
      $xtag->addChild('tag', $tag);
  }
  if( ! empty( $assmt['visibleReviewers'] ) ) {
    $xrev = $xa->addChild('visibleReviewers');
    foreach( preg_split("/[ \t,;]+/", $assmt['visibleReviewers'], -1, PREG_SPLIT_NO_EMPTY) as $who )
      $xrev->addChild('reviewer', $who);
  }
  return $xa;
}

function getSimpleItems( $xml, $nodeName ) {
  $items = array( );
  foreach( $xml->$nodeName as $item )
    $items[] = "$item";
  return join(",", $items);
}


function addRubric( $xml, $assmt ) {
  $xr = $xml->addChild('rubric');
  $rubric = fetchOne("SELECT rubricXML, rname, uident, createdDate, lastEdited, sharing"
		     . " FROM Rubric r LEFT JOIN User u ON r.owner=u.userID"
		     . " WHERE rubricID=$assmt[rubricID]");
  $xr->rubricXml    = $rubric['rubricXML'];
  $xr->name         = $rubric['rname'];
  $xr->owner        = $rubric['uident'];
  $xr->createdDate  = $rubric['createdDate'];
  $xr->lastEdited   = $rubric['lastEdited'];
  $xr->sharing      = $rubric['sharing'];
  addItemString( $xr->addChild('markItems'), $assmt['markItems'] );
  addItemString( $xr->addChild('markLabels'), $assmt['markLabels'] );
  addItemString( $xr->addChild('commentItems'), $assmt['commentItems'] );
  addItemString( $xr->addChild('commentLabels'), $assmt['commentLabels']);
  $xr->nReviewFiles = $assmt['nReviewFiles'];
}

function addItemString( $xml, $itemStr, $nodeName = 'item', $attrName = 'index' ) {
  parse_str($itemStr ?? '', $items );
  foreach( $items as $idx => $item ) {
    $xs = $xml->addChild( $nodeName, $item );
    $xs->addAttribute($attrName, $idx );
  }
}

function getItemString( $xml, $nodeName = 'item', $attrName = 'index' ) {
  $items = array( );
  foreach( $xml->$nodeName as $xi )
    $items[ "".$xi->attributes()->$attrName ] = "$xi";
  return itemsToString( $items );
}

function addSubmissions( $xml, $assmt ) {
  $xsub = $xml->addChild('submission');
  if( ! empty( $assmt['submissionText'] ) )
    $xsub->instructions = $assmt['submissionText'];
  $xreqs = $xsub->addChild('requirements');
  foreach( preg_split( "/\n/", $assmt['submissionRequirements'], -1, PREG_SPLIT_NO_EMPTY ) as $requireStr ) {
    $xr = $xreqs->addChild('requirement');
    //- $requireStr is expected to look like:
    //-    [file | url | inline] ","  [require | optional | oneof] "," ...urlencode'd additional key=value requirements...
    list($type, $required, $argStr) = explode( ",", $requireStr );
    parse_str($argStr ?? '', $args );
    $xr->addChild('type', $type);
    $xr->addChild('required', $required);
    if( ! empty($args['extn']) ) $xr->extn = $args['extn'];
    if( ! empty($args['other']) ) $xr->regex = $args['other'];
    if( ! empty($args['prompt']) ) $xr->prompt = $args['prompt'];
    if( ! empty($args['mimetype']) ) $xr->mimetype = $args['mimetype'];
    if( ! empty($args['rows']) || ! empty($args['cols'])) {
      $xtext = $xr->addChild('textInput');
      if( ! empty($args['rows']) ) $xtext->addAttribute('rows', $args['rows']);
      if( ! empty($args['cols']) ) $xtext->addAttribute('cols', $args['cols']);
    }
  }
}

function getRequirements( $xml ) {
  $spec = array( );
  foreach( $xml->requirement as $req ) {
    $type = "".$req->type;
    $required = "".$req->required;
    $args = array( );
    if( isset( $req->extn ) )
      $args['extn'] = "".$req->extn;
    if( isset( $req->other ) )
      $args['other'] = "".$req->other;
    if( isset( $req->prompt ) )
      $args['prompt'] = "".$req->prompt;
    if( isset( $req->mimetype ) )
      $args['mimetype'] = "".$req->mimetype;
    if( isset( $req->textInput ) ) {
      $args['rows'] = "".$req->textInput->rows;
      $args['cols'] = "".$req->textInput->cols;
    }
    $spec[] = "$type,$required," . itemsToString( $args );
  }
  return join("\n", $spec);
}


function addAllocations( $xml, $assmt ) {
  $xAllocs = $xml->addChild('allocations');
  $allocs = new Allocations($assmt['assmtID']);
  foreach( $allocs->allocations as $alloc ) {
    $xa = $xAllocs->addChild('allocation');
    $xa->author = $allocs->nameOfAuthor( $alloc['author'] );
    $xa->author->addAttribute('type', $assmt['authorsAre']);
    $xa->reviewer = $allocs->nameOfReviewer( $alloc['reviewer'] );
    $xa->reviewer->addAttribute('type', $assmt['reviewersAre']);
    if( ! empty($alloc['tag']) ) $xa->tag = $alloc['tag'];
    if( ! empty($alloc['lastViewed']) ) $xa->lastViewed = $alloc['lastViewed'];
    if( ! empty($alloc['lastMarked']) ) $xa->lastMarked = $alloc['lastMarked'];
    if( ! empty($alloc['lastSeen']) ) $xa->lastSeen = $alloc['lastSeen'];
    if( ! empty($alloc['lastSeenBy']) )
      $xa->lastSeenBy = nameOf( $assmt['assmtID'], $alloc['lastSeenBy'],
				$allocs->byReviewer, $allocs->byEssay, 'author');
    if( $alloc['locked'] ) $xa->addChild('locked');
    if( ! empty($alloc['marks']) ) $xa->marks = itemsToString($alloc['marks']);
    $xcs = $xa->addChild('comments');
    $cs = checked_mysql_query('SELECT item, comments, uident as madeBy, whenMade'
			      . ' FROM Comment c INNER JOIN User u ON c.madeBy=u.userID'
			      . " WHERE allocID=$alloc[allocID]" );
    while( $comment = $cs->fetch_assoc() ) {
      //$c = $xcs->addChild('comment', htmlspecialchars(Encoding::toUTF8($comment['comments'])));
      $c = addCDATA( $xcs, 'comment', Encoding::toUTF8($comment['comments']) );
      //$c = $xcs->addChild('comment', bin2hex($comment['comments']));
      $c->addAttribute('item', $comment['item']);
      $c->addAttribute('madeBy', $comment['madeBy']);
      $c->addAttribute('whenMade', $comment['whenMade']);
    }
  }
}


function insertAllocations( $xml, $assmtID, $assmt, $userIdMap, $groupMap, $reviewIdMap ) {
  $myReviewIdMap = array();
  $db = ensureDBconnected('insertAllocations');
  foreach( $xml->allocation as $alloc ) {
    $data = array('assmtID'=>$assmtID);

    $attrs = $alloc->author->attributes();
    $type = $attrs->type;
    if( $type == 'group' )
      $data['author'] = - (int)$groupMap->getGroupID( "".$alloc->author );
    else if( $type == 'review' )      
      $data['author'] = $reviewIdMap[ "".$alloc->author ];
    else
      $data['author'] = $userIdMap->getUserId( "".$alloc->author );

    if( empty($data['author']) ) continue;

    $attrs = $alloc->reviewer->attributes();
    $type = $attrs->type;
    if( $type == 'group' )
      $data['reviewer'] = - (int)$groupMap->getGroupID("".$alloc->reviewer);
    else
      $data['reviewer'] = $userIdMap->getUserId( "".$alloc->reviewer );

    if( empty($data['reviewer']) ) continue;

    if( isset( $alloc->tag) ) $data['tag'] = "".$alloc->tag;
    if( isset( $alloc->lastViewed) ) $data['lastViewed'] = $alloc->lastViewed;
    if( isset( $alloc->lastMarked) ) $data['lastMarked'] = $alloc->lastMarked;
    if( isset( $alloc->lastSeen  ) ) $data['lastSeen']   = $alloc->lastSeen;
    if( isset( $alloc->lastSeenBy ) ) $data['lastSeenBy'] = $userIdMap->getUserId( "".$alloc->lastSeenBy );
    if( isset( $alloc->locked ) ) $data['locked'] = true;
    if( isset( $alloc->marks ) )  $data['marks'] = "".$alloc->marks;
    checked_mysql_query( makeInsertQuery('Allocation', $data ) );
    $allocId = $db->insert_id;

    // see nameOf() in Allocation.php
    $myReviewIdMap[$alloc->reviewer . '/' . $alloc->author] = $allocId;

    if( isset( $alloc->comments ) ) {
      foreach( $alloc->comments->comment as $comment ) {
	$attrs = $comment->attributes( );
	$data = array('allocID'  => $allocId,
		      'item'     => "".$attrs->item,
		      'comments' => "".$comment,
		      'madeBy'   => $userIdMap->getUserId( "".$attrs->madeBy ),
		      'whenMade' => $attrs->whenMade);
	checked_mysql_query( makeInsertQuery('Comment', $data ) );
      }
    }
  }

  return $myReviewIdMap;
}

function addReviewers( $xml, $assmtID ) {
  $reviewers = fetchAll( "SELECT uident FROM Reviewer r LEFT JOIN User u ON r.reviewer=u.userID "
			 . "WHERE assmtID=$assmtID", 'uident' );
  if( ! empty( $reviewers ) ) {
    $xr = $xml->addChild('markers');
    foreach( $reviewers as $r )
      $xr->addChild('marker', $r );
  }
}


function addExtraAuthors( $xml, $assmtID ) {
  $authors = fetchAll( "SELECT uident FROM Author r LEFT JOIN User u ON r.author=u.userID "
			 . "WHERE assmtID=$assmtID", 'uident' );
  if( ! empty( $authors ) ) {
    $xr = $xml->addChild('authors');
    foreach( $authors as $a )
      $xr->addChild('author', $a );
  }
}

function addGroups( $xml, $assmtID ) {
  require_once 'Groups.php';

  $xGroups = $xml->addChild('groups');
  $groups = new Groups($assmtID);
  foreach( $groups->groupIDtoGname as $groupID => $gname ) {
    $xg = $xGroups->addChild('group');
    $xg->addAttribute('name', $gname );
    foreach( $groups->groupToMembers[ $groupID ] as $userID )
      $xg->addChild('member', $groups->userIDtoUident[ $userID ]);
  }
}

class GroupMap {
  var $_map;
  var $_assmtID;
  
  function __construct( $assmtID ) {
    $this->_map = array( );
    $this->_assmtID = $assmtID;
    $rs = checked_mysql_query("SELECT groupID, gname FROM `Groups` WHERE assmtID=$assmtID");
    while( $row = $rs->fetch_assoc() )
      $this->_map[ $row['gname'] ] = (int)$row['groupID'];
  }

  function getGroupID( $gname ) {
    if( ! isset( $this->_map[ $gname ] ) ) {
      $db = ensureDBconnected('GroupMap.getGroupID');
      checked_mysql_query( makeInsertQuery('`Groups`', array('assmtID'=>$this->_assmtID,
							   'gname'  =>$gname)) );
      $this->_map[ $gname ] = $db->insert_id;
    }
    return $this->_map[ $gname ];
  }
}

function insertGroups( $xml, $assmtID, $userIdMap, $groupMap ) {
  foreach( $xml->group as $group ) {
    $attrs = $group->attributes();
    $groupId = $groupMap->getGroupID("".$attrs->name);
    $members = array( );
    foreach( $group->member as $name )
      $members[] = "$name";
    $values = array( );
    foreach( $userIdMap->getUserIds( $members ) as $userId )
      $values[] = "($assmtID, $groupId, $userId)";
    if( ! empty($values) )
      checked_mysql_query( 'INSERT INTO GroupUser (assmtID,groupID,userID) VALUES ' . join(",", $values) );
  }
}

function addExtensions( $xml, $assmtID ) {
  $extns = fetchAll("SELECT uident, submissionEnd, reviewEnd, tag, whenMade"
		    . " FROM Extension e LEFT JOIN User u ON e.who=u.userID"
		    . " WHERE assmtID=$assmtID" );
  if( ! empty($extns) ) {
    $xExtns = $xml->addChild('extensions');
    foreach( $extns as $e ) {
      $xe = $xExtns->addChild('extension');
      $xe->addAttribute('user', $e['uident']);
      if( ! empty($e['submissionEnd'] ) ) $xe->addChild('submission', $e['submissionEnd'] );
      if( ! empty($e['reviewEnd'] ) ) $xe->addChild('review', $e['reviewEnd'] );
      if( ! empty($e['tag'] ) ) $xe->addChild('tag', $e['tag'] );
      if( ! empty($e['whenMade'] ) ) $xe->addChild('whenMade', $e['whenMade'] );
    }
  }
}

function insertExtensions( $xml, $assmtID, $userIdMap ) {
  $values = array( );
  foreach( $xml->extension as $extn ) {
    $attrs = $extn->attributes();
    $who = $userIdMap->getUserId( "".$attrs->user );
    $submissionEnd = isset( $extn->submission ) ? quote_smart("".$extn->submission) : 'NULL';
    $reviewEnd     = isset( $extn->review )     ? quote_smart("".$extn->review)     : 'NULL';
    $tag           = isset( $extn->tag )        ? quote_smart("".$extn->tag)        : 'NULL';
    $whenMade      = isset( $extn->whenMade)    ? quote_smart($extn->whenMade)   : 'NULL';
    $values[] = "($assmtID,$who,$submissionEnd,$reviewEnd,$tag,$whenMade)";
  }
  checked_mysql_query("INSERT IGNORE INTO Extension (assmtID,who,submissionEnd,reviewEnd,tag,whenMade) VALUES " . join(",", $values) );
}


function addEssays( $xml, $assmtID ) {
  $xSub = $xml->addChild('submissions');
  $rs = checked_mysql_query( "SELECT essayID, reqIndex, uident, 'text/plain' as extn, description, whenUploaded,"
			     . " lastDownloaded, url, 0 as compressed, '(content omitted)' as essay, tag, isPlaceholder, 0 as overflow"
			     . " FROM Essay e LEFT JOIN User u ON e.author=u.userID"
			     . " WHERE assmtID=$assmtID" );
  while( $row = $rs->fetch_assoc() ) {
    $xs = $xSub->addChild('submission');
    $xs->addAttribute('author', $row['uident']);
    $xs->addAttribute('reqIndex', $row['reqIndex']);
    if( ! empty($row['isPlaceholder'] ) )
      $xs->addChild('isPlaceholder');
    if( ! empty($row['extn'] ) )
      $xs->addChild('extn', Encoding::toUTF8($row['extn']));
    if( ! empty($row['description'] ) )
      addCDATA( $xs, 'description', Encoding::toUTF8($row['description']));
    if( ! empty($row['whenUploaded'] ) )
      $xs->addChild('whenUploaded', $row['whenUploaded']);
    if( ! empty($row['lastDownloaded'] ) )
      $xs->addChild('lastDownloaded', $row['lastDownloaded']);
    if( ! empty($row['url'] ) )
      $xs->addChild('url', Encoding::toUTF8($row['url']));
    if( $row['compressed'] )
      $xs->addChild('compressed');
    if( ! empty($row['tag'] ) )
      $xs->addChild('tag', $row['tag']);
    if( ! empty($row['essay'] ) ) {
      $data = $row['essay'];
      /*
      $rs2 = checked_mysql_query("SELECT data FROM Overflow WHERE essayID=$row[essayID] ORDER BY seq");
      while( $dd = $rs2->fetch_assoc() )
	$data .= $dd['data'];
      */
      $xe = $xs->addChild('essay', bin2hex($data));
      $xe->addAttribute('encoding', 'application/mac-binhex40');
    }
  }
}

function insertSubmissions( $xml, $assmtID, $userIdMap ) {
  foreach( $xml->submission as $submission ) {
    $attr = $submission->attributes( );
    $data = array('assmtID'  =>$assmtID,
		  'author'   => $userIdMap->getUserId( "".$attr->author ),
		  'reqIndex' => (int)"".$attr->reqIndex);
    if( isset( $submission->isPlaceholder ) )
      $data['isPlaceholder'] = true;
    if( isset( $submission->extn ) )
      $data['extn'] = "".$submission->extn;
    if( isset( $submission->description ) )
      $data['description'] = "".$submission->description;
    if( isset( $submission->whenUploaded ) )
      $data['lastDownloaded'] = $submission->whenUploaded;
    if( isset( $submission->lastDownloaded ) )
      $data['lastDownloaded'] = $submission->lastDownloaded;
    if( isset( $submission->url ) )
      $data['url'] = "".$submission->url;
    $data['compressed'] = isset($submission->compressed);
    if( isset( $submission->tag ) )
      $data['tag'] = "".$submission->tag;
    $overflow = '';
    if( isset( $submission->essay ) ) {
      $essay = "".$submission->essay;
      $attrs = $submission->essay->attributes( );
      if( "".$attrs->encoding == "application/mac-binhex40" )
	$essay = hex2bin( $essay );

      $MAX_CHUNK = 600*1024; //- Allow (ample) room for quote expansion
      if( strlen( $essay ) > $MAX_CHUNK ) {
	$overflow = substr( $essay, $MAX_CHUNK );
	$data['essay'] = substr( $essay, 0, $MAX_CHUNK );
	$data['overflow'] = true;
      }
    }

    $db = ensureDBconnected('insertSubmissions');

    checked_mysql_query( makeInsertQuery( 'Essay', $data ) );
    $essayID = $db->insert_id;
    for( $block = 0; strlen( $overflow ) > $block; $block += $MAX_CHUNK )
      checked_mysql_query( "INSERT INTO Overflow (essayID, data) VALUES ($essayID, "
			   . quote_smart(substr($overflow, $block, $MAX_CHUNK)) . ")");
  }
}

function addCDATA($xml, $name, $value) {
  $child = $xml->addChild( $name );
  $node = dom_import_simplexml( $child );
  $node->appendChild($node->ownerDocument->createCDATASection($value));
  return $child;
}
