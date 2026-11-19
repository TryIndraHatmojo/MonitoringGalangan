import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Button } from '@/components/ui/button';
import { useInertiaForm } from '@/lib/inertia-form';
import { Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, FileText, Layers, Plus } from 'lucide-react';
import { useEffect, useRef, type RefObject } from 'react';
import { z } from 'zod';
import { DataTable } from '@/components/data-table';
import { WbsOrder } from '@/components/wbs-order';
import { WbsTargetChart } from '@/components/wbs-target-chart';
import { month, readerSettings } from '@/lib/validation';
import { Errors, Field, LinkButton, Organization, Section, Shell, StatusBadge, formatDate, formatNumber, organizationUrl } from '../ui';

const stageSchema = z.object({ name: z.string(), weight: z.string() });
const nodeSchema = z.object({
    id: z.uuid(), parent_id: z.uuid().nullable(), type: z.enum(['category', 'group', 'item']),
    code: z.string().min(1, 'Isi kode simpul.'), name: z.string().min(1, 'Isi nama simpul.'),
    weight: z.string(), position: z.number().int().min(0).max(1000000),
    volume: z.string().nullish(), unit: z.string().nullish(), source: z.string().nullish(),
    start_date: z.string().nullish(), end_date: z.string().nullish(), pic_id: z.string().nullish(), reviewer_role: z.string().nullish(),
    method: z.enum(['direct', 'stages']).nullish(), stages: z.array(stageSchema).optional(),
});
const revisionFields = {
    draft_id: z.ulid().nullable(), version: z.number().int().positive().nullable(), effective_month: month,
};
const snapshotSchema = z.object({
    mode: z.enum(['item', 'legacy']),
    nodes: z.array(nodeSchema),
    targets: z.array(z.object({ item_id: z.uuid(), period: month, value: z.string() })),
    legacy: z.array(z.object({ category_id: z.uuid().nullable(), period: month, value: z.string(), source: z.string() })),
    lineage: z.array(z.object({ from_id: z.uuid(), to_id: z.uuid(), reason: z.string().min(1, 'Isi alasan perubahan.') })),
});
type Stage = z.infer<typeof stageSchema>;
type Node = z.infer<typeof nodeSchema>;
type Snapshot = z.infer<typeof snapshotSchema>;
type Row = Node & { level: number; project_weight: string; planned_pct: string | null; planned_contribution_pp: string | null; target_source: string | null };
type Plan = { mode: 'item' | 'legacy'; rows: Row[]; planned_pct: string | null; target_source: string | null };
type Revision = { id: string; revision: number; status: string; version: number; effective_month: string; approved_at: string | null; reason: string | null };
type Project = { id: string; name: string; number: string; start_date: string; target_date: string; wbs_restricted: boolean };
type Member = { id: string; name: string; is_pic: boolean };
type Props = {
    organization: Organization; project: Project; period: string;
    periods: { month: string; cutoff_date: string | null; official_baseline_id: string | null }[];
    published: Revision | null; publishedPlan: Plan; draft: Revision | null; working: Snapshot | null; workingPlan: Plan | null;
    history: Revision[]; difference: { added: string[]; removed: string[]; changed: { code: string; fields: string[] }[]; targetsChanged: boolean } | null;
    comparison: { baseline: Revision | null; planned_pct: string | null } | null;
    roles: Record<string, string>; methods: Record<string, Stage[]>; members: Member[]; readers: string[];
    can: { manage: boolean; access: boolean; submit: boolean; approve: boolean };
};

const typeNames = { category: 'Kategori', group: 'Subkategori', item: 'Item pekerjaan' };
const pct = (value: string | null) => value === null ? '—' : formatNumber(value) + '%';

function RoleOptions({ roles }: { roles: Props['roles'] }) {
    return <><option value="">Belum ditugaskan</option>{Object.entries(roles).map(([role, name]) => <option key={role} value={role}>{name}</option>)}</>;
}

