<?php

namespace Tests\Feature\Messaging;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * المراسلة : دردشة الزمايل + تذاكر الدعم .
 *
 * * الاختبارات دي بتشتغل على الراوتس نفسها اللي الواجهة بتنده عليها
 * * بـ axios ، مش على الخدمات — لإن كل حاجة مهمة في الميزة دي هي قاعدة
 * * تصريح أو عزل ، و دي عايشة في الكونترولر و الراوت مش في خدمة .
 *
 * * الحارسين اللي اتزودوا بعد مراجعة الميزة :
 * *   ١ - ما ينفعش تبعت لنفسك . القاعدة كانت 'different:' . Auth::id()
 * *       و دي بتاخد **اسم حقل** مش قيمة ، فكانت بتعدّي دايما و الطلب
 * *       يقع بـ 500 على القيد الفريد بدل 422 .
 * *   ٢ - ما ينفعش تفتح دردشة مع حد في شركة تانية . قايمة جهات الاتصال
 * *       في index() كانت بتخفيهم بس ، و دي واجهة مش حماية .
 *
 * * كل حاجة بتتعمل جوه transaction بترجع في الآخر ، فالقاعدة بتفضل زي
 * * ما هي بالظبط .
 */
class MessagingTest extends TestCase
{
    private ?string $originalDatabase = null;

