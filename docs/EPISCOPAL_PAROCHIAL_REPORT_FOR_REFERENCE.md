# ekklesiaRD -> Parochial Report

## Canonical Status

This file is the authoritative source for the ekklesiaRD parochial report implementation.

- Controller/view contract: `Worship/Controllers/ParochialReport.php` and `Worship/Views/ParochialReport/index.php`
- Report-year persistence: `parish_financial_summary`
- Computed autofill sources: `parishes`, `service_metrics`, `services`, `attendance`, `member_standing_summary`, `annual_pledges`, `donations`, and `expenses`

Implementation rule:

- When this document changes, update the parochial report controller, view, saved-field contract, and any supporting migrations so the runtime form matches this reference.

Page | 1

THE 20____ REPORT OF EPISCOPAL CONGREGATIONS AND MISSIONS
ACCORDING TO CANONS I.6, I.7, AND I.17
(OTHERWISE KNOWN AS THE PAROCHIAL REPORT)

Name of Congregation         Diocese

Street Address 1   City    State

Street Address 2    Zip + 4   County   Country

Mailing Address 1  Mailing City     State

Mailing Address 2  Zip + 4   State  County   Country

Congregation's Email Address  Congregation’s Web Address  Phone

Tax ID

Report Preparation (Section header)

Data and Demographics Prepared by   Email Address:   Daytime Phone:

Stewardship & Financial - Pages  Prepared by  Email Address:   Daytime Phone:

Certification by the Clerk of the Vestry (Section header)
Certified by (Print or type name)     Email Address:   Daytime Phone:
Signature          Date of meeting:

Certified By Treasurer/Financial Officer (Section header)
Certified by (Print or type name)     Email Address:   Daytime Phone:
Signature          Date:

Certification by the Rector/Vicar/Priest-in-Charge (Section header)
Certified by (Print or type name)     Email Address:   Daytime Phone:
Signature          Date:

Warden/Vestry Approval (Section header)
Approved by (Print or type name)      Email Address:   Daytime Phone:
Indicate the date that your 2025 Parochial   Date
Report was approved by the vestry or Bishop's
Committee (CanonI.6.1)

Parochial Report Completion  (Section header)
How many people participated in completing this parochial report?:
Please identify the roles of those who participated in completing this parochial report (Not including vestry members who only
voted to approve it): Examples: Rector/Vicar, Wardens, Vestry Member, Treasurer, Staff Member, Volunteer

Page footer
(Align left)Reports are due to your diocese by March 1, 20___and to the General Convention Office by May 1, 20___       (Align right)To file online visit: <https://reports.dfms.org>

Page | 2
                          _______________________________________________________________________________
(Page header) Congregation                         City                                      Diocese                                    Page 2

Attendance and Impact:
Lines 1 through 5 helps us measure the scope and reach of all parishes while acknowledging that not every participant or person is impacted by the parish is a member. In addition, our hope is that these questions also capture long distance (virtual) engagement.

To calculate “viewership” for online or streamed services please use the combined totals across all streaming platforms (e.g., 5 Zoom
viewers, 10 Facebook viewers, 25 YouTube viewers would be 40 total live viewers). Viewership should measure “unique” viewers (rather
than using viewers as a proxy for household) and includes all viewers, no matter for how long they stayed.

1.- Average Sunday Attendance In-Person
  Sunday (& Saturday Evening) Attendance for In Person Worship.
  Enter your total annual in-person attendance and divide it by the number of Sundays on which in-person services were held.
  Total In-person Attendance ____________ ÷ Total Sum of Sundays Services___________ = ASA (in person):___________

2.-  Average Sunday Attendance Online
  Sunday (& Saturday Evening) Attendance for Online Worship.
  Enter your total annual livestream attendance across all platforms (do not include your ‘viewed after’
  total) and divide by the number of Sunday services held.
   Total Online Viewership________________ ÷ Total Sum of Sundays Online Services_________ = ASA (online):___________

3.-  Average Weekday Attendance In-Person
  Include attendance at all regularly scheduled worship (Weekday Services, Holy Days, or special feasts observed annually).
  Then divide total attendance by the number of weeks in which in-person services were held.
  Total In-person Attendance________ ÷ Total Sum of Weekdays Services________ = AWA (in person):___________

4.-   Average Weekday Attendance Online
  Enter your total annual livestream attendance across all platforms (do not include your viewed-after total) and divide
  it by the number of weeks in which online services were held.
  Total Online Viewership________ ÷ Total Sum of Weekdays Online Services________ = AWA (Online):___________

