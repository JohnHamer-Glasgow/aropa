-- Update from legacy
INSERT IGNORE INTO Institution (instID, shortname, longname, features) VALUES
       (1, 'UoA', 'The University of Auckland', 'ADB=true&HAVE_NETACCOUNT=true&LDAP_SERVER=ldaps://ldap-vip.ec.auckland.ac.nz/&LDAP_BASE_DN=ou=ec_users,dc=ec,dc=auckland,dc=ac,dc=nz'),
       (2, 'AUT', 'AUT University', ''),
       (3, 'UTS', 'University of Technology, Sydney', '');

INSERT IGNORE INTO User (uident, instID, username, email)
       SELECT name, 1, name, CONCAT(name,'@auckland.ac.nz')
       FROM aropa_2008_s1.Lecturer;

INSERT INTO User (uident, instID, username, email, passwd, prefs)
       SELECT name, 1, name, CONCAT(name,'@aucklanduni.ac.nz'), passwd, prefs
       FROM aropa_2008_s1.Account
       WHERE name NOT IN (SELECT uident FROM User);

INSERT INTO User (uident, instID, username)
       SELECT DISTINCT reviewer, 1, reviewer FROM aropa_2008_s1.Allocation
       WHERE NOT EXISTS (SELECT * FROM User WHERE uident = reviewer);

INSERT INTO User (uident, instID, username)
       SELECT DISTINCT author, 1, author FROM aropa_2008_s1.Allocation
       WHERE author NOT IN (SELECT uident FROM User);

INSERT INTO User (uident, instID, username)
       SELECT DISTINCT author, 1, author FROM aropa_2008_s1.Essay
       WHERE author NOT IN (SELECT uident FROM User);

INSERT INTO User (uident, instID, username)
       SELECT DISTINCT reviewer, 1, reviewer FROM aropa_2008_s1.Reviewer
       WHERE reviewer NOT IN (SELECT uident FROM User);

INSERT INTO User (uident, instID, username)
       SELECT DISTINCT who, 1, who FROM aropa_2008_s1.Extension
       WHERE who NOT IN (SELECT uident FROM User);

INSERT INTO User (uident, instID, username)
       SELECT DISTINCT madeBy, 1, madeBy FROM aropa_2008_s1.Comment
       WHERE madeBy NOT IN (SELECT uident FROM User);

INSERT INTO User (uident, instID, username)
       SELECT DISTINCT madeBy, 1, madeBy FROM aropa_2008_s1.MarkAudit
       WHERE madeBy NOT IN (SELECT uident FROM User);

INSERT INTO User (uident, instID, username)
       SELECT DISTINCT participant, 1, participant FROM aropa_2008_s1.Participants
       WHERE participant NOT IN (SELECT uident FROM User);

INSERT INTO Course (instID, cname, cactive)
       SELECT 1, CONCAT(course, ' ', s.semester), archived FROM aropa_2008_s1.Course c CROSS JOIN aropa_2008_s1.Semesters s
       WHERE EXISTS (SELECT * FROM aropa_2008_s1.Assignment a
                     WHERE a.course = c.course
                      AND  a.reviewStart >= s.startDate
                      AND  a.reviewStart <= s.endDate);

INSERT IGNORE INTO Assignment (assmtID, isReviewsFor, courseID, isActive, aname, owner, whenCreated,
                        basepath, selfReview, allocationType, authorsAre, reviewersAre,
                        anonymousReview, showMarksInFeedback, submissionText, submissionStart,
                        submissionEnd, reviewStart, reviewEnd, feedbackStart, feedbackEnd,
                        rubricID, rubric, markItems, choiceItems, commentItems, nPerReviewer,
                        nPerSubmission, nStreams, allocationsDone,
                        tags, visibleReviewers, submissionRequirements)
        SELECT assmtID,isReviewsFor, Course.courseID, isActive, aa.name, userID, whenCreated,
                        basepath, selfReview, allocationType,
                        IF(authorsAreReviewers,'all','other'),
                        IF(authorsAreReviewers,'submit','other'),
                        anonymousReview, showGradesInFeedback, submissionText, submissionStart,
                        submissionEnd, reviewStart, reviewEnd, feedbackStart, feedbackEnd,
                        rubricID, rubric, gradeItems, choiceItems, commentItems, nPerReviewer,
                        nPerSubmission, nStreams, allocationsDone,
                        tags, visibleReviewers,
                        'file,require,prompt=Select%20a%20file,extn=any'
         FROM aropa_2008_s1.Assignment aa
	 LEFT JOIN aropa_2008_s1.Semester s ON s.semesterID = aa.semesterID
	 LEFT JOIN Course c ON c.cname = CONCAT(aa.course, ' ', s.semester)
	 LEFT JOIN User u   ON aa.owner=u.uident;

INSERT IGNORE INTO Reviewer (assmtID, reviewer)
       SELECT DISTINCT assmtID, userID
       FROM aropa_2008_s1.Reviewer INNER JOIN User ON reviewer=User.uident;