function ReaderPicker({ restricted, readers, members, update }: {
    restricted: boolean; readers: string[]; members: Member[]; update: (restricted: boolean, readers: string[]) => void;
}) {
    return <div className="stack">
        <label className="check"><input type="checkbox" checked={restricted} onChange={(event) => update(event.target.checked, readers)} /> Batasi pembaca Form Builder ke akun yang dipilih</label>
        {restricted && <div className="grid-form">
            {members.map((member) => <label key={member.id} className="check"><input type="checkbox" checked={readers.includes(member.id)}
                onChange={(event) => update(restricted, event.target.checked ? [...readers, member.id] : readers.filter((id) => id !== member.id))} /> {member.name}</label>)}
            {!members.length && <p className="help">Tambahkan anggota proyek sebelum membatasi akses Form Builder.</p>}
        </div>}
    </div>;
}

function Readers({ props, url }: { props: Props; url: string }) {
    const form = useInertiaForm({ restricted: Boolean(props.project.wbs_restricted), readers: props.readers }, readerSettings);
    return <Section title="Pembaca Form Builder">
        <Errors errors={form.errors} />
        <form className="stack" onSubmit={(event) => { event.preventDefault(); form.put(url + '/readers', { preserveScroll: true }); }}>
            <ReaderPicker members={props.members} {...form.data} update={(restricted, readers) => form.setData({ restricted, readers })} />
            <div><Button variant="outline" disabled={form.processing}>Simpan akses pembaca</Button></div>
        </form>
    </Section>;
}

function Template({ props, url }: { props: Props; url: string }) {
    const form = useInertiaForm({
        template: 'ferry', reviewer_role: '', restricted: Boolean(props.project.wbs_restricted), readers: props.readers,
        draft_id: props.draft?.id ?? null, version: props.draft?.version ?? null,
        effective_month: props.draft?.effective_month ?? props.project.start_date.slice(0, 7),
    }, readerSettings.extend({ ...revisionFields, template: z.string(), reviewer_role: z.string() }));
    return <Section title="Template struktur pekerjaan">
        <Errors errors={form.errors} />
        <form className="stack" onSubmit={(event) => { event.preventDefault(); form.post(url + '/template', { preserveScroll: true }); }}>
            <div className="grid-form">
                <Field label="Struktur"><NativeSelect value={form.data.template} onChange={(event) => form.setData('template', event.target.value)}>
                    <option value="ferry">Ferry 1500 GT</option><option value="tug">ASD Harbour Tug</option>
                </NativeSelect></Field>
                <Field label="Tipe reviewer"><NativeSelect value={form.data.reviewer_role} onChange={(event) => form.setData('reviewer_role', event.target.value)}><RoleOptions roles={props.roles} /></NativeSelect></Field>
            </div>
            <ReaderPicker members={props.members} restricted={form.data.restricted} readers={form.data.readers}
                update={(restricted, readers) => form.setData({ ...form.data, restricted, readers })} />
            <div><Button disabled={form.processing}>Salin ke draf</Button></div>
        </form>
    </Section>;
}