5.- Please estimate the average number of unique non-live views your main recorded service receives within the week following broadcast.
  Enter your average or estimated average "viewed after" total (do not include your live, online viewers reported in line 2). If you
  have not regularly tracked this number, you may want to briefly look at the data from recent, non-holiday main services to
  estimate the average viewership.
  Average 1-week (unique)::___________  [ ] Estimated

6.-  Outreach beneficiaries:
  Estimated beneficiaries of outreach ministries per week.:___________
  Estimate the average number of individuals who benefit from your outreach ministries each week. This includes
  recipients of food assistance, community support programs, shelter services, educational initiatives, and any other
  outreach efforts provided by your congregation.
  To capture the impact of the parish's outreach efforts, please include both:
   • Internal ministries directly operated by the parish
   • External organizations that the parish financially sponsors or regularly supports through parish-coordinated volunteer work
  When reporting on partner organizations, include only those beneficiaries reasonably attributable to your parish's
  involvement, not the organization's total number served.
  If the number of beneficiaries varies from week to week or occurs over a limited period of time, please provide a
  reasonable weekly average based on recent or typical activity.

7.-  Average volunteers that are not members:___________
  Average number of non-member volunteers for outreach ministries per week.
  Estimate the average number per week of individuals who are not members of your congregation, but who
  volunteer through your parish's outreach ministries.
  Do not include the total number of volunteers at a partner organization; include only those whose volunteer service
  is connected to your parish.

8.-  Total Impact:___________
  Total average impact of parish. (Calculate lines 1 through 7)

Page footer
(Align left)Reports are due to your diocese by March 1, 20___and to the General Convention Office by May 1, 20___       (Align right)To file online visit: <https://reports.dfms.org>

Page | 3
                          _______________________________________________________________________________
(Page header) Congregation                         City                                      Diocese                                    Page 3

Communicants in Good Standing:
Communicants in Good Standing are Baptized members of the reporting congregation, who “have received Holy Communion at least three times during the preceding year” and are faithful “in corporate worship, unless for good cause prevented,” and “in working, praying, and giving for the spread of the Kingdom of God.”

9.- Adult communicants in good standing     (Age 16 and over) = ___________
10.- Youth communicants in good standing     (Under age 16)      = ___________
11.- Total Communicants in Good Standing    (Sum of 9 and 10) =  ___________

Active Baptized Members of the Reporting Congregation at Year-End per Canon I.6.1.1:
For lines 12 and 13, enter the total amount active members and participants for end of report year for 20__.

M__Total Members from 20__(previous year)   _________________
 Please provide the total number of baptized members for 20____.

12.- Increases during year:___________
 All members added to the baptized members section of the congregation's Membership Register during 20___ by: baptism, confirmation/  reception, or transfer; and those persons restored from inactive status, or not counted in last year's membership count.

13.- Decreases during year:___________
 All active baptized members lost by death, transferred to another congregation, removed to inactive status in the Register of Parish    Membership and Rites, removed for other reasons, or not removed from last year's membership count.

M___Total Members for 20____ :_________________
 Add the increases entered in line 12 to Box M___. Then subtract the decreases entered in line 13 for the total active membership as of
 December 31, 20___.

Active Participants in the Reporting Congregation

14.- Active Participants
 Anyone of any age who is active in the congregation and not included in the baptized members listed above

Easter and Christmas Attendance:
For lines 15 and 16, enter the total in-person attendance. Please do not include online participation numbers.

15.- Total In-Person Easter Sunday Attendance (including Easter Vigil):___________
 Record the combined in-person attendance for all Easter Sunday services, including any Easter Vigil services held the evening before.
16.- Total In-Person Christmas Eve and Christmas Day Attendance:___________
 Record the combined in-person attendance for all services held on Christmas Eve and Christmas Day.

Clergy serving this congregation:
This question helps us understand how many individuals undertake work on behalf of the parish, recognizing in particular the contributions of lay staff (paid or unpaid). Unpaid lay staff may include, but is not limited to, nursery workers, Sunday school facilitators, parish administrators, lay preachers, etc. If you had transitions in staff over the course of the calendar year, please simply count the roles in place, not the individuals. For example, if you had a different supply priest every month, enter 1 under part-time clergy, not 12.

17.- Total amount of Clergy and Staff serving this congregation:

