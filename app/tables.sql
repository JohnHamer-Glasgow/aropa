-- AROPA database format
/*
    Copyright (C) 2017 John Hamer <J.Hamer@acm.org>

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

-- CREATE DATABASE /*!32312 IF NOT EXISTS*/ aropa;

-- CREATE USER 'aropa'@'localhost' IDENTIFIED BY 'pass';
-- GRANT ALL PRIVILEGES ON aropa.* TO 'aropa'@'localhost';
-- [ or GRANT SELECT, INSERT, UPDATE, DELETE, LOCK TABLES ON aropa.* TO 'aropa'@'localhost'; ]
-- [ SET PASSWORD FOR 'aropa'@'localhost' = OLD_PASSWORD('PXFwk9p'); ]

-- USE aropa;
-- DROP TABLE IF EXISTS Account;
-- DROP TABLE IF EXISTS Allocation;
-- DROP TABLE IF EXISTS Assignment;
-- DROP TABLE IF EXISTS Comment;
-- DROP TABLE IF EXISTS Course;
-- DROP TABLE IF EXISTS Essay;
-- DROP TABLE IF EXISTS Extension;
-- DROP TABLE IF EXISTS Groups;
-- DROP TABLE IF EXISTS LastMod;
-- DROP TABLE IF EXISTS Lecturer;
-- DROP TABLE IF EXISTS MarkAudit;
-- DROP TABLE IF EXISTS Participants;
-- DROP TABLE IF EXISTS Reviewer;
-- DROP TABLE IF EXISTS Rubric;
-- DROP TABLE IF EXISTS Semesters;
-- DROP TABLE IF EXISTS Session;
-- DROP TABLE IF EXISTS SessionAudit;
-- DROP TABLE IF EXISTS Survey;
-- DROP TABLE IF EXISTS SurveyComment;
-- DROP TABLE IF EXISTS SurveyData;


CREATE TABLE IF NOT EXISTS Institution (
       instID           int         NOT NULL AUTO_INCREMENT,
       isActive         boolean     default true,
       shortname        varchar(10),
       longname         text,
       logo             blob,
       logoType         text,
       features         text,  -- such as: ADB
                               --          HAVE_NETACCOUNT
                               --          TRUST_SERVER_AUTH
                               --          LDAP_SERVER
                               --          LDAP_BASE_DN
                               --          timezone, from timezone_abbreviations_list
  PRIMARY KEY (instID)
);
INSERT INTO Institution (instID) VALUES (1);

CREATE TABLE IF NOT EXISTS User (
        userID          int             NOT NULL AUTO_INCREMENT,
        uident          varchar(80)     NOT NULL,
        username        varchar(80), 
        status          ENUM('inactive', 'active', 'administrator') default 'active',
        instID          int             NOT NULL REFERENCES Institution(instID),
        email           text,
        pendingEmail    text,
        emailVerify     text,  -- random string used to verify email address
        passwd          text,
        prefs           text,
        lastChanged     datetime,
        fbUID           BIGINT UNSIGNED,
  PRIMARY KEY (userID),
  UNIQUE INDEX byIdent (uident, instID),
  UNIQUE INDEX byFbUID (fbUID),
  INDEX byUsername (instID, username)
);
INSERT INTO User (uident, status, instID, passwd) VALUES ('admin', 'administrator', 1, 'ZhwjFFvVjELLE'); -- password='admin'

create table if not exists Superusers (
       userID int not null references User(userID)
);
insert into Superusers (userID) values (1);

CREATE TABLE IF NOT EXISTS EmailAlias (
        email           VARCHAR(254)    NOT NULL,
        userID          int             NOT NULL,
  PRIMARY KEY (email)
);

CREATE TABLE IF NOT EXISTS Course (
        courseID        int             NOT NULL        AUTO_INCREMENT,
        instID          int             NOT NULL        REFERENCES Institution(instID),
        cname           varchar(80)     NOT NULL,
        cactive         boolean         default true,
        cident          varchar(80),    -- code given to class by instructor, so students can register
        subject         varchar(80)     not null default '',
        cuserID         int             NOT NULL DEFAULT -1, -- (blessed) instructor who created this course
  PRIMARY KEY (courseID),
  UNIQUE INDEX byInst (instID, cname)
);


CREATE TABLE IF NOT EXISTS UserCourse (
       userID           int NOT NULL REFERENCES User(userID),
       courseID         int NOT NULL REFERENCES Course(courseID),
       roles            int default 1,     -- 1=student; 2=marker; 4=guest; 8=instructor
  PRIMARY KEY (userID, courseID)
);


