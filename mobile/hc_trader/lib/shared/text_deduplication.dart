String normalizedTextIdentity(String value) => value
    .trim()
    .toLowerCase()
    .replaceAll(RegExp(r'[^a-z0-9\u4e00-\u9fff]+'), ' ')
    .trim();

List<String> uniqueNormalizedText(Iterable<String> values) {
  final seen = <String>{};
  final result = <String>[];
  for (final value in values) {
    final text = value.trim();
    if (text.isEmpty) continue;
    if (seen.add(normalizedTextIdentity(text))) result.add(text);
  }
  return result;
}

List<String> excludingNormalizedText(
  Iterable<String> values,
  Iterable<String> excluded,
) {
  final blocked = excluded.map(normalizedTextIdentity).toSet();
  return uniqueNormalizedText(
    values.where((value) => !blocked.contains(normalizedTextIdentity(value))),
  );
}
