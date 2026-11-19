<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Baselines;
use App\Services\WbsSnapshot;
use App\Services\WbsTemplates;
use Database\Seeders\DevelopmentSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_PASSWORD = 'seeder-test-password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_local_login_reset_and_session_revocation(): void
    {
        $admin = $this->admin();
        $user = $this->member($this->organization('dock-a'));
        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->component('Auth')->where('mode', 'login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard')
            ->where('auth.organization.slug', 'dock-a')->where('activeProject', null)->missing('organizations')->missing('accounts'));
        $this->get('/org/dock-a/projects')->assertInertia(fn (AssertableInertia $page) => $page->component('Projects')
            ->where('auth.organization.slug', 'dock-a')->where('activeProject', null));
        $this->post('/logout')->assertRedirect('/login');
        Notification::fake();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        $resetToken = Notification::sent($user, ResetPassword::class)->first()->token;
        $this->post('/reset-password', [
            'token' => $resetToken, 'email' => $user->email, 'password' => 'reset-test-password', 'password_confirmation' => 'reset-test-password',
        ])->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'reset-test-password'])->assertRedirect('/');
        $this->post('/logout')->assertRedirect('/login');

        DB::table('sessions')->insert(['id' => 'revocable-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => 1]);
        $this->actingAs($admin)->patch('/accounts/'.$user->id, ['active' => false])->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'revocable-session']);
        $this->assertDatabaseHas('audit_events', ['target_id' => $user->id, 'action' => 'account.status_changed']);
        $this->actingAs($user->fresh())->get('/')->assertRedirect('/login');
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->member($this->organization('dock-a'));
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
    }

    public function test_project_numbers_permissions_and_scope_are_per_organization(): void
    {
        $a = $this->organization('dock-a');
        $b = $this->organization('dock-b');
        $local = $this->member($a, 'administrator');
        $admin = $this->admin();
        $member = $this->member($a, 'planner');
        $viewer = $this->member($b);
        $this->actingAs($local)->post('/org/dock-a/projects', $this->projectFields())->assertRedirect();
        $this->post('/org/dock-b/projects', $this->projectFields())->assertNotFound();
        $this->actingAs($admin)->post('/org/dock-b/projects', $this->projectFields())->assertRedirect();
        $this->assertSame(2, DB::table('projects')->where('number', 'NB-01')->count());

        $projectA = DB::table('projects')->where('galangan_id', $a)->first();
        $projectB = DB::table('projects')->where('galangan_id', $b)->first();
        $this->assign($member, $projectA->id);
        $this->assign($viewer, $projectB->id);
        $this->actingAs($member)->get('/org/dock-a/projects/'.$projectB->id)->assertNotFound();
        $this->get('/org/dock-a/projects/'.$projectA->id.'/wbs')->assertOk();
        $this->get('/org/dock-b/projects/'.$projectB->id.'/wbs')->assertNotFound();
        $this->actingAs($viewer)->get('/org/dock-b/projects/'.$projectB->id.'/wbs')->assertForbidden();
        $this->actingAs($member)->get('/org/dock-a/access')->assertForbidden();
        $this->actingAs($local)->get('/org/dock-a/access')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('roles.planner', 'Planner / Engineering')->missing('roles.platform_admin')
            ->where('catalog', fn ($catalog) => $catalog['wbs.view'] === 'Lihat Form Builder dan baseline'));
        $this->actingAs($local)->put('/org/dock-a/access/permissions/planner', ['actions' => ['projects.view']])->assertRedirect();
        $this->actingAs($member)->get('/org/dock-a/projects/'.$projectA->id.'/wbs')->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['galangan_id' => $a, 'action' => 'organization.permissions_changed']);
        $this->actingAs($admin)->get('/org/dock-b/projects/'.$projectB->id.'/wbs')->assertOk();
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard')
            ->where('auth.user.platform_admin', true)->missing('organizations'));
    }

    public function test_data_master_uses_platform_and_organization_permissions(): void
    {
        $a = $this->organization('dock-a');
        $b = $this->organization('dock-b');
        $local = $this->member($a, 'administrator');
        $member = $this->member($a, 'planner');
        $other = $this->member($b);
        $admin = $this->admin();
        $this->get('/')->assertRedirect('/login');
        $this->actingAs($admin)->get('/data-master/user')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Master')->where('section', 'users')->has('accounts.data', 4));
        $this->get('/data-master/galangan')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Master')->where('section', 'shipyards')->has('organizations', 2));
        $this->get('/data-master/role')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Master')->where('section', 'roles')->where('roles.platform_admin', 'Administrator Pusat'));
        $this->post('/org', ['name' => 'Galangan Baru', 'slug' => 'dock-c'])->assertRedirect('/data-master/galangan');
        $newOrganization = DB::table('galangantbls')->where('slug', 'dock-c')->value('id');
        $this->assertDatabaseHas('audit_events', ['galangan_id' => $newOrganization, 'action' => 'organization.created']);

        $this->get('/org/dock-b/access/users')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('organization.slug', 'dock-b')->where('members.0.id', $other->id)->has('members', 1));
        $this->actingAs($local)->get('/org/dock-a/access/users')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Organization')->where('section', 'users')->has('members', 2)->has('invitations')->missing('permissions'));
        $this->get('/org/dock-a/access')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('section', 'roles')->has('permissions')->missing('members'));
        foreach (['user', 'galangan', 'role'] as $section) {
            $this->get('/data-master/'.$section)->assertForbidden();
        }
        $this->get('/org/dock-b/access/users')->assertNotFound();
        $this->get('/org/dock-b/access')->assertNotFound();
        $this->patch('/org/dock-a/access/members/'.$other->id, ['active' => false, 'role' => 'viewer'])->assertNotFound();
        $this->actingAs($member)->get('/org/dock-a/access/users')->assertForbidden();
        $this->get('/org/dock-a/access')->assertForbidden();
    }

    public function test_existing_account_cannot_be_invited_to_another_organization(): void
    {
        $a = $this->organization('dock-a');
        $b = $this->organization('dock-b');
        $user = $this->member($a, 'planner');
        $this->actingAs($this->member($b, 'administrator'))->post('/org/dock-b/access/invitations', [
            'email' => $user->email, 'role' => 'reviewer',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseEmpty('invitations');
        $invitation = (string) Str::ulid();
        $expires = now()->addHour();
        DB::table('invitations')->insert(['id' => $invitation, 'email' => $user->email, 'galangan_id' => $b, 'kode_role' => 'reviewer', 'expires_at' => $expires]);
        $url = URL::temporarySignedRoute('invitation.show', $expires, compact('invitation'));
        $this->actingAs($user)->get($url)->assertForbidden();
        $this->postJson($url, ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'galangan_id' => $a, 'kode_role' => 'planner']);
        $this->assertDatabaseHas('invitations', ['id' => $invitation, 'accepted_at' => null]);
    }

    public function test_invitation_creates_account_with_organization_and_role(): void
    {
        $organization = $this->organization('dock-a');
        $invitation = (string) Str::ulid();
        $expires = now()->addHour();
        $email = 'undangan@example.test';
        DB::table('invitations')->insert(['id' => $invitation, 'email' => $email, 'galangan_id' => $organization, 'kode_role' => 'reviewer', 'expires_at' => $expires]);
        $url = URL::temporarySignedRoute('invitation.show', $expires, compact('invitation'));
        $this->post($url, ['name' => 'Reviewer baru', 'password' => 'invited-test-password', 'password_confirmation' => 'invited-test-password'])
            ->assertRedirect('/');
        $this->assertDatabaseHas('users', ['email' => $email, 'galangan_id' => $organization, 'kode_role' => 'reviewer']);
        $this->get($url)->assertNotFound();
    }

    public function test_database_requires_an_organization_for_an_ordinary_user(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('users_galangan_required');

        User::factory()->create();
    }

    public function test_project_switcher_keeps_context_and_rechecks_assignment(): void
    {
        $a = $this->organization('dock-a');
        $b = $this->organization('dock-b');
        $first = $this->project($a);
        $second = $this->project($a, ['number' => 'NB-02', 'ship_name' => 'Tug kedua', 'wbs_restricted' => true]);
        $unassigned = $this->project($a, ['number' => 'NB-03']);
        $outside = $this->project($b);
        $planner = $this->member($a, 'planner');
        $this->assign($planner, $first);
        $this->assign($planner, $second);
        $base = '/org/dock-a';
        $this->actingAs($planner)->withSession(['active_projects.'.$a => $first])
            ->get('/')->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard')->where('activeProject', null));
        $this->get($base.'/projects')->assertInertia(fn (AssertableInertia $page) => $page->where('activeProject', null));
        $this->get($base.'/projects/'.$first)->assertInertia(fn (AssertableInertia $page) => $page->where('activeProject', null));
        $this->get($base.'/projects/'.$first.'/wbs')->assertInertia(fn (AssertableInertia $page) => $page->where('activeProject', null));
        $this->getJson($base.'/project-options?q=Tug')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second);
        $this->getJson('/project-options')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/org/dock-b/project-options')->assertNotFound();
        foreach ([$unassigned, $outside] as $denied) {
            $this->post($base.'/active-project', ['project_id' => $denied, 'destination' => 'projects'])->assertNotFound();
            $this->post('/active-project', ['project_id' => $denied, 'destination' => 'dashboard'])->assertNotFound();
        }
        $this->post('/active-project', ['project_id' => $first, 'destination' => 'dashboard'])
            ->assertStatus(303)->assertRedirect('/');
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard')
            ->where('activeProject.id', $first)->where('activeProject.canOpenBuilder', true));
        $this->get($base.'/projects/'.$second)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('project.id', $second)->where('activeProject.id', $first));
        $this->post($base.'/active-project', ['project_id' => $second, 'destination' => 'projects', 'q' => 'Tug'])
            ->assertStatus(303)->assertRedirect($base.'/projects?q=Tug');
        $this->get($base.'/projects')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activeProject.id', $second)->where('activeProject.canOpenBuilder', false));
        $this->post('/active-project', ['project_id' => $second, 'destination' => 'builder'])
            ->assertStatus(303)->assertRedirect($base.'/projects/'.$second);
        $this->post($base.'/active-project', ['project_id' => $first, 'destination' => 'builder'])
            ->assertStatus(303)->assertRedirect($base.'/projects/'.$first.'/wbs');
        DB::table('project_members')->where('project_id', $first)->where('user_id', $planner->id)->delete();
        $this->get($base.'/projects')->assertInertia(fn (AssertableInertia $page) => $page->where('activeProject', null));
    }

    public function test_platform_administrator_can_select_project_across_shipyards_without_leaving_master(): void
    {
        $a = $this->organization('dock-a');
        $b = $this->organization('dock-b');
        $first = $this->project($a);
        $second = $this->project($b, ['ship_name' => 'Kapal Galangan B']);
        $this->actingAs($this->admin())->getJson('/project-options?q=Kapal+Galangan+B')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second)
            ->assertJsonPath('data.0.organization_name', 'dock-b');
        $this->post('/active-project', ['project_id' => $second, 'destination' => 'org_roles', 'return_organization' => 'dock-a'])
            ->assertStatus(303)->assertRedirect('/org/dock-a/access');
        $this->get('/org/dock-a/access')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('organization.slug', 'dock-a')->where('activeProject.organization.slug', 'dock-b'));
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('activeProject.id', $second));
        $this->post('/active-project', ['project_id' => $first, 'destination' => 'master_users', 'page' => 2])
            ->assertStatus(303)->assertRedirect('/data-master/user?page=2');
        $this->post('/org/dock-a/projects', ['number' => 'NB-99'] + $this->projectFields())->assertRedirect();
        $this->post('/org/dock-a/active-project', ['project_id' => $second, 'destination' => 'dashboard'])->assertNotFound();
        $this->from('/')->post('/active-project', ['project_id' => $second, 'destination' => '/login'])->assertSessionHasErrors('destination');
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('activeProject.id', $first));
        $member = $this->member($a, 'administrator');
        $this->actingAs($member)->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('activeProject', null));
    }

    public function test_private_documents_require_project_and_document_permission(): void
    {
        Storage::fake('local');
        $a = $this->organization('dock-a');
        $b = $this->organization('dock-b');
        $project = $this->project($a);
        $manager = $this->member($a, 'manager');
        $viewer = $this->member($a);
        $outside = $this->member($b, 'administrator');
        $this->assign($manager, $project);
        $this->assign($viewer, $project);
        $base = '/org/dock-a/projects/'.$project.'/documents';
        $this->actingAs($manager)->post($base, [
            'title' => 'Kontrak', 'file' => UploadedFile::fake()->create('kontrak.pdf', 12, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $document = DB::table('project_documents')->where('project_id', $project)->first();
        Storage::disk('local')->assertExists($document->path);
        $this->get($base.'/'.$document->id)->assertDownload('kontrak.pdf');
        $this->actingAs($viewer)->get($base.'/'.$document->id)->assertForbidden();
        $this->actingAs($outside)->get($base.'/'.$document->id)->assertNotFound();
        $this->assertDatabaseHas('audit_events', ['project_id' => $project, 'action' => 'project.document_uploaded']);
    }

    public function test_device_token_replacement_and_membership_revocation_are_scoped(): void
    {
        $a = $this->organization('dock-a');
        $b = $this->organization('dock-b');
        $projectA = $this->project($a);
        $projectB = $this->project($b);
        $user = $this->member($a);
        $other = $this->member($b);
        $this->assign($user, $projectA);
        $this->assign($other, $projectB);
        $old = $this->issue($user, 'dock-a');
        $secondOrg = $this->issue($other, 'dock-b');
        $current = $this->issue($user);
        $this->tokenRequest($user, 'dock-b')->assertUnauthorized();
        $this->tokenRequest($this->admin(), null)->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
        $this->api('/api/v1/me', $old)->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
        $this->api('/api/v1/projects', $current)->assertOk()->assertJsonPath('data.0.id', $projectA)
            ->assertJsonPath('data.0.organization_id', $a)->assertJsonMissingPath('data.0.galangan_id')->assertJsonPath('meta.total', 1);
        $this->api('/api/v1/projects/'.$projectA, $current)->assertOk()
            ->assertJsonPath('data.organization_id', $a)->assertJsonMissingPath('data.galangan_id');
        $this->api('/api/v1/projects/'.$projectB, $current)->assertNotFound();
        $this->api('/api/v1/projects/'.$projectA.'/wbs', $current)->assertForbidden();
        $this->actingAs($this->admin())->patch('/org/dock-a/access/members/'.$user->id, ['active' => false, 'role' => 'viewer'])->assertRedirect();
        $this->api('/api/v1/me', $current)->assertUnauthorized();
        $this->api('/api/v1/me', $secondOrg)->assertOk()->assertJsonPath('data.organization.slug', 'dock-b');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_expired_tokens_and_tokens_revoked_from_web_are_rejected(): void
    {
        $organization = $this->organization('dock-a');
        $user = $this->member($organization);
        $expired = $this->issue($user, 'dock-a');
        DB::table('personal_access_tokens')->where('id', Str::before($expired, '|'))->update(['expires_at' => now()->subMinute()]);
        $this->api('/api/v1/me', $expired)->assertUnauthorized();

        $active = $this->issue($user, 'dock-a');
        $tokenId = Str::before($active, '|');
        $this->api('/api/v1/me', $active)->assertOk();
        $this->actingAs($user, 'web')->delete('/devices/'.$tokenId)->assertRedirect();
        $this->api('/api/v1/me', $active)->assertUnauthorized();
        $this->assertDatabaseHas('audit_events', ['target_id' => $tokenId, 'action' => 'account.device_token_revoked']);
    }

    public function test_snapshot_validation_rejects_cycles_wrong_totals_and_decreasing_targets(): void
    {
        $organization = $this->organization('dock-a');
        $project = DB::table('projects')->where('id', $this->project($organization))->first();
        $sample = WbsTemplates::project('ferry', $project, 'reviewer');
        $this->assertSame('71.000', WbsSnapshot::physicalProgress(WbsTemplates::methods()['Material'], ['80', '50', '20']));
        $url = '/org/dock-a/projects/'.$project->id.'/wbs';
        $this->actingAs($this->admin());
        $invalid = $sample;
        $invalid['nodes'][0]['type'] = 'group';
        $invalid['nodes'][0]['parent_id'] = $invalid['nodes'][0]['id'];
        $this->put($url.'/draft', ['snapshot' => $invalid, 'effective_month' => '2026-10'])->assertSessionHasErrors('nodes');
        $invalid = $sample;
        $item = $invalid['nodes'][1]['id'];
        $invalid['targets'] = [['item_id' => $item, 'period' => '2026-09', 'value' => '80'], ['item_id' => $item, 'period' => '2026-10', 'value' => '70']];
        $this->put($url.'/draft', ['snapshot' => $invalid, 'effective_month' => '2026-10'])->assertSessionHasErrors('targets');
        $invalid = $sample;
        $invalid['nodes'][0]['weight'] = '50';
        $this->put($url.'/draft', ['snapshot' => $invalid, 'effective_month' => '2026-10'])->assertSessionHasNoErrors();
        $draft = app(Baselines::class)->open($project->id);
        $this->post($url.'/'.$draft->id.'/transition', ['operation' => 'submit', 'version' => 1, 'reason' => 'Periksa bobot'])->assertSessionHasErrors('nodes');
    }

    public function test_designated_proposer_can_publish_and_locked_period_keeps_original_revision(): void
    {
        $a = $this->organization('dock-a');
        $projectId = $this->project($a);
        $person = $this->member($a, 'planner');
        $this->assign($person, $projectId);
        DB::table('projects')->where('id', $projectId)->update(['baseline_approver_id' => $person->id]);
        DB::table('galangan_premissiontbls')->insert([
            'galangan_id' => $a, 'kode_role' => 'planner', 'actions' => json_encode(['projects.view', 'wbs.view', 'wbs.manage', 'baselines.submit', 'baselines.approve']),
        ]);
        $project = DB::table('projects')->where('id', $projectId)->first();
        $snapshot = WbsTemplates::project('tug', $project, 'reviewer');
        foreach (array_filter($snapshot['nodes'], fn ($node) => $node['type'] === 'item') as $node) {
            $snapshot['targets'][] = ['item_id' => $node['id'], 'period' => '2026-10', 'value' => '0'];
            $snapshot['targets'][] = ['item_id' => $node['id'], 'period' => '2026-11', 'value' => '100'];
        }
        $service = app(Baselines::class);
        $old = $service->save($person, $project, ['snapshot' => $snapshot, 'effective_month' => '2026-09']);
        $url = '/org/dock-a/projects/'.$projectId.'/wbs/'.$old.'/transition';
        $this->actingAs($person)->post($url, ['operation' => 'submit', 'version' => 1, 'reason' => 'Baseline awal'])->assertSessionHasNoErrors();
        $this->post($url, ['operation' => 'publish', 'version' => 1])->assertConflict();
        $this->post($url, ['operation' => 'publish', 'version' => 2])->assertRedirect();
        $this->post($url, ['operation' => 'publish', 'version' => 2])->assertRedirect();
        $this->assertSame(1, DB::table('audit_events')->where('action', 'baseline.publish')->where('target_id', $old)->count());

        DB::table('project_periods')->insert(['project_id' => $projectId, 'month' => '2026-10', 'official_baseline_id' => $old]);
        $snapshot['targets'][0]['value'] = '35';
        $new = $service->save($person, $project, ['snapshot' => $snapshot, 'effective_month' => '2026-09']);
        $service->transition($person, $project, $new, 'submit', 1, 'Revisi target');
        $service->transition($person, $project, $new, 'publish', 2, null);
        $this->assertSame($old, $service->published($projectId, '2026-10')->id);
        $this->assertSame($new, $service->published($projectId, '2026-11')->id);
        $this->assertSame('0.000', $service->planned($service->snapshot($service->published($projectId, '2026-10')), '2026-10')['planned_pct']);
    }

    public function test_ferry_fixture_and_tug_publish_to_api_with_reader_constraints(): void
    {
        config(['monitoring.demo_password' => self::DEMO_PASSWORD]);
        $this->seed();
        $admin = User::query()->where('email', 'admin@demo.monitoring.test')->firstOrFail();
        $planner = User::query()->where('email', 'planner.ferry@demo.monitoring.test')->firstOrFail();
        $viewer = User::query()->where('email', 'viewer.tug@demo.monitoring.test')->firstOrFail();
        $ferry = DB::table('galangantbls')->where('slug', 'ferry-demo')->first();
        $tug = DB::table('galangantbls')->where('slug', 'tug-demo')->first();
        $project = DB::table('projects')->where('galangan_id', $ferry->id)->first();
        $tugProject = DB::table('projects')->where('galangan_id', $tug->id)->first();
        $this->post('/login', ['email' => $planner->email, 'password' => self::DEMO_PASSWORD])->assertRedirect('/');
        $this->assertAuthenticatedAs($planner);
        $this->get('/org/ferry-demo/projects/'.$project->id.'/wbs')->assertOk();
        $this->get('/org/tug-demo/projects/'.$tugProject->id)->assertNotFound();
        $this->post('/logout')->assertRedirect('/login');
        $this->actingAs($viewer)->get('/org/tug-demo/projects/'.$tugProject->id)->assertOk();
        $this->get('/org/tug-demo/projects/'.$tugProject->id.'/wbs')->assertForbidden();

        $viewerToken = $this->issue($viewer, 'tug-demo', self::DEMO_PASSWORD);
        $this->api('/api/v1/projects/'.$tugProject->id, $viewerToken)->assertOk();
        $this->api('/api/v1/projects/'.$tugProject->id.'/wbs?period=2026-10', $viewerToken)->assertForbidden();
        $this->api('/api/v1/projects/'.$project->id, $viewerToken)->assertNotFound();
        $token = $this->issue($planner, $ferry->slug, self::DEMO_PASSWORD);
        $uri = '/api/v1/projects/'.$project->id.'/wbs?period=2026-10';
        $response = $this->api($uri, $token)->assertOk()->assertJsonPath('meta.planned_pct', '30.693')->assertJsonPath('meta.total', 8);
        $item = $response->json('data.1.id');
        $this->api('/api/v1/wbs-items/'.$item.'?period=2026-10', $token)->assertOk()->assertJsonPath('data.planned_pct', null);
        $this->api('/api/v1/projects/'.$project->id.'/wbs?period=2026-09', $token)->assertOk()->assertJsonPath('meta.baseline_status', 'not_published');
        $this->api('/api/v1/projects/'.$project->id.'/wbs?period=2026-13', $token)->assertUnprocessable();
        $path = '/org/ferry-demo/projects/'.$project->id.'/wbs/readers';
        $this->actingAs($admin)->put($path, ['restricted' => true, 'readers' => [$planner->id]])->assertSessionHasNoErrors();
        $this->api($uri, $token)->assertOk();
        DB::table('wbs_readers')->where('project_id', $project->id)->delete();
        $this->api($uri, $token)->assertForbidden();
        $this->api('/api/v1/wbs-items/'.$item.'?period=2026-10', $token)->assertNotFound();
        $tugToken = $this->issue($admin, 'tug-demo', self::DEMO_PASSWORD);
        $tugWbs = '/api/v1/projects/'.$tugProject->id.'/wbs?period=';
        $this->api($tugWbs.'2026-10', $tugToken)->assertOk()->assertJsonPath('meta.planned_pct', '60.000')->assertJsonPath('meta.total', 18);
        $this->api($tugWbs.'2026-11', $tugToken)->assertOk()->assertJsonPath('meta.planned_pct', '100.000');
    }

    public function test_demo_seeding_preserves_manual_changes_on_rerun(): void
    {
        config(['monitoring.demo_password' => self::DEMO_PASSWORD]);
        $this->seed();
        $manager = User::query()->where('email', 'manager.ferry@demo.monitoring.test')->firstOrFail();
        $pic = User::query()->where('email', 'pic.ferry@demo.monitoring.test')->firstOrFail();
        $ferry = DB::table('galangantbls')->where('slug', 'ferry-demo')->first();
        $project = DB::table('projects')->where('galangan_id', $ferry->id)->first();
        $this->assertSame($manager->id, $project->manager_id);
        $this->assertSame($manager->id, $project->baseline_approver_id);
        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'user_id' => $pic->id, 'is_pic' => true]);
        $this->assertDatabaseHas('project_periods', ['project_id' => $project->id, 'month' => '2026-10', 'cutoff_date' => '2026-10-28']);
        foreach (User::all() as $account) {
            $this->assertTrue(Hash::check(self::DEMO_PASSWORD, $account->password));
        }

        $pic->update(['password' => 'changed-test-password']);
        DB::table('project_members')->where('project_id', $project->id)->where('user_id', $pic->id)->delete();
        DB::table('projects')->where('id', $project->id)->update(['baseline_approver_id' => null]);
        $auditCount = DB::table('audit_events')->count();
        $this->seed();

        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('galangantbls', 2);
        $this->assertDatabaseCount('projects', 2);
        $this->assertDatabaseCount('baseline_revisions', 2);
        $this->assertDatabaseCount('audit_events', $auditCount);
        $this->assertTrue(Hash::check('changed-test-password', $pic->fresh()->password));
        $this->assertDatabaseMissing('project_members', ['project_id' => $project->id, 'user_id' => $pic->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'baseline_approver_id' => null]);
    }

    public function test_legacy_demo_keeps_its_baseline_and_existing_leader(): void
    {
        $admin = $this->admin();
        $ferry = $this->organization('ferry-demo');
        $projectId = $this->project($ferry);
        $manager = $this->member($ferry, 'manager');
        $this->assign($manager, $projectId);
        DB::table('projects')->where('id', $projectId)->update(['number' => 'DEMO-01', 'manager_id' => $manager->id]);
        $project = DB::table('projects')->where('id', $projectId)->first();
        $snapshot = WbsTemplates::project('ferry', $project, 'reviewer');
        $snapshot['mode'] = 'legacy';
        $snapshot['legacy'][] = ['category_id' => null, 'period' => '2026-10', 'value' => '30.693028', 'source' => 'Ferry · Kurva-S (All)!M16'];
        $service = app(Baselines::class);
        $baseline = $service->save($admin, $project, ['snapshot' => $snapshot, 'effective_month' => '2026-10']);
        $service->transition($admin, $project, $baseline, 'submit', 1, 'Fixture development untuk pemeriksaan struktur dan target.');
        $service->transition($admin, $project, $baseline, 'publish', 2, null);

        config(['monitoring.demo_password' => self::DEMO_PASSWORD]);
        $this->seed();

        $demoManager = User::query()->where('email', 'manager.ferry@demo.monitoring.test')->firstOrFail();
        $this->assertDatabaseHas('projects', ['id' => $projectId, 'manager_id' => $manager->id, 'baseline_approver_id' => $demoManager->id]);
        $this->assertDatabaseHas('project_members', ['project_id' => $projectId, 'user_id' => $manager->id]);
        $this->assertDatabaseHas('users', ['galangan_id' => $ferry, 'id' => $demoManager->id, 'kode_role' => 'manager']);
        $this->assertSame($baseline, $service->published($projectId, '2026-10')->id);
        $this->assertSame(1, $service->revisions($projectId)->count());
    }

    public function test_demo_slug_collision_rolls_back_seeding(): void
    {
        $tug = $this->organization('tug-demo');
        $project = $this->project($tug);
        DB::table('projects')->where('id', $project)->update(['number' => 'DEMO-01']);
        config(['monitoring.demo_password' => self::DEMO_PASSWORD]);

        try {
            $this->seed();
            $this->fail('Seeder accepted a conflicting demo slug.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Slug demo tug-demo', $exception->getMessage());
        }

        $this->assertDatabaseCount('galangantbls', 1);
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('baseline_revisions');
        $this->assertDatabaseEmpty('audit_events');
    }

    public function test_demo_email_collision_preserves_existing_account(): void
    {
        $account = User::factory()->create(['email' => 'viewer.tug@demo.monitoring.test', 'galangan_id' => $this->organization('dock-a')]);
        config(['monitoring.demo_password' => self::DEMO_PASSWORD]);

        try {
            $this->seed();
            $this->fail('Seeder accepted a conflicting demo email.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($account->email, $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($account->password, $account->fresh()->password);
        $this->assertDatabaseCount('galangantbls', 1);
        $this->assertDatabaseEmpty('audit_events');
    }

    #[DataProvider('invalidDemoPasswords')]
    public function test_demo_seeding_requires_a_valid_configured_password(?string $password): void
    {
        config(['monitoring.demo_password' => $password]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MONITORING_DEMO_PASSWORD');

        $this->seed();
    }

    public static function invalidDemoPasswords(): array
    {
        return [
            'missing' => [null],
            'too short' => [str_repeat('a', 11)],
            'too long in bytes' => [str_repeat('é', 37)],
        ];
    }

    public function test_production_seeding_does_not_load_demo_data(): void
    {
        config(['monitoring.demo_password' => self::DEMO_PASSWORD]);
        $this->app->instance('env', 'production');
        DB::table('roletbls')->delete();
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('roletbls', 10);
        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('galangantbls');

        $this->expectException(RuntimeException::class);
        $this->artisan('db:seed', ['--class' => DevelopmentSeeder::class, '--force' => true])->run();
    }

    private function admin(): User
    {
        return User::factory()->create(['kode_role' => 'platform_admin']);
    }

    private function organization(string $slug): string
    {
        $id = (string) Str::ulid();
        DB::table('galangantbls')->insert(['id' => $id, 'slug' => $slug, 'name' => $slug, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function project(string $organization, array $fields = []): string
    {
        $id = (string) Str::ulid();
        DB::table('projects')->insert($fields + $this->projectFields() + ['id' => $id, 'galangan_id' => $organization, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function projectFields(): array
    {
        return [
            'number' => 'NB-01', 'name' => 'Proyek contoh', 'ship_name' => 'Contoh 01', 'ship_type' => 'Ferry',
            'owner' => 'Pemilik', 'shipyard' => 'Galangan', 'start_date' => '2026-09-01',
            'target_date' => '2026-11-30', 'status' => 'active', 'contract_amount' => '123456',
            'currency' => 'IDR',
        ];
    }

    private function member(string $organization, string $role = 'viewer'): User
    {
        return User::factory()->create(['galangan_id' => $organization, 'kode_role' => $role]);
    }

    private function assign(User $user, string $project): void
    {
        DB::table('project_members')->insert(['project_id' => $project, 'user_id' => $user->id]);
    }

    private function issue(User $user, ?string $slug = null, string $password = 'password'): string
    {
        return $this->tokenRequest($user, $slug, $password)->assertCreated()->json('data.token');
    }

    private function tokenRequest(User $user, ?string $slug, string $password = 'password'): TestResponse
    {
        Auth::forgetGuards();

        return $this->postJson('/api/v1/auth/tokens', [
            'email' => $user->email, 'password' => $password,
            'device_id' => 'test-device', 'device_name' => 'Perangkat tes',
        ] + ($slug === null ? [] : ['organization' => $slug]));
    }

    private function api(string $path, string $token): TestResponse
    {
        Auth::forgetGuards();

        return $this->getJson($path, ['Authorization' => 'Bearer '.$token]);
    }
}
