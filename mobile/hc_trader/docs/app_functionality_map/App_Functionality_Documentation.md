# HC Trader App Functionality Documentation

Generated date: 2026-06-28

Repository/project: mobile/hc_trader in C:\xampp\htdocs\hcgaming

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

| Role | Status | Accessible modules | Permissions | Restrictions | Related files |
| --- | --- | --- | --- | --- | --- |
| Guest / unauthenticated user | Shell access only | Auth screen, registration role lookup, public registration role metadata | Can sign in, register, request password reset, and read registration_roles where allowed. | Cannot access HomeShell, Firestore protected content, admin modules, tickets, journal, exams, or trading hub. | lib/features/auth/auth_screen.dart; lib/repositories/auth_repository.dart; firestore.rules |
| Super Administrator (role_id 1) | Implemented | All admin tabs, all More menu modules, user management, content management, feature toggles, audit, delete review | Full AdminPermission access; can assign every implemented role including Super Admin and Sub Admin. | Business restrictions are mostly policy-driven; Firestore still requires authenticated user profile. | lib/core/security/app_roles.dart; lib/core/security/admin_permissions.dart; firestore.rules |
| Sub Administrator (role_id 2) | Implemented | Admin dashboard, modules, users, onboarding, positions, content, performance, leaderboard, feature/audit/delete queues | Treated as admin in app permission extension; role matrix blocks managing existing Super/Sub Admin users. | Cannot assign role_id 1 or 2; cannot manage existing admin users per matrix/rules. | lib/core/security/app_role_permissions.dart; firestore.rules; functions/role_permissions.js |
| Signal Provider (role_id 201) | Implemented | Publisher workspace, Trading Signals, Trading Hub, Support, Recruitment if assignable matrix permits | Can view/create/edit own trading signals; can invite Trader and Lead Client. | Cannot manage users, feature toggles, market analysis, audits, or delete reviews. | lib/models/app_user.dart; lib/core/security/admin_permissions.dart; firestore.rules |
| Senior Signal Provider (role_id 202) | Implemented | Same signal publishing workspace as Signal Provider | Can view/create/edit own trading signals; can invite Trader and Lead Client. | No broad admin management permissions confirmed. | lib/core/security/app_roles.dart; lib/core/security/admin_permissions.dart |
| Market Analyst (role_id 501) | Implemented | Publisher workspace, Market Analysis, Knowledge/News view, Trading Hub, Support | Can view/create/edit own market analysis and request delete; can invite Trader and Lead Client. | Cannot administer users/features; delete approval remains Super/Sub Admin. | lib/models/app_user.dart; lib/features/hub/publisher_sheets.dart; firestore.rules |
| Signal Provider Management (role_id 502) | Implemented | Trading Signals and signal provider management | Can view/create/edit/delete trading signals and manage signal providers; can invite Trader and Lead Client. | No general user/feature/audit administration confirmed. | lib/core/security/admin_permissions.dart; lib/core/security/app_role_permissions.dart |
| Lead Client (role_id 700) | Implemented | Standard client/trader-facing modules where feature flags allow | Public registration target role; readable label exists; can be invited through referral relationships. | Not in tradingAccess set for readiness/appointments unless separately granted by app logic; no publisher/admin permissions. | lib/core/security/app_roles.dart; firestore.rules |
| Trader (role_id 750) | Implemented | Dashboard, Journal, Trading Hub, Accounts, Tools, Final Exam, Readiness, Appointments, Support, Recruitment, Profile, Settings | Can maintain own data, submit verification, create journal/capital/accounts, book appointments, submit exams, invite Trader/Lead Client. | No admin/publishing permissions except trader self-service flows. | lib/app/home_shell.dart; lib/features/more/more_screen.dart; lib/core/security/app_role_permissions.dart |
| Trading Leadership (role_id 760) | Implemented | Trader-facing modules plus recruitment/downline invitation | Can invite Recruiter, Trader and Lead Client. | No broad admin management permissions confirmed. | lib/core/security/app_role_permissions.dart; functions/role_permissions.js |
| Trading Recruiter (role_id 770) | Implemented | Recruitment/invite, trader-facing modules, Support | Can invite Trader and Lead Client. | No admin management, publishing, or feature-toggle access confirmed. | lib/features/recruitment/recruitment_screen.dart; lib/core/security/app_role_permissions.dart |
| Financial Department | Not confirmed / inferred from code | No implemented Flutter role found. | functions/role_permissions.js has canInviteFinancialDepartment=false, but no role_id/name is present. | Treat as not implemented until a role id and screens are added. | functions/role_permissions.js |


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

