import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { apiFetch, defaultHeaders } from '@/lib/api';

type Project = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    qdrant_collection?: string | null;
};

type OllamaTag = {
    name: string;
};

type ChatMessage = {
    role: 'system' | 'user' | 'assistant';
    content: string;
};

type ChatProps = {
    projects: Project[];
    models: OllamaTag[];
};

const storageKey = 'ollama.gateway.key';

export default function AiChat({ projects, models }: ChatProps) {
    const [messages, setMessages] = useState<ChatMessage[]>([
        { role: 'system', content: 'You are a helpful assistant.' },
    ]);
    const [input, setInput] = useState('');
    const [model, setModel] = useState(models[0]?.name ?? '');
    const [apiKey, setApiKey] = useState('');
    const [isSending, setIsSending] = useState(false);
    const [useStream, setUseStream] = useState(true);
    const [activeProject, setActiveProject] = useState<Project | null>(null);
    const scrollRef = useRef<HTMLDivElement>(null);

    const headers = useMemo(() => ({
        ...defaultHeaders(),
        ...(apiKey.trim() ? { Authorization: `Bearer ${apiKey.trim()}` } : {}),
    }), [apiKey]);

    useEffect(() => {
        const storedKey = window.localStorage.getItem(storageKey);
        if (storedKey) {
            setApiKey(storedKey);
        }
    }, []);

    useEffect(() => {
        window.localStorage.setItem(storageKey, apiKey);
    }, [apiKey]);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
        }, 50);

        return () => window.clearTimeout(timeout);
    }, [messages, isSending]);

    const resolveProjectFromText = (text: string) => {
        const match = text.match(/@([\w-]+)/i);
        if (!match) return { project: activeProject, cleaned: text };
        const slug = match[1].toLowerCase();
        const project =
            projects.find((item) => item.slug.toLowerCase() === slug) ??
            projects.find((item) => item.name.toLowerCase() === slug);
        const cleaned = text.replace(match[0], '').trim();
        return { project: project ?? activeProject, cleaned };
    };

    const buildSystemPrompt = (project: Project | null) => {
        if (!project) {
            return 'You are a helpful assistant. Answer across all available project knowledge.';
        }

        return `You are a helpful assistant for project "${project.name}". Answer using only this project's context. If context is missing, say so.`;
    };

    const sendMessage = async () => {
        if (!input.trim() || !model) return;

        const { project, cleaned } = resolveProjectFromText(input);
        const nextProject = project ?? null;
        if (nextProject?.slug !== activeProject?.slug) {
            setActiveProject(nextProject);
        }

        const userMessage = cleaned || input.trim();
        const systemPrompt = buildSystemPrompt(nextProject);
        const nextMessages: ChatMessage[] = [
            { role: 'system', content: systemPrompt },
            ...messages.filter((message) => message.role !== 'system'),
            { role: 'user', content: userMessage },
        ];

        setMessages(nextMessages);
        setInput('');
        setIsSending(true);

        const payload = {
            model,
            messages: nextMessages,
            stream: useStream,
        };

        try {
            const response = await fetch('/api/chat', {
                method: 'POST',
                headers,
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(`Request failed: ${response.status}`);
            }

            if (!useStream || !response.body) {
                const data = await response.json();
                setMessages((prev) => [
                    ...prev,
                    { role: 'assistant', content: data?.message?.content ?? '' },
                ]);
                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let assistantText = '';

            setMessages((prev) => [...prev, { role: 'assistant', content: '' }]);

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() ?? '';

                for (const line of lines) {
                    if (!line.trim()) continue;
                    try {
                        const chunk = JSON.parse(line);
                        const content = chunk?.message?.content ?? '';
                        assistantText += content;
                        setMessages((prev) => {
                            const updated = [...prev];
                            const lastIndex = updated.length - 1;
                            if (updated[lastIndex]?.role === 'assistant') {
                                updated[lastIndex] = { role: 'assistant', content: assistantText };
                            }
                            return updated;
                        });
                    } catch (error) {
                        console.error(error);
                    }
                }
            }
        } catch (error) {
            console.error(error);
        } finally {
            setIsSending(false);
        }
    };

    return (
        <AppLayout>
            <Head title="AI Chat" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-hidden rounded-3xl border border-slate-200/80 bg-[radial-gradient(circle_at_top,_#ecfeff,_#eef2ff_40%,_#fff7ed)] p-6 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">AI Hub Chat</h1>
                        <p className="text-sm text-slate-600">
                            Mention a project with @slug to focus responses.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <input
                            type="password"
                            className="w-60 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none"
                            placeholder="Gateway API key"
                            value={apiKey}
                            onChange={(event) => setApiKey(event.target.value)}
                        />
                        <select
                            className="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none"
                            value={model}
                            onChange={(event) => setModel(event.target.value)}
                        >
                            {models.map((tag) => (
                                <option key={tag.name} value={tag.name}>
                                    {tag.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[280px_1fr]">
                    <div className="flex flex-col gap-4 rounded-3xl border border-white/70 bg-white/80 p-4 shadow-sm">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Projects</p>
                            <p className="mt-1 text-sm text-slate-700">
                                Active: {activeProject ? `@${activeProject.slug}` : 'Global'}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => setActiveProject(null)}
                                className={`rounded-full border px-3 py-1 text-xs font-semibold ${
                                    !activeProject
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-200 bg-white text-slate-600'
                                }`}
                            >
                                Global
                            </button>
                            {projects.map((project) => (
                                <button
                                    key={project.id}
                                    type="button"
                                    onClick={() => setActiveProject(project)}
                                    className={`rounded-full border px-3 py-1 text-xs font-semibold ${
                                        activeProject?.id === project.id
                                            ? 'border-slate-900 bg-slate-900 text-white'
                                            : 'border-slate-200 bg-white text-slate-600'
                                    }`}
                                >
                                    @{project.slug}
                                </button>
                            ))}
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-3 text-xs text-slate-600">
                            Qdrant sync runs separately. Project context will grow as data sync completes.
                        </div>

                        <button
                            type="button"
                            className={`rounded-2xl border px-3 py-2 text-xs font-semibold ${
                                useStream
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-600'
                            }`}
                            onClick={() => setUseStream((prev) => !prev)}
                        >
                            {useStream ? 'Streaming enabled' : 'Streaming disabled'}
                        </button>
                    </div>

                    <div className="flex flex-col gap-4 rounded-3xl border border-white/70 bg-white/80 p-4 shadow-sm">
                        <div ref={scrollRef} className="flex h-[420px] flex-col gap-4 overflow-y-auto rounded-2xl bg-white p-4 shadow-inner">
                            {messages
                                .filter((message) => message.role !== 'system')
                                .map((message, index) => (
                                    <div
                                        key={`${message.role}-${index}`}
                                        className={`max-w-[80%] rounded-2xl px-4 py-3 text-sm shadow-sm ${
                                            message.role === 'user'
                                                ? 'ml-auto bg-slate-900 text-white'
                                                : 'mr-auto bg-slate-100 text-slate-700'
                                        }`}
                                    >
                                        {message.content}
                                    </div>
                                ))}
                        </div>

                        <div className="flex items-end gap-3">
                            <textarea
                                className="min-h-[56px] flex-1 resize-none rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                value={input}
                                onChange={(event) => setInput(event.target.value)}
                                placeholder="Ask something…"
                            />
                            <button
                                type="button"
                                onClick={sendMessage}
                                disabled={!input.trim() || !model || isSending}
                                className="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {isSending ? 'Sending…' : 'Send'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
