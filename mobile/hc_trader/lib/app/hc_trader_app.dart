import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import '../core/localization/app_locale_controller.dart';
import '../core/localization/app_localizations.dart';
import '../core/storage/local_app_storage.dart';
import '../features/auth/auth_screen.dart';
import '../features/statistics/statistics_screen.dart';
import '../models/app_user.dart';
import '../repositories/auth_repository.dart';
import '../repositories/trading_repository.dart';
import 'home_shell.dart';

class HCTraderApp extends StatelessWidget {
  const HCTraderApp({
    super.key,
    required this.authRepository,
    required this.tradingRepository,
    required this.firebaseEnabled,
    required this.storage,
    required this.localeController,
  });

  final AuthRepository authRepository;
  final TradingRepository tradingRepository;
  final bool firebaseEnabled;
  final LocalAppStorage storage;
  final AppLocaleController localeController;

  @override
  Widget build(BuildContext context) {
    return AppLocaleScope(
      controller: localeController,
      child: AnimatedBuilder(
        animation: localeController,
        builder: (context, _) => MaterialApp(
          title: 'HC Trader',
          debugShowCheckedModeBanner: false,
          theme: _theme(Brightness.light),
          darkTheme: _theme(Brightness.dark),
          locale: localeController.locale,
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          home: _AuthGate(
            authRepository: authRepository,
            tradingRepository: tradingRepository,
            firebaseEnabled: firebaseEnabled,
            storage: storage,
          ),
        ),
      ),
    );
  }

  ThemeData _theme(Brightness brightness) {
    final isDark = brightness == Brightness.dark;
    final scheme =
        ColorScheme.fromSeed(
          seedColor: const Color(0xFF00796B),
          brightness: brightness,
        ).copyWith(
          secondary: const Color(0xFFD99A17),
          tertiary: const Color(0xFF1B5E8C),
          surface: isDark ? const Color(0xFF101418) : const Color(0xFFF7F9FA),
        );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: scheme.surface,
      cardTheme: CardThemeData(
        color: isDark ? const Color(0xFF171C20) : Colors.white,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: BorderSide(
            color: isDark ? const Color(0xFF293139) : const Color(0xFFE1E7EA),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? const Color(0xFF171C20) : Colors.white,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
      ),
      appBarTheme: AppBarTheme(
        centerTitle: false,
        backgroundColor: scheme.surface,
        surfaceTintColor: Colors.transparent,
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        backgroundColor: isDark ? const Color(0xFF171C20) : Colors.white,
        indicatorColor: scheme.primaryContainer,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
    );
  }
}

class _AuthGate extends StatefulWidget {
  const _AuthGate({
    required this.authRepository,
    required this.tradingRepository,
    required this.firebaseEnabled,
    required this.storage,
  });

  final AuthRepository authRepository;
  final TradingRepository tradingRepository;
  final bool firebaseEnabled;
  final LocalAppStorage storage;

  @override
  State<_AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<_AuthGate> {
  late Stream<AppUser?> _authStream;

  @override
  void initState() {
    super.initState();
    _authStream = widget.authRepository.authStateChanges();
  }

  @override
  void didUpdateWidget(covariant _AuthGate oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.authRepository != widget.authRepository) {
      _authStream = widget.authRepository.authStateChanges();
    }
  }

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<AppUser?>(
      stream: _authStream,
      initialData: widget.authRepository.currentUser,
      builder: (context, snapshot) {
        final user = snapshot.data;
        if (user == null) {
          final query = Uri.base.queryParameters;
          return AuthScreen(
            repository: widget.authRepository,
            firebaseEnabled: widget.firebaseEnabled,
            initialReferralCode:
                query['referral_code'] ?? query['ref'] ?? query['code'] ?? '',
            campaignId: query['campaign_id'] ?? query['utm_campaign'] ?? '',
            registrationSource:
                query['registration_source'] ?? query['source'] ?? 'direct',
            onOpenGuestAnalytics: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => StatisticsScreen(
                  user: const AppUser(
                    uid: 'guest-analytics',
                    email: '',
                    displayName: 'Guest analyst',
                    isDemo: true,
                  ),
                  repository: widget.tradingRepository,
                ),
              ),
            ),
          );
        }

        return HomeShell(
          key: ValueKey(user.uid),
          user: user,
          authRepository: widget.authRepository,
          tradingRepository: widget.tradingRepository,
          storage: widget.storage,
          firebaseEnabled: widget.firebaseEnabled,
        );
      },
    );
  }
}
