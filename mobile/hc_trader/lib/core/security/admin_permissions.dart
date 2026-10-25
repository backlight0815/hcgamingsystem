import '../../models/app_user.dart';
import 'app_role_permissions.dart';

enum AdminPermission {
  dashboardView('admin.dashboard.view'),
  featureManage('feature.manage'),
  auditView('audit.view'),
  userView('account_management.view'),
  userManage('account_management.manage'),
  marketingView('marketing_resource.view'),
  marketingCreate('marketing_resource.create'),
  marketingEdit('marketing_resource.edit'),
  marketingDelete('marketing_resource.delete'),
  marketingReviewDelete('marketing_resource.review_delete'),
  ticketView('ticket.view'),
  ticketReply('ticket.reply'),
  ticketClose('ticket.close'),
  ticketReopen('ticket.reopen'),
  ticketAssign('ticket.assign'),
  ticketDelete('ticket.delete'),
  appointmentView('appointment_slot.view'),
  appointmentManage('appointment_slot.manage'),
  examView('trading_exam.view'),
  examManage('trading_exam.manage'),
  examResultReview('trading_exam.result_review'),
  marketAnalysisView('market_analyst.view'),
  marketAnalysisCreate('market_analyst.create'),
  marketAnalysisEdit('market_analyst.edit'),
  marketAnalysisRequestDelete('market_analyst.request_delete'),
  marketAnalysisReviewDelete('market_analyst.review_delete'),
  knowledgeView('knowledge_centre.view'),
  knowledgeManage('knowledge_centre.manage'),
  signalView('trading_signal.view'),
  signalCreate('trading_signal.create'),
  signalEdit('trading_signal.edit'),
  signalDelete('trading_signal.delete'),
  signalProviderManage('signal_provider.manage'),
  newsView('trading_news.view'),
  newsManage('trading_news.manage'),
  journalManage('trading_journal.manage'),
  recordingManage('recording_class.manage'),
  blogManage('trading_blog.manage'),
  performanceManage('traders_performance.manage'),
  propFirmConfigManage('prop_firm_config.manage'),
  propFirmResultReview('prop_firm_result.review');

  const AdminPermission(this.key);
  final String key;
}

extension AppUserAdministration on AppUser {
  bool get isSuperAdmin =>
      effectiveRoleIds.contains(RolePermissionMatrix.superAdmin);

  bool hasPermission(AdminPermission permission) {
    final override = permissionOverrides[permission.key];
    if (override == false) return false;
    if (override == true) return true;
    final roles = effectiveRoleIds;
    if (roles.contains(RolePermissionMatrix.superAdmin)) return true;
    if (roles.contains(RolePermissionMatrix.subAdmin)) return true;
    if (roles.contains(RolePermissionMatrix.founder)) return true;
    if (roles.contains(501)) {
      return const {
        AdminPermission.marketAnalysisView,
        AdminPermission.marketAnalysisCreate,
        AdminPermission.marketAnalysisEdit,
        AdminPermission.marketAnalysisRequestDelete,
        AdminPermission.knowledgeView,
        AdminPermission.newsView,
      }.contains(permission);
    }
    if (roles.any(const {201, 202}.contains)) {
      return const {
        AdminPermission.signalView,
        AdminPermission.signalCreate,
        AdminPermission.signalEdit,
      }.contains(permission);
    }
    if (roles.contains(502)) {
      return const {
        AdminPermission.signalView,
        AdminPermission.signalCreate,
        AdminPermission.signalEdit,
        AdminPermission.signalDelete,
        AdminPermission.signalProviderManage,
      }.contains(permission);
    }
    return false;
  }

  bool canInviteRole(int targetRoleId) => effectiveRoleIds.any(
    (roleId) => RolePermissionMatrix.canInviteRole(roleId, targetRoleId),
  );
}

const tradingFeatureDefaults = <String, bool>{
  'trading': true,
  'marketing_resources': true,
  'support_tickets': true,
  'ai_assistant': true,
  'appointments': true,
  'trading_exams': true,
  'notifications': true,
  'community': true,
  'trading_news': true,
  'signal_provider_desk': true,
  'trading_certificates': true,
  'knowledge_centre': true,
  'market_analyst': true,
  'trader_centre': true,
  'trading_signals': true,
  'leaderboard': true,
  'recording_classes': true,
  'trading_blog': true,
  'trading_pair': true,
  'traders_performance': true,
  'funded_traders': true,
  'prop_firm_config': true,
  'dealership_ecommerce': false,
};
