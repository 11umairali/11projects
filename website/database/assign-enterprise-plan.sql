-- ════════════════════════════════════════════════════════════════════════
--  MailTrixy — assign Enterprise plan to a user
--  ────────────────────────────────────────────────────────────────────
--  How to use:
--    1. (Optional) Edit the @user_email line below to target a different user.
--    2. phpMyAdmin → select your MailTrixy database → Import → choose this
--       file → Go.
--    3. Done. The user's active workspace will have an active Enterprise
--       subscription. A successful payment row is also recorded so the
--       admin dashboard's revenue cards reflect it.
--
--  What it does:
--    • Resolves the user by email
--    • Resolves their active workspace_id (or first workspace they own)
--    • Resolves the Enterprise plan id by slug
--    • Cancels any prior active subscription on that workspace (so we don't
--      end up with two "active" subs)
--    • Inserts a new active monthly Enterprise subscription
--    • Inserts a single succeeded payment row tied to that subscription
--      (using the plan's monthly_price as the amount)
--
--  Idempotent: re-running it cancels the previous Enterprise sub and
--  creates a fresh one — no duplicate "active" rows pile up. The payment
--  row gets re-inserted each time (so revenue grows by one charge per run).
-- ════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ────────────────────────────────────────────────────────────────────
-- INPUTS — edit these if needed
-- ────────────────────────────────────────────────────────────────────
SET @user_email   := 'user@mediacity.co.in';
SET @plan_slug    := 'enterprise';
SET @billing_cycle := 'monthly';   -- or 'yearly'

-- ────────────────────────────────────────────────────────────────────
-- Resolve ids — case-insensitive on email so 'User@…' / 'user@…' both work.
-- ────────────────────────────────────────────────────────────────────
SET @user_id      := (SELECT id FROM users
                      WHERE LOWER(email) = LOWER(@user_email) COLLATE utf8mb4_unicode_ci
                      LIMIT 1);
SET @workspace_id := (SELECT active_workspace_id FROM users WHERE id = @user_id);

-- Fallback chain for workspace_id (in priority order):
--   1. user's active_workspace_id (above)
--   2. any workspace_members row for this user
--   3. the very first workspace in the table (so the seeder still
--      produces output even if the user has no membership rows yet)
SET @workspace_id := COALESCE(
    @workspace_id,
    (SELECT workspace_id FROM workspace_members WHERE user_id = @user_id ORDER BY id ASC LIMIT 1),
    (SELECT id FROM workspaces WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1)
);

-- Make sure the user is a member of (and active on) that workspace, and
-- that their active_workspace_id points at it — so the Livewire pages
-- which read auth()->user()->active_workspace_id see our seeded data.
UPDATE users SET active_workspace_id = @workspace_id
 WHERE id = @user_id AND @workspace_id IS NOT NULL;

INSERT INTO workspace_members (workspace_id, user_id, role, status, created_at, updated_at)
SELECT @workspace_id, @user_id, 'owner', 'active', NOW(), NOW()
WHERE @user_id IS NOT NULL
  AND @workspace_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM workspace_members
      WHERE workspace_id = @workspace_id AND user_id = @user_id
  );

