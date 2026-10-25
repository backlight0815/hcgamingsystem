import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/network/api_client.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/models/trading_blog_post.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:hc_trader/services/trading_blog_api_service.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  test('deserializes authoritative Laravel trading blog response', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      authTokenProvider: () async => 'firebase-token',
      httpClient: MockClient((request) async {
        expect(request.headers.containsKey('authorization'), isFalse);
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'blogs': [
                {
                  'id': '41',
                  'title': 'Price Action Basics',
                  'title_zh': '',
                  'title_i18n': {'zh': 'This value must be ignored'},
                  'slug': 'price-action-basics',
                  'category': 'knowledge_sharing',
                  'excerpt': 'A concise introduction.',
                  'content': '<p>Read structure first.</p>',
                  'cover_image': 'https://example.test/cover.jpg',
                  'tags': ['price action', 'structure'],
                  'status': 'published',
                  'is_featured': true,
                  'published_at': '2026-10-09T08:00:00Z',
                  'views': 12,
                  'reading_minutes': 4,
                  'source': 'WEBSITE',
                  'version': 3,
                  'author': {
                    'id': '9',
                    'name': 'HC Editor',
                    'username': 'editor',
                  },
                },
              ],
              'pagination': {'current_page': 1, 'last_page': 1},
            },
          }),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        );
      }),
    );

    final rows = await TradingBlogApiService(client).fetchBlogs();
    final blog = TradingBlogPost.fromJson(rows.single);

    expect(blog.id, '41');
    expect(blog.title, 'Price Action Basics');
    expect(blog.localizedTitle('zh'), 'Price Action Basics');
    expect(blog.titleZh, isEmpty);
    expect(blog.excerptZh, isEmpty);
    expect(blog.contentZh, isEmpty);
    expect(blog.tags, ['price action', 'structure']);
    expect(blog.isFeatured, isTrue);
    expect(blog.readingMinutes, 4);
    expect(blog.authorId, '9');
    expect(blog.authorLabel, 'editor');
    expect(blog.source, 'WEBSITE');
    expect(blog.version, 3);
    expect(blog.publishedAt, DateTime.parse('2026-10-09T08:00:00Z'));
  });

  test('loads complete blog detail from the website API', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient((request) async {
        expect(request.method, 'GET');
        expect(request.url.path, '/api/mobile/v1/blogs/41');
        expect(request.headers.containsKey('authorization'), isFalse);
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'blog': {
                'id': '41',
                'title': 'Price Action Basics',
                'content': '<h2>Market structure</h2><p>Read it first.</p>',
                'views': 13,
              },
            },
          }),
          200,
        );
      }),
    );

    final blog = await TradingBlogApiService(client).fetchBlog('41');

    expect(blog['views'], 13);
    expect(blog['content'], contains('<h2>Market structure</h2>'));
  });

  test('uses only the verified website field contract', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient((request) async {
        return http.Response(
          jsonEncode({
            'current_page': 1,
            'last_page': 1,
            'data': [
              {
                'id': 51,
                'title': 'Website-only fields',
                'excerpt': 'A short summary from the website form.',
                'content': '<p>Mobile-safe content.</p>',
                'category': 'risk_management',
                'tags': 'risk, discipline',
                'status': 'published',
                'published_at': '2026-10-09T09:30:00Z',
                'is_featured': 1,
                'cover_image': 'https://example.test/blog.jpg',
                'title_zh': '',
                'excerpt_zh': '',
                'content_zh': '',
              },
            ],
          }),
          200,
        );
      }),
    );

    final rows = await TradingBlogApiService(client).fetchBlogs();
    final blog = TradingBlogPost.fromJson(rows.single);

    expect(blog.id, '51');
    expect(blog.excerpt, 'A short summary from the website form.');
    expect(blog.tags, ['risk', 'discipline']);
    expect(blog.status, 'published');
    expect(blog.isFeatured, isTrue);
    expect(blog.coverImage, 'https://example.test/blog.jpg');
    expect(blog.titleZh, isEmpty);
    expect(blog.excerptZh, isEmpty);
    expect(blog.contentZh, isEmpty);
  });

  test('missing optional website text becomes empty strings', () {
    final blog = TradingBlogPost.fromJson({
      'id': '52',
      'title': 'English only',
      'tags': <Object?>[],
    });

    expect(blog.excerpt, isEmpty);
    expect(blog.content, isEmpty);
    expect(blog.coverImage, isEmpty);
    expect(blog.titleZh, isEmpty);
    expect(blog.excerptZh, isEmpty);
    expect(blog.contentZh, isEmpty);
    expect(blog.tags, isEmpty);
  });

  test(
    'accepts an empty website result without leaving loading state',
    () async {
      final client = ApiClient(
        baseUrl: 'https://example.test/api/mobile/v1',
        httpClient: MockClient((request) async {
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {
                'blogs': <Object?>[],
                'pagination': {'current_page': 1, 'last_page': 1},
              },
            }),
            200,
          );
        }),
      );

      expect(await TradingBlogApiService(client).fetchBlogs(), isEmpty);
    },
  );

  test('loads public blogs even when Firebase token retrieval stalls', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      authTokenTimeout: const Duration(milliseconds: 10),
      authTokenProvider: () => Completer<String?>().future,
      httpClient: MockClient((request) async {
        expect(request.headers.containsKey('authorization'), isFalse);
        return http.Response(
          jsonEncode({'success': true, 'data': {'blogs': <Object?>[]}}),
          200,
        );
      }),
    );

    expect(await TradingBlogApiService(client).fetchBlogs(), isEmpty);
  });

  for (final status in [400, 401, 403, 404, 422, 429, 500]) {
    test('surfaces HTTP $status as a request failure', () async {
      final client = ApiClient(
        baseUrl: 'https://example.test/api/mobile/v1',
        httpClient: MockClient((request) async {
          return http.Response(
            jsonEncode({'success': false, 'message': 'HTTP $status failure'}),
            status,
          );
        }),
      );

      expect(
        TradingBlogApiService(client).fetchBlogs(),
        throwsA(
          isA<ApiException>()
              .having((error) => error.statusCode, 'statusCode', status)
              .having(
                (error) => error.message,
                'message',
                status >= 500
                    ? 'The server is temporarily unavailable. Your saved records are safe. Please retry.'
                    : 'HTTP $status failure',
              ),
        ),
      );
    });
  }

  test('surfaces an HTTP timeout as a request failure', () {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      requestTimeout: const Duration(milliseconds: 10),
      httpClient: MockClient((request) async {
        await Future<void>.delayed(const Duration(seconds: 1));
        return http.Response('{}', 200);
      }),
    );

    expect(
      TradingBlogApiService(client).fetchBlogs(),
      throwsA(
        isA<ApiException>().having(
          (error) => error.message,
          'message',
          contains('timed out'),
        ),
      ),
    );
  });

  test('surfaces invalid JSON as a parsing failure', () {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient((request) async {
        return http.Response('<html>not json</html>', 200);
      }),
    );

    expect(
      TradingBlogApiService(client).fetchBlogs(),
      throwsA(
        isA<ApiException>().having(
          (error) => error.message,
          'message',
          contains('unreadable response'),
        ),
      ),
    );
  });

  test('merges partial detail fields without clearing cached list values', () {
    final cached = TradingBlogPost.fromJson({
      'id': '61',
      'title': 'Cached title',
      'excerpt': 'Cached summary',
      'content': '<p>Cached article</p>',
      'category': 'market_outlook',
      'tags': ['markets'],
      'status': 'published',
      'views': 3,
    });

    final refreshed = cached.mergeJson({'views': 4});

    expect(refreshed.id, cached.id);
    expect(refreshed.title, cached.title);
    expect(refreshed.content, cached.content);
    expect(refreshed.tags, cached.tags);
    expect(refreshed.views, 4);
  });

  test('publishes a blog with authentication and idempotency', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      authTokenProvider: () async => 'firebase-token',
      httpClient: MockClient((request) async {
        expect(request.method, 'POST');
        expect(request.url.path, '/api/mobile/v1/blogs');
        expect(request.headers['authorization'], 'Bearer firebase-token');
        expect(request.headers['x-idempotency-key'], 'blog-request-1');
        final body = jsonDecode(request.body) as Map<String, dynamic>;
        expect(body['title'], 'Price Action Basics');
        expect(body['source'], isNull);
        expect(body['author_id'], isNull);
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'blog': {'id': '42'},
              'idempotent_replay': false,
            },
          }),
          201,
        );
      }),
    );

    await TradingBlogApiService(client).submitBlog(
      idempotencyKey: 'blog-request-1',
      payload: const {
        'title': 'Price Action Basics',
        'category': 'knowledge_sharing',
        'content': 'Read structure first.',
        'status': 'published',
      },
    );
  });

  test('retrieves every blog page for offline recovery', () async {
    final requestedPages = <String?>[];
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient((request) async {
        final page = request.url.queryParameters['page'];
        requestedPages.add(page);
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'blogs': [
                {'id': page, 'title': 'Blog $page'},
              ],
              'pagination': {'current_page': int.parse(page!), 'last_page': 2},
            },
          }),
          200,
        );
      }),
    );

    final blogs = await TradingBlogApiService(client).fetchBlogs();

    expect(requestedPages, ['1', '2']);
    expect(blogs.map((blog) => blog['id']), ['1', '2']);
  });

  test(
    'reads dynamic category labels, counts, and synchronization time',
    () async {
      final client = ApiClient(
        baseUrl: 'https://example.test/api/mobile/v1',
        httpClient: MockClient((request) async {
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {
                'blogs': [
                  {
                    'id': '91',
                    'title': 'Risk controls',
                    'category': 'risk_management',
                    'category_label': 'Capital Protection',
                  },
                ],
                'categories': {
                  'risk_management': 'Capital Protection',
                  'market_outlook': 'Market Outlook',
                },
                'category_counts': {'risk_management': 1, 'market_outlook': 0},
                'pagination': {'current_page': 1, 'last_page': 1},
                'synced_at': '2026-10-09T09:30:00Z',
              },
            }),
            200,
          );
        }),
      );

      final snapshot = await TradingBlogApiService(client).fetchSnapshot();
      final blog = TradingBlogPost.fromJson(snapshot.blogs.single);

      expect(snapshot.categories['risk_management'], 'Capital Protection');
      expect(snapshot.categoryCounts['market_outlook'], 0);
      expect(snapshot.syncedAt, DateTime.parse('2026-10-09T09:30:00Z'));
      expect(blog.categoryLabel, 'Capital Protection');
    },
  );

  test('deduplicates posts repeated across pagination boundaries', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient((request) async {
        final page = int.parse(request.url.queryParameters['page']!);
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'blogs': [
                {'id': 'same', 'title': 'Version $page'},
              ],
              'pagination': {'current_page': page, 'last_page': 2},
            },
          }),
          200,
        );
      }),
    );

    final blogs = await TradingBlogApiService(client).fetchBlogs();

    expect(blogs, hasLength(1));
    expect(blogs.single['title'], 'Version 2');
  });

  test('resolves relative website image paths against the domain root', () {
    final client = ApiClient(
      baseUrl: 'https://www.hctradinghub.com/api/mobile/v1',
    );

    expect(
      client.resolveUrl('upload/trading_blogs/cover.webp'),
      'https://www.hctradinghub.com/upload/trading_blogs/cover.webp',
    );
    expect(
      client.resolveUrl('/storage/blogs/cover.webp'),
      'https://www.hctradinghub.com/storage/blogs/cover.webp',
    );
  });

  test('labels saved posts as fallback after an API refresh failure', () async {
    SharedPreferences.setMockInitialValues({});
    final storage = await LocalAppStorage.create();
    var requestCount = 0;
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient((request) async {
        requestCount++;
        if (requestCount > 1) {
          return http.Response(
            jsonEncode({'message': 'Service unavailable'}),
            503,
          );
        }
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'blogs': [
                {
                  'id': 'cached-blog',
                  'title': 'Cached blog',
                  'category': 'risk_management',
                  'status': 'published',
                },
              ],
              'categories': {'risk_management': 'Risk Management'},
              'category_counts': {'risk_management': 1},
              'pagination': {'current_page': 1, 'last_page': 1},
              'synced_at': DateTime.now().toUtc().toIso8601String(),
            },
          }),
          200,
        );
      }),
    );
    final repository = TradingRepository(
      firebaseEnabled: true,
      apiClient: client,
      storage: storage,
    );

    await repository.refreshTradingBlogs();
    expect(repository.tradingBlogsUsingCachedFallback, isFalse);
    expect(repository.tradingBlogCategoryCounts['risk_management'], 1);

    await repository.refreshTradingBlogs();
    expect(repository.tradingBlogsUsingCachedFallback, isTrue);
    expect(
      repository.tradingBlogSyncErrorMessage,
      'The trading blog server failed (HTTP 503). Please try again later.',
    );
  });

  test('reports a legacy blog 401 without ending the app session', () async {
    SharedPreferences.setMockInitialValues({});
    final storage = await LocalAppStorage.create();
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      authTokenProvider: () async => 'firebase-reader-token',
      httpClient: MockClient((request) async => http.Response(
        jsonEncode({'success': false, 'message': 'Unauthenticated.'}),
        401,
      )),
    );
    var sessionEnded = false;
    client.onUnauthorized = () => sessionEnded = true;
    final repository = TradingRepository(
      firebaseEnabled: true,
      apiClient: client,
      storage: storage,
    );

    await repository.refreshTradingBlogs();

    expect(
      repository.tradingBlogSyncErrorMessage,
      contains('HTTP 401'),
    );
    expect(sessionEnded, isFalse);
  });

  test('reports incompatible JSON as a parsing failure', () async {
    SharedPreferences.setMockInitialValues({});
    final storage = await LocalAppStorage.create();
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient((request) async {
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'blogs': [
                {'id': 'missing-title'},
              ],
              'pagination': {'current_page': 1, 'last_page': 1},
            },
          }),
          200,
        );
      }),
    );
    final repository = TradingRepository(
      firebaseEnabled: true,
      apiClient: client,
      storage: storage,
    );

    await repository.refreshTradingBlogs();

    expect(repository.tradingBlogsUsingCachedFallback, isFalse);
    expect(
      repository.tradingBlogSyncErrorMessage,
      'Trading blog data could not be read. Please try again.',
    );
  });

  test('receives a realtime blog change hint', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      authTokenProvider: () async => 'firebase-token',
      httpClient: MockClient((request) async {
        expect(request.url.path, '/api/mobile/v1/blogs/stream');
        expect(request.headers['accept'], 'text/event-stream');
        expect(request.headers.containsKey('authorization'), isFalse);
        return http.Response(
          'event: trading.blog.changed\n'
          'data: {"event":"trading.blog.changed"}\n\n',
          200,
        );
      }),
    );

    final event = await TradingBlogApiService(client).watchChanges().first;

    expect(event['event'], 'trading.blog.changed');
  });
}