CREATE TABLE IF NOT EXISTS Assignment (
        assmtID         int             NOT NULL        AUTO_INCREMENT,
        isReviewsFor    int             default NULL    REFERENCES Assignment(assmtID),
        courseID        int                             REFERENCES Course(courseID),
        isActive        boolean         NOT NULL        default false,
        category        ENUM('', 'test', 'successful', 'aborted', 'unused') default '',
        aname           varchar(80)     NOT NULL,
        whenCreated     datetime,
        basepath        text,
        selfReview      boolean,
        allocationType  ENUM('normal', 'manual', 'streams', 'same tags', 'other tags', 'response'),
        authorsAre      ENUM('all', 'group', 'other', 'review', 'reviewer') default 'all',
        reviewersAre    ENUM('all', 'submit', 'group', 'other') default 'submit',
        anonymousReview boolean                         default true,
        showMarksInFeedback boolean                     default false,
        hasReviewEvaluation boolean                     default false,
        allowLocking    boolean                         default false,
        showReviewMarkingFeedback boolean               default false,
        submissionText  text,
        submissionEnd   datetime,
        reviewEnd       datetime,
        rubricID        int,    
        markItems       text,
        markGrades      text,
        markLabels      text,
        commentItems    text,
        commentLabels   text,
        nReviewFiles    int                               NOT NULL default 0,
        nPerReviewer    tinyint(1)                        default -1,
        nPerSubmission  tinyint(1)                        default -1,
        reviewerMarking ENUM('markAll', 'split'),
        restrictFeedback ENUM('none', 'some', 'all')      DEFAULT 'none',
        nStreams        tinyint(1) default 1,
        allocationsDone datetime,
        whenActivated   datetime,
        tags            text,
        visibleReviewers text,
        submissionRequirements text,
        lastModified    timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (assmtID)
);


CREATE TABLE IF NOT EXISTS Rubric (
        rubricID        int                             NOT NULL        AUTO_INCREMENT,
        rubric          text,
        rubricXML       mediumtext,
        rname           varchar(80),
        owner           int                             REFERENCES User(userID),
        rubricType      ENUM('survey', 'assignment')    NOT NULL,
        createdDate     datetime not null,
        lastEdited      datetime,
        sharing         ENUM('none', 'colleagues', 'everyone') DEFAULT 'none',
        copiedFrom      int                             REFERENCES Rubric(rubricID),
   PRIMARY KEY(rubricID)
);


-- Records when an assignment was last modified.  Used to trigger
-- refreshing data cached in $_SESSION.
CREATE TABLE if not exists LastMod (
        assmtID         int             REFERENCES Assignment(assmtID),
        lastMod         datetime        NOT NULL,
  PRIMARY KEY (assmtID)
);


-- Record the list of ad-hoc reviewers for an assignment, for the case
-- where reviewersAre = 'other'
CREATE TABLE IF NOT EXISTS Reviewer (
       assmtID          int         NOT NULL    REFERENCES Assignment(assmtID),
       reviewer         int         NOT NULL    REFERENCES User(userID),
  PRIMARY KEY (assmtID, reviewer)
);


-- Record the list of ad-hoc authors for an assignment, for the case
-- where authorsAre = 'other'
CREATE TABLE IF NOT EXISTS Author (
       assmtID          int         NOT NULL    REFERENCES Assignment(assmtID),
       author           int         NOT NULL    REFERENCES User(userID),
  PRIMARY KEY (assmtID, author)
);


CREATE TABLE IF NOT EXISTS Stream (
       assmtID         int         NOT NULL     REFERENCES Assignment(assmtID),
       who             int         NOT NULL     REFERENCES User(userID),
       stream          int         NOT NULL     default 0,
  PRIMARY KEY (assmtID, who)
);

CREATE TABLE IF NOT EXISTS `Groups` (
       assmtID          int                     REFERENCES Assignment(assmtID),
       groupID          int         NOT NULL    AUTO_INCREMENT,
       gname            varchar(80) NOT NULL,
  PRIMARY KEY (assmtID, groupID)
) engine=MyISAM;

CREATE TABLE IF NOT EXISTS GroupUser (
       assmtID          int                     REFERENCES Assignment(assmtID),
       groupID          int         NOT NULL    REFERENCES `Groups`(groupID),
       userID           int         NOT NULL    REFERENCES User(userID),
  PRIMARY KEY (assmtID, groupID, userID)
);

