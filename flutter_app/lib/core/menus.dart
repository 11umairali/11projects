import 'package:flutter/material.dart';
import '../screens/campaign_screens.dart';
import '../screens/contacts_screens.dart';
import '../screens/deals_screens.dart';
import '../screens/inbox_screens.dart';
import '../screens/meetings_screens.dart';
import '../screens/misc_screens.dart';
import '../screens/settings_account.dart';
import '../screens/settings_workspace.dart';
import '../screens/people_screens.dart';
import '../screens/tools_screens.dart';
import '../screens/workflow_screens.dart';

/// The four bottom tabs.  Mail · Chats · People · Workspace
enum ShellMode { inbox, social, people, workspace }

/// What the shell exposes to destination builders.
abstract class ShellCtl {
  void go(Dest d);
  void switchMode(ShellMode m);
  void quick(String action);
  Dest? find(ShellMode m, String id);
}

class Dest {
  final String id, label;
  final IconData icon;
  final Widget Function(ShellCtl s)? builder;
  final ShellMode? switchTo; // entry switches the whole menu (Inbox, Exit …)
  const Dest(this.id, this.label, this.icon, {this.builder, this.switchTo});
}

class MenuEntry {
  final String? section;
  final Dest? dest;
  final int indent;
  const MenuEntry.section(this.section)
      : dest = null,
        indent = 0;
  const MenuEntry(this.dest, {this.indent = 0}) : section = null;
}

String modeTitle(ShellMode m) => switch (m) {
      ShellMode.inbox => 'Mail',
      ShellMode.social => 'Chats',
      ShellMode.people => 'People',
      ShellMode.workspace => 'Workspace',
    };

IconData modeIcon(ShellMode m) => switch (m) {
      ShellMode.inbox => Icons.mail_rounded,
      ShellMode.social => Icons.forum_rounded,
      ShellMode.people => Icons.people_alt_rounded,
      ShellMode.workspace => Icons.business_center_rounded,
    };

Dest _d(String id, String label, IconData icon, Widget Function(ShellCtl s) b) => Dest(id, label, icon, builder: b);

// ───────────────────────────── Shared native destinations ─────────────────────────────

Dest _folder(String id, String label, IconData icon) => _d(id, label, icon, (_) => ConversationsPage(folder: id, channel: 'email'));

Dest get _email => _d('set_email', 'Email accounts', Icons.alternate_email_rounded, (_) => const EmailAccountsPage());
Dest get _channels => _d('set_channels', 'Channels', Icons.cell_tower_rounded, (_) => const ChannelsPage());
Dest get _ai => _d('set_ai', 'AI configuration', Icons.psychology_rounded, (_) => const AiSettingsPage());
Dest get _auto => _d('set_auto', 'Auto-reply rules', Icons.reply_all_rounded, (_) => const AutoReplyPage());
Dest get _contactSettings => _d('set_contacts', 'Contact settings', Icons.contacts_rounded, (_) => const ContactSettingsPage());
Dest get _team => _d('set_team', 'Team', Icons.groups_rounded, (_) => const TeamPage());
Dest get _billing => _d('set_billing', 'Billing', Icons.credit_card_rounded, (_) => const BillingPage());
Dest get _workspaceSettings => _d('set_workspace', 'Workspace settings', Icons.business_center_rounded, (_) => const WorkspaceSettingsPage());
Dest get _integrations => _d('set_integrations', 'Integrations', Icons.extension_rounded, (_) => const IntegrationsPage());
Dest get _webhooks => _d('set_webhooks', 'Webhooks', Icons.webhook_rounded, (_) => const WebhookLogsPage());
Dest get _quick => _d('quick', 'Quick replies', Icons.bolt_rounded, (_) => const QuickRepliesPage());
Dest get _kb => _d('kb', 'Knowledge base', Icons.menu_book_rounded, (_) => const KnowledgeBasePage());
Dest get _workflows => _d('workflows', 'Workflows', Icons.account_tree_rounded, (_) => const WorkflowsPage());
Dest get _campaigns => _d('campaigns', 'Campaigns', Icons.campaign_rounded, (_) => const CampaignsPage());
Dest get _mailServer => _d('mailserver', 'Mail server', Icons.dns_rounded, (_) => const MailServerPage());
Dest get _deliverability => _d('deliverability', 'Email deliverability', Icons.mark_email_read_rounded, (_) => const DeliverabilityPage());
Dest get _analytics => _d('analytics', 'Analytics', Icons.insights_rounded, (_) => const AnalyticsPage());
Dest get _activity => _d('activity', 'Activity', Icons.history_rounded, (_) => const ActivityPage());
Dest get _deals => _d('deals', 'Deals', Icons.handshake_rounded, (_) => const DealsPage());

