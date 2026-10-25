class AppRoles {
  const AppRoles._();

  static const names = <int, String>{
    1: 'Super Administrator',
    2: 'Sub Administrator',
    3: 'Founder',
    201: 'Signal Provider',
    202: 'Senior Signal Provider',
    501: 'Market Analyst',
    502: 'Signal Provider Management',
    700: 'Potential Client',
    750: 'Trader',
    760: 'Trading Leadership',
    770: 'Trading Recruiter',
    780: 'Admin Assistant',
    790: 'Financial Consultant',
    800: 'Evaluator',
  };

  static String name(int roleId) => names[roleId] ?? 'Member';

  static int idFromName(Object? value) {
    final normalized =
        value?.toString().trim().toLowerCase().replaceAll(
          RegExp(r'[\s_-]+'),
          '',
        ) ??
        '';
    return switch (normalized) {
      'superadmin' || 'superadministrator' => 1,
      'admin' || 'subadmin' || 'administrator' || 'subadministrator' => 2,
      'founder' => 3,
      'marketanalyst' => 501,
      'signalprovider' => 201,
      'seniorsignalprovider' => 202,
      'signalprovidermanagement' => 502,
      'leadclient' || 'potentialclient' || 'client' => 700,
      'trader' => 750,
      'tradingleadership' || 'leader' => 760,
      'tradingrecruiter' || 'recruiter' => 770,
      'assistant' || 'adminassistant' => 780,
      'financialconsultant' || 'financialdepartment' => 790,
      'evaluator' => 800,
      _ => 0,
    };
  }

  static String localizedName(int roleId, String languageCode) {
    if (languageCode != 'zh') return name(roleId);
    return switch (roleId) {
      1 => '超级管理员',
      2 => '管理员',
      3 => '创办人',
      201 => '交易信号提供者',
      202 => '高级交易信号提供者',
      501 => '市场分析师',
      502 => '交易信号管理',
      700 => '潜在客户',
      750 => '交易员',
      760 => '交易管理层',
      770 => '交易招聘员',
      780 => '行政助理',
      790 => '财务顾问',
      800 => '评估员',
      _ => '会员',
    };
  }
}
