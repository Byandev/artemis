import RuleForm from './rule-form';
import { type OptimizationRule, type RuleOptions } from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    rule: OptimizationRule;
    selectedAdAccountIds: string[];
    options: RuleOptions;
}

export default function EditOptimizationRule({
    workspace,
    rule,
    selectedAdAccountIds,
    options,
}: Props) {
    return (
        <RuleForm
            mode="edit"
            workspace={workspace}
            rule={rule}
            selectedAdAccountIds={selectedAdAccountIds}
            options={options}
        />
    );
}
