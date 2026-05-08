import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import * as Sentry from '@sentry/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import posthog from 'posthog-js';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';

if (import.meta.env.VITE_SENTRY_DSN) {
    Sentry.init({
        dsn: import.meta.env.VITE_SENTRY_DSN,
        environment:
            import.meta.env.VITE_SENTRY_ENVIRONMENT ?? import.meta.env.MODE,
        tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE ?? 0.1),
        integrations: [Sentry.browserTracingIntegration()],
    });
}

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

type SharedAuthUser = { id?: number | string; email?: string; name?: string };
type SharedWorkspace = { id?: number | string; slug?: string; name?: string };

const posthogToken = import.meta.env.VITE_POSTHOG_PROJECT_TOKEN;
const posthogDisabled = String(import.meta.env.VITE_POSTHOG_DISABLED ?? '').toLowerCase() === 'true';
let posthogReady = false;


if (posthogToken && !posthogDisabled) {
    posthog.init(posthogToken, {
        api_host: import.meta.env.VITE_POSTHOG_HOST || 'https://us.i.posthog.com',
        capture_pageview: false,
        capture_pageleave: true,
    });
}

const syncPosthogIdentity = (
    user?: SharedAuthUser | null,
    workspace?: SharedWorkspace | null,
) => {
    if (!posthogReady) return;

    if (user?.id) {
        posthog.identify(String(user.id), {
            email: user.email,
            name: user.name,
        });
    }

    if (workspace?.id) {
        posthog.group('workspace', String(workspace.id), {
            slug: workspace.slug,
            name: workspace.name,
        });
    }
};

createInertiaApp({
    title: (title) => formatTitle(title),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        if (posthogReady) {
            const sharedProps = props.initialPage.props as {
                auth?: { user?: SharedAuthUser | null };
                currentWorkspace?: SharedWorkspace | null;
            };

            syncPosthogIdentity(
                sharedProps.auth?.user,
                sharedProps.currentWorkspace,
            );
            posthog.capture('$pageview');

            router.on('navigate', (event) => {
                const navProps = event.detail.page.props as {
                    auth?: { user?: SharedAuthUser | null };
                    currentWorkspace?: SharedWorkspace | null;
                };

                syncPosthogIdentity(
                    navProps.auth?.user,
                    navProps.currentWorkspace,
                );
                posthog.capture('$pageview');
            });
        }

        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
