import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

export type Organization = { id: string; name: string; slug: string };
export type User = { id: string; name: string; email: string; platform_admin: boolean };
export type Shared = { auth: { user: User | null; organizations: Organization[] }; notice: string | null };
export type Page<T> = { data: T[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number };
export type AuditEvent = { id: number; action: string; actor?: string | null; reason?: string | null; target_id?: string | null; changes?: string; created_at: string };

export function useShared() {
    return usePage<Shared>().props;
}

export function organizationUrl(organization: Organization) {
    return '/org/' + organization.slug;
}

export function Shell({ title, organization, actions, children }: { title: string; organization?: Organization; actions?: ReactNode; children: ReactNode }) {
    const { auth, notice } = useShared();
    const orgPath = organization && organizationUrl(organization);

    return <>
        <Head title={title} />
        <div className="app-shell">
            <aside className="sidebar">
                <Link className="brand" href="/">
                    <span className="brand-icon" aria-hidden="true">MG</span>
                    <span>Monitoring<br /><strong>Galangan</strong></span>
                </Link>
                <div className="sidebar-content">
                    {auth.organizations.length > 0 && <label className="organization-switch">
                        <span>Organisasi</span>
                        <select aria-label="Organisasi aktif" value={organization?.slug ?? ''} onChange={(event) => router.visit('/org/' + event.target.value + '/projects')}>
                            <option value="" disabled>Pilih organisasi</option>
                            {auth.organizations.map((org) => <option key={org.id} value={org.slug}>{org.name}</option>)}
                        </select>
                    </label>}
                    <nav aria-label="Navigasi utama">
                        <Link href="/">Organisasi saya</Link>
                        {orgPath && <Link href={orgPath + '/projects'}>Proyek</Link>}
                    </nav>
                </div>
                <div className="sidebar-user">
                    <span>{auth.user?.name}</span>
                    <Link href="/logout" method="post" as="button">Keluar</Link>
                </div>
            </aside>
            <main className="workspace">
                <header className="workspace-header">
                    <div>
                        {organization && <p className="context">{organization.name}</p>}
                        <h1>{title}</h1>
                    </div>
                    {actions && <div className="actions">{actions}</div>}
                </header>
                {notice && <p role="status" className="notice">{notice}</p>}
                {children}
            </main>
        </div>
    </>;
}

export function Section({ title, actions, children }: { title?: string; actions?: ReactNode; children: ReactNode }) {
    return <section className="panel">
        {(title || actions) && <header className="panel-header">{title && <h2>{title}</h2>}{actions}</header>}
        {children}
    </section>;
}

export function Field({ label, children }: { label: string; children: ReactNode }) {
    return <label className="field"><span>{label}</span>{children}</label>;
}

export function Errors({ errors }: { errors: Record<string, unknown> }) {
    const messages = Object.values(errors).flat().filter((message): message is string => typeof message === 'string');
    if (!messages.length) return null;
    return <ul className="errors" role="alert">{[...new Set(messages)].map((message) => <li key={message}>{message}</li>)}</ul>;
}

export function Pagination<T>({ page }: { page: Page<T> }) {
    if (page.last_page < 2) return null;
    return <nav aria-label="Paginasi" className="pagination">
        {page.prev_page_url && <Link className="button secondary" href={page.prev_page_url}>Sebelumnya</Link>}
        <span>Halaman {page.current_page} dari {page.last_page}</span>
        {page.next_page_url && <Link className="button secondary" href={page.next_page_url}>Berikutnya</Link>}
    </nav>;
}

export const projectStatus: Record<string, string> = { active: 'Aktif', closed: 'Ditutup', archived: 'Diarsipkan' };
export const revisionStatus: Record<string, string> = { draft: 'Draf', submitted: 'Diajukan', published: 'Terbit' };

export function formatNumber(value: string | number | null | undefined, digits = 3) {
    if (value === null || value === undefined || value === '') return '—';
    const formatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: digits });
    if (typeof value === 'string' && /^\d+(?:\.\d+)?$/.test(value)) {
        const [whole, fraction] = value.split('.');
        const suffix = fraction?.replace(/0+$/, '') ?? '';
        if (suffix.length <= digits) return formatter.format(BigInt(whole)) + (suffix ? ',' + suffix : '');
    }
    return formatter.format(Number(value));
}

export function formatDate(value: string | null | undefined) {
    if (!value) return '—';
    const date = new Date(value.length === 10 ? value + 'T00:00:00+07:00' : value.replace(' ', 'T') + (value.includes(' ') ? 'Z' : ''));
    return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(date);
}

export function AuditList({ entries, showChanges = false }: { entries: AuditEvent[]; showChanges?: boolean }) {
    if (!entries.length) return <p className="empty">Belum ada perubahan tercatat.</p>;
    const labels: Record<string, string> = {
        'organization.created': 'Organisasi dibuat', 'organization.permissions_changed': 'Hak akses diperbarui',
        'organization.invited': 'Undangan dibuat', 'organization.invitation_accepted': 'Undangan diterima',
        'organization.membership_changed': 'Keanggotaan diperbarui', 'project.created': 'Proyek dibuat',
        'project.updated': 'Proyek diperbarui', 'project.assignments_changed': 'Penugasan diperbarui',
        'project.document_uploaded': 'Dokumen diunggah', 'baseline.draft_saved': 'Draf baseline disimpan',
        'baseline.template_copied': 'Template baseline disalin', 'baseline.submit': 'Baseline diajukan',
        'baseline.return': 'Baseline dikembalikan', 'baseline.publish': 'Baseline diterbitkan',
        'wbs.readers_changed': 'Pembaca WBS diperbarui', 'baseline.cutoff_changed': 'Cutoff diperbarui',
        'account.device_token_issued': 'Token perangkat diterbitkan', 'account.device_token_revoked': 'Token perangkat dicabut',
    };
    return <ul className="audit-list">
        {entries.map((entry) => <li key={entry.id}>
            <div><strong>{labels[entry.action] ?? entry.action}</strong><span>{entry.actor ?? 'Sistem'} · {formatDate(entry.created_at)}</span></div>
            {entry.reason && <p>{entry.reason}</p>}
            {showChanges && entry.changes && entry.changes !== '[]' && <details><summary>Rincian perubahan</summary><pre>{JSON.stringify(JSON.parse(entry.changes), null, 2)}</pre></details>}
        </li>)}
    </ul>;
}
