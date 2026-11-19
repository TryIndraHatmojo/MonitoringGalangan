import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useInertiaForm } from '@/lib/inertia-form';
import { emailAddress, optionalId } from '@/lib/validation';
import type { Errors as VisitErrors } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useId, useState } from 'react';
import type { ReactNode } from 'react';
import { z } from 'zod';
import { Errors, Field, LinkButton, Organization, Page, Pagination, Section, Shell, StatusBadge, User, organizationUrl, useShared } from '../ui';

type Account = User & { active: boolean; kode_role: string; galangan_id: string | null };
type Role = { code: string; name: string };
type Props = { organizations: Organization[] } & (
    | { section: 'users'; accounts: Page<Account>; roles: Record<string, string> }
    | { section: 'shipyards' }
    | { section: 'roles'; roles: Record<string, string>; builtInRoles: string[] }
);

const rolesUrl = '/data-master/role';
const usersUrl = '/data-master/user';
const shipyardsUrl = '/data-master/galangan';
const roleCode = /^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/;
const roleCodeHelp = 'Awali kode dengan huruf kecil. Gunakan huruf kecil, angka, dan garis bawah.';

function useMasterRemoval() {
    const [errors, setErrors] = useState<VisitErrors>({});
    const [processing, setProcessing] = useState(false);
    const errorBag = useId();

    function remove(url: string, message: string) {
        if (!window.confirm(message)) return;
        setErrors({});
        router.delete(url, {
            errorBag,
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onError: setErrors,
            onFinish: () => setProcessing(false),
        });
    }

    return { errors, processing, remove };
}

function ShipyardTable({ organizations, actions }: { organizations: Organization[]; actions: (organization: Organization) => ReactNode }) {
    return <DataTable data={organizations} rowKey={(organization) => organization.id} label="Daftar galangan"
        empty="Belum ada galangan." columns={[
            { accessorKey: 'name', header: 'Galangan' },
            { accessorKey: 'slug', header: 'Kode URL' },
            { id: 'actions', header: 'Aksi', cell: ({ row }) => actions(row.original) },
        ]} />;
}

function ShipyardDirectory({ organizations, action, suffix }: { organizations: Organization[]; action: string; suffix: string }) {
    return <Section title="Daftar galangan">
        <ShipyardTable organizations={organizations} actions={(organization) =>
            <LinkButton variant="outline" href={organizationUrl(organization) + suffix}
                aria-label={action + ' ' + organization.name}>{action}</LinkButton>} />
    </Section>;
}

function ShipyardForm({ shipyard, onClose }: { shipyard?: Organization; onClose: () => void }) {
    const form = useInertiaForm({ name: shipyard?.name ?? '', slug: shipyard?.slug ?? '' }, z.object({
        name: z.string().refine((value) => value.trim().length > 0, 'Isi nama galangan.').max(255),
        slug: z.string().max(100).regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, 'Gunakan huruf kecil, angka, dan tanda hubung.'),
    }));

    return <Section title={shipyard ? 'Ubah galangan' : 'Tambah galangan'}>
        <Errors errors={form.errors} />
        <form className="stack" onSubmit={(event) => {
            event.preventDefault();
            const options = { preserveScroll: true, onSuccess: onClose };
            if (shipyard) form.patch(shipyardsUrl + '/' + shipyard.id, options);
            else form.post('/org', options);
        }}>
            <div className="grid-form">
                <Field label="Nama galangan"><Input autoFocus required maxLength={255} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                <Field label="Kode URL"><Input required maxLength={100} pattern={'[a-z0-9]+(?:-[a-z0-9]+)*'} placeholder="galangan-contoh"
                    value={form.data.slug} onChange={(event) => form.setData('slug', event.target.value)} /></Field>
            </div>
            <div className="actions">
                <Button disabled={form.processing || (Boolean(shipyard) && !form.isDirty)}>{shipyard ? 'Simpan perubahan' : 'Tambah galangan'}</Button>
                <Button type="button" variant="outline" disabled={form.processing} onClick={onClose}>Batal</Button>
            </div>
        </form>
    </Section>;
}

