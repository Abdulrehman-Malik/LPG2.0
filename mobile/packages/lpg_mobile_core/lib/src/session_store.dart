import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SessionTokens {
  const SessionTokens({
    required this.accessToken,
    required this.refreshToken,
    required this.audience,
  });

  final String accessToken;
  final String refreshToken;
  final String audience;

  factory SessionTokens.fromJson(Map<String, dynamic> json) {
    final accessToken = (json['access_token'] ?? '').toString();
    final refreshToken = (json['refresh_token'] ?? '').toString();
    final audience = (json['audience'] ?? '').toString();
    if (accessToken.isEmpty ||
        refreshToken.isEmpty ||
        !const ['customer', 'staff'].contains(audience)) {
      throw const FormatException('The API returned an invalid session token set.');
    }
    return SessionTokens(
      accessToken: accessToken,
      refreshToken: refreshToken,
      audience: audience,
    );
  }
}

abstract interface class TokenStore {
  Future<SessionTokens?> read();
  Future<void> write(SessionTokens tokens);
  Future<void> clear();
}

class SecureTokenStore implements TokenStore {
  SecureTokenStore({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _accessKey = 'lpg_access_token';
  static const _refreshKey = 'lpg_refresh_token';
  static const _audienceKey = 'lpg_session_audience';

  @override
  Future<SessionTokens?> read() async {
    final access = await _storage.read(key: _accessKey);
    final refresh = await _storage.read(key: _refreshKey);
    final audience = await _storage.read(key: _audienceKey);
    if (access == null || refresh == null || audience == null) return null;
    if (access.isEmpty || refresh.isEmpty ||
        !const ['customer', 'staff'].contains(audience)) {
      await clear();
      return null;
    }
    return SessionTokens(
      accessToken: access,
      refreshToken: refresh,
      audience: audience,
    );
  }

  @override
  Future<void> write(SessionTokens tokens) async {
    await _storage.write(key: _accessKey, value: tokens.accessToken);
    await _storage.write(key: _refreshKey, value: tokens.refreshToken);
    await _storage.write(key: _audienceKey, value: tokens.audience);
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: _accessKey);
    await _storage.delete(key: _refreshKey);
    await _storage.delete(key: _audienceKey);
  }
}