SET @plan_id      := (SELECT id FROM plans WHERE slug = @plan_slug COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @plan_amount  := (SELECT IF(@billing_cycle = 'yearly', yearly_price, monthly_price)
                      FROM plans WHERE id = @plan_id);

-- ────────────────────────────────────────────────────────────────────
-- Sanity check — bail out (no-op) if any required id is missing.
-- The CASE WHEN guards prevent NULL inserts that would error out.
-- ────────────────────────────────────────────────────────────────────
SELECT
    @user_email   AS target_email,
    @user_id      AS resolved_user_id,
    @workspace_id AS resolved_workspace_id,
    @plan_id      AS resolved_plan_id,
    @plan_amount  AS amount_per_cycle;

-- ────────────────────────────────────────────────────────────────────
-- Cancel any existing active subscription on this workspace, so we don't
-- leave two active rows side by side.
-- ────────────────────────────────────────────────────────────────────
UPDATE subscriptions
   SET status       = 'canceled',
       canceled_at  = NOW(),
       updated_at   = NOW()
 WHERE workspace_id = @workspace_id
   AND status       = 'active'
   AND deleted_at IS NULL;

-- ────────────────────────────────────────────────────────────────────
-- Create the new active Enterprise subscription
-- ────────────────────────────────────────────────────────────────────
INSERT INTO subscriptions (
    workspace_id, plan_id,
    stripe_subscription_id, stripe_customer_id,
    status, billing_cycle,
    current_period_start, current_period_end,
    created_at, updated_at
)
SELECT
    @workspace_id, @plan_id,
    CONCAT('sub_manual_', SUBSTRING(SHA1(UUID()), 1, 16)),
    CONCAT('cus_manual_', SUBSTRING(SHA1(UUID()), 1, 16)),
    'active', @billing_cycle,
    NOW(),
    IF(@billing_cycle = 'yearly', DATE_ADD(NOW(), INTERVAL 1 YEAR), DATE_ADD(NOW(), INTERVAL 1 MONTH)),
    NOW(), NOW()
WHERE @user_id IS NOT NULL
  AND @workspace_id IS NOT NULL
  AND @plan_id IS NOT NULL;

SET @subscription_id := LAST_INSERT_ID();

-- ────────────────────────────────────────────────────────────────────
-- Record a successful payment so the admin revenue cards reflect it
-- ────────────────────────────────────────────────────────────────────
INSERT INTO payments (
    workspace_id, subscription_id,
    stripe_payment_id, stripe_invoice_id,
    amount, currency, status,
    gateway_transaction_id, gateway_slug,
    description,
    created_at, updated_at
)
SELECT
    @workspace_id, @subscription_id,
    CONCAT('pi_manual_', SUBSTRING(SHA1(UUID()), 1, 20)),
    CONCAT('in_manual_', SUBSTRING(SHA1(UUID()), 1, 20)),
    @plan_amount, 'USD', 'succeeded',
    CONCAT('txn_', SUBSTRING(SHA1(UUID()), 1, 16)),
    'manual',
    CONCAT('Enterprise plan — ', IF(@billing_cycle = 'yearly', 'Annual', 'Monthly'), ' (manual upgrade)'),
    NOW(), NOW()
WHERE @subscription_id IS NOT NULL
  AND @plan_amount IS NOT NULL;

-- ════════════════════════════════════════════════════════════════════
-- 2. TEMP-MAIL — seed 1 demo domain + 6 temp addresses for the user
-- ════════════════════════════════════════════════════════════════════
-- Temp-mail domain (only created if it doesn't exist).
-- NOTE: imap_host / imap_username / imap_password are LEFT NULL on
-- purpose. The TempMailDomain model casts these as `encrypted`, so any
-- plaintext value here would crash the admin temp-mail page with
-- "DecryptException: The payload is invalid" when Laravel tries to
-- decrypt our plain string. The demo domain doesn't need real IMAP —
-- the user-facing temp-mail page only reads addresses/messages tied to
-- the workspace, which works regardless of IMAP creds. An admin can
-- fill in real IMAP creds later via /admin/temp-mail UI (which encrypts
-- properly through the model mutators).
INSERT INTO temp_mail_domains (
    uuid, domain, display_name,
    imap_port, imap_encryption,
    status, max_addresses, default_lifetime_hours,
    created_at, updated_at
)
SELECT
    UUID(), 'inbox.demo-mailtrixy.test', 'Demo Temp Inbox',
    993, 'ssl',
    'active', 5000, 72,
    NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM temp_mail_domains
    WHERE domain = 'inbox.demo-mailtrixy.test' COLLATE utf8mb4_unicode_ci
);

-- Heal any pre-existing demo domain row that was inserted with plaintext
-- IMAP creds (from an earlier version of this file) — set them back to
-- NULL so the admin temp-mail page stops crashing on decrypt.
UPDATE temp_mail_domains
SET imap_host = NULL, imap_username = NULL, imap_password = NULL,
    updated_at = NOW()
WHERE domain = 'inbox.demo-mailtrixy.test' COLLATE utf8mb4_unicode_ci;

SET @temp_domain_id := (SELECT id FROM temp_mail_domains
                        WHERE domain = 'inbox.demo-mailtrixy.test' COLLATE utf8mb4_unicode_ci LIMIT 1);

-- 6 temp addresses for this user (mix of active + expired, varying message
-- counts). One row per INSERT — keeps each row's NOT EXISTS guard simple
-- and means a single failing row won't block the others.
INSERT INTO temp_mail_addresses
    (uuid, workspace_id, user_id, temp_mail_domain_id, local_part, full_address,
     label, is_active, messages_count, expires_at, last_received_at, created_at, updated_at)
SELECT UUID(), @workspace_id, @user_id, @temp_domain_id, 'signup-newsletter',
       'signup-newsletter@inbox.demo-mailtrixy.test', 'Newsletter signups', 1, 24,
       DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 2 HOUR),
       DATE_SUB(NOW(), INTERVAL 5 DAY), NOW()
