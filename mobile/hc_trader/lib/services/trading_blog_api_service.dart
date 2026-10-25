import 'package:flutter/foundation.dart';

import '../core/network/api_client.dart';

class TradingBlogApiService {
  const TradingBlogApiService(this._client);

  final ApiClient _client;

  Future<List<Map<String, dynamic>>> fetchBlogs({int perPage = 100}) async {
    return (await fetchSnapshot(perPage: perPage)).blogs;
  }

  Future<TradingBlogApiSnapshot> fetchSnapshot({int perPage = 100}) async {
    final stopwatch = Stopwatch()..start();
    final blogsById = <String, Map<String, dynamic>>{};
    final categories = <String, String>{};
    final categoryCounts = <String, int>{};
    DateTime? syncedAt;
    var page = 1;
    var lastPage = 1;
    try {
      do {
        final response = await _client.get(
          'blogs?per_page=$perPage&page=$page',
          authenticate: false,
        );
        if (response['success'] == false) {
          throw ApiException(
            (response['message'] ?? 'The blog request was not successful.')
                .toString(),
          );
        }
        final pageData = _blogPage(response);
        if (pageData == null) {
          throw const ApiException(
            'The server returned an invalid trading blog response.',
          );
        }
        for (final item in pageData.items.whereType<Map>()) {
          final blog = Map<String, dynamic>.from(item);
          final id = (blog['id'] ?? blog['blog_id'] ?? blog['post_id'])
              ?.toString()
              .trim();
          final identity = id?.isNotEmpty == true
              ? id!
              : '__anonymous_${page}_${blogsById.length}';
          blogsById[identity] = blog;
        }
        if (page == 1) {
          final metadata = _metadata(response);
          categories.addAll(_categoryLabels(metadata));
          categoryCounts.addAll(_categoryCounts(metadata));
          syncedAt = DateTime.tryParse(
            (metadata['synced_at'] ?? '').toString(),
          );
        }
        final pagination = pageData.pagination;
        lastPage = pagination is Map
            ? _positiveInt(pagination['last_page'], fallback: page)
            : page;
        if (lastPage > 1000) {
          throw const ApiException(
            'The server returned invalid trading blog pagination.',
          );
        }
        page++;
      } while (page <= lastPage);

      final snapshot = TradingBlogApiSnapshot(
        blogs: List<Map<String, dynamic>>.unmodifiable(blogsById.values),
        categories: Map<String, String>.unmodifiable(categories),
        categoryCounts: Map<String, int>.unmodifiable(categoryCounts),
        syncedAt: syncedAt,
      );
      if (kDebugMode) {
        debugPrint(
          '[HC Blog API] sync completed: ${snapshot.blogs.length} posts, '
          '${stopwatch.elapsedMilliseconds}ms',
        );
      }
      return snapshot;
    } on Object catch (error) {
      if (kDebugMode) {
        debugPrint(
          '[HC Blog API] sync failed after ${stopwatch.elapsedMilliseconds}ms: '
          '$error',
        );
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> submitBlog({
    required Map<String, Object?> payload,
    required String idempotencyKey,
  }) {
    return _client.post(
      'blogs',
      body: payload,
      headers: {'X-Idempotency-Key': idempotencyKey},
    );
  }

  Future<Map<String, dynamic>> fetchBlog(String blogId) async {
    final response = await _client.get(
      'blogs/${Uri.encodeComponent(blogId.trim())}',
      authenticate: false,
    );
    if (response['success'] == false) {
      throw ApiException(
        (response['message'] ?? 'The blog request was not successful.')
            .toString(),
      );
    }
    final blog = _blogDetail(response);
    if (blog == null) {
      throw const ApiException(
        'The server returned an invalid trading blog detail response.',
      );
    }
    return blog;
  }

  Future<String> uploadImage(ApiUploadFile file) async {
    final response = await _client.postMultipart(
      'blogs/images',
      files: [
        ApiUploadFile(
          field: 'image',
          filename: file.filename,
          path: file.path,
          bytes: file.bytes,
        ),
      ],
    );
    final data = response['data'];
    final url = data is Map ? data['url']?.toString().trim() : null;
    if (url == null || url.isEmpty) {
      throw const ApiException(
        'The server returned an invalid trading blog image response.',
      );
    }
    return url;
  }

  Stream<Map<String, dynamic>> watchChanges() async* {
    while (true) {
      try {
        await for (final event in _client.eventStream('blogs/stream', authenticate: false)) {
          yield event;
        }
      } on ApiException {
        // Keep the last authoritative snapshot visible while reconnecting.
      }
      await Future<void>.delayed(const Duration(seconds: 3));
    }
  }

  static int _positiveInt(Object? value, {required int fallback}) {
    final parsed = value is num ? value.toInt() : int.tryParse('$value');
    return parsed != null && parsed > 0 ? parsed : fallback;
  }

  static Map<dynamic, dynamic> _metadata(Map<String, dynamic> response) {
    final data = response['data'];
    return data is Map ? data : response;
  }

  static Map<String, String> _categoryLabels(Map<dynamic, dynamic> metadata) {
    final labels = <String, String>{};
    final rawCategories = metadata['categories'];
    if (rawCategories is Map) {
      for (final entry in rawCategories.entries) {
        final id = entry.key.toString().trim();
        final name = entry.value.toString().trim();
        if (id.isNotEmpty && name.isNotEmpty) labels[id] = name;
      }
    } else if (rawCategories is Iterable) {
      for (final item in rawCategories.whereType<Map>()) {
        final id = (item['id'] ?? item['key'] ?? item['slug'])
            ?.toString()
            .trim();
        final name = (item['name'] ?? item['label'])?.toString().trim();
        if (id?.isNotEmpty == true && name?.isNotEmpty == true) {
          labels[id!] = name!;
        }
      }
    }
    final options = metadata['category_options'];
    if (options is Iterable) {
      for (final item in options.whereType<Map>()) {
        final id = (item['id'] ?? item['key'] ?? item['slug'])
            ?.toString()
            .trim();
        final name = (item['name'] ?? item['label'])?.toString().trim();
        if (id?.isNotEmpty == true && name?.isNotEmpty == true) {
          labels[id!] = name!;
        }
      }
    }
    return labels;
  }

  static Map<String, int> _categoryCounts(Map<dynamic, dynamic> metadata) {
    final counts = <String, int>{};
    final rawCounts = metadata['category_counts'];
    if (rawCounts is Map) {
      for (final entry in rawCounts.entries) {
        final id = entry.key.toString().trim();
        if (id.isNotEmpty) {
          counts[id] = _nonNegativeInt(entry.value);
        }
      }
    }
    final options = metadata['category_options'];
    if (options is Iterable) {
      for (final item in options.whereType<Map>()) {
        final id = (item['id'] ?? item['key'] ?? item['slug'])
            ?.toString()
            .trim();
        if (id?.isNotEmpty == true) {
          counts[id!] = _nonNegativeInt(
            item['published_count'] ?? item['count'],
          );
        }
      }
    }
    return counts;
  }

  static int _nonNegativeInt(Object? value) {
    final parsed = value is num ? value.toInt() : int.tryParse('$value');
    return parsed != null && parsed >= 0 ? parsed : 0;
  }

  /// Accepts both the HC mobile envelope and conventional Laravel paginator
  /// responses. Website response changes stay inside this transport adapter
  /// instead of leaking into mobile widgets.
  static _BlogPage? _blogPage(Map<String, dynamic> response) {
    final data = response['data'];
    if (data is List) {
      return _BlogPage(items: data);
    }
    if (data is Map) {
      final items = data['blogs'] ?? data['data'];
      if (items is List) {
        return _BlogPage(
          items: items,
          pagination: data['pagination'] is Map
              ? data['pagination'] as Map
              : data,
        );
      }
    }
    final items = response['blogs'];
    if (items is List) {
      return _BlogPage(
        items: items,
        pagination: response['pagination'] is Map
            ? response['pagination'] as Map
            : null,
      );
    }
    return null;
  }

  static Map<String, dynamic>? _blogDetail(Map<String, dynamic> response) {
    final data = response['data'];
    if (data is Map) {
      final nested = data['blog'];
      if (nested is Map) return Map<String, dynamic>.from(nested);
      if (_looksLikeBlog(data)) return Map<String, dynamic>.from(data);
    }
    final blog = response['blog'];
    return blog is Map ? Map<String, dynamic>.from(blog) : null;
  }

  static bool _looksLikeBlog(Map<dynamic, dynamic> value) {
    return value.containsKey('id') ||
        value.containsKey('title') ||
        value.containsKey('content');
  }
}

class TradingBlogApiSnapshot {
  const TradingBlogApiSnapshot({
    required this.blogs,
    this.categories = const {},
    this.categoryCounts = const {},
    this.syncedAt,
  });

  final List<Map<String, dynamic>> blogs;
  final Map<String, String> categories;
  final Map<String, int> categoryCounts;
  final DateTime? syncedAt;
}

class _BlogPage {
  const _BlogPage({required this.items, this.pagination});

  final List<dynamic> items;
  final Map<dynamic, dynamic>? pagination;
}
