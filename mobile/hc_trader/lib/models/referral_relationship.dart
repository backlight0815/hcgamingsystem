enum UplineRelationshipStatus {
  available,
  noUpline,
  unavailable,
  missingRecord,
  conflict,
}

class UplineRelationship {
  const UplineRelationship({required this.status, this.displayName = ''});

  final UplineRelationshipStatus status;
  final String displayName;

  bool get isAvailable =>
      status == UplineRelationshipStatus.available && displayName.isNotEmpty;

  factory UplineRelationship.fromMap(Map<String, dynamic> data) {
    final status = switch ('${data['status'] ?? ''}') {
      'available' => UplineRelationshipStatus.available,
      'unavailable' => UplineRelationshipStatus.unavailable,
      'missingRecord' => UplineRelationshipStatus.missingRecord,
      'conflict' => UplineRelationshipStatus.conflict,
      _ => UplineRelationshipStatus.noUpline,
    };
    return UplineRelationship(
      status: status,
      displayName: '${data['displayName'] ?? ''}'.trim(),
    );
  }
}
