import 'api_client.dart';
import 'session_store.dart';

class LpgAuthService {
  LpgAuthService({required this.client, required this.tokenStore});

  final LpgApiClient client;
  final TokenStore tokenStore;

  Future<Map<String, dynamic>> login({
    required String audience,
    required String username,
    required String password,
  }) async {
    if (!const ['customer', 'staff'].contains(audience)) {
      throw ArgumentError.value(audience, 'audience');
    }
    final root = await client.postPublic(
      '$audience/auth/login',
      {'username': username.trim(), 'password': password},
    );
    final data = root['data'];
    if (data is! Map || data['tokens'] is! Map) {
      throw const FormatException('The API login response is incomplete.');
    }
    final tokens = SessionTokens.fromJson(
      Map<String, dynamic>.from(data['tokens'] as Map),
    );
    if (tokens.audience != audience) {
      throw const FormatException('The API returned a token for the wrong app.');
    }
    await tokenStore.write(tokens);
    return Map<String, dynamic>.from(data);
  }

  Future<void> logout() async {
    final tokens = await tokenStore.read();
    try {
      if (tokens != null) {
        await client.postJson('${tokens.audience}/auth/logout', {});
      }
    } finally {
      await tokenStore.clear();
    }
  }
}