function Shipyards({ organizations }: { organizations: Organization[] }) {
    const [editor, setEditor] = useState<Organization | 'new' | null>(null);
    const deletion = useMasterRemoval();
    const edited = editor && editor !== 'new' ? editor : undefined;

    return <Shell title="Galangan" destination="master_shipyards">
        {editor && <ShipyardForm key={edited?.id ?? 'new'} shipyard={edited} onClose={() => setEditor(null)} />}
        <Section title="Daftar galangan" actions={
            <Button type="button" disabled={deletion.processing} onClick={() => setEditor('new')}><Plus />Tambah galangan</Button>
        }>
            <Errors errors={deletion.errors} />
            <ShipyardTable organizations={organizations} actions={(organization) => <div className="actions">
                <LinkButton variant="outline" href={organizationUrl(organization) + '/projects'}
                    aria-label={'Kelola proyek ' + organization.name}>Kelola proyek</LinkButton>
                <Button type="button" variant="outline" disabled={deletion.processing} aria-label={'Ubah galangan ' + organization.name}
                    onClick={() => setEditor(organization)}>Ubah</Button>
                <Button type="button" variant="destructive" disabled={deletion.processing || edited?.id === organization.id}
                    aria-label={'Hapus galangan ' + organization.name}
                    onClick={() => deletion.remove(shipyardsUrl + '/' + organization.id, `Hapus galangan ${organization.name}?`)}>Hapus</Button>
            </div>} />
        </Section>
    </Shell>;
}

function AccountForm({ account, isSelf, organizations, roles, onClose }: {
    account?: Account; isSelf: boolean; organizations: Organization[]; roles: Record<string, string>; onClose: () => void;
}) {
    const options = Object.entries(roles).filter(([code]) => !account || account.platform_admin === (code === 'platform_admin'));
    const form = useInertiaForm({
        name: account?.name ?? '', email: account?.email ?? '', role: account?.kode_role ?? 'viewer',
        galangan_id: account?.galangan_id ?? '', active: account?.active ?? true, password: '', password_confirmation: '',
    }, z.object({
        name: z.string().trim().min(1, 'Isi nama lengkap.').max(255),
        email: emailAddress,
        role: z.string().refine((code) => options.some(([role]) => role === code), 'Pilih role yang tersedia.'),
        galangan_id: optionalId,
        active: z.boolean(),
        password: z.string().refine((value) => (Boolean(account) && value === '') ||
            ([...value].length >= 12 && new TextEncoder().encode(value).length <= 72 && !value.includes('\0')),
        'Gunakan kata sandi minimal 12 karakter dan maksimal 72 byte.'),
        password_confirmation: z.string(),
    }).refine(({ role, galangan_id }) => role === 'platform_admin' ? !galangan_id : Boolean(galangan_id), {
        path: ['galangan_id'], message: 'Pilih galangan untuk user ini.',
    }).refine(({ password, password_confirmation }) => password === password_confirmation, {
        path: ['password_confirmation'], message: 'Konfirmasi kata sandi berbeda.',
    }));

    return <Section title={account ? 'Ubah user' : 'Tambah user'}>
        <Errors errors={form.errors} />
        <form className="stack" onSubmit={(event) => {
            event.preventDefault();
            const visit = { preserveScroll: true, onSuccess: onClose, onFinish: () => form.reset('password', 'password_confirmation') };
            if (account) form.patch(usersUrl + '/' + account.id, visit);
            else form.post(usersUrl, visit);
        }}>
            <div className="grid-form">
                <Field label="Nama lengkap"><Input autoFocus required maxLength={255} value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)} /></Field>
                <Field label="Email"><Input type="email" required maxLength={255} value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)} /></Field>
                <Field label="Role"><NativeSelect value={form.data.role} disabled={account?.platform_admin} onChange={(event) => {
                    form.setData('role', event.target.value);
                    if (event.target.value === 'platform_admin') form.setData('galangan_id', '');
                }}>{options.map(([code, label]) => <option key={code} value={code}>{label}</option>)}</NativeSelect></Field>
                {form.data.role !== 'platform_admin' && <Field label="Galangan"><NativeSelect value={form.data.galangan_id} required
                    disabled={Boolean(account)} onChange={(event) => form.setData('galangan_id', event.target.value)}>
                    <option value="">Pilih galangan</option>
                    {organizations.map((organization) => <option key={organization.id} value={organization.id}>{organization.name}</option>)}
                </NativeSelect></Field>}
                <Field label={account ? 'Kata sandi baru (opsional)' : 'Kata sandi'}><Input type="password" autoComplete="new-password"
                    required={!account} minLength={12} value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} /></Field>
                <Field label="Konfirmasi kata sandi"><Input type="password" autoComplete="new-password" required={!account || Boolean(form.data.password)}
                    value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} /></Field>
            </div>
            <label className="check"><input type="checkbox" checked={form.data.active} disabled={isSelf}
                onChange={(event) => form.setData('active', event.target.checked)} /> Akun aktif</label>
            <div className="actions">
                <Button disabled={form.processing || (Boolean(account) && !form.isDirty)}>{account ? 'Simpan perubahan' : 'Tambah user'}</Button>
                <Button type="button" variant="outline" disabled={form.processing} onClick={onClose}>Batal</Button>
            </div>
        </form>
    </Section>;
}

