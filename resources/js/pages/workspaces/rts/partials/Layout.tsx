import { Workspace } from '@/types/models/Workspace'
import { type PropsWithChildren } from 'react'
import RtsNavigation from './RtsNavigation'

interface RTSManagementLayoutProps {
    workspace: Workspace
}

const RTSManagementLayout = ({ workspace, children }: PropsWithChildren<RTSManagementLayoutProps>) => {
    return (
        <div className="flex flex-col gap-6 p-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">RTS Management</h1>
                    <p className="text-muted-foreground mt-1">Manage RTS analytics and reports</p>
                </div>
            </div>
            <RtsNavigation workspace={workspace} />
            {children}
        </div>
    )
}

export default RTSManagementLayout