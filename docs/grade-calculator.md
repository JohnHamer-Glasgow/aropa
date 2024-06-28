# Aropä: Peer Review made Easy - Grade calculator

Peer review is a powerful means of engaging students in critical
reflection and in promptly generating copious amounts of personalised
written feedback. It can also be used to assign a mark to a piece of
student work.

Having students assign marks to each other raises many questions.  Do
students mark reliably and fairly? We have plenty of evidence to show
that they can and do. As instructors know, deciding on a mark is
usually the easy part of writing a review.

The key difference between peer marking and instructor or tutor
marking is that peers (usually) each mark several submissions, so
every submissions ends up with several marks. Since just one mark is
required, these marks need to be averaged; this can be done in a
number of ways. There may also be rogue marks, lone voices that
disagree wildly from other reviewers.

## Overview of grade calculation

A rubric can contain groups of radio buttons, and the reviewer can
select one radio button in each group. Each button has an associated
mark.  You can specify the marks in `Label Rubric`, or just use the
default marks (1 for the first button in the group, 2 for the second,
etc.)

The marks for the selected buttons are used in `Calculate Grades`,
which does all the calculations needed in averaging the marks from the
reviewers.

Aropä calculates an overall mark for a student’s submission as follows:

* for each student submission, the marks for each button group are
  collected (one from each reviewer) and a weighted average is
  calculated.  Weights will be explained shortly;

* the total mark for the submission is the sum of these weighted
  averages;

* only reviewers who have entered a mark are included in the averaging.

## Three ways of averaging

The most common way averaging a collection of marks is to take the
mean. For example, if three reviewers give marks of 4, 3 and 1 then
the mean is $(4 + 3 + 1)/3$, or $2.67$.

An alternatively method, which places less emphasis on extreme scores,
is the median (middle) mark. With the same marks as above, the median
is 3, slightly higher than the 2.67 given by the mean. This reflects
the fact that two of the three reviewers gave considerably higher
marks than the third.

Lastly, provided there are many more reviews than possible marks,
taking the most popular mark (mode) may be a reasonable alternative.
Extreme marks are effectively ignored altogether using this
method. For example, if reviewers gave marks of 4, 3, 4, 4, 1 then the
mode will be exactly 4.

## Identifying and removing outliers

The grade summary page shows the total mark, number of reviews, and
“discrepancy” for each author. Technically, discrepancy is the “mean
absolute deviation”. This has a straight-forward interpretation of how
much, on average, the reviewers disagreed each other.

Clicking on any column heading will sort (or reverse sort) on that
column. Sorting on the discrepancy is a quick way of identifying
sources of disagreement.

Clicking on the author name will show the marks from all of the
reviewers. Clicking further on the name of a reviewer will show
details of all the reviews written by that reviewer, along with the
average mark.  You can see what was written by the reviewer by
clicking on the `Extent of commenting` number. If any reviews look to
be seriously out of line, you can exclude them by selecting the
checkbox and clicking on `Recalculate grades`.

## Auto-calibration

Reviewers whose marks are generally in line with those given by other
reviewers are probably accurate and careful reviewers. Unusual marks
often arise when a reviewer is less accurate and careful. Aropä can
take this tendency into account in calculating grades. For example,
assume reviewer A is twice as “accurate” as reviewer B. If A gives a
mark of 4 and B gives a mark of 2, then the weighted average will give
twice the weight to A’s mark as to B’s; i.e. $(2 × 4 + 1 × 2)/(2 + 1) =
10/3 = 3.33$, slightly more than the 3 for an equally weighted average.

Auto-calibration can be useful when each submission has at least four
(and preferably many more) reviews. It has the effect of identifying
and reducing the influence of persistently inaccurate reviewers.

The good things about it are: (a) it is automatic; and (b) it assigns
a number to each reviewer. The auto-calibration weights can be used to
give students a mark for the consistency of their reviewing, in
addition to the quality of their submitted work.

The weaknesses are that it requires many reviews per submission, and
the weights (and consequently marks) are difficult to explain to
students.

## Spreadsheet

Use `Download grades` to get a spreadsheet with all the final marks
for each student. The columns in the spreadsheet are:

* the student’s identifier;
* the weighted average mark for each question;
* the total mark;
* the discrepancy between reviewers;
* the number of peer reviews received by the student;
* their reviewer weighting;
* the number of reviews written by the student; and
* the total length of comments written by the student.

If you wish to see all the individual marks given for every review,
use `Download all marks`.

## Summary

The steps to calculating grades are:

* Setup the rubric with one or more radio button groups;
* Associate marks with the buttons using `Label Rubric`;
* In `Calculate Grades`:

  * Decide on which grade calculation options to use;
  * Check for and exclude any “rogue reviews”; and
  * Download your grade spreadsheet!
