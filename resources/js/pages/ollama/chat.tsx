import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { apiFetch, defaultHeaders } from '@/lib/api';

type ChatMessage = {
    role: 'system' | 'user' | 'assistant';
    content: string;
};

type OllamaTag = {
    name: string;
};

const defaultMessages: ChatMessage[] = [
    { role: 'system', content: 'You are a helpful assistant.' },
];

const storageKey = 'ollama.gateway.key';

export default function OllamaChat() {
    const [messages, setMessages] = useState<ChatMessage[]>(defaultMessages);
    const [input, setInput] = useState('');
    const [model, setModel] = useState('');
    const [models, setModels] = useState<OllamaTag[]>([]);
    const [systemPrompt, setSystemPrompt] = useState(defaultMessages[0].content);
    const [apiKey, setApiKey] = useState('');
    const [isLoadingModels, setIsLoadingModels] = useState(false);
    const [isSending, setIsSending] = useState(false);
    const [useStream, setUseStream] = useState(true);
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

    useEffect(() => {
        setMessages((prev) => [{ role: 'system', content: systemPrompt }, ...prev.slice(1)]);
    }, [systemPrompt]);

    const fetchModels = async () => {
        setIsLoadingModels(true);
        try {
            const response = await apiFetch('/api/tags', { headers });
            if (!response.ok) {
                throw new Error('Failed to load models.');
            }
            const data = await response.json();
            const tags = Array.isArray(data?.models) ? data.models : [];
            setModels(tags);
            if (!model && tags.length > 0) {
                setModel(tags[0].name);
            }
        } catch (error) {
            console.error(error);
        } finally {
            setIsLoadingModels(false);
        }
    };

    useEffect(() => {
        if (apiKey.trim() || headers.Authorization === undefined) {
            fetchModels();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [apiKey]);

    const sendMessage = async () => {
        const trimmed = input.trim();
        if (!trimmed || !model) return;

        const nextMessages: ChatMessage[] = [
            { role: 'system', content: systemPrompt },
            ...messages.slice(1),
            { role: 'user', content: trimmed },
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
            const response = await apiFetch('/api/chat', {
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
            <Head title="Ollama Chat" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-hidden rounded-2xl border border-sidebar-border/60 bg-gradient-to-br from-white via-slate-50 to-slate-100 p-6 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Ollama Gateway Chat</h1>
                        <p className="text-sm text-slate-500">Stream responses through your Laravel gateway.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <input
                            type="password"
                            className="w-56 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none"
                            placeholder="Gateway API key"
                            value={apiKey}
                            onChange={(event) => setApiKey(event.target.value)}
                        />
                        <button
                            type="button"
                            onClick={fetchModels}
                            className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:border-slate-300 hover:bg-slate-50"
                        >
                            {isLoadingModels ? 'Loading…' : 'Refresh models'}
                        </button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
                    <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Model</label>
                        <select
                            className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            value={model}
                            onChange={(event) => setModel(event.target.value)}
                        >
                            <option value="" disabled>
                                Select a model
                            </option>
                            {models.map((tag) => (
                                <option key={tag.name} value={tag.name}>
                                    {tag.name}
                                </option>
                            ))}
                        </select>

                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">System prompt</label>
                        <textarea
                            className="min-h-[120px] rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            value={systemPrompt}
                            onChange={(event) => setSystemPrompt(event.target.value)}
                        />

                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Streaming</label>
                        <button
                            type="button"
                            className={`rounded-lg border px-3 py-2 text-sm font-medium ${useStream
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-600'
                                }`}
                            onClick={() => setUseStream((prev) => !prev)}
                        >
                            {useStream ? 'Streaming enabled' : 'Streaming disabled'}
                        </button>
                    </div>

                    <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div ref={scrollRef} className="flex h-[420px] flex-col gap-4 overflow-y-auto rounded-xl bg-slate-50 p-4">
                            {messages
                                .filter((message) => message.role !== 'system')
                                .map((message, index) => (
                                    <div
                                        key={`${message.role}-${index}`}
                                        className={`rounded-xl px-4 py-3 text-sm shadow-sm ${message.role === 'user'
                                                ? 'ml-auto bg-slate-900 text-white'
                                                : 'mr-auto bg-white text-slate-700'
                                            }`}
                                    >
                                        {message.content}
                                    </div>
                                ))}
                        </div>

                        <div className="flex items-end gap-3">
                            <textarea
                                className="min-h-[56px] flex-1 resize-none rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                value={input}
                                onChange={(event) => setInput(event.target.value)}
                                placeholder="Ask something..."
                            />
                            <button
                                type="button"
                                onClick={sendMessage}
                                disabled={!input.trim() || !model || isSending}
                                className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
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
