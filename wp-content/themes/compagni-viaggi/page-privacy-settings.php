<?php
/**
 * Template Name: Privacy Settings
 * Template for user privacy and GDPR settings
 */

// Require login
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

get_header();

$user_id = get_current_user_id();
$user = get_userdata($user_id);

// Get user consent data
$cookie_consent = get_user_meta($user_id, 'cdv_cookie_consent', true);
?>

<main class="site-main privacy-settings-page">
    <div class="page-header">
        <div class="container">
            <h1>🔒 Privacy & Data Settings</h1>
            <p>Update your privacy preferences and manage your personal data</p>
        </div>
    </div>

    <div class="container">
        <!-- Cookie Consent Section -->
        <div class="privacy-settings-section">
            <h2>🍪 Cookie Preferences</h2>

            <?php if ($cookie_consent) : ?>
                <div class="gdpr-notice">
                    <h4>Consent Status</h4>
                    <p>
                        <strong>Last updated:</strong> <?php echo esc_html(date('M j, Y H:i', strtotime($cookie_consent['timestamp']))); ?><br>
                        <strong>Analytics cookies:</strong> <?php echo $cookie_consent['analytics'] ? '✓ Accepted' : '✗ Declined'; ?><br>
                        <strong>Marketing cookies:</strong> <?php echo $cookie_consent['marketing'] ? '✓ Accepted' : '✗ Declined'; ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="privacy-setting-item">
                <h3>Essential Cookies</h3>
                <p>Required for the site to function (authentication, forms, preferences). Always enabled.</p>
                <label style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                    <input type="checkbox" checked disabled style="width: 20px; height: 20px;">
                    <strong>Enabled (mandatory)</strong>
                </label>
            </div>

            <div class="privacy-setting-item">
                <h3>Analytics Cookies</h3>
                <p>Help us understand how you use the site so we can improve the experience.</p>
                <label style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                    <input type="checkbox" class="gdpr-consent-checkbox" data-consent-type="analytics"
                           <?php echo (isset($cookie_consent['analytics']) && $cookie_consent['analytics']) ? 'checked' : ''; ?>
                           style="width: 20px; height: 20px;">
                    <strong>Enable analytics cookies</strong>
                </label>
            </div>

            <div class="privacy-setting-item">
                <h3>Marketing Cookies</h3>
                <p>Used to show you personalized marketing content.</p>
                <label style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                    <input type="checkbox" class="gdpr-consent-checkbox" data-consent-type="marketing"
                           <?php echo (isset($cookie_consent['marketing']) && $cookie_consent['marketing']) ? 'checked' : ''; ?>
                           style="width: 20px; height: 20px;">
                    <strong>Enable marketing cookies</strong>
                </label>
            </div>
        </div>

        <!-- Data Export Section -->
        <div class="privacy-settings-section">
            <h2>📦 Export Your Data</h2>
            <div class="privacy-setting-item">
                <h3>Request a Copy of Your Data</h3>
                <p>
                    Under GDPR you can request a copy of every piece of personal data we store about you, including your profile,
                    trips, messages, reviews, and other activity.
                </p>
                <p>
                    The export is delivered in a readable JSON file so you can reuse it elsewhere.
                </p>
                <div class="privacy-actions">
                    <button id="cdv-export-data" class="btn btn-primary">
                        📥 Download Your Data
                    </button>
                </div>
            </div>
        </div>

        <!-- Data Retention Section -->
        <div class="privacy-settings-section">
            <h2>⏱️ Data Retention</h2>
            <div class="privacy-setting-item">
                <h3>Retention Policy</h3>
                <p>
                    We store personal data only for the time required to provide our service:
                </p>
                <ul style="margin: 15px 0; padding-left: 25px; line-height: 1.8;">
                    <li><strong>User profile:</strong> Until your account is deleted</li>
                    <li><strong>Published trips:</strong> Until you remove them</li>
                    <li><strong>Messages:</strong> 2 years from the sending date (then removed automatically)</li>
                    <li><strong>Reviews:</strong> Retained permanently but anonymized if you delete your account</li>
                    <li><strong>Security logs:</strong> 90 days</li>
                </ul>
            </div>
        </div>

        <!-- Data Processing Section -->
        <div class="privacy-settings-section">
            <h2>⚙️ How We Use Your Data</h2>
            <div class="privacy-setting-item">
                <h3>Purposes of Processing</h3>
                <p>We process your personal data for the following purposes:</p>
                <ul style="margin: 15px 0; padding-left: 25px; line-height: 1.8;">
                    <li>✓ Provide and operate the travel companion platform</li>
                    <li>✓ Facilitate communication between members</li>
                    <li>✓ Keep the community safe and prevent abuse</li>
                    <li>✓ Improve product quality and user experience</li>
                    <li>✓ Send notifications related to your trips</li>
                    <li>✓ Fulfill legal and regulatory obligations</li>
                </ul>
                <p style="margin-top: 15px;">
                    <strong>Legal basis:</strong> Contract performance, legitimate interest, and consent (for non-essential cookies).
                </p>
            </div>
        </div>

        <!-- Account Deletion Section -->
        <div class="privacy-settings-section" style="border: 2px solid #f56565;">
            <h2 style="color: #c53030;">🗑️ Delete Account & Data</h2>
            <div class="privacy-setting-item">
                <h3>Right to Erasure</h3>
                <p>
                    You can request the full deletion of your account and every related record at any time.
                </p>
                <p>
                    <strong>When you request deletion:</strong>
                </p>
                <ul style="margin: 15px 0; padding-left: 25px; line-height: 1.8;">
                    <li>You will receive an email confirmation</li>
                    <li>Your account is deactivated immediately</li>
                    <li>Personal data is deleted or anonymized within 30 days</li>
                    <li>Published trips are removed from public listings</li>
                    <li>Reviews remain but are anonymized to preserve community transparency</li>
                </ul>

                <div class="gdpr-notice" style="background: #fff5f5; border-color: #f56565; margin: 20px 0;">
                    <h4 style="color: #c53030;">⚠️ Warning</h4>
                    <p>
                        Account deletion is <strong>irreversible</strong>. Once complete, we cannot restore your data.
                    </p>
                </div>

                <div class="privacy-actions">
                    <a href="<?php echo esc_url(get_privacy_policy_url()); ?>" class="btn btn-secondary" target="_blank">
                        📄 Read the Privacy Policy
                    </a>
                    <button id="cdv-request-deletion" class="btn btn-danger">
                        🗑️ Request Account Deletion
                    </button>
                </div>
            </div>
        </div>

        <!-- Contact Section -->
        <div class="privacy-settings-section">
            <h2>📧 Contact the Data Protection Officer</h2>
            <div class="privacy-setting-item">
                <p>
                    Questions about privacy or your personal data? Reach out to our Data Protection Officer:
                </p>
                <p style="margin-top: 15px;">
                    <strong>Email:</strong> <a href="mailto:privacy@compagnidiviaggi.com">privacy@compagnidiviaggi.com</a><br>
                    <strong>Response time:</strong> within 48 business hours
                </p>
            </div>
        </div>
    </div>
