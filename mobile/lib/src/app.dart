import 'package:flutter/material.dart';

import 'models/account_models.dart';
import 'models/vpn_models.dart';
import 'services/account_controller.dart';
import 'services/vpn_controller.dart';
import 'theme/app_theme.dart';
import 'ui/home_screen.dart';

class GreenfundVpnApp extends StatefulWidget {
  const GreenfundVpnApp({super.key});

  @override
  State<GreenfundVpnApp> createState() => _GreenfundVpnAppState();
}

class _GreenfundVpnAppState extends State<GreenfundVpnApp> {
  late final VpnController controller;
  late final AccountController accountController;

  @override
  void initState() {
    super.initState();
    controller = VpnController.seeded();
    accountController = AccountController()..bootstrap();
  }

  @override
  void dispose() {
    controller.dispose();
    accountController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AccountScope(
      controller: accountController,
      child: VpnScope(
        controller: controller,
        child: MaterialApp(
          title: 'Greenfund VPN',
          debugShowCheckedModeBanner: false,
          theme: buildAppTheme(),
          home: const HomeScreen(),
        ),
      ),
    );
  }
}

class AccountScope extends InheritedNotifier<AccountController> {
  const AccountScope({
    required AccountController controller,
    required super.child,
    super.key,
  }) : super(notifier: controller);

  static AccountController of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AccountScope>();
    assert(scope != null, 'AccountScope not found in context.');
    return scope!.notifier!;
  }
}

class VpnScope extends InheritedNotifier<VpnController> {
  const VpnScope({
    required VpnController controller,
    required super.child,
    super.key,
  }) : super(notifier: controller);

  static VpnController of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<VpnScope>();
    assert(scope != null, 'VpnScope not found in context.');
    return scope!.notifier!;
  }
}

extension VpnContext on BuildContext {
  VpnController get vpn => VpnScope.of(this);
  VpnState get vpnState => VpnScope.of(this).state;
  AccountController get account => AccountScope.of(this);
  AccountState get accountState => AccountScope.of(this).state;
}