WHERE @user_id IS NOT NULL AND @workspace_id IS NOT NULL AND @temp_domain_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM temp_mail_addresses
                  WHERE workspace_id = @workspace_id
                    AND full_address = 'signup-newsletter@inbox.demo-mailtrixy.test' COLLATE utf8mb4_unicode_ci);

INSERT INTO temp_mail_addresses
    (uuid, workspace_id, user_id, temp_mail_domain_id, local_part, full_address,
     label, is_active, messages_count, expires_at, last_received_at, created_at, updated_at)
SELECT UUID(), @workspace_id, @user_id, @temp_domain_id, 'demo-trials',
       'demo-trials@inbox.demo-mailtrixy.test', 'SaaS trial inbox', 1, 41,
       DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 6 HOUR),
       DATE_SUB(NOW(), INTERVAL 12 DAY), NOW()
WHERE @user_id IS NOT NULL AND @workspace_id IS NOT NULL AND @temp_domain_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM temp_mail_addresses
                  WHERE workspace_id = @workspace_id
                    AND full_address = 'demo-trials@inbox.demo-mailtrixy.test' COLLATE utf8mb4_unicode_ci);

INSERT INTO temp_mail_addresses
    (uuid, workspace_id, user_id, temp_mail_domain_id, local_part, full_address,
     label, is_active, messages_count, expires_at, last_received_at, created_at, updated_at)
SELECT UUID(), @workspace_id, @user_id, @temp_domain_id, 'shopping-deals',
       'shopping-deals@inbox.demo-mailtrixy.test', 'Coupons & deals', 1, 87,
       DATE_ADD(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 30 MINUTE),
       DATE_SUB(NOW(), INTERVAL 22 DAY), NOW()
WHERE @user_id IS NOT NULL AND @workspace_id IS NOT NULL AND @temp_domain_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM temp_mail_addresses
                  WHERE workspace_id = @workspace_id
                    AND full_address = 'shopping-deals@inbox.demo-mailtrixy.test' COLLATE utf8mb4_unicode_ci);

INSERT INTO temp_mail_addresses
    (uuid, workspace_id, user_id, temp_mail_domain_id, local_part, full_address,
     label, is_active, messages_count, expires_at, last_received_at, created_at, updated_at)
SELECT UUID(), @workspace_id, @user_id, @temp_domain_id, 'one-off-verify',
       'one-off-verify@inbox.demo-mailtrixy.test', 'One-off verification', 1, 2,
       DATE_ADD(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 1 HOUR),
       DATE_SUB(NOW(), INTERVAL 1 DAY), NOW()
WHERE @user_id IS NOT NULL AND @workspace_id IS NOT NULL AND @temp_domain_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM temp_mail_addresses
                  WHERE workspace_id = @workspace_id
                    AND full_address = 'one-off-verify@inbox.demo-mailtrixy.test' COLLATE utf8mb4_unicode_ci);

INSERT INTO temp_mail_addresses
    (uuid, workspace_id, user_id, temp_mail_domain_id, local_part, full_address,
     label, is_active, messages_count, expires_at, last_received_at, created_at, updated_at)
SELECT UUID(), @workspace_id, @user_id, @temp_domain_id, 'forum-spam-trap',
       'forum-spam-trap@inbox.demo-mailtrixy.test', 'Forum sign-ups (expired)', 0, 156,
       DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY),
       DATE_SUB(NOW(), INTERVAL 60 DAY), NOW()
WHERE @user_id IS NOT NULL AND @workspace_id IS NOT NULL AND @temp_domain_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM temp_mail_addresses
                  WHERE workspace_id = @workspace_id
                    AND full_address = 'forum-spam-trap@inbox.demo-mailtrixy.test' COLLATE utf8mb4_unicode_ci);

