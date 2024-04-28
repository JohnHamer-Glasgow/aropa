LOCK TABLES
	Institution READ,
	User WRITE,
	Course WRITE,
	UserCourse WRITE,
	Assignment WRITE,
	Essay WRITE,
	Comment WRITE,
	Rubric WRITE,
	Allocation WRITE;

SELECT @I:=MAX(instID) FROM Institution;
SELECT @U:=IFNULL(MAX(userID),0) FROM User;
SELECT @C:=IFNULL(MAX(courseID),0) FROM Course;
SELECT @A:=IFNULL(MAX(assmtID),0) FROM Assignment;
SELECT @E:=IFNULL(MAX(essayID),0) FROM Essay;
SELECT @R:=IFNULL(MAX(rubricID),0) FROM Rubric;
SELECT @L:=IFNULL(MAX(allocID),0) FROM Allocation;
SELECT @D:=CURDATE();

INSERT INTO User (userID,uident,username,status,instID,email,pendingEmail,emailVerify,passwd,prefs) VALUES
(@U+1,'hcp',NULL,'active',@I,NULL,NULL,NULL,NULL,NULL),
(@U+2,'test123a',NULL,'active',@I,NULL,NULL,NULL,NULL,NULL),
(@U+3,'test123b',NULL,'active',@I,NULL,NULL,NULL,NULL,NULL),
(@U+4,'test123c',NULL,'active',@I,NULL,NULL,NULL,NULL,NULL),
(@U+5,'test123d',NULL,'active',@I,NULL,NULL,NULL,NULL,NULL),
(@U+6,'test123e',NULL,'active',@I,NULL,NULL,NULL,NULL,NULL),
(@U+7,'test123f',NULL,'active',@I,NULL,NULL,NULL,NULL,NULL),
(@U+8,'test123g',NULL,'active',@I,NULL,NULL,NULL,NULL,NULL),
(@U+9,'test123h',NULL,'active',@I,NULL,NULL,NULL,NULL,NULL),
(@U+10,'test123i',NULL,'active',@I,NULL,NULL,NULL,NULL,NULL),
(@U+11,'test123j',NULL,'active',@I,NULL,NULL,NULL,NULL,NULL);

INSERT INTO Rubric (rubricID,rubric,rubricXML,rname,owner,rubricType,createdDate,lastEdited,sharing,copiedFrom) VALUES
(@R+1,NULL,'<p>&nbsp;</p>\r\n<p>Please comment on whether the sentence adequately captures the point of the paper. As part of your comment, please indicate what you yourself think the paper is about.</p>\r\n<p><img src=\"tinymce/jscripts/tiny_mce/plugins/aropa/img/textBlock.jpg\" style=\"width: 400px height;\" /></p>\r\n<p>&nbsp;</p>\r\n<p>Is the sentence a valid English sentence?</p>\r\n<p><span style=\"background-color: blue;\"><input type=\"radio\" /></span>&nbsp; Yes</p>\r\n<p><span style=\"background-color: blue;\"><input type=\"radio\" /></span>&nbsp; No: it is more than one sentence.</p>\r\n<p><span style=\"background-color: blue;\"><input type=\"radio\" /></span>&nbsp; No: it does not have a verb.</p>\r\n<p><span style=\"background-color: blue;\"><input type=\"radio\" /></span>&nbsp; No: it does not have a subject.</p>\r\n<p>&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<hr />\r\n<p>&nbsp;</p>\r\n<p>Does the sentence fulfil the \'maximum 25 words\' limit?</p>\r\n<p><span style=\"background-color: green;\"><input type=\"radio\" /></span>&nbsp; Yes</p>\r\n<p><span style=\"background-color: green;\"><input type=\"radio\" /></span>&nbsp; No</p>\r\n<p>&nbsp;</p>\r\n<hr />\r\n<p>&nbsp;</p>\r\n<p>Give advice on how to improve the sentence. Do not simply present your own sentence, but give advice on how this sentence could be improved.</p>\r\n<p><img src=\"tinymce/jscripts/tiny_mce/plugins/aropa/img/textBlock.jpg\" style=\"width: 400px height;\" /></p>\r\n<p>&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<p><span style=\"font-family: Arial; font-size: x-small;\"><br /></span></p>',NULL,NULL,'assignment',NOW(),NOW(),'everyone',NULL);

