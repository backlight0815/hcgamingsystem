import 'localized_value.dart';

enum PublishingLanguage {
  english('en'),
  simplifiedChinese('zh');

  const PublishingLanguage(this.code);
  final String code;

  PublishingLanguage get secondary =>
      this == english ? simplifiedChinese : english;
}

PublishingLanguage publishingLanguageForLocale(Object? locale) =>
    isChineseLocale(locale)
    ? PublishingLanguage.simplifiedChinese
    : PublishingLanguage.english;

String publishingPrimaryValue({
  required PublishingLanguage language,
  required Object? english,
  required Object? chinese,
}) =>
    (language == PublishingLanguage.simplifiedChinese ? chinese : english)
        ?.toString()
        .trim() ??
    '';

Map<String, String> publishingLocalizedMap({
  required Object? english,
  required Object? chinese,
}) {
  final en = english?.toString().trim() ?? '';
  final zh = chinese?.toString().trim() ?? '';
  return {if (en.isNotEmpty) 'en': en, if (zh.isNotEmpty) 'zh': zh};
}

List<String> publishingAvailableLanguages(
  Iterable<Map<String, String>> localizedFields,
) {
  final available = <String>{};
  for (final field in localizedFields) {
    for (final entry in field.entries) {
      if (entry.value.trim().isNotEmpty) available.add(entry.key);
    }
  }
  return [
    for (final code in const ['en', 'zh'])
      if (available.contains(code)) code,
  ];
}

bool hasCompletePrimaryLocalization({
  required PublishingLanguage language,
  required Iterable<({Object? english, Object? chinese})> requiredFields,
}) => requiredFields.every(
  (field) => publishingPrimaryValue(
    language: language,
    english: field.english,
    chinese: field.chinese,
  ).isNotEmpty,
);