-- Students may be given an extension of time to do their reviewing.
-- Use the later of Assignment.reviewEnd and Extension.reviewEnd
--                  Assignment.submissionEnd and Extension.submissionEnd
CREATE TABLE IF NOT EXISTS Extension (
        assmtID         int             NOT NULL        REFERENCES Assignment(assmtID),
        who             int             NOT NULL        REFERENCES User(userID),
        submissionEnd   datetime,
        reviewEnd       datetime,
        tag             text,
        whenMade        datetime,
  PRIMARY KEY (assmtID, who)
);



-- Essay holds pieces of work submitted for review.  Each essay is
-- associated with a single assignment.  Typically an author might
-- submit just one essay for a given assignment, but this is not
-- necessay.
CREATE TABLE IF NOT EXISTS Essay (
        essayID         int             NOT NULL        AUTO_INCREMENT,
        assmtID         int             NOT NULL        REFERENCES Assignment(assmtID),
        reqIndex        int,
        author          int             NOT NULL        REFERENCES User(userID),
        extn            text,
        description     text,
        whenUploaded    datetime,
        lastDownloaded  datetime,
        url             text,
        compressed      boolean         default true,
        essay           mediumblob,    -- allows up to 16Mb, but anything over 1Mb goes in Overflow
        tag             text,
        isPlaceholder   boolean         default false,
        overflow        boolean         default false,
  PRIMARY KEY (essayID),
  INDEX byAssignment (assmtID, author,  essayID)
);

CREATE TABLE IF NOT EXISTS Overflow (
       essayID          int             NOT NULL        REFERENCES Essay(essayID),
       seq              int             NOT NULL        AUTO_INCREMENT,
       data             mediumblob, -- usually max of 1Mb
  PRIMARY KEY (essayID, seq)
) engine=MyISAM; -- Aria;

CREATE TABLE IF NOT EXISTS Allocation (
        allocID         int             NOT NULL        AUTO_INCREMENT,
        assmtID         int             NOT NULL        REFERENCES Assignment(assmtID),
        tag             varchar(20),
        reviewer        int             NOT NULL, -- real or group UID
        author          int             NOT NULL, -- real or group UID
        lastViewed      datetime,
        lastMarked      datetime,
        lastResponse    datetime,
        lastSeen        datetime,
        lastSeenBy      int                             REFERENCES User(userID),
        locked          boolean         default false, -- set when the reviewer is all done.  If set, allows early viewing of feedback.
        marks           text,
        choices         text,
        status          enum('not-started', 'partial', 'complete') not null default 'not-started',
  PRIMARY KEY (allocID),
  UNIQUE INDEX byReviewer (assmtID, reviewer,  author),
  UNIQUE INDEX byAuthor   (assmtID,   author,  reviewer)
);

create table if not exists AllocationAudit (
       assmtID int not null,
       tag varchar(20),
       reviewer int not null,
       author int not null,
       edited timestamp not null default current_timestamp,
  index byAssmt (assmtID)
);

-- ALTER TABLE Allocation DROP INDEX byAssmtID;
-- ALTER TABLE Allocation DROP INDEX byAuthor;
-- ALTER TABLE Allocation DROP INDEX byReviewer;
-- ALTER TABLE Allocation ADD UNIQUE INDEX byAuthor (assmtID,reviewer,author);
-- ALTER TABLE Allocation ADD UNIQUE INDEX byReviewer (assmtID,author,reviewer);

-- Previous marks.  Each time the marks are changed, the old mark is
-- recorded in here.
CREATE TABLE IF NOT EXISTS MarkAudit (
        allocID         int             NOT NULL        REFERENCES Allocation(allocID),
        sequence        int             NOT NULL        AUTO_INCREMENT,
        madeBy          int             NOT NULL        REFERENCES User(userID),
        whenMade        datetime not null,
        marks           text,
        choices         text,
  PRIMARY KEY (allocID, sequence),
  INDEX byMadeBy (madeBy)
) engine=MyISAM; -- Aria;


-- Free-format comments can be entered against any rubric item.
CREATE TABLE IF NOT EXISTS Comment (
        allocID         int             NOT NULL        REFERENCES Allocation(allocID),
        item            int             not null,
        comments        mediumtext,
        madeBy          int             NOT NULL        REFERENCES User(userID),
        whenMade        timestamp        NOT NULL default current_timestamp,
  PRIMARY KEY (allocID, item, madeBy),
  INDEX byMadeBy (madeBy)
);


