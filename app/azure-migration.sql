alter table User drop index byFbUID;

create table if not exists Groups2 (
  groupId int not null auto_increment primary key,
  assmtId int references Assignment(assmtID),
  seq int not null,
  gname varchar(80) not null,
  index byAssmtId (assmtId)
);
create table if not exists GroupUser2 (
  groupId int not null references Groups2(groupId),
  userId int not null references User(userID),
  index byGroupId (groupId)
);
insert into Groups2 (assmtId, seq, gname) from select assmtID, groupID, gname from `Groups`;
insert into GroupUser2 (groupId, userId)
from select g2.groupId, gu.userID
     from GroupUser gu
     join Groups2 g2 on g2.seq = gu.groupID and g2.userID = gu.userID;
drop table `Groups`;
drop table GroupUser;
alter table Groups2 rename to `Groups`;
alter table GroupUser2 rename to GroupUser;

alter table Overflow
  drop primary key,
  modify seq int not null,
  add primary key (essayID, seq);

alter table ReviewFileOverflow
  drop primary key,
  modify seq int not null,
  add primary key (reviewFileID, seq);

alter table SessionAudit drop key byUserID, drop column seq, add index byUserID (userID);

drop table MarkAudit; // *** We have never used this
drop table CommentAudit; // *** We have never used this.
drop table Survey; // *** Better options exist
drop table Participant; // *** Better options exist
drop table SurveyData; // *** Better options exist
