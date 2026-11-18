import { router } from '@inertiajs/react';
import { Section, Shell, formatDate } from '../ui';

type Device = { id: number; name: string; organization: string; device_id: string; expires_at: string; last_used_at: string | null };

export default function Devices({ devices }: { devices: Device[] }) {
    return <Shell title="Perangkat API">
        <Section title="Token perangkat">
            {devices.length ? <div className="table-shell"><table>
                <thead><tr><th>Perangkat</th><th>Organisasi</th><th>Terakhir dipakai</th><th>Kedaluwarsa</th><th>Aksi</th></tr></thead>
                <tbody>{devices.map((device) => <tr key={device.id}>
                    <td>{device.name}<div className="help">{device.device_id}</div></td><td>{device.organization}</td>
                    <td>{formatDate(device.last_used_at)}</td><td>{formatDate(device.expires_at)}</td>
                    <td><button className="button danger" onClick={() => {
                        if (window.confirm('Cabut akses API perangkat ini?')) router.delete('/devices/' + device.id, { preserveScroll: true });
                    }}>Cabut token</button></td>
                </tr>)}</tbody>
            </table></div> : <p className="empty">Belum ada token perangkat.</p>}
        </Section>
    </Shell>;
}
