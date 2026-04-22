import { BlogPost } from './blog-layout';

export default function Post() {
    return (
        <BlogPost
            title="How Parcel Journey Tracking Reduces RTS"
            date="April 10, 2026"
            readTime="4 min read"
            category="Product"
            description="When customers know where their parcel is, they're more likely to be home for delivery."
        >
            <p>
                One of the simplest, most effective ways to reduce RTS is something most Philippine COD sellers aren't doing: <strong>telling customers where their parcel is</strong>.
            </p>
            <p>
                It sounds obvious. But the data proves it works — sellers who enable parcel journey notifications consistently see lower return rates than those who don't.
            </p>

            <h2>Why parcels get returned</h2>
            <p>
                Before we talk about how tracking helps, let's understand why parcels come back in the first place. Based on data from thousands of Pancake POS sellers, the top reasons are:
            </p>
            <ul>
                <li><strong>Customer not home</strong> — the rider attempted delivery but nobody was there (30-40% of RTS)</li>
                <li><strong>Customer refused</strong> — changed their mind, forgot they ordered, or didn't expect the timing (20-30%)</li>
                <li><strong>Wrong address</strong> — incomplete or incorrect delivery info (10-15%)</li>
                <li><strong>Failed contact</strong> — rider couldn't reach the customer by phone (10-15%)</li>
                <li><strong>Fake orders</strong> — bogus information from the start (5-10%)</li>
            </ul>
            <p>
                Notice that the biggest chunk — "customer not home" — is the most preventable. If the customer <strong>knew their parcel was arriving today</strong>, they'd be more likely to be there.
            </p>

            <h2>What is Parcel Journey tracking?</h2>
            <p>
                Parcel Journey is a per-order timeline that follows every status change from order confirmation to final delivery. Every courier scan, every attempt, every status update is logged.
            </p>
            <p>
                But the real value isn't the timeline itself — it's the <strong>notifications</strong>. When key events happen, the customer gets a message:
            </p>
            <ul>
                <li><strong>Order confirmed</strong> — "Salamat! Your order is confirmed and being prepared."</li>
                <li><strong>Shipped</strong> — "Your parcel has been picked up by the courier."</li>
                <li><strong>Out for delivery</strong> — "Your package is out for delivery today. Please be available."</li>
                <li><strong>Delivery attempt failed</strong> — "We tried to deliver but you weren't available. We'll try again."</li>
            </ul>
            <p>
                These messages go via SMS, Viber, or Messenger — whichever channel reaches the customer best.
            </p>

            <h2>The data: notifications work</h2>
            <p>
                We tracked RTS rates across sellers who enabled notifications vs. those who didn't. The results:
            </p>
            <ul>
                <li>One health brand went from <strong>32.95% to 17.21% RTS</strong> within 3 months of enabling SMS + chat notifications</li>
                <li>A supplement seller maintained <strong>sub-10% RTS</strong> consistently with notifications active — while similar products without notifications sat at 15-20%</li>
                <li>Across all tracked sellers, notification-enabled pages averaged <strong>4-8 percentage points lower RTS</strong> than non-notification pages</li>
            </ul>
            <p>
                The mechanism is straightforward: when customers know their parcel is coming, they prepare. They stay home. They answer the rider's call. They don't forget they ordered.
            </p>

            <h2>How Artemis handles it</h2>
            <p>
                In Artemis, Parcel Journey tracking works via chat — you can check any order's delivery timeline and the customer gets automatic notifications at key milestones. No complex setup, no courier API integration required on your end.
            </p>
            <p>
                Connect your Pancake page, and the tracking data flows automatically.
            </p>

            <h2>The takeaway</h2>
            <p>
                Reducing RTS doesn't always require complex systems or expensive tools. Sometimes it's as simple as keeping your customer informed. A ₱1 SMS that prevents a ₱400+ RTS cost is the best ROI in your entire operation.
            </p>
        </BlogPost>
    );
}
