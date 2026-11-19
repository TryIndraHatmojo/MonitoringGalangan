import { router } from '@inertiajs/react';
import { ChevronDown, Ship } from 'lucide-react';
import { Popover } from 'radix-ui';
import { useEffect, useId, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Organization } from '@/ui';

type ProjectOption = { id: string; ship_name: string; number: string; organization_name: string };
type Options = { data: ProjectOption[]; next_page_url: string | null };
export type ActiveProject = {
    id: string;
    ship_name: string;
    number: string;
    organization: Organization;
    canOpenBuilder: boolean;
};
export type ProjectDestination = 'dashboard' | 'projects' | 'profile' | 'builder' | 'devices'
    | 'master_users' | 'master_shipyards' | 'master_roles' | 'org_users' | 'org_roles';

export function ProjectCombobox({ active, destination, organization, url, beforeChange }: {
    active: ActiveProject | null;
    destination: ProjectDestination;
    organization?: Organization;
    url: string;
    beforeChange?: () => boolean;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const [highlight, setHighlight] = useState(0);
    const [options, setOptions] = useState<Options | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [switching, setSwitching] = useState(false);
    const listId = useId();
    const projects = options?.data ?? [];
    const highlighted = projects[highlight];

    useEffect(() => {
        if (!open) return;

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const params = new URLSearchParams({ q: query, page: String(page) });
                const response = await fetch('/project-options?' + params, {
                    headers: { Accept: 'application/json' }, signal: controller.signal,
                });
                if (!response.ok) throw new Error();
                const result: Options = await response.json();
                if (!controller.signal.aborted) setOptions(result);
            } catch {
                if (!controller.signal.aborted) setError('Daftar proyek gagal dimuat. Coba buka kembali pemilih.');
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 150);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, page, query]);

    function loadPage(next: number) {
        setPage(next);
        setHighlight(0);
        setOptions(null);
        setError('');
        setLoading(true);
    }

    function choose(project: ProjectOption) {
        if (switching) return;
        if (project.id === active?.id) {
            const listPath = '/org/' + active.organization.slug + '/projects';
            const profilePath = listPath + '/' + active.id;
            const paths: Partial<Record<ProjectDestination, string>> = { projects: listPath, profile: profilePath, builder: profilePath + '/wbs' };
            if (!paths[destination] || url.split('?')[0] === paths[destination]) {
                setOpen(false);
                return;
            }
        }
        if (beforeChange && !beforeChange()) return;

        const params = new URLSearchParams(url.split('?')[1]);
        const data: Record<string, string> = { project_id: project.id, destination };
        const keys = destination === 'projects' ? ['q', 'status', 'page'] : destination === 'master_users' ? ['page'] : [];
        for (const key of keys) {
            const value = params.get(key);
            if (value) data[key] = value;
        }
        if (organization && (destination === 'org_users' || destination === 'org_roles')) {
            data.return_organization = organization.slug;
        }
        setError('');
        router.post('/active-project', data, {
            preserveState: destination !== 'projects' && destination !== 'profile' && destination !== 'builder',
            onStart: () => setSwitching(true),
            onSuccess: () => setOpen(false),
            onError: () => setError('Proyek gagal diganti. Muat ulang halaman dan coba lagi.'),
            onFinish: () => setSwitching(false),
        });
    }

    return <Popover.Root open={open} onOpenChange={(next) => {
        if (next) {
            setQuery('');
            loadPage(1);
        }
        setOpen(next);
    }}>
        <Popover.Trigger asChild>
            <Button variant="outline" className="project-control" aria-label={active ? 'Kapal aktif: ' + active.ship_name : 'Pilih proyek kapal'}>
                <span className="project-control-icon"><Ship aria-hidden="true" /></span>
                <span className="project-control-label"><span>Kapal aktif</span><strong>{active?.ship_name ?? 'Pilih proyek'}</strong></span>
                <ChevronDown aria-hidden="true" />
            </Button>
        </Popover.Trigger>
        <Popover.Portal>
            <Popover.Content aria-label="Ganti kapal aktif" className="project-options" align="end" sideOffset={6} collisionPadding={12}>
                <Input type="search" autoComplete="off" maxLength={100} placeholder="Cari kapal atau nomor proyek" aria-label="Cari kapal atau nomor proyek"
                    role="combobox" aria-expanded={open} aria-autocomplete="list" aria-controls={listId}
                    aria-activedescendant={highlighted ? listId + '-' + highlighted.id : undefined}
                    value={query} onChange={(event) => {
                        if (query === event.target.value) return;
                        setQuery(event.target.value);
                        loadPage(1);
                    }} onKeyDown={(event) => {
                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                            event.preventDefault();
                            const step = event.key === 'ArrowDown' ? 1 : -1;
                            const next = Math.max(0, Math.min(highlight + step, projects.length - 1));
                            setHighlight(next);
                            const option = projects[next];
                            if (option) document.getElementById(listId + '-' + option.id)?.scrollIntoView({ block: 'nearest' });
                        } else if (event.key === 'Enter' && highlighted) {
                            event.preventDefault();
                            choose(highlighted);
                        }
                    }} />
                {error && <p className="mt-2 text-sm text-destructive" role="alert">{error}</p>}
                {loading && <p className="help px-2 py-3" role="status">Memuat proyek…</p>}
                {!loading && options && !projects.length && <p className="help px-2 py-3" role="status">
                    {query.trim() ? 'Proyek tidak ditemukan.' : 'Belum ada proyek yang dapat Anda akses.'}
                </p>}
                <ul id={listId} role="listbox" aria-label="Proyek yang dapat dibuka" className="project-option-list">
                    {projects.map((project, index) => <li key={project.id} id={listId + '-' + project.id} role="option"
                        aria-selected={project.id === active?.id} data-highlighted={index === highlight}
                        onMouseEnter={() => setHighlight(index)} onMouseDown={(event) => event.preventDefault()}
                        onClick={() => choose(project)}>
                        <strong>{project.ship_name}</strong><span>{project.number} · {project.organization_name}</span>
                    </li>)}
                </ul>
                {!loading && (page > 1 || options?.next_page_url) && <div className="project-options-page">
                    <Button type="button" variant="outline" size="sm" disabled={page === 1} onClick={() => loadPage(page - 1)}>Sebelumnya</Button>
                    <Button type="button" variant="outline" size="sm" disabled={!options?.next_page_url} onClick={() => loadPage(page + 1)}>Berikutnya</Button>
                </div>}
            </Popover.Content>
        </Popover.Portal>
    </Popover.Root>;
}
