import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/admin/admin_trading_operations.dart';
import 'package:hc_trader/repositories/trading_repository.dart';

void main() {
  testWidgets('community documents section and add dialog use Chinese', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('zh'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: Scaffold(body: AdminContentTab(repository: _FakeRepository())),
      ),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('社群文件'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('管理员控制的文件 0 个'), findsOneWidget);
    expect(find.text('尚未发布社群文件。'), findsOneWidget);

    await tester.tap(find.text('新增').last);
    await tester.pumpAndSettle();

    for (final text in const [
      '发布社群文件',
      '社群',
      '上传图片或 PDF',
      'PDF 最大 10 MB，图片最大 8 MB',
      '标题',
      '说明',
      '安全文件链接',
      '选择上方文件后，此项可留空。',
      '启用文件',
      '取消',
      '保存',
    ]) {
      expect(find.text(text), findsOneWidget, reason: 'Missing "$text"');
    }

    expect(find.text('Publish community document'), findsNothing);
    expect(find.text('Upload image or PDF'), findsNothing);
  });
}

class _FakeRepository extends TradingRepository {
  _FakeRepository() : super(firebaseEnabled: false);

  @override
  Future<Map<String, dynamic>> adminContent() async => {
    'recordings': const <Map<String, dynamic>>[],
    'knowledge': const <Map<String, dynamic>>[],
    'community_documents': const <Map<String, dynamic>>[],
  };
}
