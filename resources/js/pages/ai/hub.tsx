import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { apiFetch, defaultHeaders } from '@/lib/api';

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

type ApiKeyEntry = {
    id: number;
    name: string;
    last_four: string;
    user?: { id: number; name: string; email: string };
    last_used_at?: string | null;
    expires_at?: string | null;
    revoked_at?: string | null;
    created_at?: string | null;
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

type ProjectSource = {
    id: number;
    project_id: number;
    name: string;
    table: string;
    primary_key: string;
    fields: string[];
    is_active: boolean;
    last_synced_at?: string | null;
};

type SourceForm = {
    project_id: number | null;
    name: string;
    table: string;
    primary_key: string;
    fields: string;
    is_active: boolean;
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

const emptySourceForm: SourceForm = {
    project_id: null,
    name: '',
    table: '',
    primary_key: 'id',
    fields: '',
    is_active: true,
};


export default function AiHub({ projects, models, ollamaOnline, qdrant }: HubProps) {
    const [projectList, setProjectList] = useState<Project[]>(projects);
    const [modelList, setModelList] = useState<OllamaModel[]>(models);
    const [projectForm, setProjectForm] = useState<ProjectForm>(emptyProjectForm);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [modelInfo, setModelInfo] = useState<Record<string, unknown> | null>(null);
    const [status, setStatus] = useState('');
    const [modelToPull, setModelToPull] = useState('');
    const [apiKeys, setApiKeys] = useState<ApiKeyEntry[]>([]);
    const [apiKeyName, setApiKeyName] = useState('');
    const [apiKeyExpiry, setApiKeyExpiry] = useState('');
    const [apiToken, setApiToken] = useState('');
    const [qdrantHealth, setQdrantHealth] = useState<'unknown' | 'ok' | 'fail'>('unknown');
    const [collections, setCollections] = useState<{ name: string; points?: number }[]>([]);
    const [collectionName, setCollectionName] = useState('');
    const [collectionSize, setCollectionSize] = useState('768');
    const [qdrantMethod, setQdrantMethod] = useState('GET');
    const [qdrantPath, setQdrantPath] = useState('collections');
    const [qdrantBody, setQdrantBody] = useState('');
    const [qdrantResponse, setQdrantResponse] = useState('');
    const [syncStatus, setSyncStatus] = useState('');
    const [sourceProjectId, setSourceProjectId] = useState<number | null>(projects[0]?.id ?? null);
    const [sourceList, setSourceList] = useState<ProjectSource[]>([]);
    const [sourceForm, setSourceForm] = useState<SourceForm>(emptySourceForm);
    const [sourceEditingId, setSourceEditingId] = useState<number | null>(null);
    const [sourceStatus, setSourceStatus] = useState('');

    const headers = useMemo(() => defaultHeaders(), []);

    const fetchApiKeys = async () => {
        const response = await apiFetch('/ai/api-keys');
        const data = await response.json();
        if (response.ok) {
            setApiKeys(Array.isArray(data?.keys) ? data.keys : []);
        }
    };

    useEffect(() => {
        fetchApiKeys();
    }, []);

    useEffect(() => {
        if (!sourceProjectId && projectList.length > 0) {
            setSourceProjectId(projectList[0].id);
        }
    }, [projectList, sourceProjectId]);

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

    const createApiKey = async () => {
        const response = await apiFetch('/ai/api-keys', {
            method: 'POST',
            headers,
            body: JSON.stringify({
                name: apiKeyName,
                expires_at: apiKeyExpiry || null,
            }),
        });
        const data = await response.json();
        if (response.ok) {
            setApiToken(data.token);
            setApiKeyName('');
            setApiKeyExpiry('');
            fetchApiKeys();
        }
    };

    const loadQdrantStatus = async () => {
        const health = await apiFetch('/ai/qdrant/health');
        setQdrantHealth(health.ok ? 'ok' : 'fail');

        const listResponse = await apiFetch('/ai/qdrant/collections');
        const listData = await listResponse.json();
        if (listResponse.ok) {
            const names = Array.isArray(listData?.result?.collections)
                ? listData.result.collections.map((item: { name: string }) => item.name)
                : [];

            const details = await Promise.all(
                names.map(async (name: string) => {
                    const infoRes = await apiFetch(`/ai/qdrant/collections/${name}`);
                    const info = await infoRes.json();
                    const points = info?.result?.points_count ?? info?.result?.points_count_exact ?? null;
                    return { name, points };
                }),
            );
            setCollections(details);
        }
    };

    useEffect(() => {
        loadQdrantStatus();
    }, []);

    const revokeApiKey = async (id: number) => {
        const response = await apiFetch(`/ai/api-keys/${id}/revoke`, {
            method: 'POST',
            headers,
        });
        if (response.ok) {
            fetchApiKeys();
        }
    };

    const syncProject = async (projectId: number) => {
        setSyncStatus('Syncing project…');
        const response = await apiFetch(`/ai/projects/${projectId}/sync`, {
            method: 'POST',
            headers,
        });
        if (response.ok) {
            setSyncStatus('Project sync completed.');
        } else {
            setSyncStatus('Project sync failed.');
        }
    };

    const syncAllProjects = async () => {
        setSyncStatus('Syncing all active projects…');
        const response = await apiFetch('/ai/projects/sync-all', {
            method: 'POST',
            headers,
        });
        if (response.ok) {
            setSyncStatus('All project sync completed.');
        } else {
            setSyncStatus('Project sync failed.');
        }
    };

    const loadSources = async (projectId: number) => {
        const response = await apiFetch(`/ai/projects/${projectId}/sources`);
        const data = await response.json();
        if (response.ok) {
            setSourceList(Array.isArray(data?.sources) ? data.sources : []);
        }
    };

    useEffect(() => {
        if (sourceProjectId) {
            loadSources(sourceProjectId);
        } else {
            setSourceList([]);
        }
    }, [sourceProjectId]);

    const resetSourceForm = (projectId: number | null) => {
        setSourceForm({ ...emptySourceForm, project_id: projectId });
        setSourceEditingId(null);
    };

    useEffect(() => {
        if (sourceProjectId && !projectList.find((project) => project.id === sourceProjectId)) {
            const nextId = projectList[0]?.id ?? null;
            setSourceProjectId(nextId);
            resetSourceForm(nextId);
        }
    }, [projectList, sourceProjectId]);

    const saveSource = async () => {
        if (!sourceProjectId) return;
        const fields = sourceForm.fields
            .split(',')
            .map((field) => field.trim())
            .filter(Boolean);
        if (fields.length === 0) {
            setSourceStatus('Add at least one field.');
            return;
        }

        const payload = {
            name: sourceForm.name,
            table: sourceForm.table,
            primary_key: sourceForm.primary_key || 'id',
            fields,
            is_active: sourceForm.is_active,
        };

        const endpoint = sourceEditingId
            ? `/ai/projects/${sourceProjectId}/sources/${sourceEditingId}`
            : `/ai/projects/${sourceProjectId}/sources`;
        const response = await apiFetch(endpoint, {
            method: sourceEditingId ? 'PUT' : 'POST',
            headers,
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (response.ok) {
            if (sourceEditingId) {
                setSourceList((prev) =>
                    prev.map((source) => (source.id === sourceEditingId ? data.source : source)),
                );
            } else {
                setSourceList((prev) => [...prev, data.source]);
            }
            setSourceStatus('Source saved.');
            resetSourceForm(sourceProjectId);
        } else {
            setSourceStatus('Failed to save source.');
        }
    };

    const editSource = (source: ProjectSource) => {
        setSourceForm({
            project_id: source.project_id,
            name: source.name,
            table: source.table,
            primary_key: source.primary_key,
            fields: source.fields.join(', '),
            is_active: source.is_active,
        });
        setSourceEditingId(source.id);
    };

    const deleteSource = async (sourceId: number) => {
        if (!sourceProjectId) return;
        const response = await apiFetch(`/ai/projects/${sourceProjectId}/sources/${sourceId}`, {
            method: 'DELETE',
            headers,
        });
        if (response.ok) {
            setSourceList((prev) => prev.filter((source) => source.id !== sourceId));
            setSourceStatus('Source deleted.');
        } else {
            setSourceStatus('Failed to delete source.');
        }
    };

    const createCollection = async () => {
        if (!collectionName.trim()) return;
        const response = await apiFetch('/ai/qdrant/collections', {
            method: 'POST',
            headers,
            body: JSON.stringify({
                name: collectionName.trim(),
                size: Number(collectionSize),
            }),
        });
        if (response.ok) {
            setCollectionName('');
            await loadQdrantStatus();
        }
    };

    const deleteCollection = async (name: string) => {
        const response = await apiFetch(`/ai/qdrant/collections/${name}`, {
            method: 'DELETE',
            headers,
        });
        if (response.ok) {
            await loadQdrantStatus();
        }
    };

    const sendQdrantRequest = async () => {
        const path = qdrantPath.replace(/^\/+/, '');
        const options: RequestInit = {
            method: qdrantMethod,
            headers,
        };

        if (qdrantMethod !== 'GET' && qdrantMethod !== 'HEAD' && qdrantBody.trim()) {
            options.body = qdrantBody;
        }

        const response = await apiFetch(`/ai/qdrant/proxy/${path}`, options);
        const text = await response.text();
        setQdrantResponse(text);
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
                        <button
                            type="button"
                            onClick={syncAllProjects}
                            className="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:border-slate-300"
                        >
                            Sync all
                        </button>
                    </div>

                    {syncStatus && (
                        <div className="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            {syncStatus}
                        </div>
                    )}

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
                                    <button
                                        type="button"
                                        onClick={() => syncProject(project.id)}
                                        className="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-600 hover:border-emerald-300"
                                    >
                                        Sync now
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">Project Sources</h2>
                            <p className="text-xs text-slate-500">
                                Define tables and fields to sync into Qdrant.
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 grid gap-3 md:grid-cols-[220px_1fr]">
                        <select
                            value={sourceProjectId ?? ''}
                            onChange={(event) => {
                                const id = Number(event.target.value || 0);
                                setSourceProjectId(id || null);
                                resetSourceForm(id || null);
                            }}
                            className="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        >
                            <option value="">Select project</option>
                            {projectList.map((project) => (
                                <option key={project.id} value={project.id}>
                                    {project.name}
                                </option>
                            ))}
                        </select>
                        <div className="grid gap-3 md:grid-cols-2">
                            <input
                                value={sourceForm.name}
                                onChange={(event) => setSourceForm({ ...sourceForm, name: event.target.value })}
                                placeholder="Source name"
                                className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            />
                            <input
                                value={sourceForm.table}
                                onChange={(event) => setSourceForm({ ...sourceForm, table: event.target.value })}
                                placeholder="Table name"
                                className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            />
                            <input
                                value={sourceForm.primary_key}
                                onChange={(event) => setSourceForm({ ...sourceForm, primary_key: event.target.value })}
                                placeholder="Primary key (default id)"
                                className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            />
                            <input
                                value={sourceForm.fields}
                                onChange={(event) => setSourceForm({ ...sourceForm, fields: event.target.value })}
                                placeholder="Fields (comma separated)"
                                className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <label className="flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <input
                                type="checkbox"
                                checked={sourceForm.is_active}
                                onChange={(event) =>
                                    setSourceForm({ ...sourceForm, is_active: event.target.checked })
                                }
                            />
                            Active
                        </label>
                        <div className="flex flex-wrap gap-2">
                            {sourceEditingId && (
                                <button
                                    type="button"
                                    onClick={() => resetSourceForm(sourceProjectId)}
                                    className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300"
                                >
                                    Cancel
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={saveSource}
                                className="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-slate-800"
                            >
                                {sourceEditingId ? 'Update source' : 'Add source'}
                            </button>
                        </div>
                    </div>

                    {sourceStatus && (
                        <div className="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            {sourceStatus}
                        </div>
                    )}

                    <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {sourceList.map((source) => (
                            <div
                                key={source.id}
                                className="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">{source.name}</p>
                                        <p className="text-xs text-slate-500">{source.table}</p>
                                    </div>
                                    <span
                                        className={`rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide ${
                                            source.is_active
                                                ? 'bg-emerald-100 text-emerald-600'
                                                : 'bg-slate-100 text-slate-500'
                                        }`}
                                    >
                                        {source.is_active ? 'active' : 'paused'}
                                    </span>
                                </div>
                                <p className="mt-2 text-xs text-slate-600">
                                    Fields: {source.fields.join(', ')}
                                </p>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={() => editSource(source)}
                                        className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-slate-300"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => deleteSource(source.id)}
                                        className="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-500 hover:border-rose-300"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        ))}
                        {sourceList.length === 0 && (
                            <div className="rounded-2xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500">
                                No sources found for this project.
                            </div>
                        )}
                    </div>
                </section>

                <section className="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">Qdrant Monitoring</h2>
                            <p className="text-xs text-slate-500">API status and collection counts.</p>
                        </div>
                        <button
                            type="button"
                            onClick={loadQdrantStatus}
                            className="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:border-slate-300"
                        >
                            Refresh
                        </button>
                    </div>

                    <div className="mt-4 grid gap-3 md:grid-cols-[1fr_160px_140px]">
                        <input
                            value={collectionName}
                            onChange={(event) => setCollectionName(event.target.value)}
                            placeholder="collection name"
                            className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        />
                        <input
                            value={collectionSize}
                            onChange={(event) => setCollectionSize(event.target.value)}
                            placeholder="vector size"
                            className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        />
                        <button
                            type="button"
                            onClick={createCollection}
                            className="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-slate-800"
                        >
                            Create
                        </button>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            URL: {qdrant.url}
                        </span>
                        <span
                            className={`rounded-full px-3 py-1 text-[10px] font-semibold uppercase tracking-wide ${
                                qdrantHealth === 'ok'
                                    ? 'bg-emerald-100 text-emerald-600'
                                    : qdrantHealth === 'fail'
                                      ? 'bg-rose-100 text-rose-600'
                                      : 'bg-slate-100 text-slate-500'
                            }`}
                        >
                            {qdrantHealth === 'ok' ? 'healthy' : qdrantHealth === 'fail' ? 'offline' : 'unknown'}
                        </span>
                    </div>

                    <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {collections.map((collection) => (
                            <div
                                key={collection.name}
                                className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">{collection.name}</p>
                                        <p className="text-xs text-slate-500">
                                            Points: {collection.points ?? '—'}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => deleteCollection(collection.name)}
                                        className="text-xs font-semibold text-rose-500 hover:text-rose-600"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        ))}
                        {collections.length === 0 && (
                            <div className="rounded-2xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500">
                                No collections found.
                            </div>
                        )}
                    </div>

                    <div className="mt-6 rounded-3xl border border-slate-200/70 bg-slate-50 p-4">
                        <div className="flex flex-wrap items-center gap-3">
                            <select
                                value={qdrantMethod}
                                onChange={(event) => setQdrantMethod(event.target.value)}
                                className="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                            >
                                {['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map((method) => (
                                    <option key={method} value={method}>
                                        {method}
                                    </option>
                                ))}
                            </select>
                            <input
                                value={qdrantPath}
                                onChange={(event) => setQdrantPath(event.target.value)}
                                placeholder="qdrant path (e.g. collections)"
                                className="flex-1 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                            <button
                                type="button"
                                onClick={sendQdrantRequest}
                                className="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-slate-800"
                            >
                                Send
                            </button>
                        </div>
                        <textarea
                            value={qdrantBody}
                            onChange={(event) => setQdrantBody(event.target.value)}
                            placeholder="JSON body (optional)"
                            className="mt-3 min-h-[120px] w-full rounded-2xl border border-slate-200 bg-white p-3 text-xs text-slate-700"
                        />
                        <pre className="mt-3 max-h-64 overflow-auto rounded-2xl border border-slate-200 bg-white p-3 text-xs text-slate-700">
                            {qdrantResponse || 'Response will appear here.'}
                        </pre>
                    </div>
                </section>

                <section className="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">API Keys</h2>
                            <p className="text-xs text-slate-500">
                                Generate keys for remote apps and websites.
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 grid gap-4 md:grid-cols-[1.2fr_0.8fr]">
                        <div className="rounded-2xl border border-slate-200 bg-white p-4">
                            <div className="grid gap-3">
                                <input
                                    value={apiKeyName}
                                    onChange={(event) => setApiKeyName(event.target.value)}
                                    placeholder="Key name (e.g. marketing-site)"
                                    className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                />
                                <input
                                    value={apiKeyExpiry}
                                    onChange={(event) => setApiKeyExpiry(event.target.value)}
                                    placeholder="Expires at (YYYY-MM-DD) optional"
                                    className="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                />
                                <button
                                    type="button"
                                    onClick={createApiKey}
                                    className="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-slate-800"
                                >
                                    Create API key
                                </button>
                                {apiToken && (
                                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700">
                                        <p className="font-semibold">Copy this token now (shown once):</p>
                                        <p className="mt-1 break-all font-mono">{apiToken}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-4">
                            <div className="flex flex-col gap-3 text-xs text-slate-600">
                                <p className="font-semibold uppercase tracking-wide text-slate-400">Usage</p>
                                <code className="rounded-xl bg-slate-950 p-3 text-[11px] text-emerald-200">
                                    Authorization: Bearer {'{TOKEN}'}
                                </code>
                                <code className="rounded-xl bg-slate-950 p-3 text-[11px] text-emerald-200">
                                    X-API-Key: {'{TOKEN}'}
                                </code>
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {apiKeys.map((key) => (
                            <div key={key.id} className="rounded-2xl border border-slate-200 bg-white p-4">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">{key.name}</p>
                                        <p className="text-xs text-slate-500">•••• {key.last_four}</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => revokeApiKey(key.id)}
                                        className="text-xs font-semibold text-rose-500 hover:text-rose-600"
                                    >
                                        Revoke
                                    </button>
                                </div>
                                <div className="mt-2 text-xs text-slate-500">
                                    <p>Owner: {key.user?.email ?? '—'}</p>
                                    <p>Last used: {key.last_used_at ?? 'Never'}</p>
                                    <p>Expires: {key.expires_at ?? 'Never'}</p>
                                    <p>Status: {key.revoked_at ? 'Revoked' : 'Active'}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

