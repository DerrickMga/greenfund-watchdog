import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../models/account_models.dart';
import 'account_api.dart';

class AccountController extends ChangeNotifier {
  AccountController({
    AccountApi? api,
    SharedPreferencesAsync? preferences,
  })  : _api = api ?? AccountApi(),
        _preferences = preferences ?? SharedPreferencesAsync();

  static const _tokenKey = 'mobile_access_token';

  final AccountApi _api;
  final SharedPreferencesAsync _preferences;
  AccountState _state = const AccountState();

  AccountState get state => _state;

  Future<void> bootstrap() async {
    _setState(_state.copyWith(isLoading: true, clearError: true));

    try {
      final plans = await _api.plans();
      final token = await _preferences.getString(_tokenKey);
      final user = token == null ? null : await _api.me(token);

      _setState(_state.copyWith(
        token: token,
        user: user,
        plans: plans,
        isLoading: false,
      ));
    } catch (error) {
      _setState(_state.copyWith(
        isLoading: false,
        errorMessage: _messageFor(error),
      ));
    }
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
  }) async {
    await _runAuth(() => _api.register(
          name: name,
          email: email,
          password: password,
        ));
  }

  Future<void> login({
    required String email,
    required String password,
  }) async {
    await _runAuth(() => _api.login(email: email, password: password));
  }

  Future<void> selectPlan(String planCode) async {
    final token = _state.token;
    if (token == null) return;

    _setState(_state.copyWith(isLoading: true, clearError: true));

    try {
      await _api.selectPlan(token: token, planCode: planCode);
      final user = await _api.me(token);
      _setState(_state.copyWith(user: user, isLoading: false));
    } catch (error) {
      _setState(_state.copyWith(
        isLoading: false,
        errorMessage: _messageFor(error),
      ));
    }
  }

  Future<void> logout() async {
    final token = _state.token;
    if (token != null) {
      try {
        await _api.logout(token);
      } catch (_) {
        // Local logout should still complete if the server is unavailable.
      }
    }

    await _preferences.remove(_tokenKey);
    _setState(_state.copyWith(clearUser: true, clearError: true));
  }

  Future<void> _runAuth(
    Future<({String token, AppUser user})> Function() request,
  ) async {
    _setState(_state.copyWith(isLoading: true, clearError: true));

    try {
      final session = await request();
      await _preferences.setString(_tokenKey, session.token);
      _setState(_state.copyWith(
        token: session.token,
        user: session.user,
        isLoading: false,
      ));
    } catch (error) {
      _setState(_state.copyWith(
        isLoading: false,
        errorMessage: _messageFor(error),
      ));
    }
  }

  String _messageFor(Object error) {
    if (error is AccountApiException) {
      return error.message;
    }

    if (error is TimeoutException) {
      return 'The account service timed out. Check your connection and try again.';
    }

    if (error is http.ClientException) {
      return 'Could not reach https://vpn.kmgvitallinks.com. Check DNS, HTTPS, and server status.';
    }

    return 'Could not reach the account service. Check DNS, HTTPS, and server status.';
  }

  void _setState(AccountState next) {
    _state = next;
    notifyListeners();
  }

  @override
  void dispose() {
    _api.close();
    super.dispose();
  }
}
