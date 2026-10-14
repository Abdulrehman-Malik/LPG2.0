import 'package:flutter/material.dart';
import 'package:lpg_mobile_core/lpg_mobile_core.dart';

import 'app.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  try {
    final config = LpgApiConfig.fromEnvironment();
    final tokenStore = SecureTokenStore();
    final client = LpgApiClient(config: config, tokenStore: tokenStore);
    runApp(BusinessStaffApp(
      client: client,
      tokenStore: tokenStore,
      authService: LpgAuthService(client: client, tokenStore: tokenStore),
    ));
  } on FormatException catch (e) {
    runApp(_ConfigurationErrorApp(message: e.message));
  }
}

class _ConfigurationErrorApp extends StatelessWidget {
  const _ConfigurationErrorApp({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => MaterialApp(
        home: Scaffold(
          body: Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text('App configuration required\n$message', textAlign: TextAlign.center),
            ),
          ),
        ),
      );
}
