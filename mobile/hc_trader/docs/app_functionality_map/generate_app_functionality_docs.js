const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const root = path.resolve(__dirname, '..', '..');
const outDir = __dirname;
const generatedDate = '2026-06-28';

const roles = [
  {
    role: 'Guest / unauthenticated user',
    status: 'Shell access only',
    modules: 'Auth screen, registration role lookup, public registration role metadata',
    permissions: 'Can sign in, register, request password reset, and read registration_roles where allowed.',
    restrictions: 'Cannot access HomeShell, Firestore protected content, admin modules, tickets, journal, exams, or trading hub.',
    files: 'lib/features/auth/auth_screen.dart; lib/repositories/auth_repository.dart; firestore.rules',
  },
  {
    role: 'Super Administrator (role_id 1)',
    status: 'Implemented',
    modules: 'All admin tabs, all More menu modules, user management, content management, feature toggles, audit, delete review',
    permissions: 'Full AdminPermission access; can assign every implemented role including Super Admin and Sub Admin.',
    restrictions: 'Business restrictions are mostly policy-driven; Firestore still requires authenticated user profile.',
    files: 'lib/core/security/app_roles.dart; lib/core/security/admin_permissions.dart; firestore.rules',
  },
  {
    role: 'Sub Administrator (role_id 2)',
    status: 'Implemented',
    modules: 'Admin dashboard, modules, users, onboarding, positions, content, performance, leaderboard, feature/audit/delete queues',
    permissions: 'Treated as admin in app permission extension; role matrix blocks managing existing Super/Sub Admin users.',
    restrictions: 'Cannot assign role_id 1 or 2; cannot manage existing admin users per matrix/rules.',
    files: 'lib/core/security/app_role_permissions.dart; firestore.rules; functions/role_permissions.js',
  },
  {
    role: 'Signal Provider (role_id 201)',
    status: 'Implemented',
    modules: 'Publisher workspace, Trading Signals, Trading Hub, Support, Recruitment if assignable matrix permits',
    permissions: 'Can view/create/edit own trading signals; can invite Trader and Lead Client.',
    restrictions: 'Cannot manage users, feature toggles, market analysis, audits, or delete reviews.',
    files: 'lib/models/app_user.dart; lib/core/security/admin_permissions.dart; firestore.rules',
  },
  {
    role: 'Senior Signal Provider (role_id 202)',
    status: 'Implemented',
    modules: 'Same signal publishing workspace as Signal Provider',
    permissions: 'Can view/create/edit own trading signals; can invite Trader and Lead Client.',
    restrictions: 'No broad admin management permissions confirmed.',
    files: 'lib/core/security/app_roles.dart; lib/core/security/admin_permissions.dart',
  },
  {
    role: 'Market Analyst (role_id 501)',
    status: 'Implemented',
    modules: 'Publisher workspace, Market Analysis, Knowledge/News view, Trading Hub, Support',
    permissions: 'Can view/create/edit own market analysis and request delete; can invite Trader and Lead Client.',
    restrictions: 'Cannot administer users/features; delete approval remains Super/Sub Admin.',
    files: 'lib/models/app_user.dart; lib/features/hub/publisher_sheets.dart; firestore.rules',
  },
  {
    role: 'Signal Provider Management (role_id 502)',
    status: 'Implemented',
    modules: 'Trading Signals and signal provider management',
    permissions: 'Can view/create/edit/delete trading signals and manage signal providers; can invite Trader and Lead Client.',
    restrictions: 'No general user/feature/audit administration confirmed.',
    files: 'lib/core/security/admin_permissions.dart; lib/core/security/app_role_permissions.dart',
  },
  {
    role: 'Lead Client (role_id 700)',
    status: 'Implemented',
    modules: 'Standard client/trader-facing modules where feature flags allow',
    permissions: 'Public registration target role; readable label exists; can be invited through referral relationships.',
    restrictions: 'Not in tradingAccess set for readiness/appointments unless separately granted by app logic; no publisher/admin permissions.',
    files: 'lib/core/security/app_roles.dart; firestore.rules',
  },
  {
    role: 'Trader (role_id 750)',
    status: 'Implemented',
    modules: 'Dashboard, Journal, Trading Hub, Accounts, Tools, Final Exam, Readiness, Appointments, Support, Recruitment, Profile, Settings',
    permissions: 'Can maintain own data, submit verification, create journal/capital/accounts, book appointments, submit exams, invite Trader/Lead Client.',
    restrictions: 'No admin/publishing permissions except trader self-service flows.',
    files: 'lib/app/home_shell.dart; lib/features/more/more_screen.dart; lib/core/security/app_role_permissions.dart',
  },
  {
    role: 'Trading Leadership (role_id 760)',
    status: 'Implemented',
    modules: 'Trader-facing modules plus recruitment/downline invitation',
    permissions: 'Can invite Recruiter, Trader and Lead Client.',
    restrictions: 'No broad admin management permissions confirmed.',
    files: 'lib/core/security/app_role_permissions.dart; functions/role_permissions.js',
  },
  {
    role: 'Trading Recruiter (role_id 770)',
    status: 'Implemented',
    modules: 'Recruitment/invite, trader-facing modules, Support',
    permissions: 'Can invite Trader and Lead Client.',
    restrictions: 'No admin management, publishing, or feature-toggle access confirmed.',
    files: 'lib/features/recruitment/recruitment_screen.dart; lib/core/security/app_role_permissions.dart',
  },
  {
    role: 'Financial Department',
    status: 'Not confirmed / inferred from code',
    modules: 'No implemented Flutter role found.',
    permissions: 'functions/role_permissions.js has canInviteFinancialDepartment=false, but no role_id/name is present.',
    restrictions: 'Treat as not implemented until a role id and screens are added.',
    files: 'functions/role_permissions.js',
  },
];

const screens = [
  ['AuthScreen', 'App home when user is null', 'Sign in, register, password reset and language switching', 'Guest', 'Authentication', 'lib/features/auth/auth_screen.dart'],
  ['HomeShell', 'Authenticated root', 'Four-tab shell with setup banner and Profile & more action', 'Authenticated users', 'App shell', 'lib/app/home_shell.dart'],
  ['DashboardScreen', 'Home tab', 'Performance summary and dashboard metrics', 'Authenticated users', 'Dashboard', 'lib/features/dashboard/dashboard_screen.dart'],
  ['JournalScreen', 'Journal tab', 'Review and filter journal entries', 'Authenticated users', 'Trading journal', 'lib/features/journal/journal_screen.dart'],
  ['JournalFormScreen', 'Pushed from Journal', 'Create/edit journal entry and attachments', 'Authenticated users', 'Trading journal', 'lib/features/journal/journal_form_screen.dart'],
  ['TradingHubScreen', 'Trading Hub tab', 'Tabbed hub for market, learning, signals, exams, positions, resources', 'Authenticated users', 'Trading hub', 'lib/features/hub/trading_hub_screen.dart'],
  ['Market tab', 'TradingHub tab 1', 'Market analysis feed and publisher actions', 'All signed-in; publishers get create/edit', 'Market analysis', 'lib/features/hub/trading_hub_screen.dart'],
  ['Knowledge tab', 'TradingHub tab 2', 'Knowledge-centre list and detail reading', 'Signed-in', 'Knowledge centre', 'lib/features/hub/trading_hub_screen.dart'],
  ['Classes tab', 'TradingHub tab 3', 'Recording classes with password verification before access', 'Signed-in', 'Recording classes', 'lib/features/hub/trading_hub_screen.dart'],
  ['Blogs tab', 'TradingHub tab 4', 'Trading blog feed and detail view', 'Signed-in', 'Trading blog', 'lib/features/hub/trading_hub_screen.dart'],
  ['Signals tab', 'TradingHub tab 5', 'Trading signals, state labels, targets and publisher actions', 'Signed-in; signal roles publish', 'Trading signals', 'lib/features/hub/trading_hub_screen.dart'],
  ['Exam tab', 'TradingHub tab 6', 'Daily trading exam flow and results', 'Signed-in', 'Daily exam', 'lib/features/hub/trading_hub_screen.dart'],
  ['Positions tab', 'TradingHub tab 7', 'Trading position applications', 'Signed-in traders', 'Positions', 'lib/features/hub/trading_hub_screen.dart'],
  ['Resources tab', 'TradingHub tab 8', 'Marketing/resource downloads with password verification', 'Signed-in', 'Resources', 'lib/features/hub/trading_hub_screen.dart'],
  ['MarketAnalysisDetailScreen', 'Pushed from hub', 'Detailed market analysis with related items', 'Signed-in', 'Market analysis', 'lib/features/hub/trading_hub_screen.dart'],
  ['TradingBlogDetailScreen', 'Pushed from hub', 'Blog article detail', 'Signed-in', 'Trading blog', 'lib/features/hub/trading_hub_screen.dart'],
  ['RecordingDetailScreen', 'Pushed after unlock', 'Recording class detail and materials', 'Signed-in with password check', 'Recording classes', 'lib/features/hub/trading_hub_screen.dart'],
  ['ResourceDetailScreen', 'Pushed from resources', 'Marketing/resource detail and download', 'Signed-in with password check for protected access', 'Resources', 'lib/features/hub/trading_hub_screen.dart'],
  ['ReadableDetailScreen', 'Pushed from knowledge/resources', 'Generic readable detail page', 'Signed-in', 'Learning content', 'lib/features/hub/trading_hub_screen.dart'],
  ['AccountScreen', 'Accounts tab', 'Verification, account profile, trading accounts, capital and position applications', 'Authenticated users', 'Account / KYC', 'lib/features/account/account_screen.dart'],
  ['MoreScreen', 'Profile action push', 'Role-aware service grid and sign-out', 'Authenticated users', 'More menu', 'lib/features/more/more_screen.dart'],
  ['FinalExamScreen', 'More menu/setup banner', 'Official final examination and status', 'Authenticated users when feature enabled', 'Final examination', 'lib/features/examination/final_exam_screen.dart'],
  ['DailyExamResultScreen', 'Pushed after daily exam', 'Daily exam result display', 'Authenticated users', 'Daily exam', 'lib/features/examination/daily_exam_result_screen.dart'],
  ['ToolsScreen', 'More menu', 'Tool launcher', 'Authenticated users', 'Trading toolkit', 'lib/features/tools/tools_screen.dart'],
  ['RiskCalculatorScreen', 'Tools', 'Position size and risk/reward calculator', 'Authenticated users', 'Risk calculator', 'lib/features/risk/risk_calculator_screen.dart'],
  ['StatisticsScreen', 'Tools', 'Journal analytics, breakdowns and charts', 'Authenticated users', 'Statistics', 'lib/features/statistics/statistics_screen.dart'],
  ['PropFirmScreen', 'Tools', 'Prop firm progress, limits and withdrawal eligibility', 'Authenticated users', 'Prop firm', 'lib/features/prop_firm/prop_firm_screen.dart'],
  ['PsychologyScreen', 'Tools', 'Daily psychology check-in and warnings', 'Authenticated users', 'Psychology', 'lib/features/psychology/psychology_screen.dart'],
  ['TradingPlanScreen', 'Tools', 'Local trading-plan drafting', 'Authenticated users', 'Trading plan', 'lib/features/trading_plan/trading_plan_screen.dart'],
  ['ReadinessChecklistScreen', 'More menu', 'Trader readiness checklist by category', 'Role 1,2,750,760,770 when enabled', 'Readiness', 'lib/features/readiness/readiness_checklist_screen.dart'],
  ['TradingAppointmentsScreen', 'More menu/Admin', 'Book, request, approve and cancel trading appointments', 'Trader/admin/eligible roles', 'Appointments', 'lib/features/appointments/trading_appointments_screen.dart'],
  ['SupportTicketsScreen', 'More menu/Admin', 'Create, reply, assign, close/reopen support tickets', 'Support-enabled roles/admin', 'Support desk', 'lib/features/support/support_tickets_screen.dart'],
  ['TicketDetailScreen', 'Pushed from Support', 'Ticket messages and lifecycle actions', 'Ticket owner/admin', 'Support desk', 'lib/features/support/support_tickets_screen.dart'],
  ['RecruitmentScreen', 'More menu', 'Referral code and downline workspace', 'Roles with assignable public roles', 'Recruitment', 'lib/features/recruitment/recruitment_screen.dart'],
  ['ProfileScreen', 'More menu', 'Profile photo, profile fields and password change', 'Authenticated users', 'Profile', 'lib/features/profile/profile_screen.dart'],
  ['SettingsScreen', 'More menu', 'Pairs, notification preferences, privacy and language/sync settings', 'Authenticated users', 'Settings', 'lib/features/settings/settings_screen.dart'],
  ['AdminOverviewScreen', 'More menu/Admin', 'Admin TabBar root', 'Super/Sub Admin', 'Administration', 'lib/features/admin/admin_overview_screen.dart'],
  ['Admin Overview tab', 'Admin tab 1', 'Aggregate counts and recent users', 'Admin', 'Administration', 'lib/features/admin/admin_overview_screen.dart'],
  ['Admin Modules tab', 'Admin tab 2', 'Resource administration launcher', 'Admin/publisher permissions', 'Admin modules', 'lib/features/admin/admin_management_screen.dart'],
  ['Admin Users tab', 'Admin tab 3', 'Search/filter/edit users and roles', 'Admin', 'User management', 'lib/features/admin/admin_overview_screen.dart'],
  ['Admin Verification tab', 'Admin tab 4', 'Onboarding/KYC review and document preview', 'Admin', 'Verification', 'lib/features/admin/admin_overview_screen.dart'],
  ['Admin Positions tab', 'Admin tab 5', 'Approve/reject position applications', 'Admin', 'Positions', 'lib/features/admin/admin_overview_screen.dart'],
  ['Admin Content tab', 'Admin tab 6', 'Recordings, knowledge and community documents', 'Admin', 'Content', 'lib/features/admin/admin_trading_operations.dart'],
  ['Admin Performance tab', 'Admin tab 7', 'Trader performance and prop-firm reviews', 'Admin', 'Performance', 'lib/features/admin/admin_trading_operations.dart'],
  ['Admin Leaderboard tab', 'Admin tab 8', 'Leaderboard and competitions', 'Admin', 'Leaderboard', 'lib/features/admin/admin_trading_operations.dart'],
  ['Admin Requests tab', 'Admin tab 9', 'Delete request approval/rejection', 'Admin with delete review permission', 'Delete requests', 'lib/features/admin/admin_management_screen.dart'],
  ['Admin Features tab', 'Admin tab 10', 'Feature flag toggles', 'Admin with featureManage', 'Feature configuration', 'lib/features/admin/admin_management_screen.dart'],
  ['Admin Audit tab', 'Admin tab 11', 'Audit log view', 'Admin with auditView', 'Audit', 'lib/features/admin/admin_management_screen.dart'],
  ['AdminResourceScreen', 'Pushed from Admin Modules', 'Generic CRUD for configured admin collections', 'Permission-specific', 'Admin resources', 'lib/features/admin/admin_management_screen.dart'],
  ['AdminJournalReviewScreen', 'Pushed from Admin Modules', 'Review journal rows and add admin notes', 'Admin with journalManage', 'Journal administration', 'lib/features/admin/admin_management_screen.dart'],
  ['AdminExamResultsScreen', 'Pushed from Admin Modules', 'Review daily/final exam attempts', 'Admin with examResultReview', 'Exam administration', 'lib/features/admin/admin_management_screen.dart'],
];

