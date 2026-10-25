import 'dart:async';

import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/network/api_client.dart';
import '../core/storage/local_app_storage.dart';
import '../models/trading_journal_entry.dart';
import 'journal_sync_mapping.dart';

/// Synchronizes the existing Firestore journal with the Laravel journal ledger.
/// Firestore remains the app's offline store; Laravel owns cross-platform IDs,
/// revisions, deletions and the authoritative journal statistics snapshot.
class TradingJournalSyncService {
  TradingJournalSyncService({
    required this.client,
    required this.firestore,
    required this.storage,
  });

  final ApiClient client;
  final FirebaseFirestore firestore;
  final LocalAppStorage storage;
  final Map<String, Future<void>> _running = {};
  Future<void> _queueWrite = Future<void>.value();

  Future<void> _editQueue(
    String uid,
    void Function(List<Map<String, dynamic>>) edit,
  ) {
    final next = _queueWrite.then((_) async {
      final queue = storage.readList(uid, 'journal_sync_deletions').toList();
      edit(queue);
      await storage.writeList(uid, 'journal_sync_deletions', queue);
    });
    _queueWrite = next.catchError((Object _) {});
    return next;
  }

  Future<void> sync(String uid) => _running.putIfAbsent(uid, () async {
    try {
      await _sync(uid);
    } finally {
      _running.remove(uid);
    }
  });

  Future<void> queueDeletion({
    required String uid,
    required String docId,
  }) async {
    final collection = firestore
        .collection('users')
        .doc(uid)
        .collection('trading_journals');
    final snapshot = await collection.doc(docId).get();
    final data = snapshot.data();
    if (data == null) return;
    await _editQueue(uid, (queue) {
      if (!queue.any((item) => item['mobile_id'] == docId)) {
        queue.add({
          'type': 'delete',
          'mobile_id': docId,
          if (data['_sync_id'] != null) 'sync_id': data['_sync_id'],
          if (data['_sync_revision'] != null)
            'base_revision': data['_sync_revision'],
        });
      }
    });
    await collection.doc(docId).delete();
  }

