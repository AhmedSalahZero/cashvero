<?php

namespace Tests\Unit;

use App\Exceptions\OdooOperationNotAllowedException;
use App\Services\Api\Traits\HasAtomicOdooDeletion;
use Tests\TestCase;

/**
 * * موك لأودو : بيسجل كل نداء اتعمل ، و بيسمح نخلي unlink يفشل بنفس رسالة
 * * السيكوينس اللي جاية من البرودكشن
 */
class FakeOdooService
{
    use HasAtomicOdooDeletion;

    /** @var array<int, string> */
    public array $calls = [];

    public function __construct(
        private ?string $state,          // null = السجل مش موجود
        private bool $unlinkFails = false,
        private bool $restoreFails = false
    ) {}

    public function execute($model, $method, $args, $kwargs = [])
    {
        $this->calls[] = $method;

        if ($method === 'read') {
            return $this->state === null ? [] : [['id' => 7, 'state' => $this->state]];
        }

        if ($method === 'unlink') {
            if ($this->unlinkFails) {
                throw new \Exception('You cannot delete this entry, as it has already consumed a sequence number and is not the last one in the chain. You should probably revert it instead.');
            }
            $this->state = null;

            return true;
        }

        if ($method === 'button_draft' || $method === 'action_draft') {
            $this->state = 'draft';

            return true;
        }

        if ($method === 'action_post') {
            if ($this->restoreFails) {
                throw new \Exception('restore blew up');
            }
            $this->state = 'posted';

            return true;
        }

        if ($method === 'button_cancel' || $method === 'action_cancel') {
            $this->state = 'cancel';

            return true;
        }

        return true;
    }

    public function currentState(): ?string
    {
        return $this->state;
    }

    public function run(bool $skipWhenAlreadyDraft = false): bool
    {
        return $this->unlinkOdooRecordAtomically(
            'account.move',
            7,
            'button_draft',
            ['posted' => 'action_post', 'cancel' => 'button_cancel'],
            'Unlink Odoo journal entry #7',
            $skipWhenAlreadyDraft
        );
    }
}

class AtomicOdooDeletionTest extends TestCase
{
    public function test_posted_record_is_deleted_when_odoo_accepts(): void
    {
        $odoo = new FakeOdooService('posted');

        $this->assertTrue($odoo->run());
        $this->assertNull($odoo->currentState(), 'المفروض اتحذف');
        $this->assertSame(['read', 'button_draft', 'unlink'], $odoo->calls);
    }

    public function test_failed_unlink_restores_the_posted_state_and_leaves_nothing_half_done(): void
    {
        $odoo = new FakeOdooService('posted', unlinkFails: true);

        try {
            $odoo->run();
            $this->fail('كان لازم يرمي استثناء');
        } catch (OdooOperationNotAllowedException $e) {
            // ده المطلوب
        }

        $this->assertSame('posted', $odoo->currentState(), 'اودو لازم يرجع زي ما كان مش يفضل draft');
        $this->assertSame(['read', 'button_draft', 'unlink', 'read', 'action_post'], $odoo->calls);
    }

    public function test_failed_unlink_restores_a_cancelled_record_to_cancelled(): void
    {
        $odoo = new FakeOdooService('cancel', unlinkFails: true);

        $this->expectException(OdooOperationNotAllowedException::class);

        try {
            $odoo->run();
        } finally {
            $this->assertSame('cancel', $odoo->currentState());
        }
    }

    public function test_user_message_is_friendly_while_the_odoo_message_stays_for_the_logs(): void
    {
        $odoo = new FakeOdooService('posted', unlinkFails: true);

        try {
            $odoo->run();
            $this->fail('كان لازم يرمي استثناء');
        } catch (OdooOperationNotAllowedException $e) {
            $this->assertStringContainsString('consumed a sequence number', $e->getMessage());
            $this->assertStringNotContainsString('consumed a sequence number', $e->getUserMessage());
            $this->assertNotEmpty($e->getUserMessage());
            $this->assertInstanceOf(\Throwable::class, $e->getPrevious());
        }
    }

    public function test_missing_record_is_a_no_op_not_an_error(): void
    {
        $odoo = new FakeOdooService(null);

        $this->assertFalse($odoo->run());
        $this->assertSame(['read'], $odoo->calls, 'مالوش لازمة يلمس اودو');
    }

    public function test_draft_record_is_skipped_when_the_old_behaviour_asks_for_it(): void
    {
        $odoo = new FakeOdooService('draft');

        $this->assertFalse($odoo->run(skipWhenAlreadyDraft: true));
        $this->assertSame('draft', $odoo->currentState());
        $this->assertSame(['read'], $odoo->calls);
    }

    public function test_draft_record_is_deleted_without_drafting_it_again(): void
    {
        $odoo = new FakeOdooService('draft');

        $this->assertTrue($odoo->run());
        $this->assertNull($odoo->currentState());
        $this->assertSame(['read', 'unlink'], $odoo->calls, 'مايناديش button_draft على سجل draft');
    }

    public function test_a_failing_restore_does_not_hide_the_original_error(): void
    {
        $odoo = new FakeOdooService('posted', unlinkFails: true, restoreFails: true);

        $this->expectException(OdooOperationNotAllowedException::class);
        $this->expectExceptionMessageMatches('/consumed a sequence number/');

        $odoo->run();
    }
}
