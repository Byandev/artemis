import LegalLayout from './legal-layout';

export default function Security() {
    return (
        <LegalLayout title="Security" lastUpdated="April 17, 2026" description="How Artemis protects your data with industry-standard security practices.">
            <p>
                At Artemis, the security of your business data is a top priority. We implement multiple layers of protection to ensure your information stays safe, private, and accessible only to you.
            </p>

            <h2>Infrastructure security</h2>

            <h3>Cloud hosting</h3>
            <ul>
                <li>Application and database are hosted on cloud infrastructure with automated backups and point-in-time recovery</li>
                <li>Cloud-managed services with built-in redundancy and high availability</li>
                <li>Regular automated backups with secure offsite storage</li>
            </ul>

            <h3>Network security</h3>
            <ul>
                <li>All traffic between your browser and Artemis is encrypted using TLS 1.2+ (HTTPS)</li>
                <li>Internal service-to-service communication is encrypted</li>
                <li>Firewalls and network segmentation limit access to production systems</li>
                <li>DDoS protection is in place at the infrastructure level</li>
            </ul>

            <h2>Data security</h2>

            <h3>Encryption</h3>
            <ul>
                <li><strong>In transit</strong> — all data transmitted to and from Artemis uses TLS encryption</li>
                <li><strong>At rest</strong> — all stored data, including database contents and backups, is encrypted at rest</li>
                <li><strong>Secrets</strong> — API keys, tokens, and sensitive credentials are encrypted using application-level encryption before storage</li>
            </ul>

            <h3>Data isolation</h3>
            <ul>
                <li>Each workspace's data is logically isolated — no workspace can access another's data</li>
                <li>Role-based access control (RBAC) within workspaces ensures team members only see what they need</li>
                <li>API key access is scoped to individual workspaces with per-key permissions</li>
            </ul>

            <h2>Application security</h2>

            <h3>Authentication</h3>
            <ul>
                <li>Passwords are hashed using industry-standard bcrypt algorithms — we never store plain-text passwords</li>
                <li>Two-factor authentication (2FA) is available for all accounts</li>
                <li>Session management with automatic expiration and secure cookie handling</li>
                <li>Rate limiting on login attempts to prevent brute-force attacks</li>
            </ul>

            <h3>Development practices</h3>
            <ul>
                <li>Code reviews for all changes before deployment</li>
                <li>Automated testing suite run on every deployment</li>
                <li>Dependencies regularly audited for known vulnerabilities</li>
                <li>Protection against common web vulnerabilities (XSS, CSRF, SQL injection) built into the framework</li>
            </ul>

            <h2>Access controls</h2>
            <ul>
                <li>Production access is restricted to essential personnel only</li>
                <li>All administrative access is logged and auditable</li>
                <li>We follow the principle of least privilege — team members only have access necessary for their role</li>
            </ul>

            <h2>Incident response</h2>
            <p>
                In the event of a security incident, we follow a structured response process:
            </p>
            <ul>
                <li><strong>Identify</strong> — detect and assess the scope of the incident</li>
                <li><strong>Contain</strong> — isolate affected systems to prevent further impact</li>
                <li><strong>Notify</strong> — inform affected users within 72 hours as required by RA 10173</li>
                <li><strong>Remediate</strong> — fix the root cause and implement preventive measures</li>
                <li><strong>Review</strong> — post-incident analysis to strengthen defenses</li>
            </ul>

            <h2>Compliance</h2>
            <ul>
                <li><strong>Data Privacy Act (RA 10173)</strong> — fully compliant with Philippine data privacy regulations</li>
                <li><strong>NPC registration</strong> — registered with the National Privacy Commission as a personal information controller</li>
                <li>Regular internal reviews of data handling and security practices</li>
            </ul>

            <h2>Responsible disclosure</h2>
            <p>
                If you discover a security vulnerability in Artemis, we encourage responsible disclosure. Please report it to <strong>hello@artemis.ph</strong> with the subject line "Security Report." We will acknowledge receipt within 24 hours and work to resolve the issue promptly.
            </p>

            <h2>Questions</h2>
            <p>
                For questions about our security practices, contact us at <strong>hello@artemis.ph</strong>.
            </p>
        </LegalLayout>
    );
}