    private Company $companyA;
    private Company $companyB;
    private User $alice;   // شركة أ
    private User $bob;     // شركة أ
    private User $carol;   // شركة ب
    private User $super;   // سوبر أدمن

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('conversations')) {
            $this->markTestSkipped('The messaging tables are not migrated on this database.');
        }

        /**
         * * الميدلويرين دول بيتشالوا لإنهم مش موجودين في المسار الحقيقي
         * * أصلا ، مش عشان الاختبار يعدّي .
         *
         * * الراوتس متسجّلة جوه
         * * Route::group(['prefix' => LaravelLocalization::setLocale()]) ،
         * * و في الإنتاج hideDefaultLocaleInURL = false ، يعني كل URL
         * * بيبدأ بـ /en/ أو /ar/ . ساعتها :
         * *
         * *   canViewCurrentCompany بياخد segment(2) على إنه رقم شركة ،
         * *   و في /en/messages/5995 ده بيطلع "messages" — مش رقم —
         * *   فالميدلوير بيعدّي من غير ما يعمل حاجة . أبدا .
         * *
         * *   LaravelLocalizationRedirectFilter بيحوّل الـ URL اللي من
         * *   غير بادئة ، و ده مش بيحصل لإن البادئة موجودة .
         * *
         * * بس جوه PHPUnit الـ prefix بيطلع فاضي وقت تسجيل الراوتس ،
         * * فالمسار بيبقى /messages/5995 و segment(2) يبقى رقم المحادثة
         * * — فالميدلوير يقراه على إنه شركة و يرجّع 403 . ده أثر بيئة
         * * اختبار ، مش سلوك المستخدم . سيبهم شغّالين هنا معناه إن
         * * الاختبار بيقيس حاجة مالهاش دعوة بالمراسلة .
         *
         * * كل الباقي شغّال : Authenticate و CSRF و SubstituteBindings و
         * * EnsureRouteModelsBelongToCompany و EnforcePermission .
         */
        $this->withoutMiddleware([
            \App\Http\Middleware\canViewCurrentCompany::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
        ]);

        DB::beginTransaction();

        $this->companyA = $this->makeCompany('Messaging Co. A');
        $this->companyB = $this->makeCompany('Messaging Co. B');

        $this->alice = $this->makeUser('alice', User::MANAGER, $this->companyA);
        $this->bob   = $this->makeUser('bob',   User::USER,    $this->companyA);
        $this->carol = $this->makeUser('carol', User::MANAGER, $this->companyB);
        $this->super = $this->makeUser('super', User::SUPER_ADMIN, null);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    // ── بناء الحالة ───────────────────────────────────────────────

    private function makeCompany(string $name): Company
    {
        return Company::create(['name' => $name.' '.bin2hex(random_bytes(3))]);
    }

    /**
     * * الأدوار هنا Spatie ، و assignRole بيلمس الكاش بتاع الصلاحيات .
     * * forgetCachedPermissions ضروري : من غيره أول اختبار بيسخّن الكاش
     * * و اللي بعده بيقرا أدوار مستخدم مختلف .
     */
    private function makeUser(string $handle, string $role, ?Company $company): User
    {
        $user = User::create([
            'name'     => ucfirst($handle),
            'email'    => $handle.'-'.bin2hex(random_bytes(4)).'@messaging.test',
            'password' => bcrypt('irrelevant'),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole($role);

        if ($company) {
            $user->companies()->attach($company->id);
        }

        return $user->fresh();
    }

    /** محادثة مباشرة فيها رسالة واحدة من $from . */
    private function directThread(User $from, User $to, string $body = 'hello'): Conversation
    {
        $response = $this->actingAs($from)
            ->postJson(route('messages.start'), ['user_id' => $to->id, 'body' => $body]);

        $response->assertOk();

        return Conversation::findOrFail($response->json('conversation_id'));
    }

    // ── الحارسان الجديدان ─────────────────────────────────────────

    /**
     * * حارس رقم ١ . لو رجّعت 'different:' . Auth::id() مكان
     * * Rule::notIn الاختبار ده بيقع بـ 500 مش 422 — و ده بالظبط اللي
     * * كان بيحصل للمستخدم .
     */
    public function test_you_cannot_start_a_conversation_with_yourself(): void
    {
        $before = Conversation::count();

        $this->actingAs($this->alice)
            ->postJson(route('messages.start'), ['user_id' => $this->alice->id, 'body' => 'talking to myself'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');

        $this->assertSame($before, Conversation::count(), 'A refused start must not leave a thread behind.');
    }

    /** حارس رقم ٢ . */
    public function test_you_cannot_open_a_chat_with_someone_in_another_company(): void
    {
        $before = Conversation::count();

        $this->actingAs($this->alice)
            ->postJson(route('messages.start'), ['user_id' => $this->carol->id, 'body' => 'hi'])
            ->assertForbidden();

        $this->assertSame($before, Conversation::count());
    }

    public function test_colleagues_sharing_a_company_can_still_talk(): void
    {
        $this->actingAs($this->alice)
            ->postJson(route('messages.start'), ['user_id' => $this->bob->id, 'body' => 'hi'])
            ->assertOk();
    }

    /**
     * * مستخدم واحد ممكن يكون في أكتر من شركة هنا . شركة واحدة مشتركة
     * * تكفي — مش لازم تطابق كامل .
     */
    public function test_one_shared_company_is_enough_even_if_the_rest_differ(): void
    {
        /**
         * * الحالة هنا متعمّدة : مفيش مجموعة شركات منهم جزء من التانية .
         * * أليس في { أ ، ج } و كارول في { ب ، أ } . المشترك شركة واحدة
         * * بس — و دي المفروض تكفي .
         *
         * * لو الحارس اتكتب بـ diff (تطابق كامل) بدل intersect ، الحالة
         * * دي بتترفض غلط ، و ده اللي الاختبار بيمسكه .
         */
        $companyC = $this->makeCompany('Messaging Co. C');

        $this->alice->companies()->attach($companyC->id);
        $this->carol->companies()->attach($this->companyA->id);

        $this->actingAs($this->alice->fresh())
            ->postJson(route('messages.start'), ['user_id' => $this->carol->fresh()->id, 'body' => 'now we are colleagues'])
            ->assertOk();
    }

    public function test_anyone_may_open_a_chat_with_a_super_admin(): void
    {
        $this->actingAs($this->alice)
            ->postJson(route('messages.start'), ['user_id' => $this->super->id, 'body' => 'hi'])
            ->assertOk();
    }

    public function test_a_super_admin_may_open_a_chat_with_anyone(): void
    {
        $this->actingAs($this->super)
            ->postJson(route('messages.start'), ['user_id' => $this->carol->id, 'body' => 'checking in'])
            ->assertOk();
    }

    // ── صفحة الصندوق ──────────────────────────────────────────────

    public function test_the_inbox_page_renders_with_everything_the_vue_component_needs(): void
    {
        /**
         * * الفحص المدمج بتاع إينرشيا (اللي بيتأكد إن ملف الصفحة موجود
         * * على الديسك) متعطّل هنا بـ false : الحزمة بتدوّر في
         * * resource_path('js/pages') بحرف صغير ، و المشروع ده حاطط
         * * صفحاته في js/Pages بحرف كبير ، فالفحص بيفشل على أي صفحة في
         * * المشروع مش على دي بس . بنتأكد من وجود الملف بنفسنا تحت عشان
         * * ما نخسرش الضمانة .
         */
        $this->assertFileExists(resource_path('js/Pages/Messaging/Inbox.vue'));

        $this->actingAs($this->alice)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Messaging/Inbox', false)
                ->where('isSuperAdmin', false)
                ->has('conversations.direct')
                ->has('conversations.support')
                ->has('routes.start')->has('routes.support')->has('routes.poll')
                ->has('routes.show')->has('routes.updates')->has('routes.send')
                ->has('routes.read')->has('routes.status')->has('routes.deleteMessage')
            );
    }

    public function test_contacts_are_colleagues_only_and_never_yourself(): void
    {
        $this->actingAs($this->alice)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(function ($page) {
                $ids = collect($page->toArray()['props']['contacts'])->pluck('id')->all();

                $this->assertContains($this->bob->id, $ids, 'A colleague in the same company should be reachable.');
                $this->assertNotContains($this->carol->id, $ids, 'Someone in another company must not be listed.');
                $this->assertNotContains($this->alice->id, $ids, 'You are not your own contact.');
            });
    }

    // ── العزل ─────────────────────────────────────────────────────

    public function test_an_outsider_cannot_read_write_or_delete_inside_someone_elses_thread(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob, 'private');
        $message = Message::where('conversation_id', $conversation->id)->firstOrFail();

        $this->actingAs($this->carol)->getJson(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($this->carol)->getJson(route('messages.updates', $conversation))->assertForbidden();
        $this->actingAs($this->carol)->postJson(route('messages.send', $conversation), ['body' => 'butting in'])->assertForbidden();
        $this->actingAs($this->carol)->postJson(route('messages.read', $conversation))->assertForbidden();
        $this->actingAs($this->carol)->deleteJson(route('messages.delete', [$conversation, $message]))->assertForbidden();

        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->count());
        $this->assertNull($message->fresh()->deleted_at);
    }

    public function test_a_super_admin_does_not_get_to_read_private_chats(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob, 'not for you');

        // كونه سوبر أدمن بيفتحله التذاكر ، مش بريد الناس .
        $this->actingAs($this->super)
            ->getJson(route('messages.show', $conversation))
            ->assertForbidden();
    }

    public function test_someone_elses_thread_never_appears_in_your_inbox(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob, 'private');

        $ids = collect($this->actingAs($this->carol)->getJson(route('messages.poll'))->assertOk()->json('conversations.direct'))
            ->pluck('id')->all();

        $this->assertNotContains($conversation->id, $ids);
    }

    // ── التذاكر ───────────────────────────────────────────────────

    public function test_a_support_ticket_reaches_the_super_admin_without_being_a_participant(): void
    {
        $response = $this->actingAs($this->alice)
            ->postJson(route('messages.support.start'), ['subject' => 'Cannot upload', 'body' => 'It fails'])
            ->assertOk();

        $ticket = Conversation::findOrFail($response->json('conversation_id'));
        $this->assertSame('support', $ticket->type);
        $this->assertSame('open', $ticket->status);

        $this->actingAs($this->super)->getJson(route('messages.show', $ticket))->assertOk();
        $this->actingAs($this->super)->postJson(route('messages.send', $ticket), ['body' => 'Looking into it'])->assertOk();
    }

    public function test_only_a_super_admin_can_move_a_ticket_status(): void
    {
        $response = $this->actingAs($this->alice)
            ->postJson(route('messages.support.start'), ['subject' => 'Broken', 'body' => 'Halp']);
        $ticket = Conversation::findOrFail($response->json('conversation_id'));

        $this->actingAs($this->alice)
            ->patchJson(route('messages.status', $ticket), ['status' => 'resolved'])
            ->assertForbidden();
        $this->assertSame('open', $ticket->fresh()->status, 'The reporter must not be able to close their own ticket.');

        $this->actingAs($this->super)
            ->patchJson(route('messages.status', $ticket), ['status' => 'resolved'])
            ->assertOk();
        $this->assertSame('resolved', $ticket->fresh()->status);
    }

    public function test_a_direct_thread_has_no_status_to_change(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob);

        $this->actingAs($this->super)
            ->patchJson(route('messages.status', $conversation), ['status' => 'resolved'])
            ->assertForbidden();
    }

    // ── المحادثات المكرّرة ────────────────────────────────────────

    public function test_saying_hello_again_reuses_the_same_thread_in_both_directions(): void
    {
        $first = $this->directThread($this->alice, $this->bob, 'first');

        $this->assertSame($first->id, $this->directThread($this->alice, $this->bob, 'second')->id);
        $this->assertSame($first->id, $this->directThread($this->bob, $this->alice, 'third')->id);

        $this->assertSame(3, Message::where('conversation_id', $first->id)->count());
    }

    // ── غير المقروء ───────────────────────────────────────────────

    public function test_unread_counts_threads_with_something_new_not_messages(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob, 'one');
        $this->actingAs($this->alice)->postJson(route('messages.send', $conversation), ['body' => 'two'])->assertOk();
        $this->actingAs($this->alice)->postJson(route('messages.send', $conversation), ['body' => 'three'])->assertOk();

        $this->assertSame(1, Conversation::unreadCountFor($this->bob));
        $this->assertSame(0, Conversation::unreadCountFor($this->alice), 'Your own messages are not unread mail.');
    }

    public function test_reading_a_thread_clears_it(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob);
        $this->assertSame(1, Conversation::unreadCountFor($this->bob));

        $this->actingAs($this->bob)->postJson(route('messages.read', $conversation))->assertOk();

        $this->assertSame(0, Conversation::unreadCountFor($this->bob));
    }

    public function test_a_deleted_message_stops_keeping_a_thread_unread(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob, 'oops');
        $message = Message::where('conversation_id', $conversation->id)->firstOrFail();

        $this->assertSame(1, Conversation::unreadCountFor($this->bob));

        $this->actingAs($this->alice)->deleteJson(route('messages.delete', [$conversation, $message]))->assertOk();

        $this->assertSame(0, Conversation::unreadCountFor($this->bob));
    }

    // ── الحذف ─────────────────────────────────────────────────────

    public function test_deleting_is_soft_sender_only_and_stops_serving_the_body(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob, 'delete me');
        $message = Message::where('conversation_id', $conversation->id)->firstOrFail();

        $this->actingAs($this->bob)
            ->deleteJson(route('messages.delete', [$conversation, $message]))
            ->assertForbidden();
        $this->assertNull($message->fresh()->deleted_at);

        $this->actingAs($this->alice)
            ->deleteJson(route('messages.delete', [$conversation, $message]))
            ->assertOk();

        $this->assertNotNull($message->fresh()->deleted_at);
        $this->assertNotNull(Message::find($message->id), 'Deletion is soft — the row must survive.');

        foreach ([$this->alice, $this->bob] as $viewer) {
            $shown = collect($this->actingAs($viewer)->getJson(route('messages.show', $conversation))->json('messages'))
                ->firstWhere('id', $message->id);

            $this->assertTrue($shown['deleted'], 'The message must be flagged deleted.');
            $this->assertNull($shown['body'], 'A deleted message must not still carry its text.');
        }
    }

    public function test_a_message_cannot_be_deleted_through_a_different_conversation(): void
    {
        $mine  = $this->directThread($this->alice, $this->bob, 'mine');
        $other = $this->directThread($this->alice, $this->super, 'other');
        $message = Message::where('conversation_id', $other->id)->firstOrFail();

        // أليس كاتبة الرسالة و موجودة في المحادثتين — الغلط الوحيد هنا
        // هو الاقتران ، و ده بالظبط اللي بنحرسه .
        $this->actingAs($this->alice)
            ->deleteJson(route('messages.delete', [$mine, $message]))
            ->assertForbidden();

        $this->assertNull($message->fresh()->deleted_at);
    }

    // ── عقد الـ polling ───────────────────────────────────────────

    public function test_updates_returns_only_what_the_client_does_not_already_hold(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob, 'first');

        $opened = $this->actingAs($this->bob)->getJson(route('messages.show', $conversation))->assertOk();
        $lastId = $opened->json('last_id');
        $since  = $opened->json('server_time');

        $this->actingAs($this->bob)
            ->getJson(route('messages.updates', $conversation).'?'.http_build_query(['after_id' => $lastId, 'since' => $since]))
            ->assertOk()
            ->assertJsonCount(0, 'messages');

        $this->actingAs($this->alice)->postJson(route('messages.send', $conversation), ['body' => 'second'])->assertOk();

        $delta = $this->actingAs($this->bob)
            ->getJson(route('messages.updates', $conversation).'?'.http_build_query(['after_id' => $lastId, 'since' => $since]))
            ->assertOk();

        $delta->assertJsonCount(1, 'messages');
        $this->assertSame('second', $delta->json('messages.0.body'), 'Only the new message, not the whole thread.');
    }

    public function test_updates_reports_a_deletion_of_a_message_the_client_already_holds(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob, 'will vanish');
        $message = Message::where('conversation_id', $conversation->id)->firstOrFail();

        $opened = $this->actingAs($this->bob)->getJson(route('messages.show', $conversation))->assertOk();
        $lastId = $opened->json('last_id');
        $since  = $opened->json('server_time');

        // ساعة MySQL دقّتها ثانية ؛ من غير الانتظار ده الحذف ممكن يقع
        // جوه نفس الثانية بتاعة since و يتفلتر ، فالاختبار ينجح لسبب غلط .
        sleep(1);

        $this->actingAs($this->alice)->deleteJson(route('messages.delete', [$conversation, $message]))->assertOk();

        $delta = $this->actingAs($this->bob)
            ->getJson(route('messages.updates', $conversation).'?'.http_build_query(['after_id' => $lastId, 'since' => $since]))
            ->assertOk();

        $this->assertSame([$message->id], collect($delta->json('changed'))->pluck('id')->all());
        $this->assertTrue($delta->json('changed.0.deleted'));
        $this->assertNull($delta->json('changed.0.body'));
    }

    public function test_updates_with_mark_read_clears_the_badge_in_the_same_trip(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob, 'unread');
        $this->assertSame(1, Conversation::unreadCountFor($this->bob));

        $this->actingAs($this->bob)
            ->getJson(route('messages.updates', $conversation).'?'.http_build_query(['after_id' => 0, 'mark_read' => 1]))
            ->assertOk();

        $this->assertSame(0, Conversation::unreadCountFor($this->bob));
    }

    public function test_poll_answers_nothing_changed_when_the_fingerprint_matches(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob);

        $first = $this->actingAs($this->bob)->getJson(route('messages.poll'))->assertOk();
        $version = $first->json('version');
        $this->assertNotNull($version);
        $this->assertTrue($first->json('changed'), 'The first poll must return the full snapshot.');

        $this->actingAs($this->bob)
            ->getJson(route('messages.poll').'?'.http_build_query(['version' => $version]))
            ->assertOk()
            ->assertJsonPath('changed', false)
            ->assertJsonMissingPath('conversations');

        // ...و رسالة جديدة لازم تكسر البصمة ، و إلا الصندوق هيفضل يقول
        // "مفيش جديد" للأبد .
        $this->actingAs($this->alice)->postJson(route('messages.send', $conversation), ['body' => 'wake up'])->assertOk();

        $this->actingAs($this->bob)
            ->getJson(route('messages.poll').'?'.http_build_query(['version' => $version]))
            ->assertOk()
            ->assertJsonPath('changed', true);
    }

    // ── التحقّق ───────────────────────────────────────────────────

    public function test_an_empty_message_is_rejected(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob);

        $this->actingAs($this->alice)
            ->postJson(route('messages.send', $conversation), ['body' => '   '])
            ->assertStatus(422);
    }

    public function test_guests_get_nowhere_near_any_of_it(): void
    {
        $conversation = $this->directThread($this->alice, $this->bob);
        $this->app['auth']->forgetGuards();

        $this->getJson(route('messages.poll'))->assertUnauthorized();
        $this->getJson(route('messages.show', $conversation))->assertUnauthorized();
        $this->postJson(route('messages.start'), ['user_id' => $this->bob->id, 'body' => 'x'])->assertUnauthorized();
    }
}
