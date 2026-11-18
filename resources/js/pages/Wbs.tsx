import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Errors, Field, Organization, Section, Shell, formatDate, formatNumber, organizationUrl, revisionStatus } from '../ui';

type Stage = { name: string; weight: string };
type Node = {
    id: string; parent_id: string | null; type: 'category' | 'group' | 'item'; code: string; name: string;
    weight: string; position: number; volume?: string | null; unit?: string | null; source?: string | null;
    start_date?: string | null; end_date?: string | null; pic_id?: string | null; reviewer_role?: string | null;
    method?: 'direct' | 'stages' | null; stages?: Stage[];
};
type Target = { item_id: string; period: string; value: string };
type Legacy = { category_id: string | null; period: string; value: string; source: string };
type Lineage = { from_id: string; to_id: string; reason: string };
type Snapshot = { mode: 'item' | 'legacy'; nodes: Node[]; targets: Target[]; legacy: Legacy[]; lineage: Lineage[] };
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
        <label className="check"><input type="checkbox" checked={restricted} onChange={(event) => update(event.target.checked, readers)} /> Batasi pembaca WBS ke akun yang dipilih</label>
        {restricted && <div className="grid-form">
            {members.map((member) => <label key={member.id} className="check"><input type="checkbox" checked={readers.includes(member.id)}
                onChange={(event) => update(restricted, event.target.checked ? [...readers, member.id] : readers.filter((id) => id !== member.id))} /> {member.name}</label>)}
            {!members.length && <p className="help">Tambahkan anggota proyek sebelum membatasi akses WBS.</p>}
        </div>}
    </div>;
}

function Readers({ props, url }: { props: Props; url: string }) {
    const form = useForm({ restricted: Boolean(props.project.wbs_restricted), readers: props.readers });
    return <Section title="Pembaca WBS">
        <Errors errors={form.errors} />
        <form className="stack" onSubmit={(event) => { event.preventDefault(); form.put(url + '/readers', { preserveScroll: true }); }}>
            <ReaderPicker members={props.members} {...form.data} update={(restricted, readers) => form.setData({ restricted, readers })} />
            <div><button className="button secondary" disabled={form.processing}>Simpan akses pembaca</button></div>
        </form>
    </Section>;
}

function Template({ props, url }: { props: Props; url: string }) {
    const form = useForm({
        template: 'ferry', reviewer_role: '', restricted: Boolean(props.project.wbs_restricted), readers: props.readers,
        draft_id: props.draft?.id ?? null, version: props.draft?.version ?? null,
        effective_month: props.draft?.effective_month ?? props.project.start_date.slice(0, 7),
    });
    return <Section title="Salin template proyek">
        <Errors errors={form.errors} />
        <form className="stack" onSubmit={(event) => { event.preventDefault(); form.post(url + '/template', { preserveScroll: true }); }}>
            <div className="grid-form">
                <Field label="Struktur"><select value={form.data.template} onChange={(event) => form.setData('template', event.target.value)}>
                    <option value="ferry">Ferry 1500 GT</option><option value="tug">ASD Harbour Tug</option>
                </select></Field>
                <Field label="Tipe reviewer"><select value={form.data.reviewer_role} onChange={(event) => form.setData('reviewer_role', event.target.value)}><RoleOptions roles={props.roles} /></select></Field>
            </div>
            <ReaderPicker members={props.members} restricted={form.data.restricted} readers={form.data.readers}
                update={(restricted, readers) => form.setData({ ...form.data, restricted, readers })} />
            <div><button className="button" disabled={form.processing}>Salin ke draf</button></div>
        </form>
    </Section>;
}

