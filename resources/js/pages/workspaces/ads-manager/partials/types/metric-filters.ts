export interface MetricFilter {
    metric: string;
    operator: string;
    value: string;
}

export interface FormState {
    metric: string;
    operator: string;
    value: string;
}

export const OPERATOR_OPTIONS = [
    { value: 'greater_than', label: 'is greater than', symbol: '>' },
    { value: 'less_than', label: 'is less than', symbol: '<' },
    { value: 'equal', label: 'is equal to', symbol: '=' },
    { value: 'greater_than_or_equal', label: 'is ≥', symbol: '≥' },
    { value: 'less_than_or_equal', label: 'is ≤', symbol: '≤' },
];

export const getOperatorSymbol = (operator: string): string => {
    const option = OPERATOR_OPTIONS.find(opt => opt.value === operator);
    return option?.symbol || operator;
};
