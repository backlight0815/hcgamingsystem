import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../core/localization/app_localizations.dart';

abstract final class AppFeedback {
  static void success(BuildContext context, String message) {
    if (!context.mounted) return;
    final translated = context.l10n.text(message);
    final messenger = ScaffoldMessenger.of(context);
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Row(
            children: [
              const Icon(Icons.check_circle_outline, color: Colors.white),
              const SizedBox(width: 10),
              Expanded(child: Text(translated)),
            ],
          ),
          backgroundColor: const Color(0xFF177A55),
        ),
      );
  }

  static void warning(BuildContext context, String message) {
    if (!context.mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Row(
            children: [
              const Icon(Icons.warning_amber_outlined, color: Colors.white),
              const SizedBox(width: 10),
              Expanded(child: Text(context.l10n.text(message))),
            ],
          ),
          backgroundColor: const Color(0xFF9A6700),
        ),
      );
  }

  static void error(BuildContext context, Object error, {String? operation}) {
    final rawMessage = messageFor(error);
    final translated = context.l10n.text(rawMessage);
    final message = operation == null
        ? translated
        : context.l10n.text('Unable to complete this action: {message}', {
            'message': translated,
          });
    if (kDebugMode) debugPrint('[HC Trader] $message');
    if (!context.mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Row(
            children: [
              const Icon(Icons.error_outline, color: Colors.white),
              const SizedBox(width: 10),
              Expanded(child: Text(message)),
            ],
          ),
          backgroundColor: Theme.of(context).colorScheme.error,
        ),
      );
  }

  static String messageFor(Object error) {
    if (error is FirebaseAuthException) {
      return switch (error.code) {
        'invalid-email' => 'Enter a valid email address.',
        'user-not-found' ||
        'wrong-password' ||
        'invalid-credential' => 'Invalid username or password.',
        'email-already-in-use' => 'This email address is already registered.',
        'weak-password' =>
          'Use a stronger password with at least 8 characters.',
        'network-request-failed' =>
          'Unable to reach Firebase. Check your connection.',
        'too-many-requests' => 'Too many attempts. Please wait and try again.',
        'operation-not-allowed' =>
          'Email and password sign-in is not enabled for this Firebase project.',
        _ => error.message ?? 'Authentication failed (${error.code}).',
      };
    }
    if (error is FirebaseException) {
      return switch (error.code) {
        'permission-denied' =>
          'Your account does not have permission to complete this request.',
        'unauthenticated' => 'Sign in before completing this request.',
        'unavailable' =>
          'Service temporarily unavailable or network/Firebase connection failed. Please check your internet connection and try again.',
        'deadline-exceeded' =>
          'The Firebase request took too long. Please try again.',
        'resource-exhausted' =>
          'Firebase is receiving too many requests right now. Please wait and try again.',
        'not-found' => 'The requested record no longer exists.',
        'failed-precondition' =>
          error.message ?? 'This action cannot be completed yet.',
        _ => error.message ?? 'Firebase request failed (${error.code}).',
      };
    }
    final value = '$error'.replaceFirst(RegExp(r'^Exception:\s*'), '').trim();
    if (value.contains('[cloud_firestore/permission-denied]') ||
        value.contains('PERMISSION_DENIED')) {
      return 'Your account does not have permission to complete this request.';
    }
    if (value.toUpperCase() == 'UNAVAILABLE') {
      return 'Service temporarily unavailable or network/Firebase connection failed. Please check your internet connection and try again.';
    }
    if (const {
      'an internal error has occurred.',
      'an internal error occurred.',
      'internal error',
      'unknown error',
    }.contains(value.toLowerCase())) {
      return 'Something went wrong. Please try again or contact support.';
    }
    return value.isEmpty ? 'Something went wrong. Please try again.' : value;
  }
}