function NodeFields({ node, nodes, props, patch, remove }: {
    node: Node; nodes: Node[]; props: Props; patch: (changes: Partial<Node>) => void; remove: () => void;
}) {
    const item = node.type === 'item';
    const stages = node.stages ?? [];
    const parents = nodes.filter((candidate) => candidate.id !== node.id && candidate.type !== 'item');
    const updateStage = (index: number, field: keyof Stage, value: string) => patch({
        stages: stages.map((stage, i) => i === index ? { ...stage, [field]: value } : stage),
    });
    return <details className="wbs-node" open={!node.code || undefined}>
        <summary>{node.code || 'Kode baru'} · {node.name || typeNames[node.type]} · {formatNumber(node.weight)}%</summary>
        <div className="grid-form">
            <Field label="Jenis simpul"><select value={node.type} onChange={(event) => {
                const type = event.target.value as Node['type'];
                patch({ type, parent_id: type === 'category' ? null : node.parent_id ?? parents[0]?.id ?? null,
                    method: type === 'item' ? node.method ?? 'direct' : null, stages: type === 'item' ? stages : [] });
            }}>{Object.entries(typeNames).map(([type, label]) => <option key={type} value={type}>{label}</option>)}</select></Field>
            {node.type !== 'category' && <Field label="Induk"><select required value={node.parent_id ?? ''} onChange={(event) => patch({ parent_id: event.target.value || null })}>
                <option value="">Pilih induk</option>{parents.map((parent) => <option key={parent.id} value={parent.id}>{parent.code} · {parent.name}</option>)}
            </select></Field>}
            <Field label="Kode"><input required value={node.code} onChange={(event) => patch({ code: event.target.value })} /></Field>
            <Field label="Nama"><input required value={node.name} onChange={(event) => patch({ name: event.target.value })} /></Field>
            <Field label="Bobot terhadap induk (%)"><input required type="number" min="0" max="100" step="0.000001" value={node.weight} onChange={(event) => patch({ weight: event.target.value })} /></Field>
            <Field label="Urutan"><input required type="number" min="0" step="1" value={node.position} onChange={(event) => patch({ position: Number(event.target.value) })} /></Field>
            <Field label="Referensi sumber"><input value={node.source ?? ''} onChange={(event) => patch({ source: event.target.value })} /></Field>
            <Field label="Mulai rencana"><input type="date" value={node.start_date ?? ''} onChange={(event) => patch({ start_date: event.target.value || null })} /></Field>
            <Field label="Selesai rencana"><input type="date" value={node.end_date ?? ''} onChange={(event) => patch({ end_date: event.target.value || null })} /></Field>
            {item && <>
                <Field label="Volume"><input type="number" min="0" step="0.000001" value={node.volume ?? ''} onChange={(event) => patch({ volume: event.target.value || null })} /></Field>
                <Field label="Satuan"><input value={node.unit ?? ''} onChange={(event) => patch({ unit: event.target.value || null })} /></Field>
                <Field label="PIC"><select value={node.pic_id ?? ''} onChange={(event) => patch({ pic_id: event.target.value || null })}>
                    <option value="">Belum ditugaskan</option>{props.members.filter((member) => Boolean(member.is_pic)).map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}
                </select></Field>
                <Field label="Tipe reviewer"><select value={node.reviewer_role ?? ''} onChange={(event) => patch({ reviewer_role: event.target.value || null })}><RoleOptions roles={props.roles} /></select></Field>
                <Field label="Metode"><select value={node.method ?? 'direct'} onChange={(event) => patch({
                    method: event.target.value as Node['method'], stages: event.target.value === 'direct' ? [] : stages,
                })}><option value="direct">Persentase langsung</option><option value="stages">Tahapan berbobot</option></select></Field>
                <Field label="Salin template pengukuran"><select value="" onChange={(event) => {
                    if (event.target.value) patch({ method: 'stages', stages: props.methods[event.target.value].map((stage) => ({ ...stage })) });
                }}><option value="">Pilih template</option>{Object.keys(props.methods).map((name) => <option key={name}>{name}</option>)}</select></Field>
            </>}
        </div>
        {item && node.method === 'stages' && <div className="stack my-3">
            {stages.map((stage, index) => <div className="stage" key={index}>
                <Field label={'Tahap ' + (index + 1)}><input required value={stage.name} onChange={(event) => updateStage(index, 'name', event.target.value)} /></Field>
                <Field label="Bobot efektif (%)"><input required type="number" step="0.000001" min="0" max="100" value={stage.weight} onChange={(event) => updateStage(index, 'weight', event.target.value)} /></Field>
                <button type="button" className="button danger" onClick={() => patch({ stages: stages.filter((_, i) => i !== index) })}>Hapus</button>
            </div>)}
            <div><button type="button" className="button secondary" onClick={() => patch({ stages: [...stages, { name: '', weight: '0' }] })}>Tambah tahap</button></div>
        </div>}
        <button type="button" className="button danger" onClick={remove}>Hapus simpul dan turunannya</button>
    </details>;
}

