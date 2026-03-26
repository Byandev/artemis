import PageHeader from '@/components/common/PageHeader'
import { Workspace } from '@/types/models/Workspace'
import { type PropsWithChildren } from 'react'
import RtsNavigation from './RtsNavigation'

interface RTSManagementLayoutProps {
    workspace: Workspace
}

const RTSManagementLayout = ({ workspace, children }: PropsWithChildren<RTSManagementLayoutProps>) => {
    return (
        <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
            <PageHeader title="RTS Management" description="Track and manage return-to-sender orders across your workspace" />

            <div className="">
                <RtsNavigation workspace={workspace} />

                <div className="pt-4 dark:border-gray-800">
                    {children}
                </div>
            </div>
        </div>
    )
}

export default RTSManagementLayout