function NodeFields({ node, nodes, props, patch, remove, addChild }: {
    node: Node; nodes: Node[]; props: Props; patch: (changes: Partial<Node>) => void; remove: () => void; addChild: () => void;
}) {
    const item = node.type === 'item';
    const stages = node.stages ?? [];
    const parents = nodes.filter((candidate) => candidate.id !== node.id && candidate.type !== 'item');
    const updateStage = (index: number, field: keyof Stage, value: string) => patch({
        stages: stages.map((stage, i) => i === index ? { ...stage, [field]: value } : stage),
    });
    return <details className="wbs-node" open={!node.code || undefined}>
        <summary><span className="work-row-title">
            {item ? <FileText aria-hidden="true" /> : <Layers aria-hidden="true" />}
            <span>{node.code || 'Kode baru'} · {node.name || typeNames[node.type]}</span>
            <span className="work-row-weight" title="Bobot terhadap induk">{formatNumber(node.weight)}%</span>
        </span></summary>
        <div className="grid-form">
            <Field label="Jenis simpul"><NativeSelect value={node.type} onChange={(event) => {
                const type = event.target.value as Node['type'];
                patch({ type, parent_id: type === 'category' ? null : node.parent_id ?? parents[0]?.id ?? null,
                    method: type === 'item' ? node.method ?? 'direct' : null, stages: type === 'item' ? stages : [] });
            }}>{Object.entries(typeNames).map(([type, label]) => <option key={type} value={type}>{label}</option>)}</NativeSelect></Field>
            {node.type !== 'category' && <Field label="Induk"><NativeSelect required value={node.parent_id ?? ''} onChange={(event) => patch({ parent_id: event.target.value || null })}>
                <option value="">Pilih induk</option>{parents.map((parent) => <option key={parent.id} value={parent.id}>{parent.code} · {parent.name}</option>)}
            </NativeSelect></Field>}
            <Field label="Kode"><Input required value={node.code} onChange={(event) => patch({ code: event.target.value })} /></Field>
            <Field label="Nama"><Input required value={node.name} onChange={(event) => patch({ name: event.target.value })} /></Field>
            <Field label="Bobot terhadap induk (%)"><Input required type="number" min="0" max="100" step="0.000001" value={node.weight} onChange={(event) => patch({ weight: event.target.value })} /></Field>
            <Field label="Urutan"><Input required type="number" min="0" step="1" value={node.position} onChange={(event) => patch({ position: Number(event.target.value) })} /></Field>
            <Field label="Referensi sumber"><Input value={node.source ?? ''} onChange={(event) => patch({ source: event.target.value })} /></Field>
            <Field label="Mulai rencana"><Input type="date" value={node.start_date ?? ''} onChange={(event) => patch({ start_date: event.target.value || null })} /></Field>
            <Field label="Selesai rencana"><Input type="date" value={node.end_date ?? ''} onChange={(event) => patch({ end_date: event.target.value || null })} /></Field>
            {item && <>
                <Field label="Volume"><Input type="number" min="0" step="0.000001" value={node.volume ?? ''} onChange={(event) => patch({ volume: event.target.value || null })} /></Field>
                <Field label="Satuan"><Input value={node.unit ?? ''} onChange={(event) => patch({ unit: event.target.value || null })} /></Field>
                <Field label="PIC"><NativeSelect value={node.pic_id ?? ''} onChange={(event) => patch({ pic_id: event.target.value || null })}>
                    <option value="">Belum ditugaskan</option>{props.members.filter((member) => Boolean(member.is_pic)).map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}
                </NativeSelect></Field>
                <Field label="Tipe reviewer"><NativeSelect value={node.reviewer_role ?? ''} onChange={(event) => patch({ reviewer_role: event.target.value || null })}><RoleOptions roles={props.roles} /></NativeSelect></Field>
                <Field label="Metode"><NativeSelect value={node.method ?? 'direct'} onChange={(event) => patch({
                    method: event.target.value as Node['method'], stages: event.target.value === 'direct' ? [] : stages,
                })}><option value="direct">Persentase langsung</option><option value="stages">Tahapan berbobot</option></NativeSelect></Field>
                <Field label="Salin template pengukuran"><NativeSelect value="" onChange={(event) => {
                    if (event.target.value) patch({ method: 'stages', stages: props.methods[event.target.value].map((stage) => ({ ...stage })) });
                }}><option value="">Pilih template</option>{Object.keys(props.methods).map((name) => <option key={name}>{name}</option>)}</NativeSelect></Field>
            </>}
        </div>
        {item && node.method === 'stages' && <div className="stack my-3">
            {stages.map((stage, index) => <div className="stage" key={index}>
                <Field label={'Tahap ' + (index + 1)}><Input required value={stage.name} onChange={(event) => updateStage(index, 'name', event.target.value)} /></Field>
                <Field label="Bobot efektif (%)"><Input required type="number" step="0.000001" min="0" max="100" value={stage.weight} onChange={(event) => updateStage(index, 'weight', event.target.value)} /></Field>
                <Button type="button" variant="destructive" onClick={() => patch({ stages: stages.filter((_, i) => i !== index) })}>Hapus</Button>
            </div>)}
            <div><Button type="button" variant="outline" onClick={() => patch({ stages: [...stages, { name: '', weight: '0' }] })}>Tambah tahap</Button></div>
        </div>}
        <div className="actions">
            {!item && <Button type="button" variant="outline" onClick={addChild}><Plus />Subpekerjaan</Button>}
            <Button type="button" variant="destructive" onClick={remove}>Hapus simpul dan turunannya</Button>
        </div>
    </details>;
}

