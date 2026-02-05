import { Page } from '@/types/models/Page';
import { Workspace } from '@/types/models/Workspace';
import { EntityFilter } from '@/components/filters/EntityFilter';

const PageFilter = ({ workspace }: { workspace: Workspace }) => {
    return <EntityFilter<Page>
        workspace={workspace}
        endpoint={'/pages'}
        getId={(p) => p.id}
        getLabel={(p) => p.name}
    />
}

export default PageFilter;
