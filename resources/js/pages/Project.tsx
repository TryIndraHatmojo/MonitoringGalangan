import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Button } from '@/components/ui/button';
import { useInertiaForm } from '@/lib/inertia-form';
import { DataTable } from '@/components/data-table';
import type { ProjectContext } from '@/components/project-combobox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { AuditEvent, AuditList, Errors, Field, LinkButton, Organization, Section, Shell, StatusBadge, formatDate, formatNumber, organizationUrl } from '../ui';
import { Project, ProjectFields, emptyProject, projectSchema } from '../project-fields';
import { ArrowLeft, Download, ListTree, Upload } from 'lucide-react';
import { z } from 'zod';
import { optionalId } from '@/lib/validation';

type Member = { id: string; name: string; role: string };
type Assignment = { user_id: string; is_pic: boolean };
type Document = { id: string; title: string; filename: string; size: number; created_at: string };
type Props = {
    organization: Organization; project: Project; context: ProjectContext; can: Record<string, boolean>; members: Member[];
    assignments: Assignment[]; documents: Document[]; audit: AuditEvent[];
};

function Assignments({ members, assignments, project, url }: Pick<Props, 'members' | 'assignments' | 'project'> & { url: string }) {
    const form = useInertiaForm({
        members: assignments.filter((member) => members.some((record) => record.id === member.user_id)).map((member) => ({ id: member.user_id, is_pic: Boolean(member.is_pic) })),
        manager_id: project.manager_id ?? '', baseline_approver_id: project.baseline_approver_id ?? '',
    }, z.object({
        members: z.array(z.object({ id: z.ulid(), is_pic: z.boolean() })).max(300),
        manager_id: optionalId, baseline_approver_id: optionalId,
    }).refine(({ members, manager_id, baseline_approver_id }) =>
        [manager_id, baseline_approver_id].every((id) => !id || members.some((member) => member.id === id)),
    'Penanggung jawab harus tercantum sebagai anggota proyek.'));
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
            {members.length ? <Table aria-label="Penugasan anggota">
                <TableHeader><TableRow>
                    <TableHead scope="col">Nama</TableHead><TableHead scope="col">Anggota proyek</TableHead><TableHead scope="col">PIC lapangan</TableHead>
                </TableRow></TableHeader>
                <TableBody>{members.map((member) => {
                    const record = form.data.members.find((entry) => entry.id === member.id);
                    return <TableRow key={member.id}>
                        <TableCell>{member.name}</TableCell>
                        <TableCell><input type="checkbox" aria-label={'Anggota ' + member.name} checked={Boolean(record)}
                            onChange={(event) => updateMember(member.id, event.target.checked)} /></TableCell>
                        <TableCell><input type="checkbox" aria-label={'PIC ' + member.name} checked={record?.is_pic ?? false} disabled={!record}
                            onChange={(event) => form.setData('members', form.data.members.map((entry) => entry.id === member.id
                                ? { ...entry, is_pic: event.target.checked } : entry))} /></TableCell>
                    </TableRow>;
                })}</TableBody>
            </Table> : <p className="empty">Belum ada anggota organisasi aktif yang dapat ditugaskan.</p>}
            <div className="grid-form">
                {(['manager_id', 'baseline_approver_id'] as const).map((field) => <Field key={field} label={field === 'manager_id' ? 'Project Manager' : 'Approver baseline'}>
                    <NativeSelect value={form.data[field]} onChange={(event) => form.setData(field, event.target.value)}>
                        <option value="">Belum ditugaskan</option>
                        {registered.map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}
                    </NativeSelect>
                </Field>)}
            </div>
            <div><Button disabled={form.processing}>Simpan penugasan</Button></div>
        </form>
    </Section>;
}

export default function ProjectPage({ organization, project, context, can, members, assignments, documents, audit }: Props) {
    const base = organizationUrl(organization) + '/projects';
    const url = base + '/' + project.id;
    const values = Object.keys(emptyProject).map((key) => [key, String(project[key as keyof typeof emptyProject] ?? '')]);
    const form = useInertiaForm({ ...emptyProject, ...Object.fromEntries(values) }, projectSchema(can['projects.contract']));
    const upload = useInertiaForm({ title: '', file: null as File | null }, z.object({
        title: z.string().min(1, 'Isi judul dokumen.').max(180, 'Judul maksimal 180 karakter.'),
        file: z.instanceof(File).nullable().refine((file) => file && file.size <= 10 * 1024 * 1024, 'Pilih berkas berukuran maksimal 10 MB.'),
    }));

    return <Shell title={project.name} organization={organization} context={context} actions={<>
        <LinkButton variant="outline" href={base}><ArrowLeft />Proyek Kapal</LinkButton>
        {can['wbs.view'] && <LinkButton href={url + '/wbs'}><ListTree />Form Builder</LinkButton>}
    </>}>
        <Section title="Ringkasan proyek" actions={<StatusBadge status={project.status} />}>
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
        {can['projects.manage'] && <div id="edit-profil"><Section title="Edit profil proyek">
            <Errors errors={form.errors} />
            <form className="stack" onSubmit={(event) => { event.preventDefault(); form.put(url, { preserveScroll: true }); }}>
                <ProjectFields data={form.data} set={form.setData} contract={can['projects.contract']} />
                <div><Button disabled={form.processing}>Simpan perubahan</Button></div>
            </form>
        </Section></div>}
        {can['projects.members'] && project.status === 'active' && <Assignments project={project} members={members} assignments={assignments} url={url} />}
        {(can['documents.view'] || can['documents.manage']) && <Section title="Dokumen proyek">
            {can['documents.view'] && <DataTable data={documents} rowKey={(file) => file.id} label="Dokumen proyek"
                empty="Belum ada dokumen proyek." columns={[
                    { id: 'document', header: 'Dokumen', cell: ({ row: { original: file } }) => <>
                        <a className="inline-flex items-center gap-2" href={url + '/documents/' + file.id}><Download size={15} />{file.title}</a>
                        <div className="help">{file.filename}</div>
                    </> },
                    { id: 'uploaded', header: 'Tanggal unggah', cell: ({ row }) => formatDate(row.original.created_at) },
                    { id: 'size', header: 'Ukuran', cell: ({ row }) => formatNumber(row.original.size / 1024, 1) + ' KB' },
                ]} />}
            {can['documents.manage'] && project.status === 'active' && <form className="stack mt-4" onSubmit={(event) => {
                event.preventDefault();
                const element = event.currentTarget;
                upload.post(url + '/documents', { forceFormData: true, preserveScroll: true, onSuccess: () => { upload.reset(); element.reset(); } });
            }}>
                <Errors errors={upload.errors} />
                <div className="grid-form">
                    <Field label="Judul dokumen"><Input required value={upload.data.title} onChange={(event) => upload.setData('title', event.target.value)} /></Field>
                    <Field label="Berkas privat (maks. 10 MB)"><Input type="file" required accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)} /></Field>
                </div>
                <div><Button disabled={upload.processing}><Upload />Unggah dokumen</Button></div>
            </form>}
        </Section>}
        {can['audit.view'] && <Section title="Audit proyek"><AuditList entries={audit} /></Section>}
    </Shell>;
}