INSERT INTO temp_mail_addresses
    (uuid, workspace_id, user_id, temp_mail_domain_id, local_part, full_address,
     label, is_active, messages_count, expires_at, last_received_at, created_at, updated_at)
SELECT UUID(), @workspace_id, @user_id, @temp_domain_id, 'support-shield',
       'support-shield@inbox.demo-mailtrixy.test', 'Support replies firewall', 1, 13,
       DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 4 HOUR),
       DATE_SUB(NOW(), INTERVAL 3 DAY), NOW()
WHERE @user_id IS NOT NULL AND @workspace_id IS NOT NULL AND @temp_domain_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM temp_mail_addresses
                  WHERE workspace_id = @workspace_id
                    AND full_address = 'support-shield@inbox.demo-mailtrixy.test' COLLATE utf8mb4_unicode_ci);

-- Refresh expires_at on existing demo rows so they're always in the
-- future when the SQL is re-imported. Without this, idempotent NOT EXISTS
-- guards skip re-inserting, leaving the original expires_at — which is in
-- the past on a re-run, so TempMailPage's `expires_at > NOW()` filter hides
-- everything and the page looks empty.
UPDATE temp_mail_addresses
SET expires_at      = DATE_ADD(NOW(), INTERVAL 30 DAY),
    last_received_at = DATE_SUB(NOW(), INTERVAL 2 HOUR),
    is_active        = 1,
    updated_at       = NOW()
WHERE workspace_id = @workspace_id
  AND full_address IN (
      'signup-newsletter@inbox.demo-mailtrixy.test',
      'demo-trials@inbox.demo-mailtrixy.test',
      'shopping-deals@inbox.demo-mailtrixy.test',
      'one-off-verify@inbox.demo-mailtrixy.test',
      'support-shield@inbox.demo-mailtrixy.test'
  );

-- Keep the spam-trap address as the deliberately-expired example (so the
-- History tab has something to show).
UPDATE temp_mail_addresses
SET expires_at      = DATE_SUB(NOW(), INTERVAL 2 DAY),
    last_received_at = DATE_SUB(NOW(), INTERVAL 3 DAY),
    is_active        = 0,
    updated_at       = NOW()
WHERE workspace_id = @workspace_id
  AND full_address  = 'forum-spam-trap@inbox.demo-mailtrixy.test';

-- Diagnostic: how many temp-mail addresses are now visible to the page's
-- main query (workspace match + is_active=1 + expires_at>NOW)? Should be 5.
SELECT
    @user_id AS user_id,
    @workspace_id AS workspace_id,
    (SELECT COUNT(*) FROM temp_mail_addresses
     WHERE workspace_id = @workspace_id) AS total_for_workspace,
    (SELECT COUNT(*) FROM temp_mail_addresses
     WHERE workspace_id = @workspace_id AND is_active = 1 AND expires_at > NOW()) AS visible_to_page;

-- ════════════════════════════════════════════════════════════════════
-- 2b. TEMP-MAIL CONVERSATIONS — for each temp address create a backing
--     conversation row and 4-8 inbound messages (so clicking on a temp
--     address in the UI shows real emails — newsletter signups, OTP
--     codes, deal alerts, etc.). The temp address row stores the
--     conversation_id; TempMailPage::getMessagesProperty() loads
--     messages off that conversation_id.
-- ════════════════════════════════════════════════════════════════════

-- (a) For every temp address WITHOUT a conversation_id yet, create one.
--     channel='temp_mail' so it doesn't pollute the regular inbox view.
INSERT INTO conversations (
    uuid, workspace_id, contact_id, email_account_id, channel, status,
    priority, subject, preview, sentiment, is_read,
    messages_count, tags, last_message_at, created_at, updated_at
)
SELECT
    UUID(), ta.workspace_id, NULL, NULL, 'temp_mail', 'open',
    'normal',
    CONCAT('Temp inbox: ', ta.full_address),
    'Auto-generated demo emails delivered to this temp address.',
    'neutral', 1,
    0, JSON_ARRAY('temp_mail'),
    DATE_SUB(NOW(), INTERVAL 1 HOUR), ta.created_at, NOW()
FROM temp_mail_addresses ta
WHERE ta.user_id = @user_id
  AND ta.conversation_id IS NULL;

