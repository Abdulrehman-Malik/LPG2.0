import 'package:flutter/material.dart';
import 'package:lpg_mobile_core/lpg_mobile_core.dart';

import 'home_screen.dart';

class BusinessStaffApp extends StatefulWidget {
  const BusinessStaffApp({
    super.key,
    required this.client,
    required this.tokenStore,
    required this.authService,
  });

  final LpgApiClient client;
  final TokenStore tokenStore;
  final LpgAuthService authService;

  @override
  State<BusinessStaffApp> createState() => _BusinessStaffAppState();
}

class _BusinessStaffAppState extends State<BusinessStaffApp> {
  late Future<bool> _session;

  @override
  void initState() {
    super.initState();
    _session = _hasStaffSession();
  }

  Future<bool> _hasStaffSession() async {
    final tokens = await widget.tokenStore.read();
    if (tokens == null) return false;
    if (tokens.audience != 'staff') {
      await widget.tokenStore.clear();
      return false;
    }
    return true;
  }

  void _loggedIn() => setState(() => _session = Future<bool>.value(true));

  Future<void> _loggedOut() async {
    await widget.authService.logout();
    if (mounted) setState(() => _session = Future<bool>.value(false));
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
        title: 'LPG Business & Staff',
        theme: ThemeData(colorSchemeSeed: const Color(0xFF245B9E), useMaterial3: true),
        home: FutureBuilder<bool>(
          future: _session,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Scaffold(body: Center(child: CircularProgressIndicator()));
            }
            if (snapshot.data == true) {
              return StaffHomeScreen(client: widget.client, onLogout: _loggedOut);
            }
            return LpgLoginScreen(
              appName: 'LPG Business & Staff',
              audience: 'staff',
              authService: widget.authService,
              onLoggedIn: _loggedIn,
            );
          },
        ),
      );
}