const modules = [
  {
    name: 'App bootstrap, theme and auth gate',
    purpose: 'Initialize Firebase, local storage, locale controller and render either AuthScreen or HomeShell.',
    flow: 'main() initializes Firebase and storage, creates repositories, then HCTraderApp uses StreamBuilder over authStateChanges.',
    screens: 'HCTraderApp, _AuthGate, HomeShell',
    actions: 'Load current user, listen to auth stream, configure optional push notifications after first frame.',
    data: 'FirebaseAuth current user; users/{uid} profile; SharedPreferences locale/preferences.',
    backend: 'Firebase Core/Auth/Firestore; PushNotificationService.',
    access: 'All users after app launch; authenticated shell only after user profile resolves.',
    files: 'lib/main.dart; lib/app/hc_trader_app.dart; lib/app/home_shell.dart',
    notes: 'Current main.dart forces firebaseEnabled=true; README says no Laravel runtime dependency.',
  },
  {
    name: 'Authentication and session management',
    purpose: 'Secure sign in, registration, password reset, password change and sign out.',
    flow: 'Guest opens AuthScreen, signs in by email/username or registers. AuthRepository resolves Firebase user into AppUser and profile.',
    screens: 'AuthScreen, ProfileScreen password dialog, MoreScreen sign-out button',
    actions: 'Email/username login, create Firebase account, call validated profile Cloud Function, reset password, verify password, update password, sign out.',
    data: 'Firebase Auth user; users; usernames; registration_roles.',
    backend: 'Firebase Auth; Firestore; Cloud Function registerUserProfileAfterAuth; HTTP callable fallback on Windows.',
    access: 'Guest and authenticated user self-service.',
    files: 'lib/features/auth/auth_screen.dart; lib/repositories/auth_repository.dart; functions/registration_functions.js',
    notes: 'Sign-out is designed to clear local app session before asynchronous Firebase cleanup to avoid stuck loading.',
  },
  {
    name: 'Registration, referral and downline system',
    purpose: 'Create user profiles with role validation and referral/downline relationships.',
    flow: 'Registration submits user fields, role id and referral code; Cloud Function validates role/referral, creates usernames, referral codes, relationships and downline docs.',
    screens: 'AuthScreen registration tab; RecruitmentScreen',
    actions: 'Register, generate/read referral code, show downlines, invite Trader/Lead Client based on role matrix.',
    data: 'registration_roles; referral_codes; referral_relationships; users/{uid}/downlines; users.',
    backend: 'registerUserProfileAfterAuth callable; Firestore transaction; role permission mirror in functions/role_permissions.js.',
    access: 'Public registration roles Trader and Lead Client; inviter roles from RolePermissionMatrix.',
    files: 'lib/repositories/auth_repository.dart; lib/features/recruitment/recruitment_screen.dart; functions/registration_functions.js',
    notes: 'Privileged roles require trusted backend creation and access approval.',
  },
  {
    name: 'Dashboard',
    purpose: 'Give traders an at-a-glance view of performance and trading state.',
    flow: 'Home tab reads repository streams and local analytics models.',
    screens: 'DashboardScreen',
    actions: 'View summary metrics, recent performance and status cards.',
    data: 'trading_journals; capitals; trading_accounts; computed TradingSummary.',
    backend: 'Firestore user subcollections through TradingRepository.',
    access: 'Authenticated users.',
    files: 'lib/features/dashboard/dashboard_screen.dart; lib/shared/performance_charts.dart; lib/services/trading_analytics.dart',
    notes: 'Exact visual widgets depend on stream availability and cached data.',
  },
  {
    name: 'Trading journal',
    purpose: 'Record, import, analyze and administratively review trades.',
    flow: 'Journal tab lists entries; form creates/edits entries; attachments upload to Storage; admin can review collection-group data.',
    screens: 'JournalScreen, JournalFormScreen, AdminJournalReviewScreen',
    actions: 'Create/edit/delete journal, attach images/documents, import CSV/XLSX, add admin notes.',
    data: 'users/{uid}/trading_journals; Storage users/{uid}/... journal attachments.',
    backend: 'Firestore; Firebase Storage; local queued drafts in SharedPreferences; excel/file_picker for imports.',
    access: 'Owner or admin; admin review through collection-group rules.',
    files: 'lib/features/journal/*; lib/repositories/trading_repository.dart; lib/models/trading_journal_entry.dart',
    notes: 'Tests cover calculation, analytics and reporting around journal data.',
  },
  {
    name: 'Accounts, verification and capital',
    purpose: 'Manage trader profile verification, broker/trading accounts, capital transactions and position applications.',
    flow: 'Accounts tab reads onboarding profile, accounts, capital and applications; setup banner can link here.',
    screens: 'AccountScreen, Admin Verification tab, Admin Positions tab',
    actions: 'Save account profile, upload onboarding document, add/update/delete trading accounts, capital transactions, submit position applications, admin approve/reject.',
    data: 'users/{uid}/trader_onboarding/profile; onboarding_documents; trading_accounts; capitals; trading_position_applications.',
    backend: 'Firestore and Storage user paths; admin collection-group reads.',
    access: 'Owner self-service; admin review.',
    files: 'lib/features/account/account_screen.dart; lib/models/trader_account_profile.dart; lib/models/trading_account.dart',
    notes: 'This is the closest equivalent to KYC/verification in the Flutter app.',
  },
  {
    name: 'Trading hub',
    purpose: 'Central workspace for market content, learning, signals, exams, position applications and resources.',
    flow: 'TradingHubScreen uses DefaultTabController with eight hub tabs.',
    screens: 'TradingHubScreen plus Market, Knowledge, Classes, Blogs, Signals, Exam, Positions, Resources tabs and detail screens.',
    actions: 'Read content, open details, publish signals/analysis for eligible roles, unlock protected recordings/resources, take daily exam, apply for positions.',
    data: 'market_analyses; trading_signals; trading_blogs; trading_resources; trading_recordings; knowledge_centres; trading_exam_questions; trading_position_applications.',
    backend: 'Firestore streams, Storage URLs, AuthRepository password verification, url_launcher for external resources.',
    access: 'Signed-in users; publishing restricted by role.',
    files: 'lib/features/hub/trading_hub_screen.dart; lib/features/hub/publisher_sheets.dart',
    notes: 'Feature flags in app_configuration/modules can restrict content collections through Firestore rules and More menu conditions.',
  },
  {
    name: 'Trading signals',
    purpose: 'Publish and consume signal cards with pair, action, entry, SL/TP and status.',
    flow: 'Hub Signals tab streams active signals; publisher sheet writes trading_signals; Cloud Function notifies topic subscribers.',
    screens: 'Signals tab, signal publisher sheet, Admin Trading Signals resource',
    actions: 'View, publish, edit/delete where authorized, notify users.',
    data: 'trading_signals fields include signal_title/title, trading_pair, immediate_action, entry_price, targets, disclaimer, risk_level, status, created_by.',
    backend: 'Firestore; Cloud Messaging topic hc_trading_signals; notifyTradingSignal function.',
    access: 'Read signed-in if feature enabled; create/update for admins or role_id 201/202/502; delete for signal manager/owner by rules.',
    files: 'lib/models/trading_signal_item.dart; lib/features/hub/publisher_sheets.dart; functions/index.js',
    notes: 'Signal Provider Management has delete/manage permissions beyond normal signal providers.',
  },
  {
    name: 'Market analysis',
    purpose: 'Publish and read analyst views with trend, market, tags and related content.',
    flow: 'Hub Market tab streams analyses; Market Analyst/Admin users see publish actions; detail screen links related analysis.',
    screens: 'Market tab, MarketAnalysisDetailScreen, market publisher sheet, Admin Market Analyst resource',
    actions: 'View, filter, publish, edit, request delete, admin review delete.',
    data: 'market_analyses with title, body/description, market, trend, image_url, status, created_by.',
    backend: 'Firestore; Storage market_analyses/{userId}; notifyMarketAnalysis function.',
    access: 'Read signed-in if feature enabled; create/update for role_id 501/admin; delete only Super Admin by rules or via delete_requests workflow.',
    files: 'lib/models/market_analysis_item.dart; lib/features/hub/publisher_sheets.dart; firestore.rules',
    notes: 'Delete request workflow is limited to market_analyses and trading_resources.',
  },
  {
    name: 'Learning content, blogs, news, recordings and resources',
    purpose: 'Provide educational material, marketing resources, recordings, knowledge articles and business documents.',
    flow: 'Hub tabs read content collections; admin modules publish and archive records; protected resources require password re-verification.',
    screens: 'Knowledge tab, Classes tab, Blogs tab, Resources tab, detail screens, Admin Content tab, AdminResourceScreen',
    actions: 'Read/open/download, publish/edit/archive/restore, upload images/PDF/DOCX/MP4, manage community documents.',
    data: 'trading_blogs; trading_news; trading_resources; trading_recordings; knowledge_centres; community_documents.',
    backend: 'Firestore; Firebase Storage admin_assets, recording_materials, knowledge_centre, community_documents; Cloud Messaging recording/knowledge topics.',
    access: 'Signed-in read where feature enabled; admin writes; recording/resource access may require password verification.',
    files: 'lib/features/hub/trading_hub_screen.dart; lib/features/admin/admin_trading_operations.dart; storage.rules',
    notes: 'Some admin publishing dialogs contain hardcoded English text, which is a localization risk.',
  },
  {
    name: 'Daily exam and final examination',
    purpose: 'Assess trading knowledge with daily questions and formal final exam attempts.',
    flow: 'Daily exam is available in Trading Hub; final exam opens from More/setup banner; admin reviews attempts and manages question banks.',
    screens: 'Exam tab, DailyExamResultScreen, FinalExamScreen, Admin Exam Results, Admin Trading Exam Questions, Admin Final Examination',
    actions: 'Start exam, submit answers, view score/result, write attempts, admin manage questions/banks and review attempts.',
    data: 'trading_exam_questions; users/{uid}/trading_exam_attempts; final_exam_questions; final_exam_question_banks; users/{uid}/final_exam_attempts.',
    backend: 'Firestore; notification functions notifyTradingExamResult and notifyFinalExamResult.',
    access: 'Signed-in users; admin manages content/results.',
    files: 'lib/features/examination/*; lib/models/trading_exam_models.dart; lib/models/final_exam_mobile.dart',
    notes: 'Tests strongly cover final exam Chinese fallback and localization migration behavior.',
  },
  {
    name: 'Trading toolkit',
    purpose: 'Collect local trader utilities that support risk, analysis, planning and discipline.',
    flow: 'More menu opens ToolsScreen; each tool pushes its own screen.',
    screens: 'ToolsScreen, RiskCalculatorScreen, StatisticsScreen, PropFirmScreen, PsychologyScreen, TradingPlanScreen',
    actions: 'Calculate lot size and RR, view statistics, track prop firm rules, perform psychology check-in, maintain trading plan.',
    data: 'SharedPreferences drafts/history; journals/capitals for statistics/prop firm; prop_firm_models for configuration selection.',
    backend: 'Mostly local services; Firestore for journals/accounts/prop firm model selection where needed.',
    access: 'Authenticated users.',
    files: 'lib/features/tools/tools_screen.dart; lib/services/*calculator*; lib/services/*analytics*',
    notes: 'Local persistence is intentionally non-sensitive per LocalAppStorage comments.',
  },
  {
    name: 'Prop firm workflow',
    purpose: 'Track prop firm phases, rule compliance, withdrawals and admin review status.',
    flow: 'PropFirmScreen calculates progress; admin performance tab reviews prop-firm outcomes and can approve/suspend/request correction.',
    screens: 'PropFirmScreen, Admin Performance tab, Admin Prop Firm Config resource',
    actions: 'Select model, calculate target/drawdown/best-day rules, submit/receive admin review decisions.',
    data: 'prop_firm_models; users/{uid} prop_firm_* fields; journals/capitals for calculations.',
    backend: 'Firestore; PropFirmProgressService; notifyPropFirmReview Cloud Function.',
    access: 'Authenticated traders; admin manages models/reviews.',
    files: 'lib/features/prop_firm/prop_firm_screen.dart; lib/services/prop_firm_progress_service.dart; lib/features/admin/admin_trading_operations.dart',
    notes: 'Tests cover daily loss failure and report withdrawal gating.',
  },
  {
    name: 'Psychology and warning module',
    purpose: 'Score discipline and warn about revenge trading, FOMO and overconfidence.',
    flow: 'Tool screen stores check-ins locally and displays warnings/recommendations.',
    screens: 'PsychologyScreen',
    actions: 'Input mood/confidence/urge flags, calculate score, show warnings and pause advice.',
    data: 'SharedPreferences user-scoped psychology history.',
    backend: 'Local only based on PsychologyScoreService.',
    access: 'Authenticated users.',
    files: 'lib/features/psychology/psychology_screen.dart; lib/services/psychology_score_service.dart',
    notes: 'No Firebase write confirmed for psychology entries.',
  },
  {
    name: 'Readiness checklist',
    purpose: 'Help traders complete pre-live-trading readiness items with status/progress.',
    flow: 'More menu opens checklist; user completes/reopens items by category.',
    screens: 'ReadinessChecklistScreen',
    actions: 'View progress, complete item with notes/confidence, reopen/reset checklist.',
    data: 'users/{uid}/readiness_checklist with localized/dynamic fields.',
    backend: 'Firestore; localizedValue helpers for dynamic content.',
    access: 'Roles 1,2,750,760,770 when trader_centre feature is enabled.',
    files: 'lib/features/readiness/readiness_checklist_screen.dart; lib/repositories/trading_repository.dart',
    notes: 'Localization accepts *_localized, *_translations and map-style fields.',
  },
  {
    name: 'Appointments',
    purpose: 'Allow traders to book or request mentor appointments and admins/hosts to manage slots.',
    flow: 'More menu opens appointment workspace; admins create slots/hosts; users book slots or preferred times.',
    screens: 'TradingAppointmentsScreen, Admin Appointment Slots resource',
    actions: 'Create/delete slot, book slot, request preferred appointment, approve/reject/cancel, notify participants.',
    data: 'trading_appointment_slots; appointment_hosts; trading_appointments.',
    backend: 'Firestore; notification functions notifyNewAppointment and notifyAppointmentUpdate.',
    access: 'Trading roles/admin when appointments feature is enabled; Firestore validates participant_ids and times.',
    files: 'lib/features/appointments/trading_appointments_screen.dart; functions/index.js; firestore.rules',
    notes: 'Rules prevent self-hosting and past scheduled_start_at creation.',
  },
  {
    name: 'Support desk',
    purpose: 'Let users create support tickets and staff respond, assign and close/reopen tickets.',
    flow: 'More menu opens ticket list; ticket detail streams messages; admin sees wider operations in management workspace.',
    screens: 'SupportTicketsScreen, TicketDetailScreen, Admin Support Tickets resource',
    actions: 'Create ticket, upload attachments, reply, assign, close, reopen, delete by admin.',
    data: 'support_tickets; support_tickets/{ticketId}/messages; Storage support_tickets/{userId}.',
    backend: 'Firestore streams; Storage; notifySupportTicketUpdate function.',
    access: 'Ticket owner and admins; supportAccess set includes admin, signal, analyst, trader/leadership/recruiter roles.',
    files: 'lib/features/support/support_tickets_screen.dart; firestore.rules; storage.rules',
    notes: 'Message create rule requires ticket owner unless admin state is visible in getAfter result.',
  },
  {
    name: 'Recruitment and invitations',
    purpose: 'Expose referral code and downline status to roles that can invite public roles.',
    flow: 'More menu shows Recruitment when RolePermissionMatrix assignable roles include public registration roles.',
    screens: 'RecruitmentScreen',
    actions: 'View referral code, copy/share invite, inspect downline list.',
    data: 'users; users/{uid}/downlines; referral_codes; referral_relationships.',
    backend: 'Firestore; registration function maintains relationships.',
    access: 'Leader, recruiter, signal/market roles, trader and admins according to assignable matrix.',
    files: 'lib/features/recruitment/recruitment_screen.dart; lib/core/security/app_role_permissions.dart',
    notes: 'Downline profile visibility is enforced in Firestore rules by upline/referrer/invited_by fields.',
  },
  {
    name: 'Profile and settings',
    purpose: 'Let users manage profile, avatar, password, notifications, language and app preferences.',
    flow: 'More menu opens Profile/Settings; settings reads feature and notification status.',
    screens: 'ProfileScreen, SettingsScreen',
    actions: 'Upload avatar, edit name/email display fields, verify/update password, change language, notification preferences, privacy link.',
    data: 'users/{uid}; profile_images/{uid}; SharedPreferences locale and notification preferences; users/{uid}/notification_tokens.',
    backend: 'Firebase Auth, Firestore, Storage, Messaging, Local Notifications.',
    access: 'Authenticated owner.',
    files: 'lib/features/profile/profile_screen.dart; lib/features/settings/settings_screen.dart; lib/services/push_notification_service.dart',
    notes: 'Android notification permission is declared; unsupported platforms are skipped by service.',
  },
  {
    name: 'Administration dashboard and resource management',
    purpose: 'Operate the platform: users, verification, positions, content, features, audit, delete requests, performance and competitions.',
    flow: 'AdminOverviewScreen has 11 tabs; AdminManagementScreen lists configured resource modules and review screens.',
    screens: 'AdminOverviewScreen, AdminManagementScreen, AdminResourceScreen and admin tabs',
    actions: 'Manage users/roles, review onboarding/positions, CRUD content, configure features, review journals/exams/delete requests, audit actions, manage leaderboard/competitions.',
    data: 'Most shared collections plus users subcollections; audit_logs and delete_requests.',
    backend: 'Firestore admin reads/writes; Storage uploads; AdminPermission and Firestore rules.',
    access: 'Super/Sub Admin; publisher roles get narrower Publisher workspace.',
    files: 'lib/features/admin/*; lib/core/security/admin_permissions.dart; lib/repositories/trading_repository.dart',
    notes: 'Admin permission model in Flutter is broader for Sub Admin than the Cloud Function role-permission helper; Firestore role rules still constrain sensitive writes.',
  },
  {
    name: 'Feature configuration and module gating',
    purpose: 'Enable/disable business modules from Firestore.',
    flow: 'MoreScreen loads featureConfiguration; Firestore rules featureEnabled checks app_configuration/modules.',
    screens: 'Admin Features tab, MoreScreen',
    actions: 'Toggle feature flags, hide menu modules, block reads for gated Firestore content.',
    data: 'app_configuration/modules; tradingFeatureDefaults.',
    backend: 'Firestore rules and repository adminSaveFeatureConfiguration.',
    access: 'Read signed-in; write Super/Sub Admin.',
    files: 'lib/core/security/admin_permissions.dart; lib/features/admin/admin_management_screen.dart; firestore.rules',
    notes: 'Feature ai_assistant defaults true, but no confirmed Flutter AI assistant UI/client is implemented.',
  },
  {
    name: 'Localization and translation operations',
    purpose: 'Support English/Simplified Chinese UI and manual Firestore bilingual content validation.',
    flow: 'AppLocaleController persists locale; AppLocalizations resolves static labels; model helpers resolve *_i18n and localized maps; Functions/tools backfill Chinese dynamic content.',
    screens: 'Auth language controls; Settings language controls; all l10n-aware screens',
    actions: 'Switch language, localize static strings, display dynamic Firestore translations, audit/backfill translations, validate manual Chinese fields through audit tooling.',
    data: 'Localized map fields such as title_i18n, description_i18n, translations.zh, translationMeta.',
    backend: 'No translation Cloud Functions or API secrets; tools audit manual bilingual coverage.',
    access: 'Static locale all users; translation regeneration admin-only.',
    files: 'lib/core/localization/*; lib/core/localization/*; tools/audit_chinese_localisation.js; tools/backfill_final_exam_questions_zh.js',
    notes: 'Hardcoded English remains in several admin/publisher dialogs and should be reviewed for full Chinese coverage.',
  },
  {
    name: 'Notifications',
    purpose: 'Notify users about published content and workflow changes.',
    flow: 'PushNotificationService registers tokens and topic subscriptions; Cloud Functions send topic/direct FCM messages.',
    screens: 'Settings notification preferences; foreground notifications',
    actions: 'Request permission, subscribe/unsubscribe topics, store/delete tokens, show Android foreground local notification.',
    data: 'users/{uid}/notification_tokens; SharedPreferences notification_preferences.',
    backend: 'Firebase Messaging; flutter_local_notifications; Cloud Functions notification exports.',
    access: 'Supported only on Android/iOS/macOS by service check; Android configured in current project.',
    files: 'lib/services/push_notification_service.dart; functions/index.js; android/app/src/main/AndroidManifest.xml',
    notes: 'Windows/Web are not supported by the service condition even though app platforms exist.',
  },
  {
    name: 'Reports, PDF and analytics',
    purpose: 'Generate trading performance summaries and PDF/shareable reports.',
    flow: 'Statistics/PropFirm/report services consume journal and capital data.',
    screens: 'StatisticsScreen, PropFirmScreen, report/PDF actions where surfaced',
    actions: 'Calculate balance, win rate, drawdown, expectancy, best-day rule, prop-firm status, PDF build/share.',
    data: 'TradingJournalEntry, CapitalTransaction, TradingReport, TradingSummary.',
    backend: 'Local Dart services; pdf and printing packages.',
    access: 'Authenticated users; admin performance tab reads aggregate data.',
    files: 'lib/services/trading_report_service.dart; lib/services/trading_report_pdf_service.dart; lib/services/trading_analytics.dart',
    notes: 'Tests cover report rules and trading analytics.',
  },
];

