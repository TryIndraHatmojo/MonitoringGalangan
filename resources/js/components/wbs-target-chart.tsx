import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { formatNumber } from '@/ui';

export type CategoryTarget = { code: string; name: string; value: number };

export function WbsTargetChart({ data }: { data: CategoryTarget[] }) {
    if (!data.length) return null;
    return <figure className="wbs-chart">
        <figcaption className="mb-2 px-1 font-semibold">Kontribusi target per kategori <span className="help">(poin persentase)</span></figcaption>
        <div style={{ height: Math.min(Math.max(data.length * 36 + 46, 156), 410) }} className="overflow-auto">
            <div style={{ height: Math.max(data.length * 36 + 46, 156) }}>
                <ResponsiveContainer width="100%" height="100%" minWidth={0}>
                    <BarChart data={data} layout="vertical" margin={{ top: 6, right: 15, bottom: 6, left: 0 }}>
                        <CartesianGrid horizontal={false} stroke="var(--border)" />
                        <YAxis type="category" dataKey="code" width={56} interval={0} axisLine={false} tickLine={false}
                            tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }} />
                        <XAxis type="number" domain={[0, 'auto']} axisLine={false} tickLine={false} allowDecimals={false}
                            tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }} tickFormatter={(value) => formatNumber(value, 0)} />
                        <Tooltip cursor={{ fill: 'var(--muted)' }} labelFormatter={(code) => {
                            const row = data.find((entry) => entry.code === code);
                            return row ? row.code + ' · ' + row.name : code;
                        }} formatter={(value) => [formatNumber(Number(value)) + ' pp', 'Kontribusi target']} />
                        <Bar dataKey="value" fill="var(--primary)" radius={[0, 5, 5, 0]} maxBarSize={17} isAnimationActive={false} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    </figure>;
}
