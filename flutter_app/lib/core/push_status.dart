/// A saved token, provider acceptance and phone receipt are separate checks.
class PushConnectionStatus {
  final String code;
  final bool registered, busy;
  final bool? voipConfigured;
  final bool needsMigration;
  const PushConnectionStatus(this.code, {this.registered = false, this.busy = false, this.voipConfigured, this.needsMigration = false});

  factory PushConnectionStatus.fromRegistration(Map response) {
    final configured = response['push_configured'] == true;
    return PushConnectionStatus(
      '${response['push_status'] ?? (response['push_configured'] is bool ? (configured ? 'registered' : 'server_unconfigured') : 'server_status_unknown')}',
      registered: configured,
      voipConfigured: response['voip_configured'] is bool ? response['voip_configured'] as bool : null,
      needsMigration: response['registration_needs_migration'] == true,
    );
  }

  String get message => switch (code) {
    'connecting' => 'Connecting this phone for background alerts…',
    'checking' => 'Checking this phone’s connection with Firebase…',
    'registered' => 'This phone is saved on the website. Firebase delivery has not been checked yet. Tap Check connection or Send test notification.',
    'sending_test' => 'Asking the website to send a real notification to this phone…',
    'test_sent' => 'Firebase accepted the test notification. Check for an alert and a new Last remote push time below; acceptance alone does not confirm phone receipt.',
    'test_unavailable' => 'The website validated this phone but did not confirm sending a test. Update the website mobile API or ask the administrator to run the push delivery test.',
    'accepted' => 'Firebase accepted this phone’s registration check. Try a real call or message while using another app to confirm delivery.',
    'server_status_unknown' => 'This phone was saved, but the website did not report its push configuration. Use Check connection to verify Firebase; the website may need the current mobile API update.',
    'server_unconfigured' => 'The website’s Firebase credentials are missing or invalid. The server administrator must configure background delivery.',
    'project_mismatch' || 'SENDER_ID_MISMATCH' => 'The app and website use different Firebase projects. Install the matching app or update the server’s Firebase configuration.',
    'UNREGISTERED' => 'Firebase no longer recognizes this phone. Use Check connection to register it again.',
    'PERMISSION_DENIED' || 'UNAUTHENTICATED' || 'authentication_unavailable' => 'Firebase rejected the website’s credentials. The server administrator must check Cloud Messaging access.',
    'THIRD_PARTY_AUTH_ERROR' => 'Apple push credentials are missing or rejected. The server administrator must check the iOS push configuration.',
    'registration_failed' => 'The website could not save this phone for background alerts. Check the connection again; if it persists, ask the administrator to check device registration and database migrations.',
    'firebase_unavailable' => 'This phone could not obtain a push token. Check the network and Google Play services, then retry.',
    'apns_pending' => 'Waiting for Apple push registration. Check your connection, then retry.',
    'provider_dns_error' => 'The website server cannot resolve Firebase’s address. Ask the hosting provider to repair its DNS settings.',
    'provider_connection_refused' => 'The website server cannot connect to Google. Ask the hosting provider to allow outbound HTTPS to Firebase and Google authentication.',
    'provider_timeout' => 'The website server’s connection to Google timed out. Ask the hosting provider to check its firewall and network routing.',
    'provider_tls_error' => 'The website server cannot verify Google’s secure connection. Ask the administrator to update the server’s CA certificates and PHP TLS settings.',
    'authentication_rejected' => 'Google rejected the website’s Firebase login. The administrator must check the Firebase service-account key and server clock.',
    'push_server_error' => 'A website error interrupted push delivery. Ask the administrator to check the push logs and server dependencies.',
    'provider_unavailable' => 'Google returned an unexpected error. Retry, or ask the administrator to check the hosting proxy and Google service status.',
    'QUOTA_EXCEEDED' => 'Firebase rate-limited the website. Retry later or ask the administrator to check its Firebase quota.',
    'provider_unreachable' => 'The website could not reach Firebase. Retry or ask the administrator to check the server connection.',
    'check_unavailable' => 'The website could not complete this check. Apply the background-push server update and try again.',
    'signed_out' => 'Sign in to receive background calls and messages.',
    _ => 'Background delivery is not verified. Check the connection below.',
  };
}
