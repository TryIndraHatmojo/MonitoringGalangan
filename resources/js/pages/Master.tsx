import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table';
import { Input } from '@/components/ui/input';
import { useInertiaForm } from '@/lib/inertia-form';
import type { Errors as VisitErrors } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useId, useState } from 'react';
import { z } from 'zod';
import { Errors, Field, LinkButton, Organization, Page, Pagination, Section, Shell, StatusBadge, User, organizationUrl, useShared } from '../ui';

type Account = User & { active: boolean };
type Role = { code: string; name: string };
type Props = { organizations: Organization[] } & (
    | { section: 'users'; accounts: Page<Account> }
    | { section: 'shipyards' }
    | { section: 'roles'; roles: Record<string, string>; builtInRoles: string[] }
);

const rolesUrl = '/data-master/role';
const roleCode = /^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/;
const roleCodeHelp = 'Awali kode dengan huruf kecil. Gunakan huruf kecil, angka, dan garis bawah.';

function ShipyardDirectory({ organizations, action, suffix }: {
    organizations: Organization[];
    action: string;
    suffix: string;
}) {
    return <Section title="Daftar galangan">
        <DataTable data={organizations} rowKey={(organization) => organization.id} label="Daftar galangan"
            empty="Belum ada galangan." columns={[
                { accessorKey: 'name', header: 'Galangan' },
                { accessorKey: 'slug', header: 'Kode URL' },
                { id: 'actions', header: 'Aksi', cell: ({ row: { original: organization } }) =>
                    <LinkButton variant="outline" href={organizationUrl(organization) + suffix}
                        aria-label={action + ' ' + organization.name}>{action}</LinkButton> },
            ]} />
    </Section>;
}

function Shipyards({ organizations }: { organizations: Organization[] }) {
    const form = useInertiaForm({ name: '', slug: '' }, z.object({
        name: z.string().refine((value) => value.trim().length > 0, 'Isi nama galangan.').max(255),
        slug: z.string().max(100).regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, 'Gunakan huruf kecil, angka, dan tanda hubung.'),
    }));

    return <Shell title="Galangan" destination="master_shipyards">
        <ShipyardDirectory organizations={organizations} action="Kelola proyek" suffix="/projects" />
        <Section title="Tambah galangan">
            <Errors errors={form.errors} />
            <form className="stack" onSubmit={(event) => {
                event.preventDefault();
                form.post('/org', { preserveScroll: true, onSuccess: () => form.reset() });
            }}>
                <div className="grid-form">
                    <Field label="Nama galangan"><Input required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                    <Field label="Kode URL"><Input required pattern={'[a-z0-9]+(?:-[a-z0-9]+)*'} placeholder="galangan-contoh" value={form.data.slug} onChange={(event) => form.setData('slug', event.target.value)} /></Field>
                </div>
                <div><Button disabled={form.processing}><Plus />Tambah galangan</Button></div>
            </form>
        </Section>
    </Shell>;
}

function Users({ accounts, organizations }: { accounts: Page<Account>; organizations: Organization[] }) {
    const { auth } = useShared();

    return <Shell title="User" destination="master_users">
        <Section title="Status akun">
            <DataTable data={accounts.data} rowKey={(account) => account.id} label="Status akun" empty="Belum ada akun." columns={[
                { id: 'name', header: 'Nama', cell: ({ row }) => row.original.name + (row.original.platform_admin ? ' · Admin pusat' : '') },
                { accessorKey: 'email', header: 'Email' },
                { id: 'status', header: 'Status', cell: ({ row }) => row.original.active
                    ? <StatusBadge status="active" /> : <StatusBadge status="inactive">Nonaktif</StatusBadge> },
                { id: 'actions', header: 'Aksi', cell: ({ row: { original: account } }) => account.id !== auth.user?.id &&
                    <Button variant="outline" onClick={() => {
                        if (!account.active || window.confirm('Nonaktifkan akun dan cabut semua sesi serta token perangkatnya?')) {
                            router.patch('/accounts/' + account.id, { active: !account.active }, { preserveScroll: true });
                        }
                    }}>{account.active ? 'Nonaktifkan' : 'Aktifkan'}</Button> },
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
    const [deleteErrors, setDeleteErrors] = useState<VisitErrors>({});
    const [deleting, setDeleting] = useState(false);
    const deleteBag = useId();

    function deleteRole(role: Role) {
        if (!window.confirm(`Hapus role ${role.name}? Pengaturan izinnya di setiap galangan akan ikut dihapus.`)) return;
        setDeleteErrors({});
        router.delete(rolesUrl + '/' + encodeURIComponent(role.code), {
            errorBag: deleteBag,
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onError: setDeleteErrors,
            onFinish: () => setDeleting(false),
        });
    }

    const editedRole = editor && editor !== 'new' ? editor : undefined;
    return <Shell title="Role" destination="master_roles">
        {editor && <RoleForm key={editedRole ? 'edit-' + editedRole.code : 'new'} role={editedRole} onClose={() => setEditor(null)} />}
        <Section title="Daftar role" actions={
            <Button type="button" disabled={deleting} onClick={() => setEditor('new')}><Plus />Tambah role</Button>
        }>
            <Errors errors={deleteErrors} />
            <DataTable data={Object.entries(roles).map(([code, name]) => ({ code, name }))}
                rowKey={(role) => role.code} label="Daftar role" empty="Belum ada role." columns={[
                    { accessorKey: 'name', header: 'Role' },
                    { accessorKey: 'code', header: 'Kode' },
                    { id: 'actions', header: 'Aksi', cell: ({ row: { original: role } }) => <div className="actions">
                        <Button type="button" variant="outline" aria-label={'Ubah role ' + role.name} disabled={deleting}
                            onClick={() => setEditor(role)}>Ubah</Button>
                        {builtInRoles.includes(role.code)
                            ? <span className="help">Role bawaan</span>
                            : <Button type="button" variant="destructive" aria-label={'Hapus role ' + role.name}
                                disabled={deleting || editedRole?.code === role.code} onClick={() => deleteRole(role)}>Hapus</Button>}
                    </div> },
                ]} />
        </Section>
        <ShipyardDirectory organizations={organizations} action="Atur izin" suffix="/access" />
    </Shell>;
}

export default function Master(props: Props) {
    if (props.section === 'shipyards') return <Shipyards organizations={props.organizations} />;
    if (props.section === 'users') return <Users accounts={props.accounts} organizations={props.organizations} />;

    return <Roles {...props} />;
}
