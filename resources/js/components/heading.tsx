export default function Heading({
    title,
    description,
}: {
    title: string;
    description?: string;
}) {
    return (
        <div className="pb-5 mb-6 border-b border-black/6 dark:border-white/6">
            <h1 className="text-[18px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">{title}</h1>
            {description && (
                <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">{description}</p>
            )}
        </div>
    );
}
