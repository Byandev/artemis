import AppLayout from '@/layouts/app-layout';
import CsrLayout from '@/layouts/csr-layout';
import { User as UserType } from '@/types';
import { usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';

interface Props {
    children: ReactNode;
}

export default function CsrAwareLayout({ children }: Props) {
    const { auth } = usePage().props as unknown as {
        auth?: { user: UserType };
    };

    if (auth?.user?.is_csr) {
        return <CsrLayout>{children}</CsrLayout>;
    }

    return <AppLayout>{children}</AppLayout>;
}
