import { AppLayout } from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    BarChart,
    Bar,
} from 'recharts';
import { Key, TrendingUp, Activity, Clock, Plus, Copy, Trash2, RefreshCw, Eye, EyeOff } from 'lucide-react';
import axios from 'axios';
import { toast } from 'sonner';

interface ApiKey {
    id: number;
    name: string;
    masked_key: string;
    package: string | null;
    is_active: boolean;
    usage_count: number;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
}

interface ApiPackage {
    id: number;
    name: string;
    slug: string;
    description: string;
    price: number;
    rate_limit_per_minute: number;
    rate_limit_per_day: number | null;
    rate_limit_per_month: number | null;
    features: string[];
    max_api_keys: number;
}

interface UsageStat {
    date: string;
    total_requests: number;
    avg_response_time: number;
    successful_requests: number;
    failed_requests: number;
}

interface EndpointStat {
    endpoint: string;
    count: number;
}

interface RecentActivity {
    id: number;
    api_key_name: string;
    endpoint: string;
    method: string;
    status_code: number;
    response_time: number;
    ip_address: string;
    created_at: string;
    is_successful: boolean;
}

interface Summary {
    total_keys: number;
    active_keys: number;
    total_requests_30d: number;
    avg_response_time: number;
}

interface Props {
    apiKeys: ApiKey[];
    packages: ApiPackage[];
    usageStats: UsageStat[];
    endpointStats: EndpointStat[];
    recentActivity: RecentActivity[];
    summary: Summary;
}

