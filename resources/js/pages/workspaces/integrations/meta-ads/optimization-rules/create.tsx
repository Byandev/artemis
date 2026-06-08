import RuleForm from './rule-form';
import { type RuleOptions } from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    options: RuleOptions;
}

export default function CreateOptimizationRule({ workspace, options }: Props) {
    return (
        <RuleForm
            mode="create"
            workspace={workspace}
            rule={null}
            selectedAdAccountIds={[]}
            options={options}
        />
    );
}
