<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShipyardController extends Controller
{
    public function __construct(private readonly Access $access) {}

    public function index(Request $request): Response
    {
        $this->access->requirePlatform($request->user(), 'shipyards.manage');

        return Inertia::render('Master', [
            'section' => 'shipyards',
            'organizations' => $this->access->organizations(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->access->requirePlatform($request->user(), 'shipyards.manage');
        $data = $this->validateShipyard($request);
        $id = (string) Str::ulid();
        try {
            DB::transaction(function () use ($request, $data, $id): void {
                DB::table('galangantbls')->insert($data + ['id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                Audit::record($request->user(), 'organization.created', $id, target: $id, changes: $data);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Kode URL galangan sudah digunakan.']);
        }

        return redirect()->route('master.shipyards')->with('notice', 'Galangan dibuat.');
    }

    public function update(Request $request, string $shipyard): RedirectResponse
    {
        $this->access->requirePlatform($request->user(), 'shipyards.manage');
        $data = $this->validateShipyard($request, $shipyard);

        try {
            DB::transaction(function () use ($request, $shipyard, $data): void {
                $record = $this->access->lockOrganization($shipyard);
                $before = ['name' => $record->name, 'slug' => $record->slug];
                DB::table('galangantbls')->where('id', $record->id)->update($data + ['updated_at' => now()]);
                Audit::record($request->user(), 'organization.updated', $record->id, target: $record->id, changes: [
                    'before' => $before, 'after' => $data,
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Kode URL galangan sudah digunakan.']);
        }

        return redirect()->route('master.shipyards')->with('notice', 'Galangan diperbarui.');
    }

    public function destroy(Request $request, string $shipyard): RedirectResponse
    {
        $this->access->requirePlatform($request->user(), 'shipyards.manage');

        DB::transaction(function () use ($request, $shipyard): void {
            $record = $this->access->lockOrganization($shipyard);
            $shipyard = $record->id;
            if (DB::table('projects')->where('galangan_id', $shipyard)->exists()
                || User::query()->where('galangan_id', $shipyard)->exists()
                || DB::table('invitations')->where('galangan_id', $shipyard)->whereNull('accepted_at')->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['galangan' => 'Galangan masih memiliki proyek, user, atau undangan aktif.']);
            }

            DB::table('personal_access_tokens')->where('galangan_id', $shipyard)->delete();
            DB::table('galangantbls')->where('id', $shipyard)->update(['dihapus_pada' => now(), 'updated_at' => now()]);
            Audit::record($request->user(), 'organization.deleted', $shipyard, target: $shipyard, changes: [
                'before' => ['name' => $record->name, 'slug' => $record->slug],
            ]);
        }, 3);

        return redirect()->route('master.shipyards')->with('notice', 'Galangan dihapus.');
    }

    private function validateShipyard(Request $request, ?string $id = null): array
    {
        $unique = Rule::unique('galangantbls', 'slug');
        if ($id !== null) {
            $unique->ignore($id);
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $unique],
        ], ['slug.unique' => 'Kode URL galangan sudah digunakan.']);
    }
}
