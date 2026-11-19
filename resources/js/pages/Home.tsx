import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { useInertiaForm } from '@/lib/inertia-form';
import { Link, router } from '@inertiajs/react';
import { ArrowRight, Building2, Plus } from 'lucide-react';
import { z } from 'zod';
import { Card } from '@/components/ui/card';
import { DataTable } from '@/components/data-table';
import { Errors, Field, Organization, Page, Pagination, Section, Shell, StatusBadge, User, organizationUrl, useShared } from '../ui';

export default function Home({ accounts, organizations }: { accounts: Page<User & { active: boolean }> | null; organizations: Organization[] }) {
    const { auth } = useShared();
    const form = useInertiaForm({ name: '', slug: '' }, z.object({
        name: z.string().refine((value) => value.trim().length > 0, 'Isi nama organisasi.').max(255),
        slug: z.string().max(100).regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, 'Gunakan huruf kecil, angka, dan tanda hubung.'),
    }));

    if (!auth.user?.platform_admin) {
        return <Shell title="Proyek Kapal"><Section>
            <p className="empty">Keanggotaan galangan sedang nonaktif. Hubungi administrator galangan untuk mengaktifkannya.</p>
        </Section></Shell>;
    }

    return <Shell title="Administrasi galangan">
        <Section title="Daftar organisasi">
            {organizations.length ? <div className="cards">{organizations.map((org) => <Card className="organization-card" key={org.id}>
                <span className="directory-icon"><Building2 size={19} aria-hidden="true" /></span>
                <h3>{org.name}</h3>
                <Link className="inline-flex w-fit items-center gap-2" href={organizationUrl(org) + '/projects'}
                    aria-label={'Buka proyek ' + org.name}>Buka proyek<ArrowRight size={15} /></Link>
            </Card>)}</div> : <p className="empty">Belum ada organisasi yang dapat diakses.</p>}
        </Section>
        <Section title="Buat organisasi">
            <Errors errors={form.errors} />
            <form className="stack" onSubmit={(event) => { event.preventDefault(); form.post('/org'); }}>
                <div className="grid-form">
                    <Field label="Nama organisasi"><Input required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                    <Field label="Kode URL"><Input required pattern={'[a-z0-9]+(?:-[a-z0-9]+)*'} placeholder="galangan-contoh" value={form.data.slug} onChange={(event) => form.setData('slug', event.target.value)} /></Field>
                </div>
                <div><Button disabled={form.processing}><Plus />Buat organisasi</Button></div>
            </form>
        </Section>
        {accounts && <Section title="Status akun">
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
        </Section>}
    </Shell>;
}
