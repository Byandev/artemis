import { BlogPost } from './blog-layout';

export default function Post() {
    return (
        <BlogPost
            title="What is RTS and Why It's Killing Your COD Business"
            date="April 15, 2026"
            readTime="5 min read"
            category="Fundamentals"
            description="Return-to-sender isn't just a logistics problem — it's the single biggest profit leak in Philippine COD e-commerce."
        >
            <p>
                If you're running a COD business in the Philippines, you've seen it happen: you ship a parcel, the rider attempts delivery, the customer isn't home — or worse, refuses the package — and it comes right back to you. That's <strong>RTS — Return to Sender</strong>.
            </p>
            <p>
                Most sellers treat RTS as a minor annoyance. A cost of doing business. Something you just accept.
            </p>
            <p>
                That mindset is costing you more than you think.
            </p>

            <h2>The real RTS rate in the Philippines</h2>
            <p>
                Based on data from Pancake POS sellers tracked by Artemis, the average RTS rate for Philippine COD businesses sits between <strong>20% and 40%</strong>. Some product categories — particularly health supplements and beauty products — regularly hit 30-45%.
            </p>
            <p>
                That means for every 1,000 orders you ship, 200 to 400 parcels come back unsold. And every single one costs you real money.
            </p>

            <h2>Why RTS is worse than you think</h2>
            <p>
                When a parcel is returned, you don't just lose the sale. You lose:
            </p>
            <ul>
                <li><strong>Forward shipping cost</strong> — you paid to send it (₱100-150)</li>
                <li><strong>Return shipping cost</strong> — you pay to get it back (₱80-120)</li>
                <li><strong>Packaging</strong> — box, tape, filler, all wasted (₱10-20)</li>
                <li><strong>Damaged goods</strong> — 5-15% of returns come back unsellable</li>
                <li><strong>Unrealized profit</strong> — the margin you would have earned</li>
                <li><strong>Time and labor</strong> — staff processing returns instead of fulfilling orders</li>
            </ul>
            <p>
                Add it all up, and a single failed delivery costs you <strong>₱150 to ₱350</strong>. Multiply that by hundreds of returns per month, and you're looking at tens of thousands of pesos walking out the door — every month.
            </p>

            <h2>Why most sellers don't know their real number</h2>
            <p>
                Here's the uncomfortable truth: most COD sellers in the Philippines don't track their RTS rate. They know it's "high" or "normal" but can't tell you the actual percentage, the cost per failed parcel, or which pages and cities are driving the most returns.
            </p>
            <p>
                Without that visibility, you can't fix what you can't see. You end up throwing money at ads to drive more volume, when the real problem is that 30% of your orders never convert into revenue.
            </p>

            <h2>What you can do about it</h2>
            <p>
                The first step is simple: <strong>know your number</strong>. Not a rough guess — the actual RTS rate, broken down by page, product, city, and time period.
            </p>
            <p>
                The second step is understanding <strong>why</strong> parcels are being returned. Is it fake orders? Wrong addresses? Customers not being home? Each cause has a different solution.
            </p>
            <p>
                The third step is <strong>acting on the data</strong>. Customer notifications during delivery dramatically reduce "not home" RTS. Address validation catches bad addresses before you ship. Buyer risk scoring flags repeat offenders.
            </p>
            <p>
                This is exactly what Artemis was built to do — give Philippine COD sellers the visibility and tools to move their RTS number in the right direction.
            </p>

            <h2>The bottom line</h2>
            <p>
                RTS isn't a minor cost of doing business. For most PH COD sellers, it's the single largest preventable expense — bigger than ad spend, bigger than packaging, bigger than most operational costs combined.
            </p>
            <p>
                The sellers who track it and act on it keep more of every peso they earn. The sellers who ignore it keep bleeding.
            </p>
        </BlogPost>
    );
}