INSERT INTO Course (courseID,instID,cname,cactive,cident) VALUES
(@C+1,@I,'HCP-TEST','1',NULL);

INSERT INTO Assignment (assmtID, isReviewsFor, courseID, isActive,
aname, whenCreated, basepath, selfReview, allocationType, authorsAre,
reviewersAre, anonymousReview, showMarksInFeedback, submissionText,
submissionEnd, reviewEnd, rubricID, markItems, commentItems,
nPerReviewer, nPerSubmission, nStreams, allocationsDone, tags,
visibleReviewers, submissionRequirements, lastModified, markGrades,
markLabels, hasReviewEvaluation, allowLocking, reviewerMarking) VALUES
(@A+1, NULL, @C+1, '1', 'Example', NOW(), NULL, '0',
'normal', 'all', 'submit', '1', '1', '<p>Please type your one-sentence
summary of the paper into the editor (max 25 words).</p>',
CONCAT(@D,' 08:50:00'),
CONCAT(@D+INTERVAL 1 DAY, ' 11:00:00'),
@R+1, '1=4&2=2',
'0=Comment%200&1=Comment%201', '3', '0', '1',
CONCAT(@D,' 08:50:23'),
NULL, NULL, 'text,required,prompt=%28default%29&rows=&cols=',
NOW(), '1=1%2C0%2C0%2C0&2=1%2C0',
'1=Sentence%20structure&2=Sentence%20length', '0', '0', NULL);

INSERT INTO Allocation (allocID,assmtID,tag,reviewer,author,lastViewed,lastMarked,lastResponse,locked,marks,choices) VALUES
(@L+1,@A+1,'STUDENT',@U+3,@U+2,NULL,NULL,NULL,'0',NULL,NULL),
(@L+2,@A+1,'STUDENT',@U+5,@U+2,CONCAT(@D,' 09:02:45'),CONCAT(@D,' 09:03:49'),NULL,'0','1=1&2=1',NULL),
(@L+3,@A+1,'STUDENT',@U+7,@U+2,CONCAT(@D,' 09:14:18'),NULL,NULL,'0',NULL,NULL),
(@L+4,@A+1,'STUDENT',@U+4,@U+3,CONCAT(@D,' 09:01:01'),CONCAT(@D,' 09:02:10'),NULL,'0','1=3&2=1',NULL),
(@L+5,@A+1,'STUDENT',@U+6,@U+3,CONCAT(@D,' 09:06:59'),CONCAT(@D,' 09:08:04'),NULL,'0','1=3&2=1',NULL),
(@L+6,@A+1,'STUDENT',@U+7,@U+3,CONCAT(@D,' 09:15:59'),CONCAT(@D,' 09:17:32'),NULL,'0','1=3&2=1',NULL),
(@L+7,@A+1,'STUDENT',@U+2,@U+4,CONCAT(@D,' 08:50:27'),CONCAT(@D,' 08:53:49'),NULL,'0','1=1&2=2',NULL),
(@L+8,@A+1,'STUDENT',@U+3,@U+4,NULL,NULL,NULL,'0',NULL,NULL),
(@L+9,@A+1,'STUDENT',@U+5,@U+4,CONCAT(@D,' 09:03:54'),CONCAT(@D,' 09:05:16'),NULL,'0','1=1&2=2',NULL),
(@L+10,@A+1,'STUDENT',@U+3,@U+5,NULL,NULL,NULL,'0',NULL,NULL),
(@L+11,@A+1,'STUDENT',@U+6,@U+5,CONCAT(@D,' 09:09:48'),CONCAT(@D,' 09:12:06'),NULL,'0','1=1&2=1',NULL),
(@L+12,@A+1,'STUDENT',@U+7,@U+5,CONCAT(@D,' 09:14:20'),CONCAT(@D,' 09:15:47'),NULL,'0','1=1&2=1',NULL),
(@L+13,@A+1,'STUDENT',@U+2,@U+6,CONCAT(@D,' 08:54:06'),CONCAT(@D,' 08:56:04'),NULL,'0','1=2&2=2',NULL),
(@L+14,@A+1,'STUDENT',@U+4,@U+6,CONCAT(@D,' 08:59:30'),CONCAT(@D,' 09:00:57'),NULL,'0','1=2&2=2',NULL),
(@L+15,@A+1,'STUDENT',@U+5,@U+6,CONCAT(@D,' 09:05:21'),CONCAT(@D,' 09:06:14'),NULL,'0','1=2&2=2',NULL),
(@L+16,@A+1,'STUDENT',@U+2,@U+7,CONCAT(@D,' 08:56:09'),CONCAT(@D,' 08:57:24'),NULL,'0','1=3&2=1',NULL),
(@L+17,@A+1,'STUDENT',@U+4,@U+7,NULL,NULL,NULL,'0',NULL,NULL),
(@L+18,@A+1,'STUDENT',@U+6,@U+7,CONCAT(@D,' 09:08:07'),CONCAT(@D,' 09:09:43'),NULL,'0','1=2&2=1',NULL);

