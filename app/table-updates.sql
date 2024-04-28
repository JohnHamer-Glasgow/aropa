-- Copyright (c) 2009 www.cryer.co.uk
-- Script is free to use provided this copyright header is included.
DROP PROCEDURE IF EXISTS AddColumnUnlessExists;
DROP PROCEDURE IF EXISTS CreateIndexUnlessExists;
DELIMITER //
CREATE PROCEDURE AddColumnUnlessExists(
       IN tableName 	tinytext,
       IN fieldName 	tinytext,
       IN fieldDef 	text)
BEGIN
	IF NOT EXISTS (
		SELECT * FROM information_schema.COLUMNS
		WHERE COLUMN_NAME=fieldName
		AND TABLE_NAME=tableName
		AND TABLE_SCHEMA=DATABASE()
		)
	THEN
		SET @ddl = CONCAT('ALTER TABLE ', DATABASE(),'.',tableName, ' ADD COLUMN ', fieldName, ' ', fieldDef);
		PREPARE STMT FROM @ddl;
		EXECUTE STMT;
	END IF;
END;

CREATE PROCEDURE CreateIndexUnlessExists(
       IN tableName  tinytext,
       IN indexName  tinytext,
       IN isUnique   tinyint,
       IN fieldList  tinytext)
BEGIN
	IF NOT EXISTS (SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=tableName AND INDEX_NAME=indexName)
	THEN
		IF isUnique = 1 THEN
		    SET @dd1 = CONCAT('CREATE UNIQUE INDEX ', indexName,' ON ', tableName, '(', fieldList, ')');
		ELSE
		    SET @dd1 = CONCAT('CREATE INDEX ', indexName,' ON ', tableName, '(', fieldList, ')');
		END IF;
		PREPARE STMT FROM @dd1;
		EXECUTE STMT;
	END IF;
END;

//

DELIMITER ;

