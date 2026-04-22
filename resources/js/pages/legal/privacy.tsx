import LegalLayout from './legal-layout';

export default function Privacy() {
    return (
        <LegalLayout title="Privacy Policy" lastUpdated="April 17, 2026" description="How Artemis collects, uses, and protects your personal data.">
            <p>
                Artemis ("we," "our," or "us") is committed to protecting the privacy of our users. This Privacy Policy explains how we collect, use, store, and share information when you use our platform, website, and related services.
            </p>
            <p>
                By using Artemis, you agree to the collection and use of information in accordance with this policy. We comply with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong> of the Philippines.
            </p>

            <h2>Information we collect</h2>

            <h3>Account information</h3>
            <p>When you create an account, we collect:</p>
            <ul>
                <li>Name</li>
                <li>Email address</li>
                <li>Password (encrypted, never stored in plain text)</li>
                <li>Phone number (optional)</li>
                <li>Business/workspace name</li>
            </ul>

            <h3>Business data from Pancake POS</h3>
            <p>When you connect your Pancake POS page, we pull the following data to provide our analytics services:</p>
            <ul>
                <li>Order information (order ID, date, amount, status)</li>
                <li>Customer information (name, phone, address — used for delivery analytics)</li>
                <li>Delivery and courier data (tracking status, delivery attempts, RTS status)</li>
                <li>Page and shop metadata</li>
            </ul>

            <h3>Usage data</h3>
            <p>We automatically collect information about how you interact with Artemis, including pages visited, features used, and session duration. This helps us improve the platform.</p>

            <h2>How we use your information</h2>
            <p>We use the information we collect to:</p>
            <ul>
                <li>Provide and maintain the Artemis platform and analytics services</li>
                <li>Generate RTS, delivery, sales, and operational analytics for your workspace</li>
                <li>Send Parcel Journey notifications to your customers (when enabled)</li>
                <li>Provide customer support via chat</li>
                <li>Improve our platform and develop new features</li>
                <li>Communicate important updates about your account or our services</li>
            </ul>

            <h2>Data sharing</h2>
            <p>We do <strong>not</strong> sell, trade, or rent your personal information or business data to third parties. We may share data only in these cases:</p>
            <ul>
                <li><strong>Service providers</strong> — trusted third parties that help us operate our platform (hosting, email, SMS delivery), bound by confidentiality agreements</li>
                <li><strong>Legal requirements</strong> — when required by law, regulation, or legal process</li>
                <li><strong>Business transfers</strong> — in connection with a merger, acquisition, or sale of assets (with prior notice)</li>
            </ul>

            <h2>Data storage and security</h2>
            <p>Your data is stored on secure servers with encryption at rest and in transit. We implement industry-standard security measures including access controls, regular security audits, and monitoring. See our <a href="/security" className="font-semibold text-brand-500 hover:text-brand-600">Security page</a> for more details.</p>

            <h2>Data retention</h2>
            <p>We retain your account data for as long as your account is active. Business analytics data is retained for the duration of your subscription. When you delete your account, we remove your personal data within 30 days. Aggregated, anonymized data may be retained for analytics purposes.</p>

            <h2>Your rights</h2>
            <p>Under the Data Privacy Act (RA 10173), you have the right to:</p>
            <ul>
                <li><strong>Access</strong> — request a copy of the personal data we hold about you</li>
                <li><strong>Correction</strong> — request correction of inaccurate or incomplete data</li>
                <li><strong>Erasure</strong> — request deletion of your personal data</li>
                <li><strong>Data portability</strong> — request an export of your data in a machine-readable format</li>
                <li><strong>Object</strong> — object to data processing in certain circumstances</li>
            </ul>
            <p>To exercise any of these rights, contact us at <strong>hello@artemis.ph</strong>.</p>

            <h2>Cookies</h2>
            <p>We use essential cookies to maintain your session and remember your preferences (such as dark/light mode). We do not use third-party advertising or tracking cookies.</p>

            <h2>Changes to this policy</h2>
            <p>We may update this Privacy Policy from time to time. We will notify you of significant changes via email or a prominent notice on our platform. Continued use of Artemis after changes constitutes acceptance of the updated policy.</p>

            <h2>Contact us</h2>
            <p>If you have questions about this Privacy Policy or our data practices, contact us at <strong>hello@artemis.ph</strong> or visit our <a href="/contact" className="font-semibold text-brand-500 hover:text-brand-600">Contact page</a>.</p>
        </LegalLayout>
    );
}
