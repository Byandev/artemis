import { useCallback, useEffect, useState } from 'react';
import { useTaskRoutes } from '../components/task-workspace';
import { api } from '../lib/api';
import type { Attachment } from '../types';

type Wrapped<T> = { data: T };

/**
 * Reads and writes one task's files through the same JSON endpoints an
 * automated client uses, so there is no page-only data path.
 *
 * Uploads go one file per request and fold the server's copy into local state,
 * which means a batch that fails halfway keeps the files that did land.
 */
export function useTaskAttachments(
    taskId: number,
    report: (cause: unknown) => void,
) {
    const routes = useTaskRoutes();
    const { tasks, attachments } = routes;
    const [list, setList] = useState<Attachment[]>([]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);

    useEffect(() => {
        let active = true;

        setLoading(true);

        api<Wrapped<Attachment[]>>(tasks.attachments.index(taskId))
            .then((response) => {
                if (active) {
                    setList(response.data);
                }
            })
            .catch(report)
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [tasks, taskId, report]);

    const upload = useCallback(
        async (files: File[]): Promise<void> => {
            if (files.length === 0) {
                return;
            }

            setUploading(true);

            try {
                for (const file of files) {
                    const form = new FormData();
                    form.append('file', file);

                    try {
                        const response = await api<Wrapped<Attachment>>(
                            tasks.attachments.store(taskId),
                            form,
                        );

                        setList((current) => [...current, response.data]);
                    } catch (cause) {
                        report(cause);
                    }
                }
            } finally {
                setUploading(false);
            }
        },
        [tasks, taskId, report],
    );

    const remove = useCallback(
        async (attachment: Attachment) => {
            try {
                await api(attachments.destroy(attachment.id));

                setList((current) =>
                    current.filter((item) => item.id !== attachment.id),
                );
            } catch (cause) {
                report(cause);
            }
        },
        [attachments, report],
    );

    return { attachments: list, loading, uploading, upload, remove };
}
