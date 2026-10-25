import 'package:flutter_test/flutter_test.dart';

/// Analytics uses compute(), whose isolate cannot finish in fake test time.
Future<void> pumpAnalytics(WidgetTester tester, Finder header) async {
  for (var attempt = 0; attempt < 100 && header.evaluate().isEmpty; attempt++) {
    await tester.runAsync(
      () => Future<void>.delayed(const Duration(milliseconds: 20)),
    );
    await tester.pump();
  }
}