export default function ApiManagement({
    apiKeys,
    packages,
    usageStats,
    endpointStats,
    recentActivity,
    summary,
}: Props) {
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [newTokenVisible, setNewTokenVisible] = useState<string | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        api_package_id: '',
        expires_at: '',
    });

    const handleCreateApiKey = async () => {
        try {
            const response = await axios.post('/api/api-keys', formData);
            setNewTokenVisible(response.data.plain_text_token);
            toast.success(response.data.message);
            // Reload page data
            router.reload({ only: ['apiKeys'] });
            // Reset form
            setFormData({ name: '', description: '', api_package_id: '', expires_at: '' });
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Failed to create API key');
        }
    };

    const handleDeleteApiKey = async (keyId: number) => {
        if (!confirm('Are you sure you want to delete this API key?')) return;

        try {
            await axios.delete(`/api/api-keys/${keyId}`);
            toast.success('API key deleted successfully');
            router.reload({ only: ['apiKeys'] });
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Failed to delete API key');
        }
    };

    const handleRegenerateApiKey = async (keyId: number) => {
        if (!confirm('This will invalidate the old key. Continue?')) return;

        try {
            const response = await axios.post(`/api/api-keys/${keyId}/regenerate`);
            setNewTokenVisible(response.data.plain_text_token);
            toast.success(response.data.message);
            router.reload({ only: ['apiKeys'] });
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Failed to regenerate API key');
        }
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        toast.success('Copied to clipboard!');
    };

    return (
        <AppLayout>
            <Head title="API Management" />

            <div className="container mx-auto py-8 space-y-8">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">API Management</h1>
                        <p className="text-muted-foreground">
                            Manage your API keys, monitor usage, and control access
                        </p>
                    </div>
                    <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Create API Key
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Create New API Key</DialogTitle>
                                <DialogDescription>
                                    Generate a new API key for your applications
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-4">
                                <div>
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        placeholder="My Mobile App"
                                        value={formData.name}
                                        onChange={(e) =>
                                            setFormData({ ...formData, name: e.target.value })
                                        }
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="description">Description</Label>
                                    <Textarea
                                        id="description"
                                        placeholder="Optional description..."
                                        value={formData.description}
                                        onChange={(e) =>
                                            setFormData({ ...formData, description: e.target.value })
                                        }
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="package">Package</Label>
                                    <Select
                                        value={formData.api_package_id}
                                        onValueChange={(value) =>
                                            setFormData({ ...formData, api_package_id: value })
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a package" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {packages.map((pkg) => (
                                                <SelectItem key={pkg.id} value={pkg.id.toString()}>
                                                    {pkg.name} - ${pkg.price}/mo
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label htmlFor="expires_at">Expires At (Optional)</Label>
                                    <Input
                                        id="expires_at"
                                        type="date"
                                        value={formData.expires_at}
                                        onChange={(e) =>
                                            setFormData({ ...formData, expires_at: e.target.value })
                                        }
                                    />
                                </div>
                            </div>
                            <DialogFooter>
                                <Button onClick={handleCreateApiKey}>Create API Key</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                {/* New Token Display */}
                {newTokenVisible && (
                    <Card className="border-green-500 bg-green-50 dark:bg-green-950">
                        <CardHeader>
                            <CardTitle className="text-green-700 dark:text-green-300">
                                🎉 API Key Created Successfully!
                            </CardTitle>
                            <CardDescription>
                                Save this token now - you won't be able to see it again!
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <Input
                                    value={newTokenVisible}
                                    readOnly
                                    className="font-mono text-sm"
                                />
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => copyToClipboard(newTokenVisible)}
                                >
                                    <Copy className="h-4 w-4" />
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setNewTokenVisible(null)}
                                >
                                    <EyeOff className="h-4 w-4" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Total API Keys</CardTitle>
                            <Key className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.total_keys}</div>
                            <p className="text-xs text-muted-foreground">
                                {summary.active_keys} active
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Requests (30d)</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {summary.total_requests_30d.toLocaleString()}
                            </div>
                            <p className="text-xs text-muted-foreground">Total API calls</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Success Rate</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {usageStats.length > 0
                                    ? Math.round(
                                        (usageStats.reduce(
                                            (acc, stat) => acc + stat.successful_requests,
                                            0
                                        ) /
                                            summary.total_requests_30d) *
                                        100
                                    )
                                    : 0}
                                %
                            </div>
                            <p className="text-xs text-muted-foreground">Successful requests</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Avg Response</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.avg_response_time}ms</div>
                            <p className="text-xs text-muted-foreground">Response time</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Usage Charts */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Request Volume (30 days)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={300}>
                                <LineChart data={usageStats}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="date" />
                                    <YAxis />
                                    <Tooltip />
                                    <Line
                                        type="monotone"
                                        dataKey="total_requests"
                                        stroke="hsl(var(--primary))"
                                        strokeWidth={2}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Top Endpoints</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart data={endpointStats.slice(0, 5)}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="endpoint" />
                                    <YAxis />
                                    <Tooltip />
                                    <Bar dataKey="count" fill="hsl(var(--primary))" />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                </div>

                {/* API Keys Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Your API Keys</CardTitle>
                        <CardDescription>Manage and monitor your API keys</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Key</TableHead>
                                    <TableHead>Package</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Usage</TableHead>
                                    <TableHead>Last Used</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {apiKeys.map((key) => (
                                    <TableRow key={key.id}>
                                        <TableCell className="font-medium">{key.name}</TableCell>
                                        <TableCell>
                                            <code className="text-xs">{key.masked_key}</code>
                                        </TableCell>
                                        <TableCell>{key.package || 'None'}</TableCell>
                                        <TableCell>
                                            <Badge variant={key.is_active ? 'success' : 'secondary'}>
                                                {key.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{key.usage_count.toLocaleString()}</TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {key.last_used_at || 'Never'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleRegenerateApiKey(key.id)}
                                                >
                                                    <RefreshCw className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => handleDeleteApiKey(key.id)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Available Packages */}
                <Card>
                    <CardHeader>
                        <CardTitle>Available Packages</CardTitle>
                        <CardDescription>Choose a package that fits your needs</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            {packages.map((pkg) => (
                                <Card key={pkg.id} className="border-2">
                                    <CardHeader>
                                        <CardTitle>{pkg.name}</CardTitle>
                                        <CardDescription>{pkg.description}</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="text-3xl font-bold">
                                            ${pkg.price}
                                            <span className="text-sm font-normal text-muted-foreground">
                                                /mo
                                            </span>
                                        </div>
                                        <div className="space-y-2 text-sm">
                                            <div>
                                                <strong>Rate Limits:</strong>
                                                <div className="text-muted-foreground">
                                                    {pkg.rate_limit_per_minute}/min
                                                    {pkg.rate_limit_per_day &&
                                                        `, ${pkg.rate_limit_per_day}/day`}
                                                </div>
                                            </div>
                                            <div>
                                                <strong>Features:</strong>
                                                <ul className="list-disc list-inside text-muted-foreground">
                                                    {pkg.features.map((feature) => (
                                                        <li key={feature}>{feature}</li>
                                                    ))}
                                                </ul>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Recent Activity */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Activity</CardTitle>
                        <CardDescription>Latest API requests</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>API Key</TableHead>
                                    <TableHead>Endpoint</TableHead>
                                    <TableHead>Method</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Response Time</TableHead>
                                    <TableHead>IP Address</TableHead>
                                    <TableHead>Timestamp</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentActivity.slice(0, 20).map((activity) => (
                                    <TableRow key={activity.id}>
                                        <TableCell className="font-medium">
                                            {activity.api_key_name}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {activity.endpoint}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{activity.method}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    activity.is_successful ? 'success' : 'destructive'
                                                }
                                            >
                                                {activity.status_code}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{activity.response_time}ms</TableCell>
                                        <TableCell className="text-xs">
                                            {activity.ip_address}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {activity.created_at}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