const dataStructures = [
  ['registration_roles', 'Registration role catalogue', 'Auth/registration', 'Read public; admin write', 'role_id, label/name, active flags', 'auth_repository.dart; firestore.rules'],
  ['app_configuration/modules', 'Feature flags', 'More menu, rules, admin features', 'Read signed-in; Super/Sub Admin write', 'trading, appointments, support_tickets, ai_assistant, etc.', 'admin_permissions.dart; firestore.rules'],
  ['audit_logs', 'Admin/action history', 'Admin audit tab', 'Admin read; signed-in create by actor; no update/delete', 'actor_id, action, target_type, target_id, created_at', 'trading_repository.dart; firestore.rules'],
  ['admin_audit_logs', 'Registration/bootstrap audit', 'Registration function', 'Admin read; app cannot write from client', 'event/action metadata', 'registration_functions.js; firestore.rules'],
  ['delete_requests', 'Publisher/admin delete review workflow', 'Admin requests, market/resources', 'Requester create pending; Admin update; no delete', 'target_type, target_id, requester_id, status, reason', 'admin_management_screen.dart; firestore.rules'],
  ['usernames', 'Username uniqueness and login lookup', 'Auth', 'Public read; owner create/delete', 'uid, username_lowercase', 'auth_repository.dart; registration_functions.js'],
  ['referral_codes', 'Referral/invite code ownership', 'Recruitment/registration', 'Public read; owner/admin update; admin delete', 'code, user_id, owner_user_id, code_type, allowed_purpose', 'registration_functions.js; firestore.rules'],
  ['referral_relationships', 'Upline/downline links', 'Recruitment/admin', 'Admin or related users read; function writes', 'user_id, referrer_id, role_id', 'registration_functions.js; firestore.rules'],
  ['users', 'Primary app user profile', 'Auth, dashboard, admin, profile', 'Owner/admin/downline read; owner limited update; admin role update', 'email, name, username, role_id, is_admin, status, onboarding/final flags', 'app_user.dart; auth_repository.dart'],
  ['users/{uid}/trading_journals', 'Trade journal entries', 'Journal/statistics/reports/admin', 'Owner/admin read/write; admin collection-group review', 'pair, action, lot, entry, stop, take profit, result, attachments', 'trading_repository.dart; trading_journal_entry.dart'],
  ['users/{uid}/capitals', 'Deposits/withdrawals/capital adjustments', 'Accounts/statistics/prop firm', 'Owner/admin read/write', 'amount, type, account_id, created_at', 'capital_transaction.dart'],
  ['users/{uid}/trading_accounts', 'Broker/account records', 'Account/prop firm', 'Owner/admin read/write', 'broker, account_number, balance, phase/status fields', 'trading_account.dart'],
  ['users/{uid}/trader_onboarding/profile', 'Verification profile', 'Account/admin verification', 'Owner/admin read/write', 'identity/profile fields, status, review_note', 'trader_account_profile.dart'],
  ['users/{uid}/onboarding_documents/current', 'Verification document metadata', 'Account/admin verification', 'Owner/admin read/write', 'name, path/url, content type', 'trading_repository.dart; storage.rules'],
  ['users/{uid}/readiness_checklist', 'Readiness items/progress', 'Readiness checklist', 'Owner/admin read/write', 'category, title, completed, confidence, notes, localized fields', 'readiness_checklist_screen.dart'],
  ['users/{uid}/trading_position_applications', 'Position/funded account applications', 'Hub positions/admin positions', 'Owner/admin read/write', 'status, role snapshot, trade_count_snapshot, review_note', 'trading_position_application_mobile.dart'],
  ['users/{uid}/trading_exam_attempts', 'Daily exam attempts', 'Daily exam/admin exam results', 'Owner/admin read; owner create completed; admin update/delete', 'answers, score, total_questions, status', 'trading_exam_models.dart; firestore.rules'],
  ['users/{uid}/final_exam_attempts', 'Final examination attempts', 'Final exam/admin results', 'Owner/admin read/write', 'question snapshots, answers, percentage, passed/status', 'final_exam_mobile.dart'],
  ['users/{uid}/downlines', 'Downline display records', 'Recruitment', 'Owner/admin read; client write blocked', 'downline user snapshot', 'registration_functions.js; recruitment_screen.dart'],
  ['users/{uid}/notification_tokens', 'FCM tokens/preferences', 'Notifications', 'Owner read/write', 'token, platform, preferences, appointments_enabled', 'push_notification_service.dart'],
  ['users/{uid}/notifications', 'User notification records', 'Notifications/admin', 'Owner/admin read/update/delete; admin create', 'title, body, category, document_id', 'firestore.rules'],
  ['trading_pairs', 'Tradable symbols/pairs', 'Journal/settings/trading content', 'Signed-in read; admin write', 'id, symbol, metadata', 'trading_repository.dart'],
  ['trading_blogs', 'Blog posts', 'Trading hub/blog/admin', 'Signed-in read if feature enabled; admin write', 'title, content, category, tags, i18n fields', 'trading_blog_post.dart'],
  ['trading_news', 'Market/news content', 'Admin/news', 'Signed-in read if feature enabled; admin write', 'title, body, status', 'admin_management_screen.dart'],
  ['market_analyses', 'Market analyst content', 'Hub/admin/delete request/notifications', 'Signed-in read; analyst/admin create/update; Super Admin delete', 'title, market, trend, image_url, created_by', 'market_analysis_item.dart'],
  ['trading_signals', 'Trading signal content', 'Hub/admin/notifications', 'Signed-in read; signal publisher create/update; manager delete', 'signal_title, trading_pair, immediate_action, entry/targets/status', 'trading_signal_item.dart'],
  ['trading_resources', 'Marketing/resource library', 'Hub resources/admin', 'Signed-in read; admin write', 'title, description, file_url/path, status, i18n', 'trading_resource_item.dart'],
  ['trading_recordings', 'Recording classes', 'Hub classes/admin/notifications', 'Signed-in read; admin write', 'title, video_url, download_url, materials, status', 'trading_recording_class.dart'],
  ['knowledge_centres', 'Knowledge centre items', 'Hub/admin/notifications', 'Signed-in read; admin write', 'title, description, file_path, image_url, i18n', 'knowledge_centre_item.dart'],
  ['trading_exam_questions', 'Daily/foundation exam bank', 'Exam/admin', 'Signed-in read if exams enabled; admin write', 'question, options, category, i18n', 'trading_exam_models.dart'],
  ['final_exam_questions', 'Final exam source questions', 'Final exam/admin', 'Signed-in read if exams enabled; admin write', 'question_type, category, options, translations', 'final_exam_mobile.dart'],
  ['final_exam_question_banks', 'Grouped final exam banks', 'Final exam/admin', 'Signed-in read; admin write', 'bank metadata and question sets', 'trading_repository.dart'],
  ['prop_firm_models', 'Prop firm rules/configuration', 'Prop firm/admin', 'Signed-in read; admin write', 'phases, targets, drawdown, status', 'prop_firm_screen.dart'],
  ['trading_competitions', 'Leaderboard competitions', 'Admin leaderboard', 'Signed-in read; admin write/delete', 'title, date range, prize, minimum_trades, status', 'admin_trading_operations.dart'],
  ['trading_appointment_slots', 'Appointment availability', 'Appointments/admin', 'Signed-in read if appointments enabled; admin create/delete; user booking update constrained', 'host_user_id, scheduled_start_at, status, appointment_id', 'trading_appointments_screen.dart'],
  ['appointment_hosts', 'Appointment host catalogue', 'Appointments/admin', 'Signed-in read; admin write', 'host_user_id, name, availability metadata', 'trading_repository.dart'],
  ['trading_appointments', 'Booked/requested appointments', 'Appointments/notifications', 'Participant/admin read; signed-in create with constraints; admin/participant cancel/update', 'requester_id, host_user_id, participant_ids, status, scheduled_start_at', 'functions/index.js; firestore.rules'],
  ['support_tickets', 'Ticket headers', 'Support/admin/notifications', 'Owner/admin read/update; owner create; admin delete', 'ticket_number, user_id, status, subject, last_message', 'support_tickets_screen.dart'],
  ['support_tickets/{ticketId}/messages', 'Ticket conversation messages', 'Support', 'Ticket owner/admin read/create with constraints', 'message, user_id, is_admin, attachments, created_at', 'support_tickets_screen.dart'],
  ['community_documents', 'Admin-published community documents', 'Admin content', 'Admin read/write only by rules', 'community, title, description, file_url, status', 'admin_trading_operations.dart'],
];

