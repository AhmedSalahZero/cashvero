<?php

namespace Tests\Feature\Activity;

use App\Http\Controllers\RecordActivityController;
use App\Models\Company;
use App\Models\RecordActivity;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use Illuminate\Http\Request;
use Tests\TestCase;

class RecordActivityVisibilityTest extends TestCase
{
    private Company $company;

    private User $superAdmin;

    private User $otherSuperAdmin;

    private User $ordinaryUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
        $this->superAdmin = $this->makeSuperAdmin($this->company);
        $this->otherSuperAdmin = $this->makeSuperAdmin($this->company);
        $this->ordinaryUser = $this->makeUser(['company.view'], $this->company);
    }

    public function test_writer_snapshots_the_actors_super_admin_status(): void
    {
        $this->actingAs($this->superAdmin);
        $superEntry = ActivityLogger::custom($this->company, 'super snapshot');

        $this->actingAs($this->ordinaryUser);
        $ordinaryEntry = ActivityLogger::custom($this->company, 'ordinary snapshot');

        $this->assertTrue($superEntry->fresh()->actor_is_super_admin);
        $this->assertFalse($ordinaryEntry->fresh()->actor_is_super_admin);
    }

    public function test_visibility_scope_hides_only_new_super_admin_activity_from_ordinary_users(): void
    {
        $this->insertActivity($this->superAdmin, true, 'protected activity');
        $this->insertActivity($this->ordinaryUser, false, 'ordinary activity');
        $this->insertActivity($this->superAdmin, null, 'legacy activity');

        $ordinaryDescriptions = RecordActivity::query()
            ->visibleTo($this->ordinaryUser)
            ->where('subject_type', Company::class)
            ->where('subject_id', $this->company->id)
            ->pluck('description');

        $this->assertNotContains('protected activity', $ordinaryDescriptions);
        $this->assertContains('ordinary activity', $ordinaryDescriptions);
        $this->assertContains('legacy activity', $ordinaryDescriptions);

        $superDescriptions = RecordActivity::query()
            ->visibleTo($this->otherSuperAdmin)
            ->where('subject_type', Company::class)
            ->where('subject_id', $this->company->id)
            ->pluck('description');

        $this->assertContains('protected activity', $superDescriptions);
        $this->assertContains('ordinary activity', $superDescriptions);
        $this->assertContains('legacy activity', $superDescriptions);
    }

    public function test_record_timeline_enforces_the_same_visibility_policy(): void
    {
        $this->insertActivity($this->superAdmin, true, 'protected timeline entry');
        $this->insertActivity($this->ordinaryUser, false, 'ordinary timeline entry');
        $this->insertActivity($this->superAdmin, null, 'legacy timeline entry');

        $ordinarySentences = $this->timelineSentences($this->ordinaryUser);
        $this->assertNotContains('protected timeline entry', $ordinarySentences);
        $this->assertContains('ordinary timeline entry', $ordinarySentences);
        $this->assertContains('legacy timeline entry', $ordinarySentences);

        $superSentences = $this->timelineSentences($this->otherSuperAdmin);
        $this->assertContains('protected timeline entry', $superSentences);
        $this->assertContains('ordinary timeline entry', $superSentences);
        $this->assertContains('legacy timeline entry', $superSentences);
    }

    private function insertActivity(User $actor, ?bool $actorIsSuperAdmin, string $description): RecordActivity
    {
        return RecordActivity::create([
            'subject_type' => Company::class,
            'subject_id' => $this->company->id,
            'company_id' => $this->company->id,
            'user_id' => $actor->id,
            'user_name' => $actor->name,
            'actor_is_super_admin' => $actorIsSuperAdmin,
            'event' => RecordActivity::EVENT_CUSTOM,
            'description' => $description,
            'created_at' => now(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function timelineSentences(User $viewer): array
    {
        $request = Request::create('/record-activity/Company/'.$this->company->id, 'GET');
        $request->setUserResolver(fn () => $viewer);

        $response = (new RecordActivityController)(
            $request,
            $this->company,
            class_basename(Company::class),
            $this->company->id
        );

        return collect($response->getData(true)['entries'])->pluck('sentence')->all();
    }
}
