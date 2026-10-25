import 'package:flutter/material.dart';

import '../core/localization/app_localizations.dart';

class AppBackButton extends StatelessWidget {
  const AppBackButton({super.key, this.fallback});

  final VoidCallback? fallback;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: context.l10n.text('Back'),
      onPressed: () => safeBack(context, fallback: fallback),
      icon: const Icon(Icons.arrow_back),
    );
  }
}

void safeBack(BuildContext context, {VoidCallback? fallback}) {
  final navigator = Navigator.of(context);
  if (navigator.canPop()) {
    navigator.pop();
    return;
  }

  if (fallback != null) {
    fallback();
    return;
  }

  navigator.pushNamedAndRemoveUntil('/', (route) => false);
}
