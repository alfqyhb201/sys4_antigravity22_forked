<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalAdvanceReceiptsBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_repairs_historical_advance_receipt_and_allocates_to_invoices(): void
    {
        $currency = Currency::firstOrCreate(
            ['currency' => 'SAR'],
            ['currency' => 'SAR', 'currency_name' => 'ريال سعودي', 'value' => 1, 'is_base' => true]
        );

        $location = Location::firstOrCreate(['name' => 'موقع الاختبار']);
        $category = Category::firstOrCreate(['name' => 'تصنيف الاختبار']);

        $client = Client::create([
            'company' => 'شركة الاختبار للدفعات المقدمة',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
        ]);

        $contract = Contract::create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'payment_type' => 'advance',
            'weekly_designs_count' => 1,
            'monthly_designs_count' => 4,
            'total_amount' => 135,
            'start_date' => '2026-08-01',
            'end_date' => '2027-08-01',
            'billing_cycle' => 'monthly',
            'status' => 'active',
        ]);

        // 1. إنشاء السند بمبلغ 675 ورصيد غير مخصص 0 (محاكاة للحالة التاريخية)
        $receipt = Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => null,
            'amount' => 675.00,
            'original_amount' => 675.00,
            'unallocated_amount' => 0.00,
            'paid_currency_id' => $currency->id,
            'receipt_date' => '2026-08-11',
            'payment_method' => 'cash',
            'reference_number' => '73269216',
            'notes' => 'له 5 شهور مبلغ 675ر.س',
        ]);
        $receipt->timestamps = false;
        $receipt->created_at = '2026-08-11 12:00:00';
        $receipt->unallocated_amount = 0.00;
        $receipt->saveQuietly();

        // 2. فاتورة شهر أغسطس (مدفوعة ظاهرياً ولكن متبقيها 135 لعدم وجود تخصيص)
        $invoice1 = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'total_amount' => 135.00,
            'status' => 'paid',
            'issue_date' => '2026-08-11',
            'due_date' => '2026-08-31',
            'created_at' => '2026-08-11 12:00:00',
        ]);

        // 3. فاتورة شهر سبتمبر (مستحقة)
        $invoice2 = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'total_amount' => 135.00,
            'status' => 'posted',
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-30',
            'created_at' => '2026-09-01 00:00:00',
        ]);

        // التأكد من الحالة قبل التصحيح
        $this->assertEquals(0.00, (float) $receipt->fresh()->unallocated_amount);
        $this->assertEquals(135.00, $invoice1->fresh()->remaining);
        $this->assertEquals(135.00, $invoice2->fresh()->remaining);
        $this->assertEquals('posted', $invoice2->fresh()->status);

        // تشغيل أمر التصحيح
        $exitCode = \Illuminate\Support\Facades\Artisan::call('receipts:repair-historical-advances', [
            '--force' => true,
            '--receipt' => $receipt->id,
        ]);
        $this->assertEquals(0, $exitCode);

        // التحقق من النتائج بعد التصحيح
        $freshReceipt = $receipt->fresh();
        $this->assertEquals(405.00, (float) $freshReceipt->unallocated_amount);

        $freshInvoice1 = $invoice1->fresh();
        $this->assertEquals(0.00, $freshInvoice1->remaining);
        $this->assertEquals('paid', $freshInvoice1->status);

        $freshInvoice2 = $invoice2->fresh();
        $this->assertEquals(0.00, $freshInvoice2->remaining);
        $this->assertEquals('paid', $freshInvoice2->status);

        $this->assertEquals(2, ReceiptAllocation::where('receipt_id', $receipt->id)->count());

        // التحقق من عدم التكرار (Idempotency)
        $secondRun = \Illuminate\Support\Facades\Artisan::call('receipts:repair-historical-advances', [
            '--force' => true,
            '--receipt' => $receipt->id,
        ]);
        $this->assertEquals(0, $secondRun);
        $this->assertEquals(405.00, (float) $receipt->fresh()->unallocated_amount);
        $this->assertEquals(2, ReceiptAllocation::where('receipt_id', $receipt->id)->count());
    }
}
