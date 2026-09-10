import { home } from '@/routes';
import { Head, Link } from '@inertiajs/react';

interface ChangelogEntry {
    version: string;
    date: string;
    sections: {
        title: string;
        items: string[];
    }[];
}

const changelog: ChangelogEntry[] = [
    {
        version: 'v3.36.0',
        date: '2026-09-10',
        sections: [
            {
                title: 'RTS — Heat Map (New)',
                items: [
                    'A map of the Philippines now sits under RTS with every province shaded by its RTS rate — the share of shipped value that came back rather than the share of parcels, so a ₱5,000 return doesn’t read as the equal of a ₱200 one — and the period’s rate, parcels returned, orders and sales are stated above it',
                    'Two ways to colour it: RTS on its own, on bands that are fixed rather than taken from the range, so a province is the same colour in June as in August and can be read against itself; or RTS + Orders, which sorts each province into one of the four CSR monitoring groups and prints the handling that group calls for — double confirm, strict validation, strong address and COD confirmation, or ship as normal — beside the areas in it',
                    'Region rolls the same figures up to island groups. The CSR groups rank provinces against one another, which means nothing across five island groups, so that colouring is offered on the province view only and says so when it isn’t',
                    'It opens on the last 30 days rather than the current month — on the 1st there is almost nothing delivered or returned yet, and a map that opens blank reads as broken — and it takes the same team, page and shop filters as the rest of RTS, remembered per workspace',
                    'Destinations the map can’t place are counted as unplaced beside the province count and still added into the totals, so the headline figures cover the whole period rather than only the part that could be drawn',
                ],
            },
            {
                title: 'Call Logs (New)',
                items: [
                    'Every call the phones have synced is now a page of its own under Orders — newest first, with who placed it, the number, the type, how long it ran and the order it was about; the same rows the RMO breakdown showed one day at a time, listed whole',
                    'Each call is labelled RMO Customer, RMO Rider or Order Verification. A number that was on no delivery and on no order confirmed that day reads as Unmatched rather than being filed under a kind of call it wasn’t',
                    'Order verification is new as a kind of call: a CSR ringing a customer on the day their order was confirmed, before the parcel is ever loaded for delivery. An order that syncs in after the call was placed claims the calls that were waiting for it, so a number rung minutes before its order landed doesn’t stay unmatched',
                    'Search takes a phone number or an order id, and the date, persona and call type narrow the list; the order id on a row opens the orders list on that one order rather than searching for the digits',
                    'The page needs the new View Call Logs permission, so someone with role access has to hand it out before it appears in the menu',
                ],
            },
            {
                title: 'CSR Analytics',
                items: [
                    'RMO calls and order-verification calls are counted apart throughout, and the headline row is twelve figures rather than eight: RMO Called, RMO Call Time, RMO Real Conversation and Hit Rate cover the parcel chasing, Total Verification Called, Total Verification Call Time, Total Order needs Verification and Total Verified Orders cover the confirming, and each is still measured against the equally long stretch ending the day before your range',
                    'The breakdown table now carries every column of both nightly reports instead of eleven of them — RMO Answered, RMO Real Conversations, the customer and rider splits, every verification figure, the longest call and the report’s own totals, each sortable — with a columns menu that groups them into the sales report and the call report and remembers what you left showing',
                    'The comparison panel plots any one of those figures, picked from a dropdown, rather than the four it used to tab between; it fetches only the metric you asked for, and a link made back when they were tabs still opens on the metric it named',
                    'A second chart folds the period’s calls into one round of the clock, so the shape that shows is the working day itself — when the dialling starts, where it peaks, and the hours where the calls go out but nobody picks up. Both charts split into All calls, RMO and Verification, and either can be read as a table of figures instead of bars',
                    'CSRs who did nothing in the range are no longer listed. The roster is everyone attached to a workspace shop whether they worked or not, so a live week put 110 rows in the breakdown for the 22 people with figures and pushed them onto page two, and filled the comparison with 0.0% rates that dragged the average line down with them',
                ],
            },
            {
                title: 'Meta Ads Manager',
                items: [
                    'Two more groupings: Optimization Goal, from the ad set, and Campaign Objective, from the campaign — campaigns Meta reported no objective for land in an unassigned row rather than dropping out of the totals',
                    'The filter builder takes more than metric thresholds now: Created Date and Start Date, compared on / before / after / between, and Campaign Objective is / is not, picked from the objectives actually running in your accounts. A date is only offered where the breakdown you are on carries it — an ad account has neither, so a filter that could only be ignored isn’t listed',
                    'The day-by-day timeline that opens from a row now honours the filters the grid is under, so an objective filter narrows the chart to the same ads it narrowed the row to instead of plotting all of them',
                    'When Meta renders nothing for a creative preview, other placements are tried and then the account’s other linked logins — a colleague with a role on the page renders the same ad fine — and the drawer says which placement it settled on; a post that has been deleted falls back to the creative we synced, with links out to Ads Manager and the Ad Library',
                ],
            },
            {
                title: 'Orders',
                items: [
                    'The date range can be pointed at any of the order’s own dates — Created (Pancake), Confirmed, Shipped, Delivered, Returning or Returned — instead of always filtering on the Pancake created date, and which date it applies to sits beside the picker, because moving a range from the confirmed date to the delivered one is a change to the filter rather than a setting somewhere else',
                    'Every row carries the customer’s RTS risk — Low, Medium, High or No report, read off that phone number’s own return history, the same rate the CSR verification card ranks on — and the column sorts, so the riskiest customers in a batch come to the top',
                    'A Customer RTS filter narrows the list to customers with a return history or without one, and to a rate greater than, less than, equal to or between the figures you type. No report is its own answer rather than a low one: an unknown customer is exactly the case the verification call exists for',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'Leaving the team schedule with unsaved shifts now asks first — the week arrows, the sidebar, the browser’s Back and closing the tab all stop and offer to save on the way out, where before the edits simply went',
                    'The RMO call cards and the daily Discord report count RMO calls only — the customer and the rider on that day’s deliveries — and are named Total RMO Calls and Total RMO Call Duration to say so; counting every call synced put the total above the sum of its own parts in the breakdown beside it',
                    'A call now counts as connected at 3 seconds rather than 5, on the cards, in the nightly reports and in the Discord post alike',
                    'CSR Analytics follows the viewing as team switcher: both nightly reports are written per shop now, so picking a team leaves only that team’s shops on every figure on the page',
                ],
            },
        ],
    },
    {
        version: 'v3.35.0',
        date: '2026-09-03',
        sections: [
            {
                title: 'Sales & Marketing — Dashboard (New)',
                items: [
                    'A Dashboard page now opens the Sales & Marketing group with four headline tiles — Sales, Ad Spend, Blended ROAS and RTS Rate — each loading and refreshing on its own, so a slow figure never holds up the rest of the row, and the window you pick is remembered per workspace',
                    'Blended ROAS carries the attributed figure beside it: what the ad platform credits to the ads, against every peso of sales over every peso of spend — the gap between the two is the sales your ads were never credited with, which is the whole reason both are shown',
                    '“Leaders for the period” names who spent the most, who sold the most, whose ads worked hardest and whose parcels came back least, each with their share of the workspace total so a big number reads as a standout rather than just a big team',
                    'Team and product comparison charts sit below, each with a breakdown table beneath it stating exactly what the chart plots, and you can switch between Sales, Ad spend, ROAS and RTS without the page fetching anything again',
                    'The old tabbed dashboard is now five sibling pages in the menu — Dashboard, Daily Report, Ad Spent Summary, Sales Targets, and the trackers beneath them — with the previous links redirecting, and every role that could open the old dashboard can still open all five',
                ],
            },
            {
                title: 'CSR Analytics',
                items: [
                    'Eight figures now head the page — sales, RTS rate, RMO called %, total call time, calls placed, real conversations, reach rate and the longest call — each measured against the equally long stretch ending the day before your range, so the arrow beside it tells you whether the period actually moved',
                    'A comparison panel plots the whole field on whichever of four metrics you pick, every CSR against the period’s average and against their own previous figure; each person keeps the same colour as you switch tabs, and the tab you were reading survives a reload or a shared link',
                    'A daily chart sets calls placed against the ones that became real conversations, with a table beneath it splitting each day into never answered, answered and conversations plus that day’s hit rate — where the two bars sit furthest apart is effort spent without return',
                    '“Leaders for the period” names the CSR with the highest sales, the lowest RTS, the most RMO calls and the most time on the phone',
                    'The breakdown table keeps its header and the CSR name in place as you scroll across the eleven columns of figures, so a row of numbers is never stranded from the person it belongs to',
                ],
            },
            {
                title: 'RMO Management',
                items: [
                    'Orders you tick stay ticked as you page through, sort, search and narrow by assignee or confirmee, with a count of how many are selected on pages you can’t see; only changing the delivery date clears the selection, because that is the one change that makes the old set meaningless',
                    'Every shop now carries an RTS snapshot — its value-weighted return rate over the previous 14 days, refreshed twice daily — so you can tell at a glance whether a parcel comes from a shop that usually lands',
                    'A new Upsell filter picks out the orders carrying an upsell, or leaves them out, on the table and in the export alike',
                    'A day’s calls open as a breakdown from the row itself, so you can see how they went without leaving the list',
                    'Changing the date range no longer drops the other filters you had set',
                ],
            },
            {
                title: 'Finance — Income Statements',
                items: [
                    'A month can now be locked once it is settled: regenerating, overwriting and deleting are all refused until someone deliberately unlocks it, so a statement you have already reported on or paid against cannot quietly change when a late order lands — and the lock records who closed the month, and when',
                    'A new per-seller-per-product statement answers “how did this person do on this product”, which neither of the existing views could — one adds a person’s products together, the other adds a product’s sellers together — and each seller’s figures now open on a page of their own',
                    'OPEX is broken down by transaction type, one line per type with outflow that month, and every row keeps the share of the pool it was given beside the amount it produced, so a saved statement can still say what split it actually used after a later sync moves the counts underneath it',
                    'A deficit can be carried into a month against a seller and a product, and it survives a regenerate — the figure you typed is kept apart from the snapshots that get rebuilt on every save, and can be entered before the month has ever been closed',
                ],
            },
            {
                title: 'Meta Ads Manager',
                items: [
                    'Two new groupings, Page and Page Owner, bucket ads by the Facebook page their ad set promotes and by the workspace member who owns that page; ads whose page has been deleted, or whose owner no longer has an account, land in an unassigned row rather than vanishing from the totals',
                    'Any row in the breakdown opens a timeline of its day-by-day figures, with every day in the range drawn even where the ads did not run, so a quiet stretch reads as a gap instead of closing up',
                    'Each ad account now lists the people who can reach it, refreshed from Business Manager once a day',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'Gencys partners no longer see the Page ROAS Tracker, or the Ad spend and ROAS tabs on the product comparison — those are tracked in Gencys itself and the ad spend isn’t attributed per product on our side, so figures that could only be half stated have come out rather than sitting there as sums nobody can act on',
                    'Shipping fees can be imported onto orders from a spreadsheet, straight from the orders page',
                    'Checking off a checklist item now takes a proof file and a note alongside it',
                    'Intern daily records are now part of the Gencys batch sync rather than a separate pass',
                    'Parcel journey has its own stat cards and a per-shop table, each loading and refreshing on its own, and the CSR menu group is now called Operations',
                ],
            },
        ],
    },
    {
        version: 'v3.34.0',
        date: '2026-08-30',
        sections: [
            {
                title: 'Gencys ERP — How the Sync Runs',
                items: [
                    'Transaction history and purchase orders are now fetched a whole day — or a whole date range — at a time instead of one product at a time, so a pass that used to open an ERP session for every item on your list opens one and brings the entire report back',
                    'Because the report is taken whole, a day’s movements are no longer limited to the products you had registered here: everything the ERP lists comes back, including items nobody has set up in Artemis yet',
                    'Sync Batches reads differently as a result — a run is now a date, or a purchase order’s date range, rather than a product — so a pass that used to be hundreds of rows to scroll through is a handful you can actually follow, and there is only ever one of them out at the ERP at a time',
                ],
            },
            {
                title: 'Inventory — Items & Purchase Orders from the ERP',
                items: [
                    'An item the ERP names that we don’t recognise is now created here rather than dropped — it is matched first on its SKU, then on its transaction keywords, and created switched off if neither hits, so a day’s stock movements are never quietly short a product just because the two systems spell it differently; worth looking over the inactive items after a sync and merging or keywording anything that should have matched',
                    'A purchase order’s lines are now written to exactly what the ERP reports rather than added to, so an order whose product resolves somewhere new carries one line instead of both — left to accumulate, the same goods would have been counted twice against the stock the order shows as owed',
                    'Approved, To Pay, Paid, For Purchase and Purchased dates are filled in on every purchase order from its own status trail now that the ERP has stopped sending them as separate fields — so when an order reached each stage is still on record rather than going blank from here on',
                ],
            },
            {
                title: 'Admin — Workspaces',
                items: [
                    'A workspace whose subscription is past due or expired no longer throws its subscription dialog open the moment the list loads — it was interrupting whatever you came to the page to do, and the same dialog is still a click away on the row',
                    'The arrow that drops you straight into a client workspace now shows only for the primary admin account; everyone else works from the admin panel, where the workspace’s figures are already on screen',
                    'A super admin going to the dashboard now lands in the admin panel rather than being sent into a workspace, which is where signing in already put them — the two disagreed, so the link out of a workspace bounced you somewhere you had not asked for',
                    'A Finance admin account is created on every environment, verified and ready to sign in, so the finance side no longer has to share the main admin login',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'Saying who you are in the Logged in as picker no longer narrows the five RMO call cards — that is who you are, not a filter, so the page opens on the whole workspace’s day and only My Assignee Only or a name in the assignee picker cuts the cards down; last release had identifying yourself filtering them too, which left the cards describing one person while the list beside them described everyone',
                    'A delivery the ERP logs with no quantity against it — a settlement note rather than a receipt — no longer lands on the purchase order as a delivery of nothing',
                ],
            },
        ],
    },
    {
        version: 'v3.33.1',
        date: '2026-08-28',
        sections: [
            {
                title: 'RTS — RMO Call Cards',
                items: [
                    'The five call cards now report the calls one person actually placed rather than the whole workspace’s day — they follow the assignee you have picked, or failing that whoever the Logged in as picker says you are, so “my call logs” means the calls you made; left on All Assignees with no identity set they still cover the workspace’s whole day, as before',
                    'They follow the page and shop filters too, so the cards describe the rows on screen; previously they reported every call logged that day whatever you had narrowed the list to, which is what made them look stuck',
                    'The cut is by whoever dialled, not by whose orders the numbers belonged to — a CSR rings plenty of numbers that are not on the orders assigned to them, and counting against their order list was the wrong question and the reason the figures read as though the filters were being ignored',
                ],
            },
            {
                title: 'RTS — RMO Management',
                items: [
                    'The stat cards load on their own now instead of holding the page up: sorting, turning a page and typing in the search box no longer pay for six day-wide aggregates each time, and the cards only re-ask the server when something they actually depend on changes',
                    'The filter bar has an All Assignees picker, so you can read someone else’s day by name rather than only your own through My Assignee Only — the toggle still wins while it is on, and the picker greys out with a note saying so instead of being quietly ignored',
                    'While a figure is on its way the card shows a placeholder bar at its usual height rather than the previous number, so nothing reflows underneath you and a stale figure can’t be misread as the fresh one — and nothing is fetched at all while the cards are collapsed, which is how they start',
                    'Changing who you are logged in as now re-runs the table and the cards, instead of leaving both sitting on the previous person’s orders until you happened to touch a filter',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'Editing a course now saves — previously the form submitted as though it were creating a new one, so the update was rejected and the change never landed',
                    'Avg Call Duration and Hit Rate are worked out in one place shared with the daily RMO Discord report, so the card and the report cannot drift apart on what a hit rate means',
                ],
            },
        ],
    },
    {
        version: 'v3.33.0',
        date: '2026-08-28',
        sections: [
            {
                title: 'Courses (New)',
                items: [
                    'Courses is now its own admin-toggled module: a workspace switched onto it gets a Courses catalogue in the sidebar, where a course is built as modules with lessons inside them, each lesson carrying a video, and published only when you are ready — a draft is visible to the people who can edit courses and to nobody else, so half-built material never turns up in front of a learner',
                    'A learner opens a course into a player — the lesson list down one side, the video beside it, Mark complete and Next underneath — and Start becomes Continue afterwards, dropping them back at the lesson they left off on rather than at the beginning',
                    'Lesson videos upload straight from the browser to storage rather than through Artemis, so a large file no longer has to survive the trip through the app, and each lesson’s length is read off the file as it goes up — the outline shows a duration per lesson and the course header the total',
                    'The catalogue shows each person what is theirs to see: someone who can edit courses gets the whole catalogue including drafts, how many people enrolled and the average completion across them, while everyone else gets the published courses and their own progress through the ones they picked up',
                    'A completion leaderboard ranks the ten members who have finished most of the workspace’s material, and each course carries a How The Team Is Doing panel listing everyone who started it and how far through they are — a course’s completion rate counts only the people actually enrolled, so material nobody opened cannot drag the figure down',
                    'Courses has its own permissions — view, create, edit and delete are each separate — so nobody but a workspace owner sees it until a role has been granted them; check your roles after this release if your team is meant to have it',
                ],
            },
            {
                title: 'Finance — Income Statements Rebuilt',
                items: [
                    'A statement now reads as one column of figures, from delivered orders and revenue down through shipping, ad spend, the COD fee and its VAT to gross profit and the advisory share — and the same figures, in the same words, carry across the workspace statement and its per-product and per-user tables, so a number can be followed through all three instead of each page having its own shape',
                    'Cost of goods can be read two ways and you switch between them: Delivered COGS charges what actually shipped, which is the truer margin on the month’s sales, while Bought COGS charges what was purchased into stock plus the freight on it, which is what the month cost in cash — they answer different questions, so neither is picked for you',
                    'The advisory share is now struck two ways — a percentage of gross profit, or a percentage of delivered revenue — and the lower of the two is what is charged, with the statement naming which basis applied that month; a loss-making month owes nothing rather than earning a rebate',
                    'Income statements are no longer Gencys-only: a workspace that is not a Gencys partner now takes its delivered revenue, shipped parcels and courier fees from its Pancake orders and gets a statement in exactly the same shape',
                    'Saving now includes every line rather than only the ones you ticked — the cost-of-sales and OPEX checklist has gone from the statement page, and the Expenses and Net Profit columns on the statements list have been replaced by Orders and Gross Profit',
                ],
            },
            {
                title: 'Finance — Per-Product & Per-User Statements',
                items: [
                    'Both tables have been rebuilt onto the figures above: every product, and every intern, with delivered orders and revenue, shipping, ad spend, the COD fee and its VAT, cost of goods on whichever basis you are reading, gross profit and the advisory share taken off it',
                    'Net profit per intern, the per-intern drill-down page and the per-product commission rates it carried have gone with the rebuild — the per-user table now stops at gross profit after the advisory share, the same place the product table and the workspace statement stop',
                    'The header row and the first column stay put as you scroll, so a figure ten columns to the right still says which product or which person it belongs to, and every column carries a question mark spelling out what it counts and how it was worked out',
                    'Shipping counts parcels by the day they went out and counts them whatever became of them — you pay the courier for a return too — so a row’s shipped and delivered counts are not meant to agree, and the column note says as much rather than leaving it to look like an error',
                    'Ad spend is counted only where it was actually tagged to a product; spend nobody attributed stays out of the per-product figures rather than being spread around on an assumption',
                ],
            },
            {
                title: 'RTS — RMO Call Statistics',
                items: [
                    'RMO Management carries five more cards alongside the delivery counts — Total Call Logs Synced, Total Call Duration, Connected Calls (5s+), Avg Call Duration and Hit Rate — so the day’s calling effort reads beside what it produced instead of only the orders it touched',
                    'A call has to last five seconds before it counts as connected; anything shorter is the dial tone and a hang-up, and keeping those out of the numerator is what makes a hit rate worth reading',
                    'The call cards report every call the workspace logged for that delivery date, whoever placed it and whatever number it reached, so they stay still when you narrow the list underneath rather than moving for reasons the cards don’t explain',
                    'Total talk time on the call KPI now counts every call a person made that day; previously it counted only calls whose number matched an order due for delivery that day, so time spent on anything else quietly vanished from the figure',
                    'CX CALL ENDED and RIDER CALL ENDED have been added as RMO statuses, each with its own badge',
                ],
            },
            {
                title: 'RTS — Daily RMO Discord Report (New)',
                items: [
                    'The day’s RMO numbers can now be posted to Discord once a day — for delivery, called, delivered, returning and problematic, plus the five call figures — covering the whole workspace across all users, and quoting the same figures the RMO page shows so the report and the page cannot disagree',
                    'Set it up on the RMO management settings page: switch it on, paste your webhook and pick the hour it should send, whole hours only',
                    'It sits behind its own Manage RMO Notifications permission on top of the one that opens the settings page, so nobody sees the webhook field until a role has been granted it, and it is off by default — an existing workspace will not start posting because it was upgraded',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'The Inventory Items table now pins its header row and the SKU column as you scroll — with thirty-odd report columns running well past the fold, a figure ten columns right was unreadable when you could no longer see which item or which measure it belonged to',
                    'Unit Codes has a No product switch that narrows the list to codes with nothing linked yet, so the mapping still left to do is one toggle away rather than a page-by-page hunt',
                    'An OPEX transaction type now records which company metric its shared cost should be split across products by — Delivered parcels, Total orders or Delivered revenue — shown as a Split by column on the Transaction Types list, because a CSR’s salary tracks every order taken while warehouse costs track only the parcels that actually went out',
                    'The ERP sync now runs five times a day rather than four, and the inventory snapshot passes have been retimed alongside it, spreading both further across the working day',
                ],
            },
        ],
    },
    {
        version: 'v3.32.0',
        date: '2026-08-25',
        sections: [
            {
                title: 'Gencys ERP — Sync Batches (New)',
                items: [
                    'Every pull from Gencys is now a batch you can watch: a Sync Batches screen shows which one is holding the ERP right now, what is queued behind it, how far through its items it has got and how many came back short — so a sync that quietly did nothing is something you see the same morning rather than something you work out days later from a figure that looks wrong',
                    'Open a batch to see each item or date inside it, whether it came back, how many rows it brought, how long it took and what went wrong where something did — the detail that used to exist only in the logs, on the screen next to the thing it explains',
                    'You can raise a batch by hand for a date range and whichever syncs you tick, and cancel one mid-flight when you would rather it stopped, instead of waiting for the next scheduled pass to come round',
                    'The old Sync Health screen under Inventory is gone — what it showed now lives on these batch pages under Gencys ERP, covering the daily sales tracker as well as transactions and purchase orders rather than only the latter two',
                    'Sync Batches has its own permission, so nobody but a workspace owner sees it until a role has been granted it — check your roles after this release if your team watches ERP syncs',
                ],
            },
            {
                title: 'Gencys ERP — How Syncing Runs Now',
                items: [
                    'Syncing no longer fires everything at the ERP on a timer and hopes for the best: one batch works at a time and only asks for the next group of items once the previous group has answered, which is what stops the ERP rate-limiting the very sync trying to read it',
                    'An item whose answer never arrives is retried on its own rather than dragging its whole group back with it, twice before it is written off, and the batch carries on past it instead of stalling behind one bad SKU',
                    'A pass that starts while the one before it is still working now waits its turn instead of being dropped, and a pass asking for exactly what is already queued joins that batch rather than stacking a duplicate on top of it',
                    'A day of the sales tracker too large to come back in one piece is no longer treated as finished when its first instalment lands — it stays open until the last one arrives, so a busy day is recorded whole instead of truncated at whatever turned up first',
                    'The separate scheduled jobs for transactions, purchase orders and the daily sales tracker are now one, so a single run of it is a single batch you can follow from end to end',
                ],
            },
            {
                title: 'Gencys ERP — Checking a Sync Against n8n (New)',
                items: [
                    'Any item on a batch can be checked against n8n from the row itself — it reports whether that run succeeded, failed or is still going, shows the error n8n recorded, and links straight to it, so working out why a sync came back empty no longer means hunting through n8n by timestamp (this needs your n8n API details on file; without them the check simply is not offered)',
                    'A failed item can be sent again from the same row, which re-reads your ERP credentials and the current state rather than replaying the old request — offered only while nothing else is syncing, since one batch holds the ERP at a time',
                    'Where n8n has not kept a run — successful ones are commonly discarded, depending on how it is set up — the check says exactly that, rather than reading as though something had broken',
                ],
            },
            {
                title: 'Inventory — Saved Figures',
                items: [
                    'The day’s figures are no longer frozen while an ERP sync is still waiting its turn: a sync raised but not yet sent holds the save back just as one already in progress does, so a day cannot be recorded from half its stock movements and then read afterwards as though it were complete',
                    'A sync still working after midnight holds the next morning’s save back too, rather than being passed over because it happened to start the day before',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'SMS delivery reports no longer read the whole message table on every push — both the delivery-report lookup and the duplicate check on incoming messages are indexed now, which at delivery-report volume was on its own enough to keep the database busy and slow everything else down with it',
                    'Parcel journey notifications are indexed by the SMS they belong to, so a delivery report updates one row instead of scanning one of the largest tables in the system from end to end',
                    'The For Delivery and RMO Management lists no longer work out a risk score for every row that nothing ever displayed — it was being computed on each load and each export and then thrown away, so both come back quicker',
                ],
            },
        ],
    },
    {
        version: 'v3.31.0',
        date: '2026-08-20',
        sections: [
            {
                title: 'Billing — Invoices (New)',
                items: [
                    'Billing is now its own admin-toggled module: a workspace switched onto it gets an Invoices screen listing every invoice raised against it — number, who it is billed to, total, issue and due dates and status — searchable, sortable, and each one downloadable as a PDF, so the people actually being billed can pull their own copy rather than asking for one',
                    'An invoice past its due date and still unpaid is marked overdue on that list instead of sitting quietly as “sent”, so the ones worth chasing are the ones that stand out',
                    'Workspace Settings gains a Billing page holding the name, address and email invoices should be made out to — these prefill the bill-to fields when an admin raises an invoice, and anything left blank falls back to the workspace owner’s own account details',
                    'Proof of payment can be filed against an invoice at the moment it is marked paid, in the same step rather than a second one that is easy to forget, and viewed or replaced from the admin invoice list afterwards',
                    'Raising an invoice that already falls due today, or in three or five days’ time, emails the client straight away with the invoice attached, rather than waiting for the next morning’s run to catch it',
                ],
            },
            {
                title: 'Billing — Subscription Reminders',
                items: [
                    'Every workspace whose subscription ends in five days, three days, or today now gets an email each morning — a trial reminds against its trial end date and a paid subscription against its renewal date, and an account already past due is chased rather than left alone',
                    'The reminder goes to the workspace’s billing email where one is set and to the owner’s address otherwise, and it names the plan and what it costs, so whoever reads it knows exactly what is about to renew',
                    'A subscription’s billing period can now be set by hand when an admin edits it instead of always being worked out from the plan — leave either date blank and that side falls back to the plan’s own dates, and a period ending today runs through the end of the day rather than lapsing at midnight',
                ],
            },
            {
                title: 'Inventory — The Planning Report on the Items List (New)',
                items: [
                    'The Items list gains a Columns chooser and, behind it, the whole planning report: orders and units per day over three, seven and fourteen days, whether demand is rising or falling, stockout risk, when stock last came in and last went out, the last PO raised, what has been raised but not released to a supplier, the earliest expected delivery, the longest-waiting order, how many are delayed, and which stage is holding the group up',
                    'Each of those headers explains what it counts, and your choice of columns is remembered in your own browser — so a screen set up for reordering stays that way without being imposed on everyone else',
                    'The spreadsheet export carries every report column whether or not it is switched on, because a download is read away from the app and a column someone forgot to tick is one they cannot get back without asking for another file',
                    'The list now shows every item the workspace holds and reads the day’s saved figures onto it, so an item added or synced since the last save appears straight away with dashes where its figures would be — previously it stayed invisible until the next overnight run, which made a fresh sync look like it had done nothing',
                    'Those figures are now saved five times through the day rather than once at night, and each save recomputes demand from the order feed in the same pass, so the page can no longer show one run’s demand against the next run’s stock',
                ],
            },
            {
                title: 'Inventory — Purchase Orders & Shippable Stock',
                items: [
                    'Every purchase order now carries an expected delivery date — the one you enter, or two weeks from the issue date if you leave it blank, which is the standing agreement — so “is this late?” has one answer wherever it is asked rather than each screen inventing its own',
                    'An item now names the stage actually holding it up — a PO never raised, an approval sitting too long, or stock a supplier has still not shipped — so a row asking you to buy stock also tells you who to chase',
                    'The dashboard’s shippable-stock tile now counts everything that could leave today, matched per SKU because stock on one variant cannot ship an order placed against another, and it reads as plain reporting rather than an alarm — whether any of that stock has stalled is the warehouse card’s question, not this one',
                ],
            },
            {
                title: 'Meta Ads — Calendar, Reports & Rules',
                items: [
                    'The Ads Calendar can be grouped by page owner as well as by page, switched with a toggle, and the filter beside it follows whichever you are looking at — so “who launched what this week” is one click away from “which pages launched what this week”',
                    'Ad reports gain date filters on the rows themselves — is on, before, after, or between two dates — each one seeded to the report’s own window rather than to today, so adding a filter narrows the report instead of blanking it and looking broken',
                    'An optimization rule can now be narrowed to particular Facebook pages instead of applying to everything in the ad accounts it covers; leave the pages empty and the rule behaves exactly as it always has',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'SMS is now permission-controlled — SIMs, Send SMS and Outbox each have their own permission, and nobody but an owner sees them until a role has been granted them, so check your roles after this release if your team sends SMS',
                    'A forgotten password can be reset from the sign-in screen: the link arrives by email, expires, and works only once, with a cap on how often one can be requested',
                    'A row’s call badge on RMO Management now counts every call made against the order rather than only the current assignee’s, and the log names whoever actually placed each one — previously a reassigned order could read “0 calls” and then open onto a full history',
                    'RMO rows now carry the upsell price and order details from Gencys, kept as they read at the time rather than following later edits to the source order',
                    'Dashboard breakdowns can be read as a sortable table instead of bars, ordered by name or by the figure, showing the same rows either way',
                ],
            },
        ],
    },
    {
        version: 'v3.30.0',
        date: '2026-08-10',
        sections: [
            {
                title: 'Finance — Product Income Statements (New)',
                items: [
                    'New per-product breakdown on a saved income statement — every product’s orders, delivered revenue, cost of sales, gross profit, advisory share and net profit on one page, so you can see which products actually earned the month rather than only what the workspace made in total',
                    'A margin bar across the top splits the month by product, and each row carries its net margin, so a product turning over a lot at a thin margin stops hiding behind a big delivered figure',
                    'Revenue that can’t be traced to a product is shown as its own discrepancy row instead of being quietly dropped, with the unit codes behind it listed so you can map them and have the gap close',
                ],
            },
            {
                title: 'Finance — Commission Rates',
                items: [
                    'Each intern can now carry their own commission rate per product, set on their income-statement breakdown, and the commission it works out to shows beside the product’s net profit',
                    'The rate is a share of a product’s net profit — after cost of goods, shipping, COD, VAT, ad spend and the advisory share — and only products that actually made money count towards it',
                    'It is shown for reference and changes nothing about the statement itself: net profit, gross profit and the workspace totals all read exactly the same whether a rate is set or not',
                ],
            },
            {
                title: 'Finance — Transaction Types Rebuilt',
                items: [
                    'A transaction type now carries its own nature — Credit for money in, Debit for money out — and the direction of a transaction follows it, so the separate IN/OUT field has gone from the form; picking “Type of Expense” is now the one decision that sets it',
                    'Where a type lands on the income statement is now a three-way choice rather than a tick — Cost of Sales, OPEX, or Excluded altogether for movements that belong on the balance sheet, like capital spend, dividends and cash advances, which were previously forced into OPEX',
                    'Both are shown as their own columns on the Transaction Types list, and a workspace can have the standard set of categories seeded in one go, each already tagged, rather than typed in one at a time',
                ],
            },
            {
                title: 'Finance — Cost of Goods Reworked',
                items: [
                    'Cost of goods now comes from a Cost of Goods transaction tagged to a product, bought in bulk, rather than being derived per order — the figure follows what you actually paid the supplier',
                    'That cost is split between the interns who sold the product by how many delivered orders each of them had, so a product bought once and sold by three people charges each of them their share',
                    'Ad spend now appears in the per-product breakdown too, apportioned the same way, and the per-intern summary keeps a slim Delivered → Cost of Sales → OPEX → Net shape with the detail moved into the product table underneath',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    '“Back to live data” on the Inventory Items list now clears the date field itself — previously the list underneath went back to live while the picker carried on showing the day you had pinned',
                    'Remittances has been taken out of the Finance sidebar and off the Live Cashflow page while it is reworked; existing remittances are still reachable by their own links and nothing has been deleted',
                ],
            },
        ],
    },
    {
        version: 'v3.29.0',
        date: '2026-08-10',
        sections: [
            {
                title: 'Gencys ERP — Sync Health',
                items: [
                    'The daily sales tracker now shows up on Sync Health alongside the other ERP syncs — every workspace and date it fetches opens its own run and closes it when the data lands, so a fetch that quietly never came back is visible on the page instead of being noticed days later when a figure looks wrong',
                    'A successful sync now clears the earlier attempts that asked for exactly the same thing — same workspace, same date, same item — because once the data is in, a run still sitting pending or failed is stale bookkeeping rather than a gap; each one is stamped with the run that resolved it, so the trail still reads',
                    'Runs that were already stuck before this existed can be swept in one go, across all history or just a recent window, with a dry run first so you can see what would change before anything is written',
                ],
            },
            {
                title: 'Sales Targets — Team Attribution Corrected',
                items: [
                    'A team’s sales on the gameboard are now counted through the shop an order came in on rather than through whoever owns the page — someone can sit on several teams, so an order was being credited to every team its page owner belonged to and inflating all of them at once',
                    'Shops are assigned to teams deliberately by an admin, so an order now lands on one team unless a shop has been shared on purpose — and the day’s headline total still counts each order once even when it has been',
                    'This is the same link that decides which orders a team is allowed to see, so a team’s number on the board now reconciles with the orders its members can actually open — expect some team figures to move, downwards where people were being double-counted',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'On the TV slideshow each team’s bar now reads against its own target with the axis ending at 100%, so a team that has beaten its goal sits full instead of the whole scale stretching to make room for it — the exact figure is on the tile above either way',
                    'The ERP syncs are now staggered a quarter of an hour apart through the morning and afternoon — sales tracker, then intern records, then inventory — rather than landing together and competing for the same ERP session',
                    'Parcel SMS delivery checks have moved to their own queue with more workers, so a pile-up of status checks no longer holds up the messages waiting to go out',
                ],
            },
        ],
    },
    {
        version: 'v3.28.0',
        date: '2026-08-07',
        sections: [
            {
                title: 'Pages — Auto Update Budget (New)',
                items: [
                    'Each page now decides for itself whether Meta is allowed to overwrite its daily ad budget — a new Auto Update Budget column on the Pages screen with a switch reading either Auto or Manual',
                    'Every page starts on Manual, so a budget you type on the Pages screen stays exactly as you left it; switching a page to Auto is a deliberate decision to let Meta’s figure win from then on, and the four-hourly snapshot then replaces that page’s budget on its own',
                    'The switch flips straight away and rolls back with a message if the save fails, so you are never left looking at a setting that did not actually take',
                ],
            },
            {
                title: 'Sales Targets — Public Gameboard',
                items: [
                    'The public gameboard is now opened from a target’s own page rather than from the targets list — a board is always scored against one specific target, and the old link had to guess which one you meant',
                    'The board is noticeably quicker to come up: it now asks the server only for things it cannot work out itself — the day’s measured totals, each team’s goal and actual, and the leading team’s trend — and does the percentages, ROAS, ranking, achievement bands and leaderboard order in the page',
                    'Each section still loads and fails on its own, so one slow panel degrades a corner of the board rather than blanking the whole wall display',
                ],
            },
            {
                title: 'Inventory — When Stock Was Last Ordered and Last Moved',
                items: [
                    'Low Stock Items gains a Last PO Issued column, with how long ago underneath — an item that needs stock and was last ordered two months back is a different problem from one ordered yesterday, and the reorder figure alone could not tell them apart',
                    'An item that has never been ordered at all says so in red rather than showing a blank, because on a row already asking you to buy stock that is the loudest thing on the line',
                    'The unfulfilled breakdown gains Last PO In and Last PO Out — when stock last arrived and when it last went out. On a row holding stock that could ship today, an out date more than a week old turns amber: the stock is here and none of it has left',
                    'Cancelled orders are ignored when working out when something was last ordered — an order you called off is not a time you ordered the item — while delivered and closed ones still count',
                ],
            },
            {
                title: 'Inventory — Corrected Figures',
                items: [
                    'The unfulfilled split now compares each group’s totals instead of each variant separately, which moved roughly 1,400 units out of “no stock” and into “could ship today” — stock sitting on one variant was being written off as unsupplied because it could not be matched against a sibling’s demand',
                    'Supplier lateness is measured against a flat 14-day delivery target rather than a figure read off the item’s lead time — that field is the same default on almost every item, so treating it as a supplier’s quote implied a precision it never had',
                    'The delivery curve — first delivery, then 30, 60, 90 and 100% of an order landing — is now measured from the day an order was raised rather than the day it was released, which is both the number that decides whether stock arrives before you run out and one that can be worked out for every order rather than the handful carrying a full status trail',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'Date filters now follow the page when it changes the date for you — using “Back to live data” on the Inventory Items list left the box still showing a date that was no longer filtering anything',
                    'The ROAS colours on the Page ROAS Tracker have moved: 3.00 and above is now the green bar, 2 to 3 reads as short of it, and below 2 is a deep red with white text so a bad day is legible across a room',
                ],
            },
        ],
    },
    {
        version: 'v3.27.0',
        date: '2026-08-06',
        sections: [
            {
                title: 'Inventory Dashboard — Built-in Explanations',
                items: [
                    'Every panel and headline figure on the Inventory Dashboard now carries a question mark that explains how to read it — what the number counts, where it comes from, and what would make it go down — so nobody has to be walked through the page by someone who already knows it',
                    'The three bottleneck cards spell out exactly what puts each of them in the red: Operations the moment any stock breaches the week-long target, the Supplier once more than 40% of released stock is overdue, the Warehouse above 15% of unmet demand — the verdict is checkable rather than something to take on faith',
                    'The notes are candid about what the figures can’t tell you — the supplier clock currently runs from when an order was raised rather than released, so it reads harsh on the supplier, and a high warehouse figure can mean the unfulfilled counts are stale rather than that nothing is being picked, worth spot-checking before anyone gets blamed',
                    'Each explanation opens on keyboard focus as well as hover, so it’s reachable without a mouse',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'Supplier delivery times are now counted in whole calendar days — an order released at nine in the morning and delivered four days later was being reported as 3.6 days, because the release carries a time of day while the delivery only carries a date',
                ],
            },
        ],
    },
    {
        version: 'v3.26.0',
        date: '2026-08-06',
        sections: [
            {
                title: 'Inventory Dashboard — Rebuilt Around Purchase Order Flow',
                items: [
                    'The dashboard now opens with a verdict instead of a chart — it names whichever of Operations, the Supplier or the Warehouse is holding the most stock right now and says why in a sentence, so the first thing you read is the thing to act on',
                    'The four headline figures have changed to ones you can do something about — units of demand not yet met, units the reorder maths still wants ordered, units stuck inside the business that were never sent to a supplier, and units on the shelf that a waiting order could ship today; the old item and total-stock counts were true but never changed anyone’s mind',
                    'Underneath, the whole pipeline shows where every open unit is sitting stage by stage, an aging grid shows how stale each pile has got (0–7 days through to 60+), and a timings panel shows how long each step usually takes',
                    'Two worklists replace the old open-orders table — “Clear these first” for orders held inside the business, longest wait at the top, and “Chase these deliveries” for orders a supplier already has, split into nothing arrived, part delivered, and past the delivery target',
                    'The unfulfilled total is now broken down by whether the stock is physically here, so the part the warehouse could ship today is separated from the part genuinely waiting on supply',
                ],
            },
            {
                title: 'Inventory — Ordered vs Actually Moving',
                items: [
                    'Stock still owed on purchase orders is now split by whether the order has been paid for — paid means the supplier is on the hook and the goods are genuinely on their way, unpaid means the quantity is committed but nothing has started moving',
                    'The waiting-for-delivery breakdown says which is which, so an item showing weeks of cover on the back of an order that has sat unpaid for a fortnight is visible rather than buried in a single total',
                    'PO Needed is unchanged by this: every raised order still counts against what to buy, because an order that exists is committed quantity and ignoring it would have you order the same stock twice — an order stuck in a queue is a flow problem, and the dashboard panels are where it now shows up, measured as time',
                ],
            },
            {
                title: 'Inventory — Purchase Order Timings',
                items: [
                    'A new timings panel reads the ERP’s own status trail to show how long each step of a purchase order really takes — raised to approved, approved to paid, paid through to released — with the typical time and the slowest tenth side by side, and steps that are always instant left out rather than drawn as empty bars',
                    'The supplier’s leg is measured from the moment an order is released, through first delivery and on to 30, 60, 90 and 100% of the ordered quantity landing — partial delivery is the norm, and one “delivered” figure would hide an order that arrives 90% in a week then dribbles the rest out over a month',
                    'Paid dates can now be rebuilt from the ERP trail, so orders that synced before payment dates were tracked can be filled in rather than left permanently blank',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'The Inventory Items list is around four times quicker to load and sort — the stock figures are worked out in a single pass now instead of re-deriving the same handful of lookups once per column',
                    'The Delivery Lead Time table has moved off the dashboard and onto the Purchase Orders page, alongside the orders it is derived from — it is the reference you consult when setting an item’s lead time, not a daily signal',
                    'The items in / out chart now sits below a Background divider at the foot of the dashboard: it reports what already happened, which is context rather than something to act on',
                ],
            },
        ],
    },
    {
        version: 'v3.25.0',
        date: '2026-08-06',
        sections: [
            {
                title: 'Sales & Marketing — Sales Targets (New)',
                items: [
                    'New Sales Targets tab on the Sales & Marketing dashboard — a dated target holding one amount per team, so a day has a single number to hit and every team can see its share of it',
                    'Tick the teams that are on a target and set each one’s sales target and ad budget; a team can be on the target for its budget alone, with no sales figure to hit, and typing in either box ticks it for you',
                    'Each target also carries a Target ROAS — the bar teams are judged against on the board; leave it blank and 5.00 is used',
                    'A date holds at most one target — if you pick one that’s already taken you’re told on the field rather than getting an error page',
                    'Anyone who can see the dashboard can read the targets; creating, editing and deleting them needs the same permission as editing teams',
                ],
            },
            {
                title: 'Sales Targets — Public Gameboard (New)',
                items: [
                    'New public Sales Targets board behind the same password as the public RMO page and leaderboard, scored on today’s target — or the most recent one if today has none — with six headline tiles: total sales against target, overall achievement, ads budget, ROAS, qualified teams, and how far above or below the day has landed',
                    'Sales are counted straight from confirmed Pancake orders on the target’s date, so the board reads the same figure as Total Sales does elsewhere; the headline total covers everything the workspace confirmed that day, whoever it came through',
                    'ROAS here is measured against the ad budget you set rather than money already spent — it answers what the budget you handed out returned',
                    'Narrow the whole board to a single team from the header, and Refresh pulls fresh numbers in place instead of reloading the page',
                    'Each panel loads, fails and retries on its own, so one slow or broken figure never blanks the rest of the board',
                ],
            },
            {
                title: 'Sales Targets — Leader, Teams & Standings (New)',
                items: [
                    'A Current Leader banner names the team out front — highest achievement against its own target — with the two qualification criteria ticked or not and a sparkline of its last fortnight of sales',
                    'Team Performance cards for every team, best first, each showing target, sales, ad budget, achievement, ROAS and the gap either way, with a bar that runs past 100% so beating the target still shows as headroom',
                    'Teams that clear both the target and the ROAS bar are marked Qualified; anything over 100% of target picks up a Target Breaker flag, so a team can be one without the other',
                    'A Team Leaderboard table beneath ranks every team across all nine figures at once, showing the top five until you ask for all of them',
                    'Beside it, Sales vs Target puts each team’s sales next to its target as a pair of bars, and an Achievement Distribution donut counts how many teams sit in each band — teams with only an ad budget are reported separately rather than counted as failing',
                ],
            },
            {
                title: 'Sales Targets — Present on TV (New)',
                items: [
                    'A Present on TV button turns the board into a full-screen rotation for a wall display: an overview slide, then every team in turn with its medal, rank, six figures and achievement bar',
                    'Choose 5, 10, 15 or 30 seconds a slide, pause it, step back and forward, or jump straight to any slide from the dots along the bottom — a bar across the top counts down to the next turn',
                    'Arrow keys move between slides, space pauses and Escape leaves, so a presentation remote drives it without a keyboard in reach',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'A Sales Targets link now sits with the other public pages in the sidebar for workspaces with the Sales & Marketing dashboard turned on',
                    'The board reads in both light and dark, and everything on it steps up a size on a large screen so a wall-mounted TV isn’t showing laptop-sized type',
                ],
            },
        ],
    },
    {
        version: 'v3.24.0',
        date: '2026-08-06',
        sections: [
            {
                title: 'Inventory — Delivery Lead Time (New)',
                items: [
                    'New Delivery Lead Time table on the Inventory Dashboard — for each item, the average number of days from a purchase order being issued to 25%, 50%, 75% and finally all of the ordered quantity landing, so you can see not just how long a supplier takes overall but how much of it arrives early',
                    'Every figure carries the number of orders it was averaged from — a partly delivered line counts towards the levels it has already passed and not the ones it hasn’t, so the samples thin out towards the right and a solid average is easy to tell from a lone data point',
                    'Only orders issued in the last 6 months are counted, so the figures track how a supplier is performing now; cancelled orders are left out entirely rather than held against them as deliveries that were never going to arrive',
                    'A toggle rolls child SKUs up into their parent or breaks them back out, the same grouping the Inventory Items list uses',
                ],
            },
            {
                title: 'Inventory — Supplier & Paid Dates',
                items: [
                    'Purchase orders now carry their supplier and the date they were paid, both coming across from the ERP — the paid date is taken from the ERP’s own audit trail, using the first time the order was marked Paid, so a part-payment still counts as the day money moved',
                    'New Supplier and Paid Date columns on the Purchase Orders list, both sortable, and the search box now matches on supplier alongside delivery number, PO number, control number and SKU',
                    'Both are in the Excel export too, and the PO Date column has dropped its “(Paid)” note now that the paid date has a column of its own to be sorted by',
                    'The dashboard’s Open Purchase Orders table gained a Paid column sitting next to Issued, so the gap between raising an order and settling it reads at a glance',
                ],
            },
            {
                title: 'Inventory — Purchase Order History',
                items: [
                    'Opening a purchase order from the dashboard now shows its supplier, issue date and paid date across the top, before you get to the line items',
                    'Underneath the lines sits the full status history the ERP recorded — every stage, when it happened, and who moved it — so you can see why an order is sitting where it is rather than just that it is; orders synced before this existed simply show no history instead of an error',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'Pancake order syncing now spreads across more background workers, so a large sync clears faster',
                ],
            },
        ],
    },
    {
        version: 'v3.23.1',
        date: '2026-08-05',
        sections: [
            {
                title: 'Inventory — Purchase Orders',
                items: [
                    'Open Purchase Orders now lists the most recently issued order first, so the newest commitments are what you see at the top — orders with no issue date still sort to the bottom rather than leading the table',
                ],
            },
        ],
    },
    {
        version: 'v3.23.0',
        date: '2026-08-05',
        sections: [
            {
                title: 'Inventory — Dashboard Additions',
                items: [
                    'New High Unfulfilled Items table — the items owing the most stock, worst first, so you can see where the Unfulfilled tile’s total is actually concentrated instead of just how big it is',
                    'New Low Stock Items table beside it — the items most in need of a purchase order, ranked by how many units to reorder, with what’s on hand now alongside',
                    'Both tables count per group rather than per SKU, the same way the items list reads with summarize on — a grouped SKU appears once under its parent with the group’s total, instead of scattered across the table as several smaller rows',
                    'Each lists the top 20 and shows the total for the rows on screen, so the figure under the heading always describes what you’re looking at rather than the whole workspace',
                ],
            },
            {
                title: 'Inventory — Purchase Orders & Reorder Maths',
                items: [
                    'PO Needed was reordering too little — incoming deliveries were being subtracted twice, so the figure came out lower than it should have; it now counts them once, on the dashboard and on the Inventory Items list alike',
                    'Open Purchase Orders now lists oldest order first — the lines that have been outstanding longest are the ones to chase — with orders that have no issue date sorted to the bottom rather than the top',
                    'Each open PO line now shows its issue and expected delivery dates, plus a small progress ring for how much of what was ordered has landed',
                ],
            },
        ],
    },
    {
        version: 'v3.22.0',
        date: '2026-08-05',
        sections: [
            {
                title: 'Inventory — Dashboard (New)',
                items: [
                    'New Inventory Dashboard at the top of the Inventory menu — four headline tiles (Inventory Items, Total Stocks, Unfulfilled units, Open POs) each load and refresh on their own, so a slow figure never holds up the rest of the row',
                    'An Items In / Out chart shows units arriving against units leaving, day by day over the last 7, 14 or 30 days — in above the line, out below; the window ends yesterday so a half-written day doesn’t read as a slump, and write-offs are left out because they’re shrinkage rather than movement',
                    'An Open Purchase Orders table sits underneath: every line still owing stock, with what was ordered, delivered so far and still waiting, plus a running total of units outstanding — click a row to drill into that PO’s lines',
                    'Every figure is team-scoped and follows the “viewing as team” switcher, and the counts are worked out the same way the Inventory Items list works them out, so the tiles and the list agree to the unit',
                ],
            },
            {
                title: 'Inventory — Look Back at Any Day',
                items: [
                    'Every inventory item is now frozen nightly, so the Inventory Items list can be pinned to a past date and show that day’s closing stock, averages and computed columns instead of today’s',
                    'The date picker only offers days that actually have a saved snapshot — everything else is greyed out — and while a day is pinned, add/edit/delete are hidden, because those actions would change today’s items rather than the historical rows on screen',
                    'New PO QTY column on the items list — the safety buffer on top of lead-time demand (your days of coverage × the daily average), the same figure that feeds PO Needed',
                ],
            },
            {
                title: 'Inventory — Grouping Fixes',
                items: [
                    'Ungrouping now works from the summarised view — selecting a parent breaks up the whole group instead of reporting “0 items ungrouped” and leaving it intact',
                    'Bulk actions are hidden in the summarised view, where a selected row is a group rather than a single item',
                    'The summarize toggle now sticks — refreshing or changing another filter no longer springs it back on',
                ],
            },
            {
                title: 'Finance — Shares & Reference Numbers',
                items: [
                    'A transaction won’t save until the charge-to and product shares add up to the full amount — you get told how much is still unallocated and the page scrolls you to the section that needs it, instead of the server bouncing the save',
                    'Charge To is now marked required on fund requests, and a single allocation row always carries the whole amount rather than keeping a stale share from when the list was longer',
                    'Fund request reference numbers no longer collide — the next number carries on from the highest one issued rather than the row count, so deleting an older request can’t hand out one that’s still in use',
                    'The transaction form’s “Transaction” field is now labelled Transaction Description',
                ],
            },
            {
                title: 'Admin — Module Toggles',
                items: [
                    'Ad Spend Goals is now its own admin-toggled module — turn it off and the tab disappears from the S&M dashboard and every one of its routes stops responding',
                    'RMO management and Leaderboards can now be hidden on their own, without turning off the whole RTS or CSR permission category, and the Creatives module gates its own permissions the same way',
                    'The stale “View ESC Tracker” permission has been removed along with the role grants that referenced it',
                ],
            },
            {
                title: 'Smaller Improvements & Fixes',
                items: [
                    'Team schedules — clearing every shift in a week now saves; previously the cleared shifts came back on reload',
                    'Connecting a shop that’s already in use is now caught up front with a clear message, instead of failing part-way through the POS call',
                    'RMO management — changing the page, shop or user filter now keeps the rest of your filters and the current page size instead of dropping them',
                    'Gencys ERP — the daily sales tracker sync now pulls the last 3 days by default rather than just yesterday, so a missed day catches itself up',
                ],
            },
        ],
    },
    {
        version: 'v3.21.0',
        date: '2026-07-30',
        sections: [
            {
                title: 'Finance — Income Statements (New)',
                items: [
                    'New Income Statements page under Finance — pick a month and see a live preview of that month’s P&L before you save anything: delivered revenue and order count at the top, then the cost-of-sales and operating-expense lines beneath it',
                    'Two-tier profit — Gross Profit (delivered revenue minus cost of sales) and Net Profit (gross profit minus advisory share and OPEX) — with the COD fee, VAT and advisory rates editable per statement and Shipping Fee, COD Fee and VAT worked out for you',
                    'Tick or untick any line to decide what the statement includes, then Save to snapshot it; Regenerate keeps your exact set of lines while refreshing the figures, and each saved statement can be exported',
                    'Every saved statement also breaks down per person — each user’s revenue, cost of sales, gross and net profit, with a drill-in page for a single user; revenue that doesn’t resolve to anyone rolls into an Unassigned row',
                    'Transaction types now carry an Income Statement classification — mark a type as a Gross Profit deduction (cost of sales) or leave it as OPEX, shown as a badge on the Transaction Types list; the classification is stored on each statement, so re-flagging a type later won’t reclassify a statement you already closed',
                ],
            },
            {
                title: 'Finance — Transactions Rebuilt',
                items: [
                    'Adding and editing a transaction now happens on its own full page instead of a cramped dialog, and you land back on the exact list you came from when you’re done',
                    'A transaction can be charged to several people at once — give each their share, or leave a share blank and it takes an even cut of what’s left; the shares have to add up to the amount, so no part of it belongs to nobody',
                    'Tag a transaction against one or more products the same way, so spend can be traced back to the product it was for; the picker offers your workspace’s catalog products',
                    'Fill a new entry in from an approved or released fund request — the type, department, charge-to split and product split all copy across, and the transaction stays linked to the request',
                    'The account picker now shows each account’s current running balance as you choose it, so you can see what the entry is building on',
                ],
            },
            {
                title: 'Finance — Fund Requests Reworked',
                items: [
                    'Fund requests (renamed from “Request Funds” in the sidebar) now split their amount across products and across the people being charged, replacing the old Ad Spent line-item template — the same split shape transactions use, so a request and the transaction that settles it line up exactly',
                    'A department can be set on the request, and the fields that were never used — date needed and the GoTyme number — are gone from the form',
                ],
            },
            {
                title: 'RMO Management — Bulk Status, Auto-Tagging & Call Log Export',
                items: [
                    'New Bulk status update setting — turn it on and a “Set status” action appears on the RMO management page so several selected orders can be re-statused at once; orders outside the editable date window are skipped rather than failing the whole batch',
                    'New Auto-tag status setting — let the RMO status follow the courier so no CSR has to close a finished row out by hand: a parcel reporting Delivered re-tags to DELIVERED and Returning to RETURNING, every other status stays under CSR control',
                    'Auto-tagging re-applies every night at midnight to cover the day that just ended, and saving the setting re-tags today’s orders straight away so you can check it without waiting',
                    'Call logs can now be exported from the RMO management page, matching whatever the page is currently filtered to',
                    'The RMO search box accepts several terms at once — paste a comma-separated list of order numbers or tracking codes (up to 50) and get them all back in one go',
                ],
            },
            {
                title: 'Inventory — Product Lifecycle & Purchase Order Safeguards',
                items: [
                    'Two new product stages — New and Maintaining — join Testing, Scaling, Failed and Inactive, each with its own badge colour on the product pages',
                    'Filter inventory items by the stage of the product they’re linked to, on both the flat list and the summarised view',
                    'Purchase orders now show their status as a colour-coded badge and can be filtered by it on the list',
                    'Closing a purchase order that still has undelivered units now warns you first, with the outstanding count, and asks you to confirm before closing it short — closing quietly drops those units out of the incoming-stock and reorder maths',
                ],
            },
            {
                title: 'Gencys ERP — Unit Codes, Interns & Backfills',
                items: [
                    'Map a unit code to a product from the Unit Codes page — one at a time, or in bulk across a selection',
                    'Interns can carry alternate names, so ERP rows that spell someone’s name differently still resolve to the right intern (their real name and username always win)',
                    'The daily sales tracker sync can now be run for a date range, not just a single day, making backfills a single command',
                ],
            },
            {
                title: 'Smaller Improvements',
                items: [
                    'Admin — the Workspaces list now shows how many of each workspace’s pages are set up to send parcel updates by SMS and by chat, and you can sort by either',
                    'Required fields are now marked with an asterisk on the SIM form, with optional ones labelled as such',
                ],
            },
        ],
    },
    {
        version: 'v3.20.0',
        date: '2026-07-21',
        sections: [
            {
                title: 'SMS — Send From Your Own SIMs (New)',
                items: [
                    'A new SMS area (when enabled for your workspace) lets you send a text from one of your workspace’s SIMs — to a single recipient or a whole list at once — and every outbound message lands in an Outbox you can search, filter by status, and sort',
                    'The SIMs tab lists the SIMs assigned to your workspace with their carrier, status, and inbound/outbound message counts; admins provision SIMs and assign them to workspaces from a new Workspace SIMs screen in the admin area',
                    'Pages can now send their parcel-journey SMS through the Artemis SIM Gateway — choose which SIM to send from on the page’s settings, and the final delivery status flows back automatically',
                ],
            },
            {
                title: 'Finance — Ad Spent Fund Requests',
                items: [
                    'Fund requests now start from a template — keep the plain (blank) request, or pick the new Ad Spent template that adds a line-item table',
                    'Each Ad Spent line captures a product, the page it runs on, how many creatives are running, the budget per day, and the number of days — and the request total is worked out from the lines automatically, so there is no amount to hand-key',
                    'Picking one of your pages auto-fills its most recent daily budget, your saved GoTyme number carries onto the request, and you can filter the list by template to find them',
                ],
            },
            {
                title: 'Meta Ads — Tag an Ad’s Creator',
                items: [
                    'You can now tag each Meta ad with the workspace member who made it — one at a time, or in bulk across a selection — from the Ads Manager',
                    'The report builder can filter by creator, so you can slice ad performance by who produced the creative',
                ],
            },
            {
                title: 'Smaller Improvements',
                items: [
                    'Pancake Orders — show or hide table columns from a new Columns menu, with your choice remembered in your browser',
                    'Editing a creative now returns you to the exact list you came from, filters and all, instead of bouncing back to the edit page',
                    'Add a GoTyme number to your profile from Settings, ready to reuse on fund requests',
                ],
            },
        ],
    },
    {
        version: 'v3.19.1',
        date: '2026-07-16',
        sections: [
            {
                title: 'Ad Spend Goals — Per-Member Targets',
                items: [
                    'A team’s daily ad-spend goal can now be split across its members — give each member their own slice of the target right on the create/edit form; the slices must add up to at least the team’s daily target',
                    'The goal detail page shows a per-member breakdown — each member’s target, their spend so far (yesterday and their best day), progress since the goal started, and a bar showing how close they are, with a tick once they’ve hit their slice',
                    'Each member’s spend is measured the same way as the team total, so the per-member figures always reconcile with the overall goal',
                ],
            },
        ],
    },
    {
        version: 'v3.19.0',
        date: '2026-07-15',
        sections: [
            {
                title: 'Sales & Marketing Dashboard — Now Tabbed',
                items: [
                    'The Sales & Marketing dashboard is now organised into tabs — Daily Report (the default), Page ROAS Tracker, Ad Spend Goals, and Ad Spent Summary — so everything lives on one screen instead of scattered links',
                    'The old standalone Page ROAS Tracker, Ad Spend Goals, and Meta Ad Spent Summary links now redirect straight to their new tab, so existing bookmarks keep working',
                ],
            },
            {
                title: 'Page ROAS Tracker',
                items: [
                    'A day-by-day grid with dates down the side and an Orders / Sales / Ad Spend / ROAS column group for each page, plus per-page Total and Average rows so you can compare performance across pages at a glance',
                    'Filter by page, shop, or advertiser, and the view respects your team visibility — you see only the pages for the teams you belong to, and the “viewing as team” switcher narrows it further',
                    'Figures come from a new nightly per-page rollup that combines Pancake POS sales with Meta Ads spend',
                ],
            },
            {
                title: 'Ad Spend Goals (New)',
                items: [
                    'Set a daily ad-spend target for a team over a date range, with optional stepping-stone milestones on the way to it (for example ₱300k en route to a ₱500k/day goal)',
                    'Each goal shows live status — measured against the most recent complete day (yesterday) and the team’s best day within the window — on both a list view and a detail page with a progress graph',
                    'Two new role permissions — View Ad Spend Goals and Manage Ad Spend Goals — and goals respect team visibility so scoped users only see and set goals for their own teams',
                ],
            },
            {
                title: 'Ad Spent Summary',
                items: [
                    'One row per day across the selected range showing total orders, sales, ad spend, and ROAS (sales ÷ ad spend), rolled up across advertisers you can see',
                    'Filter by advertiser and date range; the tab is gated behind a new View Adspent Summary permission',
                ],
            },
            {
                title: 'Under the Hood',
                items: [
                    'A new nightly rollup (1:15am) builds per-page performance from Pancake sales and Meta Ads spend, rebuilding the last 3 days each run so late Meta attribution is captured',
                    'Fixed the Gencys ERP transaction-history sync so its 9am run fires on schedule again',
                ],
            },
        ],
    },
    {
        version: 'v3.18.0',
        date: '2026-07-15',
        sections: [
            {
                title: 'Finance — Request Funds (New)',
                items: [
                    'New Request Funds page under Finance — raise a fund request with the amount, purpose, who to charge it to, and the date it is needed; each request gets an auto-generated reference number and starts as Pending',
                    'Approvers move a request through its lifecycle — Pending → Approved → Released, or Cancel it — with the approver and remarks recorded on the request',
                    'Search by reference or purpose and filter by status, requester, or charge-to; five new role permissions (View, Create, Edit, Delete, and Approve Request Funds) control who can do what',
                ],
            },
            {
                title: 'Departments (New)',
                items: [
                    'New Departments page to define the departments in your workspace — create, edit, activate/deactivate, and delete them, each showing a live count of its members',
                    'Assign members to a department from the Members page, one at a time or in bulk across a whole selection',
                    'Four new role permissions — View, Create, Edit, and Delete Departments — assignable from the Roles screen',
                ],
            },
            {
                title: 'Sales & Marketing Dashboard (Now Live)',
                items: [
                    'The Sales & Marketing dashboard is now a full advertiser-performance view — per-advertiser sales today vs yesterday, month-to-date sales, live ranking, ad spend against target, ROAS, and RTS, with KPI cards and charts up top',
                    'Figures come from a new nightly rollup (1am) that combines Pancake POS sales with Meta Ads spend, rebuilding the last 3 days each run so late Meta attribution is captured',
                    'Enabled per workspace and gated behind the existing View Sales & Marketing Dashboard permission',
                ],
            },
            {
                title: 'Gencys ERP — Interns & Pages',
                items: [
                    'New Interns page — interns sync in from Gencys, and you can assign each one to a workspace user, toggle them active/inactive, and re-sync on demand',
                    'Intern daily records now capture per-day sales and order activity, including delivered and returned counts, collected automatically by a thrice-daily sync (9:30am, 6:30pm, 11:45pm)',
                    'New Gencys Pages screen tracks each page with its POS details, syncable on demand',
                    'Three new role permissions — View Gencys Interns, View Gencys Intern Daily Records, and View Gencys Pages',
                ],
            },
            {
                title: 'Admin — Client Report',
                items: [
                    'New per-workspace Client Report in the admin area — a last-three-months snapshot covering RTS rate (with a month-by-month trend), parcel-journey notifications sent, and RMO calls made plus total time on calls',
                    'Every figure reuses the same source as the rest of the app, so the report matches what workspace owners see; open it from the Admin Workspaces list',
                ],
            },
            {
                title: 'Inventory — Team-Scoped Items & Faster Stock Entry',
                items: [
                    'The item pickers on Purchase Orders and Stock Transactions now list only the inventory items for the teams you can see, matching the team visibility already applied across the rest of Inventory',
                    'Adding a stock transaction now auto-fills the remaining quantity from the item’s last recorded balance, so you no longer have to look it up and re-key it',
                ],
            },
            {
                title: 'Pages — Botcake ID Validation',
                items: [
                    'The page form can now check a Botcake Flow ID and Custom Field ID against Botcake before you save, so a mistyped ID is caught up front instead of failing quietly later',
                ],
            },
        ],
    },
    {
        version: 'v3.17.0',
        date: '2026-07-09',
        sections: [
            {
                title: 'Products — Assigned to Shops, Not Pages',
                items: [
                    'Products are now linked to shops instead of individual pages — pick the shops that sell a product right on the product form, and everything a product rolls up (sales, RTS, ROAS, ad spend) follows through the shop',
                    'Your existing product-to-page links were carried over to their shops automatically, so product analytics keep working with no manual re-linking',
                ],
            },
            {
                title: 'Inventory & ERP — Team Visibility',
                items: [
                    'Inventory Items, Transaction Logs, Purchase Orders, Unit Codes, and ERP Sync Health now respect team visibility — you see only the records for the teams you belong to, the same way Pages and Shops already do',
                    'Owners, managers, and anyone with “View All Workspace Data” still see everything, and the “viewing as team” switcher narrows these pages too',
                    'Items not yet linked to a product stay visible only to full-access users, keeping each team’s view focused on its own stock',
                ],
            },
        ],
    },
    {
        version: 'v3.16.0',
        date: '2026-07-09',
        sections: [
            {
                title: 'Inventory — Dashboard (New)',
                items: [
                    'New Inventory Dashboard brings stock health, movement, purchase orders, and audit together on one page — KPI cards up top with focused panels below',
                    'See fulfillment at a glance with a fulfilled-vs-unfulfilled donut, stock movement over time, purchase-order progress as a funnel, and shrinkage trends',
                    'Spot problems fast — a stock-health table, top-discrepancies chart, an alerts feed, upcoming deliveries, and recent stock adjustments are all in view',
                    'Panels load progressively so the page stays responsive while each section fills in, and a date-range picker scopes the whole view',
                ],
            },
            {
                title: 'Finance — Custom Transaction Types & Ledger Detail',
                items: [
                    'Define your own transaction types per workspace from the new Transaction Types page, instead of being limited to a fixed list — each type is reusable across transactions',
                    'Transactions now capture full ledger detail — requested by, approved by, department, charge to, reference no., and a posted status — so each account reads like a proper ledger',
                    'The transaction form and the account view were updated to enter and display these new fields',
                ],
            },
            {
                title: 'Parcel Journey — Choose Your SMS Provider',
                items: [
                    'Each page can now send parcel-journey text messages through either InfoTxt or SendGate — pick the provider on the page and enter its credentials',
                    'Existing pages keep sending through InfoTxt exactly as before; nothing changes unless you deliberately switch a page to SendGate',
                ],
            },
            {
                title: 'Shops — Simpler Setup',
                items: [
                    'Adding a shop no longer requires a POS token up front, so you can connect a shop with fewer blockers',
                    'Duplicate shops are now caught the moment you submit, instead of failing later after the POS call — clearer, faster feedback',
                ],
            },
            {
                title: 'Polish',
                items: [
                    'Date-range pickers now have a Clear button to remove the selection in one click',
                    'Creative Tracker gained a setup-tutorial link for quicker onboarding',
                ],
            },
        ],
    },
    {
        version: 'v3.15.4',
        date: '2026-07-01',
        sections: [
            {
                title: 'Inventory — Adjust Count & Discrepancies',
                items: [
                    'New “Adjust count” action on each item in the Inventory Items list — record a physical stock count and the item’s remaining quantity updates to match, carrying the difference forward as new stock moves in and out',
                    'Pick the date you counted and the dialog shows what the system thought the stock was on that date, plus the exact discrepancy that will take effect, before you save',
                    'New “Discrepancy” column on the Inventory Items list (and the Excel export) shows the offset currently applied, along with what was last counted and when',
                ],
            },
            {
                title: 'Inventory — Simpler Stock Sync',
                items: [
                    'ERP transaction history now records each row’s remaining stock exactly as the ERP reports it, instead of re-deriving a running total — the on-hand numbers now match the source',
                    'The inline “remaining quantity” edit on the Transaction Logs page is now read-only; stock corrections go through the new Adjust Count flow instead',
                ],
            },
        ],
    },
    {
        version: 'v3.15.3',
        date: '2026-07-01',
        sections: [
            {
                title: 'Creatives — Simpler Date Filtering',
                items: [
                    'The date filter is now a single range picker with a column selector — pick which date to filter by (Creative Date, Created Date, or Approved Date) and set one range, rather than juggling three separate ranges',
                    'Only one date type applies at a time, and switching the type carries your current range over to it',
                    'The Clear button now reliably removes the date filter in one click',
                ],
            },
            {
                title: 'Gencys ERP — Daily Sales Tracker Date Filtering',
                items: [
                    'The Daily Sales Tracker date filter now works the same way — one range picker plus a column selector to choose Order Date or Shipped Out Date, with only one date type filtering at a time and Clear working reliably',
                ],
            },
            {
                title: 'Video Editor Dashboard — Shows All Editors by Default',
                items: [
                    'The dashboard no longer defaults to just your own work — it now shows every editor’s activity out of the box, and you can narrow to specific people when you want to; any saved filter that previously pinned the view to you is reset automatically',
                ],
            },
        ],
    },
    {
        version: 'v3.15.2',
        date: '2026-07-01',
        sections: [
            {
                title: 'Inventory — Bulk Assign Product',
                items: [
                    'Select multiple items on the Inventory Items list and set their linked product in one go, instead of editing each item individually',
                    'The picker is searchable, so you can find the right product quickly; choose “No product (clear)” to unlink the product from every selected item',
                ],
            },
        ],
    },
    {
        version: 'v3.15.1',
        date: '2026-07-01',
        sections: [
            {
                title: 'Creatives — Bulk Assign Reviewers',
                items: [
                    'Select multiple creatives with the new row checkboxes, then assign reviewers to all of them at once — choose “Add” to attach reviewers on top of any already set, or “Replace” to overwrite each creative’s reviewer list (clearing it if you pick none)',
                ],
            },
            {
                title: 'Creatives — Separate Date Filters',
                items: [
                    'The single date filter is now three independent date ranges — Creative Date, Created Date, and Approved Date — so you can narrow the list by any of them on their own or together',
                ],
            },
            {
                title: 'Gencys ERP — Daily Sales Tracker Date Filters',
                items: [
                    'The tracker now offers a date range for each of its date columns — Order Date, Shipped, Encoded, Parcel Updated, and Date Added — each filterable on its own',
                ],
            },
        ],
    },
    {
        version: 'v3.15.0',
        date: '2026-06-30',
        sections: [
            {
                title: 'Gencys ERP — Daily Sales Tracker (More Detail)',
                items: [
                    'Each order now captures the customer’s name and full address (province, city, barangay), the courier, the payment method, and the pricing — initial price, final price, and shipping fee — all pulled in automatically from the sync',
                    'The tracker table shows these new columns and you can sort by customer, province, city, courier, and order/parcel status',
                    'Search now spans CSR, customer name, contact, tracking number, page, order details, brand, and status; plus filters for order-date range, shipped-out-date range, parcel status, and order status',
                ],
            },
            {
                title: 'Inventory — Unit Codes Rebuilt',
                items: [
                    'Unit codes now live in Inventory as a general, workspace-wide catalog rather than a Gencys-only list, so the same unit codes can drive inventory regardless of where they came from',
                    'Each unit code can be expanded to show its breakdown — the component item codes and how many of each it contains',
                    'The list is sortable by unit code and SKU, and a Sync button pulls the latest codes on demand',
                ],
            },
            {
                title: 'Inventory — Automatic Demand From Gencys Orders',
                items: [
                    'A new daily sync (7am) works out each inventory item’s recent demand straight from Gencys orders — it expands every order’s unit code into its component items, then fills in the item’s 3-day average and unfulfilled count',
                    'This keeps the "Days It Can Last" and "PO Needed" figures on the inventory list current without any manual entry',
                ],
            },
            {
                title: 'Inventory — Export Items',
                items: [
                    'New Export button on the Inventory Items list downloads an Excel file of the current view — SKU, product, status, lead time, unfulfilled, remaining quantity, waiting for delivery, 3-day average, days it can last, and PO needed — respecting whatever filters you have applied',
                ],
            },
            {
                title: 'Inventory — Purchased Orders Search by SKU',
                items: [
                    'You can now find a purchased order by the SKU of any inventory item on it, on top of the existing search by delivery no., customer PO, and control no.',
                ],
            },
        ],
    },
    {
        version: 'v3.14.2',
        date: '2026-06-30',
        sections: [
            {
                title: 'Inventory — Automatic ERP Syncs',
                items: [
                    'ERP transaction history now syncs automatically three times a day (8am, 12nn, 5pm) instead of just once each morning, so on-hand stock stays current throughout the day',
                    'ERP purchase orders now sync automatically three times a day (9am, 1pm, 6pm) — previously this had to be triggered by hand',
                    'Each sync now batches more items per run and spaces the runs further apart, easing load on the ERP and avoiding the rate-limit errors that could cause silent sync failures',
                ],
            },
            {
                title: 'Meta Ads',
                items: [
                    'Today’s ad insights now refresh every 6 hours instead of hourly, reducing load on the Meta API while still keeping the live day reasonably up to date',
                ],
            },
        ],
    },
    {
        version: 'v3.14.1',
        date: '2026-06-29',
        sections: [
            {
                title: 'Inventory — Gencys ERP Sync Health (New)',
                items: [
                    'New "Sync Health" page under Inventory shows, for every active item, whether its ERP transaction-history and purchase-order syncs went through — status, rows pulled, when it last ran, and any error message',
                    'A summary up top tracks the last 24 hours at a glance: total runs, success rate, and failures, alongside a feed of recent sync runs you can filter by type and status',
                    'The per-item status list is searchable by SKU or product name and paginated, so large catalogs stay easy to scan',
                    'Syncs that never hear back from the ERP are now automatically marked failed after a grace period instead of sitting stuck on "pending" forever',
                ],
            },
            {
                title: 'Inventory — Transaction Logs',
                items: [
                    'The "Inventory Stock (ERP)" column and inline editing of a transaction’s remaining quantity now appear only for ERP-connected (Gencys partner) workspaces — everyone else sees a cleaner, read-only view',
                ],
            },
            {
                title: 'Creatives — Filters',
                items: [
                    'New filter panel on the Creatives page — narrow the list by Format, Ads Status, Creator, and Product, with an "Apply Changes" button, a "Clear all" reset, and a badge showing how many filters are active',
                ],
            },
            {
                title: 'Finance — Transaction Import',
                items: [
                    'The CSV import dialog now guides you while mapping columns: it flags missing required fields and duplicate mappings, shows sample data under each dropdown, and only reveals the preview and enables import once every column is mapped correctly',
                ],
            },
            {
                title: 'Security & Activity Logs',
                items: [
                    'Password changes are now recorded — both successful changes and failed attempts — and tied to the right workspace so they show up in its activity log',
                    'Turning a workspace’s public-page password protection on or off is now logged as its own distinct event',
                    'Exporting transaction history is now recorded in the activity log',
                    'Removed vague "updated" entries for password fields so the activity log stays clean and meaningful',
                ],
            },
        ],
    },
    {
        version: 'v3.14.0',
        date: '2026-06-29',
        sections: [
            {
                title: 'Shops — Connect a Shop, Not a Page',
                items: [
                    'You now add a Shop instead of adding pages one at a time — enter the Shop ID and POS token in a quick pop-up and every page under that shop is imported and synced automatically',
                    'New "Refresh page list" action on each shop re-checks the shop and pulls in any newly-created pages, leaving the pages you already have untouched',
                    'First-time setup is now shop-based too — connect one shop and you are ready to go',
                ],
            },
            {
                title: 'Plans — Limits Are Now Per Shop',
                items: [
                    'Plan limits now count shops instead of pages, matching the new shop-first flow — your plan and the admin screens show how many shops are used and the shop limit',
                ],
            },
            {
                title: 'Teams — Assign Shops',
                items: [
                    'Teams now own shops instead of individual pages — pick the shops a team manages and its members automatically see all the orders, budgets, and metrics for those shops and their pages',
                ],
            },
            {
                title: 'Pages',
                items: [
                    'The POS token now lives on the shop, so it has been removed from the page form — it only needs to be set once per shop',
                    'The page edit form is simpler: the Shop ID and POS token fields are gone',
                    'The standalone "Add Page" flow and the per-page "Refresh Orders" action have been retired, since pages now come from — and sync with — their shop',
                ],
            },
            {
                title: 'Parcel Journey',
                items: [
                    'The parcel-journey analytics breakdown is now per shop instead of per page, for a cleaner view of tracked orders and messages sent across each shop',
                ],
            },
        ],
    },
    {
        version: 'v3.13.3',
        date: '2026-06-28',
        sections: [
            {
                title: 'Pancake — Order Refresh Tooling',
                items: [
                    'New maintenance command to re-pull a workspace’s shop orders (all sources, incl. Webcake) over a configurable recent window — scopeable to a single shop or page — for backfills and one-off fixes without touching the hourly sync',
                ],
            },
        ],
    },
    {
        version: 'v3.13.2',
        date: '2026-06-28',
        sections: [
            {
                title: 'Pancake — Fix',
                items: [
                    'Shop order syncing now processes every order again — a leftover debug limit that only synced a handful of test orders has been removed, with order dispatches staggered to ease load on Pancake',
                ],
            },
        ],
    },
    {
        version: 'v3.13.1',
        date: '2026-06-28',
        sections: [
            {
                title: 'Shops — Fix',
                items: [
                    'Refreshing a shop’s orders now resets its "Last Sync" first, so the column reflects the manual re-pull instead of showing a stale timestamp until the sync finishes',
                ],
            },
        ],
    },
    {
        version: 'v3.13.0',
        date: '2026-06-28',
        sections: [
            {
                title: 'Pancake — Webcake Orders Now Captured',
                items: [
                    'Order syncing now runs per shop instead of per page, so orders from every source are pulled in — including Webcake orders, which have no page and were previously skipped entirely',
                    'Each order now records where it came from (e.g. Facebook or Webcake), so its source is visible and filterable across the app',
                    'Delivery-journey text messages still go out for Webcake orders — they fall back to a shop page for messaging, while the order itself stays correctly marked as page-less',
                    'The "Single Page Shop" toggle has been removed from the page form — it is no longer needed now that syncing covers the whole shop',
                ],
            },
            {
                title: 'Shops',
                items: [
                    'New "Refresh orders" action on each shop re-pulls the last few months of orders across all sources on demand',
                    'Shops now show a "Last Sync" column so you can see when each shop last pulled orders',
                ],
            },
            {
                title: 'Dashboard',
                items: [
                    'New "Order Source" filter (Facebook, Webcake) on the dashboard — every metric, breakdown, and per-page/shop/user view respects it',
                ],
            },
            {
                title: 'RTS Analytics',
                items: [
                    'New "By Order Source" breakdown showing RTS rate for Facebook vs Webcake orders, alongside the By Price, By Delivery Attempts, and By Order Frequency cards',
                ],
            },
        ],
    },
    {
        version: 'v3.12.0',
        date: '2026-06-26',
        sections: [
            {
                title: 'Gencys ERP — New Integration',
                items: [
                    'Brand-new Gencys ERP module — enable it per workspace to pull Gencys data into Artemis: a Daily Sales Tracker (orders with full line-item detail) and a Unit Code catalog with per-code inventory',
                    'Data is collected by an automated n8n pipeline that scrapes Gencys and posts results back to dedicated callback endpoints, so figures stay fresh without manual exports',
                    'Inventory items can now sync directly from Gencys unit codes; the module and its nav only appear where Gencys has been turned on',
                    'Five new role permissions under a Gencys ERP group — View Daily Sales Tracker, View Unit Code, and Create / Edit / Delete Unit Code — all assignable from the Roles screen',
                ],
            },
            {
                title: 'Meta Ads — Report Builder',
                items: [
                    'New saved Reports area under Meta Ads — build reusable reports that explore your top-performing ads and creative across any combination of ad accounts',
                    'Group rows by Ad, Ad Name, Campaign, Ad Set, Account, or Ad Type — or by your own custom breakdowns (named, rule-based ad groups you define once and reuse)',
                    'Pick the metrics that matter, filter by name or metric thresholds, sort, and visualise as a creative gallery, bar, stacked bar, line, or area chart — with a creative preview for any ad',
                    'Reports can be archived and restored, so retiring a report no longer means losing its setup',
                ],
            },
            {
                title: 'Activity Logs — Audit Trail',
                items: [
                    'New Activity Logs page gives workspace admins an audit trail of who did what and when — sign-ins, record changes, permission and security events, integration syncs, and background jobs — with filters and a summary',
                    'A global, cross-workspace Activity Logs view is available in the admin area for platform-wide oversight',
                    'Logging runs automatically across requests, model changes, and authentication, capturing both user actions and automated system events',
                ],
            },
            {
                title: 'Settings — ERP Credentials',
                items: [
                    'New Automation Configuration screen under Settings to store the ERP username and password used by the automated sync pipeline',
                    'Passwords are encrypted and never sent back to the browser — update the username, replace the password, or clear it independently',
                ],
            },
            {
                title: 'Inventory — Purchase Order Monitoring',
                items: [
                    'Purchased Orders now track delivery progress inline — expand a PO to record deliveries against each item, set expected delivery dates, add remarks, and update status without leaving the page',
                    'Delivery timeliness is derived automatically, so you can see at a glance whether items are arriving on schedule',
                ],
            },
            {
                title: 'Inventory — ERP Sync & Fixes',
                items: [
                    'New ERP transaction-history sync (scheduled daily) keeps inventory stock aligned with the ERP via the n8n pipeline — an ERP-provided remaining stock is now tracked alongside the manually adjustable remaining quantity',
                    'Inventory items can be marked active/inactive with bulk status updates, and the product link is now optional so items without a Pancake product can still be tracked',
                    'New machine-to-machine API endpoints for purchase-order and transaction-history sync, used by the automation pipeline',
                ],
            },
            {
                title: 'Creatives',
                items: [
                    'Creative names must now be unique within a workspace, preventing accidental duplicates in the tracker',
                ],
            },
        ],
    },
    {
        version: 'v3.11.0',
        date: '2026-06-18',
        sections: [
            {
                title: 'Team-Level Data Access',
                items: [
                    "Data can now be scoped to teams — once a team owns pages or ad accounts, its members see only that team's records across pages, orders, products, shops, budgets, RTS analytics, creatives, the video-editor dashboard, Botcake sequences/flows/messages, and Meta ad performance",
                    'New "View All Workspace Data" permission sets the boundary: owners, super admins, and anyone with this permission see everything; remove it from a role (e.g. CSR) to limit that role to its teams\' data',
                    'Safe rollout — on release every existing role keeps full visibility, so nothing changes until you deliberately scope a role; a backfill command seeds team ownership from existing page owners',
                    "Fails closed — a scoped member who isn't on any team sees nothing until they're added to one, and you're warned if you assign such a role to a teamless member",
                ],
            },
            {
                title: 'Teams — Data Assignment',
                items: [
                    'New per-team screens (from the Teams list) to choose which pages and ad accounts a team owns',
                    "Ad accounts have two access tiers per team — View (see the account's data) or Manage; Manage is required to change budgets or statuses and to approve optimization proposals, and the Optimization History shows only the accounts you can manage",
                    'Pages and ad accounts can belong to multiple teams, and a member can be on multiple teams — they see the combined data of all their teams',
                    "The Pages list now shows each page's teams; the ad-account assignment screen lists only synced accounts and shows the Facebook user who connected each",
                ],
            },
            {
                title: '"Viewing as Team" Switcher',
                items: [
                    'New team switcher in the top bar, beside the workspace switcher — pick a team to focus the whole app on just that team\'s data, or "All teams" to see everything you can access',
                    'Useful for managers drilling into one team at a time; your choice sticks as you navigate and is reflected in the page URL',
                ],
            },
        ],
    },
    {
        version: 'v3.10.2',
        date: '2026-06-13',
        sections: [
            {
                title: 'Meta Ads — Optimization Rule Scheduling',
                items: [
                    'Each optimization rule now has its own schedule — choose how often it runs: hourly, every 3 hours, every 6 hours, every 12 hours, or daily (with an optional hour-of-day for daily rules)',
                    'The evaluator now runs every hour and only processes a rule when it is actually due, so different rules can run at different cadences instead of all once a day',
                    'New "Run now" action on the Optimization Rules list — evaluate a single rule on demand without waiting for its next scheduled run (requires Manage Optimization Rules)',
                    "The rules list shows each rule's schedule, and the last time it was evaluated is tracked per rule",
                ],
            },
        ],
    },
    {
        version: 'v3.10.1',
        date: '2026-06-11',
        sections: [
            {
                title: 'Meta Ads — Faster Onboarding & Sync',
                items: [
                    'Connecting a Meta (Facebook) account now immediately kicks off a full backfill — it fetches your ad accounts, then cascades campaigns, ad sets, ads, and creatives, plus the last 30 days of insights for each account, so your data is ready shortly after connecting',
                    'The connect confirmation now tells you the sync is in progress ("Syncing ad accounts and the last month of data now")',
                    'Smarter insights backfill — ad accounts with no campaigns are skipped entirely, so empty accounts no longer trigger unnecessary insights pulls',
                    'Meta Ads syncs now run on their own dedicated background workers, keeping them isolated from other jobs for more reliable, predictable data refreshes',
                ],
            },
        ],
    },
    {
        version: 'v3.10.0',
        date: '2026-06-11',
        sections: [
            {
                title: 'Meta Ads — New Integration',
                items: [
                    'Brand-new Meta Ads module replacing the old Ads Manager and Facebook Accounts pages — connect your Meta (Facebook) account via OAuth and sync ad accounts, campaigns, ad sets, ads, creatives, and insights into the workspace',
                    'Connect and manage Meta ad accounts from the new Integrations area, with per-account sync toggles so you only pull data for the accounts you care about',
                    'Meta Ads can be enabled per workspace — the module and its nav only appear where it has been turned on',
                    'Automatic background sync: ad accounts, campaigns, ad sets, and ads refresh every 30 minutes; insights every 15 minutes (last day) and hourly (last 7 days); creatives daily; end-of-day budgets snapshotted nightly to keep history Meta does not retain',
                ],
            },
            {
                title: 'Meta Ads — Unified Ads Manager',
                items: [
                    'Single unified view replacing the old Campaigns / Ad Sets / Ads tabs — pick any combination of ad accounts with a multi-picker and group by Ad Name, Ad, Campaign, Ad Set, or Ad Account',
                    'Metrics aggregate across all selected accounts; sort by any computed metric, filter on metric thresholds, search, choose visible columns (saved per grouping), and paginate',
                    'Entities with no insights in the selected date range still appear, and each group shows its ad count (e.g. "4 ads") beneath the name',
                    'Creative viewer — open any ad to preview its creative (image or video) in a phone-style frame using the live Meta ad preview',
                    'Click a group row to open a modal listing every ad in that grouping, using the same table and pagination',
                ],
            },
            {
                title: 'Meta Ads — Optimization Rules',
                items: [
                    'Define optimization rules with conditions on spend, ROAS, budget, days running, hours since last edit, and more — scoped to specific ad accounts with a priority order',
                    'Approval workflow: rules generate proposals (scale, descale, pause, enable) that are reviewed before anything changes — filter proposals by ad account and action, see the time window and date behind every condition, and approve or reject in bulk',
                    'Net budget impact total shown above the proposals table, respecting the current filters and updating live as you select rows (pause deducts the current budget)',
                    'Approving a proposal applies the action to Meta automatically; cross-rule priority claiming prevents two rules from acting on the same target, and non-moving budget changes are skipped',
                    'Optimization logs page records every rule evaluation and applied action',
                    'Automatic execution mode is temporarily disabled in the form while we monitor the approval flow',
                ],
            },
            {
                title: 'Meta Ads — Sync Health',
                items: [
                    'New Sync Health page surfaces the status of each sync run, so you can confirm data is flowing and spot failures quickly',
                ],
            },
            {
                title: 'Meta Ads — Permissions',
                items: [
                    'Eight new role permissions under the Meta Ads group: View Meta Ads, Connect FB Account, View Ad Accounts, Manage Meta Ads Accounts, View Optimization Rules, Manage Optimization Rules, Approve Optimization Rules, and View Optimization Logs — all assignable from the Roles screen',
                ],
            },
            {
                title: 'Public Workspace Pages',
                items: [
                    'Public-facing workspace pages (such as the Leaderboard) can now be protected with a workspace password',
                    'Public Leaderboard and RMO Management pages refreshed',
                ],
            },
        ],
    },
    {
        version: 'v3.9.1',
        date: '2026-06-10',
        sections: [
            {
                title: 'Pages — Export & Import',
                items: [
                    'Export all pages for a workspace to an Excel file directly from the Pages list',
                    'Import new pages from an Excel file — rows where a page with the same ID already exists in the workspace are skipped automatically',
                    'Import auto-creates associated shop records when a shop_id is provided in the file and the shop does not yet exist in the workspace',
                ],
            },
            {
                title: 'Creatives — Submission Status',
                items: [
                    'Creative list now shows whether each creative was submitted on time, early, or late relative to its planned date',
                    'Late and early submissions display a visual flag with a tooltip for quick identification',
                    'Loading indicators added to creative form submission buttons to prevent duplicate submissions',
                ],
            },
        ],
    },
    {
        version: 'v3.9.0',
        date: '2026-06-09',
        sections: [
            {
                title: 'Creatives — Creative Tracker',
                items: [
                    'New Creatives module — track ad creatives through a full production pipeline from brief to approval',
                    'List view with advanced filters (status, type, date range, page, product, assignee), sortable columns, and pagination',
                    'Create and edit creatives with product association, multiple reviewer assignments, and ads status integration',
                    'Review workflow — reviewers can add and update review decisions per creative',
                    'Six new role permissions: View, Create, Edit, Delete, Review, and Update Creative Status — all assignable from the Roles screen',
                ],
            },
            {
                title: 'Video Editor Dashboard',
                items: [
                    'New Video Editor Dashboard at /workspaces/{slug}/video-editor/dashboard — dedicated view for video production teams',
                    'Sections: KPI cards, pipeline funnel, creatives calendar with independent month navigation, throughput chart, leaderboard, recent activity feed, waiting list, revision list, and work list',
                    'Multi-select filters for products and editors; progressive loading keeps the page responsive while data loads in the background',
                    'Gated behind the new View Video Editor Dashboard permission',
                ],
            },
            {
                title: 'Sales & Marketing Dashboard',
                items: [
                    'New Sales & Marketing Dashboard page scaffolded at /workspaces/{slug}/sales-marketing/dashboard — placeholder ready for metrics and charts in the next release',
                    'Gated behind the new View Sales & Marketing Dashboard permission',
                ],
            },
            {
                title: 'RTS Analytics — Filter Persistence & AI Chat Gate',
                items: [
                    'Date range and filter selections are now persisted to localStorage per workspace — refreshing the page or navigating away and back restores the last-used filter state',
                    'RTS AI Chat widget is now gated behind a new View RTS AI Chat permission — hidden by default until the permission is granted',
                ],
            },
            {
                title: 'Bug Fixes',
                items: [
                    'CSR Analytics — added RMO percentage column',
                    'Leaderboard — date filter now correctly refreshes data when the selection changes',
                    'Workspace list — made scrollable to prevent overflow on workspaces with many entries',
                    'Teams — fixed permission validation on the Manage Schedule page',
                    'Members — fixed permission check on the Invite Members action',
                    'Admin — fixed subscription modal display for expired workspaces',
                    'Onboarding — added missing skip route so the skip button works correctly',
                    'Inventory — fixed editing inventory items',
                ],
            },
        ],
    },
    {
        version: 'v3.8.1',
        date: '2026-06-09',
        sections: [
            {
                title: 'Parcel Journey — Page Stats',
                items: [
                    'Removed the RTS Rate column from the per-page stats table on the Parcel Journey Templates page — the column added unnecessary query complexity and is better viewed on the dedicated RTS Analytics page',
                ],
            },
        ],
    },
    {
        version: 'v3.8.0',
        date: '2026-06-09',
        sections: [
            {
                title: 'Parcel Journey — Duplicate Notification Fix',
                items: [
                    'Fixed a bug where multiple parcel journey updates for the same day could each trigger a notification on repeated syncs — notifications are now only sent for journeys newer than the most recently notified one, preventing redundant messages',
                ],
            },
            {
                title: 'Pancake — Customer Sync Per Order',
                items: [
                    'Customer data is now synced directly during order processing via a new SyncCustomerAction — no separate fetch job needed',
                    'Removed the FetchShopCustomers job, TriggerFetchShopCustomers command, and the customers_last_synced_at column from shops — customer records stay fresh automatically on every order sync',
                    'Removed the unique constraint on pancake_customers.customer_id to support customers appearing across multiple shops',
                    'Removed the "Refresh customers" action from the Shops menu — the "Refresh users" action remains',
                ],
            },
            {
                title: 'Outgoing API Logging',
                items: [
                    'New outgoing_api_logs table records every external API call made by the app — service, action, HTTP method, URL, request payload, response status, response body, and duration',
                    'Botcake sendFlow and updateCustomField calls are now logged when PARCEL_JOURNEY_NOTIFICATION_LOGS_ENABLED=true; skipped notifications (when the feature is disabled) are also recorded',
                    'Logging is off by default — set PARCEL_JOURNEY_NOTIFICATION_LOGS_ENABLED=true in .env to enable',
                ],
            },
            {
                title: 'Order Sync — Faster Re-Sync Window',
                items: [
                    'orders_last_synced_at is now set 1 minute behind the sync end time (down from 15 minutes), reducing the gap between syncs and ensuring recent orders are not missed on the next run',
                ],
            },
        ],
    },
    {
        version: 'v3.7.1',
        date: '2026-06-03',
        sections: [
            {
                title: 'Sidebar — Cleanup',
                items: [
                    'Removed the Admin nav group and Customer Support link from the main workspace sidebar — both sections are temporarily hidden while their respective flows are being finalised',
                ],
            },
        ],
    },
    {
        version: 'v3.7.0',
        date: '2026-06-03',
        sections: [
            {
                title: 'Teams — Member Schedule',
                items: [
                    'New Team Member Schedule page — assign and visualize weekly schedules for each team member across all teams in the workspace',
                    'Schedule entries are keyed by team member and day-of-week; bulk assignments and per-member overrides are supported from the same view',
                    'Backed by a new team_member_schedules table (TeamMemberSchedule model) and TeamScheduleController; CSR-level schedules stored separately in csr_schedules',
                    'New manage-schedule permission controls who can view and edit team schedules',
                ],
            },
            {
                title: 'CSR — Dashboard & RMO Management',
                items: [
                    'New CSR Dashboard — dedicated view for CSR agents with performance KPIs, call activity, and order status summary scoped to the logged-in CSR',
                    'New CSR RMO Management page — CSR-scoped delivery order list with assignee filters, status updates, call logging, and daily stats; backed by a new CSR sidebar layout (csr-layout.tsx + csr-sidebar.tsx)',
                    'CSRController introduced to serve CSR-specific data separate from the shared workspace analytics path',
                ],
            },
            {
                title: 'Pages — Daily Budget Records',
                items: [
                    'New Page Daily Budget Records section — log and track daily ad budget entries per page with date, amount, and notes',
                    'Backed by a new page_daily_budget_records table (PageDailyBudgetRecord model) and PageDailyBudgetRecordController; list supports date-range filtering, pagination, and sortable columns',
                    'New update-page-budget permission gates access to creating and editing budget records',
                ],
            },
            {
                title: 'Public API — V2 Endpoints',
                items: [
                    'New /api/v1/public/v2/call-logs endpoint (CallLogV2Controller) — improved call log sync with stricter upsert logic and richer response envelope',
                    'New /api/v1/public/v2/rmo-orders endpoint (RmoOrderV2Controller) — paginated RMO order listing with assignee, status, and date filters for the mobile CSR client',
                ],
            },
            {
                title: 'Roles — Archived Roles',
                items: [
                    'Roles can now be archived instead of deleted — archived roles retain their permissions and member history but are hidden from active role assignment',
                    'New Archived Roles page lists all archived roles with restore and permanent-delete actions; accessible from the Roles index via the Archive tab',
                ],
            },
        ],
    },
    {
        version: 'v3.6.15',
        date: '2026-05-12',
        sections: [
            {
                title: 'Per-Workspace Page Limit Override',
                items: [
                    "Added a workspaces.max_pages column (new migration) that overrides the subscription plan's page_limit on a per-workspace basis — when set it wins, when null the plan limit is used, when both are null pages are unlimited",
                    'Centralised the resolution on the Workspace model — new pageLimit(), pageLimitInfo(), and hasReachedPageLimit() helpers so every creation site reads the same source of truth instead of duplicating the ?? chain',
                    "PageController::store and OnboardingController::store both now throw a ValidationException with a page_limit message when the workspace is at capacity; PageController::create still redirects to index with a flash error so users can't land on the create form when full",
                    'Onboarding page (resources/js/pages/workspaces/onboarding.tsx) now receives pageLimit/pageCount/pageLimitReached and disables the "Connect & Sync Orders" button with a tooltip + small counter ("X/Y pages used"), matching the pattern already on the pages index',
                    'Admin — /admin/workspaces now exposes a max_pages field on the workspace edit form and shows current usage as "count / max_pages" in the listing; AdminWorkspaceController validates max_pages as nullable|integer|min:1',
                ],
            },
        ],
    },
    {
        version: 'v3.6.14',
        date: '2026-05-09',
        sections: [
            {
                title: 'Botcake — Page & Shop Filters on Flows / Sequences',
                items: [
                    'Added the same Filters dropdown the main dashboard uses (Page + Shop) to /workspaces/{slug}/botcake/flows and /workspaces/{slug}/botcake/sequences — narrow the table to specific Pancake pages or shops without leaving the page',
                    'Filter state is persisted in the URL as filter[page_ids] / filter[shop_ids] (comma-separated), so refreshes and shared links preserve the selection. Page resets to 1 when the filter changes',
                    'Server-side: FlowController and SequenceController now whitelist page_ids and shop_ids — page_ids does whereIn on botcake_(flows|sequences).page_id, shop_ids walks the page relation (whereHas page → whereIn shop_id)',
                ],
            },
        ],
    },
    {
        version: 'v3.6.13',
        date: '2026-05-09',
        sections: [
            {
                title: 'Frontend Formatting Sweep',
                items: [
                    'Ran Prettier across the entire frontend (resources/js + resources/css) — 239 files reformatted with no functional changes. Quote-style normalised (single quotes), long lines wrapped, multi-line shadow / arg lists aligned, and a handful of missing spaces after type-annotation colons fixed (e.g. total_rmo_call_attempts:number → : number)',
                    'Refreshed resources/views/design-guidelines.html with the expanded component reference (~3,800 added lines of HTML samples covering the current design system) so internal contributors have an up-to-date visual catalog',
                ],
            },
        ],
    },
    {
        version: 'v3.6.12',
        date: '2026-05-09',
        sections: [
            {
                title: 'Customer Support — Tickets',
                items: [
                    'Added a workspace-side Support page (resources/js/pages/workspaces/support/index.tsx) with a "Contact Support" modal so users can open a ticket directly from the app — captures subject, message, and source page',
                    'Added an admin-side Support Tickets triage page (resources/js/pages/workspaces/admin/support-tickets/index.tsx) with status filters and row-click drilldown',
                    'Backed by a new SupportTicket model + policy + StoreSupportTicketRequest + factory; routes registered in routes/workspaces.php; covered by tests/Feature/Workspaces/SupportTicketsTest.php',
                    'Sidebar / app header / sidebar header now expose the Support entry, and Inertia shares support ticket data so the indicator stays in sync',
                ],
            },
            {
                title: 'Admin — Per-Workspace Metric Whitelist',
                items: [
                    'Admins can now control which metrics each workspace can see via /admin/workspaces/{slug}/metric-settings — backed by the new workspace_metric_settings table, MetricSettingPolicy, WorkspaceMetricSetting model, and a new App\\Support\\Metrics\\MetricRegistry helper',
                    'The MetricPicker / LocationCard / CxRtsCard components honour the whitelist; metrics not enabled for a workspace are hidden in the UI rather than silently empty',
                ],
            },
            {
                title: 'Inventory — Keyword Sync & Public API',
                items: [
                    'Added trigger-fetch-inventory-keyword-records scheduled command + TriggerFetchInventoryKeywordRecord job to keep keyword-driven inventory data fresh',
                    'New /api/v1/public/inventory-items endpoint (PublicApi\\InventoryItemController) for machine-to-machine reads of inventory state',
                    'Added remaining_qty column on inventory_items (separate migration) — surfaced in the inventory items page and form dialogs so on-hand quantity is no longer derived from running totals',
                ],
            },
            {
                title: 'CSR Analytics — Search & URL-Persistent Filters',
                items: [
                    'Added a search input on the CSR analytics page that filters the per-CSR table by name; the search term, type toggle (POS/ERP), and pagination state are now persisted in the URL so refreshes and shared links keep the filter context',
                    'Fixed CSR analytics pagination drift (86d2t08ae) and a CSR management update bug',
                ],
            },
            {
                title: 'Permissions & Errors',
                items: [
                    'Added grant-user-all-view-permissions artisan command (App\\Console\\Commands\\GrantUserAllViewPermissions) to bulk-grant every view-* permission to a user in a workspace',
                    'Added a proper /errors/403 Inertia page with copy and a "Go back" action — middleware now renders this on workspace-permission denials instead of the generic Laravel exception page; covered by tests/Feature/ForbiddenAccessTest.php',
                ],
            },
            {
                title: 'Data Integrity & Misc Fixes',
                items: [
                    'Added a unique index to call_logs (new migration) to enforce one row per (workspace, user, call_date, call_time, phone_number) and prevent duplicate ingestion',
                    'RTS Analytics — table and pagination fix (86d2t0bg9); Dashboard — crowded-filters layout fix (86d2rz9jz); Finance / Remittance row-filter bugs fixed; inventory transaction form clears state when switching from edit to create',
                    'RMO Management — JSX structure cleanup (orphaned </div> removed) so the page parses again; minor layout adjustments',
                ],
            },
        ],
    },
    {
        version: 'v3.6.11',
        date: '2026-05-08',
        sections: [
            {
                title: 'CSR Daily Records — One-Pass Read Path',
                items: [
                    'GET /api/.../csr/daily-records now collapses the previous 7 correlated subqueries into 2 grouped scans (pancake_user_pos_daily_reports + pancake_user_rmo_daily_reports) joined via leftJoinSub — significantly fewer scans on workspaces with large operator lists',
                    'Both rollup subqueries are now workspace-scoped, and the outer pancake_users query is constrained to operators in the current workspace via the pancake_shop_users → shops relationship — totals can no longer leak across workspaces',
                    'Refactored to Eloquent: PancakeUserPosDailyReport::query(), PancakeUserRmoDailyReport::query(), and User::whereHas(shopUsers.shop, ...) replace the raw DB::table calls. Added shopUsers and shop relationships on the Pancake User and ShopUser models',
                ],
            },
            {
                title: 'CSR Daily Records — Sorting & RTS Rate',
                items: [
                    'Added rts_rate as a server-side computed column (returning / (returning + delivered) * 100, rounded to 2 decimals) so CSRs can be sorted by return rate without recomputing in JS',
                    'Sortable columns: csr_name, total_orders, total_sales, total_returning, total_delivered, total_called, total_call_time, total_rmo_call_attempts, rts_rate. Default sort is -total_sales',
                ],
            },
            {
                title: 'sync:csr-daily-records — Writes to POS Rollup',
                items: [
                    'App\\Jobs\\SyncCsrDailyRecord now writes into pancake_user_pos_daily_reports keyed by pancake_user_id instead of csr_daily_records keyed by users.id — the whereNotNull(pu.user_id) filter that previously excluded Pancake operators without linked system accounts is gone, so every operator with confirmed orders is now captured',
                    'rmo_called is no longer written from this job — that count now lives exclusively in the RMO rollup (pancake_user_rmo_daily_reports), avoiding the dual-source drift that v3.6.9 already aligned',
                ],
            },
            {
                title: 'CSR Analytics — UI Column Updates',
                items: [
                    'Frontend table column accessors updated to match the new API aliases (name, total_delivered, total_returning) and a new "RMO Attempts" column showing total_rmo_call_attempts per CSR',
                ],
            },
        ],
    },
    {
        version: 'v3.6.10',
        date: '2026-05-07',
        sections: [
            {
                title: 'CSR Daily Sync — 7-Day Backfill & Queued',
                items: [
                    'sync:csr-daily-records and sync:csr-rmo-daily-records are now queue-based — the per-date aggregation moved into App\\Jobs\\SyncCsrDailyRecord and App\\Jobs\\SyncCsrRmoDailyRecord, and the commands have become thin dispatchers',
                    'Both commands now backfill the last 7 days by default (one job per day) instead of just yesterday — a transient failure on any night is automatically retried on each of the next 6 nightly runs, since the same dates keep being re-aggregated. updateOrCreate keeps it idempotent',
                    'Added --days=N (override the trailing-day window) and kept --date=YYYY-MM-DD (single-day mode) for ad-hoc backfills. The 03:00 / 04:00 nightly schedule is unchanged',
                ],
            },
        ],
    },
    {
        version: 'v3.6.9',
        date: '2026-05-07',
        sections: [
            {
                title: 'Shops — Refresh Users',
                items: [
                    'Added a "Refresh users" action to the shop row menu that re-dispatches the FetchShopUsers job for that shop — newly added Pancake operator accounts now show up in the workspace without having to wait for the next nightly sync or refresh the whole shop',
                ],
            },
            {
                title: 'CSR Daily Records — Aligned & More Detail',
                items: [
                    'sync:csr-daily-records (RMO) now defines total_called the same way the POS rollup does: pancake_order_for_delivery rows where status != PENDING. Previously the RMO and POS sides counted "called" differently, which made cross-tab comparisons drift',
                    'Added a separate total_rmo_call_attempts column to pancake_user_rmo_daily_reports — counts every matching call log per delivery row instead of collapsing to 0/1, so you can now see how many call attempts a CSR actually made versus how many deliveries they reached',
                ],
            },
            {
                title: 'CSR Performance API — Faster Reads',
                items: [
                    'GET /api/.../csr/daily-records now reads from the pre-aggregated daily rollup tables (csr_daily_records, pancake_user_erp_daily_reports, pancake_user_rmo_daily_reports) instead of recomputing from raw orders / deliveries on every request — same numbers, dramatically less work per page load on workspaces with large order volumes',
                ],
            },
        ],
    },
    {
        version: 'v3.6.8',
        date: '2026-05-07',
        sections: [
            {
                title: 'CSR Analytics — Index Coverage for Live Queries',
                items: [
                    'Added composite index (workspace_id, delivery_date, assignee_id) on pancake_order_for_delivery so the RMO outer query becomes a range scan and GROUP BY assignee_id has an ordered source — avoids the temp-table sort that was kicking in on workspaces with large delivery volume',
                    'Added composite index (workspace_id, user_id, call_date, phone_number) on call_logs so the EXISTS subquery powering RMO Called resolves via a single index seek per delivery row instead of falling back to (workspace_id, user_id) plus a row-level date / phone match',
                ],
            },
        ],
    },
    {
        version: 'v3.6.7',
        date: '2026-05-07',
        sections: [
            {
                title: 'CSR Analytics — Live POS Data',
                items: [
                    'POS metrics (Total Sales, Orders, Delivered, Returning, RTS Rate, and the per-CSR table) now read directly from pancake_orders instead of the pancake_user_pos_daily_reports rollup — figures reflect live order activity within the selected range without waiting on the daily rollup job',
                    'Per-CSR aggregation groups pancake_orders by confirmed_by; total_orders / total_sales use confirmed_at, delivered uses status=3 + delivered_at, returning uses status IN (4,5) + returning_at',
                ],
            },
            {
                title: 'CSR Analytics — Live RMO Called',
                items: [
                    'RMO Called and Total Call Time now derive directly from pancake_order_for_delivery (joined to call_logs via workspace + assignee + delivery date + rider/customer phone) instead of pancake_user_rmo_daily_reports — same aggregation logic the SyncCsrRmoDailyRecords command used, just evaluated at request time',
                ],
            },
            {
                title: 'CSR Analytics — ERP Toggle Disabled',
                items: [
                    'ERP option in the POS / ERP toggle is temporarily disabled (greyed out, unclickable) while the ERP data path is being reworked; POS remains the default and only selectable mode',
                ],
            },
            {
                title: 'Scheduler — Analytics Rollup Paused',
                items: [
                    'Hourly and back-fill analytics:rollup schedules in routes/console.php are commented out now that POS / RMO analytics no longer depend on the rollup tables — frees the queue from redundant work',
                ],
            },
        ],
    },
    {
        version: 'v3.6.6',
        date: '2026-05-07',
        sections: [
            {
                title: 'Onboarding — Help Panel',
                items: [
                    'Added a "Need help getting started?" panel under the onboarding form with three quick links: a setup-tutorial video, an email shortcut to hello@artemis.ph, and a Facebook message link — each with an icon and emerald hover accent that matches the brand palette',
                ],
            },
            {
                title: 'Onboarding — Sync Complete',
                items: [
                    'After the initial sync finishes, the onboarding page now does a full reload instead of an Inertia visit — server-side props (workspace flags, sync timestamps, sidebar visibility) refresh cleanly so the dashboard renders with up-to-date state on first paint',
                ],
            },
            {
                title: 'Dashboard — Default Date Range',
                items: [
                    'Dashboard date range now defaults to "start of month → yesterday" instead of "start of month → end of month" so the chart no longer extends into future days and dilutes today\'s metrics with empty buckets',
                ],
            },
            {
                title: 'Pancake Sync — Initial Backfill Window',
                items: [
                    "First-time Pancake page connect (and the Refresh button on Pages) now backfills 1 month of orders and shop customers instead of 3 months, cutting onboarding sync time and keeping queue load proportional to a typical seller's active window",
                ],
            },
        ],
    },
    {
        version: 'v3.6.5',
        date: '2026-05-06',
        sections: [
            {
                title: 'Botcake — Module Toggle',
                items: [
                    "Added a per-workspace botcake_module_enabled flag — the Botcake nav group (Sequences + Flows) is hidden from the sidebar when the module is off, and any permission with the Botcake category is filtered out of the user's effective permission set",
                ],
            },
            {
                title: 'Botcake — Overall vs Historical Stats',
                items: [
                    'Flows and Sequences index pages now have an Overall / Historical toggle; Historical reveals a date-range picker (defaulting to the last 7 days) and re-aggregates Sent / Phone Numbers / Success Rate from the new daily delta tables',
                    'FetchFlowStatistics and FetchSequenceStatistics now compute per-day deltas against the prior cumulative snapshot (clamped at 0 to absorb counter resets) so historical sums add up to real activity within any window — the cumulative-as-of-now value is still saved on the Flow / SequenceMessage row for the Overall view',
                    'Trigger commands renamed under the botcake: namespace (botcake:trigger-fetch-flows, botcake:trigger-fetch-sequences, botcake:trigger-fetch-flow-statistics, botcake:trigger-fetch-sequence-statistics); old names kept as aliases',
                    'Stats triggers now chunk through Flows / Sequences in batches of 200 instead of fetching all at once, preventing memory spikes on workspaces with thousands of records',
                ],
            },
            {
                title: 'Botcake — Schema & Code Layout',
                items: [
                    'botcake_flows, botcake_sequences, and botcake_sequence_messages now use the Botcake-supplied id directly as the primary key — collapsing the previous (auto-increment id + flow_id / sequence_id / message_id) split into a single column. The migration drops and recreates the six related tables to apply the change',
                    'Web controllers for Flows and Sequences moved from app/Http/Controllers/Workspaces/Botcake/ into Modules/Botcake/Http/Controllers/Web/, keeping module-owned code inside the module',
                ],
            },
            {
                title: 'Telescope & Horizon — Access Control',
                items: [
                    'Both /telescope and /horizon now require is_super_admin = true in non-local environments — non-super-admin users get a 403 instead of seeing the dashboards. Local development continues to bypass the gate via the framework default',
                ],
            },
            {
                title: 'Workspace Middleware — Lookup Fallbacks',
                items: [
                    "CheckWorkspace now falls back to the route-bound {workspace} parameter and the user's session current_workspace_id when the X-Workspace-Id header is absent, so URL-scoped routes don't need clients to set the header explicitly",
                ],
            },
        ],
    },
    {
        version: 'v3.6.4',
        date: '2026-05-06',
        sections: [
            {
                title: 'Inventory — SKU Uniqueness',
                items: [
                    'Editing an inventory item now rejects a SKU that is already used by another item in the same workspace, returning a clear validation error instead of silently saving a duplicate',
                ],
            },
            {
                title: 'Call Logs API — Idempotent Sync',
                items: [
                    'Public /call-logs/sync endpoint now upserts on (workspace, user, phone number, call date, call time) so re-syncing the same logs from the mobile app no longer creates duplicate rows',
                    'Response now returns a synced count alongside total, and incoming timestamps are preserved as-sent rather than re-anchored to the app timezone',
                ],
            },
            {
                title: 'Purchased Orders — Create Feedback',
                items: [
                    'Creating a purchased order now shows a success toast on save and an error toast (with console-logged validation details) when the form fails, matching the edit-flow behaviour',
                ],
            },
        ],
    },
    {
        version: 'v3.6.3',
        date: '2026-05-05',
        sections: [
            {
                title: 'Sign-up — Terms & Privacy',
                items: [
                    'Registration form now requires checking an "I agree to the Terms & Conditions and Privacy Policy" box before submit, with links opening the legal pages in a new tab',
                    'Backend validates the acceptance flag and returns a clear error ("You must accept the Terms & Conditions to create an account.") if it is missing',
                ],
            },
            {
                title: 'Plans — New Enterprise Tier',
                items: [
                    'Added a new Enterprise plan to the pricing line-up: custom pricing, unlimited orders and pages, 24-month data retention, full analytics, Parcel Journey SMS included, and dedicated support',
                    'Re-balanced Scale to ₱14,999/mo (down from ₱19,999) to slot under the new Enterprise tier',
                ],
            },
        ],
    },
    {
        version: 'v3.6.2',
        date: '2026-05-05',
        sections: [
            {
                title: 'Plans — Pricing & Page Limits',
                items: [
                    'Re-priced and capped page counts on paid tiers: Starter ₱1,499→₱2,999 with a 5-page cap, Growth ₱3,999→₱5,999 with 25 pages, Scale ₱9,999→₱19,999 with 100 pages',
                    'Parcel Journey SMS is now bundled (free) on every paid tier — removed the per-message rates (₱0.50 / ₱0.35 / ₱0.20) and turned SMS on for Starter so every paid plan includes it',
                ],
            },
            {
                title: 'Marketing Site',
                items: [
                    'Landing page now consistently reads "30-day free trial" everywhere (hero subline, pricing card label, pricing card lede, final CTA, and FAQ) — matches the actual trial length',
                ],
            },
            {
                title: 'Navigation',
                items: [
                    'Removed the Changelog item from the public sidebar; it stays reachable directly at /changelog',
                ],
            },
        ],
    },
    {
        version: 'v3.6.1',
        date: '2026-05-05',
        sections: [
            {
                title: 'Pages — Plan Limits',
                items: [
                    'Pages list now enforces the workspace\'s plan page limit — the index shows an "X/Y pages used" indicator under the Add New Page button, the button disables once the limit is reached, and a tooltip points to upgrading the plan',
                    'Backend now blocks the Add Page flow when the limit is hit (both the create page and the store endpoint) with a clear validation message instead of letting the request through silently',
                ],
            },
            {
                title: 'Free Trial Plan',
                items: [
                    'Bumped Free Trial defaults so new workspaces get a more useful evaluation: 10,000 order cap (was unlimited), 6 months of data retention (was 1), full analytics tier (was basic), Parcel Journey SMS enabled, and priority chat support',
                ],
            },
        ],
    },
    {
        version: 'v3.6.0',
        date: '2026-05-05',
        sections: [
            {
                title: 'RTS — RMO Management',
                items: [
                    'Split the single "Only my data" toggle into two independent filters — "My Assignee Only" and "My Confirmee Only" — so reps can narrow the list to orders they confirmed separately from those assigned to them, and combine both when needed',
                    'Public endpoint and CSV export now accept a confirmee_id filter (mirrors the existing assignee_id filter), and toggle state is persisted per browser via localStorage',
                ],
            },
            {
                title: 'Subscription Gate & Syncing Modal',
                items: [
                    'Restored the subscription-expired gate on every authenticated Inertia page — workspaces with an expired, cancelled, or lapsed trial/active subscription are surfaced the upgrade modal with active non-trial plans (skipped on local environments)',
                    'Restored the "syncing data" modal that appears on first connect until at least one page finishes its initial order sync; copy softened to "Please be patient." now that the sync runs in the background',
                ],
            },
            {
                title: 'Onboarding — Initial Sync Window',
                items: [
                    'First-time Pancake page connect now backfills 3 months of orders and shop customers (was 1 month), so newly onboarded workspaces have deeper history available immediately',
                ],
            },
            {
                title: 'Mobile / Public API — Call Logs',
                items: [
                    'Synced call-log timestamps are now normalised to the workspace timezone before storage, eliminating the off-by-hours drift on the KPI screen',
                    'Total talk time KPI now only counts calls whose phone number matches a delivery on that date (customer or rider phone on pancake_order_for_delivery), giving an accurate read of talk time tied to actual delivery work',
                ],
            },
        ],
    },
    {
        version: 'v3.5.0',
        date: '2026-05-04',
        sections: [
            {
                title: 'Analytics',
                items: [
                    'Page-view analytics via PostHog — tracks Inertia route changes, identifies the signed-in user, and groups events by workspace so funnels and retention can be sliced per workspace',
                    'Toggleable per environment via the VITE_POSTHOG_DISABLED flag, so local development stays out of production analytics',
                ],
            },
            {
                title: 'Pancake — Order Sync',
                items: [
                    'Consolidated the separate shipped-orders sync into the main page-orders job — at 9 AM, 12 PM, 3 PM, 6 PM, and 9 PM the run pulls shipped orders (filter_status[]=2); other runs pull orders updated since the last sync',
                    'Removed the standalone trigger-fetch-page-shipped-orders command and FetchPageShippedOrders job — same coverage with one scheduled command instead of two',
                    'trigger-fetch-page-orders now runs hourly (was every 30 minutes), reducing duplicate fetch overhead now that shipped pulls are interleaved',
                ],
            },
        ],
    },
    {
        version: 'v3.4.1',
        date: '2026-05-01',
        sections: [
            {
                title: 'Inventory — Purchased Orders',
                items: [
                    'List now shows subtotal and summary totals for the active filtered view, so you can see overall delivery fee and total amount without exporting',
                    'Excel export includes the same summary rows at the bottom of the file',
                ],
            },
            {
                title: 'Finance — Transactions',
                items: [
                    'Index page now surfaces totals for credit (in) and debit (out) across the active filters, giving a quick read on cashflow without leaving the page',
                ],
            },
            {
                title: 'Inventory — Stock Transactions',
                items: [
                    'Date-range filter added to the stock transactions list, with the selection preserved across pagination and sort',
                ],
            },
        ],
    },
    {
        version: 'v3.4.0',
        date: '2026-05-01',
        sections: [
            {
                title: 'Pancake — Courier Shipments',
                items: [
                    'New Courier Shipments page under Pancake — import courier reports (xlsx) and reconcile them against your Pancake orders by waybill / tracking code',
                    'J&T Express xlsx importer — uploads are parsed, upserted by (workspace, courier, waybill), and automatically linked to the matching pancake_orders row',
                    'Totals strip surfaces overall and matched-only sums for total shipping cost, COD fee, COD collected, and receivable freight, so you can see exactly how much shipping fee is tied to confirmed Pancake orders',
                    'List supports search by waybill / order # / receiver / phone, matched-only or unmatched-only filters, pickup-date range, and sortable shipping-cost columns',
                    'Two new role permissions — View Courier Shipments and Import Courier Shipments — assignable from the Roles screen',
                ],
            },
        ],
    },
    {
        version: 'v3.3.2',
        date: '2026-05-01',
        sections: [
            {
                title: 'Inventory — Purchased Orders',
                items: [
                    'List now defaults to sorting by issue date (newest first) instead of created date, so the most recently issued POs surface at the top',
                ],
            },
        ],
    },
    {
        version: 'v3.3.1',
        date: '2026-05-01',
        sections: [
            {
                title: 'Inventory — Purchased Orders',
                items: [
                    'Search the list by delivery no., customer PO, or control no.',
                    'Filter the list by issue-date range, with the selection preserved across pagination and sort',
                    'Export CSV now respects the active search, date range, and sort',
                ],
            },
        ],
    },
    {
        version: 'v3.3.0',
        date: '2026-05-01',
        sections: [
            {
                title: 'Admin — Subscription Plans',
                items: [
                    'New Subscription Plans admin panel — create, edit, and manage plans with pricing, trial settings, and feature flags',
                    'Plan list with quick row actions and a shared form between create and edit screens',
                    'Subscription plan seeder updated to match the new schema, safe to re-run idempotently',
                ],
            },
            {
                title: 'Admin — Workspaces',
                items: [
                    'Workspace Management panel reworked — sidebar logo and workspace name added, plus richer per-workspace controls for plan, subscription, and trial state',
                    'Inline plan assignment and subscription edits directly from the workspaces list',
                ],
            },
            {
                title: 'Subscriptions',
                items: [
                    'Core subscription management and 30-day free trial — new workspaces start on a one-month trial that auto-expires via a scheduled command',
                    'Subscription Expired modal — gracefully blocks workspace access when an active plan lapses, with a clear path to upgrade',
                    'Syncing Data modal — friendlier first-run state while initial workspace data is being pulled in',
                    'Subscription gate is temporarily disabled in production while the billing flow is finalised — workspaces continue working as normal in the meantime',
                ],
            },
            {
                title: 'Workspaces — Onboarding',
                items: [
                    'New onboarding flow at /workspaces/onboarding — guided first-run setup that lands new workspaces in a ready-to-use state without manual configuration',
                ],
            },
            {
                title: 'Public API — Call Logs',
                items: [
                    'Two new Bearer-token endpoints under /api/v1/public for call log listing and KPI summary',
                    'CSR mobile clients can now read call log data directly without going through the workspace UI',
                ],
            },
            {
                title: 'Dashboard',
                items: [
                    'Header and dashboard filter — alignment and reset behaviour fixed; selections now persist correctly across navigation',
                    'Statistic breakdown — removed a duplicate total row that was double-counting in summary cards',
                ],
            },
            {
                title: 'RTS Analytics',
                items: [
                    'Breakdown chart x-axis labels now align cleanly with extra bottom padding, so dates no longer overlap on dense ranges',
                ],
            },
            {
                title: 'Inventory',
                items: [
                    'Inventory Items — row-level filtering fixed; filters now apply correctly on first load',
                    'Purchased Orders — total computation fix on the create screen',
                ],
            },
            {
                title: 'Checklist',
                items: [
                    'Edit Checklist — fixed a mobile-only bug that prevented edits from saving',
                    'Add Task dialog — sorting and notification handling tightened',
                ],
            },
            {
                title: 'Polish',
                items: [
                    'Tab titles normalised to "Artemis | <Page>" across both client and SSR for consistent browser tab labels everywhere',
                    'Sidebar settings entry removed in favour of inline controls already available elsewhere',
                    'Small visual cleanups across workspace switcher, app header, and sidebar',
                ],
            },
        ],
    },
    {
        version: 'v3.2.0',
        date: '2026-04-30',
        sections: [
            {
                title: 'Analytics — Live by default',
                items: [
                    'Dashboard cards and breakdowns now compute directly from pancake_orders instead of the hourly rollup table, so numbers reflect activity in near real time rather than waiting for the next rollup pass',
                    'Opt back into the rollup by passing ?source=rollup on the analytics endpoints — useful when you want a faster (but slightly stale) read or to compare values against the rollup baseline',
                    'Per-metric source toggle — each metric class can be flipped between live and rollup independently via setSource(); RtsRate, the avg-days metrics, and the count/amount metrics all support both modes',
                ],
            },
        ],
    },
    {
        version: 'v3.1.0',
        date: '2026-04-28',
        sections: [
            {
                title: 'Finance — Transactions',
                items: [
                    'Export CSV — download the current transactions view as a CSV file with date, account, description, type, transaction type, sub-category, amount, running balance, and notes',
                    'Date range filter — filter transactions by date directly from the toolbar, with the selection preserved across pagination and other filters',
                ],
            },
            {
                title: 'Finance — Remittances',
                items: [
                    'Edit remittances in place — new Edit action in the row dropdown on the remittances list and a dedicated Edit button on the remittance detail page',
                    'Date range filter on the remittances list, matching against the billing period',
                    'Linked Transaction picker now only lists remittance-type transactions and shows amounts formatted in pesos (₱) for easier scanning',
                ],
            },
            {
                title: 'Fixes',
                items: [
                    'Pages — removed a dead duplicate dispatch in the manual refresh path',
                    'SuperAdmin — fixed a casing mismatch on the workspaces index that prevented the page from resolving on case-sensitive filesystems',
                ],
            },
        ],
    },
    {
        version: 'v3.0.2',
        date: '2026-04-27',
        sections: [
            {
                title: 'Fixes',
                items: [
                    'Analytics Rollup — page daily metrics with no activity (all-zero counts and amounts) are no longer written to the rollup table, keeping the metrics dataset compact and avoiding empty rows for inactive pages',
                ],
            },
        ],
    },
    {
        version: 'v3.0.1',
        date: '2026-04-27',
        sections: [
            {
                title: 'Fixes',
                items: [
                    'Purchased Orders — fixed a missing AuthorizesRequests import that caused authorization checks to fail on the Purchased Orders controller',
                ],
            },
        ],
    },
    {
        version: 'v3.0.0',
        date: '2026-04-27',
        sections: [
            {
                title: 'Artemis — Public Launch',
                items: [
                    'Rebranded from ecomm-control-hub to Artemis — the analytics & automation platform for Philippine COD e-commerce',
                    'New public marketing site with Hunt down RTS positioning — hero, problem, features, free trial, how it works, real seller results, and FAQ sections',
                    'New /rts-calculator page — sellers can quantify their monthly RTS bleed in pesos before signing up',
                    'New about, blog, contact, privacy, terms, data-policy, and security pages',
                    'New Artemis logo, emerald brand palette, and dark/light theme toggle on all marketing pages',
                ],
            },
            {
                title: 'Subscriptions',
                items: [
                    'Subscription management UI — workspaces can now view their plan, current period, and billing status',
                    'Plan selection and upgrade flow built on top of the v2.7.1 subscriptions foundation',
                    '14-day free trial flow — new workspaces start on a trial subscription with no credit card required',
                    'Plan tier gating across feature surfaces (gracefully shown rather than hidden when out of plan)',
                ],
            },
            {
                title: 'Roles & Permissions',
                items: [
                    'Reworked permissions engine — roles now resolve through a single source of truth across workspace, module, and action layers',
                    'Per-action permission checks across Members, Roles, Orders, Products, Teams, Inventory, Reports, Shops, and API Keys',
                    'Workspace members with the manage-api-keys permission can now generate and revoke API keys without owner intervention',
                    'Bypass mode for owner-level accounts to keep workspace recovery flows working when permissions are misconfigured',
                ],
            },
            {
                title: 'Finance — Remittances',
                items: [
                    'Detailed remittance management view with per-account balance, transaction history, and date-range filters',
                    'Bulk import from Excel — paste or upload remittance entries in batches with validation and preview before commit',
                    'Inline edit and delete of individual remittance entries from the management view',
                ],
            },
            {
                title: 'Shops',
                items: [
                    'Shops management view aligned with the Pages experience — search, sort, filter, and per-shop checklist progress',
                    'Per-shop status badge and last-sync indicator',
                ],
            },
            {
                title: 'Performance & Polish',
                items: [
                    'Optimization pass on dashboard and analytics queries — faster initial loads with smaller payloads',
                    'New skeleton loading states across dashboard, RTS analytics, and inventory pages',
                    'Sidebar scrollbar fix — no longer overlaps content on narrow viewports',
                    'Metrics pipeline tightened — fewer redundant recalculations across workspace metrics',
                ],
            },
            {
                title: 'Internal',
                items: [
                    'Marketing plan, content playbook, and post calendar documents added to the repository for the launch',
                    'Project documentation refreshed to reflect the Artemis brand and RTS-first positioning',
                ],
            },
        ],
    },
    {
        version: 'v2.7.1',
        date: '2026-04-24',
        sections: [
            {
                title: 'RMO Management',
                items: [
                    'Copy rider and customer phone numbers for the first 10 pending orders directly from the RMO Management view (currently enabled on efb.on-forge.com)',
                    'Parcel Update Notification template form now validates empty text areas before saving',
                ],
            },
            {
                title: 'Sidebar',
                items: [
                    'Removed duplicate RTS entry from the workspace sidebar',
                ],
            },
            {
                title: 'Forms',
                items: [
                    'Polish pass across Inventory Items, Purchased Orders, Products, Teams, and Employees dialogs — tightened validation and layout consistency',
                ],
            },
            {
                title: 'Internal',
                items: [
                    'Subscriptions foundation — new subscription_plans catalog and workspace_subscriptions tables, plan tier constants, idempotent plan seeder, and a subscription relation on Workspace (no user-facing UI yet)',
                    'Build config fix in vite.config.ts',
                ],
            },
        ],
    },
    {
        version: 'v2.7.0',
        date: '2026-04-22',
        sections: [
            {
                title: 'Analytics Optimization',
                items: [
                    'Analytics rollup — new scheduled command precomputes order metrics so dashboards load from aggregated data instead of recalculating per request',
                    'Backfill command for populating analytics rollups across historical date ranges',
                    'Order metrics (AOV, totals, lifetime value, repeat/retention, delivery timing, RTS averages) refactored to read from rollups — significantly faster queries',
                    'Parcel journey metrics (SMS sent, tracked orders, total for delivery) now use the same optimized pipeline',
                ],
            },
            {
                title: 'Inventory Items',
                items: [
                    'Purchased Orders view per inventory item, with pagination',
                    'Fixed inventory transaction bugs affecting stock calculations',
                ],
            },
            {
                title: 'RMO Management',
                items: [
                    'Call logs are now visible directly on the RMO management dashboard',
                    'Phone numbers can be edited in place from the RMO management view',
                    'Parcel status label updated and redundant update logic removed',
                ],
            },
            {
                title: 'CSR Mobile API',
                items: [
                    'New call log synchronization and KPI endpoints for the CSR mobile client',
                    'Call logs moved to a dedicated table with separated KPI calculations for improved accuracy',
                    'Call log sync batches database updates to reduce load during large imports',
                ],
            },
            {
                title: 'SuperAdmin Panel',
                items: [
                    'SuperAdmin panel added with cross-workspace oversight views',
                    'Casing and styling adjustments for consistency with the rest of the app',
                ],
            },
            {
                title: 'Finance',
                items: ['Initial rollout of finance features (feat/finance)'],
            },
            {
                title: 'Parcel Journey',
                items: [
                    'Parcel journey template support added (feat/pj-template)',
                    'parcel_status added to OrderForDelivery for faster filtering and is now nullable',
                ],
            },
            {
                title: 'Internal',
                items: ['Sentry integration added for error monitoring'],
            },
        ],
    },
    {
        version: 'v2.6.0',
        date: '2026-04-16',
        sections: [
            {
                title: 'Workspace Checklist',
                items: [
                    'New Checklist page in the workspace sidebar — define reusable tasks that apply to Shop or Page targets',
                    'Each checklist item has a title, target type (Shop or Page), and a required flag shown as a yes/no badge',
                    'Add, edit, and delete checklist items from a shared modal with validation',
                    'Pages and Shops list rows now include a "View Checklist" action that opens a drawer showing per-target progress',
                    'Optimistic toggle flow — checking or unchecking an item updates instantly and records who completed it',
                    'Pages and Shops sort indicator now reflects how many required checklist items are still pending per target',
                ],
            },
            {
                title: 'Purchased Orders',
                items: [
                    'All columns are now sortable, with sort state persisted in the URL query string',
                    'Create form enforces a strict YYYY-MM-DD issue date and disables submit until required fields are valid',
                ],
            },
            {
                title: 'Inventory Items',
                items: [
                    'Lead Time, Unfulfilled Count, 3-Day Average, and Created Date columns are now sortable',
                ],
            },
            {
                title: 'RMO Management',
                items: [
                    'Filter state is now preserved correctly after navigation — fixed a regression where assignee/status filters would reset',
                ],
            },
            {
                title: 'Tables',
                items: [
                    'Default page size is now 10 rows across CSR Management, CSR Analytics, RTS Analytics breakdowns (Ad, Confirmed By, Product, Rider), Parcel Templates, Roles, Inventory Purchased Orders, Ads Optimization Rules, and the public API endpoints (Users, RMO, CSR Daily Records)',
                    'Row numbering now starts from the correct offset on every paginated table across the system',
                ],
            },
        ],
    },
    {
        version: 'v2.5.1',
        date: '2026-04-16',
        sections: [
            {
                title: 'CSR Analytics',
                items: [
                    'RMO Total For Delivery — new sortable column showing orders assigned to the CSR as conferrer within the selected date range',
                    'RMO Productivity — new sortable column showing RMO called as a percentage of RMO Total For Delivery',
                    'Assignee filter now counts total orders by confirmed_by, so the stat card matches the filtered CSR',
                ],
            },
            {
                title: 'RMO Management',
                items: [
                    "Status picker, Assign to me, and Remove assignee are disabled unless the order's delivery date is today — backend validation mirrors the UI",
                    'Date picker moved to the right side of the toolbar, next to the Show/Hide Statistics button',
                ],
            },
            {
                title: 'CSR ERP Sync',
                items: [
                    'Schedule replaced: backfill runs at 2/3/4/5 AM for 2–5 days ago, plus 12 PM and 3 PM runs for yesterday (previously twice daily at 10 and 22)',
                    'New --date option on trigger-fetch-csr-erp-dail-records accepts any Carbon-parseable value for ad-hoc backfills',
                    'Sync now only targets ACTIVE Pancake users',
                ],
            },
        ],
    },
    {
        version: 'v2.5.0',
        date: '2026-04-14',
        sections: [
            {
                title: 'Inventory Items',
                items: [
                    'New computed metric columns: Unfulfilled, Current Stocks, Waiting for Delivery, 3-Day Avg, Remaining After Fulfillment, Days It Can Last, PO Needed',
                    'Lead Time — new field on inventory items; used in the PO Needed calculation',
                    'Unfulfilled Count — stored column, editable from the item form; synced hourly from Pancake order quantities with status 1, 8, or 9',
                    '3-Day Average — stored column, editable from the item form; synced hourly from confirmed orders over the last 3 full days',
                    'Waiting for Delivery — now sourced only from Purchased Orders with status "Waiting For Delivery"',
                    'All metric columns are center-aligned for easier scanning',
                ],
            },
            {
                title: 'Transaction Logs',
                items: [
                    'Lost — new field on transaction logs to record lost stock quantities',
                    'Remaining Quantity — now a plain editable field (no automatic computation)',
                ],
            },
            {
                title: 'Purchased Orders',
                items: [
                    'Status field added with 8 states: For Approval, Approved, To Pay, Paid, For Purchase, Waiting For Delivery, Delivered, Cancelled',
                    'Edit page added — purchased orders can now be fully edited after creation',
                    'Issue date is now stored and displayed in YYYY-MM-DD format',
                ],
            },
            {
                title: 'Role Permissions',
                items: [
                    'New Manage Permissions page per role — checkbox grid grouped by category with select-all per group',
                    '25 default permissions across 7 categories: Members, Roles, Orders, Products, Teams, Inventory, Reports',
                    '"Manage Permissions" action added to the role dropdown on the Roles page',
                ],
            },
            {
                title: 'Workspace Settings',
                items: [
                    'show_inventory — new setting to control whether the Inventory sidebar group is displayed per workspace',
                    'inventory_sync — new setting to enable hourly syncing of 3-day average and unfulfilled count for inventory items',
                ],
            },
            {
                title: 'Members',
                items: [
                    'Unauthorized remove action now shows an in-page permission toast instead of redirecting to a 403 page',
                ],
            },
        ],
    },
    {
        version: 'v2.4.4',
        date: '2026-04-09',
        sections: [
            {
                title: 'CSR Analytics',
                items: [
                    'Delivered and Returning columns now display as Philippine Peso currency',
                    'All columns are now sortable — click any column header to sort ascending or descending',
                    'Analytics data is now server-side paginated and sorted via QueryBuilder',
                ],
            },
        ],
    },
    {
        version: 'v2.4.3',
        date: '2026-04-09',
        sections: [
            {
                title: 'Internal',
                items: [
                    'CSR daily records are now stored in a dedicated table keyed by Pancake user ID',
                    'Fixed 401 errors on internal API endpoints caused by session auth not being available on the API middleware stack',
                ],
            },
        ],
    },
    {
        version: 'v2.4.2',
        date: '2026-04-09',
        sections: [
            {
                title: 'Fixes',
                items: ['New CSR users now default to ACTIVE status'],
            },
        ],
    },
    {
        version: 'v2.4.1',
        date: '2026-04-09',
        sections: [
            {
                title: 'Fixes',
                items: [
                    'CSR Management — sorting and pagination now work correctly after the first load',
                ],
            },
        ],
    },
    {
        version: 'v2.4.0',
        date: '2026-04-09',
        sections: [
            {
                title: 'API Keys',
                items: [
                    'Generate API keys per workspace to connect external tools and platforms',
                    'Each key has a name, prefix preview, and a last-used timestamp',
                    'Reveal the full key at any time using the eye icon — no need to regenerate after a page refresh',
                    'Revoke any key instantly from the API Keys settings page',
                ],
            },
            {
                title: 'Public API',
                items: [
                    'New public API endpoints authenticated via Bearer token: health check, workspace users, and CSR daily records',
                    'Fixed CSRF token mismatch — public API routes are now stateless and no longer require a CSRF token',
                    'User list endpoint supports search, pagination, and filtering by Pancake account',
                    'CSR daily records endpoint supports upsert — safe to call multiple times for the same date',
                ],
            },
            {
                title: 'CSR Management',
                items: [
                    'New CSR Management page — view all CSRs in your workspace with their linked Pancake accounts',
                    'New CSR Analytics page — daily performance table with total orders, sales, delivered, returning, RMO called, and RTS rate',
                    'Filter CSR analytics by date range',
                ],
            },
            {
                title: 'Inventory Items',
                items: [
                    'New Inventory Items page — create, edit, and delete inventory items per workspace',
                    'Each item has a name, SKU, unit, and description',
                ],
            },
            {
                title: 'Leaderboard',
                items: [
                    'Leaderboard now includes Called Activity and Delivery Success categories',
                    'Group leaderboard by called activity or delivery performance',
                ],
            },
            {
                title: 'Auth Pages',
                items: [
                    'Login, register, and workspace setup pages have been redesigned with a cleaner, more premium look',
                ],
            },
            {
                title: 'Sidebar',
                items: [
                    'API Keys added to the workspace switcher menu for quick access',
                    'Inventory section is hidden in production — only visible in non-production environments',
                ],
            },
        ],
    },
    {
        version: 'v2.3.0',
        date: '2026-04-07',
        sections: [
            {
                title: 'AI Chat — Dashboard & RTS Analytics',
                items: [
                    'Ask the AI questions about your data directly from the dashboard or the RTS analytics page',
                    'Dashboard: analyze your sales metrics, page performance, shop performance, and team performance',
                    'RTS Analytics: ask about returns by price, product, rider, customer risk, location, or order frequency',
                    'Answers are based on the data currently on screen, not guesses',
                ],
            },
            {
                title: 'Members — Reset Password',
                items: [
                    'Admins can now generate a password reset link for any team member',
                    'Click "Copy Reset Link" from the member\'s action menu — the link is copied to your clipboard and ready to share',
                ],
            },
            {
                title: 'RTS Analytics — Performance',
                items: [
                    'Analytics page loads noticeably faster — date filters now use index-friendly queries instead of per-row calculations',
                    'New database indexes on key columns used by the RTS queries',
                ],
            },
        ],
    },
    {
        version: 'v2.2.0',
        date: '2026-04-01',
        sections: [
            {
                title: 'Parcel Journey',
                items: [
                    'New Parcel Journey section added to the RTS menu',
                    'See all your parcel notification templates in one place',
                    'Dashboard cards showing how many orders were tracked, and how many SMS and chat messages were sent',
                    'Filter the dashboard by date range — defaults to the current month',
                    'Browse and manage notification templates with search and pagination',
                ],
            },
            {
                title: 'Page Status',
                items: [
                    'Pages can now be set to Active or Inactive instead of being archived',
                    'Toggle a page on or off directly from the create or edit form',
                    'Pages list shows a clear Active or Inactive badge for each page',
                ],
            },
        ],
    },
    {
        version: 'v2.1.0',
        date: '2026-03-27',
        sections: [
            {
                title: 'Parcel Journey Improvements',
                items: [
                    'Notifications are only sent for orders that are out for delivery today — no unnecessary messages',
                    'Stops tracking a parcel once it has been registered as returned',
                    'Rider name and contact number are now saved automatically when a delivery is recorded',
                ],
            },
            {
                title: 'RTS Analytics',
                items: [
                    'New breakdown by Product — see which items have the most returns',
                    'New breakdown by Rider — see which riders have the most returns',
                    'Price, Delivery Attempts, and Customer RTS cards now default to chart view',
                ],
            },
            {
                title: 'Pages',
                items: [
                    'Pages list now shows whether a page is using the latest sync logic or an older version',
                ],
            },
            {
                title: 'RMO Management',
                items: [
                    'Order status dropdown now shows color-coded labels for each status',
                    'Orders that are out for delivery show a live pulsing indicator',
                ],
            },
        ],
    },
    {
        version: 'v2.0.1',
        date: '2026-03-26',
        sections: [
            {
                title: 'New Features',
                items: [
                    'RMO Management — view and manage return and delivery orders in one place, with search, sorting, and status updates',
                    'Role Management — create and manage user roles to control what each team member can access',
                    'Dark Mode — switch between light and dark theme from the app header',
                    'Employees — view all team members connected to your workspace in a searchable list',
                ],
            },
            {
                title: 'Improvements',
                items: [
                    'Navigation updated with links to RMO Management and Roles',
                    'Your name and initials now appear as a pill in the header instead of a notification bell',
                    'Empty tables now show a helpful message instead of blank space',
                    'Analytics load faster thanks to short-term caching',
                ],
            },
            {
                title: 'Fixes',
                items: [
                    'Fixed sidebar background appearing transparent on mobile in dark mode',
                    'Fixed sidebar border looking too bright on mobile in dark mode',
                    'Fixed an error that occurred when setting up a new workspace',
                ],
            },
        ],
    },
    {
        version: 'v1.2.0',
        date: '2025-12-03',
        sections: [
            {
                title: 'RTS Analytics',
                items: [
                    'New RTS analytics dashboard with key return-to-sender metrics',
                    'City heatmap — see which cities have the most returns at a glance',
                    'Breakdown by user — see RTS numbers per team member',
                    'Breakdown by page — see RTS numbers per Facebook page',
                    'Breakdown by city — detailed table with return counts per city',
                ],
            },
            {
                title: 'Page Management',
                items: [
                    'Search, filter, and sort your pages list',
                    'Archive pages you no longer need and restore them anytime',
                    "View order history directly from a page's detail view",
                ],
            },
            {
                title: 'Teams',
                items: [
                    'Create and manage teams within your workspace',
                    'Assign team members to specific teams',
                ],
            },
            {
                title: 'Employees',
                items: ['Employees section added to the sidebar navigation'],
            },
            {
                title: 'Improvements',
                items: [
                    'Empty tables now show a friendly message with an icon instead of blank space',
                ],
            },
        ],
    },
    {
        version: 'v1.1.0',
        date: '2025-11-28',
        sections: [
            {
                title: 'Workspaces',
                items: [
                    'Create new workspaces from within the app',
                    'Switch between workspaces without logging out',
                    'Workspace selection screen showing all your workspaces and member counts',
                    'Workspace switcher in the sidebar for quick access',
                ],
            },
            {
                title: 'Invitations',
                items: [
                    'Invite team members to your workspace by email',
                    'Invited members can accept or decline the invitation',
                ],
            },
            {
                title: 'Facebook Integration',
                items: [
                    'Connect your Facebook accounts to a workspace',
                    'View linked ad accounts and their campaigns',
                ],
            },
            {
                title: 'Fixes',
                items: [
                    'Fixed a dialog that was getting cut off on smaller screens',
                ],
            },
        ],
    },
    {
        version: 'v1.0.0',
        date: '2025-10-31',
        sections: [
            {
                title: 'Initial Release',
                items: [
                    'Set up your first workspace to get started',
                    'Connect your pages and sync orders automatically',
                    'View all orders per Facebook page',
                    'Manually refresh orders whenever you need the latest data',
                    'Send and view messages on orders',
                ],
            },
        ],
    },
];