INSERT INTO UserCourse (userID,courseID,roles) VALUES
(@U+1,@C+1,'8'),
(@U+2,@C+1,'1'),
(@U+3,@C+1,'1'),
(@U+4,@C+1,'1'),
(@U+5,@C+1,'1'),
(@U+6,@C+1,'1'),
(@U+7,@C+1,'1'),
(@U+8,@C+1,'1'),
(@U+9,@C+1,'1'),
(@U+10,@C+1,'1'),
(@U+11,@C+1,'1');

INSERT INTO Essay (essayID,assmtID,reqIndex,author,extn,description,whenUploaded,lastDownloaded,url,compressed,essay,tag,overflow) VALUES
(@E+1,@A+1,'1',@U+2,'inline-text','11 words entered in the Aropa editor',CONCAT(@D,' 08:31:27'),NULL,NULL,'0','<p>&nbsp;</p>\r\n<p>The paper is about a new visualisation tool that displays stars.</p>',NULL,'0'),
(@E+2,@A+1,'1',@U+3,'inline-text','8 words entered in the Aropa editor',CONCAT(@D,' 08:32:26'),NULL,NULL,'0','<p>&nbsp;</p>\r\n<p>A system that displays stars as small dots.</p>',NULL,'0'),
(@E+3,@A+1,'1',@U+4,'inline-text','28 words entered in the Aropa editor',CONCAT(@D,' 08:34:10'),NULL,NULL,'0','<p>&nbsp;</p>\r\n<p>This paper describes a sophisticated information visualisation system that takes as input data about stars and displays them in a useful and effective manner, employing advanced interactive techniques.</p>',NULL,'0'),
(@E+4,@A+1,'1',@U+5,'inline-text','14 words entered in the Aropa editor',CONCAT(@D,' 08:35:02'),NULL,NULL,'0','<p>&nbsp;</p>\r\n<p>This paper purports to be about information visualisation, but is actually about scientific visualisation.</p>',NULL,'0'),
(@E+5,@A+1,'1',@U+6,'inline-text','39 words entered in the Aropa editor',CONCAT(@D,' 08:43:08'),NULL,NULL,'0','<p>&nbsp;</p>\r\n<p>This paper tells us in great detail about a star visualisation system. The star data is taken from a catalogue and is displayed in tabbed windows. I like the fact that the colour is derived from the luminosity figure.</p>',NULL,'0'),
(@E+6,@A+1,'1',@U+7,'inline-text','9 words entered in the Aropa editor',CONCAT(@D,' 08:43:45'),NULL,NULL,'0','<p>&nbsp;</p>\r\n<p>A paper about stars. Shown in a java program.</p>',NULL,'0');

