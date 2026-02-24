import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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

    const canSubmit = useMemo(() => question.trim().length > 0 && !isLoading, [question, isLoading]);

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        const prompt = question.trim();

        if (!prompt || isLoading) {
            return;
        }

        setError(null);
        setIsLoading(true);
        setMessages((current) => [...current, { role: 'user', content: prompt }]);
        setQuestion('');

        // Add empty assistant message that we'll update as we stream
        const messageIndex = messages.length + 1;
        setMessages((current) => [...current, { role: 'assistant', content: '' }]);

        try {
            const response = await fetch('/ai/projects/ask', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (
                        document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
                    )?.content ?? '',
                },
                body: JSON.stringify({
                    question: prompt,
                    conversation_id: conversationId,
                    stream: true,
                }),
            });

            if (!response.ok) {
                throw new Error('Failed to get AI response.');
            }

            const reader = response.body?.getReader();
            const decoder = new TextDecoder();
            let accumulatedContent = '';

            if (!reader) {
                throw new Error('Response body is not readable.');
            }

            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    break;
                }

                const chunk = decoder.decode(value, { stream: true });
                const lines = chunk.split('\n');

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        const data = line.slice(6);

                        if (data === '[DONE]') {
                            continue;
                        }

                        try {
                            const parsed = JSON.parse(data) as { content?: string; conversation_id?: string; error?: string };

                            if (parsed.error) {
                                throw new Error(parsed.error);
                            }

                            if (parsed.content) {
                                accumulatedContent += parsed.content;
                                setMessages((current) => {
                                    const updated = [...current];
                                    updated[messageIndex] = { role: 'assistant', content: accumulatedContent };
                                    return updated;
                                });
                            }

                            if (parsed.conversation_id) {
                                setConversationId(parsed.conversation_id);
                            }
                        } catch (parseError) {
                            console.error('Failed to parse SSE data:', data, parseError);
                        }
                    }
                }
            }

            if (accumulatedContent.length === 0) {
                throw new Error('No content received from stream.');
            }
        } catch (submitError) {
            const message = submitError instanceof Error ? submitError.message : 'Unexpected error.';
            setError(message);
            setMessages((current) => {
                const updated = [...current];
                updated[messageIndex] = { role: 'assistant', content: `Error: ${message}` };
                return updated;
            });
        } finally {
            setIsLoading(false);
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
                                <span className="inline-flex items-center gap-1 text-muted-foreground">
                                    <span className="animate-pulse">●</span> Thinking...
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
                            {isLoading ? 'Thinking...' : 'Ask'}
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
