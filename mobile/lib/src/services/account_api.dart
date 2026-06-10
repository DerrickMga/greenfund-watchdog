import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/account_models.dart';

class AccountApi {
  AccountApi({
    http.Client? client,
    String? baseUrl,
  })  : _client = client ?? http.Client(),
        _baseUrl = baseUrl ??
            const String.fromEnvironment(
              'API_BASE_URL',
              defaultValue: 'http://10.0.2.2:8000/api',
            );

  final http.Client _client;
  final String _baseUrl;

  Future<({String token, AppUser user})> register({
    required String name,
    required String email,
    required String password,
  }) async {
    final json = await _post('/mobile/register', {
      'name': name,
      'email': email,
      'password': password,
      'device_name': 'Greenfund VPN',
    });

    return (
      token: json['token'] as String,
      user: AppUser.fromJson(json['user'] as Map<String, dynamic>),
    );
  }

  Future<({String token, AppUser user})> login({
    required String email,
    required String password,
  }) async {
    final json = await _post('/mobile/login', {
      'email': email,
      'password': password,
      'device_name': 'Greenfund VPN',
    });

    return (
      token: json['token'] as String,
      user: AppUser.fromJson(json['user'] as Map<String, dynamic>),
    );
  }

  Future<AppUser> me(String token) async {
    final json = await _get('/mobile/me', token: token);
    return AppUser.fromJson(json['user'] as Map<String, dynamic>);
  }

  Future<List<SubscriptionPlan>> plans() async {
    final json = await _get('/mobile/subscription-plans');
    final plans = json['plans'] as List<dynamic>;
    return plans
        .map((item) => SubscriptionPlan.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> selectPlan({
    required String token,
    required String planCode,
  }) async {
    await _post('/mobile/subscription/select-plan', {
      'plan_code': planCode,
    }, token: token);
  }

  Future<void> logout(String token) async {
    await _post('/mobile/logout', {}, token: token);
  }

  Future<Map<String, dynamic>> _get(String path, {String? token}) async {
    final response = await _client.get(
      Uri.parse('$_baseUrl$path'),
      headers: _headers(token),
    );

    return _decode(response);
  }

  Future<Map<String, dynamic>> _post(
    String path,
    Map<String, dynamic> body, {
    String? token,
  }) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl$path'),
      headers: _headers(token),
      body: jsonEncode(body),
    );

    return _decode(response);
  }

  Map<String, String> _headers(String? token) {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  Map<String, dynamic> _decode(http.Response response) {
    final json = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode >= 400) {
      throw AccountApiException(json['message']?.toString() ?? 'Request failed.');
    }

    return json;
  }

  void close() {
    _client.close();
  }
}

class AccountApiException implements Exception {
  const AccountApiException(this.message);

  final String message;

  @override
  String toString() => message;
}
