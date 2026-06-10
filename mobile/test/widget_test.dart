import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:greenfund_vpn/src/app.dart';
import 'package:greenfund_vpn/src/services/vpn_controller.dart';
import 'package:greenfund_vpn/src/ui/home_screen.dart';

void main() {
  testWidgets('shows the VPN dashboard', (tester) async {
    final controller = VpnController.seeded();

    await tester.pumpWidget(
      VpnScope(
        controller: controller,
        child: const GreenfundVpnAppShell(),
      ),
    );

    expect(find.text('Greenfund VPN'), findsOneWidget);
    expect(find.text('Connect VPN'), findsOneWidget);
    expect(find.text('Servers'), findsOneWidget);

    controller.dispose();
  });
}

class GreenfundVpnAppShell extends StatelessWidget {
  const GreenfundVpnAppShell({super.key});

  @override
  Widget build(BuildContext context) {
    return const MaterialApp(home: HomeScreen());
  }
}