| Module | Purpose | Screens | Main actions | Data used | Firebase/API dependency | Role access | Related files | Notes / risks |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| App bootstrap, theme and auth gate | Initialize Firebase, local storage, locale controller and render either AuthScreen or HomeShell. | HCTraderApp, _AuthGate, HomeShell | Load current user, listen to auth stream, configure optional push notifications after first frame. | FirebaseAuth current user; users/{uid} profile; SharedPreferences locale/preferences. | Firebase Core/Auth/Firestore; PushNotificationService. | All users after app launch; authenticated shell only after user profile resolves. | lib/main.dart; lib/app/hc_trader_app.dart; lib/app/home_shell.dart | Current main.dart forces firebaseEnabled=true; README says no Laravel runtime dependency. |
| Authentication and session management | Secure sign in, registration, password reset, password change and sign out. | AuthScreen, ProfileScreen password dialog, MoreScreen sign-out button | Email/username login, create Firebase account, call validated profile Cloud Function, reset password, verify password, update password, sign out. | Firebase Auth user; users; usernames; registration_roles. | Firebase Auth; Firestore; Cloud Function registerUserProfileAfterAuth; HTTP callable fallback on Windows. | Guest and authenticated user self-service. | lib/features/auth/auth_screen.dart; lib/repositories/auth_repository.dart; functions/registration_functions.js | Sign-out is designed to clear local app session before asynchronous Firebase cleanup to avoid stuck loading. |
| Registration, referral and downline system | Create user profiles with role validation and referral/downline relationships. | AuthScreen registration tab; RecruitmentScreen | Register, generate/read referral code, show downlines and invite permitted roles. | registration_roles; referral_codes; referral_relationships; recruitments; users/{uid}/downlines; users. | Trusted callable functions; Firestore transactions; role permission mirror in functions/role_permissions.js. | Registration remains unapproved until Apply to Access review. | lib/repositories/auth_repository.dart; lib/features/recruitment/recruitment_screen.dart; functions/registration_functions.js | Privileged roles require trusted backend creation and access approval. |
| Dashboard | Give traders an at-a-glance view of performance and trading state. | DashboardScreen | View summary metrics, recent performance and status cards. | trading_journals; capitals; trading_accounts; computed TradingSummary. | Firestore user subcollections through TradingRepository. | Authenticated users. | lib/features/dashboard/dashboard_screen.dart; lib/shared/performance_charts.dart; lib/services/trading_analytics.dart | Exact visual widgets depend on stream availability and cached data. |
| Trading journal | Record, import, analyze and administratively review trades. | JournalScreen, JournalFormScreen, AdminJournalReviewScreen | Create/edit/delete journal, attach images/documents, import CSV/XLSX, add admin notes. | users/{uid}/trading_journals; Storage users/{uid}/... journal attachments. | Firestore; Firebase Storage; local queued drafts in SharedPreferences; excel/file_picker for imports. | Owner or admin; admin review through collection-group rules. | lib/features/journal/*; lib/repositories/trading_repository.dart; lib/models/trading_journal_entry.dart | Tests cover calculation, analytics and reporting around journal data. |
| Accounts, verification and capital | Manage trader profile verification, broker/trading accounts, capital transactions and position applications. | AccountScreen, Admin Verification tab, Admin Positions tab | Save account profile, upload onboarding document, add/update/delete trading accounts, capital transactions, submit position applications, admin approve/reject. | users/{uid}/trader_onboarding/profile; onboarding_documents; trading_accounts; capitals; trading_position_applications. | Firestore and Storage user paths; admin collection-group reads. | Owner self-service; admin review. | lib/features/account/account_screen.dart; lib/models/trader_account_profile.dart; lib/models/trading_account.dart | This is the closest equivalent to KYC/verification in the Flutter app. |
| Trading hub | Central workspace for market content, learning, signals, exams, position applications and resources. | TradingHubScreen plus Market, Knowledge, Classes, Blogs, Signals, Exam, Positions, Resources tabs and detail screens. | Read content, open details, publish signals/analysis for eligible roles, unlock protected recordings/resources, take daily exam, apply for positions. | market_analyses; trading_signals; trading_blogs; trading_resources; trading_recordings; knowledge_centres; trading_exam_questions; trading_position_applications. | Firestore streams, Storage URLs, AuthRepository password verification, url_launcher for external resources. | Signed-in users; publishing restricted by role. | lib/features/hub/trading_hub_screen.dart; lib/features/hub/publisher_sheets.dart | Feature flags in app_configuration/modules can restrict content collections through Firestore rules and More menu conditions. |
| Trading signals | Publish and consume signal cards with pair, action, entry, SL/TP and status. | Signals tab, signal publisher sheet, Admin Trading Signals resource | View, publish, edit/delete where authorized, notify users. | trading_signals fields include signal_title/title, trading_pair, immediate_action, entry_price, targets, disclaimer, risk_level, status, created_by. | Firestore; Cloud Messaging topic hc_trading_signals; notifyTradingSignal function. | Read signed-in if feature enabled; create/update for admins or role_id 201/202/502; delete for signal manager/owner by rules. | lib/models/trading_signal_item.dart; lib/features/hub/publisher_sheets.dart; functions/index.js | Signal Provider Management has delete/manage permissions beyond normal signal providers. |
| Market analysis | Publish and read analyst views with trend, market, tags and related content. | Market tab, MarketAnalysisDetailScreen, market publisher sheet, Admin Market Analyst resource | View, filter, publish, edit, request delete, admin review delete. | market_analyses with title, body/description, market, trend, image_url, status, created_by. | Firestore; Storage market_analyses/{userId}; notifyMarketAnalysis function. | Read signed-in if feature enabled; create/update for role_id 501/admin; delete only Super Admin by rules or via delete_requests workflow. | lib/models/market_analysis_item.dart; lib/features/hub/publisher_sheets.dart; firestore.rules | Delete request workflow is limited to market_analyses and trading_resources. |
| Learning content, blogs, news, recordings and resources | Provide educational material, marketing resources, recordings, knowledge articles and business documents. | Knowledge tab, Classes tab, Blogs tab, Resources tab, detail screens, Admin Content tab, AdminResourceScreen | Read/open/download, publish/edit/archive/restore, upload images/PDF/DOCX/MP4, manage community documents. | trading_blogs; trading_news; trading_resources; trading_recordings; knowledge_centres; community_documents. | Firestore; Firebase Storage admin_assets, recording_materials, knowledge_centre, community_documents; Cloud Messaging recording/knowledge topics. | Signed-in read where feature enabled; admin writes; recording/resource access may require password verification. | lib/features/hub/trading_hub_screen.dart; lib/features/admin/admin_trading_operations.dart; storage.rules | Some admin publishing dialogs contain hardcoded English text, which is a localization risk. |
| Daily exam and final examination | Assess trading knowledge with daily questions and formal final exam attempts. | Exam tab, DailyExamResultScreen, FinalExamScreen, Admin Exam Results, Admin Trading Exam Questions, Admin Final Examination | Start exam, submit answers, view score/result, write attempts, admin manage questions/banks and review attempts. | trading_exam_questions; users/{uid}/trading_exam_attempts; final_exam_questions; final_exam_question_banks; users/{uid}/final_exam_attempts. | Firestore; notification functions notifyTradingExamResult and notifyFinalExamResult. | Signed-in users; admin manages content/results. | lib/features/examination/*; lib/models/trading_exam_models.dart; lib/models/final_exam_mobile.dart | Tests strongly cover final exam Chinese fallback and localization migration behavior. |
| Trading toolkit | Collect local trader utilities that support risk, analysis, planning and discipline. | ToolsScreen, RiskCalculatorScreen, StatisticsScreen, PropFirmScreen, PsychologyScreen, TradingPlanScreen | Calculate lot size and RR, view statistics, track prop firm rules, perform psychology check-in, maintain trading plan. | SharedPreferences drafts/history; journals/capitals for statistics/prop firm; prop_firm_models for configuration selection. | Mostly local services; Firestore for journals/accounts/prop firm model selection where needed. | Authenticated users. | lib/features/tools/tools_screen.dart; lib/services/*calculator*; lib/services/*analytics* | Local persistence is intentionally non-sensitive per LocalAppStorage comments. |
| Prop firm workflow | Track prop firm phases, rule compliance, withdrawals and admin review status. | PropFirmScreen, Admin Performance tab, Admin Prop Firm Config resource | Select model, calculate target/drawdown/best-day rules, submit/receive admin review decisions. | prop_firm_models; users/{uid} prop_firm_* fields; journals/capitals for calculations. | Firestore; PropFirmProgressService; notifyPropFirmReview Cloud Function. | Authenticated traders; admin manages models/reviews. | lib/features/prop_firm/prop_firm_screen.dart; lib/services/prop_firm_progress_service.dart; lib/features/admin/admin_trading_operations.dart | Tests cover daily loss failure and report withdrawal gating. |
| Psychology and warning module | Score discipline and warn about revenge trading, FOMO and overconfidence. | PsychologyScreen | Input mood/confidence/urge flags, calculate score, show warnings and pause advice. | SharedPreferences user-scoped psychology history. | Local only based on PsychologyScoreService. | Authenticated users. | lib/features/psychology/psychology_screen.dart; lib/services/psychology_score_service.dart | No Firebase write confirmed for psychology entries. |
| Readiness checklist | Help traders complete pre-live-trading readiness items with status/progress. | ReadinessChecklistScreen | View progress, complete item with notes/confidence, reopen/reset checklist. | users/{uid}/readiness_checklist with localized/dynamic fields. | Firestore; localizedValue helpers for dynamic content. | Roles 1,2,750,760,770 when trader_centre feature is enabled. | lib/features/readiness/readiness_checklist_screen.dart; lib/repositories/trading_repository.dart | Localization accepts *_localized, *_translations and map-style fields. |
| Appointments | Allow traders to book or request mentor appointments and admins/hosts to manage slots. | TradingAppointmentsScreen, Admin Appointment Slots resource | Create/delete slot, book slot, request preferred appointment, approve/reject/cancel, notify participants. | trading_appointment_slots; appointment_hosts; trading_appointments. | Firestore; notification functions notifyNewAppointment and notifyAppointmentUpdate. | Trading roles/admin when appointments feature is enabled; Firestore validates participant_ids and times. | lib/features/appointments/trading_appointments_screen.dart; functions/index.js; firestore.rules | Rules prevent self-hosting and past scheduled_start_at creation. |
| Support desk | Let users create support tickets and staff respond, assign and close/reopen tickets. | SupportTicketsScreen, TicketDetailScreen, Admin Support Tickets resource | Create ticket, upload attachments, reply, assign, close, reopen, delete by admin. | support_tickets; support_tickets/{ticketId}/messages; Storage support_tickets/{userId}. | Firestore streams; Storage; notifySupportTicketUpdate function. | Ticket owner and admins; supportAccess set includes admin, signal, analyst, trader/leadership/recruiter roles. | lib/features/support/support_tickets_screen.dart; firestore.rules; storage.rules | Message create rule requires ticket owner unless admin state is visible in getAfter result. |
| Recruitment and invitations | Expose referral code and downline status to roles that can invite public roles. | RecruitmentScreen | View referral code, copy/share invite, inspect downline list. | users; users/{uid}/downlines; referral_codes; referral_relationships. | Firestore; registration function maintains relationships. | Leader, recruiter, signal/market roles, trader and admins according to assignable matrix. | lib/features/recruitment/recruitment_screen.dart; lib/core/security/app_role_permissions.dart | Downline profile visibility is enforced in Firestore rules by upline/referrer/invited_by fields. |
| Profile and settings | Let users manage profile, avatar, password, notifications, language and app preferences. | ProfileScreen, SettingsScreen | Upload avatar, edit name/email display fields, verify/update password, change language, notification preferences, privacy link. | users/{uid}; profile_images/{uid}; SharedPreferences locale and notification preferences; users/{uid}/notification_tokens. | Firebase Auth, Firestore, Storage, Messaging, Local Notifications. | Authenticated owner. | lib/features/profile/profile_screen.dart; lib/features/settings/settings_screen.dart; lib/services/push_notification_service.dart | Android notification permission is declared; unsupported platforms are skipped by service. |
| Administration dashboard and resource management | Operate the platform: users, verification, positions, content, features, audit, delete requests, performance and competitions. | AdminOverviewScreen, AdminManagementScreen, AdminResourceScreen and admin tabs | Manage users/roles, review onboarding/positions, CRUD content, configure features, review journals/exams/delete requests, audit actions, manage leaderboard/competitions. | Most shared collections plus users subcollections; audit_logs and delete_requests. | Firestore admin reads/writes; Storage uploads; AdminPermission and Firestore rules. | Super/Sub Admin; publisher roles get narrower Publisher workspace. | lib/features/admin/*; lib/core/security/admin_permissions.dart; lib/repositories/trading_repository.dart | Admin permission model in Flutter is broader for Sub Admin than the Cloud Function role-permission helper; Firestore role rules still constrain sensitive writes. |
| Feature configuration and module gating | Enable/disable business modules from Firestore. | Admin Features tab, MoreScreen | Toggle feature flags, hide menu modules, block reads for gated Firestore content. | app_configuration/modules; tradingFeatureDefaults. | Firestore rules and repository adminSaveFeatureConfiguration. | Read signed-in; write Super/Sub Admin. | lib/core/security/admin_permissions.dart; lib/features/admin/admin_management_screen.dart; firestore.rules | Feature ai_assistant defaults true, but no confirmed Flutter AI assistant UI/client is implemented. |
| Localization and translation operations | Support English/Simplified Chinese UI and manual Firestore bilingual content validation. | Auth language controls; Settings language controls; all l10n-aware screens | Switch language, localize static strings, display dynamic Firestore translations, audit/backfill translations, validate manual Chinese fields through audit tooling. | Localized map fields such as title_i18n, description_i18n, translations.zh, translationMeta. | No translation Cloud Functions or API secrets; tools audit manual bilingual coverage. | Static locale all users; translation regeneration admin-only. | lib/core/localization/*; lib/core/localization/*; tools/audit_chinese_localisation.js; tools/backfill_final_exam_questions_zh.js | Hardcoded English remains in several admin/publisher dialogs and should be reviewed for full Chinese coverage. |
| Notifications | Notify users about published content and workflow changes. | Settings notification preferences; foreground notifications | Request permission, subscribe/unsubscribe topics, store/delete tokens, show Android foreground local notification. | users/{uid}/notification_tokens; SharedPreferences notification_preferences. | Firebase Messaging; flutter_local_notifications; Cloud Functions notification exports. | Supported only on Android/iOS/macOS by service check; Android configured in current project. | lib/services/push_notification_service.dart; functions/index.js; android/app/src/main/AndroidManifest.xml | Windows/Web are not supported by the service condition even though app platforms exist. |
| Reports, PDF and analytics | Generate trading performance summaries and PDF/shareable reports. | StatisticsScreen, PropFirmScreen, report/PDF actions where surfaced | Calculate balance, win rate, drawdown, expectancy, best-day rule, prop-firm status, PDF build/share. | TradingJournalEntry, CapitalTransaction, TradingReport, TradingSummary. | Local Dart services; pdf and printing packages. | Authenticated users; admin performance tab reads aggregate data. | lib/services/trading_report_service.dart; lib/services/trading_report_pdf_service.dart; lib/services/trading_analytics.dart | Tests cover report rules and trading analytics. |


### 1. App bootstrap, theme and auth gate

- Business purpose: Initialize Firebase, local storage, locale controller and render either AuthScreen or HomeShell.
- User flow: main() initializes Firebase and storage, creates repositories, then HCTraderApp uses StreamBuilder over authStateChanges.
- Main screens: HCTraderApp, _AuthGate, HomeShell
- Main actions: Load current user, listen to auth stream, configure optional push notifications after first frame.
- Data used: FirebaseAuth current user; users/{uid} profile; SharedPreferences locale/preferences.
- Firebase/API dependency: Firebase Core/Auth/Firestore; PushNotificationService.
- Role access: All users after app launch; authenticated shell only after user profile resolves.
- Related files: lib/main.dart; lib/app/hc_trader_app.dart; lib/app/home_shell.dart
- Notes / risks: Current main.dart forces firebaseEnabled=true; README says no Laravel runtime dependency.

### 2. Authentication and session management

- Business purpose: Secure sign in, registration, password reset, password change and sign out.
- User flow: Guest opens AuthScreen, signs in by email/username or registers. AuthRepository resolves Firebase user into AppUser and profile.
- Main screens: AuthScreen, ProfileScreen password dialog, MoreScreen sign-out button
- Main actions: Email/username login, create Firebase account, call validated profile Cloud Function, reset password, verify password, update password, sign out.
- Data used: Firebase Auth user; users; usernames; registration_roles.
- Firebase/API dependency: Firebase Auth; Firestore; Cloud Function registerUserProfileAfterAuth; HTTP callable fallback on Windows.
- Role access: Guest and authenticated user self-service.
- Related files: lib/features/auth/auth_screen.dart; lib/repositories/auth_repository.dart; functions/registration_functions.js
- Notes / risks: Sign-out is designed to clear local app session before asynchronous Firebase cleanup to avoid stuck loading.

### 3. Registration, referral and downline system

- Business purpose: Create user profiles with role validation and referral/downline relationships.
- User flow: Registration submits user fields, role id and referral code; Cloud Function validates role/referral, creates usernames, referral codes, relationships and downline docs.
- Main screens: AuthScreen registration tab; RecruitmentScreen
- Main actions: Register, generate/read referral code, show downlines, invite Trader/Lead Client based on role matrix.
- Data used: registration_roles; referral_codes; referral_relationships; users/{uid}/downlines; users.
- Firebase/API dependency: registerUserProfileAfterAuth callable; Firestore transaction; role permission mirror in functions/role_permissions.js.
- Role access: Public registration roles Trader and Lead Client; inviter roles from RolePermissionMatrix.
- Related files: lib/repositories/auth_repository.dart; lib/features/recruitment/recruitment_screen.dart; functions/registration_functions.js
- Notes / risks: Privileged registration is unavailable through direct client writes and requires trusted backend approval.

### 4. Dashboard

- Business purpose: Give traders an at-a-glance view of performance and trading state.
- User flow: Home tab reads repository streams and local analytics models.
- Main screens: DashboardScreen
- Main actions: View summary metrics, recent performance and status cards.
- Data used: trading_journals; capitals; trading_accounts; computed TradingSummary.
- Firebase/API dependency: Firestore user subcollections through TradingRepository.
- Role access: Authenticated users.
- Related files: lib/features/dashboard/dashboard_screen.dart; lib/shared/performance_charts.dart; lib/services/trading_analytics.dart
- Notes / risks: Exact visual widgets depend on stream availability and cached data.

### 5. Trading journal

- Business purpose: Record, import, analyze and administratively review trades.
- User flow: Journal tab lists entries; form creates/edits entries; attachments upload to Storage; admin can review collection-group data.
- Main screens: JournalScreen, JournalFormScreen, AdminJournalReviewScreen
- Main actions: Create/edit/delete journal, attach images/documents, import CSV/XLSX, add admin notes.
- Data used: users/{uid}/trading_journals; Storage users/{uid}/... journal attachments.
- Firebase/API dependency: Firestore; Firebase Storage; local queued drafts in SharedPreferences; excel/file_picker for imports.
- Role access: Owner or admin; admin review through collection-group rules.
- Related files: lib/features/journal/*; lib/repositories/trading_repository.dart; lib/models/trading_journal_entry.dart
- Notes / risks: Tests cover calculation, analytics and reporting around journal data.

### 6. Accounts, verification and capital

- Business purpose: Manage trader profile verification, broker/trading accounts, capital transactions and position applications.
- User flow: Accounts tab reads onboarding profile, accounts, capital and applications; setup banner can link here.
- Main screens: AccountScreen, Admin Verification tab, Admin Positions tab
- Main actions: Save account profile, upload onboarding document, add/update/delete trading accounts, capital transactions, submit position applications, admin approve/reject.
- Data used: users/{uid}/trader_onboarding/profile; onboarding_documents; trading_accounts; capitals; trading_position_applications.
- Firebase/API dependency: Firestore and Storage user paths; admin collection-group reads.
- Role access: Owner self-service; admin review.
- Related files: lib/features/account/account_screen.dart; lib/models/trader_account_profile.dart; lib/models/trading_account.dart
- Notes / risks: This is the closest equivalent to KYC/verification in the Flutter app.

### 7. Trading hub

- Business purpose: Central workspace for market content, learning, signals, exams, position applications and resources.
- User flow: TradingHubScreen uses DefaultTabController with eight hub tabs.
- Main screens: TradingHubScreen plus Market, Knowledge, Classes, Blogs, Signals, Exam, Positions, Resources tabs and detail screens.
- Main actions: Read content, open details, publish signals/analysis for eligible roles, unlock protected recordings/resources, take daily exam, apply for positions.
- Data used: market_analyses; trading_signals; trading_blogs; trading_resources; trading_recordings; knowledge_centres; trading_exam_questions; trading_position_applications.
- Firebase/API dependency: Firestore streams, Storage URLs, AuthRepository password verification, url_launcher for external resources.
- Role access: Signed-in users; publishing restricted by role.
- Related files: lib/features/hub/trading_hub_screen.dart; lib/features/hub/publisher_sheets.dart
- Notes / risks: Feature flags in app_configuration/modules can restrict content collections through Firestore rules and More menu conditions.

### 8. Trading signals

- Business purpose: Publish and consume signal cards with pair, action, entry, SL/TP and status.
- User flow: Hub Signals tab streams active signals; publisher sheet writes trading_signals; Cloud Function notifies topic subscribers.
- Main screens: Signals tab, signal publisher sheet, Admin Trading Signals resource
- Main actions: View, publish, edit/delete where authorized, notify users.
- Data used: trading_signals fields include signal_title/title, trading_pair, immediate_action, entry_price, targets, disclaimer, risk_level, status, created_by.
- Firebase/API dependency: Firestore; Cloud Messaging topic hc_trading_signals; notifyTradingSignal function.
- Role access: Read signed-in if feature enabled; create/update for admins or role_id 201/202/502; delete for signal manager/owner by rules.
- Related files: lib/models/trading_signal_item.dart; lib/features/hub/publisher_sheets.dart; functions/index.js
- Notes / risks: Signal Provider Management has delete/manage permissions beyond normal signal providers.

### 9. Market analysis

- Business purpose: Publish and read analyst views with trend, market, tags and related content.
- User flow: Hub Market tab streams analyses; Market Analyst/Admin users see publish actions; detail screen links related analysis.
- Main screens: Market tab, MarketAnalysisDetailScreen, market publisher sheet, Admin Market Analyst resource
- Main actions: View, filter, publish, edit, request delete, admin review delete.
- Data used: market_analyses with title, body/description, market, trend, image_url, status, created_by.
- Firebase/API dependency: Firestore; Storage market_analyses/{userId}; notifyMarketAnalysis function.
- Role access: Read signed-in if feature enabled; create/update for role_id 501/admin; delete only Super Admin by rules or via delete_requests workflow.
- Related files: lib/models/market_analysis_item.dart; lib/features/hub/publisher_sheets.dart; firestore.rules
- Notes / risks: Delete request workflow is limited to market_analyses and trading_resources.

### 10. Learning content, blogs, news, recordings and resources

- Business purpose: Provide educational material, marketing resources, recordings, knowledge articles and business documents.
- User flow: Hub tabs read content collections; admin modules publish and archive records; protected resources require password re-verification.
- Main screens: Knowledge tab, Classes tab, Blogs tab, Resources tab, detail screens, Admin Content tab, AdminResourceScreen
- Main actions: Read/open/download, publish/edit/archive/restore, upload images/PDF/DOCX/MP4, manage community documents.
- Data used: trading_blogs; trading_news; trading_resources; trading_recordings; knowledge_centres; community_documents.
- Firebase/API dependency: Firestore; Firebase Storage admin_assets, recording_materials, knowledge_centre, community_documents; Cloud Messaging recording/knowledge topics.
- Role access: Signed-in read where feature enabled; admin writes; recording/resource access may require password verification.
- Related files: lib/features/hub/trading_hub_screen.dart; lib/features/admin/admin_trading_operations.dart; storage.rules
- Notes / risks: Some admin publishing dialogs contain hardcoded English text, which is a localization risk.

### 11. Daily exam and final examination

- Business purpose: Assess trading knowledge with daily questions and formal final exam attempts.
- User flow: Daily exam is available in Trading Hub; final exam opens from More/setup banner; admin reviews attempts and manages question banks.
- Main screens: Exam tab, DailyExamResultScreen, FinalExamScreen, Admin Exam Results, Admin Trading Exam Questions, Admin Final Examination
- Main actions: Start exam, submit answers, view score/result, write attempts, admin manage questions/banks and review attempts.
- Data used: trading_exam_questions; users/{uid}/trading_exam_attempts; final_exam_questions; final_exam_question_banks; users/{uid}/final_exam_attempts.
- Firebase/API dependency: Firestore; notification functions notifyTradingExamResult and notifyFinalExamResult.
- Role access: Signed-in users; admin manages content/results.
- Related files: lib/features/examination/*; lib/models/trading_exam_models.dart; lib/models/final_exam_mobile.dart
- Notes / risks: Tests strongly cover final exam Chinese fallback and localization migration behavior.

### 12. Trading toolkit

- Business purpose: Collect local trader utilities that support risk, analysis, planning and discipline.
- User flow: More menu opens ToolsScreen; each tool pushes its own screen.
- Main screens: ToolsScreen, RiskCalculatorScreen, StatisticsScreen, PropFirmScreen, PsychologyScreen, TradingPlanScreen
- Main actions: Calculate lot size and RR, view statistics, track prop firm rules, perform psychology check-in, maintain trading plan.
- Data used: SharedPreferences drafts/history; journals/capitals for statistics/prop firm; prop_firm_models for configuration selection.
- Firebase/API dependency: Mostly local services; Firestore for journals/accounts/prop firm model selection where needed.
- Role access: Authenticated users.
- Related files: lib/features/tools/tools_screen.dart; lib/services/*calculator*; lib/services/*analytics*
- Notes / risks: Local persistence is intentionally non-sensitive per LocalAppStorage comments.

### 13. Prop firm workflow

- Business purpose: Track prop firm phases, rule compliance, withdrawals and admin review status.
- User flow: PropFirmScreen calculates progress; admin performance tab reviews prop-firm outcomes and can approve/suspend/request correction.
- Main screens: PropFirmScreen, Admin Performance tab, Admin Prop Firm Config resource
- Main actions: Select model, calculate target/drawdown/best-day rules, submit/receive admin review decisions.
- Data used: prop_firm_models; users/{uid} prop_firm_* fields; journals/capitals for calculations.
- Firebase/API dependency: Firestore; PropFirmProgressService; notifyPropFirmReview Cloud Function.
- Role access: Authenticated traders; admin manages models/reviews.
- Related files: lib/features/prop_firm/prop_firm_screen.dart; lib/services/prop_firm_progress_service.dart; lib/features/admin/admin_trading_operations.dart
- Notes / risks: Tests cover daily loss failure and report withdrawal gating.

### 14. Psychology and warning module

- Business purpose: Score discipline and warn about revenge trading, FOMO and overconfidence.
- User flow: Tool screen stores check-ins locally and displays warnings/recommendations.
- Main screens: PsychologyScreen
- Main actions: Input mood/confidence/urge flags, calculate score, show warnings and pause advice.
- Data used: SharedPreferences user-scoped psychology history.
- Firebase/API dependency: Local only based on PsychologyScoreService.
- Role access: Authenticated users.
- Related files: lib/features/psychology/psychology_screen.dart; lib/services/psychology_score_service.dart
- Notes / risks: No Firebase write confirmed for psychology entries.

### 15. Readiness checklist

- Business purpose: Help traders complete pre-live-trading readiness items with status/progress.
- User flow: More menu opens checklist; user completes/reopens items by category.
- Main screens: ReadinessChecklistScreen
- Main actions: View progress, complete item with notes/confidence, reopen/reset checklist.
- Data used: users/{uid}/readiness_checklist with localized/dynamic fields.
- Firebase/API dependency: Firestore; localizedValue helpers for dynamic content.
- Role access: Roles 1,2,750,760,770 when trader_centre feature is enabled.
- Related files: lib/features/readiness/readiness_checklist_screen.dart; lib/repositories/trading_repository.dart
- Notes / risks: Localization accepts *_localized, *_translations and map-style fields.

### 16. Appointments

- Business purpose: Allow traders to book or request mentor appointments and admins/hosts to manage slots.
- User flow: More menu opens appointment workspace; admins create slots/hosts; users book slots or preferred times.
- Main screens: TradingAppointmentsScreen, Admin Appointment Slots resource
- Main actions: Create/delete slot, book slot, request preferred appointment, approve/reject/cancel, notify participants.
- Data used: trading_appointment_slots; appointment_hosts; trading_appointments.
- Firebase/API dependency: Firestore; notification functions notifyNewAppointment and notifyAppointmentUpdate.
- Role access: Trading roles/admin when appointments feature is enabled; Firestore validates participant_ids and times.
- Related files: lib/features/appointments/trading_appointments_screen.dart; functions/index.js; firestore.rules
- Notes / risks: Rules prevent self-hosting and past scheduled_start_at creation.

### 17. Support desk

- Business purpose: Let users create support tickets and staff respond, assign and close/reopen tickets.
- User flow: More menu opens ticket list; ticket detail streams messages; admin sees wider operations in management workspace.
- Main screens: SupportTicketsScreen, TicketDetailScreen, Admin Support Tickets resource
- Main actions: Create ticket, upload attachments, reply, assign, close, reopen, delete by admin.
- Data used: support_tickets; support_tickets/{ticketId}/messages; Storage support_tickets/{userId}.
- Firebase/API dependency: Firestore streams; Storage; notifySupportTicketUpdate function.
- Role access: Ticket owner and admins; supportAccess set includes admin, signal, analyst, trader/leadership/recruiter roles.
- Related files: lib/features/support/support_tickets_screen.dart; firestore.rules; storage.rules
- Notes / risks: Message create rule requires ticket owner unless admin state is visible in getAfter result.

### 18. Recruitment and invitations

- Business purpose: Expose referral code and downline status to roles that can invite public roles.
- User flow: More menu shows Recruitment when RolePermissionMatrix assignable roles include public registration roles.
- Main screens: RecruitmentScreen
- Main actions: View referral code, copy/share invite, inspect downline list.
- Data used: users; users/{uid}/downlines; referral_codes; referral_relationships.
- Firebase/API dependency: Firestore; registration function maintains relationships.
- Role access: Leader, recruiter, signal/market roles, trader and admins according to assignable matrix.
- Related files: lib/features/recruitment/recruitment_screen.dart; lib/core/security/app_role_permissions.dart
- Notes / risks: Downline profile visibility is enforced in Firestore rules by upline/referrer/invited_by fields.

### 19. Profile and settings

- Business purpose: Let users manage profile, avatar, password, notifications, language and app preferences.
- User flow: More menu opens Profile/Settings; settings reads feature and notification status.
- Main screens: ProfileScreen, SettingsScreen
- Main actions: Upload avatar, edit name/email display fields, verify/update password, change language, notification preferences, privacy link.
- Data used: users/{uid}; profile_images/{uid}; SharedPreferences locale and notification preferences; users/{uid}/notification_tokens.
- Firebase/API dependency: Firebase Auth, Firestore, Storage, Messaging, Local Notifications.
- Role access: Authenticated owner.
- Related files: lib/features/profile/profile_screen.dart; lib/features/settings/settings_screen.dart; lib/services/push_notification_service.dart
- Notes / risks: Android notification permission is declared; unsupported platforms are skipped by service.

### 20. Administration dashboard and resource management

- Business purpose: Operate the platform: users, verification, positions, content, features, audit, delete requests, performance and competitions.
- User flow: AdminOverviewScreen has 11 tabs; AdminManagementScreen lists configured resource modules and review screens.
- Main screens: AdminOverviewScreen, AdminManagementScreen, AdminResourceScreen and admin tabs
- Main actions: Manage users/roles, review onboarding/positions, CRUD content, configure features, review journals/exams/delete requests, audit actions, manage leaderboard/competitions.
- Data used: Most shared collections plus users subcollections; audit_logs and delete_requests.
- Firebase/API dependency: Firestore admin reads/writes; Storage uploads; AdminPermission and Firestore rules.
- Role access: Super/Sub Admin; publisher roles get narrower Publisher workspace.
- Related files: lib/features/admin/*; lib/core/security/admin_permissions.dart; lib/repositories/trading_repository.dart
- Notes / risks: Admin permission model in Flutter is broader for Sub Admin than the Cloud Function role-permission helper; Firestore role rules still constrain sensitive writes.

### 21. Feature configuration and module gating

- Business purpose: Enable/disable business modules from Firestore.
- User flow: MoreScreen loads featureConfiguration; Firestore rules featureEnabled checks app_configuration/modules.
- Main screens: Admin Features tab, MoreScreen
- Main actions: Toggle feature flags, hide menu modules, block reads for gated Firestore content.
- Data used: app_configuration/modules; tradingFeatureDefaults.
- Firebase/API dependency: Firestore rules and repository adminSaveFeatureConfiguration.
- Role access: Read signed-in; write Super/Sub Admin.
- Related files: lib/core/security/admin_permissions.dart; lib/features/admin/admin_management_screen.dart; firestore.rules
- Notes / risks: Feature ai_assistant defaults true, but no confirmed Flutter AI assistant UI/client is implemented.

### 22. Localization and translation operations

- Business purpose: Support English/Simplified Chinese UI and manual Firestore bilingual content validation.
- User flow: AppLocaleController persists locale; AppLocalizations resolves static labels; model helpers resolve *_i18n and localized maps; Functions/tools backfill Chinese dynamic content.
- Main screens: Auth language controls; Settings language controls; all l10n-aware screens
- Main actions: Switch language, localize static strings, display dynamic Firestore translations, audit/backfill translations, validate manual Chinese fields through audit tooling.
- Data used: Localized map fields such as title_i18n, description_i18n, translations.zh, translationMeta.
- Firebase/API dependency: No translation Cloud Functions or API secrets; tools audit manual bilingual coverage.
- Role access: Static locale all users; translation regeneration admin-only.
- Related files: lib/core/localization/*; lib/core/localization/*; tools/audit_chinese_localisation.js; tools/backfill_final_exam_questions_zh.js
- Notes / risks: Hardcoded English remains in several admin/publisher dialogs and should be reviewed for full Chinese coverage.

### 23. Notifications

- Business purpose: Notify users about published content and workflow changes.
- User flow: PushNotificationService registers tokens and topic subscriptions; Cloud Functions send topic/direct FCM messages.
- Main screens: Settings notification preferences; foreground notifications
- Main actions: Request permission, subscribe/unsubscribe topics, store/delete tokens, show Android foreground local notification.
- Data used: users/{uid}/notification_tokens; SharedPreferences notification_preferences.
- Firebase/API dependency: Firebase Messaging; flutter_local_notifications; Cloud Functions notification exports.
- Role access: Supported only on Android/iOS/macOS by service check; Android configured in current project.
- Related files: lib/services/push_notification_service.dart; functions/index.js; android/app/src/main/AndroidManifest.xml
- Notes / risks: Windows/Web are not supported by the service condition even though app platforms exist.

### 24. Reports, PDF and analytics

- Business purpose: Generate trading performance summaries and PDF/shareable reports.
- User flow: Statistics/PropFirm/report services consume journal and capital data.
- Main screens: StatisticsScreen, PropFirmScreen, report/PDF actions where surfaced
- Main actions: Calculate balance, win rate, drawdown, expectancy, best-day rule, prop-firm status, PDF build/share.
- Data used: TradingJournalEntry, CapitalTransaction, TradingReport, TradingSummary.
- Firebase/API dependency: Local Dart services; pdf and printing packages.
- Role access: Authenticated users; admin performance tab reads aggregate data.
- Related files: lib/services/trading_report_service.dart; lib/services/trading_report_pdf_service.dart; lib/services/trading_analytics.dart
- Notes / risks: Tests cover report rules and trading analytics.


## 6. Screen Inventory

| Screen/view | Route/path | Purpose | Access role | Module | Source file |
| --- | --- | --- | --- | --- | --- |
| AuthScreen | App home when user is null | Sign in, register, password reset and language switching | Guest | Authentication | lib/features/auth/auth_screen.dart |
| HomeShell | Authenticated root | Four-tab shell with setup banner and Profile & more action | Authenticated users | App shell | lib/app/home_shell.dart |
| DashboardScreen | Home tab | Performance summary and dashboard metrics | Authenticated users | Dashboard | lib/features/dashboard/dashboard_screen.dart |
| JournalScreen | Journal tab | Review and filter journal entries | Authenticated users | Trading journal | lib/features/journal/journal_screen.dart |
| JournalFormScreen | Pushed from Journal | Create/edit journal entry and attachments | Authenticated users | Trading journal | lib/features/journal/journal_form_screen.dart |
| TradingHubScreen | Trading Hub tab | Tabbed hub for market, learning, signals, exams, positions, resources | Authenticated users | Trading hub | lib/features/hub/trading_hub_screen.dart |
| Market tab | TradingHub tab 1 | Market analysis feed and publisher actions | All signed-in; publishers get create/edit | Market analysis | lib/features/hub/trading_hub_screen.dart |
| Knowledge tab | TradingHub tab 2 | Knowledge-centre list and detail reading | Signed-in | Knowledge centre | lib/features/hub/trading_hub_screen.dart |
| Classes tab | TradingHub tab 3 | Recording classes with password verification before access | Signed-in | Recording classes | lib/features/hub/trading_hub_screen.dart |
| Blogs tab | TradingHub tab 4 | Trading blog feed and detail view | Signed-in | Trading blog | lib/features/hub/trading_hub_screen.dart |
| Signals tab | TradingHub tab 5 | Trading signals, state labels, targets and publisher actions | Signed-in; signal roles publish | Trading signals | lib/features/hub/trading_hub_screen.dart |
| Exam tab | TradingHub tab 6 | Daily trading exam flow and results | Signed-in | Daily exam | lib/features/hub/trading_hub_screen.dart |
| Positions tab | TradingHub tab 7 | Trading position applications | Signed-in traders | Positions | lib/features/hub/trading_hub_screen.dart |
| Resources tab | TradingHub tab 8 | Marketing/resource downloads with password verification | Signed-in | Resources | lib/features/hub/trading_hub_screen.dart |
| MarketAnalysisDetailScreen | Pushed from hub | Detailed market analysis with related items | Signed-in | Market analysis | lib/features/hub/trading_hub_screen.dart |
| TradingBlogDetailScreen | Pushed from hub | Blog article detail | Signed-in | Trading blog | lib/features/hub/trading_hub_screen.dart |
| RecordingDetailScreen | Pushed after unlock | Recording class detail and materials | Signed-in with password check | Recording classes | lib/features/hub/trading_hub_screen.dart |
| ResourceDetailScreen | Pushed from resources | Marketing/resource detail and download | Signed-in with password check for protected access | Resources | lib/features/hub/trading_hub_screen.dart |
| ReadableDetailScreen | Pushed from knowledge/resources | Generic readable detail page | Signed-in | Learning content | lib/features/hub/trading_hub_screen.dart |
| AccountScreen | Accounts tab | Verification, account profile, trading accounts, capital and position applications | Authenticated users | Account / KYC | lib/features/account/account_screen.dart |
| MoreScreen | Profile action push | Role-aware service grid and sign-out | Authenticated users | More menu | lib/features/more/more_screen.dart |
| FinalExamScreen | More menu/setup banner | Official final examination and status | Authenticated users when feature enabled | Final examination | lib/features/examination/final_exam_screen.dart |
| DailyExamResultScreen | Pushed after daily exam | Daily exam result display | Authenticated users | Daily exam | lib/features/examination/daily_exam_result_screen.dart |
| ToolsScreen | More menu | Tool launcher | Authenticated users | Trading toolkit | lib/features/tools/tools_screen.dart |
| RiskCalculatorScreen | Tools | Position size and risk/reward calculator | Authenticated users | Risk calculator | lib/features/risk/risk_calculator_screen.dart |
| StatisticsScreen | Tools | Journal analytics, breakdowns and charts | Authenticated users | Statistics | lib/features/statistics/statistics_screen.dart |
| PropFirmScreen | Tools | Prop firm progress, limits and withdrawal eligibility | Authenticated users | Prop firm | lib/features/prop_firm/prop_firm_screen.dart |
| PsychologyScreen | Tools | Daily psychology check-in and warnings | Authenticated users | Psychology | lib/features/psychology/psychology_screen.dart |
| TradingPlanScreen | Tools | Local trading-plan drafting | Authenticated users | Trading plan | lib/features/trading_plan/trading_plan_screen.dart |
| ReadinessChecklistScreen | More menu | Trader readiness checklist by category | Role 1,2,750,760,770 when enabled | Readiness | lib/features/readiness/readiness_checklist_screen.dart |
| TradingAppointmentsScreen | More menu/Admin | Book, request, approve and cancel trading appointments | Trader/admin/eligible roles | Appointments | lib/features/appointments/trading_appointments_screen.dart |
| SupportTicketsScreen | More menu/Admin | Create, reply, assign, close/reopen support tickets | Support-enabled roles/admin | Support desk | lib/features/support/support_tickets_screen.dart |
| TicketDetailScreen | Pushed from Support | Ticket messages and lifecycle actions | Ticket owner/admin | Support desk | lib/features/support/support_tickets_screen.dart |
| RecruitmentScreen | More menu | Referral code and downline workspace | Roles with assignable public roles | Recruitment | lib/features/recruitment/recruitment_screen.dart |
| ProfileScreen | More menu | Profile photo, profile fields and password change | Authenticated users | Profile | lib/features/profile/profile_screen.dart |
| SettingsScreen | More menu | Pairs, notification preferences, privacy and language/sync settings | Authenticated users | Settings | lib/features/settings/settings_screen.dart |
| AdminOverviewScreen | More menu/Admin | Admin TabBar root | Super/Sub Admin | Administration | lib/features/admin/admin_overview_screen.dart |
| Admin Overview tab | Admin tab 1 | Aggregate counts and recent users | Admin | Administration | lib/features/admin/admin_overview_screen.dart |
| Admin Modules tab | Admin tab 2 | Resource administration launcher | Admin/publisher permissions | Admin modules | lib/features/admin/admin_management_screen.dart |
| Admin Users tab | Admin tab 3 | Search/filter/edit users and roles | Admin | User management | lib/features/admin/admin_overview_screen.dart |
| Admin Verification tab | Admin tab 4 | Onboarding/KYC review and document preview | Admin | Verification | lib/features/admin/admin_overview_screen.dart |
| Admin Positions tab | Admin tab 5 | Approve/reject position applications | Admin | Positions | lib/features/admin/admin_overview_screen.dart |
| Admin Content tab | Admin tab 6 | Recordings, knowledge and community documents | Admin | Content | lib/features/admin/admin_trading_operations.dart |
| Admin Performance tab | Admin tab 7 | Trader performance and prop-firm reviews | Admin | Performance | lib/features/admin/admin_trading_operations.dart |
| Admin Leaderboard tab | Admin tab 8 | Leaderboard and competitions | Admin | Leaderboard | lib/features/admin/admin_trading_operations.dart |
| Admin Requests tab | Admin tab 9 | Delete request approval/rejection | Admin with delete review permission | Delete requests | lib/features/admin/admin_management_screen.dart |
| Admin Features tab | Admin tab 10 | Feature flag toggles | Admin with featureManage | Feature configuration | lib/features/admin/admin_management_screen.dart |
| Admin Audit tab | Admin tab 11 | Audit log view | Admin with auditView | Audit | lib/features/admin/admin_management_screen.dart |
| AdminResourceScreen | Pushed from Admin Modules | Generic CRUD for configured admin collections | Permission-specific | Admin resources | lib/features/admin/admin_management_screen.dart |
| AdminJournalReviewScreen | Pushed from Admin Modules | Review journal rows and add admin notes | Admin with journalManage | Journal administration | lib/features/admin/admin_management_screen.dart |
| AdminExamResultsScreen | Pushed from Admin Modules | Review daily/final exam attempts | Admin with examResultReview | Exam administration | lib/features/admin/admin_management_screen.dart |


## 7. Firebase and Data Structure

Firebase services confirmed: Firebase Auth, Cloud Firestore, Firebase Storage, Firebase Cloud Functions, Firebase Messaging and Local Notifications integration.

| Collection/path | Purpose | Used by module | Read/write actions | Visible fields from code | Related files |
| --- | --- | --- | --- | --- | --- |
| registration_roles | Registration role catalogue | Auth/registration | Read public; admin write | role_id, label/name, active flags | auth_repository.dart; firestore.rules |
| app_configuration/modules | Feature flags | More menu, rules, admin features | Read signed-in; Super/Sub Admin write | trading, appointments, support_tickets, ai_assistant, etc. | admin_permissions.dart; firestore.rules |
| audit_logs | Admin/action history | Admin audit tab | Admin read; signed-in create by actor; no update/delete | actor_id, action, target_type, target_id, created_at | trading_repository.dart; firestore.rules |
| admin_audit_logs | Registration/bootstrap audit | Registration function | Admin read; app cannot write from client | event/action metadata | registration_functions.js; firestore.rules |
| delete_requests | Publisher/admin delete review workflow | Admin requests, market/resources | Requester create pending; Admin update; no delete | target_type, target_id, requester_id, status, reason | admin_management_screen.dart; firestore.rules |
| usernames | Username uniqueness and login lookup | Auth | Public read; owner create/delete | uid, username_lowercase | auth_repository.dart; registration_functions.js |
| referral_codes | Referral/invite code ownership | Recruitment/registration | Public read; owner/admin update; admin delete | code, user_id, owner_user_id, code_type, allowed_purpose | registration_functions.js; firestore.rules |
| referral_relationships | Upline/downline links | Recruitment/admin | Admin or related users read; function writes | user_id, referrer_id, role_id | registration_functions.js; firestore.rules |
| users | Primary app user profile | Auth, dashboard, admin, profile | Owner/admin/downline read; owner limited update; admin role update | email, name, username, role_id, is_admin, status, onboarding/final flags | app_user.dart; auth_repository.dart |
| users/{uid}/trading_journals | Trade journal entries | Journal/statistics/reports/admin | Owner/admin read/write; admin collection-group review | pair, action, lot, entry, stop, take profit, result, attachments | trading_repository.dart; trading_journal_entry.dart |
| users/{uid}/capitals | Deposits/withdrawals/capital adjustments | Accounts/statistics/prop firm | Owner/admin read/write | amount, type, account_id, created_at | capital_transaction.dart |
| users/{uid}/trading_accounts | Broker/account records | Account/prop firm | Owner/admin read/write | broker, account_number, balance, phase/status fields | trading_account.dart |
| users/{uid}/trader_onboarding/profile | Verification profile | Account/admin verification | Owner/admin read/write | identity/profile fields, status, review_note | trader_account_profile.dart |
| users/{uid}/onboarding_documents/current | Verification document metadata | Account/admin verification | Owner/admin read/write | name, path/url, content type | trading_repository.dart; storage.rules |
| users/{uid}/readiness_checklist | Readiness items/progress | Readiness checklist | Owner/admin read/write | category, title, completed, confidence, notes, localized fields | readiness_checklist_screen.dart |
| users/{uid}/trading_position_applications | Position/funded account applications | Hub positions/admin positions | Owner/admin read/write | status, role snapshot, trade_count_snapshot, review_note | trading_position_application_mobile.dart |
| users/{uid}/trading_exam_attempts | Daily exam attempts | Daily exam/admin exam results | Owner/admin read; owner create completed; admin update/delete | answers, score, total_questions, status | trading_exam_models.dart; firestore.rules |
| users/{uid}/final_exam_attempts | Final examination attempts | Final exam/admin results | Owner/admin read/write | question snapshots, answers, percentage, passed/status | final_exam_mobile.dart |
| users/{uid}/downlines | Downline display records | Recruitment | Owner/admin read; client write blocked | downline user snapshot | registration_functions.js; recruitment_screen.dart |
| users/{uid}/notification_tokens | FCM tokens/preferences | Notifications | Owner read/write | token, platform, preferences, appointments_enabled | push_notification_service.dart |
| users/{uid}/notifications | User notification records | Notifications/admin | Owner/admin read/update/delete; admin create | title, body, category, document_id | firestore.rules |
| trading_pairs | Tradable symbols/pairs | Journal/settings/trading content | Signed-in read; admin write | id, symbol, metadata | trading_repository.dart |
| trading_blogs | Blog posts | Trading hub/blog/admin | Signed-in read if feature enabled; admin write | title, content, category, tags, i18n fields | trading_blog_post.dart |
| trading_news | Market/news content | Admin/news | Signed-in read if feature enabled; admin write | title, body, status | admin_management_screen.dart |
| market_analyses | Market analyst content | Hub/admin/delete request/notifications | Signed-in read; analyst/admin create/update; Super Admin delete | title, market, trend, image_url, created_by | market_analysis_item.dart |
| trading_signals | Trading signal content | Hub/admin/notifications | Signed-in read; signal publisher create/update; manager delete | signal_title, trading_pair, immediate_action, entry/targets/status | trading_signal_item.dart |
| trading_resources | Marketing/resource library | Hub resources/admin | Signed-in read; admin write | title, description, file_url/path, status, i18n | trading_resource_item.dart |
| trading_recordings | Recording classes | Hub classes/admin/notifications | Signed-in read; admin write | title, video_url, download_url, materials, status | trading_recording_class.dart |
| knowledge_centres | Knowledge centre items | Hub/admin/notifications | Signed-in read; admin write | title, description, file_path, image_url, i18n | knowledge_centre_item.dart |
| trading_exam_questions | Daily/foundation exam bank | Exam/admin | Signed-in read if exams enabled; admin write | question, options, category, i18n | trading_exam_models.dart |
| final_exam_questions | Final exam source questions | Final exam/admin | Signed-in read if exams enabled; admin write | question_type, category, options, translations | final_exam_mobile.dart |
| final_exam_question_banks | Grouped final exam banks | Final exam/admin | Signed-in read; admin write | bank metadata and question sets | trading_repository.dart |
| prop_firm_models | Prop firm rules/configuration | Prop firm/admin | Signed-in read; admin write | phases, targets, drawdown, status | prop_firm_screen.dart |
| trading_competitions | Leaderboard competitions | Admin leaderboard | Signed-in read; admin write/delete | title, date range, prize, minimum_trades, status | admin_trading_operations.dart |
| trading_appointment_slots | Appointment availability | Appointments/admin | Signed-in read if appointments enabled; admin create/delete; user booking update constrained | host_user_id, scheduled_start_at, status, appointment_id | trading_appointments_screen.dart |
| appointment_hosts | Appointment host catalogue | Appointments/admin | Signed-in read; admin write | host_user_id, name, availability metadata | trading_repository.dart |
| trading_appointments | Booked/requested appointments | Appointments/notifications | Participant/admin read; signed-in create with constraints; admin/participant cancel/update | requester_id, host_user_id, participant_ids, status, scheduled_start_at | functions/index.js; firestore.rules |
| support_tickets | Ticket headers | Support/admin/notifications | Owner/admin read/update; owner create; admin delete | ticket_number, user_id, status, subject, last_message | support_tickets_screen.dart |
| support_tickets/{ticketId}/messages | Ticket conversation messages | Support | Ticket owner/admin read/create with constraints | message, user_id, is_admin, attachments, created_at | support_tickets_screen.dart |
| community_documents | Admin-published community documents | Admin content | Admin read/write only by rules | community, title, description, file_url, status | admin_trading_operations.dart |


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

| Area/module | English support | Chinese support | Dynamic translation support | Missing translation risk |
| --- | --- | --- | --- | --- |
| App static UI | Yes through AppLocalizations.text() | Yes through _zh/_zhOverrides maps | No external dynamic translation required | Some const Text/admin strings remain English and need review. |
| Role names | AppRoles.name | AppRoles.localizedName via AppLocalizations.roleName | Static mapping | PowerShell output showed encoding issues, but source stores mappings in Dart. |
| Dynamic Firestore content | Model fallback to English strings | i18n maps, *_zh fields, translations.zh and fallback helpers | Cloud Functions and tools can backfill/regenerate Chinese | Coverage depends on seeded/transformed Firestore data. |
| Final exam content | Question snapshots and source maps | Bundled fallback and migrated i18n sidecars | Tools/tests support backfill/audit | Strongest covered area in tests. |
| Admin/publisher dialogs | Partly yes | Partly through l10n.text, but many const Text strings remain | Not automatic for hardcoded labels | High-priority localization gap for Chinese admin users. |
| Notification copy | Cloud Functions use English strings | No confirmed translated notification payloads | No dynamic notification localization found | Notification localization is a gap. |


## 9. Platform-Specific Notes

| Platform | Current support | Important config | Known issue/risk |
| --- | --- | --- | --- |
| Android | Configured and release-focused | applicationId/namespace com.hcgaming.hc_trader; google-services.json; POST_NOTIFICATIONS, INTERNET, VIBRATE; network security allows cleartext only to localhost/10.0.2.2; appbundle releases present. | Primary mobile target; Play Store versionCode managed via pubspec version build number. |
| Web | Configured | web/index.html, manifest.json and PWA icons exist; Firebase web config exists in firebase_options.dart. | PushNotificationService is not supported on web by service check; manifest description still generic. |
| Windows | Configured | windows runner/CMake files exist; Firebase windows web-style config exists; AuthRepository has HTTP callable fallback for Windows. | Messaging service skips Windows; Cloud Functions plugin/channel issues were previously worked around with HTTP fallback in auth repository. |
| iOS | Not confirmed / missing project folder | No ios/ folder found in repository scope. | Dependencies support iOS conceptually, but bundle id, entitlements, GoogleService-Info.plist and signing are absent. |
| macOS | Not confirmed / missing project folder | No macos/ folder found. | Push service includes macOS in code, but platform project/config is absent. |
| Linux | Not confirmed / missing project folder | No linux/ folder found. | No platform project/config found. |


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

- [x] Firebase-only mobile startup
- [x] Email and username sign-in
- [x] Registration with role and referral validation
- [x] Password reset and password change
- [x] Persistent English/Simplified Chinese locale
- [x] Role-aware HomeShell and More menu
- [x] Dashboard performance overview
- [x] Trading journal CRUD, import and attachments
- [x] Capital and trading account management
- [x] Trader onboarding / verification profile
- [x] Trading position applications
- [x] Trading Hub with eight content/workflow tabs
- [x] Market analysis publishing and detail view
- [x] Trading signal publishing and notifications
- [x] Trading blog/news/resource/recording/knowledge content
- [x] Password-gated recording/resource access
- [x] Daily trading exam
- [x] Final examination with localized fallback
- [x] Risk calculator
- [x] Statistics and analytics
- [x] Prop firm progress and admin review
- [x] Psychology score and warning module
- [x] Trading plan local draft
- [x] Readiness checklist
- [x] Trading appointments and slot booking
- [x] Support tickets with messages/attachments
- [x] Recruitment/downline referral workspace
- [x] Profile, avatar and security settings
- [x] Notification topic preferences and token sync
- [x] Admin overview dashboard
- [x] Admin users/roles management
- [x] Admin onboarding and position review
- [x] Admin content publishing
- [x] Admin generic resource CRUD/archive/restore
- [x] Admin journal and exam result review
- [x] Admin feature toggles
- [x] Admin audit log and delete requests
- [x] Admin performance, leaderboard and competition configuration
- [x] Firestore security rules and Storage validation
- [x] Cloud Functions registration, translation and notifications
- [x] Translation/backfill/audit tooling

## 12. Testing and Quality

Existing tests:

- test/trading_services_test.dart covers calculators, trading time conversion, analytics, report rules, risk, prop firm, psychology, role permissions, localization helpers and exam parsing/fallbacks.
- test/final_exam_localisation_audit_test.dart validates strict Chinese coverage for generated final exam content.
- functions/test/referral_registration.test.js exists for registration/referral behavior in Cloud Functions.

Commands run:

| Command | Result | Notes |
| --- | --- | --- |
| flutter analyze | Passed | No issues found. |
| flutter test | Passed | 29 tests passed. |
| dot -V | Failed/unavailable | Graphviz dot is not installed on PATH. |
| mmdc --version | Failed/unavailable | Mermaid CLI is not installed on PATH. |
| pandoc --version | Failed/unavailable | Pandoc is not installed on PATH. |
| python / py / python3 --version | Failed/unavailable | Python is not installed on PATH. |
| soffice --version | Failed/unavailable | LibreOffice is not installed on PATH. |


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

- Financial Department role was requested as an example but no Flutter role_id/name/module is implemented; functions/role_permissions.js explicitly leaves canInviteFinancialDepartment=false.
- AI assistant feature flag exists, but no Flutter AI assistant UI/client or Gemini integration was confirmed. Translation functions have been removed; Chinese content is manual.
- No ios/, macos/ or linux/ platform folders were found, so those platforms are not currently buildable from this project snapshot.
- No Flutter assets folder is declared in pubspec.yaml; only Material icons, Android launcher resources and Web PWA icons are confirmed.
- Many admin and publisher dialogs use hardcoded English const Text labels, so full Simplified Chinese coverage is not complete.
- Firestore indexes file is empty. Current queries may be valid, but future compound ordering/filtering can require indexes.
- The repository still contains a legacy ApiClient and cache/offline branches, but main.dart and README confirm firebaseEnabled=true and no Laravel runtime dependency.
- Document visual render QA could not be performed in this environment because Python, LibreOffice, Pandoc, Mermaid CLI and Graphviz were not available on PATH.

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

