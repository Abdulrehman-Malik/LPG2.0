<?php

use App\Services\CustomerReceiptService;
use App\Services\ExpenseService;
use App\Services\PaymentModes;
use App\Services\SupplierPaymentService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * BL-017 smoke: the money posting services refuse invalid input before touching any table.
 * Posting itself is covered by tests/qa (verify_business_flow, cash_check, parties_check).
 *
 * @internal
 */
final class MoneyPostingServicesTest extends CIUnitTestCase
{
    public function testPaymentModesExcludeCredit(): void
    {
        $this->assertSame(['cash', 'cheque', 'online'], PaymentModes::receivable(1));
        $this->assertSame(['cash', 'cheque', 'online'], PaymentModes::payable(1));
    }

    /** @return iterable<string, array{0: callable, 1: string}> */
    public static function invalidInputs(): iterable
    {
        yield 'receipt zero amount' => [static fn ($db) => (new CustomerReceiptService($db))->post(['customer_id' => 1, 'amount' => 0, 'payment_mode' => 'cash'], 1, 1), 'Invalid receipt.'];
        yield 'receipt credit mode' => [static fn ($db) => (new CustomerReceiptService($db))->post(['customer_id' => 1, 'amount' => 10, 'payment_mode' => 'credit'], 1, 1), 'Invalid receipt.'];
        yield 'receipt no customer' => [static fn ($db) => (new CustomerReceiptService($db))->post(['amount' => 10, 'payment_mode' => 'cash'], 1, 1), 'Invalid receipt.'];
        yield 'expense negative' => [static fn ($db) => (new ExpenseService($db))->post(['category_id' => 1, 'amount' => -5, 'payment_mode' => 'cash'], 1, 1), 'Invalid expense.'];
        yield 'expense unknown mode' => [static fn ($db) => (new ExpenseService($db))->post(['category_id' => 1, 'amount' => 5, 'payment_mode' => 'barter'], 1, 1), 'Invalid expense.'];
        yield 'supplier no supplier' => [static fn ($db) => (new SupplierPaymentService($db))->post(['amount' => 5, 'payment_mode' => 'cash'], 1, 1), 'Invalid supplier payment.'];
        yield 'supplier credit mode' => [static fn ($db) => (new SupplierPaymentService($db))->post(['supplier_id' => 1, 'amount' => 5, 'payment_mode' => 'credit'], 1, 1), 'Invalid supplier payment.'];
    }

    /** @dataProvider invalidInputs */
    public function testInvalidInputIsRejected(callable $post, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        $post(Database::connect('tests'));
    }
}