Clergy:           Staff:
 ____Full Time Clergy       ____Full Time Lay Staff
 ____Part Time Clergy       ____Part Time Lay Staff
 ____Non-Stipendiary Clergy     ____Unpaid/Non-Stipendiary Lay Staff
 ____Total Clergy Staff:      ____Total Lay Staff        ____Total Staff

 ____Deacons
 ____Priest

18.- Who leads your primary worship service? (Select one)
  [ ] Full Time Priest    [ ] Short Term Supply     [ ] Lay Person
  [ ] Part Time Priest    [ ] Long Term Supply    [ ] Deacon
  [ ] Other

19.- Does your congregation have a unique or unusual clergy situation? (Select all that apply)
  [ ] Long Term Supply or Interim   [ ] Call to Common Ministry  [ ] Clergy from elsewhere in The Anglican Communion

Page footer
(Align left)Reports are due to your diocese by March 1, 20___and to the General Convention Office by May 1, 20___       (Align right)To file online visit: <https://reports.dfms.org>

Page | 4
                          _______________________________________________________________________________
(Page header) Congregation                         City                                      Diocese                                    Page 4

Sacraments & Services:
20.-  How many of the following did your parish provide? (Numeric responses)
 This information should be easily accessed through your parish register. This helps us measure the vitality of your parish through the   provision of sacraments and other lifecycle services. Definitions to guide your entries:: Baptisms: Count each person baptized.;     Confirmations, Receptions: Count each individual confirmed or received into the parish.; Marriages: Count each marriage service    conducted (not each individual).; Burials: Count each burial service, whether held at the parish or elsewhere.; Eucharists: Total number of
 Eucharistic worship services offered, including weekdays and special services.; Daily Office Services: Total number of Morning Prayer,   Evening Prayer, Compline, etc., held as public services (in person or online).

Num. of Individuals:    Num. of services:       Num. of services

 ____Baptisms           ____Marriages           ____Eucharists
 ____Confirmations      ____Burials Daily       ____Office Services
 ____Receptions

21.- Which of the following services/programs/initiatives does your parish have? (Select all that apply).
 This question allows us to measure the vitality of the parish through provision of services and initiatives that help engage the parish and   larger community. Please use the ''Other'' category for categories not listed here (such as international partnerships, interfaith     collaborations, collaboration with community organizations, etc). You can expand on any of these initiatives in questions 24-26.

 [ ] Sunday Morning Eucharist    [ ] Streamed Service     [ ] Children’s Choir       [ ] EcumenicalCollaboration
 [ ] Sunday Morning Prayer     [ ] Interactive Virtual Service   [ ] Sunday School    [ ] Virtual Formation
 [ ] Other Weekend Eucharist    [ ] Paid Musicians     [ ] Sunday Childcare   [ ] In Person Formation
 [ ] Weekday Eucharist      [ ] Volunteer Musicians    [ ] Bible Study    [ ] Campus Ministry
 [ ] Weekday        [ ] Adult Choir      [ ] Outreach Ministries
      Morning/Noonday/Evening Prayer                      (e.g. food pantry, shelter)
 [ ] Other:______________

22.- Does your parish have any subsidiary or associated organizations? (Select all that apply).
 [ ] Preschool or Nursery Program  [ ] K-8 School  [ ] 9-12 School  [ ] Outreach Ministry
 [ ] Other_________________________________________________________________________________________________________

Demographics:
23.-  For which of the following languages do you offer services? (Select all that apply)
 This question allows us to better understand the distribution of languages of worship within the parish.
 It also helps us highlight parishes which multilingual programs and services.

 [ ] English   [ ] French    [ ] Mandarin   [ ] American Sign Language (ASL)
 [ ] Spanish   [ ] Haitian Creole   [ ] Tagalog
 [ ] Other: _____________________________________________________________________________

24.- [ ] Select this box if you offer a service that is simultaneously conducted in more than one language.

25.- Approximately what percentage of your congregation is White, Caucasian, or of European descent?  Enter percentage: ______________

26.- What is the estimated average age of your congregation?  enter age: ______________

Page footer
(Align left)Reports are due to your diocese by March 1, 20___and to the General Convention Office by May 1, 20___       (Align right)To file online visit: <https://reports.dfms.org>

Page | 5

                          _______________________________________________________________________________
(Page header) Congregation                         City                                      Diocese                                    Page 5

Giving Information for 20___

Please indicate the reporting currency:______________________

