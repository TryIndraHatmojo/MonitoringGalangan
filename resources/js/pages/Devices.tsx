import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { ShieldOff } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { Section, Shell, formatDate } from '../ui';

type Device = { id: number; name: string; organization: string; device_id: string; expires_at: string; last_used_at: string | null };

export default function Devices({ devices }: { devices: Device[] }) {
    return <Shell title="Perangkat API">
        <Section title="Token perangkat">
            <DataTable data={devices} rowKey={(device) => String(device.id)} label="Token perangkat"
                empty="Belum ada token perangkat." columns={[
                    { id: 'device', header: 'Perangkat', cell: ({ row }) => <>
                        {row.original.name}<div className="help">{row.original.device_id}</div>
                    </> },
                    { accessorKey: 'organization', header: 'Organisasi' },
                    { id: 'lastUsed', header: 'Terakhir dipakai', cell: ({ row }) => formatDate(row.original.last_used_at) },
                    { id: 'expires', header: 'Kedaluwarsa', cell: ({ row }) => formatDate(row.original.expires_at) },
                    { id: 'actions', header: 'Aksi', cell: ({ row }) => <Button variant="destructive" onClick={() => {
                        if (window.confirm('Cabut akses API perangkat ini?')) router.delete('/devices/' + row.original.id, { preserveScroll: true });
                    }}><ShieldOff />Cabut token</Button> },
                ]} />
        </Section>
    </Shell>;
}
