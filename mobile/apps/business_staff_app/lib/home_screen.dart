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
    final shopId = _selectedShopId;
    if (orderId == null || shopId == null) return;
    setState(() => _actionBusy = true);
    try {
      await widget.client.postJson(
        'staff/orders/$orderId/status',
        {'status': status},
        headers: {'X-Shop-Id': '$shopId'},
      );
      if (mounted) {
        setState(() => _content = _load());
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Order moved to $status.')));
      }
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

  Future<void> _openFulfillment(Map<String, dynamic> order) async {
    if (_actionBusy) return;
    final orderId = int.tryParse('${order['id'] ?? ''}');
    final shopId = _selectedShopId;
    if (orderId == null || shopId == null) return;
    setState(() => _actionBusy = true);
    try {
      final root = await widget.client.getJson(
        'staff/orders/$orderId/fulfillment-options',
        headers: {'X-Shop-Id': '$shopId'},
      );
      final options = _asMap(_asMap(root['data'])['options']);
      final lines = _asList(options['lines']);
      if (!mounted) return;
      await _showFulfillmentSheet(order, shopId, lines);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not load physical-cylinder options. Refresh and try again.')),
      );
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _showFulfillmentSheet(
    Map<String, dynamic> order,
    int shopId,
    List<Map<String, dynamic>> lines,
  ) async {
    final orderId = int.tryParse('${order['id'] ?? ''}');
    if (orderId == null || !mounted) return;
    final sourceSelections = <int, int>{};
    final cylinderSelections = <int, Set<int>>{};
    final paymentController = TextEditingController(text: '${order['total_amount'] ?? 0}');
    String paymentMode = 'cash';
    bool submitting = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(16, 8, 16, MediaQuery.of(context).viewInsets.bottom + 20),
          child: SizedBox(
            height: MediaQuery.of(context).size.height * 0.78,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('Fulfill ${order['order_no'] ?? 'order'}', style: Theme.of(context).textTheme.titleLarge),
                Text('Confirmed order total: ${order['total_amount'] ?? 0}'),
                const SizedBox(height: 8),
                Expanded(
                  child: ListView(
                    children: [
                      ...lines.map((line) {
                        final lineNo = int.tryParse('${line['line_no'] ?? ''}') ?? 0;
                        final cylinders = _asList(line['cylinders']);
                        final sourceField = line['selection_field'] == 'source_cylinder_unit_id';
                        return Card(
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text((line['product_name'] ?? 'Order line $lineNo').toString(),
                                    style: Theme.of(context).textTheme.titleMedium),
                                Text('Required: ${line['quantity_required'] ?? ''} • ${line['sale_mode'] ?? ''}'),
                                if (cylinders.isEmpty)
                                  const Padding(
                                    padding: EdgeInsets.symmetric(vertical: 8),
                                    child: Text('No eligible cylinders are currently available.'),
                                  )
                                else if (sourceField)
                                  DropdownButtonFormField<int>(
                                    decoration: const InputDecoration(labelText: 'Filled source cylinder'),
                                    initialValue: sourceSelections[lineNo],
                                    items: cylinders.map((cylinder) {
                                      final id = int.tryParse('${cylinder['id'] ?? ''}') ?? 0;
                                      final code = (cylinder['unit_code'] ?? 'Cylinder $id').toString();
                                      final kg = cylinder['gas_weight_kg'];
                                      return DropdownMenuItem<int>(
                                        value: id,
                                        child: Text(kg == null ? code : '$code • $kg kg'),
                                      );
                                    }).toList(),
                                    onChanged: submitting ? null : (value) {
                                      if (value == null) return;
                                      setSheetState(() => sourceSelections[lineNo] = value);
                                    },
                                  )
                                else
                                  ...cylinders.map((cylinder) {
                                    final id = int.tryParse('${cylinder['id'] ?? ''}') ?? 0;
                                    final selected = cylinderSelections.putIfAbsent(lineNo, () => <int>{});
                                    final code = (cylinder['unit_code'] ?? 'Cylinder $id').toString();
                                    final kg = cylinder['gas_weight_kg'];
                                    return CheckboxListTile(
                                      contentPadding: EdgeInsets.zero,
                                      value: selected.contains(id),
                                      title: Text(kg == null ? code : '$code • $kg kg'),
                                      subtitle: Text((cylinder['status'] ?? '').toString()),
                                      onChanged: submitting ? null : (checked) {
                                        setSheetState(() {
                                          if (checked == true) {
                                            if (selected.length < (int.tryParse('${line['quantity_required'] ?? 0}') ?? 0)) selected.add(id);
                                          } else {
                                            selected.remove(id);
                                          }
                                        });
                                      },
                                    );
                                  }),
                              ],
                            ),
                          ),
                        );
                      }),
                      const SizedBox(height: 8),
                      DropdownButtonFormField<String>(
                        initialValue: paymentMode,
                        decoration: const InputDecoration(labelText: 'Payment method'),
                        items: const [
                          DropdownMenuItem(value: 'cash', child: Text('Cash')),
                          DropdownMenuItem(value: 'online', child: Text('Online / bank transfer')),
                          DropdownMenuItem(value: 'cheque', child: Text('Cheque')),
                          DropdownMenuItem(value: 'credit', child: Text('Customer credit')),
                        ],
                        onChanged: submitting ? null : (value) {
                          if (value != null) setSheetState(() => paymentMode = value);
                        },
                      ),
                      TextField(
                        controller: paymentController,
                        enabled: !submitting,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(labelText: 'Amount collected / credited'),
                      ),
                      const SizedBox(height: 8),
                      const Text('The server rechecks cylinder availability and the confirmed total before it posts the POS sale.'),
                    ],
                  ),
                ),
                FilledButton.icon(
                  onPressed: submitting ? null : () async {
                    final fulfillmentLines = <Map<String, dynamic>>[];
                    for (final line in lines) {
                      final lineNo = int.tryParse('${line['line_no'] ?? ''}') ?? 0;
                      if (line['selection_field'] == 'source_cylinder_unit_id') {
                        final sourceId = sourceSelections[lineNo];
                        if (sourceId == null) {
                          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Select a source cylinder for each gas line.')));
                          return;
                        }
                        fulfillmentLines.add({'line_no': lineNo, 'source_cylinder_unit_id': sourceId});
                      } else {
                        final selected = cylinderSelections[lineNo] ?? <int>{};
                        final required = int.tryParse('${line['quantity_required'] ?? 0}') ?? 0;
                        if (selected.length != required) {
                          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Select exactly $required cylinder(s) for line $lineNo.')));
                          return;
                        }
                        fulfillmentLines.add({'line_no': lineNo, 'selected_cylinder_unit_ids': selected.toList()});
                      }
                    }
                    final amount = double.tryParse(paymentController.text.trim());
                    if (amount == null || amount <= 0) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter a valid payment amount.')));
                      return;
                    }
                    setSheetState(() => submitting = true);
                    try {
                      final root = await widget.client.postJson(
                        'staff/orders/$orderId/fulfill',
                        {
                          'fulfillment_lines': fulfillmentLines,
                          'payments': [{'payment_mode': paymentMode, 'amount': amount}],
                        },
                        headers: {'X-Shop-Id': '$shopId'},
                      );
                      final data = _asMap(root['data']);
                      if (!mounted) return;
                      Navigator.of(sheetContext).pop();
                      setState(() => _content = _load());
                      ScaffoldMessenger.of(this.context).showSnackBar(
                        SnackBar(content: Text((data['message'] ?? 'Fulfillment request processed.').toString())),
                      );
                    } on ApiException catch (e) {
                      if (mounted) ScaffoldMessenger.of(this.context).showSnackBar(SnackBar(content: Text(e.message)));
                    } catch (_) {
                      if (mounted) ScaffoldMessenger.of(this.context).showSnackBar(
                        const SnackBar(content: Text('Fulfillment failed. Refresh stock and try again.')),
                      );
                    } finally {
                      if (mounted) setSheetState(() => submitting = false);
                    }
                  },
                  icon: submitting
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.point_of_sale),
                  label: Text(submitting ? 'Posting sale…' : 'Validate and post POS sale'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    paymentController.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Business & Staff'),
          actions: [
            IconButton(onPressed: _refresh, icon: const Icon(Icons.refresh), tooltip: 'Refresh'),
            IconButton(
              onPressed: _actionBusy ? null : () async => widget.onLogout(),
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
                            if (status == 'pending' && order['price_reconfirmation_required'] != 1 && order['price_reconfirmation_required'] != true) {
                              nextStatus = 'accepted';
                            } else if (status == 'accepted') {
                              nextStatus = 'preparing';
                            } else if (status == 'preparing') {
                              nextStatus = 'ready';
                            }
                            final canFulfill = ['accepted', 'preparing', 'ready', 'out_for_delivery'].contains(status);
                            return Card(
                              margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                              child: ListTile(
                                onTap: () => _showOrderSummary(order),
                                title: Text((order['order_no'] ?? 'Order').toString()),
                                subtitle: Text(
                                  '${order['customer_name_snapshot'] ?? 'Customer'} • $status • Total ${order['total_amount'] ?? 0}',
                                ),
                                trailing: Wrap(
                                  spacing: 4,
                                  children: [
                                    if (nextStatus != null)
                                      TextButton(
                                        onPressed: _actionBusy ? null : () => _advanceOrder(order, nextStatus!),
                                        child: Text(nextStatus == 'accepted' ? 'Accept' : nextStatus == 'preparing' ? 'Prepare' : 'Ready'),
                                      ),
                                    if (canFulfill)
                                      IconButton(
                                        tooltip: 'Fulfill order',
                                        onPressed: _actionBusy ? null : () => _openFulfillment(order),
                                        icon: const Icon(Icons.point_of_sale),
                                      ),
                                  ],
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

  void _showOrderSummary(Map<String, dynamic> order) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => Padding(
        padding: const EdgeInsets.all(20),
        child: ListView(
          shrinkWrap: true,
          children: [
            Text((order['order_no'] ?? 'Order').toString(), style: Theme.of(context).textTheme.titleLarge),
            Text('Status: ${order['status'] ?? 'unknown'}'),
            Text('Items: ${order['subtotal'] ?? 0}'),
            Text('Delivery fee: ${order['delivery_fee'] ?? 0}'),
            Text('Total: ${order['total_amount'] ?? 0}'),
            Text('Customer: ${order['customer_name_snapshot'] ?? ''}'),
            if ((order['delivery_address_snapshot'] ?? '').toString().isNotEmpty)
              Text('Delivery address: ${order['delivery_address_snapshot']}'),
            if ((order['customer_notes'] ?? '').toString().isNotEmpty)
              Text('Notes: ${order['customer_notes']}'),
          ],
        ),
      ),
    );
  }
}

Map<String, dynamic> _asMap(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _asList(dynamic value) => value is List
    ? value.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
    : <Map<String, dynamic>>[];
