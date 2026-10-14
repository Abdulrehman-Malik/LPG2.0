import 'package:flutter/material.dart';
import 'package:lpg_mobile_core/lpg_mobile_core.dart';

class CustomerSignupScreen extends StatefulWidget {
  const CustomerSignupScreen({super.key, required this.client, required this.onBack});

  final LpgApiClient client;
  final VoidCallback onBack;

  @override
  State<CustomerSignupScreen> createState() => _CustomerSignupScreenState();
}

class _CustomerSignupScreenState extends State<CustomerSignupScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _username = TextEditingController();
  final _password = TextEditingController();
  final _phone = TextEditingController();
  final _city = TextEditingController();
  final _address = TextEditingController();
  final _vehicle = TextEditingController();

  List<Map<String, dynamic>> _shops = [];
  int? _shopId;
  bool _loadingShops = true;
  bool _submitting = false;
  bool _submitted = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadShops();
  }

  @override
  void dispose() {
    _name.dispose();
    _username.dispose();
    _password.dispose();
    _phone.dispose();
    _city.dispose();
    _address.dispose();
    _vehicle.dispose();
    super.dispose();
  }

  Future<void> _loadShops() async {
    try {
      final root = await widget.client.getJson('customer/auth/shops');
      final data = root['data'];
      final raw = data is Map ? data['shops'] : null;
      final shops = raw is List
          ? raw.whereType<Map>().map((shop) => Map<String, dynamic>.from(shop)).toList()
          : <Map<String, dynamic>>[];
      if (mounted) {
        setState(() {
          _shops = shops;
          _loadingShops = false;
          if (shops.length == 1) _shopId = int.tryParse('${shops.first['id'] ?? ''}');
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() {
        _error = e.message;
        _loadingShops = false;
      });
    } catch (_) {
      if (mounted) setState(() {
        _error = 'Registration shops could not be loaded. Check your connection and retry.';
        _loadingShops = false;
      });
    }
  }

  Future<void> _submit() async {
    if (_submitting || !_formKey.currentState!.validate()) return;
    final shopId = _shopId;
    if (shopId == null) {
      setState(() => _error = 'Choose the shop you want to register with.');
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await widget.client.postPublic('customer/auth/signup', {
        'shop_id': shopId,
        'customer_name': _name.text.trim(),
        'username': _username.text.trim().toLowerCase(),
        'password': _password.text,
        'phone': _phone.text.trim(),
        'city': _city.text.trim(),
        'address': _address.text.trim(),
        'vehicle_no': _vehicle.text.trim(),
      });
      if (mounted) setState(() => _submitted = true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Registration could not be submitted. Please try again.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Customer registration'),
          leading: IconButton(onPressed: widget.onBack, icon: const Icon(Icons.arrow_back)),
        ),
        body: _loadingShops
            ? const Center(child: CircularProgressIndicator())
            : _submitted
                ? Center(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.mark_email_read_outlined, size: 64),
                          const SizedBox(height: 16),
                          Text('Request submitted', style: Theme.of(context).textTheme.headlineSmall),
                          const SizedBox(height: 12),
                          const Text(
                            'Your registration is pending shop approval. You can sign in after the shop approves your account. No duplicate customer account has been created by this request.',
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 20),
                          FilledButton(onPressed: widget.onBack, child: const Text('Back to sign in')),
                        ],
                      ),
                    ),
                  )
                : _shops.isEmpty
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(_error ?? 'No shops are currently accepting customer registrations.'),
                              const SizedBox(height: 12),
                              OutlinedButton(onPressed: _loadShops, child: const Text('Try again')),
                              TextButton(onPressed: widget.onBack, child: const Text('Back to sign in')),
                            ],
                          ),
                        ),
                      )
                    : Form(
                        key: _formKey,
                        child: ListView(
                          padding: const EdgeInsets.all(20),
                          children: [
                            Text('Create account', style: Theme.of(context).textTheme.headlineSmall),
                            const SizedBox(height: 4),
                            const Text('Your account will be linked to one shop and must be approved before you can sign in.'),
                            const SizedBox(height: 16),
                            DropdownButtonFormField<int>(
                              initialValue: _shopId,
                              decoration: const InputDecoration(labelText: 'Your shop *'),
                              items: _shops.map((shop) {
                                final id = int.tryParse('${shop['id'] ?? ''}') ?? 0;
                                final name = (shop['name'] ?? 'Shop $id').toString();
                                final business = (shop['business_name'] ?? '').toString();
                                return DropdownMenuItem<int>(
                                  value: id,
                                  child: Text(business.isEmpty ? name : '$name • $business'),
                                );
                              }).toList(),
                              onChanged: _submitting ? null : (value) => setState(() => _shopId = value),
                              validator: (value) => value == null ? 'Choose a shop.' : null,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _name,
                              textCapitalization: TextCapitalization.words,
                              decoration: const InputDecoration(labelText: 'Full name *'),
                              validator: (value) => value == null || value.trim().isEmpty ? 'Enter your name.' : null,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _username,
                              autocorrect: false,
                              decoration: const InputDecoration(labelText: 'Username *', helperText: '3–80 letters, numbers, dots, underscores or dashes'),
                              validator: (value) => value == null || !RegExp(r'^[a-zA-Z0-9._-]{3,80}$').hasMatch(value.trim())
                                  ? 'Enter a valid username.'
                                  : null,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _password,
                              obscureText: true,
                              decoration: const InputDecoration(labelText: 'Password *'),
                              validator: (value) => value == null || value.length < 8 ? 'Use at least 8 characters.' : null,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _phone,
                              keyboardType: TextInputType.phone,
                              decoration: const InputDecoration(labelText: 'Phone'),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _city,
                              textCapitalization: TextCapitalization.words,
                              decoration: const InputDecoration(labelText: 'City'),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _address,
                              textCapitalization: TextCapitalization.sentences,
                              decoration: const InputDecoration(labelText: 'Address'),
                              maxLines: 2,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _vehicle,
                              textCapitalization: TextCapitalization.characters,
                              decoration: const InputDecoration(labelText: 'Vehicle number (optional)'),
                            ),
                            if (_error != null) ...[
                              const SizedBox(height: 12),
                              Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                            ],
                            const SizedBox(height: 20),
                            FilledButton(
                              onPressed: _submitting ? null : _submit,
                              child: Text(_submitting ? 'Submitting…' : 'Submit for approval'),
                            ),
                          ],
                        ),
                      ),
      );
}
