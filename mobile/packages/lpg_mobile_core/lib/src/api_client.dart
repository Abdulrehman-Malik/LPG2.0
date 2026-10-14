import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api_config.dart';
import 'session_store.dart';

class ApiException implements Exception {
  const ApiException({
    required this.statusCode,
    required this.code,
    required this.message,
  });

  final int statusCode;
  final String code;
  final String message;

  @override
  String toString() => '$code: $message';
}

class LpgApiClient {
  LpgApiClient({
    required LpgApiConfig config,
    required TokenStore tokenStore,
    http.Client? httpClient,
  })  : _config = config,
        _tokenStore = tokenStore,
        _http = httpClient ?? http.Client();

  final LpgApiConfig _config;
  final TokenStore _tokenStore;
  final http.Client _http;

  Uri resolve(String path) => _config.resolve(path);

  Future<Map<String, dynamic>> getJson(
    String path, {
    Map<String, String> headers = const {},
  }) {
    return _request('GET', path, headers: headers);
  }

  Future<Map<String, dynamic>> postJson(
    String path,
    Map<String, dynamic> body, {
    Map<String, String> headers = const {},
  }) {
    return _request('POST', path, body: body, headers: headers);
  }

  Future<Map<String, dynamic>> postPublic(
    String path,
    Map<String, dynamic> body, {
    Map<String, String> headers = const {},
  }) {
    return _request(
      'POST',
      path,
      body: body,
      headers: headers,
      authenticated: false,
    );
  }

  Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
    bool authenticated = true,
  }) async {
    final tokens = authenticated ? await _tokenStore.read() : null;
    var response = await _send(
      method,
      path,
      body: body,
      headers: headers,
      accessToken: tokens?.accessToken,
    );

    if (authenticated && response.statusCode == 401 && tokens != null) {
      final refreshed = await _refresh(tokens);
      if (refreshed) {
        final replacement = await _tokenStore.read();
        response = await _send(
          method,
          path,
          body: body,
          headers: headers,
          accessToken: replacement?.accessToken,
        );
      } else {
        await _tokenStore.clear();
      }
    }

    return _unwrap(response);
  }

  Future<http.Response> _send(
    String method,
    String path, {
    required Map<String, String> headers,
    required String? accessToken,
    Map<String, dynamic>? body,
  }) {
    final requestHeaders = <String, String>{
      'Accept': 'application/json',
      if (body != null) 'Content-Type': 'application/json',
      ...headers,
      if (accessToken != null && accessToken.isNotEmpty)
        'Authorization': 'Bearer $accessToken',
    };
    final uri = resolve(path);
    switch (method) {
      case 'GET':
        return _http.get(uri, headers: requestHeaders);
      case 'POST':
        return _http.post(
          uri,
          headers: requestHeaders,
          body: body == null ? null : jsonEncode(body),
        );
      default:
        throw ArgumentError.value(method, 'method', 'Unsupported HTTP method');
    }
  }

  Future<bool> _refresh(SessionTokens tokens) async {
    try {
      final response = await _http.post(
        resolve('${tokens.audience}/auth/refresh'),
        headers: const {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({'refresh_token': tokens.refreshToken}),
      );
      if (response.statusCode < 200 || response.statusCode >= 300) {
        return false;
      }
      final root = _decode(response);
      final data = root['data'];
      if (data is! Map || data['tokens'] is! Map) return false;
      final replacement = SessionTokens.fromJson(
        Map<String, dynamic>.from(data['tokens'] as Map),
      );
      if (replacement.audience != tokens.audience) return false;
      await _tokenStore.write(replacement);
      return true;
    } catch (_) {
      return false;
    }
  }

  Map<String, dynamic> _unwrap(http.Response response) {
    final root = _decode(response);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final error = root['error'];
      final errorMap = error is Map ? error : const <String, dynamic>{};
      throw ApiException(
        statusCode: response.statusCode,
        code: (errorMap['code'] ?? 'API_ERROR').toString(),
        message: (errorMap['message'] ?? 'The API request failed.').toString(),
      );
    }
    return root;
  }

  Map<String, dynamic> _decode(http.Response response) {
    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) return decoded;
      if (decoded is Map) return Map<String, dynamic>.from(decoded);
    } on FormatException {
      // A non-JSON response is converted into a stable API error below.
    }
    return <String, dynamic>{};
  }

  void close() => _http.close();
}