function DraftEditor({ props, url, selectedMonth }: { props: Props; url: string; selectedMonth: string }) {
    const form = useForm({
        draft_id: props.draft?.id ?? null, version: props.draft?.version ?? null,
        effective_month: props.draft?.effective_month ?? (props.published ? props.period : props.project.start_date.slice(0, 7)),
        snapshot: props.working ?? { mode: 'item', nodes: [], targets: [], legacy: [], lineage: [] } as Snapshot,
    });
    const snapshot = form.data.snapshot;
    const nodes = snapshot.nodes;
    const first = props.project.start_date.slice(0, 7);
    const last = props.project.target_date.slice(0, 7);
    const items = nodes.filter((node) => node.type === 'item');
    const categories = nodes.filter((node) => node.type === 'category');
    const patch = (changes: Partial<Snapshot>) => form.setData('snapshot', { ...snapshot, ...changes });
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

    return <Section title={props.draft ? 'Edit draf baseline' : 'Siapkan draf baseline'}>
        <Errors errors={form.errors} />
        <form className="stack" onSubmit={(event) => { event.preventDefault(); form.put(url + '/draft', { preserveScroll: true }); }}>
            <div className="grid-form">
                <Field label="Mulai berlaku"><input type="month" required min={first} max={last} value={form.data.effective_month} onChange={(event) => form.setData('effective_month', event.target.value)} /></Field>
                <Field label="Jenis target"><select value={snapshot.mode} onChange={(event) => {
                    const mode = event.target.value as Snapshot['mode'];
                    if ((snapshot.targets.length || snapshot.legacy.length) && !window.confirm('Ganti jenis baseline dan kosongkan target pada draf ini?')) return;
                    patch({ mode, targets: [], legacy: [] });
                }}><option value="item">Per item</option><option value="legacy">Agregat legacy</option></select></Field>
            </div>
            <div className="stack">
                <h3>Struktur pekerjaan</h3>
                {nodes.map((node) => <NodeFields key={node.id} node={node} nodes={nodes} props={props}
                    patch={(changes) => patch({ nodes: nodes.map((item) => item.id === node.id ? { ...item, ...changes } : item) })}
                    remove={() => dropNode(node)} />)}
                <div><button type="button" className="button secondary" onClick={() => patch({ nodes: [...nodes, {
                    id: window.crypto.randomUUID(), parent_id: null, type: 'category', code: '', name: '', weight: '0', position: nodes.length,
                }] })}>Tambah simpul</button></div>
            </div>
            <div className="stack">
                <h3>Target kumulatif · {selectedMonth}</h3>
                {snapshot.mode === 'item' ? (items.length ? <div className="grid-form">
                    {items.map((node) => <Field key={node.id} label={(node.code || 'Item baru') + ' · ' + node.name + ' (%)'}>
                        <input type="number" min="0" max="100" step="0.000001" value={snapshot.targets.find((target) => target.item_id === node.id && target.period === selectedMonth)?.value ?? ''}
                            onChange={(event) => setTarget(node.id, event.target.value)} />
                    </Field>)}
                </div> : <p className="empty">Tambahkan item sebelum mengisi target.</p>) : <div className="stack">
                    {[{ id: null, code: '', name: 'Proyek' }, ...categories].map((node) => {
                        const entry = snapshot.legacy.find((target) => target.category_id === node.id && target.period === selectedMonth);
                        return <div className="grid-form" key={node.id ?? 'project'}>
                            <Field label={(node.code ? node.code + ' · ' : '') + node.name + (node.id ? ' (pp proyek)' : ' (%)')}>
                                <input type="number" min="0" max="100" step="0.000001" value={entry?.value ?? ''} onChange={(event) => setLegacy(node.id, 'value', event.target.value)} />
                            </Field>
                            <Field label="Sumber workbook dan sel"><input required={Boolean(entry?.value)} value={entry?.source ?? ''} onChange={(event) => setLegacy(node.id, 'source', event.target.value)} /></Field>
                        </div>;
                    })}
                </div>}
            </div>
            <details>
                <summary>Relasi item yang digabung atau dipecah</summary>
                <div className="stack">
                    {snapshot.lineage.map((link, index) => <div className="grid-form items-end" key={index}>
                        <Field label="ID item asal"><input required value={link.from_id} onChange={(event) => patch({ lineage: snapshot.lineage.map((entry, i) => i === index ? { ...entry, from_id: event.target.value } : entry) })} /></Field>
                        <Field label="Item pengganti"><select required value={link.to_id} onChange={(event) => patch({ lineage: snapshot.lineage.map((entry, i) => i === index ? { ...entry, to_id: event.target.value } : entry) })}>
                            <option value="">Pilih item</option>{items.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}
                        </select></Field>
                        <Field label="Alasan perubahan"><input required value={link.reason} onChange={(event) => patch({ lineage: snapshot.lineage.map((entry, i) => i === index ? { ...entry, reason: event.target.value } : entry) })} /></Field>
                        <div><button type="button" className="button danger" onClick={() => patch({ lineage: snapshot.lineage.filter((_, i) => i !== index) })}>Hapus relasi</button></div>
                    </div>)}
                    <div><button type="button" className="button secondary" onClick={() => patch({ lineage: [...snapshot.lineage, { from_id: '', to_id: '', reason: '' }] })}>Tambah relasi</button></div>
                </div>
            </details>
            <div><button className="button" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan draf'}</button></div>
        </form>
    </Section>;
}