INSERT IGNORE INTO Stream (assmtID, who, stream)
       SELECT DISTINCT assmtID, userID, stream
       FROM aropa_2008_s1.Reviewer INNER JOIN User ON reviewer=User.uident
       WHERE stream IS NOT NULL;

INSERT IGNORE INTO Essay (assmtID, author, extn, description, whenUploaded, url, essay, compressed, tag)
       SELECT assmtID, userID, extn, description, whenUploaded, url, '(Document purged during upgrade)', 0, tag
       FROM aropa_2008_s1.Essay INNER JOIN User ON author=uident
       WHERE aropa_2008_s1.Essay IS NOT NULL;

INSERT IGNORE INTO Allocation (allocID, assmtID, tag, reviewer, author, lastViewed, lastMarked, lastResponse,
                        locked, marks, choices)
       SELECT allocID, assmtID, tag, u1.userID, u2.userID, lastViewed, lastMarked, lastResponse,
              locked, marks, choices
       FROM aropa_2008_s1.Allocation
       LEFT JOIN User AS u1 ON reviewer = u1.uident
       LEFT JOIN User AS u2 ON author = u2.uident;

INSERT IGNORE INTO UserCourse (userID, courseID, roles)
       SELECT DISTINCT userID, courseID, 8 --'instructor'
       FROM aropa_2008_s1.Assignment a
       INNER JOIN Assignment b ON a.assmtID = b.assmtID
       INNER JOIN User ON a.owner = User.uident;

INSERT IGNORE INTO UserCourse (userID, courseID, roles)
       SELECT DISTINCT userID, courseID, 1 --'student'
       FROM aropa_2008_s1.Allocation a
       INNER JOIN User                       ON a.author = User.uident
       INNER JOIN aropa_2008_s1.Assignment b ON a.assmtID = b.assmtID
       INNER JOIN Course                     ON b.course = cname
       INNER JOIN aropa_2008_s1.Semester s   ON Course.semesterID = s.semesterID
       WHERE b.submissionEnd >= s.startDate AND b.submissionEnd <= s.endDate;

INSERT IGNORE INTO UserCourse (userID, courseID, roles)
       SELECT DISTINCT userID, courseID, 2 --'marker'
       FROM aropa_2008_s1.Allocation a
       INNER JOIN User                       ON a.author = User.uident
       INNER JOIN aropa_2008_s1.Assignment b ON a.assmtID = b.assmtID
       INNER JOIN Course                     ON b.course = cname
       INNER JOIN aropa_2008_s1.Semester s   ON Course.semesterID = s.semesterID
       WHERE b.submissionEnd >= s.startDate AND b.submissionEnd <= s.endDate;

INSERT IGNORE INTO Extension (assmtID, who, submissionEnd, reviewEnd)
       SELECT assmtID, userID, submissionEnd, reviewEnd
       FROM aropa_2008_s1.Extension e
       LEFT JOIN User ON e.who = User.uident;

INSERT IGNORE INTO MarkAudit (allocID, sequence, madeBy, whenMade, marks, choices)
       SELECT allocID, sequence, userID, whenMade, oldMarks, oldChoices
       FROM aropa_2008_s1.MarkAudit a
       LEFT JOIN User ON a.madeBy = User.uident;

INSERT IGNORE INTO Comment (allocID, item, comments, madeBy, whenMade)
       SELECT allocID, item, comments, userID, whenMade
       FROM aropa_2008_s1.Comment c
       LEFT JOIN User ON c.madeBy = User.uident
       ORDER BY whenMade DESC;

INSERT IGNORE INTO Survey (surveyID, sname, courseID, isActive, owner, whenCreated, access,
                    rubricID, surveyStart, surveyEnd, markItems, choiceItems, commentItems)
       SELECT surveyID, aropa_2008_s1.Survey.name, courseID, isActive, userID, whenCreated, a.access,
              rubricID, surveyStart, surveyEnd, markItems, choiceItems, commentItems
       FROM aropa_2008_s1.Survey a
       LEFT JOIN Course ON Course.cname = course
       LEFT JOIN User   ON User.uident = owner;

--INSERT IGNORE INTO Participants (surveyID, participant)
--       SELECT surveyID, userID
--       FROM aropa_2008_s1.Participants
--       LEFT JOIN User ON User.uident = participant;

--INSERT IGNORE INTO SurveyData (surveyID, seq, marks, choices, who, whenAdded)
--       SELECT surveyID, sequence, marks, choices, userID, whenAdded
--       FROM aropa_2008_s1.SurveyData 
--       LEFT JOIN User ON User.uident=who;

--INSERT IGNORE INTO SurveyComment (surveyID, item, seq, comment, who, whenAdded)
--       SELECT surveyID, item, sequence, comment, userID, whenAdded
--       FROM aropa_2008_s1.SurveyComment LEFT JOIN User ON User.uident=who;

INSERT IGNORE INTO Rubric (rubricID, rubric, rubricXML, rname, owner,
                    rubricType, createdDate, lastEdited, copiedFrom)
       SELECT rubricID, rubric, '', name, userID,
                    rubricType, createdDate, lastEdited, relatedTo
       FROM aropa_2008_s1.Rubric
       LEFT JOIN User ON User.uident=owner;