  Future<void> _sync(String uid) async {
    final collection = firestore
        .collection('users')
        .doc(uid)
        .collection('trading_journals');
    await storage.writeMap(uid, 'journal_sync_status', {
      'state': 'syncing',
      'message': 'Synchronizing journal…',
    });
    try {
      final snapshot = await collection
          .get(const GetOptions(source: Source.server))
          .timeout(client.requestTimeout);
      final deletes = storage.readList(uid, 'journal_sync_deletions');
      final blocked = <String>{};
      final blockedSyncIds = <String>{};
      final pendingDeleteIds = deletes
          .map((item) => '${item['mobile_id']}')
          .toSet();
      for (var offset = 0; offset < deletes.length; offset += 50) {
        final batch = deletes.skip(offset).take(50).toList();
        final results = await _sendMany(batch);
        for (var index = 0; index < batch.length; index++) {
          final pending = batch[index];
          final result = results[index];
          if (result['status'] == 'deleted' ||
              result['status'] == 'unchanged') {
            await _editQueue(
              uid,
              (queue) => queue.removeWhere(
                (item) => item['mobile_id'] == pending['mobile_id'],
              ),
            );
          } else {
            blocked.add('${pending['mobile_id']}');
            if (pending['sync_id'] != null) {
              blockedSyncIds.add('${pending['sync_id']}');
            }
          }
        }
      }
      final uploads = <Map<String, dynamic>>[];
      final originals = <String, Map<String, dynamic>>{};
      for (final doc in snapshot.docs) {
        if (doc.metadata.hasPendingWrites ||
            pendingDeleteIds.contains(doc.id)) {
          continue;
        }
        final data = doc.data();
        if ((data['type'] ?? 'trade') != 'trade') continue;
        final local = TradingJournalEntry.fromFirestore(doc);
        if (!local.source.isManualDisplaySource) continue;
        final fields = journalSyncFields(data);
        final shadow = data['_sync_shadow'] is Map
            ? Map<String, dynamic>.from(data['_sync_shadow'] as Map)
            : <String, dynamic>{};
        final syncId = data['_sync_id'];
        final changes = syncId == null
            ? fields
            : Map<String, Object?>.fromEntries(
                fields.entries.where(
                  (item) => !journalSyncEqual(item.value, shadow[item.key]),
                ),
              );
        if (syncId != null && changes.isEmpty) continue;
        final operation = <String, Object?>{
          'type': syncId == null ? 'create' : 'update',
          'mobile_id': doc.id,
          'sync_id': ?syncId,
          if (syncId != null) ...{
            'base_revision': data['_sync_revision'],
            'base': shadow,
          },
          'fields': changes,
        };
        uploads.add(operation);
        originals[doc.id] = fields;
      }
      for (var offset = 0; offset < uploads.length; offset += 50) {
        final batch = uploads.skip(offset).take(50).toList();
        final results = await _sendMany(batch);
        for (var index = 0; index < batch.length; index++) {
          final docId = '${batch[index]['mobile_id']}';
          final result = results[index];
          final status = '${result['status']}';
          if (!const {
            'created',
            'linked',
            'updated',
            'unchanged',
          }.contains(status)) {
            blocked.add(docId);
            if (batch[index]['sync_id'] != null) {
              blockedSyncIds.add('${batch[index]['sync_id']}');
            }
            if (result['candidate_sync_ids'] is List) {
              blockedSyncIds.addAll(
                (result['candidate_sync_ids'] as List).map((id) => '$id'),
              );
            }
            await firestore.runTransaction((transaction) async {
              final ref = collection.doc(docId);
              if ((await transaction.get(ref)).exists) {
                transaction.update(ref, {
                  '_sync_error':
                      result['message'] ?? 'Journal upload needs review.',
                });
              }
            });
            continue;
          }
          final record = result['record'];
          if (record is! Map) {
            throw const ApiException(
              'The journal upload acknowledgement is invalid.',
            );
          }
          final written = await _writeRemote(
            collection,
            docId,
            Map<String, dynamic>.from(record),
            uid,
            expected: originals[docId],
          );
          if (!written) blocked.add(docId);
        }
      }

      var cursor =
          (storage.readMap(uid, 'journal_sync_state')?['cursor'] as num?)
              ?.toInt() ??
          0;
      var hasMore = true;
      var checkpoint = cursor;
      var replayNeeded = false;
      while (hasMore) {
        final response = await client.get(
          'journal-sync/changes?cursor=$cursor&limit=100',
        );
        final page = response['data'];
        if (page is! Map || page['changes'] is! List) {
          throw const ApiException('The journal sync response is invalid.');
        }
        for (final change in page['changes'] as List) {
          if (change is! Map || change['record'] is! Map) {
            throw const ApiException(
              'The journal change is invalid.',
              errorCode: 'INVALID_API_RESPONSE',
            );
          }
          final record = Map<String, dynamic>.from(change['record'] as Map);
          final syncId = record['sync_id'];
          if (syncId == null) {
            throw const ApiException(
              'The journal identity is invalid.',
              errorCode: 'INVALID_API_RESPONSE',
            );
          }
          if (blockedSyncIds.contains('$syncId')) {
            replayNeeded = true;
            continue;
          }
          final docId = (record['mobile_id'] ?? 'website_$syncId').toString();
          if (blocked.contains(docId) ||
              storage
                  .readList(uid, 'journal_sync_deletions')
                  .any((item) => item['mobile_id'] == docId)) {
            replayNeeded = true;
            continue;
          }
          final ref = collection.doc(docId);
          final local = await ref.get();
          if (record['deleted_at'] != null) {
            if (local.exists && local.data()?['_sync_id'] == syncId) {
              await firestore.runTransaction((transaction) async {
                final latest = await transaction.get(ref);
                if (_hasLocalEdits(latest)) {
                  blocked.add(docId);
                  replayNeeded = true;
                } else {
                  transaction.delete(ref);
                }
              });
            }
            continue;
          }
          if (local.exists &&
              local.data()?['_sync_id'] != null &&
              local.data()?['_sync_id'] != syncId) {
            blocked.add(docId);
            replayNeeded = true;
            continue;
          }
          if (local.exists && _hasLocalEdits(local)) {
            blocked.add(docId);
            replayNeeded = true;
            continue;
          }
          final written = await _writeRemote(
            collection,
            docId,
            record,
            uid,
            expected: local.exists ? journalSyncFields(local.data()!) : null,
          );
          if (!written) {
            blocked.add(docId);
            replayNeeded = true;
          }
        }
        final nextCursor = (page['next_cursor'] as num?)?.toInt();
        hasMore = page['has_more'] == true;
        if (nextCursor == null ||
            nextCursor < cursor ||
            (hasMore && nextCursor == cursor)) {
          throw const ApiException(
            'The journal change cursor is invalid.',
            errorCode: 'INVALID_API_RESPONSE',
          );
        }
        cursor = nextCursor;
        if (!replayNeeded) checkpoint = cursor;
        final previousSync = storage.readMap(uid, 'journal_sync_state');
        await storage.writeMap(uid, 'journal_sync_state', {
          ...?previousSync,
          'cursor': checkpoint,
        });
      }
      final stats = await client.get('journal-sync/statistics');
      if (stats['data'] is! Map) {
        throw const ApiException('The journal statistics response is invalid.');
      }
      await storage.writeMap(
        uid,
        'journal_sync_statistics',
        Map<String, Object?>.from(stats['data'] as Map),
      );
      if (blocked.isEmpty) {
        await storage.writeMap(uid, 'journal_sync_state', {
          'cursor': checkpoint,
          'last_synced_at': DateTime.now().toUtc().toIso8601String(),
        });
      }
      await storage.writeMap(uid, 'journal_sync_status', {
        'state': blocked.isEmpty ? 'synced' : 'conflict',
        'message': blocked.isEmpty
            ? ''
            : '${blocked.length} journal record(s) need review.',
      });
    } on Object catch (error) {
      final state = error is ApiException
          ? switch (error.errorCode) {
              'UNAUTHENTICATED' || 'INVALID_TOKEN' => 'auth',
              'EMAIL_VERIFICATION_REQUIRED' => 'verification',
              'JOURNAL_ACCOUNT_MAPPING_UNAVAILABLE' ||
              'JOURNAL_ACCOUNT_MAPPING_CONFLICT' => 'mapping',
              'AUTH_CONFIGURATION_UNAVAILABLE' ||
              'AUTH_PROVIDER_UNAVAILABLE' => 'server',
              _ => error.statusCode == 401 ? 'auth' : 'error',
            }
          : error is TimeoutException ||
                (error is FirebaseException && error.code == 'unavailable')
          ? 'offline'
          : 'error';
      await storage.writeMap(uid, 'journal_sync_status', {
        'state': state,
        'message': error is ApiException
            ? error.message
            : state == 'offline'
            ? 'Journal sync is waiting for a connection. Your local trades are safe.'
            : 'Journal sync could not finish. Your saved records are safe. Please retry or contact support.',
      });
      rethrow;
    }
  }