CALL AddColumnUnlessExists('Assignment', 'lastModified', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
CALL AddColumnUnlessExists('Assignment', 'markGrades', 'TEXT');
CALL AddColumnUnlessExists('Essay', 'isPlaceholder', 'BOOLEAN NOT NULL DEFAULT TRUE');
CALL AddColumnUnlessExists('Course', 'cuserID', 'INT NOT NULL DEFAULT -1');

DROP TABLE GroupUser;
CREATE TABLE GroupUser (
       assmtID	    	int	    NOT NULL    REFERENCES Assignment(assmtID),
       groupID          int         NOT NULL    REFERENCES Groups(groupID),
       userID           int         NOT NULL    REFERENCES User(userID),
  PRIMARY KEY (assmtID, groupID, userID)
);

ALTER TABLE Assignment CHANGE authorsAre authorsAre ENUM('all', 'group', 'other', 'review', 'reviewer') DEFAULT 'all';
ALTER TABLE Extension ADD tag text;

-- function add_column_if_not_exist( $table, $column, $column_attr ) {
--     if( ! fetchOne("SHOW COLUMNS FROM $table WHERE Field='$column'") )
--       mysql_query("ALTER TABLE $table ADD `$column`  $column_attr");
-- }

CALL AddColumnUnlessExists('Institution', 'isActive', 'BOOLEAN DEFAULT TRUE');

CALL AddColumnUnlessExists('User', 'fbUID', 'BIGINT UNSIGNED');
CALL CreateIndexUnlessExists('User', 'byFbUID', 1, 'fbUID');

ALTER TABLE Assignment ADD restrictFeedback ENUM('none', 'some', 'all')	  DEFAULT 'none';


CREATE TABLE IF NOT EXISTS EmailAlias (
	email		VARCHAR(254)	NOT NULL,
	userID		int		NOT NULL,
  PRIMARY KEY (email)
);

ALTER TABLE Assignment ADD nReviewFiles  int NOT NULL default 0;
ALTER TABLE Survey ADD nReviewFiles  int NOT NULL default 0;

ALTER TABLE Allocation ADD lastSeen datetime;
ALTER TABLE Allocation ADD lastSeenBy int;

ALTER TABLE User ADD lastChanged datetime;
ALTER TABLE Assignment ADD whenActivated datetime;

-- Choose a plausible date for whenActivated
UPDATE Assignment a INNER JOIN
  (SELECT assmtID, MIN(whenUploaded) d FROM Essay GROUP BY assmtID) b ON a.assmtID=b.assmtID
SET a.whenActivated=b.d;

ALTER TABLE MarkAudit ADD INDEX byMadeBy (madeBy);
ALTER TABLE Comment ADD INDEX byMadeBy (madeBy);
ALTER TABLE CommentAudit ADD INDEX byMadeBy (madeBy);
ALTER TABLE Assignment ADD showReviewMarkingFeedback boolean default false;
ALTER TABLE Assignment MODIFY allocationType  ENUM('normal', 'manual', 'streams', 'same tags', 'other tags', 'response');

DROP TABLE LastMod;
CREATE TABLE LastMod (
        assmtID         int             REFERENCES Assignment(assmtID),
        lastMod         datetime        NOT NULL,
  PRIMARY KEY (assmtID)
);

ALTER TABLE Assignment ADD category ENUM('', 'test', 'successful', 'aborted') default '';
alter table Extension add whenMade datetime;

alter table Allocation add status enum('not-started', 'partial', 'complete') not null default 'not-started';
update Allocation set status = 'complete' where lastMarked is not null;

alter table SessionAudit add browser text;
alter table SessionAudit add server text;
alter table Course add subject varchar(80) not null default '';
alter table Comment drop primary key, add primary key(allocID, item, madeBy);

alter table Comment add column itemN int;
update Comment
       set itemN = case item
           when 'Argument' then 0
           when 'Law' then 1
	   when 'Mechanics' then 2
	   when 'Structure' then 3
       	   when 'Objectives' then 0
           when 'Scope' then 1
           when 'Context' then 2
           when 'Structure' then 3
           when 'Achievability' then 4
           when 'Quality' then 5
           when 'Overall' then 6
           when 'Further' then 7
           when 'Timeframe' then 8
           when 'General' then 0
           when 'Likes' then 1
           when 'Dislikes' then 2
           when 'Argument' then 0
           when 'Issue' then 1
           when 'Reasons' then 2
           when 'Counter' then 3
           when 'Sentence' then 4
           when 'SA1' then 0
           when 'LA1' then 1
           when 'SA2' then 2
           when 'LA2' then 3
           when 'SA3' then 4
           when 'LA3' then 5
           when 'Evidence' then 0
           when 'Analytical' then 1
           when 'LikedMost' then 2
           when 'Improve' then 3
           when 'Overview' then 1
           when 'Skills' then 2
           when 'Website' then 3
           when 'ImprovePres' then 0
           when 'BestPres' then 1
           when 'ImproveStruct' then 2
           when 'BestStruct' then 3
           when 'SummaryCoverage' then 4
           when 'MissingRef' then 5
           when 'Summary' then 6
           when 'CommentOnS1V' then 0
           when 'CommentOnS1J' then 1
           when 'CommentOnS3SC' then 2
           when 'CommentOnSDA' then 3
           when 'CommentOnSDS' then 4
           when 'CommentOnOverall' then 5
           when 'CommentOnS2J' then 6
           when 'CommentOnS2V' then 7
           when 'CommentOnS2SC' then 8
           when 'CommentOnS1SC' then 9
           when 'CommentOnS2G' then 10
           when 'CommentOnS1C' then 11
           when 'CommentOnS2C' then 12
           when 'CommentOnSDAS' then 13
           when 'CommentOnS1G' then 14
           when 'CommentOnS3G' then 15
           when 'CommentOnS3V' then 16
           when 'CommentOnS3J' then 17
           when 'CommentOnS3C' then 18
           when 'Composition' then 0
           when 'ADME' then 1
           when 'Mechanism' then 2
           when 'Efficacy' then 3
           when 'Adverse' then 4
           when 'Regulatory' then 5
           when 'Referencing' then 6
           when 'Presentation' then 7
           when 'tacticComments' then 0
           when 'descriptionComment' then 1
           when 'justificationComment' then 2
           when 'overallComment' then 3
           when 'consistencyComment' then 4
           when 'Q01Positive' then 0
           when 'Q01Improvements' then 1
           when 'Q02Positive' then 2
           when 'Q02Improvements' then 3
           when 'Q03Positive' then 4
           when 'Q03Improvements' then 5
           when 'Q04Positive' then 6
           when 'Q04Improvements' then 7
           when 'Q05Positive' then 8
           when 'Q05Improvements' then 9
           when 'Review_Comments' then 10
           when 'Review_Comments' then 11
           when 'C1' then 0
           when 'C2' then 1
           when 'C3' then 2
           when 'C4' then 3
           when 'C1' then 4
           when 'C2' then 5
           when 'C3' then 6
           when 'C4' then 7
	   when 'Strength' then 0
	   when 'P1_Comments' then 0
	   else convert(item, int) end;

alter table Comment
      drop primary key,
      drop column item,
      change itemN item int not null,
      change madeBy madeBy int not null,
      change allocID allocID int not null,
      add primary key(allocID, item, madeBy);

alter table Assignment change category category ENUM('', 'test', 'successful', 'aborted', 'unused') default '';

create table if not exists Superusers (userID int not null references User(userID));
insert into Superusers (userID) select userID from User where uident in ('hcp-super', 'jham005');
update User set status = 'active' where status = 'administrator';

alter table Allocation change tag tag text;
alter table Assignment add commentLabels text;

-- 11 Dev 2020
create table if not exists AllocationAudit (
       assmtID int not null,
       tag varchar(20),
       reviewer int not null,
       author int not null,
       edited timestamp not null default current_timestamp,
  index byAssmt (assmtID)
);