Stewardship:
Questions about stewardship help illustrate how pledge activity aligns with giving trends over the year.
Example A: On January 1, 20___, St. Paul's had received 84 pledge cards. Five additional pledge cards for 20____ were received later that month, and four new families made pledges during 20____. Enter 93 (84+5+4) on line 1, even though some who signed pledge cards may have died or moved away during the Report Year. On line 2 enter the total pledged from the 93 pledge cards shown on line 1, even though not all pledges

1.- Number of confirmed pledges to the general/operating fund :_____________________
 Total number of pledges (or commitments to give, etc.) made to the operating fund for the year. The number of confirmed pledges to the   general or operating fund counts each distinct pledge made to your campaign. If a family of four makes one pledge as a family, then it   counts as one pledge. If each family member makes their own distinct pledge, then that counts as four pledges.

2.- Monetary amount of confirmed pledges: US DOLLAR :______________      OTHER CURRENCY:______________
 Enter the total dollar amount of all confirmed pledges reported in line 1 above. This line reflects what members promised to give, not what  was actually paid.

Revenue:
Revenue helps us understand the types and sources of financial resources received. A parish may use either cash-basis or accrual-basis reporting, but should apply the same method to both revenue and expense sections.
Example B: A parishioner of St. James Parish makes two contributions to the parish: a $1,000 contribution to fulfill their pledge to the general
fund and a $200 contribution restricted to the Endowment Fund. The $1,000 contribution is reported on line 3, and the $200 contribution is
reported on line 4.
Example C: St. Stephen's Parish leases a portion of their campus to an outside group on weekday mornings, and they receive $2,000 a month
in rental fees. The rental fees are reported on line 5.

3.- Plate, pledge, and other contributions to the general support of the parish    (3)  USD :______________    OTHER :______________
 Enter all giving actually received during the year. Report all unrestricted
 contributions only. Examples include: loose plate offerings, payments toward
 pledges to the general/operating fund, and general gifts not designated for a
 specific purpose. Related expenses: Report corresponding operating expenses
 on Line 6.

4.- Contributions restricted by contributors for specific purposes                                        (4)   USD :______________    OTHER :______________
 Enter all giving actually received during the year that donors have designated
 for a specific purpose. Report only contributions whose use is restricted by the
 contributor. Examples include: gifts designated for outreach ministries or special
 programs; donations restricted for capital projects or building improvements;
 and contributions given for music, altar guild, youth ministry, or other specific
 ministries. Related expenses: Report expenses paid from these restricted funds
 on the corresponding line depending on the nature of the expense —
 Operating-related restricted expenses > Line 6;
 Capital-related restricted expenses > Line 7

5.- All other revenue                (5)  USD :______________    OTHER :______________
 Report all revenue not included in lines 3 or 4. Enter amounts before expenses.
 Examples include: grants from organizations, revenue from fundraising events
 (gross, not net), gross revenue from a thrift shop or similar enterprise, gross rental
 income from facilities or property, interest earned on checking, savings, or other cash
 accounts, and investment income drawn or distributed (but not gains/losses) Related
 expenses: Operating-related restricted expenses > Line 6;
 Capital-related restricted expenses > Line 7. Do not include: realized or unrealized
 investment gains or losses (those are reported on line 9 on the next page.)

          Total Revenue (Lines 3+4+5) = A   USD :______________    OTHER :______________

Page footer
(Align left)Reports are due to your diocese by March 1, 20___and to the General Convention Office by May 1, 20___       (Align right)To file online visit: <https://reports.dfms.org>

Page | 6

                          _______________________________________________________________________________
(Page header) Congregation                         City                                      Diocese                                    Page 6

Expenses:
Expenses help us understand the cost of ministry and operations. Use the same accounting method used for the revenue section.
Example D: St. Bart's Parish has a well-known music program. The parish employs a Director of Music, maintains a pipe organ, and
purchases sheet music to perform. The salary of the Director of Music, the maintenance costs of the pipe organ, and the cost of sheet music
are reported on line 6. If the parish were to expand the pipe organ, that expense would be reported on line 7.

6.- Parish non-capital expenses            (6)   USD :______________    OTHER :______________
 Report all operating expenses. Examples include: utility costs, insurance,
 clergy and staff salaries, wages, and benefits, office and administrative costs,
 diocesan assessments/apportionment, ministry and program costs, charitable
 disbursements, and routine maintenance and repairs. Related revenue: Typically
 reported on Lines 3–5. This will be the largest expense  category for most congregations.

