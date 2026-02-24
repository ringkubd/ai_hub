import { Head } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type AskResponse = {
    answer: string;
    conversation_id: string | null;
    usage?: Record<string, number>;
};

type Message = {
    role: 'user' | 'assistant';
    content: string;
    status?: string;
};

type ProgressEvent = {
    type: 'start' | 'progress' | 'response' | 'done' | 'error';
    message: string;
    content?: string;
    conversation_id?: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Projects AI', href: '/ai/projects' },
];

export default function ProjectsAiPage() {
    const [question, setQuestion] = useState('');
    const [conversationId, setConversationId] = useState<string | null>(null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [progressMessage, setProgressMessage] = useState('');
    const sessionIdRef = useRef<string>(crypto.randomUUID());
    const echo = useEcho();

    const canSubmit = useMemo(() => question.trim().length > 0 && !isLoading, [question, isLoading]);

    useEffect(() => {
        if (!echo) return;

        const channel = echo.private(`projects-ai.${sessionIdRef.current}`);

        channel.listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', () => {
            // Handled by specific event listener
        });

        channel.listen('ProjectsAgentProgress', (event: ProgressEvent) => {
            console.log('Progress event:', event);

            switch (event.type) {
                case 'start':
                case 'progress':
                    setProgressMessage(event.message);
                    break;

                case 'response':
                    if (event.content) {
                        setMessages((current) => {
                            const updated = [...current];
                            const lastIndex = updated.length - 1;
                            if (lastIndex >= 0 && updated[lastIndex].role === 'assistant') {
                                updated[lastIndex] = {
                                    role: 'assistant',
                                    content: event.content!,
                                    status: 'complete',
                                };
                            }
                            return updated;
                        });
                    }
                    if (event.conversation_id) {
                        setConversationId(event.conversation_id);
                    }
                    setProgressMessage('');
                    break;

                case 'done':
                    setIsLoading(false);
                    setProgressMessage('');
                    break;

                case 'error':
                    setError(event.message);
                    setMessages((current) => {
                        const updated = [...current];
                        const lastIndex = updated.length - 1;
                        if (lastIndex >= 0 && updated[lastIndex].role === 'assistant') {
                            updated[lastIndex] = {
                                role: 'assistant',
                                content: `Error: ${event.message}`,
                                status: 'error',
                            };
                        }
                        return updated;
                    });
                    setIsLoading(false);
                    setProgressMessage('');
                    break;
            }
        });

        return () => {
            channel.stopListening('ProjectsAgentProgress');
            echo.leave(`projects-ai.${sessionIdRef.current}`);
        };
    }, [echo]);

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        const prompt = question.trim();

        if (!prompt || isLoading) {
            return;
        }

        setError(null);
        setIsLoading(true);
        setProgressMessage('Starting...');
        setMessages((current) => [...current, { role: 'user', content: prompt }]);
        setQuestion('');

        // Add empty assistant message for real-time updates
        setMessages((current) => [...current, { role: 'assistant', content: '', status: 'thinking' }]);

        try {
            const response = await fetch('/ai/projects/ask', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (
                        document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
                    )?.content ?? '',
                },
                body: JSON.stringify({
                    question: prompt,
                    conversation_id: conversationId,
                    session_id: sessionIdRef.current,
                }),
            });

            const data = (await response.json()) as AskResponse | { message?: string };

            if (!response.ok) {
                throw new Error('message' in data && data.message ? data.message : 'Failed to get AI response.');
            }

            // Response is handled by WebSocket events, but fallback if no event received
            const result = data as AskResponse;
            
            setTimeout(() => {
                setMessages((current) => {
                    const lastMessage = current[current.length - 1];
                    if (lastMessage && lastMessage.role === 'assistant' && !lastMessage.content) {
                        const updated = [...current];
                        updated[updated.length - 1] = {
                            role: 'assistant',
                            content: result.answer,
                            status: 'complete',
                        };
                        setConversationId(result.conversation_id ?? null);
                        setIsLoading(false);
                        setProgressMessage('');
                        return updated;
                    }
                    return current;
                });
            }, 500);
        } catch (submitError) {
            const message = submitError instanceof Error ? submitError.message : 'Unexpected error.';
            setError(message);
            setMessages((current) => {
                const updated = [...current];
                const lastIndex = updated.length - 1;
                if (lastIndex >= 0 && updated[lastIndex].role === 'assistant') {
                    updated[lastIndex] = {
                        role: 'assistant',
                        content: `Error: ${message}`,
                        status: 'error',
                    };
                }
                return updated;
            });
            setIsLoading(false);
            setProgressMessage('');
        }
    };

    const resetConversation = () => {
        setConversationId(null);
        setMessages([]);
        setError(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects AI" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-lg font-semibold">Projects Database AI</h1>
                            <p className="text-sm text-muted-foreground">
                                Ask about Work Details, Work History, Invoice, and all Project tables/relations.
                            </p>
                        </div>
                        <Button variant="outline" type="button" onClick={resetConversation} disabled={isLoading}>
                            New Chat
                        </Button>
                    </div>
                </div>

                <div className="min-h-90 flex-1 space-y-3 overflow-y-auto rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    {messages.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Start by asking: “Show latest invoice totals by tender package” or “Explain WorkHistory relations”.
                        </p>
                    )}

                    {messages.map((message, index) => (
                        <div
                            key={`${message.role}-${index}`}
                            className={`max-w-3xl rounded-lg px-3 py-2 text-sm ${message.role === 'user'
                                    ? 'ml-auto bg-primary text-primary-foreground'
                                    : 'bg-muted text-foreground'
                                }`}
                        >
                            {message.content || (
                                <span className="inline-flex items-center gap-2 text-muted-foreground">
                                    <span className="flex gap-1">
                                        <span className="animate-bounce" style={{ animationDelay: '0ms' }}>●</span>
                                        <span className="animate-bounce" style={{ animationDelay: '150ms' }}>●</span>
                                        <span className="animate-bounce" style={{ animationDelay: '300ms' }}>●</span>
                                    </span>
                                    <span>Analyzing construction database...</span>
                                </span>
                            )}
                        </div>
                    ))}
                </div>

                <form onSubmit={submit} className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div className="flex gap-2">
                        <Input
                            value={question}
                            onChange={(event) => setQuestion(event.target.value)}
                            placeholder="Ask a question about Projects DB..."
                            disabled={isLoading}
                        />
                        <Button type="submit" disabled={!canSubmit}>
                            {isLoading ? 'Analyzing...' : 'Ask'}
                        </Button>
                    </div>

                    <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                        <span>{conversationId ? `Conversation: ${conversationId}` : 'No conversation yet'}</span>
                        {error && <span className="text-destructive">{error}</span>}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
