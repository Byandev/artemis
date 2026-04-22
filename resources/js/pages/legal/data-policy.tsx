import LegalLayout from './legal-layout';

export default function DataPolicy() {
    return (
        <LegalLayout title="Data Policy" lastUpdated="April 17, 2026" description="How Artemis handles, processes, and protects your e-commerce business data.">
            <p>
                This Data Policy explains how Artemis collects, processes, and manages your e-commerce business data. This policy supplements our <a href="/privacy" className="font-semibold text-brand-500 hover:text-brand-600">Privacy Policy</a> and focuses specifically on the business data that flows through our platform.
            </p>

            <h2>What business data we process</h2>
            <p>When you connect your Pancake POS page to Artemis, we process the following categories of data:</p>

            <h3>Order data</h3>
            <ul>
                <li>Order IDs, dates, and amounts</li>
                <li>Order status (confirmed, shipped, delivered, returned)</li>
                <li>Product information associated with orders</li>
                <li>Payment method and COD amounts</li>
            </ul>

            <h3>Customer data</h3>
            <ul>
                <li>Customer names and contact information (phone numbers, addresses)</li>
                <li>Delivery addresses and location data</li>
                <li>Order history per customer</li>
            </ul>

            <h3>Delivery data</h3>
            <ul>
                <li>Courier assignment and tracking information</li>
                <li>Delivery attempt logs and status updates</li>
                <li>RTS (return-to-sender) events and reasons</li>
                <li>Delivery timelines and fulfillment metrics</li>
            </ul>

            <h3>Page and shop data</h3>
            <ul>
                <li>Connected Pancake page names and identifiers</li>
                <li>Shop metadata and configuration</li>
            </ul>

            <h2>How we process your data</h2>
            <p>Your business data is processed to provide the core Artemis analytics services:</p>
            <ul>
                <li><strong>Sales analytics</strong> — revenue, order volume, AOV, and trend calculations</li>
                <li><strong>Delivery analytics</strong> — delivery success rates, attempt counts, and courier performance</li>
                <li><strong>RTS analytics</strong> — return rates by page, product, city, and time period</li>
                <li><strong>Parcel Journey</strong> — per-order delivery timelines and customer notification triggers</li>
                <li><strong>Operational metrics</strong> — fulfillment lead times and bottleneck identification</li>
            </ul>

            <h2>Data isolation</h2>
            <p>
                Artemis is a multi-workspace platform. Your data is strictly isolated within your workspace. No other workspace or user can access your business data. Team members you invite to your workspace only see data within the permissions you assign via role-based access control.
            </p>

            <h2>Third-party data sources</h2>
            <p>
                We pull data from Pancake POS via authorized API connections. We only access data that your Pancake account has permission to provide. We do not modify, write to, or alter data in your Pancake POS account — our access is read-only.
            </p>

            <h2>Customer notification data</h2>
            <p>
                When Parcel Journey tracking is enabled, we use customer contact information (phone numbers) to send delivery status notifications via SMS, Viber, or Messenger. These notifications contain only order and delivery information — no marketing or promotional content.
            </p>

            <h2>Data aggregation</h2>
            <p>
                We may aggregate and anonymize data across the platform to generate industry benchmarks and insights (e.g., average RTS rates by product category). This aggregated data cannot be traced back to any individual seller, customer, or workspace.
            </p>

            <h2>Data export</h2>
            <p>
                You can request an export of your business data at any time. We provide data exports in standard formats (CSV). Contact us at <strong>hello@artemis.ph</strong> to request an export.
            </p>

            <h2>Data deletion</h2>
            <p>
                When you disconnect a Pancake page, we stop pulling new data from that page. Historical data remains available in your workspace until you explicitly request deletion. When you delete your workspace or account, all associated business data is permanently deleted within 30 days.
            </p>

            <h2>Compliance</h2>
            <p>
                Our data processing practices comply with the <strong>Data Privacy Act of 2012 (RA 10173)</strong> and its Implementing Rules and Regulations. We are registered with the National Privacy Commission (NPC) as a personal information controller.
            </p>

            <h2>Questions</h2>
            <p>
                For questions about how we handle your business data, contact us at <strong>hello@artemis.ph</strong>.
            </p>
        </LegalLayout>
    );
}