const platforms = [
  ['Android', 'Configured and release-focused', 'applicationId/namespace com.hcgaming.hc_trader; google-services.json; POST_NOTIFICATIONS, INTERNET, VIBRATE; network security allows cleartext only to localhost/10.0.2.2; appbundle releases present.', 'Primary mobile target; Play Store versionCode managed via pubspec version build number.'],
  ['Web', 'Configured', 'web/index.html, manifest.json and PWA icons exist; Firebase web config exists in firebase_options.dart.', 'PushNotificationService is not supported on web by service check; manifest description still generic.'],
  ['Windows', 'Configured', 'windows runner/CMake files exist; Firebase windows web-style config exists; AuthRepository has HTTP callable fallback for Windows.', 'Messaging service skips Windows; Cloud Functions plugin/channel issues were previously worked around with HTTP fallback in auth repository.'],
  ['iOS', 'Not confirmed / missing project folder', 'No ios/ folder found in repository scope.', 'Dependencies support iOS conceptually, but bundle id, entitlements, GoogleService-Info.plist and signing are absent.'],
  ['macOS', 'Not confirmed / missing project folder', 'No macos/ folder found.', 'Push service includes macOS in code, but platform project/config is absent.'],
  ['Linux', 'Not confirmed / missing project folder', 'No linux/ folder found.', 'No platform project/config found.'],
];

