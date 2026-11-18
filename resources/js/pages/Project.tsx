import { Link, useForm } from '@inertiajs/react';
import { AuditEvent, AuditList, Errors, Field, Organization, Section, Shell, formatDate, formatNumber, organizationUrl, projectStatus } from '../ui';
import { Project, ProjectFields, emptyProject } from '../project-fields';

type Member = { id: string; name: string; role: string };
type Assignment = { user_id: string; is_pic: boolean };
type Document = { id: string; title: string; filename: string; size: number; created_at: string };
type Props = {
    organization: Organization; project: Project; can: Record<string, boolean>; members: Member[];
    assignments: Assignment[]; documents: Document[]; audit: AuditEvent[];
};

function Assignments({ members, assignments, project, url }: Pick<Props, 'members' | 'assignments' | 'project'> & { url: string }) {
    const form = useForm({
        members: assignments.filter((member) => members.some((record) => record.id === member.user_id)).map((member) => ({ id: member.user_id, is_pic: Boolean(member.is_pic) })),
        manager_id: project.manager_id ?? '', baseline_approver_id: project.baseline_approver_id ?? '',
    });
    const registered = members.filter((member) => form.data.members.some((entry) => entry.id === member.id));
    const updateMember = (id: string, active: boolean) => {
        if (active) {
            form.setData('members', [...form.data.members, { id, is_pic: false }]);
        } else {
            form.setData({
                members: form.data.members.filter((member) => member.id !== id),
                manager_id: form.data.manager_id === id ? '' : form.data.manager_id,
                baseline_approver_id: form.data.baseline_approver_id === id ? '' : form.data.baseline_approver_id,
            });
        }
    };

    return <Section title="Anggota dan penugasan">
        <Errors errors={form.errors} />
        <form className="stack" onSubmit={(event) => { event.preventDefault(); form.put(url + '/members', { preserveScroll: true }); }}>
            {members.length ? <div className="table-shell"><table>
                <thead><tr><th>Nama</th><th>Anggota proyek</th><th>PIC lapangan</th></tr></thead>
                <tbody>{members.map((member) => {
                    const record = form.data.members.find((entry) => entry.id === member.id);
                    return <tr key={member.id}>
                        <td>{member.name}</td>
                        <td><input type="checkbox" aria-label={'Anggota ' + member.name} checked={Boolean(record)} onChange={(event) => updateMember(member.id, event.target.checked)} /></td>
                        <td><input type="checkbox" aria-label={'PIC ' + member.name} checked={record?.is_pic ?? false} disabled={!record} onChange={(event) => form.setData('members', form.data.members.map((entry) => entry.id === member.id ? { ...entry, is_pic: event.target.checked } : entry))} /></td>
                    </tr>;
                })}</tbody>
            </table></div> : <p className="empty">Belum ada anggota organisasi aktif yang dapat ditugaskan.</p>}
            <div className="grid-form">
                {(['manager_id', 'baseline_approver_id'] as const).map((field) => <Field key={field} label={field === 'manager_id' ? 'Project Manager' : 'Approver baseline'}>
                    <select value={form.data[field]} onChange={(event) => form.setData(field, event.target.value)}>
                        <option value="">Belum ditugaskan</option>
                        {registered.map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}
                    </select>
                </Field>)}
            </div>
            <div><button className="button" disabled={form.processing}>Simpan penugasan</button></div>
        </form>
    </Section>;
}

export default function ProjectPage({ organization, project, can, members, assignments, documents, audit }: Props) {
    const base = organizationUrl(organization) + '/projects';
    const url = base + '/' + project.id;
    const values = Object.keys(emptyProject).map((key) => [key, String(project[key as keyof typeof emptyProject] ?? '')]);
    const form = useForm({ ...emptyProject, ...Object.fromEntries(values) });
    const upload = useForm({ title: '', file: null as File | null });

    return <Shell title={project.name} organization={organization} actions={<>
        <Link className="button secondary" href={base}>Daftar proyek</Link>
        {can['wbs.view'] && <Link className="button" href={url + '/wbs'}>WBS dan baseline</Link>}
    </>}>
        <Section title="Ringkasan proyek" actions={<span className={'pill ' + project.status}>{projectStatus[project.status]}</span>}>
            <dl className="facts">
                <div><dt>Nomor proyek</dt><dd>{project.number}</dd></div>
                <div><dt>Kapal</dt><dd>{project.ship_name} · {project.ship_type}</dd></div>
                <div><dt>Tonase kotor</dt><dd>{formatNumber(project.tonnage_gt)} GT</dd></div>
                <div><dt>Pemilik</dt><dd>{project.owner}</dd></div>
                <div><dt>Galangan</dt><dd>{project.shipyard}</dd></div>
                <div><dt>Jadwal</dt><dd>{formatDate(project.start_date)} – {formatDate(project.target_date)}</dd></div>
                {can['projects.contract'] && <div><dt>Nilai kontrak</dt><dd>{project.currency} {formatNumber(project.contract_amount, 2)}</dd></div>}
            </dl>
        </Section>
        {can['projects.manage'] && <Section title="Ubah master proyek">
            <Errors errors={form.errors} />
            <form className="stack" onSubmit={(event) => { event.preventDefault(); form.put(url, { preserveScroll: true }); }}>
                <ProjectFields data={form.data} set={form.setData} contract={can['projects.contract']} />
                <div><button className="button" disabled={form.processing}>Simpan perubahan</button></div>
            </form>
        </Section>}
        {can['projects.members'] && project.status === 'active' && <Assignments project={project} members={members} assignments={assignments} url={url} />}
        {(can['documents.view'] || can['documents.manage']) && <Section title="Dokumen proyek">
            {can['documents.view'] && (documents.length ? <div className="table-shell"><table>
                <thead><tr><th>Dokumen</th><th>Tanggal unggah</th><th>Ukuran</th></tr></thead>
                <tbody>{documents.map((file) => <tr key={file.id}>
                    <td><a href={url + '/documents/' + file.id}>{file.title}</a><div className="help">{file.filename}</div></td>
                    <td>{formatDate(file.created_at)}</td><td>{formatNumber(file.size / 1024, 1)} KB</td>
                </tr>)}</tbody>
            </table></div> : <p className="empty">Belum ada dokumen proyek.</p>)}
            {can['documents.manage'] && project.status === 'active' && <form className="stack mt-4" onSubmit={(event) => {
                event.preventDefault();
                const element = event.currentTarget;
                upload.post(url + '/documents', { forceFormData: true, preserveScroll: true, onSuccess: () => { upload.reset(); element.reset(); } });
            }}>
                <Errors errors={upload.errors} />
                <div className="grid-form">
                    <Field label="Judul dokumen"><input required value={upload.data.title} onChange={(event) => upload.setData('title', event.target.value)} /></Field>
                    <Field label="Berkas privat (maks. 10 MB)"><input type="file" required accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)} /></Field>
                </div>
                <div><button className="button" disabled={upload.processing}>Unggah dokumen</button></div>
            </form>}
        </Section>}
        {can['audit.view'] && <Section title="Audit proyek"><AuditList entries={audit} /></Section>}
    </Shell>;
}