INSERT INTO Comment (allocID,item,comments,madeBy,whenMade) VALUES
(@L+14,'0','<p>The point of the paper is to show how complex data can be displayed. I don\'t think this sentence says enough about the complexity.</p>',@U+4,CONCAT(@D,' 09:00:57')),
(@L+14,'1','<p>It needs to be one sentence, and it needs to focus on the complexity of the visualisation process - colour is only part of this.</p>',@U+4,CONCAT(@D,' 09:00:57')),
(@L+4,'0','<p>The sentence does not say much at all!</p>',@U+4,CONCAT(@D,' 09:02:10')),
(@L+4,'1','<p>You need to re-read the paper carefully to see what it really is about - it is more than what you say!</p>',@U+4,CONCAT(@D,' 09:02:10')),
(@L+18,'0','<p>The paper is not about stars - we need to think about it in terms of CS. Yes, there is a java program, but the paper is about visualisation.</p>',@U+6,CONCAT(@D,' 09:09:43')),
(@L+18,'1','<p>Shouldn\'t focus on the java program - think about visualisation pipeline instead.</p>',@U+6,CONCAT(@D,' 09:09:43')),
(@L+5,'0','<p>The paper is about more than this - what about the input format and the interaction?</p>',@U+6,CONCAT(@D,' 09:08:04')),
(@L+5,'1','<p>Probably need to think more about what the paper is about. Need more info.</p>',@U+6,CONCAT(@D,' 09:08:04')),
(@L+11,'0','<p>Not sure what this means? What is the difference? I don\'t think this is a summary of the paper - which is about the process of visualisation for displaying stars.</p>',@U+6,CONCAT(@D,' 09:12:06')),
(@L+11,'1','<p>What you have is an opinion, not a summary - you need to read the paper and summarise what it is about.</p>',@U+6,CONCAT(@D,' 09:12:06')),
(@L+6,'0','<p>This doesn\'t tell us much about what the paper is about - you do &nbsp;not say anything about the visualisation process - which is what the paper is actually about.</p>',@U+7,CONCAT(@D,' 09:17:32')),
(@L+6,'1','<p>You need to think about the real point of the paper - need to say something about visualisation.</p>',@U+7,CONCAT(@D,' 09:17:32')),
(@L+12,'0','<p>Not sure that this could be called a summary! It is a comment on the paper (which is about the process of visualising stars) - and I don\'t know the information/scientific difference.</p>',@U+7,CONCAT(@D,' 09:15:47')),
(@L+12,'1','<p>Need to focus on the paper itself, I think. Not a comment on the words used in it.</p>',@U+7,CONCAT(@D,' 09:15:47')),
(@L+2,'0','<p>Yes - this is what the paper is about.</p>',@U+5,CONCAT(@D,' 09:03:49')),
(@L+2,'1','<p>Maybe include more details about the system.</p>',@U+5,CONCAT(@D,' 09:03:49')),
(@L+9,'0','<p>Yes: this is what the paper is all about! Well done (you said it better than me!)</p>',@U+5,CONCAT(@D,' 09:05:16')),
(@L+9,'1','<p>Not in the word limit! Good sentence, but you need to think about how you can make it shorter (don\'t know how you can do that - 25 words is not many at all!)</p>',@U+5,CONCAT(@D,' 09:05:16')),
(@L+15,'0','<p>Yes: this is what the paper is about. But I don\'t think that colour is the most important bit.</p>',@U+5,CONCAT(@D,' 09:06:14')),
(@L+15,'1','<p>You need to remove the focus on colour - it was only a small part of the system</p>',@U+5,CONCAT(@D,' 09:06:14')),
(@L+7,'0','<p>Yes: the point is captured well. The paper is indeed about taking star data and visualising it. I don\'t know that we can say \'useful and effective\' without more evaluation information.</p>',@U+2,CONCAT(@D,' 08:53:49')),
(@L+7,'1','<p>It it over the wordlimit - perhaps some of the adjectives could be removed.</p>',@U+2,CONCAT(@D,' 08:53:49')),
(@L+13,'0','<p>The sentence includes opinion, so is not really simply a summary. The paper also describes the interactive facilities of the system.</p>',@U+2,CONCAT(@D,' 08:56:04')),
(@L+13,'1','<p>I think you need to focus on the paper, rather than your opinion of it. This will give you more words to focus on the content. I don\'t think it is useful to focus on one particular aspect (the colour) in a summary.</p>',@U+2,CONCAT(@D,' 08:56:04')),
(@L+16,'0','<p>The paper is not about stars! There is no mention of visualisation in the sentence.</p>',@U+2,CONCAT(@D,' 08:57:24')),
(@L+16,'1','<p>The sentence needs more information about the fact that the paper is about the visualisation process.</p>',@U+2,CONCAT(@D,' 08:57:24'));

UNLOCK TABLES;
