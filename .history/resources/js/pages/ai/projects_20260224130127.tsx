import { FormEvent, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

        try {
            const response = await fetch('/ai/projects/ask', {
                method: 'POST',
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
                }),
            });

            const data = (await response.json()) as AskResponse | { message?: string };

            if (!response.ok) {
                throw new Error('message' in data && data.message ? data.message : 'Failed to get AI response.');
            }

            const result = data as AskResponse;

            setConversationId(result.conversation_id ?? null);
            setMessages((current) => [...current, { role: 'assistant', content: result.answer }]);
        } catch (submitError) {
            const message = submitError instanceof Error ? submitError.message : 'Unexpected error.';
            setError(message);
            setMessages((current) => [...current, { role: 'assistant', content: `Error: ${message}` }]);
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

                <div className="min-h-[360px] flex-1 space-y-3 overflow-y-auto rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    {messages.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Start by asking: “Show latest invoice totals by tender package” or “Explain WorkHistory relations”.
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
                            {message.content}
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
