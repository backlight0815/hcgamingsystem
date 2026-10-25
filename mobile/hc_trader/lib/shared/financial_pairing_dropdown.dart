import 'package:flutter/material.dart';

import '../core/localization/app_localizations.dart';
import '../models/trading_pair.dart';

class FinancialPairingDropdown extends StatelessWidget {
  const FinancialPairingDropdown({
    super.key,
    required this.pairs,
    required this.value,
    required this.onChanged,
    this.enabled = true,
  });

  final List<TradingPair> pairs;
  final String? value;
  final ValueChanged<String?> onChanged;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final options = financialPairingOptions(pairs, legacyValue: value);
    final selected = financialPairingSelection(options, value);
    return DropdownButtonFormField<String>(
      key: const ValueKey('financial-pairing-dropdown'),
      initialValue: selected,
      isExpanded: true,
      decoration: InputDecoration(
        labelText: context.l10n.text('Financial Pairing'),
        hintText: context.l10n.text('Select a financial pairing'),
        prefixIcon: const Icon(Icons.currency_exchange),
      ),
      items: [
        for (final pair in options)
          DropdownMenuItem<String>(
            value: pair.symbol,
            child: Text(
              pair.isLegacy
                  ? context.l10n.text('{value} (legacy value)', {
                      'value': pair.label,
                    })
                  : pair.label,
              overflow: TextOverflow.ellipsis,
            ),
          ),
      ],
      validator: (selection) {
        if ((selection ?? '').trim().isEmpty) {
          return context.l10n.text('Financial pairing is required');
        }
        if (options.any(
          (option) => option.symbol == selection && option.isLegacy,
        )) {
          return context.l10n.text('Choose a supported financial pairing');
        }
        return null;
      },
      onChanged: enabled ? onChanged : null,
    );
  }
}

class FinancialPairingOption {
  const FinancialPairingOption({
    required this.symbol,
    required this.label,
    this.isLegacy = false,
  });

  final String symbol;
  final String label;
  final bool isLegacy;
}

List<FinancialPairingOption> financialPairingOptions(
  List<TradingPair> pairs, {
  String? legacyValue,
}) {
  final bySymbol = <String, FinancialPairingOption>{};
  for (final pair in pairs) {
    final symbol = normalizeFinancialPairing(pair.symbol);
    if (symbol.isEmpty) continue;
    bySymbol.putIfAbsent(
      symbol,
      () => FinancialPairingOption(symbol: symbol, label: pair.displayName),
    );
  }
  final legacy = normalizeFinancialPairing(legacyValue);
  if (legacy.isNotEmpty && !bySymbol.containsKey(legacy)) {
    bySymbol[legacy] = FinancialPairingOption(
      symbol: legacy,
      label: legacyValue!.trim(),
      isLegacy: true,
    );
  }
  final options = bySymbol.values.toList(growable: false);
  options.sort((left, right) {
    if (left.isLegacy != right.isLegacy) return left.isLegacy ? -1 : 1;
    return left.symbol.compareTo(right.symbol);
  });
  return options;
}

String? financialPairingSelection(
  List<FinancialPairingOption> options,
  String? value,
) {
  final normalized = normalizeFinancialPairing(value);
  return options.any((option) => option.symbol == normalized)
      ? normalized
      : null;
}

String normalizeFinancialPairing(Object? value) =>
    '${value ?? ''}'.trim().toUpperCase();