-- (b) Wire each temp address to its newly-created conversation.
UPDATE temp_mail_addresses ta
JOIN conversations c
    ON c.workspace_id = ta.workspace_id
   AND c.channel = 'temp_mail' COLLATE utf8mb4_unicode_ci
   AND c.subject = CONCAT('Temp inbox: ', ta.full_address) COLLATE utf8mb4_unicode_ci
SET ta.conversation_id = c.id
WHERE ta.user_id = @user_id
  AND ta.conversation_id IS NULL;

-- (c) Seed 6 demo inbound messages per temp-address conversation.
--     Skips any conversation that already has messages (idempotent).
INSERT INTO messages (
    uuid, conversation_id, workspace_id,
    direction, sender_type, sender_id,
    type, body_html, body_text, subject,
    from_email, from_name, to_emails,
    message_id_header, sentiment, detected_language,
    delivery_status, sent_at, delivered_at,
    created_at, updated_at
)
SELECT
    UUID(), ta.conversation_id, ta.workspace_id,
    'inbound', 'contact', NULL,
    'message', seed.body_html, seed.body_text, seed.subj,
    seed.from_email, seed.from_name, JSON_ARRAY(ta.full_address),
    CONCAT('<', SUBSTRING(SHA1(UUID()), 1, 24), '@inbox.demo-mailtrixy.test>'),
    'neutral', 'en',
    'delivered',
    DATE_SUB(NOW(), INTERVAL seed.h HOUR),
    DATE_SUB(NOW(), INTERVAL seed.h HOUR),
    DATE_SUB(NOW(), INTERVAL seed.h HOUR),
    DATE_SUB(NOW(), INTERVAL seed.h HOUR)
FROM temp_mail_addresses ta
CROSS JOIN (
    SELECT 1 AS h, 'Welcome to TechHub — confirm your email' AS subj,
           'noreply@techhub.test' AS from_email, 'TechHub' AS from_name,
           '<p>Hi there,</p><p>Click the button below to confirm your email and start your free trial.</p><p><a href="https://example.com/confirm/abc123" style="background:#7B61FF;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;">Confirm Email</a></p>' AS body_html,
           "Welcome to TechHub — click the link to confirm your email." AS body_text
    UNION ALL SELECT 3, 'Your verification code: 482910',
           'security@accountsd.test', 'Accounts',
           '<p>Your one-time verification code is:</p><p style="font-size:28px;font-weight:bold;letter-spacing:4px;">482910</p><p>Code expires in 10 minutes.</p>',
           'Your verification code is 482910. Expires in 10 minutes.'
    UNION ALL SELECT 6, '50% off — Black Friday week starts now',
           'deals@bloomly.test', 'Bloomly',
           '<h2 style="color:#FF7A6B">Black Friday — half off everything</h2><p>Use code <strong>BF50</strong> at checkout. Sale ends Sunday.</p><p><a href="https://example.com/sale">Shop now →</a></p>',
           'Black Friday: 50% off with code BF50. Ends Sunday.'
    UNION ALL SELECT 12, 'Order #SH-12894 has shipped',
           'shipping@orderpulse.test', 'OrderPulse Shipping',
           '<p>Hi,</p><p>Your order has shipped — tracking: <strong>1Z999AA10123456784</strong></p><p>Estimated delivery: 2-3 business days.</p>',
           'Order shipped, tracking 1Z999AA10123456784.'
    UNION ALL SELECT 24, "We've missed you! 20% off your next purchase",
           'hello@mintly.test', 'Mintly',
           '<p>It has been a while! Come back with 20% off — code <strong>WELCOME20</strong>.</p>',
           'Come back with 20% off — code WELCOME20.'
    UNION ALL SELECT 48, 'Action required: Invoice INV-2891 due in 3 days',
           'billing@cloudnimbus.test', 'CloudNimbus Billing',
           '<p>Your invoice <strong>INV-2891</strong> for $49.00 is due in 3 days.</p><p><a href="https://example.com/pay">Pay now →</a></p>',
           'Invoice INV-2891 ($49.00) is due in 3 days.'
) seed
WHERE ta.user_id = @user_id
  AND ta.conversation_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM messages m WHERE m.conversation_id = ta.conversation_id
  );

-- (d) Update each conversation's messages_count + last_message_at + previous
--     so the inbox list shows the correct preview & count.
UPDATE conversations c
SET c.messages_count = (
        SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id
    ),
    c.last_message_at = (
        SELECT MAX(m.sent_at) FROM messages m WHERE m.conversation_id = c.id
    ),
    c.preview = COALESCE(
        (SELECT m.body_text FROM messages m
         WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC LIMIT 1),
        c.preview
    )
