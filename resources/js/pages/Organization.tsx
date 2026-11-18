import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AuditEvent, AuditList, Errors, Field, Organization, Section, Shell, formatDate, organizationUrl } from '../ui';

type Member = { id: string; name: string; email: string; role: string; active: boolean; account_active: boolean };
type Props = {
    organization: Organization; members: Member[];
    invitations: { email: string; role: string; expires_at: string }[];
    roles: Record<string, string>; catalog: Record<string, string>; permissions: Record<string, string[]>; audit: AuditEvent[];
};

function MemberRow({ member, roles, url }: { member: Member; roles: Props['roles']; url: string }) {
    const form = useForm({ role: member.role, active: Boolean(member.active) });
    return <tr><td>{member.name}<div className="help">{member.email}</div></td>
        <td><select aria-label={'Tipe pengguna ' + member.name} value={form.data.role} onChange={(event) => form.setData('role', event.target.value)}>
            {Object.entries(roles).map(([code, label]) => <option value={code} key={code}>{label}</option>)}
        </select></td>
        <td><label className="check"><input type="checkbox" checked={form.data.active} onChange={(event) => form.setData('active', event.target.checked)} /> Aktif di organisasi</label>{!member.account_active && <div className="help">Akun nonaktif</div>}</td>
        <td><Errors errors={form.errors} /><button className="button secondary" disabled={form.processing || !form.isDirty}
            onClick={() => form.patch(url + '/members/' + member.id, { preserveScroll: true, onSuccess: () => form.setDefaults() })}>Simpan</button></td>
    </tr>;
}

function PermissionEditor({ role, label, catalog, selected, url }: { role: string; label: string; catalog: Props['catalog']; selected: string[]; url: string }) {
    const form = useForm({ actions: selected });
    return <form className="stack" onSubmit={(event) => {
        event.preventDefault();
        form.put(url + '/permissions/' + role, { preserveScroll: true, onSuccess: () => form.setDefaults() });
    }}>
        <strong>{label}</strong><Errors errors={form.errors} />
        <div className="grid-form">{Object.entries(catalog).map(([action, title]) => <label className="check" key={action}>
            <input type="checkbox" disabled={role === 'administrator'} checked={form.data.actions.includes(action)}
                onChange={(event) => form.setData('actions', event.target.checked ? [...form.data.actions, action] : form.data.actions.filter((entry) => entry !== action))} />
            {title}
        </label>)}</div>
        {role !== 'administrator' && <div><button className="button" disabled={form.processing}>Simpan hak akses</button></div>}
    </form>;
}

export default function AccessPage({ organization, members, invitations, roles, catalog, permissions, audit }: Props) {
    const url = organizationUrl(organization) + '/access';
    const invite = useForm({ email: '', role: 'planner' });
    const [role, setRole] = useState('planner');

    return <Shell title="Hak akses organisasi" organization={organization} actions={<Link className="button secondary" href={organizationUrl(organization) + '/projects'}>Daftar proyek</Link>}>
        <Section title="Izin per tipe pengguna">
            <div className="stack">
                <Field label="Tipe pengguna"><select value={role} onChange={(event) => setRole(event.target.value)}>
                    {Object.entries(roles).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                </select></Field>
                <PermissionEditor key={role} role={role} label={roles[role]} selected={permissions[role]} catalog={catalog} url={url} />
            </div>
        </Section>
        <Section title="Anggota organisasi">
            {members.length ? <div className="table-shell"><table><thead><tr><th>Anggota</th><th>Tipe pengguna</th><th>Keanggotaan</th><th>Aksi</th></tr></thead>
                <tbody>{members.map((member) => <MemberRow key={member.id} member={member} roles={roles} url={url} />)}</tbody></table></div>
                : <p className="empty">Belum ada anggota organisasi.</p>}
        </Section>
        <Section title="Undang anggota">
            <Errors errors={invite.errors} />
            <form className="stack" onSubmit={(event) => { event.preventDefault(); invite.post(url + '/invitations', { preserveScroll: true, onSuccess: () => invite.reset('email') }); }}>
                <div className="grid-form">
                    <Field label="Alamat email"><input required type="email" value={invite.data.email} onChange={(event) => invite.setData('email', event.target.value)} /></Field>
                    <Field label="Tipe pengguna"><select value={invite.data.role} onChange={(event) => invite.setData('role', event.target.value)}>
                        {Object.entries(roles).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                    </select></Field>
                </div>
                <div><button className="button" disabled={invite.processing}>Kirim undangan</button></div>
            </form>
            {invitations.length > 0 && <div className="table-shell"><table><thead><tr><th>Undangan menunggu</th><th>Tipe pengguna</th><th>Kedaluwarsa</th></tr></thead>
                <tbody>{invitations.map((invitation) => <tr key={invitation.email}><td>{invitation.email}</td><td>{roles[invitation.role]}</td><td>{formatDate(invitation.expires_at)}</td></tr>)}</tbody>
            </table></div>}
        </Section>
        <Section title="Audit organisasi"><AuditList entries={audit} showChanges /></Section>
    </Shell>;
}
