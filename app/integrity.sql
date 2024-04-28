SELECT COUNT(*) FROM Assignment WHERE courseID NOT IN (SELECT courseID FROM Course);

SELECT COUNT(*) FROM LastMod    WHERE assmtID NOT IN (SELECT assmtID FROM Assignment);
SELECT COUNT(*) FROM Reviewer   WHERE assmtID NOT IN (SELECT assmtID FROM Assignment);
SELECT COUNT(*) FROM Author     WHERE assmtID NOT IN (SELECT assmtID FROM Assignment);
SELECT COUNT(*) FROM Stream     WHERE assmtID NOT IN (SELECT assmtID FROM Assignment);
SELECT COUNT(*) FROM Groups     WHERE assmtID NOT IN (SELECT assmtID FROM Assignment);
SELECT COUNT(*) FROM GroupUser  WHERE assmtID NOT IN (SELECT assmtID FROM Assignment);
SELECT COUNT(*) FROM Extension  WHERE assmtID NOT IN (SELECT assmtID FROM Assignment);

SELECT COUNT(*) FROM Allocation   WHERE assmtID NOT IN (SELECT assmtID FROM Assignment);
SELECT COUNT(*) FROM MarkAudit    WHERE allocID NOT IN (SELECT allocID FROM Allocation);
SELECT COUNT(*) FROM CommentAudit WHERE allocID NOT IN (SELECT allocID FROM Allocation);

SELECT COUNT(*) FROM Essay      WHERE assmtID NOT IN (SELECT assmtID FROM Assignment);
SELECT COUNT(*) FROM Overflow   WHERE essayID NOT IN (SELECT essayID FROM Essay);

SELECT COUNT(*) FROM Survey       WHERE courseID NOT IN (SELECT courseID FROM Course);
SELECT COUNT(*) FROM Participants WHERE surveyID NOT IN (SELECT surveyID FROM Survey);
SELECT COUNT(*) FROM SurveyData   WHERE surveyID NOT IN (SELECT surveyID FROM Survey);

SELECT COUNT(*) FROM Rubric WHERE rubricID NOT IN (SELECT rubricID FROM Assignment UNION SELECT rubricID FROM Survey);
