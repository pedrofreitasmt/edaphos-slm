import ReactMarkdown, { type Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';

interface MarkdownMessageProps {
    content: string;
}

const markdownComponents: Components = {
    h1: ({ children }) => (
        <h1 className="mt-3 mb-1.5 text-sm font-bold text-stone-900 first:mt-0 dark:text-white">
            {children}
        </h1>
    ),
    h2: ({ children }) => (
        <h2 className="mt-3 mb-1.5 text-xs font-bold text-stone-900 first:mt-0 dark:text-white">
            {children}
        </h2>
    ),
    h3: ({ children }) => (
        <h3 className="mt-2.5 mb-1 text-xs font-bold text-emerald-800 first:mt-0 dark:text-emerald-300">
            {children}
        </h3>
    ),
    p: ({ children }) => (
        <p className="mb-2 leading-relaxed last:mb-0">{children}</p>
    ),
    ul: ({ children }) => (
        <ul className="mb-2 ml-4 list-disc space-y-1 last:mb-0">{children}</ul>
    ),
    ol: ({ children }) => (
        <ol className="mb-2 ml-4 list-decimal space-y-1 last:mb-0">
            {children}
        </ol>
    ),
    li: ({ children }) => <li className="leading-relaxed">{children}</li>,
    strong: ({ children }) => (
        <strong className="font-semibold text-stone-950 dark:text-white">
            {children}
        </strong>
    ),
    em: ({ children }) => (
        <em className="italic text-stone-700 dark:text-stone-300">
            {children}
        </em>
    ),
    blockquote: ({ children }) => (
        <blockquote className="my-2 border-l-2 border-emerald-500/60 bg-emerald-500/5 py-1 pr-2 pl-3 text-stone-700 dark:text-stone-300">
            {children}
        </blockquote>
    ),
    hr: () => (
        <hr className="my-2.5 border-stone-200 dark:border-stone-700/80" />
    ),
    code: ({ className, children }) => {
        const isBlock = Boolean(className?.includes('language-'));

        if (isBlock) {
            return (
                <code className="block overflow-x-auto rounded-lg bg-stone-900 p-2.5 font-mono text-[11px] text-stone-100 dark:bg-stone-950">
                    {children}
                </code>
            );
        }

        return (
            <code className="rounded bg-stone-100 px-1 py-0.5 font-mono text-[11px] text-emerald-800 dark:bg-stone-800 dark:text-emerald-300">
                {children}
            </code>
        );
    },
    pre: ({ children }) => <pre className="my-2 last:mb-0">{children}</pre>,
    table: ({ children }) => (
        <div className="my-2 overflow-x-auto rounded-lg border border-stone-200 dark:border-stone-700">
            <table className="w-full border-collapse text-left text-[11px]">
                {children}
            </table>
        </div>
    ),
    thead: ({ children }) => (
        <thead className="bg-stone-100 text-stone-800 dark:bg-stone-800 dark:text-stone-200">
            {children}
        </thead>
    ),
    th: ({ children }) => (
        <th className="border-b border-stone-200 px-2.5 py-1.5 font-semibold dark:border-stone-700">
            {children}
        </th>
    ),
    td: ({ children }) => (
        <td className="border-t border-stone-200 px-2.5 py-1.5 dark:border-stone-800">
            {children}
        </td>
    ),
    a: ({ href, children }) => (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="font-medium text-emerald-700 underline hover:text-emerald-600 dark:text-emerald-400"
        >
            {children}
        </a>
    ),
};

export function MarkdownMessage({ content }: MarkdownMessageProps) {
    return (
        <div className="prose-sm max-w-none break-words">
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={markdownComponents}
            >
                {content}
            </ReactMarkdown>
        </div>
    );
}