const localizationRows = [
  ['App static UI', 'Yes through AppLocalizations.text()', 'Yes through _zh/_zhOverrides maps', 'No external dynamic translation required', 'Some const Text/admin strings remain English and need review.'],
  ['Role names', 'AppRoles.name', 'AppRoles.localizedName via AppLocalizations.roleName', 'Static mapping', 'PowerShell output showed encoding issues, but source stores mappings in Dart.'],
  ['Dynamic Firestore content', 'Model fallback to English strings', 'i18n maps, *_zh fields, translations.zh and fallback helpers', 'Cloud Functions and tools can backfill/regenerate Chinese', 'Coverage depends on seeded/transformed Firestore data.'],
  ['Final exam content', 'Question snapshots and source maps', 'Bundled fallback and migrated i18n sidecars', 'Tools/tests support backfill/audit', 'Strongest covered area in tests.'],
  ['Admin/publisher dialogs', 'Partly yes', 'Partly through l10n.text, but many const Text strings remain', 'Not automatic for hardcoded labels', 'High-priority localization gap for Chinese admin users.'],
  ['Notification copy', 'Cloud Functions use English strings', 'No confirmed translated notification payloads', 'No dynamic notification localization found', 'Notification localization is a gap.'],
];

const featureChecklist = [
  'Firebase-only mobile startup',
  'Email and username sign-in',
  'Registration with role and referral validation',
  'Password reset and password change',
  'Persistent English/Simplified Chinese locale',
  'Role-aware HomeShell and More menu',
  'Dashboard performance overview',
  'Trading journal CRUD, import and attachments',
  'Capital and trading account management',
  'Trader onboarding / verification profile',
  'Trading position applications',
  'Trading Hub with eight content/workflow tabs',
  'Market analysis publishing and detail view',
  'Trading signal publishing and notifications',
  'Trading blog/news/resource/recording/knowledge content',
  'Password-gated recording/resource access',
  'Daily trading exam',
  'Final examination with localized fallback',
  'Risk calculator',
  'Statistics and analytics',
  'Prop firm progress and admin review',
  'Psychology score and warning module',
  'Trading plan local draft',
  'Readiness checklist',
  'Trading appointments and slot booking',
  'Support tickets with messages/attachments',
  'Recruitment/downline referral workspace',
  'Profile, avatar and security settings',
  'Notification topic preferences and token sync',
  'Admin overview dashboard',
  'Admin users/roles management',
  'Admin onboarding and position review',
  'Admin content publishing',
  'Admin generic resource CRUD/archive/restore',
  'Admin journal and exam result review',
  'Admin feature toggles',
  'Admin audit log and delete requests',
  'Admin performance, leaderboard and competition configuration',
  'Firestore security rules and Storage validation',
  'Cloud Functions registration, translation and notifications',
  'Translation/backfill/audit tooling',
];

const risks = [
  'Financial Department role was requested as an example but no Flutter role_id/name/module is implemented; functions/role_permissions.js explicitly leaves canInviteFinancialDepartment=false.',
  'AI assistant feature flag exists, but no Flutter AI assistant UI/client or Gemini integration was confirmed. Translation functions have been removed; Chinese content is manual.',
  'No ios/, macos/ or linux/ platform folders were found, so those platforms are not currently buildable from this project snapshot.',
  'No Flutter assets folder is declared in pubspec.yaml; only Material icons, Android launcher resources and Web PWA icons are confirmed.',
  'Many admin and publisher dialogs use hardcoded English const Text labels, so full Simplified Chinese coverage is not complete.',
  'Firestore indexes file is empty. Current queries may be valid, but future compound ordering/filtering can require indexes.',
  'The repository still contains a legacy ApiClient and cache/offline branches, but main.dart and README confirm firebaseEnabled=true and no Laravel runtime dependency.',
  'Document visual render QA could not be performed in this environment because Python, LibreOffice, Pandoc, Mermaid CLI and Graphviz were not available on PATH.',
];

const commands = [
  ['flutter analyze', 'Passed', 'No issues found.'],
  ['flutter test', 'Passed', '29 tests passed.'],
  ['dot -V', 'Failed/unavailable', 'Graphviz dot is not installed on PATH.'],
  ['mmdc --version', 'Failed/unavailable', 'Mermaid CLI is not installed on PATH.'],
  ['pandoc --version', 'Failed/unavailable', 'Pandoc is not installed on PATH.'],
  ['python / py / python3 --version', 'Failed/unavailable', 'Python is not installed on PATH.'],
  ['soffice --version', 'Failed/unavailable', 'LibreOffice is not installed on PATH.'],
];

function mdEscape(value) {
  return String(value).replace(/\|/g, '\\|').replace(/\n/g, '<br>');
}

function mdTable(headers, rows) {
  return [
    `| ${headers.map(mdEscape).join(' | ')} |`,
    `| ${headers.map(() => '---').join(' | ')} |`,
    ...rows.map((row) => `| ${row.map(mdEscape).join(' | ')} |`),
    '',
  ].join('\n');
}

function moduleRows() {
  return modules.map((item) => [
    item.name,
    item.purpose,
    item.screens,
    item.actions,
    item.data,
    item.backend,
    item.access,
    item.files,
    item.notes,
  ]);
}

function moduleDetailMarkdown() {
  return modules.map((item, index) => [
    `### ${index + 1}. ${item.name}`,
    '',
    `- Business purpose: ${item.purpose}`,
    `- User flow: ${item.flow}`,
    `- Main screens: ${item.screens}`,
    `- Main actions: ${item.actions}`,
    `- Data used: ${item.data}`,
    `- Firebase/API dependency: ${item.backend}`,
    `- Role access: ${item.access}`,
    `- Related files: ${item.files}`,
    `- Notes / risks: ${item.notes}`,
    '',
  ].join('\n')).join('\n');
}