function Users({ accounts, organizations, roles }: Extract<Props, { section: 'users' }>) {
    const { auth } = useShared();
    const [editor, setEditor] = useState<Account | 'new' | null>(null);
    const deletion = useMasterRemoval();
    const edited = editor && editor !== 'new' ? editor : undefined;
    const shipyards = new Map(organizations.map((organization) => [organization.id, organization.name]));

    return <Shell title="User" destination="master_users">
        {editor && <AccountForm key={edited?.id ?? 'new'} account={edited} isSelf={Boolean(edited && edited.id === auth.user?.id)}
            organizations={organizations} roles={roles} onClose={() => setEditor(null)} />}
        <Section title="Daftar user" actions={
            <Button type="button" disabled={deletion.processing} onClick={() => setEditor('new')}><Plus />Tambah user</Button>
        }>
            <Errors errors={deletion.errors} />
            <DataTable data={accounts.data} rowKey={(account) => account.id} label="Daftar user" empty="Belum ada akun." columns={[
                { accessorKey: 'name', header: 'Nama' },
                { accessorKey: 'email', header: 'Email' },
                { id: 'role', header: 'Role', cell: ({ row }) => roles[row.original.kode_role] ?? row.original.kode_role },
                { id: 'shipyard', header: 'Galangan', cell: ({ row }) => shipyards.get(row.original.galangan_id ?? '') ?? '—' },
                { id: 'status', header: 'Status', cell: ({ row }) => row.original.active
                    ? <StatusBadge status="active" /> : <StatusBadge status="inactive">Nonaktif</StatusBadge> },
                { id: 'actions', header: 'Aksi', cell: ({ row: { original: account } }) => <div className="actions">
                    <Button type="button" variant="outline" disabled={deletion.processing} aria-label={'Ubah user ' + account.name}
                        onClick={() => setEditor(account)}>Ubah</Button>
                    {account.id !== auth.user?.id && <>
                        <Button type="button" variant="outline" disabled={deletion.processing || edited?.id === account.id} onClick={() => {
                            if (!account.active || window.confirm('Nonaktifkan akun dan cabut semua sesi serta token perangkatnya?')) {
                                router.patch('/accounts/' + account.id, { active: !account.active }, { preserveScroll: true });
                            }
                        }}>{account.active ? 'Nonaktifkan' : 'Aktifkan'}</Button>
                        <Button type="button" variant="destructive" disabled={deletion.processing || edited?.id === account.id}
                            aria-label={'Hapus user ' + account.name} onClick={() => deletion.remove(usersUrl + '/' + account.id,
                                `Hapus user ${account.name}? Sesi dan token perangkatnya akan dicabut.`)}>Hapus</Button>
                    </>}
                </div> },
            ]} />
            <Pagination page={accounts} />
        </Section>
        <ShipyardDirectory organizations={organizations} action="Kelola anggota" suffix="/access/users" />
    </Shell>;
}

