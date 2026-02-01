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
    sessions: {
        id: number;
        title?: string | null;
        project_id?: number | null;
        model?: string | null;
        last_message_at?: string | null;
    }[];
};

export default function AiChat({ projects, models, sessions }: ChatProps) {
    const [messages, setMessages] = useState<ChatMessage[]>([
        { role: 'system', content: 'You are a helpful assistant.' },
    ]);
    const [input, setInput] = useState('');
    const [model, setModel] = useState(models[0]?.name ?? '');
    const [isSending, setIsSending] = useState(false);
    const [useStream, setUseStream] = useState(true);
    const [activeProject, setActiveProject] = useState<Project | null>(null);
    const [sessionList, setSessionList] = useState(sessions);
    const [activeSession, setActiveSession] = useState<number | null>(sessions[0]?.id ?? null);
    const scrollRef = useRef<HTMLDivElement>(null);

    const headers = useMemo(() => defaultHeaders(), []);

    useEffect(() => {
        apiFetch('/ai/chat/sessions')
            .then((response) => response.json())
            .then((data) => {
                if (Array.isArray(data?.sessions)) {
                    setSessionList(data.sessions);
                }
            })
            .catch(() => {});
    }, []);

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

        try {
            const response = await apiFetch('/ai/chat/send', {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    session_id: activeSession,
                    project_id: nextProject?.id ?? null,
                    model,
                    message: userMessage,
                }),
            });

            if (!response.ok) {
                throw new Error(`Request failed: ${response.status}`);
            }

            const data = await response.json();
            setActiveSession(data.session_id);
            setMessages((prev) => [
                ...prev,
                { role: 'assistant', content: data?.assistant ?? '' },
            ]);
            apiFetch('/ai/chat/sessions')
                .then((res) => res.json())
                .then((payload) => {
                    if (Array.isArray(payload?.sessions)) {
                        setSessionList(payload.sessions);
                    }
                })
                .catch(() => {});
        } catch (error) {
            console.error(error);
        } finally {
            setIsSending(false);
        }
    };

    const loadSessionMessages = async (sessionId: number) => {
        const session = sessionList.find((item) => item.id === sessionId);
        if (session?.project_id) {
            const project = projects.find((item) => item.id === session.project_id) ?? null;
            setActiveProject(project);
        } else {
            setActiveProject(null);
        }
        const response = await apiFetch(`/ai/chat/sessions/${sessionId}/messages`);
        const data = await response.json();
        if (response.ok) {
            const history = Array.isArray(data?.messages) ? data.messages : [];
            setMessages([
                { role: 'system', content: buildSystemPrompt(activeProject) },
                ...history.map((item: { role: ChatMessage['role']; content: string }) => ({
                    role: item.role,
                    content: item.content,
                })),
            ]);
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
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Sessions</p>
                            <div className="mt-2 flex flex-col gap-2">
                                {sessionList.map((session) => (
                                    <button
                                        key={session.id}
                                        type="button"
                                        onClick={() => {
                                            setActiveSession(session.id);
                                            loadSessionMessages(session.id);
                                        }}
                                        className={`rounded-2xl border px-3 py-2 text-left text-xs font-semibold ${
                                            activeSession === session.id
                                                ? 'border-slate-900 bg-slate-900 text-white'
                                                : 'border-slate-200 bg-white text-slate-600'
                                        }`}
                                    >
                                        {session.title ?? `Session ${session.id}`}
                                    </button>
                                ))}
                            </div>
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