const versionColors: Record<string, string> = {
    'v3.3.0':
        'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 ring-yellow-500/20',
    'v3.1.0': 'bg-sky-500/10 text-sky-600 dark:text-sky-400 ring-sky-500/20',
    'v3.0.2':
        'bg-teal-500/10 text-teal-600 dark:text-teal-400 ring-teal-500/20',
    'v3.0.1':
        'bg-green-500/10 text-green-600 dark:text-green-400 ring-green-500/20',
    'v3.0.0':
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-emerald-500/20',
    'v2.7.1':
        'bg-lime-500/10 text-lime-600 dark:text-lime-400 ring-lime-500/20',
    'v2.7.0':
        'bg-purple-500/10 text-purple-600 dark:text-purple-400 ring-purple-500/20',
    'v2.6.0':
        'bg-pink-500/10 text-pink-600 dark:text-pink-400 ring-pink-500/20',
    'v2.5.1':
        'bg-fuchsia-500/10 text-fuchsia-600 dark:text-fuchsia-400 ring-fuchsia-500/20',
    'v2.5.0':
        'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 ring-indigo-500/20',
    'v2.4.4':
        'bg-slate-500/10 text-slate-600 dark:text-slate-400 ring-slate-500/20',
    'v2.4.3':
        'bg-slate-500/10 text-slate-600 dark:text-slate-400 ring-slate-500/20',
    'v2.4.2':
        'bg-slate-500/10 text-slate-600 dark:text-slate-400 ring-slate-500/20',
    'v2.4.1':
        'bg-slate-500/10 text-slate-600 dark:text-slate-400 ring-slate-500/20',
    'v2.4.0':
        'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 ring-cyan-500/20',
    'v2.3.0':
        'bg-teal-500/10 text-teal-600 dark:text-teal-400 ring-teal-500/20',
    'v2.2.0':
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-emerald-500/20',
    'v2.1.0':
        'bg-blue-500/10 text-blue-600 dark:text-blue-400 ring-blue-500/20',
    'v2.0.1':
        'bg-violet-500/10 text-violet-600 dark:text-violet-400 ring-violet-500/20',
    'v1.2.0':
        'bg-amber-500/10 text-amber-600 dark:text-amber-400 ring-amber-500/20',
    'v1.1.0':
        'bg-orange-500/10 text-orange-600 dark:text-orange-400 ring-orange-500/20',
    'v1.0.0':
        'bg-rose-500/10 text-rose-600 dark:text-rose-400 ring-rose-500/20',
};

