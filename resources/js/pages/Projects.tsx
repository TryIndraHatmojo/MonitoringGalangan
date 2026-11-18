import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Project, ProjectFields, emptyProject } from '../project-fields';
import { Errors, Field, Organization, Page, Pagination, Section, Shell, formatDate, organizationUrl, projectStatus } from '../ui';

type Props = { organization: Organization; projects: Page<Project>; isAdmin: boolean; filters: { q: string; status: string } };

export default function Projects({ organization, projects, isAdmin, filters }: Props) {
    const [creating, setCreating] = useState(false);
    const url = organizationUrl(organization) + '/projects';
    const form = useForm(emptyProject);
    const search = useForm(filters);

    return <Shell title="Proyek galangan" organization={organization} actions={isAdmin && <>
        <Link className="button secondary" href={organizationUrl(organization) + '/access'}>Hak akses</Link>
        <button className="button" onClick={() => setCreating(!creating)}>{creating ? 'Tutup formulir' : 'Tambah proyek'}</button>
    </>}>
        {creating && <Section title="Proyek baru">
            <Errors errors={form.errors} />
            <form className="stack" onSubmit={(event) => { event.preventDefault(); form.post(url); }}>
                <ProjectFields data={form.data} set={form.setData} contract />
                <div><button className="button" disabled={form.processing}>Simpan proyek</button></div>
            </form>
        </Section>}
        <Section title="Daftar proyek">
            <Errors errors={search.errors} />
            <form className="grid-form mb-4 items-end" onSubmit={(event) => { event.preventDefault(); search.get(url, { preserveState: true }); }}>
                <Field label="Cari proyek"><input type="search" placeholder="Nomor, proyek, atau nama kapal" value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} /></Field>
                <Field label="Status"><select value={search.data.status} onChange={(event) => search.setData('status', event.target.value)}>
                    <option value="">Semua status</option>
                    {Object.entries(projectStatus).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                </select></Field>
                <div><button className="button secondary" disabled={search.processing}>Tampilkan</button></div>
            </form>
            {projects.data.length ? <div className="table-shell"><table>
                <thead><tr><th>Proyek</th><th>Kapal</th><th>Mulai</th><th>Target selesai</th><th>Status</th></tr></thead>
                <tbody>{projects.data.map((project) => <tr key={project.id}>
                    <td><Link href={url + '/' + project.id}>{project.name}</Link><div className="help">{project.number}</div></td>
                    <td>{project.ship_name}<div className="help">{project.ship_type}</div></td>
                    <td>{formatDate(project.start_date)}</td><td>{formatDate(project.target_date)}</td>
                    <td><span className={'pill ' + project.status}>{projectStatus[project.status]}</span></td>
                </tr>)}</tbody>
            </table></div> : <p className="empty">Tidak ada proyek yang cocok atau dapat diakses.</p>}
            <Pagination page={projects} />
        </Section>
    </Shell>;
}
