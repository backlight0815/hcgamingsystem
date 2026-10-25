import 'dart:convert';
import 'dart:io';

import 'package:hc_trader/features/multi_account_prop_firm/domain/prop_firm_models.dart';

void main(List<String> arguments) {
  if (arguments.length != 2) {
    stderr.writeln(
      'Usage: dart run tools/validate_local_prop_firm_portfolio.dart '
      '<shared_preferences.json> <preference-key>',
    );
    exitCode = 64;
    return;
  }

  final preferences = Map<String, dynamic>.from(
    jsonDecode(File(arguments[0]).readAsStringSync()) as Map,
  );
  final rawPortfolio = preferences[arguments[1]];
  if (rawPortfolio is! String || rawPortfolio.isEmpty) {
    throw const FormatException('Planner preference is missing or invalid.');
  }
  final portfolio = PropFirmPortfolio.fromJson(
    Map<String, dynamic>.from(jsonDecode(rawPortfolio) as Map),
  );

  stdout.writeln(
    jsonEncode({
      'valid': true,
      'schemaVersion': portfolio.schemaVersion,
      'currentSituationAssessed': portfolio.currentSituationAssessed,
      'accounts': [
        for (final account in portfolio.accounts)
          {
            'identifier': account.identifier,
            'firm': account.propFirmName,
            'accountCreatedAt': account.situation.accountCreationDate
                ?.toIso8601String(),
            'balance': account.currentBalance,
            'todayLoss': account.situation.lossAlreadyRecordedToday,
            'tradesToday': account.tradesToday,
            'payoutDate': account.nextPayoutDate?.toIso8601String(),
            'completedTradingDays': account.completedTradingDays,
            'minimumTradingDays': account.minimumTradingDays,
            'minimumPayout': account.minimumPayout,
          },
      ],
    }),
  );
}