function PlanTable({ plan }: { plan: Plan }) {
    if (!plan.rows.length) return <p className="empty">Belum ada struktur yang tersedia.</p>;
    return <div className="stack">
        <p><strong>Target proyek {pct(plan.planned_pct)}</strong>{plan.mode === 'legacy' && ' · Agregat legacy'}</p>
        {plan.target_source && <p className="help">{plan.target_source}</p>}
        <div className="table-shell"><table className="wbs-table">
            <thead><tr><th>WBS</th><th>Bobot lokal</th><th>Bobot proyek</th><th>Target fisik</th><th>Kontribusi proyek</th></tr></thead>
            <tbody>{plan.rows.map((row) => <tr key={row.id}>
                <td><div style={{ paddingInlineStart: Math.min(row.level, 8) * 12 }}>
                    <strong>{row.code} · {row.name}</strong>
                    {(row.target_source || row.source) && <div className="help">{row.target_source ?? row.source}</div>}
                    <details className="help"><summary>Rincian</summary>
                        {row.method && <div>{row.method === 'direct' ? 'Persentase langsung' : row.stages?.map((stage) => stage.name + ' ' + pct(stage.weight)).join(' · ')}</div>}
                        {row.start_date && <div>{formatDate(row.start_date)} – {formatDate(row.end_date)}</div>}
                        <div>ID {row.id}</div>
                    </details>
                </div></td>
                <td data-label="Bobot lokal">{pct(row.weight)}</td><td data-label="Bobot proyek">{pct(row.project_weight)}</td><td data-label="Target fisik">{pct(row.planned_pct)}</td><td data-label="Kontribusi proyek">{row.planned_contribution_pp === null ? '—' : formatNumber(row.planned_contribution_pp) + ' pp'}</td>
            </tr>)}</tbody>
        </table></div>
    </div>;
}

