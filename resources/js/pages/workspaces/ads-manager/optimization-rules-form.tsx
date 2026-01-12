import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { OptimizationRule } from '@/types/models/OptimizationRule';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { startCase } from 'lodash';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import AdsManagerLayout from './partials/Layout';

interface PageProps {
    workspace: Workspace;
    rule: OptimizationRule | null;
}

const OptimizationRulesFormPage = ({ workspace, rule }: PageProps) => {
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        target: 'campaign' as 'campaign' | 'ad_set',
        action: 'increase_budget_fixed',
        action_value: '',
        status: 'active' as 'active' | 'paused',
        conditions: [{ metric: 'spend', operator: 'greater_than', value: '' }] as Array<{
            metric: string;
            operator: string;
            value: string;
        }>,
    });
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (rule) {
            setFormData({
                name: rule.name,
                description: rule.description || '',
                target: rule.target,
                action: rule.action,
                action_value: rule.action_value?.toString() || '',
                status: rule.status,
                conditions: rule.conditions.map(c => ({
                    ...c,
                    value: c.value.toString(),
                })),
            });
        } else {
            setFormData({
                name: '',
                description: '',
                target: 'campaign',
                action: 'increase_budget_fixed',
                action_value: '',
                status: 'active',
                conditions: [{ metric: 'spend', operator: 'greater_than', value: '' }],
            });
        }
        setErrors({});
    }, [rule]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            const payload = {
                name: formData.name,
                description: formData.description || null,
                target: formData.target,
                action: formData.action,
                action_value: formData.action_value ? parseFloat(formData.action_value) : null,
                status: formData.status,
                conditions: formData.conditions.map(c => ({
                    metric: c.metric,
                    operator: c.operator,
                    value: parseFloat(c.value),
                })),
            };

            if (rule) {
                await axios.put(
                    `/workspaces/${workspace.slug}/api/optimization-rules/${rule.id}`,
                    payload
                );
            } else {
                await axios.post(`/workspaces/${workspace.slug}/api/optimization-rules`, payload);
            }

            router.get(`/workspaces/${workspace.slug}/ads-manager/optimization-rules`);
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                console.error('Error saving optimization rule:', error);
                alert('Failed to save optimization rule');
            }
        } finally {
            setLoading(false);
        }
    };

    const addCondition = () => {
        setFormData({
            ...formData,
            conditions: [
                ...formData.conditions,
                { metric: 'spend', operator: 'greater_than', value: '' },
            ],
        });
    };

    const removeCondition = (index: number) => {
        setFormData({
            ...formData,
            conditions: formData.conditions.filter((_, i) => i !== index),
        });
    };

    const updateCondition = (index: number, field: keyof typeof formData.conditions[0], value: string) => {
        const newConditions = [...formData.conditions];
        newConditions[index] = { ...newConditions[index], [field]: value };
        setFormData({ ...formData, conditions: newConditions });
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - ${rule ? 'Edit' : 'Create'} Optimization Rule`} />
            <AdsManagerLayout
                workspace={workspace}
                activeTab="optimizationRules"
            >
                <div className="flex justify-center py-6 px-4">
                    <div className="w-full">
                        <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-6">
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white/90 mb-6">
                                {rule ? 'Edit Optimization Rule' : 'Create New Optimization Rule'}
                            </h1>

                            <form onSubmit={handleSubmit} className="space-y-6">
                                {/* Name */}
                                <div>
                                    <Label htmlFor="name">
                                        Name <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="name"
                                        value={formData.name}
                                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                        placeholder="Rule name"
                                        required
                                    />
                                    {errors.name && <p className="text-sm text-red-500 mt-1">{errors.name}</p>}
                                </div>

                                {/* Description */}
                                <div>
                                    <Label htmlFor="description">Description</Label>
                                    <Textarea
                                        id="description"
                                        value={formData.description}
                                        onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                        placeholder="Rule description"
                                        rows={3}
                                    />
                                    {errors.description && <p className="text-sm text-red-500 mt-1">{errors.description}</p>}
                                </div>

                                {/* Target and Action */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {/* Target */}
                                    <div>
                                        <Label htmlFor="target">
                                            Target <span className="text-red-500">*</span>
                                        </Label>
                                        <select
                                            id="target"
                                            value={formData.target}
                                            onChange={(e) => setFormData({ ...formData, target: e.target.value as 'campaign' | 'ad_set' })}
                                            className="h-10 w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300 dark:focus:border-brand-800"
                                            required
                                        >
                                            <option value="campaign">Campaign</option>
                                            <option value="ad_set">Ad Set</option>
                                        </select>
                                        {errors.target && <p className="text-sm text-red-500 mt-1">{errors.target}</p>}
                                    </div>

                                    {/* Action */}
                                    <div>
                                        <Label htmlFor="action">
                                            Action <span className="text-red-500">*</span>
                                        </Label>
                                        <select
                                            id="action"
                                            value={formData.action}
                                            onChange={(e) => setFormData({ ...formData, action: e.target.value })}
                                            className="h-10 w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300 dark:focus:border-brand-800"
                                            required
                                        >
                                            <option value="increase_budget_fixed">Increase Daily Budget By Fixed Amount</option>
                                            <option value="decrease_budget_fixed">Decrease Daily Budget By Fixed Amount</option>
                                            <option value="increase_budget_percentage">Increase Daily Budget By Percentage</option>
                                            <option value="decrease_budget_percentage">Decrease Daily Budget By Percentage</option>
                                        </select>
                                        {errors.action && <p className="text-sm text-red-500 mt-1">{errors.action}</p>}
                                    </div>
                                </div>

                                {/* Action Value */}
                                <div>
                                    <Label htmlFor="action_value">
                                        {formData.action.includes('percentage') ? 'Percentage (%)' : 'Amount'}
                                    </Label>
                                    <Input
                                        id="action_value"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={formData.action_value}
                                        onChange={(e) => setFormData({ ...formData, action_value: e.target.value })}
                                        placeholder={formData.action.includes('percentage') ? 'e.g., 10' : 'e.g., 100'}
                                    />
                                    {errors.action_value && <p className="text-sm text-red-500 mt-1">{errors.action_value}</p>}
                                </div>

                                {/* Conditions */}
                                <div className='border p-4 rounded-2xl bg-gray-50'>
                                    <div className="flex items-center justify-between mb-4">
                                        <Label>Conditions</Label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={addCondition}
                                        >
                                            <Plus className="h-4 w-4 mr-1" />
                                            Add Condition
                                        </Button>
                                    </div>

                                    <div className="flex flex-row gap-5 flex-wrap">

                                        {/* Condition Rows */}
                                        {formData.conditions.map((condition, index) => {
                                            return (
                                                <div
                                                    key={index}
                                                    className="flex w-fit items-center gap-3 px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg"
                                                >
                                                    {/* Where label (only show for first row) */}
                                                    <div className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {index === 0 ? 'Where' : 'Or'}
                                                    </div>

                                                    {/* Metric */}
                                                    <select
                                                        value={condition.metric}
                                                        onChange={(e) => updateCondition(index, 'metric', e.target.value)}
                                                        className="h-9 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20"
                                                        required
                                                    >
                                                        <option value="spend">Spend</option>
                                                        <option value="impressions">Impressions</option>
                                                        <option value="clicks">Clicks</option>
                                                        <option value="sales">Sales</option>
                                                        <option value="roas">ROAS</option>
                                                    </select>

                                                    {/* Operator */}
                                                    <select
                                                        value={condition.operator}
                                                        onChange={(e) => updateCondition(index, 'operator', e.target.value)}
                                                        className="h-9 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20"
                                                        title={startCase(condition.operator)}
                                                        required
                                                    >
                                                        <option value="greater_than">{startCase('greater_than')}</option>
                                                        <option value="less_than">{startCase('less_than')}</option>
                                                        <option value="equal">{startCase('equal')}</option>
                                                        <option value="greater_than_or_equal">{startCase('greater_than_or_equal')}</option>
                                                        <option value="less_than_or_equal">{startCase('less_than_or_equal')}</option>
                                                    </select>

                                                    {/* Value */}
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={condition.value}
                                                        onChange={(e) => updateCondition(index, 'value', e.target.value)}
                                                        placeholder="Value"
                                                        className="h-9"
                                                        required
                                                    />

                                                    {/* Remove button */}
                                                    {formData.conditions.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => removeCondition(index)}
                                                            className="h-9 text-red-500 hover:bg-red-100 dark:hover:bg-red-900 justify-self-end"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                    {errors.conditions && <p className="text-sm text-red-500 mt-2">{errors.conditions}</p>}
                                </div>

                                {/* Status */}
                                <div>
                                    <Label htmlFor="status">Status</Label>
                                    <select
                                        id="status"
                                        value={formData.status}
                                        onChange={(e) => setFormData({ ...formData, status: e.target.value as 'active' | 'paused' })}
                                        className="h-10 w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300 dark:focus:border-brand-800"
                                        required
                                    >
                                        <option value="active">Active</option>
                                        <option value="paused">Paused</option>
                                    </select>
                                    {errors.status && <p className="text-sm text-red-500 mt-1">{errors.status}</p>}
                                </div>

                                {/* Form Actions */}
                                <div className="flex justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-800">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => router.get(`/workspaces/${workspace.slug}/ads-manager/optimization-rules`)}
                                        disabled={loading}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={loading}>
                                        {loading ? 'Saving...' : rule ? 'Update Rule' : 'Create Rule'}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </AdsManagerLayout>
        </AppLayout>
    );
};

export default OptimizationRulesFormPage;