CREATE TABLE IF NOT EXISTS CommentAudit (
         allocID         int            NOT NULL        REFERENCES Allocation(allocID),
         item            varchar(80),
         sequence        int            NOT NULL        AUTO_INCREMENT,
         comments        mediumtext,
         madeBy          int            NOT NULL        REFERENCES User(userID),
         whenMade        datetime       NOT NULL,
  PRIMARY KEY (allocID, item, sequence),
  INDEX byMadeBy (madeBy)
) engine=MyISAM; -- Aria;


-- External review files.
CREATE TABLE IF NOT EXISTS ReviewFile (
        reviewFileID    int             NOT NULL        AUTO_INCREMENT,
        allocID         int             NOT NULL        REFERENCES Allocation(allocID),
        item            int             NOT NULL,
        madeBy          int             NOT NULL        REFERENCES User(userID),
        extn            text,
        description     text,
        whenUploaded    datetime,
        lastDownloaded  datetime,
        compressed      boolean         default true,
        contents        mediumblob,    -- allows up to 16Mb, but anything over 1Mb goes in ReviewFileOverflow
        overflow        boolean         default false,
  PRIMARY KEY (reviewFileID),
  UNIQUE INDEX ByAlloc (allocID, item)
);

CREATE TABLE IF NOT EXISTS ReviewFileOverflow (
       reviewFileID     int             NOT NULL        REFERENCES ReviewFile(reviewFileID),
       seq              int             NOT NULL        AUTO_INCREMENT,
       data             mediumblob, -- usually max of 1Mb
  PRIMARY KEY (reviewFileID, seq)
) engine=MyISAM; -- Aria;


-- General purpose surveys, such as formative feedback questionnaires
CREATE TABLE IF NOT EXISTS Survey (
        surveyID        int             NOT NULL        AUTO_INCREMENT,
        vname           varchar(80)     NOT NULL,
        courseID        int                             REFERENCES Course(courseID),
        isActive        boolean         NOT NULL        default false,
        whenCreated     timestamp default current_timestamp,
        access          ENUM('anonymous', 'confidential', 'open') NOT NULL default 'anonymous',
        results         ENUM('private', 'participants', 'open') NOT NULL default 'participants',
        rubricID        int                             REFERENCES Rubric(rubricID),
        surveyStart     datetime,
        surveyEnd       datetime,
        nReviewFiles    int             NOT NULL default 0,
        markItems       text,
        markLabels      text,
        markGrades      text,
        commentItems    text,
  PRIMARY KEY (surveyID),
  INDEX byCourse (courseID, isActive, surveyID)
);


-- Participants in a survey.
CREATE TABLE IF NOT EXISTS Participants (
        surveyID        int             NOT NULL        REFERENCES Survey(surveyID),
        participant     int             NOT NULL        REFERENCES User(userID),
  PRIMARY KEY (participant, surveyID)
);


-- Survey responses
CREATE TABLE IF NOT EXISTS SurveyData (
        surveyID        int             NOT NULL        REFERENCES Survey(surveyID),
        seq             int             NOT NULL        AUTO_INCREMENT,
        marks           text,
        choices         text,
        comments        mediumtext,
        who             int                             REFERENCES User(userID),
        whenAdded       timestamp,
  PRIMARY KEY (surveyID, seq)
) engine=MyISAM; -- Aria;


create table if not exists Messages (
  messageId        int       not null auto_increment,
  createdBy        int       not null references User(userID),
  whenCreated      timestamp not null default current_timestamp,
  lastModified     timestamp not null default current_timestamp on update current_timestamp,
  message          text      not null,
  showFrom         datetime  not null,
  showUntil        datetime  not null,
  whoFor           int       not null default 15, -- 1=student; 2=marker; 4=guest; 8=instructor
  restrictToInst   int           null references Institution(instID),
  restrictToCourse int           null references Course(courseID),
  primary key (messageId)
);

CREATE TABLE if not exists Session (
        id              char(32)        NOT NULL,
        userID          int             REFERENCES User(userID),
        `data`          mediumblob      NOT NULL,
        lastUsed        int(11)         NOT NULL,
        ip              char(15)        NOT NULL,
  PRIMARY KEY (id)
);


CREATE TABLE if not exists SessionAudit (
        userID  int                     NOT NULL,
        seq     int                     NOT NULL        AUTO_INCREMENT,
        eventTime timestamp,
        event   ENUM('login', 'logout') NOT NULL,
        ip      char(15),
        browser text,
        server  text,
  INDEX byUserID (userID, seq),
  INDEX byTime (eventTime)
) engine=MyISAM; -- Aria;