export default function Changelog() {
    return (
        <>
            <Head title="Changelog — Artemis" />
            <div className="min-h-screen bg-gray-50 dark:bg-zinc-950">
                {/* Header */}
                <header className="border-b border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
                        <Link
                            href={home().url}
                            className="flex items-center gap-2.5"
                        >
                            <img
                                src="/img/logo/artemis.png"
                                alt="Artemis"
                                className="h-7 w-7 object-contain"
                            />
                            <span className="font-semibold tracking-tight text-gray-900 dark:text-white">
                                Artemis
                            </span>
                        </Link>
                        <span className="font-mono text-[11px] font-semibold tracking-widest text-gray-400 uppercase dark:text-gray-500">
                            Changelog
                        </span>
                    </div>
                </header>

                {/* Content */}
                <main className="mx-auto max-w-3xl px-6 py-10">
                    <div className="mb-8">
                        <h1 className="text-[28px] font-bold tracking-tight text-gray-900 dark:text-white">
                            What's new
                        </h1>
                        <p className="mt-1 font-mono text-[13px] text-gray-400 dark:text-gray-500">
                            Latest updates and improvements to Artemis.
                        </p>
                    </div>

                    <div className="space-y-6">
                        {changelog.map((entry) => (
                            <div
                                key={entry.version}
                                className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900"
                            >
                                {/* Version header */}
                                <div className="mb-5 flex items-center gap-3">
                                    <span
                                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 font-mono text-[11px] font-semibold ring-1 ring-inset ${versionColors[entry.version] ?? 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-400 dark:ring-white/10'}`}
                                    >
                                        {entry.version}
                                    </span>
                                    <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                        {entry.date}
                                    </span>
                                </div>

                                {/* Sections */}
                                <div className="space-y-5">
                                    {entry.sections.map((section) => (
                                        <div key={section.title}>
                                            <p className="mb-2.5 font-mono text-[10px] font-semibold tracking-widest text-gray-400 uppercase dark:text-gray-500">
                                                {section.title}
                                            </p>
                                            <ul className="space-y-1.5">
                                                {section.items.map(
                                                    (item, i) => (
                                                        <li
                                                            key={i}
                                                            className="flex items-start gap-2.5 font-mono text-[12px] text-gray-600 dark:text-gray-400"
                                                        >
                                                            <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 dark:bg-zinc-600" />
                                                            {item}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>

                    <p className="mt-8 text-center font-mono text-[11px] text-gray-400 dark:text-gray-600">
                        © {new Date().getFullYear()} Artemis. All rights
                        reserved.
                    </p>
                </main>
            </div>
        </>
    );
}
