import { Field, projectStatus } from './ui';

export const emptyProject = {
    number: '', name: '', ship_name: '', ship_type: '', tonnage_gt: '', owner: '', shipyard: '',
    start_date: '', target_date: '', contract_amount: '', currency: '', status: 'active',
};
export type ProjectInput = typeof emptyProject;
export type Project = { id: string; manager_id: string | null; baseline_approver_id: string | null } &
    Omit<ProjectInput, 'contract_amount' | 'currency' | 'tonnage_gt'> &
    { contract_amount?: string | null; currency?: string | null; tonnage_gt?: string | number | null };

export function ProjectFields({ data, set, contract }: {
    data: ProjectInput; set: (key: keyof ProjectInput, value: string) => void; contract: boolean;
}) {
    const fields: { key: keyof ProjectInput; label: string; type?: string; required?: boolean }[] = [
        { key: 'number', label: 'Nomor proyek', required: true }, { key: 'name', label: 'Nama proyek', required: true },
        { key: 'ship_name', label: 'Nama kapal', required: true }, { key: 'ship_type', label: 'Jenis kapal', required: true },
        { key: 'owner', label: 'Pemilik kapal', required: true }, { key: 'shipyard', label: 'Galangan', required: true },
        { key: 'tonnage_gt', label: 'Tonase kotor (GT)', type: 'number' },
        { key: 'start_date', label: 'Tanggal mulai', type: 'date', required: true },
        { key: 'target_date', label: 'Target selesai', type: 'date', required: true },
    ];
    if (contract) fields.push({ key: 'contract_amount', label: 'Nilai kontrak', type: 'number' }, { key: 'currency', label: 'Mata uang (mis. IDR)' });
    return <div className="grid-form">
        {fields.map(({ key, label, type, required }) => <Field key={key} label={label}>
            <input required={required} type={type ?? 'text'} min={type === 'number' ? 0 : undefined}
                step={type === 'number' ? key === 'contract_amount' ? '0.01' : '0.001' : undefined}
                maxLength={key === 'currency' ? 3 : undefined}
                value={data[key]} onChange={(event) => set(key, key === 'currency' ? event.target.value.toUpperCase() : event.target.value)} />
        </Field>)}
        <Field label="Status"><select value={data.status} onChange={(event) => set('status', event.target.value)}>
            {Object.entries(projectStatus).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
        </select></Field>
    </div>;
}