  Future<List<Map<String, dynamic>>> _sendMany(
    List<Map<String, dynamic>> operations,
  ) async {
    final response = await client.post(
      'journal-sync/batch',
      body: {'operations': operations},
    );
    final data = response['data'];
    if (data is! Map ||
        data['results'] is! List ||
        (data['results'] as List).length != operations.length) {
      throw const ApiException('The journal sync acknowledgement is invalid.');
    }
    final results = <Map<String, dynamic>>[];
    for (var index = 0; index < operations.length; index++) {
      final item = (data['results'] as List)[index];
      if (item is! Map || item['index'] != index) {
        throw const ApiException(
          'The journal sync acknowledgement is invalid.',
          errorCode: 'INVALID_API_RESPONSE',
        );
      }
      results.add(Map<String, dynamic>.from(item));
    }
    return results;
  }

  Future<bool> _writeRemote(
    CollectionReference<Map<String, dynamic>> collection,
    String docId,
    Map<String, dynamic> record,
    String uid, {
    Map<String, dynamic>? expected,
  }) async {
    // A delete can be requested while the upload is waiting for its response.
    var deleting = false;
    await _editQueue(uid, (queue) {
      for (final pending in queue) {
        if (pending['mobile_id'] == docId) {
          deleting = true;
          pending['sync_id'] = record['sync_id'];
          pending['base_revision'] = record['revision'];
        }
      }
    });
    if (deleting) return false;
    final fields = journalRemoteFields(record);
    final ref = collection.doc(docId);
    final written = await firestore.runTransaction((transaction) async {
      final latest = await transaction.get(ref);
      if (expected != null && !latest.exists) return false;
      if (latest.data()?['_sync_id'] != null &&
          latest.data()?['_sync_id'] != record['sync_id']) {
        return false;
      }
      if ((latest.data()?['_sync_revision'] as num? ?? 0) >
          (record['revision'] as num)) {
        return true;
      }
      final current = journalSyncFields(latest.data() ?? {});
      if (expected == null && latest.exists && _hasLocalEdits(latest)) {
        return false;
      }
      final changed = expected != null && !journalSyncEqual(current, expected);
      final merged = Map<String, dynamic>.from(fields);
      if (changed) {
        // Preserve only edits made since the request was sent. The server
        // acknowledgement becomes their base for the next upload.
        for (final key in current.keys) {
          if (!journalSyncEqual(current[key], expected[key])) {
            merged.remove(
              key == 'mobile_trading_account_id' ? 'trading_account_id' : key,
            );
          }
        }
      }
      transaction.set(ref, {
        ...merged,
        'user_id': uid,
        'type': 'trade',
        '_sync_id': record['sync_id'],
        '_sync_revision': record['revision'],
        '_sync_shadow': journalSyncFields(fields),
        '_sync_error': FieldValue.delete(),
      }, SetOptions(merge: true));
      return !changed;
    });
    final websiteAliasId = 'website_${record['sync_id']}';
    if (docId != websiteAliasId) {
      if (written) {
        await firestore.runTransaction((transaction) async {
          final ref = collection.doc(websiteAliasId);
          final alias = await transaction.get(ref);
          if (alias.exists &&
              !_hasLocalEdits(alias) &&
              alias.data()?['_sync_id'] == record['sync_id']) {
            transaction.delete(ref);
          }
        });
      }
    }
    return written;
  }

  bool _hasLocalEdits(DocumentSnapshot<Map<String, dynamic>> doc) {
    final data = doc.data() ?? const <String, dynamic>{};
    final shadow = data['_sync_shadow'];
    if (shadow is! Map) return data['_sync_id'] == null;
    final current = journalSyncFields(data);
    return current.entries.any(
      (item) => !journalSyncEqual(item.value, shadow[item.key]),
    );
  }
}