const markdown = `# HC Trader App Functionality Documentation

Generated date: ${generatedDate}

Repository/project: mobile/hc_trader in C:\\xampp\\htdocs\\hcgaming

## 1. App Overview

HC Trader is a Flutter application for HC Gaming trading workflows. It is a Firebase-backed trading operations app for traders, lead clients, recruiters, signal providers, market analysts and administrators. It combines secure account registration, referral/downline management, trading journal workflows, learning content, signals, market analysis, exams, prop firm tracking, support, appointments and administration.

Target users include unauthenticated visitors registering or signing in, traders and lead clients using trading workflows, recruiters/leaders inviting downlines, signal providers and market analysts publishing content, and Super/Sub Administrators operating the platform.

Confirmed platform folders: Android, Web and Windows. Android is the primary release target and has multiple AAB files under releases/. iOS, macOS and Linux are not present in this repository snapshot.

Main business flows:

- Guest: select language, sign in, register, reset password.
- Trader/client: complete setup, journal trades, manage accounts/capital, consume hub content, take exams, book appointments, request support, track readiness and prop firm status.
- Publisher: publish market analysis or trading signals based on role.
- Recruiter/leader/trader: invite public registration roles and view downlines.
- Admin: manage users, modules, content, verification, positions, journals, exams, features, audit history, delete requests, performance and competitions.

## 2. High-Level Architecture

Overview diagram source: docs/app_functionality_map/app_overview.mmd

Overview diagram image: docs/app_functionality_map/app_overview.svg

- Frontend: Flutter Material 3 app with manual Navigator/MaterialPageRoute navigation, NavigationBar main tabs, TabBar sections, StatefulWidget/StreamBuilder/FutureBuilder state.
- Backend: Firebase Auth, Cloud Firestore, Firebase Storage, Firebase Messaging and Cloud Functions.
- Local storage: SharedPreferences via LocalAppStorage for non-sensitive drafts/preferences. ApiClient still uses FlutterSecureStorage for legacy token handling, but main.dart runs Firebase mode.
- External APIs: No translation API is used by Cloud Functions or tools; Chinese content is stored manually. No direct Gemini client was confirmed in Flutter.
- Platform integrations: Android notifications/local notifications, file picker, URL launcher, PDF/printing and Excel import.

## 3. User Role Matrix

${mdTable(['Role', 'Status', 'Accessible modules', 'Permissions', 'Restrictions', 'Related files'], roles.map((role) => [role.role, role.status, role.modules, role.permissions, role.restrictions, role.files]))}

## 4. Navigation and Screen Flow

- App startup: main.dart initializes Firebase and LocalAppStorage, then runs HCTraderApp.
- Auth gate: HCTraderApp uses a StreamBuilder over AuthRepository.authStateChanges().
- Guest flow: AuthScreen provides sign-in, registration, password reset and language switching.
- Authenticated flow: HomeShell exposes four main tabs: Home, Journal, Trading Hub and Accounts.
- More flow: profile avatar opens MoreScreen with role-aware feature cards and sign out.
- Trading Hub flow: eight tabs cover Market, Knowledge, Classes, Blogs, Signals, Exam, Positions and Resources.
- Admin flow: AdminOverviewScreen has 11 tabs: Overview, Modules, Users, Verification, Positions, Content, Performance, Leaderboard, Requests, Features and Audit.
- Publisher flow: non-admin market/signal roles can access AdminManagementScreen as a Publisher workspace when they have publishing permissions.

## 5. Functional Modules

${mdTable(['Module', 'Purpose', 'Screens', 'Main actions', 'Data used', 'Firebase/API dependency', 'Role access', 'Related files', 'Notes / risks'], moduleRows())}

${moduleDetailMarkdown()}

## 6. Screen Inventory

${mdTable(['Screen/view', 'Route/path', 'Purpose', 'Access role', 'Module', 'Source file'], screens)}

## 7. Firebase and Data Structure

Firebase services confirmed: Firebase Auth, Cloud Firestore, Firebase Storage, Firebase Cloud Functions, Firebase Messaging and Local Notifications integration.

${mdTable(['Collection/path', 'Purpose', 'Used by module', 'Read/write actions', 'Visible fields from code', 'Related files'], dataStructures)}

Cloud Functions confirmed:

- registerUserProfileAfterAuth: callable registration/profile/referral creation.
- manual bilingual audit/backfill: validates Firestore Chinese coverage without API calls.
- notifyTradingSignal, notifyMarketAnalysis, notifyRecording, notifyKnowledgeCentre.
- notifyNewAppointment, notifyAppointmentUpdate.
- notifySupportTicketUpdate.
- notifyDeleteRequestDecision, notifyDeleteRequestSubmitted.
- notifyPropFirmReview.
- notifyTradingExamResult, notifyFinalExamResult.

Storage paths confirmed by storage.rules:

- users/{userId}/... for owner/admin files.
- market_analyses/{userId}/... for market analysis images.
- knowledge_centre/{userId}/... for admin knowledge images/PDF.
- profile_images/{userId}/... for avatars.
- community_documents/{userId}/... for admin community files.
- recording_materials/{userId}/... for admin recording materials.
- support_tickets/{userId}/... for ticket attachments.
- admin_assets/{userId}/... for images, documents and MP4 managed content.

## 8. Localization Coverage

${mdTable(['Area/module', 'English support', 'Chinese support', 'Dynamic translation support', 'Missing translation risk'], localizationRows)}

## 9. Platform-Specific Notes

${mdTable(['Platform', 'Current support', 'Important config', 'Known issue/risk'], platforms)}

## 10. Technical Architecture

- State management: no Provider/BLoC/Riverpod package is used. State is handled with StatefulWidget, StreamBuilder, FutureBuilder, ChangeNotifier for locale and repository streams.
- Routing: no named route table found. Navigation is manual using MaterialPageRoute and nested TabBar/NavigationBar structures.
- Folder structure: app/ shell, core/ config/localization/network/security/storage, features/ screens/modules, models/, repositories/, services/, shared/, functions/, tools/, tests/ and platform folders.
- Service layer: TradingRepository and AuthRepository wrap Firebase operations; smaller services handle analytics, calculators, reports, PDF, prop firm, psychology, time and push notifications.
- Model layer: Dart model classes map Firestore/API-style data into typed objects.
- Theme: Material 3 ColorScheme.fromSeed with light/dark themes and custom card/input/navigation styling.
- Error handling: repositories translate Firebase/Auth/API errors into AuthFailure/ApiException; UI generally catches and shows SnackBar or error cards.
- Local cache: SharedPreferences stores non-sensitive local drafts and preferences. Legacy ApiClient supports secure token storage, but app startup currently uses Firebase mode.
- Main dependencies: firebase_core, firebase_auth, cloud_firestore, firebase_storage, cloud_functions, firebase_messaging, flutter_local_notifications, shared_preferences, flutter_secure_storage, http, intl, fl_chart, pdf, printing, file_picker, excel and url_launcher.

## 11. Full Feature List

${featureChecklist.map((item) => `- [x] ${item}`).join('\n')}

## 12. Testing and Quality

Existing tests:

- test/trading_services_test.dart covers calculators, trading time conversion, analytics, report rules, risk, prop firm, psychology, role permissions, localization helpers and exam parsing/fallbacks.
- test/final_exam_localisation_audit_test.dart validates strict Chinese coverage for generated final exam content.
- functions/test/referral_registration.test.js exists for registration/referral behavior in Cloud Functions.

Commands run:

${mdTable(['Command', 'Result', 'Notes'], commands)}

Manual sanity test checklist:

- Register a new Trader with and without referral code.
- Sign in with email and username.
- Complete profile/account verification, upload document and verify admin review.
- Create/edit/delete/import journal entries and attachments.
- View dashboard/statistics/report outputs after journal changes.
- Publish market analysis as Market Analyst and signal as Signal Provider.
- Complete daily exam and final exam in English and Chinese.
- Book, approve, cancel and reject appointments.
- Create support ticket with attachments and admin reply/close/reopen.
- Toggle feature flags and confirm More menu plus Firestore-read behavior.
- Sign out on Android/Windows and confirm AuthScreen returns without infinite loading.

Role-based testing checklist:

- Super Admin can access all admin tabs and assign all implemented roles.
- Sub Admin cannot assign/manage Super/Sub Admin accounts.
- Market Analyst can publish analysis but not signals or user management.
- Signal Provider can publish signals but not market analysis or user management.
- Trader can only self-manage own records and invite allowed public roles.
- Lead Client access should be manually validated because it is implemented but narrower in MoreScreen tradingAccess.

Localization testing checklist:

- Switch language from Auth and Settings.
- Review More, Home tabs, Trading Hub, exams and admin surfaces in Chinese.
- Validate dynamic Firestore records with title_i18n/description_i18n maps.
- Run audit_chinese_localisation.js before release if Firebase credentials are available.

Firebase testing checklist:

- Deploy/check firestore.rules and storage.rules in staging.
- Verify Storage file-size/content-type constraints.
- Verify notification topics/direct messages on Android.
- Confirm callable registration works on Android, Web and Windows fallback.
- Confirm empty firestore.indexes.json still supports all production queries.

Platform testing checklist:

- Android release AAB install, notification permission, sign-in, sign-out, file upload and deep external URLs.
- Web login/content browsing without push expectations.
- Windows sign-in/register fallback for callable functions and no push expectations.

## 13. Risks, Gaps and Unknowns

${risks.map((item) => `- ${item}`).join('\n')}

## 14. Appendix

Important folders:

- lib/app: app shell and auth gate.
- lib/core: localization, security, config, network and local storage.
- lib/features: screen-level modules.
- lib/models: Firestore/API model mappers.
- lib/repositories: AuthRepository and TradingRepository data boundaries.
- lib/services: calculations, analytics, reports, notifications and time utilities.
- functions: Cloud Functions for registration, translation and notifications.
- tools: Firebase REST helpers, seeding/backfill/audit scripts.
- android, web, windows: configured platforms.
- docs: existing implementation notes plus this generated functionality map.

Important files:

- pubspec.yaml: app metadata, version 1.6.6+22 and dependencies.
- firebase.json and .firebaserc: Firebase project hcgaming-31d5d, functions runtime nodejs22.
- firestore.rules and storage.rules: primary security boundaries.
- lib/firebase_options.dart: Firebase options for Android, Web and Windows.
- android/app/build.gradle.kts: Android package com.hcgaming.hc_trader and release signing hook.
- README.md: confirms Firebase-only mobile runtime.

Useful commands:

- flutter pub get
- flutter analyze
- flutter test
- flutter build appbundle --release
- firebase deploy --only firestore:rules,firestore:indexes --project hcgaming-31d5d
- firebase deploy --only storage --project hcgaming-31d5d
- firebase deploy --only functions --project hcgaming-31d5d
`;

const mmd = `flowchart LR
  subgraph Roles["User roles"]
    Guest["Guest / unauthenticated"]
    Trader["Trader / Lead Client"]
    Recruiter["Leader / Recruiter"]
    Publisher["Market Analyst / Signal Provider"]
    Admin["Super Admin / Sub Admin"]
  end

  subgraph App["Flutter HC Trader app"]
    Auth["AuthScreen\\nSign in / Register / Reset / Language"]
    Gate["AuthGate\\nFirebase auth stream + user profile"]
    Shell["HomeShell\\nHome | Journal | Trading Hub | Accounts"]
    More["Profile & More\\nTools | Exams | Support | Recruitment | Admin"]
  end

  subgraph TraderFlow["Trader / user flow"]
    Dashboard["Dashboard"]
    Journal["Trading journal\\nCRUD / import / attachments"]
    Hub["Trading Hub\\nMarket | Knowledge | Classes | Blogs | Signals | Exam | Positions | Resources"]
    Accounts["Accounts\\nVerification | Trading accounts | Capital | Position applications"]
    Toolkit["Toolkit\\nRisk | Statistics | Prop firm | Psychology | Trading plan"]
    Ready["Readiness checklist"]
    Appts["Appointments"]
    Support["Support tickets"]
  end

  subgraph AdminFlow["Administration flow"]
    AdminRoot["AdminOverviewScreen\\n11 tabs"]
    Users["Users / Roles"]
    Verify["Verification / KYC review"]
    AdminContent["Content / Modules CRUD"]
    Reviews["Journals / Exams / Delete requests"]
    Ops["Performance / Prop firm / Leaderboard / Competitions"]
    Features["Feature toggles / Audit"]
  end

  subgraph Backend["Firebase and services"]
    FAuth["Firebase Auth"]
    Firestore["Cloud Firestore\\nusers, journals, content, exams, tickets, appointments"]
    Storage["Firebase Storage\\nprofile, support, admin assets, documents"]
    Functions["Cloud Functions\\nregistration and notifications"]
    Messaging["FCM + local notifications"]
    Local["SharedPreferences\\nlocale, drafts, preferences"]
    Translate["Manual zh fields\\nno translation API"]
    Legacy["Legacy ApiClient\\nnot used at runtime in main.dart"]
  end

  Guest --> Auth --> Gate
  Gate -->|no user| Auth
  Gate -->|user profile| Shell
  Shell --> Dashboard
  Shell --> Journal
  Shell --> Hub
  Shell --> Accounts
  Shell --> More
  More --> Toolkit
  More --> Ready
  More --> Appts
  More --> Support
  More --> AdminRoot
  Recruiter --> More
  Recruiter -->|invite / downline| Firestore
  Publisher --> Hub
  Publisher --> AdminContent
  Admin --> AdminRoot
  AdminRoot --> Users
  AdminRoot --> Verify
  AdminRoot --> AdminContent
  AdminRoot --> Reviews
  AdminRoot --> Ops
  AdminRoot --> Features
  Journal --> Firestore
  Journal --> Storage
  Accounts --> Firestore
  Accounts --> Storage
  Hub --> Firestore
  Toolkit --> Local
  Ready --> Firestore
  Appts --> Firestore
  Support --> Firestore
  Support --> Storage
  Auth --> FAuth
  Auth --> Functions
  Gate --> Firestore
  Functions --> Firestore
  Functions --> Messaging
  Functions --> Translate
  Messaging --> Trader
  Messaging --> Publisher
  Messaging --> Admin
  More --> Local
  Legacy -. "present but Firebase mode is active" .-> App
`;

function svgBox(x, y, w, h, title, lines, fill, stroke = '#243746') {
  const text = [
    `<text x="${x + 14}" y="${y + 28}" class="title">${escapeXml(title)}</text>`,
    ...lines.map((line, index) => `<text x="${x + 14}" y="${y + 52 + index * 20}" class="body">${escapeXml(line)}</text>`),
  ].join('\n');
  return `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="12" fill="${fill}" stroke="${stroke}" stroke-width="2"/>\n${text}`;
}

function svgArrow(x1, y1, x2, y2, label = '') {
  const midX = (x1 + x2) / 2;
  const midY = (y1 + y2) / 2 - 8;
  return `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="#455A64" stroke-width="2.4" marker-end="url(#arrow)"/>` +
    (label ? `\n<text x="${midX}" y="${midY}" class="label" text-anchor="middle">${escapeXml(label)}</text>` : '');
}