function DraftEditor({ props, url, selectedMonth, dirty }: { props: Props; url: string; selectedMonth: string; dirty: RefObject<boolean> }) {
    const form = useInertiaForm({
        draft_id: props.draft?.id ?? null, version: props.draft?.version ?? null,
        effective_month: props.draft?.effective_month ?? (props.published ? props.period : props.project.start_date.slice(0, 7)),
        snapshot: props.working ?? { mode: 'item', nodes: [], targets: [], legacy: [], lineage: [] } as Snapshot,
    }, z.object({ ...revisionFields, snapshot: snapshotSchema }));
    const snapshot = form.data.snapshot;
    const nodes = snapshot.nodes;
    useEffect(() => {
        dirty.current = form.isDirty;
        return () => { dirty.current = false; };
    }, [dirty, form.isDirty]);
    const first = props.project.start_date.slice(0, 7);
    const last = props.project.target_date.slice(0, 7);
    const items = nodes.filter((node) => node.type === 'item');
    const categories = nodes.filter((node) => node.type === 'category');
    const patch = (changes: Partial<Snapshot>) => form.setData('snapshot', { ...snapshot, ...changes });
    const addNode = (parent: Node | null = null) => {
        const siblings = nodes.filter((node) => node.parent_id === (parent?.id ?? null));
        const position = Math.min(1000000, siblings.reduce((last, node) => Math.max(last, node.position), -1) + 1);
        const child: Node = parent ? { id: window.crypto.randomUUID(), parent_id: parent.id, type: 'item', code: '', name: '', weight: '0', position, method: 'direct' }
            : { id: window.crypto.randomUUID(), parent_id: null, type: 'category', code: '', name: '', weight: '0', position };
        patch({ nodes: [...nodes, child] });
    };
    const setTarget = (id: string, value: string) => {
        const targets = snapshot.targets.filter((entry) => entry.item_id !== id || entry.period !== selectedMonth);
        patch({ targets: value === '' ? targets : [...targets, { item_id: id, period: selectedMonth, value }] });
    };
    const setLegacy = (category: string | null, field: 'value' | 'source', value: string) => {
        const entry = snapshot.legacy.find((target) => target.category_id === category && target.period === selectedMonth)
            ?? { category_id: category, period: selectedMonth, value: '', source: '' };
        const next = { ...entry, [field]: value };
        const others = snapshot.legacy.filter((target) => target.category_id !== category || target.period !== selectedMonth);
        patch({ legacy: next.value === '' && next.source === '' ? others : [...others, next] });
    };
    const dropNode = (node: Node) => {
        if (!window.confirm('Hapus ' + (node.code || node.name || 'simpul ini') + ' dan semua turunannya dari draf?')) return;
        const ids = new Set([node.id]);
        let previousSize = 0;
        while (previousSize !== ids.size) {
            previousSize = ids.size;
            for (const item of nodes) if (item.parent_id && ids.has(item.parent_id)) ids.add(item.id);
        }
        patch({
            nodes: nodes.filter((item) => !ids.has(item.id)),
            targets: snapshot.targets.filter((target) => !ids.has(target.item_id)),
            legacy: snapshot.legacy.filter((target) => target.category_id === null || !ids.has(target.category_id)),
            lineage: snapshot.lineage.filter((entry) => !ids.has(entry.to_id)),
        });
    };

    return <Section title="Struktur pekerjaan" actions={<Button type="button" onClick={() => addNode()}><Plus />Tambah pekerjaan</Button>}>
        <Errors errors={form.errors} />
        <form className="stack" onSubmit={(event) => { event.preventDefault(); form.put(url + '/draft', { preserveScroll: true }); }}>
            <div className="grid-form">
                <Field label="Mulai berlaku"><Input type="month" required min={first} max={last} value={form.data.effective_month} onChange={(event) => form.setData('effective_month', event.target.value)} /></Field>
                <Field label="Jenis target"><NativeSelect value={snapshot.mode} onChange={(event) => {
                    const mode = event.target.value as Snapshot['mode'];
                    if ((snapshot.targets.length || snapshot.legacy.length) && !window.confirm('Ganti jenis baseline dan kosongkan target pada draf ini?')) return;
                    patch({ mode, targets: [], legacy: [] });
                }}><option value="item">Per item</option><option value="legacy">Agregat legacy</option></NativeSelect></Field>
            </div>
            <div className="stack">
                {nodes.length > 1 && <p className="help">Seret simpul atau gunakan tombol panah untuk mengubah urutan dalam induk yang sama.</p>}
                {!nodes.length && <p className="empty">Belum ada pekerjaan pada draf.</p>}
                <WbsOrder nodes={nodes} onChange={(ordered) => patch({ nodes: ordered })} renderNode={(node) => <NodeFields node={node} nodes={nodes} props={props}
                    patch={(changes) => patch({ nodes: nodes.map((item) => item.id === node.id ? { ...item, ...changes } : item) })}
                    remove={() => dropNode(node)} addChild={() => addNode(node)} />} />
            </div>
            <div className="stack">
                <h3>Target kumulatif · {selectedMonth}</h3>
                {snapshot.mode === 'item' ? (items.length ? <div className="grid-form">
                    {items.map((node) => <Field key={node.id} label={(node.code || 'Item baru') + ' · ' + node.name + ' (%)'}>
                        <Input type="number" min="0" max="100" step="0.000001" value={snapshot.targets.find((target) => target.item_id === node.id && target.period === selectedMonth)?.value ?? ''}
                            onChange={(event) => setTarget(node.id, event.target.value)} />
                    </Field>)}
                </div> : <p className="empty">Tambahkan item sebelum mengisi target.</p>) : <div className="stack">
                    {[{ id: null, code: '', name: 'Proyek' }, ...categories].map((node) => {
                        const entry = snapshot.legacy.find((target) => target.category_id === node.id && target.period === selectedMonth);
                        return <div className="grid-form" key={node.id ?? 'project'}>
                            <Field label={(node.code ? node.code + ' · ' : '') + node.name + (node.id ? ' (pp proyek)' : ' (%)')}>
                                <Input type="number" min="0" max="100" step="0.000001" value={entry?.value ?? ''} onChange={(event) => setLegacy(node.id, 'value', event.target.value)} />
                            </Field>
                            <Field label="Sumber workbook dan sel"><Input required={Boolean(entry?.value)} value={entry?.source ?? ''} onChange={(event) => setLegacy(node.id, 'source', event.target.value)} /></Field>
                        </div>;
                    })}
                </div>}
            </div>
            <details>
                <summary>Relasi item yang digabung atau dipecah</summary>
                <div className="stack">
                    {snapshot.lineage.map((link, index) => <div className="grid-form items-end" key={index}>
                        <Field label="ID item asal"><Input required value={link.from_id} onChange={(event) => patch({ lineage: snapshot.lineage.map((entry, i) => i === index ? { ...entry, from_id: event.target.value } : entry) })} /></Field>
                        <Field label="Item pengganti"><NativeSelect required value={link.to_id} onChange={(event) => patch({ lineage: snapshot.lineage.map((entry, i) => i === index ? { ...entry, to_id: event.target.value } : entry) })}>
                            <option value="">Pilih item</option>{items.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}
                        </NativeSelect></Field>
                        <Field label="Alasan perubahan"><Input required value={link.reason} onChange={(event) => patch({ lineage: snapshot.lineage.map((entry, i) => i === index ? { ...entry, reason: event.target.value } : entry) })} /></Field>
                        <div><Button type="button" variant="destructive" onClick={() => patch({ lineage: snapshot.lineage.filter((_, i) => i !== index) })}>Hapus relasi</Button></div>
                    </div>)}
                    <div><Button type="button" variant="outline" onClick={() => patch({ lineage: [...snapshot.lineage, { from_id: '', to_id: '', reason: '' }] })}>Tambah relasi</Button></div>
                </div>
            </details>
            <div><Button disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan draf'}</Button></div>
        </form>
    </Section>;
}

