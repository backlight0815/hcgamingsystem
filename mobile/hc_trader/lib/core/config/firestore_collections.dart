/// Authoritative Cloud Firestore collection paths used by the Coursework
/// feature. Keep document field names in the model/repository layer; widgets
/// must not contain collection path literals.
abstract final class FirestoreCollections {
  static const courseworkThemes = 'coursework_themes';
  static const courseworkAssignments = 'coursework_assignments';
  static const courseworkAttempts = 'coursework_attempts';
  static const users = 'users';
  static const multipleAccountStrategy = 'multiple_account_strategy';
  static const multipleAccountWorkspace = 'workspace';
  static const multipleAccountAccounts = 'accounts';
  static const multipleAccountConfiguration = 'configuration';
  static const multipleAccountSessions = 'sessions';
  static const multipleAccountHistory = 'history';
}
