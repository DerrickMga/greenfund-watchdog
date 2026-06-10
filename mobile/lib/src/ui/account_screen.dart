import 'package:flutter/material.dart';

import '../app.dart';
import '../models/account_models.dart';

class AccountScreen extends StatelessWidget {
  const AccountScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.accountState;

    return Scaffold(
      appBar: AppBar(title: const Text('Account')),
      body: SafeArea(
        child: state.isSignedIn
            ? _SignedInAccount(state: state)
            : _AuthForms(state: state),
      ),
    );
  }
}

class _SignedInAccount extends StatelessWidget {
  const _SignedInAccount({required this.state});

  final AccountState state;

  @override
  Widget build(BuildContext context) {
    final user = state.user!;
    final activeCode = user.subscription?.plan?.code;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: ListTile(
            leading: const Icon(Icons.verified_user),
            title: Text(user.name),
            subtitle: Text(user.email),
            trailing: TextButton(
              onPressed: state.isLoading ? null : context.account.logout,
              child: const Text('Sign out'),
            ),
          ),
        ),
        const SizedBox(height: 16),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Subscription', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                Text(_subscriptionLine(user.subscription)),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        for (final plan in state.plans) ...[
          _PlanTile(
            plan: plan,
            isActive: plan.code == activeCode,
            isLoading: state.isLoading,
          ),
          const SizedBox(height: 10),
        ],
        if (state.errorMessage != null)
          Text(
            state.errorMessage!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
      ],
    );
  }

  String _subscriptionLine(UserSubscription? subscription) {
    if (subscription == null) {
      return 'No active plan yet.';
    }

    final plan = subscription.plan?.name ?? 'Unknown plan';
    return '$plan - ${subscription.status} via ${subscription.provider}';
  }
}

class _PlanTile extends StatelessWidget {
  const _PlanTile({
    required this.plan,
    required this.isActive,
    required this.isLoading,
  });

  final SubscriptionPlan plan;
  final bool isActive;
  final bool isLoading;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    plan.name,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                Text(plan.displayPrice),
              ],
            ),
            const SizedBox(height: 6),
            Text('${plan.deviceLimit} device limit'),
            const SizedBox(height: 8),
            for (final feature in plan.features)
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Row(
                  children: [
                    const Icon(Icons.check, size: 16),
                    const SizedBox(width: 8),
                    Expanded(child: Text(feature)),
                  ],
                ),
              ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: isActive || isLoading
                    ? null
                    : () => context.account.selectPlan(plan.code),
                icon: Icon(isActive ? Icons.done : Icons.workspace_premium),
                label: Text(isActive ? 'Current plan' : 'Choose plan'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AuthForms extends StatefulWidget {
  const _AuthForms({required this.state});

  final AccountState state;

  @override
  State<_AuthForms> createState() => _AuthFormsState();
}

class _AuthFormsState extends State<_AuthForms> {
  bool creating = true;
  final name = TextEditingController();
  final email = TextEditingController();
  final password = TextEditingController();

  @override
  void dispose() {
    name.dispose();
    email.dispose();
    password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = widget.state;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        SegmentedButton<bool>(
          segments: const [
            ButtonSegment(
              value: true,
              label: Text('Create'),
              icon: Icon(Icons.person_add),
            ),
            ButtonSegment(
              value: false,
              label: Text('Login'),
              icon: Icon(Icons.login),
            ),
          ],
          selected: {creating},
          onSelectionChanged: (selected) {
            setState(() => creating = selected.first);
          },
        ),
        const SizedBox(height: 16),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                if (creating) ...[
                  TextField(
                    controller: name,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Name',
                      prefixIcon: Icon(Icons.badge),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
                TextField(
                  controller: email,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    prefixIcon: Icon(Icons.email),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: password,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Password',
                    prefixIcon: Icon(Icons.password),
                  ),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: state.isLoading ? null : _submit,
                    icon: Icon(creating ? Icons.person_add : Icons.login),
                    label: Text(creating ? 'Create account' : 'Login'),
                  ),
                ),
              ],
            ),
          ),
        ),
        if (state.errorMessage != null) ...[
          const SizedBox(height: 16),
          Text(
            state.errorMessage!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ],
      ],
    );
  }

  Future<void> _submit() async {
    if (creating) {
      await context.account.register(
        name: name.text.trim(),
        email: email.text.trim(),
        password: password.text,
      );
      return;
    }

    await context.account.login(
      email: email.text.trim(),
      password: password.text,
    );
  }
}
