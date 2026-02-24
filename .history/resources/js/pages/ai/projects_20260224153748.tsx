import { Head } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useMemo, useRef, useState, useEffect } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { MessageSquare, Plus, Trash2 } from 'lucide-react';

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

type Conversation = {
    id: string;
    title: string;
    created_at: string;
    updated_at: string;
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
    const [conversations, setConversations] = useState<Conversation[]>([]);
    const [showSidebar, setShowSidebar] = useState(true);
    const sessionIdRef = useRef<string>(crypto.randomUUID());
    const messagesEndRef = useRef<HTMLDivElement>(null);

    const canSubmit = useMemo(() => question.trim().length > 0 && !isLoading, [question, isLoading]);

    // Load conversations list
    useEffect(() => {
        loadConversations();
    }, []);

    // Auto-scroll to bottom
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const loadConversations = async () => {
        try {
            const response = await fetch('/ai/conversations', {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json();
            setConversations(data.conversations || []);
        } catch {
            // Silently fail
        }
    };

    const loadConversation = async (id: string) => {
        try {
            const response = await fetch(`/ai/conversations/${id}`, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json();
            setConversationId(id);
            setMessages(
                data.messages.map((msg: any) => ({
                    role: msg.role,
                    content: msg.content,
                    status: 'complete',
                })),
            );
            setError(null);
        } catch {
            setError('Failed to load conversation');
        }
    };

    const deleteConversation = async (id: string) => {
        if (!confirm('Delete this conversation?')) return;

        try {
            await fetch(`/ai/conversations/${id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (
                        document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
                    )?.content ?? '',
                },
            });

            setConversations((prev) => prev.filter((c) => c.id !== id));
            if (conversationId === id) {
                resetConversation();
            }
        } catch {
            setError('Failed to delete conversation');
        }
    };

    useEcho<ProgressEvent>(
        `projects-ai.${sessionIdRef.current}`,
        'ProjectsAgentProgress',
        (event: ProgressEvent) => {
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
                        loadConversations(); // Refresh conversation list
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
        },
        [],
        'private',
    );

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
        setProgressMessage('');
        sessionIdRef.current = crypto.randomUUID();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects AI" />

            <div className="flex h-full flex-1 gap-4 p-4">
                {/* Sidebar */}
                {showSidebar && (
                    <div className="w-64 shrink-0 space-y-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Conversations</h2>
                            <Button size="sm" variant="ghost" onClick={resetConversation}>
                                <Plus className="h-4 w-4" />
                            </Button>
                        </div>
                        <ScrollArea className="h-[calc(100vh-12rem)]">
                            <div className="space-y-1">
                                {conversations.map((conv) => (
                                    <div
                                        key={conv.id}
                                        className={`group flex items-center gap-2 rounded-lg p-2 text-sm transition-colors hover:bg-accent ${
                                            conversationId === conv.id ? 'bg-accent' : ''
                                        }`}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => loadConversation(conv.id)}
                                            className="flex flex-1 items-start gap-2 overflow-hidden text-left"
                                        >
                                            <MessageSquare className="h-4 w-4 shrink-0 text-muted-foreground" />
                                            <span className="flex-1 truncate text-xs">{conv.title}</span>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => deleteConversation(conv.id)}
                                            className="invisible shrink-0 text-muted-foreground hover:text-destructive group-hover:visible"
                                        >
                                            <Trash2 className="h-3 w-3" />
                                        </button>
                                    </div>
                                ))}
                                {conversations.length === 0 && (
                                    <p className="py-4 text-center text-xs text-muted-foreground">No conversations yet</p>
                                )}
                            </div>
                        </ScrollArea>
                    </div>
                )}

                {/* Main Chat Area */}
                <div className="flex flex-1 flex-col gap-4">
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h1 className="text-lg font-semibold">Projects Database AI</h1>
                                <p className="text-sm text-muted-foreground">
                                    Ask about invoices, work history, materials, and more. Try saying "hi"!
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    type="button"
                                    onClick={() => setShowSidebar(!showSidebar)}
                                >
                                    {showSidebar ? 'Hide' : 'Show'} History
                                </Button>
                                <Button variant="outline" size="sm" type="button" onClick={resetConversation} disabled={isLoading}>
                                    New Chat
                                </Button>
                            </div>
                        </div>
                    </div>

                    <ScrollArea className="min-h-90 flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <div className="space-y-3">
                            {messages.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Start by asking: "Show latest invoices" or "hi" for a greeting!
                                </p>
                            )}

                            {messages.map((message, index) => (
                                <div
                                    key={`${message.role}-${index}`}
                                    className={`max-w-3xl rounded-lg px-3 py-2 text-sm ${
                                        message.role === 'user'
                                            ? 'ml-auto bg-primary text-primary-foreground'
                                            : 'bg-muted text-foreground'
                                    }`}
                                >
                                    {message.content ? (
                                        <div className="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap">
                                            {message.content}
                                        </div>
                                    ) : (
                                        <span className="inline-flex items-center gap-2 text-muted-foreground">
                                            <span className="flex gap-1">
                                                <span className="animate-bounce" style={{ animationDelay: '0ms' }}>
                                                    ●
                                                </span>
                                                <span className="animate-bounce" style={{ animationDelay: '150ms' }}>
                                                    ●
                                                </span>
                                                <span className="animate-bounce" style={{ animationDelay: '300ms' }}>
                                                    ●
                                                </span>
                                            </span>
                                            <span>{progressMessage || 'Analyzing construction database...'}</span>
                                        </span>
                                    )}
                                </div>
                            ))}
                            <div ref={messagesEndRef} />
                        </div>
                    </ScrollArea>

                    <form
                        onSubmit={submit}
                        className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
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
                            <span>
                                {conversationId ? `Conversation: ${conversationId.slice(0, 8)}...` : 'New conversation'}
                            </span>
                            {error && <span className="text-destructive">{error}</span>}
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
