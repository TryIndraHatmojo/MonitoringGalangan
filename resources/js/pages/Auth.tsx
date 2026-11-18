import { Head, Link, useForm } from '@inertiajs/react';
import { Errors, Field, Section, useShared } from '../ui';

type Props = {
    mode: 'login' | 'forgot' | 'reset' | 'invite';
    email?: string; token?: string; action?: string; invitedOrganization?: string; newAccount?: boolean; signedIn?: boolean;
};

export default function Auth({ mode, email, token, action, invitedOrganization, newAccount, signedIn }: Props) {
    const { notice } = useShared();
    const form = useForm({ name: '', email: email ?? '', token: token ?? '', password: '', password_confirmation: '' });
    const titles = { login: 'Masuk', forgot: 'Reset kata sandi', reset: 'Buat kata sandi baru', invite: 'Terima undangan' };
    const confirmation = mode === 'reset' || (mode === 'invite' && newAccount);

    return <div className="auth-page">
        <Head title={titles[mode]} />
        <div className="auth-intro">
            <span className="brand-icon" aria-hidden="true">MG</span>
            <h1>Monitoring Galangan</h1>
            <p>Kelola proyek pembangunan kapal, struktur pekerjaan, dan baseline rencana.</p>
        </div>
        <main className="auth-box">
            <Section title={titles[mode]}>
                {notice && <p className="notice" role="status">{notice}</p>}
                <Errors errors={form.errors} />
                <form className="stack" onSubmit={(event) => {
                    event.preventDefault();
                    form.post(action ?? { login: '/login', forgot: '/forgot-password', reset: '/reset-password', invite: '' }[mode], {
                        onFinish: () => form.reset('password', 'password_confirmation'),
                    });
                }}>
                    {mode === 'invite' && <p>Bergabung dengan <strong>{invitedOrganization}</strong> sebagai {email}.</p>}
                    {mode === 'invite' && newAccount && <Field label="Nama lengkap"><input autoComplete="name" required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>}
                    {mode !== 'invite' && <Field label="Email"><input type="email" autoComplete="email" required value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></Field>}
                    {mode !== 'forgot' && !signedIn && <Field label="Kata sandi"><input type="password" required autoComplete={confirmation ? 'new-password' : 'current-password'} value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} /></Field>}
                    {confirmation && <Field label="Konfirmasi kata sandi"><input type="password" required autoComplete="new-password" minLength={12} value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} /></Field>}
                    <button className="button" disabled={form.processing}>{form.processing ? 'Memproses…' : mode === 'forgot' ? 'Kirim tautan' : titles[mode]}</button>
                    {mode === 'login' ? <Link href="/forgot-password">Lupa kata sandi?</Link> : mode !== 'invite' && <Link href="/login">Kembali ke halaman masuk</Link>}
                </form>
            </Section>
        </main>
    </div>;
}
