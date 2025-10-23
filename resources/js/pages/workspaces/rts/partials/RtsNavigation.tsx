import { Workspace } from '@/types/models/Workspace';
import { Link } from '@inertiajs/react';

const RtsNavigation = ({workspace}: {workspace: Workspace}) => {
    return <div>
        <Link href={`/workspaces/my-workspace/rts/analytics`}>Analytics</Link>
        <Link href={`/workspaces/my-workspace/rts/for-delivery-today`}>For Delivery today</Link>
        <Link href={`/workspaces/my-workspace/rts/parcel-journey-notifications`}>Parcel Updates</Link>
    </div>
}

export default RtsNavigation;
