import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';

const appName = 'Artemis';

const formatTitle = (rawTitle?: string | null) => {
    if (!rawTitle) {
        return appName;
    }

    let title = rawTitle.trim();
    const prefixPattern = new RegExp(`^${appName}\s*[\-|—|\|]\s*`, 'i');
    const suffixPattern = new RegExp(`\s*[\-|—|\|]\s*${appName}$`, 'i');

    title = title.replace(prefixPattern, '').replace(suffixPattern, '').trim();
    title = title
        .replace(/\s+—\s+/g, ' | ')
        .replace(/\s+-\s+/g, ' | ')
        .replace(/\s+\|\s+/g, ' | ')
        .replace(/\s{2,}/g, ' ')
        .trim();

    return title ? `${appName} | ${title}` : appName;
};

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => formatTitle(title),
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.tsx`,
                import.meta.glob('./pages/**/*.tsx'),
            ),
        setup: ({ App, props }) => {
            return <App {...props} />;
        },
    }),
);