function PlanTable({ plan, chart = false }: { plan: Plan; chart?: boolean }) {
    if (!plan.rows.length) return <p className="empty">Belum ada struktur yang tersedia.</p>;
    const categories = chart ? plan.rows.flatMap((row) => row.type === 'category' && row.planned_contribution_pp !== null
        ? [{ code: row.code, name: row.name, value: Number(row.planned_contribution_pp) }] : []) : [];
    return <div className="stack">
        <div className="wbs-target-summary">
            <span>Target proyek</span><strong className="wbs-target-value">{pct(plan.planned_pct)}</strong>
            {plan.mode === 'legacy' && <span className="help">Agregat legacy</span>}
        </div>
        {plan.target_source && <p className="help">{plan.target_source}</p>}
        <div className="work-table"><DataTable data={plan.rows} rowKey={(row) => row.id} label="Struktur pekerjaan" columns={[
            { id: 'work', header: 'Kode dan pekerjaan', cell: ({ row: { original: row } }) =>
                <div className="min-w-44 !whitespace-normal" style={{ paddingInlineStart: Math.min(row.level, 8) * 12 }}>
                    <span className="work-row-title">
                        {row.type === 'item' ? <FileText aria-hidden="true" /> : <Layers aria-hidden="true" />}
                        <strong>{row.code} · {row.name}</strong>
                    </span>
                    <details className="help"><summary>Rincian</summary>
                        {row.method && <div>{row.method === 'direct' ? 'Persentase langsung' : row.stages?.map((stage) => stage.name + ' ' + pct(stage.weight)).join(' · ')}</div>}
                        {row.start_date && <div>{formatDate(row.start_date)} – {formatDate(row.end_date)}</div>}
                        <div>ID {row.id}</div>
                    </details>
                </div> },
            { id: 'weight', header: 'Bobot lokal', cell: ({ row }) => pct(row.original.weight) },
            { id: 'projectWeight', header: 'Bobot proyek', cell: ({ row }) => pct(row.original.project_weight) },
            { id: 'target', header: 'Target fisik', cell: ({ row }) => pct(row.original.planned_pct) },
            { id: 'contribution', header: 'Kontribusi proyek', cell: ({ row }) => row.original.planned_contribution_pp === null
                ? '—' : formatNumber(row.original.planned_contribution_pp) + ' pp' },
            { id: 'source', header: 'Sumber', cell: ({ row }) => row.original.target_source ?? row.original.source ?? '—' },
        ]} /></div>
        <WbsTargetChart data={categories} />
    </div>;
}

