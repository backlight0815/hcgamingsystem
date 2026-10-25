import 'prop_firm_models.dart';
import 'account_daily_trade_limit_service.dart';

class AccountValidationService {
  const AccountValidationService();

  void validate(PropFirmAccount account) {
    if (account.name.trim().isEmpty) {
      throw const FormatException('Account name is required.');
    }
    if (account.identifier.trim().isEmpty) {
      throw const FormatException('Account number is required.');
    }
    if (account.currency.trim().isEmpty) {
      throw const FormatException('Currency is required.');
    }
    final values = [
      account.accountSize,
      account.currentBalance,
      account.referenceEquity,
      account.dailyLossLimit,
      account.maximumLossLimit,
    ];
    if (values.any((value) => !value.isFinite) ||
        account.accountSize <= 0 ||
        account.dailyLossLimit <= 0 ||
        account.maximumLossLimit <= 0) {
      throw const FormatException('Account financial values are invalid.');
    }
    if ((account.dailyLossType == LossLimitType.percentage &&
            account.dailyLossLimit > 100) ||
        (account.maximumLossType == LossLimitType.percentage &&
            account.maximumLossLimit > 100)) {
      throw const FormatException('Percentage loss limits cannot exceed 100%.');
    }
    if (account.dailyTradeLimitEnabled &&
        (account.maxTradesPerDay < 1 ||
            account.maxTradesPerDay >
                AccountDailyTradeLimitService.maximumSafeLimit)) {
      throw const FormatException(
        'Maximum trades per day must be a whole number from 1 to 100.',
      );
    }
    final dailyAmount = _limitAmount(
      account.accountSize,
      account.dailyLossType,
      account.dailyLossLimit,
    );
    final maximumAmount = _limitAmount(
      account.accountSize,
      account.maximumLossType,
      account.maximumLossLimit,
    );
    if (dailyAmount > maximumAmount) {
      throw const FormatException(
        'Daily loss limit cannot exceed the maximum loss limit.',
      );
    }
    if (account.isFundedAccount) {
      if (!account.minimumPayout.isFinite || account.minimumPayout < 0) {
        throw const FormatException('Minimum payout amount is invalid.');
      }
    } else if (!account.profitTarget.isFinite || account.profitTarget <= 0) {
      throw const FormatException(
        'Evaluation accounts require a profit target above zero.',
      );
    }
  }

  double normalizeResult(TradeResultType type, double enteredAmount) {
    if (!enteredAmount.isFinite) {
      throw const FormatException('Enter a valid result amount.');
    }
    return switch (type) {
      TradeResultType.fullTp when enteredAmount < 0 =>
        throw const FormatException('Take Profit cannot be negative.'),
      TradeResultType.fullSl when enteredAmount <= 0 =>
        throw const FormatException('Stop Loss must be above zero.'),
      TradeResultType.fullSl => -enteredAmount.abs(),
      TradeResultType.breakeven => enteredAmount,
      _ => enteredAmount,
    };
  }

  double _limitAmount(double capital, LossLimitType type, double value) =>
      type == LossLimitType.percentage ? capital * value / 100 : value;
}
