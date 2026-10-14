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
  final Map<int, double> _cart = {};
  bool _busy = false;

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
      'fulfilment': _asMap(productData['fulfilment']),
      'products': _asList(productData['products']),
      'orders': _asList(orderData['orders']),
    };
  }

  void _refresh() => setState(() => _content = _load());

  double _step(Map<String, dynamic> product) {
    final value = double.tryParse('${product['quantity_step'] ?? 1}') ?? 1;
    return value > 0 ? value : 1;
  }

  double _minimum(Map<String, dynamic> product) {
    final value = double.tryParse('${product['minimum_quantity'] ?? 1}') ?? 1;
    return value > 0 ? value : 1;
  }

  void _add(Map<String, dynamic> product) {
    final id = int.tryParse('${product['id'] ?? ''}');
    if (id == null) return;
    final current = _cart[id] ?? 0;
    setState(() => _cart[id] = current == 0 ? _minimum(product) : current + _step(product));
  }

  void _remove(Map<String, dynamic> product) {
    final id = int.tryParse('${product['id'] ?? ''}');
    if (id == null) return;
    final current = _cart[id] ?? 0;
    final next = current - _step(product);
    setState(() {
      if (next < _minimum(product) - 0.000001) {
        _cart.remove(id);
      } else {
        _cart[id] = next;
      }
    });
  }

  Future<void> _checkout(Map<String, dynamic> data) async {
    if (_cart.isEmpty || _busy) return;
    final products = _asList(data['products']);
    final byId = {for (final p in products) int.tryParse('${p['id'] ?? ''}') ?? -1: p};
    final fulfilment = _asMap(data['fulfilment']);
    final deliveryEnabled = fulfilment['delivery_enabled'] == true || fulfilment['delivery_enabled'] == 1;
    final collectionEnabled = fulfilment['collection_enabled'] == true || fulfilment['collection_enabled'] == 1;
    final addressController = TextEditingController();
    final cityController = TextEditingController();
    final notesController = TextEditingController();
    String method = deliveryEnabled ? 'delivery' : 'collection';

    final request = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(20, 8, 20, MediaQuery.of(context).viewInsets.bottom + 24),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('Review order', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 8),
                ..._cart.entries.map((entry) {
                  final p = byId[entry.key] ?? const <String, dynamic>{};
                  return ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text((p['name'] ?? 'Product').toString()),
                    subtitle: Text('${entry.value} ${p['quantity_unit'] ?? ''}'),
                  );
                }),
                if (deliveryEnabled && collectionEnabled)
                  SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(value: 'delivery', label: Text('Delivery'), icon: Icon(Icons.delivery_dining)),
                      ButtonSegment(value: 'collection', label: Text('Collection'), icon: Icon(Icons.storefront)),
                    ],
                    selected: {method},
                    onSelectionChanged: (values) => setSheetState(() => method = values.first),
                  )
                else
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(method == 'delivery' ? Icons.delivery_dining : Icons.storefront),
                    title: Text(method == 'delivery' ? 'Delivery' : 'Collection'),
                  ),
                if (method == 'delivery') ...[
                  TextField(
                    controller: addressController,
                    decoration: const InputDecoration(labelText: 'Delivery address *'),
                    textCapitalization: TextCapitalization.sentences,
                  ),
                  TextField(
                    controller: cityController,
                    decoration: const InputDecoration(labelText: 'City'),
                    textCapitalization: TextCapitalization.words,
                  ),
                  const SizedBox(height: 8),
                  Text('Delivery fee: ${fulfilment['delivery_fee'] ?? 0}'),
                ],
                TextField(
                  controller: notesController,
                  decoration: const InputDecoration(labelText: 'Order notes (optional)'),
                  maxLines: 2,
                  textCapitalization: TextCapitalization.sentences,
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () {
                    if (method == 'delivery' && addressController.text.trim().isEmpty) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Enter a delivery address.')),
                      );
                      return;
                    }
                    Navigator.of(sheetContext).pop({
                      'fulfilment_method': method,
                      'delivery_address': addressController.text.trim(),
                      'delivery_city': cityController.text.trim(),
                      'customer_notes': notesController.text.trim(),
                    });
                  },
                  child: const Text('Submit order request'),
                ),
              ],
            ),
          ),
        ),
      ),
    );

    if (request == null || !mounted) {
      addressController.dispose();
      cityController.dispose();
      notesController.dispose();
      return;
    }

    setState(() => _busy = true);
    try {
      final root = await widget.client.postJson(
        'customer/orders',
        {
          ...request,
          'items': _cart.entries.map((entry) => {
                'product_id': entry.key,
                'quantity': entry.value,
              }).toList(),
        },
        headers: {'Idempotency-Key': 'customer-${DateTime.now().microsecondsSinceEpoch}'},
      );
      final order = _asMap(_asMap(root['data'])['order']);
      if (!mounted) return;
      setState(() {
        _cart.clear();
        _content = _load();
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Order ${order['order_no'] ?? ''} submitted for shop review.')),
      );
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Order submission failed. Refresh and try again.')),
      );
    } finally {
      addressController.dispose();
      cityController.dispose();
      notesController.dispose();
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _reconfirmPrice(Map<String, dynamic> order) async {
    final id = int.tryParse('${order['id'] ?? ''}');
    if (id == null || _busy) return;
    setState(() => _busy = true);
    try {
      final root = await widget.client.postJson('customer/orders/$id/reconfirm-price', {});
      final data = _asMap(root['data']);
      final revisedOrder = _asMap(data['order']);
      if (mounted) {
        setState(() => _content = _load());
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((data['message'] ?? 'Price confirmation processed.').toString())),
        );
        if (revisedOrder.isNotEmpty) _showOrder(revisedOrder);
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _cancelOrder(Map<String, dynamic> order) async {
    final id = int.tryParse('${order['id'] ?? ''}');
    if (id == null || _busy) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel order?'),
        content: const Text('Only orders eligible for cancellation can be cancelled.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep order')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Cancel order')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await widget.client.postJson('customer/orders/$id/cancel', {'reason': 'Cancelled by customer'});
      if (mounted) {
        setState(() => _content = _load());
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Order cancelled.')));
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showOrder(Map<String, dynamic> order) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => Padding(
        padding: const EdgeInsets.all(20),
        child: ListView(
          shrinkWrap: true,
          children: [
            Text((order['order_no'] ?? 'Order details').toString(), style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            Text('Status: ${order['status'] ?? 'unknown'}'),
            Text('Items: ${order['subtotal'] ?? 0}'),
            Text('Delivery: ${order['delivery_fee'] ?? 0}'),
            Text('Total: ${order['total_amount'] ?? 0}', style: Theme.of(context).textTheme.titleMedium),
            if (order['price_reconfirmation_required'] == 1 || order['price_reconfirmation_required'] == true) ...[
              const SizedBox(height: 12),
              const Text('The quote changed. Review the current total and confirm it to continue.'),
              FilledButton(
                onPressed: _busy ? null : () {
                  Navigator.pop(context);
                  _reconfirmPrice(order);
                },
                child: const Text('Confirm current price'),
              ),
            ],
            if (order['status'] == 'pending')
              OutlinedButton(
                onPressed: _busy ? null : () {
                  Navigator.pop(context);
                  _cancelOrder(order);
                },
                child: const Text('Cancel order'),
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Customer home'),
          actions: [
            IconButton(onPressed: _refresh, icon: const Icon(Icons.refresh), tooltip: 'Refresh'),
            IconButton(
              onPressed: _busy ? null : () async => widget.onLogout(),
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
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
                children: [
                  Text(shop['name']?.toString() ?? 'Your assigned shop', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 4),
                  const Text('Browse your shop’s catalog and send an order request. The shop confirms stock and fulfillment.'),
                  const SizedBox(height: 20),
                  Text('Available products', style: Theme.of(context).textTheme.titleMedium),
                  if (products.isEmpty) const ListTile(title: Text('No products are currently available.')),
                  ...products.map((product) {
                    final id = int.tryParse('${product['id'] ?? ''}') ?? -1;
                    final quantity = _cart[id] ?? 0;
                    final price = _asMap(product['price']);
                    final cylinder = _asMap(product['cylinder_type']);
                    final available = price['price_available'] == true || price['price_available'] == 1;
                    final unit = (product['quantity_unit'] ?? '').toString();
                    final description = (product['description'] ?? '').toString();
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(8),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              leading: const Icon(Icons.propane_tank_outlined, size: 34),
                              title: Text((product['name'] ?? 'Product').toString()),
                              subtitle: Text([
                                cylinder['name']?.toString() ?? '',
                                '${cylinder['capacity_kg'] ?? ''} kg',
                                unit,
                                if (description.isNotEmpty) description,
                              ].where((v) => v.isNotEmpty).join(' • ')),
                            ),
                            Text(
                              available
                                  ? product['sale_mode'] == 'sell_gas_only'
                                      ? 'Gas rate: ${price['gas_rate_per_kg'] ?? 0} per kg'
                                      : product['sale_mode'] == 'sell_filled'
                                          ? 'Gas: ${price['gas_rate_per_kg'] ?? 0}/kg • Cylinder: ${price['cylinder_rate'] ?? 0}'
                                          : 'Empty cylinder: ${price['cylinder_rate'] ?? 0}'
                                  : 'Price unavailable — contact the shop',
                            ),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: [
                                IconButton(onPressed: quantity > 0 && !_busy ? () => _remove(product) : null, icon: const Icon(Icons.remove_circle_outline)),
                                Text(quantity == 0 ? 'Add' : '$quantity $unit'),
                                IconButton(onPressed: available && !_busy ? () => _add(product) : null, icon: const Icon(Icons.add_circle_outline)),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                  const SizedBox(height: 20),
                  Text('My orders', style: Theme.of(context).textTheme.titleMedium),
                  if (orders.isEmpty) const ListTile(title: Text('You have not placed an order yet.')),
                  ...orders.map((order) => Card(
                        child: ListTile(
                          onTap: () => _showOrder(order),
                          leading: const Icon(Icons.receipt_long),
                          title: Text((order['order_no'] ?? 'Order').toString()),
                          subtitle: Text('${order['status'] ?? 'unknown'} • Total ${order['total_amount'] ?? 0}'),
                          trailing: const Icon(Icons.chevron_right),
                        ),
                      )),
                ],
              ),
            );
          },
        ),
        bottomNavigationBar: _cart.isEmpty
            ? null
            : SafeArea(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: FilledButton.icon(
                    onPressed: _busy || _content == null ? null : () {
                      _content.then((data) => _checkout(data));
                    },
                    icon: _busy
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.shopping_cart_checkout),
                    label: Text(_busy ? 'Processing…' : 'Review cart (${_cart.length} products)'),
                  ),
                ),
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