</main>

<style>
.privacy-settings-page {
    padding: 2rem 0 4rem;
    background: #f8f9fa;
    min-height: calc(100vh - 200px);
}

.page-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 3rem 0;
    text-align: center;
    margin-bottom: 3rem;
}

.page-header h1 {
    color: white;
    margin-bottom: 0.5rem;
}

.page-header p {
    font-size: 1.1rem;
    opacity: 0.95;
}

.privacy-settings-section {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
}

.privacy-settings-section h2 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #2d3748;
    font-size: 1.5rem;
}

.privacy-setting-item {
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 15px;
}

.privacy-setting-item:last-child {
    margin-bottom: 0;
}

.privacy-setting-item h3 {
    margin: 0 0 10px 0;
    font-size: 1.1rem;
    color: #2d3748;
}

.privacy-setting-item p {
    margin: 0 0 10px 0;
    color: #4a5568;
    font-size: 0.95rem;
    line-height: 1.6;
}

.privacy-setting-item p:last-child {
    margin-bottom: 0;
}

.privacy-setting-item ul {
    color: #4a5568;
    font-size: 0.95rem;
}

.privacy-actions {
    display: flex;
    gap: 15px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.gdpr-notice {
    background: #eef2ff;
    border-left: 4px solid #667eea;
    padding: 15px 20px;
    border-radius: 6px;
    margin: 20px 0;
}

.gdpr-notice h4 {
    margin: 0 0 10px 0;
    color: #434190;
    font-size: 1rem;
}

.gdpr-notice p {
    margin: 0;
    color: #4a5568;
    font-size: 0.9rem;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .privacy-settings-section {
        padding: 20px;
    }

    .privacy-actions {
        flex-direction: column;
    }

    .privacy-actions .btn {
        width: 100%;
    }
}
</style>

<?php
get_footer();
