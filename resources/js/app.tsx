import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

const pages = import.meta.glob<{ default: ComponentType }>('./pages/*.tsx');

void createInertiaApp({
    title: (page) => page ? page + ' · Monitoring Galangan' : 'Monitoring Galangan',
    resolve: (name) => pages['./pages/' + name + '.tsx']().then((page) => page.default),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#087c76' },
});
