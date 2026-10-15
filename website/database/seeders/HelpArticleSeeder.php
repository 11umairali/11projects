<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            // ── Getting Started ──────────────────────────────────────
            [
                'slug' => 'setting-up-your-workspace',
                'title' => 'Setting Up Your Workspace',
                'category' => 'getting-started',
                'sort_order' => 1,
                'excerpt' => 'Learn how to configure your MailTrixy workspace for your team and start collaborating.',
                'content' => "Welcome to MailTrixy! Your workspace is the central hub where your team collaborates on email outreach, manages contacts, and tracks deals. Setting it up correctly from the start will save you time and help your team work more efficiently.\n\nTo get started, head to Settings > Workspace from the sidebar. Here you can set your workspace name, upload your company logo, and configure your default timezone. These settings apply to all team members and affect how dates, times, and branding appear across the platform.\n\nNext, invite your team members by going to Settings > Team. You can send email invitations and assign roles like Admin, Manager, or Member. Each role has different permission levels, so choose carefully based on what each person needs to access.\n\nFinally, connect your first email account under Settings > Email. MailTrixy supports Gmail, Outlook, and custom IMAP/SMTP servers. Once connected, you can start sending and receiving emails directly from the platform.\n\nTip: Complete the onboarding wizard that appears when you first sign in. It walks you through each of these steps and helps you get up and running in under 5 minutes.",
                'related_feature' => 'onboarding',
            ],
            [
                'slug' => 'connecting-your-first-email-account',
                'title' => 'Connecting Your First Email Account',
                'category' => 'getting-started',
                'sort_order' => 2,
                'excerpt' => 'Step-by-step guide to connecting Gmail, Outlook, or custom email accounts to MailTrixy.',
                'content' => "Connecting your email account allows you to send and receive messages directly within MailTrixy, track opens and clicks, and keep your communication history organized alongside your contacts and deals.\n\nMailTrixy supports three types of email connections: Gmail (via OAuth), Microsoft Outlook (via OAuth), and custom IMAP/SMTP servers. For Gmail and Outlook, the process is straightforward. Navigate to Settings > Email and click \"Add Email Account.\" Select your provider and authorize MailTrixy to access your account. No passwords are stored on our servers.\n\nFor custom IMAP/SMTP connections, you will need your incoming mail server address and port (usually 993 for IMAP with SSL), your outgoing mail server address and port (usually 587 for SMTP with TLS), and your email username and password. Enter these details in the connection form and click \"Test Connection\" to verify everything works before saving.\n\nOnce connected, MailTrixy will begin syncing your recent emails. This initial sync may take a few minutes depending on the size of your mailbox. Going forward, new emails are synced in real time.\n\nImportant: Make sure your email provider allows third-party app access. For Gmail, you may need to enable \"Less secure app access\" or generate an App Password if you have 2FA enabled and are using IMAP directly.",
                'related_feature' => 'email',
            ],
            [
                'slug' => 'understanding-the-dashboard',
                'title' => 'Understanding the Dashboard',
                'category' => 'getting-started',
                'sort_order' => 3,
                'excerpt' => 'A tour of the MailTrixy dashboard and what each metric means for your business.',
                'content' => "The dashboard is the first thing you see when you log into MailTrixy. It gives you a real-time overview of your email performance, contact activity, deal progress, and campaign results all in one place.\n\nAt the top, you will find key performance indicators (KPIs) like total emails sent, open rate, reply rate, and active deals value. These numbers update throughout the day and reflect your activity over the selected time period, which you can adjust using the date filter in the top right corner.\n\nBelow the KPIs, you will see activity charts showing your email volume and engagement trends over time. These help you spot patterns, such as which days of the week get the best response rates, or whether your outreach volume is trending up or down.\n\nThe Recent Activity feed shows the latest actions taken by you and your team, such as new contacts added, deals moved to a new stage, or campaigns launched. This keeps everyone aligned without needing to check each section individually.\n\nFinally, the Quick Actions section at the bottom provides shortcuts to common tasks like composing a new email, creating a campaign, or importing contacts. Use these to jump straight into your workflow without navigating through the sidebar.",
                'related_feature' => 'dashboard',
            ],

            // ── Email ────────────────────────────────────────────────
            [
                'slug' => 'sending-your-first-email',
                'title' => 'Sending Your First Email',
                'category' => 'email',
                'sort_order' => 10,
                'excerpt' => 'How to compose and send an email from MailTrixy with tracking enabled.',
                'content' => "Sending an email from MailTrixy is simple and gives you powerful tracking capabilities that regular email clients do not offer. Every email you send can be tracked for opens, clicks, and replies, giving you visibility into engagement.\n\nTo compose a new email, click the \"Compose\" button in the Inbox section, or use the keyboard shortcut C. The compose window will open with fields for the recipient, subject line, and message body. You can type an email address directly or start typing a contact name to search your contact list.\n\nThe email editor supports rich text formatting including bold, italic, bullet lists, links, and attachments. You can also insert email templates if you have created any, which is great for sending consistent follow-ups or introductions.\n\nBefore sending, you will notice tracking options at the bottom of the compose window. Open tracking adds a tiny invisible pixel to your email that lets you know when the recipient opens it. Click tracking wraps your links so you can see when they are clicked. Both are enabled by default but can be toggled off for individual emails.\n\nOnce you hit Send, the email goes out through your connected email account. It will appear in both MailTrixy and your regular email client, so your sent folder stays in sync. You can monitor the email status in the Inbox view, where a small icon will show whether it has been opened or clicked.",
                'related_feature' => 'inbox',
            ],
            [
                'slug' => 'managing-email-accounts',
                'title' => 'Managing Email Accounts',
                'category' => 'email',
                'sort_order' => 11,
                'excerpt' => 'Add, remove, and configure multiple email accounts in your workspace.',
                'content' => "MailTrixy allows you to connect multiple email accounts to a single workspace. This is useful for teams where different members use different email addresses, or when you want to separate outreach for different brands or purposes.\n\nTo manage your email accounts, go to Settings > Email. Here you will see a list of all connected accounts with their sync status, last sync time, and daily sending limits. You can add new accounts, remove existing ones, or edit connection settings.\n\nEach email account has its own settings for signature, daily sending limit, and warm-up schedule. The daily sending limit helps protect your email reputation by preventing you from sending too many emails in a short period. We recommend starting with 50 emails per day for new accounts and gradually increasing as your sender reputation builds.\n\nEmail signatures can be customized per account. Go to the account settings and scroll to the Signature section to set up HTML signatures that will be automatically appended to your outgoing emails.\n\nIf an account shows a sync error, it usually means the connection has expired or your credentials have changed. Click \"Reconnect\" to re-authorize the account. For OAuth connections like Gmail and Outlook, this is just a one-click process.",
                'related_feature' => 'email',
            ],
            [
                'slug' => 'email-tracking-explained',
                'title' => 'Email Tracking Explained',
                'category' => 'email',
                'sort_order' => 12,
                'excerpt' => 'Understand how open tracking, click tracking, and reply detection work.',
                'content' => "Email tracking in MailTrixy gives you visibility into how recipients interact with your emails. There are three types of tracking available: open tracking, click tracking, and reply detection.\n\nOpen tracking works by embedding a tiny, invisible 1x1 pixel image in your email. When the recipient opens the email and their email client loads images, the pixel is downloaded from our servers, recording the open event. Keep in mind that some email clients block images by default, so open tracking is not 100% accurate, but it gives you a reliable directional signal.\n\nClick tracking works by routing your email links through our tracking servers. When a recipient clicks a tracked link, the click is recorded and the recipient is immediately redirected to the original destination. This happens in milliseconds, so the experience is seamless for the recipient.\n\nReply detection is automatic. When someone replies to an email you sent through MailTrixy, the reply is detected and logged against the contact record. This helps you track response rates across campaigns and individual outreach.\n\nYou can view tracking data at the individual email level in the conversation view, or in aggregate through the Analytics section. Tracking data is retained for the lifetime of your account and can be exported at any time.\n\nPrivacy note: All tracking complies with email best practices. Recipients can opt out of tracking by unsubscribing, and you can disable tracking on any individual email before sending.",
                'related_feature' => 'inbox',
            ],

            // ── Contacts ─────────────────────────────────────────────
            [
                'slug' => 'importing-contacts-from-a-file',
                'title' => 'Importing Contacts from a File',
                'category' => 'contacts',
                'sort_order' => 20,
                'excerpt' => 'Import your existing contacts into MailTrixy using CSV or Excel files.',
                'content' => "MailTrixy makes it easy to bring your existing contacts into the platform. You can import contacts from CSV or Excel files, and the import wizard will guide you through mapping your file columns to MailTrixy contact fields.\n\nTo start an import, go to Contacts and click the \"Import\" button. Select your file (CSV or XLSX format) and upload it. On the next screen, you will see a preview of your data and a mapping interface where you can match each column in your file to the corresponding MailTrixy field such as First Name, Last Name, Email, Company, Phone, and any custom fields you have created.\n\nMailTrixy will automatically detect and suggest mappings for common column names. For example, a column named \"Email Address\" will automatically map to the Email field. Review the suggested mappings and adjust any that are incorrect.\n\nDuring import, MailTrixy checks for duplicate contacts based on email address. If a contact with the same email already exists, you can choose to skip duplicates, update existing records with new data, or create duplicates. We recommend choosing \"Update existing\" to keep your data fresh without creating duplicate entries.\n\nAfter the import completes, you will see a summary showing how many contacts were created, updated, and skipped. Any rows with errors (such as invalid email formats) will be listed so you can fix and re-import them. There is no limit on the number of contacts you can import at once, but very large files (over 100,000 rows) may take a few minutes to process.",
                'related_feature' => 'contacts',
            ],
            [
                'slug' => 'using-tags-and-segments',
                'title' => 'Using Tags and Segments',
                'category' => 'contacts',
                'sort_order' => 21,
                'excerpt' => 'Organize your contacts with tags and create dynamic segments for targeted outreach.',
                'content' => "Tags and segments are two powerful ways to organize your contacts in MailTrixy. While they serve similar purposes, they work differently and are best used for different scenarios.\n\nTags are simple labels you attach to contacts manually or via automation. For example, you might tag contacts as \"VIP\", \"Conference Lead\", or \"Churned Customer\". To add a tag, open a contact record and click the tag icon, then type a new tag name or select from existing tags. You can also bulk-tag contacts by selecting multiple contacts from the list and choosing \"Add Tag\" from the bulk actions menu.\n\nSegments are dynamic groups defined by rules and conditions. Unlike tags, segments update automatically as contact data changes. For example, you can create a segment for \"Contacts who opened an email in the last 30 days\" or \"Contacts in the Technology industry with more than 50 employees.\" To create a segment, go to Contacts and click \"New Segment.\" Build your rules using the condition builder, which supports filters on any contact field, activity data, and tag membership.\n\nBoth tags and segments can be used as audience targets for campaigns. When creating a campaign, you can select one or more tags or segments as your recipient list. Segments are particularly powerful here because they ensure your campaign always targets the most up-to-date group of contacts matching your criteria.\n\nBest practice: Use tags for permanent or semi-permanent categorizations (like lead source or customer tier), and use segments for dynamic, behavior-based groupings (like engagement level or purchase history).",
                'related_feature' => 'contacts',
            ],
            [
                'slug' => 'managing-contact-lists',
                'title' => 'Managing Contact Lists',
                'category' => 'contacts',
                'sort_order' => 22,
                'excerpt' => 'Keep your contact database clean and organized with list management tools.',
                'content' => "A clean, well-organized contact database is the foundation of effective email outreach. MailTrixy provides several tools to help you maintain data quality and keep your contact lists in order.\n\nThe Contacts page shows all your contacts in a sortable, filterable table. You can filter by any field, search by name or email, and sort by date added, last activity, or any custom field. Use the column selector to customize which fields are visible in the table.\n\nDuplicate detection and merging is available under Contacts > Merge Duplicates. MailTrixy scans your database for contacts that share the same email address, phone number, or name, and presents them as potential duplicates. You can review each pair and choose which record to keep, merging the data from both into a single clean record.\n\nBulk operations let you perform actions on many contacts at once. Select contacts using the checkboxes and choose from bulk actions like Add Tag, Remove Tag, Delete, or Export. This is useful for cleaning up after an import or reorganizing your database.\n\nData enrichment automatically fills in missing contact information when possible. When you add a new contact with just an email address, MailTrixy can look up additional details like company name, job title, and social profiles. This feature can be enabled in Settings > Contacts.\n\nRegular maintenance tip: Review your contacts quarterly. Archive or remove contacts who have bounced, unsubscribed, or shown no engagement in the past 6 months. This keeps your sending reputation healthy and your metrics accurate.",
                'related_feature' => 'contacts',
            ],

            // ── Campaigns ────────────────────────────────────────────
            [
                'slug' => 'creating-your-first-campaign',
                'title' => 'Creating Your First Campaign',
                'category' => 'campaigns',
                'sort_order' => 30,
                'excerpt' => 'Build and launch your first email campaign to reach your audience at scale.',
                'content' => "Campaigns in MailTrixy let you send personalized emails to many contacts at once while tracking performance across the entire send. Whether you are announcing a product launch, sharing a newsletter, or running a cold outreach sequence, campaigns make it efficient and measurable.\n\nTo create a campaign, go to Campaigns and click \"New Campaign.\" You will walk through four steps: naming your campaign, selecting your audience, composing your email, and reviewing before launch.\n\nIn the Audience step, choose who receives your campaign. You can select a tag, segment, or manually pick contacts. MailTrixy will show you the estimated reach and automatically exclude any contacts who have unsubscribed or bounced in the past.\n\nThe Compose step is where you write your email. Use the drag-and-drop editor to build visually appealing emails, or switch to the plain text editor for simpler messages. Personalization variables like the contact first name and company name can be inserted using double curly braces. Preview your email to see how it looks with real contact data.\n\nBefore launching, the Review step shows you a summary of everything: recipient count, email preview, tracking settings, and sending schedule. You can choose to send immediately or schedule for a future date and time. For large campaigns, MailTrixy automatically throttles sending to protect your email reputation.\n\nAfter your campaign is sent, the Campaign Report page shows real-time metrics including delivery rate, open rate, click rate, reply rate, and unsubscribe rate. Use these insights to refine your messaging and targeting for future campaigns.",
                'related_feature' => 'campaigns',
            ],
            [
                'slug' => 'understanding-ab-testing',
                'title' => 'Understanding A/B Testing',
                'category' => 'campaigns',
                'sort_order' => 31,
                'excerpt' => 'Test different subject lines and content to find what resonates with your audience.',
                'content' => "A/B testing (also called split testing) lets you compare two versions of your email to see which performs better. This is one of the most effective ways to improve your open rates and engagement over time.\n\nWhen creating a campaign, you can enable A/B testing in the Compose step. MailTrixy supports testing different subject lines, email content, or sender names. The most common test is subject line testing, which helps you discover what language and tone drives the most opens.\n\nHere is how it works: You create two (or more) variants of your email, each with a different element you want to test. MailTrixy sends each variant to a small, equal portion of your audience (the test group, typically 10-20% each). After a waiting period you choose (usually 2-4 hours), MailTrixy measures the results and automatically sends the winning variant to the remaining audience.\n\nThe winning variant is determined by the metric you select: open rate for subject line tests, click rate for content tests, or reply rate for more advanced optimization. You can also choose to select the winner manually if you prefer to review the results yourself.\n\nBest practices for A/B testing: Test only one variable at a time so you know exactly what caused the difference. Make sure your test group is large enough to produce statistically meaningful results, ideally at least 200 recipients per variant. Run tests consistently over time to build up insights about what works for your specific audience.\n\nAfter the campaign completes, the report will show detailed results for each variant so you can apply your learnings to future campaigns.",
                'related_feature' => 'campaigns',
            ],
            [
                'slug' => 'setting-up-drip-sequences',
                'title' => 'Setting Up Drip Sequences',
                'category' => 'campaigns',
                'sort_order' => 32,
                'excerpt' => 'Create automated multi-step email sequences that send over days or weeks.',
                'content' => "Drip sequences are automated email series that send follow-up messages at scheduled intervals. They are perfect for onboarding new subscribers, nurturing leads, or following up after a meeting without manually remembering to send each email.\n\nTo create a drip sequence, go to Campaigns and click \"New Campaign,\" then select the \"Drip Sequence\" type. You will build a sequence of emails, each with its own content, delay, and conditions.\n\nStart by creating your first email in the sequence. This is the email that goes out immediately (or at your chosen start time) when a contact enters the sequence. Then add follow-up steps with delays between them. For example, you might send Email 1 on Day 0, Email 2 on Day 3, and Email 3 on Day 7.\n\nEach step in the sequence can have conditions that determine whether the email sends. Common conditions include: send only if the previous email was not replied to, send only if the contact has not unsubscribed, or send only if the contact has opened a previous email. These conditions help you avoid annoying contacts who have already engaged.\n\nContacts can enter the drip sequence in several ways: manually by adding them from the contact list, automatically via a workflow trigger (for example, when a new contact is created), or through a campaign audience selection.\n\nMonitor your drip sequence performance in the Campaign Report. You will see metrics for each step individually, plus an overall funnel view showing how contacts move through the sequence. Pause or modify the sequence at any time without affecting contacts who have already received earlier steps.",
                'related_feature' => 'campaigns',
            ],

            // ── Workflows ────────────────────────────────────────────
            [
                'slug' => 'what-are-workflows',
                'title' => 'What Are Workflows?',
                'category' => 'workflows',
                'sort_order' => 40,
                'excerpt' => 'Learn how workflows automate repetitive tasks and save your team hours every week.',
                'content' => "Workflows are automated sequences of actions that run in response to triggers. Think of them as \"if this happens, then do that\" rules that work around the clock so you do not have to perform repetitive tasks manually.\n\nFor example, you can create a workflow that automatically tags a contact as \"Hot Lead\" when they open three or more of your emails, then assigns them to a sales rep, and sends a Slack notification to your team. All of this happens without any manual intervention.\n\nWorkflows consist of three main building blocks: Triggers are events that start the workflow, such as \"Contact created,\" \"Email opened,\" \"Deal stage changed,\" or \"Form submitted.\" Conditions are rules that decide whether the workflow should continue, like \"If contact industry equals Technology\" or \"If deal value is greater than $10,000.\" Actions are the tasks the workflow performs, such as \"Send email,\" \"Add tag,\" \"Create deal,\" \"Wait 2 days,\" or \"Send webhook.\"\n\nYou build workflows visually using the drag-and-drop Workflow Builder. Connect triggers, conditions, and actions by dragging lines between them. The visual interface makes it easy to understand the logic at a glance and share workflows with team members.\n\nWorkflows run automatically once activated. You can monitor their execution in the Workflow Logs, where you will see each instance of the workflow running, which contacts it processed, and whether each step succeeded or failed. If a step fails, the workflow pauses and notifies you so you can investigate and fix the issue.",
                'related_feature' => 'workflows',
            ],
            [
                'slug' => 'creating-your-first-automation',
                'title' => 'Creating Your First Automation',
                'category' => 'workflows',
                'sort_order' => 41,
                'excerpt' => 'Step-by-step guide to building a simple workflow automation.',
                'content' => "Building your first workflow is straightforward with MailTrixy's visual builder. Let us walk through creating a common automation: automatically following up with new contacts who do not reply to your initial email.\n\nGo to Workflows and click \"Create Workflow.\" Give it a name like \"New Contact Follow-up\" and click \"Open Builder.\" You will see a blank canvas with a Start node.\n\nFirst, add a trigger by clicking the plus icon on the Start node. Select \"Email Sent\" as the trigger type. This means the workflow will start whenever you send an email to a contact. You can add filter conditions on the trigger, such as only triggering for emails with a specific tag or from a specific email account.\n\nNext, add a \"Wait\" action and set the delay to 3 days. This gives the recipient time to see and respond to your email before the follow-up kicks in.\n\nAfter the wait, add a \"Condition\" node to check whether the contact has replied. Select \"Has replied\" as the condition. If yes, the workflow ends (the contact engaged and does not need a follow-up). If no, the workflow continues to the next step.\n\nOn the \"No\" branch, add a \"Send Email\" action. Compose your follow-up email here. You might say something like \"Just checking in on my previous email\" with a brief reminder of your original message.\n\nSave and activate the workflow. From now on, every email you send will automatically get a follow-up after 3 days if the contact has not replied. You can view the results in Workflow Logs to see how many contacts received follow-ups and whether engagement improved.\n\nTip: Start simple and iterate. Once you are comfortable with basic workflows, explore more advanced features like multi-branch conditions, webhook integrations, and nested workflows.",
                'related_feature' => 'workflows',
            ],

            // ── Deals ────────────────────────────────────────────────
            [
                'slug' => 'setting-up-your-sales-pipeline',
                'title' => 'Setting Up Your Sales Pipeline',
                'category' => 'deals',
                'sort_order' => 50,
                'excerpt' => 'Configure deal stages and pipelines to match your sales process.',
                'content' => "The Deals section in MailTrixy provides a visual pipeline (Kanban board) for tracking your sales opportunities from first contact to closed deal. Before you start using it, you will want to configure the stages to match your specific sales process.\n\nTo set up your pipeline, go to Deals and click the gear icon. Here you can create, rename, reorder, and delete deal stages. The default stages are Lead, Qualified, Proposal, Negotiation, and Closed Won / Closed Lost, but you should customize these to match how your team actually sells.\n\nKeep your pipeline stages simple and actionable. Each stage should represent a clear milestone in your sales process. Avoid having too many stages (5-7 is ideal) as it makes the board harder to manage. Each stage should answer the question \"What needs to happen before this deal moves to the next stage?\"\n\nWhen creating a new deal, you will fill in the contact or company name, deal value, expected close date, and assign it to a team member. The deal will appear as a card on your pipeline board. Drag and drop cards between stages as deals progress.\n\nEach deal card shows key information at a glance: the deal name, value, contact name, and how long it has been in the current stage. Click a deal to open its detail view, where you can see the full history of activities, emails, notes, and stage changes.\n\nFor teams with multiple sales processes (for example, new business vs. renewals), you can create separate pipelines. Each pipeline has its own stages and can be filtered independently. Use the pipeline selector at the top of the Deals page to switch between them.",
                'related_feature' => 'deals',
            ],
            [
                'slug' => 'managing-deals',
                'title' => 'Managing Deals',
                'category' => 'deals',
                'sort_order' => 51,
                'excerpt' => 'Track deal progress, add notes, and close deals effectively.',
                'content' => "Once your pipeline is set up, managing deals day-to-day is about keeping your pipeline accurate and up-to-date so you can forecast revenue and prioritize your time effectively.\n\nThe deal board gives you a visual overview of your entire pipeline. Deals are displayed as cards in their current stage, and you can drag them between stages as they progress. The total value of deals in each stage is shown at the top of each column, giving you an instant snapshot of your pipeline health.\n\nTo update a deal, click on it to open the detail view. Here you can change the deal value, update the expected close date, add notes about your latest conversation, and log activities like calls, meetings, or emails. All of these updates are timestamped and visible to your team.\n\nDeals are linked to contacts, so all email conversations and activity with the associated contact are visible in the deal view. This means you never lose context when following up. If a deal involves multiple contacts (for example, a champion and a decision maker), you can associate multiple contacts with a single deal.\n\nUse filters and sorting to focus your attention. Filter deals by stage, owner, value range, or expected close date. Sort by value to focus on your biggest opportunities, or by age to identify deals that are stuck and need attention.\n\nWhen a deal is won or lost, move it to the appropriate final stage. MailTrixy will prompt you to record a reason for the outcome, which builds up valuable data over time about why you win and lose deals. This information appears in the Analytics section to help you improve your sales process.",
                'related_feature' => 'deals',
            ],

            // ── AI ───────────────────────────────────────────────────
            [
                'slug' => 'configuring-ai-responses',
                'title' => 'Configuring AI Responses',
                'category' => 'ai',
                'sort_order' => 60,
                'excerpt' => 'Set up and customize the AI assistant to draft emails and suggest replies.',
                'content' => "MailTrixy's AI assistant helps you write better emails faster by drafting replies, suggesting follow-ups, and improving your existing copy. To get the most out of it, you should configure it to understand your communication style and business context.\n\nGo to Settings > AI to access the AI configuration panel. The first thing to set up is your AI provider. MailTrixy supports multiple AI providers including OpenAI, Anthropic, and Google. Enter your API key for your preferred provider and select the model you want to use. More powerful models produce better results but consume more tokens.\n\nThe Tone and Style settings let you tell the AI how you communicate. Options include Professional, Friendly, Casual, and Formal. You can also provide custom instructions like \"Always mention our free trial\" or \"Keep emails under 150 words.\" These instructions apply to all AI-generated content across your workspace.\n\nThe AI assistant appears in several places throughout MailTrixy: in the email composer (click the AI icon to generate a draft or improve your writing), in the inbox (suggested replies appear below incoming emails), and in the campaign editor (use AI to generate subject lines, body copy, or A/B test variants).\n\nYou can control how much the AI assists by toggling individual features on or off. Some teams prefer to use AI only for reply suggestions, while others use it for everything from drafting outreach to generating campaign content.\n\nUsage tracking shows your monthly token consumption and helps you stay within budget. Set a monthly token limit to prevent unexpected charges from your AI provider.",
                'related_feature' => 'ai',
            ],
            [
                'slug' => 'training-ai-with-knowledge-base',
                'title' => 'Training Your AI with Knowledge Base',
                'category' => 'ai',
                'sort_order' => 61,
                'excerpt' => 'Upload documents to the Knowledge Base so the AI gives accurate, on-brand answers.',
                'content' => "The Knowledge Base in MailTrixy serves as the AI's training data. By uploading your company documents, product guides, FAQs, and other reference materials, you give the AI the context it needs to generate accurate, brand-consistent responses.\n\nTo add documents, go to Knowledge Base from the sidebar. Click \"Upload Document\" and select a file. Supported formats include PDF, Word documents, text files, and Markdown. You can also paste content directly using the \"Add from Text\" option.\n\nOnce uploaded, MailTrixy processes the document by breaking it into smaller chunks and creating searchable embeddings. This process takes a few seconds to a couple of minutes depending on the document length. When complete, the AI can reference this information when generating replies and drafts.\n\nOrganize your documents with categories and tags to keep things manageable. Common categories include Product Information, Pricing, FAQs, Company Policies, and Competitor Comparisons. Well-organized knowledge base content leads to more relevant and accurate AI responses.\n\nThe AI uses the knowledge base contextually. When someone asks about pricing in an email, the AI will search your knowledge base for pricing-related documents and use that information to draft a response. It will not make up information; if the answer is not in the knowledge base, the AI will indicate that it does not have enough information.\n\nKeep your knowledge base up to date. Whenever your products, pricing, or policies change, update the relevant documents. Outdated information leads to outdated AI responses, which can confuse customers and damage trust.",
                'related_feature' => 'knowledge-base',
            ],
            [
                'slug' => 'understanding-ai-settings',
                'title' => 'Understanding AI Settings',
                'category' => 'ai',
                'sort_order' => 62,
                'excerpt' => 'A plain-language explanation of each AI setting and what it controls.',
                'content' => "MailTrixy's AI configuration has several settings that control how the assistant behaves. Here is what each one does in plain language so you can tune it for your needs.\n\nCreativity Level (Temperature) controls how creative versus predictable the AI responses are. A lower value (around 0.3) produces consistent, safe responses that closely follow your knowledge base. A higher value (around 0.8 or above) produces more varied and creative responses. For business emails, we recommend a value between 0.5 and 0.7.\n\nMaximum Response Length sets how long the AI replies can be. Short responses are a few sentences, medium is one or two paragraphs, and long allows detailed multi-paragraph answers. Match this to your typical email style. If your team writes concise emails, keep this on Short or Medium.\n\nConfidence Threshold determines how sure the AI needs to be before taking action in automatic mode. At 80%, the AI only sends replies when it is very confident the response is correct. At 50%, it sends more frequently but may occasionally miss the mark. Start with a high threshold and lower it as you build trust in the AI.\n\nSend Mode controls the level of automation. \"Suggestions Only\" shows AI-drafted responses that you copy and paste. \"Review Before Sending\" queues drafts for your approval. \"Fully Automatic\" lets the AI send responses on its own when confidence is above your threshold.\n\nBusiness Hours restricts AI activity to your configured working hours. Outside of business hours, the AI can optionally send a custom out-of-office message instead of a full reply. Configure your business hours in Settings > Workspace.",
                'related_feature' => 'ai',
            ],

            // ── Billing ──────────────────────────────────────────────
            [
                'slug' => 'managing-your-subscription',
                'title' => 'Managing Your Subscription',
                'category' => 'billing',
                'sort_order' => 70,
                'excerpt' => 'Upgrade, downgrade, or cancel your plan and manage payment methods.',
                'content' => "MailTrixy offers several subscription plans to fit teams of different sizes and needs. You can manage your subscription at any time from Settings > Billing.\n\nThe Billing page shows your current plan, billing cycle (monthly or annual), next payment date, and payment method on file. You can see a breakdown of what is included in your plan, such as the number of email accounts, contacts, monthly emails, and AI tokens.\n\nTo upgrade your plan, click \"Change Plan\" and select a higher tier. Upgrades take effect immediately, and you will be charged a prorated amount for the remainder of your current billing period. All your data and settings are preserved when you upgrade.\n\nDowngrading works similarly but takes effect at the end of your current billing period. This means you continue to enjoy your current plan features until the period ends, then switch to the lower plan. If your current usage exceeds the limits of the lower plan (for example, more connected email accounts than allowed), you will need to adjust before the downgrade takes effect.\n\nTo update your payment method, click \"Update Payment Method\" and enter your new card details. MailTrixy supports all major credit and debit cards, as well as several regional payment methods depending on your location.\n\nInvoices are available for download in the Billing History section. Each invoice includes all the details you need for expense reporting or tax purposes. If you need a custom invoice with specific billing information (like a VAT number or purchase order), you can configure these details in the Billing Settings.",
                'related_feature' => 'billing',
            ],
            [
                'slug' => 'understanding-plan-limits',
                'title' => 'Understanding Plan Limits',
                'category' => 'billing',
                'sort_order' => 71,
                'excerpt' => 'Know what is included in your plan and what happens when you reach a limit.',
                'content' => "Each MailTrixy plan comes with specific limits on features like connected email accounts, total contacts, monthly email sends, AI tokens, and team members. Understanding these limits helps you choose the right plan and avoid unexpected interruptions.\n\nYour current usage vs. plan limits is displayed on the Dashboard and in Settings > Billing. A usage bar shows how close you are to each limit. When you reach 80% of any limit, MailTrixy will display a notification suggesting you review your plan.\n\nWhen you hit a limit, MailTrixy does not immediately cut off access. Instead, the behavior depends on the limit type. For email sending limits, additional sends are queued and will go out when the limit resets (monthly). For contact limits, you can still view and email existing contacts but cannot add new ones. For AI token limits, AI features are temporarily disabled until the next billing cycle or until you upgrade.\n\nIf you consistently hit your limits, consider upgrading to the next plan tier. Annual billing offers a significant discount compared to monthly billing, so switching to annual can offset the cost of an upgrade.\n\nFor teams with unique needs that do not fit standard plans, contact our sales team to discuss custom enterprise pricing. Enterprise plans offer custom limits, dedicated support, SLA guarantees, and additional features like SSO and audit logging.\n\nTip: Monitor the Usage section in your Dashboard regularly. This helps you anticipate when you might hit a limit and take action proactively, whether that means cleaning up your contact list, optimizing your campaign frequency, or upgrading your plan.",
                'related_feature' => 'billing',
            ],

            // ── Integrations ─────────────────────────────────────────
            [
                'slug' => 'connecting-integrations',
                'title' => 'Connecting Integrations',
                'category' => 'integrations',
                'sort_order' => 80,
                'excerpt' => 'Connect MailTrixy to Slack, Salesforce, Google Calendar, and other tools.',
                'content' => "MailTrixy integrates with popular business tools to streamline your workflow and keep your data in sync across platforms. Available integrations include Slack, Salesforce, Google Calendar, Zapier, and more.\n\nTo connect an integration, go to Settings > Integrations. You will see a grid of available integrations, each with a brief description of what it does. Click \"Connect\" on the integration you want to set up.\n\nSlack integration sends real-time notifications to your Slack channels when important events happen in MailTrixy, such as new deals created, campaigns completed, or high-value emails opened. You can customize which events trigger notifications and which channel receives them.\n\nSalesforce integration syncs your contacts, deals, and activity data between MailTrixy and Salesforce. This is a two-way sync, so changes in either platform are reflected in the other. Set up field mapping to control exactly which fields sync and in which direction.\n\nGoogle Calendar integration shows your upcoming meetings in the MailTrixy sidebar and lets you schedule meetings directly from contact records. When you create a meeting, it automatically appears on your Google Calendar with all relevant details.\n\nZapier integration opens up connections to thousands of other apps. Create Zaps that trigger MailTrixy actions based on events in other apps, or send MailTrixy data to external services. Common use cases include syncing form submissions to MailTrixy contacts, creating deals from CRM events, and logging activity to spreadsheets.\n\nEach integration can be disconnected at any time without losing your MailTrixy data. Go to Settings > Integrations, find the connected integration, and click \"Disconnect.\"",
                'related_feature' => 'integrations',
            ],

            // ── Troubleshooting ──────────────────────────────────────
            [
                'slug' => 'email-not-syncing',
                'title' => 'Email Not Syncing?',
                'category' => 'troubleshooting',
                'sort_order' => 90,
                'excerpt' => 'Fix common email sync issues and get your inbox back up and running.',
                'content' => "If your emails are not syncing between MailTrixy and your email provider, there are several common causes and solutions to try.\n\nFirst, check your email account status in Settings > Email. If the account shows a red error indicator, click on it to see the specific error message. The most common issue is an expired OAuth token, which can be fixed by clicking \"Reconnect\" and re-authorizing MailTrixy with your email provider.\n\nFor Gmail accounts, make sure you have not revoked MailTrixy's access. Go to your Google Account settings (myaccount.google.com), navigate to Security > Third-party apps with account access, and verify that MailTrixy is listed. If it was removed, reconnect from Settings > Email in MailTrixy.\n\nFor Outlook accounts, check that your Microsoft 365 admin has not disabled third-party app access. If your organization uses conditional access policies, MailTrixy may need to be whitelisted by your IT administrator.\n\nFor IMAP/SMTP accounts, verify that your server credentials have not changed. If your email provider requires an app-specific password (common when 2FA is enabled), make sure you are using the app password and not your regular account password.\n\nIf the account shows as connected but emails are still not appearing, try these steps: click the \"Force Sync\" button on the account settings page to trigger an immediate sync, check if your email provider is experiencing an outage (check their status page), and verify that your mailbox is not full, as some providers stop syncing when storage is exceeded.\n\nIf none of these solutions work, contact our support team with your account email and the error message you are seeing. We can check the server-side logs to identify the exact issue.",
                'related_feature' => 'email',
            ],
            [
                'slug' => 'campaign-not-sending',
                'title' => 'Campaign Not Sending?',
                'category' => 'troubleshooting',
                'sort_order' => 91,
                'excerpt' => 'Troubleshoot campaigns that are stuck, paused, or not delivering to recipients.',
                'content' => "If your campaign appears stuck or recipients are not receiving your emails, here are the most common causes and how to fix them.\n\nFirst, check the campaign status on the Campaigns page. A campaign can be in several states: Draft (not yet launched), Scheduled (waiting for the scheduled send time), Sending (actively delivering), Paused (manually paused or auto-paused due to an issue), and Completed (all emails sent). If your campaign is in Draft or Scheduled status, it has not started sending yet.\n\nIf the campaign shows as \"Sending\" but progress seems slow, this is likely due to sending throttling. MailTrixy automatically limits sending speed to protect your email reputation. For new email accounts, the default is 50 emails per hour. You can adjust this in Settings > Email under the account's sending limits, but we recommend increasing gradually.\n\nIf the campaign is \"Paused,\" click on it to see why. Common auto-pause reasons include a high bounce rate (more than 5% of emails bounced, indicating a list quality issue), the connected email account becoming disconnected, or hitting your plan's daily sending limit. Address the underlying issue and click \"Resume\" to continue sending.\n\nCheck the campaign report for delivery details. The report shows how many emails were sent, delivered, bounced, and deferred. High bounce rates usually mean your contact list needs cleaning. Remove invalid email addresses and re-verify your list before resuming.\n\nIf recipients say they are not seeing your emails, ask them to check their spam or promotions folder. Email deliverability depends on many factors including your sender reputation, email content, and the recipient's email provider. Make sure your sending domain has proper SPF, DKIM, and DMARC records configured. You can verify this in Settings > Email > Deliverability Check.\n\nFor campaigns using a drip sequence, check that the conditions between steps are not filtering out all recipients. A common mistake is setting a condition like \"If not replied\" when no one has replied yet, causing the workflow to wait indefinitely.",
                'related_feature' => 'campaigns',
            ],
        ];

        $appName = config('app.name', 'MailTrixy');

        foreach ($articles as $article) {
            // Replace hardcoded brand name with the configured app name
            foreach (['excerpt', 'content'] as $field) {
                if (isset($article[$field])) {
                    $article[$field] = str_replace('MailTrixy', $appName, $article[$field]);
                }
            }

            HelpArticle::updateOrCreate(
                ['slug' => $article['slug']],
                $article,
            );
        }
    }
}
