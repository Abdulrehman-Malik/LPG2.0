import 'package:flutter/material.dart';
import 'package:lpg_mobile_core/lpg_mobile_core.dart';

class CustomerHomeScreen extends StatefulWidget {
  const CustomerHomeScreen({super.key, required this.client, required this.onLogout});

  final LpgApiClient client;
  final Future<void> Function() onLogout;

  @override
  State<CustomerHomeScreen> createState() => _CustomerHomeScreenState();
}

class _CustomerHomeScreenState extends State<CustomerHomeScreen> {
  late Future<Map<String, dynamic>> _content;

  @override
  void initState() {
    super.initState();
    _content = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final productRoot = await widget.client.getJson('customer/products');
    final orderRoot = await widget.client.getJson('customer/orders');
    final productData = _asMap(productRoot['data']);
    final orderData = _asMap(orderRoot['data']);
    return {
      'shop': _asMap(productData['shop']),
      'products': _asList(productData['products']),
      'orders': _asList(orderData['orders']),
    };
  }

  void _refresh() => setState(() => _content = _load());

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Customer home'),
          actions: [
            IconButton(onPressed: _refresh, icon: const Icon(Icons.refresh), tooltip: 'Refresh'),
            IconButton(
              onPressed: () async => widget.onLogout(),
              icon: const Icon(Icons.logout),
              tooltip: 'Sign out',
            ),
          ],
        ),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _content,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return _LoadError(message: snapshot.error.toString(), onRetry: _refresh);
            }
            final data = snapshot.data ?? const <String, dynamic>{};
            final shop = _asMap(data['shop']);
            final products = _asList(data['products']);
            final orders = _asList(data['orders']);
            return RefreshIndicator(
              onRefresh: () async => _refresh(),
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(
                    shop['name']?.toString() ?? 'Your assigned shop',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 20),
                  Text('Available products', style: Theme.of(context).textTheme.titleMedium),
                  if (products.isEmpty) const ListTile(title: Text('No products are currently available.')),
                  ...products.map((product) => Card(
                        child: ListTile(
                          leading: const Icon(Icons.propane_tank_outlined),
                          title: Text((product['display_name'] ?? product['name'] ?? 'Product').toString()),
                          subtitle: Text((product['quantity_unit'] ?? '').toString()),
                        ),
                      )),
                  const SizedBox(height: 20),
                  Text('My orders', style: Theme.of(context).textTheme.titleMedium),
                  if (orders.isEmpty) const ListTile(title: Text('You have not placed an order yet.')),
                  ...orders.map((order) => Card(
                        child: ListTile(
                          leading: const Icon(Icons.receipt_long),
                          title: Text((order['order_no'] ?? 'Order').toString()),
                          subtitle: Text('${order['status'] ?? 'unknown'} • ${order['total_amount'] ?? 0}'),
                        ),
                      )),
                ],
              ),
            );
          },
        ),
      );
}

Map<String, dynamic> _asMap(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _asList(dynamic value) => value is List
    ? value.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
    : <Map<String, dynamic>>[];

class _LoadError extends StatelessWidget {
  const _LoadError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Could not load your information. $message'),
              const SizedBox(height: 12),
              FilledButton(onPressed: onRetry, child: const Text('Try again')),
            ],
          ),
        ),
      );
}
