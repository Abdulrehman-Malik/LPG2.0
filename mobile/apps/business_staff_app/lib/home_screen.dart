import 'package:flutter/material.dart';
import 'package:lpg_mobile_core/lpg_mobile_core.dart';

class StaffHomeScreen extends StatefulWidget {
  const StaffHomeScreen({super.key, required this.client, required this.onLogout});

  final LpgApiClient client;
  final Future<void> Function() onLogout;

  @override
  State<StaffHomeScreen> createState() => _StaffHomeScreenState();
}

class _StaffHomeScreenState extends State<StaffHomeScreen> {
  late Future<Map<String, dynamic>> _content;
  int? _selectedShopId;
  bool _actionBusy = false;

  @override
  void initState() {
    super.initState();
    _content = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final meRoot = await widget.client.getJson('staff/me');
    final me = _asMap(meRoot['data']);
    final shops = _asList(me['shops']);
    final currentId = int.tryParse('${me['current_shop_id'] ?? ''}') ??
        (shops.isNotEmpty ? int.tryParse('${shops.first['id']}') : null) ??
        0;
    final shopId = _selectedShopId ?? currentId;
    if (shopId <= 0) throw StateError('No accessible shop is assigned to this staff account.');
    final orderRoot = await widget.client.getJson(
      'staff/orders',
      headers: {'X-Shop-Id': '$shopId'},
    );
    return {
      'staff': _asMap(me['staff']),
      'shops': shops,
      'shop_id': shopId,
      'orders': _asList(_asMap(orderRoot['data'])['orders']),
    };
  }

  void _refresh() => setState(() => _content = _load());

  Future<void> _advanceOrder(Map<String, dynamic> order, String status) async {
    if (_actionBusy) return;
    final orderId = int.tryParse('${order['id'] ?? ''}');
    if (orderId == null) return;
    setState(() => _actionBusy = true);
    try {
      await widget.client.postJson(
        'staff/orders/$orderId/status',
        {'status': status},
        headers: {'X-Shop-Id': '${_selectedShopId ?? 0}'},
      );
      if (mounted) setState(() => _content = _load());
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('The order could not be updated. Try again.')),
      );
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Business & Staff'),
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
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text('Could not load staff context. ${snapshot.error}'),
                ),
              );
            }
            final data = snapshot.data ?? const <String, dynamic>{};
            final shops = _asList(data['shops']);
            final orders = _asList(data['orders']);
            final shopId = int.tryParse('${data['shop_id'] ?? ''}');
            return Column(
              children: [
                if (shops.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                    child: DropdownButtonFormField<int>(
                      key: ValueKey<int?>(shopId),
                      initialValue: shopId,
                      decoration: const InputDecoration(labelText: 'Active shop'),
                      items: shops.map((shop) {
                        final id = int.tryParse('${shop['id']}') ?? 0;
                        final label = (shop['name'] ?? 'Shop $id').toString();
                        return DropdownMenuItem<int>(value: id, child: Text(label));
                      }).toList(),
                      onChanged: _actionBusy ? null : (value) {
                        if (value == null) return;
                        setState(() {
                          _selectedShopId = value;
                          _content = _load();
                        });
                      },
                    ),
                  ),
                Expanded(
                  child: orders.isEmpty
                      ? const Center(child: Text('No orders for this shop.'))
                      : ListView.builder(
                          itemCount: orders.length,
                          itemBuilder: (context, index) {
                            final order = orders[index];
                            final status = (order['status'] ?? '').toString();
                            String? nextStatus;
                            if (status == 'pending' && order['price_reconfirmation_required'] != 1) {
                              nextStatus = 'accepted';
                            } else if (status == 'accepted') {
                              nextStatus = 'preparing';
                            } else if (status == 'preparing') {
                              nextStatus = 'ready';
                            }
                            return Card(
                              margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                              child: ListTile(
                                title: Text((order['order_no'] ?? 'Order').toString()),
                                subtitle: Text(
                                  '${order['customer_name_snapshot'] ?? 'Customer'} • $status • ${order['total_amount'] ?? 0}',
                                ),
                                trailing: nextStatus == null
                                    ? null
                                    : TextButton(
                                        onPressed: _actionBusy ? null : () => _advanceOrder(order, nextStatus!),
                                        child: Text(nextStatus == 'accepted' ? 'Accept' : nextStatus == 'preparing' ? 'Prepare' : 'Ready'),
                                      ),
                              ),
                            );
                          },
                        ),
                ),
              ],
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