/// Menu for each area. RULES: (1) every screen lives in exactly ONE place; (2) the four bottom tabs are never repeated.
///   Mail       – e-mail folders only
///   Chats      – one chat list with channel filters (Direct / WhatsApp / Telegram / SMS / Slack / Live chat) – no side menu
///   People     – friends, team and customers in ONE directory (filters); chat / call / e-mail buttons on every person
///   Workspace  – ONE hub screen listing everything else: daily work, insights, connections, settings, tools, help (no side menu)
List<MenuEntry> menuFor(ShellMode mode) {
  switch (mode) {
    case ShellMode.inbox:
      return [
        MenuEntry(_folder('inbox', 'Inbox', Icons.inbox_rounded)),
        MenuEntry(_folder('sent', 'Sent', Icons.send_rounded)),
        MenuEntry(_folder('starred', 'Starred', Icons.star_rounded)),
        MenuEntry(_folder('snoozed', 'Snoozed', Icons.schedule_rounded)),
        MenuEntry(_folder('archive', 'Archive', Icons.archive_rounded)),
        MenuEntry(_folder('spam', 'Spam', Icons.report_gmailerrorred_rounded)),
        MenuEntry(_folder('trash', 'Trash', Icons.delete_rounded)),
      ];

    case ShellMode.social:
      // One screen: the chat list with channel filters on top. No side menu (nothing to duplicate).
      return [
        MenuEntry(_d('chats', 'Chats', Icons.chat_bubble_outline_rounded, (_) => const ConversationsPage(socialOnly: true))),
      ];

    case ShellMode.people:
      return [
        MenuEntry(_d('people', 'Friends', Icons.people_alt_rounded, (_) => const PeoplePage())),
      ];

    case ShellMode.workspace:
      return [
        MenuEntry(_d('ws_overview', 'Workspace', Icons.space_dashboard_rounded, (s) => WorkspaceOverviewPage(onOpen: (id) {
              if (id == 'reset') return s.switchMode(ShellMode.social);
              final d = s.find(ShellMode.workspace, id);
              if (d != null) s.go(d);
            }))),
        // Daily work, insights, tools and help (opened from the rows of the Workspace hub – there is no side menu any more)
        MenuEntry(_d('dashboard', 'Dashboard', Icons.dashboard_rounded, (s) => DashboardPage(onQuick: s.quick))),
        MenuEntry(_d('meetings', 'Meetings', Icons.co_present_rounded, (_) => const MeetingsPage())),
        MenuEntry(_d('contacts', 'Contacts', Icons.contacts_rounded, (_) => const ContactsPage())),
        MenuEntry(_deals),
        MenuEntry(_campaigns),
        MenuEntry(_workflows),
        MenuEntry(_analytics),
        MenuEntry(_activity),
        MenuEntry(_d('temp_mail', 'Temporary mail', Icons.timer_outlined, (_) => const TempMailPage())),
        MenuEntry(_d('help', 'Help center', Icons.help_outline_rounded, (_) => const HelpCenterPage())),
        MenuEntry(_wrap('ws_settings', _workspaceSettings)),
        MenuEntry(_wrap('ws_team', _team)),
        MenuEntry(_wrap('ws_billing', _billing)),
        const MenuEntry.section('Connections'),
        MenuEntry(_wrap('ws_email', _email)),
        MenuEntry(_wrap('ws_mailserver', _mailServer)),
        MenuEntry(_wrap('ws_deliverability', _deliverability)),
        MenuEntry(_wrap('ws_channels', _channels)),
        MenuEntry(_wrap('ws_integrations', _integrations)),
        MenuEntry(_wrap('ws_webhooks', _webhooks)),
        const MenuEntry.section('AI & automation'),
        MenuEntry(_wrap('ws_ai', _ai)),
        MenuEntry(_wrap('ws_auto', _auto)),
        MenuEntry(_wrap('ws_quick', _quick)),
        MenuEntry(_wrap('ws_kb', _kb)),
        const MenuEntry.section('Data'),
        MenuEntry(_wrap('ws_contacts', _contactSettings)),
      ];
  }
}

Dest _wrap(String id, Dest d) => Dest(id, d.label, d.icon, builder: d.builder);

/// First real screen shown when a mode is opened.
Dest defaultDest(ShellMode mode) => menuFor(mode).firstWhere((e) => e.dest != null && e.dest!.builder != null).dest!;

Dest? findDest(ShellMode mode, String id) {
  for (final e in menuFor(mode)) {
    if (e.dest?.id == id) return e.dest;
  }
  return null;
}
