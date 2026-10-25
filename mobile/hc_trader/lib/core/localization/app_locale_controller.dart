import 'package:flutter/material.dart';

import '../storage/local_app_storage.dart';

class AppLocaleController extends ChangeNotifier {
  AppLocaleController._(
    this._storage,
    this._locale, {
    required bool usesDeviceLocale,
  }) : _usesDeviceLocale = usesDeviceLocale {
    WidgetsBinding.instance.addObserver(_localeObserver);
  }

  static const _storageKey = 'locale';
  final LocalAppStorage _storage;
  Locale _locale;
  bool _usesDeviceLocale;
  late final WidgetsBindingObserver _localeObserver = _DeviceLocaleObserver(
    _handleDeviceLocalesChanged,
  );

  Locale get locale => _locale;

  static AppLocaleController create(
    LocalAppStorage storage, {
    List<Locale>? deviceLocales,
  }) {
    final savedCode = storage.readString(_storageKey);
    final hasSavedPreference = savedCode == 'en' || savedCode == 'zh';
    final locale = hasSavedPreference
        ? Locale(savedCode!)
        : _supportedDeviceLocale(
            deviceLocales ?? WidgetsBinding.instance.platformDispatcher.locales,
          );
    return AppLocaleController._(
      storage,
      locale,
      usesDeviceLocale: !hasSavedPreference,
    );
  }

  Future<void> setLanguage(String languageCode) async {
    final normalized = languageCode == 'zh' ? 'zh' : 'en';
    _usesDeviceLocale = false;
    if (_locale.languageCode == normalized) {
      await _storage.writeString(_storageKey, normalized);
      return;
    }
    _locale = Locale(normalized);
    notifyListeners();
    await _storage.writeString(_storageKey, normalized);
  }

  static Locale _supportedDeviceLocale(Iterable<Locale> locales) {
    for (final locale in locales) {
      if (locale.languageCode.toLowerCase() == 'zh') {
        return const Locale('zh');
      }
      if (locale.languageCode.toLowerCase() == 'en') {
        return const Locale('en');
      }
    }
    return const Locale('en');
  }

  void _handleDeviceLocalesChanged(List<Locale>? locales) {
    if (!_usesDeviceLocale) return;
    final next = _supportedDeviceLocale(locales ?? const <Locale>[]);
    if (next.languageCode == _locale.languageCode) return;
    _locale = next;
    notifyListeners();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(_localeObserver);
    super.dispose();
  }

  static AppLocaleController of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AppLocaleScope>();
    assert(scope != null, 'AppLocaleScope is missing above this context.');
    return scope!.notifier!;
  }
}

class _DeviceLocaleObserver extends WidgetsBindingObserver {
  _DeviceLocaleObserver(this.onLocalesChanged);

  final ValueChanged<List<Locale>?> onLocalesChanged;

  @override
  void didChangeLocales(List<Locale>? locales) => onLocalesChanged(locales);
}

class AppLocaleScope extends InheritedNotifier<AppLocaleController> {
  const AppLocaleScope({
    super.key,
    required AppLocaleController controller,
    required super.child,
  }) : super(notifier: controller);
}