function Review({ props, url }: { props: Props; url: string }) {
    const draft = props.draft!;
    const form = useInertiaForm({ operation: 'submit', version: draft.version, reason: '' }, z.object({
        operation: z.string(), version: z.number().int().positive(), reason: z.string().max(2000, 'Alasan maksimal 2.000 karakter.'),
    }).refine(({ operation, reason }) => operation === 'publish' || Boolean(reason.trim()), {
        path: ['reason'], message: 'Isi alasan untuk melanjutkan.',
    }));
    const send = (operation: 'submit' | 'return' | 'publish') => {
        form.setData('operation', operation);
        form.post(url + '/' + draft.id + '/transition', { preserveScroll: true });
    };
    const canSubmit = draft.status === 'draft' && props.can.submit;
    const canApprove = draft.status === 'submitted' && props.can.approve;

    return <Section title={'Draf revisi ' + draft.revision} actions={<StatusBadge status={draft.status} />}>
        <div className="stack">
            <p>Berlaku mulai {draft.effective_month}{draft.reason && ' · ' + draft.reason}</p>
            {props.workingPlan && <PlanTable plan={props.workingPlan} />}
            {props.difference && <details><summary>Perbandingan dengan baseline yang berlaku</summary>
                <div className="stack">
                    <p>{props.comparison?.baseline ? 'Revisi terbit ' + props.comparison.baseline.revision : 'Belum ada baseline pembanding'}
                        {' · Target periode ' + pct(props.comparison?.planned_pct ?? null)}</p>
                    <p>Ditambah {props.difference.added.join(', ') || '—'} · Dihapus {props.difference.removed.join(', ') || '—'}</p>
                    <p>Diubah {props.difference.changed.map((change) => change.code).join(', ') || '—'}</p>
                    <p>{props.difference.targetsChanged ? 'Target berubah' : 'Target sama'}</p>
                </div>
            </details>}
            <Errors errors={form.errors} />
            {(canApprove || canSubmit) && <Field label="Alasan pengajuan atau pengembalian"><Textarea rows={2} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></Field>}
            <div className="actions">
                {canSubmit && <Button disabled={form.processing || !form.data.reason.trim()} onClick={() => send('submit')}>Ajukan baseline</Button>}
                {canApprove && <>
                    <Button variant="outline" disabled={form.processing || !form.data.reason.trim()} onClick={() => send('return')}>Kembalikan ke draf</Button>
                    <Button disabled={form.processing} onClick={() => send('publish')}>Setujui dan terbitkan</Button>
                </>}
            </div>
        </div>
    </Section>;
}

