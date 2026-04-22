import LegalLayout from './legal-layout';

export default function Terms() {
    return (
        <LegalLayout title="Terms of Service" lastUpdated="April 17, 2026" description="Terms and conditions governing your use of the Artemis platform.">
            <p>
                These Terms of Service ("Terms") govern your use of the Artemis platform operated by Artemis ("we," "our," or "us"). By creating an account or using our services, you agree to be bound by these Terms.
            </p>

            <h2>1. The service</h2>
            <p>
                Artemis is an analytics and automation platform for Philippine COD e-commerce sellers. We provide tools for tracking sales, delivery, RTS (return-to-sender) analytics, and Parcel Journey tracking by connecting to your Pancake POS account.
            </p>

            <h2>2. Account registration</h2>
            <p>To use Artemis, you must:</p>
            <ul>
                <li>Be at least 18 years old or have the legal capacity to enter into a binding agreement</li>
                <li>Provide accurate and complete registration information</li>
                <li>Keep your login credentials secure — you are responsible for all activity under your account</li>
                <li>Notify us immediately of any unauthorized access to your account</li>
            </ul>

            <h2>3. Free trial</h2>
            <p>
                We offer a 14-day free trial that includes connecting 1 Pancake page, 1 month of order and delivery data, and Parcel Journey tracking via chat. No credit card is required for the free trial. We reserve the right to modify or discontinue the free trial offer at any time.
            </p>

            <h2>4. Acceptable use</h2>
            <p>You agree not to:</p>
            <ul>
                <li>Use Artemis for any unlawful purpose or in violation of any applicable laws</li>
                <li>Attempt to access other users' data, accounts, or workspaces without authorization</li>
                <li>Reverse engineer, decompile, or disassemble any part of the platform</li>
                <li>Use the platform to send spam, harass, or harm others</li>
                <li>Interfere with or disrupt the platform's infrastructure or other users' access</li>
                <li>Resell, sublicense, or redistribute access to the platform without our written consent</li>
            </ul>

            <h2>5. Data and content</h2>
            <p>
                <strong>Your data:</strong> You retain ownership of all data you provide or that is pulled from your connected Pancake POS pages. We do not claim ownership of your business data.
            </p>
            <p>
                <strong>Our license:</strong> You grant us a limited license to access, process, and display your data solely for the purpose of providing the Artemis services to you.
            </p>
            <p>
                <strong>Data accuracy:</strong> We pull data directly from Pancake POS and display it as-is. We are not responsible for inaccuracies in data originating from third-party sources.
            </p>

            <h2>6. Intellectual property</h2>
            <p>
                The Artemis platform, including its design, code, features, documentation, and branding, is owned by us and protected by intellectual property laws. You may not copy, modify, or distribute any part of the platform without our written permission.
            </p>

            <h2>7. Service availability</h2>
            <p>
                We strive to maintain high availability but do not guarantee uninterrupted access. We may perform scheduled maintenance, and we will make reasonable efforts to notify you in advance. We are not liable for any downtime, data delays, or service interruptions caused by third-party providers (including Pancake POS or courier systems).
            </p>

            <h2>8. Limitation of liability</h2>
            <p>
                To the maximum extent permitted by law, Artemis shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including loss of profits, data, or business opportunities, arising from your use of the platform.
            </p>
            <p>
                Our total liability for any claim arising from these Terms or your use of the platform shall not exceed the amount you paid us in the 12 months preceding the claim.
            </p>

            <h2>9. Termination</h2>
            <p>
                You may cancel your account at any time. We may suspend or terminate your access if you violate these Terms or for any other reason with reasonable notice. Upon termination, your right to use the platform ceases immediately. You may request an export of your data before account deletion.
            </p>

            <h2>10. Changes to these Terms</h2>
            <p>
                We may update these Terms from time to time. We will notify you of material changes via email or in-app notification. Your continued use of Artemis after changes take effect constitutes acceptance of the updated Terms.
            </p>

            <h2>11. Governing law</h2>
            <p>
                These Terms are governed by the laws of the Republic of the Philippines. Any disputes arising from these Terms shall be resolved in the courts of the Philippines.
            </p>

            <h2>12. Contact</h2>
            <p>
                Questions about these Terms? Contact us at <strong>hello@artemis.ph</strong> or visit our <a href="/contact" className="font-semibold text-brand-500 hover:text-brand-600">Contact page</a>.
            </p>
        </LegalLayout>
    );
}