7.- Capital expenses               (7)   USD :______________    OTHER :______________
 Report expenditures for property, buildings, or major equipment. Examples include: purchase
 of property, land, or buildings; major repairs that extend the useful life (e.g., roof replacement);
 building renovations or expansions; purchase of major equipment; and capital improvements
 funded by restricted gifts. Do not include routine maintenance (those go to line 6). If funded by
 restricted gifts > Line 4; If funded by unrestricted sources > Line 5

           Total Expenses (6+7) = B   USD :______________  OTHER :______________

Year End (as of December 31):
Questions about your year-end account balances help us determine the financial assets at the parish's disposal.

8.- All cash, checking, and savings balances          (8)   USD :______________    OTHER :______________
 Enter the total year-end balance of all congregation-held cash accounts. Include: checking
accounts, savings accounts, cash holdings in financial institutions, and clergy discretionary
funds if held under the congregation’s EIN (federal tax id number)
 US DOLLAR :______________      OTHER CURRENCY:______________

Year End (as of December 31) Supplemental Information:
Question 9 provides information about how your congregation manages and utilizes its endowment and investment accounts. The following
definitions may assist you as you complete this section:
• "Dollar amount drawn for use" = how much money the parish took out for operations in the reporting year.
• "Percentage draw" = amount withdrawn / market value at end of prior year.
• Net gains/losses = dividends + interest + realized + unrealized gains/losses - investment fees.

9.- Does your congregation have an endowment or other investment account(s)?  [ ] Yes   [ ] No
 Select Yes if your parish holds any endowments or investment accounts—restricted or unrestricted—that
 generate income, gains, or dividends. Select No if you do not have any such accounts.

 9a. List your financial assets. (Numeric responses)
  Enter the year-end market value for each category:
   • Endowment (restricted): Funds with donor-imposed restrictions.
   • Endowment (unrestricted): Endowments whose use is not restricted by donors.
  Total Endowment: This field is auto calculated from your entries.
   • Non-endowed Investments (restricted): Investment accounts that are not endowments but have donor restrictions.
   • Non-endowed Investments (unrestricted): All other investment or brokerage accounts.
  Total Non-endowed Investments: This field is auto calculated from your entries.
  Total Investments: Grand total of all endowment + non-endowment investments.
   • Enter numbers only (no commas or symbols).
  ______________ Endowment (restricted)      ______________ Non-endowed Investments (restricted)
  ______________ Endowment (unrestricted)    ______________ Non-endowed Investments (unrestricted)
  ______________ Total Endowment             ______________ Total Non-endowed Investments

                                             ______________ Total Investments

9b. What was the percentage draw (the percentage withdrawn for use) i n 20__?   ______________
 Enter the percentage of the investment balance (from the end of 2024) that the parish withdrew during 2025.
 Formula:
  (Dollar amount withdrawn / market value at end of prior year) Å~ 100
  If nothing was withdrawn, enter 0.

Page footer
(Align left)Reports are due to your diocese by March 1, 20___and to the General Convention Office by May 1, 20___       (Align right)To file online visit: <https://reports.dfms.org>

Page | 7

                          _______________________________________________________________________________
(Page header) Congregation                         City                                      Diocese                                    Page 7

9c. What was the dollar amount drawn for use in 20__?
       Operating             ______________
        Funds used for day-to-day ministry or operating expenses.
        This amount should have been reported on Line 6
        (Parish Non-Capital Expenses).
       Non-operating/capital           ______________
        Funds used for capital projects, property improvements,
        or other non-operating purposes.
        This amount should have been reported on Line 7 (Capital Expenses).

9d. What was your net gain/loss (realized & unrealized) in your investment accounts?    ______________

  Enter the total net change in your investments during the year, including:
  • Dividends
  • Interest
  • Realized gains/losses
  • Unrealized gains/losses
  • Minus investment fees
  This number may be positive or negative.

Example E: St. Francis Parish has an endowment fund whose market value at the end of 2024 was $100,000.
The parish has no other endowments or investment accounts. In 2025, the parish withdrew and used $5,000
from the endowment fund. The parish reports $5,000 on line 9c. The parish's percentage draw reported on line
9b is 5% ($5,000 ÷ $100,000).

Example E (continued): In 2025, St. Francis' endowment fund receives dividends of $500 and interest payments
of $250. Total realized gains on investments were $1,000, and unrealized gains were $1,500. The parish paid
investment fees of $100. The parish's net gain/loss on investments, reported on line 9d, is $3,150 ($500 + $250 + $1,000 + $1,500 - $100).

