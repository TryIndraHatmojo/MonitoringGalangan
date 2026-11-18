<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Baselines;
use App\Services\WbsSnapshot;
use App\Services\WbsTemplates;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_login_reset_and_session_revocation(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->component('Auth')->where('mode', 'login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertOk();
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
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
    }

    public function test_project_numbers_permissions_and_scope_are_per_organization(): void
    {
        $a = $this->organization('dock-a');
        $b = $this->organization('dock-b');
        $local = User::factory()->create();
        $admin = $this->admin();
        $member = User::factory()->create();
        $this->membership($local, $a, 'administrator');
        $this->membership($member, $a, 'planner');
        $this->membership($member, $b, 'viewer');
        $this->actingAs($local)->post('/org/dock-a/projects', $this->projectFields())->assertRedirect();
        $this->post('/org/dock-b/projects', $this->projectFields())->assertNotFound();
        $this->actingAs($admin)->post('/org/dock-b/projects', $this->projectFields())->assertRedirect();
        $this->assertSame(2, DB::table('projects')->where('number', 'NB-01')->count());

        $projectA = DB::table('projects')->where('organization_id', $a)->first();
        $projectB = DB::table('projects')->where('organization_id', $b)->first();
        $this->assign($member, $projectA->id);
        $this->assign($member, $projectB->id);
        $this->actingAs($member)->get('/org/dock-a/projects/'.$projectB->id)->assertNotFound();
        $this->get('/org/dock-a/projects/'.$projectA->id.'/wbs')->assertOk();
        $this->get('/org/dock-b/projects/'.$projectB->id.'/wbs')->assertForbidden();
        $this->actingAs($local)->put('/org/dock-a/access/permissions/planner', ['actions' => ['projects.view']])->assertRedirect();
        $this->actingAs($member)->get('/org/dock-a/projects/'.$projectA->id.'/wbs')->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['organization_id' => $a, 'action' => 'organization.permissions_changed']);
        $this->actingAs($admin)->get('/org/dock-b/projects/'.$projectB->id.'/wbs')->assertOk();
    }

    public function test_existing_account_can_accept_invitation_to_another_organization(): void
    {
        $a = $this->organization('dock-a');
        $b = $this->organization('dock-b');
        $user = User::factory()->create();
        $this->membership($user, $a, 'planner');
        $invitation = (string) Str::ulid();
        $expires = now()->addHour();
        DB::table('invitations')->insert(['id' => $invitation, 'email' => $user->email, 'organization_id' => $b, 'role' => 'reviewer', 'expires_at' => $expires]);
        $url = URL::temporarySignedRoute('invitation.show', $expires, compact('invitation'));
        $this->get($url)->assertOk();
        $this->post($url, ['password' => 'password'])->assertRedirect('/org/dock-b/projects');
        $this->assertDatabaseHas('organization_members', ['organization_id' => $b, 'user_id' => $user->id, 'role' => 'reviewer']);
        $this->get($url)->assertNotFound();
    }

    public function test_private_documents_require_project_and_document_permission(): void
    {
        Storage::fake('local');
        $a = $this->organization('dock-a');
        $b = $this->organization('dock-b');
        $project = $this->project($a);
        $manager = User::factory()->create();
        $viewer = User::factory()->create();
        $outside = User::factory()->create();
        $this->membership($manager, $a, 'manager');
        $this->membership($viewer, $a, 'viewer');
        $this->membership($outside, $b, 'administrator');
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
        $user = User::factory()->create();
        $this->membership($user, $a, 'viewer');
        $this->membership($user, $b, 'viewer');
        $this->assign($user, $projectA);
        $this->assign($user, $projectB);
        $old = $this->issue($user, 'dock-a');
        $secondOrg = $this->issue($user, 'dock-b');
        $current = $this->issue($user, 'dock-a');
        $this->api('/api/v1/me', $old)->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
        $this->api('/api/v1/projects', $current)->assertOk()->assertJsonPath('data.0.id', $projectA)->assertJsonPath('meta.total', 1);
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
        $user = User::factory()->create();
        $this->membership($user, $organization, 'viewer');
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
        $person = User::factory()->create();
        $this->membership($person, $a, 'planner');
        $this->assign($person, $projectId);
        DB::table('projects')->where('id', $projectId)->update(['baseline_approver_id' => $person->id]);
        DB::table('organization_permissions')->insert([
            'organization_id' => $a, 'role' => 'planner', 'actions' => json_encode(['projects.view', 'wbs.view', 'wbs.manage', 'baselines.submit', 'baselines.approve']),
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
        $admin = $this->admin();
        $this->seed(DevelopmentSeeder::class);
        $ferry = DB::table('organizations')->where('slug', 'ferry-demo')->first();
        $project = DB::table('projects')->where('organization_id', $ferry->id)->first();
        $planner = User::factory()->create();
        $this->membership($planner, $ferry->id, 'planner');
        $this->assign($planner, $project->id);
        $token = $this->issue($planner, $ferry->slug);
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
        $tug = $this->issue($admin, 'tug-demo');
        $tugProject = $this->api('/api/v1/projects', $tug)->json('data.0.id');
        $this->api('/api/v1/projects/'.$tugProject.'/wbs?period=2026-10', $tug)->assertOk()->assertJsonPath('meta.planned_pct', '60.000')->assertJsonPath('meta.total', 18);
    }

    private function admin(): User
    {
        return User::factory()->create(['platform_admin' => true]);
    }

    private function organization(string $slug): string
    {
        $id = (string) Str::ulid();
        DB::table('organizations')->insert(['id' => $id, 'slug' => $slug, 'name' => $slug, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function project(string $organization): string
    {
        $id = (string) Str::ulid();
        DB::table('projects')->insert($this->projectFields() + ['id' => $id, 'organization_id' => $organization, 'created_at' => now(), 'updated_at' => now()]);

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

    private function membership(User $user, string $organization, string $role): void
    {
        DB::table('organization_members')->insert(['organization_id' => $organization, 'user_id' => $user->id, 'role' => $role, 'active' => true]);
    }

    private function assign(User $user, string $project): void
    {
        DB::table('project_members')->insert(['project_id' => $project, 'user_id' => $user->id]);
    }

    private function issue(User $user, string $slug): string
    {
        Auth::forgetGuards();

        return $this->postJson('/api/v1/auth/tokens', [
            'email' => $user->email, 'password' => 'password', 'organization' => $slug,
            'device_id' => 'test-device', 'device_name' => 'Perangkat tes',
        ])->assertCreated()->json('data.token');
    }

    private function api(string $path, string $token): TestResponse
    {
        Auth::forgetGuards();

        return $this->getJson($path, ['Authorization' => 'Bearer '.$token]);
    }
}
