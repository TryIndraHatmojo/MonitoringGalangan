import { Head, Link, usePage } from '@inertiajs/react';
import { Anchor, ArrowLeft, ArrowRight, Building2, CheckCircle2, CircleAlert, LayoutDashboard, ListTree, LogOut, Server, ShieldCheck, Ship, UserRound } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ProjectCombobox, type ProjectContext } from '@/components/project-combobox';

export type Organization = { id: string; name: string; slug: string };
export type User = { id: string; name: string; email: string; platform_admin: boolean };
export type Shared = { auth: { user: User | null; organization: Organization | null }; notice: string | null };
export type Page<T> = { data: T[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number };
export type AuditEvent = { id: number; action: string; actor?: string | null; reason?: string | null; target_id?: string | null; changes?: string; created_at: string };

export function useShared() {
    return usePage<Shared>().props;
}

export function organizationUrl(organization: Organization) {
    return '/org/' + organization.slug;
}

export function Shell({ title, organization, context, actions, beforeProjectChange, children }: {
    title: string;
    organization?: Organization;
    context?: ProjectContext;
    actions?: ReactNode;
    beforeProjectChange?: () => boolean;
    children: ReactNode;
}) {
    const { auth, notice } = useShared();
    const { url } = usePage();
    const currentOrg = organization ?? auth.organization;
    const orgPath = currentOrg && organizationUrl(currentOrg);
    const projectPath = orgPath && context?.active && orgPath + '/projects/' + context.active.id;
    const path = url.split('?')[0];
    const breadcrumb = context?.page === 'profile' ? 'Ringkasan proyek' : title;

    return <>
        <Head title={title} />
        <div className="app-shell">
            <aside className="sidebar">
                <Link className="brand" href="/">
                    <span className="brand-icon"><Anchor aria-hidden="true" /></span>
                    <span>Monitoring<br /><strong>Galangan</strong></span>
                </Link>
                <div className="sidebar-content">
                    <nav aria-label="Navigasi utama">
                        {auth.user?.platform_admin && <Link href="/" aria-current={path === '/' ? 'page' : undefined}><Building2 />Administrasi galangan</Link>}
                        {projectPath && <Link href={projectPath} aria-current={path === projectPath ? 'page' : undefined}><LayoutDashboard />Ringkasan proyek</Link>}
                        {orgPath && <Link href={orgPath + '/projects'} aria-current={path === orgPath + '/projects' ? 'page' : undefined}><Ship />Proyek Kapal</Link>}
                        {projectPath && context?.active?.canOpenBuilder && <Link href={projectPath + '/wbs'} aria-current={path === projectPath + '/wbs' ? 'page' : undefined}><ListTree />Form Builder</Link>}
                        {orgPath && (context?.canManageOrganization || path === orgPath + '/access') && <Link href={orgPath + '/access'} aria-current={path === orgPath + '/access' ? 'page' : undefined}><ShieldCheck />Hak akses</Link>}
                        <Link href="/devices" aria-current={path === '/devices' ? 'page' : undefined}><Server />Perangkat API</Link>
                    </nav>
                </div>
                <div className="sidebar-user">
                    <div className="sidebar-account"><span className="account-icon"><UserRound size={16} /></span><span>{auth.user?.name}</span></div>
                    <LinkButton href="/logout" method="post" as="button" variant="ghost"><LogOut />Keluar</LinkButton>
                </div>
            </aside>
            <main className="workspace-main">
                <div className="workspace-bar">
                    <p className="workspace-crumb"><span>{currentOrg?.name ?? 'Ruang kerja'}</span><strong>{breadcrumb}</strong></p>
                    {orgPath && context && <ProjectCombobox key={orgPath} base={orgPath} context={context} url={url} beforeChange={beforeProjectChange} />}
                </div>
                <div className="workspace">
                    <header className="workspace-header">
                        <h1>{title}</h1>
                        {actions && <div className="actions">{actions}</div>}
                    </header>
                    {notice && <Notice>{notice}</Notice>}
                    {children}
                </div>
            </main>
        </div>
    </>;
}

export function Section({ title, actions, children }: { title?: string; actions?: ReactNode; children: ReactNode }) {
    return <Card className="panel">
        {(title || actions) && <header className="panel-header">{title && <h2>{title}</h2>}{actions}</header>}
        {children}
    </Card>;
}

export function LinkButton({ variant, ...props }: ComponentProps<typeof Link> & { variant?: ComponentProps<typeof Button>['variant'] }) {
    return <Button asChild variant={variant}><Link {...props} /></Button>;
}

export function Notice({ children }: { children: ReactNode }) {
    return <Alert role="status" className="notice"><CheckCircle2 aria-hidden="true" /><AlertDescription>{children}</AlertDescription></Alert>;
}

export function Field({ label, children }: { label: string; children: ReactNode }) {
    return <label className="field"><span>{label}</span>{children}</label>;
}

export function Errors({ errors }: { errors: Record<string, unknown> }) {
    const messages = Object.values(errors).flat().filter((message): message is string => typeof message === 'string');
    if (!messages.length) return null;
    return <Alert variant="destructive" className="errors">
        <CircleAlert aria-hidden="true" />
        <AlertDescription><ul className="list-inside list-disc">{[...new Set(messages)].map((message) => <li key={message}>{message}</li>)}</ul></AlertDescription>
    </Alert>;
}

export function Pagination<T>({ page }: { page: Page<T> }) {
    if (page.last_page < 2) return null;
    return <nav aria-label="Paginasi" className="pagination">
        {page.prev_page_url && <LinkButton variant="outline" href={page.prev_page_url}><ArrowLeft />Sebelumnya</LinkButton>}
        <span>Halaman {page.current_page} dari {page.last_page}</span>
        {page.next_page_url && <LinkButton variant="outline" href={page.next_page_url}>Berikutnya<ArrowRight /></LinkButton>}
    </nav>;
}

export const projectStatus: Record<string, string> = { active: 'Aktif', closed: 'Ditutup', archived: 'Diarsipkan' };
export const revisionStatus: Record<string, string> = { draft: 'Draf', submitted: 'Diajukan', published: 'Terbit' };

export function StatusBadge({ status, children }: { status: string; children?: ReactNode }) {
    return <Badge variant="secondary" data-status={status} className="status-badge">
        <span className="status-dot" />
        {children ?? projectStatus[status] ?? revisionStatus[status] ?? status}
    </Badge>;
}

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
        'wbs.readers_changed': 'Pembaca Form Builder diperbarui', 'baseline.cutoff_changed': 'Cutoff diperbarui',
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
