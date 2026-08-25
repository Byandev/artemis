import { router } from '@inertiajs/react';
import { putToBucket, readDuration, requestPresign } from './presign';

interface Options {
    /** Base URL of the lesson's video endpoints, without a trailing slash. */
    videoUrl: string;
    file: File;
    onProgress: (percentage: number) => void;
    onDone: () => void;
    onError: (message: string) => void;
}

/**
 * Uploads a lesson video straight to the bucket, then tells the app to adopt
 * the object. Falls back to posting through the app only when the disk cannot
 * sign an upload.
 */
export async function uploadLessonVideo({
    videoUrl,
    file,
    onProgress,
    onDone,
    onError,
}: Options): Promise<void> {
    try {
        onProgress(0);

        const durationSeconds = await readDuration(file);
        const presign = await requestPresign(
            `${videoUrl}/presign`,
            file,
            'video/mp4',
        );

        if (!presign.supported || !presign.url || !presign.key) {
            router.post(
                videoUrl,
                { video: file, duration_seconds: durationSeconds },
                {
                    forceFormData: true,
                    preserveScroll: true,
                    onProgress: (e) => e && onProgress(e.percentage ?? 0),
                    onFinish: onDone,
                },
            );
            return;
        }

        await putToBucket(presign.url, presign.headers ?? {}, file, onProgress);

        // The object is in the bucket but not yet the lesson's video; this is
        // what moves it into the media collection.
        router.post(
            `${videoUrl}/attach`,
            {
                key: presign.key,
                file_name: file.name,
                duration_seconds: durationSeconds,
            },
            { preserveScroll: true, onFinish: onDone },
        );
    } catch (e) {
        onDone();
        onError(e instanceof Error ? e.message : 'Upload failed.');
    }
}