WHERE c.channel = 'temp_mail' COLLATE utf8mb4_unicode_ci
  AND c.workspace_id = @workspace_id;

-- (e) Mirror the count onto temp_mail_addresses.messages_count
UPDATE temp_mail_addresses ta
SET ta.messages_count = (
        SELECT COUNT(*) FROM messages m WHERE m.conversation_id = ta.conversation_id
    ),
    ta.last_received_at = (
        SELECT MAX(m.sent_at) FROM messages m WHERE m.conversation_id = ta.conversation_id
    )
WHERE ta.user_id = @user_id
  AND ta.conversation_id IS NOT NULL;

-- ════════════════════════════════════════════════════════════════════
-- 3. KNOWLEDGE BASE — 10 demo entries (mix of Q&A + documents)
--    Used by the AI auto-reply RAG layer to answer customer questions.
-- ════════════════════════════════════════════════════════════════════
INSERT INTO kb_documents (
    uuid, workspace_id, type, title, content, content_hash,
    question, answer, category, is_priority,
    status, chunks_count, usage_count, created_at, updated_at
)
SELECT * FROM (
    SELECT UUID() AS uuid, @workspace_id AS workspace_id, 'qa' AS type,
           'Refund policy' AS title,
           'Our refund policy in plain English.' AS content,
           SHA1(CONCAT('Refund policy', UUID())) AS content_hash,
           'What is your refund policy?' AS question,
           'We offer a 14-day no-questions-asked refund on all paid plans. Just email billing@mailtrixy.com from the same address as your account and we will issue a full refund within 2 business days.' AS answer,
           'billing' AS category, 1 AS pri,
           'ready' AS status, 1 AS chunks, 47 AS use_count,
           DATE_SUB(NOW(), INTERVAL 30 DAY) AS ca, NOW() AS ua
    UNION ALL SELECT UUID(), @workspace_id, 'qa', 'Cancel subscription',
           'How to cancel and what happens after.', SHA1(CONCAT('Cancel subscription', UUID())),
           'How do I cancel my subscription?',
           'Go to Settings → Billing → Cancel Subscription. Your plan stays active until the end of the current billing period — you keep access to all features until then. No early-termination fees.',
           'billing', 1, 'ready', 1, 38, DATE_SUB(NOW(), INTERVAL 28 DAY), NOW()
    UNION ALL SELECT UUID(), @workspace_id, 'qa', 'Connect Gmail',
           'OAuth flow for Gmail integration.', SHA1(CONCAT('Connect Gmail', UUID())),
           'How do I connect my Gmail account?',
           'Settings → Email Accounts → Add Account → Google. Sign in, grant the requested permissions, and your inbox will sync within 30 seconds. We use OAuth 2.0 — your password is never stored.',
           'integrations', 1, 'ready', 1, 92, DATE_SUB(NOW(), INTERVAL 25 DAY), NOW()
    UNION ALL SELECT UUID(), @workspace_id, 'qa', 'Bring your own AI key',
           'BYOK setup for OpenAI / Anthropic.', SHA1(CONCAT('Bring your own AI key', UUID())),
           'Can I use my own OpenAI API key?',
           'Yes — on Pro and Enterprise plans you can plug in your own OpenAI, Anthropic, Gemini, or Mistral key under Settings → AI. You will not be charged for AI replies — only your own provider bill applies.',
           'ai', 1, 'ready', 1, 21, DATE_SUB(NOW(), INTERVAL 18 DAY), NOW()
    UNION ALL SELECT UUID(), @workspace_id, 'qa', 'API rate limits',
           'Default API rate limits and how to lift them.', SHA1(CONCAT('API rate limits', UUID())),
           'What are the API rate limits?',
           'Free: 60 requests/minute. Starter: 300/min. Pro: 1,200/min. Enterprise: 5,000/min (custom limits available). All limits are per Sanctum token, sliding-window 60s.',
           'api', 0, 'ready', 1, 14, DATE_SUB(NOW(), INTERVAL 14 DAY), NOW()
    UNION ALL SELECT UUID(), @workspace_id, 'qa', 'White-label setup',
           'Custom domain, logo, branding setup.', SHA1(CONCAT('White-label setup', UUID())),
           'How do I white-label MailTrixy with my brand?',
           'Admin → Settings → Branding lets you upload your own logo (light + dark), favicon, primary color, and PWA icons. Custom CNAME domains are available on Enterprise — submit a ticket and we will set up the SSL cert within 24h.',
           'branding', 0, 'ready', 1, 8, DATE_SUB(NOW(), INTERVAL 10 DAY), NOW()
    UNION ALL SELECT UUID(), @workspace_id, 'qa', 'Import contacts CSV',
           'CSV format and field mapping for contact import.', SHA1(CONCAT('Import contacts CSV', UUID())),
           'How do I import contacts from CSV?',
           'Contacts → Import → drop your CSV. We auto-detect columns named email, first_name, last_name, phone, company, job_title. You can map any other column to a custom field. Up to 50,000 rows per file; larger files split automatically.',
           'contacts', 0, 'ready', 1, 33, DATE_SUB(NOW(), INTERVAL 9 DAY), NOW()
    UNION ALL SELECT UUID(), @workspace_id, 'qa', 'Webhook setup',
           'Outbound webhook configuration.', SHA1(CONCAT('Webhook setup', UUID())),
           'Do you support outgoing webhooks?',
           'Yes — Workflows → Action: Send Webhook. We support POST/PUT/PATCH, custom headers, secret-based HMAC signing, automatic retries (5 attempts with exponential backoff), and a delivery log under Settings → Webhooks.',
           'integrations', 0, 'ready', 1, 6, DATE_SUB(NOW(), INTERVAL 6 DAY), NOW()
    UNION ALL SELECT UUID(), @workspace_id, 'qa', 'GDPR data export',
           'Self-serve data export under GDPR.', SHA1(CONCAT('GDPR data export', UUID())),
           'Can I export all my data for GDPR?',
           'Yes — Settings → Data & Privacy → Export. We package contacts, conversations, messages, attachments, deals, and audit logs into a single ZIP and email a download link within 1 hour. No PII leaves the EU region for EU workspaces.',
           'compliance', 1, 'ready', 1, 11, DATE_SUB(NOW(), INTERVAL 4 DAY), NOW()
    UNION ALL SELECT UUID(), @workspace_id, 'document', 'Quick-start guide',
           'Welcome to MailTrixy! This 10-minute guide gets you from zero to a working AI auto-reply on every channel. Step 1: connect an email account. Step 2: upload your knowledge base (you are reading it now). Step 3: pick a personality preset. Step 4: turn on auto-reply. Step 5: send a test conversation. That is it.',
           SHA1(CONCAT('Quick-start guide', UUID())),
           NULL, NULL, 'getting-started', 1, 'ready', 1, 64, DATE_SUB(NOW(), INTERVAL 35 DAY), NOW()
) seed
WHERE @workspace_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM kb_documents kd
      WHERE kd.workspace_id = seed.workspace_id
        AND kd.title = seed.title COLLATE utf8mb4_unicode_ci
  );

