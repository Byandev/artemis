import { Link } from '@inertiajs/react';
import { BlogPost } from './blog-layout';

export default function Post() {
    return (
        <BlogPost
            title="The Hidden Cost of Every Failed Delivery"
            date="April 12, 2026"
            readTime="4 min read"
            category="Analytics"
            description="Most sellers think RTS only costs them the shipping fee. The real number is 3-5x higher."
        >
            <p>
                Ask a COD seller how much a failed delivery costs them, and most will say "around ₱100 — the shipping fee." That answer is off by a factor of 3 to 5.
            </p>
            <p>
                The shipping fee is just the visible part. The real cost of every RTS parcel includes layers of hidden expenses that most sellers never calculate.
            </p>

            <h2>The anatomy of an RTS cost</h2>
            <p>
                Let's break down what actually happens when a parcel gets returned, using typical numbers from a mid-sized PH COD seller:
            </p>

            <h3>1. Forward shipping — ₱120</h3>
            <p>
                You paid the courier to deliver it. Whether the customer accepts or not, that money is spent. Most couriers charge ₱100-150 depending on weight and destination.
            </p>

            <h3>2. Return shipping — ₱100</h3>
            <p>
                The parcel has to come back. Some couriers charge a return fee, others bundle it into your rate. Either way, there's a cost for the reverse logistics.
            </p>

            <h3>3. Packaging — ₱15</h3>
            <p>
                Box, bubble wrap, tape, filler, branded inserts. Small per unit, but it adds up — and it's completely wasted on a return.
            </p>

            <h3>4. Damage loss — ₱20</h3>
            <p>
                Roughly 10% of returned items come back damaged, dented, or unsealed. For a product with ₱200 COGS, that's ₱20 averaged across all returns.
            </p>

            <h3>5. Unrealized profit — ₱175</h3>
            <p>
                This is the biggest hidden cost. If your AOV is ₱500 and your margin is 35%, every failed delivery represents ₱175 in profit you would have earned. It's not a cash expense — it's an opportunity cost. But it's real.
            </p>

            <h2>The real number: ₱430 per failed parcel</h2>
            <p>
                Add those up: ₱120 + ₱100 + ₱15 + ₱20 + ₱175 = <strong>₱430 per RTS parcel</strong>.
            </p>
            <p>
                Now multiply. If you're shipping 1,000 orders a month at 30% RTS, that's 300 failed deliveries × ₱430 = <strong>₱129,000 per month</strong>. That's ₱1.55 million per year.
            </p>
            <p>
                And most sellers don't even know this number exists.
            </p>

            <h2>Why "just the shipping" thinking is dangerous</h2>
            <p>
                When you only think about the visible shipping cost, you underestimate the problem by 3-5x. That leads to bad decisions:
            </p>
            <ul>
                <li>You don't invest in reducing RTS because it seems like a small problem</li>
                <li>You focus on driving more sales volume instead of improving delivery success</li>
                <li>You don't flag or block high-risk buyers because the cost seems manageable</li>
            </ul>
            <p>
                The moment you see the real number, your priorities change.
            </p>

            <h2>Calculate your own bleed</h2>
            <p>
                We built a free tool that calculates your exact RTS bleed based on your actual numbers — orders, AOV, margin, shipping costs, and more. No signup required.
            </p>
            <p>
                <Link href="/rts-calculator" className="font-semibold text-brand-500 hover:text-brand-600">
                    Try the RTS Bleed Calculator →
                </Link>
            </p>
            <p>
                It takes 30 seconds, and the number will surprise you.
            </p>
        </BlogPost>
    );
}
