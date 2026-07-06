<?php
declare(strict_types=1);

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Wekser\Laragram\Events\PaymentReceived;
use Wekser\Laragram\Laragram;
use Wekser\Laragram\Listeners\RecordPayment;
use Wekser\Laragram\Models\Payment;
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Testing\BotUpdateFactory;
use Wekser\Laragram\Tests\Concerns\UsesUserDatabase;
use Wekser\Laragram\Tests\TestCase;

class PaymentPhase2Test extends TestCase
{
    use UsesUserDatabase;

    protected function tearDown(): void
    {
        Router::flushCache();
        BotUpdateFactory::reset();

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // PaymentReceived event accessors
    // -------------------------------------------------------------------------

    public function test_event_accessors_read_the_payment_object(): void
    {
        $event = new PaymentReceived(null, [
            'currency'                   => 'XTR',
            'total_amount'               => 500,
            'invoice_payload'            => 'sub_pro',
            'telegram_payment_charge_id' => 'chg_1',
        ]);

        $this->assertSame('sub_pro', $event->invoicePayload());
        $this->assertSame('chg_1', $event->chargeId());
        $this->assertSame(500, $event->totalAmount());
        $this->assertSame('XTR', $event->currency());
        $this->assertTrue($event->isStars());
    }

    // -------------------------------------------------------------------------
    // Pipeline fires the event
    // -------------------------------------------------------------------------

    public function test_pipeline_fires_payment_received_for_successful_payment(): void
    {
        Event::fake();
        BotUpdateFactory::reset();

        $update = BotUpdateFactory::successfulPaymentMessage(payload: 'order_9', totalAmount: 250);

        (new Laragram())->handle(Request::create('/', 'POST', $update));

        Event::assertDispatched(PaymentReceived::class, function (PaymentReceived $e) {
            return $e->invoicePayload() === 'order_9' && $e->totalAmount() === 250;
        });
    }

    public function test_pipeline_does_not_fire_for_a_plain_message(): void
    {
        Event::fake();
        BotUpdateFactory::reset();

        (new Laragram())->handle(Request::create('/', 'POST', BotUpdateFactory::message('hello')));

        Event::assertNotDispatched(PaymentReceived::class);
    }

    // -------------------------------------------------------------------------
    // RecordPayment listener
    // -------------------------------------------------------------------------

    private function createPaymentsTable(): void
    {
        Schema::create('laragram_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('uid')->nullable();
            $table->string('currency', 16);
            $table->unsignedBigInteger('total_amount');
            $table->string('invoice_payload')->nullable();
            $table->string('telegram_payment_charge_id')->unique();
            $table->string('provider_payment_charge_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    private function payment(string $chargeId = 'chg_1'): array
    {
        return [
            'currency'                   => 'XTR',
            'total_amount'               => 500,
            'invoice_payload'            => 'sub_pro',
            'telegram_payment_charge_id' => $chargeId,
            'provider_payment_charge_id' => '',
        ];
    }

    public function test_listener_persists_payment_when_enabled(): void
    {
        $this->setUpUserDatabase();
        $this->createPaymentsTable();
        config(['laragram.payments.store' => true]);

        $user = $this->makeUser(['uid' => 555]);

        (new RecordPayment())->handle(new PaymentReceived($user, $this->payment()));

        $this->assertSame(1, Payment::query()->count());

        $row = Payment::query()->first();
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame(555, (int) $row->uid);
        $this->assertSame('XTR', $row->currency);
        $this->assertSame(500, $row->total_amount);
        $this->assertSame('sub_pro', $row->invoice_payload);
    }

    public function test_listener_is_idempotent_on_charge_id(): void
    {
        $this->setUpUserDatabase();
        $this->createPaymentsTable();
        config(['laragram.payments.store' => true]);

        $user = $this->makeUser();
        $listener = new RecordPayment();

        $listener->handle(new PaymentReceived($user, $this->payment('same')));
        $listener->handle(new PaymentReceived($user, $this->payment('same')));

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_listener_is_noop_when_store_disabled(): void
    {
        $this->setUpUserDatabase();
        $this->createPaymentsTable();
        config(['laragram.payments.store' => false]);

        (new RecordPayment())->handle(new PaymentReceived($this->makeUser(), $this->payment()));

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_listener_is_noop_under_array_driver(): void
    {
        // No database set up; array driver is the TestCase default.
        config(['laragram.payments.store' => true, 'laragram.auth.driver' => 'array']);

        // Must not throw even though no payments table exists.
        (new RecordPayment())->handle(new PaymentReceived(null, $this->payment()));

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Payment model
    // -------------------------------------------------------------------------

    public function test_model_uses_configured_table(): void
    {
        config(['laragram.payments.table' => 'custom_payments']);

        $this->assertSame('custom_payments', (new Payment())->getTable());
    }
}