-- ════════════════════════════════════════════════════════════════════
-- Final report
-- ════════════════════════════════════════════════════════════════════
SELECT
    u.email,
    w.name        AS workspace,
    p.name        AS plan,
    s.status      AS subscription_status,
    s.billing_cycle,
    s.current_period_end,
    pay.amount    AS last_payment_amount,
    pay.status    AS last_payment_status
FROM users u
JOIN workspaces w        ON w.id = u.active_workspace_id
JOIN subscriptions s     ON s.workspace_id = w.id AND s.status = 'active' AND s.deleted_at IS NULL
JOIN plans p             ON p.id = s.plan_id
LEFT JOIN payments pay   ON pay.subscription_id = s.id
WHERE u.email = @user_email COLLATE utf8mb4_unicode_ci
ORDER BY pay.created_at DESC
LIMIT 1;

SELECT
    (SELECT COUNT(*) FROM temp_mail_addresses WHERE user_id = @user_id) AS temp_mail_addresses,
    (SELECT COUNT(*) FROM messages m
       JOIN conversations c ON c.id = m.conversation_id
       WHERE c.channel = 'temp_mail' COLLATE utf8mb4_unicode_ci
         AND c.workspace_id = @workspace_id) AS temp_mail_messages,
    (SELECT COUNT(*) FROM kb_documents WHERE workspace_id = @workspace_id) AS kb_documents;
