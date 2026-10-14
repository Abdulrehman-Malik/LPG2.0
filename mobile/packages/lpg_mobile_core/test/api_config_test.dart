import 'package:flutter_test/flutter_test.dart';
import 'package:lpg_mobile_core/lpg_mobile_core.dart';

void main() {
  group('LpgApiConfig', () {
    test('normalizes API v1 base URL with a trailing slash', () {
      final config = LpgApiConfig.parse('https://api.example.test/api/v1');
      expect(config.baseUri.toString(), 'https://api.example.test/api/v1/');
      expect(
        config.resolve('customer/auth/login').toString(),
        'https://api.example.test/api/v1/customer/auth/login',
      );
    });

    test('rejects URLs that omit the versioned API prefix', () {
      expect(
        () => LpgApiConfig.parse('https://api.example.test'),
        throwsFormatException,
      );
    });
  });
}
