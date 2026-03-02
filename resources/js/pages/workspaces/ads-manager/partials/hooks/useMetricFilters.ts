import { useState } from 'react';
import { type FormState, type MetricFilter } from '../types/metric-filters';

const INITIAL_FORM_STATE: FormState = {
    metric: '',
    operator: 'greater_than',
    value: '',
};

export const useMetricFilters = (
    metricFilters: MetricFilter[],
    onMetricFiltersChange: (filters: MetricFilter[]) => void
) => {
    const [formState, setFormState] = useState<FormState>(INITIAL_FORM_STATE);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [editingIndex, setEditingIndex] = useState<number | null>(null);

    const resetForm = () => {
        setFormState(INITIAL_FORM_STATE);
        setIsFormOpen(false);
        setEditingIndex(null);
    };

    const handleApplyFilter = () => {
        if (formState.metric && formState.value) {
            onMetricFiltersChange([...metricFilters, { ...formState }]);
            resetForm();
        }
    };

    const handleCancelFilter = () => {
        resetForm();
    };

    const removeMetricFilter = (index: number) => {
        onMetricFiltersChange(metricFilters.filter((_, i) => i !== index));
    };

    const startEditFilter = (index: number) => {
        setEditingIndex(index);
        setFormState(metricFilters[index]);
    };

    const handleSaveEdit = () => {
        if (editingIndex !== null && formState.metric && formState.value) {
            const updated = [...metricFilters];
            updated[editingIndex] = { ...formState };
            onMetricFiltersChange(updated);
            resetForm();
        }
    };

    const handleCancelEdit = () => {
        resetForm();
    };

    return {
        formState,
        setFormState,
        isFormOpen,
        setIsFormOpen,
        editingIndex,
        setEditingIndex,
        handleApplyFilter,
        handleCancelFilter,
        removeMetricFilter,
        startEditFilter,
        handleSaveEdit,
        handleCancelEdit,
        resetForm,
    };
};
