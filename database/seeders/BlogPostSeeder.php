<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BlogPostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $posts = [
            [
                'title' => '5 Signs Your COD Business is Bleeding Money',
                'slug' => '5-signs-your-cod-business-is-bleeding-money',
                'excerpt' => 'You might be profitable on paper but losing thousands every month. These are the red flags most PH COD sellers miss.',
                'content' => <<<MARKDOWN
Your ads are running. Orders are coming in. Revenue looks healthy. But somehow, at the end of the month, the profit feels thinner than it should.

Sound familiar? You're not alone. Most Philippine COD sellers are profitable on paper — but **bleeding money** through operational leaks they can't see. Here are the 5 biggest warning signs.

## 1. You don't know your exact RTS rate
This is the #1 red flag. If someone asked you "what's your RTS rate this month?" and you can't answer with a specific number — you're flying blind.

"Around 25-30%" is not a number. It's a guess. And that guess could be off by 10 percentage points — which on 1,000 monthly orders represents ₱40,000-60,000 in hidden costs.

**What to do:** Track your RTS rate weekly, by page, by product. If it's above 20%, you have room to improve. If it's above 30%, it's urgent.

## 2. You're scaling ads without fixing delivery
The most common mistake in PH COD: spending more on ads to drive more volume, while 30% of orders never convert into revenue.

Think about it. If your RTS rate is 30% and your cost per acquisition is ₱150, you're effectively paying ₱215 per successful delivery (₱150 ÷ 0.70). That's 43% more than you think.

Scaling ads with a high RTS rate is like filling a bucket with a hole in the bottom. The water goes in faster, but it's still leaking out.

**What to do:** Before increasing ad spend, get your RTS below 20%. Every percentage point you reduce is worth more than extra ad budget.

## 3. You don't know your cost per failed delivery
Most sellers think a failed delivery costs them "just the shipping" — around ₱100-150. The real cost is ₱300-500 when you factor in return shipping, packaging waste, damaged inventory, and unrealized profit.

If you haven't calculated your exact cost per RTS, you're underestimating the problem by 3-5x. And that means you're not prioritizing the fix.

**What to do:** Calculate your full RTS cost. Include forward shipping, return shipping, packaging, damage rate, and lost margin. The number will change how you think about the problem.

## 4. You have no customer notifications during delivery
Your customer ordered 3-5 days ago. They may have forgotten. The rider shows up unannounced. Nobody's home. Package comes back.

This is the most preventable type of RTS — and it accounts for 30-40% of all returns. A simple "Your parcel is out for delivery today" message cuts this dramatically.

Sellers using delivery notifications consistently run 4-8 percentage points lower RTS than those who don't. On 1,000 orders, that's 40-80 fewer returns per month — ₱16,000-32,000 saved.

**What to do:** Enable SMS or chat notifications at key delivery milestones. The ROI is immediate.

## 5. You treat all pages and products the same
Not all pages perform equally. Not all products have the same RTS rate. Not all cities deliver at the same success rate. But if you're looking at one blended number for your whole operation, you're missing the details that matter.

We've seen sellers where one page runs 8% RTS and another runs 35% — same business, same products, different Facebook pages. The difference? Ad targeting, customer quality, and confirmation flow.

**What to do:** Break down your analytics by page, product, and region. Find the worst performers and fix them first. Don't average away your problems.

## The common thread
All 5 signs point to the same root cause: **lack of visibility**. You can't fix what you can't see, and most COD sellers in the Philippines are operating without the data they need to make informed decisions.

The good news? Once you start tracking, the fixes become obvious. The sellers who move their RTS from 30% to 20% don't do it with magic — they do it with data.
MARKDOWN,
            ],
            [
                'title' => 'The Hidden Cost of Every Failed Delivery',
                'slug' => 'hidden-cost-of-every-failed-delivery',
                'excerpt' => 'Most sellers think RTS only costs them the shipping fee. The real number is 3-5x higher.',
                'content' => <<<MARKDOWN
Ask a COD seller how much a failed delivery costs them, and most will say "around ₱100 — the shipping fee." That answer is off by a factor of 3 to 5.

The shipping fee is just the visible part. The real cost of every RTS parcel includes layers of hidden expenses that most sellers never calculate.

## The anatomy of an RTS cost
Let's break down what actually happens when a parcel gets returned, using typical numbers from a mid-sized PH COD seller:

### 1. Forward shipping — ₱120
You paid the courier to deliver it. Whether the customer accepts or not, that money is spent. Most couriers charge ₱100-150 depending on weight and destination.

### 2. Return shipping — ₱100
The parcel has to come back. Some couriers charge a return fee, others bundle it into your rate. Either way, there's a cost for the reverse logistics.

### 3. Packaging — ₱15
Box, bubble wrap, tape, filler, branded inserts. Small per unit, but it adds up — and it's completely wasted on a return.

### 4. Damage loss — ₱20
Roughly 10% of returned items come back damaged, dented, or unsealed. For a product with ₱200 COGS, that's ₱20 averaged across all returns.

### 5. Unrealized profit — ₱175
This is the biggest hidden cost. If your AOV is ₱500 and your margin is 35%, every failed delivery represents ₱175 in profit you would have earned. It's not a cash expense — it's an opportunity cost. But it's real.

## The real number: ₱430 per failed parcel
Add those up: ₱120 + ₱100 + ₱15 + ₱20 + ₱175 = **₱430 per RTS parcel**.

Now multiply. If you're shipping 1,000 orders a month at 30% RTS, that's 300 failed deliveries × ₱430 = **₱129,000 per month**. That's ₱1.55 million per year.

And most sellers don't even know this number exists.

## Why "just the shipping" thinking is dangerous
When you only think about the visible shipping cost, you underestimate the problem by 3-5x. That leads to bad decisions:

* You don't invest in reducing RTS because it seems like a small problem
* You focus on driving more sales volume instead of improving delivery success
* You don't flag or block high-risk buyers because the cost seems manageable

The moment you see the real number, your priorities change.

## Calculate your own bleed
We built a free tool that calculates your exact RTS bleed based on your actual numbers — orders, AOV, margin, shipping costs, and more. No signup required.

[Try the RTS Bleed Calculator →](/rts-calculator)

It takes 30 seconds, and the number will surprise you.
MARKDOWN,
            ],
            [
                'title' => 'How Parcel Journey Tracking Reduces RTS',
                'slug' => 'how-parcel-journey-tracking-reduces-rts',
                'excerpt' => 'When customers know where their parcel is, they\'re more likely to be home for delivery.',
                'content' => <<<MARKDOWN
One of the simplest, most effective ways to reduce RTS is something most Philippine COD sellers aren't doing: **telling customers where their parcel is**.

It sounds obvious. But the data proves it works — sellers who enable parcel journey notifications consistently see lower return rates than those who don't.

## Why parcels get returned
Before we talk about how tracking helps, let's understand why parcels come back in the first place. Based on data from thousands of Pancake POS sellers, the top reasons are:

* **Customer not home** — the rider attempted delivery but nobody was there (30-40% of RTS)
* **Customer refused** — changed their mind, forgot they ordered, or didn't expect the timing (20-30%)
* **Wrong address** — incomplete or incorrect delivery info (10-15%)
* **Failed contact** — rider couldn't reach the customer by phone (10-15%)
* **Fake orders** — bogus information from the start (5-10%)

Notice that the biggest chunk — "customer not home" — is the most preventable. If the customer **knew their parcel was arriving today**, they'd be more likely to be there.

## What is Parcel Journey tracking?
Parcel Journey is a per-order timeline that follows every status change from order confirmation to final delivery. Every courier scan, every attempt, every status update is logged.

But the real value isn't the timeline itself — it's the **notifications**. When key events happen, the customer gets a message:

* **Order confirmed** — "Salamat! Your order is confirmed and being prepared."
* **Shipped** — "Your parcel has been picked up by the courier."
* **Out for delivery** — "Your package is out for delivery today. Please be available."
* **Delivery attempt failed** — "We tried to deliver but you weren't available. We'll try again."

These messages go via SMS, Viber, or Messenger — whichever channel reaches the customer best.

## The data: notifications work
We tracked RTS rates across sellers who enabled notifications vs. those who didn't. The results:

* One health brand went from **32.95% to 17.21% RTS** within 3 months of enabling SMS + chat notifications
* A supplement seller maintained **sub-10% RTS** consistently with notifications active — while similar products without notifications sat at 15-20%
* Across all tracked sellers, notification-enabled pages averaged **4-8 percentage points lower RTS** than non-notification pages

The mechanism is straightforward: when customers know their parcel is coming, they prepare. They stay home. They answer the rider's call. They don't forget they ordered.

## How Artemis handles it
In Artemis, Parcel Journey tracking works via chat — you can check any order's delivery timeline and the customer gets automatic notifications at key milestones. No complex setup, no courier API integration required on your end.

Connect your Pancake page, and the tracking data flows automatically.

## The takeaway
Reducing RTS doesn't always require complex systems or expensive tools. Sometimes it's as simple as keeping your customer informed. A ₱1 SMS that prevents a ₱400+ RTS cost is the best ROI in your entire operation.
MARKDOWN,
            ],
            [
                'title' => 'What is RTS and Why It\'s Killing Your COD Business',
                'slug' => 'what-is-rts-and-why-it-matters',
                'excerpt' => 'Return-to-sender isn\'t just a logistics problem — it\'s the single biggest profit leak in Philippine COD e-commerce.',
                'content' => <<<MARKDOWN
If you're running a COD business in the Philippines, you've seen it happen: you ship a parcel, the rider attempts delivery, the customer isn't home — or worse, refuses the package — and it comes right back to you. That's **RTS — Return to Sender**.

Most sellers treat RTS as a minor annoyance. A cost of doing business. Something you just accept.

That mindset is costing you more than you think.

## The real RTS rate in the Philippines
Based on data from Pancake POS sellers tracked by Artemis, the average RTS rate for Philippine COD businesses sits between **20% and 40%**. Some product categories — particularly health supplements and beauty products — regularly hit 30-45%.

That means for every 1,000 orders you ship, 200 to 400 parcels come back unsold. And every single one costs you real money.

## Why RTS is worse than you think
When a parcel is returned, you don't just lose the sale. You lose:

* **Forward shipping cost** — you paid to send it (₱100-150)
* **Return shipping cost** — you pay to get it back (₱80-120)
* **Packaging** — box, tape, filler, all wasted (₱10-20)
* **Damaged goods** — 5-15% of returns come back unsellable
* **Unrealized profit** — the margin you would have earned
* **Time and labor** — staff processing returns instead of fulfilling orders

Add it all up, and a single failed delivery costs you **₱150 to ₱350**. Multiply that by hundreds of returns per month, and you're looking at tens of thousands of pesos walking out the door — every month.

## Why most sellers don't know their real number
Here's the uncomfortable truth: most COD sellers in the Philippines don't track their RTS rate. They know it's "high" or "normal" but can't tell you the actual percentage, the cost per failed parcel, or which pages and cities are driving the most returns.

Without that visibility, you can't fix what you can't see. You end up throwing money at ads to drive more volume, when the real problem is that 30% of your orders never convert into revenue.

## What you can do about it
The first step is simple: **know your number**. Not a rough guess — the actual RTS rate, broken down by page, product, city, and time period.

The second step is understanding **why** parcels are being returned. Is it fake orders? Wrong addresses? Customers not being home? Each cause has a different solution.

The third step is **acting on the data**. Customer notifications during delivery dramatically reduce "not home" RTS. Address validation catches bad addresses before you ship. Buyer risk scoring flags repeat offenders.

This is exactly what Artemis was built to do — give Philippine COD sellers the visibility and tools to move their RTS number in the right direction.

## The bottom line
RTS isn't a minor cost of doing business. For most PH COD sellers, it's the single largest preventable expense — bigger than ad spend, bigger than packaging, bigger than most operational costs combined.

The sellers who track it and act on it keep more of every peso they earn. The sellers who ignore it keep bleeding.
MARKDOWN,
            ],
            [
                'title' => 'Why Philippine COD Sellers Need Analytics in 2026',
                'slug' => 'why-ph-cod-sellers-need-analytics',
                'excerpt' => 'The COD market is getting more competitive. Sellers who track their numbers will survive. Sellers who don\'t will keep guessing.',
                'content' => <<<MARKDOWN
Philippine e-commerce is booming. COD remains king — over 60% of online purchases in the Philippines are still cash-on-delivery. But the landscape is shifting, and the sellers who will thrive in 2026 and beyond are the ones who **run their business on data, not gut feel**.

## The COD market is maturing
Five years ago, you could throw up a Facebook ad, get orders, ship them out, and make money even with a 40% RTS rate. The margins were fat enough to absorb the waste.

That era is ending. Here's what's changed:

* **Ad costs are rising** — CPMs on Facebook and TikTok have increased 30-50% year over year. Every wasted delivery hurts more.
* **Competition is fiercer** — more sellers, more products, thinner margins. You can't afford 30% waste anymore.
* **Courier standards are tightening** — couriers are penalizing high-RTS sellers with higher rates or deprioritized service.
* **Customers expect more** — tracking, notifications, professionalism. The bare-minimum seller gets left behind.

## What "analytics" actually means for COD
When we say analytics, we don't mean complicated dashboards with 50 charts you'll never look at. For a COD seller, analytics means answering these questions:

* **How much am I actually making?** — Revenue minus all costs, including RTS waste.
* **Where am I losing money?** — Which pages, products, cities, and customer segments have the highest RTS?
* **What's getting better or worse?** — Is my RTS trending up or down? Is this week better than last?
* **What should I do next?** — Which actions will have the biggest impact on my bottom line?

If you can't answer these questions with real numbers, you're guessing. And guessing gets expensive.

## The spreadsheet trap
Some sellers track their numbers in spreadsheets. That's better than nothing — but it has real limitations:

* **Manual entry** — someone has to input data, which means delays and errors
* **No real-time view** — you're always looking at yesterday's numbers (or last week's)
* **No breakdown** — hard to slice by page, product, city, and time period simultaneously
* **No automation** — the spreadsheet tells you the problem but doesn't help you fix it

Spreadsheets are a step up from nothing. But they don't scale, and they don't take action.

## What data-driven sellers do differently
The best-performing COD sellers we work with share a few traits:

* **They check their numbers daily** — not monthly, not weekly. Daily. They catch problems early.
* **They know their RTS by page** — and they shut down or fix underperforming pages instead of averaging them away.
* **They notify customers** — automated messages at key delivery milestones. It's cheap, it works, and it's table stakes.
* **They track trends, not just snapshots** — is this month better than last? Is the trend going the right direction?

None of this is rocket science. It's discipline plus visibility. But the results compound dramatically over time.

## Why we built Artemis
Artemis exists because we saw hundreds of COD sellers — many of them doing ₱500k-5M in monthly revenue — who had no idea where their money was actually going. They were profitable, but they could have been **significantly more profitable** with basic visibility into their operations.

We built the 1st analytics and automation platform specifically for Philippine COD e-commerce. It connects to Pancake POS, pulls your data automatically, and shows you exactly what's happening — across sales, delivery, RTS, and parcel journey.

No spreadsheets. No manual entry. No guessing.

## The sellers who track, win
2026 is the year when the COD market separates into two groups: sellers who run on data, and sellers who keep guessing. The gap between them will only widen.

Which group do you want to be in?
MARKDOWN,
            ],
        ];

        foreach ($posts as $post) {
            BlogPost::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'status' => 'published',
                    'published_at' => Carbon::now(),
                    'seo_title' => $post['title'],
                    'seo_description' => $post['excerpt'],
                ]
            );
        }
    }
}
