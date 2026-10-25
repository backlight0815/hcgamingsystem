import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/hub/trading_hub_screen.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/trading_blog_post.dart';
import 'package:hc_trader/repositories/auth_repository.dart';
import 'package:hc_trader/repositories/trading_repository.dart';

class _FakeAuthRepository extends Fake implements AuthRepository {}

class _BlogScreenRepository extends TradingRepository {
  _BlogScreenRepository({
    required this.blogs,
    this.categoryLabels = const {},
    this.categoryCounts = const {},
    this.usingFallback = false,
    this.syncError,
  }) : super(firebaseEnabled: false);

  final List<TradingBlogPost> blogs;
  final Map<String, String> categoryLabels;
  final Map<String, int> categoryCounts;
  final bool usingFallback;
  final String? syncError;
  int refreshCount = 0;

  @override
  Stream<List<TradingBlogPost>> tradingBlogsStream() => Stream.value(blogs);

  @override
  Future<void> refreshTradingBlogs() async {
    refreshCount++;
  }

  @override
  Map<String, String> get tradingBlogCategoryLabels => categoryLabels;

  @override
  Map<String, int> get tradingBlogCategoryCounts => categoryCounts;

  @override
  bool get tradingBlogsUsingCachedFallback => usingFallback;

  @override
  String? get tradingBlogSyncErrorMessage => syncError;

  @override
  bool get tradingBlogCacheExpired => true;
}

const _reader = AppUser(
  uid: 'reader-1',
  email: 'reader@example.test',
  roleId: 750,
);

final _blogs = <TradingBlogPost>[
  TradingBlogPost(
    id: 'risk-1',
    title: 'Risk checklist',
    category: 'risk_management',
    categoryLabelValue: 'Capital Protection',
    excerpt: 'Protect the account before seeking returns.',
    content: '<h2>Daily limit</h2><p>Keep risk fixed.</p>',
    tags: const ['risk', 'discipline'],
    status: 'published',
    isFeatured: true,
    publishedAt: DateTime.utc(2026, 10, 9, 8),
  ),
  TradingBlogPost(
    id: 'mindset-1',
    title: 'Mindset reset',
    category: 'psychology_sharing',
    categoryLabelValue: 'Trading Psychology',
    excerpt: 'Reset after a loss.',
    content: '<p>Pause and review the setup.</p>',
    tags: const ['psychology'],
    status: 'published',
    publishedAt: DateTime.utc(2026, 10, 8, 8),
  ),
];

Widget _app(
  _BlogScreenRepository repository, {
  Locale locale = const Locale('en'),
}) => MaterialApp(
  locale: locale,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  supportedLocales: AppLocalizations.supportedLocales,
  home: Scaffold(
    body: TradingHubScreen(
      user: _reader,
      repository: repository,
      authRepository: _FakeAuthRepository(),
      publicOnly: true,
    ),
  ),
);

void main() {
  setUp(() {
    TestWidgetsFlutterBinding.ensureInitialized();
  });

  testWidgets('renders API blogs and combines category and search filters', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(1200, 1000));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final repository = _BlogScreenRepository(
      blogs: _blogs,
      categoryLabels: const {
        'risk_management': 'Capital Protection',
        'psychology_sharing': 'Trading Psychology',
        'market_outlook': 'Market Outlook',
      },
      categoryCounts: const {
        'risk_management': 1,
        'psychology_sharing': 1,
        'market_outlook': 0,
      },
    );

    await tester.pumpWidget(_app(repository));
    await tester.pumpAndSettle();

    expect(find.text('Risk checklist'), findsOneWidget);
    expect(find.text('Mindset reset'), findsOneWidget);
    expect(find.text('Trading Psychology (1)'), findsOneWidget);
    expect(find.text('Market Outlook (0)'), findsOneWidget);

    await tester.tap(find.text('Trading Psychology (1)'));
    await tester.pump();
    expect(find.text('Mindset reset'), findsOneWidget);
    expect(find.text('Risk checklist'), findsNothing);

    await tester.enterText(find.byType(TextField), 'not present');
    await tester.pump();
    expect(find.text('No blog posts match your filters'), findsOneWidget);

    await tester.tap(find.text('All'));
    await tester.enterText(find.byType(TextField), 'risk');
    await tester.pump();
    expect(find.text('Risk checklist'), findsOneWidget);

    await tester.tap(find.text('Read Featured Post'));
    await tester.pumpAndSettle();
    expect(find.text('Article'), findsOneWidget);
    final article = tester.widget<HtmlWidget>(find.byType(HtmlWidget));
    expect(article.html, contains('Daily limit'));
    expect(article.html, contains('Keep risk fixed.'));
  });

  testWidgets('pull to refresh triggers a genuine repository refresh', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(800, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final repository = _BlogScreenRepository(blogs: _blogs);

    await tester.pumpWidget(_app(repository));
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView).first, const Offset(0, 350));
    await tester.pumpAndSettle();

    expect(repository.refreshCount, 1);
  });

  testWidgets('renders website English content for a Chinese device locale', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(1000, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final repository = _BlogScreenRepository(blogs: _blogs);

    await tester.pumpWidget(_app(repository, locale: const Locale('zh', 'CN')));
    await tester.pumpAndSettle();

    expect(find.text('Risk checklist'), findsOneWidget);
    await tester.tap(find.byIcon(Icons.menu_book_outlined));
    await tester.pumpAndSettle();
    final article = tester.widget<HtmlWidget>(find.byType(HtmlWidget));
    expect(article.html, contains('Keep risk fixed.'));
  });

  testWidgets('labels cached fallback and exposes retry', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1000, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final repository = _BlogScreenRepository(
      blogs: _blogs,
      usingFallback: true,
      syncError: 'Unable to load trading blogs. Please try again.',
    );

    await tester.pumpWidget(_app(repository));
    await tester.pumpAndSettle();

    expect(find.textContaining('Showing saved posts'), findsOneWidget);
    await tester.tap(find.text('Retry'));
    await tester.pump();
    expect(repository.refreshCount, 1);
  });
}
