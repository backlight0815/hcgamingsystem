import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:cloud_functions/cloud_functions.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../core/security/app_roles.dart';
import '../models/access_status.dart';
import '../models/app_user.dart';
import '../models/referral_relationship.dart';
import '../services/firebase_callable_service.dart';
import '../services/firebase_password_reauthentication_service.dart';

class AuthRepository {
  AuthRepository({
    FirebaseAuth? auth,
    FirebaseFirestore? firestore,
    FirebaseFunctions? functions,
    FirebaseCallableService? callableService,
    FirebasePasswordReauthenticationService? passwordReauthenticationService,
  }) : _auth = auth ?? FirebaseAuth.instance,
       _firestore = firestore ?? FirebaseFirestore.instance,
       _functions =
           functions ?? FirebaseFunctions.instanceFor(region: _functionsRegion),
       _callableService = callableService ?? FirebaseCallableService(),
       _passwordReauthenticationService =
           passwordReauthenticationService ??
           FirebasePasswordReauthenticationService();

  final FirebaseAuth _auth;
  final FirebaseFirestore _firestore;
  final FirebaseFunctions _functions;
  final FirebaseCallableService _callableService;
  final FirebasePasswordReauthenticationService
  _passwordReauthenticationService;
  final _authState = StreamController<AppUser?>.broadcast();
  StreamSubscription<User?>? _firebaseSubscription;
  StreamSubscription<DocumentSnapshot<Map<String, dynamic>>>?
  _profileSubscription;
  Timer? _initialAuthTimer;
  AppUser? _currentUser;
  String? _verifiedPassword;
  String? _accountDeletionIdToken;
  bool _listening = false;
  bool _initialAuthStateResolved = false;
  bool _signingOut = false;

  static const _defaultRoles = <RegistrationRole>[
    RegistrationRole(
      id: 750,
      name: 'Trader',
      description: 'Trading journal, education, and prop-firm tools',
    ),
  ];
  static const _functionsRegion = 'us-central1';
  static const _registerProfileFunction = 'registerUserProfileAfterAuth';
  static const _desktopCustomSignInFunction = 'createDesktopCustomSignInToken';
  static const _deleteAccountFunction = 'deleteCurrentUserAccount';
  static const _uplineRelationshipFunction = 'getMyUplineRelationship';
  static const _withdrawAccessFunction = 'withdrawAccessApplication';
  static const _normalReferralPurpose = 'upline_downline';
  static const _publicRegistrationRoles = {700, 750};
  static const _signOutTimeout = Duration(seconds: 8);
  static const _authOperationTimeout = Duration(seconds: 30);
  static const _profileLoadTimeout = Duration(seconds: 12);
  static const _functionCallTimeout = Duration(seconds: 70);
  static const _accountDeletionTimeout = Duration(minutes: 10);
  static final _secureRandom = Random.secure();

  AppUser? get currentUser => _currentUser;

  Future<AppUser?> refreshCurrentUser() async {
    final firebaseUser = _auth.currentUser;
    if (firebaseUser == null || _signingOut) return null;
    final user = await _loadAppUser(firebaseUser);
    if (user != null) {
      _currentUser = user;
      _emitAuthState(user);
    }
    return user;
  }

  Stream<AppUser?> authStateChanges() {
    return Stream<AppUser?>.multi((controller) {
      final subscription = _authState.stream.listen(
        controller.add,
        onError: controller.addError,
      );
      controller.onCancel = subscription.cancel;
      _startAuthListener();
      controller.add(_currentUser);
    });
  }

  void _startAuthListener() {
    if (!_listening) {
      _listening = true;
      _initialAuthTimer = Timer(const Duration(seconds: 12), () {
        if (!_initialAuthStateResolved) {
          _emitAuthState(null);
        }
      });
      _firebaseSubscription = _auth.authStateChanges().listen(
        _handleFirebaseUser,
        onError: (Object error, StackTrace stackTrace) {
          _resolveAuthError(error, stackTrace);
        },
      );
    }
  }

