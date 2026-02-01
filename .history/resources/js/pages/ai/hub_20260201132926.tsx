import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';

type Project = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    env_key?: string | null;
    qdrant_collection?: string | null;
    is_active: boolean;
    connection?: {
        driver?: string;
        host?: string;
        port?: string;
        database?: string;
        username?: string;
        password?: string;
    } | null;
};

type OllamaModel = {
    name: string;
    size?: number;
    modified_at?: string;
};

type HubProps = {
    projects: Project[];
    models: OllamaModel[];
    ollamaOnline: boolean;
    qdrant: {
        url: string;
    };
};

type ProjectForm = {
    name: string;
    slug: string;
    description: string;
    env_key: string;
    qdrant_collection: string;
    is_active: boolean;
    connection: {
        driver: string;
        host: string;
        port: string;
        database: string;
        username: string;
        password: string;
    };
};

const emptyProjectForm: ProjectForm = {
    name: '',
    slug: '',
    description: '',
    env_key: '',
    qdrant_collection: '',
    is_active: true,
    connection: {
        driver: '',
        host: '',
        port: '',
        database: '',
        username: '',
        password: '',
    },
};

function csrfHeader() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return token ? { 'X-CSRF-TOKEN': token } : {};
}

export default function AiHub({ projects, models, ollamaOnline, qdrant }: HubProps) {
    const [projectList, setProjectList] = useState<Project[]>(projects);
    const [modelList, setModelList] = useState<OllamaModel[]>(models);
    const [projectForm, setProjectForm] = useState<ProjectForm>(emptyProjectForm);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [modelInfo, setModelInfo] = useState<Record<string, unknown> | null>(null);
    const [status, setStatus] = useState('');
    const [modelToPull, setModelToPull] = useState('');

    const headers = useMemo(
        () => ({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...csrfHeader(),
        }),
        [],
    );

    function apiFetch(input: RequestInfo, init: RequestInit = {}) {
        return fetch(input, { credentials: 'same-origin', ...init });
    }

    const refreshModels = async () => {
        setStatus('Refreshing models…');
        const response = await apiFetch('/ai/ollama/tags');
        const data = await response.json();
        if (response.ok) {
            setModelList(Array.isArray(data?.models) ? data.models : []);
            setStatus('Models refreshed.');
        } else {
            setStatus('Failed to refresh models.');
        }
    };

    const pullModel = async () => {
        if (!modelToPull.trim()) return;
        setStatus(`Pulling ${modelToPull}…`);
        const response = await apiFetch('/ai/ollama/pull', {
            method: 'POST',
            headers,
            body: JSON.stringify({ model: modelToPull.trim() }),
        });
        if (response.ok) {
            await refreshModels();
            setModelToPull('');
        } else {
            setStatus('Pull failed.');
        }
    };

    const removeModel = async (name: string) => {
        setStatus(`Removing ${name}…`);
        const response = await apiFetch('/ai/ollama/rm', {
            method: 'POST',
            headers,
            body: JSON.stringify({ model: name }),
        });
        if (response.ok) {
            await refreshModels();
        } else {
            setStatus('Remove failed.');
        }
    };

    const showModel = async (name: string) => {
        setStatus(`Loading ${name}…`);
        const response = await apiFetch('/ai/ollama/show', {
            method: 'POST',
            headers,
            body: JSON.stringify({ model: name }),
        });
        const data = await response.json();
        setModelInfo(response.ok ? data : null);
        setStatus(response.ok ? `Loaded ${name}.` : 'Show failed.');
    };

    const saveProject = async () => {
        const payload = {
            ...projectForm,
            connection: projectForm.env_key ? null : projectForm.connection,
        };
        const response = await apiFetch(
            editingId ? `/ai/projects/${editingId}` : '/ai/projects',
            {
                method: editingId ? 'PUT' : 'POST',
                headers,
                body: JSON.stringify(payload),
            },
        );
        const data = await response.json();
        if (response.ok) {
            if (editingId) {
                setProjectList((prev) =>
                    prev.map((project) => (project.id === editingId ? data.project : project)),
                );
            } else {
                setProjectList((prev) => [...prev, data.project]);
            }
            setProjectForm(emptyProjectForm);
            setEditingId(null);
        }
    };

    const editProject = (project: Project) => {
        setEditingId(project.id);
        setProjectForm({
            name: project.name,
            slug: project.slug,
            description: project.description ?? '',
            env_key: project.env_key ?? '',
            qdrant_collection: project.qdrant_collection ?? '',
            is_active: project.is_active,
            connection: {
                driver: project.connection?.driver ?? '',
                host: project.connection?.host ?? '',
                port: project.connection?.port ?? '',
                database: project.connection?.database ?? '',
                username: project.connection?.username ?? '',
                password: project.connection?.password ?? '',
            },
        });
    };

    const deleteProject = async (projectId: number) => {
        const response = await apiFetch(`/ai/projects/${projectId}`, {
            method: 'DELETE',
            headers,
        });
        if (response.ok) {
            setProjectList((prev) => prev.filter((project) => project.id !== projectId));
            if (editingId === projectId) {
                setEditingId(null);
                setProjectForm(emptyProjectForm);
            }
        }
    };

    return (
        <AppLayout>
            <Head title="AI Hub" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-hidden rounded-3xl border border-slate-200/80 bg-[radial-gradient(circle_at_top,_#f8fafc,_#eef2ff_55%,_#fef9c3)] p-6 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">AI Hub Control Room</h1>
                        <p className="text-sm text-slate-600">
                            Manage models, projects, and vector sync from one place.
                        </p>
                    </div>
                    <div className="rounded-full bg-white/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 shadow">
                        Ollama {ollamaOnline ? 'online' : 'offline'}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                    <section className="rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm backdrop-blur">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">Ollama Models</h2>
                                <p className="text-xs text-slate-500">Pull, remove, and inspect models.</p>
                            </div>
                            <button
                                type="button"
                                onClick={refreshModels}
                                className="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:border-slate-300"
                            >
                                Refresh
                            </button>
                        </div>

                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <input
                                value={modelToPull}
                                onChange={(event) => setModelToPull(event.target.value)}
                                placeholder="ollama model name"
                                className="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none"
                            />
                            <button
                                type="button"
                                onClick={pullModel}
                                className="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-slate-800"
                            >
                                Pull model
                            </button>
                        </div>

                        <div className="mt-4 grid gap-3 md:grid-cols-2">
                            {modelList.map((model) => (
                                <div
                                    key={model.name}
                                    className="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900">{model.name}</p>
                                            <p className="text-xs text-slate-500">{model.modified_at ?? '—'}</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeModel(model.name)}
                                            className="text-xs font-semibold text-rose-500 hover:text-rose-600"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => showModel(model.name)}
                                        className="mt-3 inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-slate-300"
                                    >
                                        Inspect
                                    </button>
                                </div>
                            ))}
                        </div>

                        <div className="mt-4 rounded-2xl border border-slate-200/70 bg-slate-950 p-4 text-xs text-emerald-200">
                            {status || 'Awaiting action…'}
                        </div>
                        {modelInfo && (
                            <pre className="mt-3 max-h-56 overflow-auto rounded-2xl border border-slate-200/70 bg-white p-4 text-xs text-slate-700">
                                {JSON.stringify(modelInfo, null, 2)}
                            </pre>
                        )}
                    </section>

                    <section className="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm">
                        <h2 className="text-lg font-semibold text-slate-900">Project Registry</h2>
                        <p className="text-xs text-slate-500">
                            Store encrypted DB connections and Qdrant collections.
                        </p>

                        <div className="mt-4 grid gap-3">
                            <input
                                value={projectForm.name}
                                onChange={(event) => setProjectForm({ ...projectForm, name: event.target.value })}
                                placeholder="Project name"
                                className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            />
                            <input
                                value={projectForm.slug}
                                onChange={(event) => setProjectForm({ ...projectForm, slug: event.target.value })}
                                placeholder="Slug (auto if empty)"
                                className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            />
                            <textarea
                                value={projectForm.description}
                                onChange={(event) =>
                                    setProjectForm({ ...projectForm, description: event.target.value })
                                }
                                placeholder="Project description"
                                className="min-h-[80px] rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            />
                            <div className="grid gap-3 md:grid-cols-2">
                                <input
                                    value={projectForm.qdrant_collection}
                                    onChange={(event) =>
                                        setProjectForm({ ...projectForm, qdrant_collection: event.target.value })
                                    }
                                    placeholder="Qdrant collection"
                                    className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                />
                                <input
                                    value={projectForm.env_key}
                                    onChange={(event) =>
                                        setProjectForm({ ...projectForm, env_key: event.target.value.toUpperCase() })
                                    }
                                    placeholder="Env prefix (optional)"
                                    className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                />
                            </div>
                            <div className="grid gap-3 md:grid-cols-2">
                                <input
                                    value={projectForm.connection.driver}
                                    onChange={(event) =>
                                        setProjectForm({
                                            ...projectForm,
                                            connection: { ...projectForm.connection, driver: event.target.value },
                                        })
                                    }
                                    placeholder="DB driver"
                                    className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                    disabled={Boolean(projectForm.env_key)}
                                />
                                <input
                                    value={projectForm.connection.host}
                                    onChange={(event) =>
                                        setProjectForm({
                                            ...projectForm,
                                            connection: { ...projectForm.connection, host: event.target.value },
                                        })
                                    }
                                    placeholder="DB host"
                                    className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                    disabled={Boolean(projectForm.env_key)}
                                />
                                <input
                                    value={projectForm.connection.port}
                                    onChange={(event) =>
                                        setProjectForm({
                                            ...projectForm,
                                            connection: { ...projectForm.connection, port: event.target.value },
                                        })
                                    }
                                    placeholder="DB port"
                                    className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                    disabled={Boolean(projectForm.env_key)}
                                />
                                <input
                                    value={projectForm.connection.database}
                                    onChange={(event) =>
                                        setProjectForm({
                                            ...projectForm,
                                            connection: { ...projectForm.connection, database: event.target.value },
                                        })
                                    }
                                    placeholder="DB name"
                                    className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                    disabled={Boolean(projectForm.env_key)}
                                />
                                <input
                                    value={projectForm.connection.username}
                                    onChange={(event) =>
                                        setProjectForm({
                                            ...projectForm,
                                            connection: { ...projectForm.connection, username: event.target.value },
                                        })
                                    }
                                    placeholder="DB user"
                                    className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                    disabled={Boolean(projectForm.env_key)}
                                />
                                <input
                                    value={projectForm.connection.password}
                                    onChange={(event) =>
                                        setProjectForm({
                                            ...projectForm,
                                            connection: { ...projectForm.connection, password: event.target.value },
                                        })
                                    }
                                    placeholder="DB password"
                                    className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                    disabled={Boolean(projectForm.env_key)}
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <input
                                        type="checkbox"
                                        checked={projectForm.is_active}
                                        onChange={(event) =>
                                            setProjectForm({ ...projectForm, is_active: event.target.checked })
                                        }
                                    />
                                    Active
                                </label>
                                <button
                                    type="button"
                                    onClick={saveProject}
                                    className="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-slate-800"
                                >
                                    {editingId ? 'Update project' : 'Create project'}
                                </button>
                            </div>
                        </div>
                    </section>
                </div>

                <section className="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">Projects</h2>
                            <p className="text-xs text-slate-500">
                                Qdrant endpoint: {qdrant.url}
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {projectList.map((project) => (
                            <div
                                key={project.id}
                                className="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">{project.name}</p>
                                        <p className="text-xs text-slate-500">@{project.slug}</p>
                                    </div>
                                    <span
                                        className={`rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide ${project.is_active
                                                ? 'bg-emerald-100 text-emerald-600'
                                                : 'bg-slate-100 text-slate-500'
                                            }`}
                                    >
                                        {project.is_active ? 'active' : 'paused'}
                                    </span>
                                </div>
                                <p className="mt-2 text-xs text-slate-600">
                                    {project.description || 'No description yet.'}
                                </p>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={() => editProject(project)}
                                        className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-slate-300"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => deleteProject(project.id)}
                                        className="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-500 hover:border-rose-300"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