function RoleForm({ role, onClose }: { role?: Role; onClose: () => void }) {
    const form = useInertiaForm({ kode: role?.code ?? '', nama: role?.name ?? '' }, z.object({
        kode: role ? z.string() : z.string().max(40).regex(roleCode, roleCodeHelp),
        nama: z.string().trim().min(1, 'Isi nama role.').max(255),
    }));

    return <Section title={role ? 'Ubah role' : 'Tambah role'}>
        <Errors errors={form.errors} />
        <form className="stack" onSubmit={(event) => {
            event.preventDefault();
            const options = { preserveScroll: true, onSuccess: onClose };
            if (role) form.patch(rolesUrl + '/' + encodeURIComponent(role.code), options);
            else form.post(rolesUrl, options);
        }}>
            <div className="grid-form">
                <Field label="Kode role"><Input required readOnly={Boolean(role)} autoFocus={!role} maxLength={40}
                    placeholder="qc_lapangan" value={form.data.kode}
                    onChange={(event) => form.setData('kode', event.target.value)} /></Field>
                <Field label="Nama role"><Input required autoFocus={Boolean(role)} maxLength={255} value={form.data.nama}
                    onChange={(event) => form.setData('nama', event.target.value)} /></Field>
            </div>
            <div className="actions">
                <Button disabled={form.processing || (Boolean(role) && !form.isDirty)}>{role ? 'Simpan perubahan' : 'Tambah role'}</Button>
                <Button type="button" variant="outline" disabled={form.processing} onClick={onClose}>Batal</Button>
            </div>
        </form>
    </Section>;
}

function Roles({ roles, builtInRoles, organizations }: Extract<Props, { section: 'roles' }>) {
    const [editor, setEditor] = useState<Role | 'new' | null>(null);
    const deletion = useMasterRemoval();

    const editedRole = editor && editor !== 'new' ? editor : undefined;
    return <Shell title="Role" destination="master_roles">
        {editor && <RoleForm key={editedRole ? 'edit-' + editedRole.code : 'new'} role={editedRole} onClose={() => setEditor(null)} />}
        <Section title="Daftar role" actions={
            <Button type="button" disabled={deletion.processing} onClick={() => setEditor('new')}><Plus />Tambah role</Button>
        }>
            <Errors errors={deletion.errors} />
            <DataTable data={Object.entries(roles).map(([code, name]) => ({ code, name }))}
                rowKey={(role) => role.code} label="Daftar role" empty="Belum ada role." columns={[
                    { accessorKey: 'name', header: 'Role' },
                    { accessorKey: 'code', header: 'Kode' },
                    { id: 'actions', header: 'Aksi', cell: ({ row: { original: role } }) => <div className="actions">
                        <Button type="button" variant="outline" aria-label={'Ubah role ' + role.name} disabled={deletion.processing}
                            onClick={() => setEditor(role)}>Ubah</Button>
                        {builtInRoles.includes(role.code)
                            ? <span className="help">Role bawaan</span>
                            : <Button type="button" variant="destructive" aria-label={'Hapus role ' + role.name}
                                disabled={deletion.processing || editedRole?.code === role.code} onClick={() => deletion.remove(
                                    rolesUrl + '/' + encodeURIComponent(role.code),
                                    `Hapus role ${role.name}? Pengaturan izinnya di setiap galangan akan ikut dihapus.`)}>Hapus</Button>}
                    </div> },
                ]} />
        </Section>
        <ShipyardDirectory organizations={organizations} action="Atur izin" suffix="/access" />
    </Shell>;
}

export default function Master(props: Props) {
    if (props.section === 'shipyards') return <Shipyards organizations={props.organizations} />;
    if (props.section === 'users') return <Users {...props} />;

    return <Roles {...props} />;
}
