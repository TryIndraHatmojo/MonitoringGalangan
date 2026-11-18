import { Link, router, useForm } from '@inertiajs/react';
import { Errors, Field, Organization, Page, Pagination, Section, Shell, User, organizationUrl, useShared } from '../ui';

export default function Home({ accounts }: { accounts: Page<User & { active: boolean }> | null }) {
    const { auth } = useShared();
    const form = useForm({ name: '', slug: '' });

    return <Shell title="Organisasi saya">
        <Section title="Daftar organisasi">
            {auth.organizations.length ? <div className="cards">{auth.organizations.map((org: Organization) => <div className="card stack" key={org.id}>
                <h3>{org.name}</h3>
                <Link href={organizationUrl(org) + '/projects'}>Buka proyek</Link>
            </div>)}</div> : <p className="empty">Belum ada organisasi yang dapat diakses.</p>}
        </Section>
        {auth.user?.platform_admin && <Section title="Buat organisasi">
            <Errors errors={form.errors} />
            <form className="stack" onSubmit={(event) => { event.preventDefault(); form.post('/org'); }}>
                <div className="grid-form">
                    <Field label="Nama organisasi"><input required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                    <Field label="Kode URL"><input required pattern={'[a-z0-9]+(?:-[a-z0-9]+)*'} placeholder="galangan-contoh" value={form.data.slug} onChange={(event) => form.setData('slug', event.target.value)} /></Field>
                </div>
                <div><button className="button" disabled={form.processing}>Buat organisasi</button></div>
            </form>
        </Section>}
        {accounts && <Section title="Status akun">
            <div className="table-shell"><table>
                <thead><tr><th>Nama</th><th>Email</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>{accounts.data.map((account) => <tr key={account.id}>
                    <td>{account.name}{account.platform_admin ? ' · Admin pusat' : ''}</td><td>{account.email}</td>
                    <td>{account.active ? 'Aktif' : 'Nonaktif'}</td>
                    <td>{account.id !== auth.user?.id && <button className="button secondary" onClick={() => {
                        if (!account.active || window.confirm('Nonaktifkan akun dan cabut semua sesi serta token perangkatnya?')) {
                            router.patch('/accounts/' + account.id, { active: !account.active }, { preserveScroll: true });
                        }
                    }}>{account.active ? 'Nonaktifkan' : 'Aktifkan'}</button>}</td>
                </tr>)}</tbody>
            </table></div>
            <Pagination page={accounts} />
        </Section>}
    </Shell>;
}