10.- Does your congregation have any outstanding debt, including credit card debt?    [ ] YES  [ ] NO
 Select Yes if your parish owes money to any lender (bank loans, mortgages, lines of credit, etc.). Select No if the
 parish has no debt.

10a. How much?                     ______________
 Enter the total outstanding principal owed at year-end (numeric only).

10b. What is the debt for?
  Briefly describe the purpose of the debt (e.g., roof replacement, building mortgage, equipment loan, capital improvements).
 _________________________________________________________________________________________________
 _________________________________________________________________________________________________
 _________________________________________________________________________________________________

11.- Does your congregation own buildings and/or land?              [ ] Yes              [ ] No
 Select Yes if the parish owns any property—buildings or land—whether used for worship, ministry, rental, or
 other purposes. Select No only if the congregation rents or borrows all of its space. Note: Property legally held in
 trust by your diocese should still be considered owned.

12.- Does your congregation rent or lease all or a portion of your building(s) or land to\ group(s)     [ ] Yes              [ ] No
 outside your congregation?

 Select Yes if you rent out any portion of your buildings or land to outside groups (schools, nonprofits, community
 groups, events, etc.) in exchange for rental income or contributions. Select No if you do not rent any space to
 outside groups. Report only direct rental or leasing activities here. Do not include fundraising events or unrelated
 use of space.

 12a. How much revenue did your parish earn from renting/leasing to outside groups?       ______________
  Enter the total amount received during the reporting year from rental or leasing arrangements with outside
  groups. This includes:
   • Rental fees
   • Contributions made in lieu of rental payments
   • Payments tied directly to space usage
  Do not include income from fundraising events.
  Where this amount is reported elsewhere:
  Include this amount in Line 5 - Other Operating Revenue in the main income section.

Page footer
(Align left)Reports are due to your diocese by March 1, 20___and to the General Convention Office by May 1, 20___    (Align right)To file online visit: <https://reports.dfms.org>

Page | 8

                          _______________________________________________________________________________
(Page header) Congregation                         City                                      Diocese                                    Page 8

 12b. What expenses did your parish incur from renting/leasing to outside groups?      ______________
  Enter the total expenses your congregation incurred specifically because of rental or leasing activities.
  Examples include:
   • Cleaning or janitorial services
   • Additional utilities
   • Security or supervision
   • Repairs or maintenance required due to rental use
   • Administrative costs tied to managing rentals
  If the rental activity required major repairs or improvements, include those capital expenses here as well.
  Where this amount is reported elsewhere:
   • Operating (non-capital) rental expenses → Line 6 - Parish Non-Capital Expenses
   • Capital expenses related to rental activity → Line 7 - Capital Expenses

Page footer
(Align left)Reports are due to your diocese by March 1, 20___and to the General Convention Office by May 1, 20___       (Align right)To file online visit: <https://reports.dfms.org>

Page | 9

                          _______________________________________________________________________________
(Page header) Congregation                         City                                      Diocese                                    Page 9

13.- How is your parish reducing your carbon footprint?  
 The inclusion of this question is mandated by Resolution 2022-D064. This question is optional.

_________________________________________________________________________________________________
_________________________________________________________________________________________________
_________________________________________________________________________________________________
_________________________________________________________________________________________________
_________________________________________________________________________________________________

14.- What is one program or initiative at your Parish that you feel best exemplifies your congregation?
 This short answer question helps us recognize the parish at work every day. This question is optional.

_________________________________________________________________________________________________
_________________________________________________________________________________________________
_________________________________________________________________________________________________
_________________________________________________________________________________________________
_________________________________________________________________________________________________

15.-  What is one program or initiative at your Parish that represents your hope for the future of your congregation or the
 greater Episcopal Church?
 This short answer question helps us celebrate your accomplishments and goals. This question is optional.

_________________________________________________________________________________________________
_________________________________________________________________________________________________
_________________________________________________________________________________________________
_________________________________________________________________________________________________
_________________________________________________________________________________________________

All reports are to be filed online at <https://reports.dfms.org>. If you need assistance
filing your report online, please contact your diocesan office for help.
Page footer
(Align left)Reports are due to your diocese by March 1, 20___and to the General Convention Office by May 1, 20___       (Align right)To file online visit: <https://reports.dfms.org>
