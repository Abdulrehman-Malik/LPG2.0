class LpgApiConfig {
  const LpgApiConfig._(this.baseUri);

  final Uri baseUri;

  factory LpgApiConfig.fromEnvironment() {
    const raw = String.fromEnvironment('LPG_API_BASE_URL');
    return LpgApiConfig.parse(raw);
  }

  factory LpgApiConfig.parse(String value) {
    final raw = value.trim();
    if (raw.isEmpty) {
      throw const FormatException(
        'LPG_API_BASE_URL is required. Set it with --dart-define.',
      );
    }

    final parsed = Uri.parse(raw);
    if (!parsed.hasAuthority ||
        !parsed.hasScheme ||
        !const ['http', 'https'].contains(parsed.scheme)) {
      throw const FormatException(
        'LPG_API_BASE_URL must be an absolute HTTP or HTTPS URL.',
      );
    }

    final path = parsed.path.replaceFirst(RegExp(r'/+$'), '');
    if (!path.endsWith('/api/v1')) {
      throw const FormatException(
        'LPG_API_BASE_URL must end with /api/v1.',
      );
    }

    return LpgApiConfig._(
      parsed.replace(path: '$path/', query: null, fragment: null),
    );
  }

  Uri resolve(String path) {
    final relativePath = path.startsWith('/') ? path.substring(1) : path;
    return baseUri.resolve(relativePath);
  }
}
