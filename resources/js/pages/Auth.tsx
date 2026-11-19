import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { useInertiaForm } from '@/lib/inertia-form';
import { Head, Link } from '@inertiajs/react';
import { Anchor, ArrowRight, KeyRound } from 'lucide-react';
import { z } from 'zod';
import { emailAddress } from '@/lib/validation';
import { Errors, Field, Notice, Section, useShared } from '../ui';

type Props = {
    mode: 'login' | 'forgot' | 'reset' | 'invite';
    email?: string; token?: string; action?: string; invitedOrganization?: string; newAccount?: boolean; signedIn?: boolean;
};

export default function Auth({ mode, email, token, action, invitedOrganization, newAccount, signedIn }: Props) {
    const { notice } = useShared();
    const titles = { login: 'Masuk', forgot: 'Reset kata sandi', reset: 'Buat kata sandi baru', invite: 'Terima undangan' };
    const confirmation = mode === 'reset' || (mode === 'invite' && Boolean(newAccount));
    const needsPassword = mode !== 'forgot' && !signedIn;
    const schema = z.object({
        name: mode === 'invite' && newAccount ? z.string().refine((name) => name.trim().length > 0, 'Isi nama lengkap.').max(255) : z.string(),
        email: mode === 'invite' ? z.string() : emailAddress,
        token: z.string(),
        password: confirmation ? z.string().min(12, 'Gunakan minimal 12 karakter.').max(72, 'Gunakan maksimal 72 karakter.')
            : needsPassword ? z.string().min(1, 'Isi kata sandi.').max(255) : z.string(),
        password_confirmation: z.string(),
    }).refine((data) => !confirmation || data.password === data.password_confirmation, {
        path: ['password_confirmation'], message: 'Konfirmasi kata sandi berbeda.',
    });
    const form = useInertiaForm({ name: '', email: email ?? '', token: token ?? '', password: '', password_confirmation: '' }, schema);

    return <div className="auth-page">
        <Head title={titles[mode]} />
        <div className="auth-intro">
            <span className="brand-icon"><Anchor aria-hidden="true" /></span>
            <h1>Monitoring Galangan</h1>
            <p>Kelola proyek pembangunan kapal, struktur pekerjaan, dan baseline rencana.</p>
        </div>
        <main className="auth-box">
            <Section title={titles[mode]}>
                {notice && <Notice>{notice}</Notice>}
                <Errors errors={form.errors} />
                <form className="stack" noValidate onSubmit={(event) => {
                    event.preventDefault();
                    form.post(action ?? { login: '/login', forgot: '/forgot-password', reset: '/reset-password', invite: '' }[mode], {
                        onFinish: () => form.reset('password', 'password_confirmation'),
                    });
                }}>
                    {mode === 'invite' && <p>Bergabung dengan <strong>{invitedOrganization}</strong> sebagai {email}.</p>}
                    {mode === 'invite' && newAccount && <Field label="Nama lengkap"><Input autoComplete="name" required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>}
                    {mode !== 'invite' && <Field label="Email"><Input type="email" autoComplete="email" required value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></Field>}
                    {mode !== 'forgot' && !signedIn && <Field label="Kata sandi"><Input type="password" required autoComplete={confirmation ? 'new-password' : 'current-password'} value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} /></Field>}
                    {confirmation && <Field label="Konfirmasi kata sandi"><Input type="password" required autoComplete="new-password" minLength={12} value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} /></Field>}
                    <Button disabled={form.processing}>{form.processing ? 'Memproses…' : mode === 'forgot' ? 'Kirim tautan' : titles[mode]}<ArrowRight /></Button>
                    {mode === 'login' ? <Link className="flex items-center gap-2" href="/forgot-password"><KeyRound size={15} />Lupa kata sandi?</Link> : mode !== 'invite' && <Link href="/login">Kembali ke halaman masuk</Link>}
                </form>
            </Section>
        </main>
    </div>;
}