  Future<List<RegistrationRole>> registrationRoles() async {
    const operation = 'registrationRoles';
    try {
      _debugAuth(operation, 'Loading registration roles');
      final snapshot = await _firestore
          .collection('registration_roles')
          .where('enabled', isEqualTo: true)
          .get()
          .timeout(_authOperationTimeout);
      final roles =
          snapshot.docs
              .map(
                (doc) => RegistrationRole.fromJson({
                  'id': doc.data()['role_id'] ?? doc.id,
                  ...doc.data(),
                }),
              )
              .where(
                (role) =>
                    role.id > 0 &&
                    !const [
                      1,
                      2,
                      201,
                      202,
                      501,
                      502,
                      760,
                      770,
                    ].contains(role.id),
              )
              .toList()
            ..sort((left, right) => left.name.compareTo(right.name));
      return roles.isEmpty ? _defaultRoles : roles;
    } on TimeoutException catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      // Keep registration usable even if optional role seeding is unavailable.
      return _defaultRoles;
    } on FirebaseException catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      // A fresh Firebase project may not have registration_roles seeded yet.
      // Trader is always a safe public registration role.
      return _defaultRoles;
    }
  }

  Future<void> signIn({required String email, required String password}) async {
    const operation = 'signIn';
    try {
      _signingOut = false;
      final login = email.trim().toLowerCase();
      _debugAuth(operation, 'Starting sign in', {
        'loginMode': login.contains('@') ? 'email' : 'username',
      });
      var resolvedEmail = login;
      if (!login.contains('@')) {
        _debugAuth(
          operation,
          'Resolving username before Firebase Auth sign in',
        );
        final username = await _firestore
            .collection('usernames')
            .doc(login)
            .get()
            .timeout(_authOperationTimeout);
        if (!username.exists) {
          throw const AuthFailure('Invalid username or password.');
        }
        resolvedEmail = (username.data()?['email'] ?? '').toString();
      }
      final credential = _usesDesktopHttpTransport
          ? await _signInOnDesktop(email: resolvedEmail, password: password)
          : await _auth
                .signInWithEmailAndPassword(
                  email: resolvedEmail,
                  password: password,
                )
                .timeout(_authOperationTimeout);
      _debugAuth(operation, 'Firebase Auth sign in succeeded');
      final user = await _loadAppUser(credential.user!);
      if (user == null) {
        await _auth.signOut().timeout(_signOutTimeout);
        throw const AuthFailure(
          'Your account profile is incomplete. Please contact support to repair this account.',
        );
      }
      _currentUser = user;
      _watchUserProfile(credential.user!);
      _authState.add(user);
      _debugAuth(operation, 'Sign in completed');
    } on FirebasePasswordReauthenticationFailure catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      throw AuthFailure(error.message);
    } on FirebaseCallableFailure catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      throw AuthFailure(
        _callableFailureMessage(
          error,
          fallback: 'Unable to establish the Windows sign-in session.',
        ),
      );
    } on FirebaseAuthException catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      throw AuthFailure(_authMessage(error));
    } on FirebaseException catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      throw AuthFailure(
        _firebaseMessage(error, fallback: 'Unable to sign in with Firebase.'),
      );
    } on TimeoutException catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      throw const AuthFailure(
        'The sign-in request took too long. Please try again.',
      );
    }
  }

  Future<UserCredential> _signInOnDesktop({
    required String email,
    required String password,
  }) async {
    final verified = await _passwordReauthenticationService
        .signIn(email: email, password: password)
        .timeout(_authOperationTimeout);
    final exchange = await _callableService
        .callWithAccessToken(
          _desktopCustomSignInFunction,
          const <String, dynamic>{},
          accessToken: verified.idToken,
        )
        .timeout(_functionCallTimeout);
    final customToken = '${exchange['customToken'] ?? ''}'.trim();
    if (customToken.isEmpty) {
      throw const AuthFailure(
        'Firebase returned an invalid Windows sign-in response.',
      );
    }
    final credential = await _auth
        .signInWithCustomToken(customToken)
        .timeout(_authOperationTimeout);
    if (credential.user?.uid != verified.userId) {
      await _auth.signOut().timeout(_signOutTimeout);
      throw const AuthFailure(
        'Firebase signed in a different account. Please try again.',
      );
    }
    return credential;
  }

  Future<void> register({
    required String name,
    required String username,
    required String email,
    required String password,
    required int roleId,
    String? referralCode,
    String? campaignId,
    String? registrationSource,
  }) async {
    const operation = 'register';
    _signingOut = false;
    User? firebaseUser;
    var profileCommitted = false;
    final normalizedUsername = username.trim().toLowerCase();
    final normalizedEmail = email.trim().toLowerCase();
    try {
      _debugAuth(operation, 'Starting Firebase Auth registration', {
        'roleId': roleId,
        'referralProvided': normalizeReferralCode(referralCode).isNotEmpty,
      });
      final credential = await _auth
          .createUserWithEmailAndPassword(
            email: normalizedEmail,
            password: password,
          )
          .timeout(_authOperationTimeout);
      firebaseUser = credential.user;
      if (firebaseUser == null) {
        throw const AuthFailure('Firebase did not create the account.');
      }
      _debugAuth(operation, 'Firebase Auth user created');
      await firebaseUser
          .updateDisplayName(name.trim())
          .timeout(_authOperationTimeout);

      await _createServerValidatedProfile(
        name: name.trim(),
        username: normalizedUsername,
        email: normalizedEmail,
        requestedRoleId: roleId,
        referralCode: referralCode,
        campaignId: campaignId,
        registrationSource: registrationSource,
      );
      profileCommitted = true;
      await _activateRegisteredProfile(firebaseUser, sendVerification: true);
      _debugAuth(operation, 'Registration completed');
    } on AuthFailure catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      if (await _recoverCommittedRegistration(firebaseUser, profileCommitted)) {
        return;
      }
      await _rollbackIncompleteRegistration(firebaseUser);
      rethrow;
    } on FirebaseAuthException catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      if (await _recoverCommittedRegistration(firebaseUser, profileCommitted)) {
        return;
      }
      await _rollbackIncompleteRegistration(firebaseUser);
      throw AuthFailure(_authMessage(error));
    } on FirebaseFunctionsException catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      if (await _recoverCommittedRegistration(firebaseUser, profileCommitted)) {
        return;
      }
      await _rollbackIncompleteRegistration(firebaseUser);
      throw AuthFailure(
        _functionsMessage(
          error,
          fallback: 'Unable to delete your account. Please try again.',
        ),
      );
    } on FirebaseException catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      if (await _recoverCommittedRegistration(firebaseUser, profileCommitted)) {
        return;
      }
      await _rollbackIncompleteRegistration(firebaseUser);
      throw AuthFailure(
        _firebaseMessage(
          error,
          fallback: 'Unable to create the Firebase account.',
        ),
      );
    } on TimeoutException catch (error, stackTrace) {
      _debugFailure(operation, error, stackTrace);
      if (await _recoverCommittedRegistration(firebaseUser, profileCommitted)) {
        return;
      }
      await _rollbackIncompleteRegistration(firebaseUser);
      throw const AuthFailure(
        'The registration request took too long. Please try again.',
      );
    }
  }

  Future<void> _activateRegisteredProfile(
    User firebaseUser, {
    required bool sendVerification,
  }) async {
    if (sendVerification && !firebaseUser.emailVerified) {
      try {
        await firebaseUser.sendEmailVerification().timeout(
          _authOperationTimeout,
        );
      } catch (error, stackTrace) {
        // The authoritative profile is already committed. A transient email
        // delivery failure must never trigger deletion of a valid account.
        _debugFailure('register.emailVerification', error, stackTrace);
      }
    }
    final appUser = await _loadAppUser(firebaseUser);
    if (appUser == null) {
      throw const AuthFailure(
        'Your account profile is incomplete. Please contact support to repair this account.',
      );
    }
    _currentUser = appUser;
    _watchUserProfile(firebaseUser);
    _authState.add(appUser);
  }

  Future<bool> _recoverCommittedRegistration(
    User? firebaseUser,
    bool profileCommitted,
  ) async {
    if (firebaseUser == null) return false;
    try {
      final exists =
          profileCommitted ||
          (await _firestore
                  .collection('users')
                  .doc(firebaseUser.uid)
                  .get()
                  .timeout(_profileLoadTimeout))
              .exists;
      if (!exists) return false;
      await _activateRegisteredProfile(firebaseUser, sendVerification: false);
      _debugAuth('register.recovery', 'Recovered committed profile', {
        'userId': firebaseUser.uid,
      });
      return true;
    } catch (error, stackTrace) {
      _debugFailure('register.recovery', error, stackTrace);
      // A known committed profile must be preserved even if local refresh is
      // temporarily unavailable. The profile listener retries on next launch.
      return profileCommitted;
    }
  }

  Future<void> _rollbackIncompleteRegistration(User? firebaseUser) async {
    if (firebaseUser == null) return;
    try {
      await firebaseUser.delete().timeout(_authOperationTimeout);
      _debugAuth('register.rollback', 'Removed incomplete Auth account', {
        'userId': firebaseUser.uid,
      });
    } catch (error, stackTrace) {
      _debugFailure('register.rollback', error, stackTrace);
      try {
        await _auth.signOut().timeout(_signOutTimeout);
      } catch (signOutError, signOutStackTrace) {
        _debugFailure(
          'register.rollback.signOut',
          signOutError,
          signOutStackTrace,
        );
      }
    }
  }

  Future<void> _createServerValidatedProfile({
    required String name,
    required String username,
    required String email,
    required int requestedRoleId,
    String? referralCode,
    String? campaignId,
    String? registrationSource,
  }) async {
    final payload = <String, dynamic>{
      'name': name,
      'username': username,
      'email': email,
      'requestedRoleId': requestedRoleId,
      'referralCode': normalizeReferralCode(referralCode),
      'campaignId': (campaignId ?? '').trim(),
      'registrationSource': (registrationSource ?? '').trim(),
      'deviceInfo': {
        'platform': Platform.operatingSystem,
        'platformVersion': Platform.operatingSystemVersion,
        'client': 'hc_trader_flutter',
      },
    };

    try {
      if (Platform.isWindows) {
        await _callRegisterProfileFunctionOverHttp(payload);
        return;
      }

      final callable = _functions.httpsCallable(_registerProfileFunction);
      await callable
          .call<Map<String, dynamic>>(payload)
          .timeout(_functionCallTimeout);
    } on FirebaseFunctionsException catch (error, stackTrace) {
      _debugFailure('register.profileFunction', error, stackTrace);
      if (!_canUseClientProfileFallback(payload, error.code)) {
        throw AuthFailure(_functionsMessage(error));
      }
      await _createClientValidatedProfile(
        name: name,
        username: username,
        email: email,
        requestedRoleId: requestedRoleId,
        referralCode: payload['referralCode']?.toString() ?? '',
      );
    } on _ProfileServiceUnavailable catch (error, stackTrace) {
      _debugFailure('register.profileFunction', error, stackTrace);
      if (!_canUseClientProfileFallback(payload, error.code)) {
        throw AuthFailure(error.message);
      }
      await _createClientValidatedProfile(
        name: name,
        username: username,
        email: email,
        requestedRoleId: requestedRoleId,
        referralCode: payload['referralCode']?.toString() ?? '',
      );
    } on FirebaseException catch (error, stackTrace) {
      _debugFailure('register.profileFunction', error, stackTrace);
      if (!_canUseClientProfileFallback(payload, error.code)) {
        throw AuthFailure(
          _firebaseMessage(error, fallback: 'Unable to create your profile.'),
        );
      }
      await _createClientValidatedProfile(
        name: name,
        username: username,
        email: email,
        requestedRoleId: requestedRoleId,
        referralCode: payload['referralCode']?.toString() ?? '',
      );
    } on TimeoutException catch (error, stackTrace) {
      _debugFailure('register.profileFunction', error, stackTrace);
      if (!_canUseClientProfileFallback(payload, 'deadline-exceeded')) {
        throw const AuthFailure(
          'The registration service took too long to respond. Please try again.',
        );
      }
      await _createClientValidatedProfile(
        name: name,
        username: username,
        email: email,
        requestedRoleId: requestedRoleId,
        referralCode: payload['referralCode']?.toString() ?? '',
      );
    }
  }

  Future<void> _createClientValidatedProfile({
    required String name,
    required String username,
    required String email,
    required int requestedRoleId,
    required String referralCode,
  }) async {
    final firebaseUser = _auth.currentUser;
    if (firebaseUser == null) {
      throw const AuthFailure('Sign in before completing registration.');
    }

    final roleId = _publicRegistrationRoleOrDefault(requestedRoleId);
    final normalizedReferral = normalizeReferralCode(referralCode);
    _debugAuth(
      'register.clientProfileFallback',
      'Creating profile in Firestore',
      {'roleId': roleId, 'referralProvided': normalizedReferral.isNotEmpty},
    );

    for (var attempt = 0; attempt < 8; attempt++) {
      final generatedReferralCode = _randomReferralCode();
      try {
        await _createClientProfileTransaction(
          firebaseUser: firebaseUser,
          name: name,
          username: username,
          email: email,
          roleId: roleId,
          generatedReferralCode: generatedReferralCode,
          referralCode: normalizedReferral,
        );
        return;
      } on _ReferralCodeCollision {
        _debugAuth(
          'register.clientProfileFallback',
          'Generated referral code collision, retrying',
        );
      }
    }

    throw const AuthFailure(
      'Unable to allocate a referral code. Please try again.',
    );
  }

  Future<void> _createClientProfileTransaction({
    required User firebaseUser,
    required String name,
    required String username,
    required String email,
    required int roleId,
    required String generatedReferralCode,
    required String referralCode,
  }) async {
    final uid = firebaseUser.uid;
    final now = FieldValue.serverTimestamp();
    final userRef = _firestore.collection('users').doc(uid);
    final usernameRef = _firestore.collection('usernames').doc(username);
    final generatedReferralRef = _firestore
        .collection('referral_codes')
        .doc(generatedReferralCode.toLowerCase());
    final usedReferralRef = referralCode.isEmpty
        ? null
        : _firestore.collection('referral_codes').doc(referralCode);

    await _firestore
        .runTransaction((transaction) async {
          final userSnapshot = await transaction.get(userRef);
          if (userSnapshot.exists) {
            throw const AuthFailure(
              'This Firebase account already has a profile.',
            );
          }

          final usernameSnapshot = await transaction.get(usernameRef);
          if (usernameSnapshot.exists) {
            throw const AuthFailure('This username is already registered.');
          }

          final generatedReferralSnapshot = await transaction.get(
            generatedReferralRef,
          );
          if (generatedReferralSnapshot.exists) {
            throw const _ReferralCodeCollision();
          }

          String? referrerId;
          String? referralCodeType;
          String? createdByReferralType;
          if (usedReferralRef != null) {
            final usedReferralSnapshot = await transaction.get(usedReferralRef);
            if (!usedReferralSnapshot.exists) {
              throw const AuthFailure('Please enter a valid referral code.');
            }
            final data = usedReferralSnapshot.data() ?? {};
            if (data['is_active'] == false) {
              throw const AuthFailure(
                'This referral code is no longer active.',
              );
            }
            if (_text(data['code_type'], 'normal') != 'normal') {
              throw const AuthFailure(
                'This referral code cannot be used for public registration.',
              );
            }
            if (_text(data['allowed_purpose'], _normalReferralPurpose) !=
                _normalReferralPurpose) {
              throw const AuthFailure(
                'This referral code cannot be used for public registration.',
              );
            }
            referrerId = _text(data['owner_user_id'] ?? data['user_id']);
            if (referrerId.isEmpty) {
              throw const AuthFailure(
                'This referral code is missing its owner.',
              );
            }
            referralCodeType = 'normal';
            createdByReferralType = 'normal';
          }

          transaction.set(usernameRef, {
            'uid': uid,
            'email': email,
            'username_lowercase': username,
            'created_at': now,
          });
          transaction.set(generatedReferralRef, {
            'code': generatedReferralCode,
            'user_id': uid,
            'owner_user_id': uid,
            'code_type': 'normal',
            'allowed_purpose': _normalReferralPurpose,
            'registration_role_id': 750,
            'is_active': true,
            'created_at': now,
          });
          transaction.set(userRef, {
            'uid': uid,
            'name': name,
            'username': username,
            'username_lowercase': username,
            'email': email,
            'email_lowercase': email,
            'role_id': roleId,
            'role_name': AppRoles.name(roleId),
            'permissions': _emptyAdminPermissions(),
            'status': 1,
            'account_status': 'active',
            'is_suspended': false,
            'is_admin': false,
            'accessStatus': AccessStatus.notSubmitted.storageValue,
            'access_status': AccessStatus.notSubmitted.storageValue,
            'requires_onboarding': roleId == 750,
            'final_exam_passed': false,
            'requires_final_exam': false,
            'invited_by': referrerId,
            'referrer_id': referrerId,
            'upline_id': referrerId,
            'referral_code_used': referralCode.isEmpty ? null : referralCode,
            'referral_code_type': referralCodeType,
            'created_by_referral_type': createdByReferralType,
            'referral_code': generatedReferralCode,
            'referral_code_type_generated': 'normal',
            'created_at': now,
            'updated_at': now,
          });
        })
        .timeout(_authOperationTimeout);
  }

  Future<void> _callRegisterProfileFunctionOverHttp(
    Map<String, dynamic> payload,
  ) async {
    final firebaseUser = _auth.currentUser;
    if (firebaseUser == null) {
      throw const AuthFailure('Sign in before completing registration.');
    }

    final idToken = await firebaseUser.getIdToken(true);
    if (idToken == null || idToken.isEmpty) {
      throw const AuthFailure('Sign in before completing registration.');
    }

    final projectId = Firebase.app().options.projectId.trim();
    if (projectId.isEmpty) {
      throw const AuthFailure('Firebase project is not configured.');
    }

    final uri = Uri.https(
      '$_functionsRegion-$projectId.cloudfunctions.net',
      '/$_registerProfileFunction',
    );

    try {
      final response = await http
          .post(
            uri,
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer $idToken',
            },
            body: jsonEncode({'data': payload}),
          )
          .timeout(const Duration(seconds: 70));

      final body = _jsonObject(response.body);
      final error = body['error'];
      if (error is Map) {
        final status = error['status']?.toString().toUpperCase();
        if (_isProfileServiceUnavailableStatus(status)) {
          throw _ProfileServiceUnavailable(
            code: _callableStatusCode(status),
            message: _callableErrorMessage(error),
          );
        }
        throw AuthFailure(_callableErrorMessage(error));
      }
      if (response.statusCode < 200 || response.statusCode >= 300) {
        if ([404, 408, 429, 500, 502, 503, 504].contains(response.statusCode)) {
          throw _ProfileServiceUnavailable(
            code: response.statusCode == 404 ? 'not-found' : 'unavailable',
            message:
                'Registration service is temporarily unavailable. Please try again.',
          );
        }
        throw AuthFailure(
          response.statusCode == 401
              ? 'Sign in before completing registration.'
              : 'Unable to create your profile.',
        );
      }
      if (!body.containsKey('result') && !body.containsKey('response')) {
        throw const AuthFailure('Firebase returned an invalid response.');
      }
    } on TimeoutException {
      throw const _ProfileServiceUnavailable(
        code: 'deadline-exceeded',
        message:
            'The registration service took too long to respond. Please try again.',
      );
    } on SocketException {
      throw const _ProfileServiceUnavailable(
        code: 'unavailable',
        message: 'Unable to reach Firebase. Check your connection.',
      );
    } on http.ClientException {
      throw const _ProfileServiceUnavailable(
        code: 'unavailable',
        message: 'Unable to reach Firebase. Check your connection.',
      );
    } on FormatException {
      throw const AuthFailure('Firebase returned an invalid response.');
    }
  }

  Future<void> sendPasswordReset(String email) async {
    const operation = 'sendPasswordReset';
    final normalizedEmail = normalizePasswordResetEmail(email);
    if (validatePasswordResetEmail(normalizedEmail) != null) {
      throw const PasswordResetFailure(PasswordResetFailureKind.invalidEmail);
    }
    try {
      _debugAuth(operation, 'Requesting a Firebase password reset email');
      if (_usesDesktopHttpTransport) {
        await _passwordReauthenticationService
            .sendPasswordResetEmail(email: normalizedEmail)
            .timeout(_authOperationTimeout);
      } else {
        await _auth
            .sendPasswordResetEmail(email: normalizedEmail)
            .timeout(_authOperationTimeout);
      }
    } on FirebasePasswordReauthenticationFailure catch (error, stackTrace) {
      _debugPasswordResetFailure(operation, error.code, stackTrace);
      if (isPasswordResetEnumerationSafeCode(error.code)) return;
      throw PasswordResetFailure(passwordResetFailureKindForCode(error.code));
    } on FirebaseAuthException catch (error, stackTrace) {
      _debugPasswordResetFailure(operation, error.code, stackTrace);
      if (isPasswordResetEnumerationSafeCode(error.code)) return;
      throw PasswordResetFailure(passwordResetFailureKindForCode(error.code));
    } on TimeoutException catch (_, stackTrace) {
      _debugPasswordResetFailure(operation, 'deadline-exceeded', stackTrace);
      throw const PasswordResetFailure(PasswordResetFailureKind.network);
    } on SocketException catch (_, stackTrace) {
      _debugPasswordResetFailure(operation, 'unavailable', stackTrace);
      throw const PasswordResetFailure(PasswordResetFailureKind.network);
    } on http.ClientException catch (_, stackTrace) {
      _debugPasswordResetFailure(operation, 'unavailable', stackTrace);
      throw const PasswordResetFailure(PasswordResetFailureKind.network);
    } catch (_, stackTrace) {
      _debugPasswordResetFailure(operation, 'unknown', stackTrace);
      throw const PasswordResetFailure(PasswordResetFailureKind.unknown);
    }
  }

  Future<void> signOut() async {
    _signingOut = true;
    _verifiedPassword = null;
    _accountDeletionIdToken = null;
    _currentUser = null;
    final profileSubscription = _profileSubscription;
    _profileSubscription = null;
    _emitAuthState(null);
    unawaited(_finishFirebaseSignOut(profileSubscription));
  }

  Future<void> _finishFirebaseSignOut(
    StreamSubscription<DocumentSnapshot<Map<String, dynamic>>>?
    profileSubscription,
  ) async {
    try {
      await profileSubscription?.cancel().timeout(_signOutTimeout);
    } on Object {
      // Local sign-out must not stay blocked by Firestore listener cleanup.
    }
    try {
      await _auth.signOut().timeout(_signOutTimeout);
    } on Object {
      // The app has already cleared its local session. Firebase auth-state
      // listeners will reconcile if the native sign-out completes later.
    } finally {
      if (_auth.currentUser == null) _signingOut = false;
    }
  }

  Future<void> verifyCurrentPassword({required String password}) async {
    final user = _auth.currentUser;
    final email = user?.email;
    if (user == null || email == null) {
      throw const AuthFailure('Sign in again before verifying your password.');
    }
    try {
      await user
          .reauthenticateWithCredential(
            EmailAuthProvider.credential(email: email, password: password),
          )
          .timeout(_authOperationTimeout);
      _verifiedPassword = password;
    } on FirebaseAuthException catch (error, stackTrace) {
      _debugFailure('verifyCurrentPassword', error, stackTrace);
      throw AuthFailure(_authMessage(error));
    } on TimeoutException catch (error, stackTrace) {
      _debugFailure('verifyCurrentPassword', error, stackTrace);
      throw const AuthFailure(
        'Password verification took too long. Please try again.',
      );
    }
  }

  Future<Map<String, dynamic>> accessRecording(String recordingId) async {
    _consumeVerifiedPassword();
    final snapshot = await _firestore
        .collection('trading_recordings')
        .doc(recordingId)
        .get();
    if (!snapshot.exists) throw const AuthFailure('Recording not found.');
    return snapshot.data()!;
  }

  Future<String> accessMarketingResource(String resourceId) async {
    _consumeVerifiedPassword();
    final snapshot = await _firestore
        .collection('trading_resources')
        .doc(resourceId)
        .get();
    final data = snapshot.data();
    if (data == null) throw const AuthFailure('Resource not found.');
    return (data['url'] ?? data['download_url'] ?? data['file_path'] ?? '')
        .toString();
  }

  Future<Map<String, dynamic>> profile() async {
    final user = _auth.currentUser;
    if (user == null) throw const AuthFailure('Please sign in again.');
    final userDoc = await _firestore.collection('users').doc(user.uid).get();
    final onboarding = await _firestore
        .collection('users')
        .doc(user.uid)
        .collection('trader_onboarding')
        .doc('profile')
        .get();
    return {
      ...?userDoc.data(),
      'id': user.uid,
      'onboarding': {'latest': onboarding.data()},
    };
  }

  Future<UplineRelationship> resolveCurrentUpline() async {
    if (_auth.currentUser == null) {
      throw const AuthFailure('Please sign in again.');
    }
    try {
      final result = await _functions
          .httpsCallable(_uplineRelationshipFunction)
          .call<Map<String, dynamic>>()
          .timeout(_profileLoadTimeout);
      return UplineRelationship.fromMap(result.data);
    } on FirebaseFunctionsException catch (error, stackTrace) {
      _debugFailure('resolveCurrentUpline', error, stackTrace);
      throw AuthFailure(
        _functionsMessage(
          error,
          fallback: 'Unable to load your upline right now.',
        ),
      );
    } on TimeoutException catch (error, stackTrace) {
      _debugFailure('resolveCurrentUpline', error, stackTrace);
      throw const AuthFailure('Unable to load your upline right now.');
    }
  }

  Future<ProfileUpdateResult> updateProfile({
    required String name,
    required String email,
    String? phoneNumber,
    String? photoUrl,
  }) async {
    final user = _auth.currentUser;
    if (user == null) throw const AuthFailure('Please sign in again.');
    final values = normalizeProfileUpdateValues(
      name: name,
      email: email,
      phoneNumber: phoneNumber,
    );
    if (values.name.isEmpty) {
      throw const AuthFailure('Name is required');
    }
    if (!values.email.contains('@')) {
      throw const AuthFailure('Enter a valid email');
    }
    final emailChanged = values.email != user.email?.trim().toLowerCase();
    try {
      await user.updateDisplayName(values.name);
      if (emailChanged) {
        await user.verifyBeforeUpdateEmail(values.email);
      }
      await _firestore.collection('users').doc(user.uid).set({
        'name': values.name,
        // Firebase Authentication remains the canonical email source. Keep the
        // current verified address visible until the user completes the link.
        if (emailChanged) 'pending_email': values.email,
        if (emailChanged)
          'pending_email_requested_at': FieldValue.serverTimestamp(),
        if (values.phoneNumber case final value?) ...{
          'phoneNumber': value,
          'phone_number': value,
        },
        if (photoUrl case final value?) ...{
          'photo_url': value.trim(),
          'avatar_url': value.trim(),
        },
        'updated_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
      _currentUser = await _loadAppUser(user);
      _authState.add(_currentUser);
      return ProfileUpdateResult(emailVerificationSent: emailChanged);
    } on FirebaseAuthException catch (error, stackTrace) {
      _debugFailure('updateProfile', error, stackTrace);
      throw AuthFailure(_authMessage(error));
    } on FirebaseException catch (error, stackTrace) {
      _debugFailure('updateProfile', error, stackTrace);
      throw AuthFailure(
        _firebaseMessage(
          error,
          fallback:
              'Your profile could not be updated. Please check the highlighted fields.',
        ),
      );
    }
  }

  Future<void> withdrawAccessApplication() async {
    final user = _auth.currentUser;
    if (user == null) throw const AuthFailure('Please sign in again.');
    try {
      _debugAuth('withdrawAccessApplication', 'Starting withdrawal', {
        'userId': user.uid,
      });
      await user.getIdToken(true);
      await _callableService
          .call(_withdrawAccessFunction, const <String, dynamic>{})
          .timeout(_functionCallTimeout);
      _currentUser = await _loadAppUser(user);
      _authState.add(_currentUser);
      _debugAuth('withdrawAccessApplication', 'Withdrawal completed', {
        'userId': user.uid,
      });
    } on FirebaseCallableFailure catch (error, stackTrace) {
      _debugFailure('withdrawAccessApplication', error, stackTrace);
      throw AuthFailure(_callableFailureMessage(error));
    } on TimeoutException catch (error, stackTrace) {
      _debugFailure('withdrawAccessApplication', error, stackTrace);
      throw const AuthFailure(
        'The withdrawal request took too long. Please try again.',
      );
    }
  }

  Future<void> submitAccessRequest({
    required String name,
    required String email,
    required String phoneNumber,
  }) async {
    final user = _auth.currentUser;
    if (user == null) throw const AuthFailure('Please sign in again.');
    final normalizedEmail = email.trim().toLowerCase();
    final trimmedName = name.trim();
    final trimmedPhone = phoneNumber.trim();
    if (trimmedName.isEmpty) {
      throw const AuthFailure('Name is required');
    }
    if (!normalizedEmail.contains('@')) {
      throw const AuthFailure('Enter a valid email');
    }
    if (trimmedPhone.isEmpty) {
      throw const AuthFailure('Phone number is required');
    }

    final userRef = _firestore.collection('users').doc(user.uid);
    final requestRef = _firestore.collection('accessRequests').doc(user.uid);
    final historyRef = userRef
        .collection('access_application_history')
        .doc(DateTime.now().microsecondsSinceEpoch.toString());
    await _firestore.runTransaction((transaction) async {
      final userProfile = await transaction.get(userRef);
      final request = await transaction.get(requestRef);
      final existing = request.data();
      final existingStatus = AccessStatus.fromStorage(
        existing?['status'] ??
            existing?['access_status'] ??
            existing?['accessStatus'],
      );
      if (request.exists && !existingStatus.canReapply) {
        throw const AuthFailure(
          'This access application has already been processed.',
        );
      }
      final now = FieldValue.serverTimestamp();
      if (request.exists && existing != null) {
        transaction.set(historyRef, {
          ...existing,
          'userId': user.uid,
          'user_id': user.uid,
          'archivedAt': now,
          'archived_at': now,
        });
      }
      final accessProfileUpdate = {
        'name': trimmedName,
        'email': normalizedEmail,
        'email_lowercase': normalizedEmail,
        'phoneNumber': trimmedPhone,
        'phone_number': trimmedPhone,
        'accessStatus': AccessStatus.pendingReview.storageValue,
        'access_status': AccessStatus.pendingReview.storageValue,
        'accessRequestedAt': now,
        'access_requested_at': now,
        'accessUpdatedAt': now,
        'access_updated_at': now,
        'updated_at': now,
      };
      if (userProfile.exists) {
        transaction.set(userRef, accessProfileUpdate, SetOptions(merge: true));
      } else {
        const roleId = 750;
        transaction.set(userRef, {
          'uid': user.uid,
          'name': trimmedName,
          'email': normalizedEmail,
          'email_lowercase': normalizedEmail,
          'phoneNumber': trimmedPhone,
          'phone_number': trimmedPhone,
          'role_id': roleId,
          'role_name': AppRoles.name(roleId),
          'permissions': _emptyAdminPermissions(),
          'status': 1,
          'account_status': 'active',
          'is_suspended': false,
          'is_admin': false,
          'accessStatus': AccessStatus.pendingReview.storageValue,
          'access_status': AccessStatus.pendingReview.storageValue,
          'accessRequestedAt': now,
          'access_requested_at': now,
          'accessUpdatedAt': now,
          'access_updated_at': now,
          'requires_onboarding': true,
          'final_exam_passed': false,
          'requires_final_exam': false,
          'created_at': now,
          'updated_at': now,
        });
      }
      transaction.set(requestRef, {
        'userId': user.uid,
        'user_id': user.uid,
        'name': trimmedName,
        'email': normalizedEmail,
        'phoneNumber': trimmedPhone,
        'phone_number': trimmedPhone,
        'status': AccessStatus.pendingReview.storageValue,
        'accessStatus': AccessStatus.pendingReview.storageValue,
        'access_status': AccessStatus.pendingReview.storageValue,
        'createdAt': existing?['createdAt'] ?? existing?['created_at'] ?? now,
        'created_at': existing?['created_at'] ?? existing?['createdAt'] ?? now,
        'updatedAt': now,
        'updated_at': now,
        'reviewedBy': null,
        'reviewed_by': null,
        'reviewedAt': null,
        'reviewed_at': null,
        'adminNote': null,
        'admin_note': null,
      }, SetOptions(merge: true));
    });

    _currentUser = await _loadAppUser(user);
    _authState.add(_currentUser);
  }

  Future<void> updatePassword({
    required String currentPassword,
    required String password,
  }) async {
    await verifyCurrentPassword(password: currentPassword);
    try {
      await _auth.currentUser!.updatePassword(password);
      await signOut();
    } on FirebaseAuthException catch (error, stackTrace) {
      _debugFailure('updatePassword', error, stackTrace);
      throw AuthFailure(_authMessage(error));
    }
  }

  Future<void> deleteCurrentAccount({
    required String currentPassword,
    String reason = '',
  }) async {
    await prepareCurrentAccountDeletion(currentPassword: currentPassword);
    await deletePreparedCurrentAccount(reason: reason);
  }

  bool get currentUserUsesPasswordProvider {
    final user = _auth.currentUser;
    return user == null ||
        user.providerData.any((provider) => provider.providerId == 'password');
  }

  Future<void> prepareCurrentAccountDeletion({
    required String currentPassword,
  }) async {
    final user = _auth.currentUser;
    if (user == null) throw const AuthFailure('Please sign in again.');
    _accountDeletionIdToken = null;
    if (currentUserUsesPasswordProvider) {
      if (currentPassword.isEmpty) {
        throw const AuthFailure(
          'Please verify your password again before deleting your account.',
        );
      }
      if (_usesDesktopHttpTransport) {
        final email = user.email;
        if (email == null || email.trim().isEmpty) {
          throw const AuthFailure(
            'Sign in again before verifying your password.',
          );
        }
        try {
          _accountDeletionIdToken = await _passwordReauthenticationService
              .reauthenticate(
                email: email,
                password: currentPassword,
                expectedUserId: user.uid,
              )
              .timeout(_authOperationTimeout);
        } on FirebasePasswordReauthenticationFailure catch (error) {
          throw AuthFailure(error.message);
        } on TimeoutException {
          throw const AuthFailure(
            'Password verification took too long. Please try again.',
          );
        }
      } else {
        await verifyCurrentPassword(password: currentPassword);
      }
    } else {
      // Social providers require their provider-specific sign-in UI. This app
      // does not embed those SDKs, so refresh the credential and let the
      // trusted backend enforce its five-minute recent-login window.
      await user.reload().timeout(_authOperationTimeout);
      await user.getIdToken(true).timeout(_authOperationTimeout);
    }
  }

  Future<void> deletePreparedCurrentAccount({String reason = ''}) async {
    final user = _auth.currentUser;
    if (user == null) throw const AuthFailure('Please sign in again.');
    final accountDeletionIdToken = _accountDeletionIdToken;
    _accountDeletionIdToken = null;
    try {
      final payload = {'reason': reason.trim()};
      if (accountDeletionIdToken == null) {
        await user.getIdToken(true).timeout(_authOperationTimeout);
        await _callableService
            .call(_deleteAccountFunction, payload)
            .timeout(_accountDeletionTimeout);
      } else {
        await _callableService
            .callWithAccessToken(
              _deleteAccountFunction,
              payload,
              accessToken: accountDeletionIdToken,
            )
            .timeout(_accountDeletionTimeout);
      }
      await signOut();
    } on FirebaseCallableFailure catch (error, stackTrace) {
      _debugFailure('deleteCurrentAccount', error, stackTrace);
      throw AuthFailure(
        _callableFailureMessage(
          error,
          fallback:
              'Unable to delete your account. Please try again or contact support.',
        ),
      );
    } on FirebaseAuthException catch (error, stackTrace) {
      _debugFailure('deleteCurrentAccount', error, stackTrace);
      throw AuthFailure(_authMessage(error));
    } on TimeoutException catch (error, stackTrace) {
      _debugFailure('deleteCurrentAccount', error, stackTrace);
      throw const AuthFailure(
        'The account deletion request is still taking too long. Please try again later or contact support.',
      );
    } catch (error, stackTrace) {
      _debugFailure('deleteCurrentAccount', error, stackTrace);
      throw const AuthFailure(
        'Unable to delete your account. Please try again or contact support.',
      );
    }
  }

  bool get _usesDesktopHttpTransport =>
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.windows ||
          defaultTargetPlatform == TargetPlatform.linux);

  Future<void> _handleFirebaseUser(User? firebaseUser) async {
    if (firebaseUser == null) {
      _signingOut = false;
      await _profileSubscription?.cancel();
      _profileSubscription = null;
      _currentUser = null;
      _emitAuthState(null);
      return;
    }
    if (_signingOut) {
      _currentUser = null;
      _emitAuthState(null);
      return;
    }
    try {
      AppUser? user;
      for (var attempt = 0; attempt < 10 && user == null; attempt++) {
        user = await _loadAppUser(firebaseUser);
        if (user == null) {
          await Future<void>.delayed(const Duration(milliseconds: 200));
        }
      }
      if (user == null) {
        // This can happen when a previously authenticated Firebase account no
        // longer has a corresponding Firestore profile. Always resolve the
        // startup gate instead of leaving the app on an endless spinner.
        _currentUser = null;
        _emitAuthState(null);
        return;
      }
      _currentUser = user;
      _watchUserProfile(firebaseUser);
      _emitAuthState(user);
    } catch (error, stackTrace) {
      _resolveAuthError(error, stackTrace);
    }
  }

  void _emitAuthState(AppUser? user) {
    _initialAuthStateResolved = true;
    _initialAuthTimer?.cancel();
    _initialAuthTimer = null;
    if (!_authState.isClosed) _authState.add(user);
  }

  void _resolveAuthError(Object error, StackTrace stackTrace) {
    _initialAuthStateResolved = true;
    _initialAuthTimer?.cancel();
    _initialAuthTimer = null;
    if (!_authState.isClosed) _authState.addError(error, stackTrace);
  }

  Future<AppUser?> _loadAppUser(User firebaseUser) async {
    final snapshot = await _firestore
        .collection('users')
        .doc(firebaseUser.uid)
        .get()
        .timeout(_profileLoadTimeout);
    final data = snapshot.data();
    if (data == null) return null;
    if (_int(data['status'], fallback: 1) == 0) {
      await _auth.signOut();
      throw const AuthFailure(
        'Your account is suspended. Please contact administration.',
      );
    }
    await _synchronizeVerifiedEmail(firebaseUser, data);
    return _appUserFromData(firebaseUser, data);
  }

  Future<void> _synchronizeVerifiedEmail(
    User firebaseUser,
    Map<String, dynamic> data,
  ) async {
    final authEmail = firebaseUser.email?.trim().toLowerCase() ?? '';
    final storedEmail = (data['email'] ?? '').toString().trim().toLowerCase();
    if (authEmail.isEmpty || authEmail == storedEmail) return;
    data['email'] = authEmail;
    data['email_lowercase'] = authEmail;
    try {
      await _firestore.collection('users').doc(firebaseUser.uid).set({
        'email': authEmail,
        'email_lowercase': authEmail,
        'pending_email': FieldValue.delete(),
        'pending_email_requested_at': FieldValue.delete(),
        'updated_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
    } on FirebaseException catch (error, stackTrace) {
      // The verified Auth value is still rendered immediately. A later login
      // retries the denormalized Firestore synchronization.
      _debugFailure('synchronizeVerifiedEmail', error, stackTrace);
    }
  }

  AppUser _appUserFromData(User firebaseUser, Map<String, dynamic> data) {
    return AppUser.fromJson({
      ...data,
      'id': firebaseUser.uid,
      'email': (firebaseUser.email ?? data['email'] ?? '').toString(),
      'name': (data['name'] ?? firebaseUser.displayName)?.toString(),
    });
  }

  void _watchUserProfile(User firebaseUser) {
    _profileSubscription?.cancel();
    _profileSubscription = _firestore
        .collection('users')
        .doc(firebaseUser.uid)
        .snapshots()
        .listen(
          (snapshot) async {
            if (_signingOut) return;
            final data = snapshot.data();
            if (data == null) return;
            if (_int(data['status'], fallback: 1) == 0) {
              await signOut();
              return;
            }
            final user = _appUserFromData(firebaseUser, data);
            _currentUser = user;
            if (!_authState.isClosed) _authState.add(user);
          },
          onError: (Object error, StackTrace stackTrace) {
            _debugFailure('watchUserProfile', error, stackTrace);
            if (!_authState.isClosed) {
              _authState.addError(error, stackTrace);
            }
          },
        );
  }

  void _consumeVerifiedPassword() {
    final password = _verifiedPassword;
    _verifiedPassword = null;
    if (password == null || password.isEmpty) {
      throw const AuthFailure(
        'Verify your password before opening this resource.',
      );
    }
  }

  String _authMessage(FirebaseAuthException error) => switch (error.code) {
    'email-already-in-use' => 'This email address is already registered.',
    'invalid-email' => 'Enter a valid email address.',
    'invalid-credential' ||
    'wrong-password' ||
    'user-not-found' => 'Invalid username or password.',
    'weak-password' => 'Use a stronger password with at least 8 characters.',
    'too-many-requests' => 'Too many attempts. Please wait and try again.',
    'operation-not-allowed' =>
      'Email and password sign-in is not enabled for this Firebase project.',
    'requires-recent-login' =>
      'Please sign out and sign in again before changing this information.',
    'network-request-failed' =>
      'Unable to reach Firebase. Check your connection.',
    _ => error.message ?? 'Firebase authentication failed.',
  };

  String _functionsMessage(
    FirebaseFunctionsException error, {
    String fallback = 'Unable to create your profile.',
  }) {
    final message = _cleanFirebaseMessage(error.message);
    if (message != null) return message;

    return _firebaseCodeMessage(error.code, fallback: fallback);
  }

  String _firebaseMessage(FirebaseException error, {required String fallback}) {
    final message = _cleanFirebaseMessage(error.message);
    if (message != null && error.code.toLowerCase() != 'unavailable') {
      return message;
    }
    return _firebaseCodeMessage(error.code, fallback: fallback);
  }

  String _callableFailureMessage(
    FirebaseCallableFailure error, {
    String fallback = 'Unable to complete this request. Please try again.',
  }) {
    final message = _cleanFirebaseMessage(error.message);
    if (message != null) return message;
    return _firebaseCodeMessage(error.code, fallback: fallback);
  }

  Future<void> dispose() async {
    _initialAuthTimer?.cancel();
    await _firebaseSubscription?.cancel();
    await _profileSubscription?.cancel();
    await _authState.close();
  }
}

bool _canUseClientProfileFallback(Map<String, dynamic> _, String? errorCode) {
  // Registration and referral ownership are privileged mutations. They must
  // never fall back to direct client writes when the trusted function is down.
  return false;
}

String? _cleanFirebaseMessage(String? value) {
  final message = value?.trim();
  if (message == null || message.isEmpty) return null;
  final normalized = message.toUpperCase().replaceAll('-', '_');
  final normalizedSentence = message.toLowerCase().replaceAll(
    RegExp(r'\s+'),
    ' ',
  );
  const statusCodes = {
    'UNAVAILABLE',
    'DEADLINE_EXCEEDED',
    'PERMISSION_DENIED',
    'UNAUTHENTICATED',
    'RESOURCE_EXHAUSTED',
    'FAILED_PRECONDITION',
    'INTERNAL',
    'UNKNOWN',
    'NOT_FOUND',
  };
  const genericMessages = {
    'an internal error has occurred.',
    'an internal error occurred.',
    'internal error',
    'unknown error',
  };
  return statusCodes.contains(normalized) ||
          genericMessages.contains(normalizedSentence)
      ? null
      : message;
}

String _firebaseCodeMessage(String code, {required String fallback}) {
  return switch (code.toLowerCase()) {
    'unavailable' =>
      'Service temporarily unavailable or network/Firebase connection failed. Please check your internet connection and try again.',
    'permission-denied' =>
      'Your account does not have permission to complete this request.',
    'unauthenticated' => 'Sign in before completing this request.',
    'deadline-exceeded' =>
      'The Firebase request took too long. Please try again.',
    'resource-exhausted' =>
      'Firebase is receiving too many requests right now. Please wait and try again.',
    'failed-precondition' =>
      'This request cannot be completed yet. Please refresh and try again.',
    'not-found' =>
      'The registration service is not available. Please try again later.',
    'internal' ||
    'unknown' => 'Firebase could not complete the request. Please try again.',
    _ => fallback,
  };
}

int _publicRegistrationRoleOrDefault(int roleId) =>
    AuthRepository._publicRegistrationRoles.contains(roleId) ? roleId : 750;

Map<String, bool> _emptyAdminPermissions() => const {
  'canInviteSuperAdmin': false,
  'canInviteSubAdmin': false,
  'canInviteMarketAnalyst': false,
  'canInviteSignalProvider': false,
  'canInviteFinancialDepartment': false,
  'canInviteLeader': false,
  'canInviteRecruiter': false,
  'canInviteTrader': false,
  'canInviteLeadClient': false,
  'canAccessAdminDashboard': false,
  'canManageUsers': false,
  'canManageSignals': false,
  'canManagePayments': false,
  'canManageReports': false,
};

String normalizeReferralCode(Object? value) => _normalizeCompatibilityAscii(
  _text(value),
).replaceAll(RegExp(r'\s+'), '').toLowerCase();

String _randomReferralCode() {
  const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  return List.generate(
    8,
    (_) => alphabet[AuthRepository._secureRandom.nextInt(alphabet.length)],
    growable: false,
  ).join();
}

String _text(Object? value, [String fallback = '']) {
  final resolved = value == null ? '' : value.toString().trim();
  return resolved.isEmpty ? fallback : resolved;
}

String _normalizeCompatibilityAscii(String value) {
  return String.fromCharCodes(
    value.runes.map((rune) {
      if (rune == 0x3000) return 0x20;
      if (rune >= 0xFF01 && rune <= 0xFF5E) return rune - 0xFEE0;
      return rune;
    }),
  );
}

void _debugAuth(
  String operation,
  String message, [
  Map<String, Object?> details = const {},
]) {
  if (!kDebugMode) return;
  final suffix = details.isEmpty
      ? ''
      : ' ${details.entries.map((entry) => '${entry.key}=${entry.value}').join(' ')}';
  debugPrint('[HC Trader Auth][$operation] $message$suffix');
}

void _debugFailure(String operation, Object error, StackTrace stackTrace) {
  if (!kDebugMode) return;
  if (error is FirebaseException) {
    debugPrint(
      '[HC Trader Auth][$operation] FirebaseException '
      'plugin=${error.plugin} code=${error.code} message=${error.message}',
    );
  } else {
    debugPrint('[HC Trader Auth][$operation] ${error.runtimeType}: $error');
  }
  debugPrint('[HC Trader Auth][$operation] stackTrace=$stackTrace');
}

void _debugPasswordResetFailure(
  String operation,
  String code,
  StackTrace stackTrace,
) {
  if (!kDebugMode) return;
  // Do not log the submitted address, action link, or Firebase response body.
  debugPrint(
    '[HC Trader Auth][$operation] passwordResetFailure code=${code.trim()}',
  );
  debugPrint('[HC Trader Auth][$operation] stackTrace=$stackTrace');
}

class _ProfileServiceUnavailable implements Exception {
  const _ProfileServiceUnavailable({required this.code, required this.message});

  final String code;
  final String message;

  @override
  String toString() => message;
}

class _ReferralCodeCollision implements Exception {
  const _ReferralCodeCollision();
}

class RegistrationRole {
  const RegistrationRole({
    required this.id,
    required this.name,
    this.description,
  });

  final int id;
  final String name;
  final String? description;

  factory RegistrationRole.fromJson(Map<String, dynamic> json) =>
      RegistrationRole(
        id: int.tryParse('${json['role_id'] ?? json['id'] ?? 0}') ?? 0,
        name: (json['name'] ?? 'Role').toString(),
        description: json['description']?.toString(),
      );
}

class ProfileUpdateResult {
  const ProfileUpdateResult({required this.emailVerificationSent});

  final bool emailVerificationSent;
}

class ProfileUpdateValues {
  const ProfileUpdateValues({
    required this.name,
    required this.email,
    required this.phoneNumber,
  });

  final String name;
  final String email;
  final String? phoneNumber;
}

ProfileUpdateValues normalizeProfileUpdateValues({
  required String name,
  required String email,
  String? phoneNumber,
}) {
  return ProfileUpdateValues(
    name: name.trim(),
    email: email.trim().toLowerCase(),
    phoneNumber: phoneNumber?.trim(),
  );
}

class AuthFailure implements Exception {
  const AuthFailure(this.message);

  final String message;

  @override
  String toString() => message;
}

enum PasswordResetEmailValidation { empty, invalid }

enum PasswordResetFailureKind {
  invalidEmail,
  rateLimited,
  network,
  configuration,
  unknown,
}

class PasswordResetFailure implements Exception {
  const PasswordResetFailure(this.kind);

  final PasswordResetFailureKind kind;

  @override
  String toString() => 'Password reset could not be completed (${kind.name}).';
}

String normalizePasswordResetEmail(String value) => value.trim().toLowerCase();

PasswordResetEmailValidation? validatePasswordResetEmail(String value) {
  final email = value.trim();
  if (email.isEmpty) return PasswordResetEmailValidation.empty;
  if (email.length > 254 ||
      email.contains(RegExp(r'[\s\r\n,;]')) ||
      email.indexOf('@') <= 0 ||
      email.indexOf('@') != email.lastIndexOf('@')) {
    return PasswordResetEmailValidation.invalid;
  }

  final parts = email.split('@');
  final local = parts.first;
  final domain = parts.last;
  if (local.length > 64 ||
      local.startsWith('.') ||
      local.endsWith('.') ||
      local.contains('..') ||
      domain.length > 253 ||
      !domain.contains('.')) {
    return PasswordResetEmailValidation.invalid;
  }

  final labels = domain.split('.');
  if (labels.any(
    (label) =>
        label.isEmpty ||
        label.length > 63 ||
        label.startsWith('-') ||
        label.endsWith('-') ||
        !RegExp(r'^[a-zA-Z0-9-]+$').hasMatch(label),
  )) {
    return PasswordResetEmailValidation.invalid;
  }
  return null;
}

bool isPasswordResetEnumerationSafeCode(String rawCode) {
  final code = rawCode.trim().toLowerCase().replaceAll('_', '-');
  return const {
    'user-not-found',
    'email-not-found',
    'user-disabled',
  }.contains(code);
}

PasswordResetFailureKind passwordResetFailureKindForCode(String rawCode) {
  final code = rawCode.trim().toLowerCase().replaceAll('_', '-');
  return switch (code) {
    'invalid-email' => PasswordResetFailureKind.invalidEmail,
    'too-many-requests' ||
    'too-many-attempts-try-later' => PasswordResetFailureKind.rateLimited,
    'network-request-failed' ||
    'unavailable' ||
    'deadline-exceeded' ||
    'timeout' => PasswordResetFailureKind.network,
    'operation-not-allowed' ||
    'app-not-authorized' ||
    'invalid-api-key' ||
    'api-key-not-valid' ||
    'failed-precondition' ||
    'unauthorized-domain' => PasswordResetFailureKind.configuration,
    _ => PasswordResetFailureKind.unknown,
  };
}

int _int(Object? value, {int fallback = 0}) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;

Map<String, dynamic> _jsonObject(String value) {
  final decoded = jsonDecode(value);
  if (decoded is! Map) {
    throw const FormatException('Expected a JSON object.');
  }
  return Map<String, dynamic>.from(decoded);
}

String _callableErrorMessage(Map<dynamic, dynamic> error) {
  final message = error['message']?.toString().trim();
  if (_cleanFirebaseMessage(message) case final cleaned?) return cleaned;

  return switch (error['status']?.toString().toUpperCase()) {
    'ALREADY_EXISTS' => 'This account or username is already registered.',
    'INVALID_ARGUMENT' => 'Check your registration details and try again.',
    'PERMISSION_DENIED' => 'You are not allowed to complete this registration.',
    'UNAUTHENTICATED' => 'Sign in before completing registration.',
    'UNAVAILABLE' =>
      'Service temporarily unavailable or network/Firebase connection failed. Please check your internet connection and try again.',
    'DEADLINE_EXCEEDED' =>
      'The registration service took too long to respond. Please try again.',
    _ => 'Unable to create your profile.',
  };
}

bool _isProfileServiceUnavailableStatus(String? status) => const {
  'UNAVAILABLE',
  'DEADLINE_EXCEEDED',
  'NOT_FOUND',
  'INTERNAL',
  'UNKNOWN',
}.contains(status);

String _callableStatusCode(String? status) {
  return switch (status) {
    'DEADLINE_EXCEEDED' => 'deadline-exceeded',
    'NOT_FOUND' => 'not-found',
    'INTERNAL' => 'internal',
    'UNKNOWN' => 'unknown',
    _ => 'unavailable',
  };
}
