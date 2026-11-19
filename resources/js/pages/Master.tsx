import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table';
import { Input } from '@/components/ui/input';
import { useInertiaForm } from '@/lib/inertia-form';
import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { z } from 'zod';
import { Errors, Field, LinkButton, Organization, Page, Pagination, Section, Shell, StatusBadge, User, organizationUrl, useShared } from '../ui';

type Account = User & { active: boolean };
type Props = { organizations: Organization[] } & (
    | { section: 'users'; accounts: Page<Account> }
    | { section: 'shipyards' }
    | { section: 'roles'; roles: Record<string, string> }
);

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

export default function Master(props: Props) {
    if (props.section === 'shipyards') return <Shipyards organizations={props.organizations} />;
    if (props.section === 'users') return <Users accounts={props.accounts} organizations={props.organizations} />;

    return <Shell title="Role" destination="master_roles">
        <Section title="Daftar role">
            <DataTable data={Object.entries(props.roles).map(([code, name]) => ({ code, name }))}
                rowKey={(role) => role.code} label="Daftar role" empty="Belum ada role." columns={[
                    { accessorKey: 'name', header: 'Role' },
                    { accessorKey: 'code', header: 'Kode' },
                ]} />
        </Section>
        <ShipyardDirectory organizations={props.organizations} action="Atur izin" suffix="/access" />
    </Shell>;
}