function escapeXml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}

const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="1800" height="1180" viewBox="0 0 1800 1180" role="img" aria-label="HC Trader app functionality overview">
  <defs>
    <marker id="arrow" markerWidth="12" markerHeight="12" refX="10" refY="4" orient="auto" markerUnits="strokeWidth">
      <path d="M0,0 L10,4 L0,8 z" fill="#455A64"/>
    </marker>
    <style>
      .heading { font: 700 30px Arial, sans-serif; fill: #123; }
      .lane { font: 700 20px Arial, sans-serif; fill: #234; }
      .title { font: 700 17px Arial, sans-serif; fill: #0E1A22; }
      .body { font: 14px Arial, sans-serif; fill: #25313A; }
      .label { font: 12px Arial, sans-serif; fill: #455A64; }
      .small { font: 13px Arial, sans-serif; fill: #455A64; }
    </style>
  </defs>
  <rect width="1800" height="1180" fill="#F7FAFC"/>
  <text x="50" y="56" class="heading">HC Trader - Functional Map</text>
  <text x="50" y="82" class="small">Roles, Flutter navigation, major modules, Firebase services, admin flow, trader flow, recruitment, localization and data movement.</text>

  <text x="70" y="130" class="lane">User Roles</text>
  ${svgBox(50, 150, 300, 92, 'Guest', ['Sign in, register, reset password', 'Read public registration roles'], '#E3F2FD')}
  ${svgBox(50, 262, 300, 112, 'Trader / Lead Client', ['Journal, hub, accounts, tools', 'Exams, support, appointments', 'Own data only'], '#E8F5E9')}
  ${svgBox(50, 394, 300, 92, 'Leader / Recruiter', ['Invite public roles', 'View downline workspace'], '#FFF8E1')}
  ${svgBox(50, 506, 300, 112, 'Publisher Roles', ['Market Analyst publishes analysis', 'Signal Provider publishes signals', 'Limited admin workspace'], '#F3E5F5')}
  ${svgBox(50, 638, 300, 112, 'Super / Sub Admin', ['Operate users, content, reviews', 'Feature flags, audit, reports'], '#FFEBEE')}

  <text x="465" y="130" class="lane">Flutter Navigation</text>
  ${svgBox(430, 150, 330, 95, 'AuthScreen', ['Language, sign in, registration', 'Password reset'], '#E1F5FE')}
  ${svgBox(430, 280, 330, 95, 'AuthGate', ['Firebase auth stream', 'Loads users/{uid} profile'], '#E1F5FE')}
  ${svgBox(430, 410, 330, 118, 'HomeShell', ['Bottom tabs:', 'Home, Journal, Trading Hub, Accounts', 'Profile avatar opens More'], '#E0F2F1')}
  ${svgBox(430, 570, 330, 180, 'Profile & More', ['Final exam, toolkit, readiness', 'appointments, support, recruitment', 'profile, settings, admin', 'role and feature gated'], '#F1F8E9')}

  <text x="875" y="130" class="lane">Trader / User Modules</text>
  ${svgBox(830, 150, 330, 112, 'Dashboard', ['Trading performance summary', 'Journal/capital analytics'], '#FFFFFF')}
  ${svgBox(830, 282, 330, 132, 'Journal + Accounts', ['Journal CRUD/import/attachments', 'Verification/KYC profile', 'Trading accounts and capital'], '#FFFFFF')}
  ${svgBox(830, 434, 330, 172, 'Trading Hub', ['Market, knowledge, classes, blogs', 'Signals, daily exam, positions', 'Resources and detail pages', 'Publisher actions for eligible roles'], '#FFFFFF')}
  ${svgBox(830, 626, 330, 152, 'Tools and Workflow', ['Risk calculator, statistics, reports', 'Prop firm, psychology, plan', 'Readiness checklist', 'Appointments and support'], '#FFFFFF')}

  <text x="1265" y="130" class="lane">Admin Flow</text>
  ${svgBox(1220, 150, 300, 132, 'AdminOverviewScreen', ['11 tabs: overview, modules, users', 'verification, positions, content', 'performance, leaderboard, requests', 'features, audit'], '#FFF3E0')}
  ${svgBox(1220, 302, 300, 132, 'AdminManagementScreen', ['Resource CRUD/archive/restore', 'Signals, analysis, exams, props', 'tickets, appointments, journals', 'delete requests'], '#FFF3E0')}
  ${svgBox(1220, 454, 300, 132, 'Operations', ['Onboarding and position review', 'Prop firm review', 'Leaderboard and competitions', 'Audit trail'], '#FFF3E0')}

  <text x="1518" y="130" class="lane">Firebase / Services</text>
  ${svgBox(1505, 150, 245, 92, 'Firebase Auth', ['email/username session', 'password reset/change'], '#ECEFF1')}
  ${svgBox(1505, 262, 245, 132, 'Cloud Firestore', ['users, journals, content', 'exams, tickets, appointments', 'roles and feature flags'], '#ECEFF1')}
  ${svgBox(1505, 414, 245, 92, 'Firebase Storage', ['profile/support/admin files', 'validated by storage.rules'], '#ECEFF1')}
  ${svgBox(1505, 526, 245, 112, 'Cloud Functions', ['registration/referrals', 'translations', 'notifications'], '#ECEFF1')}
  ${svgBox(1505, 658, 245, 112, 'Messaging + Local', ['FCM topics/direct messages', 'SharedPreferences drafts', 'locale/preferences'], '#ECEFF1')}

  <text x="70" y="845" class="lane">Cross-cutting Flows</text>
  ${svgBox(50, 870, 540, 110, 'Recruitment / referral / downline', ['Registration validates referral code and role.', 'Cloud Function writes users, usernames, referral_codes,', 'referral_relationships and users/{uid}/downlines.'], '#FAFAFA')}
  ${svgBox(630, 870, 540, 110, 'Localization / manual bilingual content', ['Static labels use AppLocalizations.', 'Dynamic Firestore text uses localizedValue/model helpers.', 'Functions do not translate; tools validate manual Chinese fields.'], '#FAFAFA')}
  ${svgBox(1210, 870, 540, 110, 'Security boundary', ['Firestore/Storage rules enforce ownership, admin roles,', 'feature flags, publisher roles, appointment participants,', 'and file size/content-type limits.'], '#FAFAFA')}

  ${svgArrow(350, 196, 430, 196)}
  ${svgArrow(595, 245, 595, 280)}
  ${svgArrow(595, 375, 595, 410)}
  ${svgArrow(760, 470, 830, 205)}
  ${svgArrow(760, 470, 830, 348)}
  ${svgArrow(760, 470, 830, 520)}
  ${svgArrow(595, 528, 595, 570)}
  ${svgArrow(760, 660, 830, 702)}
  ${svgArrow(1160, 348, 1220, 368)}
  <path d="M995 778 L995 815 L1628 815 L1628 770" fill="none" stroke="#455A64" stroke-width="2.4" marker-end="url(#arrow)"/>
  <text x="1310" y="807" class="label" text-anchor="middle">module data, files, functions and notifications</text>
  <path d="M1370 586 L1370 815" fill="none" stroke="#455A64" stroke-width="2.4" marker-end="url(#arrow)"/>
  ${svgArrow(590, 925, 630, 925)}
  ${svgArrow(1170, 925, 1210, 925)}
</svg>
`;

function writeFiles() {
  fs.mkdirSync(outDir, { recursive: true });
  fs.writeFileSync(path.join(outDir, 'App_Functionality_Documentation.md'), markdown, 'utf8');
  fs.writeFileSync(path.join(outDir, 'app_overview.mmd'), mmd, 'utf8');
  fs.writeFileSync(path.join(outDir, 'app_overview.svg'), svg, 'utf8');
}

function para(text, style = 'Normal') {
  const styleXml = style === 'Normal' ? '' : `<w:pPr><w:pStyle w:val="${style}"/></w:pPr>`;
  const runs = String(text).split('\n').map((line) => `<w:r><w:t xml:space="preserve">${escapeXml(line)}</w:t></w:r>`).join('<w:r><w:br/></w:r>');
  return `<w:p>${styleXml}${runs}</w:p>`;
}

function bullet(text) {
  return `<w:p><w:pPr><w:pStyle w:val="Bullet"/></w:pPr><w:r><w:t xml:space="preserve">${escapeXml(text)}</w:t></w:r></w:p>`;
}

function pageBreak() {
  return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
}

function table(headers, rows) {
  const tr = (cells, header = false) => `<w:tr>${cells.map((cell) => `<w:tc><w:tcPr><w:tcW w:w="2400" w:type="dxa"/></w:tcPr>${para(cell, header ? 'TableHeader' : 'TableText')}</w:tc>`).join('')}</w:tr>`;
  return `<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="B0BEC5"/><w:left w:val="single" w:sz="4" w:color="B0BEC5"/><w:bottom w:val="single" w:sz="4" w:color="B0BEC5"/><w:right w:val="single" w:sz="4" w:color="B0BEC5"/><w:insideH w:val="single" w:sz="4" w:color="CFD8DC"/><w:insideV w:val="single" w:sz="4" w:color="CFD8DC"/></w:tblBorders></w:tblPr>${tr(headers, true)}${rows.map((row) => tr(row)).join('')}</w:tbl>`;
}

function docSection(title, body) {
  return [para(title, 'Heading1'), ...body].join('');
}

function buildDocx() {
  const body = [
    para('HC Trader', 'Title'),
    para('Application Functionality Documentation', 'Subtitle'),
    para(`Generated date: ${generatedDate}`),
    para('Repository/project: mobile/hc_trader'),
    para('Prepared from static project scan, source inspection, Firebase rules/configuration, and local Flutter checks.'),
    pageBreak(),
    docSection('Executive Summary', [
      para('HC Trader is a Firebase-backed Flutter app for trading operations, trader education, journal tracking, prop-firm workflows, referrals, appointments, support and administration. It serves traders/clients, recruiters/leaders, publishers and administrators.'),
      para('The business value is a single secure mobile workspace for onboarding, trading discipline, education, signal/analysis publishing, support workflows and admin governance.'),
    ]),
    docSection('High-Level Architecture', [
      para('See app_overview.svg and app_overview.mmd in this folder for the full role/navigation/backend diagram.'),
      bullet('Frontend: Flutter Material 3, manual Navigator/MaterialPageRoute, NavigationBar and TabBar structures.'),
      bullet('Backend: Firebase Auth, Firestore, Storage, Functions and Messaging.'),
      bullet('Local: SharedPreferences for non-sensitive drafts/preferences; legacy ApiClient remains but main.dart runs Firebase mode.'),
      bullet('External: no translation API is used for localization tooling/functions; no confirmed Gemini client in Flutter.'),
    ]),
    docSection('User Role Matrix', [
      table(['Role', 'Status', 'Accessible modules', 'Permissions', 'Restrictions', 'Related files'], roles.map((role) => [role.role, role.status, role.modules, role.permissions, role.restrictions, role.files])),
    ]),
    docSection('Navigation and Screen Flow', [
      bullet('Startup: main.dart initializes Firebase, LocalAppStorage and locale, then HCTraderApp renders AuthGate.'),
      bullet('Guest: AuthScreen supports sign in, registration, password reset and language selection.'),
      bullet('Authenticated: HomeShell exposes Home, Journal, Trading Hub and Accounts tabs.'),
      bullet('More: Profile avatar opens role-aware cards for exams, tools, readiness, appointments, support, recruitment, profile, settings and admin.'),
      bullet('Trading Hub: eight tabs for Market, Knowledge, Classes, Blogs, Signals, Exam, Positions and Resources.'),
      bullet('Admin: AdminOverviewScreen exposes 11 tabs covering platform operations, resource management and governance.'),
    ]),
    docSection('Module-by-Module Explanation', [
      table(['Module', 'Purpose', 'Screens', 'Main actions', 'Data', 'Backend', 'Role access', 'Files', 'Notes'], moduleRows()),
      ...modules.flatMap((item, index) => [
        para(`${index + 1}. ${item.name}`, 'Heading2'),
        bullet(`Business purpose: ${item.purpose}`),
        bullet(`User flow: ${item.flow}`),
        bullet(`Screens: ${item.screens}`),
        bullet(`Actions: ${item.actions}`),
        bullet(`Data: ${item.data}`),
        bullet(`Backend: ${item.backend}`),
        bullet(`Role access: ${item.access}`),
        bullet(`Files: ${item.files}`),
        bullet(`Notes/risks: ${item.notes}`),
      ]),
    ]),
    docSection('Screen Inventory', [
      table(['Screen/view', 'Route/path', 'Purpose', 'Access role', 'Module', 'Source file'], screens),
    ]),
    docSection('Firebase and Data Structure', [
      para('Firebase services confirmed: Auth, Cloud Firestore, Storage, Cloud Functions, Messaging and local notification integration.'),
      table(['Collection/path', 'Purpose', 'Used by module', 'Read/write actions', 'Visible fields', 'Related files'], dataStructures),
      para('Cloud Functions include registration, translation triggers/regeneration, trading signal/market/recording/knowledge notifications, appointment updates, support ticket updates, delete request notifications, prop-firm review updates and exam result notifications.'),
      para('Storage rules validate owner/admin access and file-size/content-type limits for users, market_analyses, knowledge_centre, profile_images, community_documents, recording_materials, support_tickets and admin_assets.'),
    ]),
    docSection('Localization Coverage', [
      table(['Area/module', 'English support', 'Chinese support', 'Dynamic translation support', 'Missing translation risk'], localizationRows),
    ]),
    docSection('Platform-Specific Notes', [
      table(['Platform', 'Current support', 'Important config', 'Known issue/risk'], platforms),
    ]),
    docSection('Technical Architecture', [
      bullet('State management uses StatefulWidget, StreamBuilder, FutureBuilder and ChangeNotifier for locale. No Provider/BLoC/Riverpod dependency was found.'),
      bullet('Routing uses manual MaterialPageRoute pushes instead of a named route registry.'),
      bullet('Repositories provide the main data boundary; services contain pure calculations, reports, notifications and utilities.'),
      bullet('Theme uses Material 3 ColorScheme.fromSeed with light/dark modes.'),
      bullet('Errors are surfaced through AuthFailure/ApiException, error widgets and SnackBars.'),
      bullet('Dependencies include Firebase packages, intl, fl_chart, pdf, printing, url_launcher, http, secure/shared storage, file_picker and excel.'),
    ]),
    docSection('Full Feature List', featureChecklist.map((item) => bullet(`[x] ${item}`))),
    docSection('Testing and Quality', [
      para('Existing tests cover service calculations, role permissions, localization and exam parsing/fallbacks. Local checks passed.'),
      table(['Command', 'Result', 'Notes'], commands),
      para('Recommended manual testing: registration/referral, sign-in/sign-out, verification, journal import/attachments, dashboard/statistics, publisher workflows, exams, appointments, support, feature toggles, role restrictions and Android release installation.'),
    ]),
    docSection('Risks, Gaps and Unknowns', risks.map((item) => bullet(item))),
    docSection('Appendix', [
      bullet('Important folders: lib/app, lib/core, lib/features, lib/models, lib/repositories, lib/services, functions, tools, android, web, windows, test and docs.'),
      bullet('Important files: pubspec.yaml, firebase.json, .firebaserc, firestore.rules, storage.rules, lib/firebase_options.dart, android/app/build.gradle.kts and README.md.'),
      bullet('Useful commands: flutter pub get; flutter analyze; flutter test; flutter build appbundle --release; firebase deploy --only firestore:rules,firestore:indexes; firebase deploy --only storage; firebase deploy --only functions.'),
    ]),
    `<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>`,
  ].join('');

  const documentXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:w10="urn:schemas-microsoft-com:office:word" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml" xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup" xmlns:wpi="http://schemas.microsoft.com/office/word/2010/wordprocessingInk" xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml" xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape" mc:Ignorable="w14 wp14"><w:body>${body}</w:body></w:document>`;

  const stylesXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:sz w:val="20"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:qFormat/><w:pPr><w:spacing w:after="240"/></w:pPr><w:rPr><w:rFonts w:ascii="Aptos Display" w:hAnsi="Aptos Display"/><w:b/><w:color w:val="00695C"/><w:sz w:val="54"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:qFormat/><w:pPr><w:spacing w:after="360"/></w:pPr><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:color w:val="455A64"/><w:sz w:val="28"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:before="300" w:after="160"/></w:pPr><w:rPr><w:b/><w:color w:val="004D40"/><w:sz w:val="30"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:before="220" w:after="100"/></w:pPr><w:rPr><w:b/><w:color w:val="1B5E8C"/><w:sz w:val="24"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Bullet"><w:name w:val="Bullet"/><w:basedOn w:val="Normal"/><w:pPr><w:ind w:left="360" w:hanging="180"/></w:pPr><w:rPr><w:sz w:val="19"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="TableHeader"><w:name w:val="Table Header"/><w:basedOn w:val="Normal"/><w:rPr><w:b/><w:color w:val="FFFFFF"/><w:sz w:val="17"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="TableText"><w:name w:val="Table Text"/><w:basedOn w:val="Normal"/><w:rPr><w:sz w:val="16"/></w:rPr></w:style>
  <w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:color="B0BEC5"/><w:left w:val="single" w:sz="4" w:color="B0BEC5"/><w:bottom w:val="single" w:sz="4" w:color="B0BEC5"/><w:right w:val="single" w:sz="4" w:color="B0BEC5"/><w:insideH w:val="single" w:sz="4" w:color="CFD8DC"/><w:insideV w:val="single" w:sz="4" w:color="CFD8DC"/></w:tblBorders></w:tblPr></w:style>
</w:styles>`;

  const contentTypes = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>`;
  const rels = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>`;
  const documentRels = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>`;
  const core = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>HC Trader Application Functionality Documentation</dc:title><dc:creator>Codex</dc:creator><cp:lastModifiedBy>Codex</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">${generatedDate}T00:00:00Z</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">${generatedDate}T00:00:00Z</dcterms:modified></cp:coreProperties>`;
  const app = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Codex OpenXML Generator</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop><Company>HC Gaming</Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>1.0</AppVersion></Properties>`;

  const docxPath = path.join(outDir, 'App_Functionality_Documentation.docx');
  writeZip(docxPath, [
    ['[Content_Types].xml', contentTypes],
    ['_rels/.rels', rels],
    ['word/document.xml', documentXml],
    ['word/styles.xml', stylesXml],
    ['word/_rels/document.xml.rels', documentRels],
    ['docProps/core.xml', core],
    ['docProps/app.xml', app],
  ]);
}

const crcTable = (() => {
  const table = new Uint32Array(256);
  for (let i = 0; i < 256; i += 1) {
    let c = i;
    for (let k = 0; k < 8; k += 1) {
      c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
    }
    table[i] = c >>> 0;
  }
  return table;
})();

function crc32(buffer) {
  let crc = 0xFFFFFFFF;
  for (const byte of buffer) {
    crc = crcTable[(crc ^ byte) & 0xFF] ^ (crc >>> 8);
  }
  return (crc ^ 0xFFFFFFFF) >>> 0;
}

function dosTimeDate() {
  const date = new Date(`${generatedDate}T00:00:00Z`);
  const dosTime = (date.getUTCHours() << 11) | (date.getUTCMinutes() << 5) | Math.floor(date.getUTCSeconds() / 2);
  const dosDate = ((date.getUTCFullYear() - 1980) << 9) | ((date.getUTCMonth() + 1) << 5) | date.getUTCDate();
  return { dosTime, dosDate };
}

function header(size) {
  return Buffer.alloc(size);
}

function writeZip(filePath, entries) {
  const { dosTime, dosDate } = dosTimeDate();
  const localParts = [];
  const centralParts = [];
  let offset = 0;

  for (const [name, content] of entries) {
    const nameBuffer = Buffer.from(name, 'utf8');
    const data = Buffer.from(content, 'utf8');
    const compressed = zlib.deflateRawSync(data);
    const crc = crc32(data);

    const local = header(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0x0800, 6);
    local.writeUInt16LE(8, 8);
    local.writeUInt16LE(dosTime, 10);
    local.writeUInt16LE(dosDate, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(compressed.length, 18);
    local.writeUInt32LE(data.length, 22);
    local.writeUInt16LE(nameBuffer.length, 26);
    local.writeUInt16LE(0, 28);
    localParts.push(local, nameBuffer, compressed);

    const central = header(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(20, 4);
    central.writeUInt16LE(20, 6);
    central.writeUInt16LE(0x0800, 8);
    central.writeUInt16LE(8, 10);
    central.writeUInt16LE(dosTime, 12);
    central.writeUInt16LE(dosDate, 14);
    central.writeUInt32LE(crc, 16);
    central.writeUInt32LE(compressed.length, 20);
    central.writeUInt32LE(data.length, 24);
    central.writeUInt16LE(nameBuffer.length, 28);
    central.writeUInt16LE(0, 30);
    central.writeUInt16LE(0, 32);
    central.writeUInt16LE(0, 34);
    central.writeUInt16LE(0, 36);
    central.writeUInt32LE(0, 38);
    central.writeUInt32LE(offset, 42);
    centralParts.push(central, nameBuffer);

    offset += local.length + nameBuffer.length + compressed.length;
  }

  const centralOffset = offset;
  const centralBuffer = Buffer.concat(centralParts);
  const end = header(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(0, 4);
  end.writeUInt16LE(0, 6);
  end.writeUInt16LE(entries.length, 8);
  end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(centralBuffer.length, 12);
  end.writeUInt32LE(centralOffset, 16);
  end.writeUInt16LE(0, 20);

  fs.writeFileSync(filePath, Buffer.concat([...localParts, centralBuffer, end]));
}

writeFiles();
buildDocx();

const summary = {
  generatedDate,
  screensFound: screens.length,
  modulesFound: modules.length,
  implementedRolesFound: roles.filter((role) => role.status === 'Implemented').length,
  firestoreCollectionPathsDocumented: dataStructures.length,
  files: [
    'app_overview.mmd',
    'app_overview.svg',
    'App_Functionality_Documentation.md',
    'App_Functionality_Documentation.docx',
  ],
};
fs.writeFileSync(path.join(outDir, 'generation_summary.json'), `${JSON.stringify(summary, null, 2)}\n`, 'utf8');
console.log(JSON.stringify(summary, null, 2));