export default function FormBuilder(props: Props) {
    const base = organizationUrl(props.organization) + '/projects/' + props.project.id;
    const url = base + '/wbs';
    const selection = useInertiaForm({ period: props.period }, z.object({ period: month }));
    const period = props.periods.find((record) => record.month === props.period);
    const cutoff = useInertiaForm({ period: props.period, cutoff_date: period?.cutoff_date ?? '' }, z.object({
        period: month, cutoff_date: z.union([z.literal(''), z.iso.date()]),
    }).refine(({ period, cutoff_date }) => !cutoff_date || cutoff_date.startsWith(period + '-'), {
        path: ['cutoff_date'], message: 'Tanggal cutoff harus berada dalam periode yang dipilih.',
    }));
    const draftKey = props.draft?.id + ':' + props.draft?.version;
    const editable = props.can.manage && props.draft?.status !== 'submitted';
    const dirtyDraft = useRef(false);

    return <Shell title="Form Builder" organization={props.organization} destination="builder"
        beforeProjectChange={() => !dirtyDraft.current || window.confirm('Tinggalkan draf yang belum disimpan?')}
        actions={<LinkButton variant="outline" href={base}><ArrowLeft />Ringkasan proyek</LinkButton>}>
        <Section>
            <div className="builder-period">
                <form className="grid-form items-end" onSubmit={(event) => {
                    event.preventDefault();
                    selection.get(url);
                }}>
                    <Field label="Periode rencana"><Input type="month" required value={selection.data.period} onChange={(event) => selection.setData('period', event.target.value)} /></Field>
                    <div><Button variant="outline" disabled={selection.processing}><CalendarDays />Tampilkan periode</Button></div>
                </form>
                <Errors errors={selection.errors} />
                {period?.official_baseline_id && <p className="help">Periode resmi memakai versi baseline yang tersimpan saat publikasi.</p>}
                {props.can.manage && !period?.official_baseline_id && <form className="grid-form items-end" onSubmit={(event) => {
                    event.preventDefault();
                    cutoff.put(url + '/cutoff', { preserveScroll: true });
                }}>
                    <Field label={'Tanggal cutoff · ' + props.period}><Input type="date" value={cutoff.data.cutoff_date} onChange={(event) => cutoff.setData('cutoff_date', event.target.value)} /></Field>
                    <div><Button variant="outline" disabled={cutoff.processing}>Simpan cutoff</Button></div>
                </form>}
                <Errors errors={cutoff.errors} />
            </div>
        </Section>
        {editable && <DraftEditor key={props.project.id + ':' + draftKey + ':' + props.period} props={props} url={url} selectedMonth={props.period} dirty={dirtyDraft} />}
        {editable && props.can.access && !props.working?.nodes.length && <Template key={draftKey} props={props} url={url} />}
        <Section title={editable ? 'Baseline terbit' : 'Struktur pekerjaan'}
            actions={props.published && <StatusBadge status="published">Baseline terbit · Revisi {props.published.revision} · {props.published.effective_month}</StatusBadge>}>
            {props.published ? <PlanTable plan={props.publishedPlan} chart /> : <p className="empty">Belum ada baseline terbit untuk periode ini.</p>}
            {props.history.length > 0 && <details className="mt-4"><summary>Riwayat versi terbit</summary>
                <ul className="stack">{props.history.map((version) => <li key={version.id}><Link href={url + '?period=' + props.period + '&revision=' + version.id}>
                    Revisi {version.revision} · berlaku {version.effective_month}
                </Link></li>)}</ul>
            </details>}
        </Section>
        {props.draft && <Review key={draftKey} props={props} url={url} />}
        {props.can.access && <Readers key={String(props.project.wbs_restricted) + ':' + props.readers.join(',')} props={props} url={url} />}
    </Shell>;
}
