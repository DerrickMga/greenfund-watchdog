class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    this.subscription,
  });

  final int id;
  final String name;
  final String email;
  final UserSubscription? subscription;

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      subscription: json['subscription'] == null
          ? null
          : UserSubscription.fromJson(json['subscription'] as Map<String, dynamic>),
    );
  }
}

class UserSubscription {
  const UserSubscription({
    required this.status,
    required this.provider,
    this.trialEndsAt,
    this.renewsAt,
    this.endsAt,
    this.plan,
  });

  final String status;
  final String provider;
  final String? trialEndsAt;
  final String? renewsAt;
  final String? endsAt;
  final SubscriptionPlan? plan;

  factory UserSubscription.fromJson(Map<String, dynamic> json) {
    return UserSubscription(
      status: json['status'] as String,
      provider: json['provider'] as String? ?? 'manual',
      trialEndsAt: json['trial_ends_at'] as String?,
      renewsAt: json['renews_at'] as String?,
      endsAt: json['ends_at'] as String?,
      plan: json['plan'] == null
          ? null
          : SubscriptionPlan.fromJson(json['plan'] as Map<String, dynamic>),
    );
  }
}

class SubscriptionPlan {
  const SubscriptionPlan({
    required this.code,
    required this.name,
    required this.priceCents,
    required this.currency,
    required this.interval,
    required this.deviceLimit,
    required this.features,
  });

  final String code;
  final String name;
  final int priceCents;
  final String currency;
  final String interval;
  final int deviceLimit;
  final List<String> features;

  factory SubscriptionPlan.fromJson(Map<String, dynamic> json) {
    final rawFeatures = json['features'];

    return SubscriptionPlan(
      code: json['code'] as String,
      name: json['name'] as String,
      priceCents: json['price_cents'] as int? ?? 0,
      currency: json['currency'] as String? ?? 'USD',
      interval: json['interval'] as String? ?? 'month',
      deviceLimit: json['device_limit'] as int? ?? 1,
      features: rawFeatures is List
          ? rawFeatures.map((item) => item.toString()).toList()
          : const [],
    );
  }

  String get displayPrice {
    final amount = (priceCents / 100).toStringAsFixed(2);
    return '$currency $amount/$interval';
  }
}

class AccountState {
  const AccountState({
    this.user,
    this.token,
    this.plans = const [],
    this.isLoading = false,
    this.errorMessage,
  });

  final AppUser? user;
  final String? token;
  final List<SubscriptionPlan> plans;
  final bool isLoading;
  final String? errorMessage;

  bool get isSignedIn => user != null && token != null;

  AccountState copyWith({
    AppUser? user,
    String? token,
    List<SubscriptionPlan>? plans,
    bool? isLoading,
    String? errorMessage,
    bool clearUser = false,
    bool clearError = false,
  }) {
    return AccountState(
      user: clearUser ? null : user ?? this.user,
      token: clearUser ? null : token ?? this.token,
      plans: plans ?? this.plans,
      isLoading: isLoading ?? this.isLoading,
      errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
    );
  }
}
