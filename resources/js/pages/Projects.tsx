import { Link } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Button } from '@/components/ui/button';
import { useInertiaForm } from '@/lib/inertia-form';
import { useState } from 'react';
import { Project, ProjectFields, emptyProject, projectSchema } from '../project-fields';
import { Plus, Search, Ship, X } from 'lucide-react';
import { z } from 'zod';
import { Card } from '@/components/ui/card';
import { Errors, Field, Organization, Page, Pagination, Section, Shell, StatusBadge, formatDate, organizationUrl, projectStatus } from '../ui';

type Props = { organization: Organization; projects: Page<Project>; canCreate: boolean; filters: { q: string; status: string } };

export default function Projects({ organization, projects, canCreate, filters }: Props) {
    const [creating, setCreating] = useState(false);
    const url = organizationUrl(organization) + '/projects';
    const form = useInertiaForm(emptyProject, projectSchema(true));
    const search = useInertiaForm(filters, z.object({ q: z.string().max(100, 'Pencarian maksimal 100 karakter.'), status: z.string() }));

    return <Shell title="Proyek Kapal" organization={organization} destination="projects" actions={canCreate &&
        <Button aria-expanded={creating} onClick={() => setCreating(!creating)}>{creating ? <X /> : <Plus />}{creating ? 'Tutup formulir' : 'Proyek baru'}</Button>
    }>
        {creating && <Section title="Proyek baru">
            <Errors errors={form.errors} />
            <form className="stack" onSubmit={(event) => { event.preventDefault(); form.post(url); }}>
                <ProjectFields data={form.data} set={form.setData} contract />
                <div><Button disabled={form.processing}>Simpan proyek</Button></div>
            </form>
        </Section>}
        <Section>
            <Errors errors={search.errors} />
            <form className="project-filters" onSubmit={(event) => { event.preventDefault(); search.get(url, { preserveState: true }); }}>
                <Field label="Cari proyek"><Input type="search" placeholder="Nomor, proyek, atau nama kapal" value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} /></Field>
                <Field label="Status"><NativeSelect value={search.data.status} onChange={(event) => search.setData('status', event.target.value)}>
                    <option value="">Semua status</option>
                    {Object.entries(projectStatus).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                </NativeSelect></Field>
                <div><Button variant="outline" disabled={search.processing}><Search />Tampilkan</Button></div>
            </form>
        </Section>
        {projects.data.length === 0 && <p className="empty">{filters.q || filters.status
            ? 'Tidak ada proyek yang cocok dengan filter.' : 'Belum ada proyek yang dapat Anda akses.'}</p>}
        <div className="project-cards">
            {projects.data.map((project) => <Card key={project.id} className="project-card">
                    <div className="project-card-top">
                        <span className="project-card-icon"><Ship aria-hidden="true" /></span>
                        <StatusBadge status={project.status} />
                    </div>
                    <div>
                        <h2><Link href={url + '/' + project.id} className="hover:text-primary hover:underline">{project.ship_name}</Link></h2>
                        <p className="help">{project.ship_type} · {project.number}</p>
                        {project.name !== project.ship_name && <p className="mt-1 text-sm">{project.name}</p>}
                    </div>
                    <dl className="project-card-facts">
                        <div><dt>Galangan</dt><dd>{project.shipyard}</dd></div>
                        <div><dt>Mulai</dt><dd>{formatDate(project.start_date)}</dd></div>
                        <div><dt>Target selesai</dt><dd>{formatDate(project.target_date)}</dd></div>
                    </dl>
                </Card>)}
        </div>
        <Pagination page={projects} />
    </Shell>;
}