function Review({ props, url }: { props: Props; url: string }) {
    const draft = props.draft!;
    const form = useForm({ operation: 'submit', version: draft.version, reason: '' });
    const send = (operation: 'submit' | 'return' | 'publish') => {
        form.transform((data) => ({ ...data, operation }));
        form.post(url + '/' + draft.id + '/transition', { preserveScroll: true });
    };
    const canSubmit = draft.status === 'draft' && props.can.submit;
    const canApprove = draft.status === 'submitted' && props.can.approve;

    return <Section title={'Draf revisi ' + draft.revision} actions={<span className={'pill ' + draft.status}>{revisionStatus[draft.status]}</span>}>
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
            {(canApprove || canSubmit) && <Field label="Alasan pengajuan atau pengembalian"><textarea rows={2} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></Field>}
            <div className="actions">
                {canSubmit && <button className="button" disabled={form.processing || !form.data.reason.trim()} onClick={() => send('submit')}>Ajukan baseline</button>}
                {canApprove && <>
                    <button className="button secondary" disabled={form.processing || !form.data.reason.trim()} onClick={() => send('return')}>Kembalikan ke draf</button>
                    <button className="button" disabled={form.processing} onClick={() => send('publish')}>Setujui dan terbitkan</button>
                </>}
            </div>
        </div>
    </Section>;
}

export default function Wbs(props: Props) {
    const base = organizationUrl(props.organization) + '/projects/' + props.project.id;
    const url = base + '/wbs';
    const [month, setMonth] = useState(props.period);
    const period = props.periods.find((record) => record.month === props.period);
    const cutoff = useForm({ period: props.period, cutoff_date: period?.cutoff_date ?? '' });
    const draftKey = props.draft?.id + ':' + props.draft?.version;
    const editable = props.can.manage && props.draft?.status !== 'submitted';

    return <Shell title="WBS dan baseline" organization={props.organization}
        actions={<Link className="button secondary" href={base}>Ringkasan proyek</Link>}>
        <Section title={props.project.number + ' · ' + props.project.name}>
            <div className="stack">
                <form className="grid-form items-end" onSubmit={(event) => {
                    event.preventDefault();
                    router.get(url, { period: month });
                }}>
                    <Field label="Periode rencana"><input type="month" required value={month} onChange={(event) => setMonth(event.target.value)} /></Field>
                    <div><button className="button secondary">Tampilkan periode</button></div>
                </form>
                {period?.official_baseline_id && <p className="help">Periode resmi memakai versi baseline yang tersimpan saat publikasi.</p>}
                {props.can.manage && !period?.official_baseline_id && <form className="grid-form items-end" onSubmit={(event) => {
                    event.preventDefault();
                    cutoff.put(url + '/cutoff', { preserveScroll: true });
                }}>
                    <Field label={'Tanggal cutoff · ' + props.period}><input type="date" value={cutoff.data.cutoff_date} onChange={(event) => cutoff.setData('cutoff_date', event.target.value)} /></Field>
                    <div><button className="button secondary" disabled={cutoff.processing}>Simpan cutoff</button></div>
                </form>}
                <Errors errors={cutoff.errors} />
            </div>
        </Section>
        <Section title="Baseline terbit" actions={props.published && <span className="pill published">Revisi {props.published.revision} · {props.published.effective_month}</span>}>
            {props.published ? <PlanTable plan={props.publishedPlan} /> : <p className="empty">Belum ada baseline terbit untuk periode ini.</p>}
            {props.history.length > 0 && <details className="mt-4"><summary>Riwayat versi terbit</summary>
                <ul className="stack">{props.history.map((version) => <li key={version.id}><Link href={url + '?period=' + props.period + '&revision=' + version.id}>
                    Revisi {version.revision} · berlaku {version.effective_month}
                </Link></li>)}</ul>
            </details>}
        </Section>
        {props.draft && <Review key={draftKey} props={props} url={url} />}
        {editable && props.can.access && !props.working?.nodes.length && <Template key={draftKey} props={props} url={url} />}
        {editable && <DraftEditor key={draftKey + ':' + props.period} props={props} url={url} selectedMonth={props.period} />}
        {props.can.access && <Readers key={String(props.project.wbs_restricted) + ':' + props.readers.join(',')} props={props} url={url} />}
    </Shell>;
}
