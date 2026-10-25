import 'package:flutter/material.dart';

import '../../models/app_user.dart';

enum ProfileModuleSize { highlighted, standard, compact }

class ProfileServiceItem {
  const ProfileServiceItem({
    required this.id,
    required this.titleKey,
    required this.descriptionKey,
    required this.icon,
    required this.destinationBuilder,
    this.size = ProfileModuleSize.standard,
    this.statusKey,
    this.statusValues = const {},
  });

  final String id;
  final String titleKey;
  final String descriptionKey;
  final IconData icon;
  final WidgetBuilder destinationBuilder;
  final ProfileModuleSize size;
  final String? statusKey;
  final Map<String, Object?> statusValues;
}

class ProfileCompletion {
  const ProfileCompletion({
    required this.completedFields,
    required this.totalFields,
    required this.nextStepKey,
  });

  final int completedFields;
  final int totalFields;
  final String nextStepKey;

  double get progress => totalFields == 0 ? 1 : completedFields / totalFields;
  bool get isComplete => completedFields >= totalFields;

  factory ProfileCompletion.fromUser(AppUser user) {
    final hasName = user.displayName?.trim().isNotEmpty == true;
    final hasEmail = user.email.trim().contains('@');
    final completed = (hasName ? 1 : 0) + (hasEmail ? 1 : 0);
    return ProfileCompletion(
      completedFields: completed,
      totalFields: 2,
      nextStepKey: !hasName
          ? 'Add your display name'
          : !hasEmail
          ? 'Add a valid email address'
          : 'Profile details complete',
    );
  }
}
