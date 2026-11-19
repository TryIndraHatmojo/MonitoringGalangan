import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useInertiaForm } from '@/lib/inertia-form';
import { useState } from 'react';
import { Send } from 'lucide-react';
import { z } from 'zod';
import { emailAddress } from '@/lib/validation';
import { AuditEvent, AuditList, Errors, Field, Organization, Section, Shell, formatDate, organizationUrl } from '../ui';

type Member = { id: string; name: string; email: string; role: string; active: boolean; account_active: boolean };
type UsersProps = {
    section: 'users'; organization: Organization; roles: Record<string, string>; members: Member[];
    invitations: { email: string; role: string; expires_at: string }[];
};
type RolesProps = {
    section: 'roles'; organization: Organization; roles: Record<string, string>;
    catalog: Record<string, string>; permissions: Record<string, string[]>; audit: AuditEvent[];
};

function MemberRow({ member, roles, url }: { member: Member; roles: UsersProps['roles']; url: string }) {
    const form = useInertiaForm({ role: member.role, active: Boolean(member.active) }, z.object({
        role: z.string().refine((value) => Object.hasOwn(roles, value), 'Pilih tipe pengguna.'),
        active: z.boolean(),
    }));
    return <TableRow><TableCell>{member.name}<div className="help">{member.email}</div></TableCell>
        <TableCell><NativeSelect aria-label={'Tipe pengguna ' + member.name} value={form.data.role} onChange={(event) => form.setData('role', event.target.value)}>
            {Object.entries(roles).map(([code, label]) => <option value={code} key={code}>{label}</option>)}
        </NativeSelect></TableCell>
        <TableCell><label className="check"><input type="checkbox" checked={form.data.active} onChange={(event) => form.setData('active', event.target.checked)} /> Aktif di organisasi</label>{!member.account_active && <div className="help">Akun nonaktif</div>}</TableCell>
        <TableCell><Errors errors={form.errors} /><Button variant="outline" disabled={form.processing || !form.isDirty}
            onClick={() => form.patch(url + '/members/' + member.id, { preserveScroll: true, onSuccess: () => form.setDefaults() })}>Simpan</Button></TableCell>
    </TableRow>;
}

function PermissionEditor({ role, label, catalog, selected, url }: { role: string; label: string; catalog: RolesProps['catalog']; selected: string[]; url: string }) {
    const form = useInertiaForm({ actions: selected }, z.object({
        actions: z.array(z.string().refine((action) => Object.hasOwn(catalog, action), 'Izin tidak tersedia.')),
    }));
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
        {role !== 'administrator' && <div><Button disabled={form.processing}>Simpan hak akses</Button></div>}
    </form>;
}

function Roles({ organization, roles, catalog, permissions, audit }: RolesProps) {
    const url = organizationUrl(organization) + '/access';
    const [role, setRole] = useState('planner');

    return <Shell title="Role" organization={organization} destination="org_roles">
        <Section title="Izin per tipe pengguna">
            <div className="stack">
                <Field label="Tipe pengguna"><NativeSelect value={role} onChange={(event) => setRole(event.target.value)}>
                    {Object.entries(roles).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                </NativeSelect></Field>
                <PermissionEditor key={role} role={role} label={roles[role]} selected={permissions[role]} catalog={catalog} url={url} />
            </div>
        </Section>
        <Section title="Audit organisasi"><AuditList entries={audit} showChanges /></Section>
    </Shell>;
}

function Users({ organization, members, invitations, roles }: UsersProps) {
    const url = organizationUrl(organization) + '/access';
    const invite = useInertiaForm({ email: '', role: 'planner' }, z.object({
        email: emailAddress, role: z.string().refine((value) => Object.hasOwn(roles, value), 'Pilih tipe pengguna.'),
    }));

    return <Shell title="User" organization={organization} destination="org_users">
        <Section title="Anggota organisasi">
            {members.length ? <Table aria-label="Anggota organisasi"><TableHeader><TableRow>
                <TableHead scope="col">Anggota</TableHead><TableHead scope="col">Tipe pengguna</TableHead><TableHead scope="col">Keanggotaan</TableHead><TableHead scope="col">Aksi</TableHead>
            </TableRow></TableHeader><TableBody>{members.map((member) => <MemberRow key={member.id} member={member} roles={roles} url={url} />)}</TableBody></Table>
                : <p className="empty">Belum ada anggota organisasi.</p>}
        </Section>
        <Section title="Undang anggota">
            <Errors errors={invite.errors} />
            <form className="stack" onSubmit={(event) => { event.preventDefault(); invite.post(url + '/invitations', { preserveScroll: true, onSuccess: () => invite.reset('email') }); }}>
                <div className="grid-form">
                    <Field label="Alamat email"><Input required type="email" value={invite.data.email} onChange={(event) => invite.setData('email', event.target.value)} /></Field>
                    <Field label="Tipe pengguna"><NativeSelect value={invite.data.role} onChange={(event) => invite.setData('role', event.target.value)}>
                        {Object.entries(roles).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                    </NativeSelect></Field>
                </div>
                <div><Button disabled={invite.processing}><Send />Kirim undangan</Button></div>
            </form>
            {invitations.length > 0 && <div className="mt-4"><DataTable data={invitations} rowKey={(invitation) => invitation.email}
                label="Undangan menunggu" columns={[
                    { accessorKey: 'email', header: 'Undangan menunggu' },
                    { id: 'role', header: 'Tipe pengguna', cell: ({ row }) => roles[row.original.role] },
                    { id: 'expires', header: 'Kedaluwarsa', cell: ({ row }) => formatDate(row.original.expires_at) },
                ]} /></div>}
        </Section>
    </Shell>;
}

export default function AccessPage(props: RolesProps | UsersProps) {
    return props.section === 'roles' ? <Roles {...props} /> : <Users {...props} />;
}
