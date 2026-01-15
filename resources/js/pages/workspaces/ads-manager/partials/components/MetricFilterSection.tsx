import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';
import { type FormState, type MetricFilter } from '../types/metric-filters';
import { MetricFilterChips } from './MetricFilterChips';
import { MetricFilterForm } from './MetricFilterForm';

interface MetricFilterSectionProps {
    selectedMetrics: string[];
    metricFilters: MetricFilter[];
    formState: FormState;
    isFormOpen: boolean;
    editingIndex: number | null;
    onFormStateChange: (state: FormState) => void;
    onOpenForm: () => void;
    onApplyFilter: () => void;
    onCancelFilter: () => void;
    onEditFilter: (index: number) => void;
    onSaveEdit: () => void;
    onCancelEdit: () => void;
    onRemoveFilter: (index: number) => void;
}

export const MetricFilterSection = ({
    selectedMetrics,
    metricFilters,
    formState,
    isFormOpen,
    editingIndex,
    onFormStateChange,
    onOpenForm,
    onApplyFilter,
    onCancelFilter,
    onEditFilter,
    onSaveEdit,
    onCancelEdit,
    onRemoveFilter,
}: MetricFilterSectionProps) => {
    if (selectedMetrics.length === 0) return null;

    return (
        <div className="border-t border-gray-200 pt-3 space-y-3">
            {editingIndex !== null ? (
                <MetricFilterForm
                    formState={formState}
                    selectedMetrics={selectedMetrics}
                    onFormStateChange={onFormStateChange}
                    onApply={onSaveEdit}
                    onCancel={onCancelEdit}
                    variant="edit"
                />
            ) : !isFormOpen ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onOpenForm}
                    className="h-8"
                >
                    <Plus className="h-3.5 w-3.5 mr-2" />
                    Add Filter
                </Button>
            ) : (
                <MetricFilterForm
                    formState={formState}
                    selectedMetrics={selectedMetrics}
                    onFormStateChange={onFormStateChange}
                    onApply={onApplyFilter}
                    onCancel={onCancelFilter}
                    variant="add"
                />
            )}

            <MetricFilterChips
                filters={metricFilters}
                onEdit={onEditFilter}
                onRemove={onRemoveFilter}
            />
        </div>
    );
};
